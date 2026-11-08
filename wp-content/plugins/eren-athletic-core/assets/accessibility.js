(function(){'use strict';
 var main=document.querySelector('main,[role="main"]');
 if(main){main.tabIndex=-1;var heading=main.querySelector('h1');if(heading){if(!heading.id)heading.id='ea-main-title';main.setAttribute('aria-labelledby',heading.id);}}
 document.querySelectorAll('a.skip-link').forEach(function(link){
  var target=document.getElementById(link.hash.slice(1))||main;if(!target)return;link.href='#'+target.id;
  link.addEventListener('click',function(){target.tabIndex=-1;target.focus({preventScroll:true});target.scrollIntoView({block:'start'});});
 });
 var search=document.querySelector('#ea-site-search'),opener;
 if(search&&typeof search.showModal==='function'){
  document.querySelectorAll('#wpdevartwidesearchbutton,#wpdevartsmallsearchbutton').forEach(function(button){button.removeAttribute('onclick');button.addEventListener('click',function(){opener=button;search.showModal();search.querySelector('input').focus();});});
  search.querySelector('[data-close-search]').addEventListener('click',function(){search.close();});search.addEventListener('close',function(){if(opener)opener.focus();});
 }
 document.querySelectorAll('dialog').forEach(function(dialog){dialog.addEventListener('keydown',function(event){
  if(event.key!=='Tab'||!dialog.open)return;var controls=Array.from(dialog.querySelectorAll('a[href],button,input,select,textarea,[tabindex]')).filter(function(e){return !e.disabled&&e.tabIndex>=0&&e.getClientRects().length&&!e.hidden;});if(!controls.length)return;
  var first=controls[0],last=controls[controls.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
 });});
 document.querySelectorAll('form').forEach(function(form){
  if(form.closest('.ea-site-search')||form.classList.contains('searchform'))return;
  var errors=new Map(),summary;
  function fieldName(input){return input.labels&&input.labels.length?input.labels[0].textContent.replace(/\*/g,'').trim():'Alan';}
  function render(){if(!summary){summary=document.createElement('div');summary.className='ea-a11y-errors';summary.setAttribute('role','alert');summary.tabIndex=-1;form.prepend(summary);}summary.replaceChildren();summary.hidden=!errors.size;if(!errors.size)return;
   var title=document.createElement('p');title.textContent='Lütfen aşağıdaki alanları kontrol edin:';summary.append(title);var list=document.createElement('ul');errors.forEach(function(item,input){var li=document.createElement('li'),link=document.createElement('a');link.href='#'+input.id;link.textContent=fieldName(input)+': '+item.textContent;link.addEventListener('click',function(e){e.preventDefault();input.focus();});li.append(link);list.append(li);});summary.append(list);
  }
  function clear(input){var error=errors.get(input);if(!error)return;input.removeAttribute('aria-invalid');var ids=(input.getAttribute('aria-describedby')||'').split(/\s+/).filter(function(id){return id&&id!==error.id;});if(ids.length)input.setAttribute('aria-describedby',ids.join(' '));else input.removeAttribute('aria-describedby');error.remove();errors.delete(input);render();}
  form.addEventListener('invalid',function(event){var input=event.target;if(!input.id)return;event.preventDefault();var inputError=errors.get(input);
   if(!inputError){inputError=document.createElement('span');inputError.id=input.id+'-validation-error';inputError.className='ea-a11y-field-error';input.after(inputError);errors.set(input,inputError);var ids=(input.getAttribute('aria-describedby')||'').split(/\s+/).filter(Boolean);ids.push(inputError.id);input.setAttribute('aria-describedby',Array.from(new Set(ids)).join(' '));}
   var v=input.validity;inputError.textContent=v.valueMissing?'Bu alanı doldurun.':v.typeMismatch?'Geçerli bir e-posta adresi girin.':v.tooShort?'En az '+input.minLength+' karakter girin.':v.tooLong?'En fazla '+input.maxLength+' karakter girin.':v.rangeUnderflow||v.rangeOverflow?'İzin verilen aralıkta bir değer girin.':'Geçerli bir değer girin.';input.setAttribute('aria-invalid','true');render();queueMicrotask(function(){var invalid=Array.from(form.elements).find(function(e){return e.willValidate&&!e.validity.valid;});if(invalid)invalid.focus();});
  },true);
  form.addEventListener('input',function(event){if(event.target.validity&&event.target.validity.valid)clear(event.target);});
  form.addEventListener('change',function(event){if(event.target.validity&&event.target.validity.valid)clear(event.target);});
 });
 var serverError=document.querySelector('.ea-account-error[role=alert],.ea-notice-error[role=alert],.ea-rental-policy[role=alert]');if(serverError){serverError.tabIndex=-1;serverError.focus();var form=serverError.closest('section')?.querySelector('form');if(form){if(!serverError.id)serverError.id='ea-server-error';form.setAttribute('aria-describedby',serverError.id);}}
}());
