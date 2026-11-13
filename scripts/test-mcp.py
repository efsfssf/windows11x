"""Real PHP HTTP integration: live context, OAuth PKCE and isolated AI writes."""
import base64
import copy
import hashlib
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BIN', 'php')


def check(ok, message):
    if not ok:
        raise AssertionError(message)


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None


class Client:
    def __init__(self, origin):
        self.origin = origin
        self.csrf = ''
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())

    def request(self, path, body=None, form=None, headers=None):
        headers = dict(headers or {})
        if form is not None:
            body = urllib.parse.urlencode(form).encode()
            headers['Content-Type'] = 'application/x-www-form-urlencoded'
        elif body is not None:
            body = json.dumps(body, ensure_ascii=False).encode()
            headers['Content-Type'] = 'application/json'
        req = urllib.request.Request(self.origin + path, data=body, headers=headers)
        try:
            response = self.opener.open(req, timeout=15)
        except urllib.error.HTTPError as error:
            response = error
        self.headers = response.headers
        raw = response.read().decode()
        return response.status, json.loads(raw) if 'application/json' in self.headers.get('Content-Type', '') and raw else raw

    def api(self, action, body=None):
        return self.request('/api/index.php?action=' + action, body, headers={'X-CSRF-Token': self.csrf})

    def rpc(self, method, params=None, token=None, headers=None):
        headers = dict(headers or {})
        if token:
            headers['Authorization'] = 'Bearer ' + token
        headers['Accept'] = 'application/json, text/event-stream'
        return self.request('/api/mcp.php', {'jsonrpc': '2.0', 'id': 1, 'method': method, 'params': params or {}}, headers=headers)

    def tool(self, name, args=None, token=None):
        status, rpc = self.rpc('tools/call', {'name': name, 'arguments': args or {}}, token)
        check(status == 200, f'RPC HTTP error: {status} {rpc}')
        check('result' in rpc, f'RPC error: {rpc}')
        result = rpc['result']
        return not result.get('isError', False), result.get('structuredContent') or json.loads(result['content'][0]['text'])


with tempfile.TemporaryDirectory(prefix='windows11x-mcp-') as tmp:
    private = Path(tmp) / 'private'
    private.mkdir()
    password = secrets.token_urlsafe(24)
    password_hash = subprocess.check_output([PHP, '-r', 'echo password_hash(trim(stream_get_contents(STDIN)), PASSWORD_DEFAULT);'], input=password.encode()).decode()
    (private / 'config.php').write_text("<?php return ['username'=>'admin','password_hash'=>'" + password_hash + "'];")
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    origin = f'http://127.0.0.1:{port}'
    resource = origin + '/api/mcp.php'
    env = {**os.environ, 'ATLAS_PRIVATE_DIR': str(private), 'ATLAS_BASE_URL': origin}
    env.pop('ATLAS_ADMIN_PASSWORD_HASH', None)
    log = open(Path(tmp) / 'php.log', 'w+')
    process = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', str(ROOT)], env=env, stdout=log, stderr=log)
    owner = Client(origin)
    viewer = Client(origin)
    try:
        for _ in range(60):
            try:
                status, session = owner.api('session')
                break
            except urllib.error.URLError:
                time.sleep(.1)
        check(status == 200, 'Server failed to start')
        owner.csrf = session['csrf']
        status, login = owner.api('login', {'username': 'admin', 'password': password})
        check(status == 200, 'Owner login')
        owner.csrf = login['csrf']
        status, initial = viewer.api('data')
        check(status == 200 and initial['revision'] == 0 and len(initial['content']['research']) == 86, 'Fallback content differs from UI')
        status, md = viewer.request('/ai-context.php')
        check(status == 200 and 'g2-dual' in md and 'Критерий галочки:' in md, 'Public context missing task IDs/proof')
        check(viewer.headers.get('Cache-Control') == 'no-store' and 'Set-Cookie' not in viewer.headers, 'Context cached or mutates login session')
        status, full = viewer.request('/ai-context.php?full=1')
        check(status == 200 and len(full) > len(md) * 5 and initial['content']['research'][0]['content'] in full, 'Full context omits source texts')
        check(viewer.rpc('initialize', {'protocolVersion': '2025-11-25'})[1]['result']['protocolVersion'] == '2025-11-25', 'Initialize negotiation')
        check(viewer.rpc('ping')[1]['result'] == {}, 'Empty MCP result must be an object')
        check(viewer.rpc('ping', headers={'Origin': 'https://evil.example'})[0] == 403, 'Bad origin accepted')
        check(viewer.request('/api/mcp.php')[0] == 405, 'GET stream must be explicitly unsupported')
        status, notified = viewer.request('/api/mcp.php', {'jsonrpc': '2.0', 'method': 'notifications/initialized'})
        check(status == 202 and notified == '', 'Notification response')
        tools = viewer.rpc('tools/list')[1]['result']['tools']
        check(len(tools) >= 10 and any(t['name'] == 'set_task_status' and not t['annotations']['readOnlyHint'] for t in tools), 'Tool annotations missing')
        ok, context = viewer.tool('get_project_context')
        check(ok and context['revision'] == 0 and context['tasks'] and context['researchBodiesIncluded'] is False, 'Fresh compact context')
        ok, task = viewer.tool('get_task', {'taskId': 'g2-dual'})
        check(ok and task['task']['proof'], 'Task read')
        research = initial['content']['research'][0]
        ok, chunk = viewer.tool('get_research', {'id': research['id'], 'limit': 100})
        check(ok and chunk['research']['content'] == research['content'][:100] and chunk['nextOffset'] == 100, 'Unicode pagination')
        ok, chunk2 = viewer.tool('get_research', {'id': research['id'], 'offset': 100, 'limit': 100})
        check(ok and chunk2['research']['content'] == research['content'][100:200], 'Second research page')
        check(viewer.tool('search_research', {'query': 'stateseparation', 'limit': 1})[1]['total'] > 0, 'Full-text search')
        check(not viewer.tool('get_task', {'taskId': 'missing'})[0], 'Missing task accepted')
        check(not viewer.tool('get_research', {'id': research['id'], 'extra': True})[0], 'Unknown argument accepted')
        provenance = {'researchDate': '2026-11-12', 'source': 'test-log.txt / SHA test', 'evidenceLevel': 'offline_test'}
        comment = {'revision': 0, 'taskId': 'g2-dual', 'text': 'Проверка UTF-8 🧪', **provenance}
        check(not viewer.tool('add_comment', comment)[0], 'Anonymous write accepted')
        check('WWW-Authenticate' in viewer.headers, 'Missing OAuth discovery challenge')
        metadata = viewer.request('/api/oauth.php?action=metadata')[1]
        check(metadata['issuer'] == origin and metadata['code_challenge_methods_supported'] == ['S256'], 'OAuth issuer/PKCE metadata')
        check(viewer.request('/api/oauth.php?action=resource')[1]['resource'] == resource, 'Wrong OAuth audience')
        status, registration = viewer.request('/api/oauth.php?action=register', {'client_name': 'Test ИИ', 'redirect_uris': ['https://client.example/callback'], 'token_endpoint_auth_method': 'none'})
        check(status == 201, 'Dynamic client registration')
        client_id = registration['client_id']
        check(viewer.request('/api/oauth.php?action=register', {'redirect_uris': ['http://evil.example/callback']})[0] == 400, 'Insecure redirect accepted')
        verifier = secrets.token_urlsafe(48)
        challenge = base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).decode().rstrip('=')

        def authorize(scope='atlas:read atlas:write'):
            params = {'client_id': client_id, 'redirect_uri': 'https://client.example/callback', 'response_type': 'code', 'code_challenge': challenge, 'code_challenge_method': 'S256', 'resource': resource, 'scope': scope, 'state': 'test-state'}
            url = '/api/oauth.php?action=authorize&' + urllib.parse.urlencode(params)
            check(viewer.request(url)[0] == 200, 'Guest consent page')
            check(viewer.request(url, form={'csrf': owner.csrf, 'decision': 'allow'})[0] == 403, 'Guest/CSRF approval accepted')
            status, page = owner.request(url)
            check(status == 200 and 'Разрешить подключение' in page, 'Owner consent page')
            check(owner.headers.get('X-Frame-Options') == 'DENY', 'Consent frame protection')
            status, _ = owner.request(url, form={'csrf': owner.csrf, 'decision': 'allow'})
            check(status == 303, 'Approval redirect')
            response = urllib.parse.parse_qs(urllib.parse.urlsplit(owner.headers['Location']).query)
            check(response['iss'] == [origin] and response['state'] == ['test-state'], 'Issuer/state omitted')
            return response['code'][0]

        code = authorize()
        exchange = {'grant_type': 'authorization_code', 'code': code, 'client_id': client_id, 'redirect_uri': 'https://client.example/callback', 'code_verifier': verifier, 'resource': resource}
        check(viewer.request('/api/oauth.php?action=token', form={**exchange, 'code_verifier': secrets.token_urlsafe(48)})[0] == 400, 'Wrong PKCE verifier accepted')
        check(viewer.request('/api/oauth.php?action=token', form={**exchange, 'resource': 'https://wrong.example'})[0] == 400, 'Wrong token audience accepted')
        status, tokens = viewer.request('/api/oauth.php?action=token', form=exchange)
        check(status == 200 and tokens['token_type'] == 'Bearer', 'Token exchange')
        check(viewer.request('/api/oauth.php?action=token', form=exchange)[0] == 400, 'Code replay accepted')
        access = tokens['access_token']
        private_json = (private / 'oauth.json').read_text()
        check(access not in private_json and tokens['refresh_token'] not in private_json, 'Plaintext tokens persisted')
        ok, saved = viewer.tool('add_comment', comment, access)
        check(ok and saved['revision'] == 1, f'AI comment failed: {saved}')
        current = viewer.api('data')[1]
        check(current['state']['comments']['g2-dual'][0]['researchDate'] == provenance['researchDate'], 'Research date lost')
        check(viewer.tool('get_task', {'taskId': 'g2-dual'})[1]['task']['comments'][0]['text'].startswith('Проверка UTF-8'), 'Public reader cannot see AI write')
        check(not viewer.tool('add_comment', comment, access)[0] and viewer.api('version')[1]['revision'] == 1, 'Stale AI write accepted')
        check(owner.api('save', initial)[0] == 409, 'Old owner snapshot overwrote AI change')
        bad = {**comment, 'revision': 1, 'researchDate': '2026-02-31'}
        check(not viewer.tool('add_comment', bad, access)[0], 'Impossible research date accepted')
        status_args = {'revision': 1, 'taskId': 'g2-dual', 'done': False, 'evidence': 'Снять отметку для проверки', **provenance}
        check(not viewer.tool('set_task_status', {**status_args, 'done': True, 'evidenceLevel': 'hypothesis'}, access)[0], 'Hypothesis allowed to complete task')
        ok, saved = viewer.tool('set_task_status', status_args, access)
        check(ok and saved['revision'] == 2 and not viewer.tool('get_task', {'taskId': 'g2-dual'})[1]['task']['done'], 'AI checkbox update')
        ok, saved = viewer.tool('add_task', {'revision': 2, 'gateId': 'G0', 'title': 'Новая задача', 'description': 'Проверка', 'proof': 'Должен быть журнал'}, access)
        check(ok and saved['revision'] == 3, f'Add AI task: {saved}')
        new_task = saved['taskId']
        check(viewer.tool('update_task', {'revision': 3, 'taskId': new_task, 'title': 'Уточнено', 'description': 'Детали', 'proof': 'Нужен конкретный лог'}, access)[0], 'Update custom task')
        check(viewer.tool('get_task', {'taskId': new_task})[1]['task']['title'] == 'Уточнено', 'Updated task missing')
        doc = {'revision': 4, 'id': 'ai-test-doc', 'title': 'Исследование ИИ', 'summary': 'Проверено', 'topic': 'API Set', 'content': 'Текст исследования 🧪', 'format': 'md', **provenance}
        check(viewer.tool('save_research', doc, access)[0], 'Save research')
        check(viewer.tool('save_research', {**doc, 'revision': 5, 'content': 'Обновлённый текст'}, access)[0], 'Update research')
        check(viewer.tool('get_research', {'id': 'ai-test-doc'})[1]['research']['content'] == 'Обновлённый текст', 'Research update invisible')
        event = {'revision': 6, 'title': 'Запись ИИ', 'text': 'Проверенный результат', **provenance}
        check(viewer.tool('save_timeline_entry', event, access)[0], 'Timeline write')
        check(viewer.tool('save_timeline_entry', {**event, 'revision': 7, 'text': 'Дополнение'}, access)[0], 'Timeline append')
        check('Проверенный результат' in next(d for d in viewer.api('data')[1]['content']['days'] if d['date'] == provenance['researchDate'])['text'], 'Timeline append lost old event')
        check(viewer.tool('get_changes', {'sinceRevision': 0})[1]['changes'][-1]['actor'] == 'AI: Test ИИ', 'Audit actor missing')
        check(json.loads((private / 'atlas.previous.json').read_text())['revision'] == 7, 'Shared AI backup missing')
        check('Обновлённый текст' in viewer.request('/ai-context.php?full=1')[1] and viewer.headers['X-Atlas-Revision'] == '8', 'Context not live after AI write')
        readonly_code = authorize('atlas:read')
        read_tokens = viewer.request('/api/oauth.php?action=token', form={**exchange, 'code': readonly_code})[1]
        check(not viewer.tool('add_comment', {**comment, 'revision': 8}, read_tokens['access_token'])[0], 'Read-only grant wrote data')
        refresh_args = {'grant_type': 'refresh_token', 'refresh_token': tokens['refresh_token'], 'client_id': client_id, 'resource': resource}
        status, rotated = viewer.request('/api/oauth.php?action=token', form=refresh_args)
        check(status == 200 and rotated['access_token'] != access, 'Refresh rotation')
        check(viewer.request('/api/oauth.php?action=token', form=refresh_args)[0] == 400, 'Refresh replay accepted')
        check(viewer.rpc('ping', token=access)[0] == 401, 'Old access survived token rotation')
        connections = owner.api('ai-connections', {})[1]['connections']
        check(len(connections) == 2, 'Connection management list')
        for connection in connections:
            check(owner.api('ai-revoke', {'id': connection['id']})[0] == 200, 'Owner revocation')
        check(viewer.rpc('ping', token=rotated['access_token'])[0] == 401, 'Revoked grant still works')
        check(owner.api('ai-connections', {})[1]['connections'] == [], 'Revoked grant listed')
        check(viewer.api('ai-connections', {})[0] in (401, 403), 'Guest can manage grants')
        code = authorize()
        credential_tokens = viewer.request('/api/oauth.php?action=token', form={**exchange, 'code': code})[1]
        (private / 'config.php').write_text("<?php return ['username'=>'changed','password_hash'=>'" + password_hash + "'];")
        check(viewer.rpc('ping', token=credential_tokens['access_token'])[0] == 401, 'Credential change did not revoke access')
        check(owner.api('save', viewer.api('data')[1])[0] == 401, 'Credential change did not revoke owner session')
        check(viewer.api('version')[1]['revision'] == 8, 'Security tests changed project data')
        print('PASS: live public context, Unicode source pagination, MCP discovery/read/write, OAuth DCR + PKCE + audience + replay protection, readonly scope, owner/AI conflicts, dated evidence, backup/audit, refresh rotation, revocation and credential invalidation')
    except Exception:
        log.flush()
        log.seek(0)
        print(log.read()[-10000:])
        raise
    finally:
        process.terminate()
        process.wait(timeout=10)
        log.close()
