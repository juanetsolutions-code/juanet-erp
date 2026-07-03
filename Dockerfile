# ==========================================
# Stage 1: Base PHP Environment
# ==========================================
FROM php:8.4-fpm-alpine AS base

# Install official extension installer tool
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Install essential runtime tools and required PHP extensions
RUN chmod +x /usr/local/bin/install-php-extensions && \
    apk add --no-cache \
        bash \
        curl \
        git \
        unzip \
        tzdata && \
    install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        redis \
        zip

# Copy latest Composer binary
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Configure dedicated non-root application user using Alpine-native commands
RUN addgroup -g 1000 -S juanet && \
    adduser -G www-data -u 1000 -h /home/juanet -D -S juanet && \
    mkdir -p /home/juanet/.composer && \
    chown -R juanet:www-data /home/juanet

WORKDIR /var/www/html


# ==========================================
# Stage 2: Development Environment Target
# ==========================================
FROM base AS dev

# Install Node.js and NPM exclusively in the dev environment for assets building
RUN apk add --no-cache nodejs npm

USER juanet


# ==========================================
# Stage 3: Dependencies Build Layer
# ==========================================
FROM base AS vendor-builder

# Copy dependency manifests first for optimum layer caching
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader


# ==========================================
# Stage 4: Frontend Assets Build Layer
# ==========================================
FROM node:22-alpine AS assets-builder

WORKDIR /app

# Copy dependency files and compile frontend assets using Node
COPY package.json package-lock.json vite.config.js tailwind.config.js ./
COPY resources/ ./resources/

RUN npm ci && npm run build


# ==========================================
# Stage 5: Production Target Environment
# ==========================================
FROM base AS production

# Enforce secure production configurations
ENV APP_ENV=production
ENV APP_DEBUG=false

# Self-contained performance-tuned Opcache configuration
RUN { \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.fast_shutdown=1'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Safely establish directory structures before setting ownership permissions
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/app/public \
        bootstrap/cache

# Copy application files and map ownership to non-root application user
COPY --chown=juanet:www-data . .

# Merge vendor dependencies and compiled assets from build stages
COPY --from=vendor-builder --chown=juanet:www-data /var/www/html/vendor ./vendor
COPY --from=assets-builder --chown=juanet:www-data /app/public/build ./public/build

# Restructure permissions securely for critical runtime directories
RUN chmod -R 775 storage bootstrap/cache

# Execute Laravel optimization commands safely during build-time using dummy fallbacks
RUN APP_KEY=base64:JUANETEnterpriseSaaSPlatformKeyLocalDev84= \
    DB_CONNECTION=sqlite \
    DB_DATABASE=:memory: \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan event:cache

# Enforce strict non-root execution
USER juanet

EXPOSE 9000

# Alpine-native healthcheck checking FPM master process state
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD pidof php-fpm || exit 1

CMD ["php-fpm"]

