# JUANET EOS: Go-Live Checklist

This checklist contains the pre-launch steps, infrastructure verifications, and deployment quality checks required to launch **JUANET EOS** to the public.

---

## 🗂️ 1. Domain & DNS Mappings Checklist

Configure and verify the DNS records at your domain registrar (TTL: `300` seconds for rapid propagation during launch):

- [x] **A Record (Apex Domain)**:
      *   *Host*: `@`
      *   *Target IP*: Your Production VPS IPv4 Address
- [x] **A Record (Subdomains)**:
      *   *Host*: `*.juanet.cloud` (or individual subdomains: `console`, `api`, `school`)
      *   *Target IP*: Your Production VPS IPv4 Address
- [x] **TXT Record (SPF Policy)**:
      *   *Host*: `@`
      *   *Value*: `v=spf1 include:mailgun.org ~all`
- [x] **TXT Record (DKIM Signature)**:
      *   *Host*: `k1._domainkey`
      *   *Value*: Provided by Mailgun to authenticate outgoing transaction receipts.
- [x] **TXT Record (DMARC Policy)**:
      *   *Host*: `_dmarcx`
      *   *Value*: `v=DMARC1; p=reject; rua=mailto:dmarc@juanet.cloud`

---

## 🔒 2. Operating System & Firewall Hardening

Before opening web ports, lock down the host VPS using the Ubuntu Uncomplicated Firewall (`ufw`):

- [x] **Disable Root SSH Logins**: Set `PermitRootLogin no` in `/etc/ssh/sshd_config` and enforce SSH keypair authentication only.
- [x] **Set Default UFW Policies**:
      ```bash
      ufw default deny incoming
      ufw default allow outgoing
      ```
- [x] **Open Essential Communication Ports**:
      ```bash
      ufw allow 22/tcp     # SSH (Only allow from trusted dev IP addresses)
      ufw allow 80/tcp     # HTTP (Nginx Let's Encrypt validation)
      ufw allow 443/tcp    # HTTPS (Client traffic)
      ufw allow 8443/tcp   # CloudPanel Admin Console Access
      ```
- [x] **Enable Firewall State**: `ufw enable`

---

## 🗄️ 3. Production Database Security Audit

Confirm that the PostgreSQL instance is locked down against external scanning:

- [x] **Disable External IP Bindings**: Ensure `listen_addresses` in `postgresql.conf` is bound strictly to local loops (`127.0.0.1` or internal container network interfaces).
- [x] **Enforce Strong Password Auth**: Configure `/var/lib/pgsql/data/pg_hba.conf` to require `scram-sha-256` authentication for all local connection sockets.
- [x] **Row-Level Security Activation**: Run database audit scripts to verify that `ENABLE ROW LEVEL SECURITY` has been successfully executed on all tenant-scoped tables.

---

## 🚀 4. Deployment Command Sequence

Run the production deployment pipeline inside the host application directory:

```bash
# 1. Pull the certified RC2 code block
git pull origin main

# 2. Install production dependencies
composer install --no-dev --optimize-autoloader
npm ci

# 3. Cache configuration and routing parameters to bypass disk checks
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Run database migrations safely
php artisan migrate --force

# 5. Build optimized front-end assets
npm run build

# 6. Restart queue workers to clear cached state
php artisan queue:restart
```
