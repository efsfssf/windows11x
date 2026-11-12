# Первоисточники из ответов пользователя (состояние 12.11.2026)

**Provenance:** этот файл составлен из конкретных сообщений пользователя в текущем проектном чате, не является автоматически захваченным boot log или сохранённым `.dmp`. Там, где текст сокращён, помечено. Не объединять логи разных загрузок. Не использовать addresses со старой 26100.1742 в новой 26100.9444.

## 1. Factory OS — `ntoskrnl` и `winload` 22000.1

```powershell
PS C:\USERS\FactoryUser> Get-Item C:\Windows\System32\ntoskrnl.exe, C:\Windows\System32\winload.efi | Select-Object Name, Length, @{N="Version";E={$_.VersionInfo.FileVersion}}
ntoskrnl.exe 11743560 10.0.22000.1
Get-Item: Cannot find path 'C:\Windows\System32\winload.efi' because it does not exist.
```

Поиск дал `M:\Windows\system32\Boot\winload.efi`; затем пользователь:
```
FullName                               Length   Version                  SHA256
C:\Windows\System32\ntoskrnl.exe     11743560  10.0.22000.1            A9B5C0A47F1C4606A94ADB3674017CBD35448C3792C4D26F51847A3FFA8C7B7C
M:\Windows\System32\Boot\winload.efi  2307288  10.0.22000.1            9FB057CB65462624F35B41FE686F71FC03F11E3770FDB81E04900640030AD41D
```
`Get-Volume` в FOS PowerShell не найден, поэтому использовали `mountvol`, `fltmc`.

## 2. Factory OS — `mountvol` все показанные IDs

```text
\\?\Volume{db9fb733-0f92-41e5-ab06-ff1e7ae9946a}\   NO MOUNT POINTS
\\?\Volume{dff26bef-f2aa-413f-8e45-260df4a4a141}\   B:\
\\?\Volume{8183040a-8b44-4592-92f7-c6d9ee0560f7}\   NO MOUNT POINTS
\\?\Volume{a76b8ce2-0187-4c13-8fca-8651c9b0620a}\   M:\
\\?\Volume{7aeb5015-47d7-484a-891e-0ed8d85b43fc}\   NO MOUNT POINTS
\\?\Volume{6a013b35-c42d-4d87-ae6a-57b47b70acad}\   S:\
\\?\Volume{c8adae64-47f4-4cfa-b67c-1e743ad5dfc9}\   P:\
\\?\Volume{b0479ff4-43bb-4710-90cd-20653989dd7d}\   O:\
\\?\Volume{fe296136-48c2-4b94-a83a-f4345a1eb2f3}\   C:\
\\?\Volume{aa16c266-5f58-4cf8-829f-c323cbe22ae6}\   NO MOUNT POINTS
\\?\Volume{12aef83a-6cf2-4ea1-932f-b3a586a65308}\   NO MOUNT POINTS
\\?\Volume{61266b3b-4e02-426d-8f13-d9bb82984e2f}\   NO MOUNT POINTS
```
`fltmc volumes` пользователь показал (сокращены промежуточные служебные объекты):
```text
M: \Device\HarddiskVolume5 NTFS
O: \Device\HarddiskVolume9 NTFS
C: \Device\HarddiskVolume10 NTFS
  \Device\HarddiskVolume11 NTFS
  \Device\HarddiskVolume4 NTFS
P: \Device\HarddiskVolume8 NTFS
B: \Device\HarddiskVolume3 NTFS
  \Device\HarddiskVolume2 FAT
S: \Device\HarddiskVolume7 NTFS
  \Device\HarddiskVolume6 NTFS
  \Device\VhdHardDisk{61266b3b-4e02-426d-8f13-d9bb82984e2f} NTFS
```
`fltmc instances` очень длинный вывод; **значимые строки** (оригинал приводил многочисленные FileInfo/FsDepends/Wof на остальных томах):
```text
XRo       B:  altitude 261101 instance XRo frame 0 SprtFtrs 0000000c
XRo       M:  altitude 261101 instance XRo frame 0 SprtFtrs 0000000c
wcifs     M:  altitude 189899 instance wcifs Outer Instance
bindflt   C:  altitude 409800 instance bindflt Instance
FileInfo  C:/B:/M:/S:/P:/O: altitude 40500
FsDepends C:/B:/M:/S:/P:/O: altitude 407000
Wof       C:/B:/M:/S:/P:/O: altitude 40700
```
Эти строки **не** доказывают все операции write-block/persistence, но фиксируют действительные filters и locations для одного running FOS.

## 3. Factory OS — полный символический путь OSData из PowerShell NtQuerySymbolicLinkObject

Пользователь создал C# `NtLinks.Query()` через `NtOpenSymbolicLinkObject`/`NtQuerySymbolicLinkObject` и получил:
```text
\OSDataRoot -> \Device\OSDataDevice
\Device\OSDataDevice -> \ArcName\multi(0)disk(0)rdisk(4)partition(1)
\??\O: -> \Device\HarddiskVolume9
\??\M: -> \Device\HarddiskVolume5
\ArcName\multi(0)disk(0)rdisk(4)partition(1) -> \Device\Harddisk7\Partition1
\Device\Harddisk7\Partition1 -> \Device\HarddiskVolume9
```
Следовательно **FOS** `OSDataRoot`→`O:` доказано для этой конкретной работающей VM. Это не postboot 26100.

## 4. Factory OS — все существенные секции BCD от пользователя

```text
Firmware Boot Manager
identifier              {fwbootmgr}
displayorder            {bootmgr}
                        {94f6b649-bad9-11f1-ab95-806e6f6e6963}
                        {94f6b652-bad9-11f1-ab95-806e6f6e6963}
timeout                 0

Windows Boot Manager
identifier              {bootmgr}
device                  partition=\Device\HarddiskVolume4
windowssyspart          partition=\Device\HarddiskVolume4
path                    \efi\boot\bootx64.efi
description             Windows Boot Manager
locale                  en-US
inherit                 {globalsettings}
testsigning             Yes
default                 {current}
bootsequence            {01de5a27-8705-40db-bad6-96fa5187d4a6}
timeout                 0
displaybootmenu         No
persistbootsequence     Yes

Firmware Application (101fffff)
identifier              {94f6b649-bad9-11f1-ab95-806e6f6e6963}
description             EFI SCSI Device

Firmware Application (101fffff)
identifier              {94f6b652-bad9-11f1-ab95-806e6f6e6963}
description             EFI Network

Windows Boot Loader
identifier              {311b88b5-9b30-491d-bad9-167ca3e2d417}
device                  ramdisk=[M:]\PROGRAMS\UpdateOS\UpdateOS.wim,{ramdiskoptions}
path                    \windows\system32\boot\winload.efi
description             Windows Update OS (Boot from WIM)
inherit                 {bootloadersettings}
osdevice                ramdisk=[M:]\PROGRAMS\UpdateOS\UpdateOS.wim,{ramdiskoptions}
systemroot              \windows
pae                     ForceEnable
numproc                 1
bootmenupolicy          Standard
detecthal               Yes
winpe                   Yes

Windows Boot Loader
identifier              {current}
device                  partition=M:
path                    \windows\system32\boot\winload.efi
description             MAINOS
locale                  en-US
inherit                 {bootloadersettings}
allowedinmemorysettings 0x15000075
osdevice                partition=M:
osdatadevice            partition=O:
bspdevice               partition=B:
systemroot              \windows
mfgmode                 EmptyProfile
resumeobject            {94f6b663-bad9-11f1-ab95-806e6f6e6963}
bootmenupolicy          Standard
bootstatuspolicy        IgnoreAllFailures
detecthal               Yes
debug                   Yes

Resume from Hibernate
identifier              {94f6b663-bad9-11f1-ab95-806e6f6e6963}
device                  partition=M:
path                    \windows\system32\boot\winresume.efi
description             MAINOS
locale                  en-US
inherit                 {resumeloadersettings}
allowedinmemorysettings 0x15000075
filedevice              partition=O:
associatedosdevice      partition=M:
custom:21000026         partition=O:
filepath                \hiberfil.sys
debugoptionenabled      No

Windows Boot Application (1020000a)
identifier              {01de5a27-8705-40db-bad6-96fa5187d4a6}
device                  partition=\Device\HarddiskVolume4
path                    \EFI\Microsoft\boot\mobilestartup.efi
description             Mobile Startup App
inherit                 {bootloadersettings}
recoverysequence        {311b88b5-9b30-491d-bad9-167ca3e2d417}
recoveryenabled         Yes
enablebootorderclean    Yes
enableiuloader          Yes
enableresettpm          Yes

Debugger Settings
identifier              {dbgsettings}
description             Windows Debugger Settings
busparams               1
key                     1.2.3.4
debugtype               NET
hostip                  169.254.255.255
port                    50000
dhcp                    Yes

Global Settings
identifier              {globalsettings}
inherit                 {dbgsettings}
                        {emssettings}
testsigning             Yes
extendedinput           Yes
nokeyboard              Yes
bootflow                0x0

Boot Loader Settings
identifier              {bootloadersettings}
inherit                 {globalsettings}
                        {hypervisorsettings}

Hypervisor Settings
identifier              {hypervisorsettings}
hypervisordebugtype     Serial

Setup Ramdisk Options
identifier              {ramdiskoptions}
description             Ramdisk Options
ramdisksdidevice        partition=\Device\HarddiskVolume4
ramdisksdipath          \boot\boot.sdi
```
**Note:** Отладочный `key 1.2.3.4` приведён как он содержался в вывoде (публичный лабораторный пример), не предназначен для другой VM. Доступ к SSH и другим секретам не передаётся. EMS Settings были `bootems No`.

## 5. Windows 26100 обычный guest — BCD и Object Manager (11.11)

```text
Windows Boot Loader
identifier              {current}
device                  partition=C:
path                    \Windows\system32\winload.efi
description             Windows 11
locale                  en-US
inherit                 {bootloadersettings}
recoverysequence        {79fae444-d99f-11f1-b294-e2adf96b223d}
displaymessageoverride  Recovery
recoveryenabled         Yes
testsigning             Yes
isolatedcontext         Yes
allowedinmemorysettings 0x15000075
osdevice                partition=C:
systemroot              \Windows
resumeobject            {221d2f88-d942-11f1-b7d4-d843ae62f1b6}
nx                      OptIn
bootmenupolicy          Standard
```
Нет osdatadevice/bspdevice в текущем BCD. Фактический `NtLinks26100.Query`:
```
\OSDataRoot -> \Device\OSDataDevice
\Device\OSDataDevice -> \ArcName\multi(0)disk(0)rdisk(0)partition(3)
\??\C: -> \Device\HarddiskVolume3
```
Последний `NtLinks26100.Query` для ARC и partition target не выполнен/вывод не передан, поэтому C: physical alias не формально доказан. Запрос чтения BCD/Object Manager **сам по себе ничего не подключал**.

## 6. 26100 WinDbg: ранний TraceSmoke B-DualSize (важнейшие логи 09–10.11)

После staging:
```powershell
$zip="$env:USERPROFILE\Downloads\Windows11X-TraceSmoke-26100-AB-Lab.zip"
$dst="$env:USERPROFILE\Downloads\TraceSmoke"
Expand-Archive -LiteralPath $zip -DestinationPath $dst -Force
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "$dst\Stage-TraceSmoke.ps1" -Variant B-DualSize
```
Пользователь видел:
```
The operation completed successfully.
The operation completed successfully.
STAGED - do not reboot before attaching WinDbg.
Variant: B-DualSize
BaseApiSetSchema
----------------
WCOS-TraceSmoke\ApiSetSchema.dll
```

WinDbg boot log действительно:
```
BD: Boot Debugger Initialized
Connected to Windows Boot Debugger 26100 x64 target
Windows Boot Debugger Kernel Version 26100 UP Free x64
*** Windows is unable to verify the signature of
    the file \Windows\system32\WCOS-TraceSmoke\ApiSetSchema.dll. It will be allowed to load
    because the boot debugger is enabled.
```
Пользователь поставил BP на `winload!OslpLdrExOverrideImport+0x137`, на запуске:
```
TRACE_FOUND
fffff802`33340270  "ext-ms-win-ntos-trace-l1-1-0.dll"
fffff802`333402b0  ""
winload!OslpLdrExOverrideImport+0x137:
00000000`0117ae23 e808d32500 call winload!ApiSetResolveToHost
```
После `p`:
```
winload!OslpLdrExOverrideImport+0x13c
r eax = 0
kd> dq @rbp-20 L2
... 00000000`00000000 fffff802`344ec024
kd> db @rbp+38 L1
... 01
```
`du poi(@rbp-18)` возвращало случайные Unicode glyphs; raw `dw` начинался `a42d 77f6...`, **НЕ строка Host**, потому что Length=0. `s -u poi(winload+0x354f00) L0x29c74 "WcosTraceSmoke.sys"` пуст.

**Первая загрузка A/B/C, breakpoint site RVAs точны для W26100.9444:**
```
kd> bp winload+0x23222
kd> bp winload+0x2335f
kd> bp winload+0x23375
```

A hit:
```
winload!OslLoadApiSetSchema+0x186 (winload+0x23222)
kd> dq winload+0x354f00 L1
... fffff800`77595f20
kd> dq winload+0x354ef8 L1
... 00000000`00029c84
kd> dd poi(winload+0x354f00) L10
... 00000006 00029c84 00000000 000003d8
... 00000098 0000aa30 0000001f ff080007
... 00029c84 ...
kd> dd (poi(winload+0x354f00)+0x94f0) L5
... 00000000 00000000 00000000 00029c60
... 00000024
kd> du (poi(winload+0x354f00)+0x29c60)
... "WcosTraceSmoke.sys"
```
B hit (before `call OslpApplyDynamicSchemaExtensions`):
```
winload+0x2335F
OslApiSetSchema = fffff800`77595f20, Size 0x29C84
Trace legacy HostOff=0x29C60 HostLen=0x24
```
C hit after dynamic:
```
winload+0x23375
OslApiSetSchema = fffff800`78724d20, Size 0x29C74
Trace legacy HostOff=0x1F304 HostLen=0
kd> s -u poi(winload+0x354f00) L0x29c84 "WcosTraceSmoke.sys"
(no hits)
```

**ВТОРАЯ отдельная загрузка:**
```
kd> bp winload+0x23023
kd> g
Breakpoint 0 hit winload!OslpApplyDynamicSchemaExtensions+0x4f
kd> r al
al=0
kd> dq @rbx L3
00000000`01411db0  00000000`01419a30 00000000`0141a008
00000000`01411dc0  00000000`01181f48
kd> p   ; test al,al → je empty
kd> p
kd> p
kd> du @rax
00000000`01419bf8  ""
```
Статически это связывается с `OslpDtraceExtensionEnabled` (RVA 0x22F48), таблицей dynamic Trace; **AL=0 второй загрузки нельзя переносить как runtime fact в первую A/B/C**. Debugger admission не доказывает CI без debugger.

## 7. Windows26100 postboot latest audit + off-line dual API Set

Пользователь проверил в current 26100:
```text
Computer      : DESKTOP-HSVPECH
OSDataFile    : True
OSDataMounted : True
MountTask     : False
PreviousAudit : True
```
Затем latest audit `C:\ProgramData\Windows11X\Audit\Latest.json`:
```text
ReportDate    : 2026-11-11T23:26:53.7184534+03:00
ReportBoot    : 2026-11-11T23:18:47.7863480+03:00
CurrentBoot   : 11/11/2026 11:18:47 PM
PoCScore      : 20/20
CoreEvidence  : 2/100
CriticalGates : 0/7
HiveAtAudit   : True
HiveNow       : True
TaskAtAudit   : Running
TaskNow       : False
```
**Важно:** JSON последнего аудита в current package НЕ находится; приведены реальные scalar values, которые прислал пользователь. Старый локальный файл `Audit-20261108-092614.json` — отдельно, 16/20.

Проверка текущих installed 26100 components:
```
C:\Windows\System32\drivers\statesepext.sys   Exists=False
C:\Windows\System32\onecorestateseparationext.dll Exists=False
C:\Windows\System32\drivers\XRo.sys         Exists=False
C:\Windows\System32\ssboot.exe               Exists=False
```
Current audit contracts:
```
Name                                             Sealed Host
ext-ms-win-core-stateseparationext-l1-1-0        True   [empty]
ext-ms-win-ntos-stateseparation-l1-1-0           True   [empty]
```
Все эти результаты ранее уже известны; не нужно снова спрашивать `Test-Path`/Get-AuthenticodeSignature оригинала.

**НОВЕЙШИЙ** dual V6+V7 offline check пользователя, после PowerShell Execution Policy initial block:
```
PS C:\Users\fog> cd C:\Users\fog\Downloads\wcos_new_apiset_26100
PS ...> powershell.exe -NoProfile -ExecutionPolicy Bypass -File ".\Verify-Offline-APISet.ps1"
OFFLINE FORMAT CHECK: PASS (not a boot/trust validation)
SHA256: 75FB8F895EA030C60F435863971EEB025798ABDC306958B593DBE6EB185C0BBD
Authenticode status: HashMismatch

Contract             : ext-ms-win-ntos-stateseparation-l1-1-0
V6Host               : statesepext.sys
V7Host               : statesepext.sys
SealedV6             : True
V7DefaultHostPresent : True
V7HostIndex          : 286

Contract             : ext-ms-win-core-stateseparationext-l1-1-0
V6Host               : onecorestateseparationext.dll
V7Host               : onecorestateseparationext.dll
SealedV6             : True
V7DefaultHostPresent : True
V7HostIndex          : 287

WARNING: Modified PE is not trusted for Windows early boot.
```
**Этот файл ещё не установлен и не загружался как базовая схема.** `HashMismatch` — ожидаемая недействительность исходной подписи после модификации байтов. Контрольный новый ZIP из прежнего ответа `Windows11X-APISet-26100-V6V7-OFFLINE-LAB.zip` пользователь получил, однако в текущий контейнер нового handoff этот generated ZIP не пере-монтирован. Если нужен original `.dll`, попросить пользователя прикрепить его; не создавать ложное подтверждение отсутствующего файла.
