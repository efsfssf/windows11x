# ТЕХНИЧЕСКИЕ РЕЗУЛЬТАТЫ ВНЕШНИХ «УМНЫХ ИИ» — сохранение точных выводов

**Статус**: 12.11.2026. Это не общая новая гипотеза; конспект сохранённых отчётов, снабжённый ссылками на подлинные исходные MD/CSV в `reports/`. В некоторых ранних отчетах было меньше доказательств, чем в итоговом семантическом аудите 11.11. Наиболее поздние подтверждённые исправления имеют приоритет, а не более уверенная формулировка старого ИИ.

## A. Первый AI 09.11: APISet «Complete Research» (вне процесса VM)

Исходная формулировка: **вывод для показанной загрузки — обоснованная реконструкция, а не окончательное доказательство причины**. Сверены все **69/69** исходных SHA-256; V6 записи B-DualSize корректно содержали Host Trace. B Stage по реальному WinDbg снимку сначала попал в буфер; при dynamic extension его legacy mapping потерялся. Разрешение контракта возвращало `STATUS_SUCCESS`, но `Host.Length=0`; тестовая `WcosTraceSmoke.sys` не была привязана.

Статически доказано в W26100.9444:
- V6 wrapper `Version=6`, Count=984, `EntryOffset=0x98`, HashOffset=0xAA30, HashFactor=`0x1F` **по 0x18**.
- `N+0x1C` начинается embedded7 header (`07 00 08 FF`), `N+0x20` содержит embedded size; `N+0x08` outer namespace Flags=0, не 1.
- `ApiSetComposeSchema` выбирает V7 path для wrapper6+embedded7: RVA `0x27958B–0x2795AD` → `ApiSetComposeSchema_V7` `0x27AA24`. `ApiSetResolveToHost` `0x279181–0x27919F` выбирает V7 resolver `0x27A60C` при определённых flags `E+2`.
- B legacy Trace entry offset `N+0x4358`, value `N+0x94F0`; `HostOffset=0x29C60`, `HostLength=0x24`, имя 18 UTF16 WCHAR (`WcosTraceSmoke.sys`). Это дописано непосредственно за исходными compact V7 blocks, которые заканчиваются по N+`0x29C60`.
- `NS.Size` B=`0x29C84`, а финальный runtime `0x29C74`; арифметика `0x29C60+0x14` соответствует добавлению **пустой host value record** в compact composition, а не универсальному 20-byte composer metadata. Путь к этому статически восстановлен во втором исследовании 10.11.
- `BaseApiSetSchema` выбирает альтернативное **имя базового PE**, а `ApiSetSchemaExtensions` работает с overlay/составлением; смешивать нельзя.
- `SEALED` не объясняет пустой Trace: `Trace.Entry.Flags=0`. State Separation имеет `Flags=1` и другое ограничение.

Ограничения TraceSmoke: гостевой post-copy SHA не был снят; три промежуточных memory dumps в одном boot отсутствуют; результаты A/B/C и AL=0 — разные boots; не снимался полный compact resolved output после финальной composition. Поэтому точная причинно-следственная локализация каждого byte изменения **не является полностью динамически доказанной**, хотя механизм V7 сильнее «base всегда выигрывает».

Ранее другой ИИ предлагал упрощённое `ApiSetComposeSchema(base,ext): for existing contracts SKIP, else append`, потому что итог Size совпал `Original+0x14`. Эта схема неверно выдаёт одну гипотезу за доказанный алгоритм; приоритет более позднего `ROOT-CAUSE.md`/`MISCONCEPTIONS.md` и `TRACE-EXPLANATION.md`.

Файлы: `reports/ROOT-CAUSE.md`, `reports/FACTS.md`, `reports/STRUCTURES.md`, `reports/BINARY-COMPARISON.csv`, `reports/DATAFLOW.md`, `reports/HYPOTHESES.md`, `reports/UNRESOLVED.md`, `reports/SEAL-BOUNDARIES.md`, `reports/MISCONCEPTIONS.md`, `reports/NEXT-READONLY-TEST.md`.

## B. Второй AI 10.11: embedded V7 + SEALED + read-only decoder

**Input hashes** (для указанных 26100 releases): W26100.9444 `1c1a978448b8e0fc2284c13d6e55fde8bf9827dca432cdc8a9be66c2e729adda`; BaseApiSet 26100.9278 `391a2818fdef14f4a9b6e6ce2a7e7f01d04f943de9ef543ec683927cfe480889`; K26100.9457 `d90c69cfa925d42037ef1cdba3ff5b3f13cc14e16060938073b97e396fdde2f2`; FOS statesepext `167fda36a71c2aad4c146bc66ae4c83e9d525d008d6abfd8eda0e056c9a96658`. Verified input 13 PE/SYS source files as reported.

`V7-STRUCTURES.md` bounded decoder description: section `.apiset`: file start `0x2000`, PE RVA `0x3000`; N=namespace start; E=N+`0x1C`; actual compact block addressing `E + encodedOffset − WORD[E+0x12] + index*stride`. Для данного input `WORD[E+0x12]=0x1C`, значит encoded offsets **численно совпадают с N-relative** — ошибочно прибавлять 0x1C дважды. Строки Host/importer используют pool-relative offsets, отдельно `DWORD[E+0x24]`. Compact records имеют 12 байт, legacy entry 24 байта, legacy value 20 байт. Декодер read-only `v7_decode.py` **не** полный Windows API Set parser/composer; он bounded по двум целям.

Состояния оригинала:
- `ext-ms-win-ntos-stateseparation-l1-1-0` — both legacy/compact default Host отсутствует, importer-specific records отсутствуют; compact version flags bit0 SEALED.
- `ext-ms-win-ntos-trace-l1-1-0` — Host пуст, unsealed; динамический Trace fallback в W26100 таблице `OslpDynamicSchemaExtensions`.
- `ext-ms-win-core-stateseparationext-l1-1-0` — original base также SEALED/Host empty.
- Примеры оригинальных **SEALED с непустым SYS Host**, включая `tm.sys`, подтверждают: SEALED мешает перезаписать имеющийся контракт обычной схемой **расширений**, но **не запрещает** непустой Host в исходной корректной BASE.

**Flags и адреса в W26100**:
| Сущность | Поле | Значение и смысл |
|---|---|---|
| Legacy namespace | N+0x08 DWORD | 0, не per-entry SEALED |
| embedded namespace | E+0x2 = N+0x1E BYTE | `0x08`; отдельный namespace-level gate, bit0 composition-guard; bit2 маршрутизирует resolver; bit3/4 hash helpers |
| Legacy State entry | N+0x4328, first DWORD | 1=SEALED |
| Legacy Trace entry | N+0x4358, first DWORD | 0 |
| Compact State version | N+0x25A1A (+0xA) | bit0=1 SEALED, file 0x27A1A, PE RVA 0x28A1A |
| Compact family | +0xA | flag `0x10`; bit1 связан с host present (НЕ SEALED); bit4 не до конца восстановлен |
| Legacy value entry | +0 | 0; `Value.Flags=2` не является доказанным `HOST_EXTENSION` способом |

Найден ограниченный механизм отказа overlay SEALED для найденных записей: legacy composer `0x279709–0x27970D`; V7 extension path `0x27C622–0x27C646`, V7 output host path `0x27C9CA–0x27CB02`. Значения `N+0x1C=7` — **не** стандартный v6 HashMultiplier. Старый неподтверждённый «unseal by schema-level Flags=1/clear entry/value 2» отвергнут независимой проверкой. Нельзя считать любую несостоявшуюся подмену STATE host результатом sealed-gate: исходная base с другим compact V7 host раньше вообще не существовала.

Runtime control Trace: таблица `OslpDynamicSchemaExtensions` W26100 RVA `0x2B2DB0`, строка contract RVA `0x2BAA30`, host `dtrace.sys` RVA `0x2BB008`, predicate `OslpDtraceExtensionEnabled` RVA `0x22F48`; в **отдельном** boot `AL=0` → empty string fallback. Точную статическую причину `AL=0` по этому единственному runtime снимку не установили; не переносить это значение в boot A/B/C.

**Что осталось в decoder:** неизвестные private fields E+1/+8/+C/+10/+74.., смысл некоторых header/descriptors, все importer-specific paths, вся универсальная валидность В7. Для новых host default записи offline builder 12.11 сделал более широкую операцию, но в реальном `winload` он ещё не проверялся.

Файлы: `reports/VERIFIED-V7.md` (если имеется под этим именем), `reports/V7-STRUCTURES.md`, `reports/SEALED-SEMANTICS.md`, `reports/TRACE-EXPLANATION.md`, `reports/UNRESOLVED.md`, `reports/v7_decode.py`. В текущем пакете имена оставлены как у исходного top-level `VERIFIED.md` / `V7-STRUCTURES.md` при конфликте имен через `reports/api-v7/` и `reports/semantic-26100/` (см. INDEX).

## C. Агент 08.11: Signer scenario, PDB, Phase ABI и Phase0 helper

**Winload** 26100.9444 из `WINLOAD-26100-9444-CALLGRAPH.md`:
- `OslLoadApiSetSchema 0x2309C`, получает SYSTEM hive loader context, registry value `BaseApiSetSchema` `0x23104/0x23111`; выбор PE suffix `0x23171`, `OslpLoadApiSetSchemaImage 0x231A9`; section lookup `0x23478–0x234A6`, copy до `0x231EA`.
- image trust именно ранних сценариев: обычный init CI policy3 `OslInitializeCodeIntegrity` `0x17731–0x17745`, store `0x17C6D`; API Set schema scenario1 at `0x1C2B0`, `0x198B5A–0x198B60`; early kernel API Set Host via `0x1BE98→0x19D7FE→0x1C168..0x1C180` тоже scenario1. `ImgpValidateImageHash` custom kernel signers scenarios `{2,3,4,7}` at `0x197927–0x197965`, **не scenario1**.
- Проводится basename vs PE `OriginalFilename` check at `0x1959BB`, mismatch NTSTATUS `0xC0430007` (отдельная причина failure от подписи). Нельзя просто переименовать DLL без учёта embedded metadata.
- После принятого schema существует механизм early binding: `OslpLdrExOverrideImport 0x1BCEC` → `ApiSetResolveToHost 0x279130` → `LdrpLoadImage` → `BlLdrBindImportReferences`.
- Старый исход `NO-GO`: отсутствует принятый compatible signed schema and host/hives, а не отсутствует механизм выбора вообще. `testsigning`, самоподпись kernel signer, только `Get-AuthenticodeSignature` не закрывают boot trust gate. `Policy1/DisableIntegrityChecks` исключались правилами проекта.

**Kernel external ABI** (26100.9457), reconstructed proto from bytes, not official Microsoft SDK:
```c
NTSTATUS ExpInitializeStateSeparationPhase0(BOOLEAN Enabled, BOOLEAN DevMode);
NTSTATUS ExpInitializeStateSeparationPhase1(BOOLEAN Enabled);
NTSTATUS ExpInitializeStateSeparationPhase2(BOOLEAN Enabled,
                                          PCUNICODE_STRING Source,
                                          PCUNICODE_STRING Target);
```
Phase0/Phase1 исполняются ранним ядром, Phase2 demand by WNF, NTSTATUS in EAX. Старый `statesepext.sys` 22000 импортирует 18 Zw + 8 Rtl/Ex/CRT, не может быть заменён `return STATUS_SUCCESS` без работы по переносу реестра.

**Phase0 22000**: export RVA 0x8090 INIT, Phase1 RVA `0x8190`, helper A `0x8644`, helper B `0x9344`; enabled/DevModeEverEnabled; `KeyRedirectionLinks` reads `SourceNtPath`, `TargetNtPath`, `KernelLink`, `TreeSync`, `AlwaysEnabled`. `0x89DD` open source; failure→create parents and source (`0x89F4`, `0x8A22`); TreeSync existing target rules (`0x8A77..0x8AAC`); recursive helper `0x1050`, `0x11A0`; GUID rename `0x1630` / ZwRenameKey `0x1775`; REG_LINK recreation `0x17C8`. No transaction-wide rollback; potential GUID backups and partial targets. Original source of FOS redirection map included.

**49/49** agent unit tests passed; 8/8 WSK CAB examined were 22000.1, not 26100. No supported public PC26100 OEM source proven. `SAME` measured portions do not imply stable IRQL/scratch/ACL/reparse.

## D. Агент 10.11: StateSeparation 22000-vs-26100 Offline Audit — что тогда было и чего не хватало

Один архив `Windows11X-StateSeparation-22000-vs-26100-Offline-Audit-2026-11-10.zip` есть в исходных данных проекта. Там high-quality `VERIFIED.md`, `INCOMPATIBLE.md`, `UNKNOWN.md`, `NEXT-EVIDENCE.md`, `COMPATIBILITY-MATRIX.csv`; работа только read-only. Обнаружены 26/26 имен, вызовы phase и WNF, `INIT` retention path на 26100; не найден фундаментальный разрыв *внешней формы Phase ABI*. **Важная оговорка отчёта:** исходное предоставленное содержимое не включало оригинальное ядро 22000 — другой упакованный kernel был 26100.1742, поэтому реальное 22000-vs-26100 NT API semantic comparison в этом конкретном аудите было **UNKNOWN**. Поздний агент 11.11 впервые добавил подтверждённый K22000 и закрыл это ограничение частично. Нельзя описывать аудит 10.11 как уже доказавший все межъядерные семантики.

Отдельный `INIT-LIFECYCLE-2026-11-11.rar` (включён в handoff) содержит доказательства for W26100 K26100 `MiSnapDriverRange`: new dependency получает bit `0x04000000`, loader entry Flags+0x68, в ordinary cleanup имя секции `INIT` исключено при bit26. Это снимает упрощённую гипотезу о гарантированном freed code для Phase2, **не** доказывает no unload/discard in every lifetime path.

## E. Агент 11.11: независимое **семантическое** сравнение FOS22000 и NT26100

Это наиболее сильный исследовательский отчёт для ABI/state separation `README-RESULTS.md`, `VERIFIED.md`, `DIFFERENCES.md`, `PHASE-CALLGRAPH.md`, `LOADER-LIFECYCLE.md`, `NT-API-DETAILS.md`, `NT-API-COMPATIBILITY.csv`, `STATE-DEPENDENCIES.md`, `UNRESOLVED.md`. **Семь hash-pinned inputs**, 92 raw machine instructions, 6 callsites phases, 18 Zw dispatch linked to Nt bodies ×2. Source identities:

| Сокращение | Версия | SHA256 |
|---|---|---|
| K22000 | 22000.1 | `a9b5c0a47f1c4606a94adb3674017cbd35448c3792c4d26f51847a3ffa8c7b7c` |
| W22000 | 22000.1 | `9fb057cb65462624f35b41fe686f71fc03f11e3770fdb81e04900640030ad41d` |
| SS22000 | 22000.1 | `167fda36a71c2aad4c146bc66ae4c83e9d525d008d6abfd8eda0e056c9a96658` |
| Schema22000 | 22000.1 | `49696b4557023ea455aa7731a18467b623292a7c2ec837d8437a9c2dcd9cd498` |
| K26100 | 26100.9457 | `d90c69cfa925d42037ef1cdba3ff5b3f13cc14e16060938073b97e396fdde2f2` |
| W26100 | 26100.9444 | `1c1a978448b8e0fc2284c13d6e55fde8bf9827dca432cdc8a9be66c2e729adda` |
| Schema26100 | 26100.9278 | `391a2818fdef14f4a9b6e6ce2a7e7f01d04f943de9ef543ec683927cfe480889` |

### E.1 Точные фазы + IAT для новых K26100

| Фаза | K22000 call / IAT RVA | K26100 call / IAT RVA | Реальные args |
|---|---|---|---|
| P0 | `0xB0B60B / 0x13D2D0` | `0xC294EE / 0x150378` | `CL`=Enabled; `DL`=DevMode |
| P1 | `0xB10280 / 0x13D2C8` | `0xC6DC8E / 0x150368` | `CL`=CmStateSeparationEnabled != 0 |
| P2 | `0x7797B5 / 0x13D2D8` | `0xB0F996 / 0x150370` | `CL` Enabled, `RDX`/`R8` source/target `UNICODE_STRING*` |

Call order: `IoInitSystemPreDrivers`→P0, после завершения остальных driver-стадий `Phase1InitializationIoReady`→P1, поздние WNF consumers→P2 при cache miss and `CreateDisposition==1`. **НЕ** unconditional «boot phase0→1→2». В обоих kernels **все 3** NTSTATUS `0xC00000BB` допускаются как необязательная реализация (`K22000 0xB3CCDE/0xB3F858/0x7797C3`; `K26100 0xC29506/0xC6DCA6/0xB0F9A4`). В раннем 10.11 отчёте first two 22000 checks не видели, потому что вынесены в cold code; **11.11 это исправлено**.

### E.2 INIT, dependency Flags, lifetime

| Этап | W22000/K22000 | W26100/K26100 |
|---|---|---|
| `LdrpLoadImports` new dep flag | W `0xEF82C` `0x04000000` | W `0x19D5FB` `0x04000000` |
| flags copied to loader `+0x68` | W `0xEFEE5` | W `0x19DCDA` |
| kernel selector `INIT` keep | K `0x77C355` bit26 and `0x77C467` compare `0x54494E49` | K `0xA536FA` and `0xA53999` |
| kernel cleanup | K `0x8111A9..` | K `0x705B30..` |

`_KLDR_DATA_TABLE_ENTRY` common prefix size `0xA0`, `DllBase +0x30`, `Flags+0x68`, `LoadCount+0x6C`, `LoadedImports+0x88`; kernel reads them. **Полный boot entry allocation** W22000 `0xE8`, W26100 `0x120`, internal pointers diverged; не переносить старые private offsets, например `PipInitializeDriverDependentDLLs` W22000 uses `entry+0xE4`, W26100 `entry+0x11C`.

В normal mode2 cleanup `INIT` нового dependency с bit26 **не выбран**; нет доказательства гарантированного Phase2 use-after-free. Но lifetime реального Host, reuse of existing entry, explicit unload/discard/low memory ещё UNKNOWN.

### E.3 Реальные NT API implementations (не одни Zw stubs)

FOS statesepext imports 18 Zw и 8 Rtl/Ex/CRT. В kernel PE `KiServiceTable` K22000 `0xCA690`, K26100 `0xDADC0` из on-disk entries хранит **прямые RVA**, не runtime encoded shift. Для всех 18 ×2 получены реальные Nt bodies по service index, а не только Zw prologs. Ровно **4 SAME** при оговорённых inputs:
1. `RtlInitUnicodeString` (22000 RVA `0x2DEF50`, 26100 `0x431490`) — NULL, LENGTH clamp, descriptor semantics.
2. `RtlAppendUnicodeToString` (22000 `0x2B1D30`, 26100 `0x415E40`) — bounds/NULL/update and `0xC0000023`.
3. `RtlGetControlSecurityDescriptor` (22000 `0x7416E0`, 26100 `0xA699B0`) — Control/Revision under stated contracts.
4. `RtlSetControlSecurityDescriptor` (see CSV for complete IDs and bounds).

Остальные **22 INCONCLUSIVE**, включая register CM create/link/rename/copy, storage/security/IRQL/scratch; ни один доказанный unconditional external breaking change не найден. Это положительная возможность продолжать исследование, **не** бинарное доказательство совместимости всего модуля.

### E.4 WNF descriptors, caching и static paths

`WNF Phase2`: после удачного `ZwCreateKey` с `REG_CREATED_NEW_KEY` и `CreateDisposition==1` — callback, затем atomic `cmpxchg` cache pointer: K22000 `0x779787`, K26100 `0xB0F9C5`. WNF descriptors in both consistent names including `\REGISTRY\MACHINE\OSDATA\Notifications`; base K22000 `0xA38150`, K26100 `0xB4D6F0`, stride `0x30`. Возможный поздний `NtCreateWnfStateName` path K22000 `0x6EE0AD...`, K26100 `0x88220E...`. Atomic publication ≠ serialized prior callback or shared scratch F globals `+0x3060/+0x3068`.

### E.5 Обнаруженные *внутренние* изменения, не приписывать external ABI

- В `IoInitSystemPreDrivers` parent return: K22000 checks boolean-style `AL`, K26100 checks sign of EAX NTSTATUS. External Phase0 NTSTATUS callback unchanged in observed path.
- `Cm` functions rearranged (NtOpenKey via NtOpenKeyEx, NtCreateKey via stack-expansion helper, etc.), без полного доказательства эквивалентности.
- `MiFreeBootDriverInitializationCode`, `MmCallDllInitialize`, `MiIssueDllInitializeCall` internal implementations differ.
- `_LOADER_PARAMETER_BLOCK` обоих kernels size `0x170`, `ArcBootDeviceName +0xB8`, `ArcOSDataDeviceName +0x150`; поля имеются и используются, **но** это не означает, что в текущей 26100 `osdatadevice` отдельный от C:.

### E.6 Инструменты и границы PDB

PDB GUID/RSDS and DBI Age match each PE; **PDB Info stream Age differs** (например K26100 RSDS age1, DBI1, Info6; W26100 RSDS2, DBI2, Info4). Не утверждать, что PDB default exact-match на любой WinDbg автоматически доказан. Symbol names cross-check with byte ranges and export; 3121/3126 K22000, 3311/3317 K26100 anchors match, W22000 416/416, W26100 476/476. Unmatched public aliases `ExAcquireRundownProtection`, `ExReleaseRundownProtection`, `KeLoaderBlock`, `KeNumberProcessors`, `RtlMoveMemory`, плюс `memset` на K26100. Claims для этих alias addresses не делались.

## F. Новая схема 12.11: что новый ИИ должен проверить сам, если файл приложен

Схема `ApiSetSchema-Windows11X-26100-OFFLINE-UNSIGNED.dll` на базе 26100.9278, SHA нового файла `75fb8f895ea030c60f435863971eeb025798abdc306958b593dbe6eb185c0bbd`, размер `194016`, по пользовательскому PowerShell V6 и V7 default-host для State (`statesepext.sys`) / Core (`onecorestateseparationext.dll`) оба непустые, legacy State SEALED=True, V7 host-present=True, indices `286/287`. Исходный Host count 286, новый 288, old 984 legacy contracts, old 984 V7 version records; новый Size `0x29CE0`. Original signature `HashMismatch`, **не early-loaded**. Не менялась OriginalFilename PE по результату builder-описания, но **не** считать acceptance proof. Core `.sys` и user-mode `.dll` не установлены на обычной текущей 26100. Старые STrace/TraceSmoke расширения не содержат работающего State Separation Host.

Сам builder и `Verify-Offline-APISet.ps1` ранее были предоставлены пользователю в `Windows11X-APISet-26100-V6V7-OFFLINE-LAB.zip`, но в *текущем контейнере handoff* оригинального созданного архива нет. SHA можно проверять при повторном прикреплении. **Не создавать новый файл с тем же именем и другим SHA, выдавая его за уже проверенный**. Это критично для различения попыток boot.

## G. Итоговые неизвестные / status gates «умного ИИ»

| ID | Что остаётся UNKNOWN | Что требуется |
|---|---|---|
| S01 | Совместимый доверенный оригинальный 26100 State Separation Host/schema source | точные OEM/MUM/CAT/PE providers и early signing scenario1; либо ограниченный поддерживаемый diagnostic route |
| S02 | Полная семантика CM registry paths | graphs CmCreateKey/CmOpenKey/CmSetValueKey/CmRenameKey, status, callbacks, recovery/ACL |
| S03 | Параллельный доступ к scratch F globals | полный incoming Phase2/CopySyncKeyTree graph и lock scopes |
| S04 | Lifetime/discard/unload | explicit unload, MM discard, reuse, refcounts, final real Host flags |
| S05 | Полный loader classification/reuse | gate для каждого image, paths reuse versus new dependency |
| S06 | Storage/Security/Pool/CRT import semantics | real NT bodies, options & IRQL |
| S07 | policy → loader block → early hives | producer guards DEVICES/OSBOOT/OSDATA, readiness, error/resume |
| S08 | Persisted-state caching/reset | callers, reset, negative cache, malformed values |
| S09 | XRo, user-mode, servicing/recovery | согласованные 26100 components, update/rollback strategy |
| S10 | PDB Identity provenance | publisher info-age discrepancies и omitted alias |

**Правило:** `INCOMPATIBLE` требует доказанного нарушенного контракта, а не неизвестности. `COMPATIBLE` в отчёте относится строго к указанным механизмам и не означает boot. Самый новый valid runtime proof для V6+V7 — только OFFLINE PASS.
