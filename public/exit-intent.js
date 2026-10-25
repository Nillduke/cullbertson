'use strict';
(()=>{
    const popup=document.querySelector('#exit-intent-dialog');
    if(!popup||typeof popup.showModal!=='function')return;
    const storageKey='culbertson.exit-intent.seen.v1';
    const preview=popup.dataset.preview==='true';
    const started=performance.now();
    let shown=false,previousFocus=null;
    const seen=()=>{try{return sessionStorage.getItem(storageKey)==='1';}catch{return shown;}};
    const remember=()=>{try{sessionStorage.setItem(storageKey,'1');}catch{/* The in-memory flag still prevents repeats on this page. */}};
    function openPopup(){
        if(shown||popup.open||document.hidden||document.querySelector('dialog[open]'))return;
        // Visitors already entering a quote should be able to finish uninterrupted.
        if(!preview&&document.querySelector('#auto-quote-wizard,#service-quote-wizard'))return;
        previousFocus=document.activeElement;
        popup.showModal();
        document.body.classList.add('exit-intent-open');
        shown=true;
        if(!preview)remember();
        popup.querySelector('#exit-intent-title').focus({preventScroll:true});
    }
    function closePopup(){popup.close();}
    popup.querySelectorAll('[data-dismiss-exit]').forEach(button=>button.addEventListener('click',closePopup));
    popup.querySelector('.exit-intent-cta').addEventListener('click',()=>{remember();closePopup();});
    popup.addEventListener('cancel',event=>{event.preventDefault();closePopup();});
    popup.addEventListener('close',()=>{
        document.body.classList.remove('exit-intent-open');
        if(previousFocus?.isConnected)previousFocus.focus({preventScroll:true});
    });
    popup.addEventListener('click',event=>{
        if(event.target!==popup)return;
        const box=popup.getBoundingClientRect();
        if(event.clientX<box.left||event.clientX>box.right||event.clientY<box.top||event.clientY>box.bottom)closePopup();
    });
    // Desktop exit intent: the pointer leaves the top of the page after 8 seconds.
    // No unload prompt, navigation interception, or timed popup on touch screens.
    document.addEventListener('mouseout',event=>{
        if(preview||seen()||shown||event.relatedTarget!==null||event.clientY>0||performance.now()-started<8000)return;
        if(!matchMedia('(hover: hover) and (pointer: fine)').matches)return;
        if(document.querySelector('.mega-trigger[aria-expanded="true"],.menu-toggle[aria-expanded="true"]'))return;
        openPopup();
    });
    if(preview)openPopup();
})();
