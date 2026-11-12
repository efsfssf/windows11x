# Windows 11X / NT 26100 — API Set TraceSmoke: подтверждённый checkpoint

**Дата отчёта:** 2026-11-10  
**Назначение:** фиксирует новые динамические наблюдения WinDbg, уточняет ранее выдвинутые версии и определяет следующую исследовательскую задачу.  
**Статус:** TraceSmoke — диагностическая стадия, не подтверждённый механизм подключения произвольного SYS и не доказательство работающей State Separation.  
**Происхождение:** реальные выводы WinDbg, присланные пользователем 2026-11-10; сравнение с архивом `Windows11X-APISet-26100-Complete-Research-Handoff-2026-11-09.zip` и исследовательскими файлами `FACTS.md`, `STRUCTURES.md`, `DATAFLOW.md`, `SEAL-BOUNDARIES.md`, `ROOT-CAUSE.md`. Выводы разделены по уровню доказательности.

## 0. Главное резюме

В первой контролируемой загрузке `winload.efi` действительно держал изменённый **legacy V6** Trace Host `WcosTraceSmoke.sys` в памяти до самого вызова `OslpApplyDynamicSchemaExtensions`. После возврата именно из этого вызова указатель и размер API Set изменились, а добавленная строка исчезла из итогового буфера. Это **непосредственно подтверждено** тремя точками останова A/B/C в одной загрузке.

В отдельной последующей загрузке внутри `OslpApplyDynamicSchemaExtensions` динамический predicate DTrace вернул `AL=0` и исполнение перешло к **пустой UTF-16 строке** вместо `dtrace.sys`. Это **непосредственно подтверждено** WinDbg.

Имеется хорошо поддержанная причинная модель: V6-правка не затрагивает компактное представление 7; динамическая композиция перестраивает схему из представления 7, добавляя пустую запись Trace. **Однако** полная схема всех compact V7-records не расшифрована; следовательно, не следует утверждать, что каждая промежуточная запись и каждый байт итогового буфера были пошагово наблюдаемы.

**Не путать** Trace (`Entry.Flags=0`) и State Separation (`Entry.Flags=1`, SEALED). Trace-контроль сам по себе не доказывает допустимость переопределения SEALED-контракта.

## 1. Входные бинарники: идентичность и границы эксперимента

| Компонент | Версия / SHA-256 |
|---|---|
| `winload.efi` | NT 26100.9444 — `1c1a978448b8e0fc2284c13d6e55fde8bf9827dca432cdc8a9be66c2e729adda` |
| `ntoskrnl.exe` | NT 26100.9457 — `d90c69cfa925d42037ef1cdba3ff5b3f13cc14e16060938073b97e396fdde2f2` |
| original `ApiSetSchema.dll` | NT 26100.9278 — `391a2818fdef14f4a9b6e6ce2a7e7f01d04f943de9ef543ec683927cfe480889` |
| `B-DualSize/ApiSetSchema.dll` | `ba382268c00918b4a99b2efe3577782673aa6be58e7b567a776e43fca21583f9` |

Пользователь **отдельно сообщил**, что SHA-256 установленной внутри VM `C:\Windows\System32\WCOS-TraceSmoke\ApiSetSchema.dll` совпал с B-DualSize. Числовое значение hash внутри нового PowerShell-вывода не приложено, но проверка подтверждена пользователем. Более того, точка A напрямую доказывает, что изменённые поля присутствовали в памяти `winload`.

Эксперимент использовал альтернативный путь через `BaseApiSetSchema`. Предупреждение boot debugger о недействительной подписи и допуске под отладкой **не доказывает** нормальную работу под Secure Boot / Code Integrity. Эксперимент проводился в лабораторной VM; это не поддерживаемый способ обслуживания Windows.

### Исходные структуры

- Внешняя обёртка v6: `Version=6`, `Count=984`, `Namespace.Flags=0`, `EntryOffset=0x98`, `HashOffset=0xAA30`, `HashFactor=0x1F` на `+0x18`.
- По `+0x1C` — embedded header с маркером 7 (`07 00 08 FF`), **не** `HashFactor`.
- Оригинальный namespace `Size=0x29C60`; B-DualSize имеет внешние и embedded размеры `0x29C84`.
- Trace v6: namespace entry `+0x4358`, `Flags=0`, `ValueOffset=0x94F0`, `ValueCount=1`; B value `HostOffset=0x29C60`, `HostLength=0x24`, UTF-16 `WcosTraceSmoke.sys`.
- State Separation v6: entry `+0x4328`, `Flags=1` (SEALED), value `+0x94C8`, Host пуст в оригинале 26100.
- Объявленные полезные embedded V7-блоки в B заканчиваются на `0x29C60`; V6 patch не переразмечал содержимое этих блоков.
- V7 resolver для этого формата выбирается через `ApiSetResolveToHost`, RVA `0x279181..0x27919F` → RVA `0x27A60C`.

RVA привязаны **только** к названному `winload.efi` 26100.9444; WinDbg runtime VA зависит от ASLR.

## 2. Первая загрузка — непосредственный эксперимент A / B / C

Три точки останова в `winload!OslLoadApiSetSchema`:

```text
bp winload+0x23222   ; A: схема скопирована/опубликована
bp winload+0x2335f   ; B: перед call OslpApplyDynamicSchemaExtensions
bp winload+0x23375   ; C: после возврата динамической обработки
```

### A — RVA `0x23222`, до обработки расширений

```text
Breakpoint 0 hit
winload!OslLoadApiSetSchema+0x186
rip=00000000`01182222

kd> dq winload+0x354f00 L1
00000000`014b3f00  fffff800`77595f20
kd> dq winload+0x354ef8 L1
00000000`014b3ef8  00000000`00029c84
kd> dd poi(winload+0x354f00) L10
fffff800`77595f20  00000006 00029c84 00000000 000003d8
fffff800`77595f30  00000098 0000aa30 0000001f ff080007
fffff800`77595f40  00029c84 00000000 ffffffff 001c0074
fffff800`77595f50  00080090 00000098 0000c858 03de03d8
kd> dd (poi(winload+0x354f00)+0x94f0) L5
fffff800`7759f410  00000000 00000000 00000000 00029c60
fffff800`7759f420  00000024
kd> du (poi(winload+0x354f00)+0x29c60)
fffff800`775bfb80  "WcosTraceSmoke.sys"
```

**Доказано:** данные B-DualSize (размеры, legacy Trace value, имя Host) реально поступили в ранний буфер загрузчика. Это сильнее, чем только сообщение о пути PE или SHA диска.

### B — RVA `0x2335F`, до `OslpApplyDynamicSchemaExtensions`

```text
Breakpoint 1 hit
winload!OslLoadApiSetSchema+0x2c3:
00000000`0118235f  e870fcffff  call winload!OslpApplyDynamicSchemaExtensions
rip=00000000`0118235f

kd> dq winload+0x354f00 L1
00000000`014b3f00  fffff800`77595f20
kd> dq winload+0x354ef8 L1
00000000`014b3ef8  00000000`00029c84
kd> dd poi(winload+0x354f00) L10
fffff800`77595f20  00000006 00029c84 00000000 000003d8
fffff800`77595f30  00000098 0000aa30 0000001f ff080007
fffff800`77595f40  00029c84 00000000 ffffffff 001c0074
fffff800`77595f50  00080090 00000098 0000c858 03de03d8
kd> dd (poi(winload+0x354f00)+0x94f0) L5
fffff800`7759f410  00000000 00000000 00000000 00029c60
fffff800`7759f420  00000024
```

**Доказано:** перед вызовом динамической обработки указатель и проверенные значения V6 Trace остаются как в A. Не заявлять, что проверен *каждый байт* буфера, поскольку полный двоичный dump A/B не сохранялся.

### C — RVA `0x23375`, после `OslpApplyDynamicSchemaExtensions`

```text
Breakpoint 2 hit
winload!OslLoadApiSetSchema+0x2d9:
00000000`01182375  488b05841b3300  mov rax,qword ptr [winload!OslApiSetSchema]
rip=00000000`01182375

kd> dq winload+0x354f00 L1
00000000`014b3f00  fffff800`78724d20
kd> dq winload+0x354ef8 L1
00000000`014b3ef8  00000000`00029c74
kd> dd poi(winload+0x354f00) L10
fffff800`78724d20  00000006 00029c74 00000000 000003d8
fffff800`78724d30  00000098 0000aa30 0000001f ff080007
fffff800`78724d40  00029c74 00000000 ffffffff 001c0074
fffff800`78724d50  00080090 00000098 0000c858 03de03d8
kd> dd (poi(winload+0x354f00)+0x94f0) L5
fffff800`7872e210  00000000 00000000 00000000 0001f304
fffff800`7872e220  00000000
kd> s -u poi(winload+0x354f00) L0x29c84 "WcosTraceSmoke.sys"
[нет совпадений]
```

**Доказано:** между B и C, внутри `OslpApplyDynamicSchemaExtensions`, **поменялись указатель активного namespace, размер и legacy Trace value**. После вызова итоговая активная схема не содержит тестовую строку по выполненному поиску. Итоговый `HostOffset=0x1F304` при `HostLength=0` — **не** UTF-16 имя: в памяти это бинарные данные.

| Поле | A | B | C |
|---|---|---|---|
| `OslApiSetSchema` | `fffff80077595f20` | `fffff80077595f20` | `fffff80078724d20` |
| Size | `0x29C84` | `0x29C84` | `0x29C74` |
| v6 Trace HostOffset | `0x29C60` | `0x29C60` | `0x1F304` |
| v6 Trace HostLength | `0x24` | `0x24` | `0` |
| Поиск тестовой строки | найдена в A | offset/length сохраняются | не найдена в C |

Статическое арифметическое объяснение `0x29C60 + 0x14 = 0x29C74` согласуется с V7 rebuild, но **конкретная инструкция добавления 0x14 не трассировалась в момент выполнения**.

## 3. Вторая загрузка — непосредственно установлена пустая ветка DTrace

Это **другая загрузка**; не выдавать все записи ниже за продолжение того же boot A/B/C.

Поставлена точка после вызова косвенного predicate:

```text
bp winload+0x23023
...
Breakpoint 0 hit
winload!OslpApplyDynamicSchemaExtensions+0x4f:
00000000`01182023 84c0  test al,al
kd> r al
al=0
kd> dq @rbx L3
00000000`01411db0  00000000`01419a30 00000000`0141a008
00000000`01411dc0  00000000`01181f48
```

Дальнейшее исполнение **без изменения памяти**:

```text
kd> p
00000000`01182025 7406  je  winload!OslpApplyDynamicSchemaExtensions+0x59
kd> p
00000000`0118202d 488d05c47b2900  lea rax,[winload!`string' (00000000`01419bf8)]
kd> p
00000000`01182034 4889442430  mov qword ptr [rsp+30h],rax
kd> p
00000000`01182039 4c8d442468  lea r8,[rsp+68h]
kd> du @rax
00000000`01419bf8  ""
```

**Доказано:** predicate DTrace в этой загрузке вернул 0; условный переход пошёл в ветку `empty_string`; `RAX` действительно указывал на пустую UTF-16 строку.

### Объяснение по машинному коду

`OslpDtraceExtensionEnabled`, RVA `0x22F48`, проверяет:

```asm
call BlGetApplicationEntry
mov  edx,26000145h
call BlGetBootOptionBoolean
... ; ошибка чтения приводит к ложному результату
... ; если выключено, return AL=0
call OslpIsApiSetHosted ; если BCD включено
... ; возвращает bool
```

`OslpIsApiSetHosted` по RVA `0x22E8C`:

```asm
call ApiSetResolveToHost
... ; проверка статуса
... ; проверка resolved marker
... ; проверка Host.Length != 0
call OslpDoesApiSetHostExist ; проверка существования, возможны две попытки
```

`OslpApplyDynamicSchemaExtensions` (RVA `0x22FD4`) независимо от истинности predicate строит extension через `ApiSetCreateSchema` и передаёт его `ApiSetComposeSchema`. При `AL=0` выбирает пустую строку, а не `dtrace.sys`.

**Не установлено:** где именно predicate получил false — чтение/значение BCD `dtrace`, пустой ответ V7 resolver или отсутствие фактического host по пути, проверяемому `OslpDoesApiSetHostExist`. Из `AL=0` нельзя выводить однозначно конкретную первичную причину.

## 4. Итог по TraceSmoke: строгое разделение выводов

**Непосредственно подтверждено:**

1. B-DualSize загружен в буфер, и его legacy Trace Host присутствует в точке A.
2. В точке B legacy Host остаётся; в точке C после динамической обработки выделено другое представление/буфер, в котором имя тестового SYS не обнаружено.
3. В отдельной загрузке встроенное условие Trace возвращает `AL=0`, и выбрана пустая строка для генерируемого расширения.
4. В более раннем runtime-тесте `ApiSetResolveToHost` для Trace дал `eax=0`, marker 1 и `UNICODE_STRING.Length=MaximumLength=0`; это **обработанный запрос с пустым Host**, не успешная загрузка SYS.
5. В используемом PE имеются V7-composer/resolver, и структура B содержит внутренние блоки представления 7, оставленные патчером нетронутыми.

**Сильная, но ещё не полностью доказанная реконструкция:** замена активной схемы при динамической композиции строится по embedded V7 данным; поскольку B менял только legacy V6 Host и appended UTF-16 за пределами объявленных компактных блоков, результат не сохраняет тестовый Host; пустой dynamic Trace extension согласуется с `0x29C74` и нулевой длиной.

**Важно:** это не эксперимент по `SEALED` State Separation. Trace legacy entry имеет flags 0. Больше не повторять TraceSmoke без конкретного нового вопроса: доказательства основной стадийности уже получены.

## 5. State Separation / SEALED: текущая техническая граница

Исследуемый контракт:

```text
ext-ms-win-ntos-stateseparation-l1-1-0
```

- В оригинальном 26100.9278 legacy `API_SET_NAMESPACE_ENTRY` имеет `Flags=1` (`SEALED`), `ValueOffset=0x94C8`, пустой Host.
- В Factory OS 22000 этот контракт имеет `Flags=0` и Host `statesepext.sys`, но FOS схема v6 `Count=945` несовместима с современным embedded-7 workflow 26100 (`Count=984`). Ранее FOS база вызвала `c0000005` при композиции до проверки самого State Separation Host.
- В `winload.efi` проверки младшего бита записи действительно встречаются в коде композиции (`0x279709..0x27970D` legacy и `0x27C622..0x27C646` / `0x27C9CA..0x27CB02` embedded V7 согласно предыдущему статическому аудиту). Это подтверждает **ограничения обычной композиции/extension overlay** поверх SEALED-контракта.
- Не установлено, может ли корректная и доверенная базовая схема 26100 содержать resolver-visible непустой Host State Separation. Никакого подтверждённого успешного связывания `statesepext.sys` с ядром 26100 пока нет.
- Даже успех host resolution **не означает** загрузку совместимого драйвера, корректную привязку импортов Phase0/1/2, State Separation, неизменяемый MainOS или сервисируемость.

## 6. Следующая работа — НЕ новая правка бинарников

**Рекомендуемый следующий этап: полностью офлайновый read-only анализ embedded V7 представления оригинального `ApiSetSchema.dll` 26100.9278, в первую очередь записи State Separation.** Это тот момент, когда имеет смысл подключить мощный ИИ, способный систематически анализировать дизассемблер и построить валидирующий декодер.

Не просить создавать патч, обходить `SEALED`, подписи или Code Integrity. Сначала нужна доказательная карта представления 7 и его правил.

### Конкретные вопросы

1. Как compact V7 хранит mapping `ext-ms-win-ntos-stateseparation-l1-1-0`? Найти индекс, hash, flags, default-host и importer-specific values (если они есть) из **оригинальных** байтов.
2. Получить такую же карту для `ext-ms-win-ntos-trace-l1-1-0` как контроль и сопоставить с подтверждёнными WinDbg результатами.
3. Какие именно compact flags компоновщик и resolver считают SEALED? Сопоставить с legacy `Entry.Flags=1` и показать конкретные RVA проверок.
4. Привести схемы read-only проверки значений, исключающие ошибку смещений (namespace-relative vs embedded-relative); отдельно отобразить границы всех блоков.
5. Определить, какая часть операции `OslpApplyDynamicSchemaExtensions` копирует/пересоздаёт wrapper и какие compact blocks участвуют. Не выдавать гипотезу о конкретной инструкции за runtime-трассу.
6. Какой контракт/импортер использует ядро 26100 по Phase0/1/2? Сопоставить с уже полученным ABI-аудитом, не делая вывода о пригодности FOS драйвера лишь по совпадению экспортов.
7. Явно перечислить то, что можно проверить офлайн, и что потребовало бы будущей read-only трассы VM.

**Критерий завершения этапа:** независимый, воспроизводимый декодер/документация компактных V7-записей Trace и State Separation, адресная карта реально использующих их ветвей winload, аккуратно установленная граница SEALED. Не требуется и не подразумевается создание работающего modified host.

## 7. Решение по лабораторной VM

- Не менять BCD, политики доверия, ядро, стандартный `System32\ApiSetSchema.dll` или установленные драйверы в рамках следующего офлайн-этапа.
- Не объявлять включённую с boot debugger экспериментальную схему проверенной для штатной защищённой загрузки.
- Если WinDbg ещё стоит на breakpoint — можно очистить исследовательские breakpoint через `bc *` и продолжить через `g`, если требуется завершить тест. До следующего рискованного теста воспользоваться сохранённым checkpoint VM.

## 8. Откуда брать полные файлы

Архив `Windows11X-APISet-26100-Complete-Research-Handoff-2026-11-09.zip` содержит оригинальные файлы 26100, Factory OS 22000, STrace, PhaseBinding/TraceSmoke (A/B), исходные скрипты, оригинальные логи предыдущих загрузок, hash manifest и отчёты. **Он не содержит добавленные здесь runtime-выводы 2026-11-10**, поэтому приложить к нему этот checkpoint.

Детальное задание для мощного ИИ: `Windows11X-APISet-26100-V7-SEALED-Offline-Research-Task-2026-11-10.md`.
