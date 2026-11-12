# Phase0/1/2: ABI и поведение, NT 26100.9457 ↔ Factory 22000.1

Дата: 2026-11-08. Только статический анализ копий PE; вызовов в VM не было. Здесь **новые адреса 26100.9457**, не адреса прежнего ядра 26100.1742.

## Результат

Внешний x64 ABI трёх экспортов восстановлен существенно точнее: Phase0 получает **два BOOLEAN: Enabled и DevelopmentMode**, Phase1 один BOOLEAN Enabled; Phase2 BOOLEAN Enabled и **два `PCUNICODE_STRING`**. На этой границе нет обнаруженного приватного большого kernel context, который требовалось бы переносить из 22000. Это положительное доказательство совместимости *формы вызовов*, а не доказательство безопасного выполнения старого расширения на 26100. Для загрузочного GO остаются доверенный binding, семантика/подготовка ульев и файлового пространства, порядок и обработка частичных изменений.

## Идентичность источников

| Источник | SHA-256 / идентификатор |
|---|---|
| `ntoskrnl.exe` 26100.9457 | `d90c69cfa925d42037ef1cdba3ff5b3f13cc14e16060938073b97e396fdde2f2` |
| `statesepext.sys` 22000.1 | `167fda36a71c2aad4c146bc66ae4c83e9d525d008d6abfd8eda0e056c9a96658` |
| Точный PDB NT 26100.9457 | `ntkrnlmp.pdb`, RSDS GUID `C29EBFB0-6B78-B3C0-20DC-A66D99713F9E`, Age `1`; Microsoft symbol server, ссылка ниже |

PDB извлечён по RSDS **текущего** PE. GUID и DBI Age=1 совпадают PE; Info stream Age=6, это отличие сохранено в metadata, причина здесь не устанавливается. Символы экспортируемых функций дополнительно совпадают PE export RVAs. Имена ниже проверены через S_PUB32, адреса — PE и `.pdata`; типы восстановлены из инструкций, не из отсутствующих private type symbols. См. `evidence/abi-current.asm`, `abi-factory.asm`, `abi-summary.json`, `abi-nt-symbols.json`, `abi-state-globals-xrefs.json`.

## Контракт границы

```c
NTSTATUS ExpInitializeStateSeparationPhase0(
    BOOLEAN StateSeparationEnabled, BOOLEAN DevelopmentMode);
NTSTATUS ExpInitializeStateSeparationPhase1(BOOLEAN StateSeparationEnabled);
NTSTATUS ExpInitializeStateSeparationPhase2(
    BOOLEAN StateSeparationEnabled,
    PCUNICODE_STRING SourceRegistryPath,
    PCUNICODE_STRING TargetRegistryPath);
```

Это реконструированные prototypes, не опубликованный Microsoft DDI и не разрешение загружать чужую версию модуля. Прототипы и layout для проверки находятся в `src/stateseparation_contract.h`.

| Фаза | NT 26100.9457 call / IAT RVA | Caller и секция | Фактические входы |
|---|---|---|---|
| Phase0 | `0xC294EE` / `0x150378` | `IoInitSystemPreDrivers`, диапазон `.pdata` `0xC28BCC–0xC29EA3`, `INIT` | CL ← `RtlIsStateSeparationEnabled` `0xA7AA90` → `CmIsStateSeparationEnabled` `0x4A7584`; DL ← `CmIsStateSeparationDevModeEnabled` `0x7DCF68` |
| Phase1 | `0xC6DC8E` / `0x150368` | `Phase1InitializationIoReady`, `0xC6DC38–0xC6E020`, `INIT` | CL ← `CmStateSeparationEnabled != 0` |
| Phase2 | `0xB0F996` / `0x150370` | Фрагмент `0xB0F924–0xB0F9E0`, `PAGE`; `.pdata` CHAININFO → `ExpWnfGetNameStoreRegistryRoot`, `0x8816A4–0x881769` | CL ← тот же Enabled; RDX ← descriptor+8; R8 ← descriptor+0x18 |

Обе функции получения режима читают **разные** DWORD globals: `CmStateSeparationEnabled` RVA `0xEEFB28`, `CmStateSeparationDevMode` RVA `0xEEFB24`. Их привязка к policy подтверждена таблицей `CmControlVector`: записи `0xC84D90`/`0xC84DC0`/`0xC84DF0` содержат `StateSeparation\Policy`, соответственно `Enabled`, `DevelopmentMode`, `AllHivesVolatile`, и адреса globals `0xEEFB28`/`0xEEFB24`/`0xEEFB20`. Это описание кода, **не** инструкция включить policy.

## Phase2 — не общий третий этап старта ОС

Caller обслуживает WNF name store и вызывает Phase2 при создании нового registry key (`CreateDisposition == 1`, `0xB0F978`). Поэтому последовательность Phase0 → Phase1 → Phase2 не означает три последовательных обязательных boot callbacks. Phase2 — условная миграция дерева WNF при первом создании выбранного хранилища. Время первого вызова в активной WCOS и политика освобождения секции `INIT` самого kernel extension здесь не доказаны; callee 22000 также находится в `INIT`. Нельзя из секции `PAGE` caller автоматически заключать, что extension можно вызывать после освобождения его `INIT`.

`ExpWnfNameStoreDescriptors` `0xB4D6F0` содержит записи размером `0x30`:

| Offset | Восстановленное поле |
|---|---|
| `0x00` | StoreKind + padding (8 байт) |
| `0x08` | Source `UNICODE_STRING`, 16 байт |
| `0x18` | StateSeparated `UNICODE_STRING`, 16 байт |
| `0x28` / `0x2C` | Два DWORD flags; имена типов не восстановлены |

Запись 1: source `\Registry\Machine\Software\Microsoft\Windows NT\CurrentVersion\Notifications`; target `\REGISTRY\MACHINE\OSDATA\Notifications`. Записи 0 и 2 сохраняют одинаковые source/target пути для system/volatile notifications. Factory Phase2 `0x8360–0x83C2`: Disabled либо любой NULL pointer → 0; иначе `0x1050(Source, Target, NULL)` (copy own values разрешён и для существующего target), затем освобождение глобального scratch buffer `0x3068` и обнуление `0x3060`. Оба аргумента передаются непосредственно как `OBJECT_ATTRIBUTES.ObjectName` внутри `0x1050`, что доказывает `PCUNICODE_STRING`, а не WCHAR C-string. Глобальный scratch означает дополнительное требование сериализации вызовов; полный lock graph здесь не восстановлен.

## Exact failure policy текущего NT

| Callsite | Статус | Действие |
|---|---|---|
| Phase0 | NT_SUCCESS **или** `0xC00000BB` | Продолжение с `0xC2951C` |
| Phase0 | Другой отрицательный NTSTATUS | `IopInitFailCode` по `0xF8CF50` ← `0x13` на `0xC2950D`; возврат исходного NTSTATUS через `0xC29E75` |
| Phase1 | NT_SUCCESS **или** `0xC00000BB` | Продолжение с `0xC6DCC3` |
| Phase1 | Другой отрицательный NTSTATUS | `KeBugCheckEx(0x32,0,0,0,0)` на `0xC6DCBD` |
| Phase2 | NT_SUCCESS **или** `0xC00000BB` | Продолжение с `0xB0F9BF` |
| Phase2 | Другой отрицательный NTSTATUS | Закрыть открытый key handle на `0xB0F9B3`, вернуть исходный статус через основной caller |

Наличие допуска `STATUS_NOT_SUPPORTED` объясняет необязательный extension в обычной Windows. Оно не объясняет исторический bugcheck `0x5F` и не является работающей State Separation. Имя `IopInitFailCode` по `0xF8CF50` подтверждено точным PDB.

## Оригинальная Factory Phase0

Экспорт `0x8090–0x8181`, `INIT`:

1. `Enabled == 0` → 0 без основной работы (`0x80B7`).
2. `Enabled && DevelopmentMode`: открывает policy key, пишет `DevModeEverEnabled` DWORD 1 (`0x8101`, `0x8147`). **Ошибка открытия и ошибка записи не являются итоговым отказом Phase0**: открытие при неуспехе переходит сразу к links, результат SetValue затирается закрытием и дальнейшими helper. Это best effort отметка.
3. `0x8165` вызывает `0x8644(DevelopmentMode)`; отрицательный статус сразу возвращается.
4. Только при неотрицательном ответе links helper вызывается `0x9344`, его статус становится результатом Phase0.

`KernelLink` правила здесь направлены в **OSBOOT**, не OSDATA:

| Правило | Source | Target | AlwaysEnabled |
|---|---|---|---|
| AccessibilityLink | `\REGISTRY\MACHINE\System\Accessibility` | `\REGISTRY\MACHINE\OSBOOT\System\Accessibility` | отсутствует |
| ComputerName | `\REGISTRY\MACHINE\SYSTEM\CurrentControlSet\Control\ComputerName` | `\REGISTRY\MACHINE\OSBOOT\SYSTEM\CurrentControlSet\Control\ComputerName` | 1 |

Детальная verified семантика `TreeSync`, rename/delete/create-link и частичных ошибок дана в `REGISTRY-PHASE0-MODEL.md` и исполняемой offline модели. В частности, TreeSync **не означает безусловную перезапись существующих значений target**. Нет основания добавлять транзакционный rollback в модель оригинала: результат должен отражать оставшееся частичное состояние.

## Phase1 и ранние ульи

Factory Phase1 `0x8190–0x8356` — raw file provisioning для `SAM`, `SECURITY`, `DEFAULT`, а не вызов загрузки живых `OSBOOT/OSDATA/DEVICES`. При Enabled=0 → 0. Пробует открыть `\??\O:\Windows\FirstBoot.Complete`; любой NT_SUCCESS → 0, любой отрицательный статус запускает provisioning. Обеспечивает каталоги `\OSDataRoot\Windows\System32\Config\`, получает target filenames из `HiveRedirectionList`, копирует SystemRoot hive files по одному. Helper copy использует `FILE_OVERWRITE_IF`: ошибка может удалить частичную target copy, предыдущий target не восстанавливается; ошибки flush игнорируются. Полный разбор и RVA — `evidence/abi-phase1-details.md`.

Для настоящего State Separation корректное backing-хранилище OSBOOT требуется **до реальной Phase0**, а OSDataRoot и файловая система — до работы Phase1. Links helper умеет создавать родителей target, поэтому успешная операция над именем `\Registry\Machine\OSBOOT` сама по себе не доказывает загрузку отдельного live hive. Сами callbacks не гарантируют, что подготовка выполнена.

## Зависимости и IRQL

26/26 имён импортов `statesepext.sys` экспортирует текущий NT9457; проверка сохранена в `abi-summary.json`. Они работают с NT registry, file I/O, security descriptors, pool, Unicode и UUID. Прямых импортов приватных kernel globals нет. Это снимает *name-resolution* несовместимость, но не требования к контексту и состоянию.

`ZwOpenKey` требует PASSIVE_LEVEL по Microsoft DDI; Phase0/Phase2 реально вызывают его. Phase1 выделяет paged pool (`ExAllocatePool2` Flags=0x100), выполняет синхронный file I/O. Поэтому необходим PASSIVE_LEVEL и подходящий system-thread/handle context. Сам факт `INIT` не является доказательством IRQL. Динамическое измерение текущего IRQL и полный caller lock graph на активном State Separation пути NT9457 в этом offline анализе **не выполнены**. Внутри трёх экспортов нет найденного подъёма IRQL.

EntryPoint оригинального расширения `0x8010` после security-cookie init вызывает `0x8078`, который возвращает `0xC0000001`. Обычный boot-start service с таким DriverEntry не представляет путь запуска Phase0/1/2.

## Совместимость: что доказано и что осталось

| Утверждение | Вердикт |
|---|---|
| Порядок/ширина аргументов 22000 callee соответствует текущим 26100 callers | Подтверждена поверхность x64 ABI |
| Импортируемые имена существуют в текущем ядре | Подтверждено 26/26 |
| Phase2 получает Unicode registry paths, а не неизвестный большой context | Подтверждено caller + callee |
| Старый модуль безопасно работает на новой конфигурации registry/storage | Не доказано |
| Доступность OSBOOT до Phase0 и OSDataRoot до Phase1 в текущей VM | Не доказана; обычная desktop конфигурация недостаточна |
| IRQL, concurrency и весь lock graph при активном режиме | Частично ограничены DDI, динамически не доказаны |
| Microsoft предоставляет servicing-compatible пару модуля и schema для текущего PC | Не следует из ABI; отдельный source/binding gate |

Утверждать полную бинарную совместимость 22000 → 26100 нельзя. Утверждать, что она исключена изменением сигнатуры Phase2, также нельзя: найденные данные показывают стандартные `UNICODE_STRING` и согласованные calling conventions. Разумный вывод — **ABI-кандидат, не сертифицированная переносимая реализация**.

## Воспроизведение и первичные ссылки

`abi-current.asm` и `abi-factory.asm` — полные выбранные функции/фрагменты с RVA. `abi-summary.json` содержит PDB identity, SHA-256, import-set проверку и распарсенные WNF descriptors. `src/abi_audit.py` повторно извлекает ABI evidence из заданных PE/PDB (см. `--help`). `abi-analysis.py` — дополнительный offline поиск references к mode globals, требует `pefile`/`capstone` и точного PDB; PDB можно повторно получить из Microsoft symbol server по PE RSDS.

- [Точный NT PDB](https://msdl.microsoft.com/download/symbols/ntkrnlmp.pdb/C29EBFB06B78B3C020DCA66D99713F9E1/ntkrnlmp.pdb)
- [ZwOpenKey — IRQL PASSIVE_LEVEL](https://learn.microsoft.com/en-us/windows-hardware/drivers/ddi/wdm/nf-wdm-zwopenkey)
- [ExAllocatePool2 — paged pool и IRQL](https://learn.microsoft.com/en-us/windows-hardware/drivers/ddi/wdm/nf-wdm-exallocatepool2)
