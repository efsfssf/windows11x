# Windows 11X Core 26100: architecture feasibility checkpoint

**Date:** 2026-11-05. **Target:** genuine WCOS-like boot/storage/state separation on a modern supported NT branch. Shell, OOBE, and Win32 containers deferred.

## Verified public evidence

1. Microsoft Factory OS uses Storage Spaces for the WCOS layout, including BS_EFISP, Virt_EFIESP, EFIESP, MainOS, OSData, Data, BSP, Preinstalled, SERVICING_METADATA and SERVICING_FILES. `MainOS` and `EFIESP` are intended read-only at runtime. Source: https://learn.microsoft.com/en-us/windows-hardware/manufacture/desktop/factoryos/disk-layout
2. Microsoft differentiates `STATESEPARATION_ON` from `STATESEPARATION_DEVMODE`: enforcement includes both immutable file areas and HKLM SYSTEM/SOFTWARE hives. Source: https://learn.microsoft.com/en-us/windows-hardware/manufacture/desktop/factoryos/state-separation
3. Factory OS image feature configuration includes `UEFI_VM`, `STORAGESPACES_LAYOUT`, `STATESEPARATION_ON`, `SERVICING_STACK`, and boot modes. Source: https://learn.microsoft.com/en-us/windows-hardware/manufacture/desktop/factoryos/factory-product
4. The kernel export `RtlIsStateSeparationEnabled` is a documented API with supported Windows versions predating Windows 10X. Its presence in an ordinary Windows binary does not establish WCOS support. Source: https://learn.microsoft.com/en-us/windows-hardware/drivers/ddi/ntddk/nf-ntddk-rtlisstateseparationenabled
5. Available Factory OS build 22000.1 is the last listed Factory OS build on BetaWiki. This is an archival inventory, not definitive proof Microsoft stopped all internal WCOS work. Source: https://betawiki.net/wiki/Windows_Factory_OS
6. Independent Xbox Series S research reports Xbox SystemOS 26100.8561 (an Xbox-specific build branch). Its host/virtualization/storage components are Xbox-specific and not evidence of a drop-in x64 PC WCOS base. The research author states that some details are inferences. Source: https://github.com/Coolaid003/XboxSeries
7. Validation OS is still maintained on the 26100 branch, but uses a different OS composition and is not documented as Factory OS/WCOS. Source: https://learn.microsoft.com/en-us/windows-hardware/manufacture/desktop/validation-os-release-notes/2610

## Current project evidence

- Factory OS 22000 VHDX exists on the user's Hyper-V host but has not been analyzed in this phase. No new claim about its binaries or registry is made.
- Windows 10X 20279 is bootable and its OOBE cannot create a user through tested local-account WinRT calls (0x80070005). This failure does not block architecture research.
- The original 20279 `ModernUXShellApp.exe` imports `RtlIsStateSeparationEnabled` from ntdll, but this is only evidence of using a state-separation query, not proof of kernel implementation on 26100.
- No verified build-26100 PC `ntoskrnl.exe`, offline registry, or complete servicing package inventory has been supplied for direct analysis at this stage.

## Next technical checkpoint (read only)

Run `Collect-WCOS-Core-26100.ps1` on a system **whose kernel actually has build 26100**, or against an already accessible 26100 Windows directory. Review resulting JSON for version, relevant drivers, registry traces, and package metadata.

Then analyze Factory OS 22000's corresponding file/package/service inventory from an already detached and safely accessible copy. Compare specifically:

- boot: BCD/Winload, virtual EFI and Storage Spaces boot support;
- storage: role-specific MainOS, OSData, Data, servicing volumes;
- state: enforced immutable filesystem and volatile SYSTEM/SOFTWARE behavior;
- deployment: feature-package composition and supported servicing;
- platform: components/WinRT activations required for a user session only *after* the core boots.

**Stop/go criterion:** Presence of a function, driver, key, or partition label is insufficient. A proof-of-concept must boot a consistent 26100-based image, route mutable writes away from MainOS, preserve the intended state after reboot, and support a documented recovery/servicing path. Until then 'genuine WCOS 26100' is unproven.

## Strategy

Use Factory OS 22000 as behavioral and layout reference; use Windows 11 26100 as a candidate supply of modern PC binaries. Use Xbox research to find mechanisms worth comparing, not to transplant protected Xbox components. Do not start by deleting Win32/Explorer or swapping `ntoskrnl.exe` across build families. If no compatible original WCOS composition is attainable, explicitly branch to a new Windows-on-NT state-separated architecture rather than calling a cosmetic approximation 'original WCOS'.
