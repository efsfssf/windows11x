# Embedded 7: проверенная часть формата и разрешение двух контрактов

Объект: оригинальная `ApiSetSchema.dll` 26100.9278, SHA-256 `391a2818fdef14f4a9b6e6ce2a7e7f01d04f943de9ef543ec683927cfe480889`. Код потребителя: `winload.efi` 26100.9444, SHA-256 `1c1a978448b8e0fc2284c13d6e55fde8bf9827dca432cdc8a9be66c2e729adda`. Все code RVA ниже относятся к этому winload.

Спецификация ограничена проверенными чтениями/записями и фактическим профилем файла. Имена `family`, `version`, `alias`, `host` — рабочие названия по поведению кода, не восстановленные private C type names. В частности, schema-namespace с embedded marker 7 и принимаемый composer формат **extension с первым byte=7** нельзя считать одной и той же структурой.

## Базы, длины и внешняя обёртка

`N` — начало `.apiset`: file `0x2000`, RVA DLL `0x3000`. `E=N+0x1C`. Любое смещение вида `N+x` в этой записке — namespace-relative. Диапазоны полуоткрытые `[start,end)`.

Обращения к compact blocks имеют форму:

```text
address = E + encodedOffset - WORD[E+0x12] + index*stride
```

В данном файле `WORD[E+0x12]=0x1C`, поэтому encoded offsets численно равны N-relative offsets. Это проверяется по `0x27A6BC..0x27A6F0`, `0x27A71D..0x27A732`, `0x27B845..0x27B866`; добавлять `0x1C` к ним второй раз ошибочно. Строковые ссылки Host/importer имеют другую форму: это смещения **внутри pool**, которые добавляются к `DWORD[E+0x24]`.

Внешний V6 header: Version=6 (`N+0`), Size=`0x29C60` (`+4`), Flags=0 (`+8`), Count=984 (`+0xC`), EntryOffset=`0x98` (`+0x10`), HashOffset=`0xAA30` (`+0x14`), HashFactor=31 (`+0x18`). `N+0x1C` — уже embedded header; его DWORD `0xFF080007` не является HashFactor.

Legacy entry — 24 байта, legacy value — 20 байт. Compact entry ниже — 12 байт. Их поля и flags нельзя смешивать.

## Проверенные поля embedded header

| Offset от E | Ширина | Значение | Проверенное применение / предел знания |
|---|---:|---:|---|
| `+0` | BYTE | 7 | Выбор V7 пути |
| `+1` | BYTE | 0 | Значение прочитано; смысл не присвоен |
| `+2` | BYTE | `0x08` | Namespace flags: bit0 запрещает composition; bit2 выбирает legacy resolver для wrapper; bit3/bit4 участвуют в hash helper |
| `+3` | BYTE | `0xFF` | Верхняя граница для Host record byte `+0xA`, сравнение `0x27A8D0..0x27A8DA` |
| `+4` | DWORD | `0x29C60` | Объявленная Size, сравнивается с доступным размером `0x27A217..0x27A227`; это предел в координатах данного wrapper, не размер `E..E+Size` |
| `+8`, `+0xC` | DWORD каждый | 0, `0xFFFFFFFF` | Raw значения; семантика здесь не установлена |
| `+0x10` | WORD | `0x74` | Raw word; не выдаётся за доказанную длину всего физического header |
| `+0x12` | WORD | `0x1C` | Вычитаемый bias базы |
| `+0x14`, `+0x16` | WORD каждый | `0x90`, 8 | Validator проверяет сумму = `DWORD[+0x18]` (`0x27A27C..0x27A28F`) |
| `+0x18` | DWORD | `0x98` | Начало сохраняемого legacy региона |
| `+0x1C` | DWORD | `0xC858` | Длина сохраняемого legacy региона; `+0x18 + +0x1C = +0x24` |
| `+0x20`, `+0x22` | WORD каждый | 984, 990 | Фактические counts legacy entries/values; для адресации resolver используются descriptors ниже |
| `+0x24` | DWORD | `0xC8F0` | Pool offset |
| `+0x28` | DWORD | `0x12A14` | Pool length в байтах |
| `+0x2C`, `+0x38`, `+0x44`, `+0x50`, `+0x5C` | 12 байт каждый | См. descriptors | Пять descriptors compact blocks |
| `+0x68` | DWORD | `0x29C00` | Importer-chain block offset |
| `+0x6C` | DWORD | `0x60` | Его длина в байтах |
| `+0x70` | WORD | 6 | Число importer nodes |
| `+0x72` | WORD | 16 | Stride importer node; count*stride=length |
| `+0x74..+0x7B` | 8 байт | нули | Физические байты до legacy EntryOffset. Назначение не установлено |

Физическая область между N и первой legacy entry имеет размер `0x98`; embedded часть занимает `0x7C` байт. Значение word `E+0x10=0x74` и этот физический размер намеренно не отождествляются.

Descriptor, проверенный `0x27B768..0x27B948`:

| Offset в descriptor | Поле |
|---|---|
| `+0 DWORD` | encoded block offset |
| `+4 WORD` | record count |
| `+6 WORD` | во всех пяти descriptors 0; назначение не установлено |
| `+8 BYTE` | record stride |
| `+9 BYTE` | hash-control flags; bit1 выбирает укороченную форму имени для первого hash, bit2 переводит вычисленные hashes к low BYTE |
| `+0xA BYTE` | multiplier первого hash, здесь 31 |
| `+0xB BYTE` | multiplier второго hash, здесь 37 |

## Все границы исходного namespace

| Блок | N-relative диапазон | File диапазон | RVA DLL начала | Количество / stride |
|---|---|---|---:|---|
| Wrapper + embedded area | `[0,0x98)` | `[0x2000,0x2098)` | `0x3000` | — |
| Legacy entries | `[0x98,0x5CD8)` | `[0x2098,0x7CD8)` | `0x3098` | 984 / 24 |
| Legacy values | `[0x5CD8,0xAA30)` | `[0x7CD8,0xCA30)` | `0x8CD8` | 990 / 20 |
| Legacy hash index | `[0xAA30,0xC8F0)` | `[0xCA30,0xE8F0)` | `0xDA30` | 984 / 8 |
| UTF-16 pool | `[0xC8F0,0x1F304)` | `[0xE8F0,0x21304)` | `0xF8F0` | `0x12A14` байт |
| Family records | `[0x1F304,0x21CB0)` | `[0x21304,0x23CB0)` | `0x22304` | 889 / 12 |
| Family hash index | `[0x21CB0,0x23878)` | `[0x23CB0,0x25878)` | `0x24CB0` | 889 / 8 |
| Alias records | `[0x23878,0x238A8)` | `[0x25878,0x258A8)` | `0x26878` | 4 / 12 |
| Alias hash index | `[0x238A8,0x238C8)` | `[0x258A8,0x258C8)` | `0x268A8` | 4 / 8 |
| Version records | `[0x238C8,0x266E8)` | `[0x258C8,0x286E8)` | `0x268C8` | 984 / 12 |
| Version hash index | `[0x266E8,0x285A8)` | `[0x286E8,0x2A5A8)` | `0x296E8` | 984 / 8 |
| Auxiliary block | `[0x285A8,0x285A8)` | пуст | `0x2B5A8` | 0 / 4, смысл не восстановлен |
| Host records | `[0x285A8,0x29310)` | `[0x2A5A8,0x2B310)` | `0x2B5A8` | 286 / 12 |
| Host hash index | `[0x29310,0x29C00)` | `[0x2B310,0x2BC00)` | `0x2C310` | 286 / 8 |
| Importer nodes | `[0x29C00,0x29C60)` | `[0x2BC00,0x2BC60)` | `0x2CC00` | 6 / 16 |

Каждый hash index следует после **всех** records соответствующего блока: `indexStart=blockStart+count*stride`. Индексная запись — 8 байт: DWORD первого hash, WORD record index, ещё WORD (в оригинале 0). Lookup читает index как WORD; insertion пишет DWORD по `+4` (`0x27CECD`). Декодер проверяет сортировку первого hash и перестановку record indices для всех пяти таблиц.

Descriptors: family flags=2, остальные flags=0; все hash factors `[31,37]`. Auxiliary stride=4; остальные stride=12. Для непустых таблиц итоговая длина = `count*(12+8)`; auxiliary в этом файле пуст и его payload-семантика не выводится из одного stride.

В B-DualSize Size=`0x29C84`, но все перечисленные границы прежние. Append `[N+0x29C60,N+0x29C84)` лежит за compact blocks. Дополнительная длина header не создаёт compact host record автоматически.

## Поля records, нужные для этих контрактов

Family record (descriptor `E+0x2C`):

| Offset | Проверенная роль |
|---|---|
| `+0 DWORD` | Второй hash; сравнение с вычисленным значением |
| `+4 WORD` | Default host table index, если `flags & 2` |
| `+6 WORD` | Head importer chain |
| `+8 WORD` | Число importer nodes для family |
| `+0xA BYTE` | Bit1 (`2`) — mapping/Host присутствует. При 0 resolver выходит до host table. У целей raw flags=`0x10`; смысл bit4 здесь не установлен |
| `+0xB BYTE` | 0; семантика не установлена |

Version record (descriptor `E+0x44`):

| Offset | Проверенная роль |
|---|---|
| `+0 DWORD` | Второй hash versioned-имени без последнего revision |
| `+4 WORD` | Family index |
| `+6 WORD` | У обеих целей 0; семантика не установлена |
| `+8 BYTE` | Major byte после `-l1-`; заполнение `0x27C35E..0x27C366 → 0x27BD44` |
| `+9 BYTE` | Revision byte; при `flags & 4` используется как верхний предел запрошенной revision |
| `+0xA BYTE` | Bit0 SEALED: используется composer. Bit2 (`4`): resolver сравнивает requested revision с byte `+9` (`0x27A709..0x27A717`) |
| `+0xB BYTE` | 0; семантика не установлена |

Host record (descriptor `E+0x5C`): DWORD второго hash по `+0`, DWORD pool-relative string offset по `+4`, WORD длины в **UTF-16 code units** по `+8`, BYTE уровня/предела по `+0xA`, BYTE по `+0xB` не интерпретирован. Resolver умножает длину на 2 для `UNICODE_STRING` (`0x27A8F6..0x27A902`). У 286 исходных записей длина ненулевая; пустого host record и hash=0 в host index нет.

Importer node: WORD next index по `+0`; WORD `+2` не интерпретирован; DWORD pool-relative importer offset по `+4`; DWORD host offset по `+8`; WORD importer chars по `+0xC`; WORD host chars по `+0xE`. Terminal index=`0xFFFF`. Все шесть исходных chains состоят из одного node. Чтения/переходы: `0x27A943..0x27A9F6`.

У alias records resolver использует WORD family index `+4`, BYTE gates `+6`, DWORD selector `+8` (`0x27A7CA..0x27A7E7`). Полная семантика этих gates, auxiliary block и selector-функций в спецификацию двух целевых контрактов не включена.

## Воспроизводимое вычисление и поиск имени

`ApiSetResolveToHost` (RVA `0x279130`) для данного wrapper выполняет проверки на `0x279181..0x27919F`: Version6, EntryOffset>8, byte `N+0x1C=7`, `(byte[N+0x1E]&4)==0`. Затем ветвь `0x279157..0x279177` вызывает **V7 resolver `0x27A60C`**. Ему передаются E, buffer имени и число WCHAR; importer, marker и Host — отдельными stack arguments. Неиспользованному здесь четвёртому аргументу название не присваивается.

Parser `0x27B954` классифицирует оба имени как type=3 (versioned ordinary contract). Для State длина=38, длина без `-0`=36, укороченная без `-1-0`=34; для Trace 28/26/24 соответственно. При schema.flags=8 hash helper `0x27B680` исключает позиции `[4,6]` (`ms-`) и `-l1` (State `[31,33]`, Trace `[21,23]`), приводит ASCII A–Z к lower case и считает:

```text
h = 0
for each remaining ASCII character c:
    h = (h * factor + lowercase(c)) modulo 2^32
```

Family hash pair использует короткий текст без `-1-0`; version pair — текст без `-0`. Factors 31 и 37 берутся из своего descriptor, а не из legacy header. Для целей получаются:

| Контракт / таблица | Hash text | Hash31 | Hash37 |
|---|---|---:|---:|
| State family | `ext-win-ntos-stateseparation` | `0x340E2871` | `0xA0C11B91` |
| State version | `ext-win-ntos-stateseparation-1` | `0x6925D5D5` | `0xA8AC711B` |
| Trace family | `ext-win-ntos-trace` | `0xF3395B9F` | `0x54020205` |
| Trace version | `ext-win-ntos-trace-1` | `0x0A50F583` | `0x3EBCD36F` |

`0x27B768`: вычисляет оба hash; binary search по первому (`0x27B852..0x27B882`), отступает к началу группы равных hash (`0x27B8B0..0x27B8CE`), проверяет второй hash у records (`0x27B905..0x27B927`). Результат принимается, только если совпадение второго hash ровно одно; иначе `AX=0xFFFF`. Строкового сравнения полного имени на этом пути нет.

## Конкретные записи и их источники

| Объект | N offset | File offset | RVA DLL | Raw bytes |
|---|---:|---:|---:|---|
| State family 647 | `0x21158` | `0x23158` | `0x24158` | `91 1b c1 a0 ff ff ff ff 00 00 10 00` |
| State family index slot172 | `0x22210` | `0x24210` | `0x25210` | `71 28 0e 34 87 02 00 00` |
| State version 710 | `0x25A10` | `0x27A10` | `0x28A10` | `1b 71 ac a8 87 02 00 00 01 00 01 00` |
| State version index slot396 | `0x27348` | `0x29348` | `0x2A348` | `d5 d5 25 69 c6 02 00 00` |
| Trace family 649 | `0x21170` | `0x23170` | `0x24170` | `05 02 02 54 ff ff ff ff 00 00 10 00` |
| Trace family index slot847 | `0x23728` | `0x25728` | `0x26728` | `9f 5b 39 f3 89 02 00 00` |
| Trace version 712 | `0x25A28` | `0x27A28` | `0x28A28` | `6f d3 bc 3e 89 02 00 00 01 00 00 00` |
| Trace version index slot36 | `0x26808` | `0x28808` | `0x29808` | `83 f5 50 0a c8 02 00 00` |

У каждой строки первая и вторая hash-проверки дают единственное совпадение. Version family links `0x287` и `0x289` попадают внутрь family count=889. Default `0xFFFF` не разыменовывается: family.flags&2=0. Head `0xFFFF` и count=0 не задают importer node. Поэтому default Host отсутствует, importer-specific values отсутствуют; указать offset несуществующей Host-строки было бы ошибкой.

Legacy name State: `N+0x19EB4`, file `0x1BEB4`, RVA DLL `0x1CEB4`, 76 байт. Trace: `N+0x19F3E`, file `0x1BF3E`, RVA DLL `0x1CF3E`, 56 байт. Компактные records сами полную строку имени не хранят; связь доказана lookup двух hashes, ссылкой version→family и независимой legacy сверкой.

## Default и importer-specific выбор после lookup

Обычный type3 resolver сначала ищет version (`0x27A693..0x27A703`), проверяет cap bit2, затем берёт family index (`0x27A71D..0x27A742`). На `0x27A8AC` family.flags сдвигается вправо на 1; `0x27A8B1` проверяет bit0 результата. Marker становится 1 даже при отсутствии mapping. При установленном host-present:

1. `0x27A8BA..0x27A8DA`: host index→Host record; byte Host+0xA не должен превышать E+3. Иначе остаётся первоначально обнулённый Host.
2. `0x27A8E0..0x27A902`: default Buffer=`E-bias+poolOffset+host.relative`; Length=MaximumLength=chars*2.
3. `0x27A907..0x27A918`: importer NULL или family importer count=0 оставляет default.
4. `0x27A943..0x27A9A6`: проверка node index < WORD[E+0x70], сравнение importer с node.importer. Сравнение helper `0x190408`, последний аргумент 1: ASCII comparison без учёта регистра. При несовпадении — node.next, максимум family.count итераций.
5. `0x27A9AA..0x27A9F6`: первое совпадение заменяет Buffer и Length на node.host. Нет совпадения / индекс за границей сохраняет default. `0x27AA0E..0x27AA11` пишет marker и возвращает STATUS_SUCCESS.

Непустой тест importer-specific на оригинале: `ext-ms-win-kernel32-errorhandling-l1-1-0`, family `N+0x20DF8`, default index12 → `kernel32.dll`; node5 по `N+0x29C50`: importer=`kernel32.dll`, host=`faultrep.dll`, next=`0xFFFF`. С `--importer kernel32.dll` декодер возвращает `faultrep.dll`; с `ntoskrnl.exe` — default `kernel32.dll`. Это проверяет отдельно реальный importer node, а не только два пустых целевых случая. [v7-positive-controls.json](evidence/v7-positive-controls.json).

## Проверки декодера и пределы

[v7_decode.py](v7_decode.py) вычисляет индексы, а не подставляет 647/649/710/712 как offsets поиска. Эти числа используются лишь как ожидаемые результаты `--self-test`. До чтения проверяются PE raw sections, namespace/section sizes, bias, границы pool и таблиц, count/stride, сортировка hash indices, индексы records, ссылки family/Host и importer chains. Для строк дополнительно проверяется `relative + chars*2 <= poolLength`. Ошибки дают exit1; вход всегда остаётся неизменным.

Поддержан только проверенный профиль namespace flags=8 и ordinary ASCII `*-l1-major-revision`, без `.dll` suffix в командной строке. Для обязательной независимой сверки требуется точное присутствие имени в legacy companion; это ограничение декодера, а не правило Windows resolver для всех запрошенных revision. Проверена полная цепочка для двух целей и положительных контролей; cap-bit2 queries намеренно отклоняются. Это не реализация всех вариантов parser (`~` aliases, другие уровни, selectors, compressed hashes), не полный clone validator winload и не composer. Неизвестные поля сохранены raw и перечислены в [UNRESOLVED.md](UNRESOLVED.md).
