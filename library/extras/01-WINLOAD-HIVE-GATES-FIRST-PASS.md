# W22000 vs W26100 — OslPrepareTarget DEVICES/OSBOOT first-pass static verification

Date: 2026-11-11. Source: original user-provided winload.efi PE files. Machine code re-read locally using `objdump -d -M intel` over exact PE VAs. This is not a VM runtime trace or a recovered complete CFG.

## Source identities

| File | Version | SHA256 (expected) |
|---|---|---|
| FOS22000/winload.efi | 10.0.22000.1 | `9fb057cb65462624f35b41fe686f71fc03f11e3770fdb81e04900640030ad41d` |
| 26100/winload.efi | 10.0.26100.9444 | `1c1a978448b8e0fc2284c13d6e55fde8bf9827dca432cdc8a9be66c2e729adda` |

RVA below equals PE VA - 0x180000000. Avoid confusing with .text file offsets or WinDbg relocated VA.

## Machine-verified branch points

| Function detail | W22000 RVA | W26100 RVA | Interpretation / limit |
|---|---|---|---|
| Selector validity | `0x9E38: cmp r13d, 0xFFFFFFFF; je 0x9F70` | `0x97E6: cmp r12d,0xFFFFFFFF; je 0x9912` | Entire attach path skipped when selector is -1. Selector source/meaning still needs producer audit. |
| Boolean condition | `0x9E44: test bl,bl; je 0x9F70` | `0x97F0: test r15b,r15b; je 0x9912` | False skips. Boolean origin/semantic name NOT established by these bytes. |
| Additional global gate | `0x9E4C: cmp byte [rip+0x25219E],al; jne 0x9F70` where `al=0` | `0x97F9: cmp byte [rip+0x34B8B1],bl; jne 0x9912` | Requires equality with register; cannot label policy without producer audit. |
| DEVICES attach | `0x9E9A: call 0x32D0` | `0x9846: call 0x3DA4` | Both call shared hive attach helper with selector and pointers/name/path data. |
| DEVICES attach failure | `0x9EA9: js 0x9EE7` | `0x984F: js 0x9887` | Negative status triggers error path. |
| Next candidate reads | `0x9EB8: call 0x21E4` (and retry `0x9ED2`) | `0x985F: call 0x2BB0` (retry `0x9878`) | Two fallbacks to read/resolve a registry key; exact helper semantics need verification. |
| Missing key tolerated | `0x9EDB: cmp edi,0xC0000225` | `0x987F: cmp edi,0xC0000225` | STATUS_NOT_FOUND tolerated at this stage; other negative statuses go error exit. |
| OSBOOT global gate | `0x9EF1: cmp byte [global],al; jne 0x9F70` | `0x9895: cmp byte [global],bl; jne 0x9912` | May skip OSBOOT after DEVICES processing; semantic producers unverified. |
| OSBOOT attach | `0x9F3E: call 0x32D0` | `0x98E0: call 0x3DA4` | Separate named hive attach; not automatically equivalent to DEVICES. |
| OSBOOT follow-up helper | `0x9F54: call 0x21E4` | `0x98F6: call 0x2BB0` | Called only after successful attach; `0xC0000225` again tolerated. |

Read-only dump files in this package contain nearby instructions, stack arguments, and failures. The earlier full semantic comparison independently associated the name/path objects with `DEVICES` and `\\windows\\system32\\config\\DEVICES` and likewise `OSBOOT`; this first-pass verifies callsite branches, not the full character contents of all pointer objects.

## Upstream clues (not full reconstruction)

- W22000 `0x9B5C`: `call 0x96E4` receives output pointers (one at rbp+0x67); `0x9BFB: mov r13d,[rbp+0x77]` later supplies selector before attach. W22000 `0x9E35` loads BL from `[rbp+0x67]`.
- W26100 `0x94F3: call 0x8DEC` receives output pointers; `0x97E6` checks R12D and `0x97F0` checks R15B. These values have prior producers and need full def-use validation; different register choices alone do not establish a behavior difference.
- Both paths include checks around possible setup / HHR-preparation; `OslIsHhrPrepare` function identity and precise consumer conditions need symbol+CFG corroboration. Do not assign meaning to unverified local/register based on name alone.
- The report on `StateSeparation` from 2026-11-06 saw first-chance exception `OslGetRootCell` selector `0xFFFFFFFF` in a different *experimental* 26100 boot. Similarity of sentinel does NOT demonstrate the cause of that exception or the present attach branch execution.

## What this proves / what it does not

Proves from bytes: both W22000 and W26100 have structurally comparable *conditional* two-hive attach logic; skip on invalid selector/false condition; special not-found status handling; similar attach and follow-up pattern.

Does NOT prove: the state-separation policy enables these guards, the exact BCD→guard transformation, which partition provides the bytes, that DEVICES/OSBOOT are successfully attached in this live FOS boot, that 26100 will accept FOS hive bytes, or that 26100 State Separation is enabled. Unknowns remain deliberately open.

## Next required evidence

Complete producer/consumer graph for: selector (R13D/R12D); boolean (BL/R15B); global gate; returned loaded hive ID, control set selection, attach helper source device/path; kernel loader-block hive list and CM mount. Use both original bin versions, RVA and exact instructions, no patched PE and no BCD/VM changes.
