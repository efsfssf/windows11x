# Спецификация кандидата для Microsoft / OEM

Нужен проверяемый исходный комплект для внутреннего engineering/testing **PC x64 Windows Core OS на NT26100**, пригодный для согласования с ntoskrnl26100.9457 / winload26100.9444 / API Set26100.9278. Номер WSK/SDK без версии OS payload недостаточен.

Просим указать, существует ли такой поддерживаемый для PC комплект, его точную product/package identity, доступность/entitlement и разрешённый канал получения. Если его нет — нужен явный ответ об отсутствии доступного PC26100 продукта, а не ссылка на Xbox/IoT desktop/Validation OS.

Требуемые части для инженерной приёмки:

1. Подлинный amd64 schema PE с назначенным `ext-ms-win-ntos-stateseparation-l1-1-0` host и соответствующим user-mode State Separation host; согласованность остальных контрактов с обслуженной26100 либо официальный полный matching baseline.
2. Kernel extension с экспортами `ExpInitializeStateSeparationPhase0/1/2`, совместимый ABI и lifecycle, его MUM/CAT/payload provider, SHA-256 и версия.
3. Подтверждённый signing/provisioning маршрут для **раннего API Set schema/kernel-extension signing scenario**, а не только обычного PnP driver. В исследованном winload enforced schema scenario1 не входит в custom-kernel-signer widening scenarios2/3/4/7. Требуется конкретный поддерживаемый ответ для этой категории образа.
4. Image composition/OEMInput/FM, источники OSBOOT/OSDATA/DEVICES и early-load descriptors/flags, OSDataRoot и volume layout. Storage Spaces/BCD packages — если эта композиция их требует.
5. Совместимые XRo/config, SSBoot/state reconciliation и update/reset/recovery/servicing package dependencies, если задействованы композицией. Поддерживаемая стратегия обновления до последующих cumulative updates.
6. Exact source media/build ID и SHA-256 каждого payload, сведения о совместимости PC/Hyper-V Gen2 и разрешённом использовании.

Запрос не предполагает отключения CI, изменения подписанных ntoskrnl/winload/apisetschema или переноса runtime22000 в26100. Собственные файлы проекта и результаты офлайн-приёмки предоставляются отдельно. Сообщения поставщику автоматически не отправлялись.
