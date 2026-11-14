"""Integration checks called inside the existing isolated OAuth test fixture."""
import copy
import gzip
import json
from datetime import datetime
from pathlib import Path
import time


def check_management(owner, viewer, token, private):
    def check(ok, message):
        if not ok:
            raise AssertionError(message)

    revision = viewer.api('version')[1]['revision']
    baseline = viewer.api('data')[1]
    provenance = {'researchDate': '2026-11-14', 'source': 'isolated integration fixture', 'evidenceLevel': 'confirmed'}

    def write(name, args):
        nonlocal revision
        ok, result = viewer.tool(name, {'revision': revision, **args}, token)
        check(ok, f'{name}: {result}')
        check(result['revision'] == revision + 1 and result['backupId'], f'{name}: missing monotonic revision/backup')
        revision = result['revision']
        meta = viewer.tool('get_backup', {'backupId': result['backupId']}, token)[1]['metadata']
        check(meta['revision'] == revision - 1, f'{name}: snapshot is not BEFORE action')
        seconds = (datetime.fromisoformat(meta['expiresAt']) - datetime.fromisoformat(meta['createdAt'])).total_seconds()
        check(seconds == 7 * 86400, 'Retention differs from seven days')
        return result

    def patch(changes, reason='Integration check'):
        return write('edit_site', {'changesJson': json.dumps(changes, ensure_ascii=False), 'reason': reason})

    def raw_snapshot(backup_id):
        meta = json.loads((private / 'backups' / (backup_id + '.meta.json')).read_text())
        raw = (private / 'backups' / (backup_id + meta['extension'])).read_bytes()
        return json.loads(gzip.decompress(raw) if meta['extension'] == '.json.gz' else raw)

    check(not viewer.tool('list_backups')[0], 'Anonymous backup listing')
    catalogue = viewer.rpc('tools/list')[1]['result']['tools']
    check(next(t for t in catalogue if t['name'] == 'get_backup')['securitySchemes'][0]['type'] == 'oauth2', 'Backup tool advertised public')
    check(owner.api('backups')[0] == 200 and viewer.api('backups')[0] == 401, 'Owner-only backup API')
    ok, first_section = viewer.tool('get_site_section', {'section': 'site', 'limit': 100})
    check(ok and first_section['nextOffset'] == 100, 'Section pagination')
    first = patch([{'op': 'replace', 'path': '/content/site/name', 'value': 'Управление ИИ 🧪'}])
    baseline_id = first['backupId']
    check(raw_snapshot(baseline_id)['content'] == baseline['content'], 'Complete before-state not backed up')
    check('password_hash' not in json.dumps(raw_snapshot(baseline_id)), 'Credentials in backup')
    check(not viewer.tool('get_backup', {'backupId': baseline_id})[0], 'Anonymous snapshot read')
    check(viewer.api('backup&id=' + baseline_id)[0] == 401, 'Anonymous browser backup download')
    check(owner.api('backup-info&id=' + baseline_id)[1]['research'] == 87, 'Owner preview contents')

    c1 = write('add_comment', {'taskId': 'g2-dual', 'text': 'test one', **provenance})['commentId']
    c2 = write('add_comment', {'taskId': 'g2-dual', 'text': 'test two', **provenance})['commentId']
    original = next(c for c in viewer.tool('get_task', {'taskId': 'g2-dual'})[1]['task']['comments'] if c['id'] == c1)
    write('update_comment', {'taskId': 'g2-dual', 'commentId': c1, 'text': 'edited one', **provenance})
    edited = next(c for c in viewer.tool('get_task', {'taskId': 'g2-dual'})[1]['task']['comments'] if c['id'] == c1)
    check(edited['createdAt'] == original['createdAt'] and edited['id'] == original['id'] and edited['author'] == original['author'], 'Comment edit rewrote identity')
    deleted = write('delete_comment', {'taskId': 'g2-dual', 'commentId': c1, 'reason': 'Delete ONLY test one'})
    remaining = viewer.tool('get_task', {'taskId': 'g2-dual'})[1]['task']['comments']
    check(any(c['id'] == c2 for c in remaining) and not any(c['id'] == c1 for c in remaining), 'Comment deletion affected wrong records')
    before = revision
    check(not viewer.tool('delete_comment', {'revision': revision, 'taskId': 'g2-dual', 'commentId': 'missing', 'reason': 'bad ID'}, token)[0], 'Missing comment deleted something')
    check(viewer.api('version')[1]['revision'] == before, 'Failed delete created a revision')
    # Recover one comment without undoing later work.
    restored_comment = next(c for c in raw_snapshot(deleted['backupId'])['state']['comments']['g2-dual'] if c['id'] == c1)
    patch([{'op': 'add', 'path': '/state/comments/g2-dual/-', 'value': restored_comment}])
    check(any(c['id'] == c1 for c in viewer.tool('get_task', {'taskId': 'g2-dual'})[1]['task']['comments']), 'Single comment recovery')
    # Old notes are materialized without resurrecting the deleted legacy entry.
    patch([{'op': 'add', 'path': '/state/notes/g0-vm', 'value': 'legacy test'}])
    write('delete_comment', {'taskId': 'g0-vm', 'commentId': 'legacy-g0-vm', 'reason': 'Delete legacy entry'})
    check(not viewer.tool('get_task', {'taskId': 'g0-vm'})[1]['task']['comments'], 'Deleted legacy note reappeared')

    gate = {'id': 'G-AI-test', 'title': 'Новый этап', 'subtitle': '', 'status': 'wait', 'statusText': '', 'context': '', 'focus': False}
    write('save_gate', gate)
    write('save_gate', {**gate, 'title': 'Переименовано'})
    t = write('add_task', {'gateId': 'G0', 'title': 'Move me', 'description': 'Keep identity', 'proof': 'A log'})['taskId']
    comment = write('add_comment', {'taskId': t, 'text': 'Keep this', **provenance})['commentId']
    write('set_task_status', {'taskId': t, 'done': True, 'evidence': 'Fixture log present', **provenance})
    write('move_task', {'taskId': t, 'gateId': gate['id'], 'position': 0})
    moved = viewer.tool('get_task', {'taskId': t})[1]['task']
    check(moved['done'] and moved['gateId'] == gate['id'] and moved['comments'][0]['id'] == comment, 'Move lost state/comments')
    write('delete_gate', {'gateId': gate['id'], 'reason': 'Remove entire test gate'})
    check(not viewer.tool('get_task', {'taskId': t})[0], 'Cascade retained task')
    data = viewer.api('data')[1]
    check(t not in data['state']['done'] and t not in data['state']['comments'], 'Cascade retained orphan state')
    write('delete_task', {'taskId': 'g2-final', 'reason': 'Check deletion of original task'})
    check(not viewer.tool('get_task', {'taskId': 'g2-final'})[0], 'Base task deletion unsupported')
    check(viewer.tool('get_task', {'taskId': 'g2-dual'})[0], 'Deleting one task removed another')
    write('delete_research', {'id': 'ai-test-doc', 'reason': 'Delete test document'})
    check(not viewer.tool('get_research', {'id': 'ai-test-doc'})[0], 'Research not deleted')
    write('delete_timeline_entry', {'researchDate': '2026-11-12', 'reason': 'Delete one day'})
    check(not any(x['date'] == '2026-11-12' for x in viewer.api('data')[1]['content']['days']), 'Timeline not deleted')
    # Every section is editable without executing uploaded strings as code.
    patch([
        {'op': 'add', 'path': '/content/texts/test~1~0', 'value': '<script>not executable</script>'},
        {'op': 'replace', 'path': '/content/snapshot', 'value': '14.11.2026'},
        {'op': 'add', 'path': '/content/terms/-', 'value': ['Тест', 'Детали']},
        {'op': 'replace', 'path': '/content/architecture/nt/0/title', 'value': 'Уточнение'},
        {'op': 'replace', 'path': '/content/overviewBlocks/proofSteps/0/text', 'value': 'Updated'},
        {'op': 'replace', 'path': '/content/myths/0/text', 'value': 'Updated'},
        {'op': 'move', 'from': '/content/gates/0', 'path': '/content/gates/1'},
    ])
    check(viewer.api('data')[1]['content']['texts']['test/~'] == '<script>not executable</script>', 'JSON Pointer escaping')
    for changes in [
        [{'op': 'replace', 'path': '/content/site/name', 'value': 'must roll back'}, {'op': 'remove', 'path': '/missing'}],
        [{'op': 'remove', 'path': '/content/site/name'}],
        [{'op': 'test', 'path': '/content/site/name', 'value': 'wrong'}],
        [{'op': 'add', 'path': '/backups', 'value': []}],
        [{'op': 'replace', 'path': '/config/password_hash', 'value': 'not allowed'}],
        [{'op': 'move', 'from': '/content/site', 'path': '/content/site/nested'}],
    ]:
        check(not viewer.tool('edit_site', {'revision': revision, 'changesJson': json.dumps(changes), 'reason': 'Negative test'}, token)[0], 'Invalid patch accepted')
        check(viewer.api('version')[1]['revision'] == revision, 'Failed patch partially committed')
    check(viewer.api('data')[1]['content']['site']['name'] == 'Управление ИИ 🧪', 'Atomic patch rolled back incorrectly')
    check(not viewer.tool('restore_backup', {'revision': revision - 1, 'backupId': baseline_id, 'reason': 'Stale restore'}, token)[0], 'Stale full restore accepted')
    check(owner.api('restore-backup', {'revision': revision, 'backupId': baseline_id})[0] == 200, 'Owner full restore')
    revision += 1
    current = viewer.api('data')[1]
    check(current['content'] == baseline['content'] and current['state'] == baseline['state'], 'Full restore differs from snapshot')
    check(current['changes'][-1]['action'] == 'restore_backup' and current['changes'][-1]['actor'] == 'owner', 'Restore erased audit history')
    undo_restore_id = current['changes'][-1]['backupId']
    check(raw_snapshot(undo_restore_id)['content']['site']['name'] == 'Управление ИИ 🧪', 'Restore did not back up current content')
    result = write('restore_backup', {'backupId': undo_restore_id, 'reason': 'Undo the full restore'})
    check(viewer.api('data')[1]['content']['site']['name'] == 'Управление ИИ 🧪', 'Restore cannot be undone')
    write('restore_backup', {'backupId': baseline_id, 'reason': 'Leave fixture in baseline content/state'})

    directory = private / 'backups'
    # Seven-day cleanup and expiry enforcement are independent of model instructions.
    expired_id = 'b-' + str(int(time.time()) - 7 * 86400 - 10) + '-0-' + 'a' * 16
    (directory / (expired_id + '.meta.json')).write_text('{}')
    (directory / (expired_id + '.json')).write_text('{}')
    check(not viewer.tool('get_backup', {'backupId': expired_id}, token)[0], 'Expired copy readable')
    check(not viewer.tool('restore_backup', {'revision': revision, 'backupId': expired_id, 'reason': 'Expired'}, token)[0], 'Expired copy restorable')
    check(viewer.tool('list_backups', {'limit': 1}, token)[1]['nextOffset'] == 1, 'Backup pagination')
    check(not list(directory.glob(expired_id + '*')), 'Expired backup files not pruned')
    check(not viewer.tool('get_backup', {'backupId': '../../config.php'}, token)[0], 'Backup path traversal')
    # Integrity failure must reject restore before applying any change.
    meta = json.loads((directory / (baseline_id + '.meta.json')).read_text())
    path = directory / (baseline_id + meta['extension'])
    original_bytes = path.read_bytes()
    path.write_bytes(b'corrupt')
    try:
        check(not viewer.tool('restore_backup', {'revision': revision, 'backupId': baseline_id, 'reason': 'Corrupt'}, token)[0], 'Corrupt backup restored')
        check(viewer.api('version')[1]['revision'] == revision, 'Corrupt restore changed data')
    finally:
        path.write_bytes(original_bytes)
    # Simulate disk/directory failure without relying on permissions (CI may run as root).
    saved_dir = private / 'backups-held'
    directory.rename(saved_dir)
    directory.write_text('backup storage unavailable')
    try:
        status, _ = viewer.rpc('tools/call', {'name': 'delete_task', 'arguments': {'revision': revision, 'taskId': 'g2-final', 'reason': 'Must fail closed'}}, token)
        check(status == 503, 'Mutation succeeded without backup storage')
        check(viewer.api('version')[1]['revision'] == revision, 'Failed backup changed revision')
        check(viewer.tool('get_task', {'taskId': 'g2-final'})[0], 'Failed backup still deleted task')
    finally:
        directory.unlink()
        saved_dir.rename(directory)
    for path in directory.glob('*'):
        check(path.stat().st_mode & 0o077 == 0, 'Snapshot readable outside owner permissions')
    check(viewer.api('data')[1]['content'] == baseline['content'], 'Tests failed to restore baseline')
    print('PASS: full site CRUD, exact comment edits/deletes, cascade/move, atomic JSON Patch, seven-day full backups, private listing/download, individual recovery, restore/undo, retention, integrity and fail-closed storage')
    return revision
