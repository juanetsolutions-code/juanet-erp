# JUANET EOS: System Health Guide

This document outlines the monitoring parameters, check frequencies, and health indicator logic used by the **JUANET EOS System Health Dashboard** to ensure production-grade uptime.

---

## 🖥️ 1. Real-Time Status Indicators

The admin health dashboard monitors nine vital operational subsystems. Each system status is evaluated against strict performance thresholds:

| Subsystem Component | Monitor Target / Test Action | Normal State | Warning State | Critical State |
| :--- | :--- | :---: | :---: | :---: |
| **Database** | Execution of `SELECT 1` ping. | `< 10ms` | `10ms - 100ms` | Failure / Timeout |
| **Redis Cache** | Read/write of random key. | `< 2ms` | `2ms - 15ms` | Failure / Timeout |
| **Queue Workers** | Daemon queue heartbeat check. | `0 pending` | `> 50 items` | Worker Offline |
| **Storage (S3/Local)** | Readable file existence check. | Successful | — | Unreachable Disk |
| **Notification Gateway** | External SMTP & SMS API ping. | `< 150ms` | `150ms - 500ms` | Gateway Offline |
| **M-PESA Webhook** | Safaricom Daraja API handshake. | Successful | SSL Latency | Gateway Offline |
| **Gemini AI Gateway** | API token quota & ping checks. | Successful | Quota Warning | Out of Quota / Down |
| **Scheduler** | Cron job execution logs. | Executed | Delayed > 5m | Daemon Stalled |
| **Active Workers** | Concurrent background execution. | Active | Low Capacity | Server Deadlock |

---

## 📈 2. Resource Allocation Boundaries

System health is tightly coupled to hardware constraints on host Cloud Run containers or VPS systems:

*   **CPU Utilization**:
    *   *Normal*: `< 65%`
    *   *Warning*: `65% - 85%` (Triggers container autoscaling sequence)
    *   *Critical*: `> 85%` (Triggers DevOps alarm notification)
*   **RAM Allocation**:
    *   *Normal*: `< 75%`
    *   *Warning*: `75% - 90%`
    *   *Critical*: `> 90%` (Potential memory leaks; triggers proactive worker restarts)
*   **Disk Usage**:
    *   *Normal*: `< 70%`
    *   *Warning*: `70% - 85%`
    *   *Critical*: `> 85%` (Triggers automated log rotation and temporary asset purge)

---

## ⚙️ 3. Deployment Identifiers

To guarantee seamless version audits, the health dashboard extracts and shows exact environmental parameters:

*   **Application Version**: `v1.0.0-RC2`
*   **Git Commit SHA**: Dynamically parsed from `.git/refs/heads/main` (e.g. `8f4b1d2e`)
*   **Active Environment**: `production`
*   **Port Binding**: Internal `3000` (routed via SSL proxy)

---

## 🔔 4. Operational Recovery Playbook

If any critical health alarm fires, the system automatically runs self-healing routines before alerting engineers:

1.  **Queue Worker Stale**: System runs `php artisan queue:restart` to spawn fresh, non-leaking PHP-FPM processes.
2.  **Database Connection Surge**: Proactively scales up connection pool sizes and terminates idle sessions.
3.  **Cache Memory Eviction**: Triggers partial eviction of expired analytics keys to free up RAM.
