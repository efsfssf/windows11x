/* Live context, OAuth connection management and conflict-aware refresh. */
const aiPrompt='Используй подключение Windows11X. Перед каждым новым исследовательским выводом и советом о галочке вызывай get_project_context, сообщай revision и время чтения. Нужные первоисточники читай через get_research, включая все страницы. Если чтение не удалось, прямо скажи, что актуальность не подтверждена. Для галочки указывай точный taskId, этап, название, критерий proof и доказательство. Сохраняй результаты через add_comment или save_research с датой исследования, источником и уровнем доказательства. Изменяй отметку через set_task_status только при выполненном критерии. Перед записью читай свежую revision; после конфликта перечитай данные и пересмотри изменение. Сообщай версию успешного сохранения. Гипотезы и OFFLINE_TEST не приравнивай к RUNTIME.';
async function openAiPanel(){
 showModal('aiOverlay');document.getElementById('aiClose').focus();
 document.getElementById('aiMcpUrl').value=new URL('api/mcp.php',location.href).href;
 document.getElementById('aiPrompt').value=aiPrompt;
 document.getElementById('aiConnectionsSection').hidden=!auth.editor;
 document.getElementById('aiConnections').textContent='Загрузка…';
 if(auth.editor)await loadAiConnections();
}
async function loadAiConnections(){
 try{
  const data=await api('ai-connections',{});
  document.getElementById('aiConnections').innerHTML=data.connections.length?data.connections.map(c=>`<article class="ai-connection"><div><strong>${esc(c.name)}</strong><small>${esc(c.scope.includes('atlas:write')?'Чтение и запись':'Только чтение')} · до ${esc(new Date(c.expiresAt).toLocaleString('ru-RU'))}</small></div><button type="button" class="button button-light" data-revoke-ai="${esc(c.id)}">Отозвать</button></article>`).join(''):'<p>Подключений пока нет. Добавьте MCP в своём ИИ и разрешите доступ на странице входа этого сайта.</p>';
 }catch(error){document.getElementById('aiConnections').textContent=error.message;}
}
document.addEventListener('click',async event=>{
 if(event.target.closest('#aiButton'))await openAiPanel();
 if(event.target.closest('[data-close-ai]')){hideModal('aiOverlay');document.getElementById('aiButton').focus();}
 if(event.target.closest('#copyAiPrompt')){await copyText(aiPrompt+'\n\nMCP: '+new URL('api/mcp.php',location.href).href+'\nБез MCP можно прочитать '+new URL('ai-context.php',location.href).href+' и полные тексты '+new URL('ai-context.php?full=1',location.href).href);toast('Инструкция для ИИ скопирована');}
 if(event.target.closest('#copyLiveContext')){
  try{const response=await fetch('ai-context.php',{cache:'no-store'});if(!response.ok)throw Error('Не удалось получить актуальный контекст');const text=await response.text();await copyText(text);toast('Контекст с сервера скопирован · версия '+response.headers.get('X-Atlas-Revision'));}catch(error){toast(error.message);}
 }
 const revoke=event.target.closest('[data-revoke-ai]');
 if(revoke&&auth.editor&&confirm('Отозвать доступ этого ИИ? Для повторной записи потребуется новое подключение.')){
  revoke.disabled=true;try{await api('ai-revoke',{id:revoke.dataset.revokeAi});await loadAiConnections();toast('Доступ отозван');}catch(error){toast(error.message);revoke.disabled=false;}
 }
});
let refreshingAtlas=false;
let linkedTaskOpened=false;
function openLinkedTask(){
 if(linkedTaskOpened)return;linkedTaskOpened=true;
 const id=new URL(location.href).searchParams.get('task');if(!id)return;
 const found=findTask(id);if(!found){toast('Задача по ссылке не найдена');return;}
 expandedGates.add(found.gate.id);route='plan';location.hash='#plan';render();openTask(id);
}
async function refreshAtlasIfChanged(){
 if(!serverReady||refreshingAtlas||document.hidden)return;
 refreshingAtlas=true;
 try{
  const version=await api('version');if(version.revision<=serverRevision)return;
  if(pendingSave||saving||editorDraft||commentBusy||commentDrafts.size){
   saveBlocked=true;setSync('На сервере новая версия '+version.revision+'. Скачайте копию черновика и загрузите актуальные данные.');document.getElementById('reloadData').hidden=false;return;
  }
  const data=await api('data');
  if(data.revision<=serverRevision||pendingSave||saving||editorDraft||commentBusy||commentDrafts.size)return;
  serverRevision=data.revision;useContent(data.content);state=normalizePlan(data.state);render();
  if(currentTaskId){if(findTask(currentTaskId))openTask(currentTaskId);else closeTask();}
  setSync('Обновлено с сервера · версия '+serverRevision);
 }catch{setSync('Не удалось проверить обновления. Последняя загруженная версия: '+serverRevision);}
 finally{refreshingAtlas=false;}
}
setInterval(refreshAtlasIfChanged,30000);
window.addEventListener('focus',refreshAtlasIfChanged);
document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshAtlasIfChanged();});
