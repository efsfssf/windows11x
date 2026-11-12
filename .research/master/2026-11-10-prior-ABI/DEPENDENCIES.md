# Состояние, ранняя загрузка, MainOS/OSData и servicing

Завершение аудита: 2026-11-11; каталог сохраняет дату начала 2026-11-10. Ниже K — kernel26100.9457, W — winload26100.9444, F — statesepext22000.1. Все адреса — RVA именно этих PE. Наличие кода отделено от активации на конкретном устройстве.

## Исполняемые механизмы в 26100

| Уровень | Независимо проверенные bytes/data | Что это подтверждает / чего не подтверждает |
|---|---|---|
| Policy | K `0xC84D90/0xC84DC0/0xC84DF0` ссылаются на Enabled/DevelopmentMode/AllHivesVolatile; globals `0xEEFB28/24/20` | Policy consumers существуют; реальное значение hive не извлекается из BSS исходного PE |
| Kernel hooks | Три Phase import descriptors/IAT/calls; общий parent `0x705B30` вызывает Phase0-путь до Phase1-пути | Архитектура extension integration сохраняется; это не готовый Host |
| Registry/files map API | Export K `RtlGetPersistedStateLocation` `0x94DD10`; descriptors `0xB4A608/0xB4A618` указывают на `...\StateSeparation\RedirectionMap\Keys/Files` | Есть код открытия map roots, source subkey и чтения value; одной строки в PE для этого вывода недостаточно |
| WNF state | K `0x8816A4 → 0xB0F924`; descriptor index1 меняет Notifications на `\REGISTRY\MACHINE\OSDATA\Notifications` | Есть native consumer отдельного OSDATA namespace и Phase2; backend hive/INIT lifetime отдельно не доказаны |
| OSData namespace | K function `0xC18A18`: `0xC18CF8` использует `\Device\OSDataDevice`, `0xC18DC1/+DDE` — `\OSDataRoot` и OSDataDevice | Код формирует соответствующие namespace descriptors и выполняет операции создания; реальная физическая топология не следует из имён |
| DEVICES | W `0x9821/+982D`, `0x9846 → 0x3DA4`: имя DEVICES и `\windows\system32\config\DEVICES`; K `0xC5461A..2F` при Enabled передаёт DEVICES helper `0xC540E8` | Есть загрузочная/CM ветвь для отдельного состояния; её guards/device/hive success в конкретном boot не сняты |
| OSBOOT | W `0x98BB/+98C7`, `0x98E0 → 0x3DA4`: OSBOOT и соответствующий файл Config | Ветка существует, но не выполняется безусловно: W `0x97E6..0x9806`, `0x9895..A2` проверяет входные состояния |

Источник bytes: [kernel-native-functions.asm](evidence/kernel-native-functions.asm), [kernel-order-and-storage.asm](evidence/kernel-order-and-storage.asm), [winload-hive-loading.asm](evidence/winload-hive-loading.asm). Полный список найденных RIP/direct references — `kernel-native-references.json`, `winload-native-references.json`.

Две существенные детали:

1. В K `0xC18D08..3C` используется указатель входной структуры `+0x150`, а при NULL выбирается `+0xB8`. Та же процедура далее формирует OSDataDevice/OSDataRoot. Поэтому даже наличие этих имён и успех namespace operations ещё не доказывают физическое отделение writable state от MainOS. Типовые имена полей loader block не подставлялись без нового type audit.
2. K `RtlGetPersistedStateLocation` проверяет byte `0xE67AD1` на `0x94DD69`; при открытии отсутствующего root (`STATUS_OBJECT_NAME_NOT_FOUND`) записывает 1 на `0x94DEC4` и идёт к default-path logic. На этом пути имеется cached absence. Полный набор resets этого byte не исследован: нельзя обещать, что позднее создание map автоматически активирует все ранние обращения. Cold code `0xB218B4..0xB21922` читает class2 info, требует REG_SZ при успешном query и копирует Data+0xC.

## Что необходимо F ещё до выполнения полезной работы

Phase0 требует доступного SYSTEM policy/rules и корректных OSBOOT target paths. Helper `0x91A4` создаёт key parents через ZwCreateKey, а не подсоединяет hive. Обычный ключ с именем OSBOOT без правильного backing store не обеспечивает требуемую persistence. После rename source и создания volatile links часть изменений уже сделана; последующая ошибка не откатывает всё дерево.

Phase1 требует работающего file I/O и разрешения `\OSDataRoot`; target имена приходят из HiveRedirectionList. Данные SAM/SECURITY/DEFAULT берутся из `\SystemRoot\system32\config\...`. Входной системный hive, каталог target и их ACL должны быть согласованы. Простое наличие тома O: недостаточно: F отдельно имеет жёстко заданный sentinel `\??\O:\Windows\FirstBoot.Complete`, а копирует через OSDataRoot и конфигурационные target paths.

Если sentinel уже существует, Phase1 возвращает success без доказательства правильного содержимого target hives. Если source file отсутствует, copy helper специально допускает success. Поэтому ни EAX=0, ни сам факт вызова Phase1 не являются проверкой готовности всего state backend.

Phase2 требует корректных source/target UNICODE_STRING registry paths, доступных registry stores, PASSIVE-compatible контекста и сохранности INIT кода F. F registry scratch `0x3068/0x3060` используется общим helper `0x11A0`, а Phase2 затем его освобождает. Сериализация извне не доказана. Отдельный file-security scratch F `0x3070/0x3078` используется helper `0x83CC/0x8510`; его нельзя смешивать с registry scratch.

## Что ещё входит в настоящую State Separation

Повторно пересчитан исторический Factory inventory: 62 Files, 459 Keys и 81 KeyRedirectionLinks. Две KernelLink записи, которые выбирает ранний F helper, — лишь часть конфигурации. В inventory targets Keys распределены как 400 OSDATA, 40 OSBOOT, 19 DEVICES; physical boundary `M:\Data` для 25 file mappings самим inventory не установлен. Объём конфигурации не означает, что каждое правило действительно отработало.

Извлечённый FOS `onecorestateseparationext.dll` экспортирует `InitializeStateSeparation` RVA `0x1980`, SHA-256 `e73a7cdf579db78159ec4d2761742d80dcf5a8da2f4abb185efdc9662f436ada`. Его полный ABI/поведение здесь не объявлены восстановленными. В оригинальной schema26100 соответствующий `ext-ms-win-core-stateseparationext-l1-1-0` заново декодирован: SEALED и Host отсутствует ([core-stateseparation-v7.json](evidence/core-stateseparation-v7.json)). Это отдельный user-mode integration вопрос, который одним kernel SYS не закрывается.

MainOS write protection — отдельная обязанность. В 26 imports F нет FltRegisterFilter или реализации minifilter. В данном наборе Factory этим занимается другой компонент XRo и его конфигурация. Отдельный mutable том и registry links сами по себе не обеспечивают запрет записи MainOS.

## Проверка дополнительного архива пользователя

Прочитан и распакован как данные `E:\WCOS\JSON_Results_From_AI\Windows11X-WCOS-2026-11-05-COMPLETE-HANDOFF.zip`, SHA-256 `6d088bba79750b337d8f1b76be0bf31278c66e1c107421467c974233742c1524`. Inventory всех 78 members и вложенных ZIP — [archive-inventory.json](evidence/archive-inventory.json). Поставляемые Python/PowerShell/EXE не запускались. Для извлечения CAB payload использован штатный Windows expand.exe, без установки пакетов.

Найдено:

| Материал | Проверенный результат |
|---|---|
| Nested `26100-WCOS-binaries.zip` | Kernel/winload **26100.1742**, wcifs/spaceport **26100.1591**, usermgr **26100.712**; не заменяют целевой kernel9457 и не являются baseline22000 |
| XRo.sys из CAB | 22000.1, 110920 bytes, SHA-256 `64f8d35bdb9836f45e407fa12e9cfc6087d9b2d030251e340d03d601516d691c`; 62/62 его NT imports найдены в K9457 |
| SSBoot CAB | ssboot.exe 22000.1, 704512 bytes, SHA-256 `0fc2cc3c6aba528535f401dc9056b2bef684d8b891f5c2a428802127d38c9cbc`; spaceutil.exe 22000.1, 221184 bytes, SHA-256 `4808ee3584f81ccec2fc792e6f2a106910e7531d2303883fc22d2e8c521a5090` |
| FeatureManifest | FactoryOSFM OSVersion22000.1: SS-Boot и SplitLayoutSupport в BasePackages; STATESEPARATION_ON и DEVMODE выбирают разные packages |
| Enable-StateSeparation MUM | Version22000.1; перечислены RedirectionMap Baseline/OneCore, SecurityTemplates, MemoryManager-Config, ImageCustomization, **ServicingMap Files/Keys** |
| Generic device layout XML | Описаны отдельные MainOS/BSP/OSData/Data stores в OSPool. Это layout metadata22000, не доказательство текущей топологии26100 |

Проверены реальные PE версии и хеши: [component-pe-inventory.json](evidence/component-pe-inventory.json). FLTMGR26100 и ntdll26100 в этом архиве не предоставлены, поэтому остальные XRo imports и SSBoot NT user-mode зависимости заново по целевым DLL не сверялись.

**Чего не найдено:** оригинального kernel22000 и согласованного State Separation package set26100. Вложенные ZIP проверены по inventory; единственный вложенный ntoskrnl относится к 26100.1742. Каталог пользователя за пределами названного архива не обследовался.

Исторический `26100-XRo-SSBoot-check.json` сообщает отсутствие XRo.sys и Windows\ssboot.exe в тогда смонтированном IoT LTSC образе; spaceutil26100.1 там был. Это чужой snapshot старого образа, не новый полный аудит всех редакций/пакетов 26100. Отсутствие в нём не доказывает глобальное отсутствие Microsoft реализации26100.

## Обслуживание

MUM доказывает наличие отдельной servicing-конфигурации в наборе22000. Он не доказывает применимость его package identities к 26100, совместимость component store, ACL/migration rules, обновление split hives и recovery. Runtime callbacks F не содержат целиком этот pipeline.

Следовательно, совместимость требует согласованности как минимум четырёх слоёв: выбранный kernel/user-mode Host; ранние devices/hives/namespace; карты и ACL с writable-state/write-protection; servicing/recovery metadata. Наличие native кодовых ветвей 26100 поддерживает дальнейшее исследование этих слоёв, но не закрывает их фактическую совместимость.
