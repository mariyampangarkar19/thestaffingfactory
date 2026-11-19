#!/usr/bin/env bash
# Builds and starts the OPOPO demo inside a GitHub Codespace. Run again at any time: bash .devcontainer/start-demo.sh
# Everything it does is also written to /tmp/opopo-demo.log.
set -uo pipefail
cd "$(dirname "$0")/.."
exec > >(tee -a /tmp/opopo-demo.log) 2>&1

export DEMO_URL="https://${CODESPACE_NAME:-localhost}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"

echo "== OPOPO demo: starting $(date)"

for i in $(seq 1 60); do
    docker info >/dev/null 2>&1 && break
    echo "Waiting for Docker to start..."
    sleep 2
done
docker info >/dev/null 2>&1 || { echo "FAILED: Docker did not start. Rebuild the codespace (Ctrl+Shift+P, 'Rebuild Container')."; exit 1; }

if ! docker compose -f compose.yaml -f .devcontainer/compose.codespaces.yaml --profile app up -d --build; then
    echo "FAILED: the build stopped. Copy the lines above and send them over."
    exit 1
fi

echo "Building the database and demo data (2-3 minutes the first time)..."
for i in $(seq 1 120); do
    curl -fsS -o /dev/null "http://localhost:8000/up" && break
    sleep 5
done
if ! curl -fsS -o /dev/null "http://localhost:8000/up"; then
    echo "FAILED: the app did not answer. Its last messages:"
    docker compose -f compose.yaml -f .devcontainer/compose.codespaces.yaml --profile app logs --tail 60 app
    exit 1
fi

gh codespace ports visibility 8000:public -c "${CODESPACE_NAME:-}" >/dev/null 2>&1 \
    || echo "Note: make the link public yourself: Ports tab, right-click 8000, Port Visibility, Public."

echo "$DEMO_URL" > /tmp/opopo-demo.url
bash .devcontainer/show-links.sh
