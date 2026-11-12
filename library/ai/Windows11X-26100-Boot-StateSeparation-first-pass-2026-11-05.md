# Windows 11X / WCOS 26100: начало сравнения State Separation
Date: 2026-11-05. Source: user-provided WSK 22000 CAB + 26100 binaries. Read-only static analysis.

## Import-by-name compatibility: XRo.sys 22000 vs ntoskrnl.exe 26100

- XRo import count from ntoskrnl.exe: **62**.
- Matched in ntoskrnl.exe 26100 export table: **62**.
- Missing: **none**.
- XRo imports from FLTMGR.SYS: **21**, not checked against 26100 yet.
- Warning: export-name match alone is insufficient to claim driver binary/runtime compatibility.

## XRo required FLTMGR.SYS imports (pending 26100 export check)

- `FltStartFiltering`
- `FltGetVolumeFromFileObject`
- `FltAttachVolume`
- `FltGetFilterFromName`
- `FltGetFileSystemType`
- `FltReleaseFileNameInformation`
- `FltGetFileNameInformation`
- `FltGetDestinationFileNameInformation`
- `FltParseFileNameInformation`
- `FltSetCallbackDataDirty`
- `FltIsVolumeWritable`
- `FltGetDiskDeviceObject`
- `FltSetInstanceContext`
- `FltGetVolumeFromInstance`
- `FltReleaseContext`
- `FltGetVolumeGuidName`
- `FltObjectDereference`
- `FltAllocateContext`
- `FltUnregisterFilter`
- `FltGetInstanceContext`
- `FltRegisterFilter`

## 26100 kernel: State Separation UTF-16 references (selected)

- `\OSDataRoot\Windows\System32\config\DEVICES`
- `\OSDataRoot\Windows\bootstat.dat`
- `\Registry\Machine\OSDATA\System\CurrentControlSet\Control\LiveDump`
- `\Registry\Machine\OSDATA\System\CurrentControlSet\Control\CrashControl`
- `System\CurrentControlSet\Control\StateSeparation\PnP\DriverHiveOverlays`
- `\OSDataRoot`
- `\Registry\Machine\System\CurrentControlSet\Control\StateSeparation\RedirectionMap\Keys`
- `\Registry\Machine\System\CurrentControlSet\Control\StateSeparation\RedirectionMap\Files`
- `StateSeparation\Policy`
- `\OSDataRoot\Windows\`
- `\REGISTRY\MACHINE\OSDATA\Notifications`
- `\OSDataRoot`
- `\Device\OSDataDevice`
- `\OSDataRoot`
- `System\CurrentControlSet\Control\StateSeparation\PnP\ResetDevices\Classes`
- `System\CurrentControlSet\Control\StateSeparation\PnP\ResetDevices\Services`
- `\OSDataRoot\Windows\`
- `\REGISTRY\MACHINE\OSDATA`
- `StateSeparation\Policy`
- `CurrentControlSet\Control\StateSeparation\RedirectionMap`

## 26100 kernel: State Separation API-set imports

- `ExpInitializeStateSeparationPhase1`
- `ExpInitializeStateSeparationPhase0`
- `ExpInitializeStateSeparationPhase2`

## 26100 winload.efi selected UTF-16 references

- `StateSeparation`
- `\OsDataRoot\`
- `CurrentControlSet\Control\StateSeparation\RedirectionMap`

## Factory OS 22000 ssboot.exe details

- PE subsystem: `00000001	(NT native)` (native NT).
- Imports from `ntdll.dll`: **167** functions. Note native `ssboot.exe` is not an ordinary user-facing CMD tool.
- Its static UTF-16 strings include OSData, bind mappings, settings merge, flush and persisted registry pending metadata; call order / usage not proven.

- `%OSDataDrive%\Users\System`
- `%OSDataDrive%\SystemData`
- `%OSDataDrive%`
- `\Registry\Machine\OSDATA\CurrentControlSet\Control\SSBoot`
- `\Registry\Machine\OSDATA`
- `\Registry\Machine\OSDATA\CurrentControlSet\Control\SSBoot\Directory_Source`
- `\Registry\Machine\OSDATA\CurrentControlSet\Control\SSBoot\Directory_Destination`
- `\Registry\Machine\OSDATA\CurrentControlSet\Control\SSBoot\File_Source`
- `\Registry\Machine\OSDATA\CurrentControlSet\Control\SSBoot\File_Destination`
- `\Registry\Machine\OSDATA\CurrentControlSet\Control\SSBoot\SDDL_Source`
- `\Registry\Machine\OSDATA\CurrentControlSet\Control\SSBoot\SDDL_Destination`
- `\Registry\Machine\OSDATA\CurrentControlSet\Control\SSBoot\Attribute_Source`
- `\Registry\Machine\OSDATA\CurrentControlSet\Control\SSBoot\Attribute_Destination`
- `\Registry\Machine\OSDATA\CurrentControlSet\Control\SSBoot\Error_Origination`
- `OSDataDisk`
- `\REGISTRY\MACHINE\OSDATA\CurrentControlSet\Control\SSBoot`
- `%OSDataDrive%\Windows\FirstBoot.Complete`
- `\Registry\Machine\OSBOOT\ControlSetOverride\StateSeparation\Policy`
- `SS_BOOT_EXTEND_SPACE_POOL`
- `SS_BOOT_EXTEND_DATA_VOLUME`
- `SS_BOOT_PROVISION_DATA_PARTITION`
- `SS_BOOT_PROPAGATE_SECURITY_DESCRIPTORS`
- `SS_BOOT_BIND_MAPPINGS_FAILED`
- `SS_BOOT_CREATE_BOOT_DONE_FILE`
- `SS_BOOT_FLUSH`
- `SS_BOOT_EXTEND_MUTABLE_SPACES`
- `SS_BOOT_MERGE_SETTINGS`
- `SS_BOOT_CREATE_SYMLINK`
- `SS_BOOT_CREATE_JUNCTION`
- `SS_BOOT_REFRESH_LANGUAGES`
- `SS_BOOT_PERFORM_ACTIONLIST`
- `SS_BOOT_RESERVE_MIGRATE_WU_STORE`
- `SS_BOOT_FINISHED`
- `\REGISTRY\MACHINE\OSData\Software\Microsoft\PolicyManager\providers\51BF5DBF-9EAE-45BB-998E-AFB0E32E0E5F\default\device`
- `\REGISTRY\MACHINE\OSData\Software\Microsoft\Enrollments\51BF5DBF-9EAE-45BB-998E-AFB0E32E0E5F`
- `\ProvisionalMetadata\PersistedBindMappings.bin`
- `ProvisionalMetadata\PersistedRegistryPending.xml`
- `\ProvisionalMetadata\PersistedRegistryPending.xml`
- `\Registry\Machine\SYSTEM\CurrentControlSet\Control\StateSeparation\RedirectionMap\Files`
- `\Registry\Machine\SYSTEM\CurrentControlSet\Control\StateSeparation\RedirectionMap\Keys`

## Current runtime functional evidence (FOS-SS-Test)

- `M:` direct file write rejected: `The media is write protected`.
- `C:\ProgramData\WCOS_PERSIST_PROBE.txt` survives reboot.
- `HKLM\SOFTWARE\UnitTest\PersistedRegistryValue\WCOS_SourceProbe` existed in current session, disappeared after reboot.
- Direct write to `HKLM\OSDATA\SOFTWARE\UnitTest\PersistedRegistryValue\WCOS_DirectProbe` survives reboot.
- The named `RedirectionMap` rule exists but did not automatically redirect the probe; reason unknown.

## Next read-only step

Obtain from Windows 11 IoT LTSC 26100 WIM index 2 (read-only mount):

- `Windows\System32\drivers\fltmgr.sys`
- `Windows\System32\ntdll.dll`

Compare 21 XRo FLTMGR imports and 167 ssboot ntdll imports; these are symbol compatibility checks, not a green light to install 22000 drivers.

## Safety guardrails

- Do not mutate WIM/BCD/BootManager/CI settings or install the 22000 driver into a 26100 system.
- Original `FOS` must remain untouched. `FOS-SS-Test` is the explicitly reported testing VM.
- Do not use file name, registry keys, or partition names as proof of modern WCOS boot.
