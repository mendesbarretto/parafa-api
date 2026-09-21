#!/bin/bash

set -euo pipefail

echo "Deploy da API Parafa CNPJ"

SERVER_USER="${SERVER_USER:-root}"
SERVER_IP="${SERVER_IP:-147.182.248.223}"
SERVER_PATH="${SERVER_PATH:-/home/parafa-api}"
IMAGE_NAME="${IMAGE_NAME:-cnpj-parafa-api}"
IMAGE_TAG="${IMAGE_TAG:-latest}"
HOST_PORT="${HOST_PORT:-8082}"
INTERNAL_NETWORK="${INTERNAL_NETWORK:-cnpj-internal}"
API_MEMORY_LIMIT="${API_MEMORY_LIMIT:-256m}"
API_MEMORY_RESERVATION="${API_MEMORY_RESERVATION:-160m}"
API_CPU_LIMIT="${API_CPU_LIMIT:-0.50}"
WEB_MEMORY_LIMIT="${WEB_MEMORY_LIMIT:-48m}"
WEB_MEMORY_RESERVATION="${WEB_MEMORY_RESERVATION:-24m}"
WEB_CPU_LIMIT="${WEB_CPU_LIMIT:-0.20}"
IMAGE="${IMAGE_NAME}:${IMAGE_TAG}"
ARCHIVE="${IMAGE_NAME}.tar.gz"

cleanup() {
  rm -f "$ARCHIVE"
}
trap cleanup EXIT

echo "Buildando imagem: $IMAGE"
docker build -t "$IMAGE" .

echo "Exportando imagem..."
docker save "$IMAGE" | gzip > "$ARCHIVE"

echo "Enviando arquivos para ${SERVER_USER}@${SERVER_IP}:${SERVER_PATH}..."
ssh "${SERVER_USER}@${SERVER_IP}" "mkdir -p '${SERVER_PATH}/docker'"
scp "$ARCHIVE" docker-compose.yml .env.docker.example "${SERVER_USER}@${SERVER_IP}:${SERVER_PATH}/"
scp docker/nginx.conf "${SERVER_USER}@${SERVER_IP}:${SERVER_PATH}/docker/"
scp -r public "${SERVER_USER}@${SERVER_IP}:${SERVER_PATH}/"

ssh "${SERVER_USER}@${SERVER_IP}" "SERVER_PATH='${SERVER_PATH}' ARCHIVE='${ARCHIVE}' IMAGE_NAME='${IMAGE_NAME}' IMAGE_TAG='${IMAGE_TAG}' HOST_PORT='${HOST_PORT}' INTERNAL_NETWORK='${INTERNAL_NETWORK}' API_MEMORY_LIMIT='${API_MEMORY_LIMIT}' API_MEMORY_RESERVATION='${API_MEMORY_RESERVATION}' API_CPU_LIMIT='${API_CPU_LIMIT}' WEB_MEMORY_LIMIT='${WEB_MEMORY_LIMIT}' WEB_MEMORY_RESERVATION='${WEB_MEMORY_RESERVATION}' WEB_CPU_LIMIT='${WEB_CPU_LIMIT}' bash -s" <<'ENDSSH'
set -euo pipefail
cd "$SERVER_PATH"

if [ ! -f .env ]; then
  echo "Arquivo $SERVER_PATH/.env ausente. Copie .env.docker.example e preencha APP_KEY e DB_* antes do deploy."
  exit 1
fi

docker network inspect "$INTERNAL_NETWORK" >/dev/null 2>&1 || docker network create "$INTERNAL_NETWORK"
docker load < "$ARCHIVE"

if docker compose version >/dev/null 2>&1; then
  COMPOSE="docker compose"
else
  COMPOSE="docker-compose"
fi

HOST_PORT="$HOST_PORT" INTERNAL_NETWORK="$INTERNAL_NETWORK" IMAGE_NAME="$IMAGE_NAME" IMAGE_TAG="$IMAGE_TAG" \
API_MEMORY_LIMIT="$API_MEMORY_LIMIT" API_MEMORY_RESERVATION="$API_MEMORY_RESERVATION" API_CPU_LIMIT="$API_CPU_LIMIT" \
WEB_MEMORY_LIMIT="$WEB_MEMORY_LIMIT" WEB_MEMORY_RESERVATION="$WEB_MEMORY_RESERVATION" WEB_CPU_LIMIT="$WEB_CPU_LIMIT" \
  $COMPOSE up -d --no-build --force-recreate
rm -f "$ARCHIVE"
docker image prune -f >/dev/null || true

for attempt in $(seq 1 15); do
  if curl --fail --silent "http://127.0.0.1:${HOST_PORT}/up" >/dev/null; then
    break
  fi
  if [ "$attempt" -eq 15 ]; then
    echo "API CNPJ não respondeu ao health check"
    docker logs --tail 50 cnpj-parafa-api || true
    docker logs --tail 50 cnpj-parafa-api-web || true
    exit 1
  fi
  sleep 2
done

docker ps --filter name=cnpj-parafa-api
docker logs --tail 30 cnpj-parafa-api || true
ENDSSH

echo "Deploy concluído: http://127.0.0.1:${HOST_PORT}/up no servidor"
