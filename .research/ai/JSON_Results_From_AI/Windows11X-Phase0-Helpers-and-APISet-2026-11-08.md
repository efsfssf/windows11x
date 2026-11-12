# Windows11X / WCOS 26100 — Phase0 helpers and the registry API Set question

Date: 2026-11-08. **Static, read-only analysis only.** Source: user-provided original Factory OS 22000 `statesepext.sys`, plus previously verified 26100 API Set / loader research. No PoC01 or Hyper-V changes.

## Why `Session Manager\ApiSetSchemaExtensions` is not the simple answer

- An API Set entry is resolved by the loader against the API Set schema. The *default* State Separation entries in `apisetschema.dll` 26100 are hostless and have the per-entry flag `SEALED=1`.
- In the inspected 26100 `winload.efi`, `OslpApplyDynamicSchemaExtensions` calls `ApiSetComposeSchema`; disassembly finds `test al, 1` for the contract entry and a branch skipping the modification on that flag. Therefore, a conventional dynamic schema extension in `...\ApiSetSchemaExtensions` is not a verified way of adding a host to these two specific entries.
- `BaseApiSetSchema` under the same registry key has a different meaning: it changes the **name of the base schema file** loaded by `OslLoadApiSetSchema`, not a single contract. It would require a compatible trustworthy 26100 schema and a boot-loadable compatible host, neither presently available. The installed PoC01 had no `BaseApiSetSchema` value (`0 match(es)`).
- No static evidence here establishes that *all imaginable* boot-loader mechanisms are impossible. A signed, coherent new base schema or a trusted alternative integration route is a separate unresolved engineering question.
- Merely placing `statesepext.sys` on disk does not supply the missing API Set binding, nor does registering it as a conventional boot-start service implement the kernel extension mechanism.

## Exact original Phase0 function and its two helpers

Image: original `statesepext.sys` Factory OS 22000, SHA256 `167fda36a71c2aad4c146bc66ae4c83e9d525d008d6abfd8eda0e056c9a96658`.

| Node | Image RVA | Function range from PE unwind table | Confirmed behaviors / calls |
|---|---:|---:|---|
| `ExpInitializeStateSeparationPhase0` | `0x8090` | `0x8090..0x8182` | Checks `CL` and `DL`; conditionally sets `DevModeEverEnabled`; invokes both helpers. |
| helper A | `0x8644` | `0x8644..0x8B7E` | Opens the original KeyRedirectionLinks configuration, enumerates keys, queries values; uses `SourceNtPath`, `TargetNtPath`, `KernelLink`, `TreeSync`, `AlwaysEnabled`; calls more internal helpers (`0x91A4`, `0x1050`, `0x1630`, `0x17C8`). |
| helper B | `0x9344` | `0x9344..0x971C` | Opens and reads system ComputerName / ActiveComputerName / Tcpip Parameters; contains `ZwSetValueKey` calls to write `ComputerName` and `Hostname`-related values. |

### Disassembly landmarks

- Phase0 (`0x8165`) contains `call 0x8644`; at `0x816A` checks NTSTATUS; at `0x816E` calls `0x9344` only if preceding status is nonnegative.
- Helper A (`0x8680`) loads a prebuilt UNICODE_STRING (RVA `0x2060`) whose decoded value is `\Registry\Machine\System\CurrentControlSet\Control\StateSeparation\KeyRedirectionLinks`; `0x86BC` calls `ZwOpenKey` (IAT `0x5000`), `0x8702` calls `ZwEnumerateKey` (IAT `0x50A8`), and `0x883E` calls `ZwQueryValueKey` (IAT `0x5090`).
- Referenced UNICODE_STRING objects in the image, decoded rather than inferred: `0x2010 AlwaysEnabled`, `0x2020 TreeSync`, `0x2030 KernelLink`, `0x2040 TargetNtPath`, `0x2050 SourceNtPath`, `0x2060 KeyRedirectionLinks NT path`.
- Helper B at `0x93AA`, `0x951C`, `0x95CA` references the NT registry paths `ComputerName\ComputerName`, `ComputerName\ActiveComputerName`, `Services\Tcpip\Parameters`. At `0x959E` and `0x9666`, the instruction calls the `ZwSetValueKey` import (IAT `0x5010`).

These are **observed binary operations**, not yet a complete reconstruction of every branch, return status, object lifetime, or synchronization rule. `llvm-objdump` may display misleading closest-export names for these *internal* helpers: `0x8644` and `0x9344` are **not** exports of Phase2 even when disassembly annotates them under that label. The PE unwind records define the function boundaries above.

## Consequence for porting

Porting Phase0 as a no-op that returns `STATUS_SUCCESS` would bypass both the registry-redirection map setup and original setup of mutable configuration values. It would not be real WCOS State Separation.

The next strictly necessary static engineering gate is to reconstruct helper A's redirection semantics: the meanings of `TreeSync`, `KernelLink`, `SourceNtPath`, `TargetNtPath`, `AlwaysEnabled`, error handling, and the points at which it creates a kernel link or syncs a registry tree. Then compare required underlying kernel object behavior with 26100; only after that decide whether a compatible reimplementation of the early phases is technically plausible.

**Separate unresolved boot gate:** obtain a trustworthy 26100-compatible binding for State Separation phases. `ApiSetSchemaExtensions` does not solve this by itself. Do not change `BaseApiSetSchema`, boot signatures, `apisetschema.dll`, `ntoskrnl.exe`, or `Enabled=1` in the working PoC01 at this stage.

## Additional internal calls relevant to the future minimal port

- `helper A (0x8644)` directly calls internal functions `0x1050`, `0x1630`, `0x17C8`, and `0x91A4`. Their exact high-level responsibilities still need control-flow / argument analysis.
- At `0x1630..0x17C0`, the code calls `ZwOpenKey`, `ExUuidCreate`, `swprintf_s`, and `ZwRenameKey` (IAT `0x50C8`). It thus performs nontrivial registry/name operations rather than a passive map lookup.
- At `0x17C8..0x1926`, the code calls `ZwOpenKey`, `ZwDeleteKey`, `ZwCreateKey`, `ZwSetValueKey`, and `ZwClose`. The exact options and value types must be recovered before claiming that this implements a `REG_LINK` operation.
- At `0x1050..0x1197`, the code calls `ZwOpenKey`, `ZwCreateKey`, and helper `0x11A0`; a path to `0x91A4` is executed conditionally.

**Narrow next unit of work:** determine how the flags `TreeSync` and `KernelLink` select these helpers and exactly what registry object each helper creates/renames/deletes. This is the minimum part of Phase0 to reproduce correctly on a new NT branch; it is separate from the still-blocked early API Set binding.
