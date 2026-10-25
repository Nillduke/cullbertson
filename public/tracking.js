'use strict';
(()=>{
 const element=document.getElementById('culbertson-tracking-config');
 if(!element)return;
 let config;try{config=JSON.parse(element.textContent);}catch{return;}
 const panel=document.getElementById('tracking-choice');
 const settings=document.getElementById('tracking-settings');
 const close=document.querySelector('[data-tracking-close]');
 const consentKey='culbertson_google_consent';
 const sentKey='culbertson_completed_quote_events';
 const version=Number(config.consentVersion)||1;
 let allowed=false,started=false,hasChoice=false;
 const pending=new Set(),sent=new Set();
 window.dataLayer=window.dataLayer||[];
 window.gtag=window.gtag||function(){window.dataLayer.push(arguments);};
 const gtag=(...args)=>window.gtag(...args);
 const cleanUrl=value=>{if(!value)return '';try{const u=new URL(value,window.location.origin);return /^https?:$/.test(u.protocol)?u.origin+u.pathname:'';}catch{return '';}};
 const location=cleanUrl(window.location.href),referrer=cleanUrl(document.referrer||'');
 gtag('consent','default',{analytics_storage:'denied',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied'});
 function appendScript(id,src){if(document.getElementById(id))return;const script=document.createElement('script');script.id=id;script.async=true;script.src=src;document.head.appendChild(script);}
 function start(){
  if(started||!allowed)return;started=true;
  gtag('set',{page_location:location,page_referrer:referrer,allow_google_signals:false,allow_ad_personalization_signals:false});
  gtag('js',new Date());
  const legacyPath=config.legacyAutoConfirmationPath.replace(/\/+$/,'');
  const currentPath=window.location.pathname.replace(/\/+$/,'');
  // The legacy GTM rule counts any visit to this thank-you URL as a lead.
  // Use direct page measurement here so bookmarks/reloads cannot create false conversions.
  const legacyConfirmation=currentPath===legacyPath||currentPath.endsWith(legacyPath);
  if(legacyConfirmation){
   gtag('config',config.primaryAnalyticsId,{send_page_view:true,page_location:location,page_referrer:referrer});
   gtag('config',config.adsId,{send_page_view:false});
  }else{
   window.dataLayer.push({'gtm.start':Date.now(),event:'gtm.js'});
   appendScript('culbertson-gtm','https://www.googletagmanager.com/gtm.js?id='+encodeURIComponent(config.containerId));
  }
  // GT-WR4WXG6 and G-9CTSTL96SL identify the same Google tag/destination.
  // Configure the GA4 destination once; the forms-only destination sends no page view.
  const pageDestinations=new Set([config.primaryAnalyticsId]);
  if(!pageDestinations.has(config.additionalAnalyticsId)){
   gtag('config',config.additionalAnalyticsId,{send_page_view:true,page_location:location,page_referrer:referrer});
   pageDestinations.add(config.additionalAnalyticsId);
  }
  if(!pageDestinations.has(config.formsAnalyticsId))gtag('config',config.formsAnalyticsId,{send_page_view:false});
  appendScript('culbertson-google-tag','https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(config.additionalGoogleTagId));
 }
 function show(focus=false){if(!panel||!settings)return;panel.hidden=false;settings.hidden=true;settings.setAttribute('aria-expanded','true');if(close)close.hidden=!hasChoice;if(focus)panel.querySelector('h2')?.focus();}
 function hide(){if(!panel||!settings)return;panel.hidden=true;settings.hidden=false;settings.setAttribute('aria-expanded','false');}
 function apply(choice,persist=false){
  const wasStarted=started;allowed=choice===true;hasChoice=true;
  if(persist){try{localStorage.setItem(consentKey,JSON.stringify({version,allowed,expires:Date.now()+Math.max(1,Math.min(365,Number(config.consentDays)||180))*86400000}));}catch{}}
  gtag('consent','update',{analytics_storage:allowed?'granted':'denied',ad_storage:allowed?'granted':'denied',ad_user_data:'denied',ad_personalization:'denied'});
  hide();if(allowed)start();else if(wasStarted&&persist)window.location.reload();
 }
 try{
  const saved=JSON.parse(localStorage.getItem(consentKey)||'null');
  if(saved&&saved.version===version&&typeof saved.allowed==='boolean'&&saved.expires>Date.now())apply(saved.allowed);
 }catch{}
 if(!hasChoice)show();
 try{const previous=JSON.parse(sessionStorage.getItem(sentKey)||'[]');if(Array.isArray(previous))previous.filter(x=>/^[a-f0-9]{64}$/.test(x)).forEach(x=>sent.add(x));}catch{}
 document.querySelectorAll('[data-tracking-choice]').forEach(button=>button.addEventListener('click',()=>apply(button.dataset.trackingChoice==='allow',true)));
 settings?.addEventListener('click',()=>show(true));
 close?.addEventListener('click',()=>{hide();settings?.focus();});
 async function completeQuote(input){
  const reference=input?.reference,service=input?.service;
  if(!allowed||input?.completed!==true||typeof reference!=='string'||!/^CUL-\d{6}-[A-F0-9]{12}$/.test(reference)||typeof service!=='string'||!/^[a-z][a-z0-9-]{0,39}$/.test(service)||pending.has(reference))return false;
  pending.add(reference);
  try{
   if(!window.crypto?.subtle)return false;
   const digest=await window.crypto.subtle.digest('SHA-256',new TextEncoder().encode(reference));
   const id=Array.from(new Uint8Array(digest),byte=>byte.toString(16).padStart(2,'0')).join('');
   if(!allowed||sent.has(id))return false;
   start();
   const destinations=[...new Set([config.primaryAnalyticsId,config.formsAnalyticsId,config.additionalAnalyticsId])];
   // Only these non-personal fields are routed to Google; never pass the form payload.
   gtag('event','generate_lead',{send_to:destinations,transaction_id:id,coverage_type:service,page_location:location,page_referrer:referrer});
   if((config.adsServices||['auto']).includes(service))gtag('event','conversion',{send_to:config.adsId+'/'+config.adsConversionLabel,transaction_id:id,page_location:location,page_referrer:referrer});
   window.dataLayer.push({event:'culbertson_quote_complete',transaction_id:id,coverage_type:service});
   sent.add(id);try{sessionStorage.setItem(sentKey,JSON.stringify([...sent].slice(-100)));}catch{}
   return true;
  }catch{return false;}finally{pending.delete(reference);}
 }
 window.CulbertsonTracking=Object.freeze({completeQuote});
})();
