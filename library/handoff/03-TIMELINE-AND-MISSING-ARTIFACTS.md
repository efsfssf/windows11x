# Хронология, реестр исходных материалов, отсутствующие файлы

## История работы без потери контекста

| Период | Работа | Что доказано / что не было доказано |
|---|---|---|
| До 05.11 | Windows 10X Santorini 20279, ModernUX, Composer, OOBE, usermgr | Оригинальная 20279 загружалась; OOBE user creation через изученные WinRT API не удалось (`0x80070005`), `usermgr.dll` исследован. Архивы `ModernUX-20279-20261104-013300.zip`, `OOBE-20279-20261104-012934.zip`. Это **отложенная** UX-ветка, не блокер настоящей WCOS Core. В 20279 `ModernUXShellApp.exe` импортирует `RtlIsStateSeparationEnabled` из ntdll — признак зависимости, не доказательство переноса GUI в 26100. |
| 04–05.11 | Изучена документация и архивы WSK Factory OS 22000, работающая FOS, исходные функции State Separation, оригинальная Storage Spaces/GPT layout. | WSK 22000 не превращает IoT26100 в WCOS; StateSeparation не single-switch. |
| 05.11 | FOS runtime maps, XRo instances, persists OSDATA/data, MainOS write denial; BCD 14 objects, Windows 10X and WIM sources, старый полный handoff. | Настоящая FOS 22000 — эталон. Прямой OSData probe persists, custom redirect probe не сработал; не считать 459/465 rules живой защитой всех paths. |
| 06.11 | Старые PE 26100.1742, Kernel ABI surface, регистры фаз и IAT; сравнение FOS/26100 `.apiset`, full registry. | Оба ядра содержат Phase0/1/2 callsites; в обычной 26100 IAT fallback `STATUS_NOT_SUPPORTED`. Старые 26100 схемы 974 contracts, FOS 945. 26 imports name matches ≠ semantics. |
| 07.11 | Аудит 22000 WSK CAB/CAT/XRo/SSBoot, `SEALED` per-entry, DevMode, Runtime FOS IAT, WinDbg exceptions `OslGetRootCell`/`0x5F`. | FOS runtime реально real statesepext binding; 26100 default no Host. Нельзя включать policy вслепую. |
| 08.11 | Полный офлайн engineering AI: Winload code paths CI scenarios, Phase0 helper semantics, Source and Binding GO/NO-GO, PDB, Signing Lab. | BaseApiSetSchema селектор доказан статически. `scenario1` не входит в custom kernel-signer 2/3/4/7; Phase0 ABI reconstructed, 49/49 offline unit tests. Boot NO-GO на старом комплекте. |
| 08.11 | Windows11X-StateSeparation-Audit-v1.0 / постзагрузочный D: PoC | 08.11 audit 16/20, Core 2/100, 0/7. |
| 09.11 | Пользователь загрузил B-DualSize TraceSmoke, снимал WinDbg на старте 26100 | Alternative schema реально загружалась под debugger. В A/B v6 Trace Host есть, C после dynamic V7 — пуст. Осторожно разделять две загрузки. |
| 09–10.11 | Внешний AI: 69 input hashes, root cause diff, V7 decoder и `SEALED` patch semantics | Наиболее вероятная причина — V6-only правка не обновляла compact embedded7; отчёты исправили неверные объяснения о `N+0x1C=HashMultiplier`, global SEALED и `VALUE Flags=2`. |
| 10.11 | StateSep ABI Offline AI: 26 names, callbacks, 26100 INIT retention, no kernel22000 in первом пакете | Не было доказано все Nt semantics; INCONCLUSIVE. |
| 11.11 | Новый FOS kernel22000 и loader были проверены, мощный AI изучил **реальные** 2 ядра | Все три Phase STATUS_NOT_SUPPORTED в 22000/26100; `0x04000000` dependency и INIT retention в обоих; 4/26 SAME limited, 22/26 inconclusive, 18 Zw→Nt lookup ×2. |
| 11.11 | Живая FOS `mountvol`, `fltmc`, `NtLinks`, BCD; начато точное отслеживание `OSBOOT`/`DEVICES` | Полная цепочка FOS OSDataRoot→HarddiskVolume9=O: доказана. BCD связывает `M:`/`O:`/`B:`. Winload guard calls найдено, upstream producers/real attach timing пока неизвестны. |
| 11.11 | Отдельный мощный ИИ получил narrow OSBOOT/DEVICES задачу; попросил исходные `.reg`, ObjMgr logs | Отдельный handoff с derivations был предложен; пользователь отложил его отправку. Окончательного ответа этого ИИ пока не было. |
| 11.11 | Проверена действующая Windows 26100 `C:\Users\fog`, BCD, OSDataRoot, Audit Latest.json | Текущая guest Windows11 с C:, без osdata/bsp в BCD; OSDataRoot→Arc rdisk0 part3. Postboot D: hive и mounted `HKLM\WCOS_OSDATA`; Latest Audit **20/20**, Core 2/100, 0/7. |
| 11–12.11 | Вернулись к API Set; создан новый dual V6+embedded7 PE с двумя Host, сохраняющими SEALED | Offline Python/PowerShell validation PASS, SHA `75fb8f...c0bbd`, Host indices 286,287, `HashMismatch`. **Новая DLL не загружалась при boot, не установлена**. |
| 12.11 | Пользователь просит полный контекст для нового чата, чтобы не зациклиться | Этот handoff, логи, манифесты, оригинальные отчёты/доступные source archives. |

## Новые файлы, которыми пользователь точно располагает по прошлым шагам

`C:\Users\fog\Downloads\wcos_new_apiset_26100\` — пользователь запускал `Verify-Offline-APISet.ps1`; там новый `.dll` и вспомогательные файлы. Предыдущий сгенерированный архив имел название `Windows11X-APISet-26100-V6V7-OFFLINE-LAB.zip`. **В текущем `/mnt/data` исходно не было его точной копии**, поэтому этот handoff НЕ выдаёт новый сгенерированный `.dll` за приложенный без SHA. При переносе в новый чат приложить из пользовательской папки указанный ZIP или сам DLL вместе с PowerShell проверкой. Ожидаемый SHA DLL `75fb8f895ea030c60f435863971eeb025798abdc306958b593dbe6eb185c0bbd`.

Другие прошлые отчёты могли иметь имена `Windows11X-APISet-26100-Complete-Research-Handoff-2026-11-09/`, `Windows11X-APISet-26100-V7-SEALED-Research-Handoff-2026-11-10/`, `Windows11X-FOS22000-vs-NT26100-Semantic-Comparison-2026-11-11/`, `Windows11X-WCOS-Roadmap-and-HiveGate-Audit-2026-11-11.zip`, `Windows11X-FOS-Hive-and-ObjectManager-Evidence-2026-11-11.zip`. Эти имена фигурировали в чате; **не все указанные полные ZIP присутствуют в текущем container**. Но отдельные `ROOT-CAUSE`, `VERIFIED`, `DIFFERENCES`, `PHASE-CALLGRAPH`, `V7-STRUCTURES` и т. д. сохранены в `reports/` и не пропадают из этого пакета.

## Оригинальные исторические источники из 05.11

- `ModernUX-20279-20261104-013300.zip`: ModernUXProduct, shell executable/JS, assets, winmd. Отдельный исходный архив пользователя сохранён в проекте, в новый ZIP перенесён производный вариант с удалёнными файлами шрифтов.
- `OOBE-20279-20261104-012934.zip`: CloudExperienceHost, WinRT metadata, OOBE JavaScript, OEM prompts; сохранён оригинал, в новый ZIP перенесён производный вариант без шрифтов.
- `Windows11X-WCOS-2026-11-05-COMPLETE-HANDOFF.zip`: 78 файлов, включает вложенные упомянутые архивы, многочисленные screenshots, JSON 787 KB, WSK feature maps/CABs и старые parser outputs. Вложен в текущий новый пакет в версии с исключёнными nested 20279 архивами с возможными шрифтами, и дополнительно приложен `Windows11X-WCOS-HANDOFF-2026-11-05-LATEST-FULL.json` и его README и другие производные тексты. Если другой чат должен исследовать UX 20279, пользователь может дать оригинальные архивы отдельно.
- `Windows11X-WCOS-TECHNICAL-HANDOFF-2026-11-08.zip`: хешированные early research docs, PE/source ZIP, PDB, maps, **вложен** в source archive.
- `Windows11X-StateSeparation-22000-vs-26100-Offline-Audit-2026-11-10.zip`: большое исследование 170 entries, содержит вложенные старые 20279 ZIP с assets/fonts; текущий handoff прилагает extraction **без этих вложенных 20279 ZIP** и с собственным index. Оригинал по прежнему в проектном состоянии, но не выдается как оригинально побайтно идентичный новый архив.
- `FOS.rar`: оригинальные FOS22000 kernel, Boot/winload etc, **вложен** в sources.
- `INIT-LIFECYCLE-2026-11-11.rar`: оригинальные результаты powerful AI, `MiSnapDriverRange`, PDB/asm, **вложен**.
- `WCOS-26100-9457.zip`: SHA-pinned current kernel/winload/schema source bytes, **вложен**.
- `Windows11X-StateSeparation-Audit-v1.0.rar`: аудитор и README, **вложен**.
- `Windows11X-26100-Signing-Lab-v0.2.rar`: TESTSIGNED/UNSIGNED probe source/PE for old experiments, **вложен как legacy, не boot-ready**.

## Отсутствующие оригиналы и неизвестное содержание

1. `WCOS-FullRegistry-22000.zip` и `WCOS-FullRegistry-26100.zip`, SHA 7333... и 8bc7..., ранее были предоставлены и использованы для статистики. В current /mnt/data не найдены. Производные сводки и runtime map есть, **не** полные `.reg` как primary evidence.
2. **Новый** физический архив `Windows11X-APISet-26100-V6V7-OFFLINE-LAB.zip` от предыдущего ассистента в current mounted filesystem не присутствует. Пользователь его локально распаковывал; в master описан результат.
3. Реальный `Latest.json` от 11.11 `20/20` на госте не загружался в current session как файл; присутствует пользовательский вывод чисел. В текущем архиве есть прошлый `Audit-20261108-092614.json` (16/20).
4. Complete `11.11 Semantic Comparison` full evidence `.asm` package по тексту `README-RESULTS` был у ИИ, но в текущую сессию отдельный ZIP не приложен; top-level report files и CSV есть. Отдельно не выдавать «все 92 ASM файлы» за включённые, если таких нет в приложенном пакетe.
5. Нет полного raw WinDbg dump той же загрузки для нового dual V7. Нет доказательства его boot acceptance и нет approved signed 26100 `statesepext.sys` и `onecorestateseparationext.dll`.
6. Нет нового отчёта powerful AI о точных producers DEVICES/OSBOOT после запроса `.reg`; задача остаётся открыта.
7. Нет аттестованной 26100 PC WCOS OEM full image/product source, CAT/MUM/servicing/recovery match. Публичные источники могут быть неполными; отсутствие находки не равно математической невозможности.

## Правило чтения устаревших источников

Для `26100.1742` использовать только RVA/хеши этой версии и `.apiset` 974. Для `26100.9444/9457/9278` использовать новые RVA/984. Отчёт 10.11 без оригинального K22000 нельзя цитировать как полный 22000-vs-26100 semantic proof; его supersedes 11.11 report. Не приписывать Bash hooks ошибки и Claude `[cyber]` refusal бинарному факту: это была особенность чужой исследовательской среды 09.11, а не сообщение `winload` или Windows. Не доверять утверждениям старого «умного ИИ» о `N+0x1C=HashMultiplier=7`, namespace SEALED, без независимых байтов (опровергнуто).
