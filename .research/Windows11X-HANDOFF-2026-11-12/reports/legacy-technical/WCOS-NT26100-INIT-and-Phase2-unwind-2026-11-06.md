# Windows 11X — уточнение ABI-аудита: ранние вызовы и цепочки unwind

Дата: 2026-11-06. Объект: Factory OS NT 22000 ↔ Windows NT 26100. Основано на двух ранее полученных `ntoskrnl` и предоставленном пользователем выводе WinDbg на работающем Activation01. VM, BCD и реестр не изменялись.

## 1. Почему Phase0/Phase1 не дизассемблируются после загрузки

В WinDbg на работающей Windows 26100:
- `u nt+0xBD9A4E` показал нули и данные;
- `u nt+0xC1EE3A` показал повторяющиеся `0xA5`;
- `u nt+0xAC8F2E` показал корректный код Phase2.

Сверка исходного PE **подтвердила ранее вычисленные RVA**. Первые два находятся в `INIT` — секции инициализации, которую ОС может освободить/переиспользовать после старта. Следовательно, позднее чтение этих адресов в отладчике не воспроизводит исходный машинный код. Из одних байтов позднего снимка нельзя делать вывод о ложности исходного RVA.

| Фаза | NT 22000 RVA | Секция 22000 | NT 26100 RVA | Секция 26100 | Оригинальная инструкция в файле 26100 |
|---|---:|---|---:|---|---|
| Phase0 | `0xB0B60B` | `INIT` | `0xBD9A4E` | `INIT` | `48 FF 15 1B B9 56 FF` → `call [nt!_imp_ExpInitializeStateSeparationPhase0]` |
| Phase1 | `0xB10280` | `INIT` | `0xC1EE3A` | `INIT` | `48 FF 15 27 65 52 FF` → `call [nt!_imp_ExpInitializeStateSeparationPhase1]` |
| Phase2 | `0x7797B5` | `PAGE` | `0xAC8F2E` | `PAGE` | `48 FF 15 43 C4 67 FF` → `call [nt!_imp_ExpInitializeStateSeparationPhase2]` |

Для 26100 .pdata/unwind границы функций:
- Phase0 `0xBD912C–0xBDA403` — соответствует `nt!IoInitSystemPreDrivers` по PDB/WinDbg.
- Phase1 `0xC1EDE4–0xC1F1B4` — соответствует `nt!Phase1InitializationIoReady`.
- Phase2 `0xAC8EBC–0xAC8F78` — дополнительный фрагмент, связанный с основной функцией, см. далее.

## 2. Phase2: восстановление исходной функции по UNW_FLAG_CHAININFO

В NT 26100 .pdata запись для `0xAC8F2E`:

```text
RUNTIME_FUNCTION = (Begin=0xAC8EBC, End=0xAC8F78, UnwindInfo=0x08F750)
UNWIND_INFO at 0x08F750: Version=2, Flags=4 (UNW_FLAG_CHAININFO)
Chained RUNTIME_FUNCTION = (Begin=0x935030, End=0x9350F5, UnwindInfo=0x08F738)
```

Основная функция `0x935030` обозначена в публичных символах как `nt!ExpWnfGetNameStoreRegistryRoot`. Таким образом, фрагмент `0xAC8EBC–0xAC8F78` относится к этой функции; большой числовой офсет, показанный `ln`, объясняется вынесенным фрагментом кода. Это подтверждается структурным `CHAININFO`, а не только именем ближайшего символа.

У NT 22000 Phase2 находится в `PAGE` в unwind-диапазоне `0x779668–0x7797D3`, без такого `CHAININFO`; по этому факту нельзя автоматически называть его родительскую функцию без проверки символов 22000.

## 3. Входные параметры и обработка статусов NT 26100

В **исходном** `ntoskrnl.exe` 26100 перед Phase0:

```asm
call  nt+0x7A7AD8
mov   bl,al
call  nt+0x9FE750
mov   cl,al
mov   dl,bl
call  qword ptr [nt!_imp_ExpInitializeStateSeparationPhase0]
```

В Phase1:

```asm
cmp   dword ptr [nt!CmStateSeparationEnabled],r15d
setne cl
call  qword ptr [nt!_imp_ExpInitializeStateSeparationPhase1]
```

В Phase2:

```asm
cmp   dword ptr [nt!CmStateSeparationEnabled],0
lea   r8,[rdi+0x18]
lea   rdx,[rdi+8]
setne cl
add   r8,rsi
add   rdx,rsi
call  qword ptr [nt!_imp_ExpInitializeStateSeparationPhase2]
```

Все три исходных callsite имеют ветку обработки `STATUS_NOT_SUPPORTED`, но это само по себе не гарантирует успешное включение механизма.

В памяти *работающей* Activation01 код Phase2 отличается от исходного PE: прямой `call nt!DefaultAddProfileSource` и `mov r10,[nt!_imp_...]` вместо исходного непрямого вызова. Это наблюдение о динамическом коде; точную причину изменения (оптимизация/перепривязка импорта загрузчиком и т.п.) **не объявляем доказанной** без дополнительной отладки.

## 4. Безопасное продолжение

На остановленной Activation01 в WinDbg можно получить ближайшие символы функций, формирующих флаги Phase0, без обращения к уже освобождённой секции `INIT`:

```text
ln nt+0x7A7AD8
ln nt+0x9FE750
```

При необходимости для дополнительной проверки структуры Phase2:

```text
.fnent nt+0xAC8F2E
ln nt+0x935030
```

Точки останова на Phase0/1 в уже загруженном госте не ставить: это инициализационный код, которого в памяти может уже не быть. Для динамического наблюдения нужна отдельная VM и отладка на соответствующей фазе загрузки. Никаких подмен `statesepext.sys`, API Set, BCD, реестра или изменяющих команд пока не требуется.

## 5. Ограничения

PE-структуры, секции, bytes callsite и unwind подтверждены анализом локальных файлов. Точное имя caller для Phase0/1 взято из предоставленного WinDbg (`ln`) и совпадает с границами `.pdata`. Связь фрагмента Phase2 с основным caller основана на `CHAININFO` и имени основной функции из WinDbg. Семантика обеих функций, которые возвращают `AL` перед Phase0, пока не установлена.
