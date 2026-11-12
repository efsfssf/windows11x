# Windows 11X — сравнение реестра Factory OS 22000 и Windows 26100

Дата анализа: 2026-11-06. Источник: два предоставленных пользователем архива `WCOS-Reg-Factory22000.zip` и `WCOS-Reg-26100.zip`. 
Граница достоверности: сравниваются экспортированные ветки, а не все ульи реестра и не события runtime. Различия могут зависеть от версии, состава образа и текущего состояния ВМ.

## 1. Объём сравнения

| Раздел экспорта | Factory OS 22000 (ключи / значения) | Windows 26100 (ключи / значения) |
|---|---:|---:|
| 01-OSBOOT.reg | 132 / 452 | нет экспорта (ключ отсутствовал) |
| 02-StateSeparation.reg | 672 / 1415 | 2 / 0 |
| 03-FeatureManagement.reg | 6 / 3 | 1929 / 5666 |
| 04-SessionManager.reg | 37 / 171 | 27 / 150 |
| 05-XRo.reg | 10 / 118 | нет экспорта (ключ отсутствовал) |

## 2. State Separation и правила

- Factory OS: `StateSeparation` содержит 672 экспортированных ключа и 1415 значения.
- В Factory OS есть `Policy\Enabled = 1`, в Windows 26100 в этой экспериментальной ВМ экспорт содержит только пустые `StateSeparation` и `Policy`: значение `Enabled` удалено после восстановления; оставшийся каркас ключей — результат прежних экспериментов, а не свидетельство работающей State Separation.
- Экспорт Factory OS включает 81 дочерний `KeyRedirectionLinks`, 62 дочерних `RedirectionMap\Files` и 465 дочерних `RedirectionMap\Keys`.
- `PnP` под `StateSeparation` имеет 55 ключей, включая корень, и `SecurityTemplates` — 3 ключа.
- Направления TargetPath для 465 правил ключей: `OSDATA` = 404, `OSBOOT` = 40, `DEVICES` = 19, `O:` = 1, `` = 1.
- Направления перенаправлений файлов: 37 в `O:` и 25 в `M:`; пути в `M:\Data` не обязательно указывают на физическую запись в защищённый MainOS — в Factory OS там может быть mountpoint.
- Явное правило `FeatureManagement`: `SYSTEM\CurrentControlSet\Control\FeatureManagement` → `OSBOOT\ControlSetOverride\FeatureManagement`.

Примеры других путей из файла Factory OS:

| Название | SourcePath | TargetPath |
|---|---|---|
| FeatureManagement | `SYSTEM\CurrentControlSet\Control\FeatureManagement` | `OSBOOT\ControlSetOverride\FeatureManagement` |
| BluetoothBthPortParams | `System\CurrentControlSet\Services\BTHPORT\Parameters` | `DEVICES\bthportparams` |
| ChargeLimiting | `SYSTEM\CurrentControlSet\Control\Power\CAD\ChargeLimiting` | `DEVICES\CurrentControlSet\Control\Power\CAD\ChargeLimiting` |
| CIEnrollments | `SYSTEM\CurrentControlSet\Control\CI\Enrollments` | `OSBOOT\SYSTEM\CurrentControlSet\Control\CI\Enrollments` |
| ClipSVC | `SOFTWARE\Microsoft\Windows NT\CurrentVersion\ClipSVC` | `OSDATA\SOFTWARE\Microsoft\Windows NT\CurrentVersion\ClipSVC` |
| BootSHID | `SYSTEM\CurrentControlSet\Services\Bootsh\Parameters` | `OSDATA\BootSH` |
| LanmanServer | `\REGISTRY\MACHINE\SYSTEM\CurrentControlSet\Services\LanmanServer` | `\REGISTRY\MACHINE\OSDATA\SYSTEM\CurrentControlSet\Services\LanmanServer` |

## 3. OSBOOT и Feature Management

- Factory OS: `OSBOOT` экспортирован, 132 ключа и 452 значения. Имеются `ControlSetOverride`, `CurrentControlSet`, `System`, `RNG`, `WMI`; в системе `OSBOOT\ControlSetOverride\FeatureManagement\LastKnownGood\LKGConfiguration` присутствует как **нулевая REG_BINARY** (`hex:`).
- Factory OS в экспортированном `SYSTEM\...\FeatureManagement` содержит `LastKnownGood\Supported=1`, два раздела Overrides (`0`, `15`) и один конкретный ID в `Overrides\0`.
- Windows 26100: `FeatureManagement` содержит 1929 ключей и 5666 значений. В корне `FconPresent=1`; в `LastKnownGood` — `Supported=1` и `LKGConfiguration` **4756 байт**.
- Для 26100 `HKLM\OSBOOT` отсутствует, а правило перенаправления `FeatureManagement` отсутствует. Это подтверждает отличие конфигурации, но само по себе не объясняет исключение в загрузчике.
- Важно: по имени `FsepOpenFeatureManagementRootSubKeys` нельзя доказывать, что функция реализует State Separation; нужны сравнительные трассировки без/с политикой.

## 4. Session Manager — ранняя загрузка

| Параметр | Factory OS 22000 | Windows 26100 |
|---|---|---|
| `BootExecute` | `autocheck autochk *`; `FirstBootTasks` | `autocheck autochk *` |
| `SetupExecute` | `SSBoot` | пустое `REG_MULTI_SZ` |
| `NumberOfInitialSessions` | `1` | `2` |
| `NamespaceSeparation\AppcontainerUserSeparation` | `1` | `0` |
| `NamespaceSeparation\InteractiveUserSeparation` | `1` | `0` |
| `Environment\OSDataDrive` | `O:` | нет в экспортированной ветке |
| `Environment\DataDrive` | `C:` | нет в экспортированной ветке |
| `Environment\SettingsDrive` | `O:\os` | нет в экспортированной ветке |
| `Environment\ReserveDrive` | `R:` | нет в экспортированной ветке |

Параметры `NamespaceSeparation` и `StateSeparation` — **разные механизмы**; не следует автоматически менять первые на `1` ради активации второго.

## 5. XRo

- Factory OS: `Services\XRo` — 10 ключей, 118 значений. Среди прочего `Start=0`, `Type=2`, `RegistryFilterEnabled=1`, `DisableExemptKeys=1`; в экземпляре минифильтра указан `Altitude=261101`.
- Экспорт Windows 26100 сообщает отсутствие `HKLM\SYSTEM\CurrentControlSet\Services\XRo`. Это соответствует прошлым runtime-проверкам, но не доказывает отсутствие всех механизмов State Separation внутри бинарных компонентов 26100.
- Наличие `Policy\Enabled=1` не создаёт ни XRo, ни правила, ни рабочие тома/ульи. Копировать XRo с 22000 без анализа совместимости не рекомендуется.

## 6. HiveList

- Factory OS: `SOFTWARE` расположен на `HarddiskVolume5`, а `SAM`, `SECURITY`, `OSDATA`, `.DEFAULT` — на `HarddiskVolume9`; видны также многочисленные container hive/base/delta записи.
- Windows 26100: `SYSTEM`, `SOFTWARE`, `SAM`, `SECURITY`, `.DEFAULT` — на `HarddiskVolume3`, BCD на `HarddiskVolume2`.
- `OSBOOT` и `DEVICES` **не перечислены** в этом снимке `HiveList` Factory OS, хотя `HKLM\OSBOOT` присутствует. Поэтому **отсутствие записи в HiveList не является доказательством отсутствия такого пространства реестра**.

## 7. Связь с отладкой 26100 (не из этих архивов)

- В ранее присланном WinDbg-журнале при тесте `Enabled=1` произошёл first-chance `0xC0000005` в `winload!OslGetRootCell+0x10`, `RDX=0xFFFFFFFF`, стек: `FcPalOpenWellKnownRegistryKey` → `FsepOpenFeatureManagementRootSubKeys` → `FseInitializeFeatureConfigurationTable`.
- Сопоставление с `OSBOOT`/`FeatureManagement` является **гипотезой**, но прямой причинной связи пока нет. Исключение могло проявиться из-за отладочного контекста или иного отказа.
- Нет подтверждения запуска ядра с настоящей State Separation и нет объяснения первопричины `SECURITY_INITIALIZATION_FAILED (0x5F)`.

## 8. Следующий подход

1. Сохранить неизменными рабочие baseline и backup Activation01. Не повторять `Enabled=1`.
2. Исследовать раздельно реальные источники/типы `OSBOOT`, `DEVICES`, `OSDATA` в Factory OS и механизм их подключения на ранней загрузке (чтение, не создание ключей в 26100).
3. Определить, какая часть модели недоступна в Windows IoT 26100: image composition, boot parameters, загрузка ульев, XRo, маппинги реестра, обслуживание и целостность.
4. Перед новым boot-тестом получить контрольную трассировку `winload` на исправном состоянии 26100, чтобы не спутать обычные first-chance исключения с эффектом `Enabled=1`.

Примечание: намеренно не включены секретные или уникальные machine-specific значения из OSBOOT и длинные массивы FeatureManagement.
