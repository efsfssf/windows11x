# Структуры и размеры `.apiset`

Все смещения namespace отсчитываются от начала `.apiset` (file `0x2000` у 26100). Значения `HostLength` и `NameLength` — **байты UTF-16LE**. Названия дополнительных полей представления 7 ниже описывают только проверенное употребление в коде, без притворной полной спецификации формата.

## Legacy v6 wrapper 26100

| Смещение | Поле / проверенное значение оригинала | B-DualSize |
|---:|---|---:|
| `+0x00` | Version `6` | `6` |
| `+0x04` | Size `0x29C60` | `0x29C84` |
| `+0x08` | Namespace.Flags `0` | `0` |
| `+0x0C` | Count `984` | `984` |
| `+0x10` | EntryOffset `0x98` | `0x98` |
| `+0x14` | HashOffset `0xAA30` | `0xAA30` |
| `+0x18` | HashFactor `0x1F` | `0x1F` |
| `+0x1C` | embedded 7 marker/flags bytes `07 00 08 FF` | то же |
| `+0x20` | embedded 7 Size `0x29C60` | `0x29C84` |

Legacy namespace entry имеет 24 байта: Flags, NameOffset, NameLength, HashedLength, ValueOffset, ValueCount. Trace entry по `+0x4358` (`file 0x6358`) даёт `Flags=0, NameOffset=0x19F3E, NameLength=0x38, HashedLength=0x34, ValueOffset=0x94F0, ValueCount=1`. State Separation entry по `+0x4328` (`file 0x6328`) даёт `Flags=1, ValueOffset=0x94C8, ValueCount=1`.

Legacy value entry имеет 20 байт: Flags, NameOffset, NameLength, HostOffset, HostLength. B Trace value начинается по `+0x94F0` (`file 0xB4F0`): `0,0,0,0x29C60,0x24`; host bytes — `file 0x2BC60..0x2BC83`. Оригинал — `0,0,0,0,0`. Изменения B относительно оригинала: PE checksum file `0x130..0x131`, section VirtualSize file `0x238`, wrapper Size file `0x2004`, embedded Size file `0x2020`, value HostOffset/Length file `0xB4FC..0xB500` и 18 ненулевых UTF-16 символов по чётным file offsets `0x2BC60..0x2BC82` (всего 27 отличающихся байтов). A имеет те же изменения без embedded Size и свой checksum (26 отличающихся байтов). Суммарные **длины** строк — 36 байт, хотя отличающихся от нулевой области физических байтов — 18.

## Встроенное представление 7

Код `0x27A1B0–0x27A1D5` переносит рабочую базу на wrapper `+0x1C`; `0x27A217` читает 32-битную длину по embedded `+4` (внешнее `+0x20`). Сдвиг `word[embedded+0x12]=0x1C` используется при адресации таблиц, например `0x27B845–0x27B866`; поэтому их offsets практически адресуют исходный wrapper и `Size` не нужно трактовать как длину *сверх* смещения `0x1C`.

Проверенная цепочка границ в исходном header: embedded `+0x24=0xC8F0`, `+0x28=0x12A14`; сумма `0x1F304` равна embedded `+0x2C`. Дальнейшие блоки начинаются по `0x23878`, `0x238C8`, `0x285A8`, `0x285A8`, `0x29C00`; последний имеет длину `0x60`, конец — `0x29C60`. Это совпадает с концом исходной схемы. У B длина в embedded header расширена до `0x29C84`, но **все данные объявленных блоков `+0xC8F0..+0x29C60` побайтно прежние**. Legacy Trace value по `+0x94F0` находится до них, новая UTF-16 строка начинается непосредственно после их конца. Простейшие проверки границ встроенного header проходят (`last_end <= Size`), однако это не доказательство полной семантической корректности B.

Код расчёта размера нового V7-композита (`0x27BEFC–0x27C012`) по исходным полям даёт:

```text
0x98 (wrapper/header) + 0x12A14 (embedded +0x28)
+ 0xC858 (embedded +0x1C) + 889*20 + 4*20
+ 984*20 + 286*20 + 0x60
= 0x29C60
```

Указанные counts/strides взяты из embedded header; вычисление **не использует** изменённое B `Size=0x29C84` как готовую длину копии. Ветка учёта ещё одной компактной записи в V6→V7 extension path прибавляет `0x14` (`0x27C5D6`; аналогичные ветви существуют для других групп). Так получается `0x29C74`, ровно наблюдаемый итоговый Size. Конкретная ветка в live boot не трассирована: это объяснение по статическому пути и конечному размеру, а не запись о выполненной инструкции.

`HostOffset=0x1F304` в живой legacy value совпадает численно с началом одного из блоков embedded 7, но при `HostLength=0` не обозначает строку. Содержимое по живому `schema+0x1F304` — бинарная таблица, что видно по `dw` в WinDbg.

## Сравнение с Factory OS 22000

Оригинальный FOS SHA-256 `49696b4557023ea455aa7731a18467b623292a7c2ec837d8437a9c2dcd9cd498`: v6 `Count=945`, `Size=0x1D394`, `EntryOffset=0x1C`, `HashFactor=31`, State Separation entry index 684 `Flags=0`, Host `statesepext.sys`, `HostLength=0x1E`. У FOS `+0x1C=0` — это начало обычного entry массива, **не** тот же embedded header; `+0x20=0x58B4` нельзя именовать «вторым Size». FOS используется только как сравнение, не как совместимый base для 26100.
