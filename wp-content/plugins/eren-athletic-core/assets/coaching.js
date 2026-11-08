(function(){
 'use strict';
 document.querySelectorAll('.ea-calendar-form').forEach(function(form){
  var node=form.querySelector('.ea-coaching-data'),resource=form.querySelector('#ea-resource'),trainer=form.querySelector('#ea-trainer'),wrap=form.querySelector('.ea-coach-trainer-wrap');
  if(!node||!resource||!trainer||!wrap)return;
  var data=JSON.parse(node.textContent);
  function populate(){var previous=trainer.value,choices=data[resource.value]||[];trainer.replaceChildren();choices.forEach(function(t){trainer.add(new Option(t.title,String(t.id)));});if(choices.some(function(t){return String(t.id)===previous;}))trainer.value=previous;wrap.hidden=!Object.prototype.hasOwnProperty.call(data,resource.value);trainer.required=!wrap.hidden;trainer.disabled=wrap.hidden;if(!wrap.hidden&&!choices.length)trainer.add(new Option('Yetkili antrenör henüz eşleştirilmemiş',''));}
  resource.addEventListener('change',populate,true);
  trainer.addEventListener('change',function(){resource.dispatchEvent(new Event('change'));});
  populate();
 });
}());
