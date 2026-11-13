"""Read-only checks against the published PHP host, including OAuth discovery."""
import json
import time
import urllib.request

ORIGIN = 'https://dandomi.tech'


def fetch(path, payload=None):
    headers = {'Cache-Control': 'no-cache', 'Accept': 'application/json, text/event-stream'}
    data = None
    if payload is not None:
        data = json.dumps(payload).encode()
        headers['Content-Type'] = 'application/json'
    with urllib.request.urlopen(urllib.request.Request(ORIGIN + path, data=data, headers=headers), timeout=25) as response:
        return response.headers, response.read().decode()


for attempt in range(3):
    try:
        headers, context = fetch('/ai-context.php?verify=' + str(time.time_ns()))
        assert 'text/plain' in headers.get('Content-Type', '')
        assert headers.get('Cache-Control') == 'no-store'
        assert headers.get('X-Atlas-Revision', '').isdigit()
        assert 'Версия:' in context and 'get_project_context' in context
        _, raw = fetch('/.well-known/oauth-authorization-server')
        metadata = json.loads(raw)
        assert metadata['issuer'] == ORIGIN
        assert 'S256' in metadata['code_challenge_methods_supported']
        _, raw = fetch('/.well-known/oauth-protected-resource/api/mcp.php')
        assert json.loads(raw)['resource'] == ORIGIN + '/api/mcp.php'
        _, raw = fetch('/api/mcp.php', {'jsonrpc': '2.0', 'id': 1, 'method': 'initialize', 'params': {'protocolVersion': '2025-11-25', 'capabilities': {}, 'clientInfo': {'name': 'deployment-check', 'version': '1'}}})
        assert json.loads(raw)['result']['serverInfo']['name'] == 'windows11x-atlas'
        _, raw = fetch('/api/mcp.php', {'jsonrpc': '2.0', 'id': 2, 'method': 'tools/list'})
        assert any(t['name'] == 'add_comment' for t in json.loads(raw)['result']['tools'])
        print('PASS: published context revision ' + headers['X-Atlas-Revision'] + ', OAuth discovery, MCP initialize and tools/list')
        break
    except Exception:
        if attempt == 2:
            raise
        time.sleep(5)
