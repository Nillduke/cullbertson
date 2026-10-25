(() => {
  const type=document.querySelector('select[name="type"]'), insurance=document.querySelector('#insurance-fields'), legacy=document.querySelector('#legacy-content-fields');
  if(!type||!insurance)return;
  function toggle(){const active=type.value==='insurance';insurance.hidden=!active;legacy.hidden=active;insurance.querySelectorAll('input,textarea,select').forEach(el=>el.disabled=!active);legacy.querySelectorAll('input,textarea,select').forEach(el=>el.disabled=active);}
  type.addEventListener('change',toggle);toggle();
  document.querySelectorAll('[data-insurance-repeater]').forEach(repeater=>{
    const rows=repeater.querySelector('[data-insurance-rows]'),key=repeater.dataset.insuranceRepeater;
    function renumber(){Array.from(rows.children).forEach((row,index)=>{row.querySelector('input').name=`insurance[${key}][${index}][title]`;row.querySelector('textarea').name=`insurance[${key}][${index}][body]`;});}
    repeater.addEventListener('click',event=>{
      const row=event.target.closest('[data-insurance-row]');
      if(event.target.closest('[data-row-remove]')){window.cmsEditors?.release(row);row.remove();}
      if(event.target.closest('[data-row-up]')&&row.previousElementSibling){window.cmsEditors?.release(row);rows.insertBefore(row,row.previousElementSibling);}
      if(event.target.closest('[data-row-add]')){const item=document.createElement('div');item.className='cms-panel';item.dataset.insuranceRow='';item.innerHTML='<label>Title<input></label><label>Body HTML<textarea rows="5" data-rich-editor data-editor-auto="true"></textarea></label><button type="button" data-row-up>Move up</button> <button type="button" data-row-remove>Remove</button>';rows.append(item);}
      renumber();window.cmsEditors?.scan();
    });
  });
})();
