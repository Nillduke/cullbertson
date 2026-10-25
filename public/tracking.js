'use strict';
(()=>{
 const element=document.getElementById('culbertson-tracking-config');if(!element)return;
 let config;try{config=JSON.parse(element.textContent);}catch{return;}
 const sentKey='culbertson_completed_quote_events',pending=new Set(),sent=new Set();
 window.dataLayer=window.dataLayer||[];
 window.gtag=window.gtag||function(){window.dataLayer.push(arguments);};
 const gtag=(...args)=>window.gtag(...args);
 const cleanUrl=value=>{if(!value)return '';try{const u=new URL(value,window.location.origin);return /^https?:$/.test(u.protocol)?u.origin+u.pathname:'';}catch{return '';}};
 const location=cleanUrl(window.location.href),referrer=cleanUrl(document.referrer||'');
 if(!config.useContainer)gtag('config',config.primaryAnalyticsId,{send_page_view:true,page_location:location,page_referrer:referrer});
 const destinations=new Set([config.primaryAnalyticsId]);
 if(!destinations.has(config.additionalAnalyticsId)){
  gtag('config',config.additionalAnalyticsId,{send_page_view:true,page_location:location,page_referrer:referrer});
  destinations.add(config.additionalAnalyticsId);
 }
 if(!destinations.has(config.formsAnalyticsId))gtag('config',config.formsAnalyticsId,{send_page_view:false});
 try{const previous=JSON.parse(sessionStorage.getItem(sentKey)||'[]');if(Array.isArray(previous))previous.filter(x=>/^[a-f0-9]{64}$/.test(x)).forEach(x=>sent.add(x));}catch{}
 async function completeQuote(input){
  const reference=input?.reference,service=input?.service;
  if(input?.completed!==true||typeof reference!=='string'||!/^CUL-\d{6}-[A-F0-9]{12}$/.test(reference)||typeof service!=='string'||!/^[a-z][a-z0-9-]{0,39}$/.test(service)||pending.has(reference))return false;
  pending.add(reference);
  try{
   if(!window.crypto?.subtle)return false;
   const digest=await window.crypto.subtle.digest('SHA-256',new TextEncoder().encode(reference));
   const id=Array.from(new Uint8Array(digest),byte=>byte.toString(16).padStart(2,'0')).join('');
   if(sent.has(id))return false;
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
