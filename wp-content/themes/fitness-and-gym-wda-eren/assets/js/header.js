(function () {
	'use strict';

	function setupAccessibleMenu() {
		var button = document.getElementById('wpdevartOpenMenuButton');
		var nav = document.getElementById('wpdevartmobilemenu');
		var menu = nav ? nav.querySelector('ul.menu') : null;

		if (!button || !nav || !menu) {
			return;
		}

		if (!menu.id) {
			menu.id = 'erah-primary-menu';
		}

		button.setAttribute('type', 'button');
		button.setAttribute('aria-label', 'Menüyü aç');
		button.setAttribute('aria-controls', menu.id);
		button.setAttribute('aria-expanded', 'false');

		// The parent theme's inline handler installs a page-wide focus trap for this disclosure menu.
		button.removeAttribute('onclick');

		function syncMenuState() {
			var isOpen = button.classList.contains('menu-opened') || menu.classList.contains('open');
			button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			button.setAttribute('aria-label', isOpen ? 'Menüyü kapat' : 'Menüyü aç');
		}

		button.addEventListener('click', function () {
			window.requestAnimationFrame(syncMenuState);
		});
		nav.addEventListener('focusout', function () {
			window.requestAnimationFrame(function () {
				if (!nav.contains(document.activeElement) && button.getAttribute('aria-expanded') === 'true') button.click();
			});
		});

		document.addEventListener('keydown', function (event) {
			if ('Escape' === event.key && 'true' === button.getAttribute('aria-expanded')) {
				event.preventDefault();
				button.click();
				button.focus();
			}
		});
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', setupAccessibleMenu);
	} else {
		setupAccessibleMenu();
	}
})();
