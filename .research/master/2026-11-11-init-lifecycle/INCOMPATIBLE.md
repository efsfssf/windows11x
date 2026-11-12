# INCOMPATIBLE — установленные отрицательные и условные случаи

## Для штатного dependency-image cleanup доказанного INIT-блокера нет

Для стандартного исследованного пути26100 «новый import Host → entry.Flags&0x04000000 → normal initialization cleanup» **не установлено освобождение F INIT**. Напротив, K `MiSnapDriverRange` `0xA536FA → 0xA53999 → 0xA5370F` исключает эту секцию из selected discardable range. Поэтому прежнее опасение нельзя повышать до вывода «Phase2 обязательно вызывает освобождённую память».

## I01 — обычный discardable treatment не даёт нужной гарантии

Если image entry не имеет dependency bit и не попадает под другие skip conditions, F INIT проходит generic discardable branch K `0xA53707/0xA53709 → 0xA538DB`. MiFreeDriverInitialization передаёт найденный range в freeing routine на K `0xA53411`; там есть decommit path `0x7F6873`. Phase2 RVA F+0x8360 находится в этом INIT range.

Следовательно, **контракт с поздним вызовом INIT callback несовместим с фактическим освобождением этого range при сохранённом IAT target**. Это подтверждённое условие нарушения lifetime, а не наблюдённый случай на target VM. Наличие всех его runtime-предпосылок не доказано. В нормальном dependency path главный предпосылочный шаг — выбор INIT для освобождения — как раз исключён.

Обычный драйверный cleanup реально существует: IopLoadDriver K `0x94C81A → MmFreeDriverInitialization`. Но нельзя автоматически присвоить ту же classification kernel-imported Host или считать, что один успех binding оставляет её неизменной. У W повторное использование существующего image может сохранять либо очищать dependency classification (`0x1C04E..0x1C067`); его фактическое состояние надо знать.

## I02 — неверные универсальные правила опровергнуты

| Утверждение | Контрдоказательство |
|---|---|
| DISCARDABLE означает безусловное освобождение INIT после Phase1 | K `0xA536FA/0xA53999` добавляет dependency/name exception |
| Для DLL cleanup вообще не вызывается | Общий enumerate pass K `0x705B9F`, callback `0x7F6610`; dynamic DLL path K `0x94E9E0` вызывает тот же Mi helper |
| Успешный binding сам по себе pin-ит INIT | Решение читает entry.Flags и section metadata; существующий entry может иметь другую classification |
| CMPXCHG root cache сериализует Phase2 | K `0xB0F996` вызывает Phase2 раньше publication `0xB0F9C5` |
| Размещение caller в PAGE доказывает фактический late Phase2 | Нужны uncached root и новый key: K `0x8816F8..0x8816FE`, `0xB0F978` |
| Правила K26100 автоматически являются правилами Factory22000 | Kernel/winload22000 с соответствующими bytes в доступном наборе отсутствуют |

Ни один из этих выводов не требует и не предлагает изменения loader flags, PE sections, IAT или boot policies.
