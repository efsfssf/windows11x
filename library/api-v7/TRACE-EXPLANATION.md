# Dynamic Trace: две загрузки и статическое объяснение

Код: только `winload.efi` 26100.9444 с SHA-256 `1c1a978448b8e0fc2284c13d6e55fde8bf9827dca432cdc8a9be66c2e729adda`. Адреса кода — RVA. Динамические данные — из [нового checkpoint](Windows11X-APISet-26100-TraceSmoke-Verified-Checkpoint-2026-11-10.md), а не из ZIP от 9 ноября и не из новой VM-сессии.

## Загрузка 1: A/B/C

| Точка | RVA | Pointer активного namespace | Size | Legacy Trace HostOffset / HostLength |
|---|---:|---|---:|---|
| A | `0x23222` | `fffff80077595f20` | `0x29C84` | `0x29C60 / 0x24` |
| B, перед вызовом dynamic extensions | `0x2335F` | `fffff80077595f20` | `0x29C84` | `0x29C60 / 0x24` |
| C, после возврата | `0x23375` | `fffff80078724d20` | `0x29C74` | `0x1F304 / 0` |

В A `du` показал `WcosTraceSmoke.sys`. В B проверенные header/value поля сохранялись. В C поиск строки не дал совпадений, pointer и size изменились. Это локализует изменение активной schema внутри вызова `OslpApplyDynamicSchemaExtensions`. Полных dump A/B нет: побайтная неизменность всех промежуточных таблиц в A→B не утверждается.

Фраза «Host был в памяти» здесь означает присутствие UTF-16 **имени** в буфере schema. Она не означает загрузку образа `WcosTraceSmoke.sys`, выполнение его кода или binding ядра к нему.

В этой загрузке predicate на `0x23023` не был заснят. Поэтому её A/B/C нельзя дополнять AL=0 из следующей загрузки как одним непрерывным логом.

## Загрузка 2: AL=0 и пустая ветвь

На `0x23023` реально наблюдалось `AL=0`. Затем пошагово исполнены `je 0x2302D`, `lea rax,[empty_string]`, сохранение RAX и `du @rax → ""`. Это отдельная загрузка.

Снятая таблица `RBX` содержала runtime pointers на Trace contract, `dtrace.sys` и predicate. В PE та же таблица находится по RVA `0x2B2DB0`:

| Slot | Содержимое в PE |
|---|---|
| `+0` | VA `0x1802BAA30` → RVA `0x2BAA30`, UTF-16 `ext-ms-win-ntos-trace-l1-1-0` |
| `+8` | VA `0x1802BB008` → RVA `0x2BB008`, UTF-16 `dtrace.sys` |
| `+0x10` | VA `0x180022F48` → RVA `0x22F48`, `OslpDtraceExtensionEnabled` |
| Следующая запись `+0x18` | NULL, конец списка |

Вызов predicate — `0x2301E`, через indirect-call helper `0x2AA860`; проверка результата — `0x23023`. При true берётся table+8 (`0x23027`), при false — empty string RVA `0x2BABF8` (`0x2302D`). После этого обе ветви доходят до `ApiSetCreateSchema` (`0x23056`) и, если создание успешно, `ApiSetComposeSchema` (`0x23076`). False не означает «не создавать extension».

## Что именно проверяет predicate

`OslpDtraceExtensionEnabled`, начало `0x22F48`:

| RVA | Инструкции / смысл |
|---|---|
| `0x22F73` | Получение application entry через helper `0x18DAEC` |
| `0x22F7D..0x22F86` | EDX=`0x26000145`; RCX=application entry+0x18; чтение boot-option Boolean helper `0x18E3A4` |
| `0x22F8B..0x22F93` | Результирующий byte; если NTSTATUS отрицателен, `cmovs` заменяет его нулём |
| `0x22F97..0x22F9A` | Boolean=0 → `0x22FB3`, AL=0 |
| `0x22F9C..0x22FA7` | При Boolean!=0 вызывается `OslpIsApiSetHosted` для table[0], с текущей schema и заданным path/device context |
| `0x22FAC..0x22FAE` | Ответ hosted helper нормализуется к bool |

То есть включённый BCD option — только одно из условий. Он не подставляет `dtrace.sys` непосредственно в resolver и не гарантирует Host.

`OslpIsApiSetHosted`, начало `0x22E8C`:

1. Создаёт `UNICODE_STRING` контракта (`0x22EC0`), вызывает `ApiSetResolveToHost` на `0x22EDF`. Importer аргумент равен NULL (`0x22ECA`).
2. Отрицательный status (`0x22EE4..0x22EE6`), marker=0 (`0x22EE8..0x22EED`) либо Host.Length=0 (`0x22EEF..0x22EF4`) → AL=0, **до** обращения к host-файлу.
3. Для непустого Host вызывает `OslpDoesApiSetHostExist` (`0x22F07`) с промежуточным `drivers\` suffix из RVA `0x2BB020`.
4. Если первая попытка не удалась, повторяет helper с R8=NULL (`0x22F10..0x22F1D`), то есть без этого suffix. Любой успех → AL=1; оба неуспеха → AL=0.

`OslpDoesApiSetHostExist`, начало `0x22DE8`:

- Сохраняет исходную Length предоставленного path. При ненулевом R8 пытается добавить строку suffix через `0x191138` (`0x22E13..0x22E1D`); неуспех даёт false.
- Проверяет доступную ёмкость для Host и завершающего WCHAR (`0x22E1F..0x22E33`), копирует ровно Host.Length bytes и ставит NULL terminator (`0x22E35..0x22E57`).
- `0x22E62 → 0xB0B9C`: пробует открыть получившийся путь с flags `0x81`; при успехе helper закрывает handle (`0xB0BC2 → 0xAECF0`) и возвращает true. Отрицательный open status даёт false. Это проверка открытия, не загрузка/исполнение SYS.
- В обычном пути восстанавливает исходную Length по `0x22E6A`; размерный отказ также даёт false.

Следовательно, возможны false из-за ошибки чтения option, выключенного option, resolver/marker/пустого Host, ошибки подготовки path, недостаточной ёмкости либо неуспеха обеих file-open попыток. Одного снятого AL=0 недостаточно для выбора причины.

## Что дают оригинальные compact данные

Декодер устанавливает у исходной Trace family649:

```text
family N+0x21170: hash37=0x54020205
default index=0xFFFF, importer head=0xFFFF, count=0, flags=0x10
version N+0x25A28: family=649, flags=0
```

При **неизменённом compact mapping на входе predicate** V7 resolver возвращает marker1 и полностью нулевой Host. Тогда `0x22EF4` даёт false и до существования файлов дело не доходит. B меняет только legacy Trace value, а исходные compact tables остаются прежними. Поэтому включённый option вместе с одной V6-правкой не гарантирует true predicate.

Это конкретный статически доказанный способ получить false для предоставленных compact bytes. Он не доказывает, что именно `0x22EF4` выполнилась во второй загрузке: текущие compact данные, status чтения BCD и более ранние registry extensions в ней полностью не снимались.

## Почему rebuild убирает legacy-only Host

Вместо копирования всей Size B V7 composer строит новый layout по block counts и pool length. `0x27C128` копирует pool, legacy companion region и compact blocks; extension emission затем синхронизирует соответствующий legacy Host с новым compact результатом.

Статический исходный размер (`0x27BEFC..0x27C012`):

```text
0x98 + 0xC858 + align4(0x12A14)
+ 889*(12+8) + 4*(12+8) + 984*(12+8)
+ 0*(4+8) + 286*(12+8) + 6*16
= 0x29C60
```

Изменённая B Size=`0x29C84` участвует в проверке границ, но не является длиной слепой копии. Строка `WcosTraceSmoke.sys` находится после конца всех исходных compact blocks (`N+0x29C60`), и в host table ссылки на неё нет.

При создании dynamic extension `0x23034..0x23056` передаёт пару **[Host, Contract]**, где Host равен `""` при false, и byte flags=1. `ApiSetCreateSchema` `0x279EFC` создаёт V6 **host-extension**: outer entry name = Host, value Host = Contract; namespace Flags=1 (`0x279FEA`). `0x27A062` задаёт один value. Нельзя трактовать этот V6 extension как обычный base entry с contract в Name.

Для случая «compact base как original/B и единственный empty Trace extension» по коду установлено:

| Этап | Проверенный факт о пути |
|---|---|
| Sizing Host | `0x27C531` ищет пустое outer-host имя в host table; в оригинале hash0 отсутствует и все 286 host lengths ненулевые |
| Добавка | `0x27C546..0x27C54E`: +1 host, +`0x14` bytes, то есть record12+index8; pool growth для строки длины0 равен0 (`0x27C555..0x27C55B`) |
| Поиск контракта | Trace version712 уже существует; lookup ведёт в existing ветвь `0x27C5FC`, не в `0x27C5D6` |
| SEALED | Trace flags=0; gate `0x27C622` не закрывает mapping update; importer имя пусто, node не добавляется |
| Новый Host | `0x27C7C5 → 0x27C394` создаёт host record; `0x27C417` пишет chars=0, `0x27C455` пишет pool-relative offset текущего конца pool=`0x12A14` |
| Mapping | `0x27CA9A..0x27CA9F`: family.host-present=1, default index нового пустого Host |
| Legacy companion | `0x27CAED..0x27CAFE`: HostOffset=`0xC8F0+0x12A14=0x1F304`, HostLength=0 |

Получается Size=`0x29C60+0x14=0x29C74`, ровно наблюдаемый в C. При таком layout пустой Buffer указывает на конец pool, который одновременно является началом family block. Поэтому по адресу лежат бинарные данные: Length=0 не позволяет читать их как имя.

**Исправление предыдущего отчёта:** `STRUCTURES.md`/`DATAFLOW.md` ссылались на `0x27C5D6` как возможный источник добавки `0x14` для Trace. Теперь установлено, что Trace compact version **уже есть**. Для указанного исходного состояния добавка относится к **Host** на `0x27C54E`. Кроме того, ветвь отсутствующего version на `0x27C5D6..0x27C5F3` учитывает и version, и family, а не одну host-запись. Старые документы сохранены как история, но новая карта имеет приоритет.

Эта арифметика и структура — статическая реконструкция с явно указанными входами. Полный сгенерированный buffer, промежуточные records и конкретная sizing instruction не были сняты в загрузке1. Совпадение Size/HostOffset/Length подтверждает согласованность модели, не заменяя отсутствующую runtime-трассу.

## Предел вывода для State Separation

Trace имеет unsealed version flags=0. State Separation version flags=1 и проходит другой исход SEALED gate при попытке обычного overlay. Общий факт перестроения V7 объясняет исчезновение legacy-only изменения; Trace-контроль не доказывает применимость overlay к SEALED State Separation. Успешная обработка resolver запроса с Length=0 также не является успешной загрузкой, binding или выполнением Phase0/1/2.
