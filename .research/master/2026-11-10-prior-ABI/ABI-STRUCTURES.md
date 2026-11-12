# ABI, структуры и последовательность Phase0/1/2

Сокращения: **F** — оригинальный `statesepext.sys` 10.0.22000.1, SHA-256 `167fda36a71c2aad4c146bc66ae4c83e9d525d008d6abfd8eda0e056c9a96658`; **K** — `ntoskrnl.exe` 10.0.26100.9457, SHA-256 `d90c69cfa925d42037ef1cdba3ff5b3f13cc14e16060938073b97e396fdde2f2`. Все адреса — RVA указанного PE, не runtime VA. В F у исследованных секций file offset совпадает с RVA. File offsets K сохранены у каждой инструкции в ASM.

Первичные артефакты: [factory-functions.asm](evidence/factory-functions.asm), [kernel-phase-and-policy.asm](evidence/kernel-phase-and-policy.asm), [kernel-order-and-storage.asm](evidence/kernel-order-and-storage.asm), [structures-and-policy.json](evidence/structures-and-policy.json). Типы ниже восстановлены из инструкций и стандартных layouts x64, а не из private type symbols.

## Внешняя граница

```c
NTSTATUS ExpInitializeStateSeparationPhase0(
    BOOLEAN Enabled, BOOLEAN DevelopmentMode);       // CL, DL; result EAX
NTSTATUS ExpInitializeStateSeparationPhase1(
    BOOLEAN Enabled);                                // CL; result EAX
NTSTATUS ExpInitializeStateSeparationPhase2(
    BOOLEAN Enabled,                                 // CL
    PCUNICODE_STRING SourceRegistryPath,              // RDX
    PCUNICODE_STRING TargetRegistryPath);             // R8; result EAX
```

У BOOLEAN доказано чтение младшего байта. Старшие биты RCX/RDX не являются доказанными параметрами. Объём shadow space, выравнивание stack и сохранение nonvolatile регистров соответствуют x64 Windows. Через эту границу не передаётся восстановленный private kernel context или структура, встроенная в тело ядра.

K импортирует три функции через `ext-ms-win-ntos-stateseparation-l1-1-0.dll`, а не реализует их как собственные три экспорта. Экспорт `RtlIsStateSeparationEnabled` у K, напротив, собственный. Матрица IAT:

| Фаза | Export F / секция | IAT K | Call K | Подготовка аргументов K |
|---|---|---|---|---|
| 0 | `0x8090`, INIT | `0x150378` | `0xC294EE` | `0xC294DE` вызывает getter `0x7DCF68`, сохраняет AL в BL; `0xC294E5` вызывает `0xA7AA90`; `0xC294EA/EC`: CL=AL, DL=BL |
| 1 | `0x8190`, INIT | `0x150368` | `0xC6DC8E` | `0xC6DC84/8B`: проверка DWORD K+`0xEEFB28`, `setne cl` |
| 2 | `0x8360`, INIT | `0x150370` | `0xB0F996` | `0xB0F97E`: тот же Enabled; RDX=descriptor+8, R8=descriptor+0x18, `0xB0F985..993` |

Getter K `0x4A7584` читает DWORD `0xEEFB28`; export `0xA7AA90` вызывает его. Getter `0x7DCF68` отдельно читает DWORD `0xEEFB24`. В control table по K `0xC84D90/0xC84DC0/0xC84DF0` этим адресам соответствуют `StateSeparation\Policy\Enabled`, `DevelopmentMode`, `AllHivesVolatile` (`0xEEFB20`). Это привязка к данным policy, не доказательство фактических значений в конкретном boot.

## Phase0: что делает F и что возвращает

- F `0x80B7..B9`: Enabled=0 → EAX=0, конец без основной работы.
- F `0x80BF..C1`: DevelopmentMode=0 пропускает запись диагностического признака.
- При DevelopmentMode!=0 F `0x8101` открывает policy key; F `0x8147` пытается записать DWORD `DevModeEverEnabled=1`. Ошибка открытия ведёт к продолжению на `0x8163`. Return SetValue не определяет итог: далее вызываются Close и helpers.
- F `0x8163/65`: CL=DevelopmentMode, call helper `0x8644`. Отрицательный результат возвращается без вызова следующего helper (`0x816A..6C`).
- При неотрицательном результате вызывается F `0x9344` (`0x816E`); его EAX становится итоговым NTSTATUS.

Helper `0x8644` читает `KeyRedirectionLinks`, выбирает записи с KernelLink!=0; при DevelopmentMode!=0 дополнительно требует AlwaysEnabled!=0. Query KernelLink/AlwaysEnabled с ошибкой означает skip; обязательные SourceNtPath/TargetNtPath с ошибкой прекращают проход. TreeSync по умолчанию 0 при неуспешном query.

Основной путь одного правила: source open F `0x89DD`; при ошибке ensure-parent + CreateKey (`0x89F4`, `0x8A22`); target open F `0x8A77`; copy/merge F `0x8AAC → 0x1050`; rename source F `0x8ABB → 0x1630`; создание volatile REG_LINK F `0x8AD4 → 0x17C8`. Точные операции сохраняются в полном ASM; любое утверждение о rollback ограничено этим графом: компенсирующего обратного rename/delete созданного target в нём нет.

В supplied runtime-map заново пересчитаны 81 links и две KernelLink записи: AccessibilityLink и ComputerName. Обе направлены в OSBOOT; только ComputerName имеет AlwaysEnabled=1. Это данные предоставленного исторического inventory, не новые чтения реестра.

Helper `0x9344` отдельно читает ComputerName и обновляет ActiveComputerName/Tcpip Hostname/NV Hostname. Initial open/query fatal; отсутствие ActiveComputerName может быть пропущено. Поэтому успех links helper ещё не означает успех всей Phase0.

## Phase1: файловое provisioning

F `0x81B5..B7`: Enabled=0 → 0. При включённом режиме F `0x81E9 → 0x8E28` пробует открыть `\??\O:\Windows\FirstBoot.Complete` (строка F `0x2880`). Любой неотрицательный status этой попытки даёт ранний успех (`0x81EE..F0`). Любой отрицательный запускает дальнейшее provisioning; он не различается здесь на «файл отсутствует» и прочие ошибки.

Далее выделяется локальный paged buffer 0x1000, flags `0x100`, tag `0x70655353` (F `0x81F6..0x8208`). F `0x822D → 0x9068` обеспечивает каталоги под `\OSDataRoot\Windows\System32\Config\` (строка F `0x2210`). По очереди SAM, SECURITY, DEFAULT:

| Hive | Registry input string F | Source file string F | Query target / copy calls F |
|---|---|---|---|
| SAM | `0x2260` | `0x2290` | `0x8263 → 0x8F18`; `0x827D → 0x8B84` |
| SECURITY | `0x22D0` | `0x2310` | `0x82BA → 0x8F18`; `0x82D0 → 0x8B84` |
| DEFAULT | `0x2360` | `0x2390` | `0x8309 → 0x8F18`; `0x831F → 0x8B84` |

Target имена запрашиваются по `...\Control\HiveRedirectionList` (F `0x23E0`, query `0x9005`, class2). Отсутствующий корректный список не заменяется в этом helper готовыми target путями. Copy helper открывает исходный файл, создаёт/перезаписывает target с disposition5 `FILE_OVERWRITE_IF` (F `0x8C77..0x8C9A`), переносит metadata/security и копирует блоками до 0x10000 bytes. Source OBJECT_NAME_NOT_FOUND (`0xC0000034`) специально преобразуется в success на `0x8C2B..31`; прочие ошибки source open возвращаются.

F `0x8D4C`: ReadFile; `0x8D0E`: WriteFile; EOF `0xC0000011` на `0x8D5E..63` → success. F `0x8D6F`: FlushBuffersFile вызывается, но его status не заменяет сохранённый copy status EBX. При отрицательном copy status target после закрытия handles передаётся DeleteFile (`0x8DF9`); прежнее содержимое перезаписанного target этим не восстанавливается.

Это копирование hive-файлов; по import table и восстановленному графу здесь нет вызова Nt/ZwLoadKey. Создание обычных key parents в Phase0 также не является mount hive.

## Phase2: не обязательный третий boot callback

F `0x836C..78` проверяет Enabled, RDX и R8 на ноль. Disabled/NULL → 0. Иначе F `0x837A..83` передаёт source/target descriptors в helper `0x1050` с третьим аргументом NULL. Helper помещает эти указатели непосредственно в `OBJECT_ATTRIBUTES.ObjectName` (F `0x1066`, `0x10BA`); это доказательство PCUNICODE_STRING, не WCHAR* и не неизвестного kernel context.

После copy EAX сохраняется в EBX (F `0x838F`). Registry scratch pointer F+`0x3068` освобождается при ненулевом значении; pointer/capacity F+`0x3068/+0x3060` обнуляются (F `0x839B..0x83AF`). Итог — исходный copy status (`0x83B6`). Полноценного локального lock вокруг этого shared scratch в F не найдено.

K `0x8816A4` вычисляет descriptor offset как index*0x30 (`0x8816DB..DF`). Если handle ещё не cached, выбирает source/target по Enabled (`0x881741..64`) и создаёт key в cold fragment `0xB0F924`. Только `CreateDisposition==1` на K `0xB0F978` вызывает Phase2. Handle публикуется через `lock cmpxchg` **после** Phase2 (`0xB0F9C5`). Эта публикация сама по себе не доказывает сериализацию всех обращений к F scratch.

WNF descriptors K+`0xB4D6F0`, stride0x30:

| Index | Source descriptor / target descriptor K | Проверенные пути |
|---|---|---|
| 0 | `0xB4D6F8 / 0xB4D708` | Оба `\Registry\Machine\System\CurrentControlSet\Control\Notifications` |
| 1 | `0xB4D728 / 0xB4D738` | Source `\Registry\Machine\Software\Microsoft\Windows NT\CurrentVersion\Notifications`; target `\REGISTRY\MACHINE\OSDATA\Notifications` |
| 2 | `0xB4D758 / 0xB4D768` | Оба `\Registry\Machine\Software\Microsoft\Windows NT\CurrentVersion\VolatileNotifications` |

Структура записи: 8 байт kind/padding, UNICODE_STRING source по +8, target по +0x18, DWORD flags по +0x28/+0x2C. Назначение flags шире их прочитанного использования не присваивается.

## Порядок и error policy K

Заново восстановлен общий родитель K `0x705B30`, а не только символические имена фаз:

```text
K+0x705B4E call K+0xC1A728  // общая ранняя инициализация
K+0x705B66 call K+0xC28B48
  K+0xC28B5A call K+0xC28BCC
    K+0xC294EE call [Phase0 IAT]
  ... дальнейшая I/O initialization ...
K+0x705B91 call K+0xC6DC38
  K+0xC6DC8E call [Phase1 IAT]
```

Таким образом, на этом нормальном пути Phase0 предшествует Phase1; ошибка общего I/O пути до Phase1 может привести к bugcheck. Phase2 имеет отдельный demand path WNF: общего безусловного порядка «0 затем 1 затем 2» код не устанавливает. Точное время всех возможных Phase2 calls относительно discard INIT остаётся неизвестным.

| Фаза | При NT_SUCCESS или `0xC00000BB` | Иной отрицательный status |
|---|---|---|
| 0 | Продолжение K `0xC2951C`; сравнение BB по `0xC29506` | K `0xC2950D`: DWORD `0xF8CF50`=0x13; возврат через `0xC29E75`; общий родитель может bugcheck0x69 |
| 1 | Продолжение K `0xC6DCC3`; BB check `0xC6DCA6` | K `0xC6DCBD`: KeBugCheckEx(0x32,0,0,0,0) |
| 2 | Продолжение K `0xB0F9BF`; BB check `0xB0F9A4` | Закрыть key K `0xB0F9B3`; вернуть status через `0x881708` |

Warning statuses с установленным sign bit не равны NT_SUCCESS. Например, `0x80000005` после исчерпания buffer/OOM пути не становится поддерживаемым fallback автоматически.

## Структуры фактически использованного протокола

| Layout x64 | Поля и подтверждающее использование |
|---|---|
| UNICODE_STRING, 0x10 | USHORT Length+0, MaximumLength+2, padding+4, WCHAR* Buffer+8. Caller WNF descriptors и F `0x1050` согласованы; RtlInitUnicodeString K `0x431490` пишет +0/+2/+8 |
| OBJECT_ATTRIBUTES, 0x30 | ULONG Length+0, HANDLE RootDirectory+8, PUNICODE_STRING ObjectName+0x10, ULONG Attributes+0x18, SD*+0x20, SQOS*+0x28. F `0x1083/90`, `0x10C5..D9` и file wrapper `0x8E28` используют эту форму; flags0x240=CASE_INSENSITIVE|KERNEL_HANDLE |
| IO_STATUS_BLOCK, 0x10 | Status/Pointer+0, ULONG_PTR Information+8. Copy loop F `0x8CE3`, `0x8D00..4C` использует Information как число bytes, ограниченное block size0x10000 |
| KEY_NODE_INFORMATION, class1 | LastWriteTime+0, TitleIndex+8, ClassOffset+0xC, ClassLength+0x10, NameLength+0x14, Name+0x18. F `0x12D9..0x12FC` |
| KEY_VALUE_FULL_INFORMATION, class1 | TitleIndex+0, Type+4, DataOffset+8, DataLength+0xC, NameLength+0x10, Name+0x14. F `0x14DF..0x1513` |
| KEY_VALUE_PARTIAL_INFORMATION, class2 | TitleIndex+0, Type+4, DataLength+8, Data+0xC. HiveRedirectionList F `0x8FF8..0x902F`; K own persisted-path code `0xB218AE..0xB21922` also consumes this form |
| FILE_BASIC_INFORMATION, 0x28, class4 | Четыре LARGE_INTEGER timestamps, ULONG FileAttributes+0x20, padding. F `0x85CD..0x860F` передаёт размер0x28 и class4 |
| SECURITY_DESCRIPTOR header | BYTE Revision+0, BYTE Sbz1+1, USHORT Control+2. K `0xA699B0` требует Revision1; setter K `0x78D440` работает с Control+2; F `0x8470..0x84D5` согласован |
| UUID, 0x10 | DWORD, два WORD, восемь BYTE. F `0x16C1..0x174A` формирует UUID string; K ExUuidCreate `0x89E910` пишет поля 0/+4/+6/+8/+0xC |

Private CM hive objects, KTHREAD и loader block **не пересекают Phase ABI**. Это снимает один класс структурных рисков, но не доказывает корректность provisioning, прав доступа и lifetime. Старые helpers находятся частично в INIT; пригодность их вызова после reclaim INIT не доказана.
