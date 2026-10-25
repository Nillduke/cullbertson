(() => {
 'use strict';
 document.querySelectorAll('[data-sp-search]').forEach(input=>{
  input.addEventListener('input',()=>{
   const kind=input.dataset.spSearch,query=input.value.toLocaleLowerCase().trim();let count=0;
   document.querySelectorAll('[data-sp-item="'+kind+'"]').forEach(item=>{const matches=item.textContent.toLocaleLowerCase().includes(query);item.hidden=!matches;if(matches)count++;});
   const empty=document.querySelector('[data-sp-empty="'+kind+'"]');if(empty)empty.hidden=count>0;
   if(kind==='sitemap')document.querySelectorAll('.sp-map-section').forEach(section=>{section.hidden=!Array.from(section.querySelectorAll('[data-sp-item]')).some(item=>!item.hidden);});
  });
 });
 document.querySelectorAll('.sp-request-form').forEach(form=>form.addEventListener('submit',event=>{
  const files=form.querySelector('input[type="file"]')?.files;
  if(files&&(files.length>3||Array.from(files).some(file=>file.size>2097152))){event.preventDefault();form.querySelector('input[type="file"]').setCustomValidity('Choose up to 3 files, no larger than 2 MB each.');form.reportValidity();return;}
  form.querySelector('button[type="submit"]').disabled=true;
 }));
 document.querySelectorAll('.sp-request-form input[type="file"]').forEach(input=>input.addEventListener('change',()=>input.setCustomValidity('')));
})();
