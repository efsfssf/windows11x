# Windows11X — BaseApiSetSchema в winload 26100: проверено
Дата: 2026-11-08. Источник: winload.efi Windows 26100.1742 из ранее полученного 26100-WCOS-binaries.zip, публичные символы из Symbols-Direct.zip; реестр PoC01 — вывод пользователя. Только офлайн-анализ, VM не изменялась.

## Проверенный путь
- PDB: `OslHiveGetBaseApiSetSchemaName`, RVA `0x453C`.
- Функция вызывает `CmpLoadApiSetSchemaExtensionsNode` (RVA `0x23C8B8`), затем `CmpFindValueByName` (RVA `0x23D0D0`) с UNICODE_STRING `BaseApiSetSchema` (структура RVA `0x2BA158`), затем `CmpValueToData` (RVA `0x23D264`).
- `CmpLoadApiSetSchemaExtensionsNode` проходит три вложенных имени: `Control` (UNICODE_STRING RVA `0x2B9EC8`), `Session Manager` (`0x2BA2C8`), `ApiSetSchemaExtensions` (`0x2BA188`). Следовательно, ищется значение в SYSTEM\ControlSet...\Control\Session Manager\ApiSetSchemaExtensions.
- `OslLoadApiSetSchema`, RVA `0x1EAE4`: вызов `OslHiveGetBaseApiSetSchemaName` по адресу `0x18001EB2D`; использование `cmovne rdx,rbx` при выборе строки для `BlAppendUnicodeToString`, RVA `0x1EB49`. Строка по умолчанию `ApiSetSchema.dll` (RVA `0x28F5B8`). Это реальный выбор **имени базового файла**, а не доказанный механизм расширения SEALED-контрактов.
- В живой PoC01 команда `reg.exe query HKLM\SYSTEM /s /f BaseApiSetSchema` вернула `0 match(es)`, значит указанной конфигурации нет. В показанном фрагменте найдены только штатные копии `apisetschema.dll`; вывод по другим именам не предоставлен.

## Контекст, уже установленный ранее
- Factory OS 22000 `.apiset`: 945 контрактов, оба State Separation хоста назначены, EntryFlags=0.
- Windows 26100 `.apiset`: 974 контракта, 961 SEALED; два контракта State Separation пустые и SEALED.
- Смена базовой схемы **целиком** на схему 22000 не эквивалентна добавлению двух контрактов к 26100, не гарантирует загрузку NT 26100.
- `statesepext.sys` 22000 импортирует 26 функций, 26/26 экспортируются NT 26100, но ранняя ABI и семантика Phase0/1/2 не доказаны; пользовательский компонент тоже должен быть совместим.
- Имеющийся `winload.efi` 26100 при композиции API Set проверяет SEALED per-entry flag. Выбор другой схемы посредством названия файла не решает отсутствие совместимой подписанной схемы и расширения.

## Практический итог
`BaseApiSetSchema` как самостоятельный путь минимальной State Separation — **не подтверждён / не годится для первого загрузочного изменения PoC01**. Не выставлять значение и не менять signed файлы, пока нет совместимого варианта схемы и проверенного раннего extension host.

Следующий *обязательный* шлюз, а не новый аудит: найти **конкретный подтверждённый источник набора 26100**, содержащего одновременно совместимую базовую схему с назначенными State Separation host, нативную реализацию ранних фаз и требуемые policy/storage components; либо доказать техническую осуществимость собственно адаптированной минимальной реализации и корректной безопасной цепочки её загрузки. Отсутствие такого набора пока остаётся главным блокером первого true WCOS boot.
