"""Exercise folder imports against the isolated HTTP/OAuth fixture."""
from pathlib import Path
import tempfile
from urllib.parse import quote


def check_research_import(owner, viewer, token, private, write, patch):
    def check(ok, message):
        if not ok:
            raise AssertionError(message)

    root = Path(__file__).resolve().parents[1]
    with tempfile.TemporaryDirectory(prefix='import-check-', dir=root / 'library') as temporary:
        folder = Path(temporary)
        relative = folder.relative_to(root).as_posix()
        texts = {
            '2026-11-13 Обзор.MD': '# Заголовок исследования\n\nUTF-8: проверка 🧪',
            'nested/data.JSON': '{"value": "read as text"}',
            'nested/table.csv': 'name,value\none,1',
            'nested/note.txt': 'A plain note',
            'sample.ps1': 'throw "never execute"',
            'sample.py': 'raise Exception("never execute")',
            'another.markdown': '# Another heading',
        }
        for name, text in texts.items():
            target = folder / name
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_text(text, encoding='utf-8')
        (folder / 'bom.md').write_bytes(b'\xef\xbb\xbf# BOM heading')
        (folder / 'binary.txt').write_bytes(b'binary\x00data')
        (folder / 'oversize.md').write_bytes(b'x' * 2000001)
        (folder / '.hidden.md').write_text('hidden')
        (folder / 'ignored.html').write_text('<script>ignore</script>')
        (folder / '.hidden').mkdir()
        (folder / '.hidden' / 'secret.md').write_text('hidden directory')
        (folder / 'empty').mkdir()
        (folder / 'outside.txt').symlink_to(private / 'config.php')
        (folder / 'outside-dir').symlink_to(private, target_is_directory=True)
        action = 'scan-research&folder=' + quote(relative)
        original_revision = viewer.api('version')[1]['revision']
        check(viewer.api(action)[0] == 401, 'Anonymous filesystem scan allowed')
        status, result = owner.api(action)
        check(status == 200 and result['recursive'] and result['count'] == 8, 'Supported recursive files not scanned')
        check(len(result['warnings']) == 2, 'Skipped invalid/large files not reported')
        records = {r['path'].removeprefix(relative + '/'): r for r in result['files']}
        check(set(records) == set(texts) | {'bom.md'}, 'Hidden, unsupported or symlink files leaked')
        for name, text in texts.items():
            check(records[name]['content'] == text, 'Import changed source text')
        check(records['bom.md']['content'] == '# BOM heading', 'UTF-8 BOM not stripped')
        md = records['2026-11-13 Обзор.MD']
        check(md['title'] == 'Заголовок исследования' and md['date'] == '2026-11-13' and md['dateSource'] == 'filename', 'Markdown title/date metadata')
        check(records['nested/data.JSON']['format'] == 'json' and records['another.markdown']['format'] == 'md', 'Extension mapping')
        check(records['sample.py']['dateSource'] == 'file_mtime', 'Fallback date provenance missing')
        check(owner.api('scan-research&folder=' + quote(relative + '/empty'))[1]['count'] == 0, 'Empty folder scan')
        for bad in ['../', '/etc', '/library', 'library/../api', 'library//ai', 'library/.git', relative + '/outside-dir']:
            check(owner.api('scan-research&folder=' + quote(bad))[0] == 422, 'Invalid folder accepted: ' + bad)
        check(owner.api('scan-research&folder=library/nonexistent-fixture')[0] == 404, 'Missing folder not reported')
        check(viewer.api('version')[1]['revision'] == original_revision, 'Read-only scan changed data')
        check(not viewer.tool('import_research_folder', {'revision': original_revision, 'folder': relative, 'reason': 'Anonymous'})[0], 'Anonymous import accepted')
        before = viewer.api('data')[1]
        imported = write('import_research_folder', {'folder': relative, 'reason': 'Import all fixture formats'})
        after = viewer.api('data')[1]
        check(imported['added'] == 8 and imported['updated'] == 0 and len(after['content']['research']) == len(before['content']['research']) + 8, 'First import counts')
        check(after['changes'][-1]['target'] == relative, 'Import folder missing from audit')
        for doc in before['content']['research']:
            check(doc in after['content']['research'], 'Import changed unrelated research')
        # Reimport updates text while keeping owner edits and all absent records.
        index = next(i for i, r in enumerate(after['content']['research']) if r['path'] == md['path'])
        patch([
            {'op': 'replace', 'path': f'/content/research/{index}/title', 'value': 'Owner title'},
            {'op': 'replace', 'path': f'/content/research/{index}/date', 'value': '2026-11-01'},
        ])
        (folder / '2026-11-13 Обзор.MD').write_text('# File title changed\nFresh contents', encoding='utf-8')
        (folder / 'sample.py').unlink()
        again = write('import_research_folder', {'folder': relative, 'reason': 'Reimport changed file'})
        research = viewer.api('data')[1]['content']['research']
        updated = next(r for r in research if r['id'] == md['id'])
        check(again['added'] == 0 and again['updated'] == 1 and len(research) == len(after['content']['research']), 'Reimport duplicated/deleted entries')
        check(updated['title'] == 'Owner title' and updated['date'] == '2026-11-01' and updated['content'] == '# File title changed\nFresh contents', 'Reimport overwrote owner metadata or kept stale text')
        revision = viewer.api('version')[1]['revision']
        check(not viewer.tool('import_research_folder', {'revision': revision - 1, 'folder': relative, 'reason': 'Stale revision'}, token)[0], 'Stale import accepted')
        check(not viewer.tool('import_research_folder', {'revision': revision, 'folder': 'library/../api', 'reason': 'Invalid folder'}, token)[0], 'Unsafe MCP import accepted')
        check(viewer.api('version')[1]['revision'] == revision, 'Failed import changed data')
    print('PASS: owner-only recursive folder scan, supported formats, file limits, safe paths, text/date decoding, backed-up MCP import and duplicate-free metadata-preserving reimport')
