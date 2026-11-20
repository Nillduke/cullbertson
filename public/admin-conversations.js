(()=>{'use strict';const workspace=document.querySelector('[data-conversations]');if(!workspace)return;
const stream=workspace.querySelector('[data-thread-stream]'),form=workspace.querySelector('[data-message-form]'),body=form?.querySelector('[data-message-body]'),id=form?.querySelector('[data-message-id]'),button=form?.querySelector('[data-save-message]'),help=form?.querySelector('[data-composer-help]'),editing=form?.querySelector('[data-editing-label]'),sendButton=form?.querySelector('[data-send-message]');
function mode(){if(!form)return;const channel=form.querySelector('[name="channel"]:checked')?.value||'reply',note=channel==='note',sms=channel==='sms';button.textContent=note?'Add internal note':(id.value?'Update draft':'Save draft');help.textContent=note?'Private note - Visible only to your admin team.':(sms?form.dataset.smsHelp:form.dataset.mailHelp);sendButton.hidden=note;sendButton.disabled=(sms?form.dataset.smsReady:form.dataset.mailReady)!=='1';sendButton.textContent=sms?'Send SMS':'Send email';body.maxLength=sms?1600:10000;body.placeholder=note?'Add a note for your team...':(sms?'Write a text message (up to 1600 characters)...':'Write your reply...');if(note){id.value='';editing.hidden=true;}}
form?.querySelectorAll('[name="channel"]').forEach(r=>r.addEventListener('change',()=>{id.value='';editing.hidden=true;mode();}));
const selected=()=>Array.from(workspace.querySelectorAll('[data-select-conversation]'));
function selection(){const boxes=selected(),count=boxes.filter(box=>box.checked).length;const all=workspace.querySelector('[data-select-all]');if(all){all.checked=count===boxes.length&&count>0;all.indeterminate=count>0&&count<boxes.length;}workspace.querySelector('[data-selection-count]').textContent=count?'('+count+')':'';workspace.querySelector('.cx-bulk-bar').hidden=count===0;}
workspace.querySelector('[data-select-all]')?.addEventListener('change',event=>{selected().forEach(box=>box.checked=event.target.checked);selection();});
selected().forEach(box=>box.addEventListener('change',selection));
workspace.querySelector('[data-inbox-sort]')?.addEventListener('change',event=>event.target.form.requestSubmit());
workspace.querySelector('[data-bulk-action]')?.addEventListener('change',event=>{const owner=workspace.querySelector('[data-bulk-owner]');owner.hidden=event.target.value!=='assign';owner.required=!owner.hidden;});
function contactTab(name){workspace.querySelectorAll('[data-contact-tab]').forEach(button=>button.setAttribute('aria-selected',button.dataset.contactTab===name?'true':'false'));workspace.querySelectorAll('[data-contact-pane]').forEach(pane=>pane.hidden=pane.dataset.contactPane!==name);}
function searchFields(){const input=workspace.querySelector('[data-field-search]');if(!input)return;const query=input.value.trim().toLocaleLowerCase();const fields=workspace.querySelectorAll('[data-contact-pane="fields"] .cx-fields>div,[data-contact-pane="fields"] .sub-values>div');let matches=0;fields.forEach(field=>{field.hidden=!!query&&!field.textContent.toLocaleLowerCase().includes(query);if(!field.hidden)matches++;});workspace.querySelector('[data-field-no-results]').hidden=!query||matches>0;}
workspace.querySelector('[data-field-search]')?.addEventListener('input',searchFields);
workspace.addEventListener('click',event=>{
 const tab=event.target.closest('[data-contact-tab]');if(tab)contactTab(tab.dataset.contactTab);
 if((event.target.closest('[data-compose-email]')||event.target.closest('[data-compose-note]')||event.target.closest('[data-compose-sms]'))&&form){const channel=event.target.closest('[data-compose-note]')?'note':(event.target.closest('[data-compose-sms]')?'sms':'reply');form.querySelector('[name="channel"][value="'+channel+'"]').checked=true;id.value='';editing.hidden=true;mode();body.focus();}
 if(event.target.closest('[data-open-call]'))workspace.querySelector('[data-call-dialog]')?.showModal();
 if(event.target.closest('[data-close-call]'))workspace.querySelector('[data-call-dialog]')?.close();
const details=event.target.closest('[data-toggle-details]');if(details){if(matchMedia('(max-width:1200px)').matches)workspace.classList.toggle('details-open');else workspace.classList.toggle('details-hidden');}
if(event.target.closest('[data-show-list]'))workspace.classList.add('show-list');
const edit=event.target.closest('[data-edit-draft]');if(edit&&form){form.querySelector('[name="channel"][value="'+(edit.dataset.channel||'reply')+'"]').checked=true;id.value=edit.dataset.editDraft;body.value=edit.dataset.body;editing.hidden=false;mode();body.focus();}
if(event.target.closest('[data-cancel-edit]')){id.value='';body.value='';editing.hidden=true;mode();}});
form?.addEventListener('submit',event=>{
 const action=event.submitter?.value||'draft';form.dataset.submitting='1';
 const input=document.createElement('input');input.type='hidden';input.name='delivery_action';input.value=action;form.append(input);
 button.disabled=true;sendButton.disabled=true;
 if(action==='send')sendButton.textContent='Sending...';else button.textContent='Saving...';
});
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
  const preview=row.querySelector('.cx-preview');if(preview&&lead.preview){preview.textContent=lead.preview;preview.title=lead.preview;}
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
  if(form&&data.outgoing_mail&&form.dataset.submitting!=='1'){
   form.dataset.mailReady=data.outgoing_mail.ready?'1':'0';
   form.dataset.mailHelp=data.outgoing_mail.ready?'From '+data.outgoing_mail.from+' to '+data.outgoing_mail.to:data.outgoing_mail.reason;
   if(data.sms_state){form.dataset.smsReady=data.sms_state.ready?'1':'0';form.dataset.smsHelp=data.sms_state.ready?'SMS to '+data.sms_state.to:data.sms_state.reason;}mode();
  }
  replaceDetails('[data-live-contact]',data.contact_html);replaceDetails('[data-live-answers]',data.answers_html);searchFields();
  if(data.voice_state){workspace.querySelectorAll('[data-open-call]').forEach(button=>{button.disabled=!data.voice_state.ready;button.title=data.voice_state.reason||'Call via Twilio';});const agent=workspace.querySelector('[data-call-agent]'),destination=workspace.querySelector('[data-call-destination]');if(agent)agent.textContent=data.voice_state.agent;if(destination)destination.textContent=data.voice_state.to;}
  version=data.version;if(bottom)stream.scrollTop=stream.scrollHeight;
 }
 workspace.querySelector('[data-thread-state]').textContent='Up to date - Checks every 20 seconds';
}catch{workspace.querySelector('[data-thread-state]').textContent='Refresh paused - Reload to reconnect';}finally{busy=false;}}
refresh();setInterval(refresh,20000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});}mode();})();
