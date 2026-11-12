# SEALED: условия, поля и точные границы проверенного поведения

Все code RVA — `winload.efi` 10.0.26100.9444, SHA-256 `1c1a978448b8e0fc2284c13d6e55fde8bf9827dca432cdc8a9be66c2e729adda`. Raw instructions: [winload-v7.asm](evidence/winload-v7.asm). File offset инструкции в `.text` = RVA−`0xC00`.

Подтверждено: SEALED проверяется при overlay **уже найденного контракта**. Исходная schema может содержать SEALED-контракт с непустым Host: такой случай реально присутствует в оригинальной DLL. При этом исходный State Separation содержит SEALED version record и family без Host. Конкретный State Separation с другим Host в принятой базовой schema здесь не создавался и не проверялся.

## Разные пространства flags

| Объект | Поле | Значение у целей | Связь с проверками |
|---|---|---|---|
| Legacy namespace | DWORD N+8 | 0 | Не per-contract SEALED |
| Embedded namespace | BYTE E+2=N+0x1E | 8 | Bit0 — gate composition всей schema; bit2 — routing resolver; bit3/4 — hashing |
| Legacy contract | DWORD Entry+0 | State=1, Trace=0 | Legacy SEALED bit0 |
| Compact **version** | BYTE record+0xA | State=1, Trace=0 | Проверки SEALED type3 читают bit0 отсюда |
| Compact **family** | BYTE record+0xA | у обоих `0x10` | Bit1 — mapping/Host-present; bit4 не объявляется SEALED |
| Compact alias | BYTE record+6 | к целям не применяется | Type2 composer читает bit0 этого поля |
| Legacy value | DWORD Value+0 | у целей 0 | Независимые flags; не отождествляются с namespace flags |

Особенно важно: два compact records имеют поле `+0xA`, но это не делает flags family и version одинаковыми. SEALED State хранится по `N+0x25A1A` (file `0x27A1A`, RVA DLL `0x28A1A`); его family flags по `N+0x21162` равны `0x10`.

## Namespace gates — отдельно от contract SEALED

| Code RVA | Инструкции / вход | Условие | Результат |
|---|---|---|---|
| `0x2322D..0x23247` | Выбор flags N+0x1E для wrapper с embedded7; `0x23245: test al,1` | Bit0 namespace установлен | OslLoadApiSetSchema обходит последующую обработку extensions; это gate всей schema |
| `0x27AA60..0x27AA83` | Выбор flags из V6+embedded7, чистого V6 или standalone7 | `0x27AA83: test al,1` | При 1 переход `0x27AABA`, EAX=`0xC000000D` (INVALID_PARAMETER) |
| `0x27AA87..0x27AA9B` | Проверка вида базовой schema | Не standalone7 и не wrapper6 с embedded marker7 | Тот же отказ `0x27AABA` |
| `0x2795C6..0x2795D4` | Legacy base namespace Flags, только legacy composer | Bit0 установлен | INVALID_PARAMETER; этот legacy путь не выбирается для исходной schema с embedded7 |

Факт `E+2=8` означает, что bit0 namespace у исследуемой базы сброшен. Он не отменяет SEALED bit отдельной version record.

## Выбор V7 composer и перенос базовых данных

`ApiSetComposeSchema` начинается по `0x27955C`. `0x27958B..0x2795AD` направляет standalone7 либо wrapper6 с embedded marker7 к `0x279C34`, откуда вызывается V7 composer `0x27AA24`. В отличие от resolver routing, в этом dispatch нет gate `(N+0x1E & 4)`.

Последовательность в `0x27AA24`:

1. `0x27AAA3 → 0x27A1B0`: проверка base; `0x27AAC9 → 0x278F20`: проверка extension.
2. `0x27AAF7 → 0x27BE18`: создание context, base pointer становится E, при wrapper сохраняется признак legacy companion.
3. `0x27AB09 → 0x27AC40` для extension byte7 либо `0x27AB10 → 0x27C488` для V6 extension: подсчёт добавок.
4. `0x27AB24 → 0x27BEFC`: размер по блокам; `0x27AB2E`: allocation.
5. `0x27AB77 → 0x27C014`: header/layout; `0x27ABA9 → 0x27C128`: перенос исходных pool, legacy region, compact records/indices и importer block.
6. `0x27ABCD → 0x27AEAC` либо `0x27ABD4 → 0x27C684`: применение extension.
7. `0x27ABE5`: проверка результата; `0x27ABFC/0x27AC03`: публикация нового buffer/size после успешной обработки.

Копирование family/version/host blocks в `0x27C128..0x27C289` не содержит проверки SEALED. Например, family `count*20` копируется на `0x27C194..0x27C1BA`, version — `0x27C1EA..0x27C210`, hosts — `0x27C215..0x27C23B`. Множитель20 здесь включает record12 и index8. Предсуществующий default-host не удаляется только из-за version SEALED.

## V6 extension → compact base: проверки SEALED

V6 host-extension представление читается по его роли: outer entry задаёт имя **host**, вложенные value Host-поля задают контракты. Это видно в `0x27C4E1..0x27C531` (outer entry name ищется в host table) и `0x27C59B..0x27C5A8` (value `+0xC/+0x10` передаются parser контракта). Нельзя считать любое V6 extension записью обычного base mapping «contract→host».

| Code RVA | Проверенные инструкции и входные поля | Условие / результат инструкций | Интерпретация в пределах этого пути |
|---|---|---|---|
| `0x27C5C7..0x27C5D4` | Lookup контракта в descriptor E+0x44; AX сравнивается с `0xFFFF` | Если отсутствует — sizing новых version/family на `0x27C5D6..0x27C5F3` | Проверка SEALED существующей записи доступна только после успешного поиска |
| `0x27C5FC..0x27C622` | Вычисляется version pointer через descriptor.stride, base bias; `44 84 44 01 0a`: `test byte [rcx+rax+0xA],r8b`, R8D=1 | `0x27C627: jne 0x27C646` | SEALED существующего version пропускает учёт importer node для данного value; это не глобальный отказ |
| `0x27C629..0x27C63F` | Value.NameOffset по `value+4` | Ненулевой importer→+1 node и +0x10 bytes; нулевой→ничего | Эта часть sizing выполняется только после SEALED gate либо для отсутствующей записи |
| `0x27C9BF..0x27C9CA` | `0x27C9C5`: запись WORD version+4=family index; `45 84 58 0a`: `test byte [r8+0xA],r11b`, R11D=1 | `0x27C9CE: jne 0x27CB02` | SEALED пропускает mapping update. Link WORD пишется **до** gate; нельзя утверждать побайтную неизменность всей version record |
| `0x27C9D4..0x27CA93` | При ненулевом Value.NameOffset строится node со старым default и importer; меняются family+6/+8 | Достижимо после SEALED gate | Старый default сохраняется как importer-specific host для указанного importer; новый host становится default |
| `0x27CA9A..0x27CA9F` | `or byte [r14+0xA],2`; WORD family+4=new host index | Достижимо после gate | Устанавливается mapping/Host-present независимо от длины новой host-строки |
| `0x27CAAA..0x27CAFE` | Поиск legacy companion entry и запись legacy value host offset/length | Только при наличии wrapper companion и после gate | Legacy результат пересобирается из нового compact host |
| `0x27CB02..0x27CB33` | Следующий value / outer entry | После skip SEALED обработка продолжается | SEALED не эквивалентен ошибке всей композиции |

Outer host может быть найден/добавлен раньше проверки contract SEALED: sizing `0x27C531..0x27C55B`, emission `0x27C7A7..0x27C7D4`. Следовательно, extension с SEALED target не обязан оставить весь namespace побайтно прежним: возможны добавленные host/pool данные, которые не стали mapping этого target. Подтверждённый запрет здесь относится к применению mapping к найденному SEALED version, а не ко всякой allocation/copy.

## Extension byte7 → compact base

Это другая ветвь, выбранная по `byte[extension]==7`. Её extension records и values отличаются от компактных base records. Наличие обоих путей не означает переносимость произвольного schema header между ними.

| Code RVA | Вход / точные действия | Условие | Результат |
|---|---|---|---|
| `0x27AD15..0x27AD2E` | Для parser type2 берётся flags BYTE найденного alias+6 | Передаётся в общий bit0 gate | Роль SEALED для alias подтверждена применением, полная alias-семантика не восстановлена |
| `0x27AD6D..0x27AD86` | Для type3 берётся flags BYTE найденного version+0xA | Передаётся в общий gate | Та же версия-контракт, что у двух целей |
| `0x27AD8A..0x27AD8F` | `and dl,r8b`, R8D=1; `test dl,dl`; `jne 0x27AE6E` | Bit0=1 | Sizing пропускает values этого extension contract; переходит к следующему |
| `0x27B187..0x27B1B3` | Для type2 читаются семейство/старый default/old importer head, AL=alias+6 | Общий gate | Подготовка не означает применённый overlay |
| `0x27B24A..0x27B271` | Для type3 читается family, AL=version+0xA | Общий gate | Проверяется существующая compact запись |
| `0x27B274..0x27B276` | `a8 01`: `test al,1`; `jne 0x27B515` | SEALED | Emission пропускает обновление family и обработку значений этого контракта |
| `0x27B27F..0x27B2E2` | Для unsealed задаётся family link; дополнительные flags для type2/type3 по extension | После gate | Изменяется найденный либо вновь созданный record |
| `0x27B3BE..0x27B4B2` | При наличии importer имени node получает старый default; новый host становится default (`family.flags |= 2`, family+4) | После gate | То же направление importer/default overlay, что в V6 host-extension пути |

Когда запись отсутствует, helper создаёт record; соответствующие пути не требуют проверки SEALED несуществующего объекта. Эта работа описывает это ветвление, но не предлагает способы создания альтернативных версий или обхода ограничений.

## Legacy V6 composer: контрольная карта

| Code RVA | Вход | Условие | Результат |
|---|---|---|---|
| `0x279709..0x27970D` | `mov eax,[rax]` найденного legacy namespace entry; `test al,1` | Bit0=1 | Skip sizing данного overlay к `0x279748` |
| `0x279AB2..0x279AB6` | `mov eax,[rdi]` legacy entry; `test al,1` | Bit0=1 | Skip emission к `0x279BA4` |

Эти инструкции проверены по байтам, но исходный wrapper6+embedded7 dispatch выбирает V7 composer; нельзя использовать legacy ветвь как доказательство конкретного исполнения в Trace boot.

## Resolver не превращает SEALED в «запрет базового Host»

В обычном type3 resolver `0x27A6F8` проверяет descriptor hash-compression bit2; `0x27A709` проверяет **version flags bit2**, при необходимости сравнивая requested revision с byte version+9. В этой цепочке нет проверки version.flags bit0. Далее default/importer Host выбирается из family (`0x27A71D..0x27A9F6`). Проверка `family.flags&2` означает наличие mapping, а не SEALED.

Оригинальный `ext-ms-win-ntos-tm-l1-1-0` доказывает сочетание SEALED и непустого resolver-visible base Host без изменения каких-либо битов: version711 flags=1, family648 flags=`0x12`, host183=`tm.sys`. Источники и воспроизводимый тест — [VERIFIED.md](VERIFIED.md), [v7-positive-controls.json](evidence/v7-positive-controls.json).

Следовательно, проверенное правило для рассматриваемого пути: **существующий SEALED contract не принимает обычный host overlay в этих composer branches; уже содержащийся в base Host читается resolver обычным способом**. Это не обещание загрузки SYS, доверия образу или совместимости ABI. У State Separation в исследуемой оригинальной базе Host отсутствует по данным family647. Поддерживаемая и принятая системой иная base-конфигурация именно этого контракта остаётся отдельным вопросом.

Проверки привязаны к найденному version/alias record, не к строковому префиксу `ext-ms-win-ntos-*` и не к family.flags `0x10`. У двух целей имеется ровно по одному version record на family. Поведение иных типов, смешанных семейств и всех возможных extension flags за пределами описанных ветвей здесь не объявляется полностью восстановленным.
