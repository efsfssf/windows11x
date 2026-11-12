# NEXT EVIDENCE — минимальные следующие доказательства

Порядок выбран по способности различить «ABI-кандидат» и действительный архитектурный блок. Это список свидетельств для исследования, не инструкция установки, включения policy, патча или обхода boot protection.

| Приоритет | Недостающее свидетельство | Что проверить офлайн | Как изменится вывод |
|---|---|---|---|
| 1 | **Оригинальный ntoskrnl.exe22000.1** того же Factory набора, желательно соответствующий PDB | Сверить hash/version/RSDS; сопоставить callers трёх Phase и используемые реализации 26 API, включая CM/I/O policy paths | Позволит превратить U01 в конкретные VERIFIED deltas или INCOMPATIBLE conditions; экспортов/строк из отчёта недостаточно |
| 2 | Правило lifetime kernel-extension INIT для FOS и целевого K9457 | Восстановить load/discard flags и kernel initialization-section reclaim path; сопоставить с WNF Phase2 demand call graph | Возможен реальный архитектурный запрет при доказанном вызове после discard; либо снятие главного lifecycle риска |
| 3 | Подлинный package/feature/component inventory26100 для предназначенной split-state платформы | Проверить manifests, base/Host identities, user-mode helper, XRo/SSBoot или их штатные замены, matching catalog/servicing dependencies | Отличит отсутствующий компонент конкретного retail образа от отсутствия всей подходящей конфигурации |
| 4 | Исходные target hives/loader-state evidence до Phase0/1, полученные ранее или как read-only dump | Сверить policy/rules/HiveRedirectionList, active hives и backing files, loader OSData input, OSDataRoot resolution и security descriptors | Проверит реальные предусловия вместо успеха disabled/fallback callback |
| 5 | FLTMGR26100, ntdll26100 и соответствующие компонентные manifests | Продолжить отдельный аудит XRo callback ABI и SSBoot/user-mode dependencies; не ограничиваться 62/62 NT именами | Закроет часть U05; не гарантирует write protection без доказательства instances/policy |
| 6 | Декодированные servicing/security/layout manifests22000 и сопоставимые26100 | Сравнить schema/config version, ServicingMap rules, hive migration, recovery и mount/reparse requirements | Установит условия persistence/update либо конкретные несовместимые правила |

В уже проверенном `E:\WCOS\JSON_Results_From_AI\Windows11X-WCOS-2026-11-05-COMPLETE-HANDOFF.zip` **нет kernel22000** и нет coherent State Separation package set26100. Повторно распаковывать его ради этих двух входов не требуется. Он дал XRo/SSBoot22000, readable MUM/FeatureManifest и более ранние26100 PE; они сохранены в evidence.

Для пунктов1–3 VM не нужна. По возможности следует использовать уже имеющиеся файлы и dumps. Лишь когда офлайн-граф не позволяет установить U02/U03/U04, полезно наблюдение **уже существующей штатной активной конфигурации**: фактические Phase entry arguments/IRQL, call stack, section residency, loader entry flags, active hive backing и namespace targets. Такое наблюдение не должно требовать подмены return values, записи в registry/BCD или создания modified image.

Отдельный PASS для будущего результата должен означать одновременно: нужный accepted Host действительно выбран; ABI и code lifetime согласованы; callback вызывается в допустимом контексте; ранние mutable stores корректно подготовлены; MainOS protection и servicing имеют собственные доказательства. Успех одного import binding или EAX=0 от Phase1 этого не заменяет.
