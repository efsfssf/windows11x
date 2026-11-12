# Windows 11X / WCOS 26100 — зафиксированный инженерный план

Дата: 2026-11-11. Статус: OFFLINE-FIRST / NO NEW BOOT MODIFICATIONS.

## Цель и определения

Создать реальную x64 PC WCOS на согласованной ветке Windows NT 26100: kernel + boot, раннее State Separation, настоящие раздельные тома/подключённые hives, защита MainOS, durable OSData/registry, обновление/восстановление. Windows 11 LTSC с переименованными томами или оболочкой не является успехом. Factory OS 22000 — эталон архитектуры; 20279 — эталон будущего UX, не базовая ОС.

## Последовательные gates (критерии, не проценты)

| Gate | Задача | Доказательство успеха | Текущее состояние |
|---|---|---|---|
| G0 | Зафиксировать оригинальные PE/VM baselines | Хеши, точные версии, сохранённые снимки/восстановление | Источники K/W 22000 и 26100 хешированы; контроль непрерывности VM образов — отдельно |
| G1 | Восстановить эталон FOS 22000 | Устройства/тома/BCD, OSDataRoot, DEVICES/OSBOOT/OSDATA hives и boot conditions, XRo | M/O/B и Object Manager chain установлены; boot hive guards найдено, producers/actual load timing неизвестны |
| G2 | Выяснить подписанный/согласованный Host для 26100 | Подтверждённый source/package/schema и нормальная загрузочная цепочка State Separation Host | BLOCKED: stock 26100 embedded V7 SEALED, Host отсутствует |
| G3 | Подтвердить ранний registry/storage pipeline 26100 | Условия OslAttachHiveToLoaderBlock и реальные backing hives/targets совпадают с выбранной архитектурой, без механической подмены FOS данных | Код в 26100 есть; end-to-end состояние отсутствует |
| G4 | Закрыть критическую Phase/CM безопасность | Реальный binding, 3 callback contracts, INIT final Flags, CM Create/Link/Copy/Rename + shared scratch concurrency | Внешний Phase ABI и обычная INIT retention изучены; 22 API семантически INCONCLUSIVE, scratch UNKNOWN |
| G5 | Первый минимальный boot PoC (Hyper-V Gen2) | Согласованный boot, runtime выбранный Host, проверенные IAT targets и правильные ранние hives, без отключения protections | Не начат; запрещено считать обычный Windows 26100 boot успехом |
| G6 | Доказать separation и жизнеспособность | Воспроизводимая защита MainOS, записываемая/постоянная OSData, перенаправление реестра, reboot, servicing и recovery | Не начат на 26100 |
| G7 | Пользовательский слой после WCOS Core | UWP/WinUI, позднее Santorini/Composer 20279, Win32 container | Отложено |

G1 информирует G3. G2 — независимый критический блокер: нельзя переходить к G5 без G2, G3, G4. G6 нужен для признания настоящей WCOS, а не просто загрузившегося ядра.

## Известные факты

- FOS 22000 running: BCD {current}: device/osdevice=M:, osdatadevice=O:, bspdevice=B:, winload path=\\windows\\system32\\boot\\winload.efi. M=HarddiskVolume5, O=HarddiskVolume9, B=HarddiskVolume3.
- User observed Object Manager chain in one FOS SSH session: \\OSDataRoot -> \\Device\\OSDataDevice -> \\ArcName\\multi(0)disk(0)rdisk(4)partition(1) -> \\Device\\Harddisk7\\Partition1 -> \\Device\\HarddiskVolume9 = O:. This closes the chain for that FOS boot. It does not prove 26100 mappings.
- FOS XRo attached to M: and B: at altitude 261101; not shown for O:. Attachment is not universal enforcement proof.
- FOS `HiveList` saved earlier: OSDATA/SAM/SECURITY on HarddiskVolume9; SOFTWARE on HarddiskVolume5; OSBOOT/DEVICES absent from HiveList but HKLM keys and OSData-side files observed. Missing HiveList entry != unmounted hive.
- W22000 and W26100 both have conditional `OslAttachHiveToLoaderBlock` branches DEVICES/OSBOOT. This checkpoint independently re-disassembled them. Detailed guards in 01-WINLOAD-HIVE-GATES-FIRST-PASS.md.
- K22000 and K26100: Phase0/1 boot hooks, WNF-conditional Phase2, 0x04000000 dependency flag and special INIT cleanup selector; semantics of all dependencies not guaranteed.
- Stock 26100: API Set State Separation contract has no Host, version SEALED. This is main G2 blocker. No speculative V6 patch, BCD toggle or old-driver drop-in as baseline.

## Work discipline

One actionable question at a time. Before any VM write: purpose, predicted observable, recovery/rollback, and hard success/failure criteria. Reuse saved evidence before asking new SSH commands. Maintain one canonical log per boot/VM and version/hash. Never merge WinDbg observations across different boots. Explicitly tag OBSERVED / STATIC PROVEN / STATIC RECONSTRUCTION / UNKNOWN. Every gate has a STOP condition.

## Current sprint (ONLY G1 / G3)

Question: **Which exact producers control the W22000/W26100 DEVICES and OSBOOT attach guards and what source/target hive path and loader block entry result from each outcome?**

A first-pass binary check is complete. Follow-up narrow static analysis: trace the guards' register/stack/global producers, attach helper signatures and code status gates, and map hive availability to BCD `osdatadevice` / early storage readiness; separate FOS runtime observed from kernel statically possible. Do NOT turn on State Separation on 26100 during this sprint.

After that: close G2 source/package/binding (parallel, not via repeated TraceSmoke), and G4 targeted CM scratch audit. If G2 has no usable original/compatible source, stop boot-PoC plans and call it BLOCKED pending components; don't replace by themed Windows 11.
