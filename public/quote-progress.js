(() => {
 'use strict';
 const uuid=()=>typeof crypto.randomUUID==='function'?crypto.randomUUID():'10000000-1000-4000-8000-100000000000'.replace(/[018]/g,c=>(c^crypto.getRandomValues(new Uint8Array(1))[0]&15>>c/4).toString(16));
 const clean=value=>value===null?'':Array.isArray(value)?value.map(clean):typeof value==='object'?Object.fromEntries(Object.entries(value).map(([k,v])=>[k,clean(v)])):value;
 window.QuoteLeadProgress=class {
  constructor(service,copy={}){
   this.service=service;this.copy=copy;this.key='culbertson-quote-flow:'+service;this.id=null;
   try{this.id=sessionStorage.getItem(this.key);}catch{}
   this.hasDraft=/^[a-f0-9-]{36}$/i.test(this.id||'');if(!this.hasDraft)this.reset();
   this.state=document.querySelector('.quote-save-state')||document.createElement('p');this.state.className='quote-save-state';this.state.setAttribute('role','status');this.state.setAttribute('aria-live','polite');if(!this.state.isConnected)document.querySelector('.wizard-actions').after(this.state);
  }
  reset(){this.id=uuid();try{sessionStorage.setItem(this.key,this.id);}catch{}}
  clear(){try{sessionStorage.removeItem(this.key);}catch{}}
  async resume(){
   if(!this.hasDraft)return null;
   try{const r=await fetch('/quote/progress?'+new URLSearchParams({service:this.service,flow_id:this.id}),{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});if(!r.ok)return null;const d=(await r.json()).draft;if(d?.completion_status==='completed'){this.reset();return null;}return d?{...d,payload:clean(d.payload)}:null;}catch{return null;}
  }
  milestones(stages){const ol=document.querySelector('.quote-milestones ol');ol.replaceChildren(...stages.map((stage,index)=>{const li=document.createElement('li');li.dataset.stage=stage;const number=document.createElement('span');number.textContent=index+1;li.append(number,document.createTextNode(this.copy.milestones?.[stage]||stage));return li;}));ol.style.setProperty('--quote-stage-count',stages.length);}
  notice(){const p=document.createElement('p');p.className='quote-progress-notice';p.append(document.createTextNode(this.copy.notice||'We save your contact details and progress when you select Continue. '));const a=document.createElement('a');a.href='/privacy-policy-2/';a.target='_blank';a.rel='noopener';a.textContent='Privacy policy';p.append(' ',a);return p.outerHTML;}
  async save(snapshot,step,label,percent){
   this.state.textContent=this.copy.saving||'Saving your progress...';this.state.classList.remove('saved','failed');
   const r=await fetch('/quote/progress',{method:'POST',credentials:'same-origin',keepalive:true,headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({flow_id:this.id,service:this.service,step,step_label:label,percent:Math.min(99,Math.max(0,Math.round(percent))),...snapshot})});
   let result;try{result=await r.json();}catch{result={};}
   if(!r.ok){this.state.textContent=this.copy.error||'We could not save your progress. Please try again.';this.state.classList.add('failed');throw Error(result.error||Object.values(result.errors||{}).flat()[0]||this.state.textContent);}
   this.state.textContent=(this.copy.saved||'Progress saved')+' | '+result.reference;this.state.classList.add('saved');return result;
  }
 };
})();
