(function () {
	'use strict';
	var form = document.querySelector('.ea-form[action*="admin-post.php"]');
	if (!form || !form.querySelector('[name="action"][value="ea_core_booking"]')) return;
	if (form.classList.contains('ea-calendar-form')) return;
	var resource = form.querySelector('#ea-resource');
	var date = form.querySelector('#ea-date');
	var slot = form.querySelector('#ea-slot');
	var status = form.querySelector('.ea-slot-status');
	var referee = form.querySelector('.ea-referee-field');
	function updateReferee() {
		var option = resource.options[resource.selectedIndex];
		referee.hidden = !option || !option.parentElement || option.parentElement.label !== 'Salon' || option.getAttribute('data-referee') === 'unavailable';
	}
	function loadSlots() {
		slot.innerHTML = '<option value="">' + eaCore.loading + '</option>';
		slot.disabled = true;
		status.textContent = '';
		updateReferee();
		if (!resource.value || !date.value) {
			slot.innerHTML = '<option value="">Önce kaynak ve tarih seçin</option>';
			return;
		}
		var data = new URLSearchParams({ action: 'ea_core_slots', nonce: eaCore.nonce, resource: resource.value, date: date.value });
		fetch(eaCore.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body: data.toString() })
			.then(function (response) { return response.json(); })
			.then(function (response) {
				slot.innerHTML = '';
				if (!response.success || !response.data.slots.length) {
					slot.innerHTML = '<option value="">' + eaCore.empty + '</option>';
					status.textContent = response.success ? eaCore.empty : response.data.message;
					return;
				}
				slot.innerHTML = '<option value="">' + eaCore.choose + '</option>';
				response.data.slots.forEach(function (item) {
					var option = document.createElement('option');
					option.value = item.start + '|' + item.end;
					option.textContent = item.start + '–' + item.end;
					slot.appendChild(option);
				});
				slot.disabled = false;
			})
			.catch(function () {
				slot.innerHTML = '<option value="">Saatler alınamadı</option>';
				status.textContent = 'Bağlantı sorunu oluştu. Lütfen tekrar deneyin.';
			});
	}
	resource.addEventListener('change', loadSlots);
	date.addEventListener('change', loadSlots);
	updateReferee();
}());
