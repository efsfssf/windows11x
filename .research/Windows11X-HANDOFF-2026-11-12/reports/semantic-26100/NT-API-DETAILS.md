# Сравнение 26 NT API: семантика и ограничения

Полный перечень с F IAT/callsites, exports обеих версий, реальными implementation RVA, service indices, ownership/status/locking prerequisites — [NT-API-COMPATIBILITY.csv](NT-API-COMPATIBILITY.csv). В таблице ровно 26 строк, один импорт на строку.

`SAME` означает эквивалентность **указанного ограниченного контракта** по рассмотренному телу. `CHANGED` использовался бы для доказанного изменения наблюдаемого контракта; в этой работе такой API не установлен. `INCONCLUSIVE` не означает несовместимость. Изменения реализации отражены отдельной колонкой и ниже.

## Как исключено сравнение одних Zw stubs

F импортирует 18 Zw routines и 8 Rtl/Ex/CRT routines. Для всех 26 подтверждены прямые exports обоих kernels и фактическое использование соответствующего F IAT. Для 18 Zw отдельно:

1. Прочитан `mov eax, service_index` в Zw export.
2. По соответствующему on-disk `KiServiceTable` прочитан реальный Nt RVA. Для данных PE entries — прямые DWORD RVA.
3. Для всех **18×2** targets получено совпадение с Nt public symbol; сохранены bodies и direct-call references, включая значимые cold branches.

Таблицы: K22000 RVA `0xCA690`, K26100 `0xDADC0`. Пример ZwOpenKey: K22000 export `0x40D660`, index `0x12`, NtOpenKey `0x783FE0`; K26100 export `0x6AA050`, index `0x12`, NtOpenKey `0xA4DD70`. В частности, это не старый kernel26100.1742 под видом baseline22000.

Данные: [api-implementation-map.json](analysis/evidence/api-implementation-map.json), `{kf,kn}-nt-implementations.asm`. Связи indirect callbacks и весь CM/I/O graph этим не объявляются восстановленными. Service numbers из старого F не зашиты в протокол вызова: используются именованные imports.

## Четыре SAME в оговорённой области

| API | K22000 | K26100 | Проверенное поведение |
|---|---:|---:|---|
| RtlInitUnicodeString | `0x2DEF50` + cold `0x49D540` | `0x431490` | Обнуление первых 8 байт descriptor; Buffer+8=source; NULL source без scan; WCHAR длина в bytes; при длине >=0xFFFE clamp к0xFFFC; MaximumLength=Length+2 |
| RtlAppendUnicodeToString | `0x2B1D30`, helper `0x2B1DE0` | `0x415E40` | NULL source→success; предел0x7FFE WCHAR; проверка sum bytes<=MaximumLength; copy/update Length; NUL только при свободном WCHAR; overflow→0xC0000023 |
| RtlGetControlSecurityDescriptor | `0x7416E0` | `0xA699B0` | Revision output DWORD; только Revision1 допускает чтение Control WORD+2; иначе0xC0000058; полное короткое тело, 28 байт, одинаково |
| RtlSetControlSecurityDescriptor | `0x7E4310` | `0x78D440` | Set bits⊆mask; запрещённый mask `0xFFFFC03F`; Control=(Control&~mask)|set; иначе0xC000000D; 61 байт одинаковы |

Под «валидными» здесь понимаются доступные caller-owned buffers/descriptors и NUL-terminated source без конкурирующего изменения. Для append не заявлена произвольная overlap/aliasing семантика. Обработка access violations, произвольной порчи памяти и instrumented build variants не сравнивается этим выводом.

RtlInitUnicodeString22000 использует собственный scan и вынесенную clamp ветку; 26100 вызывает wcslen и использует cmovae. Append22000 вызывает RtlInitUnicodeStringEx и переводит его failure в BUFFER_TOO_SMALL; 26100 делает эквивалентную проверку длины непосредственно. Поэтому разные инструкции сами по себе здесь не означают разный контракт. SD getter/setter дополнительно сверены побайтно: [primitive-comparison.json](analysis/evidence/primitive-comparison.json).

## Pool, UUID и форматирование: частично согласованные протоколы

F использует ExAllocatePool2 с Flags=`0x100` и Tag=`0x70655353`; allocation sites включают `0x1267`, `0x147D`, `0x15A2`, `0x8208`, `0x8754`, `0x8BC8`, `0x9486`. Выделяются paged buffers, включая0x1000 для Phase1 и0x10000 для file copy. Проверки NULL и освобождения видны в F. Registry scratch — общий `F+0x3068` и length `+0x3060`; file-security scratch — отдельные `+0x3070/+0x3078`.

K22000 ExAllocatePool2 `0xA6A3C0`: tag check `0xA6A3E2`, flags validation `0xA6A3ED..420`, type mask0x1C0 и отдельные type branches. K26100 `0xB800F0`: one-hot type check `0xB80107..15`, mask/bit checks `0xB8012B..3F`, nonzero tag `0xB80141`. Значения F проходят оба frontends. В обеих версиях это не лицензия выполнять paged allocation при произвольном IRQL.

Реализация allocator переработана: K22000 содержит свой большой routine и node/quota branches; K26100 имеет compact frontend, `ExpAllocatePoolWithTagFromNode`, `VfHandlePoolAlloc` и quota path. ExFreePoolWithTag22000 `0xA6A120` — wrapper ExFreeHeapPool; 26100 `0xB80870` — другое большое тело с собственными checks. Полная эквивалентность zeroing/quota/Verifier/low-memory/invalid-free не доказана. **Обе pool API: INCONCLUSIVE**, при подтверждённом согласовании конкретных входных flags/tag.

ExUuidCreate: K22000 `0x74D920`, K26100 `0x89E910`. Есть cache/atomic fast path и slow refresh/lock logic. Оба формируют UUID и могут вернуть `0x40020056` (local-only); F `0x16CF..0x16D8` этот статус принимает перед дальнейшим форматированием. Но seed, sequence refresh, runtime locking и все error paths не замкнуты — **INCONCLUSIVE**. В K22000 slow path вынесен; отсутствие call в первом `.pdata` range не означает отсутствия slow path.

swprintf_s: K22000 `0x3DB380 → 0x3DB3B0`, K26100 `0x5045F0 → 0x504620` (`vswprintf_s`). F на `0x174A` форматирует UUID leaf name. Совпадение wrappers и x64 varargs согласовано, но полный CRT формат/error/invalid-parameter graph не эквивалентирован — **INCONCLUSIVE**.

## Registry: реальные frontend differences, общий используемый протокол

| API group | Прямое наблюдение в обоих kernels | Незакрытая семантика |
|---|---|---|
| ZwOpenKey | 22000 NtOpenKey `0x783FE0` вызывает CmOpenKey `0x784024`; 26100 NtOpenKey `0xA4DD70` вызывает NtOpenKeyEx на `0xA4DD77` с нулевым дополнительным параметром | Весь open/namespace/security/callback graph и CM policy |
| ZwCreateKey | 22000 `0x7072F0 → 0x70733D` CmCreateKey; 26100 `0xA7DF60`, `0xA7DFEA` KeExpandKernelStackAndCallout | Реальное создание VOLATILE/CREATE_LINK на нужном backing hive, callback order, stack/capture failures |
| ZwSetValueKey | Nt bodies `0x6E4970 / 0x88CF10`: handle/reference, pre/post callback и CM operation paths | REG_LINK/ACL/hive/write enforcement, capture and persistence |
| ZwQueryValueKey | `0x6D9100 / 0x84A840`: capture/reference/CM paths | Все classes/types/lengths, missing key/value, virtualization/cache |
| ZwDeleteKey | `0x7058F0 / 0x891990`: rundown, references, callbacks, CmDeleteKey | Concurrent subkeys/links, delete statuses, read-only stores |
| ZwEnumerateKey | `0x6DFB60 / 0x849DB0`: callbacks и CmEnumerateKey | Concurrent mutation, buffer lengths и полный backend |
| ZwEnumerateValueKey | `0x6E2380 / 0x84B990`: callbacks и CmEnumerateValueKey | Full-information offsets и все fallback paths |
| ZwRenameKey | `0x90B750 / 0x7DE240`: subject capture, callbacks, registry process attach, CmRenameKey, cleanup | Virtualization, collisions, atomicity относительно последующих операций F |

Точные RVA для каждого API автоматически сохранены в CSV; body addresses здесь относятся к соответствующему Nt, не Zw stub. Для всех восьми registry functions итог **INCONCLUSIVE** по полной семантике.

F использует query class2 (`KEY_VALUE_PARTIAL_INFORMATION`: Type+4, DataLength+8, Data+0xC), key enumerate class1, value enumerate class1, а в `SetupRedirectionSymLink` `0x17C8` — create options3 и value type6. Это фактический протокол F. В PDB текущего набора не найдены полные TPI definitions трёх KEY_* information structs; их равенство во всех ветвях нельзя выдавать за новый type proof. Binary consumers и ветви CM изучены, но полного buffer-layout audit всех classes не выполнено.

Исходные свойства F, которые нельзя назвать новым NT breaking change: query errors могут пропускать правила; rename/copy/link не образуют общую транзакцию; некоторые DeleteKey/Close/Set/Flush results игнорируются; не все конфигурационные type/length проверяются. Они существуют уже в reference22000.

## File I/O, security и handle cleanup

Сопоставлены настоящие Nt bodies всех file/security imports: NtCreateFile, NtReadFile, NtWriteFile, NtQuery/SetInformationFile, NtFlushBuffersFile, NtDeleteFile, NtQuery/SetSecurityObject, NtClose. Имеются IopCreateFile/IRP/file-object/reference/security-method paths; один одинаковый external API не делает их внутренний graph неизменным.

Проверен протокол F:

- NativeCreateFile `0x8E28`: OBJECT_ATTRIBUTES flags0x240, добавление access0x100080, стековые create arguments.
- NativeCopyFile `0x8B84`: buffer0x10000; read/write используют IO_STATUS_BLOCK.Information; target overwrite disposition и синхронный I/O; EOF обрабатывается отдельно. ZwFlushBuffersFile вызывается на `0x8D6F`, но его return не записывается как итоговый EBX status.
- CopyAttributes `0x8510`: class4 и length0x28 для query/set на `0x85E6/0x860F`.
- Security helpers используют SD Control0x100/0x200, query masks4/0x1F и query-buffer-growth/cleanup.

TPI extracts обоих kernels подтверждают одинаковые `_UNICODE_STRING` (0x10), `_OBJECT_ATTRIBUTES` (0x30), `_IO_STATUS_BLOCK` (0x10) и `_FILE_BASIC_INFORMATION` (0x28) с одинаковыми перечисленными offsets. Например Information+8, FileAttributes+0x20. Extracts: `analysis/evidence/{kf,kn}-_*.json`. Для некоторых других типов PDB содержит только неполные/отсутствующие definitions — пустой JSON не выдаётся за layout proof.

NtClose22000 `0x676120` вызывает ObpCloseHandle; NtClose26100 `0x870F10` разворачивает handle-table/reference/close branches внутри routine. NtFlushBuffersFile в обоих версиях вызывает NtFlushBuffersFileEx с нулевыми дополнительными входами. Это конкретные implementation observations; для обеих functions полная semantic equivalence остаётся **INCONCLUSIVE**.

Конечные file/security результаты зависят от FS, minifilters, ACL/privileges, object methods, synchronous completion, writable backend и runtime контекста. В пакете нет полного современного enforcement/storage stack. Подменять эти условия одним 26/26 binding нельзя.
