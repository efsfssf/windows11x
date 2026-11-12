#!/usr/bin/env python3
"""Offline-only API Set v6+embedded-v7 schema experiment (NT 26100.9278).

Does not access or modify Windows, registry, BCD, disks or boot policy. The
output PE has an INVALID original signature and MUST NOT be installed as a
boot system file. This builder supports exactly one hash-pinned input image.
"""
from pathlib import Path
import struct, hashlib, json, sys
from v7_decode import Schema

EXPECTED='391a2818fdef14f4a9b6e6ce2a7e7f01d04f943de9ef543ec683927cfe480889'
TARGETS={
    'ext-ms-win-ntos-stateseparation-l1-1-0':'statesepext.sys',
    'ext-ms-win-core-stateseparationext-l1-1-0':'onecorestateseparationext.dll'
}

def pack32(b,p,v): struct.pack_into('<I',b,p,v)
def pack16(b,p,v): struct.pack_into('<H',b,p,v)

def pe_checksum(data):
    # Same 16-bit folding as the Windows PE CheckSumMappedFile algorithm.
    pe=struct.unpack_from('<I',data,0x3c)[0]
    checksum_off=pe+24+64
    total=0
    for i in range(0,len(data),4):
        word = struct.unpack_from('<I',data,i)[0] if i+4<=len(data) else int.from_bytes(data[i:],'little')
        if i == checksum_off: word=0
        total=(total & 0xffffffff)+word+(total>>32)
        if total > 0xffffffff: total=(total & 0xffffffff)+(total>>32)
    total=(total & 0xffff)+(total>>16)
    total=(total & 0xffff)+(total>>16)
    return (total+len(data)) & 0xffffffff

def build(original):
    if hashlib.sha256(original).hexdigest()!=EXPECTED: raise ValueError('Original schema SHA-256 mismatch')
    old=Schema(original)
    assert old.size==0x29c60 and old.tables['version']['count']==984 and old.tables['host']['count']==286
    before={q:old.decode(q) for q in TARGETS}
    for q,v in before.items():
        assert v['default_absent'] and v['legacy_crosscheck']['values'][0]['host']==''
    # Append new strings at the END OF EXISTING V7 string pool, keeping every old
    # pool-relative string reference unchanged. The tables are then relocated.
    strings=b''
    newstrings={}
    for q,hostname in TARGETS.items():
        newstrings[q]={'relative':old.pool_size+len(strings),'ns_offset':old.pool+old.pool_size+len(strings),'name':hostname}
        strings+=hostname.encode('utf-16le')
    if len(strings)%4: strings+=b'\x00'*(4-len(strings)%4)
    delta_pool=len(strings)
    old_host=old.tables['host']; old_host_records_end=old_host['index']
    count=old_host['count']; add=len(TARGETS)
    newhost_records=b''
    for q,meta in newstrings.items():
        hostname=meta['name']
        # +0: secondary hash37 of lower-case string, +4: pool-relative byte offset,
        # +8: host name UTF-16 code unit count, +10: level=0xff; +11 reserved=0.
        newhost_records+=struct.pack('<IIHBB',old.hash(hostname,37),meta['relative'],len(hostname),0xff,0)
        meta['host_index']=count+len(newhost_records)//12-1
    index_entries=[(old.u32(old_host['index']+i*8),old.u16(old_host['index']+i*8+4),old.u16(old_host['index']+i*8+6)) for i in range(count)]
    index_entries += [(old.hash(meta['name'],31),meta['host_index'],0) for meta in newstrings.values()]
    index_entries.sort(key=lambda x:(x[0],x[1]))
    new_host_indices=b''.join(struct.pack('<IHH',*x) for x in index_entries)
    old_ns=bytes(old.data[:old.size])
    pool_end=old.pool+old.pool_size
    ns=bytearray(old_ns[:pool_end]+strings+
                 old_ns[pool_end:old_host_records_end]+newhost_records+
                 new_host_indices+old_ns[old.import_start:old.size])
    total_delta=delta_pool+add*20
    assert len(ns)==old.size+total_delta

    # Shift all table descriptors; host and importer are additionally shifted
    # after newly inserted host records/index entries.
    for key in ['family','alias','version','auxiliary','host']:
        pack32(ns,old.tables[key]['descriptor'],old.tables[key]['start']+delta_pool)
    pack32(ns,0x84,old.import_start+total_delta)
    pack16(ns,old_host['descriptor']+4,count+add)
    pack32(ns,0x44,old.pool_size+delta_pool)
    pack32(ns,4,len(ns))
    pack32(ns,0x20,len(ns))

    # Update V7 family default-host map and V6 legacy value (SEALED stays SEALED).
    for q,meta in newstrings.items():
        decoded=before[q]
        old_family_off=old.record('family',decoded['family']['index'])
        new_family_off=old_family_off+delta_pool
        pack16(ns,new_family_off+4,meta['host_index'])
        ns[new_family_off+10]=old.u8(old_family_off+10)|2
        val=decoded['legacy_crosscheck']['values'][0]
        vo=int(val['record']['namespace_offset'],16)
        pack32(ns,vo+12,meta['ns_offset'])
        pack32(ns,vo+16,len(meta['name'].encode('utf-16le')))

    assert len(ns)<=old.raw_size
    result=bytearray(original)
    result[old.raw:old.raw+len(ns)]=ns
    pe=struct.unpack_from('<I',result,0x3c)[0]
    section_table=pe+24+struct.unpack_from('<H',result,pe+20)[0]
    apiset_hdr=None
    for i,s in enumerate(old.image.sections):
        if s[0]==b'.apiset': apiset_hdr=section_table+i*40
    assert apiset_hdr is not None
    pack32(result,apiset_hdr+8,len(ns)) # virtual size for .apiset only
    pack32(result,pe+24+64,0)
    pack32(result,pe+24+64,pe_checksum(result))
    out=bytes(result)
    updated=Schema(out)
    after={q:updated.decode(q) for q in TARGETS}
    for q,expected in TARGETS.items():
        assert after[q]['modeled_result']['host']==expected
        assert after[q]['legacy_crosscheck']['values'][0]['host']==expected
        assert after[q]['sealed'] is True
        assert after[q]['family']['fields']['flags']['value']=='0x12'
    assert updated.tables['version']['count']==old.tables['version']['count']==984
    # All legacy schemas unchanged apart from two selected Host values.
    untouched=0
    for i in range(old.legacy_count):
        e=old.legacy_entry+24*i
        flags,no,nl,hl,vo,vc=struct.unpack_from('<6I',old.data,e)
        name=old.data[no:no+nl].decode('utf-16le')
        ne=struct.unpack_from('<6I',updated.data,e)
        assert (flags,no,nl,hl,vo,vc)==ne
        if name in TARGETS: continue
        assert old.data[vo:vo+20*vc]==updated.data[vo:vo+20*vc]
        untouched+=1
    # All original hosts and importer descriptors preserved byte-for-byte;
    # all old hashed lookup tables should be unchanged except fixed relocations.
    for key in ['family','alias','version','auxiliary']:
        t=old.tables[key]; new=updated.tables[key]
        assert old.data[t['start']:t['end']]==updated.data[new['start']:new['end']] if key!='family' else True
    for key in ['alias','version','auxiliary']:
        t=old.tables[key]; new=updated.tables[key]
        assert old.data[t['start']:t['end']]==updated.data[new['start']:new['end']]
    assert old.data[old_host['start']:old_host['index']]==updated.data[updated.tables['host']['start']:updated.tables['host']['start']+count*12]
    assert old.data[old.import_start:old.size]==updated.data[updated.import_start:updated.size]
    for q in ['ext-ms-win-ntos-trace-l1-1-0','ext-ms-win-ntos-tm-l1-1-0','ext-ms-win-kernel32-errorhandling-l1-1-0']:
        assert old.decode(q,'kernel32.dll')['modeled_result']==updated.decode(q,'kernel32.dll')['modeled_result']
    report={
        'status':'OFFLINE ONLY; NOT TRUSTED FOR BOOT',
        'source_sha256':EXPECTED,'modified_sha256':hashlib.sha256(out).hexdigest(),
        'pe_bytes':len(out),'namespace_old_bytes':old.size,'namespace_new_bytes':updated.size,
        'legacy_contracts_unchanged':untouched,'legacy_contracts_total':old.legacy_count,
        'v7_version_records_preserved':updated.tables['version']['count'],
        'v7_host_records_original':count,'v7_host_records_new':updated.tables['host']['count'],
        'v7_pool_old_bytes':old.pool_size,'v7_pool_new_bytes':updated.pool_size,
        'new_host_bindings':[dict(contract=q,host=after[q]['modeled_result']['host'],sealed=after[q]['sealed'],v7_family_index=after[q]['family']['index'],v7_host_index=after[q]['default_host']['index'],legacy_v6_host=after[q]['legacy_crosscheck']['values'][0]['host']) for q in TARGETS],
        'unmodified_control_contracts':['ext-ms-win-ntos-trace-l1-1-0','ext-ms-win-ntos-tm-l1-1-0','ext-ms-win-kernel32-errorhandling-l1-1-0'],
        'limitations':['Not boot-tested','Modified original Microsoft PE no longer retains valid original signature','No trusted 26100 state separation SYS/DLL host in package','Host ABI early-load and protection unproven','No VM changes performed','Do not overwrite system ApiSetSchema.dll'],
    }
    return out,report

def main():
    src=Path(sys.argv[1]); output=Path(sys.argv[2]); rep=Path(sys.argv[3]);data=src.read_bytes()
    b,r=build(data)
    output.write_bytes(b);rep.write_text(json.dumps(r,indent=2)+'\n',encoding='utf-8')
    print(json.dumps(r,indent=2))

if __name__=='__main__': main()
