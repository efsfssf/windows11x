# Windows 11X / WCOS — State Separation API Set comparison

Date: 2026-11-06

## Sources and verification

Binary input files:

- `apisetschema-22000.dll` — SHA-256 `49696b4557023ea455aa7731a18467b623292a7c2ec837d8437a9c2dcd9cd498`
- `apisetschema-26100.dll` — SHA-256 `aeaaea67b6c57b4d85cc1d7b724f2ec8fe57aa3210c7aee78b3f40e231fd70f1`

Both are x64 PE DLLs with `.apiset` namespace schema v6. The namespace was decoded structurally: header, entries, value records and UTF-16LE string offsets. Results were independently checked against literal UTF-16 presence of target module names.

- 22000 namespace: 945 entries, `.apiset` size 119700 bytes.
- 26100 namespace: 974 entries, `.apiset` size 127404 bytes.

## State Separation contract mappings

| API Set contract | Factory OS 22000 | Windows 26100 (IoT LTSC) |
| --- | --- | --- |
| `ext-ms-win-ntos-stateseparation-l1-1-0` | `statesepext.sys` (entry 684, flags 0) | **empty host/target** (entry 702, flags 1) |
| `ext-ms-win-core-stateseparationext-l1-1-0` | `onecorestateseparationext.dll` (entry 487, flags 0) | **empty host/target** (entry 495, flags 1) |
| `api-ms-win-stateseparation-helpers-l1-1-0` | `kernelbase.dll` (entry 354, flags 0) | contract not present at this exact revision |
| `api-ms-win-stateseparation-helpers-l1-1-1` | contract not present at this exact revision | `kernelbase.dll` (entry 357, flags 1) |

The entries have one value record each, with an empty alias/caller selector. No alternate module is named for either of the two extension contracts in the inspected 26100 file. Bitfield/flag semantics have not been established; do not interpret flags=1 as a particular runtime behavior without further evidence.

Additional cross-check: the 26100 contract `ext-ms-win-ntos-kcminitcfg-l1-1-0` has target `cmimcext.sys` (where the 22000 schema's target is empty), showing that this schema contains populated NTOS extension mappings and that an empty target is not a parsing failure.

## Correlation with earlier WinDbg runtime observations

Factory OS 22000:

```
nt!_imp_ExpInitializeStateSeparationPhase0 -> statesepext!ExpInitializeStateSeparationPhase0
nt!_imp_ExpInitializeStateSeparationPhase1 -> statesepext!ExpInitializeStateSeparationPhase1
nt!_imp_ExpInitializeStateSeparationPhase2 -> statesepext!ExpInitializeStateSeparationPhase2
```

Windows 26100, `Activation01`:

```
nt!_imp_ExpInitializeStateSeparationPhase0 -> nt!DefaultAddProfileSource
nt!_imp_ExpInitializeStateSeparationPhase1 -> nt!DefaultAddProfileSource
nt!_imp_ExpInitializeStateSeparationPhase2 -> nt!DefaultAddProfileSource
```

The 26100 fallback function contains `mov eax, 0C00000BBh; ret`, returning `STATUS_NOT_SUPPORTED`. This is observed runtime binding and is consistent with the absent API Set host.

## Conclusion and limits

**Confirmed:** In the inspected Factory OS 22000 API Set schema, State Separation kernel and user-mode extension contracts map to named extension modules. In the inspected Windows 26100 schema, the same two contracts are present but have empty target module names. Runtime WinDbg measurements from both systems agree with this difference for the kernel contract.

**Not yet confirmed:** Whether an equivalent 26100 extension build exists elsewhere; exact boot-loader logic for extension loading; 22000-to-26100 ABI/runtime compatibility; completeness of other required WCOS components; whether changing the contract mapping alone would permit a safe boot. Import-name parity alone does not prove compatibility.

**Safety:** Do not replace `apisetschema.dll`, inject the 22000 driver, or enable `StateSeparation\Policy\Enabled=1` in a bootable 26100 environment based solely on these findings. Keep existing baseline disks and Factory OS originals intact.

## Next read-only diagnostic

On the 26100 Activation01 guest, check whether the module files exist in their expected installed paths:

```powershell
$paths = @(
  'C:\Windows\System32\drivers\statesepext.sys',
  'C:\Windows\System32\onecorestateseparationext.dll',
  'C:\Windows\System32\drivers\cmimcext.sys'
)
foreach ($p in $paths) {
  [pscustomobject]@{
    File = $p
    Exists = Test-Path -LiteralPath $p
  }
}
```

If the files are absent, continue with static boot-loader and extension-loading analysis using preserved binaries and manifests, not live patching.
