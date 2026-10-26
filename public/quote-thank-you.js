(() => {
 'use strict';
 const element=document.getElementById('quote-completion-data');
 if(!element)return;
 try{window.CulbertsonTracking?.completeQuote(JSON.parse(element.textContent));}catch{}
})();
