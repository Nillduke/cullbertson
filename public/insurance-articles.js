'use strict';
document.querySelectorAll('[data-article-slider]').forEach(slider=>{
    const track=slider.querySelector('.insurance-article-track'),controls=slider.querySelector('.insurance-article-controls'),previous=slider.querySelector('[data-article-previous]'),next=slider.querySelector('[data-article-next]'),meter=slider.querySelector('.insurance-article-meter>span'),status=slider.querySelector('.insurance-article-status');
    const cards=[...track.children];
    function update(){
        const max=track.scrollWidth-track.clientWidth,pos=Math.max(0,Math.min(track.scrollLeft,max));
        controls.hidden=max<=2;
        previous.disabled=pos<=2;
        next.disabled=pos>=max-2;
        const fraction=Math.min(1,track.clientWidth/Math.max(track.scrollWidth,1));
        meter.style.width=fraction*100+'%';
        meter.style.left=(max>0?pos/max*(1-fraction)*100:0)+'%';
    }
    function move(direction){
        const distance=cards[0].getBoundingClientRect().width+parseFloat(getComputedStyle(track).gap||0);
        track.scrollBy({left:distance*direction,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});
    }
    previous.addEventListener('click',()=>move(-1));
    next.addEventListener('click',()=>move(1));
    track.addEventListener('scroll',update,{passive:true});
    track.addEventListener('scrollend',()=>{
        const box=track.getBoundingClientRect();
        const first=cards.findIndex(card=>card.getBoundingClientRect().right>box.left+10);
        status.textContent='Showing articles '+(first+1)+' to '+Math.min(cards.length,first+Math.max(1,Math.floor(track.clientWidth/cards[0].getBoundingClientRect().width)))+' of '+cards.length+'.';
    });
    if(typeof ResizeObserver!=='undefined')new ResizeObserver(update).observe(track);
    else window.addEventListener('resize',update,{passive:true});
    slider.classList.add('is-interactive');update();
});
