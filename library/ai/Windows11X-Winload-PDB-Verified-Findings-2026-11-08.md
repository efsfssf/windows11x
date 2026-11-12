# Windows11X — validated winload PDB findings (2026-11-08)

**Scope:** Read-only inspection of `Symbols-Direct.zip` together with the previously hash-pinned `winload.efi` samples. No changes to PoC01, host BCD, kernel, signatures or VM disks.

## 1. What was actually received

| Image | SHA-256 of PE image (verified) | PDB size | PDB format | Public `S_PUB32` symbols |
|---|---|---:|---|---:|
| Factory OS 22000.1 `winload.efi` | `9FB057CB65462624F35B41FE686F71FC03F11E3770FDB81E04900640030AD41D` | 1,486,848 B | MSF 7.00 | 9,986 |
| Windows 26100.1742 `winload.efi` | `5B47DA9E127DFD76689E0CF60D3C14149F42E93656D45843F141DC43F2B7D4A5` | 1,839,104 B | MSF 7.00 | 11,099 |

Both PDBs can be parsed as MSF 7.00 files. The `S_PUB32` symbol records were extracted directly from the PDB `SymRecordStream` identified by its DBI header, not by a simple string search.

### Identity / age caveat

| Build | PE RSDS GUID | PE age | PDB Info-stream GUID | PDB Info-stream age | PDB DBI-stream age |
|---|---|---:|---|---:|---:|
| 22000.1 | `B507952F-E1BD-A9F4-B735-EB0DBC808809` | 2 | same | 3 | 2 |
| 26100.1742 | `9927675C-FD39-CE15-D67A-27231BD80F42` | 2 | same | 4 | 2 |

The PDB **Info-stream ages do not equal the executable RSDS ages**. The DBI age in both files is 2 and matches RSDS. This might reflect symbol publishing/stripping, but *that cause has not been demonstrated*. This report does **not** assert that a default WinDbg exact-match verification succeeded. Names and section offsets below come directly from the public symbol records in the supplied PDBs.

## 2. Named internal routines (RVA from public symbols)

For these functions, `S_PUB32` specifies section 1 `.text`, with section RVA `0x1000`. The RVA shown below = `0x1000 + symbol offset`.

| Symbol | 22000.1 RVA | 26100.1742 RVA |
|---|---|---|
| `OslLoadApiSetSchema` | `0x1D764` | `0x1EAE4` |
| `OslpApplyDynamicSchemaExtensions` | `0x1D69C` | `0x1EA1C` |
| `ApiSetComposeSchema` | `0x1A01CC` | `0x249090` |
| `ApiSetpSearchForApiSet` | `0x1A0028` | `0x248EEC` |
| `ApiSetpSearchForApiSetHost` | `0x1A0118` | `0x248FDC` |
| `OslLoadOsExtensions` | `0xE414` | `0x120C4` |
| `OslIsStateSeparationEnabled` | `0x5CE8` | `0x6B14` |
| `OslpIsApiSetHosted` | `0x1D558` | `0x1E8D4` |
| `OslpDoesApiSetHostExist` | `0x1D4B8` | `0x1E830` |

These are **file image RVAs**, not runtime addresses under ASLR.

## 3. Verified caller → callee relationship

In both PE disassemblies:

- `winload 22000`: `OslpApplyDynamicSchemaExtensions` contains a direct `call` at `RVA 0x1D73E` to `ApiSetComposeSchema` at `RVA 0x1A01CC`.
- `winload 26100`: `OslpApplyDynamicSchemaExtensions` contains a direct `call` at `RVA 0x1EABE` to `ApiSetComposeSchema` at `RVA 0x249090`.

The 26100 disassembly identifies the call as `e8 cd a5 22 00` at VA `0x18001EABE`, target `0x180249090`.

## 4. Verified check of contract flags

The disassembly of `ApiSetComposeSchema` contains the following instruction sequences:

- 22000 at RVA `0x1A0354`: `mov eax, [rax]`; at `0x1A0356`: `test al, 0x1`; at `0x1A0358`: `jne` to the branch that skips adding a host for that match.
- 26100 at RVA `0x24921B`: `mov eax, [rax]`; at `0x24921D`: `test al, 0x1`; at `0x24921F`: `jne` to the skip branch.

Combined with the prior structural `.apiset` extraction:

- In Factory OS 22000, `ext-ms-win-ntos-stateseparation-l1-1-0` has EntryFlags=0 and default host `statesepext.sys`.
- In Windows 26100, the same contract has EntryFlags=1 (`SEALED`) and an empty default host.

**Conclusion supported by local code:** `OslpApplyDynamicSchemaExtensions` ultimately calls the composition routine which contains a gate on the per-entry `SEALED` bit. A routine registry API Set schema extension does not provide a proven binding path for the sealed State Separation contract in the inspected 26100 loader. This corroborates — and sharpens — the previous RVA-only research; it does not prove no other Microsoft-internal configuration could ever provide a host.

## 5. Implications for the first true WCOS 26100 boot

- `Symbols-Direct.zip` is a useful breakthrough for *static identification* of loader functions. It is not a boot fix.
- Next minimal offline question: which signed, version-coherent *26100* source can supply an active State Separation API Set host and the necessary compatible extension? This **source gate remains unresolved**.
- Any alternative binding hypothesis must be verified against **this** loader and not guessed from generic API Set documentation. Do not write or register a speculative schema extension or alter `apisetschema.dll`, `winload.efi`, kernel import pointers, signing enforcement, or `Enabled=1` in the working PoC01.
- The post-boot OSData/REG_LINK PoC remains valid but is not early kernel State Separation.

## Notes on external format semantics

PDB Info/DBI layouts and RSDS matching model: https://llvm.org/docs/PDB/PdbStream.html and https://llvm.org/docs/PDB/DbiStream.html. The term `SEALED` is based on independently extracted contract entry flags and the branch observed in `ApiSetComposeSchema`.
