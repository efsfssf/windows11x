# Windows 11X / WCOS 26100 — ЕДИНЫЙ ФАЙЛ ПЕРЕДАЧИ КОНТЕКСТА

Содержит конкатенацию основных handoff разделов, доступных также как отдельные файлы. Архивы и исходные PE хранятся в отдельном полном ZIP; сам этот MD не является заменой raw binaries.



---

# НАЧАТЬ ЗДЕСЬ / 00-START-HERE-RU.md

# WINDOWS 11X / WCOS 26100 — ПЕРЕДАЧА КОНТЕКСТА В НОВЫЙ ЧАТ

**Актуальность:** 12 ноября 2026, после офлайн-проверки NEW V6+embedded V7 API Set.
**Важность:** этот пакет заменяет в вопросах *статуса* старые handoff от 5 и 8 ноября, но НЕ удаляет их из истории. Самое подробное изложение — `01-MASTER-HANDOFF-RU.md`, реальные пользовательские логи — `live-logs/`, независимые исследования — `reports/` и `sources/`. Прежде чем давать команды, прочитай ВСЕ указанные главы.

## Короткое указание новому ChatGPT / ИИ

Это продолжение многодневного проекта. **НЕ начинай исследование с нуля. НЕ отправляй пользователя повторно проверять пустые Host, отсутствие файлов на штатной 26100, BCD, OSDataRoot, 26 imports, базовый ABI Phase0/1/2 или 20/20 postboot PoC. Всё это уже известно.** Последний реальный прогресс — создана *новая* экспериментальная `ApiSetSchema.dll` 26100.9278 с согласованно добавленными двумя Host одновременно в legacy V6 и embedded V7. Пользователь проверил её скриптом `Verify-Offline-APISet.ps1`: PASS, SHA256 `75FB8F895EA030C60F435863971EEB025798ABDC306958B593DBE6EB185C0BBD`, оба Host видны, но `Authenticode: HashMismatch`. **Это офлайн-проверка; файл НЕ установлен и НЕ загрузочно испытан.** Прежний TraceSmoke B-DualSize *с boot debugger* уже показал, что альтернативная base schema реально читалась winload, но V6-only Trace Host пропал в runtime после dynamic V7 composition.

**Последний вопрос пользователя:** почему нельзя повторить уже известный TraceSmoke-style тест с новой схемой в подготовленной, восстановимой VM? Пользователь хотел сначала без WinDbg, а позже подключить его. Надо предложить конкретный диагностически полезный, воспроизводимый следующий шаг; не обещать автоматическую загрузку модифицированного PE и не скрывать ограничение boot signing scenario1. Нельзя подменять эксперимент разговором о «давайте снова найдём Host/проверим statesepext.sys на C:». Не путать это с готовностью всей WCOS.

**Непосредственная конечная цель:** добиться доверенной, корректной ранней привязки `ExpInitializeStateSeparationPhase0/1/2` на NT26100 вместо stub `STATUS_NOT_SUPPORTED`, затем реальные OSBOOT/DEVICES/OSDATA, immutable MainOS, persistent OSData, servicing/recovery. Один успешный boot/наличие строк не равен WCOS.

## Главные ограничения и правила честности

- Имена файлов и SHA-256 ниже относятся к *конкретным ревизиям* (26100.1742 vs 26100.9278/9444/9457); нельзя смешивать RVA и число контрактов: для старой схемы **974**, для актуальной исследованной **984**, FOS **945**.
- Статический ABI != реальный kernel binding != работающие фазы != State Separation != защищённая WCOS.
- `SEALED` — ограничение overlay существующего контракта, но не запрет существования Host в исходной base schema. `V6+embeddedV7` — два разных представления; правка только legacy V6 проиграла TraceSmoke.
- Файл `onecorestateseparationext.dll` для FOS 22000 в исторических материалах существует, но совместимой 26100 DLL/драйвера нет; не обещать startup только от новой таблицы.
- Надёжная загрузка новой **неподписанной** схемы без отладчика **не доказана**; `testsigning=Yes` не равен разрешению early image signing scenario1. Boot debugger ранее допускал файл после предупреждения — это отдельное испытательное условие.
- Никаких команд изменения оригинальной `C:\Windows\System32\ApiSetSchema.dll`, произвольного отключения CI, слепого `Enabled=1`, миграции `statesepext.sys` 22000 как boot-start service без отдельной гипотезы и гарантированного rollback.
- Пользователь предпочитает русский, **одну осмысленную операцию за раз**, точные инструкции: где хост, где гость, что изменяет/что не изменяет, критерии PASS/FAIL. Длинные исследования передавать файлом, а не множить бессвязные мини-отчёты.

## Минимальный порядок чтения

1. `01-MASTER-HANDOFF-RU.md` — единый подробный документ: цель, история, все тесты, выводы «умного ИИ», версии, блокеры, план.
2. `02-TECHNICAL-AGENTS-AND-REVISION-DETAILS.md` — доказательная матрица независимых ИИ, адреса/ABI/V7/INIT и исправления старых гипотез.
3. `03-TIMELINE-AND-MISSING-ARTIFACTS.md` — хронология, что действительно сделано, что отсутствует в этой сессии.
4. `live-logs/USER-OBSERVED-WINDBG-BCD-REGISTRY-2026-11-12.md` — дословные/сокращённые помеченные результаты пользователя, остановки A/B/C, BCD и OSDataRoot.
5. `04-NEXT-STEP-AND-NO-REPEAT.md` — конкретные gates и запрет замкнутых кругов.
6. `SOURCE-SHA256-MANIFEST.csv` и `ARCHIVE-CONTENTS.txt` — что реально вложено и чего нет. Часть сырого исторического пакета (например Windows 10X 20279 ZIP с шрифтами) НЕ повторно упакована; исходное имя и provenance зафиксированы.

**Уровни доказательства:** `RUNTIME` (пользовательский вывод из конкретной загрузки), `STATIC` (байты конкретного PE), `OFFLINE_TEST` (парсер/скрипт), `INFERRED` (обоснованное объяснение), `UNKNOWN`, `NO-GO` (при определённых условиях). Никогда не повышать `INFERRED` в `RUNTIME` без trace.


---

# ПОЛНОЕ ДОСЬЕ / 01-MASTER-HANDOFF-RU.md

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


---

# РЕЗУЛЬТАТЫ УМНЫХ ИИ / 02-TECHNICAL-AGENTS-AND-REVISION-DETAILS.md

# ТЕХНИЧЕСКИЕ РЕЗУЛЬТАТЫ ВНЕШНИХ «УМНЫХ ИИ» — сохранение точных выводов

**Статус**: 12.11.2026. Это не общая новая гипотеза; конспект сохранённых отчётов, снабжённый ссылками на подлинные исходные MD/CSV в `reports/`. В некоторых ранних отчетах было меньше доказательств, чем в итоговом семантическом аудите 11.11. Наиболее поздние подтверждённые исправления имеют приоритет, а не более уверенная формулировка старого ИИ.

## A. Первый AI 09.11: APISet «Complete Research» (вне процесса VM)

Исходная формулировка: **вывод для показанной загрузки — обоснованная реконструкция, а не окончательное доказательство причины**. Сверены все **69/69** исходных SHA-256; V6 записи B-DualSize корректно содержали Host Trace. B Stage по реальному WinDbg снимку сначала попал в буфер; при dynamic extension его legacy mapping потерялся. Разрешение контракта возвращало `STATUS_SUCCESS`, но `Host.Length=0`; тестовая `WcosTraceSmoke.sys` не была привязана.

Статически доказано в W26100.9444:
- V6 wrapper `Version=6`, Count=984, `EntryOffset=0x98`, HashOffset=0xAA30, HashFactor=`0x1F` **по 0x18**.
- `N+0x1C` начинается embedded7 header (`07 00 08 FF`), `N+0x20` содержит embedded size; `N+0x08` outer namespace Flags=0, не 1.
- `ApiSetComposeSchema` выбирает V7 path для wrapper6+embedded7: RVA `0x27958B–0x2795AD` → `ApiSetComposeSchema_V7` `0x27AA24`. `ApiSetResolveToHost` `0x279181–0x27919F` выбирает V7 resolver `0x27A60C` при определённых flags `E+2`.
- B legacy Trace entry offset `N+0x4358`, value `N+0x94F0`; `HostOffset=0x29C60`, `HostLength=0x24`, имя 18 UTF16 WCHAR (`WcosTraceSmoke.sys`). Это дописано непосредственно за исходными compact V7 blocks, которые заканчиваются по N+`0x29C60`.
- `NS.Size` B=`0x29C84`, а финальный runtime `0x29C74`; арифметика `0x29C60+0x14` соответствует добавлению **пустой host value record** в compact composition, а не универсальному 20-byte composer metadata. Путь к этому статически восстановлен во втором исследовании 10.11.
- `BaseApiSetSchema` выбирает альтернативное **имя базового PE**, а `ApiSetSchemaExtensions` работает с overlay/составлением; смешивать нельзя.
- `SEALED` не объясняет пустой Trace: `Trace.Entry.Flags=0`. State Separation имеет `Flags=1` и другое ограничение.

Ограничения TraceSmoke: гостевой post-copy SHA не был снят; три промежуточных memory dumps в одном boot отсутствуют; результаты A/B/C и AL=0 — разные boots; не снимался полный compact resolved output после финальной composition. Поэтому точная причинно-следственная локализация каждого byte изменения **не является полностью динамически доказанной**, хотя механизм V7 сильнее «base всегда выигрывает».

Ранее другой ИИ предлагал упрощённое `ApiSetComposeSchema(base,ext): for existing contracts SKIP, else append`, потому что итог Size совпал `Original+0x14`. Эта схема неверно выдаёт одну гипотезу за доказанный алгоритм; приоритет более позднего `ROOT-CAUSE.md`/`MISCONCEPTIONS.md` и `TRACE-EXPLANATION.md`.

Файлы: `reports/ROOT-CAUSE.md`, `reports/FACTS.md`, `reports/STRUCTURES.md`, `reports/BINARY-COMPARISON.csv`, `reports/DATAFLOW.md`, `reports/HYPOTHESES.md`, `reports/UNRESOLVED.md`, `reports/SEAL-BOUNDARIES.md`, `reports/MISCONCEPTIONS.md`, `reports/NEXT-READONLY-TEST.md`.

## B. Второй AI 10.11: embedded V7 + SEALED + read-only decoder

**Input hashes** (для указанных 26100 releases): W26100.9444 `1c1a978448b8e0fc2284c13d6e55fde8bf9827dca432cdc8a9be66c2e729adda`; BaseApiSet 26100.9278 `391a2818fdef14f4a9b6e6ce2a7e7f01d04f943de9ef543ec683927cfe480889`; K26100.9457 `d90c69cfa925d42037ef1cdba3ff5b3f13cc14e16060938073b97e396fdde2f2`; FOS statesepext `167fda36a71c2aad4c146bc66ae4c83e9d525d008d6abfd8eda0e056c9a96658`. Verified input 13 PE/SYS source files as reported.

`V7-STRUCTURES.md` bounded decoder description: section `.apiset`: file start `0x2000`, PE RVA `0x3000`; N=namespace start; E=N+`0x1C`; actual compact block addressing `E + encodedOffset − WORD[E+0x12] + index*stride`. Для данного input `WORD[E+0x12]=0x1C`, значит encoded offsets **численно совпадают с N-relative** — ошибочно прибавлять 0x1C дважды. Строки Host/importer используют pool-relative offsets, отдельно `DWORD[E+0x24]`. Compact records имеют 12 байт, legacy entry 24 байта, legacy value 20 байт. Декодер read-only `v7_decode.py` **не** полный Windows API Set parser/composer; он bounded по двум целям.

Состояния оригинала:
- `ext-ms-win-ntos-stateseparation-l1-1-0` — both legacy/compact default Host отсутствует, importer-specific records отсутствуют; compact version flags bit0 SEALED.
- `ext-ms-win-ntos-trace-l1-1-0` — Host пуст, unsealed; динамический Trace fallback в W26100 таблице `OslpDynamicSchemaExtensions`.
- `ext-ms-win-core-stateseparationext-l1-1-0` — original base также SEALED/Host empty.
- Примеры оригинальных **SEALED с непустым SYS Host**, включая `tm.sys`, подтверждают: SEALED мешает перезаписать имеющийся контракт обычной схемой **расширений**, но **не запрещает** непустой Host в исходной корректной BASE.

**Flags и адреса в W26100**:
| Сущность | Поле | Значение и смысл |
|---|---|---|
| Legacy namespace | N+0x08 DWORD | 0, не per-entry SEALED |
| embedded namespace | E+0x2 = N+0x1E BYTE | `0x08`; отдельный namespace-level gate, bit0 composition-guard; bit2 маршрутизирует resolver; bit3/4 hash helpers |
| Legacy State entry | N+0x4328, first DWORD | 1=SEALED |
| Legacy Trace entry | N+0x4358, first DWORD | 0 |
| Compact State version | N+0x25A1A (+0xA) | bit0=1 SEALED, file 0x27A1A, PE RVA 0x28A1A |
| Compact family | +0xA | flag `0x10`; bit1 связан с host present (НЕ SEALED); bit4 не до конца восстановлен |
| Legacy value entry | +0 | 0; `Value.Flags=2` не является доказанным `HOST_EXTENSION` способом |

Найден ограниченный механизм отказа overlay SEALED для найденных записей: legacy composer `0x279709–0x27970D`; V7 extension path `0x27C622–0x27C646`, V7 output host path `0x27C9CA–0x27CB02`. Значения `N+0x1C=7` — **не** стандартный v6 HashMultiplier. Старый неподтверждённый «unseal by schema-level Flags=1/clear entry/value 2» отвергнут независимой проверкой. Нельзя считать любую несостоявшуюся подмену STATE host результатом sealed-gate: исходная base с другим compact V7 host раньше вообще не существовала.

Runtime control Trace: таблица `OslpDynamicSchemaExtensions` W26100 RVA `0x2B2DB0`, строка contract RVA `0x2BAA30`, host `dtrace.sys` RVA `0x2BB008`, predicate `OslpDtraceExtensionEnabled` RVA `0x22F48`; в **отдельном** boot `AL=0` → empty string fallback. Точную статическую причину `AL=0` по этому единственному runtime снимку не установили; не переносить это значение в boot A/B/C.

**Что осталось в decoder:** неизвестные private fields E+1/+8/+C/+10/+74.., смысл некоторых header/descriptors, все importer-specific paths, вся универсальная валидность В7. Для новых host default записи offline builder 12.11 сделал более широкую операцию, но в реальном `winload` он ещё не проверялся.

Файлы: `reports/VERIFIED-V7.md` (если имеется под этим именем), `reports/V7-STRUCTURES.md`, `reports/SEALED-SEMANTICS.md`, `reports/TRACE-EXPLANATION.md`, `reports/UNRESOLVED.md`, `reports/v7_decode.py`. В текущем пакете имена оставлены как у исходного top-level `VERIFIED.md` / `V7-STRUCTURES.md` при конфликте имен через `reports/api-v7/` и `reports/semantic-26100/` (см. INDEX).

## C. Агент 08.11: Signer scenario, PDB, Phase ABI и Phase0 helper

**Winload** 26100.9444 из `WINLOAD-26100-9444-CALLGRAPH.md`:
- `OslLoadApiSetSchema 0x2309C`, получает SYSTEM hive loader context, registry value `BaseApiSetSchema` `0x23104/0x23111`; выбор PE suffix `0x23171`, `OslpLoadApiSetSchemaImage 0x231A9`; section lookup `0x23478–0x234A6`, copy до `0x231EA`.
- image trust именно ранних сценариев: обычный init CI policy3 `OslInitializeCodeIntegrity` `0x17731–0x17745`, store `0x17C6D`; API Set schema scenario1 at `0x1C2B0`, `0x198B5A–0x198B60`; early kernel API Set Host via `0x1BE98→0x19D7FE→0x1C168..0x1C180` тоже scenario1. `ImgpValidateImageHash` custom kernel signers scenarios `{2,3,4,7}` at `0x197927–0x197965`, **не scenario1**.
- Проводится basename vs PE `OriginalFilename` check at `0x1959BB`, mismatch NTSTATUS `0xC0430007` (отдельная причина failure от подписи). Нельзя просто переименовать DLL без учёта embedded metadata.
- После принятого schema существует механизм early binding: `OslpLdrExOverrideImport 0x1BCEC` → `ApiSetResolveToHost 0x279130` → `LdrpLoadImage` → `BlLdrBindImportReferences`.
- Старый исход `NO-GO`: отсутствует принятый compatible signed schema and host/hives, а не отсутствует механизм выбора вообще. `testsigning`, самоподпись kernel signer, только `Get-AuthenticodeSignature` не закрывают boot trust gate. `Policy1/DisableIntegrityChecks` исключались правилами проекта.

**Kernel external ABI** (26100.9457), reconstructed proto from bytes, not official Microsoft SDK:
```c
NTSTATUS ExpInitializeStateSeparationPhase0(BOOLEAN Enabled, BOOLEAN DevMode);
NTSTATUS ExpInitializeStateSeparationPhase1(BOOLEAN Enabled);
NTSTATUS ExpInitializeStateSeparationPhase2(BOOLEAN Enabled,
                                          PCUNICODE_STRING Source,
                                          PCUNICODE_STRING Target);
```
Phase0/Phase1 исполняются ранним ядром, Phase2 demand by WNF, NTSTATUS in EAX. Старый `statesepext.sys` 22000 импортирует 18 Zw + 8 Rtl/Ex/CRT, не может быть заменён `return STATUS_SUCCESS` без работы по переносу реестра.

**Phase0 22000**: export RVA 0x8090 INIT, Phase1 RVA `0x8190`, helper A `0x8644`, helper B `0x9344`; enabled/DevModeEverEnabled; `KeyRedirectionLinks` reads `SourceNtPath`, `TargetNtPath`, `KernelLink`, `TreeSync`, `AlwaysEnabled`. `0x89DD` open source; failure→create parents and source (`0x89F4`, `0x8A22`); TreeSync existing target rules (`0x8A77..0x8AAC`); recursive helper `0x1050`, `0x11A0`; GUID rename `0x1630` / ZwRenameKey `0x1775`; REG_LINK recreation `0x17C8`. No transaction-wide rollback; potential GUID backups and partial targets. Original source of FOS redirection map included.

**49/49** agent unit tests passed; 8/8 WSK CAB examined were 22000.1, not 26100. No supported public PC26100 OEM source proven. `SAME` measured portions do not imply stable IRQL/scratch/ACL/reparse.

## D. Агент 10.11: StateSeparation 22000-vs-26100 Offline Audit — что тогда было и чего не хватало

Один архив `Windows11X-StateSeparation-22000-vs-26100-Offline-Audit-2026-11-10.zip` есть в исходных данных проекта. Там high-quality `VERIFIED.md`, `INCOMPATIBLE.md`, `UNKNOWN.md`, `NEXT-EVIDENCE.md`, `COMPATIBILITY-MATRIX.csv`; работа только read-only. Обнаружены 26/26 имен, вызовы phase и WNF, `INIT` retention path на 26100; не найден фундаментальный разрыв *внешней формы Phase ABI*. **Важная оговорка отчёта:** исходное предоставленное содержимое не включало оригинальное ядро 22000 — другой упакованный kernel был 26100.1742, поэтому реальное 22000-vs-26100 NT API semantic comparison в этом конкретном аудите было **UNKNOWN**. Поздний агент 11.11 впервые добавил подтверждённый K22000 и закрыл это ограничение частично. Нельзя описывать аудит 10.11 как уже доказавший все межъядерные семантики.

Отдельный `INIT-LIFECYCLE-2026-11-11.rar` (включён в handoff) содержит доказательства for W26100 K26100 `MiSnapDriverRange`: new dependency получает bit `0x04000000`, loader entry Flags+0x68, в ordinary cleanup имя секции `INIT` исключено при bit26. Это снимает упрощённую гипотезу о гарантированном freed code для Phase2, **не** доказывает no unload/discard in every lifetime path.

## E. Агент 11.11: независимое **семантическое** сравнение FOS22000 и NT26100

Это наиболее сильный исследовательский отчёт для ABI/state separation `README-RESULTS.md`, `VERIFIED.md`, `DIFFERENCES.md`, `PHASE-CALLGRAPH.md`, `LOADER-LIFECYCLE.md`, `NT-API-DETAILS.md`, `NT-API-COMPATIBILITY.csv`, `STATE-DEPENDENCIES.md`, `UNRESOLVED.md`. **Семь hash-pinned inputs**, 92 raw machine instructions, 6 callsites phases, 18 Zw dispatch linked to Nt bodies ×2. Source identities:

| Сокращение | Версия | SHA256 |
|---|---|---|
| K22000 | 22000.1 | `a9b5c0a47f1c4606a94adb3674017cbd35448c3792c4d26f51847a3ffa8c7b7c` |
| W22000 | 22000.1 | `9fb057cb65462624f35b41fe686f71fc03f11e3770fdb81e04900640030ad41d` |
| SS22000 | 22000.1 | `167fda36a71c2aad4c146bc66ae4c83e9d525d008d6abfd8eda0e056c9a96658` |
| Schema22000 | 22000.1 | `49696b4557023ea455aa7731a18467b623292a7c2ec837d8437a9c2dcd9cd498` |
| K26100 | 26100.9457 | `d90c69cfa925d42037ef1cdba3ff5b3f13cc14e16060938073b97e396fdde2f2` |
| W26100 | 26100.9444 | `1c1a978448b8e0fc2284c13d6e55fde8bf9827dca432cdc8a9be66c2e729adda` |
| Schema26100 | 26100.9278 | `391a2818fdef14f4a9b6e6ce2a7e7f01d04f943de9ef543ec683927cfe480889` |

### E.1 Точные фазы + IAT для новых K26100

| Фаза | K22000 call / IAT RVA | K26100 call / IAT RVA | Реальные args |
|---|---|---|---|
| P0 | `0xB0B60B / 0x13D2D0` | `0xC294EE / 0x150378` | `CL`=Enabled; `DL`=DevMode |
| P1 | `0xB10280 / 0x13D2C8` | `0xC6DC8E / 0x150368` | `CL`=CmStateSeparationEnabled != 0 |
| P2 | `0x7797B5 / 0x13D2D8` | `0xB0F996 / 0x150370` | `CL` Enabled, `RDX`/`R8` source/target `UNICODE_STRING*` |

Call order: `IoInitSystemPreDrivers`→P0, после завершения остальных driver-стадий `Phase1InitializationIoReady`→P1, поздние WNF consumers→P2 при cache miss and `CreateDisposition==1`. **НЕ** unconditional «boot phase0→1→2». В обоих kernels **все 3** NTSTATUS `0xC00000BB` допускаются как необязательная реализация (`K22000 0xB3CCDE/0xB3F858/0x7797C3`; `K26100 0xC29506/0xC6DCA6/0xB0F9A4`). В раннем 10.11 отчёте first two 22000 checks не видели, потому что вынесены в cold code; **11.11 это исправлено**.

### E.2 INIT, dependency Flags, lifetime

| Этап | W22000/K22000 | W26100/K26100 |
|---|---|---|
| `LdrpLoadImports` new dep flag | W `0xEF82C` `0x04000000` | W `0x19D5FB` `0x04000000` |
| flags copied to loader `+0x68` | W `0xEFEE5` | W `0x19DCDA` |
| kernel selector `INIT` keep | K `0x77C355` bit26 and `0x77C467` compare `0x54494E49` | K `0xA536FA` and `0xA53999` |
| kernel cleanup | K `0x8111A9..` | K `0x705B30..` |

`_KLDR_DATA_TABLE_ENTRY` common prefix size `0xA0`, `DllBase +0x30`, `Flags+0x68`, `LoadCount+0x6C`, `LoadedImports+0x88`; kernel reads them. **Полный boot entry allocation** W22000 `0xE8`, W26100 `0x120`, internal pointers diverged; не переносить старые private offsets, например `PipInitializeDriverDependentDLLs` W22000 uses `entry+0xE4`, W26100 `entry+0x11C`.

В normal mode2 cleanup `INIT` нового dependency с bit26 **не выбран**; нет доказательства гарантированного Phase2 use-after-free. Но lifetime реального Host, reuse of existing entry, explicit unload/discard/low memory ещё UNKNOWN.

### E.3 Реальные NT API implementations (не одни Zw stubs)

FOS statesepext imports 18 Zw и 8 Rtl/Ex/CRT. В kernel PE `KiServiceTable` K22000 `0xCA690`, K26100 `0xDADC0` из on-disk entries хранит **прямые RVA**, не runtime encoded shift. Для всех 18 ×2 получены реальные Nt bodies по service index, а не только Zw prologs. Ровно **4 SAME** при оговорённых inputs:
1. `RtlInitUnicodeString` (22000 RVA `0x2DEF50`, 26100 `0x431490`) — NULL, LENGTH clamp, descriptor semantics.
2. `RtlAppendUnicodeToString` (22000 `0x2B1D30`, 26100 `0x415E40`) — bounds/NULL/update and `0xC0000023`.
3. `RtlGetControlSecurityDescriptor` (22000 `0x7416E0`, 26100 `0xA699B0`) — Control/Revision under stated contracts.
4. `RtlSetControlSecurityDescriptor` (see CSV for complete IDs and bounds).

Остальные **22 INCONCLUSIVE**, включая register CM create/link/rename/copy, storage/security/IRQL/scratch; ни один доказанный unconditional external breaking change не найден. Это положительная возможность продолжать исследование, **не** бинарное доказательство совместимости всего модуля.

### E.4 WNF descriptors, caching и static paths

`WNF Phase2`: после удачного `ZwCreateKey` с `REG_CREATED_NEW_KEY` и `CreateDisposition==1` — callback, затем atomic `cmpxchg` cache pointer: K22000 `0x779787`, K26100 `0xB0F9C5`. WNF descriptors in both consistent names including `\REGISTRY\MACHINE\OSDATA\Notifications`; base K22000 `0xA38150`, K26100 `0xB4D6F0`, stride `0x30`. Возможный поздний `NtCreateWnfStateName` path K22000 `0x6EE0AD...`, K26100 `0x88220E...`. Atomic publication ≠ serialized prior callback or shared scratch F globals `+0x3060/+0x3068`.

### E.5 Обнаруженные *внутренние* изменения, не приписывать external ABI

- В `IoInitSystemPreDrivers` parent return: K22000 checks boolean-style `AL`, K26100 checks sign of EAX NTSTATUS. External Phase0 NTSTATUS callback unchanged in observed path.
- `Cm` functions rearranged (NtOpenKey via NtOpenKeyEx, NtCreateKey via stack-expansion helper, etc.), без полного доказательства эквивалентности.
- `MiFreeBootDriverInitializationCode`, `MmCallDllInitialize`, `MiIssueDllInitializeCall` internal implementations differ.
- `_LOADER_PARAMETER_BLOCK` обоих kernels size `0x170`, `ArcBootDeviceName +0xB8`, `ArcOSDataDeviceName +0x150`; поля имеются и используются, **но** это не означает, что в текущей 26100 `osdatadevice` отдельный от C:.

### E.6 Инструменты и границы PDB

PDB GUID/RSDS and DBI Age match each PE; **PDB Info stream Age differs** (например K26100 RSDS age1, DBI1, Info6; W26100 RSDS2, DBI2, Info4). Не утверждать, что PDB default exact-match на любой WinDbg автоматически доказан. Symbol names cross-check with byte ranges and export; 3121/3126 K22000, 3311/3317 K26100 anchors match, W22000 416/416, W26100 476/476. Unmatched public aliases `ExAcquireRundownProtection`, `ExReleaseRundownProtection`, `KeLoaderBlock`, `KeNumberProcessors`, `RtlMoveMemory`, плюс `memset` на K26100. Claims для этих alias addresses не делались.

## F. Новая схема 12.11: что новый ИИ должен проверить сам, если файл приложен

Схема `ApiSetSchema-Windows11X-26100-OFFLINE-UNSIGNED.dll` на базе 26100.9278, SHA нового файла `75fb8f895ea030c60f435863971eeb025798abdc306958b593dbe6eb185c0bbd`, размер `194016`, по пользовательскому PowerShell V6 и V7 default-host для State (`statesepext.sys`) / Core (`onecorestateseparationext.dll`) оба непустые, legacy State SEALED=True, V7 host-present=True, indices `286/287`. Исходный Host count 286, новый 288, old 984 legacy contracts, old 984 V7 version records; новый Size `0x29CE0`. Original signature `HashMismatch`, **не early-loaded**. Не менялась OriginalFilename PE по результату builder-описания, но **не** считать acceptance proof. Core `.sys` и user-mode `.dll` не установлены на обычной текущей 26100. Старые STrace/TraceSmoke расширения не содержат работающего State Separation Host.

Сам builder и `Verify-Offline-APISet.ps1` ранее были предоставлены пользователю в `Windows11X-APISet-26100-V6V7-OFFLINE-LAB.zip`, но в *текущем контейнере handoff* оригинального созданного архива нет. SHA можно проверять при повторном прикреплении. **Не создавать новый файл с тем же именем и другим SHA, выдавая его за уже проверенный**. Это критично для различения попыток boot.

## G. Итоговые неизвестные / status gates «умного ИИ»

| ID | Что остаётся UNKNOWN | Что требуется |
|---|---|---|
| S01 | Совместимый доверенный оригинальный 26100 State Separation Host/schema source | точные OEM/MUM/CAT/PE providers и early signing scenario1; либо ограниченный поддерживаемый diagnostic route |
| S02 | Полная семантика CM registry paths | graphs CmCreateKey/CmOpenKey/CmSetValueKey/CmRenameKey, status, callbacks, recovery/ACL |
| S03 | Параллельный доступ к scratch F globals | полный incoming Phase2/CopySyncKeyTree graph и lock scopes |
| S04 | Lifetime/discard/unload | explicit unload, MM discard, reuse, refcounts, final real Host flags |
| S05 | Полный loader classification/reuse | gate для каждого image, paths reuse versus new dependency |
| S06 | Storage/Security/Pool/CRT import semantics | real NT bodies, options & IRQL |
| S07 | policy → loader block → early hives | producer guards DEVICES/OSBOOT/OSDATA, readiness, error/resume |
| S08 | Persisted-state caching/reset | callers, reset, negative cache, malformed values |
| S09 | XRo, user-mode, servicing/recovery | согласованные 26100 components, update/rollback strategy |
| S10 | PDB Identity provenance | publisher info-age discrepancies и omitted alias |

**Правило:** `INCOMPATIBLE` требует доказанного нарушенного контракта, а не неизвестности. `COMPATIBLE` в отчёте относится строго к указанным механизмам и не означает boot. Самый новый valid runtime proof для V6+V7 — только OFFLINE PASS.


---

# ХРОНОЛОГИЯ И ПРОБЕЛЫ / 03-TIMELINE-AND-MISSING-ARTIFACTS.md

# Хронология, реестр исходных материалов, отсутствующие файлы

## История работы без потери контекста

| Период | Работа | Что доказано / что не было доказано |
|---|---|---|
| До 05.11 | Windows 10X Santorini 20279, ModernUX, Composer, OOBE, usermgr | Оригинальная 20279 загружалась; OOBE user creation через изученные WinRT API не удалось (`0x80070005`), `usermgr.dll` исследован. Архивы `ModernUX-20279-20261104-013300.zip`, `OOBE-20279-20261104-012934.zip`. Это **отложенная** UX-ветка, не блокер настоящей WCOS Core. В 20279 `ModernUXShellApp.exe` импортирует `RtlIsStateSeparationEnabled` из ntdll — признак зависимости, не доказательство переноса GUI в 26100. |
| 04–05.11 | Изучена документация и архивы WSK Factory OS 22000, работающая FOS, исходные функции State Separation, оригинальная Storage Spaces/GPT layout. | WSK 22000 не превращает IoT26100 в WCOS; StateSeparation не single-switch. |
| 05.11 | FOS runtime maps, XRo instances, persists OSDATA/data, MainOS write denial; BCD 14 objects, Windows 10X and WIM sources, старый полный handoff. | Настоящая FOS 22000 — эталон. Прямой OSData probe persists, custom redirect probe не сработал; не считать 459/465 rules живой защитой всех paths. |
| 06.11 | Старые PE 26100.1742, Kernel ABI surface, регистры фаз и IAT; сравнение FOS/26100 `.apiset`, full registry. | Оба ядра содержат Phase0/1/2 callsites; в обычной 26100 IAT fallback `STATUS_NOT_SUPPORTED`. Старые 26100 схемы 974 contracts, FOS 945. 26 imports name matches ≠ semantics. |
| 07.11 | Аудит 22000 WSK CAB/CAT/XRo/SSBoot, `SEALED` per-entry, DevMode, Runtime FOS IAT, WinDbg exceptions `OslGetRootCell`/`0x5F`. | FOS runtime реально real statesepext binding; 26100 default no Host. Нельзя включать policy вслепую. |
| 08.11 | Полный офлайн engineering AI: Winload code paths CI scenarios, Phase0 helper semantics, Source and Binding GO/NO-GO, PDB, Signing Lab. | BaseApiSetSchema селектор доказан статически. `scenario1` не входит в custom kernel-signer 2/3/4/7; Phase0 ABI reconstructed, 49/49 offline unit tests. Boot NO-GO на старом комплекте. |
| 08.11 | Windows11X-StateSeparation-Audit-v1.0 / постзагрузочный D: PoC | 08.11 audit 16/20, Core 2/100, 0/7. |
| 09.11 | Пользователь загрузил B-DualSize TraceSmoke, снимал WinDbg на старте 26100 | Alternative schema реально загружалась под debugger. В A/B v6 Trace Host есть, C после dynamic V7 — пуст. Осторожно разделять две загрузки. |
| 09–10.11 | Внешний AI: 69 input hashes, root cause diff, V7 decoder и `SEALED` patch semantics | Наиболее вероятная причина — V6-only правка не обновляла compact embedded7; отчёты исправили неверные объяснения о `N+0x1C=HashMultiplier`, global SEALED и `VALUE Flags=2`. |
| 10.11 | StateSep ABI Offline AI: 26 names, callbacks, 26100 INIT retention, no kernel22000 in первом пакете | Не было доказано все Nt semantics; INCONCLUSIVE. |
| 11.11 | Новый FOS kernel22000 и loader были проверены, мощный AI изучил **реальные** 2 ядра | Все три Phase STATUS_NOT_SUPPORTED в 22000/26100; `0x04000000` dependency и INIT retention в обоих; 4/26 SAME limited, 22/26 inconclusive, 18 Zw→Nt lookup ×2. |
| 11.11 | Живая FOS `mountvol`, `fltmc`, `NtLinks`, BCD; начато точное отслеживание `OSBOOT`/`DEVICES` | Полная цепочка FOS OSDataRoot→HarddiskVolume9=O: доказана. BCD связывает `M:`/`O:`/`B:`. Winload guard calls найдено, upstream producers/real attach timing пока неизвестны. |
| 11.11 | Отдельный мощный ИИ получил narrow OSBOOT/DEVICES задачу; попросил исходные `.reg`, ObjMgr logs | Отдельный handoff с derivations был предложен; пользователь отложил его отправку. Окончательного ответа этого ИИ пока не было. |
| 11.11 | Проверена действующая Windows 26100 `C:\Users\fog`, BCD, OSDataRoot, Audit Latest.json | Текущая guest Windows11 с C:, без osdata/bsp в BCD; OSDataRoot→Arc rdisk0 part3. Postboot D: hive и mounted `HKLM\WCOS_OSDATA`; Latest Audit **20/20**, Core 2/100, 0/7. |
| 11–12.11 | Вернулись к API Set; создан новый dual V6+embedded7 PE с двумя Host, сохраняющими SEALED | Offline Python/PowerShell validation PASS, SHA `75fb8f...c0bbd`, Host indices 286,287, `HashMismatch`. **Новая DLL не загружалась при boot, не установлена**. |
| 12.11 | Пользователь просит полный контекст для нового чата, чтобы не зациклиться | Этот handoff, логи, манифесты, оригинальные отчёты/доступные source archives. |

## Новые файлы, которыми пользователь точно располагает по прошлым шагам

`C:\Users\fog\Downloads\wcos_new_apiset_26100\` — пользователь запускал `Verify-Offline-APISet.ps1`; там новый `.dll` и вспомогательные файлы. Предыдущий сгенерированный архив имел название `Windows11X-APISet-26100-V6V7-OFFLINE-LAB.zip`. **В текущем `/mnt/data` исходно не было его точной копии**, поэтому этот handoff НЕ выдаёт новый сгенерированный `.dll` за приложенный без SHA. При переносе в новый чат приложить из пользовательской папки указанный ZIP или сам DLL вместе с PowerShell проверкой. Ожидаемый SHA DLL `75fb8f895ea030c60f435863971eeb025798abdc306958b593dbe6eb185c0bbd`.

Другие прошлые отчёты могли иметь имена `Windows11X-APISet-26100-Complete-Research-Handoff-2026-11-09/`, `Windows11X-APISet-26100-V7-SEALED-Research-Handoff-2026-11-10/`, `Windows11X-FOS22000-vs-NT26100-Semantic-Comparison-2026-11-11/`, `Windows11X-WCOS-Roadmap-and-HiveGate-Audit-2026-11-11.zip`, `Windows11X-FOS-Hive-and-ObjectManager-Evidence-2026-11-11.zip`. Эти имена фигурировали в чате; **не все указанные полные ZIP присутствуют в текущем container**. Но отдельные `ROOT-CAUSE`, `VERIFIED`, `DIFFERENCES`, `PHASE-CALLGRAPH`, `V7-STRUCTURES` и т. д. сохранены в `reports/` и не пропадают из этого пакета.

## Оригинальные исторические источники из 05.11

- `ModernUX-20279-20261104-013300.zip`: ModernUXProduct, shell executable/JS, assets, winmd. Отдельный исходный архив пользователя сохранён в проекте, в новый ZIP перенесён производный вариант с удалёнными файлами шрифтов.
- `OOBE-20279-20261104-012934.zip`: CloudExperienceHost, WinRT metadata, OOBE JavaScript, OEM prompts; сохранён оригинал, в новый ZIP перенесён производный вариант без шрифтов.
- `Windows11X-WCOS-2026-11-05-COMPLETE-HANDOFF.zip`: 78 файлов, включает вложенные упомянутые архивы, многочисленные screenshots, JSON 787 KB, WSK feature maps/CABs и старые parser outputs. Вложен в текущий новый пакет в версии с исключёнными nested 20279 архивами с возможными шрифтами, и дополнительно приложен `Windows11X-WCOS-HANDOFF-2026-11-05-LATEST-FULL.json` и его README и другие производные тексты. Если другой чат должен исследовать UX 20279, пользователь может дать оригинальные архивы отдельно.
- `Windows11X-WCOS-TECHNICAL-HANDOFF-2026-11-08.zip`: хешированные early research docs, PE/source ZIP, PDB, maps, **вложен** в source archive.
- `Windows11X-StateSeparation-22000-vs-26100-Offline-Audit-2026-11-10.zip`: большое исследование 170 entries, содержит вложенные старые 20279 ZIP с assets/fonts; текущий handoff прилагает extraction **без этих вложенных 20279 ZIP** и с собственным index. Оригинал по прежнему в проектном состоянии, но не выдается как оригинально побайтно идентичный новый архив.
- `FOS.rar`: оригинальные FOS22000 kernel, Boot/winload etc, **вложен** в sources.
- `INIT-LIFECYCLE-2026-11-11.rar`: оригинальные результаты powerful AI, `MiSnapDriverRange`, PDB/asm, **вложен**.
- `WCOS-26100-9457.zip`: SHA-pinned current kernel/winload/schema source bytes, **вложен**.
- `Windows11X-StateSeparation-Audit-v1.0.rar`: аудитор и README, **вложен**.
- `Windows11X-26100-Signing-Lab-v0.2.rar`: TESTSIGNED/UNSIGNED probe source/PE for old experiments, **вложен как legacy, не boot-ready**.

## Отсутствующие оригиналы и неизвестное содержание

1. `WCOS-FullRegistry-22000.zip` и `WCOS-FullRegistry-26100.zip`, SHA 7333... и 8bc7..., ранее были предоставлены и использованы для статистики. В current /mnt/data не найдены. Производные сводки и runtime map есть, **не** полные `.reg` как primary evidence.
2. **Новый** физический архив `Windows11X-APISet-26100-V6V7-OFFLINE-LAB.zip` от предыдущего ассистента в current mounted filesystem не присутствует. Пользователь его локально распаковывал; в master описан результат.
3. Реальный `Latest.json` от 11.11 `20/20` на госте не загружался в current session как файл; присутствует пользовательский вывод чисел. В текущем архиве есть прошлый `Audit-20261108-092614.json` (16/20).
4. Complete `11.11 Semantic Comparison` full evidence `.asm` package по тексту `README-RESULTS` был у ИИ, но в текущую сессию отдельный ZIP не приложен; top-level report files и CSV есть. Отдельно не выдавать «все 92 ASM файлы» за включённые, если таких нет в приложенном пакетe.
5. Нет полного raw WinDbg dump той же загрузки для нового dual V7. Нет доказательства его boot acceptance и нет approved signed 26100 `statesepext.sys` и `onecorestateseparationext.dll`.
6. Нет нового отчёта powerful AI о точных producers DEVICES/OSBOOT после запроса `.reg`; задача остаётся открыта.
7. Нет аттестованной 26100 PC WCOS OEM full image/product source, CAT/MUM/servicing/recovery match. Публичные источники могут быть неполными; отсутствие находки не равно математической невозможности.

## Правило чтения устаревших источников

Для `26100.1742` использовать только RVA/хеши этой версии и `.apiset` 974. Для `26100.9444/9457/9278` использовать новые RVA/984. Отчёт 10.11 без оригинального K22000 нельзя цитировать как полный 22000-vs-26100 semantic proof; его supersedes 11.11 report. Не приписывать Bash hooks ошибки и Claude `[cyber]` refusal бинарному факту: это была особенность чужой исследовательской среды 09.11, а не сообщение `winload` или Windows. Не доверять утверждениям старого «умного ИИ» о `N+0x1C=HashMultiplier=7`, namespace SEALED, без независимых байтов (опровергнуто).


---

# ПЛАН И STOP RULES / 04-NEXT-STEP-AND-NO-REPEAT.md

# Единый план и практические критерии перехода

Цель: реальная WCOS 26100, не постзагрузочная симуляция. Разделы плана нумеруются G0–G7; это **gates**, не проценты готовности.

| Gate | Цель | Результат к 12.11.2026 | Что должно стать доказательством |
|---|---|---|---|
| G0 | Baseline / recovery | Версии и SHA основных PE зафиксированы, известен snapshot `Before-PhaseBinding-26100-9457`, но фактическое состояние всех современных VM snapshots на 12.11 не переснималось | сохранённые консистентные snapshots, журнал какой VM/boot/revision |
| G1 | Factory OS 22000 reference | Реальный FOS 22000 boot, BCD M/O/B, Object Manager chain, XRo, registry maps, копии ульев подтверждены; оставался upstream winload DEVICES/OSBOOT CFG | связать producers guards, loader block, имена backing stores в одном callgraph / runtime |
| G2 | API Set / Host source and accepted base | BaseApiSetSchema selector в winload доказан; TraceSmoke реально загружал alt PE с debugger; новый dual V6+V7 вариант успешно офлайн декодирован; Host version 26100 trusted не получен | новая schema прошла actual early parser + CI именно в нужном контексте, Host mapping доказан по итоговой скомпонованной схеме |
| G3 | Early split hives + storage | 26100 code paths обнаружены, BCD на текущей 26100 только C:, OSDataRoot fallback наблюдался; native OSBOOT/DEVICES ещё не подключены как FOS | реальные early hives, отдельные backing stores, OSDataRoot/BSP/storage identities, registry mount times |
| G4 | Kernel Phase binding / ABI / safety | Все 3 callback ABI и статусные rules исследованы; INIT retention bounded, 4/26 imports SAME limited, 22/26 INCONCLUSIVE; trusted HOST нет | точные живые IAT 3 фаз и их исполнение + failure semantics, CM scratch/locking и INIT lifetime доказаны |
| G5 | Первый boot WCOS 26100 PoC | **Не построен и не загружен**; существующая обычная 26100 с postboot D: не считается WCOS | согласованный Winload/Kernel/extension, early separate storage, reproducible Hyper-V Gen2 boot |
| G6 | Доказать настоящую State Separation | Не достигнуто | MainOS RO с проверками записи, перенос mutable реестра/файлов, сохранение после reboot, servicing/reset/recovery |
| G7 | UX / apps | Отложено | UWP/WinUI, оболочка Santorini/Composer 20279, затем Win32 sandbox/container |

## ТЕКУЩИЙ ближайший sprint (единственный вместо повторения кругов)

**Цель T1:** оценить именно новый V6+embeddedV7 APISet, а не повторить V6-only TraceSmoke, с сохранённым оригиналом и снятым WinDbg. Удалённый мощный ИИ параллельно разбирает producer guards `OSBOOT/DEVICES` — его работа НЕ блокирует ограниченный тест способа выбора/композиции схемы, но блокирует полноценную WCOS.

Перед планированием boot: (a) проверить идентичность VM snapshot и исходного PE, (b) получить/подтвердить SHA последнего dual-файла на диске гостя, (c) подтвердить, как boot policy допускает альтернативный PE: `HashMismatch` однозначен для обычной Microsoft-подписи, ранний scenario1; прошлый debugger-admission ≠ standalone acceptance, (d) обеспечить rollback offline для SYSTEM/BCD и получить критерии диагностики без WinDbg. Не менять штатную системную DLL. Если эти условия не выполнены, сначала narrow test/inspection и согласованный controlled experiment, а не бессмысленный boot.

**Важное различие результатов:**
- `offline format PASS`: парсер читает V6+V7, два Host на местах — уже **подтверждено**.
- `winload selected alternate base`: это уже подтверждалось для старого B-DualSize с **boot debugger**; для НОВОГО dual пока нет.
- `Winload accepted unsigned modified PE`: для НОВОГО нет доказательства; при старом boot debugger было warning + allowed.
- `composed active namespace has two Host`: для нового НЕ измерено.
- `Kernel Phase IAT -> new trusted extension`: НЕ измерено; compatible 26100 host отсутствует.
- `Phase0/1/2 actually works` и `WCOS core`: НЕ доказано.

## Не повторять

1. Перечитывать тот же пустой Host штатной ApiSetSchema 26100 или спрашивать установить пустой `statesepext.sys` на `C:`. Факт уже проверен многократно.
2. Делать `bcdedit /enum current` и `NtLinks` как «новые открытия»: FOS и 26100 цепочки известны, для 26100 осталась только не выполненная последняя ARC→HarddiskVolume проверка.
3. Оценивать D:/REG_LINK как проделанный Core Stage. Postboot PoC 20/20, Core 2/100, 0/7 — разные измерения.
4. Повторять сканирование 26 imports: совпадение имен НЕ полноценная совместимость; 4 SAME / 22 INCONCLUSIVE отражены в отчётах 11.11.
5. Снова предлагать просто изменить V6 Host / `Flags=SEALED` / `Value.Flags=2`: эти варианты уже исследованы и ошибка объяснена.
6. Начинать исследование Windows 10X OOBE, shell, Win32 контейнера до G6.
7. Превращать вывод «всё исследовано» в необоснованное обещание «можно прямо сейчас установить и запустить».

## Внешний ИИ: отдельная открытая задача

Был получен запрос на оригиналы `.reg` HKLM\OSBOOT/HKLM\DEVICES и Object Manager runtime log. Для него был сформирован дополнительный handoff с восстановленными по выводу пользователя ObjMgr/BCD, производными registry reports; **исходные** `WCOS-FullRegistry-22000.zip` / `26100.zip` отсутствовали в доступной на тот момент директории и не выдавались за присутствующие. Последняя точная просьба этого ИИ: локальные пути к оригинальным `.reg`; его окончательного нового анализа guard-producers пользователь пока не прислал.

Пока исследование guard-producers не закончено, не присваивать boolean `BL` / `R15B`, selector `R13D` / `R12D` произвольные названия. Есть статические адреса, не полный producer graph.


---

# ОРИГИНАЛЬНЫЕ ВЫВОДЫ ПОЛЬЗОВАТЕЛЯ / live-logs/USER-OBSERVED-WINDBG-BCD-REGISTRY-2026-11-12.md

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


---

# ИНДЕКС КОМПЛЕКТА / 06-PACKAGE-INDEX.md

# Состав пакета / первоисточники / отличия от старых

Основной способ чтения: `00-START-HERE-RU.md` -> `01-MASTER-HANDOFF-RU.md` -> `02-TECHNICAL-AGENTS-AND-REVISION-DETAILS.md` -> `live-logs/...` -> исходные отчёты `reports/` и `sources/`.

- `00-START-HERE-RU.md` — новый чат: важные правила, главный актуальный статус.
- `01-MASTER-HANDOFF-RU.md` — полная цель, environment, WCOS/KERNEL, все проверенные шаги, PDB, BCD, State Separation, современный API Set.
- `02-TECHNICAL-AGENTS-AND-REVISION-DETAILS.md` — самые содержательные результаты внешних ИИ и corrections с точными RVA, версиями, хешами и ограничениями.
- `03-TIMELINE-AND-MISSING-ARTIFACTS.md` — хронология и честный список недоступных первичных источников.
- `04-NEXT-STEP-AND-NO-REPEAT.md` — единый план G0–G7, gates, осмысленный текущий test sprint.
- `05-FIRST-MESSAGE-TO-NEW-CHAT.txt` — готовое первое сообщение, которое можно вставить в новый чат вместе с ZIP.
- `live-logs/USER-OBSERVED-WINDBG-BCD-REGISTRY-2026-11-12.md` — реальные наблюдения пользователя, отдельно от static hypotheses.
- `reports/semantic-26100/` — внешний ИИ 11.11: README, VERIFIED, DIFFERENCES, callgraph, INIT retention, 26 API CSV, dependencies, unknowns.
- `reports/api-v7/` — внешний ИИ 10.11: `V7-STRUCTURES`, `SEALED-SEMANTICS`, `TRACE-EXPLANATION`, decoder.
- `reports/api-rootcause/` — внешний ИИ 09.11: 69 file manifest-derived facts, hypotheses, origin of TraceSmoke V6/V7, old wrong claims corrected.
- `reports/preboot-signing/` — инженерный AI 08.11: winload PDB, scenario1/signing/trust, Phase0, 49/49 tests, NO-GO, MUM/CAB source requirements.
- `reports/legacy-technical/` — все доступные отдельные audits 05–08.11, registry, XRo, ports, Winload, ABI.
- `reference/` — старые project handoff и full registry summaries + исходный `FactoryOS-22000-StateSeparation-runtime-map.json` + старый аудит 08.11.
- `sources/` — архивы оригинальных PE/FOS, full 08.11 technical handoff, INIT-LIFECYCLE 11.11, StateSep Offline Audit 10.11 (санитизированная копия без двух вложенных 20279 asset ZIP с шрифтами), Signing Lab, postboot auditor.
- `old-research/` — исторический текст стороннего ИИ с ошибочными утверждениями, отмечен как ненадёжный.
- `PROJECT-STATE-2026-11-12.json` — машинно-читаемый статус и ссылки на исходники.
- `SOURCE-SHA256-MANIFEST.csv` — перечисление SHA-256 всех файлов пакета (без самого manifest и сводного generated zip).
- `ARCHIVE-CONTENTS.txt` — list с размерами для проверки, что файлы реально вложены.

ПРИВАТНОСТЬ: пакет содержит внутренние сведения о виртуальных машинах, путях, реестре и PE/отладке. Не публиковать открыто и не пересылать посторонним без необходимости. Старые пользовательские дампы могли содержать чувствительные системные данные; в этом пакете основной реестр представлен производными сводками, а не полным старым raw SYSTEM/SAM/SECURITY.

Важная **граница:** отдельный новый тестовый dual APISet 12.11 отсутствовал в mounted `/mnt/data`, поэтому не вложен «как будто оригинал». Его пользователь уже проверил в `C:\Users\fog\Downloads\wcos_new_apiset_26100`; для будущего boot trace запросить именно тот же по SHA256 объект. Нельзя использовать `apisetschema.dll` из корня пакета как замену новой dual-схеме: корневой отдельный файл в исходниках был FOS22000 и НЕ соответствует 26100.
