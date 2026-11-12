# UNKNOWN — оставшаяся неопределённость после восстановления selector

## U01 — какой entry реально получил бы выбранный Host на target

Static normal/new-import path устанавливает dependency flag и сохраняет INIT. В данной работе нет target snapshot реально принятого F Host: текущая базовая schema такого Host не выбирает. Поэтому фактические Flags конкретного runtime entry, повторное использование image и последующие изменения classification не измерены.

W `0x1C04E..0x1C067` и generic existing-entry path `0x19D42F..0x19D450` показывают, почему нельзя заменять этот вопрос одним успехом binding. Новая статическая гарантия условна по classification; она существенно сильнее прежнего «lifetime вообще неизвестен», но не является наблюдением текущей VM.

## U02 — весь image lifetime и явный discard

Исключение K `0xA53999` восстановлено для normal/default selector с R9=NULL, используемого MiFreeDriverInitialization. Address-directed path `MmDiscardDriverSection` K `0xC6A101..0xC6A11B → MiSnapDriverRange` использует другую ветвь `0xA5388B`. Whole-image unload также отдельная операция (`MmUnloadSystemImage`, K+0xAA6520).

Не доказано, что произвольный внешний компонент никогда не запросит такой discard/unload для данного image. Проверенный automatic init-cleanup path этого не делает для F INIT dependency image. Retention здесь не объявляется бессрочным pin всех mappings или всех physical pages. Переход image-backed страницы в нерезидентное состояние также не равен use-after-free; нужна оценка mapping/prototype state.

## U03 — точный собственный lifecycle Factory OS22000

F22000.1 и matching statesepext.pdb доступны; placement INIT и code boundaries известны. Но соответствующих kernel/winload22000 bytes в предоставленных архивах нет. Нельзя установить по одному F SYS, использовал ли22000 тот же Flags bit, selector exception, порядок cleanup и list conversion.

Адреса K26100 не переносятся на22000; данные старого kernel26100.1742 не заменяют kernel22000. Это ограничение сравнения поколений, а не отрицательное доказательство для F на K26100.

## U04 — фактическое время и условия Phase2

Post-boot callable WNF fronts и call edges к root helper установлены. Для реального callback нужны допустимый lifetime/access/context, пустой cache и результат создания нового key. Созданные раньше roots и уже существующие registry keys обычно устраняют Phase2 из данного запроса.

Не установлено, какие stores и silo caches заполнены до конца boot на конкретной активной State Separation конфигурации и когда/почему они меняются позже. Нельзя утверждать ни «все Phase2 до cleanup», ни «Phase2 обязательно после cleanup». Это не препятствует статическому INIT-retention выводу, который не требует запрета late calls.

## U05 — глобальная сериализация shared state F

Обнаруженные WNF mechanisms имеют ограниченную роль: handle publication после callback; per-silo sequence lock берётся после root call; critical region меняет per-thread KernelApcDisable. Они не доказывают взаимное исключение всех возможных Phase2 callbacks и Phase0/Phase2 обращений к shared registry scratch F+0x3060/+0x3068.

Полный external lock graph, одновременное создание разных store roots, silo/registry callback reentrancy и фактический IRQL не восстановлены. Риск гонки не объявляется наблюдённой гонкой. Retention code не закрывает эти вопросы автоматически.

## U06 — что данный результат не устанавливает

Этот этап не устанавливает принятие/доверие F на target, готовность OSData/hives, функциональную State Separation, servicing или совместимость всех зависимостей. Предыдущие VERIFIED результаты 26 imports/Phase ABI используются как вход и не повторялись. Исследование отвечает на конкретный вопрос об automatic INIT cleanup, а не разрешает развёртывание смешанного набора.
