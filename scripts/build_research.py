"""Build the offline research catalogue from the supplied handoff and AI archives.

The script copies text reports only. PE images, VM data and nested archives stay in
the source ZIP/RAR files and are never included in the web catalogue.
"""

from pathlib import Path
import json
import re
import shutil

ROOT = Path(__file__).resolve().parents[1]
EXTRACTED = ROOT / ".research"
AI = EXTRACTED / "ai" / "JSON_Results_From_AI"
HANDOFF = EXTRACTED / "Windows11X-HANDOFF-2026-11-12"
MASTER = EXTRACTED / "master"
LIBRARY = ROOT / "library"

AI_NOTES = {
    "WCOS-Windows11X-research-2026-11-05.json": ("Истоки проекта: Santorini 20279, цель WCOS 26100 и первые ограничения.", "Архитектура"),
    "Windows11X-26100-Boot-StateSeparation-first-pass-2026-11-05.json": ("Первый статический проход по XRo и State Separation; совпадение импортов ещё не означает совместимость.", "Загрузка"),
    "Windows11X-26100-Boot-StateSeparation-first-pass-2026-11-05.md": ("Первый проход по XRo: 62 имени импортов найдены, работоспособность не доказана.", "Загрузка"),
    "Windows11X-Core-26100-research.md": ("Ранняя карта архитектуры Core OS и источников для будущего 26100.", "Архитектура"),
    "WINDOWS11X-PROJECT-CONTEXT.md": ("Цель проекта и правила чтения ранних результатов от 5 ноября.", "Архитектура"),
    "Windows11X-WCOS-HANDOFF-2026-11-05-COPY-TO-NEW-AI.txt": ("Раннее сообщение для передачи контекста следующему исследователю.", "Передача"),
    "Windows11X-WCOS-HANDOFF-2026-11-05-LATEST-FULL.json": ("Большой структурированный снимок работ на 5 ноября; статус обновлён позднейшими исследованиями.", "Передача"),
    "Windows11X-WCOS-HANDOFF-2026-11-05-LATEST-READ-ME.md": ("Подробный ранний handoff. Оценка 70% касалась субъективной готовности исследования, не ОС.", "Передача"),
    "WCOS-ApiSet-StateSeparation-22000-vs-26100.md": ("Сравнение старых V6 схем: FOS 945 контрактов, 26100.1742 — 974.", "API Set"),
    "WCOS-DEVICES-Registry-Comparison-22000-26100-2026-11-06.md": ("Что находится в DEVICES Factory OS и чем отличается экспорт обычной 26100.", "Реестр"),
    "WCOS-NT26100-INIT-and-Phase2-unwind-2026-11-06.md": ("Ранние наблюдения по INIT и Phase2; позже уточнены сравнением двух настоящих ядер.", "Фазы"),
    "WCOS-Registry-Comparison-22000-vs-26100-2026-11-06.md": ("Сравнение экспортированных веток реестра; это статические данные, не события загрузки.", "Реестр"),
    "WCOS-Registry-Comparison-22000-vs-26100-DEVICES-2026-11-06.md": ("Расширенное сравнение OSBOOT, DEVICES и SYSTEM по экспортам.", "Реестр"),
    "WCOS-Statesepext-ABI-and-Loader-Audit-2026-11-06.md": ("Статический анализ старого statesepext и вызовов загрузчика.", "Фазы"),
    "WCOS-Winload-Kernel-Extensions-22000-vs-26100-2026-11-06.md": ("Сравнение загрузчиков и механизма расширений ядра на ранних ревизиях.", "Загрузка"),
    "Windows11X-FullRegistry-Architecture-Comparison-22000-vs-26100-2026-11-06.md": ("Полный разбор экспортов реестра FOS и 26100: ульи, правила, backing stores.", "Реестр"),
    "Windows11X-FullRegistry-Architecture-Summary-2026-11-06.json": ("Структурированные числа по OSBOOT, DEVICES, SYSTEM и State Separation.", "Реестр"),
    "Windows11X-26100-StateSeparation-Binding-Gate-2026-11-07.md": ("Почему отсутствие принятого Host не закрывается одним переключателем реестра.", "API Set"),
    "Windows11X-APISet-22000-vs-26100-verified-2026-11-07.md": ("Проверенная разница между FOS API Set и старой схемой 26100.", "API Set"),
    "Windows11X-FactoryOS-22000-CAB-Analysis-2026-11-07.md": ("Инвентаризация CAB Factory OS 22000; пакеты не являются готовым комплектом 26100.", "Источники"),
    "Windows11X-WCOS-Verified-Audit-and-Plan-2026-11-07.md": ("Сверенный план и статус до исследований V7 и новой dual схемы.", "План"),
    "Windows11X-26100-BaseApiSetSchema-verified-2026-11-08.md": ("Доказано, что winload умеет выбирать альтернативный базовый PE через BaseApiSetSchema.", "API Set"),
    "Windows11X-26100-PreBoot-Binding-Checkpoint-2026-11-08.md": ("Условия безопасного предзагрузочного эксперимента и границы подписывания.", "Загрузка"),
    "Windows11X-Phase0-Helpers-and-APISet-2026-11-08.md": ("Работа Phase0 с реестром и различие BaseApiSetSchema и overlays.", "Фазы"),
    "Windows11X-Phase0-Port-Contract-2026-11-08.md": ("Минимальный контракт переноса Phase0; это не готовый порт драйвера.", "Фазы"),
    "Windows11X-Winload-PDB-Verified-Findings-2026-11-08.md": ("Проверенные PDB символы winload и привязка к конкретным бинарным ревизиям.", "Загрузка"),
    "Windows11X-APISet-26100-TraceSmoke-Verified-Checkpoint-2026-11-10.md": ("Реальный WinDbg TraceSmoke: альтернативная база читалась, legacy Host пропал после композиции.", "API Set"),
    "Windows11X-WCOS-Research-Master-Checkpoint-2026-11-11.md": ("Сводка результатов 5–11 ноября с границами доказанного и открытыми вопросами.", "Передача"),
}

KEY_NOTES = {
    "api-rootcause/ROOT-CAUSE.md": "Причина старого TraceSmoke: V6-only Host не сохранялся в итоговой V7 композиции; детали конкретной загрузки частично реконструированы.",
    "api-rootcause/MISCONCEPTIONS.md": "Исправления старых ошибок про HashFactor, SEALED и смешение разных загрузок.",
    "api-v7/SEALED-SEMANTICS.md": "SEALED ограничивает overlay существующего контракта, но не запрещает Host в исходной base schema.",
    "api-v7/TRACE-EXPLANATION.md": "Раздельный разбор двух TraceSmoke загрузок и итогового пустого Host.",
    "preboot-signing/DECISION.md": "При имеющихся компонентах 8 ноября не было доверенного пути для полноценного раннего binding.",
    "preboot-signing/WINLOAD-26100-9444-CALLGRAPH.md": "Цепь выбора схемы, проверки образа, resolve Host и загрузки ранней зависимости.",
    "semantic-26100/README-RESULTS.md": "Независимое сравнение FOS 22000 и NT 26100: общая форма фаз сохранена, перенос модуля не доказан.",
    "semantic-26100/DIFFERENCES.md": "Классификация совместимых фрагментов и оставшихся неизвестных без обещания полного boot.",
    "semantic-26100/NT-API-DETAILS.md": "Из 26 импортов 4 SAME в ограниченном scope, 22 INCONCLUSIVE.",
    "2026-11-11-init-lifecycle/VERIFIED.md": "INIT нового import dependency сохраняется в обычной очистке при 0x04000000; прочие пути жизни не закрыты.",
    "2026-11-10-prior-ABI/VERIFIED.md": "Промежуточный ABI аудит: 26 имён и форма трёх фаз, до полноценного сравнения с K22000.",
    "extras/00-ROADMAP.md": "Инженерные gates G0–G7 и реальные критерии перехода.",
    "extras/01-WINLOAD-HIVE-GATES-FIRST-PASS.md": "DEVICES/OSBOOT attach branches найдены в обеих версиях; producers и runtime остаются открытыми.",
    "handoff/01-MASTER-HANDOFF-RU.md": "Актуальный главный источник статуса на 12 ноября, включая новую dual V6+V7 схему.",
    "handoff/04-NEXT-STEP-AND-NO-REPEAT.md": "Последовательные gates и список проверок, которые уже не нужно повторять.",
    "lab/README-RU.md": "Точный лабораторный пакет нового dual V6+V7 файла: изменения, ограничения и проверка.",
    "lab/verification-report.json": "Машиночитаемый отчёт: 984 контракта, 2 новых Host, SHA и статус OFFLINE ONLY.",
    "lab/v7-decoder-confirmation.json": "Подробное декодирование embedded V7 нового кандидата.",
    "lab/build_dual_schema.py": "Исходный код сборщика dual схемы из hash-pinned оригинала.",
    "lab/Verify-Offline-APISet.ps1": "Скрипт офлайн-проверки SHA, V6/V7 записей и Host; не загружает DLL.",
    "lab/v7_decode.py": "Ограниченный декодер структур embedded V7 для технической проверки.",
}


def decode(path):
    raw = path.read_bytes()
    try:
        return raw.decode("utf-8-sig")
    except UnicodeDecodeError:
        return raw.decode("cp1251", errors="replace")


def date_of(name):
    hit = re.search(r"2026[-_](11)[-_](0[5-9]|1[0-2])", name)
    if hit:
        return "2026-11-" + hit.group(2)
    if "11-05" in name or "PROJECT-CONTEXT" in name or name == "Windows11X-Core-26100-research.md":
        return "2026-11-05"
    if name == "WCOS-ApiSet-StateSeparation-22000-vs-26100.md":
        return "2026-11-06"
    if name.startswith("2026-11-"):
        return name[:10]
    raise ValueError("Date missing for " + name)


def title_of(content, filename):
    for line in content.splitlines():
        if line.startswith("# "):
            return re.sub(r"[`*_]", "", line[2:]).strip()
    return filename.rsplit(".", 1)[0].replace("-", " ")


def excerpt(content):
    for line in content.splitlines()[1:]:
        line = line.strip()
        if len(line) > 65 and not line.startswith(("#", "|", "-", "```", "{", "[")):
            return re.sub(r"[`*_]", "", line)[:225].rstrip() + ("…" if len(line) > 225 else "")
    return "Подробный исходный отчёт с данными и границами выводов."


def add(records, source, rel, group, topic=None, note=None, day=None):
    content = decode(source)
    dest = LIBRARY / rel
    dest.parent.mkdir(parents=True, exist_ok=True)
    dest.write_text(content, encoding="utf-8")
    key = rel.replace("\\", "/")
    if topic is None:
        low = key.lower()
        topic = ("API Set" if any(x in low for x in ("api", "v7", "trace", "seal")) else
                 "Фазы" if any(x in low for x in ("phase", "init", "semantic", "abi")) else
                 "Реестр" if any(x in low for x in ("hive", "registry", "devices", "osboot")) else
                 "Загрузка" if any(x in low for x in ("winload", "sign", "binding", "boot")) else "Архитектура")
    if day is None:
        day = date_of(key)
    records.append({
        "id": key.replace("/", "--").replace(".", "-"),
        "date": day,
        "title": title_of(content, source.name),
        "summary": note or KEY_NOTES.get(key) or excerpt(content),
        "topic": topic,
        "group": group,
        "path": "library/" + key,
        "format": source.suffix.lower().lstrip("."),
        "content": content,
    })


def main():
    records = []
    for source in sorted(AI.iterdir()):
        if source.suffix.lower() not in {".md", ".json", ".txt"}:
            continue
        note, topic = AI_NOTES[source.name]
        add(records, source, "ai/" + source.name, "JSON_Results_From_AI", topic, note, date_of(source.name))

    reports = HANDOFF / "reports"
    report_dates = {"api-rootcause": "2026-11-09", "api-v7": "2026-11-10",
                    "preboot-signing": "2026-11-08", "semantic-26100": "2026-11-11"}
    for folder, day in report_dates.items():
        for source in sorted((reports / folder).iterdir()):
            if source.suffix.lower() in {".md", ".csv"}:
                add(records, source, folder + "/" + source.name, "Технический отчёт", day=day)

    for folder, day in (("2026-11-11-init-lifecycle", "2026-11-11"),
                        ("2026-11-10-prior-ABI", "2026-11-10")):
        for source in sorted((MASTER / folder).iterdir()):
            if source.suffix.lower() in {".md", ".csv"}:
                add(records, source, folder + "/" + source.name, "Архив Master 11.11", day=day)

    extras = ROOT.parent / "Windows11X-WCOS-Roadmap-and-HiveGate-Audit-2026-11-11"
    for filename in ("00-ROADMAP.md", "01-WINLOAD-HIVE-GATES-FIRST-PASS.md"):
        add(records, extras / filename, "extras/" + filename, "Дополнение 11.11", day="2026-11-11")

    for filename in ("00-START-HERE-RU.md", "01-MASTER-HANDOFF-RU.md",
                     "02-TECHNICAL-AGENTS-AND-REVISION-DETAILS.md",
                     "03-TIMELINE-AND-MISSING-ARTIFACTS.md", "04-NEXT-STEP-AND-NO-REPEAT.md"):
        add(records, HANDOFF / filename, "handoff/" + filename, "Актуальный handoff", "Передача", day="2026-11-12")
    add(records, HANDOFF / "live-logs" / "USER-OBSERVED-WINDBG-BCD-REGISTRY-2026-11-12.md",
        "handoff/USER-OBSERVED-WINDBG-BCD-REGISTRY-2026-11-12.md",
        "Актуальный handoff", "Наблюдения", "Пользовательские выводы WinDbg, BCD и Object Manager; разные загрузки разделены.", "2026-11-12")

    for source in sorted((EXTRACTED / "lab").iterdir()):
        if source.suffix.lower() in {".md", ".json", ".py", ".ps1"}:
            add(records, source, "lab/" + source.name, "Лаборатория 12.11", "API Set", day="2026-11-12")

    records.sort(key=lambda r: (r["date"], r["group"], r["title"]))
    output = "window.WCOS_RESEARCH = " + json.dumps(records, ensure_ascii=False, separators=(",", ":")) + ";\n"
    (ROOT / "research-data.js").write_text(output, encoding="utf-8")
    print("Built", len(records), "reports;", len([r for r in records if r["group"] == "JSON_Results_From_AI"]), "from JSON_Results_From_AI")


if __name__ == "__main__":
    main()
