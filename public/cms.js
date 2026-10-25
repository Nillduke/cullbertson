(() => {
 'use strict';
 const token=document.querySelector('meta[name="csrf-token"]')?.content;
 const menu=document.querySelector('.admin-menu-toggle'),scrim=document.querySelector('.admin-nav-scrim');
 const closeMenu=()=>{document.body.classList.remove('admin-nav-open');menu?.setAttribute('aria-expanded','false');if(scrim)scrim.hidden=true;};
 menu?.addEventListener('click',()=>{const open=!document.body.classList.contains('admin-nav-open');document.body.classList.toggle('admin-nav-open',open);menu.setAttribute('aria-expanded',String(open));scrim.hidden=!open;});
 scrim?.addEventListener('click',closeMenu);document.addEventListener('keydown',e=>{if(e.key==='Escape')closeMenu();});
 document.querySelector('[data-password-toggle]')?.addEventListener('click',e=>{const input=document.getElementById('admin-password'),show=input.type==='password';input.type=show?'text':'password';e.currentTarget.textContent=show?'Hide':'Show';e.currentTarget.setAttribute('aria-pressed',String(show));});
 let picker,selectedCallback,pickerPage=1,pickerQuery='',pickerRequest=0;
 const element=(tag,cls,text)=>{const el=document.createElement(tag);if(cls)el.className=cls;if(text!==undefined)el.textContent=text;return el;};
 async function upload(file,progress){
  const data=new FormData();data.append('image',file,file.name||'image.png');
  const response=await fetch('/admin/media',{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':token},body:data});
  const result=await response.json();
  if(!response.ok)throw new Error(Object.values(result.errors||{}).flat().join(' ')||result.message||'Image upload failed.');
  progress?.(100);return result.location;
 }
 function createPicker(){
  picker=element('dialog','cms-media-dialog');picker.setAttribute('aria-label','Choose an image from the media library');
  const header=element('header');header.append(element('h2',null,'Media library'));const close=element('button',null,'Close');close.type='button';close.onclick=()=>picker.close();header.append(close);
  const search=element('form','cms-picker-search'),input=element('input');input.placeholder='Search images by name or description';input.setAttribute('aria-label','Search media');const searchButton=element('button',null,'Search');searchButton.type='submit';search.append(input,searchButton);search.onsubmit=e=>{e.preventDefault();pickerQuery=input.value;loadPicker(1);};
  const status=element('p','cms-picker-status');status.setAttribute('role','status');
  const grid=element('div','cms-picker-grid');
  const footer=element('div','cms-picker-footer');const prev=element('button',null,'Previous');prev.type='button';prev.dataset.pickerPrev='';prev.onclick=()=>loadPicker(pickerPage-1);const next=element('button',null,'Next');next.type='button';next.dataset.pickerNext='';next.onclick=()=>loadPicker(pickerPage+1);
  const label=element('label',null,'Upload image');const file=element('input');file.type='file';file.accept='image/jpeg,image/png,image/webp';file.hidden=true;label.append(file);file.onchange=async()=>{if(!file.files[0])return;status.textContent='Uploading image...';try{const path=await upload(file.files[0]);selectedCallback(path,{alt:''});picker.close();}catch(error){status.textContent=error.message;}finally{file.value='';}};
  footer.append(prev,label,next);picker.append(header,search,status,grid,footer);document.body.append(picker);
 }
 async function loadPicker(page){
  const sequence=++pickerRequest;const status=picker.querySelector('.cms-picker-status'),grid=picker.querySelector('.cms-picker-grid');status.textContent='Loading images...';
  try{
   const response=await fetch(`/admin/media/picker?page=${page}&q=${encodeURIComponent(pickerQuery)}`,{headers:{Accept:'application/json'}});if(!response.ok)throw new Error('Could not load the media library. Please sign in again if your session expired.');const data=await response.json();if(sequence!==pickerRequest)return;
   pickerPage=data.current_page;grid.replaceChildren();status.textContent=data.total?`${data.total.toLocaleString()} images - Page ${data.current_page} of ${data.last_page}`:'No images found. Try a different search or upload an image.';
   data.data.forEach(image=>{const button=element('button');button.type='button';button.setAttribute('aria-label',`Choose ${image.name}`);const img=element('img');img.src=image.path;img.alt=image.alt||image.name;img.loading='lazy';button.append(img,element('span',null,image.name));button.onclick=()=>{selectedCallback(image.path,{alt:image.alt||''});picker.close();};grid.append(button);});
   picker.querySelector('[data-picker-prev]').disabled=!data.prev_page_url;picker.querySelector('[data-picker-next]').disabled=!data.next_page_url;
  }catch(error){status.textContent=error.message;}
 }
 function browse(callback){if(!picker)createPicker();selectedCallback=callback;picker.showModal();loadPicker(1);}
 const richFields=new Map();let counter=0,changed=false,submitting=false;
 const setDirty=()=>{changed=true;document.querySelectorAll('.cms-editor-state').forEach(el=>el.textContent='Unsaved changes');};
 function visible(field){return !field.disabled && !field.closest('[hidden]') && !Array.from(field.closest('form')?.querySelectorAll('details')||[]).some(d=>d.contains(field)&&!d.open);}
 function release(root){root.querySelectorAll('textarea[data-rich-editor]').forEach(field=>{const ed=window.tinymce?.get(field.id);if(ed){if(ed.isDirty())ed.save();ed.remove();}field.hidden=false;field.dataset.loading='';updateToolbar(field,false);});}
 function updateToolbar(field,active){const tools=richFields.get(field);if(!tools)return;tools.querySelector('[data-mode=visual]').setAttribute('aria-pressed',String(active));tools.querySelector('[data-mode=source]').setAttribute('aria-pressed',String(!active));}
 async function startEditor(field){
  if(field.disabled || field.dataset.loading==='true'||window.tinymce?.get(field.id))return;
  if(!window.tinymce){field.hidden=false;return;}
  field.dataset.loading='true';
  try{await tinymce.init({
   target:field,base_url:'/vendor/tinymce',suffix:'.min',license_key:'gpl',promotion:false,branding:true,add_form_submit_trigger:false,submit_patch:false,
   height:520,min_height:300,resize:true,menubar:'file edit view insert format tools table help',toolbar_mode:'sliding',
   plugins:'accordion advlist anchor autolink autosave charmap code codesample directionality emoticons fullscreen help image importcss insertdatetime link lists media nonbreaking pagebreak preview quickbars save searchreplace table visualblocks visualchars wordcount',
   toolbar:['save undo redo restoredraft | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | removeformat','alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | accordion charmap emoticons codesample | ltr rtl | searchreplace code fullscreen preview'],
   save_onsavecallback:editor=>editor.getElement().form?.requestSubmit(),
   quickbars_selection_toolbar:'bold italic | quicklink h2 h3 blockquote',quickbars_insert_toolbar:'quickimage quicktable hr',
   contextmenu:'link image table',image_advtab:true,image_caption:true,image_title:true,link_title:true,link_context_toolbar:true,
   browser_spellcheck:true,advlist_bullet_styles:'default,circle,square',advlist_number_styles:'default,lower-alpha,lower-roman,upper-alpha,upper-roman',
   image_uploadtab:true,images_file_types:'jpg,jpeg,png,webp',automatic_uploads:true,paste_data_images:true,
   images_upload_handler:(blobInfo,progress)=>upload(blobInfo.blob(),progress),
   file_picker_types:'image',file_picker_callback:(callback)=>browse(callback),
   relative_urls:false,remove_script_host:false,convert_urls:false,entity_encoding:'raw',
   content_style:'body{font-family:Arial,sans-serif;color:#193448;line-height:1.8;font-size:16px;padding:12px 22px}img{max-width:100%;height:auto}h1,h2,h3{line-height:1.25;color:#14334e}a{color:#2676d9}table{border-collapse:collapse}td,th{padding:8px}',
   autosave_ask_before_unload:false,autosave_restore_when_empty:false,autosave_retention:'60m',
   setup(editor){editor.on('init',()=>{field.dataset.loading='';updateToolbar(field,true);});editor.on('change input undo redo',setDirty);}
  });}catch(error){field.hidden=false;field.dataset.loading='';const message=element('p','cms-errors','The visual editor could not load. Your HTML source is still available.');field.before(message);}
 }
 function scan(){
  document.querySelectorAll('textarea[data-rich-editor]').forEach(field=>{
   if(!richFields.has(field)){
    if(!field.id)field.id=`cms-rich-${++counter}`;
    if(field.parentElement.tagName==='LABEL'){
     const original=field.parentElement,wrapper=element('div',original.className),caption=element('label',null,Array.from(original.childNodes).filter(n=>n.nodeType===3).map(n=>n.textContent).join(' ').trim()||'Content');caption.htmlFor=field.id;original.before(wrapper);wrapper.append(caption,field);original.remove();
    }
    const tools=element('div','cms-rich-toolbar');const visual=element('button',null,'Visual editor'),source=element('button',null,'HTML source');visual.type=source.type='button';visual.dataset.mode='visual';source.dataset.mode='source';source.setAttribute('aria-pressed','true');visual.setAttribute('aria-pressed','false');
    visual.onclick=()=>{field.dataset.sourceMode='';startEditor(field);};source.onclick=()=>{field.dataset.sourceMode='true';const ed=tinymce?.get(field.id);if(ed){if(ed.isDirty())ed.save();ed.remove();}field.hidden=false;field.dataset.loading='';updateToolbar(field,false);};
    tools.append(visual,source,element('small',null,'Self-hosted TinyMCE'));field.before(tools);richFields.set(field,tools);field.addEventListener('input',setDirty);
   }
   if(field.dataset.editorAuto==='true'&&visible(field)&&!field.dataset.sourceMode)startEditor(field);
  });
  document.querySelectorAll('input[name="featured_image"],input[name="photo"],input[name="insurance[hero]"],input[name="insurance[feature_image]"]').forEach(field=>{
   if(field.closest('.cms-image-input'))return;const wrap=element('span','cms-image-input');field.before(wrap);wrap.append(field);const button=element('button',null,'Browse media');button.type='button';button.onclick=()=>browse(path=>{field.value=path;field.dispatchEvent(new Event('input',{bubbles:true}));});wrap.append(button);
  });
 }
 document.addEventListener('toggle',e=>{if(e.target.tagName==='DETAILS'&&e.target.open)scan();},true);
 document.addEventListener('change',e=>{if(e.target.matches('select[name="type"]'))setTimeout(scan,0);});
 document.querySelectorAll('.cms-shell form').forEach(form=>{
  if(form.action.includes('/admin/login'))return;
  form.addEventListener('input',setDirty);
  form.addEventListener('submit',async event=>{
   const editors=Array.from(form.querySelectorAll('textarea[data-rich-editor]')).map(field=>window.tinymce?.get(field.id)).filter(Boolean);
   if(editors.length&&!form.dataset.editorsSynced){
    event.preventDefault();const submitter=event.submitter;const state=document.querySelector('.cms-editor-state');if(state)state.textContent='Saving content...';
    try{for(const editor of editors){const results=await editor.uploadImages();if(results.some(result=>result.status===false))throw new Error('Please finish uploading images before saving.');if(editor.isDirty())editor.save();}form.dataset.editorsSynced='true';setTimeout(()=>{form.requestSubmit(submitter||undefined);delete form.dataset.editorsSynced;},0);}catch(error){form.dataset.editorsSynced='';if(state)state.textContent=error.message;const message=element('p','cms-errors',error.message);form.prepend(message);}
   }else{submitting=true;changed=false;}
  });
 });
 window.addEventListener('beforeunload',event=>{if(changed&&!submitting){event.preventDefault();event.returnValue='';}});
 window.cmsEditors={scan,release};scan();
})();
