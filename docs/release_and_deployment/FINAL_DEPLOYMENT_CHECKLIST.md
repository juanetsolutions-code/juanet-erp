# Final Production Deployment Checklist (RC1)

This checklist outlines all the final verification and deployment steps required to launch the **JUANET Enterprise SaaS Platform** (JUANET EOS) in a production environment.

---

## 📅 Pre-Deployment Checklist

Before deploying the platform, verify that you have completed the following steps:

- [ ] **GitHub Secrets Configured**: Ensure all production credentials have been added to your GitHub Secrets repository.
- [ ] **DNS Records Active**: Verify your production domain's A/AAAA records point to your host VPS server IP address.
- [ ] **CloudPanel Reverse Proxy Configured**: Confirm that a reverse proxy site is configured on CloudPanel pointing to `http://127.0.0.1:8080`.
- [ ] **Let's Encrypt SSL Active**: Generate and activate an auto-renewing SSL/TLS certificate for your domain on CloudPanel.
- [ ] **PostgreSQL Staging Test**: Run database migration tests against a mock PostgreSQL database to confirm schema stability.

---

## 🚀 Active Deployment Checklist

Follow these steps during your live deployment window:

- [ ] **Step 1: Check in to Staging VPS**: Verify that Docker and Git are installed and up to date on your target VPS.
- [ ] **Step 2: Pull Latest Repository**: Run the automated deployment engine to pull the latest codebase from your production branch.
- [ ] **Step 3: Run Safe Database Migrations**: Execute transaction-guarded migrations on your production database.
- [ ] **Step 4: Verify Live Logs**: Monitor active container logs to ensure services boot successfully:
  ```bash
  docker compose -f docker-compose.production.yml logs -f --tail=100
  ```
- [ ] **Step 5: Run Automated Health Check**: Query the application's health check endpoint to confirm 100% health status:
  ```bash
  curl -I http://127.0.0.1:8080/api/health
  ```

---

## 🏆 Post-Deployment & Handover Checklist

Complete these tasks after a successful deployment:

- [ ] **Verification Smoke Test**: Log in to the production dashboard and verify core features (e.g. CRM pipeline updates, invoice generation, etc.).
- [ ] **Worker Status Inspection**: Confirm queue workers and cron scheduler tasks are processing successfully.
- [ ] **Verify Outbox Deserialization**: Verify that transactional outbox events are published to Redis without failures.
- [ ] **Enable Host Nightly Backup**: Confirm your daily database and persistent storage volume backups are active.
- [ ] **WAF Proxies Enabled**: Verify that your domain's Cloudflare orange-cloud proxy is active to protect against DDoS attacks.
- [ ] **Handover Complete**: Release the **Release Candidate 1 (RC1)** platform to your client and stakeholders!
