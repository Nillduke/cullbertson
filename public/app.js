'use strict';
const reducedMotion=window.matchMedia('(prefers-reduced-motion: reduce)');
const video=document.querySelector('#hero-video');const videoToggle=document.querySelector('#video-toggle');
const pauseIcon='<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M9 5v14M15 5v14"/></svg>';
const playIcon='<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m8 4 12 8-12 8V4Z"/></svg>';
if(video && videoToggle){
function updateVideoControl(){videoToggle.innerHTML=video.paused?playIcon:pauseIcon;videoToggle.setAttribute('aria-label',video.paused?'Play background video':'Pause background video');}
if(reducedMotion.matches){video.pause();video.removeAttribute('autoplay');}else{video.play().catch(updateVideoControl);}
video.addEventListener('play',updateVideoControl);video.addEventListener('pause',updateVideoControl);video.addEventListener('error',()=>{video.hidden=true;document.querySelector('.hero').style.backgroundImage='url("/assets/florida-neighborhood.webp")';document.querySelector('.hero').style.backgroundSize='cover';videoToggle.hidden=true;});
videoToggle.addEventListener('click',()=>{if(video.paused)video.play().catch(updateVideoControl);else video.pause();});
}
const menuButton=document.querySelector('.menu-toggle');
const mobileMenu=document.querySelector('#mobile-menu');
const megaTriggers=[...document.querySelectorAll('.mega-trigger')];
const insuranceButton=document.querySelector('#insurance-menu-toggle');
const insuranceMenu=document.querySelector('#insurance-menu');
function closeInsurance(){
 document.querySelectorAll('.mega-panel').forEach(panel=>panel.hidden=true);
 megaTriggers.forEach(button=>button.setAttribute('aria-expanded','false'));
 document.querySelector('.site-header').classList.remove('mega-open');
}
function closeMobile(){
 document.querySelector('.site-header').classList.remove('mobile-open');
 document.body.classList.remove('navigation-open');mobileMenu.hidden=true;
 menuButton.setAttribute('aria-expanded','false');menuButton.setAttribute('aria-label','Open navigation');
}
menuButton.addEventListener('click',()=>{
 const open=menuButton.getAttribute('aria-expanded')!=='true';closeInsurance();
 mobileMenu.hidden=!open;document.querySelector('.site-header').classList.toggle('mobile-open',open);
 document.body.classList.toggle('navigation-open',open);menuButton.setAttribute('aria-expanded',String(open));menuButton.setAttribute('aria-label',open?'Close navigation':'Open navigation');
});
mobileMenu.querySelectorAll('a').forEach(link=>link.addEventListener('click',closeMobile));
function openMega(button){
 const open=button.getAttribute('aria-expanded')!=='true';closeInsurance();
 if(open){document.querySelector('#'+button.getAttribute('aria-controls')).hidden=false;button.setAttribute('aria-expanded','true');document.querySelector('.site-header').classList.add('mega-open');}
}
megaTriggers.forEach(button=>{
 button.addEventListener('click',()=>openMega(button));
 button.addEventListener('keydown',event=>{if(event.key==='ArrowDown'){event.preventDefault();if(button.getAttribute('aria-expanded')!=='true')openMega(button);document.querySelector('#'+button.getAttribute('aria-controls')+' a').focus();}});
});
document.querySelectorAll('[data-close-mega]').forEach(button=>button.addEventListener('click',()=>{const trigger=document.querySelector('.mega-trigger[aria-expanded="true"]');closeInsurance();trigger?.focus();}));
document.addEventListener('click',event=>{if(!event.target.closest('.site-header'))closeInsurance();});
document.querySelector('.site-header').addEventListener('focusout',event=>{if(event.relatedTarget&&!event.currentTarget.contains(event.relatedTarget))closeInsurance();});
document.addEventListener('keydown',event=>{if(event.key==='Escape'){const active=document.querySelector('.mega-trigger[aria-expanded="true"]');if(active){closeInsurance();active.focus();}if(!mobileMenu.hidden){closeMobile();menuButton.focus();}}});
window.matchMedia('(min-width: 961px)').addEventListener('change',()=>{closeMobile();closeInsurance();});
const dialog=document.querySelector('#feedback-dialog');const form=document.querySelector('#feedback-form');const content=document.querySelector('#feedback-content');const success=document.querySelector('#feedback-success');const error=document.querySelector('#feedback-error');const message=document.querySelector('#feedback-message');const count=document.querySelector('#message-count');const submitButton=form.querySelector('button[type="submit"]');let opener=null;let csrfToken=null;let closing=false;
async function getToken(){const response=await fetch('/feedback',{credentials:'same-origin',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,Accept:'application/json'},cache:'no-store'});const data=await response.json();if(!response.ok||!data.token)throw new Error('Feedback is temporarily unavailable. Please try again.');csrfToken=data.token;return csrfToken;}
function openFeedback(trigger){if(dialog.open)return;opener=trigger;closeInsurance();closeMobile();content.hidden=false;success.hidden=true;error.hidden=true;dialog.setAttribute('aria-labelledby','feedback-title');dialog.setAttribute('aria-describedby','feedback-intro');dialog.showModal();document.body.classList.add('modal-open');document.querySelector('#feedback-title').focus();if(!csrfToken)getToken().catch(()=>{});}
function closeFeedback(){if(closing||!dialog.open)return;closing=true;dialog.classList.add('closing');window.setTimeout(()=>{dialog.close();dialog.classList.remove('closing');document.body.classList.remove('modal-open');closing=false;opener?.focus();},reducedMotion.matches?0:240);}
document.querySelector('#feedback-tab').addEventListener('click',e=>openFeedback(e.currentTarget));document.querySelectorAll('[data-open-feedback]').forEach(b=>b.addEventListener('click',e=>openFeedback(e.currentTarget)));document.querySelector('#feedback-close').addEventListener('click',closeFeedback);document.querySelector('#feedback-done').addEventListener('click',closeFeedback);dialog.addEventListener('cancel',e=>{e.preventDefault();closeFeedback();});dialog.addEventListener('click',e=>{if(e.target===dialog){const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)closeFeedback();}});
message.addEventListener('input',()=>{count.textContent=message.value.length+' / 2000';});
form.addEventListener('submit',async e=>{e.preventDefault();if(!form.reportValidity())return;error.hidden=true;submitButton.disabled=true;submitButton.querySelector('span').textContent='Sending…';form.setAttribute('aria-busy','true');try{if(!csrfToken)await getToken();const fields=new FormData(form);const response=await fetch('/feedback',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({token:csrfToken,rating:fields.get('rating'),type:fields.get('type'),message:fields.get('message'),name:fields.get('name'),email:fields.get('email'),page:window.location.pathname})});const data=await response.json();if(!response.ok||!data.ok){if(response.status===403)csrfToken=null;throw new Error(data.error||'We couldn’t save your feedback. Please try again.');}form.reset();count.textContent='0 / 2000';content.hidden=true;success.hidden=false;dialog.setAttribute('aria-labelledby','feedback-success-title');dialog.setAttribute('aria-describedby','feedback-success-message');document.querySelector('#feedback-done').focus();}catch(e){error.textContent=e.message||'We couldn’t save your feedback. Please try again.';error.hidden=false;}finally{submitButton.disabled=false;submitButton.querySelector('span').textContent='Send feedback';form.removeAttribute('aria-busy');}});

const siteHeader=document.querySelector('.site-header');
function updateHeader(){siteHeader.classList.toggle('scrolled',window.scrollY>48);}
window.addEventListener('scroll',updateHeader,{passive:true});updateHeader();

const coverageQuoteForm=document.querySelector('#coverage-quote-form');
const quoteChoices=JSON.parse(document.querySelector('#cms-home-quote-products')?.textContent||'{}');
function showQuoteChoice(key){
 const choice=quoteChoices[key];
 document.querySelector('#quote-product-title').textContent=choice.title;
 coverageQuoteForm.action=key==='auto'?'/auto-quote/':key==='bundle'?'/quote/':'/'+choice.path;
 const more=document.querySelector('#quote-learn-link');
 more.href='/'+choice.info;more.textContent=choice.label;
 coverageQuoteForm.querySelectorAll('[name="service"]').forEach(field=>field.remove());
 let coverageField=coverageQuoteForm.querySelector('[name="coverage"]');
 if(coverageField)coverageField.remove();
 if(choice.path==='get-a-quote/'){coverageField=document.createElement('input');coverageField.type='hidden';coverageField.name=key==='bundle'?'service':'coverage';coverageField.value=key==='bundle'?'home-auto':key.toLowerCase();coverageQuoteForm.append(coverageField);}
}
document.querySelectorAll('[data-quote-product]').forEach(button=>button.addEventListener('click',()=>{
 const key=button.dataset.quoteProduct;const choice=quoteChoices[key];showQuoteChoice(key);
 document.querySelector('#quote-product-description').textContent=choice.description;
 document.querySelector('#quote-product-icon').src='/assets/quote-'+choice.icon+'.svg';
 document.querySelectorAll('.quote-product[data-quote-product]').forEach(tile=>tile.setAttribute('aria-pressed',String(tile===button)));
 document.querySelector('.quote-reset').hidden=key==='auto';
 const subproducts=document.querySelector('#quote-subproducts');subproducts.replaceChildren();subproducts.hidden=!choice.options;
 (choice.options||[]).forEach((label,index)=>{
  const option=document.createElement('button');option.type='button';option.textContent=label;option.setAttribute('aria-pressed',String(index===0));
  option.addEventListener('click',()=>{showQuoteChoice(label==='Home'?'property':label==='Motorcycle'?'recreation':label);subproducts.querySelectorAll('button').forEach(item=>item.setAttribute('aria-pressed',String(item===option)));});subproducts.append(option);
 });
}));

const customerReviewSlides=[...document.querySelectorAll('.review-slide')];
const customerReviewDots=[...document.querySelectorAll('[data-review-index]')];
let currentCustomerReview=0;
function displayCustomerReview(index){
 if(!customerReviewSlides.length)return;
 currentCustomerReview=(index+customerReviewSlides.length)%customerReviewSlides.length;
 customerReviewSlides.forEach((slide,i)=>{slide.hidden=i!==currentCustomerReview;});
 customerReviewDots.forEach((dot,i)=>dot.setAttribute('aria-pressed',String(i===currentCustomerReview)));
}
customerReviewDots.forEach(dot=>dot.addEventListener('click',()=>displayCustomerReview(Number(dot.dataset.reviewIndex))));
document.querySelector('#review-previous')?.addEventListener('click',()=>displayCustomerReview(currentCustomerReview-1));
document.querySelector('#review-next')?.addEventListener('click',()=>displayCustomerReview(currentCustomerReview+1));
document.querySelector('.review-controls')?.addEventListener('keydown',event=>{
 if(event.key==='ArrowLeft'||event.key==='ArrowRight'){
  event.preventDefault();displayCustomerReview(currentCustomerReview+(event.key==='ArrowRight'?1:-1));customerReviewDots[currentCustomerReview].focus();
 }
});

const journalMoreButton=document.querySelector('#journal-more');
journalMoreButton?.addEventListener('click',()=>{
 const expanded=journalMoreButton.getAttribute('aria-expanded')!=='true';
 document.querySelectorAll('.article-extra').forEach(card=>{card.hidden=!expanded;});
 journalMoreButton.setAttribute('aria-expanded',String(expanded));
 journalMoreButton.innerHTML=expanded?'Show fewer articles <span aria-hidden="true">−</span>':'Show more articles <span aria-hidden="true">+</span>';
});
