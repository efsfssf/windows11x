/* Owner session, serialized server writes and a data-driven content editor. */
let auth = {editor:false, csrf:'', configured:false};
let serverRevision = 0, serverReady = false, pendingSave = false, saving = false;
let saveTimer = null, saveBlocked = false, editorDraft = null, editorSection = 'site';
let editorSelection = 0, editorBaseline = '';
const sectionLabels = {site:'Оформление и навигация',texts:'Тексты всех страниц',gates:'Этапы и задачи',days:'Хронология',architecture:'Архитектура',terms:'Словарь',research:'Исследования',metadata:'Дата и артефакт',overviewBlocks:'Карточки обзора',myths:'Частые неверные выводы'};
const fieldLabels = {name:'Название сайта',subtitle:'Подзаголовок',latest:'Последняя точка',footer:'Подпись внизу',footerNote:'Пояснение внизу',nav:'Названия разделов',overview:'Обзор',plan:'План',architecture:'Архитектура',timeline:'Хронология',library:'Исследования',glossary:'Словарь',snapshot:'Дата среза',artifactHash:'SHA-256 артефакта',id:'Идентификатор',title:'Название',short:'Кратко',detail:'Подробности',description:'Описание',proof:'Критерий результата',done:'Отмечено по умолчанию',focus:'Выделить этап',status:'Цвет статуса',statusText:'Текст статуса',context:'Контекст',tasks:'Задачи',date:'Дата',text:'Текст',tags:'Теги',evidence:'Уровень доказательства',nt:'Наш кандидат',fos:'Эталон',summary:'Краткое описание',topic:'Тема',group:'Источник',format:'Формат',path:'Исходное имя файла',content:'Содержимое документа',featuredResearchId:'ID исследования для ссылки на главной',volumes:'Роли хранения',phases:'Фазы',label:'Подпись',letter:'Буква тома',when:'Когда выполняется',proofSteps:'Лестница доказательств',corrections:'Исправления',flow:'Маршрут проверок',complete:'Достигнуто',current:'Текущий шаг',before:'Прежний вывод',after:'Исправленный вывод'};
function formatDate(value){return /^\d{4}-\d{2}-\d{2}$/.test(value)?value.split('-').reverse().join('.'):value;}
function canEdit(){if(auth.editor&&serverReady&&!saveBlocked)return true;toast(saveBlocked?'Есть конфликт сохранения. Сохраните копию и загрузите актуальные данные.':'Для изменения войдите как владелец');return false;}
async function api(action,body){
 const response=await fetch('api/index.php?action='+action,{method:body===undefined?'GET':'POST',credentials:'same-origin',cache:'no-store',headers:body===undefined?{}:{'Content-Type':'application/json','X-CSRF-Token':auth.csrf},body:body===undefined?undefined:JSON.stringify(body)});
 let data;try{data=await response.json();}catch{throw new Error('Для входа и общего сохранения нужен PHP 8.1+ на сервере');}
 if(!response.ok){const error=new Error(data.error||'Ошибка сервера');error.status=response.status;throw error;}
 return data;
}
function useContent(next){
 content=structuredClone(next);GATES=content.gates;DAYS=content.days;TERMS=content.terms;ARCH=content.architecture;RESEARCH=content.research;
 SNAPSHOT=content.snapshot;DUAL_SHA=content.artifactHash;
 if(!ARCH[archMode])archMode='nt';archIndex=Math.max(0,Math.min(archIndex,ARCH[archMode].length-1));
}
async function replacePlan(next){
 next=normalizePlan(next);
 if(pendingSave||saving){await flushSaves();if(pendingSave||saving)throw new Error('Дождитесь сохранения текущих изменений');}
 const result=await api('save',{revision:serverRevision,content,state:next});serverRevision=result.revision;state=next;setSync('Сохранено на сервере');
}
async function initializeAtlas(){
 content.research=RESEARCH;
 try{
  const [session,data]=await Promise.all([api('session'),api('data')]);auth=session;serverRevision=data.revision;
  if(data.content)useContent(data.content);
  state=normalizePlan(data.state);serverReady=true;
  render();setSync('Данные загружены · версия '+serverRevision);if(typeof openLinkedTask==='function')openLinkedTask();
 }catch(error){setSync('Нет связи с сервером');document.getElementById('accessNotice').textContent=error.message;render();}
}
function updateChrome(){
 const site=content.site;
 document.title=site.name+' — '+site.subtitle;document.querySelector('.brand').setAttribute('aria-label',site.name+' — обзор');
 document.getElementById('brandTitle').textContent=site.name;document.getElementById('brandSubtitle').textContent=site.subtitle;
 document.querySelector('.crumb-root').textContent=site.name;
 document.getElementById('latestPoint').textContent=site.latest;
 document.getElementById('snapshotDate').textContent='Срез знаний · '+content.snapshot;
 document.getElementById('footerTitle').textContent=site.footer;document.getElementById('footerNote').textContent=site.footerNote;
 document.querySelector('.nav-number').textContent=String(content.gates.length).padStart(2,'0');
 const nav=site.nav||{};document.querySelectorAll('[data-nav-label]').forEach(el=>{if(nav[el.dataset.navLabel])el.textContent=nav[el.dataset.navLabel];});
 document.getElementById('loginButton').hidden=auth.editor;document.getElementById('logoutButton').hidden=!auth.editor;document.getElementById('editButton').hidden=!auth.editor;
 document.getElementById('accessNotice').textContent=auth.editor?'Вы вошли как владелец. Изменения доступны всем после сохранения.':serverReady?'Режим просмотра. Изменять данные может только владелец.':'Режим просмотра. Подключение к серверу…';
 document.body.classList.toggle('is-editor',auth.editor);
}
function setSync(text){document.getElementById('syncStatus').textContent=text;document.getElementById('noteSaved').textContent=text;}
function queueSave(){if(!auth.editor||!serverReady||saveBlocked)return;pendingSave=true;setSync('Есть несохранённые изменения…');clearTimeout(saveTimer);saveTimer=setTimeout(()=>flushSaves(),500);}
async function flushSaves(){
 clearTimeout(saveTimer);if(saving||!pendingSave||saveBlocked)return !pendingSave;
 saving=true;document.getElementById('retrySave').hidden=true;
 try{while(pendingSave){pendingSave=false;const snapshot=JSON.stringify({content,state});setSync('Сохранение…');const result=await api('save',{revision:serverRevision,...JSON.parse(snapshot)});serverRevision=result.revision;setSync('Сохранено на сервере');}}
 catch(error){pendingSave=true;if(error.status===409)saveBlocked=true;if(error.status===401)auth.editor=false;setSync(error.message);document.getElementById('retrySave').hidden=saveBlocked;document.getElementById('reloadData').hidden=!saveBlocked;toast(error.message);updateChrome();return false;}
 finally{saving=false;}
 return true;
}
function showModal(id){document.getElementById(id).hidden=false;document.body.style.overflow='hidden';}
function hideModal(id){document.getElementById(id).hidden=true;document.body.style.overflow='';}
function openEditor(){if(!canEdit())return;editorDraft=structuredClone(content);for(const task of state.custom){const gate=editorDraft.gates.find(g=>g.id===task.gate);if(gate){const {custom,gate:gateId,...fields}=task;gate.tasks.push(fields);}}editorBaseline=JSON.stringify(editorDraft);editorSelection=0;renderEditor();showModal('editorOverlay');document.getElementById('editorSection').focus();}
function pathValue(path){return path.reduce((obj,key)=>obj[key],editorDraft);}
function fieldPath(path){return esc(JSON.stringify(path));}
function editorField(value,path,label){
 const attr=`data-editor-path="${fieldPath(path)}"`;
 if(Array.isArray(value)&&path[0]==='terms'&&path.length===2)return value.map((item,i)=>editorField(item,[...path,i],i?'Объяснение':'Термин')).join('');
 if(Array.isArray(value))return `<fieldset class="editor-array"><legend>${esc(label)} (${value.length})</legend>${value.map((item,index)=>`<details class="editor-item"><summary>${esc(typeof item==='object'?(item?.title||item?.[0]||'Запись '+(index+1)):item||'Пустая строка')}</summary>${editorField(item,[...path,index],String(index+1))}<div class="editor-row-actions"><button type="button" data-array-up="${fieldPath([...path,index])}" ${index?'':'disabled'}>Выше</button><button type="button" data-array-down="${fieldPath([...path,index])}" ${index<value.length-1?'':'disabled'}>Ниже</button><button type="button" data-array-remove="${fieldPath([...path,index])}">Удалить</button></div></details>`).join('')}<button type="button" class="button button-light" data-array-add="${fieldPath(path)}">Добавить запись</button></fieldset>`;
 if(value&&typeof value==='object')return Object.entries(value).filter(([key])=>key!=='day').map(([key,item])=>editorField(item,[...path,key],fieldLabels[key]||(path[0]==='texts'?String(item).trim().slice(0,90)||key:key))).join('');
 if(typeof value==='boolean')return `<label class="editor-checkbox"><input type="checkbox" ${attr} ${value?'checked':''}>${esc(label)}</label>`;
 const key=path.at(-1);
 if(key==='status')return `<label class="editor-field">${esc(label)}<select ${attr}>${['partial','wait','future','complete'].map(x=>`<option value="${x}" ${value===x?'selected':''}>${({partial:'Частично',wait:'Ожидает',future:'Позже',complete:'Завершено'})[x]}</option>`).join('')}</select></label>`;
 if(key==='format')return `<label class="editor-field">${esc(label)}<select ${attr}>${['md','json','csv','txt','ps1','py'].map(x=>`<option ${value===x?'selected':''}>${x}</option>`).join('')}</select></label>`;
 const short=['id','date','artifactHash','snapshot','name','title','format','path'].includes(key);
 return `<label class="editor-field">${esc(label)}${short?`<input ${attr} value="${esc(value)}" ${key==='id'?'readonly':''} ${key==='date'?'type="date"':''}>`:`<textarea ${attr} rows="${key==='content'?18:String(value).length>200?4:2}">${esc(value)}</textarea>`}</label>`;
}
function renderEditor(){
 document.getElementById('editorSection').value=editorSection;
 let value=editorDraft[editorSection], path=[editorSection];
 let selection='';
 if(editorSection==='metadata'){value={snapshot:editorDraft.snapshot,artifactHash:editorDraft.artifactHash};path=[];}
 if(editorSection==='texts'){
  const keys=Object.keys(value);const prefix=document.getElementById('editorTextPage').value||'overview';
  value=Object.fromEntries(keys.filter(k=>k.startsWith(prefix+'.')).map(k=>[k,value[k]]));
  selection='<p>Все значения — обычный текст. Разметка страницы сохраняется.</p>';
 }
 if(Array.isArray(value)){
  editorSelection=Math.max(0,Math.min(editorSelection,value.length-1));
  selection=`<div class="editor-select-row"><select id="editorRecord" aria-label="Запись">${value.map((x,i)=>`<option value="${i}" ${i===editorSelection?'selected':''}>${esc(x.title||x[0]||'Запись '+(i+1))}</option>`).join('')}</select><button type="button" class="button button-light" data-array-add="${fieldPath(path)}">Добавить</button>${value.length?`<button type="button" class="button button-danger" data-array-remove="${fieldPath([...path,editorSelection])}">Удалить</button><button type="button" data-array-up="${fieldPath([...path,editorSelection])}" ${editorSelection?'':'disabled'}>↑</button><button type="button" data-array-down="${fieldPath([...path,editorSelection])}" ${editorSelection<value.length-1?'':'disabled'}>↓</button>`:''}</div>`;
  path=[...path,editorSelection];value=value[editorSelection]??null;
 }
 document.getElementById('editorTextPage').hidden=editorSection!=='texts';
 const title=editorSection==='texts'?'Текст':sectionLabels[editorSection];
 document.getElementById('editorFields').innerHTML=selection+(value===null?'<p>Добавьте первую запись.</p>':editorField(value,path,title));
}
function newItem(path){
 const key=path.at(-1),id='item-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,7);
 if(key==='gates')return {id,title:'Новый этап',subtitle:'',status:'wait',statusText:'Не начат',context:'',focus:false,tasks:[]};
 if(key==='tasks')return {id,title:'Новая задача',description:'',proof:'',done:false};
 if(key==='days')return {date:new Date().toISOString().slice(0,10),title:'Новое событие',text:'',tags:[]};
 if(key==='terms')return ['Новый термин','Объяснение'];
 if(key==='research')return {id,title:'Новое исследование',summary:'',topic:'Общее',group:'Автор',date:new Date().toISOString().slice(0,10),format:'md',path:id+'.md',content:''};
 if(key==='proofSteps')return {title:'Новая ступень',text:'',complete:false};
 if(key==='corrections')return {title:'Новое исправление',text:''};
 if(key==='flow')return {title:'Новый шаг',current:false};
 if(key==='volumes')return {letter:'',title:'Новая роль',text:''};
 if(key==='phases')return {label:'Новая фаза',title:'',text:'',when:''};
 if(key==='myths')return {before:'Прежний вывод',after:'Исправление',text:''};
 if(key==='nt'||key==='fos')return {title:'Новый узел',short:'',detail:'',evidence:'UNKNOWN'};
 return '';
}
function downloadBackup(value,name){const url=URL.createObjectURL(new Blob([JSON.stringify(value,null,2)],{type:'application/json'}));const a=document.createElement('a');a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}
function closeEditor(){if(editorDraft&&JSON.stringify(editorDraft)!==editorBaseline&&!confirm('Закрыть редактор без сохранения?'))return;editorDraft=null;hideModal('editorOverlay');}
document.addEventListener('input',event=>{
 const el=event.target;if(!el.matches('[data-editor-path]')||!editorDraft)return;
 const path=JSON.parse(el.dataset.editorPath),key=path.pop();pathValue(path)[key]=el.type==='checkbox'?el.checked:el.value;
});
document.addEventListener('change',event=>{
 if(event.target.id==='editorSection'){editorSection=event.target.value;editorSelection=0;renderEditor();}
 if(event.target.id==='editorTextPage')renderEditor();
 if(event.target.id==='editorRecord'){editorSelection=Number(event.target.value);renderEditor();}
});
document.addEventListener('click',async event=>{
 const target=event.target;
 if(target.closest('#loginButton')){showModal('loginOverlay');document.getElementById('loginUsername').focus();}
 if(target.closest('[data-close-login]'))hideModal('loginOverlay');
 if(target.closest('#editButton'))openEditor();
 if(target.closest('[data-close-editor]'))closeEditor();
 if(target.closest('#retrySave'))await flushSaves();
 if(target.closest('#downloadBackup,[data-download-backup]')){const backupState=structuredClone(state);if(editorDraft)backupState.custom=[];downloadBackup({app:'Windows11X Atlas',version:2,content:editorDraft||content,state:backupState},'Windows11X-full-backup.json');}
 if(target.closest('#reloadData,[data-reload-atlas]')){if(confirm('Перед загрузкой актуальной версии скачайте копию своих изменений. Продолжить?'))location.reload();}
 if(target.closest('#migrateLocal')){if(canEdit()&&confirm('Заменить общий план отметками и заметками из старого хранилища этого браузера?')){try{await replacePlan(loadState());render();toast('Старый план перенесён на сервер');}catch(error){toast(error.message);}}}
 if(target.closest('#logoutButton')){
  if((pendingSave||saving)&&!await flushSaves()){toast('Сначала сохраните изменения или скачайте резервную копию');return;}
  try{const result=await api('logout',{});auth={...auth,...result};closeTask();editorDraft=null;hideModal('editorOverlay');render();}catch(error){toast(error.message);}
 }
 for(const operation of ['add','remove','up','down']){
  const button=target.closest('[data-array-'+operation+']');if(!button||!editorDraft||!canEdit())continue;
  const path=JSON.parse(button.getAttribute('data-array-'+operation));
  if(operation==='add'){const list=pathValue(path);list.push(newItem(path));if(path.length===1)editorSelection=list.length-1;}
  else{
   if(operation==='remove'&&!confirm('Удалить запись из содержимого сайта? Изменение применится после сохранения.'))return;
   const index=path.pop(),list=pathValue(path);
   if(operation==='remove'){
    if(path[0]==='terms'&&path.length===2){toast('Удаляйте термин целиком');return;}
    list.splice(index,1);
   }else{const other=index+(operation==='up'?-1:1);if(other<0||other>=list.length)return;[list[index],list[other]]=[list[other],list[index]];if(path.length===1)editorSelection=other;}
  }
  renderEditor();break;
 }
});
document.getElementById('loginForm').addEventListener('submit',async event=>{
 event.preventDefault();const button=document.getElementById('loginSubmit');button.disabled=true;document.getElementById('loginError').textContent='';
 try{if(!auth.csrf)auth={...auth,...await api('session')};const result=await api('login',{username:document.getElementById('loginUsername').value,password:document.getElementById('loginPassword').value});auth={...auth,...result};if(!serverReady)await initializeAtlas();document.getElementById('loginPassword').value='';hideModal('loginOverlay');render();toast('Вы вошли. Редактирование доступно.');}
 catch(error){document.getElementById('loginError').textContent=error.message;}finally{button.disabled=false;}
});
document.getElementById('editorSave').addEventListener('click',async()=>{
 if(!canEdit()||!editorDraft)return;const button=document.getElementById('editorSave');button.disabled=true;
 try{
  if(pendingSave||saving){await flushSaves();if(pendingSave||saving)throw new Error('Дождитесь сохранения плана и повторите');}
  const nextState=structuredClone(state);nextState.custom=[];
  const result=await api('save',{revision:serverRevision,content:editorDraft,state:nextState});serverRevision=result.revision;state=nextState;useContent(editorDraft);editorDraft=null;hideModal('editorOverlay');render();setSync('Сохранено на сервере');toast('Содержимое обновлено для всех посетителей');
 }catch(error){if(error.status===409){saveBlocked=true;document.getElementById('reloadData').hidden=false;}setSync(error.message);toast(error.message);}finally{button.disabled=false;}
});
document.getElementById('restoreBackup').addEventListener('change',async event=>{
 const file=event.target.files?.[0];event.target.value='';if(!file||!canEdit())return;
 try{const data=JSON.parse(await file.text());if(data.app!=='Windows11X Atlas'||data.version!==2||!data.content||!data.state)throw new Error('Нужна полная резервная копия Windows11X');if(!confirm('Заменить всё содержимое сайта и общий план этой копией?'))return;
 if(pendingSave||saving){await flushSaves();if(pendingSave||saving)throw new Error('Сначала сохраните текущие изменения');}
 const result=await api('save',{revision:serverRevision,content:data.content,state:normalizePlan(data.state)});serverRevision=result.revision;state=normalizePlan(data.state);useContent(data.content);render();toast('Полная копия восстановлена на сервере');
 }catch(error){toast(error.message);}
});
window.addEventListener('beforeunload',event=>{if(pendingSave||saving||commentBusy||commentDrafts.size||(editorDraft&&JSON.stringify(editorDraft)!==editorBaseline)){event.preventDefault();event.returnValue='';}});

document.addEventListener('keydown',event=>{
 const overlay=['aiOverlay','loginOverlay','editorOverlay','readerOverlay','taskOverlay'].map(id=>document.getElementById(id)).find(el=>!el.hidden);
 if(!overlay)return;
 if(event.key==='Escape'&&['aiOverlay','loginOverlay','editorOverlay'].includes(overlay.id)){event.preventDefault();event.stopImmediatePropagation();overlay.id==='editorOverlay'?closeEditor():hideModal(overlay.id);}
 if(event.key==='Tab'){
  const fields=[...overlay.querySelectorAll('button,input,textarea,select,a[href]')].filter(el=>!el.disabled&&el.getClientRects().length);
  if(!fields.length)return;const first=fields[0],last=fields.at(-1);
  if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
 }
},true);
