# Проверенные факты и уровень доказательства

Дата: 2026-11-11. Все адреса далее — **RVA конкретного PE указанной версии**, не VA работающей системы. FOS kernel обозначен K22000, современный kernel — K26100; загрузчики — W22000/W26100; F — исходный `statesepext.sys` 22000.1.

## 1. Идентичность входов

Файлы прочитаны заново; размеры и SHA-256 всех семи входов совпали с `00-BINARY-MANIFEST.csv`. VS_FIXEDFILEINFO обоих FOS kernel/loader: 10.0.22000.1; K26100: 10.0.26100.9457; W26100: 10.0.26100.9444. Метаданные секций и RSDS — [image-metadata.json](analysis/evidence/image-metadata.json); сверка манифеста — [manifest-verification.json](analysis/evidence/manifest-verification.json).

| Вход | Размер | SHA-256 |
|---|---:|---|
| FOS22000/ntoskrnl.exe | 11743560 | `a9b5c0a47f1c4606a94adb3674017cbd35448c3792c4d26f51847a3ffa8c7b7c` |
| FOS22000/winload.efi | 2307288 | `9fb057cb65462624f35b41fe686f71fc03f11e3770fdb81e04900640030ad41d` |
| FOS22000/statesepext.sys | 61752 | `167fda36a71c2aad4c146bc66ae4c83e9d525d008d6abfd8eda0e056c9a96658` |
| FOS22000/apisetschema.dll | 143672 | `49696b4557023ea455aa7731a18467b623292a7c2ec837d8437a9c2dcd9cd498` |
| 26100/ntoskrnl.exe | 13104632 | `d90c69cfa925d42037ef1cdba3ff5b3f13cc14e16060938073b97e396fdde2f2` |
| 26100/winload.efi | 3358408 | `1c1a978448b8e0fc2284c13d6e55fde8bf9827dca432cdc8a9be66c2e729adda` |
| 26100/apisetschema.dll | 194016 | `391a2818fdef14f4a9b6e6ce2a7e7f01d04f943de9ef543ec683927cfe480889` |

Соответствие FOS-хешей файлам работающей Factory OS принято из аттестации пользователя и входного описания. В этой работе VM не опрашивалась. Один точный исходный full image для двух файлов с разных томов, package provenance и статус подписей **не установлены** SHA-256 локального пакета.

## 2. Символы: что совпадает и что не совпадает

Использован локальный кэш Microsoft PDB; сеть не использовалась. Для каждого PE GUID и DBI Age совпадают RSDS. **Info stream Age отличается**, поэтому полного совпадения всех полей идентичности нет; это явно сохраняется, а не скрывается. Символы служат разметкой, основной источник утверждений — bytes PE и независимые адресные связи.

| Объект | GUID | RSDS Age / DBI Age | Info Age |
|---|---|---:|---:|
| K22000 / ntkrnlmp.pdb | `0CA9F8C3-ECA3-61E2-D711-5EE49D60A518` | 1 / 1 | 5 |
| K26100 / ntkrnlmp.pdb | `C29EBFB0-6B78-B3C0-20DC-A66D99713F9E` | 1 / 1 | 6 |
| W22000 / winload_prod.pdb | `B507952F-E1BD-A9F4-B735-EB0DBC808809` | 2 / 2 | 3 |
| W26100 / winload_prod.pdb | `0B1E3F9A-3468-75BB-A8B8-24EFDF33BB09` | 2 / 2 | 4 |
| F / statesepext.pdb | `9245993A-D5B5-ED8D-D3AC-98E18709C703` | 1 / 1 | 3 |

Для kernels независимо совпали PE export RVA и PDB public RVA у `KeBugCheckEx`, `MmLoadSystemImage`, `RtlImageNtHeader`. Из всех одинаково названных anchors совпали 3121/3126 у K22000 и 3311/3317 у K26100; у загрузчиков 416/416 и 476/476, у F 4/4. Несовпадения не удалены из данных: `ExAcquireRundownProtection`, `ExReleaseRundownProtection`, `KeLoaderBlock`, `KeNumberProcessors`, `RtlMoveMemory`; дополнительно `memset` у K26100. Имена могут обозначать разные export/public aliases; точная причина каждого несовпадения отдельно не исследована. По этим несовпадающим anchors выводы о State Separation не сделаны.

Полные пути, SHA-256 PDB, RSDS bytes и результаты anchors: `analysis/evidence/{kf,kn,wf,wn,ss}-symbol-provenance.json`. PDB types для common loader prefix и `_LOADER_PARAMETER_BLOCK` сохранены отдельно. Интерпретация offsets подтверждена инструкциями их чтения/копирования; public types не объявляются описанием всего частного boot entry.

## 3. Непосредственно проверенные утверждения

Все строки таблицы имеют уровень **STATICALLY PROVEN в указанной области**. Bytes/offsets инструкций — [CODE-EVIDENCE.csv](analysis/evidence/CODE-EVIDENCE.csv); полные тела — соответствующие `.asm`.

| ID | Утверждение | 22000: PE / RVA | 26100: PE / RVA | Предел |
|---|---|---|---|---|
| V01 | Новый import dependency начинает с `0x04000000` | W `0xEF82C`: `c7458c00000004` | W `0x19D5FB`: те же bytes | Требуется реально принятый новый образ |
| V02 | Flags передаются recursive loader и записываются в `entry+0x68` | W `0xEFA8A → 0xEFAC8`, `0xEFEE5` | W `0x19D85B`, `0x19DCDA` | Повторное использование entry рассматривается отдельно |
| V03 | Kernel boot entry сохраняет common prefix, включая Flags | K `0xB1605F → 0x771A1F..75` | K `0xC695BC → 0xAAD995..9F3` | Полный private layout не одинаков |
| V04 | INIT исключается при dependency bit26 | K `0x77C355`: `bt eax,0x1A`; `0x77C467`: `cmp edx,0x54494E49` | K `0xA536FA`: `test [rax+0x68],0x04000000`; `0xA53999`: тот же compare | Исследованный selector mode2 и обычная cleanup |
| V05 | Boot cleanup выполняется после callback Phase1 | K `0x8111A9`, затем `0x8111AE` | K `0x705B30` и `0x705B9F` | Не доказательство всех discard/unload путей |
| V06 | Dependency DLL initialization отбирается тем же bit | K `0xB02F88` | K `0xC71BF3` | Не SCM Start и не DriverEntry |
| V07 | Реальные Phase0/1/2 calls | K `0xB0B60B / 0xB10280 / 0x7797B5` | K `0xC294EE / 0xC6DC8E / 0xB0F996` | IAT binding в живой системе здесь не снимался |
| V08 | **Все три** callbacks принимают `0xC00000BB` | K `0xB3CCDE / 0xB3F858 / 0x7797C3` | K `0xC29506 / 0xC6DCA6 / 0xB0F9A4` | Другие negative statuses не становятся success |
| V09 | WNF Phase2 только при `CreateDisposition==1` | K `0x77977B` | K `0xB0F978` | Также нужны cache miss и успешный ZwCreateKey |
| V10 | WNF cache публикуется после callback | K `0x779787`: `lock cmpxchg [rbx],rcx` | K `0xB0F9C5`: те же bytes | Атомарный handle cache не доказывает сериализацию scratch |
| V11 | Есть путь позднего permanent WNF API к Phase2 | K `0x6EE0AD → 0x778884 → 0x7788E0 → 0x779668` | K `0x88220E → 0x881DC8 → 0x881E24 → 0x8816A4` | Статическая достижимость, не наблюдение исполнения |
| V12 | WNF descriptors совпадают семантически | K base `0xA38150`, stride `0x30` | K base `0xB4D6F0`, stride `0x30` | Равенство путей не доказывает одинаковый backing hive |
| V13 | OSData link может использовать boot-device fallback | K `0xB26643 / 0xB26677` | K `0xC18D08 / 0xC18D3C` | Имя OSData не гарантирует физическую изоляцию |
| V14 | Исходные загрузчики имеют guarded DEVICES/OSBOOT attach | W `0x9E9A / 0x9F3E` | W `0x9846 / 0x98E0` | Безусловная загрузка не заявляется |
| V15 | Все 26 импортов F — прямые exports обоих kernels; 18 Zw связаны с реальными Nt телами | K service table `0xCA690` | K service table `0xDADC0` | API resolution не равно полной semantic equivalence |
| V16 | Host в исходных schemas отличается | Schema entry `0x703C`, value `0x186FC`: `statesepext.sys` | Schema family `0x24158`, version `0x28A10`: Host отсутствует, SEALED | Не аудит всех возможных поддерживаемых схем/редакций |

## 4. Что именно исправлено в прежних материалах

1. Предварительное различие error-handling Phase0/1 не подтвердилось: cold code K22000 содержит те же специальные проверки `STATUS_NOT_SUPPORTED`. Проверка только основного диапазона функции дала бы неверный результат.
2. Вывод о сохранении INIT у dependency на 26100 воспроизведён и теперь **независимо подтверждён также для оригинального K22000**. Ранее переносить это правило на 22000 без kernel было нельзя.
3. Довод «Phase2 в DISCARDABLE INIT означает гарантированный UAF после boot» недостаточен: есть отдельный selector predicate. Обратное «образ никогда нельзя выгрузить/освободить» также не доказано.
4. Утверждение о 26 именах усилено сопоставлением двух kernels и реальных Nt bodies. Оно по-прежнему не превращено в утверждение о 26 эквивалентных сервисах.
5. Исторические RVA 26100.1742 из отчёта 2026-11-06 не используются как RVA текущих 26100.9457/9444. Аттестация работающей VM не объединена с прежними экспериментальными boot traces.

## 5. Метод и воспроизводимость

PE32+ разобраны Python reader с проверкой raw/RVA bounds; дизассемблирование — установленный Capstone x64. Индексы code references строятся по `.pdata` ranges; это не обещает покрытия всех leaf/динамических переходов. Важные leaf routines дополнительно разобраны от public/export начала; cold fragments прослежены по прямым ветвлениям. Непрямые CFG вызовы не считаются восстановленными только по имени ближайшего символа.

`KiServiceTable` **в этих on-disk PE содержит прямые RVA**. Все 36 записей (18×2) совпали с соответствующими Nt public bodies. Runtime-формула shifted relative offsets к этим байтам не применялась. Изменения service numbers не использованы как доказательство несовместимости: F вызывает named Zw imports.

Основные команды из корня пакета:

```text
python analysis/tools/compare.py metadata
python analysis/tools/compare.py index kf kn wf wn ss
python analysis/tools/prepare.py
python analysis/tools/research.py apis
python analysis/tools/build_evidence.py
python analysis/tools/api_table.py
python analysis/tools/final_verify.py
```

Для повторного извлечения symbol JSON нужны PDB по сохранённым provenance paths; для проверки bytes, SHA, IAT, schemas и текущих CSV достаточно пакета и сохранённых extracts. `prepare.py` создаёт только `.asm`/JSON в `analysis/evidence`; отдельное `compare.py dis` печатает RVA/file offsets/bytes. Алгоритмическое сведение INIT selector — ограниченная проверка branch predicates на реальных PE section headers, **не исполнение драйвера и не эмуляция MM**.

**OBSERVED** в отчёте относится только к сохранённому историческому runtime-map и входной аттестации, с их ограничениями. **INFERRED** — возможность дальнейшей совместимости при выполнении внешних условий. **UNKNOWN** — всё, для чего нет достаточного статического или runtime доказательства. Новых runtime наблюдений эта работа не создаёт.
