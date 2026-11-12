# VERIFIED — INIT lifetime, statesepext22000 на предполагаемом extension path26100

Дата: 2026-11-11. Этот этап уточняет UNKNOWN/U02 предыдущего аудита. Проверки 26 импортов и внешних Phase prototypes заново не проводились: исследованы loader classification, initialization cleanup, поздние WNF paths и структуры. Работа полностью офлайн; PE/SYS не исполнялись и не изменялись.

**Главный результат:** в NT26100.9457 есть специальное правило, сохраняющее `INIT` у driver-dependent DLL images. Стандартный путь загрузки **нового import dependency**, включая выбранный API Set Host, задаёт соответствующий loader flag `0x04000000`, который переносится в kernel entry. Обычная очистка после Phase1 затем **не выбирает INIT такого образа для освобождения**. Поэтому сочетание «Phase2 находится в DISCARDABLE INIT» и «WNF может обращаться к ней поздно» само по себе не доказывает use-after-free в этом механизме.

Это доказательство поведения указанного статического пути с названными условиями. Оно не является runtime-снимком реально принятого statesepext Host на26100, не гарантирует сохранность при явном discard/unload и не переносит kernel rules26100 на Factory OS22000. Остаток — [UNKNOWN](UNKNOWN.md), отрицательные/условные случаи — [INCOMPATIBLE](INCOMPATIBLE.md), минимальные следующие данные — [NEXT EVIDENCE](NEXT-EVIDENCE.md).

## 1. Источники и символы

Все code addresses — **RVA к PE в соответствующей колонке**, не runtime VA. В ASM у каждой инструкции дополнительно указан file offset. F — оригинальный statesepext22000.1; K — kernel26100.9457; W — winload26100.9444.

| PE | SHA-256 |
|---|---|
| F | `167fda36a71c2aad4c146bc66ae4c83e9d525d008d6abfd8eda0e056c9a96658` |
| K | `d90c69cfa925d42037ef1cdba3ff5b3f13cc14e16060938073b97e396fdde2f2` |
| W | `1c1a978448b8e0fc2284c13d6e55fde8bf9827dca432cdc8a9be66c2e729adda` |

Найдены соответствующие PDB в локальном `C:\ProgramData\Dbg\sym`; их копии сохранены в `evidence/symbols/`. Сеть не использовалась. GUID и DBI Age совпадают PE RSDS:

| Объект | GUID | RSDS / DBI Age | PDB Info stream Age |
|---|---|---:|---:|
| K / ntkrnlmp.pdb | `C29EBFB0-6B78-B3C0-20DC-A66D99713F9E` | 1 | 6 |
| W / winload_prod.pdb | `0B1E3F9A-3468-75BB-A8B8-24EFDF33BB09` | 2 | 4 |
| F / statesepext.pdb | `9245993A-D5B5-ED8D-D3AC-98E18709C703` | 1 | 3 |

Различие Info Age сохранено, не скрыто. Привязка symbol segment+offset к PE дополнительно проверена независимыми export anchors K (`KeBugCheckEx`, `MmLoadSystemImage`, `RtlImageNtHeader`). Public names взяты из S_PUB32; layouts — из TPI, а не из названия cache directory. [symbol-provenance.json](evidence/symbol-provenance.json) содержит PDB hashes и исходные RSDS bytes.

## 2. Структура, по которой Memory Manager принимает решение

PDB K: `_KLDR_DATA_TABLE_ENTRY`, size `0xA0`, type index `0x345D`. Проверенные поля:

| Offset | Поле | Использование в исследуемом коде |
|---:|---|---|
| `+0` | InLoadOrderLinks | MmEnumerateSystemImages проходит список |
| `+0x30` | DllBase | MiFreeDriverInitialization получает image base |
| `+0x40` | SizeOfImage | Работа с image pages |
| `+0x48/+0x58` | FullDllName / BaseDllName | Перенос entry, не критерий INIT retention |
| `+0x68` | Flags, DWORD | Именно отсюда читается `0x04000000` |
| `+0x6C` | LoadCount, WORD | Отличается от per-section discard decision |
| `+0x88` | LoadedImports | Dependency bookkeeping; не объявляется доказательством постоянного pin |

[Полная извлечённая структура](evidence/_KLDR_DATA_TABLE_ENTRY.json). В kernel allocation имеются дополнительные внутренние поля за пределами `0xA0`; они не выдаются за поля этой публичной TPI-структуры. Winload PDB types не содержит; соответствие его common entry prefix доказывается конкретными записями по `+0x68` и последующим копированием ядром.

`0x04000000` здесь — loader-entry classification driver-dependent DLL, а не IMAGE_SCN_MEM_DISCARDABLE, не флаг API Set SEALED и не user-mode PEB Flags. Роль подтверждается тем, что `PipInitializeDriverDependentDLLs` K `0xC71BF3` отбирает entries по этому bit, и тем же bit в section selector. Имена user-mode bitfields `_LDR_DATA_TABLE_ENTRY` сюда не переносились.

## 3. От import Host до kernel entry

Источники: [loader-and-boot-snippets.asm](evidence/loader-and-boot-snippets.asm), [winload-loader-path.asm](evidence/winload-loader-path.asm), [LdrpLoadImage.asm](evidence/LdrpLoadImage.asm), [MiInitializeLoadedModuleList.asm](evidence/MiInitializeLoadedModuleList.asm), [kernel-loader-path.asm](evidence/kernel-loader-path.asm).

| PE / RVA | Подтверждённое действие |
|---|---|
| W `0x19D5FB`, LdrpLoadImports | `mov dword [rbp-0x74],0x04000000`: исходные flags **import dependency** |
| W `0x19D81A..0x19D85B` | Flags из local передаются в stack argument `[rsp+0x60]` recursive LdrpLoadImage; альтернативная попытка `0x19D90F..0x19D94E` передаёт их аналогично |
| W `0x19D1CB..0x19D1F3` | LdrpLoadImage передаёт flags `[rbp+0x150]` через `[rsp+0x40]` в BlLdrAllocateDataTableEntry |
| W `0x19DC20`, `0x19DCDA` | Allocator читает flags argument в R14D и записывает `newEntry+0x68 = R14D` |
| W `0x1C310..0x1C33D` | OslpLdrExProcessImportFlags изменяет другой output — import-stage/image flags. Указатель на dependency flags в этом callback не переписывается |
| W `0x1C0AC..0x1C30E` | OslpLdrExProcessImageFlags проверяет dependency bit через R14, но в исследованной функции не очищает его; её image-policy outputs — отдельные pointers |
| K `0xC695A2..0xC695BC` | MiInitializeLoadedModuleList передаёт boot entry в MiConstructLoaderEntry с mode `0x10000000` |
| K `0xAAD995..0xAAD9F3` | Для этого mode копируется common prefix, включая DWORD Flags `+0x68`; последующие исследованные записи не очищают dependency bit |

Это применимо к **новому** импортируемому образу, принятому loader. API Set rewrite меняет выбранный host name, после чего работает тот же recursive import load. Доказанный ранее empty-Host путь, напротив, пропускает image load; он не создаёт здесь fictitious entry.

Для уже найденного entry правило сложнее: W `OslpLdrExProcessEntryFlags` `0x1C04E..0x1C067` сохраняет dependency bit только при его наличии и в запросе, и в существующем entry; иначе маска `0xFBFFFFFF` очищает bit. Аналогичный generic path есть в W `0x19D42F..0x19D450`. Поэтому **сам факт binding не гарантирует нужную classification**, особенно при повторном использовании ранее загруженного образа.

Import dependency не следует путать с названием `CoreExtensionsDriverListHead` в `_LOADER_PARAMETER_BLOCK`. Это отдельный boot-driver list (`+0x60`); исследуемая гарантия опирается на Flags конкретного image entry в image list, не на сходство названий «extension».

## 4. Когда проходит автоматическая очистка

K `Phase1Initialization`, RVA `0x705B30`:

```asm
0x705B91 call Phase1InitializationIoReady   ; 0xC6DC38
0x705B96 xor  edx,edx
0x705B98 lea  rcx,[MiFreeBootDriverInitializationCode] ; 0x7F6610
0x705B9F call MmEnumerateSystemImages      ; 0x4FC280
```

`MmEnumerateSystemImages → MiEnumerateSystemImages` K `0x208A60` проходит `PsLoadedModuleList`, берёт load lock и per-entry loader lock, затем вызывает callback (`0x208ABD..0x208AD8`). Это **общий проход по images**, а не только последствия успешно вызванного DriverEntry.

Callback K `0x7F6614`:

```asm
test dword [rcx+0x68],0x40000000
jne  return
call MiFreeDriverInitialization           ; 0xA53360
```

То есть для некоторого класса entries callback вообще пропускает повторную обработку. Для остальных `MiFreeDriverInitialization` дополнительно проверяет Flags&`0x800` (`0xA53389`), physical/kernel/HAL conditions, затем вызывает selector с R8D=2 (`0xA533B9..0xA533BD`). Эти дополнительные skips не требуются, чтобы доказать INIT exception по dependency bit.

Обычный driver load имеет отдельный явный cleanup: `IopLoadDriver` K `0x94C816` получает `_DRIVER_OBJECT.DriverSection` по `+0x28`, а K `0x94C81A` вызывает `MmFreeDriverInitialization`. Wrapper K `0x94A52C` берёт locks и вызывает тот же Mi helper. Dynamic DLL initialization path также может вызвать MiFreeDriverInitialization (`MmLoadSystemImageEx`, K `0x94E9D2..0x94E9E0`). Следовательно, различие не сводится к «для DLL cleanup никогда не вызывается»: решающим является **отбор секции**.

## 5. Точный INIT exception в MiSnapDriverRange

K `MiSnapDriverRange`, RVA `0xA53568`; relevant normal/default branch вызывается MiFreeDriverInitialization с R8D=2 и R9=NULL. Raw bytes и переходы: [retention-selector-snippets.asm](evidence/retention-selector-snippets.asm), [полная функция](evidence/rva-0xa53568.asm).

```asm
0xA536E2 mov  edx,[rdi]                  ; first DWORD IMAGE_SECTION_HEADER.Name
0xA536F1 mov  ecx,[rdi+0x24]             ; Characteristics
0xA536F4 and  ecx,0x02000000             ; DISCARDABLE
0xA536FA test dword [rax+0x68],0x04000000 ; loader-entry Flags
0xA53701 jne  0xA53999
...
0xA53999 cmp  edx,0x54494E49             ; little-endian bytes I N I T
0xA5399F je   0xA5370F                   ; do not add this section to selected range
0xA539A5 jmp  0xA53707
0xA53707 test ecx,ecx
0xA53709 jne  0xA538DB                   ; generic discardable range path
```

Это сравнение **первых четырёх Name bytes**, не всех восьми. У F Name=`INIT\0\0\0\0`, поэтому совпадение точное. Переход `0xA5370F` может завершить ранее накопленный range соседних sections, но саму эту INIT в него не добавляет. Для F границы разделены страницами; INIT `[RVA 0x8000,0xA000)` (max virtual/raw size 0x2000), Phase2 `0x8360` лежит внутри. Следующие discardable GFIDS/.rsrc/.reloc этим исключением не защищены.

При возврате непустого selected range MiFreeDriverInitialization вызывает K `MiFreeInitializationCode` на `0xA53411`. Та выполняет реальную MM работу, включая `MiDecommitPages` на `0x7F6873` и separate large-page handling; это не просто переименование секции или освобождение списка metadata. Но для F INIT с dependency bit данный normal selector **не возвращает INIT как range**.

| Metadata classification | F INIT в normal selector | Вывод |
|---|---|---|
| Import dependency, Flags&0x04000000!=0 | Не выбирается | Автоматическая initialization cleanup сохраняет этот код |
| Без dependency bit, при отсутствии иных skips | Generic discardable candidate | Одного названия INIT/DISCARDABLE недостаточно для сохранности |
| Другие discardable sections даже с dependency bit | Generic candidates | Это не blanket pin всех sections |

[verify_lifecycle.py](verify_lifecycle.py) выполняет ограниченное воспроизведение **этих фактических ветвей по декодированным bytes K**, на metadata исходных F sections. Он останавливается до MM calls и не исполняет PE. Результат PASS и точные traces — [validation.json](evidence/validation.json). Это не имитация всей Memory Manager и не runtime PTE dump.

## 6. Что не следует смешивать с этим проходом

`MmFreeLoaderBlock` K `0xC5CAD0` обрабатывает loader memory descriptors. Маска `0x204080` на `0xC5CB1E` выбирает types7/14/21: по TPI `_TYPE_OF_MEMORY` это LoaderOsloaderHeap, LoaderStartupDpcStack, LoaderNlsData. Это не правило «освободить INIT всех loaded images» и не выбор LoaderSystemCode9/LoaderHalCode10/LoaderBootDriver11.

`KeFreeInitializationCode` K `0xBEDB10` использует отдельный `MmDiscardDriverSection` path. Его address argument на `0xBEDB2F` равен K+`0xBE6DF0`, то есть находится в **INITKDBG**, а не является адресом F. Ни слово Initialization в имени, ни удаление loader block не доказывают освобождение Phase2.

Сам `MmDiscardDriverSection` K `0xC6A0B0` задаёт address-directed selector (R9=переданный address, `0xC6A101..0xC6A11B`). В MiSnapDriverRange ненулевой R9 выбирает ветвь `0xA536A0 → 0xA5388B`, отличную от normal default branch. INIT exception выше не объявляется защитой от всех явных discard APIs или от выгрузки целого image.

## 7. WNF остаётся достижим после boot; это не означает вызов освобождённого кода

K имеет обычные, сохраняемые PAGE paths:

```text
NtCreateWnfStateName (0x881F00)
  0x88220E → ExpWnfRegisterPermanentName (0x881DC8)
  0x881E24 → ExpWnfGetNameStoreRegistryRoot (0x8816A4)

NtQueryWnfStateData (0x896540)
  0x896C96 → ExpWnfLookupPermanentName (0x8814B8)
  0x88153A → ExpWnfGetNameStoreRegistryRoot
```

[Проверенные call edges](evidence/wnf-late-paths.json); ASM NtCreate/Register приложены. Эти paths не имеют в исследованной цепочке gate «Phase1 ещё не завершена». Это статическая достижимость при допустимых access/lifetime/context inputs, не утверждение, что каждый user-mode процесс может вызвать любой путь.

Root helper читает cached handle и при его отсутствии создаёт key. Только `CreateDisposition==1` (`0xB0F978`) доходит до Phase2 IAT call K `0xB0F996`; callback выполняется **до** `lock cmpxchg` publication на `0xB0F9C5`. Существующий key или уже заполненный cache обычно исключает этот callback. Temporary in-memory names не следует приравнивать к данному permanent/persistent store path.

TPI: `_ESERVERSILO_GLOBALS.WnfSiloState` по `+0x370`; `_WNF_SILODRIVERSTATE.PermanentNameStoreRootKey` по +8 и PersistentNameStoreRootKey по +0x10. Это объясняет чтение silo fields `+0x378/+0x380` в root helper. Well-known root0 хранится отдельно, K `ExpWnfWellKnownNameStoreRootKey=0xFDA340`. Следовательно, boot progress и ленивое создание всех registry stores — разные состояния; нельзя считать все root handles навсегда заполненными только из завершения Phase1.

## 8. Сериализация и контекст — установленные границы

- Cache cmpxchg идёт **после** Phase2; это публикация handle, не lock вокруг callback. ZwCreateKey обеспечивает своё registry-create поведение, но внешнюю глобальную сериализацию shared scratch F этот вызов сам по себе не доказывает.
- В `ExpWnfAllocateNextPersistentNameSequence` root call K `0x881A9D` предшествует acquire sequence-number lock K `0x881AD4`; второй root call `0x881B8C` также раньше acquire `0x881BC8`. TPI называет этот silo lock PermanentNameSequenceNumberLock, offset0x20 внутри WnfSiloState. Нельзя использовать его как доказанный mutex для уже выполненного Phase2.
- `NtCreateWnfStateName` K `0x881F77` уменьшает `_KTHREAD.KernelApcDisable` (TPI offset0x1E4), а `0x882244` вызывает KeLeaveCriticalRegion. Это per-thread APC discipline, не взаимное исключение разных threads и не доказательство IRQL=PASSIVE по одному instruction.
- Внешние access/privilege checks и PreviousMode handling существуют в NtCreate. Они ограничивают вызов, но не заменяют INIT lifetime rule и не устанавливают универсальную сериализацию F scratch.

Удержание INIT и безопасность concurrency — разные выводы. Этот этап закрывает static default-cleanup условие хранения кода; не объявляет весь stateful F module потокобезопасным.

## 9. Предел сравнения поколений

Для F22000 подтверждены section geometry/flags и placement Phase2. Для K26100 подтверждены **его собственные** loader/selector/WNF правила по matching PDB и PE. Kernel22000 в предоставленных архивах отсутствует; ранее найденный additional kernel — 26100.1742. Поэтому идентичность `MiSnapDriverRange`, Flags или boot cleanup22000 не предполагается.

Полученный вывод относится к вопросу «как **этот F image** обрабатывается **этим K/W path26100**». Для доказательства собственного FOS22000 lifecycle нужен соответствующий kernel/winload22000, а не перенос адресов26100.

## Воспроизведение

Из этого подкаталога, Python3.6+ и Capstone:

```powershell
python -B .\verify_lifecycle.py
python -B .\pdb_read.py .\evidence\symbols\ntkrnlmp.pdb
```

PE inputs читаются по paths предыдущего аудита. PDB inputs сохранены локально. [CODE-MAP.csv](CODE-MAP.csv) содержит основные RVA; полные ASM и извлечённые types/publics лежат в evidence. Валидация проверяет hashes, RSDS/DBI binding, symbol anchors, используемые code bytes и selector replay. Она не проверяет заново 26 imports или внешние Phase ABI.
