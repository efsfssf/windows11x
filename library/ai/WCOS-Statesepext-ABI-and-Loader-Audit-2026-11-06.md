# Windows 11X — статический аудит вызовов State Separation и пути загрузки расширений

**Дата:** 2026-11-06  
**Объект:** Factory OS 22000.1 x64 ↔ Windows 11 IoT Enterprise LTSC 26100.1742 x64  
**Режим:** чтение предоставленных PE-файлов. VM, BCD, registry, API Set и системные диски не изменялись.

## 1. Источники и воспроизводимость

| Объект | Размер (байт) | SHA-256 |
|---|---:|---|
| `statesepext.sys` 22000 | 61 752 | `167fda36a71c2aad4c146bc66ae4c83e9d525d008d6abfd8eda0e056c9a96658` |
| `cmimcext-26100.sys` | 70 976 | `7b5d556839603629a523e96d5b7670a435a6c61c695ffff968e2890bc3c098ee` |
| `ntoskrnl-22000.exe` | 11 743 560 | `a9b5c0a47f1c4606a94adb3674017cbd35448c3792c4d26f51847a3ffa8c7b7c` |
| `ntoskrnl.exe` 26100 | 12 723 640 | `93db70780a95192d13d04b4c3cb49c22a138063adba5c83d4855be02b645fdd0` |
| `winload.efi` 22000 | 2 307 288 | `9fb057cb65462624f35b41fe686f71fc03f11e3770fdb81e04900640030ad41d` |
| `winload.efi` 26100 | 3 100 680 | `5b47da9e127dfd76689e0cf60d3c14149f42e93656d45843f141dc43f2b7d4a5` |

Инструменты: структурное чтение PE-заголовков, `objdump -x`, `objdump -D -M intel`, поиск RIP-relative обращений `call qword ptr [rip+disp32]` к конкретным IAT-слотам. RVA указаны относительно ImageBase соответствующего PE, **не** являются абсолютными адресами работающей системы.

## 2. Главная находка: три настоящих callsite в обоих ядрах

| Фаза | NT 22000: адрес call (RVA) | NT 26100: адрес call (RVA) | Входы (по регистрам непосредственно перед вызовом) |
|---|---|---|---|
| `Phase0` | `0xB0B60B` | `0xBD9A4E` | `CL`: результат системного определения режима; `DL`: дополнительный флаг. |
| `Phase1` | `0xB10280` | `0xC1EE3A` | `CL`: проверка флага, соответствующего `CmStateSeparationEnabled` в 26100. |
| `Phase2` | `0x7797B5` | `0xAC8F2E` | `CL`: флаг, `RDX` и `R8`: указатели на данные/имена, вычисляемые из соседних полей. |

Во всех трёх случаях instruction — `48 FF 15 <disp32>` (`call qword ptr [rip+disp32]`). В таблицах импортов NT 22000 и 26100 имеются соответствующие записи `ExpInitializeStateSeparationPhase0/1/2`.

IAT-слоты NT 22000: `Phase0=0x13D2D0`, `Phase1=0x13D2C8`, `Phase2=0x13D2D8`.  
IAT-слоты NT 26100: `Phase0=0x145370`, `Phase1=0x145368`, `Phase2=0x145378`.

**Результат WinDbg из текущего исследования:**
- Factory OS 22000: все три IAT-слота ссылаются на три разных настоящих адреса в загруженном `statesepext.sys`.
- Windows 26100: все три ссылаются на общую резервную функцию NT, возвращающую `STATUS_NOT_SUPPORTED (0xC00000BB)`.
- Windows 26100 также реально загружает другое расширение `cmimcext.sys` и привязывает к нему `CmSetInitMachineConfig`/`CmCompleteInitMachineConfig`.

### 2.1. Существенное различие в обработке ошибок

Все три участка вызывающего кода **в NT 26100** содержат явно предусмотренную обработку кода `0xC00000BB`: при этом статусе ядро продолжает исполнение, а не немедленно завершает инициализацию с ошибкой. Подтверждено дизассемблированием:

- `Phase0`: `nt+0xBD9A66` — `cmp eax,0xC00000BB`, затем переход к продолжению.
- `Phase1`: `nt+0xC1EE52` — `cmp eax,0xC00000BB`, затем переход к продолжению.
- `Phase2`: `nt+0xAC8F3C` — `cmp eax,0xC00000BB`, затем переход к продолжению.

**Следствие:** прежняя формулировка «заглушка сама по себе объясняет BSOD Activation01» неверна как доказанный факт. Она может быть *следствием* конфигурации, но анализ требует проверить порядок формирования режима State Separation и доступность ульев. `STATUS_NOT_SUPPORTED` явно обработан как необязательный вариант в NT 26100.

В коде NT 22000 поведение ошибок частично различается: `Phase2` также сравнивает ответ с `STATUS_NOT_SUPPORTED`, тогда как ветвления `Phase0/Phase1` в исследованных местах устроены иначе. Полная семантика всех путей без символов функций, контекста и реального рантайма не установлена.

## 3. Аудит ABI самого `statesepext.sys`

**PE:** AMD64, нативный драйвер; экспортирует `DllUnload`, `ExpInitializeStateSeparationPhase0`, `Phase1`, `Phase2`; обычная точка входа не выглядит как успешный `DriverEntry` обычного драйвера устройств.

**Импорты:** ровно **26** символов из `ntoskrnl.exe`; поимённо **26/26** находятся в таблицах экспортов как NT 22000, так и NT 26100:

`ZwOpenKey`, `RtlInitUnicodeString`, `ZwSetValueKey`, `ZwClose`, `ExAllocatePool2`, `ExFreePoolWithTag`, `ZwCreateFile`, `ZwQuerySecurityObject`, `RtlGetControlSecurityDescriptor`, `RtlSetControlSecurityDescriptor`, `ZwSetSecurityObject`, `ZwQueryInformationFile`, `ZwSetInformationFile`, `ZwReadFile`, `ZwWriteFile`, `ZwFlushBuffersFile`, `ZwDeleteFile`, `ZwCreateKey`, `ZwQueryValueKey`, `RtlAppendUnicodeToString`, `ZwDeleteKey`, `ZwEnumerateKey`, `ZwEnumerateValueKey`, `ExUuidCreate`, `swprintf_s`, `ZwRenameKey`.

**Что важно:** импортируемые функции — в основном документированные/устоявшиеся NT-операции с реестром и файлами, а не ссылки на неэкспортируемые глобальные переменные ядра. Это позитивный признак **для разрешения импорта**, но нельзя из этого автоматически выводить совместимость семантики вызовов и данных.

**Контроль путей выполнения в самом драйвере 22000:**
- `Phase0`: проверяет `CL`, затем `DL`; если первый флаг нулевой, завершает функцию без основной работы. При соответствующих флагах вызывает `ZwOpenKey`, `ZwSetValueKey` и вспомогательные процедуры.
- `Phase1`: при нулевом `CL` возвращает успех без основной работы; при установленном флаге выделяет память и запускает несколько процедур работы с системным состоянием.
- `Phase2`: при `CL=0` либо нулевых `RDX`/`R8` возвращает `STATUS_SUCCESS`; иначе вызывает внутреннюю `CopyKeyTreeByName`, затем освобождает временный буфер.

**Строки, подтверждающие зависимость от конфигурации Factory OS:** `\OSDataRoot\Windows\System32\Config\`, `\Registry\Machine\System\CurrentControlSet\Control\StateSeparation\KeyRedirectionLinks`, `DevModeEverEnabled`, `AlwaysEnabled`, `\??\O:\Windows\FirstBoot.Complete`, `SAM`, `SECURITY`, `DEFAULT`.

Важное ограничение: эти пути специфичны для ожидаемой архитектуры и состояния Factory OS, а проверяемая Windows 26100 не имеет подготовленных ульев/правил перенаправления. Ранее запуск режима `Policy\Enabled=1` на Activation01 приводил к отказу загрузки.

## 4. Статический след `winload.efi`: что удалось, а чего нет

В обоих `winload.efi` найден машинный код, который ссылается на строку `ApiSetSchema.dll`, обрабатывает полученные данные и помещает указатель в загрузочный контекст:

| Проверка | winload 22000 | winload 26100 |
|---|---|---|
| `lea rdx,[rip+...]` со строкой `ApiSetSchema.dll` | RVA `0x1D783` | RVA `0x1EB38` |
| Запись результирующего указателя в `[...+0xAA0]` | около `0x1D843` | около `0x1EBE7` |

У 26100 до обращения к строке присутствует дополнительный условный выбор другого указателя на имя (`cmovne rdx,rbx`). **Нельзя** считать эту ветку переключателем State Separation без дальнейшего анализа.

Оба образа содержат `ApiSetSchemaExtensions`, `Core Platform Extensions`, `Core Security Extensions`, но точная функция, определяющая набор расширений и загружающая `statesepext.sys`/`cmimcext.sys`, **не установлена**. Стандартный каталог импортов `winload.efi` не даёт искомой цепочки.

Подтверждённое соответствие схем и фактических IAT-ссылок:

`Factory OS API Set` → `ext-ms-win-ntos-stateseparation-l1-1-0` → `statesepext.sys` → `nt!_imp_ExpInitializeStateSeparationPhase*`.

`Windows 26100 API Set` → тот же контракт, но **без целевого модуля** → резервные точки входа NT.

В Windows 26100 другой контракт `ext-ms-win-ntos-kcminitcfg-l1-1-0` отображается на `cmimcext.sys`, что подтверждено живой системой. Это показывает, что **общая инфраструктура существует**; детали порядка загрузки ещё предстоит доказать.

### 4.1. Точные идентификаторы символов загрузчиков

Оба бинарных загрузчика ссылаются в PE Debug Directory на **`winload_prod.pdb`** (не `winload.pdb`):

- 22000: GUID `B507952F-E1BD-A9F4-B735-EB0DBC808809`, Age `2`.
- 26100: GUID `9927675C-FD39-CE15-D67A-27231BD80F42`, Age `2`.

Это даёт возможность запросить соответствующие **публичные символы Microsoft** через WinDbg для обозначения внутренних функций. Наличие GUID не гарантирует публикацию/полноту PDB.

## 5. Что установлено и какой следующий безопасный эксперимент

**Установлено:** (1) обе версии NT содержат по три реальных callsite; (2) 26100 принимает ответ `STATUS_NOT_SUPPORTED`; (3) `statesepext` имеет 26/26 совпадающих импортов; (4) его три фазы зависят от входных флагов и данных реестра; (5) у 26100 остаётся рабочая архитектура расширений, подтверждённая `cmimcext`.

**Не установлено:** (1) является ли ABI внутренних параметров Phase2 совместимым с 26100 в режиме State Separation; (2) кто и когда должен формировать `CmStateSeparationEnabled` и подготовленные хранилища; (3) полный граф функций `winload.efi`, отвечающих за загрузку extension host; (4) достаточно ли для WCOS Core сопоставления API Set — сейчас **не следует** так предполагать.

**Следующий шаг без перезагрузки:** на Activation01 в WinDbg с загруженными символами NT, остановив ядро через Break, выполнить:

```text
ln nt+0xBD9A4E
ln nt+0xC1EE3A
ln nt+0xAC8F2E
```

Эти команды определят имена трёх вызывающих функций по публичному NT PDB (если имена доступны). Это поможет привязать фазы к точному месту инициализации в 26100.

**Следующий этап после имён:** только на отдельном `FOS-KD-Test` исследовать `winload_prod.pdb` и точки перехода к Core Platform Extensions в boot debugger; на эталонных `FOS`/`FOS-SS-Test` и на рабочих образах 26100 ничего не изменять. Ни `statesepext.sys` 22000, ни API Set 22000 **не переносить** в Activation01 до проверки состояний ульев и ABI.

## 6. Ссылки на общую модель (не на приватную реализацию Factory OS)

- Microsoft Learn, [Windows API sets](https://learn.microsoft.com/en-us/windows/win32/apiindex/windows-apisets).
- Microsoft Learn, [API set loader operation](https://learn.microsoft.com/en-us/windows/win32/apiindex/api-set-loader-operation).

Эти материалы объясняют принцип отделения контракта от модуля, но не документируют конкретный недоступный публично механизм Windows Core OS kernel extension host.
