# Единый план и практические критерии перехода

Цель: реальная WCOS 26100, не постзагрузочная симуляция. Разделы плана нумеруются G0–G7; это **gates**, не проценты готовности.

| Gate | Цель | Результат к 12.11.2026 | Что должно стать доказательством |
|---|---|---|---|
| G0 | Baseline / recovery | Версии и SHA основных PE зафиксированы, известен snapshot `Before-PhaseBinding-26100-9457`, но фактическое состояние всех современных VM snapshots на 12.11 не переснималось | сохранённые консистентные snapshots, журнал какой VM/boot/revision |
| G1 | Factory OS 22000 reference | Реальный FOS 22000 boot, BCD M/O/B, Object Manager chain, XRo, registry maps, копии ульев подтверждены; оставался upstream winload DEVICES/OSBOOT CFG | связать producers guards, loader block, имена backing stores в одном callgraph / runtime |
| G2 | API Set / Host source and accepted base | BaseApiSetSchema selector в winload доказан; TraceSmoke реально загружал alt PE с debugger; новый dual V6+V7 вариант успешно офлайн декодирован; Host version 26100 trusted не получен | новая schema прошла actual early parser + CI именно в нужном контексте, Host mapping доказан по итоговой скомпонованной схеме |
| G3 | Early split hives + storage | 26100 code paths обнаружены, BCD на текущей 26100 только C:, OSDataRoot fallback наблюдался; native OSBOOT/DEVICES ещё не подключены как FOS | реальные early hives, отдельные backing stores, OSDataRoot/BSP/storage identities, registry mount times |
| G4 | Kernel Phase binding / ABI / safety | Все 3 callback ABI и статусные rules исследованы; INIT retention bounded, 4/26 imports SAME limited, 22/26 INCONCLUSIVE; trusted HOST нет | точные живые IAT 3 фаз и их исполнение + failure semantics, CM scratch/locking и INIT lifetime доказаны |
| G5 | Первый boot WCOS 26100 PoC | **Не построен и не загружен**; существующая обычная 26100 с postboot D: не считается WCOS | согласованный Winload/Kernel/extension, early separate storage, reproducible Hyper-V Gen2 boot |
| G6 | Доказать настоящую State Separation | Не достигнуто | MainOS RO с проверками записи, перенос mutable реестра/файлов, сохранение после reboot, servicing/reset/recovery |
| G7 | UX / apps | Отложено | UWP/WinUI, оболочка Santorini/Composer 20279, затем Win32 sandbox/container |

## ТЕКУЩИЙ ближайший sprint (единственный вместо повторения кругов)

**Цель T1:** оценить именно новый V6+embeddedV7 APISet, а не повторить V6-only TraceSmoke, с сохранённым оригиналом и снятым WinDbg. Удалённый мощный ИИ параллельно разбирает producer guards `OSBOOT/DEVICES` — его работа НЕ блокирует ограниченный тест способа выбора/композиции схемы, но блокирует полноценную WCOS.

Перед планированием boot: (a) проверить идентичность VM snapshot и исходного PE, (b) получить/подтвердить SHA последнего dual-файла на диске гостя, (c) подтвердить, как boot policy допускает альтернативный PE: `HashMismatch` однозначен для обычной Microsoft-подписи, ранний scenario1; прошлый debugger-admission ≠ standalone acceptance, (d) обеспечить rollback offline для SYSTEM/BCD и получить критерии диагностики без WinDbg. Не менять штатную системную DLL. Если эти условия не выполнены, сначала narrow test/inspection и согласованный controlled experiment, а не бессмысленный boot.

**Важное различие результатов:**
- `offline format PASS`: парсер читает V6+V7, два Host на местах — уже **подтверждено**.
- `winload selected alternate base`: это уже подтверждалось для старого B-DualSize с **boot debugger**; для НОВОГО dual пока нет.
- `Winload accepted unsigned modified PE`: для НОВОГО нет доказательства; при старом boot debugger было warning + allowed.
- `composed active namespace has two Host`: для нового НЕ измерено.
- `Kernel Phase IAT -> new trusted extension`: НЕ измерено; compatible 26100 host отсутствует.
- `Phase0/1/2 actually works` и `WCOS core`: НЕ доказано.

## Не повторять

1. Перечитывать тот же пустой Host штатной ApiSetSchema 26100 или спрашивать установить пустой `statesepext.sys` на `C:`. Факт уже проверен многократно.
2. Делать `bcdedit /enum current` и `NtLinks` как «новые открытия»: FOS и 26100 цепочки известны, для 26100 осталась только не выполненная последняя ARC→HarddiskVolume проверка.
3. Оценивать D:/REG_LINK как проделанный Core Stage. Postboot PoC 20/20, Core 2/100, 0/7 — разные измерения.
4. Повторять сканирование 26 imports: совпадение имен НЕ полноценная совместимость; 4 SAME / 22 INCONCLUSIVE отражены в отчётах 11.11.
5. Снова предлагать просто изменить V6 Host / `Flags=SEALED` / `Value.Flags=2`: эти варианты уже исследованы и ошибка объяснена.
6. Начинать исследование Windows 10X OOBE, shell, Win32 контейнера до G6.
7. Превращать вывод «всё исследовано» в необоснованное обещание «можно прямо сейчас установить и запустить».

## Внешний ИИ: отдельная открытая задача

Был получен запрос на оригиналы `.reg` HKLM\OSBOOT/HKLM\DEVICES и Object Manager runtime log. Для него был сформирован дополнительный handoff с восстановленными по выводу пользователя ObjMgr/BCD, производными registry reports; **исходные** `WCOS-FullRegistry-22000.zip` / `26100.zip` отсутствовали в доступной на тот момент директории и не выдавались за присутствующие. Последняя точная просьба этого ИИ: локальные пути к оригинальным `.reg`; его окончательного нового анализа guard-producers пользователь пока не прислал.

Пока исследование guard-producers не закончено, не присваивать boolean `BL` / `R15B`, selector `R13D` / `R12D` произвольные названия. Есть статические адреса, не полный producer graph.
