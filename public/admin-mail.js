'use strict';
(()=>{
 const provider=document.getElementById('mail-provider');if(!provider)return;
 const host=document.getElementById('mail-host'),port=document.getElementById('mail-port'),encryption=document.getElementById('mail-encryption'),auth=document.getElementById('mail-authentication'),note=document.querySelector('[data-provider-note]');
 function update(changed=false){
  const relay=provider.value.startsWith('godaddy'),log=provider.value==='log';
  if(relay){host.value=provider.value==='godaddy_local'?'localhost':'relay-hosting.secureserver.net';port.value='25';encryption.value='none';auth.checked=false;}
  else if(changed&&provider.value==='smtp'){host.value='';port.value='587';encryption.value='starttls';auth.checked=true;}
  host.readOnly=relay||log;port.readOnly=relay||log;encryption.disabled=false;auth.disabled=false;
  document.querySelectorAll('[data-smtp-auth],[data-password-clear]').forEach(el=>el.hidden=relay||log||!auth.checked);
  note.textContent=provider.value==='godaddy_local'?'GoDaddy cPanel: localhost, port 25, no authentication and no encryption. Use a sender mailbox on your own domain.':provider.value==='godaddy_relay'?'Legacy GoDaddy / Windows relay: relay-hosting.secureserver.net, port 25, no authentication and no encryption. cPanel normally uses localhost.':log?'Log mode writes the email to the private application log. It does not send an email.':'Use the SMTP settings from your mail provider. STARTTLS typically uses 587; implicit TLS typically uses 465. Passwords remain encrypted and are never displayed.';
 }
 provider.addEventListener('change',()=>update(true));auth.addEventListener('change',()=>update());update();
})();
