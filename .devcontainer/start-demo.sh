#!/usr/bin/env bash
# Starts the demo agency inside a GitHub Codespace and opens port 8000 to anyone with the link.
set -euo pipefail
cd "$(dirname "$0")/.."

export DEMO_URL="https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"

docker compose -f compose.yaml -f .devcontainer/compose.codespaces.yaml --profile app up -d --build

until curl -fsS -o /dev/null "http://localhost:8000/up"; do
    echo "Waiting for the app to start..."
    sleep 5
done

gh codespace ports visibility 8000:public -c "$CODESPACE_NAME" || echo "Could not make port 8000 public. In the Ports tab, right-click 8000 and choose Port Visibility > Public."

echo
echo "OPOPO demo is running."
echo "  Agency CRM:        ${DEMO_URL}/crm"
echo "  Freelancer portal: ${DEMO_URL}/portal"
