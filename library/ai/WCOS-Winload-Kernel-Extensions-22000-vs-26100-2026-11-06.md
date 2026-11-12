# Windows 11X — анализ загрузчика и расширений ядра (22000 ↔ 26100)

**Дата:** 06.11.2026. **Режим:** только чтение предоставленных бинарных файлов и сопоставление с выводом WinDbg. Реестры, BCD и образы виртуальных машин не изменялись.

## 1. Исходные файлы

| Бинарный файл | Источник | Размер, байт | SHA-256 |
|---|---|---:|---|
| `winload-22000.efi` | `WCOS-KernelBoot-22000.zip` | 2 307 288 | `9fb057cb65462624f35b41fe686f71fc03f11e3770fdb81e04900640030ad41d` |
| `winload-26100.efi` | `26100-WCOS-binaries.zip` | 3 100 680 | `5b47da9e127dfd76689e0cf60d3c14149f42e93656d45843f141dc43f2b7d4a5` |
| `apisetschema-22000.dll` | Factory OS 22000 | 143 672 | `49696b4557023ea455aa7731a18467b623292a7c2ec837d8437a9c2dcd9cd498` |
| `apisetschema-26100.dll` | Activation01 26100 | 153 888 | `aeaaea67b6c57b4d85cc1d7b724f2ec8fe57aa3210c7aee78b3f40e231fd70f1` |
| `statesepext.sys` | Factory OS 22000 | 61 752 | Приложенный оригинальный файл |
| `cmimcext-26100.sys` | Activation01 26100 | 70 976 | Приложенный оригинальный файл |

## 2. Независимая повторная проверка API Set

Секции `.apiset` разобраны структурно: заголовок namespace v6, записи контрактов и их значений. Это не поиск названий по текстовым строкам.

| Контракт | Factory OS 22000 | Windows 26100 |
|---|---|---|
| `ext-ms-win-ntos-stateseparation-l1-1-0` | `statesepext.sys` (запись 684) | **Пустой target** (702) |
| `ext-ms-win-core-stateseparationext-l1-1-0` | `onecorestateseparationext.dll` (487) | **Пустой target** (495) |
| `ext-ms-win-ntos-kcminitcfg-l1-1-0` | Пустой target (680) | `cmimcext.sys` (697) |
| `api-ms-win-stateseparation-helpers` | `l1-1-0` → `kernelbase.dll` | `l1-1-1` → `kernelbase.dll` |

Размер таблиц: 945 записей в 22000 и 974 в 26100. Пустой target — свойство **конкретного исследованного образа 26100**, а не доказательство отсутствия подходящего компонента во всех редакциях и вариантах Windows 26100.

## 3. Проверка гипотезы по живым системам (WinDbg)

**Factory OS 22000:**

```text
nt!_imp_ExpInitializeStateSeparationPhase0 -> statesepext!ExpInitializeStateSeparationPhase0
nt!_imp_ExpInitializeStateSeparationPhase1 -> statesepext!ExpInitializeStateSeparationPhase1
nt!_imp_ExpInitializeStateSeparationPhase2 -> statesepext!ExpInitializeStateSeparationPhase2
```

**Windows 26100 Activation01:**

```text
nt!_imp_ExpInitializeStateSeparationPhase0 -> nt!DefaultAddProfileSource
nt!_imp_ExpInitializeStateSeparationPhase1 -> nt!DefaultAddProfileSource
nt!_imp_ExpInitializeStateSeparationPhase2 -> nt!DefaultAddProfileSource
```

Вторая функция-заглушка содержит `mov eax,0xC00000BB; ret` и возвращает `STATUS_NOT_SUPPORTED`. В исследованной конфигурации 26100 переменная `CmStateSeparationEnabled` равна нулю.

**Отдельное свидетельство в 26100:** WinDbg показал реально загруженный `cmimcext.sys` и связал с ним импорты ядра `CmCompleteInitMachineConfig` и `CmSetInitMachineConfig`. Следовательно, инфраструктура подключения **по крайней мере некоторых** расширений ядра в 26100 функциональна.

## 4. Статический анализ winload.efi

Оба файла — x64 PE загрузчика Windows. Их стандартный каталог PE-импортов пуст; ограничиваться `Import Directory` для исследования механизма загрузки нельзя.

### 4.1. Код с прямой ссылкой на `ApiSetSchema.dll`

В обоих загрузчиках найдена RIP-relative инструкция `lea rdx,[rip+...]`, использующая строку `ApiSetSchema.dll`:

| Показатель | 22000 | 26100 |
|---|---|---|
| Смещение строки в файле | `0x1DCED8` | `0x28B1B8` |
| RVA строки | `0x1E0CD8` | `0x28F5B8` |
| RVA инструкции `lea rdx` | `0x1D783` | `0x1EB38` |
| Запись указателя в поле структуры `[...+0xAA0]` поблизости | Есть, около RVA `0x1D843` | Есть, около RVA `0x1EBE7` |

Вокруг этих точек код структурно похож: выбирает имя объекта, вызывает вспомогательные процедуры, обрабатывает полученные данные и сохраняет указатель в структуру загрузочного контекста. **Точные имена процедур, тип структуры и полный граф вызовов не установлены**. Наличие этой последовательности согласуется с обработкой API Set-схемы во время загрузки ОС.

В ветке 26100 обнаружена дополнительная условная ветка, способная выбрать альтернативный указатель на имя перед дальнейшим вызовом. Её назначение не определено; нельзя объявлять её переключателем State Separation.

### 4.2. Сохранившиеся строки

Обе версии содержат UTF-16 строки `ApiSetSchemaExtensions`, `StateSeparation`, `OSBOOT`, `Core Platform Extensions`, `Core Security Extensions`. Это полезные ориентиры для дизассемблирования, **но строки сами по себе не доказывают работоспособность механизма**. Для двух последних названий прямых RIP-relative ссылок в первом проходе не выявлено: возможны таблицы косвенных указателей.

Литеральной строки контракта `ext-ms-win-ntos-stateseparation-l1-1-0` в обоих `winload.efi` не обнаружено: сопоставление присутствует в отдельной `apisetschema.dll`.

## 5. Сравнение statesepext.sys и cmimcext.sys

| Проверка | `statesepext.sys` 22000 | `cmimcext.sys` 26100 |
|---|---|---|
| Экспорты | `DllUnload` и три `ExpInitializeStateSeparationPhase*` | `DllUnload`, `CmCompleteInitMachineConfig`, `CmSetInitMachineConfig` |
| Импорты из `ntoskrnl.exe` | 26 | 15 |
| Совпадение имён с экспортами ядра 26100 | **26/26** | **15/15** |
| Стандартная точка входа PE | Через вспомогательную функцию возвращает `STATUS_UNSUCCESSFUL` | По аналогичной схеме возвращает `STATUS_UNSUCCESSFUL` |
| Реальное связывание функций подтверждено WinDbg | Да, на 22000 | Да, на 26100 |

Структурное сходство точек входа согласуется со специальной моделью загрузки расширений ядра, а не с обычным сценарием регистрации службы драйвера. Однако точный путь исполнения загрузчика из этого **не доказан**.

**Ограничение:** совпадение 26/26 названий НЕ означает бинарную/ABI-совместимость. Оно не проверяет скрытые требования, порядок вызовов, структуру входных данных, настройки защиты, обработку реестра, IRQL, версию внутренних механизмов и последствия включения State Separation.

## 6. Сводка: факты и гипотезы

1. **Доказано:** в Factory OS 22000 три импорта ядра ведут к реальным экспортам `statesepext.sys`; в исследованной 26100 — к общей заглушке `STATUS_NOT_SUPPORTED`.
2. **Доказано:** API Set-схемы различаются именно в указании реализации контракта. Windows 26100 при этом реально загружает другое расширение `cmimcext.sys`.
3. **Есть сильные косвенные свидетельства:** код `winload.efi` в обеих ветках обрабатывает `ApiSetSchema.dll` ещё на стадии подготовки загрузки.
4. **Не доказано:** что достаточно изменить mapping API Set; что `statesepext.sys` версии 22000 запустится с ядром 26100; что одна только загрузка драйвера создаст State Separation; что необходимые механизмы обслуживания и восстановления совместимы; что Microsoft выпускает готовую WCOS 26100 для x64 ПК.

## 7. Следующий наиболее информативный эксперимент

На **отдельном отладочном клоне**, с символами именно используемого `winload.efi`, исследовать в boot debugger функции, обращающиеся к `ApiSetSchema.dll` и `ApiSetSchemaExtensions`, и фактическую последовательность подготовки списка расширений до старта ядра. Первый этап — только поиск символов/чтение дизассемблированного кода; никаких замен файлов, правок BCD ради State Separation или регистрационного запуска `statesepext.sys`.

Лишь после восстановления проверяемого графа загрузки — отдельный ABI-аудит `statesepext.sys` 22000 на 26100 с проверкой возвращаемых значений, входных структур и необходимых ульев реестра.

**Не изменять:** эталонные `FOS` и `FOS-SS-Test`, 26100 BASE, `Activation01`, оригинальные API Set-схемы и `StateSeparation\\Policy\\Enabled`.

## 8. Внешние справочные материалы

- [Microsoft Learn: Windows API sets](https://learn.microsoft.com/en-us/windows/win32/apiindex/windows-apisets)
- [Microsoft Learn: API set loader operation](https://learn.microsoft.com/en-us/windows/win32/apiindex/api-set-loader-operation)

Документация объясняет **общую** модель перенаправления API Set-контрактов и сама по себе не документирует приватную цепочку загрузки расширений NT в Factory OS.
