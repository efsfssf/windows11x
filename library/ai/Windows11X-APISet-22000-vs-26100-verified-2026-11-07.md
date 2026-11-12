# Windows 11X — сравнение API Set 22000 / 26100

Дата анализа: 2026-11-07. Источники: `FOS22000-CoreComponents.zip` (Factory OS 22000.1) и загруженный `apisetschema.dll` (Windows 26100 из PoC01).

## Проверенные исходные данные

- Factory OS: размер PE 143672 байта; SHA-256 `49696B4557023EA455AA7731A18467B623292A7C2EC837D8437A9C2DCD9CD498`.
- Windows 26100: размер PE 152992 байта; SHA-256 `AEAAEA67B6C57B4D85CC1D7B724F2EC8FE57AA3210C7AEE78B3F40E231FD70F1`.
- Оба PE: `AMD64`, `.apiset` version 6.
- Количество контрактов: Factory OS 945, Windows 26100 974.
- Поле `API_SET_NAMESPACE.Flags` в обоих образах равно 0. Его нельзя путать с `API_SET_NAMESPACE_ENTRY.Flags`.

## Прямое сравнение контрактов

| Контракт | Factory OS 22000 | Windows 26100 |
|---|---|---|
| `ext-ms-win-ntos-stateseparation-l1-1-0` | `statesepext.sys`; EntryFlags=`0x0` | `(empty)`; EntryFlags=`0x1` |
| `ext-ms-win-core-stateseparationext-l1-1-0` | `onecorestateseparationext.dll`; EntryFlags=`0x0` | `(empty)`; EntryFlags=`0x1` |
| `ext-ms-win-ntos-kcminitcfg-l1-1-0` | `(empty)`; EntryFlags=`0x0` | `cmimcext.sys`; EntryFlags=`0x1` |
| `api-ms-win-stateseparation-helpers-l1-1-0` | `kernelbase.dll`; EntryFlags=`0x0` | — |
| `api-ms-win-stateseparation-helpers-l1-1-1` | — | `kernelbase.dll`; EntryFlags=`0x1` |

В Windows 26100 961 из 974 контрактов имеют `EntryFlags=1`; в Factory OS 22000 все 945 контрактов имеют `EntryFlags=0`.

## Интерпретация и границы доказанного

`API_SET_SCHEMA_ENTRY_FLAGS_SEALED=1` — известный флаг, обычно запрещающий переопределение контракта через схему расширений при композиции загрузчиком. Это подтверждает документация формата/API Set, но реальное поведение конкретного `winload.efi` 26100 ещё не проверено трассировкой.

Оба нужных контракта State Separation имеются в 26100, но **не имеют назначенных хостов и имеют `EntryFlags=1`**. Прямое добавление Registry API Set Extension не является обоснованным первым вариантом интеграции. Подпись старого драйвера 22000 не гарантирует бинарной или ABI-совместимости.

В Factory OS контракты ведут к **двум разным компонентам**: `statesepext.sys` (3 фазы ядра) и `onecorestateseparationext.dll` (`InitializeStateSeparation`, пользовательский режим), что необходимо учитывать при проектировании системы.

## Следующее безопасное действие

Изучить семантику `Sealed` в точной версии `winload.efi` 26100.1742 офлайн или прицельно проверить наличие согласованной современной реализации State Separation в специализированной Windows той же ветки. Не изменять системные `apisetschema.dll`, BCD или `StateSeparation\Policy\Enabled` в работающей PoC01 до доказательства цепочки загрузки.
