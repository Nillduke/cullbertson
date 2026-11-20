(() => {
 'use strict';
 let pending,ready;
 const refresh=()=>{
  if(!pending)pending=(async()=>{
   const response=await fetch('/quote/submit?refresh='+Date.now(),{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
   let data;try{data=await response.json();}catch{}
   if(!response.ok||typeof data?.token!=='string'||!data.token)throw Error('We could not refresh your form session. Please try again.');
   const meta=document.querySelector('meta[name="csrf-token"]');if(meta)meta.content=data.token;
   document.querySelectorAll('form input[name="_token"]').forEach(input=>{input.value=data.token;});
   return data.token;
  })().finally(()=>{pending=null;});
  return pending;
 };
 const start=()=>{ready=refresh();ready.catch(()=>{});};
 const ensure=async()=>{await ready.catch(()=>refresh());return document.querySelector('meta[name="csrf-token"]').content;};
 window.CulbertsonFormSession={refresh,ensure,async post(url,payload){
  let token=await ensure();
  const send=()=>fetch(url,{method:'POST',credentials:'same-origin',keepalive:true,headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':token},body:JSON.stringify(payload)});
  let response=await send();
  if(response.status===419){token=await refresh();response=await send();}
  return response;
 }};
 start();
 window.addEventListener('pageshow',event=>{if(event.persisted)start();});
 const approved=new WeakSet(),waiting=new WeakSet();
 document.addEventListener('submit',event=>{
  const form=event.target;
  if(form.method.toLowerCase()!=='post'||!form.querySelector('input[name="_token"]')||new URL(form.action,location.href).origin!==location.origin)return;
  if(approved.has(form)){approved.delete(form);return;}
  event.preventDefault();event.stopImmediatePropagation();
  if(waiting.has(form))return;
  waiting.add(form);
  const submitter=event.submitter;
  refresh().then(token=>{
   form.querySelectorAll('input[name="_token"]').forEach(input=>{input.value=token;});
   form.querySelector('[data-session-error]')?.remove();
   approved.add(form);form.requestSubmit(submitter||undefined);approved.delete(form);
  }).catch(error=>{
   let notice=form.querySelector('[data-session-error]');
   if(!notice){notice=document.createElement('p');notice.setAttribute('data-session-error','');notice.setAttribute('role','alert');form.append(notice);}
   notice.textContent=error.message;
  }).finally(()=>waiting.delete(form));
 },true);
})();
