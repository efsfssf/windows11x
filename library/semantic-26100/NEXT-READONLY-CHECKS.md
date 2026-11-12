# Минимальные следующие пассивные проверки

Эти шаги только читают существующие файлы/metadata/снимки. Они не требуют включать State Separation, перезагружать систему, менять BCD/registry/API Set, загружать драйверы, устанавливать breakpoints или изменять CI/Secure Boot. Эта работа их в VM не выполняла.

## 1. Сначала идентичность и происхождение

Сопоставить уже существующий source-image manifest, package identities и тома, откуда получены оба FOS файла. Нужны путь файла, volume/image identity и SHA-256 из одного аттестованного источника; затем сопоставление с семью локальными входами. Для новых ранее сохранённых образцов достаточно офлайн hash/version/RSDS/signature metadata, без исполнения PE. Это закрывает R01, а не runtime compatibility.

## 2. Предпочесть существующий dump одной загрузки

Если уже имеется полный dump/остановленный read-only debugger context, из **одного и того же boot** снять:

| Объект | Минимальные поля | Проверяемый вопрос |
|---|---|---|
| nt/winload/extension identity | image names/base/size, version, доступные hashes/RSDS | Совпадает ли объект с исследованным PE |
| Три Phase IAT slots | значения pointers + принадлежность соответствующему image и bytes entry | Реальный Host либо fallback |
| Loader module entry Host | DllBase+0x30, Flags+0x68, LoadCount+0x6C, LoadedImports+0x88, section headers | Применим ли доказанный INIT predicate к этому image |
| API Set runtime object | версия, исходный file identity, реально выбранный Host для ntoskrnl importer | Нельзя заменить готовой картинкой legacy entry из другого boot |
| INIT mapping | страницы Phase2 и code bytes при имеющемся позднем snapshot | Сохранился ли конкретный код фактически |

Точные адреса следует вычислять как base соответствующего module + RVA из этого пакета только после проверки версии. Разрешены чтение памяти/типов/символов и анализ уже существующих dumps. Не предлагается организовывать новые остановы/breakpoints или вызывать Phase функции.

## 3. Namespace и backing stores без записей

Из того же dump или уже сохранённых диагностических данных сопоставить:

- `\OSDataRoot` → `\Device\OSDataDevice` → реальный volume/device identity; отдельно ArcOSDataDeviceName/ArcBootDeviceName и факт fallback.
- Связь MainOS/OSData/Data с mount/reparse/volume metadata; `M:\Data` не считать физическим MainOS без разрешения alias.
- Подключённые DEVICES, OSBOOT, OSDATA: backing filenames/device identity, hive flags и mount roots.
- HiveRedirectionList, KeyRedirectionLinks, RedirectionMap, relevant ACL и sentinel presence; чтение реально существующих значений без их исправления.

Полученный namespace snapshot не закрывает persistence или immutability, но исключает смешение имени с отдельным backing store.

## 4. Дальнейший offline code audit — только узкие оставшиеся branches

После получения согласованных входов приоритет S02/S03: замкнуть CM create-link/rename/copy error paths и входящий граф shared scratch с lock scopes. Затем S04/S06: explicit discard/unload и используемые I/O/security completion/options. Не требуется бессистемный whole-kernel diff.

Для S01/S09 нужны оригинальные уже доступные package/component files целевой версии и их manifests. Анализировать их офлайн; наличие одного FOS MUM не доказывает применимость в26100. Никакой patch/bypass workflow из этого шага не следует.

## Чего пассивные проверки не установят

Один dump и один успешный boot не докажут неизменяемость MainOS, durable state relocation, обновление split hives и recovery. Если нет уже записанных независимых результатов таких проверок, эти пункты остаются UNKNOWN. Генерировать нагрузку, пробовать запись на защищённый том, создавать WNF objects или делать update/reboot ради аудита здесь не требуется.
