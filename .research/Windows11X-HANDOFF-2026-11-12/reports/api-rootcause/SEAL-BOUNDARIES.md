# Границы действия `SEALED`

## Какие flags различать

- **Namespace.Flags:** внешний v6 `+0x08=0` в оригинале 26100, A и B. Встроенный header 7 имеет свой flags byte по внешнему `+0x1E=0x08`; `OslLoadApiSetSchema` проверяет его bit 0 перед последующей композицией (`0x2322D–0x23247`). Эти флаги относятся к схеме в целом.
- **Contract Entry.Flags:** State Separation legacy entry по `+0x4328` имеет `1`; Trace по `+0x4358` имеет `0`. Это per-contract SEALED в доступном v6 представлении. Полное соответствие compact V7 entry для State Separation отдельно не декодировано.
- **VALUE_ENTRY.Flags:** независимое поле value. Число `2` в нём не эквивалентно `API_SET_SCHEMA_FLAGS_HOST_EXTENSION=2` и не доказано как способ изменить SEALED mapping.

## Что показывает код

| Ситуация | Вывод |
|---|---|
| Неизменённый base 26100 | Legacy State Separation имеет пустой Host; resolver-visible V7 mapping для него в данном исследовании отдельно не снимался. Наличие SEALED не делает пустой Host непустым. |
| Выбор другого base через `BaseApiSetSchema` | `0x23171/0x231A9` меняют выбранный PE image до композиции. Сам contract SEALED bit не блокирует чтение принятой базовой схемы; image trust и согласованность её представлений — отдельные условия. Доказанного принятого 26100 base с непустым V7 State Separation Host здесь нет. |
| Обычное расширение/композиция | Legacy ветвь `ApiSetComposeSchema` `0x279709–0x27970D` проверяет bit 0 найденного entry и пропускает добавление host. V7 extension path `0x27C622–0x27C646` проверяет bit 0 компактной записи, а при выдаче host есть аналогичная проверка `0x27C9CA–0x27CB02`. Это кодовое основание для ограничения overlay поверх SEALED контракта. Оно не доказывает, что все мыслимые base representations отвергаются. |
| Разрешение имени Host | Resolver использует активную схему. В данном PE v6+embedded7 направляется в V7 resolver (`0x279181–0x27919F`). SEALED относится прежде всего к возможности extension-композиции; по одному SEALED bit нельзя объявить успешную или невозможную загрузку конкретного host. `STATUS_SUCCESS` и marker 1 допускают Length 0. |

Factory OS 22000 имеет State Separation `Flags=0`, Host=`statesepext.sys` (30 UTF-16LE байт); его структура отличается от 26100, а ранее зафиксированный 26100 crash при использовании этого base произошёл до проверки Host. STrace демонстрирует unsealed Trace extension, не решение для SEALED State Separation. PhaseBinding изменял legacy v6 value у SEALED entry, но не подтвердил изменение embedded V7 host; его память и resolver проверялись в разных загрузках.

**Итог:** обычное extension-композирование существующего SEALED контракта ограничено проверками entry bit 0. Теоретическая возможность непустого Host в принятой согласованной base-схеме не исключается самим этим bit, но в приложенных материалах не доказана. Host mapping, проверка доверия PE/SYS, связывание импортов, ABI и фактическое State Separation — разные этапы.
