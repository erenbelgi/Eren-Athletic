(function(){
 'use strict';
 document.querySelectorAll('.ea-calendar-form').forEach(function(form){
  var dataNode=form.nextElementSibling;
  while(dataNode&&!dataNode.matches('.ea-booking-member-data'))dataNode=dataNode.nextElementSibling;
  if(!dataNode)return;
  var data=JSON.parse(dataNode.textContent),resource=form.querySelector('#ea-resource'),date=form.querySelector('#ea-date'),slot=form.querySelector('#ea-slot'),select=form.querySelector('#ea-booking-membership'),fields=form.querySelector('.ea-member-booking-fields'),notice=form.querySelector('.ea-membership-booking-notice'),detail=form.querySelector('.ea-membership-booking-detail');
  if(!select||!data.authenticated||!data.memberships.length)return;
  function update(){
   var previous=select.value,chosen=Number(resource.value),selectedDate=date.value;
   var allowed=data.memberships.filter(function(m){return m.remaining>0&&m.resources.indexOf(chosen)!==-1&&(!selectedDate||selectedDate<=m.end_label.split(' ')[0].split('.').reverse().join('-'));});
   select.replaceChildren();
   allowed.forEach(function(m){select.add(new Option(m.title+' · '+m.remaining+' seans · Bitiş: '+m.end_label,String(m.id)));});
   if(allowed.some(function(m){return String(m.id)===previous;}))select.value=previous;
   fields.disabled=!allowed.length;
   notice.textContent=allowed.length?'':(chosen?'Bu hizmet için geçerli rezervasyon hakkınız bulunmuyor.':'Rezervasyon hakkını görmek için bir hizmet seçin.');
   showDetail();
  }
  function showDetail(){var m=data.memberships.find(function(m){return String(m.id)===select.value;});detail.textContent=m?'Kalan '+m.remaining+' seans · Paket bitişi '+m.end_label:'';}
  resource.addEventListener('change',update);date.addEventListener('change',update);slot.addEventListener('change',update);select.addEventListener('change',showDetail);update();
 });
}());
