# Состав пакета / первоисточники / отличия от старых

Основной способ чтения: `00-START-HERE-RU.md` -> `01-MASTER-HANDOFF-RU.md` -> `02-TECHNICAL-AGENTS-AND-REVISION-DETAILS.md` -> `live-logs/...` -> исходные отчёты `reports/` и `sources/`.

- `00-START-HERE-RU.md` — новый чат: важные правила, главный актуальный статус.
- `01-MASTER-HANDOFF-RU.md` — полная цель, environment, WCOS/KERNEL, все проверенные шаги, PDB, BCD, State Separation, современный API Set.
- `02-TECHNICAL-AGENTS-AND-REVISION-DETAILS.md` — самые содержательные результаты внешних ИИ и corrections с точными RVA, версиями, хешами и ограничениями.
- `03-TIMELINE-AND-MISSING-ARTIFACTS.md` — хронология и честный список недоступных первичных источников.
- `04-NEXT-STEP-AND-NO-REPEAT.md` — единый план G0–G7, gates, осмысленный текущий test sprint.
- `05-FIRST-MESSAGE-TO-NEW-CHAT.txt` — готовое первое сообщение, которое можно вставить в новый чат вместе с ZIP.
- `live-logs/USER-OBSERVED-WINDBG-BCD-REGISTRY-2026-11-12.md` — реальные наблюдения пользователя, отдельно от static hypotheses.
- `reports/semantic-26100/` — внешний ИИ 11.11: README, VERIFIED, DIFFERENCES, callgraph, INIT retention, 26 API CSV, dependencies, unknowns.
- `reports/api-v7/` — внешний ИИ 10.11: `V7-STRUCTURES`, `SEALED-SEMANTICS`, `TRACE-EXPLANATION`, decoder.
- `reports/api-rootcause/` — внешний ИИ 09.11: 69 file manifest-derived facts, hypotheses, origin of TraceSmoke V6/V7, old wrong claims corrected.
- `reports/preboot-signing/` — инженерный AI 08.11: winload PDB, scenario1/signing/trust, Phase0, 49/49 tests, NO-GO, MUM/CAB source requirements.
- `reports/legacy-technical/` — все доступные отдельные audits 05–08.11, registry, XRo, ports, Winload, ABI.
- `reference/` — старые project handoff и full registry summaries + исходный `FactoryOS-22000-StateSeparation-runtime-map.json` + старый аудит 08.11.
- `sources/` — архивы оригинальных PE/FOS, full 08.11 technical handoff, INIT-LIFECYCLE 11.11, StateSep Offline Audit 10.11 (санитизированная копия без двух вложенных 20279 asset ZIP с шрифтами), Signing Lab, postboot auditor.
- `old-research/` — исторический текст стороннего ИИ с ошибочными утверждениями, отмечен как ненадёжный.
- `PROJECT-STATE-2026-11-12.json` — машинно-читаемый статус и ссылки на исходники.
- `SOURCE-SHA256-MANIFEST.csv` — перечисление SHA-256 всех файлов пакета (без самого manifest и сводного generated zip).
- `ARCHIVE-CONTENTS.txt` — list с размерами для проверки, что файлы реально вложены.

ПРИВАТНОСТЬ: пакет содержит внутренние сведения о виртуальных машинах, путях, реестре и PE/отладке. Не публиковать открыто и не пересылать посторонним без необходимости. Старые пользовательские дампы могли содержать чувствительные системные данные; в этом пакете основной реестр представлен производными сводками, а не полным старым raw SYSTEM/SAM/SECURITY.

Важная **граница:** отдельный новый тестовый dual APISet 12.11 отсутствовал в mounted `/mnt/data`, поэтому не вложен «как будто оригинал». Его пользователь уже проверил в `C:\Users\fog\Downloads\wcos_new_apiset_26100`; для будущего boot trace запросить именно тот же по SHA256 объект. Нельзя использовать `apisetschema.dll` из корня пакета как замену новой dual-схеме: корневой отдельный файл в исходниках был FOS22000 и НЕ соответствует 26100.
