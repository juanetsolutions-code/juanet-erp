# Security Audit & Hardening Report (RC1)

This document details the security architecture, data isolation mechanisms, encryption policies, and host firewall configurations in **JUANET EOS**.

---

## 1. Multi-Tenant Data Isolation

Our multi-tenant data isolation architecture provides strong defense-in-depth security:

*   **Global Database Query Scopes**: Laravel's global query scopes automatically append tenant filters to all SQL queries, preventing cross-tenant data access.
*   **Database Row-Level Security (RLS)**: Under production configurations, native PostgreSQL Row-Level Security policies are enabled:
    ```sql
    CREATE POLICY tenant_isolation_policy ON client_proposals
        FOR ALL USING (organization_id = auth.current_tenant_id());
    ```
*   **Strict Member Authorization**: Middleware checks enforce membership verification before granting API access to tenant resources.

---

## 2. API Security & Input Validation

*   **Symmetric Encryption Key**: Critical values (such as OAuth tokens, payment passkeys, and API secrets) are encrypted at rest using AES-256-CBC.
*   **Request Validation**: Input fields are strictly validated to prevent common SQL injection, cross-site scripting (XSS), and mass-assignment attacks.
*   **Strict Sanctum Authentication**: All API endpoints are guarded by token validation middleware.

---

## 3. Production Hardening & Network Security

To protect our containerized production infrastructure against external threats:

*   **Fail2ban Brute Force Prevention**: Configure Fail2ban on the host VPS to monitor authentication failures and ban malicious IP addresses:
    ```ini
    [nginx-http-auth]
    enabled = true
    filter = nginx-http-auth
    port = http,https
    logpath = /var/log/nginx/error.log
    maxretry = 5
    ```
*   **UFW Firewall Rules**: Restrict external access on the host VPS to essential ports:
    ```bash
    sudo ufw default deny incoming
    sudo ufw default allow outgoing
    sudo ufw allow 22/tcp      # Secure SSH
    sudo ufw allow 80/tcp      # HTTP
    sudo ufw allow 443/tcp     # HTTPS
    sudo ufw allow 8443/tcp    # Secure CloudPanel access
    sudo ufw enable
    ```
*   **Cloudflare Proxy Protection**: Proxies traffic through Cloudflare's Web Application Firewall (WAF) to hide your origin server's IP address and mitigate DDoS attacks.
*   **Non-Root Container Runtime**: The production container runs under a dedicated, non-root user (`juanet`), preventing container breakout vulnerability risks.
