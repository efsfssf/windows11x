# Результат независимого сравнения

Дата: 2026-11-11. Проверены исходные файлы этого пакета, без исполнения PE и без обращения к VM.

**Да: оригинальные Factory OS 22000.1 и NT 26100 содержат сходный исследованный pipeline kernel extensions / State Separation.** Сопоставлены реальные вызовы, источник аргументов, обработка статусов, передача признака import dependency и исключение INIT из обычной очистки. Это существенно сильнее совпадения имён импортов.

**Совместимость всего `statesepext.sys` 22000 на 26100 не доказана.** Исходная схема 26100 не выбирает State Separation Host. Изменены внутренние загрузочные структуры и реализации NT services. Наличие OSData namespace не доказывает отдельное хранилище; неизменяемость MainOS, persistence, servicing и сериализация scratch не установлены.

Главные новые результаты:

- В **22000 и 26100** принимается `STATUS_NOT_SUPPORTED` после **всех трёх** callback. У 22000 проверки Phase0/1 вынесены в cold code. Предположение об отличии реакции на этот статус снято.
- В **обеих версиях** новый import dependency получает `0x04000000`. Оба ядра читают этот bit в `Flags+0x68` и исключают секцию, первые четыре байта имени которой `INIT`, из исследованной очистки. Само сочетание «Phase2 в DISCARDABLE INIT + поздний WNF» не доказывает use-after-free ни в одной версии.
- Phase0/1 — два упорядоченных boot callback. Phase2 — callback WNF по условию нового registry root (`CreateDisposition==1`), возможный и позднее. Это не безусловная линейная последовательность «0 → 1 → 2».
- WNF descriptors в обеих версиях совпадают по именам и управляющим значениям, включая `\REGISTRY\MACHINE\OSDATA\Notifications`.
- Все 26 импортов разрешаются в обоих ядрах. Для 18 Zw дополнительно сопоставлены **реальные Nt implementations по on-disk service table**, а не только stubs. В таблице семантики: **4 SAME в явно ограниченной области, 22 INCONCLUSIVE**. Доказанного breaking change применяемого внешнего NT API не найдено; эквивалентность 22 сложных функций не заявляется.

Основные материалы:

1. [VERIFIED.md](VERIFIED.md) — идентичность, символы, доказанные утверждения и проверка прежних выводов.
2. [DIFFERENCES.md](DIFFERENCES.md) — COMPATIBLE / INCOMPATIBLE / UNKNOWN и границы каждого результата.
3. [LOADER-LIFECYCLE.md](LOADER-LIFECYCLE.md) — загрузчик, перенос Flags, INIT, различия внутренних структур.
4. [PHASE-CALLGRAPH.md](PHASE-CALLGRAPH.md) — реальные callsites, аргументы, статусы, WNF и контекст.
5. [NT-API-COMPATIBILITY.csv](NT-API-COMPATIBILITY.csv) и [NT-API-DETAILS.md](NT-API-DETAILS.md) — все 26 API.
6. [STATE-DEPENDENCIES.md](STATE-DEPENDENCIES.md) — registry/storage/servicing.
7. [UNRESOLVED.md](UNRESOLVED.md) — недостающие статические и runtime доказательства.
8. [NEXT-READONLY-CHECKS.md](NEXT-READONLY-CHECKS.md) — минимальные пассивные проверки.

Машинные доказательства: [92 инструкции с bytes, RVA и file offsets](analysis/evidence/CODE-EVIDENCE.csv), [метаданные PE](analysis/evidence/image-metadata.json), [26×2 API implementations](analysis/evidence/api-implementation-map.json), [WNF descriptors](analysis/evidence/wnf-descriptor-comparison.json). Полные исследованные функции находятся в `analysis/evidence/*.asm`; методы — в `analysis/tools/`.

Добавлены только материалы анализа. Патчи, изменённые PE, настройки BCD/реестра/CI/Secure Boot и изменения VM не создавались.
