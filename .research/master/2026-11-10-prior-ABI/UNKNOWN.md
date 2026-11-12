# UNKNOWN — что ещё нельзя заключить

## U01 — полная семантическая совместимость NT API22000↔26100

Не предоставлен kernel22000. Дополнительный архив содержит kernel26100.1742, поэтому он не даёт второго поколения для двустороннего disassembly diff. Проверены 26 export names целевого K9457, фактические calls F и используемые layouts/часть Rtl/Ex bodies. Конкретный breaking change среди этих поверхностей не установлен; полная эквивалентность CM/I/O/security behavior не доказана.

Особенно нужны факты о registry symbolic links, rename/access/transactions/callbacks, hive volatility и security descriptors, file overwrite/backup intent и filter callbacks. Общая стабильность публичного NT ABI не гарантирует прежних прав, состояния и policy. Перечень каждой функции — NT-API-SEMANTICS.

## U02 — lifetime INIT и Phase2

Все три exports F находятся в INIT; section Characteristics=`0x62000020` включает DISCARDABLE. Phase2 caller K находится в PAGE и выполняется по demand creation WNF key, не только как безусловная boot phase. Не установлен точный lifetime INIT kernel-extension image и время последнего возможного Phase2 вызова. Если code освобождается раньше такого вызова, это станет блокирующим архитектурным несоответствием. Пока условие не доказано; объявлять его реальным fault нельзя. Размещение F Phase2 в INIT существовало уже в22000; само по себе оно не доказывает новую регрессию26100.

## U03 — concurrency, IRQL и контекст

F registry scratch `0x3068/0x3060` разделяется helper graph и освобождается Phase2. В исследованном F нет локального lock вокруг этого состояния. K WNF publication cmpxchg идёт после callback; он сам по себе не сериализует весь copy helper. Полная внешняя сериализация, рекурсия/callbacks и параллельные store creation не восстановлены.

F использует paged pool и синхронные Zw registry/file calls; требуется соответствующий PASSIVE-compatible контекст. Фактический IRQL на активном режиме не снят. Нельзя вывести его только из INIT или из нормального успеха disabled/fallback пути. Security contexts и privileges для file metadata/SACL также не проверены runtime.

## U04 — ранние devices/hives и правильное состояние

Не предоставлены согласованные boot-time snapshots целевой26100 до Phase0/1 с backing files, active OSBOOT/OSDATA/DEVICES hives, OSDataRoot resolution, HiveRedirectionList и ACL. Native code26100 существует, но зависит от guards и входной loader структуры. Его fallback +0x150→+0xB8 означает, что одни имена OSData не доказывают отдельный writable backend.

F может создать обычные key parents и вернуть success при существующем sentinel или отсутствующем source file. Это не является доказательством корректного монтажа hive и persistence. Статическое исследование не устанавливает реальное содержимое/состояние конкретной VM.

## U05 — весь комплект и защита MainOS

XRo22000 извлечён и по NT именам совместим62/62. FLTMGR26100 в доступном наборе отсутствует; структуры filter callbacks, version behavior и реальные instances не аудированы полностью. SSBoot22000 и onecorestateseparationext22000 доступны, но полного target ntdll/user-mode dependency set26100 нет. Функциональность XRo не следует из одного факта регистрации, а защита MainOS не следует из Phase callbacks.

Не доказано наличие Microsoft servicing-compatible набора этих модулей именно для26100 PC. Старый snapshot retail WIM не является полным поиском по всем продуктам26100; package metadata22000 не является metadata26100.

## U06 — обслуживание, recovery и границы mutable state

В 22000 MUM есть отдельные ServicingMap Files/Keys, security templates, memory-manager config и image-customization components. Полные compressed manifests и их применение целевым servicing stack26100 не восстановлены. Не доказаны update/recovery, согласованность system/OSData hives, reparse/mount boundaries и whitelist behavior. Некоторые архивные manifests имеют бинарный DCM payload; MUM inventory не заменяет их декодированный content.

Имя M:\Data в Factory inventory не доказывает физическую запись в защищённый MainOS; путь может пересекать mount/reparse boundary. Напротив, простое переименование тома в OSData не обеспечивает правильную границу состояния.

## U07 — доверенная интеграция и provenance

Hashes идентифицируют исследованные файлы; они не являются новой проверкой Authenticode/catalog chains и допуска конкретного target boot. Обычный API Set overlay над SEALED здесь не признан механизмом интеграции. Поддерживаемый coherent base/Host набор26100 не предоставлен. Это отдельный gate от ABI и структур, без предложений обхода.

Исторические runtime-отчёты FOS, старой26100 и обновлённой9457 не смешивались в один boot. Ни этот аудит, ни повторный API Set decoder не являются наблюдением активной State Separation или работающей Phase0/1/2 на новом target.
