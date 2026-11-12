# Реальные зависимости State Separation

Здесь разделены: код оригинального FOS как reference; наличие соответствующих consumers в 26100; историческая конфигурация; недоказанные свойства работающей системы. Новый пакет содержит 7 PE, а не полный набор split-layout/storage/servicing компонентов.

## 1. Policy и порядок готовности

Boot Phase0 получает enabled и development-mode через kernel helpers. Phase1/2 читают CmStateSeparationEnabled. Сами callbacks не устанавливают все устройства/ульи/policy. Наличие строки Enabled или установка одного флага не заменяют создание нужной topology.

В F Phase0 `0x8090`:

```text
enabled?
  development-mode? -> best-effort DevModeEverEnabled
  InitializeStateSeparationRedirectionLinks(DevelopmentMode)
  при успехе -> ReflectComputerName
```

Helper `0x8644` перечисляет KeyRedirectionLinks, запрашивает KernelLink (`0x883E`) и AlwaysEnabled (`0x8889`), читает source/target paths, создаёт нужные parents и вызывает registry copy/rename/link helpers. Реальные policy/rules должны быть доступны в ранней SYSTEM namespace. `RecurseCreateKeyTreeForPath` `0x91A4` использует ZwCreateKey — **не подсоединяет отдельный hive**.

Следовательно, ключ с именем OSBOOT или OSDATA без проверенного backing store не заменяет правильно подключённый hive. Registry link, копирование состояния и будущая persistence — разные свойства. После rename/copy часть изменений может уже произойти; общий rollback в F не обнаружен.

## 2. Что обеспечивает winload в обеих версиях

В `OslPrepareTarget` имеются guarded branches для дополнительных hives:

| Hive | W22000 | W26100 | Доказано |
|---|---|---|---|
| DEVICES | name `0x9E72`, path `0x9E81`, attach `0x9E9A → 0x32D0` | `0x9821`, `0x982D`, `0x9846 → 0x3DA4` | Передача DEVICES и `\windows\system32\config\DEVICES` в OslAttachHiveToLoaderBlock |
| OSBOOT | `0x9F16`, `0x9F25`, `0x9F3E → 0x32D0` | `0x98BB`, `0x98C7`, `0x98E0 → 0x3DA4` | Аналогичная передача OSBOOT и Config\OSBOOT |

Ветви не безусловны: проверяются входное состояние/handle, boolean и OslIsHhrPrepare. W22000 `0x9E38..0x9E59`, `0x9EF1..0x9EFE`; W26100 `0x97E6..0x9806`, `0x9895..0x98A2`. После attach имеются status checks и выбор control set. Полностью приписывать всем входным booleans policy meaning без анализа их producers нельзя.

Kernel consumers `CmGetSystemDriverList`, `CmpInitializeSystemHivesLoad` и CM state-separation helpers также имеются в обоих images. Прочитаны обе реализации и их дополнительные ветви (`{kf,kn}-native-extra.asm`). Часть CM internals переработана: например, у 26100 есть `CmpFindStateSepKeysRedirectionMapNode`, тогда как в 22000 открытие map выполняется другим путём. Это подтверждает сохранение native consumers, а не одинаковые ошибки/ACL/persistence во всём CM graph.

## 3. OSData namespace и физическое устройство

В обоих kernels `CreateSystemRootLink` формирует OSDataDevice/OSDataRoot. K22000 entry `0xB263D0`, K26100 `0xC18A18`.

| Действие | K22000 | K26100 |
|---|---:|---:|
| Имя `\Device\OSDataDevice` | reference `0xB26633`, string `0xB2CC50` | reference `0xC18CF8`, string `0xC76A90` |
| ArcOSDataDeviceName | `0xB26643`: `[rbx+0x150]` | `0xC18D08`: `[rbx+0x150]` |
| NULL fallback к ArcBootDeviceName | `0xB26677`: `[rbx+0xB8]` | `0xC18D3C`: `[rbx+0xB8]` |
| Имя `\OSDataRoot` | reference `0xB266D2`, string `0xB2CC80` | reference `0xC18DC1`, string `0xC76AC0` |

В `_LOADER_PARAMETER_BLOCK` обоих kernels эти поля действительно имеют такие имена и offsets; size0x170. Изученный код создаёт symbolic namespace links с проверками статусов. **При fallback OSData namespace может ссылаться на boot-device input**. Поэтому ни имя OSDataRoot, ни label тома OSData не доказывают отдельный физический writable store и неизменяемый MainOS.

## 4. Phase1: файлы перенаправленных hives

F `0x8190` сначала проверяет hard-coded sentinel `\??\O:\Windows\FirstBoot.Complete` (UTF-16 string `F+0x2880`, NativeCreateFile call `0x81E9`). Helper сам формирует UNICODE_STRING/OBJECT_ATTRIBUTES. Если open успешен, возвращает success без проверки всех target файлов. Если sentinel недоступен, создаёт directory `\OSDataRoot\Windows\System32\Config\` и читает targets через QueryHiveRedirectionFileList `0x8F18`.

Источники:

| Registry hive path для lookup target | File source |
|---|---|
| `\REGISTRY\MACHINE\SAM` | `\SystemRoot\system32\config\SAM` |
| `\REGISTRY\MACHINE\SECURITY` | `\SystemRoot\system32\config\SECURITY` |
| `\REGISTRY\USER\.DEFAULT` | `\SystemRoot\system32\config\DEFAULT` |

Targets и длины не выводятся из буквы O:, а приходят из HiveRedirectionList. Нужны правильные записи этого списка, доступный OSDataRoot, parent directories, согласованные ACL/metadata, доступность исходных файлов и допустимый I/O context. NativeCopyFile имеет отдельные failure/cleanup/EOF paths; отсутствие source допускает success на рассмотренном missing-source path. Не все Close/Delete/Flush statuses отражаются в итоговом результате. Поэтому успех Phase1 недостаточен для доказательства полной materialization/persistence SAM/SECURITY/DEFAULT.

Файловый buffer Phase1 принадлежит этому вызову и освобождается; это не тот же буфер, что глобальный registry scratch Phase2.

## 5. Phase2 и Notifications store

При WNF root creation передаётся source/target UNICODE_STRING. Единственная различающаяся пара из трёх — index1: Software\...\Notifications → `\REGISTRY\MACHINE\OSDATA\Notifications`. Строки и descriptor tail fields совпали в двух kernels. Нужны реальный доступный OSDATA hive и корректные source/target trees/ACL.

F Phase2 вызывает registry CopyKeyTreeByName, затем освобождает глобальные StashNameBuffer/Length. Собственный доказанный lock этой пары не найден. WNF handle cmpxchg стоит после callback. Реальное количество callbacks, их indices, silo context и возможное пересечение остаются UNKNOWN; одинаковый кодовый риск в reference не является новым breaking change26100.

## 6. Persisted-state API и RedirectionMap

В обоих kernels есть `RtlGetPersistedStateLocation`: K22000 `0x691210`, K26100 `0x94DD10`. Обе реализации и cold branches сохранены в `{kf,kn}-lifecycle-wnf.asm`. Они потребляют StateSeparation\RedirectionMap с registry/file mappings. Сам факт export/строки не был единственным основанием: разобраны lookup/query/control branches.

Эта работа не доказывает полную эквивалентность negative-result caching, resets, malformed values и всех callers. В частности, прежний 26100 вывод о cached absence нельзя переносить в виде обещания «позднее появление map немедленно активирует перенаправление». Для этого нужны полный reset/caller audit и конкретное состояние загрузки.

## 7. Историческая конфигурация FOS: OBSERVED с ограничениями

Повторно пересчитан `reference/FactoryOS-22000-StateSeparation-runtime-map.json`:

- 62 RedirectionMap Files:37 OSDATA и25 `M:\Data` aliases с непроверенной физической границей.
- 459 RedirectionMap Keys:400 OSDATA,40 OSBOOT,19 DEVICES.
- 81 KeyRedirectionLinks:78 OSDATA,3 OSBOOT.
- Snapshot сообщает volumes MainOS/BSP/OSData/Data/SERVICING_FILES/PreInstalled и XRo на M:/B:, altitude261101, registry filter enabled.

Source-log SHA-256 в map: `489862be0faecf54152ae63779e1d7a5a90c53db4977dcf460c1204b30974704`. Это **чужой сохранённый read-only snapshot**, не новое наблюдение этой работы и не continuous trace с какими-либо другими VM boots. Собственный исходный log здесь не переаттестован. Map прямо ограничивает выводы: enforcement и persistence не тестировались, наличие правила не доказывает его исполнение, Storage Spaces topology не установлена. Сводка — [historical-map-summary.json](analysis/evidence/historical-map-summary.json).

## 8. MainOS immutability, user mode и servicing

У F 26 imports не содержат FltRegisterFilter; эти callback тела не реализуют целиком filesystem minifilter/write-enforcement stack. Исторические XRo instances свидетельствуют о наличии компонента в той FOS-конфигурации, но не доказывают, что все запрещённые записи отклонялись. Здесь не запускались записи для проверки enforcement.

Предыдущий offline audit описывает FOS22000 XRo/SSBoot/SpaceUtil, user-mode `onecorestateseparationext.dll`, MUM/ServicingMap и device-layout metadata. **В текущих семи первичных PE этих компонентов нет**. Их исторические версии/ABI и совместимость с26100 заново по согласованным современным компонентам не доказаны. Нельзя по отсутствию в одном старом snapshot объявить глобальное отсутствие Microsoft реализации в26100.

Servicing требует согласованных package identities, component store, split hive rules, ACL/security templates, update/recovery и user-mode initialization. Callback success, shared Phase names, labels разделов и matching26 imports не закрывают эти обязанности.

Минимальный набор независимых доказательств настоящего State Separation: реальная активация нужного Host и его lifetime; корректные writable stores/hives/namespace; исполнение redirection rules; enforcement MainOS; сохранение состояния; согласованные servicing/recovery consumers. Из них эта работа статически закрывает только перечисленные части kernel/loader pipeline.
