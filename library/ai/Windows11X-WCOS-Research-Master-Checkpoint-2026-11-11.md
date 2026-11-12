# Windows 11X / WCOS — единый технический checkpoint

**Дата:** 2026-11-11. **Состояние:** итоги работ 2026-11-05…11, включая исследования внешнего ИИ (API Set V7/SEALED, NT API, INIT, сравнительный аудит 22000↔26100) и свежий пользовательский вывод `mountvol`, `fltmc volumes`, `fltmc instances` из `FOS-SS-Test`.

**Назначение:** точная передача накопленных сведений следующему инженеру/ИИ без потери машинных адресов, условий, неудачных опытов и границ доказанного. Подробные оригинальные отчёты приложены к ZIP-пакету; этот документ — индекс и консолидированная интерпретация, а не замена дизассемблированных evidence files. Нового WinDbg-эксперимента в момент подготовки не проводилось.

## 0. Важнейший итог

1. **Доказано сохранение существенного Windows Core OS pipeline в NT 26100**: Phase0/1/2 imports/callsites, обработка статусов, некоторые State Separation registry/WNF/OSData consumers, механизм boot dependency и удержание `INIT` при классификации `0x04000000`. Сравнение произведено с настоящими `ntoskrnl.exe`/`winload.efi` Factory OS 22000.1, а не только с именами экспортов.
2. **НЕ доказана полная совместимость `statesepext.sys` 22000 с ядром 26100**: 26 импортов доступны по именам в обоих ядрах, 4 NT API сопоставлены как `SAME` лишь в оговорённом диапазоне, 22 — `INCONCLUSIVE`; нет гарантии соответствия CM/I/O/security semantics, жизненного цикла, locking, storage и политики.
3. **Главный блокер активации:** оригинальная `ApiSetSchema.dll` 26100.9278 имеет State Separation version `SEALED` **без default Host**. В FOS 22000 штатно выбран `statesepext.sys`. API Set V7-композитор не сохраняет legacy-only V6 host patch. В предоставленных данных **нет согласованного поддерживаемого 26100 State Separation Host/component set**.
4. **Новый живой факт FOS:** `fltmc instances` показывает `XRo` на `M:` (MainOS) и `B:` (BSP), altitude `261101`, а также `wcifs` на `M:`. На `O:` (OSData) instance XRo **не показан**. В более раннем испытании `FOS-SS-Test` прямая запись на M: отвергнута с ошибкой «media is write protected»; связь отказа именно с XRo не изолирована, и защита ВСЕХ записей не доказана.
5. **Новое live `fltmc volumes`:** `M:`=`\Device\HarddiskVolume5` (NTFS), `O:`=`\Device\HarddiskVolume9` (NTFS), `B:`=`\Device\HarddiskVolume3` (NTFS). Это разные логические тома, но отдельный GUID/номер не доказывает независимый физический носитель, корректность `\OSDataRoot`, Space layout или immutable MainOS.
6. **Готового настоящего WCOS 26100 boot PoC пока нет.** Успешный Windows NT 26100 boot, отдельный OSData volume, post-boot hive mount или загруженный SYS не должны называться доказанной Windows Core OS.

## 1. Цель и границы проекта

Windows 11X должна быть **реальной загружаемой на x64 PC Windows Core OS**, с согласованными системными компонентами ветки 26100 (затем обновляемой), ранним State Separation и отдельным persistent изменяемым состоянием на OSData, включая split system registry hives; физически и логически обоснованными MainOS/OSData/BSP/EFI и, где оригинальная архитектура требует, Storage Spaces; проверенным enforcement MainOS, обслуживанием, восстановлением и обновлением. Это не Windows 11 с темой, post-boot REG_LINK или переименованными томами.

Референсы: Factory OS 22000.1 — реальный PC WCOS для сравнения, Windows 10X Santorini 20279 — источник GUI/Composer/OOBE для **поздней** фазы; Windows 11 IoT Enterprise LTSC 2024 / LTSC 26100 — источник современного согласованного PC kernel, не готовая WCOS; Validation OS, Xbox GameCore, Holographic — только вспомогательные сведения, не переносимый PC-equivalent.

**Порядок:** сначала Core (boot-binding, ранняя registry/storage layout, state persistence, MainOS enforcement, servicing), затем UWP/WinUI/Santorini/Win32 isolation. Не копировать ядро 26100 поверх Factory OS 22000 и не трактовать любой успешный boot как WCOS.

## 2. Типы доказательств

- **RUNTIME-OBSERVED:** пользовательский WinDbg/PowerShell вывод в конкретной VM/загрузке; не склеивать разные загрузки и виртуальные машины.
- **STATICALLY VERIFIED:** содержимое предоставленных PE/SYS, SHA-256, таблицы импортов, инструкций, PDB anchor/callgraph, декодирование schema. Это доказывает путь в коде при его предпосылках, **не его исполнение**.
- **STATIC RECONSTRUCTION:** конкретная модель работы composer/resolver при известных входных байтах, подтверждённая согласованной арифметикой; не равна снимку всего промежуточного runtime buffer.
- **UNKNOWN:** неподтверждённая совместимость или состояние. Отсутствие доказательства не считать `INCOMPATIBLE`.

## 3. Проверенные исходные бинарники для последнего сравнения

| Файл | Размер | SHA-256 |
|---|---:|---|
| FOS22000 `ntoskrnl.exe` 10.0.22000.1 | 11 743 560 | `a9b5c0a47f1c4606a94adb3674017cbd35448c3792c4d26f51847a3ffa8c7b7c` |
| FOS22000 `winload.efi` 10.0.22000.1 | 2 307 288 | `9fb057cb65462624f35b41fe686f71fc03f11e3770fdb81e04900640030ad41d` |
| FOS22000 `statesepext.sys` 10.0.22000.1 | 61 752 | `167fda36a71c2aad4c146bc66ae4c83e9d525d008d6abfd8eda0e056c9a96658` |
| FOS22000 `ApiSetSchema.dll` | 143 672 | `49696b4557023ea455aa7731a18467b623292a7c2ec837d8437a9c2dcd9cd498` |
| NT26100 `ntoskrnl.exe` 10.0.26100.9457 | 13 104 632 | `d90c69cfa925d42037ef1cdba3ff5b3f13cc14e16060938073b97e396fdde2f2` |
| NT26100 `winload.efi` 10.0.26100.9444 | 3 358 408 | `1c1a978448b8e0fc2284c13d6e55fde8bf9827dca432cdc8a9be66c2e729adda` |
| NT26100 `ApiSetSchema.dll` 10.0.26100.9278 | 194 016 | `391a2818fdef14f4a9b6e6ce2a7e7f01d04f943de9ef543ec683927cfe480889` |

Два файла FOS были прочитаны по SSH из `FOS-SS-Test`: `C:\Windows\System32\ntoskrnl.exe` и `M:\Windows\System32\Boot\winload.efi`; пользователь получил версии 22000.1 и приведённые хеши; затем байты сравнивались с приложенным `FOS.rar`. Одна и та же версия + hashes **не устанавливают** полную provenance/identity factory image и подписей/пакетной цепочки с разных томов (R01).

Сравнительный ИИ проверил SHA256 семи входов, 92 адресованных машинных инструкций и шесть Phase callsites. PDB: для исследованных объектов совпали RSDS GUID и DBI Age; **Info Age отличается**; несколько export/public aliases не совпадают, поэтому PDB использован как разметка вместе с реальными инструкциями, а не как абсолютное доказательство всех private типов. Все RVA ниже **относятся к конкретной названной сборке**: нельзя переносить адреса старой 26100.1742 или .1591 на 26100.9444/9457.

## 4. Что было известно до новых отчётов (2026-11-05…08)

### 4.1. FOS reference — динамические испытания, не новый runtime trace

- На `FOS-KD-Test` через WinDbg был показан **загруженный** `statesepext.sys`, три действительных `_imp_ExpInitializeStateSeparationPhase*` указывали на его экспорты.
- При `CmInitSystem0` аппаратная точка записи фиксировала `CmStateSeparationEnabled=1` из `StateSeparation\Policy\Enabled=1`.
- На исследовательском клоне `FOS-SS-Test` сообщалось: `XRo` с `Start=0` и `RegistryFilterEnabled=1`, на M:/B:, запись на M: отвергнута с `The media is write protected`. Это результат конкретной попытки, не универсальная аттестация всех путей write interception.
- Ранее после reboot **файл на Data C:** и **прямая запись** в `HKLM\OSDATA\SOFTWARE` сохранились. Тестовое значение, записанное в обычный `HKLM\SOFTWARE`, после reboot исчезло. Отдельное выбранное правило RedirectionMap не произвело доказанного автоматического перенаправления; не трактовать успешную persistence прямой записи как успех всех правил.
- Не смешивать `FOS-KD-Test`, `FOS-SS-Test`, исходную `FOS`, `Activation01` и `Windows11X-26100-PoC01`; даже одинаковая сборка не означает идентичную VM/session state.

### 4.2. Состояние штатной исследованной NT26100

- На ранней 26100 `CmInitSystem0/CmpGetSystemControlValues` прочитали descriptor `Enabled`, `DevelopmentMode`, `AllHivesVolatile`; `StateSeparation\Policy` существовал, но `Enabled` отсутствовал → `CmStateSeparationEnabled=0`.
- Через WinDbg Phase0, Phase1, Phase2 IAT указывали на `nt!DefaultAddProfileSource`, все возвращали `STATUS_NOT_SUPPORTED = 0xC00000BB`; ядро имело обработку этого статуса. На новой 26100.9457 это повторно подтверждено для трёх IAT, а `statesepext.sys` отсутствовал в loaded module list. Текущая stock 26100 **не исполняла настоящий statesepext**.
- Лабораторный post-boot `OSData`-mount/`HKLM\WCOS_OSDATA` в `Windows11X-26100-PoC01` был показан отдельно; он не свидетельствует об early WCOS binding. Старые аудиторские оценки `16/20` post-boot и `2/100` Core (0/7 критических gates) — исторические неформальные метрики и не заменяют проверку текущей машины.
- Прежние отказы boot, включая `SECURITY_INITIALIZATION_FAILED 0x5F`, и отдельная first-chance AV `0xC0000005` в `winload!OslGetRootCell` при index `0xFFFFFFFF`, **не доказаны** результатом отсутствия StateSep/OSBOOT/DEVICES; нельзя считать их причинность установленной.

## 5. API Set: 22000 vs 26100, и почему V6 patch не работает

### 5.1. Готовое binding отличается

- FOS22000 original: `ext-ms-win-ntos-stateseparation-l1-1-0` → **`statesepext.sys`** (legacy V6 entry Flags=0); user-mode `ext-ms-win-core-stateseparationext-l1-1-0` → `onecorestateseparationext.dll`.
- NT26100 original: оба State Separation contracts без Host, а NT State contract `SEALED` (per-version bit0); `ext-ms-win-ntos-kcminitcfg-l1-1-0` имеет `cmimcext.sys` и этот другой kernel extension штатно работает. Наличие других работающих extensions не доказывает готовую StateSep.
- Исходная modern schema 26100.9278 имеет внешний V6 namespace 984 entries с компактной **embedded V7**; legacy header `Flags=0`, `Version=6`, `Count=984`, `HashFactor=31`, `Size=0x29C60`, `EntryOffset=0x98`; marker V7 at N+`0x1C`. Namespace flags и per-contract SEALED — разные поля.
- До обновления старые modern schemas имели 974 entries; недавнее сравнение с 26100.9278 описывает 984, не переносить на текущую DLL старые count/off/RVA.

### 5.2. Точный compact V7 для двух контрактов (original 26100.9278)

`N` — начало `.apiset` (`file offset 0x2000`, DLL RVA `0x3000`); `E=N+0x1C`, bias из `E+0x12`=`0x1C`, поэтому encoded offsets равны N-relative. Pool: `N+0xC8F0..0x1F304`, length `0x12A14`. Family records 889, aliases 4, version records 984, host records 286, importer nodes 6. У обеих целевых family ни default Host, ни importer-specific mapping **не существует** (это не host с пустой строкой).

| Поле | State Separation | Trace |
|---|---|---|
| Имя | `ext-ms-win-ntos-stateseparation-l1-1-0` | `ext-ms-win-ntos-trace-l1-1-0` |
| Legacy entry / Value | `N+0x4328` / `N+0x94C8` | `N+0x4358` / `N+0x94F0` |
| Compact family index / record | 647 / `N+0x21158` | 649 / `N+0x21170` |
| Compact version index / record | 710 / `N+0x25A10` | 712 / `N+0x25A28` |
| Version flags at `record+0xA` | `0x01` = SEALED | `0x00` |
| Family flags at `record+0xA` | `0x10` (Host-present bit `0x02` отсутствует) | `0x10` |
| WORD family+4 default host index | `0xFFFF` | `0xFFFF` |
| Importer head/count | `0xFFFF`/0 | `0xFFFF`/0 |
| Resolver result для оригинального компактного mapping | `STATUS_SUCCESS`, marker 1, zero-length Host | то же |

`ApiSetResolveToHost` W26100 RVA `0x279130` переводит этот wrapper в компактный V7 resolver RVA `0x27A60C` (поскольку `N+0x1E & 4 == 0`), а не читает legacy ValueEntry как источник текущего Host. Version SEALED проверяется composer при overlay; обычный type3 resolver не отвергает Host только из-за bit0.

**Положительный контроль SEALED:** оригинальный `ext-ms-win-ntos-tm-l1-1-0` → `tm.sys`, version711 SEALED flags1, family648 flags `0x12`, host index183. Также в схеме есть SEALED+nonempty examples `clipsp.sys`, `globmerger.sys`, `cmimcext.sys`, `ntosext.sys`, `werkernel.sys`. Значит, SEALED **не запрещает** Host, уже входящий в базовую схему, но **ограничивает overlay найденного sealed contract**. У State Separation base mapping пустой. Настоящая принятая 26100 base-schema с непустым именно StateSep Host не найдена/не доказана.

### 5.3. Проверки SEALED на машинных адресах W26100.9444

- Legacy namespace `Flags` at N+8 =0, embedded namespace `E+2`=8, compact **version** `+0xA`=1 у State; compact **family** `+0xA`=0x10 — **не SEALED**.
- V6 host-extension → V7 compact composer: sizing version gate `0x27C5FC..0x27C627`, конкретный `test bit0` around `0x27C622`, при sealed пропуск importer sizing; emission `0x27C9CA` проверяет version bit0, при sealed пропускает обновление family mapping; часть ранее созданных host/pool records при этом может остаться. Это не обязательная побайтная неизменность всего namespace.
- V7 extension branch: sizing gate `0x27AD8A..0x27AD8F`, emission gate `0x27B274..0x27B276`; обработка alias — другая ветвь (`alias+6`), не смешивать с version record.
- Для nonsealed resolver проверка version bit2 находится at `0x27A709`; family.flags&2 определяет Host-present. У State version flags1 не закрывает чтение **существующего** base Host.
- Namespace-level gating (например `OslLoadApiSetSchema` `0x23245`) — отдельный механизм от per-version SEALED. Нельзя стирать SEALED ради обхода и объявлять это доказанной совместимостью.

### 5.4. TraceSmoke — завершённый контролируемый опыт

B-DualSize `ApiSetSchema.dll` SHA256 `ba382268c00918b4a99b2efe3577782673aa6be58e7b567a776e43fca21583f9`. В B legacy Trace ValueEntry HostOffset=`0x29C60`, HostLength=`0x24` (UTF-16 `WcosTraceSmoke.sys`), outer и embedded Size=`0x29C84`; **compact V7 blocks по сравнению с оригиналом неизменны**. До dynamic composer присутствие строки — это **наличие имени**, не загрузка SYS, не kernel binding.

**Загрузка 1 — один непрерывный A/B/C log:**

| Точка W26100 RVA | Active schema pointer | Size | Legacy Trace HostOffset / Length |
|---|---|---|---|
| A `0x23222` | `fffff80077595f20` | `0x29C84` | `0x29C60` / `0x24`; строка видна |
| B `0x2335F` (перед dynamic) | тот же | `0x29C84` | `0x29C60` / `0x24` |
| C `0x23375` (после dynamic) | `fffff80078724d20` | `0x29C74` | `0x1F304` / `0`; строка не найдена |

**Загрузка 2 — отдельный лог:** на `0x23023` `AL=0`, выполнена ветка `je 0x2302D`, выбрана пустая UTF-16 fallback строка (`du @rax → ""`). Не совмещать `AL=0` из загрузки 2 с A/B/C загрузки 1 как будто это один эксперимент. Причина конкретного AL=0 в историческом запуске (BCD option, active schema, host-check и другие условия) не сохранена и **UNKNOWN**.

`OslpApplyDynamicSchemaExtensions` W26100 begins at `0x22FD4`; dynamic Trace table near `0x2B2DB0`; predicate `OslpDtraceExtensionEnabled` `0x22F48` читает BCD boolean `0x26000145`, затем при его включении `OslpIsApiSetHosted` `0x22E8C`. Последний требует resolver success, marker, Host.Length>0 и доступный Host image path. `AL=0` может возникать на разных ветвях. False выбирает пустой extension Host, **но не пропускает** создание extension: затем идут `ApiSetCreateSchema` `0x23056` и `ApiSetComposeSchema` `0x23076`.

**Уточнённое объяснение `+0x14`**: Trace version уже существует, новый пустой Host record (12-byte record + 8-byte index) учитывается sizing at `0x27C531..0x27C54E`, не создаётся новая Trace version; исходный размер `0x29C60` + `0x14` = `0x29C74`. Composer перестраивает V7 layout по counts и pool, не копирует append B как готовый compact Host. Он перезаписывает legacy companion HostOffset `0x1F304`, HostLength0. Ранний отчёт, относивший `+0x14` к созданию version, **исправлен**. Полный выходной compact buffer из первой загрузки не сохранён; соответствие размеров и off — сильная реконструкция, но не дамп всех intermediate байтов.

**Вывод TraceSmoke:** доказана непригодность legacy-only patch к исходной embedded V7 как решения Host binding. Trace unsealed; его эксперимент **не доказывает** возможность overlay для sealed State. Не повторять бесконечно этот опыт.

## 6. Сравнение Phase ABI: FOS 22000.1 ↔ NT 26100.9457

**Существенный результат исследования ИИ от 2026-11-11:** сходство доказано **на реальных callsites и условиях**, а не только на импортах. При этом полный перенос модуля не подтверждён.

| Callback | FOS K22000: call RVA / IAT RVA | NT26100 K: call RVA / IAT RVA | Аргументы |
|---|---|---|---|
| `ExpInitializeStateSeparationPhase0` | `0xB0B60B / 0x13D2D0` | `0xC294EE / 0x150378` | `CL=RtlIsStateSeparationEnabled()`, `DL=CmIsStateSeparationDevModeEnabled()` |
| `ExpInitializeStateSeparationPhase1` | `0xB10280 / 0x13D2C8` | `0xC6DC8E / 0x150368` | `CL=(CmStateSeparationEnabled != 0)` |
| `ExpInitializeStateSeparationPhase2` | `0x7797B5 / 0x13D2D8` | `0xB0F996 / 0x150370` | `CL=(CmStateSeparationEnabled != 0)`, `RDX=source UNICODE_STRING*`, `R8=target UNICODE_STRING*` |

Все три возвращают `NTSTATUS` в `EAX`. Достоверно исследованное статусное решение в **обоих** kernels:

```text
if NT_SUCCESS(status) or status == STATUS_NOT_SUPPORTED (0xC00000BB): continue
else:
    Phase0 -> failure path I/O initialization
    Phase1 -> fatal initialization path (KeBugCheckEx, 0x32)
    Phase2 -> close newly opened key handle and propagate error
```

Проверки для 22000 расположены в main/cold ranges: Phase0 `0xB3CCDE`, Phase1 `0xB3F858`, Phase2 `0x7797C3`; 26100 соответственно `0xC29506`, `0xC6DCA6`, `0xB0F9A4`. **Предыдущая гипотеза** о различии обработки `STATUS_NOT_SUPPORTED` в 22000/26100 опровергнута: ранний анализ обрезал cold paths.

**Порядок:** Phase0 в `IoInitSystemPreDrivers`, затем Phase1 в `Phase1InitializationIoReady` перед `CmInitSystem2`; Phase2 — отдельный **demand-dependent WNF callback**, потенциально гораздо позднее, а не обязательный третий шаг сразу после Phase1. Не путать «Phase0 callback» с MM/kernel initialization phase0 по названию. Обе kernels сохраняют логическую последовательность; отдельный момент первого Phase2 не установлен.

### 6.1. Реальные exports оригинального `statesepext.sys` F22000

- **Phase0 `0x8090`:** если `CL=0`, успех/no-op. При development mode — best-effort запись `DevModeEverEnabled`; далее `InitializeStateSeparationRedirectionLinks` `0x8644` (редиректы, registry copy/rename/link) и при успехе `ReflectComputerName` `0x9344`. Считываются `KeyRedirectionLinks`, `KernelLink`, `AlwaysEnabled`, source/target пути, нужны ранние SYSTEM rules/parents/backing. `RecurseCreateKeyTreeForPath` `0x91A4` создаёт registry keys, **не монтирует отдельный hive**.
- **Phase1 `0x8190`:** `CL=0` → success. Проверка `\??\O:\Windows\FirstBoot.Complete`; при успехе — early return без сверки всех целевых файлов. Иначе allocation paged scratch `0x1000`, создание `\OSDataRoot\Windows\System32\Config\`, чтение `HiveRedirectionList`, копирование SAM/SECURITY/DEFAULT через `NativeCopyFile` `0x8B84`, release собственного буфера. Sentinel либо missing source может позволить success без доказательства всех data relocation.
- **Phase2 `0x8360`:** `CL=0` или `RDX/R8=NULL` → success. Иначе `CopyKeyTreeByName` `0x1050` со source/target descriptors, затем освобождение глобального `StashNameBuffer` F+`0x3068` и сброс F+`0x3060` (`StashNameLength`). Собственный охватывающий lock в исследованном F не доказан; важно проверить параллелизм.
- **Entry point** F+`0x8078`: `mov eax,0xC0000001; ret` (`STATUS_UNSUCCESSFUL`). Это не обычный SCM driver старт; F используется как early import dependency с Phase exports. У F нет `DllInitialize`, хотя `DllUnload` имеется. Не интерпретировать DriverEntry failure как отказ Phase ABI.

### 6.2. WNF Phase2 — конкретное совпадение поведения

В обоих ядрах WNF root helper выбирает cached handle; при cache miss берёт descriptor (stride `0x30`), в зависимости от State Separation enabled выбирает path, делает `ZwCreateKey`, и **только при новом ключе `CreateDisposition==1`** вызывает Phase2. Если уже есть ключ, Phase2 пропускается. После допустимого статуса handle публикуется атомарным `lock cmpxchg`; при ошибке callback handle закрывается; лишний handle тоже закрывается при конкурирующей публикации.

- K22000 WNF helper `0x779668`, `ZwCreateKey` `0x77976E`, disposition `0x77977B`, Phase2 call `0x7797B5`, publication `0x779787`.
- K26100 helper `0x8816A4` + cold `0xB0F924`, create `0xB0F96B`, disposition `0xB0F978`, Phase2 `0xB0F996`, publication `0xB0F9C5`.
- В обоих есть поздняя цепочка `NtCreateWnfStateName → ExpWnfRegisterPermanentName → ExpWnfGetNameStoreRegistryRoot`; не установлен обязательный момент выполнения: cache/disposition/consumer решают, будет ли callback.
- **Три пары WNF descriptors в обеих versions** (index0/1/2):
  - `0`: `\Registry\Machine\System\CurrentControlSet\Control\Notifications` → тот же путь.
  - `1`: `\Registry\Machine\Software\Microsoft\Windows NT\CurrentVersion\Notifications` → `\REGISTRY\MACHINE\OSDATA\Notifications`.
  - `2`: `\Registry\Machine\Software\Microsoft\Windows NT\CurrentVersion\VolatileNotifications` → тот же путь.
  - Tail DWORD pairs index0=`1,0`, index1=`0,0`, index2=`0,1` совпали.
- `lock cmpxchg` вокруг **публикации cache handle после callback**, **не** доказывает сериализацию тела Phase2 и общего F scratch. APC disable внутри syscall не равен lock и не определяет IRQL.

## 7. Loader dependency `0x04000000` и `INIT` — доказательство для ОБОИХ ядер

F Phase0/1/2 расположены в секции `INIT`, помеченной `IMAGE_SCN_MEM_DISCARDABLE=0x02000000`. Раньше ошибочно делался возможный вывод «поздний Phase2 обязательно вызовет освобождённый INIT». Новое сравнение выявило **штатную специальную ветвь удержания INIT у import dependency**.

| Участок | FOS22000 | NT26100 |
|---|---|---|
| W: новый LdrpLoadImports dependency flags=`0x04000000` | `winload+0xEF82C` | `winload+0x19D5FB` |
| W: запись flags в loader entry `+0x68` | `winload+0xEFEE5` | `winload+0x19DCDA` |
| W: полный boot entry allocation | `0xE8` bytes | `0x120` bytes |
| K: copy common prefix с flags | `nt+0x771A1F..0x771A75` | `nt+0xAAD995..0xAAD9F3` |
| K: зависимая DLL classification | `nt+0xB02F88` | `nt+0xC71BF3` |
| K: normal INIT selector test bit26 | `nt+0x77C355` (`bt eax,0x1A`) | `nt+0xA536FA` (`test ...,0x04000000`) |
| K: проверка первых 4 bytes секции `INIT` | `nt+0x77C467` | `nt+0xA53999` |

При конечном `entry.Flags & 0x04000000 != 0` обычный selector mode2 не выбирает секцию с первыми bytes имени `INIT` для discard, даже если section DISCARDABLE. Так что **для нового правильно принятого dependency image** поздний Phase2 **не является доказанным UAF по одной причине `INIT`**.

**Очень важные пределы:**

- В обоих winload есть merge при повторно используемом existing loader entry, mask `0xFBFFFFFF` может **снять** bit26 в некоторых условиях. Новая загрузка и reuse — разные случаи. Не обещать вечное удержание произвольного Host.
- Полный private boot entry изменён (`0xE8` vs `0x120`), но common `_KLDR_DATA_TABLE_ENTRY` prefix size `0xA0`, `Flags+0x68`, `DllBase+0x30`, `LoadCount+0x6C`, `LoadedImports+0x88` согласован. `PipInitializeDriverDependentDLLs` берёт другие private поля `+0xE4` vs `+0x11C`.
- Конструктор kernel loader entry использует различные private modes (22000 `1`, 26100 `0x10000000`), а boot cleanup имеет разные внутренние формы. Эти различия **не** являются доказанным разрывом внешнего Phase ABI.
- Находка относится к **обычной boot cleanup**; explicit unload/discard, reference counts, pressure, все policy/image-classification paths и реальный 26100 statesepext loader entry ещё не закрыты.

## 8. Сравнение всех 26 NT imports F22000

Аудит external-ИИ 2026-11-11 проверил 26/26 export names обоих kernels, 18 named Zw imports (проанализированы реальные `Nt*` targets через on-disk `KiServiceTable` 22000 RVA `0xCA690`, 26100 RVA `0xDADC0`) и 8 Rtl/Ex/CRT. Это не сравнение одних Zw stubs. Для всех 26 реконструированы implementation RVA, call sites и границы применимости; полный список — **`NT-API-COMPATIBILITY.csv`** в приложенном пакете.

**Классификация:** 4 `SAME` (только перечисленный протокол с валидными buffers), **22 `INCONCLUSIVE`**; доказанного нарушения требуемого внешнего API contract не обнаружено, но 22 пути не объявлены эквивалентными.

Четыре условно SAME:

| API | K22000 implementation RVA | K26100 implementation RVA | Проверено |
|---|---|---|---|
| `RtlInitUnicodeString` | `0x2DEF50` + cold | `0x431490` | NULL / length / clamp / descriptor |
| `RtlAppendUnicodeToString` | `0x2B1D30` | `0x415E40` | bounded append / terminate / failures |
| `RtlGetControlSecurityDescriptor` | `0x7416E0` | `0xA699B0` | short body, revision / control |
| `RtlSetControlSecurityDescriptor` | `0x7E4310` | `0x78D440` | mask / allowed bits / result |

Остальные 22, краткие ограничения:

- `ExAllocatePool2` и `ExFreePoolWithTag`: F использует paged flags `0x100`, tag `0x70655353`, оба frontends принимают исследованный input; но реализации allocation/free, quota/Verifier/low-memory и контекст не эквивалентированы. Scratch Phase1=0x1000, file-copy buffer=0x10000; global registry scratch отдельно.
- `ExUuidCreate` и `swprintf_s`: сходный вызываемый протокол UUID/format; cache/seed/locking и полный CRT error graph не доказаны.
- Registry `ZwOpenKey`, `ZwCreateKey`, `ZwSetValueKey`, `ZwQueryValueKey`, `ZwDeleteKey`, `ZwEnumerateKey`, `ZwEnumerateValueKey`, `ZwRenameKey`: во всех доступны реальные backend paths, но 26100 Nt frontends частично перестроены (`NtOpenKey → NtOpenKeyEx`, create key через stack expansion). Полный create-link/rename/copy, callbacks, security, hive and virtualization semantics неизвестен.
- File/security `ZwCreateFile`, `ZwReadFile`, `ZwWriteFile`, `ZwQueryInformationFile`, `ZwSetInformationFile`, `ZwFlushBuffersFile`, `ZwDeleteFile`, `ZwQuerySecurityObject`, `ZwSetSecurityObject`, `ZwClose`: реальные Nt bodies изучены, но FS/IRP/security/handles/full errors могут зависеть от среды. F `NativeCopyFile` использует `IO_STATUS_BLOCK.Information`, 0x10000 buffer, sync I/O, overwrite; `ZwFlushBuffersFile` return не обязательно становится итоговым статусом F.
- На уровне проверенных PDB types оба kernels согласовали `_UNICODE_STRING` (0x10), `_OBJECT_ATTRIBUTES` (0x30), `_IO_STATUS_BLOCK` (0x10), `_FILE_BASIC_INFORMATION` (0x28) и нужные offsets. Это не универсальное доказательство всех private structures.

**Важное несовпадение внутри ядра, не экспорт Phase0:** `IoInitSystemPreDrivers` parent K22000 проверяет `AL` (boolean), K26100 проверяет sign `EAX` (NTSTATUS). Это предупреждение **не переносить private internal calling convention**, хотя три **внешние Phase exports** сохранили NTSTATUS contract.

Наличие 26/26 имён, аналогично прежним XRo 62/62 NT и 21/21 FLTMGR imports (более старый аудит), **не доказывает** переносимость старых SYS в 26100. Подписи, ABI, real paths и подготовка среды — отдельные проверки.

## 9. Подготовка OSData, OSBOOT, DEVICES и RedirectionMap

### 9.1. Ранний код действительно присутствует в обеих версиях

- `winload!OslPrepareTarget` имеет **условные**, а не безусловные ветви `OslAttachHiveToLoaderBlock` для `DEVICES` и `OSBOOT`: W22000 DEVICES `0x9E9A → 0x32D0`, OSBOOT `0x9F3E → 0x32D0`; W26100 DEVICES `0x9846 → 0x3DA4`, OSBOOT `0x98E0 → 0x3DA4`. Условие зависит от входных booleans/hive readiness/HHR, не из одного имени файла.
- `_LOADER_PARAMETER_BLOCK` в обеих kernels size `0x170`; `ArcBootDeviceName+0xB8`, `ArcOSDataDeviceName+0x150`. `CreateSystemRootLink`: K22000 `0xB263D0`, K26100 `0xC18A18`. Есть ссылки `\Device\OSDataDevice` и `\OSDataRoot` и **fallback** OSDataDeviceName=NULL → ArcBootDeviceName. Поэтому даже `\OSDataRoot` в object manager без разрешения backing device может указывать на boot volume. Отдельность не выводится из label.
- Kernel `CmGetSystemDriverList`, `CmpInitializeSystemHivesLoad`, Registry/CM StateSep consumers имеются в обоих; в современном коде есть `CmpFindStateSepKeysRedirectionMapNode` с иной внутренней реализацией. `RtlGetPersistedStateLocation`: K22000 `0x691210`, K26100 `0x94DD10`; потребляет registry/file redirection map, но negative caching/reset/malformed behavior остаётся неизвестным.

### 9.2. Конфигурация оригинальной Factory OS 22000

Числа из полного исторического экспорта, не путать прямые правила с общим рекурсивным количеством ключей:

| Класс | Прямые правила / распределение | Служебный подсчёт включая контейнеры |
|---|---|---|
| `RedirectionMap\Keys` | **459**: 400 OSDATA, 40 OSBOOT, 19 DEVICES | 466 keys включая root / 465 descendants |
| `RedirectionMap\Files` | **62**: 37 OSDATA, 25 alias `M:\Data` | 63 включая root |
| `KeyRedirectionLinks` | **81**: 78 OSDATA, 3 OSBOOT | 82 включая root |
| `Control\StateSeparation` | 672 keys / 1415 values | в экспортированной ветке |
| `Services\XRo` | 10 keys / 118 values | в экспортированной ветке |

Сопоставленная стоковая NT26100 SYSTEM export: `StateSeparation` 2 keys / 0 values, нет исходных полноразмерных rule maps и `Services\XRo`; это **разница исходной конфигурации**, не доказательство отсутствия modern code. В FOS `OSBOOT` исторически 132 keys, `DEVICES` 2407 exported entries, а у 26100 часть состояния находится в обычном SYSTEM. Полного экспорта всех registry hives обеих ОС эти сравнения не содержали.

Для Phase1 требуются корректные `HiveRedirectionList`, соответствующие цели для `\REGISTRY\MACHINE\SAM`, `\REGISTRY\MACHINE\SECURITY`, `\REGISTRY\USER\.DEFAULT`, реальные исходные файлы и durable OSDATA backing. Отдельный registry **key** OSDATA или OSBOOT без реально подключённого hive не эквивалентен этому. WNF `\REGISTRY\MACHINE\OSDATA\Notifications` также требует смонтированного writable hive.

### 9.3. MainOS XRo и стек обслуживания — отдельно от Phase callbacks

Внешний FOS `statesepext.sys` не реализует весь filesystem enforcement; 26 его NT imports не содержат `FltRegisterFilter`. XRo, ФС/фильтры, `SSBoot`, `SpaceUtil`, servicing maps/MUM, `onecorestateseparationext.dll`, registry redirection, update/recovery — **другие согласованные части архитектуры**. В текущих семи primary PE полного набора нет; переносимость компонентов XRo/FLTMGR и современных пакетов не доказана.

Historical FOS runtime map сообщает MainOS, BSP, OSData, Data, SERVICING_FILES, PreInstalled, XRo M:/B:, `RegistryFilterEnabled`, но **не** физическую Storage Spaces topology, не full enforcement и не полную persistence. Исторические данные не приравнивать к сегодняшнему единому boot trace.

## 10. СВЕЖИЕ runtime evidence из `FOS-SS-Test` (переданы пользователем 2026-11-11)

### 10.1. Поиск оригинальных FOS PE по SSH

`C:\Windows\System32\ntoskrnl.exe` был найден на C:, `M:\Windows\System32\Boot\winload.efi` — на M:. Также существуют копии `winload.efi` в Vail BaseLayer и WinSxS; **для актуального FOS сравнения использован конкретный M:\Windows\System32\Boot\winload.efi** с версией `10.0.22000.1`, размером `2307288`, SHA `9fb057...d41d`. `Get-Volume` недоступен в минимальном PowerShell FOS — это не неисправность Storage Spaces и не повод менять режимы загрузки. Использован штатный `mountvol` и `fltmc`.

### 10.2. `mountvol`: идентичность GUID томов

| DOS path | Volume GUID (буквально из вывода) | Статус |
|---|---|---|
| `B:\` | `dff26bef-f2aa-413f-8e45-260df4a4a141` | mounted |
| `M:\` | `a76b8ce2-0187-4c13-8fca-8651c9b0620a` | mounted |
| `S:\` | `6a013b35-c42d-4d87-ae6a-57b47b70acad` | mounted |
| `P:\` | `c8adae64-47f4-4cfa-b67c-1e743ad5dfc9` | mounted |
| `O:\` | `b0479ff4-43bb-4710-90cd-20653989dd7d` | mounted |
| `C:\` | `fe296136-48c2-4b94-a83a-f4345a1eb2f3` | mounted |
| no mount point | `db9fb733-0f92-41e5-ab06-ff1e7ae9946a` | unmounted by letter |
| no mount point | `8183040a-8b44-4592-92f7-c6d9ee0560f7` | unmounted by letter |
| no mount point | `7aeb5015-47d7-484a-891e-0ed8d85b43fc` | unmounted by letter |
| no mount point | `aa16c266-5f58-4cf8-829f-c323cbe22ae6` | unmounted by letter |
| no mount point | `12aef83a-6cf2-4ea1-932f-b3a586a65308` | unmounted by letter |
| no mount point | `61266b3b-4e02-426d-8f13-d9bb82984e2f` | unmounted by letter |

**Всего в `mountvol`: 12 GUID, 6 с буквами и 6 без точек монтирования.** GUID является идентификатором логического volume, не отдельного физического накопителя и не доказательством Storage Spaces. Наименования S:, P: и C: без дальнейшего сравнения с device layout не присваивать произвольным ролям.

### 10.3. `fltmc volumes`: DOS → реальные NT device names

| DOS path | NT device | Filesystem |
|---|---|---|
| `M:` MainOS | `\Device\HarddiskVolume5` | NTFS |
| `O:` OSData | `\Device\HarddiskVolume9` | NTFS |
| `B:` BSP | `\Device\HarddiskVolume3` | NTFS |
| `C:` (Data в историческом тесте) | `\Device\HarddiskVolume10` | NTFS |
| `P:` | `\Device\HarddiskVolume8` | NTFS |
| `S:` | `\Device\HarddiskVolume7` | NTFS |
| no DOS path | `\Device\HarddiskVolume11` | NTFS |
| no DOS path | `\Device\HarddiskVolume4` | NTFS |
| no DOS path | `\Device\HarddiskVolume6` | NTFS |
| no DOS path | `\Device\HarddiskVolume2` | FAT |
| no DOS path | `\Device\VhdHardDisk{61266b3b-4e02-426d-8f13-d9bb82984e2f}` | NTFS |
| non-volume | `\Device\Mup` | Remote |
| non-volume | `\Device\NamedPipe`, `\Device\Mailslot` | NamedPipe / Mailslot |

**Что действительно добавлено:** разные M:/O: имеют собственные HarddiskVolume5/9 и Volume GUID; есть FAT (кандидат на EFI только гипотеза) и VhdHardDisk с совпадающим GUID из unmapped mountvol. **Не доказано:** сопоставление каждого невидимого GUID с конкретным `HarddiskVolume#`, физический layout, наличие Storage Spaces, реальный target Object Manager link `\OSDataRoot`, отдельность хранилищ на аппаратном уровне.

### 10.4. `fltmc instances`: новый главный результат

Прямые экземпляры фильтров на названных томах:

| Filter | Tom / object | Altitude | Instance / remarks |
|---|---|---:|---|
| **XRo** | **M: (MainOS)** | **261101** | `XRo`, Frame0, SprtFtrs=`0000000c` |
| **XRo** | **B: (BSP)** | **261101** | `XRo`, Frame0, SprtFtrs=`0000000c` |
| **wcifs** | **M: (MainOS)** | **189899** | `wcifs Outer Instance`, Frame0, SprtFtrs=`0000000f` |
| `bindflt` | `C:` | `409800` | `bindflt Instance`, Frame0 |
| `npsvctrig` | `\Device\NamedPipe` | `46000` | `npsvctrig`, Frame0 |
| `FileInfo` | `C:`, `B:`, `M:`, `S:`, `P:`, `O:`, `\Device\Mup`, неназванные тома | `40500` | `FileInfo`, Frame0 |
| `FsDepends` | `C:`, `B:`, `M:`, `S:`, `P:`, `O:`, `\Device\Mup`, неназванные тома | `407000` | `FsDepends`, Frame0 |
| `Wof` | `C:`, `B:`, `M:`, `S:`, `P:`, `O:`, неназванные тома | `40700` | `Wof Instance`, Frame0 |

Последняя строка вывода была просто `wcifs` без Volume Name — **не считать её отдельной привязкой к неизвестному тому без дополнительных данных**. XRo **не показан** на O:, C:, P:, S: или неназванных томах в этом snapshot. Это **наблюдение существующих instances**, не правило, что XRo «никогда» туда не цепляется. Значение VlStatus в выводе пусто; не домысливать значение битов `SprtFtrs` и статус enforcement.

**Интерпретация в сочетании с прежними данными:** FOS имеет выделенные M:/O: и подключённый XRo именно к M:/B:, что согласуется с моделью защиты immutable MainOS/BSP при оставленном mutable OSData. Исторический отказ прямой записи на M: усиливает свидетельства защиты в FOS. Но без контролируемой причинной проверки нельзя утверждать, что отказ вызван только XRo, что XRo перехватывает все виды операций, что конкретная политика корректна или что долговременное разделение всех mutable записей действует.

### 10.5. Сохранённая формулировка пользовательского `fltmc instances` (без reinterpretation)

```text
XRo                   B:   261101   XRo   Frame 0   SprtFtrs 0000000c
XRo                   M:   261101   XRo   Frame 0   SprtFtrs 0000000c
wcifs                 M:   189899   wcifs Outer Instance   Frame 0   SprtFtrs 0000000f
bindflt               C:   409800   bindflt Instance   Frame 0   SprtFtrs 0000000f
npsvctrig             \Device\NamedPipe   46000   npsvctrig   Frame 0   SprtFtrs 00000008
```

Полный остальной список приведён в таблице 10.4; исходные текстовые dumps — `runtime/FOS-SS-Test-mountvol-volumes-instances-2026-11-11.txt` в пакете.

## 11. Важнейшие исправления ошибок и ложных выводов

1. **`SEALED` не означает отсутствие Host в базовой схеме.** Контроль `tm.sys` подтверждает обратное. SEALED у State version защищает его mapping от изученных overlay branches; family bit4 (0x10) — не SEALED.
2. **Original modern schema имеет compact V7**: наследование legacy V6 Host patch не означает V7 resolver binding; присутствие UTF-16 имени до composer не означает драйвер загружен.
3. **Trace `+0x14` — новый host record**, а не новый Trace contract/version (Trace уже существует). A/B/C одной загрузки и AL=0 другой строго разделены. Причина конкретного AL=0 исторически неизвестна.
4. **Все три Phase callbacks принимают `STATUS_NOT_SUPPORTED` и в FOS22000 тоже**; прежнее предположение о нарушении поведения 22000 из-за знакового status было основано на неполном анализе cold code.
5. **`INIT` DISCARDABLE не означает гарантированный UAF:** при dependency bit26 обычная очистка пропускает INIT в обоих kernels. Обратное утверждение «никогда не выгружается» тоже не доказано; incoming image classification и reuse важны.
6. **Общее число 26/26 импорта — не 26/26 semantic compatibility**. Итог по 26: 4 ограниченно SAME и 22 INCONCLUSIVE; доказанного breaking contract нет.
7. **Phase2 — WNF demand path**, не гарантированный sequential «Phase0→Phase1→Phase2» boot callback. Нужно учитывать disposition, cache, silo и конкуренцию.
8. **`XRo` подключён** — факт; **MainOS полностью immutable** — требует enforcement evidence. `O:` без XRo в snapshot согласуется с mutable store, но не доказывает отдельность backing.
9. **`OSDataRoot` может иметь fallback к boot device**; одни Volume GUID, буквы, registry symbolic links и названия томов не доказывают нужную physical separation.
10. **Успех Phase1 может быть ранним** из-за FirstBoot.Complete; успех тестовой прямой OSDATA записи не доказывает автоматическое перенаправление всего SOFTWARE.
11. **Старые 26100 RVAs, hashes/counts не актуальны** для 26100.9444/9457/9278. Сверять конкретные versions.
12. **Private loader structure нельзя копировать**: full entries 0xE8 vs 0x120, Pip metadata +0xE4 vs +0x11C, constructor modes и return protocol IoInitSystemPreDrivers различаются.
13. Исторические `0x5F` и `OslGetRootCell` AV — **причинность не установлена**; нельзя объявлять, что их вызывает отсутствие/добавление определённого SYS или hive.
14. **Две FOS VM и два boot sessions — независимые эксперименты**. Данные новой Factory OS, старого WinDbg, TraceSmoke и modern 26100 не объединяются в один trace.

## 12. Оставшиеся P0/P1/P2 вопросы из последнего умного аудита

### P0 — то, что мешает считать настоящий 26100 Core готовым

- **S01:** аутентичный согласованный, подписанный и принятый платформой набор `State Separation Host`/компонентов для 26100, including package/schema provenance и ранний выбор Host. Исходный State API Set остаётся без Host/SEALED. Отсутствие кандидата в текущем пакете не доказывает глобальное отсутствие у Microsoft; кандидата **не установили**.
- **S02:** полный семантический дифф CM paths (`CmCreateKey/OpenKey/SetValue/Rename`, REG_LINK/VOLATILE, registry callbacks/security/hive backends/error handling) для реально вызываемых F операций.
- **S03:** полный incoming graph пользователей глобального `StashNameBuffer` и lock scopes, WNF indices/silos/threads; `cmpxchg` после callback не доказывает его сериализацию.
- **R01:** исходный FOS package/volume identity/provenance двух PE из разных томов — hashes и versions совпали, но цепочка полного image/подписей не установлена.
- **R02:** настоящий runtime 26100 IAT binding к State Host, фактический final loader Flags/classification и поздний INIT mapping в **одной** загрузке. В исследованной штатной 26100 по-прежнему только fallback.
- **R03:** реальный `\OSDataRoot → \Device\OSDataDevice → volume/device/backing` без ArcBootDevice fallback, Separate MainOS/OSData writable store. Свежий `fltmc` закрыл часть DOS/NT mapping **в FOS**, но не разрешил сам object link и современную 26100 topology.
- **R04:** реально подключённые OSDATA/OSBOOT/DEVICES hives, backing file identity, `HiveRedirectionList` targets/ACL/security в нужный boot момент. Registry key не эквивалентен LOADED HIVE.

### P1 — нужно для безопасного выполнения и persistence

- **S04:** все explicit unload/discard/low-memory refcount paths сверх normal `MiSnapDriverRange`.
- **S05:** loader existing-entry/alias/image-policy, final bit26 и подпись/trust.
- **S06:** 22 uncertain NT APIs по используемым options, I/O completion/security/pool/CRT provider graph.
- **S07:** полный policy activation dataflow → loader fields → `CmStateSeparationEnabled`/dev mode/volatility, time of hive readiness.
- **S08:** `RtlGetPersistedStateLocation` negative-result caching/reset/write sites и полный file/key mapping consumers.
- **R05:** возможные Phase2 late calls, index/silo/IRQL/locks/scratch/concurrency, конкретный code lifetime.
- **R06:** единый boot/session timeline Phase0→Phase1→hive readiness→WNF→cleanup (с учётом demand-dependent Phase2).
- **R07:** фактически durable relocation после перезагрузок/ошибок — SAM, SECURITY, DEFAULT и другие split hives, файловые и реестровые redirection rules.
- **R08:** полный MainOS write enforcement, включая разные пути изменения файлов; persistence. Исторический отказ записи плюс XRo M/B — сильные данные FOS, но не полный proof.

### P2 — полноценная пригодность ОС

- **S09:** modern XRo/FLTMGR, user-mode/state-separation DLL, servicing/recovery and package graph, Storage Spaces; согласованные hashes/package identities.
- **S10:** полное происхождение PDB и нескольких несовпадающих export/public anchors (не блокирует уже независимо сверенные opcode bytes).
- **R09:** фактическое обновление, откат, recovery и servicing системы с разделённым состоянием.

## 13. Принципиальная оценка перспектив и следующий порядок

**Хорошая новость:** новые сравнения устранены/сужены многие риски, которые могли быть фундаментальным стоп-сигналом: нативные hook callsites действительно живы в 26100; стандартная форма Phase ABI и STATUS_NOT_SUPPORTED совпадают; обычный `INIT` lifetime selector совместим по проверенному механизму; WNF dataflow сильно похож; ядро содержит ранние split-hive/OSData consumers. Это серьёзнее поиска похожих строк в бинарниках.

**Сложность остаётся серьёзной:** нет современного готового signed and accepted State Separation Host/целого состава компонентов; не доказаны 22 сложных семантики API, CM scratch serialization, реальная отдельная backing topology, MainOS write enforcement и servicing. Интеграция старых 22000 бинарников путём прямой подмены не обоснована. **Ни «проект невозможен», ни «мы почти собрали ОС» из текущих материалов не следует.** Мы построили технически обоснованную карту исследуемой архитектуры.

**Рациональная очередь работ без повторения прошлых экспериментов:**

1. Закрыть **FOS-reference topology** read-only: из одной актуальной сессии установить target `\OSDataRoot`/`\Device\OSDataDevice`, backing томов, реально загруженные OSBOOT/DEVICES/OSDATA и граф устройств; сохранить хеши/identity. Свежие mountvol+fltmc — первый подтверждённый слой этого описания.
2. Параллельно завершить **offline P0 S01/S02/S03** (только при наличии новых исходных пакетов/доказательств): поиск 26100-consistent component set и узкий CM/scratch audit. Не заниматься случайными новыми патчами API Set.
3. Сформулировать acceptance criteria и риск-гейты для отдельной disposable Hyper-V Gen2 boot PoC, только когда binding/implementation/store evidence закрыты. Включение Policy без ранних hives, перезапись stock schema, прямой сервисный запуск FOS SYS, отключение подписей ради «успеха» не решают архитектурную проблему.
4. После валидного binding/boot: проверить реальное разделение registry+files, отказ MainOS writes, persistence на reboot, recovery и servicing. Только затем оболочка 20279 и совместимость приложений.

**Следующий безопасный шаг в текущем чате:** больше не повторять `fltmc volumes/instances`. Чётко зафиксировать уже полученные snapshots; затем выбрать одну read-only проверку разрешения `\OSDataRoot` и связанных загрузочных volume/hive backings на FOS. Изучение конкретной команды зависит от наличия утилит в минимальной FOS; цель — чтение Object Manager symbolic link, без изменения томов/BCD/реестра. Исследование на работающей 26100 не начинать до обоснования P0.

## 14. Реестр документов/оригинальных результатов (вложены в ZIP)

**Последний внешний ИИ 2026-11-11:** `README-RESULTS.md`, `VERIFIED.md`, `DIFFERENCES.md`, `LOADER-LIFECYCLE.md`, `PHASE-CALLGRAPH.md`, `NT-API-COMPATIBILITY.csv`, `NT-API-DETAILS.md`, `STATE-DEPENDENCIES.md`, `UNRESOLVED.md`, `NEXT-READONLY-CHECKS.md`. Это полный набор **10 полученных отдельных файлов**, даже если пользователь называл «восемь материалов» (некоторые были сгруппированы как один пункт).

**Внешний ИИ 2026-11-10, V7:** `VERIFIED.md`, `V7-STRUCTURES.md`, `SEALED-SEMANTICS.md`, `TRACE-EXPLANATION.md`, `UNRESOLVED.md`, `v7_decode.py` — копии с отдельным префиксом, чтобы не конфликтовать с одноимёнными файлами последнего отчёта.

**Предшествующие checkpoints:** `Windows11X-APISet-26100-TraceSmoke-Verified-Checkpoint-2026-11-10.md`; `Windows11X-26100-9457-Confirmed-Status-2026-11-08.md`; `Windows11X-WCOS-Verified-Audit-and-Plan-2026-11-07.md`; первичные реестровые сравнения/FactoryOS runtime map, `Windows11X-StateSeparation-22000-vs-26100-Offline-Audit-2026-11-10.zip`.

**Полная база бинарников и алгоритмических доказательств:** `Windows11X-FOS22000-vs-NT26100-Semantic-Comparison-2026-11-11.zip` (семь primary PE / задача сравнения), более ранние архивы offline audits. **Загруженные в чат 10 финальных report files последнего внешнего ИИ — отдельные источники**, их НЕ было внутри раннего 16MB task ZIP (созданного до того как ИИ выполнил задание). Данный новый пакет добавляет их и явным образом сохраняет provenance.

**Честная граница этого checkpoint:** при подготовке данного текста не был повторён весь Capstone/PDB/92-instruction audit, не сделан новый runtime debugger trace и не получен физический дисковый/Storage Spaces map. Все цитируемые machine offsets восходят к указанным первичным отчётам/скриптам и отличаются от нынешнего live fltmc observation. Для независимой проверки нужны вложенные первичные evidence archives и соответствующие PE. Текст не должен интерпретироваться как утверждение «WCOS26100 уже работает».
