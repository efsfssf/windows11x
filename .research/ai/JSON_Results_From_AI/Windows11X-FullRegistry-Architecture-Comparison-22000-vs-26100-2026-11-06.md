# Windows 11X / WCOS Core - Full System Registry Comparison

**Date:** 2026-11-06  
**Sources:** User-supplied `WCOS-FullRegistry-22000.zip` (Factory OS 22000, FOS-SS-Test) and `WCOS-FullRegistry-26100.zip` (Windows 11 IoT Enterprise LTSC 2024 build 26100, Activation01).  
**Purpose:** Identify verifiable boot/registry/state-separation architecture differences before any new experimental activation.  
**Evidence:** Static `.reg` exports, `HiveList.txt` text captures, and earlier user-observed Factory OS runtime volumes. **No boot-time registry tracing is contained in these exports.**

## Executive findings

1. **Factory OS State Separation is a configuration architecture, not a single switch.** Its `SYSTEM\CurrentControlSet\Control\StateSeparation` subtree has 672 exported paths and 1,415 values. The investigated 26100 clone has just two empty keys in the corresponding subtree (artefacts of earlier experiments); no factory redirection map and no `XRo` service.
2. **The Factory OS OSBOOT hive is richer than previously established.** It includes 132 paths: 118 under `OSBOOT\SYSTEM`, eight under `OSBOOT\ControlSetOverride`, as well as other root areas. `OSBOOT\SYSTEM\CurrentControlSet` contains WMI auto-loggers, Session Manager-related entries, selected services and other persisted settings.
3. **PnP/device state is visibly separated in Factory OS.** `DEVICES\ControlSet001\Enum` has 548 exported paths while `SYSTEM\CurrentControlSet\Enum` contains only 16. Standard Windows 26100 instead has 367 paths within `SYSTEM\CurrentControlSet\Enum`. The `DEVICES` `CurrentControlSet` and `ControlSet001` presentations are largely duplicated in export and must not be counted as two independent physical collections.
4. **The factory driver database largely resides in SYSTEM, not DEVICES.** `Factory SYSTEM\DriverDatabase` has 19,365 exported paths, versus 13,559 in 26100 `SYSTEM\DriverDatabase`. The separate Factory `DEVICES\DriverDatabase` has only two paths. This supersedes any earlier suggestion that Factory OS has only a two-key driver database.
5. **OSBOOT and 26100 Feature Management are not interchangeable.** Factory `OSBOOT\ControlSetOverride\FeatureManagement\LastKnownGood\LKGConfiguration` is an empty `REG_BINARY` in the export. Windows 26100's `SYSTEM\CurrentControlSet\Control\FeatureManagement\LastKnownGood\LKGConfiguration` has 4,756 bytes and `Supported=1`. Factory `SYSTEM` has only `Supported=1` at that location.
6. **No root cause for the previous `0x5F` BSOD is established.** The earlier WinDbg exception `winload!OslGetRootCell` (AV, index `0xFFFFFFFF`) is a separate runtime observation. Registry exports do not prove that missing OSBOOT/DEVICES caused it, and no new `Enabled=1` test is recommended without a specific validated dependency hypothesis.

## Coverage and integrity

The ZIP archives pass ZIP member CRC validation. The Windows `.reg` files are UTF-16 and were parsed into normalized case-insensitive key and value names. Wrapped multiline `hex` values were joined. Two standalone quotation-mark lines in the 26100 WdBoot export were ignored because they arise from an unusual literal multiline string; the enclosing value was already captured. Counts of keys are unaffected. Values are compared by normalized exported representation (not by runtime equivalence or registry metadata).

| Source hive | Exported key paths | Exported registry values |
| --- | ---: | ---: |
| Factory OS 22000 `SYSTEM` | 32,177 | 105,919 |
| Factory OS 22000 `OSBOOT` | 132 | 452 |
| Factory OS 22000 `DEVICES` | 2,407 | 6,017 |
| Windows 26100 `SYSTEM` | 38,070 | 117,241 |

Counts include both `ControlSet001` and `CurrentControlSet` views where exported. The exports intentionally exclude `SAM`, `SECURITY`, `SOFTWARE` and user profile hives. Consequently these are **not complete machine-wide registry images**; they are complete exports of the specified hives as seen by `reg export`.

### Exact SYSTEM path overlap

| Metric | Count |
| --- | ---: |
| Paths in Factory SYSTEM | 32,177 |
| Paths in 26100 SYSTEM | 38,070 |
| Exact case-insensitive common paths | 12,433 |
| Factory-only paths | 19,744 |
| 26100-only paths | 25,637 |
| Common paths with identical exported value sets | 10,880 |

These numbers are a *structural* difference, not a direct compatibility score: the source OS builds differ, software installations differ, and Factory OS places selected runtime state outside SYSTEM.

## State Separation policy and redirection

| Component | Factory OS 22000 | Experimental Windows 26100 |
| --- | --- | --- |
| `StateSeparation\Policy\Enabled` | `DWORD 1` | Value absent after recovery |
| `StateSeparation` subtree, per ControlSet view | 672 paths / 1,415 values | 2 paths / 0 values |
| `RedirectionMap\Keys` | 465 descendants with SourcePath and TargetPath (459 direct rule children + 6 nested) | Not present |
| `RedirectionMap\Files` | 62 rule children | Not present |
| `KeyRedirectionLinks` | 81 descendants, plus the root key | Not present |
| `Services\XRo` | Present, `Start=0`, `Type=2` | Not present |
| `Services\XRo\Parameters\RegistryFilterEnabled` | `DWORD 1` | Not present |

Factory registry **key redirection target roots**, derived from the 465 rule records:

| Destination root | Rules |
| --- | ---: |
| `OSDATA` | 404 |
| `OSBOOT` | 40 |
| `DEVICES` | 19 |
| Nonstandard / other path shapes | 2 |

Representative rules from the provided Factory `SYSTEM.reg`:

```
SYSTEM\CurrentControlSet\Control\FeatureManagement
    -> OSBOOT\ControlSetOverride\FeatureManagement
SYSTEM\HardwareConfig
    -> DEVICES\HardwareConfig
SYSTEM\CurrentControlSet\Control\Nsi
    -> DEVICES\Networking\Nsi\PersistentStore
M:\Windows\System32\config
    -> O:\Windows\System32\config
```

A registry-exported map is a *declaration of redirection intent*. It does not establish which rules fire in boot/kernel/user mode, nor that copying them to another Windows release will implement the mechanism. The previous user runtime proof of MainOS write protection is separate evidence.

## OSBOOT architecture in Factory OS

| Path group | Exported paths | Interpretation |
| --- | ---: | --- |
| `OSBOOT\SYSTEM` | 118 | Selective system/runtime settings, notably WMI auto-loggers, services, Session Manager and power-related configuration |
| `OSBOOT\ControlSetOverride` | 8 | FeatureManagement, NLS, Session Manager, TimeZoneInformation |
| Other top-level OSBOOT areas | 6 | `CurrentControlSet`, RNG, WMI and root-level structures |

Unlike `DEVICES`, OSBOOT has no exported `OSBOOT\Select`. The presence of `OSBOOT\System\CurrentControlSet` shows that describing the OSBOOT hive as merely four `ControlSetOverride` branches is **incomplete**.

Factory OS runtime observations independently show that `O:\Windows\System32\config\OSBOOT` (~64 KiB) and `DEVICES` (~1 MiB) exist on OSData, and equivalent files were not found at the checked MainOS config path. The registry exports alone cannot prove that these particular files back the live hives in every boot phase.

## Feature Management differences

| Subtree or value | Factory OS 22000 | Windows 26100 |
| --- | ---: | ---: |
| `SYSTEM\CurrentControlSet\Control\FeatureManagement` exported paths | 6 | 1,929 |
| Exported values within this subtree | 3 | 5,666 |
| `SYSTEM\...\LastKnownGood\Supported` | 1 | 1 |
| `SYSTEM\...\LastKnownGood\LKGConfiguration` | Not in that SYSTEM key | 4,756 bytes |
| `OSBOOT\ControlSetOverride\FeatureManagement\LastKnownGood\LKGConfiguration` | Empty REG_BINARY | OSBOOT absent |
| `SYSTEM\...\FeatureManagement\FconPresent` | Not present at this key | 1 |

Modern 26100 has a substantially different and much larger native Feature Management state. A `winload` stack through `FsepOpenFeatureManagementRootSubKeys` cannot independently identify whether the failing missing/invalid hive index concerns OSBOOT, SYSTEM, or a different initialization bug.

## Device registry distribution and comparisons

| Area | Factory OS 22000 | Standard 26100 |
| --- | ---: | ---: |
| Factory `DEVICES\ControlSet001\Enum` / 26100 `SYSTEM\CurrentControlSet\Enum` | 548 | 367 |
| Factory `SYSTEM\CurrentControlSet\Enum` | 16 | Not applicable as a separate 26100 hive |
| Factory `DEVICES\HardwareConfig` / 26100 `SYSTEM\HardwareConfig` | 13 | 13 |
| Factory `DEVICES\Networking` | 412 | Not a single corresponding 26100 subtree |
| Factory `DEVICES\DriverDatabase` / 26100 `SYSTEM\DriverDatabase` | 2 | 13,559 |
| Factory `SYSTEM\DriverDatabase` | 19,365 | 13,559 |

There are **167 common relative descendant paths** between Factory `DEVICES\ControlSet001\Enum` and Windows 26100 `SYSTEM\CurrentControlSet\Enum`; 123 of these have matching exported value sets. Including both `Enum` root entries yields 168 common paths. This is a useful indicator of compatible PnP naming conventions, **not** proof that live device redirection already works in 26100.

Factory `DEVICES\Networking` also contains NSI and other persisted network state. Dynamic network IDs, per-VM device identity and version differences mean a literal value-by-value transfer is not a valid migration procedure.

## Boot and services

| Metric | Factory OS 22000 | Windows 26100 |
| --- | ---: | ---: |
| Service subkeys under `SYSTEM\CurrentControlSet\Services` | 449 | 718 |
| Services with explicit `Start=0` | 55 | 97 |
| Service `XRo` | Present; boot start | Absent |
| `spaceport` | Start 0 | Start 0 |
| `storvsc` | Start 0 | Start 0 |
| `vmbus` | Start 0 | Start 0 |
| `wof` | Start 0 | Start 0 |
| `bindflt` | Start 2 | Start 2 |
| `wcifs` | Start 2 | Start 2 |
| `UnionFS` | No corresponding factory service key | Start 2 |

The standard drivers that both OS builds share are necessary parts of a contemporary Windows storage/runtime stack, but their existence does not activate WCOS State Separation.

`SYSTEM\CurrentControlSet\Control\Session Manager`:

| Value | Factory OS 22000 | Windows 26100 |
| --- | --- | --- |
| `BootExecute` | `autocheck autochk *` plus `FirstBootTasks` | `autocheck autochk *` |
| `SetupExecute` | `SSBoot` | Empty `REG_MULTI_SZ` |
| `AppcontainerUserSeparation` / `InteractiveUserSeparation` | Previously observed as enabled in Factory OS | Previously observed as disabled in 26100 |

The first two rows were verified again from these full `.reg` archives; the user-separation row is carried forward from previously gathered evidence and is not itself newly established by the displayed Session Manager root values.

## HiveList caveat

The Factory HiveList snapshot explicitly lists `OSDATA`, `SAM` and `SECURITY` on a Windows config path on `HarddiskVolume9` and `SOFTWARE` on `HarddiskVolume5`. The Windows 26100 snapshot lists `SYSTEM`, `SOFTWARE`, `SAM` and `SECURITY` on `HarddiskVolume3`. **The Factory snapshot does not enumerate OSBOOT or DEVICES despite both being accessible through HKLM and having corresponding files on OSData.** Do not infer from this that those hives are absent or not loaded. `HiveList` on its own is not a complete proof of boot-phase hive lifecycle.

## Relevance to the previous WinDbg failure

The earlier live kernel/boot debugging of Activation01 provided an exception at `winload!OslGetRootCell+0x10` with `rdx=0xFFFFFFFF` during `FcPalOpenWellKnownRegistryKey` -> `FsepOpenFeatureManagementRootSubKeys` -> `FseInitializeFeatureConfigurationTable`. The repeated first-chance `0xC0000005` messages were observed after explicitly enabling `StateSeparation\Policy\Enabled` in a disposable 26100 clone.

The registry comparison provides a credible list of **missing architectural dependencies**, but no direct causal tie to that exception or to the separately observed `SECURITY_INITIALIZATION_FAILED (0x5F)` stop. Because an ordinary 26100 configuration boots successfully, any renewed experiment must first distinguish the debugger's first-chance exception policy, Feature Management errors, boot-hive availability and State Separation policy effects. Avoid repeat activation by policy alone.

## Ranked next actions

1. **Static boot dependency map (no new guest modification):** Combine existing Factory OS BCD details (`osdatadevice`, `bspdevice`, boot loader path), StateSeparation redirection targets (`OSDATA`, `OSBOOT`, `DEVICES`), and component inventories (`XRo.sys`, `ssboot.exe`, `FirstBootTasks.exe`) in one cross-version matrix. Confirm which items actually exist in the 26100 IoT WIM and where their functionality is implemented.
2. **Establish live hive origin on FactoryOS (read-only observation):** Identify runtime native registry objects for `OSBOOT`, `DEVICES`, `OSDATA`, plus backing file mapping and which boot stage loads each. File existence alone is not a boot-order trace. Keep FOS original untouched; work on FOS-SS-Test.
3. **Compare the 22000/26100 boot loader's handling of missing hive selectors:** Prefer disassembly, symbols and read-only tracing before introducing any new hive or redirect rule. The invalid selector `0xFFFFFFFF` is a hypothesis to investigate, not an assumed OSBOOT identifier.
4. **Define a minimal internally consistent boot proof-of-concept**, only after dependencies 1-3 are grounded. Preserve the 26100 immutable BootOK parent and recovered Activation01 backup. Measure functional outcomes: early boot, protected MainOS, persistent OSData+registry, service/update/recovery integrity.

## Boundaries and data handling

- The four `.reg` exports do **not** include `HKLM\SOFTWARE`, `HKLM\SAM`, `HKLM\SECURITY`, nor user hives. No attempt was made to access confidential credential storage.
- SYSTEM still contains installation-specific device IDs, network configuration, mounted volume identifiers and possibly security-relevant configuration. Share the original ZIPs only within a trusted environment; the present report deliberately avoids reproducing those details.
- Registry keys/values and paths are evidence of *configuration*, not evidence that a driver/filter/redirect actually executed.
- The 22000 and 26100 images differ in purpose and servicing content, so larger/smaller counts do not indicate correctness or infer a transferable configuration.

## Source archive checksums (SHA-256)

```
7333fffab2c838d53c9f029251e4af3f8f4cf8a029e70a342b6a19df44354177  WCOS-FullRegistry-22000.zip
8bc73fddb85ff403f72c761eb34c540033c203050728c99b6feaf0125951ab66  WCOS-FullRegistry-26100.zip
```

**Bottom line:** The data strongly supports true multi-hive, redirected and protected-state architecture in Factory OS 22000, and an ordinary monolithic SYSTEM registry layout in the 26100 experimental boot. It does **not** yet show a functional 26100 WCOS. The next task is to map missing boot-phase components rather than copy a registry hive or flip State Separation policy.
