(function () {
    'use strict';

    var form = document.querySelector('#contact-form form');
    if (!form) return;

    var rules = {
        name:    function (v) { return v.trim().length >= 3; },
        company: function (v) { return v.trim().length >= 3; },
        email:   function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v); },
        phone:   function (v) { return /^\+\d{11}$/.test(v); }
    };

    function check(input) {
        var rule = rules[input.dataset.validate];
        if (!rule) return true;
        var ok = rule(input.value);
        input.classList.toggle('invalid', !ok);
        return ok;
    }

    form.querySelectorAll('[data-validate]').forEach(function (input) {
        input.addEventListener('blur', function () { check(input); });
        input.addEventListener('input', function () {
            if (input.classList.contains('invalid')) check(input);
        });
    });

    // Телефон: только + и цифры (если Inputmask не подключён)
    var phone = form.querySelector('[data-validate="phone"]');
    if (phone && !window.Inputmask) {
        phone.addEventListener('input', function () {
            phone.value = phone.value.replace(/(?!^\+)\D/g, '').slice(0, 12);
        });
    }

    form.addEventListener('submit', function (e) {
        var valid = true;
        form.querySelectorAll('[data-validate]').forEach(function (input) {
            if (!check(input)) valid = false;
        });
        if (!valid) {
            e.preventDefault();
            var btn = form.querySelector('.form-button');
            btn.querySelector('.form-button__title').textContent = btn.dataset.error;
            setTimeout(function () {
                btn.querySelector('.form-button__title').textContent = 'Оставить заявку';
            }, 2000);
        }
    });
})();
