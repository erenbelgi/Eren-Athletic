(function () {
    'use strict';
    document.querySelectorAll('.ea-account-password-toggle').forEach(function (button) {
        var input = document.getElementById(button.getAttribute('aria-controls'));
        if (!input) return;
        button.hidden = false;
        button.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.textContent = show ? 'Şifreyi gizle' : 'Şifreyi göster';
        });
    });
}());
