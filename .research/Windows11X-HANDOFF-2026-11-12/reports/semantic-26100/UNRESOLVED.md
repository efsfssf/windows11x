# Точные пробелы и их приоритет

Из неизвестности не делается вывод о несовместимости. Ни один пункт ниже не является предложением патчить или включать режим. Уровень приоритета задаёт порядок получения доказательств, а не план вмешательства в систему.

## Статически незакрытые вопросы

| Приоритет / ID | Что отсутствует | Что уже есть | Какое доказательство закроет вопрос |
|---|---|---|---|
| P0 / S01 | Согласованный State Separation Host/component set для26100 и provenance/поддерживаемая integration | Исходная schema26100 SEALED и без Host; FOS22000 Host известен | Оригинальные package/schema metadata и подписанные компоненты именно целевой версии с их chain/provenance; анализ без изменения schema |
| P0 / S02 | Полный CM semantic diff для используемых create-link/copy/rename/query paths | Реальные Nt bodies обеих версий, service table связи, callbacks и существенные helpers | Замкнутые CmCreateKey/CmOpenKey/CmSetValueKey/CmRenameKey graphs, type/option handling, access checks, callback order, virtualization, hive/backend и errors |
| P0 / S03 | Сериализация всех users общего scratch F | WNF CreateDisposition gate, post-callback cache cmpxchg, globals3060/3068, отсутствие найденного собственного lock | Полный входящий graph Phase2 и всех CopySyncKeyTree users с lock scopes/silo/index cases; доказательство покрытия всех путей |
| P1 / S04 | Все lifetime/discard/unload paths extension image | Доказан ordinary mode2 INIT retention при bit26 в обеих версиях | Аудит explicit discard/unload, refcounts/LoadedImports и pressure paths; не только normal boot cleanup |
| P1 / S05 | Все loader classification paths для существующего entry и policy variants | Новая dependency ветка и clear-bit merge найдены в обоих loaders | Полный alias/reuse/image-policy graph и условия конечного Flags; реальные конечные image entries отдельно |
| P1 / S06 | Полная семантика I/O/security pool/CRT imports | Реальные Nt/Ex/Rtl bodies, primitive contracts, common structure layouts | Конкретные used-option/size/status branches до соответствующих FS/security/allocator consumers; provider binaries и policy context |
| P1 / S07 | Полный activation dataflow policy → loader fields → CM globals | Boolean consumers, loader hives и namespace producers установлены | Все producers и package/default policy inputs, reset/error cases, порядок hive readiness |
| P1 / S08 | Persisted-state negative caching/reset semantics и все consumers | Обе RtlGetPersistedStateLocation implementations доступны и извлечены | Сопоставление точных reset/write sites и callers для file/key mapping, malformed/default/absence paths |
| P2 / S09 | Полный современный enforcement/user-mode/servicing/recovery stack | Исторические FOS map и references; семь первичных PE | Согласованный комплект обеих версий: XRo/FLTMGR/user-mode/package maps/security templates/update-recovery, без подмены версиями из других образов |
| P2 / S10 | Полное совпадение PDB identity/всех anchors | GUID+DBI Age и тысячи anchors match; Info Age и 5/6 anchors не match | Разъяснение происхождения stripped/cache PDB либо идентичный original symbol set. Текущие bytes claims не зависят от неподтверждённых aliases |

18 Zw routines и 8 других импортов не признаны «все изменённые» или «все одинаковые»: в CSV 4 SAME для ограниченных primitives и22 INCONCLUSIVE. Изменение private loader allocation size/offsets, constructor mode и IoInitSystemPreDrivers return handling доказано; не хватает не факта этих изменений, а доказательств их последствий за пределами исследованного hook path.

## Вопросы, требующие реального состояния или уже сохранённых наблюдений

| Приоритет / ID | Недостающее наблюдение | Почему static PE недостаточно |
|---|---|---|
| P0 / R01 | Единый source image/package chain FOS kernel и winload с разных volumes | Одинаковая версия и VM-аттестация hash не задают full image provenance |
| P0 / R02 | Реальные Phase IAT targets, фактически выбранная schema, image identity и loader Flags конкретного Host в26100 | У исходной schema Host пуст; изменённые схемы/старые traces не доказывают текущую привязку |
| P0 / R03 | Backing store OSDataDevice/OSDataRoot и отдельность writable state от MainOS | Код прямо имеет ArcBootDevice fallback; буквы/labels недостаточны |
| P0 / R04 | Реально подключённые DEVICES/OSBOOT/OSDATA и HiveRedirectionList paths/ACL | Native branches conditional; CREATE_KEY не эквивалентен LOAD_HIVE |
| P1 / R05 | Первый/поздние Phase2 calls: index, silo, status, IRQL, locks, code mapping, concurrency | Demand paths и shared scratch существуют; число и пересечение callbacks не следуют из graph |
| P1 / R06 | Согласованный один-boot timeline Phase0/1, hive readiness, WNF и cleanup | Нельзя соединять snapshots разных загрузок в один порядок событий |
| P1 / R07 | Фактические source/target files/registry contents и успешная durable relocation | Success может получиться при sentinel или missing-source conditions |
| P1 / R08 | Проверенные MainOS write enforcement и persistence | Наличие XRo/filter instance/config не является результатом enforcement/persistence проверки |
| P2 / R09 | Реальное update/recovery/servicing поведение согласованной системы | В kernel callbacks этого pipeline нет целиком |

R07–R09 нельзя закрыть одной пассивной текущей фотографией: нужны уже существующие независимые наблюдения либо отдельная будущая авторизованная проверка, выходящая за рамки данного read-only аудита. Здесь такие проверки не выполнялись и не предлагаются как немедленные действия.

## Граница вывода о boot PoC

Статически подтверждена существенная общность механизма, но оснований объявить безопасный boot PoC готовым нет. До такого обсуждения нужны как минимум S01–S03 и R01–R04, затем доказательства контекста/lifetime и согласованного enforcement/storage/servicing набора. Это перечень недостающих доказательств, **не рецепт загрузочной модификации**. Никакие изменения protections, signatures или binding tables в отчёте не предложены.
