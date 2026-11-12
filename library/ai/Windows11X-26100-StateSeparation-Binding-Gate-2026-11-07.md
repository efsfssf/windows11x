# Windows 11X 26100 — State Separation binding: verification and decision

Date: 2026-11-07. Status: research only; NO change to host, Hyper-V, registry, BCD, CI, kernel or system files.

## Objective

Authentic bootable Windows Core OS based on internally consistent Windows NT 26100 x64, with proven MainOS/OSData State Separation, split hives, write protection, servicing and recovery. A plain Windows 11 installation with an extra disk or BCD `osdatadevice` parameter is NOT success.

## Verified files and data

- Factory OS 22000.1 `statesepext.sys`: SHA-256 `167fda36a71c2aad4c146bc66ae4c83e9d525d008d6abfd8eda0e056c9a96658`, size 61,752, Authenticode valid per user's host test; `XRo.sys` signed, original source 22000.1.
- Factory OS 22000 `apisetschema.dll`: SHA-256 `49696b4557023ea455aa7731a18467b623292a7c2ec837d8437a9c2dcd9cd498`, 143,672 bytes, schema version 6, namespace flags 0, 945 entries.
- Windows 26100.1742 `apisetschema.dll` (file version 26100.1591): SHA-256 `aeaaea67b6c57b4d85cc1d7b724f2ec8fe57aa3210c7aee78b3f40e231fd70f1`, 152,992 bytes, schema version 6, namespace flags 0, 974 entries.
- Independent PE parsing confirmed **entry flags** and default host:

| Contract | Factory OS 22000: EntryFlags, host | Windows 26100: EntryFlags, host |
|---|---|---|
| `ext-ms-win-ntos-stateseparation-l1-1-0` | `0`, `statesepext.sys` | `1`, **empty** |
| `ext-ms-win-core-stateseparationext-l1-1-0` | `0`, `onecorestateseparationext.dll` | `1`, **empty** |
| `ext-ms-win-ntos-kcminitcfg-l1-1-0` | `0`, empty | `1`, `cmimcext.sys` |

- Windows 26100 has 961 entries with EntryFlags=1 and 13 with EntryFlags=0; Factory OS 22000 has 945 entries with EntryFlags=0.
- **Important:** schema-level `Flags=0` (base namespace extendible) is *not* equivalent to per-contract `EntryFlags=0`. In the known Windows 10 API Set implementation, `API_SET_SCHEMA_ENTRY_FLAGS_SEALED=0x1` blocks overrides of that specific API Set contract by registry-based schema extensions. This rule is documented in reverse-engineering research, not fully runtime-tested against this exact 26100 `winload.efi`.
- User enumerated five 26100 `ApiSetSchemaExtensions` registry entries and read `.apiset` version 6 with namespace Flags=0 in all files. Under historical semantics, a host extension should set header `API_SET_SCHEMA_FLAGS_HOST_EXTENSION=0x2`. Those registrations alone therefore do *not* prove that boot loader uses or accepts the five files. Their payload represents host-to-contract mappings (e.g. `drvsetup.dll` lists two driver-setup contracts), not an assurance of successful composition.
- The public `cmimcext.sys` 26100 import binding confirms that *some* kernel-extension mechanism survives; it does not prove `statesepext.sys` 22000 binary compatibility or that the sealed State Separation contract is patchable through an ordinary extension.
- In WinDbg, Factory OS kernel's Phase0/1/2 imports bind to loaded `statesepext.sys`. Windows 26100's corresponding IAT bindings resolve to `nt!DefaultAddProfileSource` and return `0xC00000BB` (STATUS_NOT_SUPPORTED), which the 26100 call sites explicitly tolerate. `CmStateSeparationEnabled=0` in inspected 26100. Kernel import names are present (26/26), not proof of runtime compatibility.
- Original FOS is a **composed image**. 22000 WSK manifests include `STATESEPARATION_ON` and `STATESEPARATION_DEVMODE`, and boot/state CABs include StateSeparation Enable, ReadOnlyMinifilter, SS-Boot, SplitLayoutSupport, GPT/StorageSpaces layouts. Runtime reference includes OSBOOT, DEVICES, OSDATA hives, `XRo` filter and redirect maps. None is replicated by copying a `.sys` alone.
- Windows11X-26100-PoC01: x64 Gen2 bootable 26100.1742, saved pre-test OOBE-BootOK copy, D: NTFS 16GiB OSData-STAGING, alternate BCD entry pointing osdatadevice to D:. Successful boot under that entry shows only that the configuration is tolerated when D: exists, not that the State Separation subsystem is active. Prior removal of D: led to recovery but no precise bugcheck code was captured. Activation01 prior `Enabled=1` test ended in 0x5F; precise cause remains unproved.

## Decision on proposed API Set host-extension shortcut

**Do not proceed with crafting/registering an extension pointing `statesepext.sys` to the sealed `ext-ms-win-ntos-stateseparation-l1-1-0` contract in PoC01.** Existing evidence predicts the override will be rejected/ignored under the known per-entry sealed contract rules. Signing and early kernel load introduce further requirements. Explicitly verify 26100 winload's composition behaviour before revisiting; the current data do not justify a boot experiment.

## Recommended gates, in order

1. **Source gate:** determine if an authentic 26100 WCOS-compatible State Separation extension package/schema exists within an authorized Microsoft OEM/WSK/Windows specialized image distribution. An unrelated Xbox/IoT build number does not establish that it has a portable PC-compatible component.
2. **Binding gate:** on preserved binaries, establish how the 26100 kernel extension contract can be resolved without modifying protected system files. Check whether the 26100 loader honours the per-entry sealed flag; the registry `ApiSetSchemaExtensions` route is likely blocked for this contract. No ad-hoc IAT patching or disabled signature enforcement as a project foundation.
3. **Boot state gate:** from 22000 WSK packages + exported hives, reconstruct required OSDataRoot, OSBOOT, DEVICES, SSBoot, redirect maps, XRo ordering and BCD/StorageSpaces dependencies. Verify equivalent available 26100 implementations before enabling policy.
4. **PoC gate:** only after source/binding/state gates are satisfied, test on isolated recoverable 26100 disk, leaving `STATESEPARATION_POLICY Enabled=1` off until dependency ordering is known. Observe Phase0/1/2 IAT binding, return values, redirected file/registry persistence, immutable MainOS and recovery.
5. **Fail-fast criterion:** if no compatible 26100 extension and no clean composable signed boot chain are available, label genuine WCOS 26100 port *blocked pending source/implementation*, not partially implemented via separate volume names.

## Direct sources

- User-supplied binary files and read-only research: `WCOS-ApiSet-StateSeparation-22000-vs-26100.md`, `WCOS-Statesepext-ABI-and-Loader-Audit-2026-11-06.md`, `WCOS-Winload-Kernel-Extensions-22000-vs-26100-2026-11-06.md`, `FactoryOS-22000-StateSeparation-runtime-map.json`, WSK22000 Argon feature XMLs.
- Reverse-engineered API Set per-entry SEALED semantics: https://geoffchappell.com/studies/windows/win32/apisetschema/index.htm
- API Set loader background: https://learn.microsoft.com/en-us/windows/win32/apiindex/api-set-loader-operation
- Factory OS and state feature packaging: https://learn.microsoft.com/en-us/windows-hardware/manufacture/desktop/factoryos/factory-product
- State Separation model: https://learn.microsoft.com/en-us/windows-hardware/manufacture/desktop/factoryos/state-separation

## Immediate instruction

Keep PoC01, Activation01 and Factory reference untouched. **Do not register a homemade extension, copy `statesepext.sys`/`XRo.sys` into the guest's drivers directory, replace signed `apisetschema.dll`, enable `StateSeparation\\Policy\\Enabled=1`, or enable boot-related bypass flags** on the basis of present data. The next useful work is to close source and binding gates, not to run another speculative boot.
