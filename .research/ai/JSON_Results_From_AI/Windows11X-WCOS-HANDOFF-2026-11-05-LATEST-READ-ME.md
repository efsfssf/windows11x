# Windows 11X / настоящая PC WCOS 26100 — ОБНОВЛЁННЫЙ ПОЛНЫЙ HANDOFF

**Снимок:** 2026-11-05. **Преемственность:** этот пакет ДОПОЛНЯЕТ и ИСПРАВЛЯЕТ архив `Windows11X-WCOS-2026-11-05-COMPLETE-HANDOFF.zip` (78 файлов; вложен в `sources/new_since_prior_handoff/`). **Статус:** предварительное исследование оценивается пользователю примерно в **70%** перед **первым PoC**. Это субъективный ориентир, **не измерение**, и **не** 70% готовой ОС. PoC 26100 **ещё НЕ создан и НЕ загружался**.

## Порядок чтения для другого ИИ

1. Прочесть **этот документ полностью**, затем `notes/NEW_EXPERIMENTS_AND_CONSOLE_LOGS.md`, `notes/NEXT_SAFE_ACTION.md`, `notes/INFERENCES_VS_FACTS.md`.
2. Открыть `derived/COMPLETE_HANDOFF_CURRENT.json` (в нём вложен прежний полный 769-КБ handoff, карта State Separation, разбор всех 14 BCD объектов и актуальные факты), `derived/BCD_ALL_OBJECTS.json`, `derived/REGISTRY_FORENSIC_SUMMARY.json`.
3. **При необходимости извлечь** вложенный прежний ZIP: он содержит всю предыдущую базу WSK/FactoryOS/20279/26100, логи и скриншоты. Не удалять архивы и не считать их дубликатами без проверки хэшей.
4. Сопоставлять утверждения с первичными `.hiv`, `.reg`, WIM/PE ZIP и логами из `sources/`. Файл `SHA256SUMS.txt` содержит контрольные суммы. Расширения `*.hiv` — бинарные REGF, **не текст**. Для осторожного чтения приложен `tools/wcos_hive_readonly.py` (не изменяет кусты, не воспроизводит транзакционные .LOG-файлы).
5. **НЕ опираться только на старый `START_HERE_COMPLETE_HANDOFF_RU.md`:** он заморожен **до** теста стойкости OSDATA, новых кустов, сравнения SMSS/Winload и BCD. Ниже есть раздел исправлений.

## 0. НЕИЗМЕННАЯ ЦЕЛЬ И ПРАВИЛА

Windows 11X должна быть **настоящей загружаемой современной Windows Core OS на x64 ПК**, основанной на согласованных системных компонентах Windows NT **26100 (Germanium)** и впоследствии обновляемой на новые ветки. **НЕ** обычная Windows 11 26100 с темой, изменёнными именами томов или отключёнными службами. Архитектурный эталон — **Factory OS 22000.1 / Windows 10X 20279**, но эти продукты нельзя механически переносить на 26100.

Критерии: Hyper-V Gen2 boot; реальная защищённость MainOS, отдельный сохраняемый OSData/Data; необходимое разделение реестра, включая OSDATA/OSBOOT/DEVICES; соответствие BCD/схемы хранилищ и ранней загрузки; reboot persistence; работающий servicing/update/recovery; по возможности отсутствие SysWOW64 и обычного Win32-хоста. **Позже**, после WCOS Core: UWP/WinUI, Santorini/Composer из 20279, OOBE, VAIL/Argon Win32-контейнер, возможно Win32 изоляция. **Не начинать с оболочки**.

Проверять не только наличие строк, имён импортов, ключей или названий разделов, но и **реальное поведение в загрузившемся PoC**. Все эксперименты на **отдельной копии/новом VHDX**, не менять оригинал Factory OS/20279, не записывать в mount 26100 WIM. Пользователь предпочитает **по одному безопасному шагу**, аккуратные CMD/PowerShell с указанием, где исполнять, и **один большой отчёт после большого этапа**, а не мини-отчёты.

## 1. РАБОЧИЕ СИСТЕМЫ И РАСПОЛОЖЕНИЕ ФАЙЛОВ

### Основной хост (пользовательская Windows 11 x64 / Hyper-V)

- WSK 22000.1: `E:\DISK D\FOS\WINDOWS_SYSTEM_KIT_co_release_22000_210604-1628` (в пути **пробел**).
- Factory OS Argon OEMInput: `E:\DISK D\FOS\FactoryWin32\FactoryOS_win32_manufacturing_AMD64_UEFI_SpacesGPT_VM.xml`.
- Существующий оригинальный образ: `E:\DISK D\FOS\FactoryOS_win32_manufacturing_AMD64_UEFI_SpacesGPT_VM.vhdx` и связанный `.avhdx`; **не монтировать, не сливать differencing chain и не изменять**.
- Оригинальная VM `FOS`; для экспериментальных тестов пользователь **подтвердил клон `FOS-SS-Test`**. В госте клона имя компьютера осталось `FactoryOS-3Q3P6`: SSH prompt **не определяет**, какую VM подключили; IP сверять с Hyper-V.
- **Актуальный IP тестовой VM:** `172.18.69.72`; прежний IP `172.18.72.222` **устарел**. SSH: `FactoryUser@172.18.69.72`. Пароль пользователь знает и ранее вводил интерактивно; **не хранится в этом handoff, не помещать в команды**.
- Архивы на хосте: `E:\WCOS\Logs\WCOS-BootChain-Compare.zip`, `E:\WCOS\Logs\WCOS-KernelBoot-22000.zip`, `E:\WCOS\Logs\BCD-FOS-22000.hiv`, кусты ранее выгружены в `E:\WCOS\Logs\RegistryDump\` (точная локальная структура зависит от SCP).
- Windows 11 IoT Enterprise LTSC 2024 x64 ISO: `E:\WCOS\21600\en-us_windows_11_iot_enterprise_ltsc_2024_x64_dvd_f6b14814.iso`. В WIM index **2** = IoT Enterprise LTSC, index 1 Enterprise LTSC, index 3 IoT Subscription LTSC. WIM index 2 ранее монтировался **только для чтения**: `E:\WCOS\Mount\26100-IoT-LTSC`; не считать текущий mount 100% актуальным до проверки DISM.
- На WIM 26100: `Windows\System32\ntoskrnl.exe`, `Windows\System32\smss.exe`, `Windows\System32\winload.efi`. `FirstBootTasks.exe` по `...\System32\FirstBootTasks.exe` **не найден**. `XRo.sys` и `ssboot.exe` в ожидаемых путях WIM/WinSxS не обнаружены (по предыдущей проверке). Ветки 26100 содержат `spaceutil.exe`, `spaceport.sys`, `wcifs.sys`, `bindflt.sys`, `UnionFS.sys`, `usermgr.dll` и др.
- Ранее у `ntoskrnl.exe` WIM зафиксирована file-version **26100.1742**, у отдельных других PE может быть servicing revision **26100.1** — не считать весь набор одним file-version; SHA-256 важнее.

### Гость FactoryOSArgon 22000.1

- Shell через SSH — **WCOS host**, `ver=10.0.22000.1`, default shell `C:\Users\FactoryUser>`; НЕ путать с `cmd.exe` внутри VAIL/Argon Win32 контейнера.
- `%SystemDrive%=M:`, `%DataDrive%=C:`, `%ProgramData%=C:\ProgramData`.
- Тома: `M:` MainOS = `\Device\HarddiskVolume5` NTFS (серийный `D008-9B51`); `O:` OSData = `\Device\HarddiskVolume9` NTFS (`6C08-EEE1`); `C:` Data = `\Device\HarddiskVolume10` NTFS (`3409-06C0`); `B:` BSP = `\Device\HarddiskVolume3` NTFS (`E808-B807`); `S:` SERVICING_FILES = `\Device\HarddiskVolume7` NTFS (`8809-4955`); `P:` PreInstalled = `\Device\HarddiskVolume8` NTFS (`4608-D21E`). Другие без букв.
- Для Win32 Argon/VAIL контейнера на **оригинальном FOS** понадобилось `Set-VMProcessor -VMName FOS -ExposeVirtualizationExtensions $true`; после nested virtualization появился Win32 cmd 22000.1. Не переносить вывод о контейнере на host.
- Host минимальный: некоторых программ `diskpart.exe`, `powershell.exe`, `whoami.exe` нет; это нормально, само по себе не доказывает архитектуру.

## 2. ПОДТВЕРЖДЁННАЯ STATE SEPARATION И КОНТРОЛИРУЕМЫЕ ТЕСТЫ

**Runtime на FOS-SS-Test:** `HKLM\SYSTEM\CurrentControlSet\Control\StateSeparation\Policy\Enabled=1`. Служба `XRo`: `Start=0` (`BOOT_START`), `ImagePath=system32\drivers\XRo.sys`, `DependOnService=FltMgr`, Group `FSFilter Content Screener`, Type=2, `RegistryFilterEnabled=1`, `DisableExemptKeys=1`. `fltmc instances -f XRo` показал attach к **M: MainOS и B: BSP**, altitude **261101**, frame 0; не показал attach к O:/C:; ранее также видны `bindflt`, `wcifs`, `Wof`.

**Проверка физической защиты:** попытка `echo WCOS_TEST > M:\WCOS_XRO_PROBE.txt` -> `The media is write protected.` Это функционально подтверждает отказ конкретной записи на MainOS, согласующийся с XRo/дополнительной политикой; **точная причина отказа не изолирована** (возможна и политика тома). `echo WCOS_TEST > C:\ProgramData\WCOS_PERSIST_PROBE.txt`, затем `type` -> `WCOS_TEST`; после нескольких graceful reboot файл остался (Data persistence).

**Файловое перенаправление:** `M:\Data` — NTFS mount-point reparse tag **0xA0000003**, target `\??\Volume{fe296136-48c2-4b94-a83a-f4345a1eb2f3}\` ранее сопоставлен с C: Data. В **бинарном SYSTEM.hiv** `HKLM\SYSTEM\WCOSJunctions` хранит `M:\Data = \DosDevices\C:`. **Осторожно:** `dir M:\winload.efi /s /b` породил многократные пути `M:\Data\Data\Data\...\Windows...` через этот цикл. **Не сканировать рекурсивно корень M: или M:\Data**; использовать абсолютные пути.

**Гранулярная registry map:** в SYSTEM `ControlSet001\Control\StateSeparation\RedirectionMap\Files` **62 subkeys** (37 targets OSDATA, 25 M:\Data); `...\Keys` **459** (400 OSDATA, 40 OSBOOT, 19 DEVICES); `KeyRedirectionLinks` **81** (78 OSDATA, 3 OSBOOT). Эти counts подтверждены непосредственным чтением SYSTEM.hiv и предыдущим полным распарсенным JSON `FactoryOS-22000-StateSeparation-runtime-map.json`. В XRo `Parameters\RegistryWhiteList` **84 значения**; `Volumes` subkeys `EFIESP`, `SYSTEM`. Некоторые правила test-only/неоднозначные: `PersistedRegistryValueTest`, `InstalledColorProfiles` с путями M:/O: внутри `Keys`. **Нельзя переносить все правила на 26100 без анализа и доказательства исполнения.**

**Точный эксперимент с реестром и перезагрузкой на клоне:**
1. `reg add "HKLM\SOFTWARE\UnitTest\PersistedRegistryValue" /v WCOS_SourceProbe /t REG_SZ /d SOURCE_TEST_26100 /f` -> SUCCESS; запрос в обычном HKLM\SOFTWARE **перед reboot** показывает value; запрос `HKLM\OSDATA\SOFTWARE\UnitTest\PersistedRegistryValue` для этого value -> NOT FOUND.
2. `reg add "HKLM\OSDATA\SOFTWARE\UnitTest\PersistedRegistryValue" /v WCOS_DirectProbe /t REG_SZ /d DIRECT_OSDATA_TEST /f` -> SUCCESS; запрос в HKLM\OSDATA **перед reboot** показывает value; в HKLM\SOFTWARE тот же value не найден.
3. После graceful reboot: `WCOS_SourceProbe` в HKLM\SOFTWARE **отсутствует**; `WCOS_DirectProbe` в HKLM\OSDATA **присутствует** (`REG_SZ DIRECT_OSDATA_TEST`); в HKLM\SOFTWARE `WCOS_DirectProbe` **отсутствует**; файл `C:\ProgramData\WCOS_PERSIST_PROBE.txt` сохранился.
4. **Независимая проверка офлайн-файлами:** `SOFTWARE.hiv` содержит `UnitTest\PersistedRegistryValue\TestDefault="Default Test Data"`, **нет** `WCOS_SourceProbe`; `OSDATA.hiv` содержит `SOFTWARE\UnitTest\PersistedRegistryValue\WCOS_DirectProbe="DIRECT_OSDATA_TEST"`. Значит обычный SOFTWARE может быть недолговечным в такой конфигурации, OSDATA persistent, а **именно правило тестовой автоматической редирекции не проявилось**. Не утверждать, что вся автоматическая redirection не работает, и не утверждать, что каждое значение SOFTWARE volatile без теста.
5. Самый первый `WCOSProbe=Persistent` был записан до полноценной проверки и его судьба не установлена; более поздний контролируемый тест выше **заменяет прежний неопределённый вывод**.

## 3. НОВЫЕ 5 КУСТОВ РЕЕСТРА: ПОДРОБНЫЕ ФАКТЫ

Переданы полные raw `SYSTEM.hiv` (**9 764 864 байт**), `SOFTWARE.hiv` (**33 882 112 байт**), `OSDATA.hiv` (**843 776 байт**), `OSBOOT.hiv` (**65 536 байт**), `DEVICES.hiv` (**983 040 байт**); дополнительно `StateSeparation.reg`, `XRo.reg`, `SessionManager.reg` (UTF-16LE BOM), `HiveList.txt` и отдельно `BCD-FOS-22000.hiv` (**32 768 байт**). Это результаты `reg save` **с работающей системы**, а не прямые байт-копии original hive files; имена корней могут отображаться как `ROOT`, а некоторые runtime-mounted namespace и volatile поля не гарантированно попадают в save. У всех шести hive SIG `regf`, sequence1=sequence2, см. `derived/REGISTRY_FORENSIC_SUMMARY.json`.

### SYSTEM.hiv / XRo / State Separation

- Root `ROOT`: `ControlSet001`, `DriverDatabase`, `MountedDevices`, `WCOSJunctions`, `Setup`, `Select`, `Software`, `StateSeparationBVT` и др.
- `ControlSet001\Control\StateSeparation` имеет разделы: `KeyRedirectionLinks`, `PnP`, `Policy`, `RedirectionMap`, `SecurityTemplates`. `Policy\Enabled=1`. `RedirectionMap\Files=62`, `Keys=459`, `KeyRedirectionLinks=81` (и value `SparseMap=1`).
- `ControlSet001\Services\XRo`: Start=0, Type=2, ErrorControl=1, Group=`FSFilter Content Screener`, ImagePath=`system32\drivers\XRo.sys`, DependOnService=`FltMgr`, `Parameters\RegistryFilterEnabled=1`, `DisableExemptKeys=1`, `RegistryWhiteList`=84 values, `Volumes` subkeys EFIESP/SYSTEM. Сами 84 имени whitelist в `derived/REGISTRY_FORENSIC_SUMMARY.json` и original `XRo.reg`/SYSTEM.hiv.
- `ControlSet001\Services\spaceport`: `Start=0`, Group=`System Bus Extender`, ImagePath=`System32\drivers\spaceport.sys`; **наличие драйвера и включение в boot не доказывают физическую роль Storage Spaces без других данных**.
- `ControlSet001\Control\Session Manager`: `BootExecute` REG_MULTI_SZ `autocheck autochk *` + `FirstBootTasks`; `SetupExecute` REG_MULTI_SZ `SSBoot`. Раздел `NamespaceSeparation`: `AppcontainerUserSeparation=1`, `InteractiveUserSeparation=1`.
- `WCOSJunctions`: `M:\Data=\DosDevices\C:`.

### OSDATA.hiv

- Root `OSDATA`: `BootSH`, `CurrentControlSet`, `Microsoft`, `Networking`, `Notifications`, `ResourceManager`, `SOFTWARE`, `System`.
- `CurrentControlSet\Control\SSBoot`: value `SS_BOOT_PROPAGATE_SECURITY_DESCRIPTORS` DWORD **0xC0000034** (STATUS_OBJECT_NAME_NOT_FOUND); `Error_Origination\ProcessUFPTelemetry(87)=0xC000000D` (STATUS_INVALID_PARAMETER); `SDDL_Source` **272 DWORD values** — **264** с 0xC0000034 и **8** с 0xC000003A (STATUS_OBJECT_PATH_NOT_FOUND). **Это диагностические записи, не доказательство текущей неисправности SSBoot**. Все 272 имён присутствуют в machine JSON и raw hive.
- `SOFTWARE\UnitTest\PersistedRegistryValue\WCOS_DirectProbe="DIRECT_OSDATA_TEST"` — сохранилось через reboot.
- Физический путь текущего runtime `\REGISTRY\MACHINE\OSDATA` => `\Device\HarddiskVolume9\Windows\System32\config\OSDATA` (HiveList). Ранее файл на O: 1 048 576 байт; это не обязано совпадать с `reg save` snapshot 843 776 байт.

### SOFTWARE.hiv

- Root `ROOT`: `Classes`, `Microsoft`, `OpenSSH`, `Policies`, `Setup`, `UnitTest`, `WOW6432Node` и др.
- `UnitTest\PersistedRegistryValue\TestDefault="Default Test Data"` и отсутствующий после reboot `WCOS_SourceProbe`. `WOW6432Node` существует в образе Argon 22000 и **не говорит** о планируемом минимальном 26100 host.
- HiveList связывает обычный HKLM\SOFTWARE с `\Device\HarddiskVolume5\Windows\system32\config\SOFTWARE` (**MainOS**); это ключ к интерпретации исчезновения изменений после reboot.

### OSBOOT.hiv

- Root `ROOT`: `ControlSetOverride`, `CurrentControlSet`, `RNG`, `System`, `WMI`, `{7746D80F-97E0-4E26-9543-26B41FC22F79}`.
- `System\CurrentControlSet\Control\ComputerName\FirstBootRan=1`, подчинённые ключи `ActiveComputerName` и `ComputerName`.
- `OSBOOT\ControlSetOverride\StateSeparation\Policy` **не найден** в момент проверки. При этом сам OSBOOT существует; это не повод считать его неактивным.

### DEVICES.hiv

- Root `ROOT`: `ControlSet001`, `DriverDatabase`, `HardwareConfig`, `Networking`, `Select`, `System`, `USB`, `USBStor`, `USB_UsbFlags`, `USB_USBFN` и др.
- `ControlSet001\Enum`: 11 subkeys, включая ACPI, ACPI_HAL, DISPLAY, HID, HTREE, Root, SCSI, STORAGE, SWD, VMBUS. В `Enum\STORAGE\Disk` **9** subkeys, `Enum\STORAGE\Volume` **17**, `Enum\SCSI` содержит `Disk&Ven_Msft&Prod_Virtual_Disk`.
- В `SYSTEM\ControlSet001\Enum` ранее наблюдали почти пустую ветку (включая ROOT); **значительная PnP-конфигурация реально сохранена в DEVICES**, что важно для переносимой State Separation 26100. Нельзя считать, что отдельных OSDATA и SOFTWARE достаточно.

### HiveList.txt / физическая топология реестра

- `\REGISTRY\MACHINE\OSDATA` => `HarddiskVolume9\Windows\System32\config\OSDATA` (OSData)
- `\REGISTRY\MACHINE\SOFTWARE` => `HarddiskVolume5\Windows\system32\config\SOFTWARE` (MainOS)
- `\REGISTRY\USER\.DEFAULT`, `\REGISTRY\MACHINE\SAM`, `\REGISTRY\MACHINE\SECURITY` => `HarddiskVolume9` (OSData)
- User NTUSER.DAT SID профилей `DefaultAccount`, `DefaultUser`, `FactoryUser` => `HarddiskVolume10\USERS\...` (Data); service profiles NetworkService/LocalService/Wcmsvc => `HarddiskVolume9\SharedData\ServiceProfiles\...` (OSData).
- Container registries: **base** `HarddiskVolume5\Windows\Containers\Vail\BaseLayer\Files\Windows\System32\config\{SYSTEM,SOFTWARE,SECURITY,SAM,DEFAULT}`; **delta** `\Device\VhdHardDisk{...}\WcSandboxState\Hives\{SYSTEM_DELTA,SOFTWARE_DELTA,SECURITY_DELTA,SAM_DELTA,DEFAULTUSER_DELTA}`. Это явное доказательство registry-layering у VAIL/Argon 22000. Не смешивать host и container.
- `\REGISTRY\MACHINE\BCD00000000` => `\Device\HarddiskVolume11\efi\Microsoft\boot\BCD`.
- В отображённом HiveList **нет обычных строк** с физическими файлами `SYSTEM`, `OSBOOT`, `DEVICES`, хотя hives выгружены. Причина и стадия их регистрации **не доказаны**; absence in snapshot != nonexistent.
- Исходные `HiveList.txt` и все сырьевые файлы сохранены целиком. Файлы могут содержать конфиденциальные идентификаторы; пакет **ПРИВАТНЫЙ**.

## 4. РАННЯЯ ЗАГРУЗКА FACTORY OS 22000 (runtime + бинарники)

- `HKLM\SYSTEM\CurrentControlSet\Control\Session Manager\BootExecute` = REG_MULTI_SZ `autocheck autochk *\0FirstBootTasks` (два отдельных элемента); `SetupExecute` = `SSBoot`. **Факт регистрационной настройки не доказывает, что SSBoot выполняется на каждом boot**.
- `M:\Windows\System32\FirstBootTasks.exe` exists; `M:\Windows\FirstBootTasks.exe` **не существует**.
- `M:\Windows\ssboot.exe` exists; `M:\Windows\System32\ssboot.exe` **не существует**.
- `O:\Windows\FirstBoot.Complete` 0 bytes, timestamp **2026-09-27 16:11** по экранному выводу гостя (не проверено, локальное ли время); `OSBOOT\System\CurrentControlSet\Control\ComputerName\FirstBootRan=1`.
- `HKLM\SYSTEM\Setup\SystemSetupInProgress=0`, `SetupType=0`; `SetupExecute=SSBoot` остаётся записанным. `OSDATA` содержит диагностические DWORD `0xC0000034`, `0xC000000D`, `0xC000003A` — не трактовать как доказательство краха boot.
- Отдельная `FirstBootTasks.exe` использует `RtlIsStateSeparationEnabled`, `RtlWriteRegistryValue`, `NtQueryValueKey`, `NtSetValueKey`, `NtFlushKey`. Найдены строки `FirstBootRan`, `BootExecute`, `ComputerName`, `ActiveComputerName`, `Hostname`, `NV Hostname`, `PlatformName`. **Не найдено явной строки SSBoot** в предоставленном PE. По именам импортов, **26/26** символов FirstBootTasks 22000 обнаружены в ntdll.dll 26100 (старый отчёт), но это **не** доказательство запуска.

### Сравнение SMSS.exe 22000 и 26100 (источник `WCOS-BootChain-Compare.zip`)

- 22000: **173 928 bytes**, SHA256 `be09b15c760cd37ccab1e227cf0ce829e8efb1c88840e14ed9e48cfcea1817ab`.
- 26100: **216 272 bytes**, SHA256 `f2f0e04e01c361434a0453f2a49f819dc11e8f7420623432e3c15d5bb12b0785`.
- PE: Native NT. Обе версии содержат строки `BootExecute`, `SetupExecute`, `PlatformExecute`, `\\REGISTRY\\MACHINE\\OSDATA\\Session Manager`, импорт `RtlIsStateSeparationEnabled` (независимо пересмотрено по байтам). В 26100 дополнительно `SetupExecuteNoPnpSync`.
- По прошлому анализу PE imports: 22000 **198**, 26100 **215**, совпадают по именам **196/198**; у 22000 `NtConnectPort` и `NtRequestWaitReplyPort`, у 26100 среди новых `NtAlpcConnectPort`. Числа **проверялись в прошлом анализе**, в данном handoff повторно проверено наличие строк и SHA, не полнота PE import parse.
- `FirstBootTasks.exe` 22000: **32 768 bytes**, SHA256 `252cf809b49f8485afd99f981b25586ffb96a67d9f283afd577cbccdb448b1e3`. **В штатном WIM 26100 аналог System32\FirstBootTasks.exe не обнаружен.** Это не повод подменять smss 26100 на 22000.

## 5. ЯДРО, WINLOAD И МЕЖВЕРСИОННЫЕ ИНТЕРФЕЙСЫ

### Источники и хэши

| PE | Размер, bytes | SHA-256 |
|---|---:|---|
| ntoskrnl.exe 22000 | 11 743 560 | `a9b5c0a47f1c4606a94adb3674017cbd35448c3792c4d26f51847a3ffa8c7b7c` |
| ntoskrnl.exe 26100 | 12 723 640 | `93db70780a95192d13d04b4c3cb49c22a138063adba5c83d4855be02b645fdd0` |
| winload.efi 22000 | 2 307 288 | `9fb057cb65462624f35b41fe686f71fc03f11e3770fdb81e04900640030ad41d` |
| winload.efi 26100 | 3 100 680 | `5b47da9e127dfd76689e0cf60d3c14149f42e93656d45843f141dc43f2b7d4a5` |

- `ntoskrnl.exe` обеих веток содержит строковые символы `ExpInitializeStateSeparationPhase0`, `Phase1`, `Phase2`, `RtlIsStateSeparationEnabled`; Unicode paths `\OSDataRoot`, `\Device\OSDataDevice`, `StateSeparation\Policy`, `ControlSetOverride`, `OSBOOT`, `DEVICES`. Ранее также обнаружены `\OSDataRoot\Windows\System32\config\DEVICES`, `\REGISTRY\MACHINE\OSDATA`, карты files/keys. **Строки и экспорты не доказывают активацию фичи в retail 26100**.
- `winload.efi` обеих веток: Unicode paths `\OsDataRoot`, `ControlSetOverride`, `OSBOOT`, `DEVICES`, `OSPool`; ранее также обнаружены string references to redirection maps. **Не доказывает то же исполнение кодовых ветвей**.
- Был неправильный запрос через SCP `M:/Windows/System32/winload.efi` -> `No such file or directory`; правильный файл **Factory OS хоста**: `M:\Windows\System32\Boot\winload.efi`. В контейнерном base-layer также есть `M:\Windows\Containers\Vail\BaseLayer\Files\Windows\System32\Boot\winload.efi` — **не брать контейнерную копию как host loader**. Обнаружен также host WinSxS instance. Архив `WCOS-KernelBoot-22000.zip` теперь исправлен и содержит **оба файла**, проверено.
- В исследовании бинарников WSK `XRo.sys` (22000) 110 920 bytes и `ssboot.exe` (22000) 704 512 bytes. Ранее проверено по именам импортов: XRo->ntoskrnl26100 **62/62**, XRo->fltmgr26100 **21/21**, ssboot->ntdll26100 **167/167** => **250/250**. В `FirstBootTasks` -> ntdll 26100 **26/26**. **Именно только совпадение имён!** Не доказаны структурная ABI совместимость, kernel callbacks, загрузка XRo, каталоговая подпись/code integrity, поведение SSBoot, servicing.
- У `XRo.sys` 22000 есть PE Security Directory, у `ssboot.exe` embedded security directory zero, но в WSK package есть `update.cat`. Реальный статус цепочки доверия на 26100 **не проверен**.
- Ntdll и winload для 26100 из offline IoT LTSC, WSK FactoryOS Argon 22000 — это не существующая готовая PC WCOS 26100.

## 6. НОВЫЙ BCD — КРИТИЧЕСКИЙ РЕЗУЛЬТАТ

`BCD-FOS-22000.hiv`: **32768 bytes**, binary `regf`, root `ROOT`, подразделы `Description` и `Objects`, **14 объектов BCD**. Полная расшифровка **каждого элемента каждого объекта**, с типами, значениями и HEX, лежит в `derived/BCD_ALL_OBJECTS.json`; raw BCD вложен.

### Основной Windows Boot Loader (MAINOS)

- BCD object **`{7619dcc9-fafe-11d9-b411-000476eba25f}`** (он же Default Boot Manager).
- `path` = `\windows\system32\boot\winload.efi` (lowercase из BCD); `description` = `MAINOS`; `locale`=`en-US`; `systemroot`=`\windows`; `inherit`=`{6efb52bf-1766-41db-a6b3-0ee5eff72bd7}`; `resumeobject`=`{94f6b663-bad9-11f1-ab95-806e6f6e6963}`; `mfgmode`=`EmptyProfile`; `allowedinmemorysettings=0x15000075`; `bootmenupolicy=Standard`, `bootstatuspolicy=IgnoreAllFailures`, `detecthal=Yes`, `debug=Yes` по BCDEdit.
- BCD device elements: **`11000001`** (application device), **`21000001`** (`osdevice`), **`21000153`** (`osdatadevice`), **`21000154`** (`bspdevice`). Это 4 отдельных Element entries, но `11000001`/`21000001` содержат один и тот же MainOS GUID.
- Каждый из 4 бинарных дескрипторов **88 bytes**, на offset `0x20` Windows little-endian GPT partition GUID; на offset `0x38` второй GUID **не интерпретировать окончательно без проверки** (возможный parent/disk ID). Значения:
  - MainOS GUID at 0x20 = `{a76b8ce2-0187-4c13-8fca-8651c9b0620a}`.
  - OSData GUID at 0x20 = `{b0479ff4-43bb-4710-90cd-20653989dd7d}`.
  - BSP GUID at 0x20 = `{dff26bef-f2aa-413f-8e45-260df4a4a141}`.
  - Оставшиеся поля каждого оригинального blob не декодированы; **не копировать GUID старой VM в новую**.
- Из реального независимого вывода `bcdedit.exe /store 'E:\WCOS\Logs\BCD-FOS-22000.hiv' /enum '{default}' /v`:
  `device unknown`, `osdevice unknown`, `osdatadevice unknown`, `bspdevice unknown`, но ID, `MAINOS`, `path`, `mfgmode` и др. прочитаны. **`unknown` не доказывает порчу BCD:** при offline чтении нет соответствующих дисков или используется неподдерживаемый device descriptor; точная причина не проверена. `bcdedit` БЕЗ ключей `/set` работает по сохранённому файлу read-only.
- **Нельзя считать `osdatadevice`/`bspdevice` доказательством, что пользовательская retail 26100 автоматически примет такие же BCD-элементы**. Это доказательство конфигурации 22000.

### Другие BCD объекты

- **Boot Manager** `{9dea862c-5cdd-4e70-acc1-f32b344d4795}`; `path=\efi\boot\bootx64.efi`, `description=Windows Boot Manager`; default `{7619dcc9-...}`.
- **Update OS (Boot from WIM)** `{311b88b5-9b30-491d-bad9-167ca3e2d417}`; `path=\windows\system32\boot\winload.efi`; отдельный **204-byte** device/osdevice descriptor; ранее разобранный путь к WIM `\PROGRAMS\UpdateOS\UpdateOS.wim` (проверить точное расположение строки в raw blob при дальнейшем исследовании). **Наличие записи ≠ доказательство выполненного обновления**.
- **Mobile Startup App** `{01de5a27-8705-40db-bad6-96fa5187d4a6}`; `path=\EFI\Microsoft\boot\mobilestartup.efi`.
- **Resume** `{94f6b663-bad9-11f1-ab95-806e6f6e6963}`; `path=\windows\system32\boot\winresume.efi`, `hiberfil.sys`.
- **Debugger Settings** `{4636856e-540f-4170-a130-a84776f4c654}`: `debugtype`/`network` settings; в реестре явно `12000019=1`, `1200001d=1.2.3.4`, другая бинарная конфигурация. Не делать вывод об активном подключении debugger из одной записи.
- **Ramdisk Options** `{ae5534e0-a924-466c-b836-758539a3ee3a}` и `\boot\boot.sdi`.
- Остальные BCD объекты GUID перечислены и доступны в полном `BCD_ALL_OBJECTS.json`; сохранены даже неизвестные сырые элементы без интерпретации.
- Физический путь BCD из HiveList `\Device\HarddiskVolume11\efi\Microsoft\boot\BCD`, и MainOS bootpath из BCD `\Windows\System32\Boot\winload.efi` — не путать BCD-хранилище и загрузчик.

## 7. WSK, PRODUCT FEATURES, STORAGE SPACES, CONTAINERS — РАНЕЕ УСТАНОВЛЕННОЕ

- WSK FactoryOSArgon OEMInput: `Product=FactoryOSArgon`, `DeviceLayoutType=GPT_SPACES_4096`, `ReleaseType=Test`, features `STATESEPARATION_ON`, `STORAGESPACES_LAYOUT`, `UEFI_VM`, `SERVICING_STACK`, `CORE_WIN32_SUPPORT_ARGON`, `WCOS_CONTAINERS`, `WCOS_APP_WIN32SUPPORT_ARGON`, `ENABLE_WOW`, `SHELL_BOOT` и др. Списки features, 11 XML Argon manifests и карта feature->packages в **вложенном предыдущем ZIP**.
- Другие WSK OEMInput `LegacyGPT` имеют State Separation без `STORAGESPACES_LAYOUT`; значит Storage Spaces **нельзя объявить безусловно необходимыми для любого WCOS**, но выбранная FactoryOSArgon с GPT Spaces их использует/настраивает. Следует выбирать схему по конкретной загрузочной архитектуре.
- Скан **288 CAB**, среди них (перечень НЕ полный) `Microsoft-OneCore-Enable-StateSeparation-Package`, `...ReadOnlyMinifilter-Package`, `...StateSeparation-RedirectionMap-TestContent-Package`, `Microsoft-OneCore-SS-Boot-Package`, `Microsoft-WindowsCore-SplitLayoutSupport-Package`, `Microsoft-OneCore-BcdCoreOSLoadGPTSpace-Package`, `...BcdCoreMobileStartupGPTSpace-Package`, `...GenericGPTSpaceDeviceLayout4096-Package`, `...ServicingStack-Lite-Package`, `...Update-Stack-Package`, `...Containers-Package`, `...Composable-VailArgonCommon-Package`, `...ContainerOSLinkArgon-Wrapper-Package`, `...VAIL-HostConfig-Package`. Наличие CAB в WSK **не означает**, что пакет установлен в VM.
- CAB mUM/XML дают более точные сведения об установке компонентов. `ssboot.exe` из WSK устанавливается в `\Windows\ssboot.exe`, `spaceutil.exe` в `\Windows\System32\spaceutil.exe`. Некоторые `.manifest` используют бинарный `DCM\x01 / PA30`. Не делать предположений по знакомым названиям.
- Win32 Argon/VAIL registry delta/base layering реально присутствует (см. HiveList), но shell и контейнер пока отложены после Core.

## 8. ИСТОЧНИКИ 26100 И НЕПОДТВЕРЖДЁННЫЕ ВЕЩИ

- Приоритет — **Windows 11 IoT Enterprise LTSC 2024 x64, WIM index 2**, а не ValOS/обычная Windows 11 как готовая WCOS. Ни номер сборки Xbox 26100, ни Holographic 22621, ни наличие экспортов не доказывают PC WCOS на 26100.
- `ntoskrnl.exe` 26100 содержит `RtlIsStateSeparationEnabled` и ранние `ExpInitializeStateSeparationPhase*`, OSDATA strings; `winload.efi` содержит OSDATA/OSBOOT/DEVICES; `smss.exe` содержит native BootExecute/SetupExecute и OSDATA Session Manager; `spaceport`, `wcifs`, `bindflt`, `UnionFS` есть. `Windows.System.Internal.WCOS.WCOSUserManager`, Windows Runtime `UserManager` registrations есть в SOFTWARE; **запуск WCOS не доказан**.
- **Не найдено** штатного `XRo.sys`, `ssboot.exe`, `FirstBootTasks.exe` в соответствующих местах 26100 retail WIM. Простой перенос бинарников 22000 не допускается до проверки KMCI/каталоговых подписей/ABI/ранней инициализации и на **только отдельной VM**.
- Не решена причина, почему тестовая Registry RedirectionMap rule не перенаправила `WCOS_SourceProbe`. Важная техническая неизвестность для Core 26100.
- Не доказана boot-time схема Storage Spaces/OSPool на 26100, trusted signing, совместимость split hive и сторонних компонентов в разных ветках, servicing/update/recovery. Даже успешная загрузка обычной 26100 с GPT не будет считаться WCOS PoC.

## 9. ИСТОРИЯ ПОСЛЕДНИХ ПРОВЕРОК (ХРОНОЛОГИЯ)

1. В прежнем полном handoff эксперимент Registry persistence ещё **не завершён**, версия clone была не подтверждена. После него пользователь уточнил и подтверждал `FOS-SS-Test`; выполнены reboot и controlled test, доказавшие persist OSDATA / disappearance SOFTWARE.
2. Пользователь сохранил `OSDATA.hiv`, затем все пять hive и три `.reg` + `HiveList.txt`; позже `BCD-FOS-22000.hiv`. Прямое чтение бинарных snapshots подтвердило независимую картину registry state.
3. `WCOS-BootChain-Compare.zip` включает `22000\FirstBootTasks.exe`, `22000\smss.exe`, `26100\smss.exe` (3 PE). Проверены strings и импорты 196/198 + 26/26 (предыдущий анализ).
4. Попытка SCP `M:/Windows/System32/winload.efi` вернула `No such file or directory`; `ntoskrnl.exe` успешно скопирован, но изначальный ZIP был неполным. Потом нашли `M:\Windows\System32\Boot\winload.efi`, пересобрали `WCOS-KernelBoot-22000.zip` с **2 файлами**.
5. Поиск `dir M:\winload.efi /s /b` выявил цикл `M:\Data\Data\...`; больше не повторять рекурсивный root scan.
6. Сохранён реальный BCD, распарсены 14 объектов и уникальные устройства; независимый `bcdedit /store` показал элементы и `unknown` для offline devices.
7. Последний предложенный read-only preflight Windows 11 перед созданием **нового отдельного VHDX** (проверка DISM mount, свободного места E:, исходных PE/hives и свободного имени VM) **ЕЩЁ НЕ ПРИСЛАН ПОЛЬЗОВАТЕЛЕМ**. Не утверждать, что PoC диск создан.

## 10. СЛЕДУЮЩИЙ КРУПНЫЙ ЭТАП — ПЛАН БЕЗОПАСНОГО PoC

**Ближайший запрос к пользователю:** дать результат **только-читающего preflight** (см. `notes/NEXT_SAFE_ACTION.md`), проверить read-only WIM index 2 и свободное место E:, отсутствие конфликта VM `Windows11X-26100-PoC01`. Затем создать **отдельный** экспериментальный VHDX и Hyper-V Gen2 VM (не трогать `FOS`, `FOS-SS-Test`, 20279, WIM). Этап 0: контрольная загрузка **согласованной обычной 26100** как baseline — **НЕ** считать это WCOS. Затем на этом отдельном PoC последовательно проверить BCD/OSData discovery, кусты, ранний SSBoot, XRo validation/signature, State Separation, Data persist/reboot, servicing/recovery. Все destructive шаги только с plan-of-rollback и запретом записи в оригинальный диск.

**Критерии первого WCOS Core PoC:** не декоративное разбиение, а 26100 components boot + доказанный active state separation, MainOS write denial с контрольной причиной, OSData persistent, registry persistence + корректное перенаправление, совместимый BCD, reproducibility after reboot. Не обязательно полный интерфейс или Win32.

**Сейчас исследование условно ~70% перед первой сборкой; готовность ОС гораздо ниже.** Не превращать это в объективный KPI. Оставшиеся рискованные зоны важнее арифметики.

## 11. ПРИОРИТЕТЫ СЛЕДУЮЩЕМУ ИИ

- Доверяй raw evidence выше пересказов. В `notes/INFERENCES_VS_FACTS.md` отмечены уровни достоверности и поправки к старым формулировкам.
- **Не возвращайся снова** к анализу 20279 OOBE, shell, Win32 контейнера, если пользователь не меняет цель.
- Не утверждай, что retail Windows 26100 уже умеет boot WCOS, не подменяй ядро/SMSS и не переписывай BCD вслепую.
- В PowerShell `scp` пароль запрашивается интерактивно; SSH IP **172.18.69.72**, старый IP устарел. Ни пароли, ни секреты из реестра в публичные отчёты не выносить.
- **Не делай десятки мини-отчётов.** Один проверяемый большой отчёт после этапа. Поддерживай переносимый JSON+ZIP.
- Соблюдай последовательность: verify -> copy/backup -> test in fresh VM -> snapshot/evaluate -> only then next step.

## 12. СОДЕРЖИМОЕ ПАКЕТА / ОГРАНИЧЕНИЯ

- `sources/new_since_prior_handoff/Windows11X-WCOS-2026-11-05-COMPLETE-HANDOFF.zip`: ранее изготовленный **исходный полный handoff 78 файлов** (сам содержит большой JSON, README, OOBE/20279, WSK, 26100 PE, old derived manifests and original logs). Он нужен для всех деталей до данной фазы.
- `sources/new_since_prior_handoff/*.hiv, *.reg, HiveList.txt`: 6 bin hive, 3 reg exports, текстовые hivelist.
- `sources/new_since_prior_handoff/WCOS-BootChain-Compare.zip`, `WCOS-KernelBoot-22000.zip`, `WCOS26100-Boot-Interfaces.zip`: первичные PE и новые материалы 26100.
- `derived/BCD_ALL_OBJECTS.json`: полные 14 BCD objects, каждый Element в hex и decode where safe.
- `derived/REGISTRY_FORENSIC_SUMMARY.json`: ключевые значения snapshots, все 84 XRo whitelist names, все 272 SSBoot diagnostic names, root inventory.
- `derived/COMPLETE_HANDOFF_CURRENT.json`: все предыдущие данные + новый материал + machine evidence + явные corrections.
- `notes/NEW_EXPERIMENTS_AND_CONSOLE_LOGS.md`: точные новые команды/выводы, насколько они присутствуют в чате.
- `notes/NEXT_SAFE_ACTION.md`: старт с последней реальной точки.
- `tools/wcos_hive_readonly.py`: упрощённый **read-only** REGF parser (не substitute полноценного библиотечного hive parser; имеет ограничения).
- `MANIFEST.json` и `SHA256SUMS.txt`: размеры, SHA-256 всех вложенных файлов, полезно для воспроизводимости.

**ПРИВАТНОСТЬ:** полный архив содержит SYSTEM, SOFTWARE, OSDATA, OSBOOT, DEVICES и сетевые/системные идентификаторы, возможно токены или секреты. **Не публиковать и не отправлять третьим лицам, которым не доверяете.** Пароль SSH намеренно **не** включён в экспорт. Отдельные SAM и SECURITY raw hives намеренно не запрашивались.

**Важное ограничение:** хотя собраны все доступные в текущем рабочем окружении первичные файлы и предшествующий полный handoff, это не полная побайтовая история всего диалога (часть ранних действий известна только по сохранённым предыдущим summary/logs). Для спорного пункта проверяйте оригинальный текст логов и исходники. Текущий JSON фиксирует **все доступные здесь** важные новые и предыдущие сведения, не претендуя на недоказанные факты.
