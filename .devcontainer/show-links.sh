#!/usr/bin/env bash
# Prints the demo links, or what the demo is doing if it is not ready yet.
if [ -f /tmp/opopo-demo.url ] && curl -fsS -o /dev/null "http://localhost:8000/up"; then
    url=$(cat /tmp/opopo-demo.url)
    echo
    echo "=============================================="
    echo " OPOPO demo is running"
    echo "   Agency CRM:        $url/crm"
    echo "   Freelancer portal: $url/portal"
    echo "   Password for every demo login: Demo-Agency-2026"
    echo "=============================================="
else
    echo "OPOPO demo is not ready yet. Progress so far (last 15 lines of /tmp/opopo-demo.log):"
    tail -n 15 /tmp/opopo-demo.log 2>/dev/null || echo "(not started; run: bash .devcontainer/start-demo.sh)"
    echo
    echo "Run 'bash .devcontainer/show-links.sh' again in a minute."
fi
