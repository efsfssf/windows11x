# VERIFIED — State Separation 22000 ↔ NT 26100.9457

Завершено 2026-11-11; работа начата 2026-11-10. Только офлайн-чтение PE, конфигурационных inventory и пакетов. Бинарники не исполнялись; VM, registry, BCD и component store не изменялись. Извлечение ZIP/CAB в рабочий каталог не является установкой.

**Ответ на главный вопрос:** обнаруженных ABI и native integration механизмов достаточно, чтобы продолжать доказательное исследование настоящей State Separation на 26100. **Фундаментальный разрыв архитектуры или внешнего Phase ABI не установлен.** При этом перенос готового набора22000 на текущую26100 не доказан: текущий Host binding не удовлетворяет задаче, а lifecycle Phase2, ранние hives/storage, полная NT API semantic equivalence и servicing остаются открытыми. Это разрешение продолжать исследование по доказательствам, не заключение о готовой бинарной совместимости.

Результат разделён на [INCOMPATIBLE](INCOMPATIBLE.md), [UNKNOWN](UNKNOWN.md), [NEXT EVIDENCE](NEXT-EVIDENCE.md). Детали: [ABI-STRUCTURES](ABI-STRUCTURES.md), [NT-API-SEMANTICS](NT-API-SEMANTICS.md), [DEPENDENCIES](DEPENDENCIES.md), [матрица](COMPATIBILITY-MATRIX.csv).

## Идентичность и адресация

| Объект | SHA-256 |
|---|---|
| F: statesepext.sys 10.0.22000.1, 61752 bytes | `167fda36a71c2aad4c146bc66ae4c83e9d525d008d6abfd8eda0e056c9a96658` |
| K: ntoskrnl.exe 10.0.26100.9457, 13104632 bytes | `d90c69cfa925d42037ef1cdba3ff5b3f13cc14e16060938073b97e396fdde2f2` |
| W: winload.efi 10.0.26100.9444 | `1c1a978448b8e0fc2284c13d6e55fde8bf9827dca432cdc8a9be66c2e729adda` |
| ApiSetSchema.dll 10.0.26100.9278 | `391a2818fdef14f4a9b6e6ce2a7e7f01d04f943de9ef543ec683927cfe480889` |
| onecorestateseparationext.dll 22000.1 | `e73a7cdf579db78159ec4d2761742d80dcf5a8da2f4abb185efdc9662f436ada` |
| Дополнительный COMPLETE-HANDOFF от 5 ноября | `6d088bba79750b337d8f1b76be0bf31278c66e1c107421467c974233742c1524` |

Все адреса — RVA **конкретного PE с пометкой F/K/W**, не абсолютные VA. Для F исследованные `.text`, INIT, IAT имеют file offset=RVA; K имеет другую section mapping. Каждый ASM содержит и RVA, и вычисленный file offset; единой формулы для всех секций K не применяется. PDB в этом этапе не загружались: прежние имена внутренних функций используются только как подсказки; важные выводы привязаны к bytes, import/export directories и `.pdata`.

## V01 — все 26 импортов присутствуют

F — AMD64 PE32+; K — AMD64 PE32+. Import Directory F содержит ровно 26 именованных imports из единственной библиотеки ntoskrnl.exe. Все 26 найдены в Export Directory K, forwarders отсутствуют. Полная таблица с RVA каждого export и всеми найденными IAT references: [imports-26.csv](evidence/imports-26.csv).

Это поимённая совместимость и наблюдаемый интерфейс. У функций Rtl/Ex дополнительно проверены используемые ширины параметров/полей, pool flags, SD control masks и UUID status. Полный двухсторонний аудит семантики всех NT services этим не заменяется.

## V02 — внешние Phase ABI согласованы

| Фаза | ABI | Export F | Call K / IAT K |
|---|---|---:|---|
| Phase0 | NTSTATUS(BOOLEAN Enabled, BOOLEAN DevelopmentMode) | `0x8090` | `0xC294EE / 0x150378` |
| Phase1 | NTSTATUS(BOOLEAN Enabled) | `0x8190` | `0xC6DC8E / 0x150368` |
| Phase2 | NTSTATUS(BOOLEAN Enabled, PCUNICODE_STRING Source, PCUNICODE_STRING Target) | `0x8360` | `0xB0F996 / 0x150370` |

F Phase2 передаёт два указателя в OBJECT_ATTRIBUTES.ObjectName helper `0x1050`; K передаёт два 16-byte Unicode descriptors из WNF table. Большого private context на этой границе нет. Используемые UNICODE_STRING, OBJECT_ATTRIBUTES, KEY_* information, IO_STATUS_BLOCK и FILE_BASIC_INFORMATION layouts описаны с доказательствами в ABI-STRUCTURES.

## V03 — Phase0 предшествует Phase1 на нормальном пути; Phase2 условная

Независимо восстановлен общий parent K `0x705B30`: call `0xC28B48` на `0x705B66` приводит к Phase0, а последующий call `0xC6DC38` на `0x705B91` — к Phase1. Phase2 вызывается отдельным WNF path только при новом key (`CreateDisposition==1`, K `0xB0F978`). Нельзя описывать все три как безусловную линейную последовательность boot callbacks.

В K все три caller допускают STATUS_NOT_SUPPORTED=`0xC00000BB`. Иные отрицательные статусы могут оборвать initialization, вызвать bugcheck в Phase1 или вернуть ошибку WNF. Пустой Host/fallback не является автоматически объяснением исторического BSOD.

## V04 — F реализует настоящие операции, но лишь часть платформы

Phase0 выбирает KernelLink rules, обеспечивает source/target, выполняет merge, UUID rename и volatile REG_LINK. TreeSync на existing target сохраняет его own values/DACL при данном Phase0 call; это не безусловная перезапись. Rename/CreateLink не транзакционны: cleanup не восстанавливает исходное дерево.

Phase1 копирует SAM/SECURITY/DEFAULT hive-файлы по HiveRedirectionList через OSDataRoot, с FILE_OVERWRITE_IF и условным sentinel skip. Она не монтирует OSBOOT/OSDATA/DEVICES. Phase2 копирует registry subtree, возвращает status и освобождает shared registry scratch. Точные операции/нестрогая обработка ошибок сохранены в ASM и ABI-STRUCTURES.

По исходной Factory inventory заново пересчитаны 62 file mappings, 459 key mappings и 81 links; только два links имеют KernelLink. Это подтверждённое содержимое предоставленного JSON, а не свежая проверка live hive или всех правил runtime.

## V05 — механизмы State Separation в K/W существуют как код

Подтверждены не только строки:

- K getters Enabled/DevelopmentMode и control table `0xC84D90..0xC84DF0`.
- K RtlGetPersistedStateLocation `0x94DD10` открывает roots Keys/Files, читает class2 REG_SZ и имеет default/cached-absence path.
- K WNF descriptor `0xB4D738` содержит OSDATA Notifications и реально используется Phase2 caller.
- K `0xC18A18` использует OSDataDevice/OSDataRoot; conditional input fallback не доказывает отдельный physical device.
- K `0xC5461A..2F` имеет Enabled-gated DEVICES processing; W `0x9846/0x98E0` передаёт DEVICES/OSBOOT paths в hive-loading helper `0x3DA4`.

Этого достаточно, чтобы отвергнуть тезис «в NT26100 от State Separation остались только названия». Готовую и активную split-state платформу эти факты не доказывают.

## V06 — повторная проверка результатов предыдущего этапа

По исходной schema26100 заново запускался read-only V7 decoder: State family647/version710 имеет SEALED version flags=1, default-host отсутствует, importer count=0. Для user-mode core-stateseparationext также Host отсутствует. Это ограничение данной базы, не общий запрет непустого SEALED Host. Положительный исходный пример tm.sys сохранён в предыдущем аудите.

Исторический отчёт 8 ноября для **этого K9457** сообщает fallback трёх Phase IAT в K+`0x4E5A70`. Тело по этому RVA заново проверено: `mov eax,0xC00000BB; ret`. Разрешение конкретных runtime IAT заново не выполнялось и не выдаётся за новое наблюдение.

## V07 — дополнительный архив изучен, предел найден точно

Распакованы 78 members, просмотрены inventories вложенных ZIP, нужные CAB payload извлечены без установки. Извлечены настоящие XRo.sys/ssboot.exe/spaceutil.exe22000 и package/feature/layout metadata. XRo NT imports: 62/62 по именам присутствуют в K9457. Проверка FLTMGR/ntdll и полной работы XRo/SSBoot не выполнялась.

**В указанном архиве нет kernel22000.** Единственный nested ntoskrnl — 26100.1742. Также там нет подтверждённого согласованного State Separation package set26100. Это отражено в UNKNOWN и NEXT-EVIDENCE, а не заполнено предположениями.

## Воспроизведение

[audit_pe.py](audit_pe.py) читает PE как bytes, не загружает их и не вызывает NT API. Python3.6+ и Capstone:

```powershell
python -B .\audit_pe.py factory summary
python -B .\audit_pe.py factory comparison
python -B .\audit_pe.py factory iat-sites
python -B .\audit_pe.py factory function 8090 8190 8360
python -B .\audit_pe.py kernel function 705b30 c28b48 8816a4 94dd10
```

Default input paths указывают на исходные PE предыдущего каталога исследования. Полная проверка входных hashes/byte equality и контрольных адресов — [validation.json](evidence/validation.json). Ограничения покрытия: `.pdata` references охватывают разобранные runtime-function ranges, не являются доказательством отсутствия всех возможных косвенных вызовов в произвольных данных.
