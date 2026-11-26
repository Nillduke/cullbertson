(()=>{
 const quiz=document.querySelector('[data-auto-quiz]');if(!quiz)return;
 const question=quiz.querySelector('[data-quiz-question]'),progress=quiz.querySelector('[data-quiz-progress]'),bar=quiz.querySelector('[data-quiz-bar]'),answers=quiz.querySelector('[data-quiz-answers]'),result=quiz.querySelector('[data-quiz-result]'),back=quiz.querySelector('[data-quiz-back]');
 const choices=[['State Farm','State Farm'],['GEICO','Geico'],['Allstate','Allstate'],['Other','Other'],['Currently not insured','__not_insured__']];
 const state={step:1,ownership:'',carrier:'',zip:'',firstName:'',lastName:'',email:'',phone:'',sms:false,saving:false};
 const flowId=globalThis.crypto?.randomUUID?.()||'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,c=>{const r=Math.random()*16|0;return(c==='x'?r:(r&3|8)).toString(16)});
 const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 function contactForm(){return `<form class="auto-lp-contact-form" data-contact-form novalidate>
  <div class="auto-lp-contact-name"><div><label for="auto-lp-first">First name</label><input id="auto-lp-first" name="first_name" autocomplete="given-name" maxlength="80" required value="${esc(state.firstName)}"></div><div><label for="auto-lp-last">Last name</label><input id="auto-lp-last" name="last_name" autocomplete="family-name" maxlength="80" required value="${esc(state.lastName)}"></div></div>
  <div><label for="auto-lp-email">Email</label><input id="auto-lp-email" name="email" type="email" autocomplete="email" maxlength="254" required value="${esc(state.email)}"></div>
  <div><label for="auto-lp-phone">Phone</label><input id="auto-lp-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="24" required value="${esc(state.phone)}"></div>
  <label class="auto-lp-consent"><input name="sms" type="checkbox" ${state.sms?'checked':''}><span>I agree that Culbertson Agency may call or text me at the number provided about my insurance request, including using automated technology. Message and data rates may apply. Reply STOP to opt out. Consent is not a condition of purchase. <a href="/privacy-policy-2/" target="_blank" rel="noopener">Privacy</a> and <a href="/sms-terms-of-service/" target="_blank" rel="noopener">Terms</a>.</span></label>
  <p class="auto-lp-submit-error" role="alert" hidden></p>
  <button class="auto-lp-submit" type="submit">Request my auto insurance review <span aria-hidden="true">✓</span></button>
 </form>`;}
 function render(){
  const final=state.step===4;progress.textContent=final?'Last step: your contact details':`Question ${state.step} of 3`;bar.style.width=final?'100%':`${state.step*33.333}%`;back.hidden=state.step===1;result.hidden=state.step<3;answers.hidden=state.step>=3;
  if(state.step===1){question.textContent='Do you own or lease your vehicle?';answers.innerHTML='<button type="button" data-ownership="own">I own my car <span aria-hidden="true">→</span></button><button type="button" data-ownership="lease">I lease my car <span aria-hidden="true">→</span></button>';}
  if(state.step===2){question.textContent='Who is your current auto insurance carrier?';answers.innerHTML=choices.map(([label,value])=>`<button type="button" data-carrier="${value}">${label}<span aria-hidden="true">→</span></button>`).join('');}
  if(state.step===3){question.textContent='What is your Florida ZIP code?';result.innerHTML='<label class="auto-lp-zip-label" for="auto-lp-zip">ZIP code</label><input id="auto-lp-zip" type="text" inputmode="numeric" autocomplete="postal-code" maxlength="5" pattern="[0-9]{5}" aria-label="ZIP code" value="'+esc(state.zip)+'"><button type="button" data-continue disabled>Continue <span aria-hidden="true">→</span></button><small role="alert" hidden>Please enter a valid 5-digit ZIP code.</small>';const input=result.querySelector('input'),submit=result.querySelector('[data-continue]'),error=result.querySelector('[role="alert"]');const valid=()=>{state.zip=input.value.replace(/\D/g,'').slice(0,5);if(state.zip!==input.value)input.value=state.zip;submit.disabled=!/^\d{5}$/.test(state.zip);error.hidden=true};input.addEventListener('input',valid);valid();}
  if(final){question.textContent='Where can we reach you?';result.innerHTML=contactForm();}
 }
 answers.addEventListener('click',event=>{const own=event.target.closest('[data-ownership]');if(own){state.ownership=own.dataset.ownership;state.step=2;render();return;}const carrier=event.target.closest('[data-carrier]');if(carrier){state.carrier=carrier.dataset.carrier;state.step=3;render();}});
 back.addEventListener('click',()=>{if(state.step>1&&!state.saving){if(state.step===4)captureContact();state.step--;render();}});
 result.addEventListener('click',event=>{if(!event.target.closest('[data-continue]')||state.step!==3)return;const input=result.querySelector('#auto-lp-zip'),error=result.querySelector('[role="alert"]');state.zip=input.value.trim();if(!/^\d{5}$/.test(state.zip)){error.hidden=false;input.focus();return;}state.step=4;render();result.querySelector('#auto-lp-first').focus();});
 function captureContact(){const form=result.querySelector('[data-contact-form]');if(!form)return;const fields=new FormData(form);state.firstName=String(fields.get('first_name')||'').trim();state.lastName=String(fields.get('last_name')||'').trim();state.email=String(fields.get('email')||'').trim();state.phone=String(fields.get('phone')||'').trim();state.sms=fields.get('sms')==='on';}
 result.addEventListener('submit',async event=>{
  const form=event.target.closest('[data-contact-form]');if(!form)return;event.preventDefault();if(state.saving)return;captureContact();
  if(!form.reportValidity())return;
  state.saving=true;const button=form.querySelector('.auto-lp-submit'),error=form.querySelector('[role="alert"]');button.disabled=true;button.textContent='Sending your request…';error.hidden=true;
  const body={flow_id:flowId,first_name:state.firstName,last_name:state.lastName,email:state.email,phone:state.phone,ownership:state.ownership,insured:state.carrier==='__not_insured__'?'No':'Yes',carrier:state.carrier==='__not_insured__'?'Currently not insured':state.carrier,zip:state.zip,sms:state.sms,landing_path:location.pathname.replace(/\/?$/,'/')};
  try{
   const send=token=>fetch('/auto-insurance/submit',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':token},body:JSON.stringify(body)});
   let token=document.querySelector('meta[name="csrf-token"]')?.content||'',response=await send(token);
   if(response.status===419){const refresh=await fetch('/auto-quote/submit',{credentials:'same-origin',cache:'no-store',headers:{'Accept':'application/json'}});if(refresh.ok){const fresh=await refresh.json();token=fresh.token||token;const meta=document.querySelector('meta[name="csrf-token"]');if(meta)meta.content=token;response=await send(token);}}
   const data=await response.json().catch(()=>({}));if(!response.ok)throw new Error(Object.values(data.errors||{}).flat()[0]||data.message||'We could not save your request. Please try again.');
   if(!data.redirect)throw new Error('Your request was saved, but we could not open the confirmation page. Please call us at (239) 210-7700.');
   location.assign(data.redirect);
  }catch(err){error.textContent=err.message||'We could not save your request. Please try again.';error.hidden=false;button.disabled=false;button.innerHTML='Request my auto insurance review <span aria-hidden="true">✓</span>';state.saving=false;}
 });
 render();
})();
