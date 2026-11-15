/* Owner-only seven-day recovery. Snapshot files remain outside the web root. */
let backupsOffset=0,backupsBusy=false;
function renderBackupRows(rows){
 return rows.map(b=>`<article class="backup-row"><header><strong>Версия ${esc(b.revision)}</strong><span>${esc(new Date(b.createdAt).toLocaleString('ru-RU'))}</span></header><p>До действия: <strong>${esc(b.beforeAction.action)}</strong> · ${esc(b.beforeAction.actor)}${b.beforeAction.target?' · '+esc(b.beforeAction.target):''}</p>${b.beforeAction.reason?`<p>${esc(b.beforeAction.reason)}</p>`:''}<small>Доступна до ${esc(new Date(b.expiresAt).toLocaleString('ru-RU'))}</small><div class="backup-actions"><button type="button" class="button button-light" data-preview-backup="${esc(b.id)}">Посмотреть</button><button type="button" class="button button-light" data-download-snapshot="${esc(b.id)}">Скачать</button><button type="button" class="button button-light" data-restore-snapshot="${esc(b.id)}" data-snapshot-revision="${esc(b.revision)}">Восстановить весь сайт</button></div></article>`).join('');
}
async function loadServerBackups(reset=true){
 if(!auth.editor||backupsBusy)return;
 backupsBusy=true;document.getElementById('backupsStatus').textContent='Загрузка копий…';
 try{
  if(reset){backupsOffset=0;document.getElementById('backupsList').innerHTML='';document.getElementById('backupPreview').textContent='';}
  const data=await api('backups&offset='+backupsOffset);
  document.getElementById('backupsList').innerHTML+=renderBackupRows(data.backups);
  document.getElementById('backupsStatus').textContent=data.total?`Копий за последние 7 дней: ${data.total}`:'Копий пока нет. Первая появится перед следующим изменением.';
  document.getElementById('moreBackups').hidden=data.nextOffset===null;backupsOffset=data.nextOffset??0;
 }catch(error){document.getElementById('backupsStatus').textContent=error.message;}
 finally{backupsBusy=false;}
}
async function restoreServerSnapshot(id,revision){
 if(!auth.editor||backupsBusy)return;
 if(artifactBusy||artifactFormOpen||pendingSave||saving||editorDraft||commentBusy||commentDrafts.size){toast('Сначала сохраните или скачайте свои черновики. Восстановление их заменит.');return;}
 backupsBusy=true;
 try{
  const current=await api('version');
  if(!confirm(`Восстановить ВЕСЬ сайт из версии ${revision}? Сейчас на сервере версия ${current.revision}. Все более поздние изменения содержимого и плана будут отменены. Перед восстановлением текущая версия сохранится на 7 дней.`))return;
  const result=await api('restore-backup',{revision:current.revision,backupId:id});
  const data=await api('data');serverRevision=data.revision;useContent(data.content);state=normalizePlan(data.state);saveBlocked=false;
  closeTask();hideModal('backupsOverlay');render();setSync('Восстановлено · версия '+result.revision);toast('Сайт восстановлен. Предыдущее состояние тоже сохранено на 7 дней.');
 }catch(error){document.getElementById('backupsStatus').textContent=error.message;toast(error.message);}
 finally{backupsBusy=false;}
}
document.addEventListener('click',async event=>{
 if(event.target.closest('#backupsButton')){if(!auth.editor)return;showModal('backupsOverlay');document.getElementById('backupsClose').focus();await loadServerBackups();}
 if(event.target.closest('[data-close-backups]')&&!backupsBusy){hideModal('backupsOverlay');document.getElementById('backupsButton').focus();}
 if(event.target.closest('#moreBackups'))await loadServerBackups(false);
 if(event.target.closest('#refreshBackups'))await loadServerBackups();
 const preview=event.target.closest('[data-preview-backup]'),download=event.target.closest('[data-download-snapshot]'),restore=event.target.closest('[data-restore-snapshot]');
 if((preview||download)&&auth.editor&&!backupsBusy){
  backupsBusy=true;
  try{
   const id=preview?.dataset.previewBackup||download.dataset.downloadSnapshot;
   const data=await api((preview?'backup-info':'backup')+'&id='+encodeURIComponent(id));
   if(download)downloadBackup({app:'Windows11X Atlas',version:2,content:data.snapshot.content,state:data.snapshot.state},'Windows11X-revision-'+data.metadata.revision+'.json');
   else {
    document.getElementById('backupPreview').textContent=`Версия ${data.metadata.revision} · этапов: ${data.gates} · задач: ${data.tasks} · исследований: ${data.research} · комментариев: ${data.comments} · файлов: ${(data.artifacts||[]).length}. Копия создана перед действием ${data.metadata.beforeAction.action}. Полное восстановление заменит весь проект. Отдельные файлы можно вернуть кнопками ниже.`;
    document.getElementById('backupPreview').innerHTML+=(data.artifacts||[]).map(f=>`<div class="backup-file"><strong>${esc(f.title)}</strong><small>${esc(f.filename)} · ${esc(f.version)}</small><a href="api/files.php?action=download&amp;id=${encodeURIComponent(f.id)}&amp;backupId=${encodeURIComponent(id)}">Скачать файл</a><button class="button button-light" type="button" data-restore-artifact="${esc(f.id)}" data-artifact-backup="${esc(id)}">Восстановить только этот файл</button></div>`).join('');
   }
  }catch(error){toast(error.message);document.getElementById('backupsStatus').textContent=error.message;}
  finally{backupsBusy=false;}
 }
 if(restore)await restoreServerSnapshot(restore.dataset.restoreSnapshot,restore.dataset.snapshotRevision);
});
