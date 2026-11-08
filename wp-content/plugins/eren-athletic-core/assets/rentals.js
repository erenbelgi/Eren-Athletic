(function(){'use strict';document.querySelectorAll('.ea-rental-route').forEach(function(route){var form=route.closest('form'),resource=form&&form.querySelector('#ea-resource'),data=JSON.parse(route.querySelector('.ea-rental-route-data').textContent),link=route.querySelector('a'),base=link.href;if(!resource)return;function sync(){var url=data[resource.value];route.hidden=!!resource.value&&!url;if(url)link.href=url;else link.href=base;}resource.addEventListener('change',sync);sync();});document.querySelectorAll('.ea-rental-form').forEach(function(form){
 var date=form.querySelector('#ea-date'),slot=form.querySelector('#ea-slot'),start=form.querySelector('[name=start_time]'),venue=form.querySelector('[name=venue_id]');
 function sync(){start.value=(slot.value||'').split('|')[0]||'';form.querySelectorAll('[data-auth-base]').forEach(function(a){var u=new URL(a.dataset.authBase,location.href);u.searchParams.set('rental_venue',venue.value);u.searchParams.set('rental_date',date.value);u.searchParams.set('rental_start',start.value);a.href=u.href;});}
 var selected=start.value;if(selected&&date.value){var observer=new MutationObserver(function(){var option=Array.from(slot.options).find(function(o){return o.value.split('|')[0]===selected;});if(option){slot.value=option.value;sync();observer.disconnect();}});observer.observe(slot,{childList:true});}
 slot.addEventListener('change',sync);date.addEventListener('change',sync);form.addEventListener('submit',function(){sync();});
});
document.querySelectorAll('.ea-rental-gallery-section').forEach(function(section){
 var links=Array.from(section.querySelectorAll('.ea-rental-gallery-item')),dialog=section.querySelector('dialog'),picture=dialog&&dialog.querySelector('img'),index=0,origin;
 if(!dialog||typeof dialog.showModal!=='function'||!links.length)return;
 function show(i){index=(i+links.length)%links.length;picture.src=links[index].href;picture.alt=links[index].querySelector('img').alt;picture.classList.toggle('ea-rental-legacy-frame',links[index].dataset.legacy==='1');dialog.querySelector('.ea-rental-lightbox-count').textContent=(index+1)+' / '+links.length;}
 links.forEach(function(link,i){link.addEventListener('click',function(event){event.preventDefault();origin=link;show(i);dialog.showModal();});});
 dialog.querySelector('.ea-rental-lightbox-close').addEventListener('click',function(){dialog.close();});
 dialog.querySelector('.ea-rental-lightbox-prev').addEventListener('click',function(){show(index-1);});dialog.querySelector('.ea-rental-lightbox-next').addEventListener('click',function(){show(index+1);});
 dialog.addEventListener('keydown',function(event){if(event.key==='ArrowLeft'||event.key==='ArrowRight'){event.preventDefault();show(index+(event.key==='ArrowLeft'?-1:1));}});
 dialog.addEventListener('click',function(event){if(event.target===dialog){var rect=dialog.getBoundingClientRect();if(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom)dialog.close();}});
 dialog.addEventListener('close',function(){picture.removeAttribute('src');if(origin)origin.focus();});
 if(links.length<2){dialog.querySelector('.ea-rental-lightbox-prev').hidden=true;dialog.querySelector('.ea-rental-lightbox-next').hidden=true;}
});}());
