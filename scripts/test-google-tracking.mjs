import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {webcrypto} from 'node:crypto';

const config=JSON.parse(fs.readFileSync(new URL('../database/import/google-tracking.json',import.meta.url),'utf8'));
const source=fs.readFileSync(new URL('../public/tracking.js',import.meta.url),'utf8');
function storage(seed={}){const map=new Map(Object.entries(seed));return {getItem:key=>map.get(key)??null,setItem:(key,value)=>map.set(key,String(value))};}
function boot({local=storage(),session=storage(),path='/auto-quote/',referrer='https://source.example/?email=private@example.com'}={}){
 const nodes=new Map(),scripts=[],buttons=[],events={};
 const node=()=>({hidden:true,attrs:{},addEventListener(name,fn){this[name]=fn;},setAttribute(k,v){this.attrs[k]=v;},focus(){this.focused=true;}});
 const heading=node(),panel=node(),settings=node(),close=node();
 panel.querySelector=()=>heading;
 nodes.set('culbertson-tracking-config',{textContent:JSON.stringify(config)});nodes.set('tracking-choice',panel);nodes.set('tracking-settings',settings);
 for(const choice of ['allow','deny']){const b=node();b.dataset={trackingChoice:choice};buttons.push(b);}
 const doc={referrer,getElementById:id=>nodes.get(id)||null,querySelector:()=>close,querySelectorAll:()=>buttons,createElement:()=>({}),head:{appendChild(script){nodes.set(script.id,script);scripts.push(script);}}};
 const location={origin:'https://agency.example',pathname:path,href:'https://agency.example'+path+'?email=private@example.com&phone=2025550100#secret',reload(){events.reload=(events.reload||0)+1;}};
 const window={location,crypto:webcrypto};
 vm.runInNewContext(source,{window,document:doc,localStorage:local,sessionStorage:session,URL,Date,Set,JSON,TextEncoder,Uint8Array,Array,Number,String,Object,encodeURIComponent});
 return {window,local,session,scripts,panel,settings,events,choice:value=>buttons.find(x=>x.dataset.trackingChoice===value).click(),commands:()=>window.dataLayer.filter(x=>!x.event).map(x=>Array.from(x))};
}
const lead={reference:'CUL-261026-A1B2C3D4E5F6',service:'auto',completed:true,email:'private@example.com',phone:'2025550100',answers:{name:'Synthetic Person'}};
{
 const test=boot();assert.equal(test.scripts.length,0);assert.equal(test.panel.hidden,false);
 assert.equal(await test.window.CulbertsonTracking.completeQuote(lead),false);
 test.choice('deny');assert.equal(test.scripts.length,0);assert.equal(test.panel.hidden,true);
 assert.equal(test.commands().filter(x=>x[0]==='event').length,0);
}
{
 const test=boot();test.choice('allow');
 assert.equal(test.scripts.length,2);
 assert.ok(test.scripts.some(x=>x.src.endsWith('GTM-NPKN5562')));
 assert.ok(test.scripts.some(x=>x.src.endsWith('GT-WR4WXG6')));
 const configs=test.commands().filter(x=>x[0]==='config');
 assert.equal(configs.filter(x=>x[1]==='G-9CTSTL96SL').length,1);
 assert.equal(configs.filter(x=>x[1]==='G-F93LLMC4SC').length,0);
 assert.equal(configs.find(x=>x[1]==='G-0H056Z22M3')[2].send_page_view,false);
 assert.equal(await test.window.CulbertsonTracking.completeQuote({...lead,completed:false}),false);
 assert.equal(await test.window.CulbertsonTracking.completeQuote(lead),true);
 assert.equal(await test.window.CulbertsonTracking.completeQuote(lead),false);
 const events=test.commands().filter(x=>x[0]==='event');
 assert.equal(events.length,2);assert.equal(events[0][1],'generate_lead');assert.equal(events[1][1],'conversion');
 assert.equal(events[1][2].send_to,'AW-18511592688/HbyeCIfhj54dEPD5gftE');
 assert.match(events[0][2].transaction_id,/^[a-f0-9]{64}$/);
 const serialized=JSON.stringify(test.window.dataLayer);
 for(const secret of ['private@example.com','2025550100','Synthetic Person','A1B2C3D4E5F6','?email=','#secret'])assert.ok(!serialized.includes(secret));
 const reload=boot({local:test.local,session:test.session});
 assert.equal(await reload.window.CulbertsonTracking.completeQuote(lead),false);
 assert.equal(reload.commands().filter(x=>x[0]==='event').length,0);
 assert.equal(await reload.window.CulbertsonTracking.completeQuote({...lead,reference:'CUL-261026-B1B2C3D4E5F6',service:'home'}),true);
 assert.equal(reload.commands().filter(x=>x[0]==='event'&&x[1]==='generate_lead').length,1);
 assert.equal(reload.commands().filter(x=>x[0]==='event'&&x[1]==='conversion').length,0);
 test.choice('deny');assert.equal(test.events.reload,1);
 const rejected=boot({local:test.local});assert.equal(rejected.scripts.length,0);
}
{
 const test=boot({path:'/culbertsonoct/thanks-for-contacting-us/thanks-auto-insurance-quote/',referrer:''});
 test.choice('allow');assert.equal(test.scripts.length,1);assert.ok(!test.scripts.some(x=>x.id==='culbertson-gtm'));
 assert.equal(test.commands().filter(x=>x[0]==='event').length,0);
 assert.equal(test.commands().find(x=>x[0]==='set')[1].page_referrer,'');
}
{
 const expired=storage({culbertson_google_consent:JSON.stringify({version:1,allowed:true,expires:0})});
 const test=boot({local:expired});assert.equal(test.scripts.length,0);assert.equal(test.panel.hidden,false);
}
for(const file of ['../public/quote-assets/auto/form.js','../public/quote-assets/services/services.js']){
 const text=fs.readFileSync(new URL(file,import.meta.url),'utf8');
 assert.equal((text.match(/CulbertsonTracking\?\.completeQuote/g)||[]).length,1);
 assert.ok(text.includes('completed:result.ok===true'));
}
assert.ok(!fs.readFileSync(new URL('../public/quote-progress.js',import.meta.url),'utf8').includes('CulbertsonTracking'));
console.log('PASS: consent gating, tag alias handling, destination routing, URL/PII exclusion, completion-only events, repeat protection, withdrawal and legacy-page safeguards.');
