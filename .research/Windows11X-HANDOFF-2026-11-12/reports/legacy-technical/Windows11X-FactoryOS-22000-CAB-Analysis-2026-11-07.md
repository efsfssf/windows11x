# Аудит предоставленных компонентов Factory OS / State Separation

Дата исследования: 2026-11-07. Источник: 11 предоставленных CAB-файлов, локальная проверка Microsoft Cabinet и `update.mum` без установки пакетов.

## Сводная таблица

| Пакет | Архитектура | Версия | Файлов в CAB | Исполняемые файлы в CAB |
|---|---|---|---:|---|
| `Enable-StateSeparation-DevelopmentMode-Package` | `amd64` | `10.0.22000.1` | 12 | Нет |
| `Enable-StateSeparation-DevelopmentMode-Package` | `arm64` | `10.0.22000.1` | 12 | Нет |
| `Enable-StateSeparation-DevelopmentMode-Package` | `arm` | `10.0.22000.1` | 12 | Нет |
| `Enable-StateSeparation-Package` | `amd64` | `10.0.22000.1` | 11 | Нет |
| `Enable-StateSeparation-Package` | `arm64` | `10.0.22000.1` | 11 | Нет |
| `Enable-StateSeparation-Package` | `arm` | `10.0.22000.1` | 11 | Нет |
| `Enable-StateSeparation-ReadOnlyMinifilter-Package` | `amd64` | `10.0.22000.1` | 10 | XRo.sys |
| `Enable-StateSeparation-ReadOnlyMinifilter-Package` | `arm64` | `10.0.22000.1` | 10 | XRo.sys |
| `Enable-StateSeparation-ReadOnlyMinifilter-Package` | `arm` | `10.0.22000.1` | 10 | XRo.sys |
| `SS-Boot-Package` | `amd64` | `10.0.22000.1` | 6 | spaceutil.exe, ssboot.exe |
| `SplitLayoutSupport-Package` | `amd64` | `10.0.22000.1` | 4 | Нет |

## Выводы по AMD64

- `Microsoft-OneCore-Enable-StateSeparation-Package`: в CAB **нет `statesepext.sys`**. В составе присутствуют установочные метаданные, манифесты `StateSeparation-RedirectionMap-Baseline`, `-OneCore`, `-MemoryManager-Config`, `-ServicingMap-Files/Keys`, `-SecurityTemplates`, `-ImageCustomization` и другие.
- `Microsoft-OneCore-Enable-StateSeparation-DevelopmentMode-Package`: отдельная конфигурация со сходными картами перенаправления и дополнительным компонентом `DevelopmentMode`. Это не заменитель полноценного `STATESEPARATION_ON` при проверке защиты.
- `Microsoft-OneCore-Enable-StateSeparation-ReadOnlyMinifilter-Package`: в CAB находится `XRo.sys` для `amd64` длиной 110920 байт, SHA-256 `64F8D35BDB9836F45E407FA12E9CFC6087D9B2D030251E340D03D601516D691C`. Хэш совпадает с ранее проверенным файлом пользователя `E:\WCOS\Logs\XRo.sys`. Также включены манифесты конфигурации XRo и постоянных/временных белых списков.
- `Microsoft-OneCore-SS-Boot-Package`: содержит `ssboot.exe` (704512 байт) и `spaceutil.exe` (221184 байта); MUM указывает `\Windows\ssboot.exe` и `\Windows\System32\spaceutil.exe`.
- `Microsoft-WindowsCore-SplitLayoutSupport-Package`: в архиве манифесты; нет самостоятельных PE-файлов.

## Важные ограничения

1. **Все предоставленные пакеты имеют версию `10.0.22000.1`, а не `26100`**; проверена версия из `update.mum`. Нельзя считать их подходящими к `26100` по одному совпадению экспортов.
2. В составе **нет бинарника `statesepext.sys`**; он имеется у пользователя отдельно, но механизм его добавления в конкретную компоновку из этих пяти AMD64 CAB не установлен.
3. Все извлечённые `.manifest` имеют бинарный заголовок `DCM`, т. е. **полные правила реестра и защиты не были распакованы** в читаемый XML. Состав компонентов и файлов установлен из читаемого `update.mum`.
4. Вывод `update.mum` о package identity не является проверкой подписи CAB или доказательством возможности обслуживания 26100 через DISM.
5. Оригинальная Factory OS требует совместимой загрузочной цепочки, согласованной API Set-схемы, разделённых ульев, карты перенаправления и политики защиты; отдельно присутствующие пакеты не означают готовую WCOS 26100.
6. ARM и ARM64 пакеты не следует смешивать с x64 (AMD64) компоновкой.

## Что делать дальше

Не применять эти CAB к PoC01 через `DISM /Add-Package`, не менять `StateSeparation\Policy\Enabled`, не модифицировать BCD или API Set. Следующий полезный этап — изучить в **оригинальной Factory OS 22000** состав Feature Manifest/WSK по `STATESEPARATION_ON` / `STATESEPARATION_DEVMODE` и выяснить, из какого согласованного компонента поставляется `statesepext.sys`. Параллельно проверить, существует ли пакетная компоновка State Separation именно для 26100. Пока такая компоновка не подтверждена.

## Первичные официальные источники

- Factory OS FeatureID `STATESEPARATION_ON` и `STATESEPARATION_DEVMODE`: https://learn.microsoft.com/en-us/windows-hardware/manufacture/desktop/factoryos/factory-product?view=windows-11
- State separation, ограничения записи и dev mode: https://learn.microsoft.com/en-us/windows-hardware/manufacture/desktop/factoryos/state-separation?view=windows-11
- Создание Factory OS образа посредством WSK и Feature Manifest: https://learn.microsoft.com/en-us/windows-hardware/manufacture/desktop/factoryos/build-a-factoryos-image?view=windows-11

## Контрольные SHA-256 CAB (проверены локально)

| Пакет | Архитектура | SHA-256 |
|---|---|---|
| `Microsoft-OneCore-Enable-StateSeparation-DevelopmentMode-Package` | `amd64` | `DDC024673342931ACC9FDEB94671AF81176CC66D98A9C1C335181FE306D5CEE5` |
| `Microsoft-OneCore-Enable-StateSeparation-DevelopmentMode-Package` | `arm64` | `308D2B21B05B87DB82E04B1DC873E7B84B2336758CDF7ED4E80F75B4CDBA4CD7` |
| `Microsoft-OneCore-Enable-StateSeparation-DevelopmentMode-Package` | `arm` | `3AC22904A90EEFB031254276B25BD8D6A4164D973DA78879930850905F0E2457` |
| `Microsoft-OneCore-Enable-StateSeparation-Package` | `amd64` | `5D878C6403217F05E00566E2E28F114C294FAF645C674AC8C397E2EBD0FF8475` |
| `Microsoft-OneCore-Enable-StateSeparation-Package` | `arm64` | `ABEA44C09E50F0DC61B5E197F23F53CE85BF9B7C343CAA60BB445E2AD2F68034` |
| `Microsoft-OneCore-Enable-StateSeparation-Package` | `arm` | `1030E785FE31B4F48B9574DEA7A6F98B93F8D0C0CE7F6699E59AF68549A40D8A` |
| `Microsoft-OneCore-Enable-StateSeparation-ReadOnlyMinifilter-Package` | `amd64` | `D3CC4647B296C6C650C89CE081CDAC67450049351E5B1C5B8261EAA6EB02DED7` |
| `Microsoft-OneCore-Enable-StateSeparation-ReadOnlyMinifilter-Package` | `arm64` | `F02286DFBE0436023F70B9BD831EC61B2D96436DEA1BDBE227673C393EAA7F0D` |
| `Microsoft-OneCore-Enable-StateSeparation-ReadOnlyMinifilter-Package` | `arm` | `003B56051C4123182A5D6978C71066346CF62434CD38881F09DA678F16B9B98B` |
| `Microsoft-OneCore-SS-Boot-Package` | `amd64` | `CEA9E891CF9B553E9A7108FEF27CC470FB14BBA20E812808159B4D9F27078CC0` |
| `Microsoft-WindowsCore-SplitLayoutSupport-Package` | `amd64` | `881DA40B9350D0E08C164C8FDE9A48849FACCD755676BEE80E27E1B819271167` |
