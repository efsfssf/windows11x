#!/usr/bin/env python3
"""Read-only, stdlib-only decoder for the inspected NT 26100 embedded-7 profile.

Only ordinary ASCII *-l1-MAJOR-REVISION queries with an exact legacy companion
name are supported. This is deliberately
not a universal API Set parser, composer, PE loader, or patcher. Output is JSON on
stdout. Files are opened only by pathlib.Path.read_bytes(). Python 3.6+.
"""
import argparse
import bisect
import hashlib
import json
import pathlib
import re
import struct
import sys

TARGETS = ['ext-ms-win-ntos-stateseparation-l1-1-0',
           'ext-ms-win-ntos-trace-l1-1-0']
ORIGINAL = '391a2818fdef14f4a9b6e6ce2a7e7f01d04f943de9ef543ec683927cfe480889'


def need(ok, message):
    if not ok:
        raise ValueError(message)


def hx(n):
    return '0x{:X}'.format(n)


def unpack(data, off, fmt):
    size = struct.calcsize(fmt)
    need(0 <= off <= len(data) and size <= len(data)-off, 'file bounds at '+hx(off))
    return struct.unpack_from(fmt, data, off)


class Image:
    def __init__(self, data):
        self.data = data
        need(data[:2] == b'MZ', 'MZ signature')
        nt = unpack(data, 0x3c, '<I')[0]
        need(data[nt:nt+4] == b'PE\0\0', 'PE signature')
        machine, count = unpack(data, nt+4, '<HH')
        opt = unpack(data, nt+20, '<H')[0]
        need(machine == 0x8664 and unpack(data, nt+24, '<H')[0] == 0x20b, 'AMD64 PE32+')
        self.sections = []
        for i in range(count):
            off = nt+24+opt+40*i
            name, vs, va, size, raw = unpack(data, off, '<8s4I')
            need(raw <= len(data) and size <= len(data)-raw, 'section raw bounds')
            self.sections.append((name.rstrip(b'\0'), vs, va, size, raw))


class Schema:
    def __init__(self, data):
        self.image = Image(data)
        sections = [s for s in self.image.sections if s[0] == b'.apiset']
        need(len(sections) == 1, 'exactly one .apiset')
        _, self.virtual_size, self.rva, self.raw_size, self.raw = sections[0]
        self.data = data[self.raw:self.raw+self.raw_size]
        need(self.u32(0) == 6 and self.u32(0x10) > 8, 'v6 wrapper')
        need(self.u8(0x1c) == 7, 'embedded-7 marker')
        self.size = self.u32(4)
        need(0x98 <= self.size <= self.raw_size and self.size <= self.virtual_size, 'namespace bounds')
        need(self.u32(0x20) == self.size, 'outer / embedded size mismatch')
        # E + encodedOffset - word[E+0x12]; this profile has E=N+0x1c.
        self.bias = self.u16(0x2e)
        need(self.bias == 0x1c, 'unsupported embedded base bias')
        need(self.u16(0x2c) == 0x74, 'unsupported header word at embedded+0x10')
        need(self.u8(0x1e) == 8, 'only inspected flags profile 0x08 supported')
        self.pool = self.ref(self.u32(0x40))
        self.pool_size = self.u32(0x44)
        self.span(self.pool, self.pool_size)
        self.tables = {}
        for key, d in [('family', 0x48), ('alias', 0x54), ('version', 0x60),
                       ('auxiliary', 0x6c), ('host', 0x78)]:
            off, count, extra, stride, flags, f1, f2 = self.get(d, '<IHH4B')
            need(stride == (4 if key == 'auxiliary' else 12), 'unsupported stride')
            start = self.ref(off)
            index = start+count*stride
            end = index+count*8
            self.span(start, end-start)
            need(extra == 0 and flags in (0, 2), 'unsupported descriptor profile')
            hashes = [self.u32(index+i*8) for i in range(count)]
            need(hashes == sorted(hashes), 'hash index order: '+key)
            indices = [self.u16(index+i*8+4) for i in range(count)]
            need(sorted(indices) == list(range(count)), 'hash index permutation: '+key)
            self.tables[key] = dict(descriptor=d, start=start, count=count,
                                    stride=stride, flags=flags, factor1=f1,
                                    factor2=f2, index=index, end=end, hashes=hashes)
        order = ['family', 'alias', 'version', 'auxiliary', 'host']
        need(self.pool+self.pool_size == self.tables['family']['start'], 'pool / table boundary')
        for left, right in zip(order, order[1:]):
            need(self.tables[left]['end'] == self.tables[right]['start'], 'table adjacency')
        self.import_start = self.ref(self.u32(0x84))
        self.import_size = self.u32(0x88)
        self.import_count = self.u16(0x8c)
        self.import_stride = self.u16(0x8e)
        need(self.import_stride == 16 and self.import_count*16 == self.import_size, 'importer dimensions')
        need(self.tables['host']['end'] == self.import_start, 'host / importer boundary')
        self.span(self.import_start, self.import_size)
        need(self.import_start+self.import_size <= self.size, 'last block boundary')
        need(self.u32(0x34) == self.u16(0x30)+self.u16(0x32), 'prefix dimensions')
        need(self.u32(0x34)+self.u32(0x38) == self.u32(0x40), 'legacy region boundary')
        self.legacy_count = self.u32(0xc)
        self.legacy_entry = self.u32(0x10)
        self.span(self.legacy_entry, self.legacy_count*24)
        self.span(self.u32(0x14), self.legacy_count*8)

    def get(self, off, fmt):
        return unpack(self.data, off, fmt)

    def u8(self, off): return self.get(off, '<B')[0]
    def u16(self, off): return self.get(off, '<H')[0]
    def u32(self, off): return self.get(off, '<I')[0]

    def span(self, off, size):
        need(0 <= off <= self.size and 0 <= size <= self.size-off,
             'namespace bounds at '+hx(off)+' size '+hx(size))

    def ref(self, encoded):
        return 0x1c+encoded-self.bias

    def source(self, off, size):
        self.span(off, size)
        return dict(namespace_offset=hx(off), embedded_offset=hx(off-0x1c) if off >= 0x1c else None,
                    file_offset=hx(self.raw+off), rva=hx(self.rva+off), size=hx(size),
                    bytes=self.data[off:off+size].hex())

    def field(self, off, size, value):
        return dict(value=value, source=self.source(off, size))

    def string(self, relative, chars):
        need(relative <= self.pool_size and chars*2 <= self.pool_size-relative, 'string pool bounds')
        off = self.pool+relative
        self.span(off, chars*2)
        return dict(text=self.data[off:off+chars*2].decode('utf-16le'),
                    source=self.source(off, chars*2))

    def record(self, key, i):
        t = self.tables[key]
        need(0 <= i < t['count'], 'record index bounds: '+key)
        return t['start']+i*t['stride']

    @staticmethod
    def hash(text, factor):
        h = 0
        for c in text:
            n = ord(c)
            if 65 <= n <= 90: n += 32
            h = (h*factor+n) & 0xffffffff
        return h

    def lookup(self, key, first_text, second_text):
        t = self.tables[key]
        h1 = self.hash(first_text, t['factor1'])
        h2 = self.hash(second_text, t['factor2'])
        lo, hi = bisect.bisect_left(t['hashes'], h1), bisect.bisect_right(t['hashes'], h1)
        hits = []
        rows = []
        for slot in range(lo, hi):
            at = t['index']+slot*8
            idx = self.u16(at+4)
            o = self.record(key, idx)
            rows.append(dict(slot=slot, source=self.source(at, 8), record_index=idx,
                             second_hash_match=self.u32(o) == h2))
            if self.u32(o) == h2: hits.append(idx)
        need(len(hits) == 1, 'lookup must have exactly one second-hash match: '+key)
        idx = hits[0]
        return dict(index=idx, primary_hash=hx(h1), secondary_hash=hx(h2),
                    primary_text=first_text, secondary_text=second_text, candidates=rows,
                    record=self.source(self.record(key, idx), t['stride']))

    def legacy(self, query):
        for i in range(self.legacy_count):
            o = self.legacy_entry+i*24
            flags, no, nl, hl, vo, vc = self.get(o, '<6I')
            self.span(no, nl)
            name = self.data[no:no+nl].decode('utf-16le')
            if name.lower() != query.lower(): continue
            self.span(vo, vc*20)
            values = []
            for j in range(vc):
                v = vo+j*20
                vf, io, il, ho, ln = self.get(v, '<5I')
                self.span(io, il); self.span(ho, ln)
                values.append(dict(record=self.source(v, 20), flags=vf,
                                   importer=self.data[io:io+il].decode('utf-16le'),
                                   host=self.data[ho:ho+ln].decode('utf-16le'),
                                   host_source=self.source(ho, ln)))
            return dict(index=i, record=self.source(o, 24), flags=flags,
                        name=dict(text=name, source=self.source(no, nl)), values=values)
        raise ValueError('legacy cross-check name missing')

    def decode(self, query, importer=None):
        need(importer is None or all(ord(c) < 128 for c in importer), 'only ASCII importer comparison supported')
        m = re.fullmatch(r'((?:api|ext)-[a-z0-9-]+)-l1-([0-9]+)-([0-9]+)', query.lower())
        need(m is not None, 'only ordinary ASCII l1-major-revision queries supported')
        stem, major, revision = m.group(1), int(m.group(2)), int(m.group(3))
        need(major <= 255 and revision <= 255, 'query version outside inspected byte form')
        # 27B680: header flags 0x08 skip parser ranges [4,6] and the -l1 range.
        if stem[4:7] == 'ms-': stem = stem[:4]+stem[7:]
        family_text = stem
        version_text = stem+'-'+m.group(2)
        family = self.lookup('family', family_text, family_text)
        version = self.lookup('version', version_text, version_text)
        vo = self.record('version', version['index'])
        fo = self.record('family', family['index'])
        need(self.u16(vo+4) == family['index'], 'version / family reference mismatch')
        flags = self.u8(vo+10)
        fflags = self.u8(fo+10)
        major_byte, revision_byte = self.u8(vo+8), self.u8(vo+9)
        need(major_byte == major, 'version byte / query mismatch')
        need(not(flags & 4), 'capped-version gate outside this decoder query profile')
        host_index, importer_head, importer_count = self.get(fo+4, '<3H')
        family['fields'] = dict(default_host=self.field(fo+4, 2, host_index),
                                importer_head=self.field(fo+6, 2, importer_head),
                                importer_count=self.field(fo+8, 2, importer_count),
                                flags=self.field(fo+10, 1, hx(fflags)))
        version['fields'] = dict(family_index=self.field(vo+4, 2, self.u16(vo+4)),
                                 major=self.field(vo+8, 1, major_byte),
                                 revision=self.field(vo+9, 1, revision_byte),
                                 flags=self.field(vo+10, 1, hx(flags)))
        default = None
        overrides = []
        selected = ''
        if fflags & 2:
            ho = self.record('host', host_index)
            relative, chars, level = self.get(ho+4, '<IHB')
            default = dict(index=host_index, record=self.source(ho, 12),
                           pool_offset=self.field(ho+4, 4, hx(relative)),
                           chars=self.field(ho+8, 2, chars),
                           level=self.field(ho+10, 1, level), string=self.string(relative, chars))
            need(level <= self.u8(0x1f), 'host level gate would reject default')
            selected = default['string']['text']
            k = importer_head
            seen = set()
            matched = False
            for _ in range(importer_count):
                need(0 <= k < self.import_count and k not in seen, 'importer chain bounds/cycle')
                seen.add(k)
                io = self.import_start+k*16
                nxt, opaque, ir, hr, il, hl = self.get(io, '<HHIIHH')
                im, host = self.string(ir, il), self.string(hr, hl)
                overrides.append(dict(index=k, record=self.source(io, 16), next=nxt,
                                      opaque_word=hx(opaque), importer=im, host=host))
                if not matched and importer is not None and im['text'].lower() == importer.lower():
                    selected = host['text']
                    matched = True
                k = nxt
        else:
            need(host_index == 0xffff and importer_count == 0, 'unexpected no-default profile')
        siblings = []
        for i in range(self.tables['version']['count']):
            o = self.record('version', i)
            if self.u16(o+4) == family['index']: siblings.append(i)
        return dict(query=query, legacy_crosscheck=self.legacy(query), family=family,
                    version=version, sealed=bool(flags & 1), family_version_indices=siblings,
                    default_host=default, default_absent=not bool(fflags & 2),
                    importer_specific_values=overrides, importer_query=importer,
                    modeled_result=dict(status='STATUS_SUCCESS', marker=1, host=selected,
                                        length=len(selected.encode('utf-16le')),
                                        buffer='NULL' if default is None else 'see string source'))

    def summary(self):
        tables = {}
        for key, t in self.tables.items():
            tables[key] = dict(descriptor=self.source(t['descriptor'], 12), count=t['count'],
                               stride=t['stride'], flags=hx(t['flags']),
                               hash_factors=[t['factor1'], t['factor2']],
                               records=[hx(t['start']), hx(t['index'])],
                               hash_index=[hx(t['index']), hx(t['end'])])
        return dict(section=dict(raw=hx(self.raw), rva=hx(self.rva), virtual_size=hx(self.virtual_size)),
                    namespace_size=self.field(4, 4, hx(self.size)),
                    embedded_size=self.field(0x20, 4, hx(self.u32(0x20))),
                    embedded_flags=self.field(0x1e, 1, hx(self.u8(0x1e))),
                    bias=self.field(0x2e, 2, hx(self.bias)),
                    string_pool=[hx(self.pool), hx(self.pool+self.pool_size)], tables=tables,
                    importers=dict(start=hx(self.import_start), end=hx(self.import_start+self.import_size),
                                   count=self.import_count, stride=self.import_stride,
                                   descriptor=self.source(0x84, 12)),
                    bounds_and_index_checks='PASS')


def self_test(schema):
    need(hashlib.sha256(schema.image.data).hexdigest() == ORIGINAL, 'self-test requires original hash')
    a, b = [schema.decode(q, 'ntoskrnl.exe') for q in TARGETS]
    need((a['family']['index'], a['version']['index'], a['sealed']) == (647, 710, True), 'State identity')
    need((b['family']['index'], b['version']['index'], b['sealed']) == (649, 712, False), 'Trace identity')
    need(all(x['default_absent'] and x['modeled_result']['length'] == 0 and
             not x['importer_specific_values'] for x in (a, b)), 'target hosts')
    need(0 not in schema.tables['host']['hashes'] and
         all(schema.u16(schema.record('host', i)+8) > 0 for i in range(schema.tables['host']['count'])),
         'original host table has no empty string record')
    tm = schema.decode('ext-ms-win-ntos-tm-l1-1-0')
    need(tm['sealed'] and tm['modeled_result']['host'] == 'tm.sys', 'sealed nonempty base example')
    override = schema.decode('ext-ms-win-kernel32-errorhandling-l1-1-0', 'kernel32.dll')
    need(override['modeled_result']['host'] == 'faultrep.dll', 'importer-specific example')
    fallback = schema.decode('ext-ms-win-kernel32-errorhandling-l1-1-0', 'ntoskrnl.exe')
    need(fallback['modeled_result']['host'] == 'kernel32.dll', 'default fallback')
    rejected = []
    for label, action in [('namespace overrun', lambda: schema.span(schema.size, 1)),
                          ('invalid family index', lambda: schema.record('family', 0xffff)),
                          ('string pool overrun', lambda: schema.string(schema.pool_size, 1)),
                          ('truncated PE', lambda: Schema(schema.image.data[:0x2100]))]:
        try: action()
        except ValueError: rejected.append(label)
        else: raise ValueError('negative test accepted: '+label)
    return dict(result='PASS', positive_cases=['both target identities and absent hosts', 'no empty host record in original',
                'SEALED tm.sys base', 'importer match faultrep.dll', 'default fallback kernel32.dll'],
                rejected=rejected)


def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument('schema', nargs='?', default=str(pathlib.Path(__file__).parent/'evidence/files/windows-26100/apisetschema.dll'))
    ap.add_argument('--contract', action='append', help='ordinary l1-MAJOR-REVISION name; repeatable')
    ap.add_argument('--importer', help='importer name, e.g. ntoskrnl.exe')
    ap.add_argument('--self-test', action='store_true')
    args = ap.parse_args()
    path = pathlib.Path(args.schema)
    data = path.read_bytes()
    schema = Schema(data)
    output = dict(input=str(path), sha256=hashlib.sha256(data).hexdigest(),
                  format_scope='inspected 26100 v6+embedded7 profile; ASCII l1 ordinary queries only',
                  structure=schema.summary(), contracts=[schema.decode(q, args.importer) for q in (args.contract or TARGETS)])
    if args.self_test: output['self_test'] = self_test(schema)
    print(json.dumps(output, indent=2, ensure_ascii=True))


if __name__ == '__main__':
    try: main()
    except (ValueError, UnicodeError, OSError, struct.error) as exc:
        print('decode rejected: '+str(exc), file=sys.stderr)
        sys.exit(1)
