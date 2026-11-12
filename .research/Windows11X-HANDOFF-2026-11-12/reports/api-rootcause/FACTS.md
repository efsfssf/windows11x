# Проверенные факты и их источник

## Приложенные файлы

Независимая проверка SHA-256 и размеров всех **69** элементов `FILE-MANIFEST.csv` дала 69 совпадений. Все обсуждаемые 26100 `ApiSetSchema.dll` — PE32+ x64 (`Machine=0x8664`), `.apiset` file `0x2000`, RVA `0x3000`, RawSize `0x2A000`. `winload.efi` исследован ровно в версии 26100.9444 с SHA-256 `1c1a978448b8e0fc2284c13d6e55fde8bf9827dca432cdc8a9be66c2e729adda`; адреса кода ниже — RVA этого файла. Названия внутренних функций согласованы с приложенным PDB-отчётом `reference/research/WINLOAD-26100-9444-CALLGRAPH.md`; его оговорка о несовпадении PDB Info age и RSDS age сохраняется. Инструкции повторно сняты из самого PE.

| Файл | SHA-256 | `.apiset.VirtualSize` | namespace Size | Trace Host |
|---|---|---:|---:|---|
| оригинал 26100.9278 | `391a2818fdef14f4a9b6e6ce2a7e7f01d04f943de9ef543ec683927cfe480889` | `0x29C60` | `0x29C60` | пусто |
| A-LegacySize | `67b15197c5edc07fe5a2653461a1740d17ac6d6dadbbea2971c1d409781132a3` | `0x29C84` | `0x29C84` | `WcosTraceSmoke.sys` |
| B-DualSize | `ba382268c00918b4a99b2efe3577782673aa6be58e7b567a776e43fca21583f9` | `0x29C84` | `0x29C84` | `WcosTraceSmoke.sys` в v6 записи |
| PhaseBinding | `ad587f8b9810f25b74d4524222f8e60b7af3dc6d6c1b41f931567ba5542f227c` | `0x29CA0` | `0x29CA0` | Trace пусто; State Separation: `PhaseBindingProbe-TESTSIGNED.sys` в v6 записи |

Во всех четырёх 26100 файлах `Version=6`, `Flags=0`, `Count=984`, `EntryOffset=0x98`, `HashOffset=0xAA30`, `HashFactor=31` по `+0x18`, 984 корректных отсортированных hash entries и 990 проверенных value entries. `+0x1C=0xFF080007` — начало встроенного заголовка 7, а не `HashFactor`. В оригинале и A `+0x20=0x29C60`, в B `+0x20=0x29C84`. A и B различаются ровно двумя физическими байтами: file `0x130` (PE checksum: `90→B4`) и file `0x2020` (`60→84`). Сравнение всех изменённых байтов с оригиналом дано в [BINARY-COMPARISON.csv](BINARY-COMPARISON.csv).

Trace — v6 entry index 712, namespace `+0x4358`, `Flags=0`, `ValueOffset=0x94F0`, `ValueCount=1`. State Separation — index 710, `+0x4328`, `Flags=1`, `ValueOffset=0x94C8`, `ValueCount=1`. В B Trace `HostOffset=0x29C60`, `HostLength=0x24`; byte span `0x29C60..0x29C84` не выходит за v6 Size и `.apiset.VirtualSize`.

## Код загрузчика

- `OslLoadApiSetSchema` `0x23160–0x231A9` выбирает имя базового образа и вызывает `OslpLoadApiSetSchemaImage`. Последняя возвращает mapped `.apiset` адрес из PE section `VirtualAddress` и section `VirtualSize` (`0x23478–0x234A6`). По `0x231D3–0x231EA` выделяется буфер и копируется именно это число байтов; исходный PE image затем выгружается (`0x231F3`). Глобальные pointer/size публикуются по `0x231F8/0x231FF`, loader block `+0xAA0/+0xAA8` — по `0x2320D/0x2321B`.
- Гейт композиции (`0x23222–0x23247`) для wrapper 6 читает бит 0 **встроенных** flags по `+0x1E`, а не `Namespace.Flags` или Trace Entry.Flags. У приложенного B `+0x1E=0x08`, бит 0 равен 0.
- `ApiSetComposeSchema` `0x27958B–0x2795AD` распознаёт embedded 7; `0x279C34` вызывает V7-компоновщик `0x27AA24`. Он валидирует встроенный заголовок (`0x27AAA3 → 0x27A1B0`), пересчитывает размер (`0x27AB24 → 0x27BEFC`), выделяет новый буфер и публикует его (`0x27AB2E`, `0x27ABF0–0x27AC03`). V7 валидатор проверяет `Size` по embedded `+4` (`0x27A217–0x27A240`) и конец объявленных блоков (`0x27A345–0x27A365`).
- После registry extensions `OslLoadApiSetSchema` вызывает динамические extensions по `0x2335F`. Единственная ненулевая запись текущей compile-time таблицы (`0x2B2DB0`) задаёт Trace contract, `dtrace.sys`, predicate `0x22F48`; fallback по `0x2BABF8` — пустая строка. Код `0x22FD4–0x23089` создаёт schema extension и вызывает композицию в обеих ветках predicate. Вывод о результате predicate в конкретной загрузке остаётся косвенным.
- `ApiSetResolveToHost` `0x279181–0x27919F` для embedded 7 с `+0x1E & 4 == 0` вызывает resolver `0x27A60C`; код не идёт по обычному legacy v6 выбору value `0x279225–0x279271`. В V7 resolver `0x27A8E0–0x27A902` формирует default output с длиной из компактной записи, `0x27A907–0x27A9F6` проверяет importer-specific путь, а `0x27A9F9–0x27AA11` записывает marker и `STATUS_SUCCESS`. Нулевая длина допустима.

## Непосредственное наблюдение WinDbg

Источник — `03-WINDBG-RAW-TRANSCRIPT-2026-11-09.md`, шаги 2–6 **одной** Trace-загрузки. Предупреждение называет `\Windows\system32\WCOS-TraceSmoke\ApiSetSchema.dll` и разрешение загрузки при boot debugger; это не SHA-256 гостевой копии. После resolver `eax=0`, marker `[rbp+38h]=1`, `UNICODE_STRING` по `[rbp-20h]` имеет обе длины 0. Итоговый указатель по `winload+0x354F00` равен `0xfffff802344ccd20`; header Size `0x29C74`, Count 984; Trace legacy entry `+0x4358` ведёт к value `+0x94F0`, HostOffset `0x1F304`, HostLength 0. `schema+0x1F304` указывает на бинарные данные; поиск UTF-16 `WcosTraceSmoke.sys` во всём итоговом namespace не нашёл совпадений. Дамп `db schema+0x29C60 L40` выходит за пределы Size после первых `0x14` байт; оставшиеся байты не следует считать частью схемы.

Не зафиксированы хеш файла **после копирования внутри гостя**, буфер до первого compose, промежуточный namespace после registry extensions и результат Trace predicate. Старые PhaseBinding наблюдения относятся к разным boots.
