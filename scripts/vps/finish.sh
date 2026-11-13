#!/usr/bin/env bash
# Run AFTER DNS points to the VPS. Does not disable certificate verification.
set -Eeuo pipefail
[[ $EUID == 0 ]] || exit 1
python3 - <<'PY'
import socket
ips={x[4][0] for x in socket.getaddrinfo('dandomi.tech',80)}
if ips!={'155.212.217.163'}:
 raise SystemExit('DNS not ready (including AAAA): '+', '.join(sorted(ips)))
PY
certbot --nginx --non-interactive --agree-tos --register-unsafely-without-email --redirect -d dandomi.tech
nginx -t
systemctl reload nginx
curl --fail --show-error --silent https://dandomi.tech/api/index.php?action=version
curl --fail --show-error --silent https://dandomi.tech/.well-known/oauth-authorization-server/ | python3 -c 'import json,sys;assert json.load(sys.stdin)["issuer"]=="https://dandomi.tech";print("\nHTTPS and OAuth discovery OK")'
systemctl list-timers --all | grep -i certbot || true
echo 'HTTPS ready. MCP endpoint: https://dandomi.tech/api/mcp.php'
echo 'Keep the old host until the owner login, dated comments and research documents have been checked on the VPS.'
