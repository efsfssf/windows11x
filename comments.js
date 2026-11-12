/* Task discussion: research dates are explicit, separate from posting time. */
const commentDrafts = new Map();
let commentBusy = false;
function normalizePlan(value){
 const next=structuredClone(value);
 next.comments=Object.fromEntries(Object.entries(next.comments||{}));
 next.done=Object.fromEntries(Object.entries(next.done||{}));
 // Old notes have no reliable research date. Preserve that uncertainty.
 for(const [taskId,text] of Object.entries(next.notes||{})){
  if(typeof text!=='string'||!text.trim())continue;
  const list=next.comments[taskId]||=[];
  const id='legacy-'+taskId;
  if(!list.some(item=>item.id===id))list.push({id,text,researchDate:null,createdAt:null});
 }
 next.notes={};
 return next;
}
function taskComments(id){return state.comments?.[id]||[];}
function todayDate(){const now=new Date();return [now.getFullYear(),String(now.getMonth()+1).padStart(2,'0'),String(now.getDate()).padStart(2,'0')].join('-');}
function rememberCommentDraft(){
 if(!currentTaskId)return;
 const text=document.getElementById('commentText').value;
 const researchDate=document.getElementById('commentDate').value;
 const editId=document.getElementById('commentForm').dataset.editId||null;
 if(text||editId)commentDrafts.set(currentTaskId,{text,researchDate,editId});else commentDrafts.delete(currentTaskId);
}
function renderTaskComments(){
 const list=taskComments(currentTaskId);
 document.getElementById('commentCount').textContent=String(list.length);
 document.getElementById('commentList').innerHTML=list.length?[...list].reverse().map(item=>`<article class="task-comment"><header><strong>${item.researchDate?'Исследование · '+esc(formatDate(item.researchDate)):'Дата исследования не указана'}</strong>${item.createdAt?`<small>Добавлено ${esc(new Date(item.createdAt).toLocaleString('ru-RU'))}</small>`:'<small>Перенесено из прежней заметки</small>'}</header><p>${esc(item.text)}</p>${auth.editor?`<div class="comment-actions"><button type="button" data-edit-comment="${esc(item.id)}">Изменить</button><button type="button" data-delete-comment="${esc(item.id)}">Удалить</button></div>`:''}</article>`).join(''):'<p class="comments-empty">Комментариев пока нет.</p>';
 document.getElementById('commentForm').hidden=!auth.editor;
 const draft=commentDrafts.get(currentTaskId);
 document.getElementById('commentText').value=draft?.text||'';
 document.getElementById('commentDate').value=draft?.researchDate??todayDate();
 document.getElementById('commentForm').dataset.editId=draft?.editId||'';
 document.getElementById('commentSubmit').textContent=draft?.editId?'Сохранить комментарий':'Добавить комментарий';
 document.getElementById('cancelCommentEdit').hidden=!draft?.editId;
}
async function persistComments(next){
 try{await replacePlan(next);}catch(error){
  if(error.status===409){saveBlocked=true;document.getElementById('reloadData').hidden=false;}
  if(error.status===401){auth.editor=false;updateChrome();}
  throw error;
 }
}
async function submitTaskComment(event){
 event.preventDefault();if(!canEdit()||!currentTaskId||commentBusy)return;
 const taskId=currentTaskId,form=document.getElementById('commentForm');
 const text=document.getElementById('commentText').value.trim(),researchDate=document.getElementById('commentDate').value;
 if(!text||!/^\d{4}-\d{2}-\d{2}$/.test(researchDate)){toast('Укажите текст и дату исследования');return;}
 const editId=form.dataset.editId;
 rememberCommentDraft();commentBusy=true;document.getElementById('commentSubmit').disabled=true;document.getElementById('commentText').disabled=true;document.getElementById('commentDate').disabled=true;
 try{
  // Flush earlier plan edits before taking the snapshot for this operation.
  if(pendingSave||saving){await flushSaves();if(pendingSave||saving)throw new Error('Дождитесь сохранения плана и повторите');}
  const next=normalizePlan(state),list=next.comments[taskId]||=[];
  if(editId){const item=list.find(x=>x.id===editId);if(!item)throw new Error('Комментарий не найден');item.text=text;item.researchDate=researchDate;}
  else list.push({id:'comment-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2),text,researchDate,createdAt:new Date().toISOString()});
  await persistComments(next);commentDrafts.delete(taskId);
  if(currentTaskId===taskId)renderTaskComments();if(route==='plan')document.getElementById('view').innerHTML=renderPlan();toast('Комментарий сохранён на сервере');
 }catch(error){toast(error.message);}finally{commentBusy=false;document.getElementById('commentSubmit').disabled=false;document.getElementById('commentText').disabled=false;document.getElementById('commentDate').disabled=false;}
}
document.getElementById('commentForm').addEventListener('submit',submitTaskComment);
document.addEventListener('input',event=>{if(event.target.matches('#commentText,#commentDate'))rememberCommentDraft();});
document.addEventListener('change',event=>{if(event.target.id==='commentDate')rememberCommentDraft();});
document.addEventListener('click',async event=>{
 const edit=event.target.closest('[data-edit-comment]'),remove=event.target.closest('[data-delete-comment]');
 if(commentBusy)return;
 if(event.target.closest('#cancelCommentEdit')){commentDrafts.delete(currentTaskId);renderTaskComments();return;}
 if((!edit&&!remove)||!canEdit()||!currentTaskId||commentBusy)return;
 const taskId=currentTaskId,id=(edit||remove).getAttribute(edit?'data-edit-comment':'data-delete-comment');
 const item=taskComments(taskId).find(x=>x.id===id);if(!item)return;
 if(edit){if(commentDrafts.has(taskId)&&!confirm('Заменить несохранённый черновик выбранным комментарием?'))return;commentDrafts.set(taskId,{text:item.text,researchDate:item.researchDate||'',editId:id});renderTaskComments();document.getElementById('commentText').focus();return;}
 if(!confirm('Удалить этот комментарий?'))return;
 commentBusy=true;
 try{
  if(pendingSave||saving){await flushSaves();if(pendingSave||saving)throw new Error('Дождитесь сохранения плана и повторите');}
  const next=normalizePlan(state);next.comments[taskId]=next.comments[taskId].filter(x=>x.id!==id);await persistComments(next);
  if(commentDrafts.get(taskId)?.editId===id)commentDrafts.delete(taskId);
  if(currentTaskId===taskId)renderTaskComments();if(route==='plan')document.getElementById('view').innerHTML=renderPlan();toast('Комментарий удалён');
 }catch(error){toast(error.message);}finally{commentBusy=false;}
});
