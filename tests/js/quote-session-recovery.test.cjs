const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
const path=require('node:path');

function setup(responses){
 const meta={content:'old-token'},calls=[];
 const sandbox={window:{},Date,console,document:{querySelector:()=>meta},fetch:async(url,options)=>{calls.push({url,...options});const r=responses.shift();if(!r)throw Error('Unexpected request');if(r instanceof Error)throw r;return {status:r.status,ok:r.status>=200&&r.status<300,json:async()=>r.body??{}};}};
 vm.createContext(sandbox);vm.runInContext(fs.readFileSync(path.join(__dirname,'../../public/quote-progress.js'),'utf8'),sandbox);sandbox.QuoteLeadProgress=sandbox.window.QuoteLeadProgress;
 return {Class:sandbox.QuoteLeadProgress,calls,meta};
}
test('expired sessions refresh CSRF and retry the identical quote payload once',async()=>{
 const {Class,calls,meta}=setup([{status:419},{status:200,body:{token:'fresh-token'}},{status:200,body:{ok:true}}]);
 const payload={flow_id:'same-flow',answers:{email:{email:'visitor@example.com'}}};
 const response=await Class.post('/quote/progress',payload);
 assert.equal(response.status,200);assert.equal(calls.length,3);
 assert.match(calls[1].url,/^\/quote\/submit\?refresh=/);assert.equal(calls[1].cache,'no-store');
 assert.equal(calls[0].body,calls[2].body);assert.equal(calls[0].headers['X-CSRF-TOKEN'],'old-token');
 assert.equal(calls[2].headers['X-CSRF-TOKEN'],'fresh-token');assert.equal(meta.content,'fresh-token');
 assert.equal(calls[2].credentials,'same-origin');
});
test('normal saves and validation or throttling errors are not automatically repeated',async()=>{
 for(const status of [200,422,429,503]){const {Class,calls}=setup([{status}]);assert.equal((await Class.post('/quote/submit',{})).status,status);assert.equal(calls.length,1);}
});
test('repeated session failure stops after one retry',async()=>{
 const {Class,calls}=setup([{status:419},{status:200,body:{token:'fresh-token'}},{status:419}]);
 assert.equal((await Class.post('/auto-quote/submit',{request:{email:'visitor@example.com'}})).status,419);assert.equal(calls.length,3);
});
test('failed refresh does not post again or alter the quote payload',async()=>{
 const {Class,calls}=setup([{status:419},{status:503}]);const payload={answers:{name:'Saved visitor'}};
 await assert.rejects(()=>Class.post('/quote/progress',payload),/refresh your form session/);assert.equal(calls.length,2);assert.equal(payload.answers.name,'Saved visitor');
});
test('recovered progress shows saved state and real errors leave a clear unsaved status',async()=>{
 for(const status of [200,419]){
  const {Class}=setup([{status:419},{status:200,body:{token:'fresh-token'}},{status,body:{reference:'LOCAL-ONLY'}}]);
  const classes=new Set(),lead=Object.create(Class.prototype);Object.assign(lead,{service:'home',id:'flow',copy:{},state:{textContent:'',classList:{add:c=>classes.add(c),remove:(...names)=>names.forEach(n=>classes.delete(n))}}});
  if(status===200){await lead.save({answers:{}},'email','Email',5);assert.match(lead.state.textContent,/Progress saved/);assert.ok(classes.has('saved'));}
  else{await assert.rejects(()=>lead.save({answers:{}},'email','Email',5),/allow cookies/);assert.equal(lead.state.textContent,'Progress not saved yet');assert.ok(classes.has('failed'));}
 }
});
