const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
function setup(responses){
 const meta={content:'cached'},inputs=[{value:'cached'},{value:'cached'}],calls=[],events={},windowEvents={};
 const document={querySelector:()=>meta,querySelectorAll:()=>inputs,addEventListener:(name,fn)=>events[name]=fn};
 const context={window:{addEventListener:(name,fn)=>windowEvents[name]=fn},document,location:{href:'https://example.com/contact',origin:'https://example.com'},URL,Date,WeakSet,fetch:async(url,options)=>{
  calls.push({url,...options});let r=responses.shift();if(typeof r==='function')r=await r();if(r instanceof Error)throw r;if(!r)throw Error('Unexpected fetch');
  return {status:r.status,ok:r.status===200,json:async()=>r.body||{}};
 }};
 vm.runInNewContext(fs.readFileSync('public/form-session.js','utf8'),context);
 return {session:context.window.CulbertsonFormSession,meta,inputs,calls,events,windowEvents};
}
test('load fetches uncached session and updates meta and every hidden token',async()=>{
 const x=setup([{status:200,body:{token:'fresh'}}]);
 assert.equal(await x.session.ensure(),'fresh');assert.equal(x.calls.length,1);
 assert.equal(x.calls[0].cache,'no-store');assert.equal(x.calls[0].credentials,'same-origin');
 assert.match(x.calls[0].url,/refresh=\d+/);assert.equal(x.meta.content,'fresh');assert.deepEqual(x.inputs.map(i=>i.value),['fresh','fresh']);
});
test('AJAX submission waits for page-load refresh',async()=>{
 let release;const x=setup([()=>new Promise(resolve=>release=resolve),{status:200}]);
 const submission=x.session.post('/quote/progress',{email:'example@example.com'});
 assert.equal(x.calls.length,1);release({status:200,body:{token:'new'}});
 await submission;assert.equal(x.calls[1].headers['X-CSRF-TOKEN'],'new');
});
test('419 refreshes once and later submissions use latest token',async()=>{
 const x=setup([{status:200,body:{token:'first'}},{status:419},{status:200,body:{token:'second'}},{status:200},{status:200}]);
 await x.session.post('/feedback',{message:'test'});await x.session.post('/quote/progress',{});
 assert.equal(x.calls.length,5);assert.equal(x.calls[1].body,x.calls[3].body);
 assert.equal(x.calls[4].headers['X-CSRF-TOKEN'],'second');assert.equal(x.inputs[0].value,'second');
});
test('concurrent refreshes share one request and restored pages refresh again',async()=>{
 const x=setup([{status:200,body:{token:'first'}},{status:200,body:{token:'restored'}}]);
 await Promise.all([x.session.ensure(),x.session.refresh(),x.session.refresh()]);assert.equal(x.calls.length,1);
 x.windowEvents.pageshow({persisted:true});await x.session.ensure();assert.equal(x.meta.content,'restored');
});
test('native form submission refreshes before replay and preserves submitter',async()=>{
 const x=setup([{status:200,body:{token:'first'}},{status:200,body:{token:'native'}}]);await x.session.ensure();
 let prevented=false,stopped=false,replayed=false;const submitter={name:'send'};
 const form={method:'post',action:'/contact-us/send',querySelector:selector=>selector.includes('_token')?x.inputs[0]:null,querySelectorAll:()=>x.inputs,requestSubmit:button=>{assert.equal(button,submitter);assert.equal(x.inputs[0].value,'native');replayed=true;}};
 x.events.submit({target:form,submitter,preventDefault:()=>prevented=true,stopImmediatePropagation:()=>stopped=true});
 assert.ok(prevented&&stopped);assert.equal(replayed,false);
 await new Promise(resolve=>setImmediate(resolve));assert.ok(replayed);
});
test('load failure can recover at submission; non-session errors are never retried',async()=>{
 const x=setup([new Error('offline'),{status:200,body:{token:'recovered'}},{status:422}]);
 assert.equal((await x.session.post('/quote/progress',{})).status,422);assert.equal(x.calls.length,3);
});
