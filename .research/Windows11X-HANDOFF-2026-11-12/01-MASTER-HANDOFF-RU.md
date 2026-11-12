# WINDOWS 11X / WCOS NT 26100 — ПОЛНОЕ ТЕХНИЧЕСКОЕ ДОСЬЕ

**Снимок:** 12 ноября 2026, после проверки двойной API Set V6+embedded V7 на реальном госте Windows 26100 (офлайн-скрипт). **Назначение:** передать преемственность новому ИИ и исключить повторение старых циклов. Все пути, адреса, версии, статусы и исходы разделены по происхождению. В комплекте есть дополнительные файлы с точными логами, таблицами и крупными исходными архивами.

## 0. Указание новому ИИ — ПРИОРИТЕТ И ЗАПРЕТ ПОВТОРОВ

**Нельзя начинать с нуля.** Мы уже: загрузили настоящий Factory OS 22000 на Hyper-V; выгрузили и проанализировали SYSTEM/OSBOOT/DEVICES/OSDATA и правила; сопоставили оригинальные PE 22000 и 26100 по ABI фаз, `INIT`, реальным Zw→Nt, WNF; разобрали API Set V6/embedded V7, `SEALED`, BaseApiSetSchema, два старых TraceSmoke boot; построили postboot OSData PoC 20/20 на обычной 26100; создали **новый** двухпредставленный API Set 26100 и пользователь проверил его офлайн (`PASS`, SHA ниже). Бесконечно проверять отсутствие Host или `statesepext.sys` — не помощь.

Текущий запрос пользователя к разработке: **тест нового V6+V7 API Set в уже подготовленной восстановимой Hyper-V VM; предпочтительно сначала без WinDbg, потом при необходимости с ним**. Новая DLL пока не установлена; для неё нет boot trust acceptance proof. Двигаться осмысленно, не обещать готовую WCOS. Вышеизложенное не отменяет независимую работу мощного ИИ над OSBOOT/DEVICES.

## 1. Главная цель Windows 11X (неизменна с 05.11)

Создать **настоящую Windows Core OS на x64 PC**, а не тему, облегченную Windows 11 или VHD с новыми названиями разделов. База: современный *согласованный* набор компонентов семейства **NT 26100** (впоследствии обновляемый до новых веток), архитектурно подобный Factory OS / Windows 10X. Платформа первоначальной проверки: **Hyper-V Gen2**.

Требования к готовому core: реальный early boot с kernel extensions и `Phase0/1/2`; `State Separation` со списком правил и backing stores; отдельно защищённая от записи `MainOS` и изменяемые `OSData`, `Data` и части реестра; OSBOOT/DEVICES/OSDATA, если требуется оригинальной композицией; корректная согласованная схема Storage Spaces/GPT/EFI/BSP/Servicing; сохранение состояния после reboot; допустимый servicing/update/recovery и защищённая целостность. Не выводить WCOS по названию раздела, ключу `Enabled=1`, одному `API Set Host`, успешному обычному boot, отсутствию SysWOW64 или `REG_LINK` на D:.

Только **после доказанного WCOS Core**: UWP/WinUI (включая WinUI 3 после доказательства AppModel), оригинальный Santorini/Composer из Windows 10X 20279; Win32, если реально возможно, через отдельный контейнер/изоляцию. Стремиться к минимальному x64-хосту без SysWOW64/обычного Win32, но не ломать ранний proof ради этого. OEM/WSK не считать существующим/совместимым 26100 только по маркетинговому названию.

Источники и роли:

| Источник | Назначение | Чего не означает |
|---|---|---|
| Factory OS 22000.1 Argon x64 | Непосредственно работающий PC WCOS эталон (State Separation, BCD, XRo, WSK, Storage Spaces) | Не drop-in ядро/APISet для 26100 |
| Windows 11 IoT Enterprise LTSC 2024 / Enterprise LTSC 2024 (26100) | Современная согласованная основа PC kernel и boot; первая предпочитается | Не WCOS из коробки; исходный `statesepext` Host пуст |
| Windows 10X Santorini 20279 | Оригинальный UX, Composer, ModernUX, AppModel, OOBE | Не современный исходный kernel для финала |
| Validation OS 26100 | Только сравнительный минимальный образ | Не Factory OS |
| Xbox SystemOS/GameCore 26100 | Косвенный пример современных специализированных Windows | Не переносимая PC WCOS |
| Holographic 22621 | Ещё одна специализированная Windows | Не готовая PC WCOS 26100 |

## 2. Инвентарь систем и виртуальных машин

### 2.1 Hyper-V хост и исторические пути (всё проверять перед новыми командами)

Основной хост Windows 11 x64 + Hyper-V. WSK 22000 каталог ранее: `E:\DISK D\FOS\WINDOWS_SYSTEM_KIT_co_release_22000_210604-1628`; Argon OEMInput: `E:\DISK D\FOS\FactoryWin32\FactoryOS_win32_manufacturing_AMD64_UEFI_SpacesGPT_VM.xml`; исходный VHDX `E:\DISK D\FOS\FactoryOS_win32_manufacturing_AMD64_UEFI_SpacesGPT_VM.vhdx` с возможным `.avhdx` — **не изменять и не сливать**. Дополнительные исходные архивы в `E:\WCOS\Logs\`, `E:\WCOS\Logs\RegistryDump\`; точное нынешнее местоположение может отличаться.

ISO Windows 11 IoT Enterprise LTSC: `E:\WCOS\21600\en-us_windows_11_iot_enterprise_ltsc_2024_x64_dvd_f6b14814.iso`; ранее WIM index `2` — IoT Enterprise LTSC, index `1` — Enterprise LTSC, index `3` — IoT Subscription LTSC. Историческая read-only точка монтирования `E:\WCOS\Mount\26100-IoT-LTSC` (НЕ предполагать, что сейчас смонтирована). Ранее в 26100 WIM не обнаружены XRo/SSBoot/statesepext как готовый WCOS комплект; есть низкоуровневые хранилища/фильтры Windows, но это не достаточная архитектура.

### 2.2 Factory OS VM

Оригинальная `FOS` не трогается; тестовые VM/клоны `FOS-KD-Test` (WinDbg реальный kernel binding) и `FOS-SS-Test` (SSH, registry/volume/State Separation). Внутри FOS-SS-Test guest hostname может оставаться `FactoryOS-3Q3P6`, что само по себе не идентифицирует VM. Учётный пользователь `FactoryUser`; исторически рабочий SSH IP `172.18.69.72`, старый `172.18.72.222` устаревший, **IP при новой сессии перепроверять**, пароль не хранить и не передавать. Factory OS shell — WCOS host, не командная строка Argon/VAIL Win32 контейнера.

Доказанные исходные FOS версии/хеши:
- K22000 `C:\Windows\System32\ntoskrnl.exe` 11,743,560, `10.0.22000.1`, SHA256 `A9B5C0A47F1C4606A94ADB3674017CBD35448C3792C4D26F51847A3FFA8C7B7C`.
- W22000 `M:\Windows\System32\Boot\winload.efi` 2,307,288, `10.0.22000.1`, SHA256 `9FB057CB65462624F35B41FE686F71FC03F11E3770FDB81E04900640030AD41D`.
- `statesepext.sys` 61,752, SHA256 `167FDA36A71C2AAD4C146BC66AE4C83E9D525D008D6ABFD8EDA0E056C9A96658`.
- FOS `.apiset` PE 143,672, 945 contracts, SHA256 `49696b4557023ea455aa7731a18467b623292a7c2ec837d8437a9c2dcd9cd498`.

### 2.3 Windows 26100 тестовые VM

`Windows11X-26100-PoC01` (Hyper-V Gen2), `Activation01` в более ранней ветке отладки, текущая работающая VM в PowerShell `C:\Users\fog`, hostname `DESKTOP-HSVPECH` на 11.11. Нельзя автоматически считать все эти имена одной машиной или одной загрузкой без evidence. В старом preboot checkpoint PoC01: **Secure Boot On**, профиль MicrosoftWindows; `manage-bde -status C:` — BitLocker полностью расшифрован и не использовался. Имеется ранее заявленная snapshot/checkpoint `Before-PhaseBinding-26100-9457`; статус текущей машины надо подтверждать перед модификацией.

**Различать версии файлов**:
- Старый baseline NT26100.1742 kernel SHA256 `93db70780a95192d13d04b4c3cb49c22a138063adba5c83d4855be02b645fdd0`, W26100.1742 `5b47da9e127dfd76689e0cf60d3c14149f42e93656d45843f141dc43f2b7d4a5`; соответствующий ранний `.apiset` 974 contracts, PE 152992 SHA `aeaaea67b6c57b4d85cc1d7b724f2ec8fe57aa3210c7aee78b3f40e231fd70f1`.
- Актуальный исследованный *смешанный servicing набор* kernel 26100.9457 SHA `d90c69cfa925d42037ef1cdba3ff5b3f13cc14e16060938073b97e396fdde2f2` (13,104,632); winload 26100.9444 SHA `1c1a978448b8e0fc2284c13d6e55fde8bf9827dca432cdc8a9be66c2e729adda` (3,358,408); schema 26100.9278 194016 SHA `391a2818fdef14f4a9b6e6ce2a7e7f01d04f943de9ef543ec683927cfe480889`, **984 contracts**. Устойчивые RVA далее соответствуют указанным версиям. Номер ядра 26100.9457 не доказывает, что каждый компонент этого набора имеет .9457.

### 2.4 Текущая 26100 — BCD и системное состояние по пользователю

`bcdedit.exe /enum "{current}"` для текущей обычной Windows 11: `device partition=C:`, `osdevice partition=C:`, `path \Windows\system32\winload.efi`, `description Windows 11`, `systemroot \Windows`, `recoveryenabled Yes`, `testsigning Yes`, `isolatedcontext Yes`, `allowedinmemorysettings 0x15000075`, `nx OptIn`, `bootmenupolicy Standard`. **НЕТ** `osdatadevice`, `bspdevice` в выводе; это не значит отсутствует Object Manager `OSDataRoot`.

`NtLinks26100` пользователь выполнил:
```
\OSDataRoot -> \Device\OSDataDevice
\Device\OSDataDevice -> \ArcName\multi(0)disk(0)rdisk(0)partition(3)
\??\C: -> \Device\HarddiskVolume3
```
**Последняя ARC→HarddiskVolume цепочка на 26100 не заснята**; поэтому нельзя формально объявлять тот же Volume3 лишь по совпадению номера partition(3). Согласуется с fallback OSDataRoot на boot device, но это не доказанная отдельная OSData/State Separation. Последующая рекомендованная проверка ARC не была прислана.

## 3. Factory OS 22000 — что реально доказано

### 3.1 Тома / файловая система / фильтры

Подтверждённые дисковые имена в `FOS-SS-Test`: `M:` MainOS = `\Device\HarddiskVolume5`; `O:` OSData = `\Device\HarddiskVolume9`; `B:` BSP = `\Device\HarddiskVolume3`; `C:` Data = HarddiskVolume10; `S:` SERVICING_FILES = HarddiskVolume7; `P:` PreInstalled = HarddiskVolume8. Есть другие NTFS/FAT и один `\Device\VhdHardDisk{61266b3b-4e02-426d-8f13-d9bb82984e2f}`. `Get-Volume` в Factory OS не существовал; читали `mountvol` / `fltmc volumes` / `fltmc instances`.

`mountvol` volume GUID mappings:
- O: `{b0479ff4-43bb-4710-90cd-20653989dd7d}`;
- M: `{a76b8ce2-0187-4c13-8fca-8651c9b0620a}`;
- B: `{dff26bef-f2aa-413f-8e45-260df4a4a141}`;
- C: `{fe296136-48c2-4b94-a83a-f4345a1eb2f3}`;
- S: `{6a013b35-c42d-4d87-ae6a-57b47b70acad}`;
- P: `{c8adae64-47f4-4cfa-b67c-1e743ad5dfc9}`.

`fltmc instances`: `XRo` **только на M: и B:**, altitude `261101` (приводились `SprtFtrs=0000000c`); `wcifs` на M:, `bindflt` на C:, `FileInfo`, `FsDepends`, `Wof` на нескольких томах. Ранний контрольный тест записи в MainOS был отвергнут, Data файл после reboot сохранился; один отказ записи сам по себе не изолирует XRo от иных ACL/mount прав. `M:\Data` может быть alias/mountpoint на другом backing store, **не** считать его физической записью в MainOS до анализа reparse/mount.

**Полностью подтверждённая цепочка Object Manager в одной работающей FOS:**
```
\OSDataRoot
 -> \Device\OSDataDevice
 -> \ArcName\multi(0)disk(0)rdisk(4)partition(1)
 -> \Device\Harddisk7\Partition1
 -> \Device\HarddiskVolume9 == O:
```
Все цели получены через `NtOpenSymbolicLinkObject`/`NtQuerySymbolicLinkObject`; это runtime наблюдение одной загрузки, **не** доказательство точной последовательности формирования link в winload. `\??\M:` -> `\Device\HarddiskVolume5`. Широкая гипотеза «OSData всегда O: в каждой Factory OS» не заявляется.

### 3.2 Factory OS BCD (реальное `bcdedit /enum all`, 11.11)

`{current}` MAINOS: `device partition=M:`, `osdevice partition=M:`, `osdatadevice partition=O:`, `bspdevice partition=B:`, `path \windows\system32\boot\winload.efi`, `systemroot \windows`, `mfgmode EmptyProfile`, `debug Yes`, `allowedinmemorysettings 0x15000075`, `resumeobject` есть. `bootmgr`: `device/windowssyspart partition=\Device\HarddiskVolume4`, `path \efi\boot\bootx64.efi`, `testsigning Yes`, `bootsequence={01de5a27-8705-40db-bad6-96fa5187d4a6}`, `persistbootsequence Yes`, `default={current}`, `timeout 0`. В `Windows Boot Application` `mobilestartup.efi` из EFI volume4, recovery sequence на UpdateOS. `Windows Update OS`: ramdisk `[M:]\PROGRAMS\UpdateOS\UpdateOS.wim`, `winload.efi` в `\windows\system32\boot\`, `winpe Yes`. Hibernate resume: `device=M:`, `filedevice=O:`, `associatedosdevice=M:`, `filepath=\hiberfil.sys`; то есть расположение изменяемого hiber state на O:. `testsigning=Yes/debug` — **свойства этого тестового образа**, не необходимые критерии WCOS.

### 3.3 Реестр FOS: карты, ульи, runtime persistence

Исходные старые `.reg`/`HiveList` анализировали в `WCOS-FullRegistry-22000.zip` и `WCOS-FullRegistry-26100.zip`; **их отдельных оригинальных архивов в текущем /mnt/data нет**, но сводные JSON/MD и исторический handoff сохранились. SHA старых оригиналов (из прежнего аудита): FOS `7333fffab2c838d53c9f029251e4af3f8f4cf8a029e70a342b6a19df44354177`; 26100 `8bc73fddb85ff403f72c761eb34c540033c203050728c99b6feaf0125951ab66`. Новому ИИ нельзя изобретать локальный путь к ним: запросить у пользователя, только если нужны RAW значения.

По полному parsed экспорту:
- FOS `SYSTEM`: **32,177** ключей, **105,919** значений; Windows26100 `SYSTEM`: **38,070** ключей, **117,241** значений.
- FOS `OSBOOT`: **132** ключа, **452** значения. В нём `OSBOOT\SYSTEM` 118, `ControlSetOverride` 8, другие roots; `CurrentControlSet` содержит некоторые WMI Autologgers, Session Manager, services. У 26100 отсутствовал отдельно экспортированный OSBOOT.
- FOS `DEVICES`: **2,407** ключей, **6,017** значений. `DEVICES\ControlSet001\Enum` 548 ключей, `DEVICES\Networking` 412; 26100 обычный SYSTEM Enum ~366–367. Многие `CurrentControlSet`/`ControlSet001` являются дубликатом view, не независимыми коллекциями. **DriverDatabase** преимущественно в FOS SYSTEM (~19,365 keys), а не DEVICES (~2); прежний тезис «всё driverdatabase ушло в DEVICES» опровергнут.
- FOS `SYSTEM\...\StateSeparation`: **672 paths, 1,415 values**; обычная тестовая 26100 — два пустых ключа/0 values после rollback. FOS `Policy\Enabled=1`. Количества правил зависят от метода подсчёта: runtime map `62` file rules, `459` key rules, `81` KeyRedirectionLinks; полный `.reg` export исторически имел `465` дочерних ключей под `RedirectionMap\Keys` (плюс root, 466). **Не смешивать эти подсчёты**. Ключевые направления: `OSDATA` ~400–404, `OSBOOT` 40, `DEVICES` 19; 81 `KeyRedirectionLinks`, из них 78→OSDATA и 3→OSBOOT. File redirects 37 на O:, 25 `M:\Data` (alias unverified).
- Конкретные примеры: `SYSTEM\CurrentControlSet\Control\FeatureManagement` → `OSBOOT\ControlSetOverride\FeatureManagement`; `BTHPORT\Parameters` → `DEVICES\bthportparams`; config `M:\Windows\System32\config` → `O:\windows\system32\config`.
- В старом FOS `HiveList` backing для `OSDATA`, `SAM`, `SECURITY` наблюдался на `HarddiskVolume9`, для `SOFTWARE` — на `HarddiskVolume5`. `OSBOOT`/`DEVICES` не отображались там обычным способом, но ветки `HKLM\OSBOOT`/`HKLM\DEVICES` и файлы `O:\Windows\System32\config\OSBOOT` (~64 KiB) / `DEVICES` (~1 MiB) существовали. **Отсутствие имени в HiveList не доказывает отсутствие смонтированного hive**.
- Старые эксперименты persistence на FOS: прямое значение под `HKLM\OSDATA` сохранялось через reboot; тестовое значение в `HKLM\SOFTWARE` исчезало при reboot; управляемый тест ожидания автоперенаправления конкретного value **не подтвердил автоматический redirect**. Сохранился Data файл; запись на MainOS отвергалась. Это результаты отдельных проб/гостей, а не доказательство, что все State Separation rules активны.

### 3.4 Живая Factory OS kernel binding

На отдельной `FOS-KD-Test` WinDbg наблюдал *действительно загруженный* `statesepext.sys` (образ размером ~`0xD000` mapping) и реальные IAT `nt!_imp_ExpInitializeStateSeparationPhase0/1/2` → `statesepext!ExpInitializeStateSeparationPhase0/1/2`. Этот runtime факт отличен от случайных строк/PE imports. В обычной Activation01/26100 эти импорты указывали на fallback-обработчик `nt!DefaultAddProfileSource` с результатом `STATUS_NOT_SUPPORTED` (подробности у старых аудитов; не выдавать имя функции за Host).

## 4. Эксперименты Windows 26100 до V7: история без приукрашивания

### 4.1 Phase Binding/политика StateSeparation

В экспериментальных 26100 были тесты `StateSeparation\Policy\Enabled=1`, но доказанного подходящего раннего Host не было. Наблюдались отдельные отрицательные исходы: исключение `0xC0000005` в `winload!OslGetRootCell` с invalid selector `0xFFFFFFFF` в одной ранней конфигурации; в другой `SECURITY_INITIALIZATION_FAILED` bugcheck `0x5F`. Эти события нельзя автоматически считать одним и тем же дефектом, ни доказательством несовместимости statesepext 22000: их причинность не установлена. После rollback проверка реестра 26100 показывала только пустые каркасы `StateSeparation` / `Policy` без Enabled. Повторная установка Enabled=1 без реализации и hives не имеет смысла.

`PhaseBindingProbe-TESTSIGNED.sys`, `PhaseBindingProbe-UNSIGNED.sys` из старого Signing Lab существовали как экспериментальные probes, не полноценные порты FOS. Был API Set с legacy V6 State host `PhaseBindingProbe-TESTSIGNED.sys` SHA `ad587f8b9810f25b74d4524222f8e60b7af3dc6d6c1b41f931567ba5542f227c`. Текст в namespace в одном boot и пустой resolution в другом не доказывают исчезновение в одном последовательном trace.

### 4.2 Почему не «запустим V6 FOS в 26100»

Factory OS schema 22000: **945** контрактов, default State `statesepext.sys` / `onecorestateseparationext.dll`, оба `Entry.Flags=0` и обычный legacy V6. Схема 26100 старой ревизии: 974 с пустыми State Host и SEALED; более новой: **984**, legacy V6 wrapper поверх compact/embedded 7. Замена всей FOS схемы не сохраняет набор 26100 и вызывала ранний crash до проверки State Host; нельзя объявлять «FOS DLL годится, только подпись исправим». STrace и подписанная `STraceSchemaSetFrwd.dll` демонстрировали расширения unsealed Trace, но не обход SEALED State Separation. **Наличие подписи Microsoft у стороннего исследовательского sample не значит, что автор выпустил подпись сам.**

### 4.3 `BaseApiSetSchema` (ключевая находка, 08–09.11)

Winload по старому PE 26100.1742: PDB `OslHiveGetBaseApiSetSchemaName` (`0x453C`) ищет `HKLM\SYSTEM\ControlSet...\Control\Session Manager\ApiSetSchemaExtensions\BaseApiSetSchema`, затем `OslLoadApiSetSchema` выбирает **альтернативный basename базового PE**, а не добавляет overlay; подробные RVA 1742 в старом отчёте, не применять к 9444.

Для исследованного **winload 26100.9444** PDB+PE: `OslLoadApiSetSchema` `0x2309C`; читает значение `BaseApiSetSchema` в `0x23104–0x23155`, выбирает suffix в `0x23171`, `OslpLoadApiSetSchemaImage` по `0x231A9`; `.apiset` section извлекается и копируется до `0x231EA`; global `OslApiSetSchema` по RVA `0x354F00`, Size `0x354EF8`; ручной `WinDbg` подтвердил, что альтернативный путь `\Windows\system32\WCOS-TraceSmoke\ApiSetSchema.dll` действительно участвует при boot с debugger. Доказан выбор пути, **не** доверие к любому произвольному модифицированному базовому PE.

Порядок загрузки: `.apiset` → initial publication → namespace-level gate → registry extensions `0x23282..0x2333B` → `OslpApplyDynamicSchemaExtensions` (RVA `0x22FD4`), который использует `ApiSetCreateSchema`/`ApiSetComposeSchema`; затем API Set ResolveToHost для imports. **Важно**: `ApiSetSchemaExtensions` записи не эквивалентны `BaseApiSetSchema` — это два разных механизма.

### 4.4 TraceSmoke B-DualSize (09–10.11), реальный WinDbg

На 26100.9444 старый zip `Windows11X-TraceSmoke-26100-AB-Lab.zip` и `Stage-TraceSmoke.ps1 -Variant B-DualSize` (пользовательский PowerShell) дали `STAGED - do not reboot before attaching WinDbg`; staged значение `BaseApiSetSchema=WCOS-TraceSmoke\ApiSetSchema.dll`. В boot debugger был message:
```
*** Windows is unable to verify the signature of
 the file \Windows\system32\WCOS-TraceSmoke\ApiSetSchema.dll.
 It will be allowed to load because the boot debugger is enabled.
```
Следовательно, альтернативный PE действительно читался при debugger-assisted boot. Это **не** доказательство обычного autonomous signing acceptance.

Первая остановка `winload!OslpLdrExOverrideImport+0x137`: имя API Set `ext-ms-win-ntos-trace-l1-1-0.dll`, `call winload!ApiSetResolveToHost`, после `p` `eax=0` (= STATUS_SUCCESS), marker `01`, но `UNICODE_STRING.Length=0` и Host buffer показывает бинарные данные. `eax=0` с пустым Host != загруженный `WcosTraceSmoke.sys`.

В **одной** загрузке с bp на `OslLoadApiSetSchema`:
- A `winload+0x23222`: `OslApiSetSchema` ptr `fffff800\`77595f20`, Size `0x29C84`, legacy Trace value по ns+`0x94F0`: HostOff=`0x29C60`, HostLen=`0x24`, `du` строки даёт `WcosTraceSmoke.sys`.
- B `winload+0x2335F` (перед вызовом dynamic extension): *та же* база/размер/legacy HostOff/Len.
- C `winload+0x23375` (после dynamic extension): ptr `fffff800\`78724d20`, Size=`0x29C74`, legacy Trace value: HostOff=`0x1F304`, HostLen=`0`. Поиск UTF16 строки `WcosTraceSmoke.sys` в финальном namespace пустой.
Отсюда **runtime доказано**, что активная схема меняется *между B и C*, а новый Host отсутствует в legacy итоговом буфере. Нельзя без полного compact V7 dump утверждать, какой конкретно подшаг стёр каждый байт и был ли runtime resolver вызван после конкретного snapshot.

Во **второй, отдельной** загрузке на `OslpApplyDynamicSchemaExtensions+0x4F` / `winload+0x23023`: `AL=0`; пошагово `je` на empty string branch (`du @rax -> ""`). Текущая таблица динамического Trace содержала contract, `dtrace.sys`, `OslpDtraceExtensionEnabled`. **Нельзя смешивать AL второй загрузки с A/B/C первой**, как будто это один trace.

Независимый офлайн анализ «умного ИИ» установил: у B изменён legacy V6 Trace Host, но исходные compact/embedded V7 host records остались пустыми; V7 resolver и composer используются на этой версии; `0x29C74=0x29C60+0x14` объясняется пересборкой и добавлением 20-байтовой пустой host value, не копированием всех legacy bytes. Прежнее объяснение «base всегда побеждает ext и entry просто SKIP» недостаточно: есть различие V6/V7 и dynamic Trace path. Точная цепь байтов для всей конкретной VM без снимков остаётся `INFERRED`. `SEALED` не причина **Trace**-контроля, поскольку Trace entry `Flags=0`.

#### TraceSmoke бинарные факты

Original Schema 26100.9278 SHA `391a2818...e480889`; A-LegacySize SHA `67b15197c5edc07fe5a2653461a1740d17ac6d6dadbbea2971c1d409781132a3`; B-DualSize SHA `ba382268c00918b4a99b2efe3577782673aa6be58e7b567a776e43fca21583f9`; PhaseBinding SHA `ad587f8b9810f25b74d4524222f8e60b7af3dc6d6c1b41f931567ba5542f227c`.

Оригинальная `.apiset` file offset `0x2000`, PE section RVA `0x3000`, RawSize `0x2A000`. V6 wrapper Version 6, Count 984, EntryOff=`0x98`, HashOff=`0xAA30`, HashFactor=`31` по `+0x18`; **`+0x1C` — EMBEDDED V7 marker**, не HashFactor! В original/V6-only Value Trace пуст; B `NS.Size=0x29C84`, embedded Size по `NS+0x20=0x29C84`, `Value+0xC=0x29C60`, `Value+0x10=0x24`, UTF16 host appended at `NS+0x29C60`. Compact block end original `NS+0x29C60`. V7 живёт в независимых таблицах и строковом пуле. Отдельный `v7_decode.py` и `V7-STRUCTURES.md` в комплекте.

### 4.5 `SEALED` V7 — окончательное разграничение

**Разные поля:** outer v6 namespace flags `N+0x08=0`; embedded header V7 flags `N+0x1E=0x08`; legacy per-entry State Separation flags=1, Trace flags=0; compact *version* flags bit0 State=1, Trace=0; compact *family* flags State `0x10` (host-present бит1), нельзя путать с `SEALED`; legacy value Flags отдельны. Любые утверждения, что `Namespace[0x1C]=7` = HashMultiplier или `Namespace.Flags=1` для 26100, — **ошибки старого стороннего ИИ**, исправлены аудитом. `Value.Flags=2` не доказан способ исправления.

`ApiSetComposeSchema` для existing SEALED contract содержит запрет overlay; однако оригинальные 26100 схемы включают SEALED contracts с непустым host (`tm.sys`). **Значит SEALED не делает Host принципиально невозможным в базовой схеме.** Путь выбора принятой base через `BaseApiSetSchema` существует, но совместимый доверенный State Separation base + host до нового кандидата не был создан. Успех synthetic compact mapping не равен прохождению code integrity.

## 5. НОВЕЙШЕЕ: создан NEW dual V6+embedded V7 API Set 26100, офлайн PASS

**Это последняя фактическая работа перед handoff.** Отдельная экспериментальная DLL создана на базе точного PE `ApiSetSchema.dll` 26100.9278 SHA `391a2818fdef14f4a9b6e6ce2a7e7f01d04f943de9ef543ec683927cfe480889`. Вложенное предполагаемое имя на госте: `C:\Users\fog\Downloads\wcos_new_apiset_26100\ApiSetSchema-Windows11X-26100-OFFLINE-UNSIGNED.dll`, архив из предыдущего ответа `Windows11X-APISet-26100-V6V7-OFFLINE-LAB.zip`. Если новый чат не видит сам ZIP, **попросить пользователя прикрепить его**; нельзя придумывать sandbox-путь: в текущей сессии сам этот предыдущий сгенерированный архив не был заново предоставлен.

Конкретные изменения в legacy V6 **и embedded V7** одновременно:
| Contract | Expected mapped Host |
|---|---|
| `ext-ms-win-ntos-stateseparation-l1-1-0` | `statesepext.sys` |
| `ext-ms-win-core-stateseparationext-l1-1-0` | `onecorestateseparationext.dll` |

Сохранены per-contract `SEALED`. Число V6 контрактов **984**, старых V7 version records **984**, legacy unchanged other **982**, V7 Host count **286→288**, новые host indexes `286` и `287`. Размер namespace **`0x29C60→0x29CE0`**; PE размер 194,016 bytes. Структурные hash/offset tests в Python и дублирующий PowerShell прошли; это не full in-winload composer execution. Вызов PowerShell напрямую блокировался `PSSecurityException` («scripts disabled»); затем **без изменения постоянной Execution Policy** пользователь запустил:
```
powershell.exe -NoProfile -ExecutionPolicy Bypass -File ".\Verify-Offline-APISet.ps1"
```
и получил:
```
OFFLINE FORMAT CHECK: PASS (not a boot/trust validation)
SHA256: 75FB8F895EA030C60F435863971EEB025798ABDC306958B593DBE6EB185C0BBD
Authenticode status: HashMismatch
Contract: ext-ms-win-ntos-stateseparation-l1-1-0
  V6Host: statesepext.sys
  V7Host: statesepext.sys
  SealedV6: True
  V7DefaultHostPresent: True
  V7HostIndex: 286
Contract: ext-ms-win-core-stateseparationext-l1-1-0
  V6Host: onecorestateseparationext.dll
  V7Host: onecorestateseparationext.dll
  SealedV6: True
  V7DefaultHostPresent: True
  V7HostIndex: 287
WARNING: Modified PE is not trusted for Windows early boot.
```
**Статус:** создание/оффлайн-тест завершены; **не установлено/не загрузочно испытано**. Не надо повторно «доказывать пустой Host»; следующий исследовательский шаг — осмысленное испытание нового файла в isolated snapshot с признанием signing риска и хостов, а не повторение старого V6-only TraceSmoke. Даже если boot доходит до kernel, без настоящего принятого `statesepext.sys` в конфигурации binding может не произойти. `HashMismatch` значит исходная Microsoft подпись не совпадает с новым PE. Ранее debugger *позволял* загрузку старого modified B; это не гарантия автономного запуска нового.

## 6. Postboot State Separation Audit 1.0 / D: — завершённый вспомогательный эксперимент

Тестовый архив `Windows11X-StateSeparation-Audit-v1.0.rar` содержит `Windows11X-StateSeparation-Audit.ps1`, README и CMD launcher. Это **аудитор read-only**; сам архив не доказывает, что система изначально была развёрнута скриптом. Он описывает экспериментальную постзагрузочную схему: отдельный `D:` staging, `D:\Windows\System32\Config\OSDATA`, копии `SYSTEM/SOFTWARE/SAM/SECURITY/DEFAULT`, ручной/авто mounted `HKLM\WCOS_OSDATA`, имена tasks `Windows11X-OSData-Mount`, 32/64-bit `REG_LINK` и persistence tests. Не путать его с ранними OSBOOT/DEVICES и настоящим изолированным MainOS.

Старый реально доступный audit `Audit-20261108-092614.json`: PoCScore **16/20**, CoreScore **2/100** (это coverage evidence, НЕ готовность ОС), 7 required Core gates / **0 passed**, `GenuineWCOSProven=false`. Тогда mount-task был отмечен, но hive не всегда был доступен именно в момент аудита.

**Новый пользовательский runtime 11.11:**
```
Computer: DESKTOP-HSVPECH
OSDataFile: True
OSDataMounted: True
MountTask: False
PreviousAudit: True
ReportDate: 2026-11-11T23:26:53.7184534+03:00
ReportBoot: 2026-11-11T23:18:47.7863480+03:00
CurrentBoot: 11/11/2026 11:18:47 PM
PoCScore: 20/20
CoreEvidence: 2/100
CriticalGates: 0/7
HiveAtAudit: True
HiveNow: True
TaskAtAudit: Running
TaskNow: False
```
**Важный caveat:** `Get-ScheduledTask -TaskName` не нашёл task NOW, но в audit тогда `Running`; это может быть отличие времени, папки задачи, ACL или изменения состояния — не делать вывод «task точно удалена» по булевому `[bool]` без уточнения, если когда-нибудь станет важно. **20/20** значит завершён **postboot PoC**, а **не** WCOS Core. Не тратить на повышение его баллов новые часы.

В той же current 26100 `bcdedit current` не содержит отдельных OSData/BSP; `Get-AuthenticodeSignature` нового modified API Set позже вернул HashMismatch. Четыре файла по `C:\Windows\System32` (`statesepext.sys`, `onecorestateseparationext.dll`, `XRo.sys`, `ssboot.exe`) в пользовательской текущей Windows 26100 проверены `Exists=False`. Это известный результат; повторять Test-Path бессмысленно.

## 7. Независимые исследования мощных ИИ — краткое главное (полные детали отдельно)

### 7.1 09–10 ноября: `APISet-26100-Complete-Research-Handoff` и `V7-SEALED-Research-Handoff`

Первый AI 09.11: 69/69 файлов манифеста проверены, V6-only Trace Host корректен в B, но результирующая схеме из embedded7/resolver и dynamic extension его не использует; причина именно в этой форме реконструкции **сильная, но INFERRED для конкретного VM boot** из-за недостающих intermediate dumps и post-copy hash. В старом AI-предложении «base всегда побеждает extension» была слишком простая модель. Исправлены ошибочные interpret flags/HashMultiplier и смешение двух boot traces.

Второй AI 10.11: восстановлены обе compact записи State/Trace, нет default/importer-specific hosts в оригинале; SEALED относится к version record и накладывает gate на overlay существующего контракта, не запрещает signed base с host. У Trace runtime dynamic `AL=0` в отдельно снятом boot, но точный provenance predicate остался unknown. Static `+0x14` size growth — пустая host entry во V7 composer, не просто wrapper metadata. Написан read-only decoder `v7_decode.py`, структурный JSON. Проверены 13 исходных PE/SYS; VM/PE исходники не изменены.

### 7.2 08 ноября: engineering агент и Signing/CI

`WINLOAD-26100-9444-CALLGRAPH.md`, `DECISION.md`, `BINDING-OPTIONS.md`, `PHASE0-ABI-AND-BEHAVIOR.md`, `REGISTRY-PHASE0-MODEL.md`, `TEST-RESULTS.md`: 49/49 unit tests, 8 CAB исходно 22000, current 984 / FOS945 / old26100 974; 33 added/23 removed/1 changed; проверена цепь `BaseApiSetSchema → select alt PE → image validation → ApiSetResolveToHost → LdrpLoadImage → BlLdrBindImportReferences`. **OriginalFileName check**, `Code Integrity scenario1` при обычной ранней схеме/хосте; Custom Kernel Signers расширяют root scenarios `{2,3,4,7}`, **не 1**. `testsigning=Yes`, self-sign и драйверный test signer сами не решают admission. Это формальное **NO-GO по поддерживаемому boot path** в пакете 08.11, не утверждение что с boot debugger нельзя исследовать. Research-mode debugger admission был реально показан позже для старого TraceSmoke.

### 7.3 10 ноября: State Separation ABI offline

Результат внешнего ИИ: **26/26 exports** by names у kernel для оригинального `statesepext.sys` 22000, форма трёх фаз совпадает, последовательность 0→1 в boot, Phase2 WNF demand, схожий kernel native code. Но исходный разбор 10.11 **не имел** настоящего kernel 22000 в одном первоначальном наборе и честно оставлял semantic compatibility UNKNOWN; исправлено более поздним исследованием.

### 7.4 11 ноября: настоящий независимый семантический diff **FOS kernel 22000 vs NT 26100**

Повторно подтверждены **все семь hash-pinned** PE, PDB RSDS/DBI identities с отдельными Info Age caveats, 92 инструкции с raw bytes и 6 Phase callsites, полные body real Nt service implementations для 18 Zw по обеим версиям. Внешний Phase ABI согласуется. **После всех трёх callbacks в обоих ядрах принимается `STATUS_NOT_SUPPORTED`** (раньше cold code FOS приводил к неверному предположению). В обеих версиях `winload` для нового dependency ставит `0x04000000`, kernel сохраняет Flags+`0x68`, `MiSnapDriverRange` при bit26 исключает `INIT` из обычной cleanup; это отменяет простое «Phase2 DISCARDED ⇒ неизбежный UAF» и не доказывает все unload paths.

`Phase2` — не всегда boot этап: возможен после cache miss при создании WNF root (`CreateDisposition==1`) с именами `\REGISTRY\MACHINE\OSDATA\Notifications` и прочими descriptors; `lock cmpxchg` при публикации cache после callback не означает полную синхронизацию scratch. Из 26 NT API imports: **4 SAME в ограниченном scope, 22 INCONCLUSIVE**, ни один доказанный breaking external contract не найден, но «всё совместимо» неверно. Внутренние структуры разошлись: W22000 boot entry `0xE8` vs W26100 `0x120`, common prefix `_KLDR_DATA_TABLE_ENTRY` имеет size `0xA0`, Flags+`0x68`; internal `IoInitSystemPreDrivers` return contract поменялся; CM create/rename/copy/link сложен и требует отдельного evidence. Исходная schema26100 SEALED Host-less остаётся главным source/binding блокером.

Открытые `S01` source/host/trust; `S02` CM семантика; `S03` общая scratch сериализация; `S04` unload/discard; `S05` loader classification/reuse; `S07` ранняя policy/hive readiness; S09 servicing XRo/storage — не считать готовыми.

### 7.5 11 ноября: ранние `OSBOOT/DEVICES` (последний этап внешнего ИИ)

В `OslPrepareTarget` обоих **оригинальных** W22000/W26100 statically найдены условные:
- W22000 `DEVICES` attach callsite `0x9E9A` → `OslAttachHiveToLoaderBlock 0x32D0`; `OSBOOT` `0x9F3E` → `0x32D0`.
- W26100 `DEVICES` `0x9846` → `0x3DA4`; `OSBOOT` `0x98E0` → `0x3DA4`.
- Условие валидного selector: W22000 `0x9E38 cmp R13D,-1`, 26100 `0x97E6 cmp R12D,-1`.
- Булевы условия W22000 `0x9E44 test BL`, 26100 `0x97F0 test R15B`; дополнительные global/`OslIsHhrPrepare` gates.
- Ошибки: `STATUS_NOT_FOUND 0xC0000225` на некоторых путях считается допустимым, другие failures — stop. Helper after DEVICES attach W22000 `0x21E4`, W26100 `0x2BB0` (до полного producer graph не назначать необоснованный смысл).
- Точный upstream producers, backing store/loader block entry/порядок относительно OSData/BSP **НЕ закрыты**. Пользователь уже дал отдельному «мощному ИИ» этот narrow task с оригинальными PE; тот запросил исходные OSBOOT/DEVICES `.reg` и объектные логи. Новый ответ этого агента к текущему моменту не получен.

## 8. Архитектурные и кодовые трудности, не превращать в выдуманный GO

`statesepext.sys` FOS Phase0 (`RVA 0x8090`, INIT) при enabled/development читает `KeyRedirectionLinks`; Helper A `0x8644`, Helper B `0x9344`. Helper A использует `ZwOpenKey`→создать отсутствующий source/родителей, `TreeSync` копирует только отсутствующие subtrees и не перезаписывает existing target values/DACL, затем UUID rename старого source, volatile `REG_LINK` по исходному имени. Для некоторых ошибок удаления/создания rollback отсутствует; возможны частичные результаты, GUID backup. Не переносить функцию на 26100, игнорируя CM/IRQL/locking. Kernel Phase0 args два BOOLEAN (enabled, DevMode); Phase1 один BOOLEAN; Phase2 BOOLEAN+два `PCUNICODE_STRING`. Это реконструкция по bytes, не публичный DDI.

`RtlIsStateSeparationEnabled()` существует даже на обычной Windows; это не доказательство WCOS. `XRo.sys` 22000 импорты по именам 62/62 ядро26100 и 21/21 FLTMGR26100; `cmimcext.sys` 15/15 imports. Эти числа не равны runtime binary compatibility. Нужен user-mode State Separation host, File Filter, security templates, Storage Spaces, servicing, возможно original WSK/OEM пакеты. Публичный WSK 22000 CAB исходно 22000; «официальная PC WCOS 26100 существует» **не доказано**, но и «всё невозможно» не доказано.

## 9. Открытая задача: живая проверка нового V6+V7 в VM

Мы пришли к практическому шагу, который пользователь просил после множества кругов. Но различать **тест формата/раннего выбора** (может быть в отдельной восстановимой VM) и **полноценный binding trust/source/hive gates** (не завершены). Предыдущий TraceSmoke B-DualSize с debugger создаёт precedent исследовательского admission, не guaranteed no-debug boot. Последнее состояние нового файла — `HashMismatch`, **не установлен**. Путь к этому новому файлу на пользовательской VM приведён выше. Инструкции установки в текущем handoff **намеренно не создаются** без проверки версии VM/snapshot и admission, так как это может сделать boot невозможным.

Для следующего шага нужна одна чёткая гипотеза: *«загруженный через BaseApiSetSchema новый V6+V7, если принят, сохраняет два непустых Host в final composed namespace»*. PASS должен быть evidence **финальной скомпонованной** схемы (лучше ранний debugger snapshot), а не только загрузка Windows или присутствие строки в файле. Failure должен быть различим CI refusal vs parser/composer vs missing host module; без boot debugger такой диагноз часто невозможен. Результат GO на test schema **ещё не** GO на полноценную WCOS.

## 10. Источники и дополнительные отчёты

Смотри `02-TECHNICAL-AGENTS-AND-REVISION-DETAILS.md`, `reports/semantic-26100/README-RESULTS.md`, `reports/semantic-26100/VERIFIED.md`, `reports/semantic-26100/DIFFERENCES.md`, `reports/semantic-26100/PHASE-CALLGRAPH.md`, `reports/semantic-26100/LOADER-LIFECYCLE.md`, `reports/semantic-26100/NT-API-DETAILS.md`, `reports/api-v7/SEALED-SEMANTICS.md`, `reports/api-v7/V7-STRUCTURES.md`, `reports/preboot-signing/WINLOAD-26100-9444-CALLGRAPH.md`, `reports/preboot-signing/DECISION.md`, `reports/api-rootcause/ROOT-CAUSE.md`, `live-logs/USER-OBSERVED-WINDBG-BCD-REGISTRY-2026-11-12.md`. Предыдущие handoff от 5/8 ноября и FOS runtime map приложены как reference. Оригинальные ZIP, которых сейчас нет, перечислены в `03-TIMELINE-AND-MISSING-ARTIFACTS.md` — их отсутствие нельзя маскировать новыми придуманными файлами.

**Успех передачи:** новый чат читает этот документ, уточняет только реальную текущую развилку (удалённый агент отвечает? готова ли isolated snapshot?), и начинает с нового *dual* файла, а не с `bcdedit`, `Test-Path`, `.apiset` оригинала или старой Phase0 ABI. Все прежние результаты остаются сохранёнными.
