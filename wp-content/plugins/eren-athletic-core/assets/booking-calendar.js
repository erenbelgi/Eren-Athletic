(function () {
 'use strict';
 document.querySelectorAll('.ea-calendar-form').forEach(function (form) {
  var calendar = form.querySelector('.ea-booking-calendar'); if (!calendar) return;
  var resource = form.querySelector('#ea-resource'), date = form.querySelector('#ea-date'), slot = form.querySelector('#ea-slot');
  var status = form.querySelector('.ea-slot-status'), referee = form.querySelector('.ea-referee-field');
  var title = calendar.querySelector('.ea-calendar-title'), days = calendar.querySelector('.ea-calendar-days'), times = calendar.querySelector('.ea-calendar-times');
  var message = calendar.querySelector('.ea-calendar-message'), dayTitle = calendar.querySelector('.ea-calendar-day-title');
  var prev = calendar.querySelector('.ea-calendar-prev'), next = calendar.querySelector('.ea-calendar-next');
  var labels = { available: 'Müsait', partial: 'Kısmen dolu', full: 'Tamamen dolu', closed: 'Kapalı veya kalan saat yok', undefined: 'Çalışma takvimi tanımlanmamış', past: 'Geçmiş tarih' };
  var today = eaCalendar.today, month = today.slice(0, 7), monthSequence = 0, daySequence = 0;
  var monthController, dayController;
  var months = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
  function dayDate(value) { return new Date(value + 'T12:00:00'); }
  function fullDate(value) { return dayDate(value).toLocaleDateString('tr-TR', { day: 'numeric', month: 'long', year: 'numeric' }); }
  function resetSlots() { slot.replaceChildren(new Option('Önce bir gün ve saat seçin', '')); slot.disabled = true; times.replaceChildren(); times.removeAttribute('aria-busy'); status.textContent = ''; }
  function refereeState() { var option = resource.options[resource.selectedIndex]; referee.hidden = !option || option.parentElement.label !== 'Salon' || option.dataset.referee === 'unavailable'; if (referee.hidden) referee.querySelector('input').checked = false; }
  function request(values, signal) {
   return fetch(eaCalendar.ajaxUrl, { method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: signal, headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body: new URLSearchParams(Object.assign({ action: 'ea_calendar', nonce: eaCalendar.nonce, resource: resource.value, trainer_id: (form.querySelector('#ea-trainer') || {}).value || '' }, values)).toString() })
    .then(function (r) { return r.json(); }).then(function (r) { if (!r.success) throw new Error(r.data && r.data.message || 'Takvim yüklenemedi. Sayfayı yenileyip tekrar deneyin.'); return r.data; });
  }
  function markSelection() { days.querySelectorAll('button').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.date === date.value ? 'true' : 'false'); }); }
  function loadDay(preserve) {
   var focusTime=times.contains(document.activeElement)?document.activeElement.dataset.value:null;
   var selected = preserve ? slot.value : '', sequence = ++daySequence; if (dayController) dayController.abort(); dayController = new AbortController(); resetSlots();
   if (!resource.value || !date.value || date.value < today) { if (date.value && date.value < today) status.textContent = 'Geçmiş tarihler seçilemez.'; return; }
   var requestedDate = date.value; markSelection(); dayTitle.textContent = fullDate(requestedDate) + ' · Saat seçimi'; status.textContent = 'Saatler yükleniyor…'; times.setAttribute('aria-busy', 'true');
   request({ date: requestedDate }, dayController.signal).then(function (data) {
    if (sequence !== daySequence || date.value !== requestedDate) return;
    times.removeAttribute('aria-busy'); status.textContent = labels[data.status];
    data.slots.forEach(function (item) {
     var value = item.start + '|' + item.end, text = item.start + '–' + item.end;
     var button = document.createElement('button'); button.type = 'button'; button.className = 'ea-calendar-time'; button.disabled = !item.available; button.textContent = text + (item.available ? '' : ' · Dolu'); button.dataset.value = value; button.dataset.dateLabel=fullDate(requestedDate); button.dataset.available=item.available?'1':'0'; button.setAttribute('aria-label',fullDate(requestedDate)+' · '+text+' · '+(item.available?'Müsait':'Dolu')); button.setAttribute('aria-pressed', 'false');
     if (item.available) { slot.add(new Option(text, value)); button.addEventListener('click', function () { slot.value = value; syncTime(); status.textContent = fullDate(date.value) + ' · ' + text + ' seçildi.'; }); }
     times.appendChild(button);
    });
    slot.disabled = slot.options.length < 2;
    if (!slot.disabled && Array.from(slot.options).some(function (o) { return o.value === selected; })) slot.value = selected;
    syncTime();
    if(focusTime&&document.activeElement===document.body){var restored=Array.from(times.querySelectorAll('button')).find(function(b){return b.dataset.value===focusTime&&!b.disabled;});if(restored)restored.focus({preventScroll:true});}
   }).catch(function (error) { if (error.name === 'AbortError' || sequence !== daySequence) return; times.removeAttribute('aria-busy'); status.textContent = error.message; });
  }
  function syncTime() { times.querySelectorAll('button').forEach(function (b) { var chosen=b.dataset.value===slot.value;b.setAttribute('aria-pressed',chosen?'true':'false');b.setAttribute('aria-label',b.dataset.dateLabel+' · '+b.dataset.value.replace('|','–')+' · '+(chosen?'Seçili':(b.dataset.available==='1'?'Müsait':'Dolu'))); }); }
  function loadMonth() {
   var focusDay=days.contains(document.activeElement)?document.activeElement.dataset.date:null;
   var sequence = ++monthSequence; if (monthController) monthController.abort(); monthController = new AbortController();
   title.textContent = months[Number(month.slice(5)) - 1] + ' ' + month.slice(0, 4); prev.disabled = month <= today.slice(0, 7); next.disabled = month >= '2099-12';
   days.replaceChildren(); days.removeAttribute('aria-busy');
   if (!resource.value) { message.textContent = 'Önce bir salon, eğitim veya antrenör seçin.'; return; }
   message.textContent = 'Takvim yükleniyor…'; days.setAttribute('aria-busy', 'true');
   request({ month: month }, monthController.signal).then(function (data) {
    if (sequence !== monthSequence) return;
    today = data.today; date.min = today; days.removeAttribute('aria-busy');
    var offset = (dayDate(month + '-01').getDay() + 6) % 7;
    for (var i = 0; i < offset; i++) { var spacer = document.createElement('span'); spacer.setAttribute('aria-hidden', 'true'); days.appendChild(spacer); }
    data.days.forEach(function (day) {
     var b = document.createElement('button'); b.type = 'button'; b.className = 'ea-calendar-day'; b.dataset.date = day.date; b.dataset.state = day.status; b.dataset.statusMark=({available:'M',partial:'K',full:'D',closed:'—',undefined:'?',past:'×'})[day.status]||'?'; b.textContent = String(Number(day.date.slice(-2))); b.disabled = day.date < today;
     b.setAttribute('aria-label', fullDate(day.date) + ': ' + labels[day.status]); b.title = labels[day.status];
     if (day.date === today) b.setAttribute('aria-current', 'date');
     b.addEventListener('click', function () { date.value = day.date; loadDay(false); }); days.appendChild(b);
    });
    markSelection(); if(focusDay&&document.activeElement===document.body){var restore=Array.from(days.querySelectorAll('button')).find(function(b){return b.dataset.date===focusDay&&!b.disabled;});if(restore)restore.focus({preventScroll:true});} message.textContent = 'Takvim güncel doluluğu gösterir. Bir gün seçerek saatleri inceleyin.';
    if (data.days.every(function (d) { return d.status === 'undefined' || d.status === 'past'; })) message.textContent = 'Bu kaynağın çalışma takvimi henüz tanımlanmamış.';
   }).catch(function (error) { if (error.name !== 'AbortError' && sequence === monthSequence) { days.removeAttribute('aria-busy'); message.textContent = error.message; } });
  }
  function changeMonth(delta) { var d = dayDate(month + '-01'); d.setMonth(d.getMonth() + delta); month = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0'); loadMonth(); }
  prev.addEventListener('click', function () { changeMonth(-1); }); next.addEventListener('click', function () { changeMonth(1); });
  days.addEventListener('keydown', function (event) { var shifts = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }; if (!(event.key in shifts) || !event.target.dataset.date) return; event.preventDefault(); var all = Array.from(days.querySelectorAll('button')), index = all.indexOf(event.target) + shifts[event.key]; while (all[index] && all[index].disabled) index += shifts[event.key] < 0 ? -1 : 1; if (all[index]) all[index].focus(); });
  resource.addEventListener('change', function () { ++daySequence; if (dayController) dayController.abort(); date.value = ''; resetSlots(); refereeState(); dayTitle.textContent = 'Saat seçimi'; month = today.slice(0, 7); loadMonth(); });
  date.addEventListener('change', function () { if (date.value && date.value >= today) { month = date.value.slice(0, 7); loadMonth(); } loadDay(false); }); slot.addEventListener('change', syncTime);
  // Avoid removing a focused control during window/timer refresh. Server-side booking
  // validation remains authoritative; refresh resumes when the user leaves the widget.
  function refresh() { if (form.contains(document.activeElement)) return; if (resource.value && !document.hidden) { loadMonth(); if (date.value) loadDay(true); } }
  form.addEventListener('focusout', function (event) { if (!form.contains(event.relatedTarget)) window.setTimeout(refresh, 0); });
  window.addEventListener('focus', refresh); window.addEventListener('pageshow', function (event) { if (event.persisted) refresh(); }); document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
  window.setInterval(refresh, 60000);
  var restored = new URLSearchParams(location.search).get('ea_calendar_date'); if (/^\d{4}-\d{2}-\d{2}$/.test(restored || '') && restored >= today) { date.value = restored; month = restored.slice(0, 7); }
  calendar.hidden = false; refereeState(); loadMonth(); if (date.value) loadDay(false);
 });
}());
