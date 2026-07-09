# JUANET EOS: Commercial Launch Guide

This guide describes the deployment procedures, environment configurations, and security practices required to transition **JUANET EOS** to a live, commercial production environment.

---

## 🛠️ 1. Production Hosting & Server Requirements

For optimal performance, scalability, and security, the live environment must adhere to the following architecture:

*   **Virtual Private Server (VPS)**:
    *   *Minimum Specs*: 4 Cores CPU, 8GB RAM, 80GB NVMe SSD.
    *   *Operating System*: Ubuntu Server 22.04 LTS (HWE kernel).
*   **Web Control Panel**: CloudPanel VPS platform (v2.x) running Nginx as the front-end reverse proxy.
*   **Database Service**:
    *   *Engine*: PostgreSQL (v15+) or containerized local instance with active SSL.
    *   *Backup*: Auto-scheduled cron backups written to secure external block storage.
*   **Redis Cache Server**: Redis (v7+) running on local memory, secured behind local host bindings.

---

## 🔒 2. Let's Encrypt TLS & Nginx Optimization

Nginx must be configured to enforce strict SSL/TLS parameters to protect sensitive financial data:

*   **SSL Version**: TLS 1.3 only (TLS 1.0, 1.1, and 1.2 disabled for high security).
*   **Let's Encrypt Certificate**: Auto-renewing certificate configured via CloudPanel.
*   **Nginx Security Headers**:
    ```nginx
    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header Content-Security-Policy "default-src 'self' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com;" always;
    ```

---

## ⚙️ 3. Critical Environment Parameters (`.env`)

Verify that the production environment file (`.env`) contains the correct keys and is isolated from unauthorized read permissions (`chmod 600 .env`):

```env
# Core Application Environment Configuration
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:3JvU7P8tM... # Generated using php artisan key:generate
APP_URL=https://juanet.cloud

# Primary PostgreSQL Configuration
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=juanet_production
DB_USERNAME=juanet_db_user
DB_PASSWORD=secure_vps_database_password
DB_SSLMODE=require

# Redis Session & Cache Configuration
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=secure_redis_password

# Safaricom M-PESA Daraja Settings (Digital Payments)
MPESA_ENVIRONMENT=live
MPESA_CONSUMER_KEY=secure_daraja_key
MPESA_CONSUMER_SECRET=secure_daraja_secret
MPESA_SHORTCODE=secure_paybill_number
MPESA_PASSKEY=secure_lipa_na_mpesa_passkey
MPESA_CALLBACK_URL=https://juanet.cloud/api/payments/m-pesa/callback

# Google Gemini API Settings (AI Automation)
GEMINI_API_KEY=secure_google_ai_studio_api_token

# Transactional SMTP mail Delivery (Mailgun)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@mg.juanet.cloud
MAIL_PASSWORD=secure_mailgun_password
MAIL_FROM_ADDRESS="no-reply@juanet.cloud"
MAIL_FROM_NAME="JUANET EOS"
```

---

## 📈 4. Commercial Launch Verification Plan

Before processing the first live financial transaction, DevOps team members must execute the following verifications:

1.  **SSL Validation**: Execute SSL Labs diagnostic checks to confirm a perfect **A+** ranking.
2.  **Database Migration Check**: Run `php artisan migrate --force` to confirm all tables are correctly indexed and secure RLS policies are active.
3.  **Outbox Worker Heartbeat**: Start the Laravel Queue daemon and confirm pending background jobs are processed in sequence.
4.  **M-PESA Webhook Check**: Execute a sandbox STK-Push test from an external device to confirm callback receipt and ledger posting.
