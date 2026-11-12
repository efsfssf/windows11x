"""Integration checks against a real PHP server with isolated temporary storage."""
import copy
import http.cookiejar
import json
import os
from pathlib import Path
import secrets
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BIN', 'php')

def check(ok, message):
    if not ok:
        raise AssertionError(message)

class Client:
    def __init__(self, base):
        self.base = base
        self.csrf = ''
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def call(self, action, body=None, csrf=None):
        headers = {} if body is None else {'Content-Type': 'application/json', 'X-CSRF-Token': self.csrf if csrf is None else csrf}
        request = urllib.request.Request(self.base + '?action=' + action, data=None if body is None else json.dumps(body).encode(), headers=headers)
        try:
            response = self.opener.open(request)
        except urllib.error.HTTPError as error:
            response = error
        self.headers = response.headers
        result = json.loads(response.read())
        return response.status, result

    def session(self):
        status, session = self.call('session')
        check(status == 200, 'session unavailable')
        self.csrf = session['csrf']
        return session

with tempfile.TemporaryDirectory(prefix='windows11x-api-') as tmp:
    private = Path(tmp) / 'private'
    private.mkdir()
    password = secrets.token_urlsafe(24)
    password_hash = subprocess.check_output([PHP, '-r', 'echo password_hash(trim(stream_get_contents(STDIN)), PASSWORD_DEFAULT);'], input=password.encode()).decode()
    env = {**os.environ, 'ATLAS_PRIVATE_DIR': str(private), 'ATLAS_ADMIN_PASSWORD_HASH': password_hash}
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    log = open(Path(tmp) / 'php.log', 'w+')
    process = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', str(ROOT)], env=env, stdout=log, stderr=log)
    base = f'http://127.0.0.1:{port}/api/index.php'
    try:
        guest = Client(base)
        for _ in range(60):
            try:
                session = guest.session()
                break
            except urllib.error.URLError:
                time.sleep(.1)
        else:
            raise AssertionError('PHP did not start')
        check(not session['editor'] and session['configured'], 'guest got editor access')
        check(guest.call('data')[1]['revision'] == 0, 'initial revision')
        check(not guest.headers.get('Set-Cookie'), 'Public data request must not replace the session cookie')
        check(guest.call('save', {})[0] == 401, 'guest write accepted')
        check(guest.call('login', {'username': 'admin', 'password': password}, csrf='invalid')[0] == 403, 'CSRF accepted')
        check(guest.call('login', {'username': 'admin', 'password': 'wrong'})[0] == 401, 'wrong password accepted')
        status, login = guest.call('login', {'username': 'admin', 'password': password})
        check(status == 200 and login['editor'], 'owner login failed')
        guest.csrf = login['csrf']
        raw = subprocess.check_output(['node', '-e', "const fs=require('fs'),vm=require('vm');const c={window:{}};vm.createContext(c);for(const f of ['content-defaults.js','research-data.js'])vm.runInContext(fs.readFileSync(f,'utf8'),c);console.log(JSON.stringify({...c.window.WCOS_DEFAULTS,research:c.window.WCOS_RESEARCH}));"], cwd=ROOT)
        content = json.loads(raw)
        payload = {'revision': 0, 'content': content, 'state': {'version': 1, 'done': {'g2-dual': False}, 'notes': {'g2-dual': 'Проверено на другом устройстве'}, 'custom': []}}
        status, result = guest.call('save', payload)
        check(status == 200 and result['revision'] == 1, f'full initial content rejected: {result}')
        viewer = Client(base)
        viewer.session()
        check(viewer.call('data')[1]['state']['notes']['g2-dual'].startswith('Проверено'), 'cross-browser persistence failed')
        check(viewer.call('save', payload)[0] == 401, 'anonymous write after seed accepted')
        check(guest.call('save', payload)[0] == 409, 'stale revision overwrote data')
        payload['revision'] = 1
        content['site']['name'] = 'Обновлённый атлас'
        content['gates'].append({'id': 'g8-new', 'title': 'Новый этап', 'subtitle': '', 'status': 'wait', 'statusText': '', 'context': '', 'focus': False, 'tasks': []})
        content['days'].append({'date': '2027-01-02', 'title': 'Следующий год', 'text': '', 'tags': []})
        content['research'].append({'id': 'new-doc', 'title': 'Новый документ', 'summary': '', 'date': '2027-01-02', 'format': 'md', 'group': 'Автор', 'topic': 'Новое', 'content': '# Обновлено'})
        check(guest.call('save', payload)[0] == 200, 'new content rejected')
        check(viewer.call('data')[1]['content']['site']['name'] == 'Обновлённый атлас', 'public content not updated')
        check(json.loads((private / 'atlas.previous.json').read_text())['revision'] == 1, 'previous revision backup missing')
        broken = copy.deepcopy(payload)
        broken['revision'] = 2
        broken['content']['gates'][0]['id'] = 'bad" onclick="alert(1)'
        check(guest.call('save', broken)[0] == 422, 'invalid identifiers accepted')
        broken = copy.deepcopy(payload)
        broken['revision'] = 2
        broken['content']['days'][0]['date'] = '2026-02-31'
        check(guest.call('save', broken)[0] == 422, 'invalid date accepted')
        check(guest.call('save', '{broken')[0] == 400, 'non-object body accepted')
        check(guest.call('data')[1]['revision'] == 2, 'failed write changed revision')
        check(guest.call('logout', {})[0] == 200, 'logout failed')
        guest.session()
        check(guest.call('save', payload)[0] == 401, 'logout kept write access')
        for _ in range(10):
            check(viewer.call('login', {'username': 'admin', 'password': 'wrong'})[0] == 401, 'login throttled too early')
        check(viewer.call('login', {'username': 'admin', 'password': password})[0] == 429, 'login throttle missing')
        fresh = Client(base)
        fresh.session()
        check(fresh.call('login', {'username': 'admin', 'password': password})[0] == 429, 'new cookie bypassed throttle')
        print('PASS: guest read-only API, CSRF, login/logout, 86-source save, cross-client persistence, new stages/dates/docs, conflict rejection, validation, backup, login throttle')
    except Exception:
        log.flush()
        log.seek(0)
        print(log.read())
        raise
    finally:
        process.terminate()
        process.wait(timeout=10)
        log.close()
