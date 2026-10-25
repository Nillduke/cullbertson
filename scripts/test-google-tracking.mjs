import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {webcrypto} from 'node:crypto';
const defaults=JSON.parse(fs.readFileSync(new URL('../database/import/google-tracking.json',import.meta.url),'utf8'));
const source=fs.readFileSync(new URL('../public/tracking.js',import.meta.url),'utf8');
const head=fs.readFileSync(new URL('../resources/views/partials/google-tracking-head.blade.php',import.meta.url),'utf8');
function storage(){const values=new Map();return {getItem:k=>values.get(k)||null,setItem:(k,v)=>values.set(k,v)};}
function boot({path='/auto-quote/',session=storage()}={}){
 const config={...defaults,useContainer:!path.replace(/\/+$/,'').endsWith(defaults.legacyAutoConfirmationPath.replace(/\/+$/,''))};
 const scripts=[],nodes=new Map();let context;
 const insert=script=>{if(script.src)scripts.push(script.src);};
 const document={referrer:'https://source.example/?email=private@example.com',getElementById:id=>nodes.get(id)||null,createElement:()=>({}),getElementsByTagName:()=>[{parentNode:{insertBefore:insert}}]};
 const location={origin:'https://agency.example',pathname:path,href:'https://agency.example'+path+'?email=private@example.com&phone=2025550100#secret'};
 const window={location,crypto:webcrypto};
 const scope={window,location,document,sessionStorage:session,URL,Date,Set,JSON,TextEncoder,Uint8Array,Array,Number,String,Object};
 Object.defineProperty(scope,'localStorage',{get(){throw Error('Tracking must not depend on consent storage');}});
 for(const key of ['dataLayer','gtag'])Object.defineProperty(scope,key,{configurable:true,get:()=>window[key],set:v=>{window[key]=v;}});
 context=vm.createContext(scope);
 for(const match of head.matchAll(/<script id="([^"]+)"([^>]*)>([\s\S]*?)<\/script>/g)){
  const [,id,attrs,text]=match;
  if(id==='culbertson-tracking-config')continue;
  if(id==='culbertson-gtm-snippet'&&!config.useContainer)continue;
  const src=attrs.match(/\bsrc="([^"]+)"/);
  const render=s=>s.replace(/\{\{\s*\$googleTracking\['([^']+)'\]\s*\}\}/g,(_,key)=>config[key]);
  if(src)scripts.push(render(src[1]));else if(text.trim())vm.runInContext(render(text),context);
 }
 nodes.set('culbertson-tracking-config',{textContent:JSON.stringify(config)});
 vm.runInContext(source,context);
 return {window,scripts,session,commands:()=>window.dataLayer.filter(x=>!x.event).map(x=>Array.from(x))};
}
const lead={reference:'CUL-261026-A1B2C3D4E5F6',service:'auto',completed:true,email:'private@example.com',phone:'2025550100',answers:{name:'Synthetic Person'}};
const test=boot();
assert.equal(test.scripts.length,2);
assert.ok(test.scripts.some(x=>x.endsWith('GTM-NPKN5562')));
assert.ok(test.scripts.some(x=>x.endsWith('AW-18511592688')));
assert.equal(test.commands().filter(x=>x[0]==='consent').length,0);
const configs=test.commands().filter(x=>x[0]==='config');
assert.equal(configs.filter(x=>x[1]==='G-9CTSTL96SL').length,1);
assert.equal(configs.filter(x=>x[1]==='G-F93LLMC4SC').length,0);
assert.equal(configs.filter(x=>x[1]==='AW-18511592688').length,1);
assert.equal(configs.find(x=>x[1]==='G-0H056Z22M3')[2].send_page_view,false);
assert.equal(await test.window.CulbertsonTracking.completeQuote({...lead,completed:false}),false);
assert.equal(await test.window.CulbertsonTracking.completeQuote(lead),true);
assert.equal(await test.window.CulbertsonTracking.completeQuote(lead),false);
const events=test.commands().filter(x=>x[0]==='event');assert.equal(events.length,2);
assert.equal(events[0][1],'generate_lead');assert.equal(events[1][1],'conversion');
assert.equal(events[1][2].send_to,'AW-18511592688/HbyeCIfhj54dEPD5gftE');
assert.match(events[0][2].transaction_id,/^[a-f0-9]{64}$/);
const serialized=JSON.stringify(test.window.dataLayer);
for(const secret of ['private@example.com','2025550100','Synthetic Person','A1B2C3D4E5F6','?email=','#secret'])assert.ok(!serialized.includes(secret));
const reload=boot({session:test.session});assert.equal(await reload.window.CulbertsonTracking.completeQuote(lead),false);
assert.equal(await reload.window.CulbertsonTracking.completeQuote({...lead,reference:'CUL-261026-B1B2C3D4E5F6',service:'home'}),true);
assert.equal(reload.commands().filter(x=>x[0]==='event'&&x[1]==='conversion').length,0);
const legacy=boot({path:'/culbertsonoct/thanks-for-contacting-us/thanks-auto-insurance-quote/'});
assert.equal(legacy.scripts.length,1);assert.equal(legacy.commands().filter(x=>x[0]==='event').length,0);
for(const file of ['../public/quote-assets/auto/form.js','../public/quote-assets/services/services.js'])assert.ok(fs.readFileSync(new URL(file,import.meta.url),'utf8').includes('completed:result.ok===true'));
assert.ok(!fs.readFileSync(new URL('../public/quote-progress.js',import.meta.url),'utf8').includes('CulbertsonTracking'));
console.log('PASS: supplied snippets load immediately, no consent dependency, destination routing, PII exclusion, completed-only conversions and repeat/legacy protection.');
