# Фактические результаты проверки — 2026-11-08

**49/49 unittest PASS, 0 skipped** на анализирующем Linux/Python3.12 хосте. Полный вывод: `evidence/all-unit-tests.txt`. Модель Helper A —18, PE/schema/candidate profile —19, workflow/source scanner —9, реальный CAB reader на synthetic CAB —3. Windows без libarchive пропустит только три дополнительных CAB-теста.

| Проверка | Фактический результат | Предел |
|---|---|---|
| Original outer ZIP | Оба приложенных файла побайтно идентичны; SHA256 `a2957a86a84e03ef6da5c79732e0f78f1df0ed82b5ef8646454372469555909d` | Это идентичность пакета, не подпись Microsoft |
| Три текущих PE SHA | Все совпали с exact заданием | Никаких live VM проверок |
| Fresh ZIP extraction + verify_package CLI | exit0; новый `replay-input`, JSON `fresh-extraction-verification.json` | Выход0 означает завершённый аудит, итог NO_BOOT_TEST |
| Schema reader | current984 / Factory945 / previous974; все v6 hash table entries соответствуют именам | Bounded reader, не полная Windows loader validation |
| Previous→current diff | 33 added /23 removed /1 changed | Сравниваются все flags/aliases/hosts/hashed lengths |
| Factory schema как current candidate | Rejected | 945→984 и несогласованные остальные entries |
| Exact two-host synthetic candidate | Structure can pass; boot_authorized=false | Не создавался PE или trusted boot artifact |
| Independent PE/schema review | pefile совпал для imports/exports/headers; второй parser подтвердил984 records | Отдельный reviewer; см. INDEPENDENT-REVIEW |
| In-memory Helper A model | Missing-source/create, existing-target, TreeSync, fault propagation, partial effects/no rollback PASS | Helper B, native CM/ACL/IRQL/INIT lifetime не эмулируются |
| Actual source CAB inventory | 8/8 readable; MUM22000.1 amd64; member sizes/hashes записаны | DCM/PA30 manifests не декодированы, CAT signatures не проверены |
| OEM source directory scanner | Завершён без ошибок на предоставленном handoff; учитывает ssboot.exe/spaceutil.exe | Metadata не заменяет trust/applicability; regression coverage добавлена |
| ABI C11 header | `cc -std=c11 -x c -fsyntax-only src/stateseparation_contract.h` exit0 | Только layout declarations, не драйвер |
| ABI extraction | 26/26 import names есть; новые callers/types/WNF descriptors сохранены | Не сертификация переносимости22000 |
| Byte anchors | 11/11 exact SHA+byte witnesses matched | Не исполнение кода и не проверка trust |

Матрица доказательств: **61 строк** в `EVIDENCE-MATRIX.csv`, с direct/inference scope и условиями опровержения. Все RVA привязаны к указанным в соответствующей строке бинарникам. Старые runtime VA не использованы как новые RVA.

Воспроизводимость основных результатов:

```text
python -m unittest discover -s tests -v
python src/verify_package.py ORIGINAL-ENGINEERING-PACKAGE.zip --work new-input --out new-results/package.json
python src/verify_witnesses.py new-input
python src/phase0_model.py --rules evidence/phase0-rules-22000.json
```

Не выполнялись: установка/загрузка kernel code, boot PoC, изменение VM/BCD/EFI/trust, подключение live hives, включение StateSeparation policy, тесты persistence/MainOS enforcement/servicing. Ни один из этих результатов не называется проверенным. Не повторялись пользовательские live IAT/Secure Boot/TESTSIGNING проверки.
