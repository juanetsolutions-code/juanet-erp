#!/usr/bin/env bash

# ==============================================================================
# JUANET EOS AUTOMATED FAILURE ROLLBACK ENGINE
# ==============================================================================

set -euo pipefail

APP_DIR="/var/www/juanet"

echo "⚠️ [ROLLBACK] WARNING: Initiating automated rollback sequence..."

cd "$APP_DIR"

# Step 1: Restore environment backups
if [ -f .env.bak ]; then
    echo "💾 [ROLLBACK] Restoring previous configuration backup..."
    mv .env.bak .env
else
    echo "   -> No configuration backup (.env.bak) found. Continuing..."
fi

# Step 2: Rollback the git index to previous state
echo "📥 [ROLLBACK] Reverting git index to the previous stable state..."
git reset --hard HEAD@{1}

# Step 3: Re-recreate previous container builds
echo "🐳 [ROLLBACK] Rebuilding and spinning up previous container images..."
docker compose -f docker-compose.production.yml up -d --build --remove-orphans

# Step 4: Run database migration rollback (if safe and supported)
echo "🗄️ [ROLLBACK] Reverting last database migration batch..."
docker compose -f docker-compose.production.yml exec -T app php artisan migrate:rollback --step=1 --force || echo "⚠️ Migration rollback bypassed or failed. Review manually."

# Step 5: Flush and clear optimized configurations
echo "⚙️ [ROLLBACK] Purging invalid performance caches..."
docker compose -f docker-compose.production.yml exec -T app php artisan config:clear
docker compose -f docker-compose.production.yml exec -T app php artisan route:clear
docker compose -f docker-compose.production.yml exec -T app php artisan view:clear
docker compose -f docker-compose.production.yml exec -T app php artisan event:clear

# Step 6: Restart workers and scheduler
echo "🔄 [ROLLBACK] Restarting queue daemon and event bus channels..."
docker compose -f docker-compose.production.yml exec -T app php artisan queue:restart

echo "⭐ [ROLLBACK] Rollback sequence completed! Checking manual logs is recommended."
