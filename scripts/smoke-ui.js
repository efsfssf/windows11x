// Headless logic smoke test. It checks rendering and state paths without a browser.
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '..');
const elements = new Map();
function element(id) {
  if (!elements.has(id)) elements.set(id, {
    id, innerHTML: '', textContent: '', value: '', hidden: false,
    style: {}, classList: {add() {}, remove() {}, toggle() {}},
    addEventListener() {}, focus() {}, click() {}, dataset: {}, attributes: {},
    setAttribute(key,value) {this.attributes[key]=value;}, getAttribute(key) {return this.attributes[key]??null;},
  });
  return elements.get(id);
}
const sandbox = {
  document: {
    body: {style: {},classList:{toggle() {}}},
    getElementById: element,
    querySelectorAll() { return []; },
    querySelector() { return element('dummy'); },
    addEventListener() {},
    createElement() {return {click() {}, remove() {}, select() {}, value: ''};},
  },
  location: {hash: '',href:'https://dandomi.tech/'},
  localStorage: {getItem() {return null;}, setItem() {}},
  sessionStorage: {getItem() {return null;}, setItem() {}, removeItem() {}},
  setTimeout() {return 1;}, clearTimeout() {}, setInterval() {return 1;},
  confirm() {return true;},
  scrollTo() {}, addEventListener() {},
  structuredClone,
  fetch() {return Promise.reject(new Error("Test offline"));},
  Blob,
  URL: class extends URL {static createObjectURL(blob) {sandbox.exportedBlob = blob; return 'blob:smoke';} static revokeObjectURL() {}},
  navigator: {clipboard: {writeText(text) {sandbox.copiedText = text; return Promise.resolve();}}},
};
sandbox.window = sandbox;
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync(path.join(root, 'research-data.js'), 'utf8'), sandbox);
vm.runInContext(fs.readFileSync(path.join(root, 'content-defaults.js'), 'utf8'), sandbox);
vm.runInContext(fs.readFileSync(path.join(root, 'editor.js'), 'utf8'), sandbox);
vm.runInContext(fs.readFileSync(path.join(root, 'comments.js'), 'utf8'), sandbox);
vm.runInContext(fs.readFileSync(path.join(root, 'artifacts.js'), 'utf8'), sandbox);
vm.runInContext(fs.readFileSync(path.join(root, 'app.js'), 'utf8'), sandbox);
vm.runInContext(fs.readFileSync(path.join(root, 'ai.js'), 'utf8'), sandbox);
vm.runInContext(fs.readFileSync(path.join(root, 'backups.js'), 'utf8'), sandbox);
function check(ok, message) { if (!ok) throw new Error(message); }
const views = {
  overview: 'Настоящая Core OS', plan: 'Большой маршрут проекта',
  architecture: 'Как устроена система', timeline: 'Хронология исследований',
  library: 'Исследования по датам', glossary: 'Словарь проекта', files:'Файлы проекта',
};
for (const [route, text] of Object.entries(views)) {
  sandbox.location.hash = '#' + route;
  vm.runInContext('render()', sandbox);
  check(element('view').innerHTML.includes(text), route + ' did not render');
}
check(vm.runInContext("editorField('partial',['gates',0,'status'],'Статус')",sandbox).includes('value="partial"'),'Status selector loses stored enum');
check(sandbox.WCOS_RESEARCH.length === 86, 'Research document count changed');
check(sandbox.WCOS_RESEARCH.filter(x => x.group === 'JSON_Results_From_AI').length === 28, 'AI archive count changed');
const first = sandbox.WCOS_RESEARCH[0];
check(sandbox.WCOS_RESEARCH.every(x => fs.existsSync(path.join(root, x.path))), 'A copied source document is missing');
const safe = vm.runInContext("renderMarkdown('# Test\\n<script>alert(1)</script>')", sandbox);
check(safe.includes('&lt;script&gt;') && !safe.includes('<script>'), 'Markdown escaping failed');
const before = vm.runInContext('counts()', sandbox);
vm.runInContext("toggleTask('g2-dual')", sandbox);
check(vm.runInContext('counts().done', sandbox)===before.done,'Guest changed the plan');
check(vm.runInContext('renderPlan()',sandbox).includes('disabled'),'Guest checkboxes are enabled');
check(before.total >= 35 && before.done > 0 && before.done < before.total, 'Initial plan state invalid');
sandbox.location.hash = '#library';
vm.runInContext('render()', sandbox);
check(element('libraryGrid').innerHTML.includes('study-card'), 'Library cards did not render');
vm.runInContext(`openReader(${JSON.stringify(first.id)})`, sandbox);
check(element('readerContent').innerHTML.length > 100, 'Research reader is empty');
sandbox.location.hash = '#plan';
vm.runInContext('render()', sandbox);
vm.runInContext("auth.editor=true;serverReady=true;toggleTask('g2-dual')", sandbox);
const after = vm.runInContext('counts()', sandbox);
check(after.done === before.done - 1, 'Unchecking a completed plan step failed');
vm.runInContext("toggleTask('g2-dual')", sandbox);
check(vm.runInContext('counts().done', sandbox) === before.done, 'Rechecking a plan step failed');
vm.runInContext('exportState()', sandbox);
Promise.all([sandbox.exportedBlob.text(), vm.runInContext('copyForAI()', sandbox)]).then(async ([json]) => {
  const exported = JSON.parse(json);
  check(exported.tasks.length === before.total, 'Export is missing task descriptions');
  check(exported.latestArtifact.sha256.length === 64, 'Export is missing artifact identity');
  check(sandbox.copiedText.includes('Галочки — мой журнал'), 'AI summary was not copied');
  vm.runInContext("state.done['g2-dual'] = false", sandbox);
  sandbox.testFile = {text() {return Promise.resolve(json);}};
  vm.runInContext("pendingSave=false;saving=false;api=async()=>({revision:1})",sandbox);
  await vm.runInContext('importState(testFile)', sandbox);
  check(vm.runInContext("isDone(findTask('g2-dual').task)", sandbox), 'Import did not restore plan state');
  vm.runInContext("state=normalizePlan({version:1,notes:{'g2-dual':'Old note'},done:[],custom:[]});state=normalizePlan(state);",sandbox);
  check(vm.runInContext("taskComments('g2-dual').length===1 && taskComments('g2-dual')[0].researchDate===null",sandbox),'Legacy note duplicated or given a fabricated date');
  vm.runInContext("state=normalizePlan({version:1,notes:[],done:[],comments:[],custom:[]});currentTaskId='g2-dual';renderTaskComments();",sandbox);
  element('commentText').value='<script>unsafe</script> Research result';element('commentDate').value='2026-11-08';
  await vm.runInContext("submitTaskComment({preventDefault(){}})",sandbox);
  check(vm.runInContext("taskComments('g2-dual')[0].researchDate==='2026-11-08'",sandbox),'Research date was replaced by posting date');
  check(element('commentList').innerHTML.includes('&lt;script&gt;')&&!element('commentList').innerHTML.includes('<script>'),'Comment HTML escaping failed');
  check(vm.runInContext("JSON.stringify(state).includes('Research result')",sandbox),'Empty PHP array caused comment data loss');
  element('commentForm').dataset.editId=vm.runInContext("taskComments('g2-dual')[0].id",sandbox);
  element('commentText').value='Updated research';element('commentDate').value='2026-11-09';
  await vm.runInContext("submitTaskComment({preventDefault(){}})",sandbox);
  check(vm.runInContext("taskComments('g2-dual').length===1 && taskComments('g2-dual')[0].text==='Updated research'",sandbox),'Editing duplicated the comment');
  await vm.runInContext('copyForAI()',sandbox);
  check(sandbox.copiedText.includes('09.11.2026: Updated research'),'AI summary omitted dated comments');
  vm.runInContext('exportState()',sandbox);
  const withComments=JSON.parse(await sandbox.exportedBlob.text());
  check(withComments.state.comments['g2-dual'][0].researchDate==='2026-11-09','Export omitted comment dates');
  element('commentForm').dataset.editId='';element('commentText').value='Keep failed draft';element('commentDate').value='2026-11-10';
  vm.runInContext("api=async()=>{throw new Error('Network failure')}",sandbox);
  await vm.runInContext("submitTaskComment({preventDefault(){}})",sandbox);
  check(vm.runInContext("taskComments('g2-dual').length===1 && commentDrafts.get('g2-dual').text==='Keep failed draft'",sandbox),'Failed save lost the draft or changed the discussion');
  vm.runInContext("api=async()=>({revision:1});auth.editor=false;renderTaskComments()",sandbox);
  check(element('commentForm').hidden&&!element('commentList').innerHTML.includes('data-edit-comment'),'Guest can edit comments');
  await vm.runInContext("submitTaskComment({preventDefault(){}})",sandbox);
  check(vm.runInContext("taskComments('g2-dual').length===1",sandbox),'Guest comment submission accepted');
  vm.runInContext("auth.editor=false;updateChrome()",sandbox);
  check(element('aiButton').hidden,'Guest sees AI connection button');
  element('aiOverlay').hidden=true;
  await vm.runInContext('openAiPanel()',sandbox);
  check(element('aiOverlay').hidden,'Guest can open AI connection panel');
  vm.runInContext("auth.editor=true;updateChrome()",sandbox);
  check(!element('aiButton').hidden,'Owner cannot see AI connection button');
  await vm.runInContext('openAiPanel()',sandbox);
  check(!element('aiOverlay').hidden,'Owner cannot open AI connection panel');
  vm.runInContext("auth.editor=false;updateChrome()",sandbox);
  check(element('aiOverlay').hidden&&element('aiButton').hidden,'Logout left AI connection panel open');
  vm.runInContext("auth.editor=true",sandbox);
  vm.runInContext("content.texts['overview.1']='<img src=x onerror=alert(1)>';content.gates.push({id:'new',title:'New',subtitle:'',status:'wait',statusText:'',context:'',tasks:[]});",sandbox);
  check(vm.runInContext('renderOverview()',sandbox).includes('&lt;img'),'Editable prose allows raw HTML');
  check(vm.runInContext('renderPlan()',sandbox).includes('gate-new'),'A new stage was not rendered');
  vm.runInContext("state.custom=[{id:'custom-test',gate:'G0',title:'Editable custom task',description:'',proof:'',done:false,custom:true}];openEditor();",sandbox);
  check(vm.runInContext("editorDraft.gates[0].tasks.some(t=>t.id==='custom-test')",sandbox),'Custom task is missing from editor');
  vm.runInContext("editorDraft=null;commentDrafts.clear();pendingSave=false;saving=false;commentBusy=false;saveBlocked=false;serverRevision=4;",sandbox);
  const liveContent=structuredClone(sandbox.WCOS_DEFAULTS);liveContent.research=sandbox.WCOS_RESEARCH;liveContent.site.name='Обновлено ИИ';
  sandbox.liveResponse={revision:5,content:liveContent,state:{version:1,done:{},notes:{},comments:{},custom:[]}};
  vm.runInContext("api=async(action)=>action==='version'?{revision:5}:liveResponse",sandbox);
  await vm.runInContext('refreshAtlasIfChanged()',sandbox);
  check(vm.runInContext("serverRevision===5&&content.site.name==='Обновлено ИИ'",sandbox),'AI update did not refresh the open page');
  vm.runInContext("pendingSave=true;api=async()=>({revision:6})",sandbox);
  await vm.runInContext('refreshAtlasIfChanged()',sandbox);
  check(vm.runInContext('saveBlocked&&pendingSave&&serverRevision===5',sandbox),'Live refresh lost an unsaved draft');
  check(!element('reloadData').hidden,'Conflict did not offer loading current data');
  sandbox.testBackupRows=[{id:'b-1794680000-1-aaaaaaaaaaaaaaaa',revision:1,createdAt:'2026-11-14T12:00:00Z',expiresAt:'2026-11-21T12:00:00Z',beforeAction:{action:'delete_comment',actor:'<script>unsafe</script>',reason:'<img src=x>'}}];
  const backupHtml=vm.runInContext('renderBackupRows(testBackupRows)',sandbox);
  check(backupHtml.includes('&lt;script&gt;')&&!backupHtml.includes('<script>'),'Backup history allows HTML injection');
  vm.runInContext("pendingSave=false;saving=false;saveBlocked=false;api=async()=>({backups:testBackupRows,total:1,nextOffset:null});",sandbox);
  await vm.runInContext('loadServerBackups()',sandbox);
  check(element('backupsList').innerHTML.includes('Восстановить весь сайт')&&element('moreBackups').hidden,'Backup list controls missing');
  vm.runInContext("pendingSave=true",sandbox);
  await vm.runInContext("restoreServerSnapshot('b-1794680000-1-aaaaaaaaaaaaaaaa',1)",sandbox);
  check(vm.runInContext('pendingSave&&!backupsBusy',sandbox),'Restore bypassed unsaved draft guard');
  vm.runInContext('setMobileMenu(true)',sandbox);
  check(!element('mobileMenuBackdrop').hidden&&element('menuButton').getAttribute('aria-expanded')==='true','Mobile menu did not open');
  vm.runInContext('setMobileMenu(false)',sandbox);
  check(element('mobileMenuBackdrop').hidden&&element('menuButton').getAttribute('aria-expanded')==='false','Mobile menu did not close');
  check(vm.runInContext("researchFolderFor({path:'library/ai/nested/file.md'})==='library/ai/nested'&&researchFolderFor({path:'new.md'})==='library'",sandbox),'Research folder defaults');
  sandbox.folderFiles=[{id:'server-new',path:'library/ai/new.md',content:'# New',format:'md',title:'New',date:'2026-11-14'},{id:'server-other',path:first.path,content:'Updated from server',format:first.format,title:'File title',date:'2026-11-14'}];
  vm.runInContext("saveBlocked=false;pendingSave=false;editorSection='research';openEditor();researchFolderDraft='library/ai';api=async(action)=>{if(action!=='scan-research&folder=library%2Fai')throw new Error('Wrong folder request');return {files:folderFiles,count:2,folder:'library/ai',warnings:[]}}",sandbox);
  const originalTitle=vm.runInContext('editorDraft.research[0].title',sandbox);
  await vm.runInContext('loadResearchFolder()',sandbox);
  check(vm.runInContext('editorDraft.research.length===87&&content.research.length===86',sandbox),'Folder loading did not remain a draft');
  check(vm.runInContext('editorDraft.research[0].title',sandbox)===originalTitle&&vm.runInContext("editorDraft.research[0].content==='Updated from server'",sandbox),'Folder loading lost owner metadata or stale text');
  await vm.runInContext('loadResearchFolder()',sandbox);
  check(vm.runInContext('editorDraft.research.length===87',sandbox),'Folder reloading duplicated documents');
  const folderDraft=vm.runInContext('JSON.stringify(editorDraft)',sandbox);
  vm.runInContext("api=async()=>{throw new Error('Folder unavailable')}",sandbox);
  await vm.runInContext('loadResearchFolder()',sandbox);
  check(vm.runInContext('JSON.stringify(editorDraft)',sandbox)===folderDraft&&element('editorResearchStatus').textContent==='Folder unavailable','Failed folder request lost the draft');
  sandbox.artifactFixture={id:'file-test',familyId:'file-test',filename:'build.dll',title:'<script>Unsafe title</script>',description:'A build',version:'exp01',status:'offline_pass',issues:'Boot not tested',testedOn:'VM fixture',verification:'Static only',sha256:'b'.repeat(64),size:1024,taskIds:['g2-dual'],createdAt:'2026-11-15T00:00:00Z',updatedAt:'2026-11-15T00:00:00Z'};
  vm.runInContext("content.artifacts=[artifactFixture];auth.editor=false;artifactQuery='';artifactStatus='';artifactTask='';",sandbox);
  const guestFiles=vm.runInContext('renderArtifacts()',sandbox);
  check(guestFiles.includes('&lt;script&gt;')&&!guestFiles.includes('<script>')&&!guestFiles.includes('data-new-artifact'),'Guest file view permits HTML or upload');
  vm.runInContext("auth.editor=true",sandbox);
  check(vm.runInContext('renderArtifacts()',sandbox).includes('data-new-artifact'),'Owner file upload missing');
  vm.runInContext("artifactQuery='Boot';artifactStatus='offline_pass';artifactTask='g2-dual'",sandbox);
  check(vm.runInContext('artifactFilterRows().length===1',sandbox),'File search/status/task filter');
  vm.runInContext("artifactStatus='runtime_pass'",sandbox);
  check(vm.runInContext('artifactFilterRows().length===0',sandbox),'File status filter ignored');
  vm.runInContext("artifactStatus='';openArtifact('file-test');renderTaskArtifacts('g2-dual')",sandbox);
  check(element('artifactBody').innerHTML.includes('Офлайн-проверка пройдена')&&!element('artifactBody').innerHTML.includes('<script>'),'File details/evidence escaping');
  check(element('taskArtifacts').innerHTML.includes('file-test'),'Task view omitted related file');
  vm.runInContext("TERMS=[];ARCH.nt=[];DAYS=[];GATES=[];RESEARCH=[];renderArchitecture();renderTimeline();renderGlossary();renderPlan();renderLibrary();",sandbox);
  console.log('PASS: 7 views, artifact filters/task links/safe downloads, 86 sources (28 AI archive), reader, safe Markdown, plan toggle, export/import and AI summary');
}).catch(error => {console.error(error); process.exitCode = 1;});
