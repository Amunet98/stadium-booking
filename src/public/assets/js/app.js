/* Interface behaviour: scroll reveals, the fixture countdown, the navbar's
 * scrolled state, and the password toggles.
 *
 * Vanilla and self-hosted, like Bootstrap next to it. Nothing here is required
 * for the page to work — every element it touches is readable, bookable and
 * navigable with scripting off. That is the constraint the reveal animation is
 * written around, below.
 */
(function () {
    'use strict';

    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

    /* -------------------------------------------------------- scroll reveal
     *
     * [data-reveal] elements start at opacity 0 in the stylesheet, so if this
     * never runs the page is blank. Two guards against that: <html> carries
     * `no-js` until the inline head script removes it (which neutralises the
     * rule), and anything still unrevealed when the observer is unavailable is
     * shown immediately here.
     */
    function reveal() {
        var items = document.querySelectorAll('[data-reveal]');
        if (!items.length) {
            return;
        }

        // Reduced motion, or a browser without IntersectionObserver: show
        // everything at once rather than animating or, worse, hiding it.
        if (reduced.matches || !('IntersectionObserver' in window)) {
            items.forEach(function (el) { el.classList.add('is-in'); });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                entry.target.classList.add('is-in');
                // One-shot: re-animating on the way back up is noise, and it
                // makes a scroll-up feel like the page is reloading.
                observer.unobserve(entry.target);
            });
        }, { rootMargin: '0px 0px -24px 0px', threshold: 0 });

        items.forEach(function (el, index) {
            // Stagger within a group. The index resets per container so a
            // six-card grid does not end with a third-of-a-second delay.
            var siblings = el.parentElement
                ? Array.prototype.indexOf.call(el.parentElement.children, el)
                : index;
            el.style.setProperty('--i', String(Math.min(siblings, 5)));
            observer.observe(el);
        });

        // Failsafe for the end of the document.
        //
        // The bottom rootMargin shrinks the observation area, so an element
        // that only ever comes to rest inside that shrunken strip — the last
        // section above the footer, the final row of a grid on a narrow screen
        // — never reports as intersecting, and stays at opacity 0 with no way
        // for the reader to recover it. Once the page is scrolled to the
        // bottom there is nothing left to wait for, so reveal whatever is
        // still hidden.
        var atBottom = function () {
            if (window.innerHeight + window.scrollY < document.body.scrollHeight - 4) {
                return;
            }
            items.forEach(function (el) {
                el.classList.add('is-in');
                observer.unobserve(el);
            });
            window.removeEventListener('scroll', atBottom);
        };

        window.addEventListener('scroll', atBottom, { passive: true });
        atBottom();
    }

    /* ------------------------------------------------------ navbar elevation */
    function navbar() {
        var nav = document.getElementById('app-nav');
        if (!nav) {
            return;
        }

        var ticking = false;
        var apply = function () {
            nav.classList.toggle('is-scrolled', window.scrollY > 24);
            ticking = false;
        };

        window.addEventListener('scroll', function () {
            // Coalesce to one class write per frame; scroll fires far more
            // often than the screen refreshes.
            if (!ticking) {
                ticking = true;
                window.requestAnimationFrame(apply);
            }
        }, { passive: true });

        apply();
    }

    /* ------------------------------------------------------------- countdown
     *
     * The target is carried in a <time datetime> so the date is still readable
     * — and machine-readable — with scripting off.
     */
    function countdown() {
        var root = document.querySelector('[data-countdown]');
        if (!root) {
            return;
        }

        var target = Date.parse(root.getAttribute('data-countdown'));
        if (isNaN(target)) {
            return;
        }

        var fields = {
            days:    root.querySelector('[data-cd="days"]'),
            hours:   root.querySelector('[data-cd="hours"]'),
            minutes: root.querySelector('[data-cd="minutes"]'),
            seconds: root.querySelector('[data-cd="seconds"]')
        };

        var pad = function (n) { return n < 10 ? '0' + n : String(n); };

        var tick = function () {
            var left = Math.max(0, target - Date.now());
            var secs = Math.floor(left / 1000);

            if (fields.days)    { fields.days.textContent    = String(Math.floor(secs / 86400)); }
            if (fields.hours)   { fields.hours.textContent   = pad(Math.floor(secs / 3600) % 24); }
            if (fields.minutes) { fields.minutes.textContent = pad(Math.floor(secs / 60) % 60); }
            if (fields.seconds) { fields.seconds.textContent = pad(secs % 60); }

            if (left === 0 && timer) {
                window.clearInterval(timer);
            }
        };

        var timer = window.setInterval(tick, 1000);
        tick();
    }

    /* ------------------------------------------------------ password reveal */
    function passwords() {
        document.querySelectorAll('.app-password button').forEach(function (button) {
            button.addEventListener('click', function () {
                var wrap  = button.closest('.app-password');
                var input = wrap.querySelector('input');
                var shown = input.type === 'text';

                input.type = shown ? 'password' : 'text';
                wrap.classList.toggle('is-shown', !shown);
                button.setAttribute('aria-label', shown ? 'Show password' : 'Hide password');
                button.setAttribute('aria-pressed', String(!shown));
            });
        });
    }

    /* ------------------------------------------------ filter auto-submit
     *
     * The fixtures filters are a plain GET form with a submit button, so they
     * work without this. With scripting on, changing a select applies straight
     * away and the button becomes redundant.
     */
    function filters() {
        var form = document.querySelector('[data-autosubmit]');
        if (!form) {
            return;
        }

        form.querySelectorAll('select, input[type="checkbox"]').forEach(function (field) {
            field.addEventListener('change', function () { form.submit(); });
        });

        var button = form.querySelector('[data-autosubmit-fallback]');
        if (button) {
            button.hidden = true;
        }
    }

    function init() {
        reveal();
        navbar();
        countdown();
        passwords();
        filters();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
