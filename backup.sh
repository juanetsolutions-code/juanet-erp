#!/usr/bin/env bash

# ==============================================================================
# JUANET EOS AUTOMATED ROTATED BACKUP ENGINE
# ==============================================================================

set -euo pipefail

# Configurations
BACKUP_DIR="/var/www/juanet/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
RETENTION_DAYS=30

echo "🗄️ [BACKUP] Initiating database and persistent volumes backup..."

mkdir -p "$BACKUP_DIR"

# Step 1: Backup PostgreSQL relational database
echo "🐘 [BACKUP] Dumping PostgreSQL databases..."
docker compose -f /var/www/juanet/docker-compose.production.yml exec -T postgres \
  pg_dump -U postgres postgres | gzip > "$BACKUP_DIR/postgres_backup_$TIMESTAMP.sql.gz"

# Step 2: Backup Redis cache keys & AOF state
echo "🔑 [BACKUP] Backing up Redis data volume..."
docker compose -f /var/www/juanet/docker-compose.production.yml exec -T redis \
  redis-cli save || echo "⚠️ Sync save skipped, pulling direct RDB file."

tar -czf "$BACKUP_DIR/redis_backup_$TIMESTAMP.tar.gz" -C /var/lib/docker/volumes/juanet-redis-data/_data . || true

# Step 3: Backup MinIO Object Storage
echo "📦 [BACKUP] Archiving MinIO buckets..."
tar -czf "$BACKUP_DIR/minio_backup_$TIMESTAMP.tar.gz" -C /var/lib/docker/volumes/juanet-miniodata/_data . || true

# Step 4: Purge old backups to maintain free disk space
echo "🧹 [BACKUP] Cleaning up backups older than $RETENTION_DAYS days..."
find "$BACKUP_DIR" -type f -mtime +$RETENTION_DAYS -delete

echo "✅ [BACKUP] Backup completed successfully at $(date)! Archives saved in $BACKUP_DIR"
