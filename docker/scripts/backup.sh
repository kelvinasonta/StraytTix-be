#!/usr/bin/env bash
# ==============================================================================
# Strayt Tix - MySQL Database Backup Utility
# ==============================================================================
set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/opt/strayttix}"
BACKUP_DIR="${DEPLOY_PATH}/backups"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP_FILE="${BACKUP_DIR}/db_backup_${TIMESTAMP}.sql.gz"
RETENTION_DAYS=7

mkdir -p "${BACKUP_DIR}"

echo "==> Starting database backup at ${TIMESTAMP}..."

cd "${DEPLOY_PATH}"

# Ensure environment variables are loaded if .env exists
if [ -f ".env" ]; then
    # shellcheck disable=SC1091
    export $(grep -v '^#' .env | xargs)
fi

DB_USER="${DB_USERNAME:-strayttix_user}"
DB_PASS="${DB_PASSWORD:-}"
DB_NAME="${DB_DATABASE:-strayttix}"

if [ -z "${DB_PASS}" ]; then
    echo "ERROR: DB_PASSWORD is not set in environment or .env" >&2
    exit 1
fi

# Execute mysqldump inside the container and compress with gzip
docker compose -f docker-compose.prod.yml exec -T mysql \
    mysqldump \
        -u root \
        -p"${DB_PASS}" \
        --single-transaction \
        --quick \
        --routines \
        --triggers \
        "${DB_NAME}" | gzip > "${BACKUP_FILE}"

# Verify backup size
BACKUP_SIZE=$(stat -c%s "${BACKUP_FILE}" 2>/dev/null || stat -f%z "${BACKUP_FILE}" 2>/dev/null || echo 0)

if [ "${BACKUP_SIZE}" -lt 100 ]; then
    echo "ERROR: Backup file is empty or too small (${BACKUP_SIZE} bytes). Backup failed." >&2
    rm -f "${BACKUP_FILE}"
    exit 1
fi

echo "==> Backup successfully created: ${BACKUP_FILE} (${BACKUP_SIZE} bytes)"

# Clean up older backups beyond retention window
echo "==> Pruning backups older than ${RETENTION_DAYS} days..."
find "${BACKUP_DIR}" -name "db_backup_*.sql.gz" -mtime +"${RETENTION_DAYS}" -exec rm -f {} + || true

echo "==> Backup operation completed."

