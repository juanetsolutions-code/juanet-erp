#!/usr/bin/env bash

# ==============================================================================
# JUANET EOS AUTOMATED PRODUCTION DEPLOYMENT & VERIFICATION ENGINE
# ==============================================================================

set -euo pipefail

# Configurations
APP_DIR="/var/www/juanet"
HEALTH_CHECK_URL="http://127.0.0.1:8080/api/health"
MAX_ATTEMPTS=6
SLEEP_SECONDS=5

echo "🚀 [DEPLOY] Initiating deployment sequence for JUANET Enterprise SaaS..."

# Step 1: Navigate to application directory
cd "$APP_DIR"

# Step 2: Backup environment files and configurations
echo "💾 [DEPLOY] Backing up current configurations..."
cp .env .env.bak || true

# Step 3: Pull latest codebase from main branch
echo "📥 [DEPLOY] Fetching latest changes from GitHub..."
git fetch origin main
git reset --hard origin/main

# Step 4: Build or update the Docker Images
echo "🐳 [DEPLOY] Building production container images..."
docker compose -f docker-compose.production.yml build --pull

# Step 5: Start containers in detached mode
echo "⚡ [DEPLOY] Spinning up production containers..."
docker compose -f docker-compose.production.yml up -d --remove-orphans

# Step 6: Wait briefly for services to stabilize
echo "⏳ [DEPLOY] Waiting for core databases and containers to stabilize..."
sleep 10

# Step 7: Run safe, transaction-guarded database migrations
echo "🗄️ [DEPLOY] Executing secure database migrations..."
docker compose -f docker-compose.production.yml exec -T app php artisan migrate --force

# Step 8: Clear and optimize application cache
echo "⚙️ [DEPLOY] Refreshing application performance caches..."
docker compose -f docker-compose.production.yml exec -T app php artisan config:cache
docker compose -f docker-compose.production.yml exec -T app php artisan route:cache
docker compose -f docker-compose.production.yml exec -T app php artisan view:cache
docker compose -f docker-compose.production.yml exec -T app php artisan event:cache

# Step 9: Restart asynchronous Queue Workers gracefully
echo "🔄 [DEPLOY] Gracefully restarting high and default priority queue workers..."
docker compose -f docker-compose.production.yml exec -T app php artisan queue:restart

# Step 10: Perform automated health verification check
echo "🔍 [DEPLOY] Running automated HTTP health checks on endpoint: $HEALTH_CHECK_URL"

attempt=1
success=false

while [ $attempt -le $MAX_ATTEMPTS ]; do
    echo "   -> Health Check Attempt $attempt/$MAX_ATTEMPTS..."
    STATUS_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$HEALTH_CHECK_URL" || echo "000")
    
    if [ "$STATUS_CODE" -eq 200 ]; then
        echo "✅ [DEPLOY] System reports 100% HEALTHY! Status Code: $STATUS_CODE"
        success=true
        break
    else
        echo "   ⚠️ Warning: System returned status code $STATUS_CODE. Retrying in $SLEEP_SECONDS seconds..."
        sleep $SLEEP_SECONDS
        attempt=$((attempt + 1))
    fi
done

if [ "$success" = false ]; then
    echo "❌ [DEPLOY-FAILURE] Health check failed after $MAX_ATTEMPTS attempts. Triggering automated rollback!"
    ./rollback.sh
    exit 1
fi

# Clean up backups on success
rm -f .env.bak
echo "🏆 [DEPLOY] Deployment completed successfully! JUANET EOS is now LIVE!"
