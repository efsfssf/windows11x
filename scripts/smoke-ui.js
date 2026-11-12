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
    addEventListener() {}, focus() {}, click() {},
  });
  return elements.get(id);
}
const sandbox = {
  document: {
    body: {style: {}},
    getElementById: element,
    querySelectorAll() { return []; },
    querySelector() { return element('dummy'); },
    addEventListener() {},
    createElement() {return {click() {}, remove() {}, select() {}, value: ''};},
  },
  location: {hash: ''},
  localStorage: {getItem() {return null;}, setItem() {}},
  sessionStorage: {getItem() {return null;}, setItem() {}, removeItem() {}},
  setTimeout() {return 1;}, clearTimeout() {},
  confirm() {return true;},
  scrollTo() {}, addEventListener() {},
  Blob,
  URL: {createObjectURL(blob) {sandbox.exportedBlob = blob; return 'blob:smoke';}, revokeObjectURL() {}},
  navigator: {clipboard: {writeText(text) {sandbox.copiedText = text; return Promise.resolve();}}},
};
sandbox.window = sandbox;
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync(path.join(root, 'research-data.js'), 'utf8'), sandbox);
vm.runInContext(fs.readFileSync(path.join(root, 'app.js'), 'utf8'), sandbox);
function check(ok, message) { if (!ok) throw new Error(message); }
const views = {
  overview: 'Настоящая Core OS', plan: 'Большой маршрут проекта',
  architecture: 'Как устроена система', timeline: 'Хронология исследований',
  library: 'Исследования по датам', glossary: 'Словарь проекта',
};
for (const [route, text] of Object.entries(views)) {
  sandbox.location.hash = '#' + route;
  vm.runInContext('render()', sandbox);
  check(element('view').innerHTML.includes(text), route + ' did not render');
}
check(sandbox.WCOS_RESEARCH.length === 86, 'Research document count changed');
check(sandbox.WCOS_RESEARCH.filter(x => x.group === 'JSON_Results_From_AI').length === 28, 'AI archive count changed');
const first = sandbox.WCOS_RESEARCH[0];
check(sandbox.WCOS_RESEARCH.every(x => fs.existsSync(path.join(root, x.path))), 'A copied source document is missing');
const safe = vm.runInContext("renderMarkdown('# Test\\n<script>alert(1)</script>')", sandbox);
check(safe.includes('&lt;script&gt;') && !safe.includes('<script>'), 'Markdown escaping failed');
const before = vm.runInContext('counts()', sandbox);
check(before.total >= 35 && before.done > 0 && before.done < before.total, 'Initial plan state invalid');
sandbox.location.hash = '#library';
vm.runInContext('render()', sandbox);
check(element('libraryGrid').innerHTML.includes('study-card'), 'Library cards did not render');
vm.runInContext(`openReader(${JSON.stringify(first.id)})`, sandbox);
check(element('readerContent').innerHTML.length > 100, 'Research reader is empty');
sandbox.location.hash = '#plan';
vm.runInContext('render()', sandbox);
vm.runInContext("toggleTask('g2-dual')", sandbox);
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
  await vm.runInContext('importState(testFile)', sandbox);
  check(vm.runInContext("isDone(findTask('g2-dual').task)", sandbox), 'Import did not restore plan state');
  console.log('PASS: 6 views, 86 sources (28 AI archive), reader, safe Markdown, plan toggle, export/import and AI summary');
}).catch(error => {console.error(error); process.exitCode = 1;});
