"""Binary storage checks in the isolated owner/OAuth fixture; never touches production."""
import hashlib
import json
import os
import time
import urllib.error
import urllib.request


def check_artifacts(owner, viewer, token, private):
    def check(ok, message):
        if not ok:
            raise AssertionError(message)

    baseline = viewer.api('data')[1]
    revision = baseline['revision']
    fields = dict(title='Тестовая DLL', version='exp01', description='<script>text only</script>',
                  status='offline_pass', issues='Boot ещё не проверялся', testedOn='Isolated fixture',
                  verification='Static fixture bytes; no runtime claim', taskIds=['g2-dual'])
    content = b'MZ\x00\xff' + bytes(range(256)) * 2200  # Two streamed chunks, binary UTF-8-invalid payload.
    sha = hashlib.sha256(content).hexdigest()

    def call(client, action, body=None, binary=False, csrf=None):
        headers = {}
        if body is not None:
            headers = {'Content-Type': 'application/octet-stream' if binary else 'application/json',
                       'X-CSRF-Token': client.csrf if csrf is None else csrf}
        raw_body = body if binary or body is None else json.dumps(body, ensure_ascii=False).encode()
        req = urllib.request.Request(client.origin + '/api/files.php?action=' + action, data=raw_body, headers=headers)
        try:
            response = client.opener.open(req, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        raw = response.read()
        payload = json.loads(raw) if 'application/json' in response.headers.get('Content-Type', '') else raw
        return response.status, payload, response.headers

    def mutate(action, args):
        nonlocal revision
        status, result, _ = call(owner, action, {'revision': revision, **args})
        check(status == 200, f'{action}: {status} {result}')
        check(result['revision'] == revision + 1 and result.get('backupId'), 'Artifact write without backup/monotonic revision')
        revision = result['revision']
        return result

    def upload(base_id=''):
        status, started, _ = call(owner, 'begin', {'filename': 'ApiSet тест.dll', 'size': len(content)})
        check(status == 200, 'Begin upload')
        upload_id = started['uploadId']
        check(call(owner, f'chunk&uploadId={upload_id}&offset=1', content[:1], True)[0] == 409, 'Out-of-order chunk accepted')
        check(call(owner, f'chunk&uploadId={upload_id}&offset=0', b'x' * (524288 + 1), True)[0] == 413, 'Oversized chunk accepted')
        check(call(owner, 'finish', {'revision': revision, 'uploadId': upload_id, **fields})[0] == 422, 'Incomplete file published')
        for offset in range(0, len(content), started['chunkBytes']):
            data = content[offset:offset + started['chunkBytes']]
            action = f'chunk&uploadId={upload_id}&offset={offset}'
            check(call(owner, action, data, True)[0] == 200, 'Chunk transfer failed')
            check(call(owner, action, data, True)[0] == 200, 'Safe retry was not idempotent')
        check(call(owner, 'finish', {'revision': revision - 1, 'uploadId': upload_id, **fields})[0] == 409, 'Stale upload publication accepted')
        check(call(owner, 'finish', {'revision': revision, 'uploadId': upload_id, **fields, 'verification': ''})[0] == 422, 'Success claim without evidence')
        check(call(owner, 'finish', {'revision': revision, 'uploadId': upload_id, **fields, 'taskIds': ['missing']})[0] == 404, 'Missing task linked')
        result = mutate('finish', {'uploadId': upload_id, 'baseArtifactId': base_id, **fields})
        again = call(owner, 'finish', {'revision': revision - 1, 'uploadId': upload_id, **fields})[1]
        check(again['alreadySaved'] and again['artifact']['id'] == result['artifact']['id'], 'Lost response retry duplicated upload')
        return result

    check(call(viewer, 'begin', {'filename': 'x.dll', 'size': 1})[0] == 401, 'Guest upload accepted')
    check(call(owner, 'begin', {'filename': 'x.dll', 'size': 1}, csrf='wrong')[0] == 403, 'Missing upload CSRF protection')
    for name in ['../x.dll', 'a/b.dll', 'a\\b.dll', 'a\r\n.dll']:
        check(call(owner, 'begin', {'filename': name, 'size': 1})[0] == 422, 'Path/header injection filename accepted')
    check(call(owner, 'begin', {'filename': 'x.dll', 'size': 104857601})[0] == 413, 'File limit ignored')
    first = upload()
    baseline_id = first['backupId']
    row = first['artifact']
    check(row['sha256'] == sha and row['size'] == len(content), 'Wrong server SHA/size')
    check(row['familyId'] == row['id'], 'First version family')
    status, downloaded, headers = call(viewer, 'download&id=' + row['id'])
    check(status == 200 and downloaded == content, 'Downloaded bytes differ')
    check('attachment;' in headers['Content-Disposition'] and headers['X-Content-Type-Options'] == 'nosniff', 'Executable download not forced to attachment')
    check(headers['X-Artifact-SHA256'] == sha, 'Download SHA missing')
    blob = private / 'artifacts' / (sha + '.blob')
    check(blob.stat().st_mode & 0o077 == 0 and len(list((private / 'artifacts').glob('*.blob'))) == 1, 'Private file permissions')
    check(row['id'] in viewer.tool('get_task', {'taskId': 'g2-dual'})[1]['task']['artifactIds'], 'Task lacks file link')
    check(viewer.tool('get_site_section', {'section': 'artifacts'})[0], 'MCP cannot read artifact metadata')
    check(viewer.tool('get_project_context')[1]['content']['artifacts'][0]['sha256'] == sha, 'AI context omits file identity')
    index = len(viewer.api('data')[1]['content']['artifacts']) - 1
    ok, edited = viewer.tool('edit_site', {'revision': revision, 'changesJson': json.dumps([{'op': 'replace', 'path': f'/content/artifacts/{index}/issues', 'value': 'AI уточнил ограничение'}]), 'reason': 'Artifact AI metadata check'}, token)
    check(ok and edited['backupId'], 'AI cannot manage file metadata with backup')
    revision = edited['revision']
    second = upload(row['id'])['artifact']
    check(second['id'] != row['id'] and second['familyId'] == row['familyId'], 'New version overwrote original')
    check(len(list((private / 'artifacts').glob('*.blob'))) == 1, 'Identical bytes duplicated disk usage')
    for _ in range(3):
        check(call(owner, 'begin', {'filename': 'pending.dll', 'size': 1})[0] == 200, 'Pending upload slot')
    check(call(owner, 'begin', {'filename': 'fourth.dll', 'size': 1})[0] == 429, 'Concurrent upload quota ignored')
    check(call(viewer, 'clear-pending', {})[0] == 401, 'Guest can cancel uploads')
    check(call(owner, 'clear-pending', {})[0] == 200 and not list((private / 'artifact-uploads').glob('*.part')), 'Abandoned uploads cannot be cleared')
    check(len(viewer.api('data')[1]['content']['artifacts']) == 2, 'Clearing staging deleted published files')
    updated = mutate('update', {'id': row['id'], **fields, 'status': 'failed', 'issues': 'Known failure'})
    check(call(viewer, 'delete', {'id': row['id'], 'revision': revision})[0] == 401, 'Guest delete accepted')
    deleted = mutate('delete', {'id': row['id']})
    check(call(viewer, 'download&id=' + row['id'])[0] == 404, 'Deleted file still public')
    check(call(viewer, 'download&id=' + row['id'] + '&backupId=' + deleted['backupId'])[0] == 401, 'Guest reads deleted snapshot file')
    check(call(owner, 'download&id=' + row['id'] + '&backupId=' + deleted['backupId'])[1] == content, 'Backup bytes unavailable')
    mutate('restore-file', {'id': row['id'], 'backupId': deleted['backupId']})
    check(call(viewer, 'download&id=' + row['id'])[1] == content and len(viewer.api('data')[1]['content']['artifacts']) == 2, 'Individual recovery affected other files')
    check(call(owner, 'restore-file', {'id': row['id'], 'backupId': deleted['backupId'], 'revision': revision - 1})[0] == 409, 'Stale recovery accepted')
    # Integrity is checked before serving bytes.
    blob.write_bytes(b'wrong')
    check(call(viewer, 'download&id=' + row['id'])[0] == 503, 'Corrupt binary served')
    blob.write_bytes(content)
    # Last deletion renews binary retention even if it was uploaded months ago.
    os.utime(blob, (time.time() - 30 * 86400,) * 2)
    mutate('delete', {'id': row['id']})
    last_deleted = mutate('delete', {'id': second['id']})
    check(blob.exists() and blob.stat().st_mtime > time.time() - 10, 'Deletion failed to retain bytes for seven days')
    # Restore one old file without reverting the site.
    mutate('restore-file', {'id': second['id'], 'backupId': last_deleted['backupId']})
    check(call(viewer, 'download&id=' + second['id'])[1] == content, 'Retained file cannot be recovered')
    orphan = private / 'artifacts' / ('a' * 64 + '.blob')
    orphan.write_bytes(b'orphan')
    os.utime(orphan, (time.time() - 8 * 86400,) * 2)
    mutate('update', {'id': second['id'], **fields})
    check(not orphan.exists(), 'Expired unreferenced bytes not reclaimed')
    # Deleted tasks remain historical links and do not block unrelated metadata edits.
    ok, deleted_task = viewer.tool('delete_task', {'revision': revision, 'taskId': 'g2-dual', 'reason': 'Test historical file link'}, token)
    check(ok, 'Task with file links cannot be deleted')
    revision = deleted_task['revision']
    mutate('update', {'id': second['id'], **fields})
    # Full restore removes artifact metadata but keeps bytes for undo.
    restored = owner.api('restore-backup', {'revision': revision, 'backupId': baseline_id})
    check(restored[0] == 200, 'Full restore with binaries failed')
    revision += 1
    data = viewer.api('data')[1]
    check(data['content'] == baseline['content'] and data['state'] == baseline['state'], 'Artifact tests failed to restore original content/state')
    check(blob.exists(), 'Full restore irreversibly deleted artifact bytes')
    print('PASS: private immutable binary storage, streamed/retry-safe uploads, CSRF/auth, SHA-256 download, task links, evidence validation, AI edits, deduplicated versions, individual/full recovery and seven-day binary retention')
    return revision
