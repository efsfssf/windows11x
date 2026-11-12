# Windows11X / WCOS 26100 — pre-boot binding checkpoint

Date: 2026-11-08. Scope: offline read-only examination of user-provided signed-era Windows `apisetschema.dll` copies; no VM, firmware, BCD, or system file modifications.

## Hyper-V PoC01 environment (user-reported)

- Guest Windows 26100; Gen2 Hyper-V existing VM `Windows11X-26100-PoC01`.
- Secure Boot: **On**, template `MicrosoftWindows`.
- `manage-bde -status C:`: **BitLocker Version: None; Fully Decrypted; 0.0% encrypted; Protection Off; Key Protectors: None Found**.
- This rules out a BitLocker recovery-key dependency for future Secure Boot policy experiments on C: but does **not** validate a modified boot chain or imply custom PE files will pass verification.

## Verified .apiset entries (direct PE section parser)

Source images:
- Factory OS 22000.1: `apisetschema-22000.dll` (143672 bytes).
- Windows 26100: `apisetschema-26100.dll` (152992 bytes, SHA-256 `aeaaea67b6c57b4d85cc1d7b724f2ec8fe57aa3210c7aee78b3f40e231fd70f1`).

Both images carry API Set schema version 6. 22000 has 945 entries; 26100 has 974 entries.

| Contract | 22000 index; entry flags; host | 26100 index; entry flags; host | 26100 value record offset relative to `.apiset` |
|---|---|---|---|
| `ext-ms-win-core-stateseparationext-l1-1-0` | 487; 0; `onecorestateseparationext.dll` | 495; 1 (SEALED); **none** | `0x117A8` |
| `ext-ms-win-ntos-stateseparation-l1-1-0` | 684; 0; `statesepext.sys` | 702; 1 (SEALED); **none** | `0x16908` |

In the 26100 `.apiset`, each relevant API Set value record has `HostOffset=0` and `HostLength=0`. It is **not** sufficient to change the SEALED flag: there is no actual host name to select. Assigning one requires a coherent modified namespace payload and a trusted early host module; the schema is embedded in a PE file. Editing the bytes of the existing 26100 `apisetschema.dll` would invalidate its original signature.

## Actual engineering gate before *any* boot test

A minimal host-binding experiment needs *all* of:

1. **Namespace gate:** a 26100-consistent base API Set schema mapping the two relevant contracts to the host modules without breaking the other 972 contracts.
2. **Image-trust gate:** a verified development boot path accepting the alternate base schema PE *and* the custom driver; simply disabling Secure Boot is not a proven substitute for these verifications.
3. **Phase dispatch gate:** proof that kernel Phase0/1/2 imports resolve to the host and the test returns a controlled status before enabling state separation. The existing unsigned PhaseBindingProbe is **not** a deployable solution.

These gates are currently unresolved. The first successful binding probe (if attainable) would **not yet** constitute real State Separation. Genuine WCOS requires the actual early registry/state logic and durable MainOS/OSData layout afterwards.

## Next implementation task, constrained to offline work

Design and validate a two-contract *schema delta specification* against the 26100 namespace, with invariant checking that all unrelated contracts retain the exact original entry/host semantics. Evaluate a legitimate trusted development loading mechanism before attempting to produce or deploy a modified PE image. Do not patch signed binaries, toggle Secure Boot, or enable policy in the working PoC01 on this evidence alone.
