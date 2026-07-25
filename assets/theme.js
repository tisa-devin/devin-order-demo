(function () {
    'use strict';

    var STORAGE_KEY = 'app-theme';
    var DEFAULT_THEME = 'blue';

    function currentTheme() {
        try {
            return localStorage.getItem(STORAGE_KEY) || DEFAULT_THEME;
        } catch (e) {
            return DEFAULT_THEME;
        }
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.querySelectorAll('[data-theme-option]').forEach(function (item) {
            item.classList.toggle('active', item.dataset.themeOption === theme);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        applyTheme(currentTheme());

        document.querySelectorAll('[data-theme-option]').forEach(function (item) {
            item.addEventListener('click', function (event) {
                event.preventDefault();
                var theme = item.dataset.themeOption;
                try {
                    localStorage.setItem(STORAGE_KEY, theme);
                } catch (e) {
                    /* ストレージが使えない場合は現在のページのみに適用する */
                }
                applyTheme(theme);
            });
        });
    });
})();
