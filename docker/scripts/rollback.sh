#!/usr/bin/env bash
# ==============================================================================
# Strayt Tix - Production Deployment Rollback Utility
# ==============================================================================
# Usage:
#   ./docker/scripts/rollback.sh <PREVIOUS_COMMIT_SHA_OR_IMAGE_TAG> [BACKUP_FILE_PATH]
#
# Examples:
#   # Rollback application containers to previous commit tag:
#   ./docker/scripts/rollback.sh 4c979d854cf4f828a2b535cb9426f34376c2ce5d
#
#   # Rollback containers and restore database from pre-release backup:
#   ./docker/scripts/rollback.sh 4c979d8 /opt/strayttix/backups/db_backup_pre_xxxx.sql.gz
# ==============================================================================
set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/opt/strayttix}"
TARGET_TAG="${1:-}"
DB_RESTORE_FILE="${2:-}"

if [ -z "${TARGET_TAG}" ]; then
    echo "ERROR: Missing target commit SHA or image tag."
    echo "Usage: $0 <TARGET_COMMIT_SHA> [OPTIONAL_DB_BACKUP_FILE]"
    exit 1
fi

cd "${DEPLOY_PATH}"

# Source environment
if [ -f ".env" ]; then
    # shellcheck disable=SC1091
    export $(grep -v '^#' .env | xargs)
fi

REGISTRY="${DOCKER_REGISTRY:-ghcr.io}"
REPO_OWNER="${GITHUB_REPOSITORY_OWNER:-kelvinasonta}"
IMAGE_NAME="${REGISTRY}/${REPO_OWNER}/strayttix-be:${TARGET_TAG}"

echo "=========================================================="
echo " Starting Rollback to: ${IMAGE_NAME}"
echo "=========================================================="

echo "==> 1. Pulling target fallback image..."
docker pull "${IMAGE_NAME}"

# Optional DB Restore
if [ -n "${DB_RESTORE_FILE}" ]; then
    if [ ! -f "${DB_RESTORE_FILE}" ]; then
        echo "ERROR: Database restore file not found: ${DB_RESTORE_FILE}" >&2
        exit 1
    fi
    echo "==> 2. Restoring database from backup: ${DB_RESTORE_FILE}..."
    gunzip -c "${DB_RESTORE_FILE}" | docker compose -f docker-compose.prod.yml exec -T mysql \
        mysql -u root -p"${DB_PASSWORD}" "${DB_DATABASE:-strayttix}"
    echo "Database restore completed."
else
    echo "==> 2. Skipping database rollback (no backup file supplied)."
fi

echo "==> 3. Restarting application containers with target image..."
APP_IMAGE="${IMAGE_NAME}" docker compose -f docker-compose.prod.yml up -d \
    --remove-orphans \
    app queue scheduler nginx

echo "==> 4. Clearing cached application configuration and restarting workers..."
docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache
docker compose -f docker-compose.prod.yml exec -T app php artisan route:cache
docker compose -f docker-compose.prod.yml exec -T queue php artisan queue:restart

echo "==> 5. Verifying health check on /up..."
MAX_RETRIES=10
COUNTER=0
until [ $COUNTER -ge $MAX_RETRIES ]; do
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1/up || echo "000")
    if [ "${HTTP_STATUS}" -eq 200 ]; then
        echo "Health check succeeded (HTTP 200 OK)."
        break
    fi
    echo "Waiting for app readiness... (Status: ${HTTP_STATUS}) [${COUNTER}/${MAX_RETRIES}]"
    COUNTER=$((COUNTER + 1))
    sleep 3
done

if [ $COUNTER -ge $MAX_RETRIES ]; then
    echo "FATAL: Rollback health check failed!" >&2
    exit 1
fi

echo "=========================================================="
echo " Rollback to ${TARGET_TAG} completed successfully!"
echo "=========================================================="

