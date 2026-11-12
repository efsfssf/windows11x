# winload 26100.9444: выбор схемы, проверка образа и ранний host binding

Анализ 2026-11-08; только копии файлов. RVA в таблицах относятся **только** к `winload.efi` SHA-256 `1c1a978448b8e0fc2284c13d6e55fde8bf9827dca432cdc8a9be66c2e729adda`, preferred image base `0x180000000`. Абсолютный VA в objdump = base + RVA. Это не runtime-адреса VM.

## Достоверность имён

С Microsoft Symbol Server получен `winload_prod.pdb/0B1E3F9A346875BBA8B824EFDF33BB092/winload_prod.pdb`, 2,019,328 байт, SHA-256 `237bf48cf62a0c1487f7898e558f3f9b530d198793bbfd0df6ff62704ea3a9bf`. GUID совпадает с RSDS PE; RSDS age=2, DBI age=2, **PDB Info age=4**. Публикационная причина различия не установлена; успешный WinDbg exact-match не заявляется. Имена извлечены из `S_PUB32` DBI SymRecordStream и сопоставлены с реальным кодом, а не перенесены со старого PE. Материалы: `evidence/boot-symbols-9444.txt`, `evidence/boot-current-symbols.json`, парсеры `boot-analysis.py` и `boot-pdb-symbols.py`.

## 1. От SYSTEM к выбранному файлу — VERIFIED

| RVA | Наблюдаемый код / эффект |
|---|---|
| `0xBBCF`, `0xC041`, `0xC050` | `OslpLoadAllModules` получает `OslSystemHiveHandle`; передаёт его в `OslLoadApiSetSchema(0x2309C)`. К пути ранее добавлено `system32\\` по `0xBCDE`. |
| `0x230E4`–`0x230FA` | Берётся объект hive по handle и его выбранная cell `[object+0x12E8]`; вызывается `CmpLoadApiSetSchemaExtensionsNode(0x26CBD8)`. Это hive loader context, не live Registry API. |
| `0x26CC12`, `0x26CC51`, `0x26CC8C` | Три `CmpFindSubKeyByName` проходят `Control`, `Session Manager`, `ApiSetSchemaExtensions`. UNICODE_STRING соответственно `0x2E9AA0`, `0x2E9EB0`, `0x2E9DC0`. |
| `0x23104`, `0x23111` | `CmpFindValueByName(0x26D3F0)` ищет `BaseApiSetSchema`; UNICODE_STRING `0x2E9D60`, строка `0x2E98E8`. |
| `0x2311B`, `0x2313B`, `0x23155` | Отсутствующая cell/данные оставляет alternate pointer нулевым; `CmpValueToData(0x26D584)` возвращает адрес данных значения. Сам `CmpValueToData` вызывает `CmpGetValueData`; failure либо требующая освобождения data-buffer ветвь возвращает NULL. |
| `0x23160`–`0x23175` | Default=`ApiSetSchema.dll`; ненулевой `r14` выбирается `cmovne rdx,r14`, затем **добавляется к уже подготовленному пути** через `BlAppendUnicodeToString`. Это не выбор другого boot device. |
| `0x2317E` | Ошибка append/allocation → `0xC0000017 STATUS_NO_MEMORY`. |
| `0x231A9` | Вызов `OslpLoadApiSetSchemaImage(0x233C8)` с тем же loader OS-device handle и полным выбранным именем. Ошибка статуса не маскируется: `0x231B2 → 0x23396`. |

Искомая конфигурация: SYSTEM, выбранный загрузчиком ControlSet, `Control\\Session Manager\\ApiSetSchemaExtensions`, значение `BaseApiSetSchema`. Конкретный номер ControlSet и runtime-тип поля `+0x12E8` данным разбором не назначаются. Значение **участвует в актуальном коде без отдельного feature-query на наблюдаемом пути**; это не доказывает отсутствия иных policy gates при загрузке образа.

## 2. Schema — PE image, а не доверенный raw namespace — VERIFIED

`evidence/boot-schema-flow.txt` содержит полный разбор `0x2309C–0x234CF`.

1. `OslpLoadApiSetSchemaImage` вызывает `OslIsTcbLaunchEnabled(0x84E0)` по `0x233EB`. Флаги OslLoadImage: `0x80` для обычной ветви, `0x880` для TCB (`0x23417/0x23420/0x23424`). Это **не** произвольный raw-file read.
2. `0x23457 → OslLoadImage(0x1B640) → 0x1B7DC → BlLdrLoadImage(0x19E46C)`; TCB/preload ветвь также существует и не приравнивается к обычной Hyper-V загрузке. `BlLdr` использует таблицу callback `0x2B2D50`, включая `OslpLdrExProcessImageFlags(0x1C0AC)`.
3. Только после успешной image-load по `0x23478` вызывается `BlImgFindSection` с именем `.apiset`. Отсутствие секции: unload и `0xC000007B STATUS_INVALID_IMAGE_FORMAT` по `0x2348D/0x23492`.
4. Успех возвращает mapped-image-base + `.apiset.VirtualAddress` (`0x23499–0x234A0`) и `.apiset.VirtualSize` (`0x234A3–0x234A6`), а также loader image entry для последующего unload.

### Реальная цепочка доверия и конкретный дополнительный gate

Полная детализация и ветви: **`evidence/boot-trust.md`**; исходный дизассемблированный код: `boot-trust-*.txt`.

- `OslpLdrExProcessImageFlags` переводит image flags в флаги `BlImg` с учётом **LoadIntegrityCheckPolicy**, signing scenario, TCB и custom signer policy. Для обычной schema-ветви исходный набор `BlImg=0x424000`; при CI policy>=1 включается validation bit `0x10`. Нельзя назначить runtime-значение этой глобальной policy по одному статическому PE.
- `LdrpLoadImage → BlImgLoadPEImageEx → ImgpLoadPEImage` содержит настоящий путь криптографической проверки: вызов `ImgpValidateImageHash(0x1977A0)` по `0x1957DD`, затем `MinCrypL_CheckSignedFile`, `SbValidateSigningPolicy`, default signing policy / SI policy / каталоги в зависимости от условий.
- Есть **дополнительная проверка OriginalFilename**: `0x1959BB → BlResourceVerifyOriginalFilenameFromImage(0x1A1774)`. Сравнивается basename выбранного пути с `OriginalFilename` version resource, без учёта регистра. Mismatch ведёт к `0xC0430007` по `0x1959C4`. Копия Microsoft schema под произвольным другим basename поэтому не доказывает применимый маршрут. Подкаталог с прежним basename сам этой проверкой не исключён; проверка доверия остаётся.
- **Критическое уточнение custom kernel signers:** вызов `SIPolicyAreCustomKernelSignersAllowed(0x24418C)` по `0x19795C` достижим только для signing scenarios **{2,3,4,7}**: последовательность `sub 2 / sub 1 / sub 1 / cmp 3` по `0x197927–0x19793F`. При true accepted-root flags расширяются `OR 0x11` по `0x197965`. Для schema с LoadIntegrityCheckPolicy>=2 callback0x1C2B0 задаёт bit15; `ImgpGetScenarioFromImageFlags(0x198B4C)` по `0x198B5A/0x198B60` выбирает **scenario1**. Следовательно, официальное разрешение пользовательских **kernel driver** signers **не распространяется автоматически на эту base schema ветвь**.
- **Значение policy для безопасного пути:** `OslInitializeCodeIntegrity(0x176B0)` по `0x1772C` вызывает `BlImgQueryCodeIntegrityBootOptions`, затем по `0x17731–0x17745` задаёт policy1 при DisableIntegrityChecks=true, иначе policy3; сохраняет global по `0x17C6D`. `BlImgQueryCodeIntegrityBootOptions(0x19377C)` читает первый boolean из BCD ID `0x16000048` (DisableIntegrityChecks) по `0x1937AC–0x1937C8`; TESTSIGNING — отдельный второй boolean `0x16000049`. Поэтому обычный путь без nointegritychecks идёт через policy3 → schema scenario1 → исключение custom signers. **Policy1 потребовала бы отключения проверки целостности и не является допустимым решением проекта.** Это вывод по статическому коду, без нового аудита VM.
- Для нового самостоятельно подписанного schema PE недостаточно generic `signtool /pa` или наличия сертификата: требуется принятый именно schema boot signing scenario. Такого артефакта и доказательства в пакете нет. Универсальная невозможность любого иного официального signing/provisioning route не заявляется.

- Для исследованной normal-policy ветви validation flags=`0x14`, выбираются `BlImgAcceptedTrustedBootRootKeys` и EKU OID `1.3.6.1.4.1.311.61.5.1`; без test/flight исходный trusted-boot subset mask=`0x800` (`0x1978A0–0x1978CA`, `0x196025–0x19607D`). Имя соответствующего root enum по первичному определению не установлено; его нельзя произвольно перевести в универсальное «Microsoft-only».
- Отрицательный validation status обрабатывается по `0x195985–0x1959A1`, включая `ImgpFilterValidationFailure(0x1984D8)`. Этот helper содержит debugger-зависимое ослабление для `0xC0000428`/`0xC0000605` (`0x1984FE–0x198540`). Без такой ветви сохраняется failure и выполняется cleanup `0x1959C9`. Поэтому принятие при boot debugger не заменяет доказательство доверенной подписи. Ослабление не используется как основа интеграции или next experiment.

Изменение байтов `.apiset` в существующем подписанном образе меняет покрываемый Authenticode hash. Однако это не самостоятельное доказательство того, что никакая легитимная новая подпись/политика принципиально невозможна. **Доказан путь проверки и исключение schema scenario1 из custom-kernel-signers расширения root set на пути без DisableIntegrityChecks. Иные допустимые signers/provisioning для schema и конкретное разрешение пользовательской schema остаются UNKNOWN.**

## 3. Namespace, extensions и handoff — VERIFIED

| RVA | Эффект |
|---|---|
| `0x231D3`–`0x231EA` | Выделение heap по virtual size секции и `memcpy` namespace из image. |
| `0x231F3` | Исходный schema image разгружается через `BlLdrUnloadImage`. Runtime namespace — отдельная копия, не loader image driver. |
| `0x231F8`, `0x231FF` | Публикуются `OslApiSetSchema` (`0x354F00`), size (`0x354EF8`). |
| `0x2320D`, `0x2321B` | Указатель/размер записываются в extension loader block `+0xAA0/+0xAA8`. |
| `0x23222`–`0x23247` | Namespace sealed flag может отключить дальнейшую композицию. Код умеет v6 и v7-header представление. Это отдельный gate от sealed bit конкретного контракта. |
| `0x23282`–`0x23308` | Перечисляются registry extensions; каждый файл проходит тот же `OslpLoadApiSetSchemaImage`. Failure image load записывается в status записи, затем обработка продолжается (`0x23320`). |
| `0x2333B` | `ApiSetComposeSchema(0x27955C)`; failure композиции обрабатывается отдельно, не выдаётся за успех host override. |
| `0x2335F` | `OslpApplyDynamicSchemaExtensions(0x22FD4)`. В текущей compile-time таблице `0x2B2DB0`: `ext-ms-win-ntos-trace-l1-1-0` / `dtrace.sys` / predicate `OslpDtraceExtensionEnabled(0x22F48)`; это **не** универсальная таблица для State Separation. |
| `0x23375`–`0x23390` | После композиции окончательный namespace снова сохраняется в extension `+0xAA0/+0xAA8`. |

Для текущей **v6 base** функция `ApiSetComposeSchema` выполняет `mov eax,[rax]; test al,1; jne` по `0x279709/0x27970B/0x27970D`, пропуская добавление host к найденному sealed контракту. Это подтверждение **на новом PE**, а не повторение адресов от 1742. `ApiSetComposeSchema` также умеет v7 (`0x279C34 → 0x27AA24`); гипотетическая v7/base-migration конфигурация не исследована до доказанной signed integration и не включается в предлагаемый маршрут.

Kernel handoff независимо подтверждён `ntoskrnl.exe` **26100.9457**: `MiInitializeApiSets` RVA `0xC6920C`, size read `0xC6923F/0xC692AA`, pointer read `0xC692B1`, copy `0xC692B8` из extension `+0xAA8/+0xAA0`. См. `evidence/abi-current.asm`. Наличие handoff не создаёт host mapping, которого нет в namespace.

## 4. Как host попал бы в kernel imports — VERIFIED, условное выполнение

Это loader import path, не поздний `DriverEntry` сервиса.

- В `OslpLoadAllModules` сначала загружается nt image (`0xBDC3`), затем schema (`0xC050`), затем второй `OslLoadImage` для `ntoskrnl.exe` (`0xC232`, raw flags `0x102` против `0x100` у первого вызова). Именно такой порядок допускает разрешение kernel imports уже с выбранным namespace; semantic label второго pass подтверждается дальнейшим import path.
- `OslLoadImage` передаёт callback table `0x2B2D50`; table slot `+0x20` указывает на **`OslpLdrExOverrideImport(0x1BCEC)`**.
- `LdrpLoadImports(0x19D54C)` вызывает этот callback через table `+0x20` по `0x19D721`. Callback получает `OslApiSetSchema` по `0x1BDF2` и вызывает **`ApiSetResolveToHost(0x279130)` по `0x1BE23`**.
- Если контракт разрешён, но host name пуст, callback устанавливает skip byte по `0x1BE3C`; это не загрузка `statesepext.sys`. Для непустого host по `0x1BE5F/0x1BE66` копируется host name; по `0x1BE85/0x1BE8F` выбирается fallback directory `System32\\drivers\\` (инициализировано `InitializeSystemPaths(0x1B8DC)`), import flags получают bit `0x10` по `0x1BE98`.
- Затем `LdrpLoadImports` строит host image path (`0x19D7D8`), рекурсивно вызывает `LdrpLoadImage` (`0x19D85B`), с отдельными image-integrity policies, и **`BlLdrBindImportReferences(0x19DED8)` по `0x19D988`**. Последний проходит экспортный каталог host и вызывает `LdrpBindReference(0x19C9A0)` по `0x19DF6B`; ошибка не превращается в успешную привязку.

**У kernel extension host есть собственный тот же signing gate.** `LdrpLoadImports` передаёт callback указатель context flags (`0x19D70C`), а `OslpLdrExOverrideImport` добавляет `0x10` по `0x1BE98`. Контекст рекурсивного host load тот же: `0x19D7FE → 0x19CDB6`; callback image flags читает его по `0x19CF5A` и вызывается по `0x19CF5D`. Kernel load flags после снятия import-binding bit — `0x100`; назначенный host получает `0x110`. Поэтому `OslpLdrExProcessImageFlags` идёт через bit8 branch `0x1C168/0x1C16E`, при normal policy3 добавляет `0x8270` по `0x1C180`; bit15 снова назначает **scenario1**. Это **не ordinary boot-start-driver scenario**, и custom-kernel-signers expansion scenarios2/3/4/7 его не покрывает. Даже отдельно разрешённый собственный обычный kernel driver не доказывает раннюю привязку этого API Set host. Полный raw flow: `boot-loadallmodules.txt`, `boot-loadimage.txt`, `boot-imports.txt`, `boot-recursive-loadimage.txt`, `boot-trust-custom-policy.txt`.

Это доказывает техническую точку раннего host loading для *принятой схемы и подходящего принятого host*. Не доказаны принятие новой альтернативной схемы, ABI-совместимый новый host, его допустимый boot signing scenario и выполнение Phase0/1/2. Существование callbacks не закрывает эти четыре условия.

## Что именно теперь установлено

**BaseApiSetSchema — реальный штатный селектор filename/path suffix, который ведёт в обычный PE loader, original-filename и CI/policy проверки. Он не предоставляет автоматическое доверие содержимому.** SEALED per-contract ограничивает последующую композицию, но само по себе не блокирует выбор другой принятой base. Входная задача получила конкретный callgraph и gate; доказанного допустимого альтернативного образа нет. На данных проекта — **NO-GO для первого boot эксперимента**, с открытой, явно обозначенной возможностью законного provisioning/signing route после появления конкретного совместимого набора и policy evidence.
