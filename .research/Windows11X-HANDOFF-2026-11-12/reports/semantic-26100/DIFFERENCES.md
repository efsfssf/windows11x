# Сходства, различия и классификация

Классификация относится к конкретному механизму и указанным условиям. `COMPATIBLE` не означает работоспособный boot или весь WCOS. `INCOMPATIBLE` требует положительного противоречия, а не отсутствия доказательств. Полная переносимость модуля в исходную 26100 — **UNKNOWN**; из коробки его Host не выбран.

## COMPATIBLE — проверенная область

| Механизм | Основание | Граница |
|---|---|---|
| Архитектура import dependency → loader Flags → kernel selector | W22000 `0xEF82C/0xEFEE5`, W26100 `0x19D5FB/0x19DCDA`; K22000 `0x77C355/0x77C467`, K26100 `0xA536FA/0xA53999` | Новый принятый dependency image; не любой произвольный способ загрузки |
| Common `_KLDR_DATA_TABLE_ENTRY` prefix | В обоих PDB size `0xA0`, DllBase `+0x30`, Flags `+0x68`, LoadCount `+0x6C`, LoadedImports `+0x88`; bytes копирования подтверждены | Полные boot/kernel private allocations этим не описаны |
| Внешние callback Phase0/1 | В обоих kernels те же boolean sources, calling convention и статусное решение | Успех callback не доказывает persistence |
| Внешний Phase2 для рассмотренного WNF вызова | CL=enabled, RDX/R8=`UNICODE_STRING*`; три descriptor pairs совпадают по содержимому | Все возможные будущие consumers и arbitrary malformed input не исследованы |
| `STATUS_NOT_SUPPORTED` как необязательная реализация | Шесть реальных compares, включая cold ranges 22000 | Не превращает остальные ошибки в допустимый результат |
| WNF new-root gate и publication order | `CreateDisposition==1`, callback, затем cmpxchg в обоих kernels | Не доказывает общую сериализацию |
| Внешнее name binding 26 API | Прямые exports обоих kernels; все 26 реально используются F | Это отдельный, более узкий результат, чем API semantics |
| Четыре коротких NT contracts | `RtlInitUnicodeString`, `RtlAppendUnicodeToString`, SD getter/setter; см. NT-API-DETAILS | Валидные буферы, оговорённый диапазон, без недоказанного aliasing |
| Назначение boot namespace fields | В обоих `_LOADER_PARAMETER_BLOCK` size `0x170`, ArcBootDeviceName `+0xB8`, ArcOSDataDeviceName `+0x150`; код читает именно их | Физическая топология не следует из layout |

## Доказанные различия; какие предположения несовместимы

| Объект сравнения | 22000 | 26100 | Классификация и смысл |
|---|---|---|---|
| Исходное API Set binding State Separation | V6 entry Flags=0, default `statesepext.sys`; Schema RVA `0x703C/0x186FC` | Embedded7, family без default Host; version SEALED; RVA `0x24158/0x28A10` | **INCOMPATIBLE с предположением об одинаковой готовой привязке**. Современная исходная схема не загружает F. Это конфигурационное различие, не доказательство сломанного Phase ABI |
| Полный loader boot entry allocation | W `0xEFE8B`: size `0xE8` | W `0x19DC27`: size `0x120` | **INCOMPATIBLE с полным побайтным отождествлением структур**. Совместим исследованный prefix |
| Per-image metadata в PipInitializeDriverDependentDLLs | K `0xB02F91`: чтение `entry+0xE4` | K `0xC71C00`: чтение `entry+0x11C` | **INCOMPATIBLE с переносом этого частного offset**. F не импортирует эту private структуру |
| Входной режим MiConstructLoaderEntry для boot records | K `0xB16054`: stack mode=1 | K `0xC695A2..BC`: mode=`0x10000000` | **UNKNOWN для полного private ABI; CHANGED internal protocol**. Одинаковый смысл конкретного copy path проверен. Передавать старое число в современный внутренний routine на основании этого аудита нельзя |
| IoInitSystemPreDrivers: проверка результата родителем | K `0xB05C98..9F`: call, `test al,al`, false-failure | K `0xC28B5A..61`: call, `test eax,eax`, sign-failure | **INCOMPATIBLE с отождествлением старого internal boolean return и нового NTSTATUS return**. Внешний Phase0 NTSTATUS при этом сохранён |
| Boot INIT cleanup реализация | K `MmFreeBootDriverInitializationCode` `0x8111C8`: load lock/resource + прямой module-list loop | K `Phase1Initialization` → enumeration/callback `MiFreeBootDriverInitializationCode` `0x7F6610` | **CHANGED implementation; COMPATIBLE по исследованному selector predicate**, точная эквивалентность всех MM side effects UNKNOWN |
| DllInitialize dispatch | K `MmCallDllInitialize` `0x810924`: большой inline helper | K `0x7F60FC`: lookup + `MiIssueDllInitializeCall` | **UNKNOWN для полной эквивалентности; CHANGED implementation**. Необязательный export проверяется в обоих. Это не доказанный разрыв F: у него `DllInitialize` отсутствует |
| NT registry frontends | NtOpenKey и NtCreateKey прямо вызывают CmOpenKey/CmCreateKey | NtOpenKey через NtOpenKeyEx; NtCreateKey через stack-expansion callout | **UNKNOWN для полной семантики; CHANGED implementation**. Одна перестройка callgraph не означает INCOMPATIBLE API |

Ни один исследованный конкретный внешний Phase/NT API contract, используемый F, не дал доказанного безусловного breaking change между этими kernels. В частности, не обнаружено доказательства удаления REG_LINK, VOLATILE keys или FILE_BASIC_INFORMATION class4. Утверждать их отсутствие либо гарантированное поведение во всех условиях нельзя.

## UNKNOWN — точный перечень незакрытых механизмов

| Механизм | Почему UNKNOWN |
|---|---|
| Рабочая современная State Separation Host integration | Входная schema26100 Host не содержит; согласованный современный пакет/поддерживаемая привязка не предоставлены |
| Полная семантика 22 из 26 NT imports | Сопоставлены реальные тела и существенные вызовы, но CM/I/O/security/allocator/CRT graph и все внешние conditions не замкнуты |
| Сериализация registry scratch `F+0x3068/0x3060` | WNF cmpxchg следует после callback; sequence lock в другом месте; F не содержит доказанного собственного lock |
| Все unload/discard/low-memory lifetimes | Mode2 INIT retention не заменяет аудит MmDiscardDriverSection/unload/refcounts/прочих consumers |
| Первый момент Phase2 и его полное отношение к boot phases | Есть demand paths; тотального порядка с Phase0/1 из static graph не следует |
| Фактические IRQL, locks и silo в конкретном вызове | Наличие INIT/PAGE и syscall entry недостаточно для runtime значений |
| Корректная подготовка OSData, DEVICES, OSBOOT и relocated hives | Имена и native code имеются в обеих версиях; runtime backing stores не сняты |
| MainOS immutability и перенаправление всех writes | В семи входах нет полного enforcement stack; историческая XRo instance не доказывает enforcement |
| Persistence, servicing, update/recovery и user-mode State Separation | Не покрываются тремя kernel callbacks и таблицей импортов |
| Единый исходный FOS image/package provenance и подписи | Локальные hashes проверены; full image chain/volume origin и CI acceptance не выведены из них |

Источники: [VERIFIED](VERIFIED.md), [loader lifecycle](LOADER-LIFECYCLE.md), [phase contract](PHASE-CALLGRAPH.md), [API CSV](NT-API-COMPATIBILITY.csv), [пробелы](UNRESOLVED.md). Здесь нет предложения устранять перечисленные различия патчами.
