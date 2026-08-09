/* Theme toggle.
 *
 * The theme itself is applied by an inline script in the document head, before
 * any stylesheet loads, so the page cannot paint in the wrong theme and then
 * correct itself. This file only handles the click and the persistence.
 */
(function () {
    'use strict';

    var root   = document.documentElement;
    var button = document.getElementById('theme-toggle');
    if (!button) {
        return;
    }

    button.addEventListener('click', function () {
        var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-bs-theme', next);
        try {
            localStorage.setItem('theme', next);
        } catch (e) {
            /* Private browsing with storage disabled: the toggle still works
               for this page view, it just will not be remembered. */
        }
    });

    // Follow the OS if the user has never made an explicit choice here.
    var media = window.matchMedia('(prefers-color-scheme: dark)');
    var onChange = function (event) {
        try {
            if (localStorage.getItem('theme')) {
                return;
            }
        } catch (e) {
            return;
        }
        root.setAttribute('data-bs-theme', event.matches ? 'dark' : 'light');
    };

    if (typeof media.addEventListener === 'function') {
        media.addEventListener('change', onChange);
    } else if (typeof media.addListener === 'function') {
        media.addListener(onChange);
    }
})();
