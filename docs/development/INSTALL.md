# Installation & Local Environment Setup Guide

This guide provides step-by-step instructions for installing and running the **JUANET Enterprise SaaS Platform** on your local workstation. It covers both Docker and Manual installation methods.

---

## 💻 Method 1: The Dockerized Workflow (Recommended)

Docker Compose automates the provisioning of PHP, Node, PostgreSQL, Redis, Mailpit, and MinIO.

### Prerequisites
*   [Docker Desktop](https://www.docker.com/products/docker-desktop) (v24.x or newer) installed and running.
*   Terminal command accessibility (`docker` and `docker compose`).

### Step 1: Clone the Codebase
```bash
git clone https://github.com/juanetsolutions-code/juanet-erp.git juanet-platform
cd juanet-platform
```

### Step 2: Configure Local Environment File
Duplicate the provided environment template:
```bash
cp .env.example .env
```
The default environment values in `.env.example` are pre-tuned for Docker Compose. No initial changes are required to boot the application.

### Step 3: Spin Up Containers
Launch all services in the background:
```bash
docker compose up -d
```
This starts:
1.  `laravel.test` — The PHP 8.4 Laravel 12 application container (`http://localhost:8080`)
2.  `vite` — The React asset development compiler (`http://localhost:3000`)
3.  `postgres` — The PostgreSQL 15 database instance (`port 5432`)
4.  `redis` — High-performance Redis caching, sessions, and queue store (`port 6379`)
5.  `minio` — S3-compatible local object storage server (`port 9000` / Console on `9001`)
6.  `mailpit` — SMTP Mock Mail Trap testing console (`port 1025` / Web UI on `8025`)

### Step 4: Run Application Initializers
Once containers are online, execute standard database setup inside the running Laravel container:
```bash
# Install PHP Composer packages
docker compose exec laravel.test composer install

# Generate application security key
docker compose exec laravel.test php artisan key:generate

# Build database schema and seed mock demo records
docker compose exec laravel.test php artisan migrate --seed

# Establish storage engine symlinks
docker compose exec laravel.test php artisan storage:link

# Install JavaScript dependencies
docker compose exec laravel.test npm install
```

### Step 5: Open the Application
*   **Frontend Dashboard UI**: Open [http://localhost:3000](http://localhost:3000)
*   **Backend API Endpoint**: Access [http://localhost:8080/api](http://localhost:8080/api)
*   **Mailbox Interface**: Open [http://localhost:8025](http://localhost:8025)

---

## ⚙️ Method 2: Manual Local Installation

Follow these instructions to run the application directly on your local system without containerization.

### Local Prerequisites
1.  **PHP**: Version `8.4.x` or newer.
    *   *Required Extensions*: `openssl`, `pdo_pgsql`, `mbstring`, `tokenizer`, `xml`, `gd`, `zip`, `redis`.
2.  **Node.js & npm**: `v20.x` or newer.
3.  **PostgreSQL**: `v15.x` or newer.
4.  **Redis**: Server running on default port `6379`.

### Step 1: Install PHP Dependencies
Using Composer, install PHP vendor packages:
```bash
composer install --no-interaction --prefer-dist --optimize-autoloader
```

### Step 2: Install Frontend Dependencies
Download and extract node modules:
```bash
npm install
```

### Step 3: Configure Database & .env
Create your local environment configuration:
```bash
cp .env.example .env
```
Open `.env` and configure your database connection parameters:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=juanet_platform
DB_USERNAME=your_postgres_username
DB_PASSWORD=your_postgres_password
```

Create a database named `juanet_platform` on your local PostgreSQL cluster:
```sql
CREATE DATABASE juanet_platform;
```

### Step 4: Run Initializers
```bash
# Generate the Laravel APP_KEY
php artisan key:generate

# Link the public storage directory
php artisan storage:link

# Run migrations and seed tables with default configurations
php artisan migrate --seed
```

### Step 5: Run the Development Servers
In separate terminal sessions, launch the following background processes:

#### Session A: Laravel PHP Server
```bash
php artisan serve --port=8080
```

#### Session B: Vite Frontend Dev Server
```bash
npm run dev
```

#### Session C: Async Queue Workers
```bash
php artisan queue:work redis --queue=high,default,low --tries=3
```

#### Session D: Background Task Scheduler
```bash
php artisan schedule:work
```

Open [http://localhost:3000](http://localhost:3000) to view the running dashboard.

---

## 🛠 Post-Install Checklist & Verifications

To verify that your installation was successful and all sub-systems are operating correctly, perform the following checks:

### 1. Database Table Auditing
Ensure that your database table schemas built correctly:
```bash
php artisan db:show
```

### 2. Verify Object Storage Link
Verify that local files are accessible under public directory rules:
```bash
# Create a test file and check that the symlink routes it correctly
touch storage/app/public/test_connection.txt
ls public/storage/test_connection.txt
```

### 3. Run Automated Core Tests
Run the PHP test suite to verify system integrity and financial ledger calculations:
```bash
php artisan test
```
All tests should pass without errors.
