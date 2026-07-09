# JUANET EOS: SYSTEM DEPLOYMENT, ARCHITECTURE, & CI/CD HANDBOOK

This handbook serves as the definitive reference manual for deploying, monitoring, scaling, and maintaining the **JUANET Enterprise SaaS Platform (JUANET EOS)** in production. It describes how the application containers integrate with an Ubuntu VPS managed by **CloudPanel** and automated via **GitHub Actions**.

---

## 1. Complete Architecture Diagram

JUANET EOS utilizes a Domain-Driven Design (DDD) layout coupled with a clean, decoupled Presentation layer.

```
+-----------------------------------------------------------------------------------+
|                                 PRESENTATION LAYER                                |
|                        Vite-compiled React 19 Frontend Client                      |
+-----------------------------------------┬-----------------------------------------+
                                          │ HTTP Requests / API JSON Calls
                                          ▼
+-----------------------------------------------------------------------------------+
|                                 REVERSE PROXY LAYER                               |
|        CloudPanel Nginx (SSL & DNS)  ==> Local Docker Nginx (Port 8080)           |
+-----------------------------------------┬-----------------------------------------+
                                          │ Proxies to fastcgi://app:9000
                                          ▼
+-----------------------------------------------------------------------------------+
|                               APPLICATION CORE LAYER                              |
|                         Laravel 12 (PHP 8.4-FPM) Engine                           |
|  +---------------------------+---------------------------+---------------------+  |
|  |     CRM Pipeline Domain   |   Immutable Ledger Domain |   Workforce Domain  |  |
|  +---------------------------+---------------------------+---------------------+  |
|  |     Proposal Builder      |   Client Portal Hub       |   Notification Hub  |  |
|  +---------------------------+---------------------------+---------------------+  |
+--------------------┬----------------────┬----------------────┬--------------------+
                     │                    │                    │
                     ▼                    ▼                    ▼
+--------------------+----+ +-------------+----+ +--------------+----+ +--------------+
|   DATABASE PORT    | |   |  CACHE/SESSION | |   | OBJECT STORAGE | |   | EVENT DAEMON |
| PostgreSQL 15 (RLS)| |   | Redis AOF (K/V)| |   | MinIO / S3 API | |   | Queue/Worker |
+-------------------------+ +------------------+ +------------------+ +--------------+
```

---

## 2. Deployment Diagram

This diagram visualizes how CloudPanel on the VPS hosts the network bridge and coordinates containers.

```
+-----------------------------------------------------------------------------------+
|                              UBUNTU VPS HOST SYSTEM                               |
|                                                                                   |
|  +-----------------------------------------------------------------------------+  |
|  |                            CLOUDPANEL PLATFORM                              |  |
|  |  * Site: erp.juanet.co                                                      |  |
|  |  * SSL: Let's Encrypt TLS 1.3 Auto-renew                                    |  |
|  |  * Port Forwarding Proxy Pass: http://127.0.0.1:8080                        |  |
|  +--------------------------------------┬--------------------------------------+  |
|                                         │ Port 8080 Loopback
|                                         ▼
|  +-----------------------------------------------------------------------------+  |
|  |                           DOCKER COMPOSE NETWORK                            |  |
|  |                                                                             |  |
|  |   +-----------------------+              +------------------------------+   |  |
|  |   |    Nginx Container    |─────────────►|    PHP-FPM App Container     |   |  |
|  |   |      (Port 80)        |              |          (Port 9000)         |   |  |
|  |   +-----------------------+              +--------------┬---------------+   |  |
|  |                                                         │                   |  |
|  |              ┌──────────────────────────────────────────┼───────────────┐   |  |
|  |              ▼                                          ▼               ▼   |  |
|  |   +-----------------------+              +------------------+  +--------+---+  |  |
|  |   |  PostgreSQL Database  |              |  Redis Container |  | MinIO  |  |  |  |
|  |   |      (Port 5432)      |              |    (Port 6379)   |  | (9000) |  |  |  |
|  |   +-----------┬-----------+              +--------┬---------+  +----┬---+  |  |
|  +───────────────┼───────────────────────────────────┼─────────────────┼──────────+
|                  │                                   │                 │
|                  ▼                                   ▼                 ▼
|       [ juanet-pgdata Volume ]             [ juanet-redis-data ]  [ juanet-minio ]
|        (Local Disk Database)               (Append Only Log)      (S3-Compatible)
+-----------------------------------------------------------------------------------+
```

---

## 3. Networking Diagram

The secure flow of ingress traffic and network isolation rules:

```
[ User Browser ] ────► [ Cloudflare Edge WAF (SSL Handshake / Proxy ) ]
                                         │
                                         ▼ Public Port 443 (HTTPS)
                              [ VPS Ingress Firewall ]
                                         │
                                         ▼ Decodes Port 443
                               [ CloudPanel Nginx ]
                                         │
                                         ▼ Decrypts TLS & routes to localhost:8080
                            [ Docker bridge Interface ]
                                         │
                                         ▼ Port 80 (Internal HTTP)
                                [ Nginx Container ]
                                         │
                                         ▼ FastCGI (TCP Port 9000)
                             [ PHP-FPM App Container ]
                                   /     │     \
            ┌─────────────────────┘      │      └──────────────────────┐
            ▼ Internal TCP Port 5432     ▼ Internal TCP Port 6379      ▼ Internal TCP Port 9000
    [ PostgreSQL Container ]      [ Redis Container ]          [ MinIO Container ]
```

---

## 4. Folder Structure

### I. Workspace Project Folder Layout
```
/ (Workspace Root)
├── .github/
│   └── workflows/
│       └── deploy.yml          # Continuous Integration and Deployment Actions Workflow
├── app/
│   └── Domain/                 # Bounded DDD Domain Models & Business Logic
├── bootstrap/                  # Framework bootstrapping and initializers
├── config/                     # Core application performance setups
├── database/                   # Migrations, seeders, and relational factories
├── docker/                     # Nginx and PHP base build settings
├── public/                     # Compiled frontend static assets
├── resources/                  # Uncompiled React views, CSS, and component files
├── Dockerfile                  # Multi-target build script
├── DEVELOPMENT_Dockerfile      # Development-optimized environment
├── PRODUCTION_Dockerfile       # Production-optimized environment
├── docker-compose.yml          # Local orchestration engine
├── docker-compose.production.yml # Multi-container production configurations
├── docker-compose.development.yml # Specialized developer compose file
├── deploy.sh                   # Auto-deployment and verification script
├── rollback.sh                 # Zero-downtime failures rollback runner
└── backup.sh                   # Rotated pgsql, Redis, and storage buckets backup
```

### II. Host VPS Production Layout
```
/var/www/
└── juanet/                    # Project Deployment Directory
    ├── .env                   # Live Active Production Environment Secrets
    ├── docker-compose.production.yml # Active Production Containers Compose Config
    ├── deploy.sh              # Runnable Auto-deploy script
    ├── rollback.sh            # Runnable Failure recovery script
    ├── backup.sh              # Executable Backups compiler
    ├── backups/               # Rotated backup dump directory
    └── storage/               # Shared persistent docker volume mountpoint
```

---

## 5. CI/CD Flow Diagram

```
[ Developer Push to main ]
          │
          ▼
┌─────────────────────────┐
│  GitHub Actions Runner  │
├─────────────────────────┤
│ 1. Setup PHP 8.4 & Node │
│ 2. Install Dependencies │
│ 3. Run TSX Linter Tests │
│ 4. Run Laravel Unit     │
│ 5. Compile React Views  │
│ 6. Verify Pint Format   │
└─────────┬───────────────┘
          │ (All Checks Pass Green)
          ▼
┌─────────────────────────┐
│     SSH VPS Gateway     │
├─────────────────────────┤
│ 1. Git pull codebase    │
│ 2. Build Prod Images    │
│ 3. Restart Containers   │
│ 4. Run Migrations       │
│ 5. Reset Queue Workers  │
└─────────┬───────────────┘
          │
          ▼
┌─────────────────────────┐
│ Automated Health Check  │
├─────────────────────────┤
│ Curl returns HTTP 200?  ├──(Yes: Deploy Success)──► Live Production Online!
└─────────┬───────────────┘
          │ (No / Timeout)
          ▼
┌─────────────────────────┐
│   Automated Rollback    │
├─────────────────────────┤
│ 1. Revert Git HEAD      │
│ 2. Re-create Containers │
│ 3. Rollback Migrations  │
│ 4. Restore Backed .env  │
└─────────────────────────┘
```

---

## 6. Production Deployment Guide

Follow this step-by-step guide to provision and launch JUANET EOS on a new production VPS.

### Step 1: Provision the VPS Server
*   Install **Ubuntu 22.04 LTS** or **24.04 LTS** on your virtual private server.
*   Secure SSH access and disable root password authentication.
*   Install CloudPanel following the official documentation:
    ```bash
    curl -sS https://installer.cloudpanel.io/install.sh | sudo bash
    ```

### Step 2: Install Docker Engine & Compose
Install Docker on the VPS to run the application containers:
```bash
sudo apt-get update
sudo apt-get install -y docker.io docker-compose-plugin
sudo systemctl enable docker
sudo systemctl start docker
```

### Step 3: Configure CloudPanel Site & Reverse Proxy
1.  Log in to the CloudPanel admin interface (port `8443`).
2.  Navigate to **Add Site** and choose **Reverse Proxy**.
3.  Enter your domain name (e.g., `erp.juanet.co`).
4.  Set the **Reverse Proxy URL** target to `http://127.0.0.1:8080`.
5.  Navigate to the site's **SSL/TLS** tab and click **New Let's Encrypt Certificate** to generate a certificate.

### Step 4: Clone the Repository & Configure the Environment
Create the installation directory on the VPS and configure the environment:
```bash
sudo mkdir -p /var/www/juanet
sudo chown -R $USER:$USER /var/www/juanet
cd /var/www/juanet

# Clone the repository
git clone https://github.com/juanetsolutions-code/juanet-erp.git .

# Create your production environment file
cp production.env.example .env
nano .env # Add your secure production passwords and secrets
```

### Step 5: Start the Application Container Stack
Start the production services using Docker Compose:
```bash
# Make deployment and maintenance scripts executable
chmod +x deploy.sh rollback.sh backup.sh

# Run the deployment script to build images, start containers, and run migrations
./deploy.sh
```

---

## 7. Disaster Recovery Guide

This guide defines standard recovery procedures for common system failures.

### Database Schema Corruption Recovery
If the relational database is corrupted or fails to load:
1.  Stop the active container stack immediately:
    ```bash
    docker compose -f docker-compose.production.yml down
    ```
2.  Locate your latest nightly backup in `/var/www/juanet/backups/`.
3.  Restart only the PostgreSQL container:
    ```bash
    docker compose -f docker-compose.production.yml up -d postgres
    ```
4.  Restore the database dump:
    ```bash
    gunzip -c backups/postgres_backup_YYYYMMDD_HHMMSS.sql.gz | docker compose -f docker-compose.production.yml exec -T postgres psql -U postgres -d postgres
    ```
5.  Start the remaining containers:
    ```bash
    docker compose -f docker-compose.production.yml up -d
    ```

### Full Server Migration
To migrate the entire platform to a new VPS:
1.  Run a final backup of the databases and storage volumes on the old server:
    ```bash
    ./backup.sh
    ```
2.  Copy the backup files from `/var/www/juanet/backups/` to the new VPS.
3.  Follow the **Production Deployment Guide** (Section 6) to configure CloudPanel and Docker on the new server.
4.  Restore the PostgreSQL backup and extract the Redis and MinIO volume archives into their corresponding Docker volumes on the new server.
5.  Update your domain's DNS records to point to the new server's IP address.

---

## 8. Rollback Guide

If a deployment fails the automated health checks or introduces critical runtime issues, you can trigger a manual rollback at any time:

```bash
cd /var/www/juanet
./rollback.sh
```

This rollback script will:
1.  Restore the previous configuration backup (`.env.bak`).
2.  Revert the Git repository to the previous stable state.
3.  Rebuild and restart the stable container images.
4.  Roll back the last database migration batch to maintain database integrity.
5.  Flush optimized performance caches and restart the queue workers.

---

## 9. Backup Guide

### Nightly Automated Backup Schedule
To automate backups, configure a cron job to run the `backup.sh` script nightly at 2:00 AM:

```bash
sudo crontab -e
```

Add the following entry:
```cron
0 2 * * * /var/www/juanet/backup.sh >> /var/log/juanet_backup.log 2>&1
```

This script generates compressed, timestamped backups of the PostgreSQL database, Redis keys, and MinIO storage volumes, keeping a rolling 30 days of archives.

---

## 10. Scaling Guide

### I. High Availability Setup
To scale JUANET EOS horizontally to handle higher traffic volumes:

```
                          [ External Load Balancer ]
                           /                      \
                          ▼                        ▼
                 [ App Node 1 (VPS) ]    [ App Node 2 (VPS) ]
                 * Port 8080 (Docker)    * Port 8080 (Docker)
                          \                        /
                           ▼                      ▼
                 +──────────────────────────────────+
                 |       PERSISTENT INFRASTRUCTURE  |
                 |  * Shared PostgreSQL Database     |
                 |  * Shared Redis Cluster (Sessions)|
                 |  * Amazon S3 API Object Storage  |
                 +──────────────────────────────────+
```

1.  **Configure Sticky Sessions**: Ensure the load balancer uses sticky sessions to route users to the same application node.
2.  **Externalize Persistent Databases**: Move PostgreSQL and Redis out of the local Docker volumes to dedicated, managed cloud database clusters.
3.  **Use Amazon S3 for Storage**: Update your storage configuration to use a managed Amazon S3 bucket instead of the local MinIO volume.

### II. Decoupling Microservices
To simplify maintenance and scale components independently, you can decouple heavy workloads into dedicated microservices:
*   **AI Support Processor**: Move support ticket categorization and proposal scoping tasks into a dedicated service.
*   **Financial Report Compiler**: Decouple financial report generation and PDF compiling tasks from the main thread.

---

## 11. Monitoring Guide

To monitor system performance and detect issues early, configure the following metrics:

1.  **System Metric Monitoring**:
    *   **CPU Utilization**: Keep average utilization below 70%.
    *   **Memory Footprint**: Ensure at least 20% of system memory remains free.
    *   **Disk I/O**: Monitor disk read/write operations to prevent storage bottlenecks.
2.  **Application Monitoring**:
    *   **Laravel Log Aggregation**: Configure Logstash or an external log compiler to aggregate errors and warnings.
    *   **Queue Health**: Monitor Redis queue lengths to ensure workers are processing jobs promptly.
    *   **Health Alerts**: Configure automated email or Slack notifications for critical system alerts (e.g., HTTP 500 errors).

---

## 12. Maintenance Guide

Ensure the platform remains secure and optimized by establishing a regular maintenance routine:

*   **Database Optimization**: Run PostgreSQL vacuuming and optimize database tables monthly.
*   **Security Patches**: Run system and package updates quarterly:
    ```bash
    sudo apt update && sudo apt upgrade -y
    ```
*   **System Cleanup**: Prune unused Docker containers, images, and networks quarterly to free up disk space:
    ```bash
    docker system prune -af
    ```

---

## 13. Enterprise Best Practices Review

*   **Strict Logical Tenant Isolation**: Every database table containing tenant data must include an `organization_id` foreign key. Laravel's global query scopes automatically append this check to all queries, preventing cross-tenant data access.
*   **Immutable General Ledger**: All financial transactions are written as immutable double-entry ledger postings. Debits must equal credits for every posting, maintaining database integrity and providing a reliable audit trail.
*   **Transactional Outbox Pattern**: To prevent data inconsistency when dispatching asynchronous events, all events are written to an `event_outbox` table first. A daemon background worker processes this table and publishes events to Redis, ensuring reliable delivery even if the message broker is temporarily offline.
