(()=>{'use strict';const workspace=document.querySelector('[data-conversations]');if(!workspace)return;
const stream=workspace.querySelector('[data-thread-stream]'),form=workspace.querySelector('[data-message-form]'),body=form?.querySelector('[data-message-body]'),id=form?.querySelector('[data-message-id]'),button=form?.querySelector('[data-save-message]'),help=form?.querySelector('[data-composer-help]'),editing=form?.querySelector('[data-editing-label]');
function mode(){if(!form)return;const note=form.querySelector('[name="channel"]:checked')?.value==='note';button.textContent=note?'Add internal note':(id.value?'Update reply draft':'Save reply draft');help.textContent=note?'Private note · Visible only to your admin team.':'Draft only - Customer email sending is not enabled in this inbox.';body.placeholder=note?'Add a note for your team…':'Write your reply…';if(note){id.value='';editing.hidden=true;}}
form?.querySelectorAll('[name="channel"]').forEach(r=>r.addEventListener('change',mode));
workspace.addEventListener('click',event=>{const details=event.target.closest('[data-toggle-details]');if(details){if(matchMedia('(max-width:1200px)').matches)workspace.classList.toggle('details-open');else workspace.classList.toggle('details-hidden');}
if(event.target.closest('[data-show-list]'))workspace.classList.add('show-list');
const edit=event.target.closest('[data-edit-draft]');if(edit&&form){form.querySelector('[name="channel"][value="reply"]').checked=true;id.value=edit.dataset.editDraft;body.value=edit.dataset.body;editing.hidden=false;mode();body.focus();}
if(event.target.closest('[data-cancel-edit]')){id.value='';body.value='';editing.hidden=true;mode();}});
form?.addEventListener('submit',()=>{button.disabled=true;button.textContent='Saving…';});
function updateLead(lead){
 if(!lead)return;
 workspace.querySelectorAll('[data-lead-name]').forEach(node=>node.textContent=lead.display_name);
 workspace.querySelectorAll('[data-lead-initial]').forEach(node=>node.textContent=Array.from(lead.display_name)[0]?.toLocaleUpperCase()||'');
 workspace.querySelectorAll('[data-lead-title]').forEach(node=>node.textContent=lead.form_title);
 workspace.querySelectorAll('[data-lead-contact]').forEach(node=>node.textContent=[lead.email,lead.phone,lead.completion_status==='incomplete'?'Partial '+lead.progress_percent+'%':'Completed'].filter(Boolean).join(' | '));
 workspace.querySelectorAll('[data-lead-completion]').forEach(node=>node.textContent=lead.completion_status==='incomplete'?'Partial lead':'Completed');
 const row=workspace.querySelector('.cx-conversation.selected');
 if(row){
  const set=(selector,text)=>{const node=row.querySelector(selector);if(node)node.textContent=text;};
  set('.cx-person-line strong',lead.display_name);set('.cx-avatar',Array.from(lead.display_name)[0]?.toLocaleUpperCase()||'');
  set('.cx-list-subject',lead.subject);set('.cx-reference',[lead.reference,lead.email].filter(Boolean).join(' | '));
  const badge=row.querySelector('.cx-partial');
  if(lead.completion_status==='incomplete'){
   if(badge)badge.textContent='Partial '+lead.progress_percent+'%';
   else{const node=document.createElement('span');node.className='cx-partial';node.textContent='Partial '+lead.progress_percent+'%';row.querySelector('.cx-conversation-meta')?.append(node);}
  }else badge?.remove();
 }
}
function replaceDetails(selector,html){
 const panel=workspace.querySelector(selector);if(!panel||typeof html!=='string')return;
 const states=Array.from(panel.querySelectorAll('details')).map(node=>node.open);
 panel.innerHTML=html;panel.querySelectorAll('details').forEach((node,index)=>{if(states[index]!==undefined)node.open=states[index];});
}
if(stream){stream.scrollTop=stream.scrollHeight;let version='',busy=false;
async function refresh(){if(document.hidden||busy)return;busy=true;try{
 const response=await fetch(stream.dataset.url,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store'});
 if(!response.ok)throw new Error('Refresh unavailable');const data=await response.json();
 if(data.version!==version){
  const bottom=stream.scrollHeight-stream.scrollTop-stream.clientHeight<100;
  stream.innerHTML=data.html;updateLead(data.lead);
  replaceDetails('[data-live-contact]',data.contact_html);replaceDetails('[data-live-answers]',data.answers_html);
  version=data.version;if(bottom)stream.scrollTop=stream.scrollHeight;
 }
 workspace.querySelector('[data-thread-state]').textContent='Up to date - Checks every 20 seconds';
}catch{workspace.querySelector('[data-thread-state]').textContent='Refresh paused - Reload to reconnect';}finally{busy=false;}}
refresh();setInterval(refresh,20000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});}mode();})();
