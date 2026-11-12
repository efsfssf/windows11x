# NEXT EVIDENCE — минимальные данные для окончательного runtime/межпоколенного вывода

## 1. Для normal/new-import пути26100 VM больше не нужна

Уже установлена цепочка: W+0x19D5FB → entry.Flags+0x68 → kernel common-prefix copy → post-Phase1 enumerate → MiSnapDriverRange dependency/INIT exception. Static question «существует ли удержание INIT для этого механизма» закрыт утвердительно. Повторять 26-import/Phase ABI проверки или старый TraceSmoke ради него не требуется.

## 2. Чтобы подтвердить именно фактический Host, достаточно небольшого read-only snapshot

Только из уже существующей подходящей конфигурации или сохранённого dump; этот этап не создаёт её и не предлагает boot modifications.

| Данные | Конкретная структура / адреса | Что различают |
|---|---|---|
| Identity image и module entry | Actual loaded image hash/version; PsLoadedModuleList, `_KLDR_DATA_TABLE_ENTRY` K9457 size0xA0 | Тот ли Host/PE исследуется |
| Classification перед/после cleanup | Entry+0x30 DllBase, +0x40 SizeOfImage, +0x68 Flags, +0x6C LoadCount | Реально ли сохранён dependency bit, не переиспользован ли иной entry |
| Этап cleanup | До K+0x705B9F и после K+0x705BA4 в одной session | Удержание после общего image pass, а не только наличие до него |
| Phase2 target и mapping | Kernel IAT slot K+0x150370; target expected HostBase+0x8360; section pages HostBase+[0x8000,0xA000) | Сохранился ли IAT target и исполняемый/resolvable image mapping |

Читать фактические PE headers и fields по matching PDB; фиксированные RVA допустимы только для K9457/W9444/F22000 с указанными hashes. PTE Valid=0 не считать автоматически освобождением: требуется различать paged-out/prototype/transition state от decommitted mapping. Символический адрес без чтения mapping/code также недостаточен.

## 3. Для закрытия вопроса actual late Phase2

Нужны существующий trace/dump или наблюдение штатной нагрузки: timestamp относительно cleanup, call stack на K+0xB0F996, root kind (0/1/2), silo identity, cached handle до вызова, CreateDisposition и выбранные source/target descriptors. Достаточно зафиксировать реальный запрос; специально создавать/удалять keys или подменять return values не нужно.

Если такого callback не наблюдалось, это не доказательство отсутствия late path. Если наблюдалось после cleanup с сохранённым mapping, это закроет практическую lifetime-проверку, не доказывая всю семантику Phase2.

## 4. Для сравнения Factory OS22000

Нужны оригинальные kernel22000.1 и соответствующий winload22000 с проверенными RSDS/PDB из того же Factory набора. Минимум для сравнения bytes:

- соответствия loader entry layouts и import-dependency flags;
- selector, исключающий либо выбирающий INIT;
- automatic cleanup callers/порядок относительно WNF;
- per-silo root cache, create disposition gate и синхронизация.

Matching PDB самого statesepext22000 недостаточен: он не содержит реализацию Memory Manager22000. Эти kernel/winload22000 в ранее проверенном COMPLETE-HANDOFF отсутствуют.

## 5. Для сериализации и полного image lifetime

Потребуются external lock/context evidence для Phase0/Phase2 и WNF paths: IRQL, APC state, caller locks, concurrent store/silo activity и ownership shared scratch. Если есть explicit discard/unload, нужны его caller, target address/range, image classification и dependency references. Без такого события не следует объявлять его причиной освобождения.

Все следующие действия остаются чтением файлов, dumps и существующих наблюдений. Изменения PE/SYS, loader flags, IAT, registry, BCD или защитных политик не являются частью плана.
