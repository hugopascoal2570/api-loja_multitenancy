#!/bin/bash
#
# Script de backup do banco de dados MySQL via Docker
# Uso: ./scripts/backup.sh [--compress] [--keep=7]
#

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="${PROJECT_DIR}/backups"

# Carrega variaveis do .env
if [ -f "${PROJECT_DIR}/.env" ]; then
    DB_DATABASE=$(grep -E '^DB_DATABASE=' "${PROJECT_DIR}/.env" | cut -d '=' -f2)
    DB_USERNAME=$(grep -E '^DB_USERNAME=' "${PROJECT_DIR}/.env" | cut -d '=' -f2)
    DB_PASSWORD=$(grep -E '^DB_PASSWORD=' "${PROJECT_DIR}/.env" | cut -d '=' -f2)
fi

DB_DATABASE="${DB_DATABASE:-laravel}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-root}"

COMPRESS=false
KEEP_DAYS=7

# Parse argumentos
for arg in "$@"; do
    case $arg in
        --compress)
            COMPRESS=true
            shift
            ;;
        --keep=*)
            KEEP_DAYS="${arg#*=}"
            shift
            ;;
    esac
done

# Encontra o container MySQL
CONTAINER=$(docker ps --format '{{.Names}}' | grep -i -E 'db|mysql' | head -1)

if [ -z "$CONTAINER" ]; then
    echo "ERRO: Container MySQL nao encontrado. Verifique se o Docker esta rodando."
    exit 1
fi

# Cria diretorio de backups
mkdir -p "$BACKUP_DIR"

TIMESTAMP=$(date +%Y-%m-%d_%H-%M-%S)
FILENAME="backup_${DB_DATABASE}_${TIMESTAMP}.sql"
FILEPATH="${BACKUP_DIR}/${FILENAME}"

echo "========================================="
echo "  BACKUP DO BANCO DE DADOS"
echo "========================================="
echo "Banco:     ${DB_DATABASE}"
echo "Container: ${CONTAINER}"
echo "Data:      $(date '+%d/%m/%Y %H:%M:%S')"
echo "-----------------------------------------"

START_TIME=$(date +%s)

if [ "$COMPRESS" = true ]; then
    FILEPATH="${FILEPATH}.gz"
    FILENAME="${FILENAME}.gz"
    docker exec "$CONTAINER" mysqldump \
        -u"$DB_USERNAME" \
        -p"$DB_PASSWORD" \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        "$DB_DATABASE" 2>/dev/null | gzip > "$FILEPATH"
else
    docker exec "$CONTAINER" mysqldump \
        -u"$DB_USERNAME" \
        -p"$DB_PASSWORD" \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        "$DB_DATABASE" 2>/dev/null > "$FILEPATH"
fi

END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))
FILESIZE=$(du -h "$FILEPATH" | cut -f1)

echo "Backup concluido!"
echo "Arquivo:  ${FILENAME}"
echo "Tamanho:  ${FILESIZE}"
echo "Duracao:  ${DURATION}s"

# Limpa backups antigos
REMOVED=$(find "$BACKUP_DIR" -name "backup_*.sql*" -mtime +${KEEP_DAYS} -delete -print | wc -l)
if [ "$REMOVED" -gt 0 ]; then
    echo "Removidos ${REMOVED} backup(s) com mais de ${KEEP_DAYS} dias."
fi

echo "========================================="
