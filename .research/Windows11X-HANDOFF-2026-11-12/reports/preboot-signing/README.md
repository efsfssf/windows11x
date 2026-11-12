# Windows11X: инженерный результат 2026-11-08

**Начать с DECISION.md. Вердикт — NO-GO на boot NT26100.9457 по имеющимся артефактам.** Доказаны loader flow, конкретное ограничение Custom Kernel Signers для schema/early host и surface ABI фаз. Рабочий kernel extension и принятый alternative schema не созданы и не заявляются. Код здесь — офлайн-анализ и in-memory модель, не установщик WCOS.

## Содержимое

| Файл | Результат |
|---|---|
| `DECISION.md` | Решение и точный блокер |
| `WINLOAD-26100-9444-CALLGRAPH.md` | Текущие RVA/PDB, schema selection, CI, early host load/bind и handoff |
| `BINDING-OPTIONS.md` | Матрица вариантов и предусловий |
| `PHASE0-ABI-AND-BEHAVIOR.md` | Три ABI, текущие callers, Phase1/2, ограничения совместимости |
| `REGISTRY-PHASE0-MODEL.md` | Helper semantics, TreeSync, ошибки и отсутствие rollback |
| `SOURCES-AND-BOOT-DEPENDENCIES.md` | Официальные источники, CAB identities, ранние зависимости |
| `EVIDENCE-MATRIX.csv` | Проверяемые claims, источники, RVA и falsification conditions |
| `NEXT-BOOT-EXPERIMENT.md` | NO BOOT TEST и один следующий практический шаг |
| `OEM-COMPONENT-REQUEST.md` | Конкретная спецификация поставщику; ничего автоматически не отправлено |
| `TEST-RESULTS.md` | Выполненные проверки и их пределы |
| `src/`, `tests/`, `evidence/` | Исходники, тесты, raw ASM, JSON traces/инвентари |

## Проверка пользователем без WinDbg

Требуется Python3.10+. Основные команды используют только стандартную библиотеку. Открыть обычную командную строку в извлечённой папке этого результата. Администратор не нужен.

1. Выполнить тесты:

```text
python -m unittest discover -s tests -v
```

На Windows три дополнительных CAB-теста могут получить SKIP, если нет libarchive. Основные schema/model/workflow тесты от него не зависят. В analysis-среде все CAB-тесты тоже выполнены.

2. Проверить исходный приложенный пользователем ZIP (указать фактический путь). `work-input` и файл результата должны быть **новыми**, иначе скрипт остановится:

```text
python src/verify_package.py "C:\Downloads\Windows11X-Agent-Engineering-Package-2026-11-08.zip" --work "work-input" --out "my-results\package.json"
```

Команда копирует содержимое только в `work-input`, проверяет три точных SHA-256 и разбирает схемы. Ни Windows registry, ни VM она не открывает. В результате ожидаются counts `984/945/974`, diff предыдущая→новая `33 added/23 removed/1 changed`, rejected Factory schema candidate, итог `NO_BOOT_TEST`. Успешный exit0 означает успешный офлайн-аудит, не разрешение загрузки. Exit2 — ошибка входа/формата; исходные PE остаются нетронутыми.

3. Воспроизвести byte anchors выводов и модель:

```text
python src/verify_witnesses.py work-input
python src/phase0_model.py --rules evidence/phase0-rules-22000.json
python src/phase0_model.py --rules evidence/phase0-rules-22000.json --dev-mode
```

`verify_witnesses` читает exact PE SHA и выбранные instruction bytes. Это replay байтов, не криптографическая проверка подписей. Модель использует только Python dict; `phase0_complete=false` остаётся всегда. В обычном model mode выбраны оба KernelLink, в dev mode только ComputerName. Семантика `BLOCKED_UNKNOWN` и model guards описаны отдельно.

## Инструменты для конкретного нового кандидата

```text
python src/scan_oem_source.py "C:\WCOS26100-Candidate" --baseline "work-input\WCOS-26100-9457\apisetschema.dll" --out "my-results\candidate-inventory.json"
python src/wcoscheck.py candidate "work-input\WCOS-26100-9457\apisetschema.dll" "C:\WCOS26100-Candidate\Windows\System32\ApiSetSchema.dll"
```

Сначала нужен конкретный официальный источник по `OEM-COMPONENT-REQUEST.md`. Scanner читает **извлечённую папку**, не распаковывает/монтирует ISO/WIM/CAB и не вызывает payload. Поля `version_hints`, MUM identity, embedded certificate presence не являются доказательством подписи или применимости.

`candidate` — строгий **профиль минимального изменения проекта**: тот же baseline SHA26100.9278 и 984 контракта, сохранённые flags/aliases/hashed lengths/прочие maps, заменены только два нужных host values. Это bounded parser/policy filter, не полная реализация Windows API Set validation. Он не строит schema PE, не подписывает его и **никогда не выдаёт boot_authorized=true**. Exit3 — кандидат не соответствует этому профилю; exit0 — прошла только структура, оставшиеся trust/ABI/environment gates перечислены в JSON. Полностью согласованный официальный другой baseline требует отдельного профиля приёмки, а не автоматического объявления его негодным по всему WCOS.

## Дополнительная воспроизводимость для инженера

`source_inventory.py` читает CAB через системную libarchive, не извлекает payload и не проверяет CAT signatures. Пример после распаковки вложенного `sources/WSK22000-StateSeparation-boot-CABs.zip` в отдельную папку:

```text
python src/source_inventory.py "path-to-extracted-CABs" > "my-results\cab-inventory.json"
```

`abi_audit.py` требует pefile/capstone и скачанные Microsoft PDB; `evidence/boot-*.py`/`abi-analysis.py` — сохранённые вспомогательные analysis scripts первоначальной среды с её путями. Для обычного воспроизведения достаточно stdlib-команд выше и сохранённых raw instructions. Microsoft runtime PE в результат повторно не включены. Полные PDB не включены; exact URLs, GUID/RSDS/DBI/Info age caveats и hashes сохранены в отчётах. Возврат PDB Info Age, отличного от RSDS, явно отражён, успешный WinDbg exact-match не утверждается.

Compile-only проверка display-only ABI header, если есть C11 compiler:

```text
cc -std=c11 -x c -fsyntax-only src/stateseparation_contract.h
```

Для ABI extraction использовать только закреплённые хешами PE; скрипт откажет на другом NT/extension:

```text
python src/abi_audit.py --nt "work-input/WCOS-26100-9457/ntoskrnl.exe" --extension "work-input/Windows11X-WCOS-TECHNICAL-HANDOFF-2026-11-08/binaries/statesepext.sys" --nt-pdb "ntkrnlmp.pdb" --pdb-parser "evidence/boot-pdb-symbols.py" --output "my-abi-results"
```

## Граница безопасности и доказательств

Ни одного изменения host/PoC01, BCD, trust store, Secure Boot variables, live hives или системных PE этим результатом не выполнено. Не повторялись известные IAT/TESTSIGNING/Secure Boot проверки пользователя. Новые online обращения — официальные источники и Microsoft symbols. Persistence, read-only MainOS и servicing текущей VM не проверялись и не заявляются. Успешные synthetic tests подтверждают инструменты/модель в указанном scope, не раннюю State Separation.
