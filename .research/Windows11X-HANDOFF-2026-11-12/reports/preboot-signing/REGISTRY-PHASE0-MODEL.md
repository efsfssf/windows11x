# Phase0: восстановленная семантика Registry / dry-run

Проверено 2026-11-08 на **statesepext.sys 10.0.22000.1**, SHA-256 `167fda36a71c2aad4c146bc66ae4c83e9d525d008d6abfd8eda0e056c9a96658`. Все адреса здесь — RVA этого PE. Вывод не переносит реализацию 22000 на NT 26100 автоматически. Сырые инструкции: `evidence/registry-full-objdump.txt`; воспроизводимая команда: `objdump -d -Mintel statesepext.sys` (image base `0x1C0000000`, для RVA вычесть эту базу).

## Новые существенные результаты

1. **Отсутствующий SourceNtPath не означает skip.** Helper A сначала открывает **источник**, при любой отрицательной ошибке пытается создать его родителей, затем пустой источник. `0x89DD` — ZwOpenKey(source); `0x89EB` — ветка success; `0x89F4` — ensure parents; `0x8A22` — ZwCreateKey(source, options=0). При ошибке создания возвращается она, а не исходный OpenKey NTSTATUS.
2. **TreeSync — дополнение существующего дерева, а не перезапись mutable values.** Если target существует и TreeSync=0, копирование вообще пропускается (`0x8A77..0x8A9B`). Иначе вызывается `0x1050(source,target,targetCString)` (`0x8A9D..0x8AAC`). Если target уже существует, nonnull третий аргумент устанавливает `copyOwn=0` (`0x1143..0x114E`). Рекурсия `0x11A0` всегда обрабатывает дочерние ключи, но собственные values/DACL копирует только при `copyOwn=1` (`0x13FE..0x1401`). Новый child передаёт `copyOwn=(Disposition == REG_CREATED_NEW_KEY)` (`0x13A8..0x13B4`). Поэтому existing target key сохраняет свои values/DACL, а отсутствующие поддеревья получают исходные values/DACL.
3. **Rollback в исследованных ветках нет.** После синхронизации source получает UUID-имя в том же parent (`0x1630`, ZwRenameKey на `0x1775`), затем исходное имя становится volatile link (`0x17C8`). Ошибка после rename оставляет GUID backup и уже созданные target keys/values; fail path только закрывает handles/освобождает scratch, не переименовывает обратно (`0x8B0B..0x8B59`). Успех также не удаляет GUID backup в этом call graph.
4. **Не все NTSTATUS строго распространяются.** `0x17C8` игнорирует отрицательный OpenKey(source), затем пытается CreateKey. Если OpenKey успешен, ZwDeleteKey вызывается на `0x186E`, **его return не проверяется** перед ZwClose/CreateKey. Ошибки CreateKey (`0x18B7..0x18B9`) и SetValue (`0x1904..0x1912`) возвращаются. Это не транзакционный перенос.

## Наблюдавшиеся правила

Исходник карты: `FactoryOS-22000-StateSeparation-runtime-map.json`, SHA-256 `aff9b37223deddbe6ce813f363ed09ea9057ab8aa23093d47c21967ac60dc814`. Проверено 81 запись и ровно две с ненулевым `KernelLink`. Копия их аудит-манифеста: `evidence/phase0-rules-22000.json`.

| Rule | SourceNtPath | TargetNtPath | AlwaysEnabled | TreeSync |
|---|---|---|---|---|
| AccessibilityLink | `\REGISTRY\MACHINE\System\Accessibility` | `\REGISTRY\MACHINE\OSBOOT\System\Accessibility` | отсутствует | отсутствует |
| ComputerName | `\REGISTRY\MACHINE\SYSTEM\CurrentControlSet\Control\ComputerName` | `\REGISTRY\MACHINE\OSBOOT\SYSTEM\CurrentControlSet\Control\ComputerName` | 1 | отсутствует |

Обе ранние ссылки идут в **OSBOOT**. В модели отсутствующий optional flag представлен нулём; исходная карта сохраняет различие «не задан»/«0». Входной флаг helper A включает проверку AlwaysEnabled; ABI-анализ сопоставляет его с DevelopmentMode, см. `PHASE0-ABI-AND-BEHAVIOR.md`. Из существования этих двух правил нельзя вывести ненужность оставшихся 79 для других стадий.

## Последовательность helper A 0x8644

| RVA | Действие / обработка результата |
|---|---|
| `0x86BC` | Открыть `System\CurrentControlSet\Control\StateSeparation\KeyRedirectionLinks`; ошибка fatal. |
| `0x8702..0x87A3` | ZwEnumerateKey(KeyNodeInformation=1), увеличение scratch buffer при `C0000023` / `80000005`, повтор. Allocation failure -> `C000009A` на `0x8B06`. `8000001A` -> success на `0x8B01`. Другие отрицательные enumeration errors fatal. |
| `0x883E..0x885B` | Query KernelLink; error либо data=0 -> закрыть запись/следующая. |
| `0x8861..0x88A6` | При входном CL!=0 Query AlwaysEnabled; error/0 -> skip. |
| `0x88CF..0x88EF` | Query TreeSync; отрицательная ошибка даёт TreeSync=0 (`cmovns esi,[data]`), а не fatal. |
| `0x8931`, `0x896A` | Query обязательных SourceNtPath и TargetNtPath; отрицательный status fatal. |
| `0x89DD..0x8A38` | Обеспечить source; отсутствующий source создаётся пустым. |
| `0x8A77..0x8AB5` | Если target открылся и !TreeSync: skip copy. Иначе helper `0x1050`; его отрицательный status fatal. |
| `0x8ABB..0x8AC4` | Rename source to UUID name; ошибка fatal. |
| `0x8AD4..0x8ADD` | Создать volatile REG_LINK на старом source; ошибка fatal. |
| `0x8B0B..0x8B59` | Cleanup handles/scratch, возврат сохранённого статуса, без compensating registry writes. |

UNICODE_STRING в `.rdata`: `0x2010` -> AlwaysEnabled; `0x2020` -> TreeSync; `0x2030` -> KernelLink; `0x2040` -> TargetNtPath; `0x2050` -> SourceNtPath; `0x2060` -> полный путь KeyRedirectionLinks. Это подтверждает, что первая операция source, вторая target.

## Helpers: восстановленные границы

**0x1050: open/create + recurse.** Первый аргумент — PUNICODE_STRING source, второй — PUNICODE_STRING target, третий — optional null-terminated target path для ensure-parent. Source открывается с `KEY_READ=0x20019` (`0x10A4`), target с `KEY_ALL_ACCESS=0xF003F` (`0x10E9`). Любая ошибка target-open вызывает попытку ensure-parent (если arg3!=0) и CreateKey(options=0). Target создан заново -> copy own values/DACL; target открылся и arg3!=0 -> preserve own. Отрицательные source-open / ensure-parent / create / recurse statuses возвращаются; handles закрываются.

**0x11A0: рекурсивный merge.** Аргументы HANDLE source, HANDLE target, BOOLEAN copyOwn. ZwEnumerateKey(KeyNodeInformation=1) -> open source child -> ZwCreateKey target child, используя исходный Class, options=0 -> recursion. Затем, если copyOwn, ZwEnumerateValueKey(KeyValueFullInformation=1) и ZwSetValueKey для каждого value (`0x1513`), после этого QuerySecurityObject/SetSecurityObject с SecurityInformation=4 (**DACL**, не owner/group/SACL), `0x1556` и `0x15F4`. Нет удаления лишних target values/children, нет перезаписи own values у existing target при Phase0-вызове. Generic helper `0x1050` с arg3=null может копировать own values в existing target, поэтому это не универсальный запрет overwrite для всех callers. Буферы query/enumerate растут по требуемому размеру; OOM возвращает `C000009A` (`0x1604`). Отрицательные операции прерывают текущий проход и возвращают status; уже записанные данные остаются.

**0x91A4: родители, не mount hive.** Принимает null-terminated full path. Пропускает первые три `\`, затем создаёт префиксы до разделителей через ZwCreateKey(options=0, KEY_ALL_ACCESS), закрывая каждый handle (`0x921C..0x9304`, вызов `0x92AE`). Конечный key без завершающего `\` не создаёт; caller создаёт его отдельно. Лимит loop `0x104` UTF-16 code units (`0x9205`, `0x92DB`). По достижении лимита возврат последнего статуса: **явный STATUS_NAME_TOO_LONG здесь не обнаружен**. Наш offline model блокирует overlong/malformed paths, не имитирует такую частичную обработку. Этот helper не вызывает NtLoadKey и не доказывает, что OSBOOT — загруженный системный hive; модель требует hive root как внешнее предусловие.

**0x1630: UUID rename.** Source open KEY_ALL_ACCESS; ExUuidCreate (`0x16C1`); `STATUS_UUID_LOCAL_ONLY=0x40020056` явно принимается (`0x16CF..0x16D4`), и прочие NT_SUCCESS также проходят. Format в `.rdata` `0x24A0`: `%08lx-%04x-%04x-%02x%02x-%02x%02x%02x%02x%02x%02x`. ZwRenameKey (`0x1775`) меняет последний компонент, не переносит hive. Cleanup закрывает handle; exception handler `0x192C` — security cookie machinery, не registry rollback. Модель использует детерминированное `MODEL-GUID-nnnn`, **не настоящий ExUuidCreate**.

**0x17C8: link.** Source/target строки -> UNICODE_STRING. Source open/delete best effort как выше; CreateKey CreateOptions=3 (`0x1894`, вызов `0x18AB`), то есть VOLATILE|CREATE_LINK. Затем `SymbolicLinkValue` (`.rdata 0x2470`), Type=6 REG_LINK, данные target UTF-16 длиной UNICODE_STRING.Length без завершающего NUL (`0x18D2..0x18F4`). Если SetValue не прошёл, уже созданный link object не удаляется в этом helper. Гонка с повторным появлением source / уже имеющиеся links не моделируются как успешные: `BLOCKED_UNKNOWN`.

**0x9344: ComputerName/hostname.** Это отдельный обязательный шаг Phase0 после успешного helper A, а не часть registry-link модели. Query `...\ComputerName\ComputerName` value ComputerName (`0x9400`, `0x945E`; retry при `80000005`). Ошибка initial open/query fatal. Затем best-effort open `...\ComputerName\ActiveComputerName`: отрицательный open на `0x957C` пропускает SetValue и продолжает Tcpip path; при успешном open ошибка SetValue fatal (`0x959E..0x95AE`). Открыть `Services\Tcpip\Parameters` (`0x9618`) — ошибка fatal; задать REG_SZ Hostname (`0x9666`) и NV Hostname (`0x96B0`), status возвращается. Длина строки включает NUL (`0x9507..0x9515`). Особенность: OOM динамического query-buffer на `0x9498` уходит на cleanup со старым ebx (последний `STATUS_BUFFER_OVERFLOW`), отдельной подстановки C000009A в этой ветке нет. Модель **не заявляет** реализации helper B.

## Модель, тесты и граница результата

`src/phase0_model.py` работает только с Python dict; даже в Windows не импортирует winreg/ctypes, не монтирует hive, не загружает драйвер. Она реализует проверенную структуру решений helper A и наблюдаемые частичные изменения, а не точную эмуляцию Configuration Manager. NTSTATUS fault injection проверяет propagation. Поля `phase0_complete=false` и `rollback_performed=false` сохраняются всегда; `helper_a_complete=true` — только успешная симуляция одного правила или skip, не доказательство ядра.

Дополнительные **консервативные ограничения модели**, не поведение оригинала: отсутствующий hive root; path >=260; malformed DWORD; ancestor-overlap source/target; existing link; гонка source после rename -> `BLOCKED_UNKNOWN`. Sorted child enumeration — детерминизация модели; настоящий порядок enumeration не обещается. ACL/DACL и Class — opaque test data, Windows access checks и self-relative SECURITY_DESCRIPTOR не эмулируются. Не моделируются query-value buffer corruption, malformed value types, memory IRQL, exception semantics и полное состояние Phase0/1/2.

Проверка без WinDbg, Python 3.10+:

```text
python -m unittest discover -s tests -p test_phase0_model.py -v
python src/phase0_model.py --rules evidence/phase0-rules-22000.json
python src/phase0_model.py --rules evidence/phase0-rules-22000.json --dev-mode
```

Фактический результат: **18/18 tests PASS**, `evidence/phase0-test-results.txt`. Два реальных правила проходят in-memory simulation в обычном режиме; в `--dev-mode` AccessibilityLink пропускается, ComputerName выбран. Трейсы `phase0-demo.json` и `phase0-demo-devmode.json` не содержат live hive state: seed — только пустые искусственные SYSTEM/OSBOOT roots.

Microsoft API definitions (проверено web 2026-11-08):

- [ZwCreateKey](https://learn.microsoft.com/en-us/windows-hardware/drivers/ddi/wdm/nf-wdm-zwcreatekey): flags VOLATILE/CREATE_LINK, disposition new/existing, NTSTATUS.
- [ZwSetValueKey](https://learn.microsoft.com/en-us/windows-hardware/drivers/ddi/wdm/nf-wdm-zwsetvaluekey): value type/data arguments.
- [ZwQuerySecurityObject](https://learn.microsoft.com/en-us/windows-hardware/drivers/ddi/ntifs/nf-ntifs-zwquerysecurityobject) и [SECURITY_INFORMATION](https://learn.microsoft.com/en-us/windows-hardware/drivers/ifs/security-information): DACL semantics.

Доверенную загрузку совместимого extension эти инструменты не создают. Новый registry результат объясняет, почему нельзя включать Policy\Enabled=1 и рассчитывать на безопасный откат: оригинальная Phase0 имеет частичные необратимые в пределах вызова изменения, а live OSBOOT и matching boot/servicing pipeline пока являются независимыми гейтами.
