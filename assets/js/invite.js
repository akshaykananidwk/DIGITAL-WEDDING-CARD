/* =========================================================================
   Public invitation page.
   Countdown, opening animation, music, gallery, share tracking and RSVP.
   All of it degrades gracefully: the invitation is fully readable with
   JavaScript disabled.
   ========================================================================= */
(function () {
    'use strict';

    const reducedMotion = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ------------------------------------------------------------------
    //  Countdown
    // ------------------------------------------------------------------

    function initCountdown() {
        const node = document.querySelector('[data-inv-countdown]');
        if (!node) { return; }

        const target = parseInt(node.getAttribute('data-inv-countdown'), 10) * 1000;
        if (!target || isNaN(target)) { return; }

        const cells = {
            days: node.querySelector('[data-inv-cd="days"]'),
            hours: node.querySelector('[data-inv-cd="hours"]'),
            minutes: node.querySelector('[data-inv-cd="minutes"]'),
            seconds: node.querySelector('[data-inv-cd="seconds"]'),
        };
        const finished = node.querySelector('[data-inv-cd-finished]');

        function pad(value) { return String(value).padStart(2, '0'); }

        function tick() {
            const remaining = target - Date.now();
            if (remaining <= 0) {
                node.classList.add('is-finished');
                if (finished) { finished.hidden = false; }
                Object.keys(cells).forEach(function (key) {
                    if (cells[key]) { cells[key].textContent = '00'; }
                });
                window.clearInterval(timer);
                return;
            }
            const seconds = Math.floor(remaining / 1000);
            if (cells.days) { cells.days.textContent = pad(Math.floor(seconds / 86400)); }
            if (cells.hours) { cells.hours.textContent = pad(Math.floor((seconds % 86400) / 3600)); }
            if (cells.minutes) { cells.minutes.textContent = pad(Math.floor((seconds % 3600) / 60)); }
            if (cells.seconds) { cells.seconds.textContent = pad(seconds % 60); }
        }

        tick();
        const timer = window.setInterval(tick, 1000);
    }

    // ------------------------------------------------------------------
    //  Opening cover / envelope
    // ------------------------------------------------------------------

    function initCover() {
        const cover = document.querySelector('.inv-cover');
        if (!cover) { return; }

        const skipRequested = cover.getAttribute('data-inv-skip') === '1';
        if (skipRequested || reducedMotion) {
            cover.remove();
            document.body.classList.remove('inv-locked');
            return;
        }

        document.body.classList.add('inv-locked');

        function open() {
            const envelope = cover.querySelector('.inv-envelope');
            if (envelope) { envelope.classList.add('is-open'); }
            window.setTimeout(function () {
                cover.classList.add('is-open');
                document.body.classList.remove('inv-locked');
                window.setTimeout(function () { cover.remove(); }, 750);
                // Start the music only after a real user gesture, which is
                // what browsers require anyway.
                const music = document.querySelector('.inv-music');
                if (music && music.getAttribute('data-inv-autoplay') === '1') {
                    music.click();
                }
            }, envelope ? 700 : 100);
        }

        cover.querySelectorAll('[data-inv-open]').forEach(function (trigger) {
            trigger.addEventListener('click', open);
        });
        const skip = cover.querySelector('[data-inv-skip-button]');
        if (skip) {
            skip.addEventListener('click', function () {
                cover.remove();
                document.body.classList.remove('inv-locked');
            });
        }
    }

    // ------------------------------------------------------------------
    //  Music
    // ------------------------------------------------------------------

    function initMusic() {
        const button = document.querySelector('.inv-music');
        const audio = document.getElementById('inv-audio');
        if (!button || !audio) { return; }

        const labelPlay = button.getAttribute('data-label-play') || 'Play music';
        const labelPause = button.getAttribute('data-label-pause') || 'Pause music';

        function sync(playing) {
            button.setAttribute('aria-pressed', playing ? 'true' : 'false');
            button.setAttribute('aria-label', playing ? labelPause : labelPlay);
            button.title = playing ? labelPause : labelPlay;
        }

        button.addEventListener('click', function () {
            if (audio.paused) {
                const attempt = audio.play();
                if (attempt && typeof attempt.catch === 'function') {
                    // Autoplay blocked: leave the button in the paused state
                    // rather than pretending it is playing.
                    attempt.then(function () { sync(true); }).catch(function () { sync(false); });
                } else {
                    sync(true);
                }
            } else {
                audio.pause();
                sync(false);
            }
        });

        audio.addEventListener('ended', function () { sync(false); });
        sync(false);
    }

    // ------------------------------------------------------------------
    //  Multi-page layout
    // ------------------------------------------------------------------

    function initBook() {
        const book = document.querySelector('[data-inv-book]');
        if (!book) { return; }

        const pages = Array.prototype.slice.call(book.querySelectorAll('.inv-book__page'));
        const dots = Array.prototype.slice.call(book.querySelectorAll('.inv-book__dot'));
        const prev = book.querySelector('[data-inv-book-prev]');
        const next = book.querySelector('[data-inv-book-next]');
        let index = 0;

        function show(target) {
            index = Math.max(0, Math.min(pages.length - 1, target));
            pages.forEach(function (page, i) { page.classList.toggle('is-active', i === index); });
            dots.forEach(function (dot, i) { dot.classList.toggle('is-active', i === index); });
            if (prev) { prev.disabled = index === 0; }
            if (next) { next.disabled = index === pages.length - 1; }
            book.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
        }

        if (prev) { prev.addEventListener('click', function () { show(index - 1); }); }
        if (next) { next.addEventListener('click', function () { show(index + 1); }); }
        dots.forEach(function (dot, i) { dot.addEventListener('click', function () { show(i); }); });

        show(0);
    }

    // ------------------------------------------------------------------
    //  Reveal on scroll
    // ------------------------------------------------------------------

    function initReveal() {
        const nodes = document.querySelectorAll('.inv-reveal');
        if (nodes.length === 0) { return; }

        if (reducedMotion || !('IntersectionObserver' in window)) {
            nodes.forEach(function (node) { node.classList.add('is-visible'); });
            return;
        }
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });
        nodes.forEach(function (node) { observer.observe(node); });
    }

    // ------------------------------------------------------------------
    //  Petals (rich motion only)
    // ------------------------------------------------------------------

    function initPetals() {
        const layer = document.querySelector('.inv-petals');
        if (!layer || reducedMotion) { return; }
        const root = document.querySelector('.inv-root');
        if (!root || root.getAttribute('data-motion') !== 'rich') { return; }

        for (let i = 0; i < 14; i++) {
            const petal = document.createElement('span');
            petal.className = 'inv-petal';
            petal.style.left = Math.random() * 100 + '%';
            petal.style.animationDuration = (9 + Math.random() * 9).toFixed(1) + 's';
            petal.style.animationDelay = (Math.random() * 8).toFixed(1) + 's';
            petal.style.transform = 'scale(' + (0.6 + Math.random() * 0.9).toFixed(2) + ')';
            layer.appendChild(petal);
        }
    }

    // ------------------------------------------------------------------
    //  Share
    // ------------------------------------------------------------------

    function initShare() {
        const root = document.querySelector('[data-inv-share-endpoint]');
        const endpoint = root ? root.getAttribute('data-inv-share-endpoint') : '';
        const token = root ? root.getAttribute('data-inv-token') : '';

        function record(channel) {
            if (!endpoint) { return; }
            const body = new FormData();
            body.append('_token', token);
            body.append('channel', channel);
            // keepalive lets the beacon finish even as the page navigates away.
            fetch(endpoint, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                keepalive: true,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': token },
            }).catch(function () { /* analytics must never break sharing */ });
        }

        document.querySelectorAll('[data-inv-share]').forEach(function (button) {
            button.addEventListener('click', function () {
                record(button.getAttribute('data-inv-share'));
            });
        });

        // Native share sheet where available.
        const nativeButton = document.querySelector('[data-inv-native-share]');
        if (nativeButton) {
            if (!navigator.share) {
                nativeButton.hidden = true;
            } else {
                nativeButton.addEventListener('click', async function () {
                    try {
                        await navigator.share({
                            title: nativeButton.getAttribute('data-title') || document.title,
                            text: nativeButton.getAttribute('data-text') || '',
                            url: nativeButton.getAttribute('data-url') || window.location.href,
                        });
                        record('other');
                    } catch (error) { /* the user dismissed the sheet */ }
                });
            }
        }

        // Copy link.
        document.querySelectorAll('[data-inv-copy]').forEach(function (button) {
            button.addEventListener('click', async function () {
                const text = button.getAttribute('data-inv-copy');
                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(text);
                    } else {
                        const field = document.createElement('textarea');
                        field.value = text;
                        field.style.position = 'fixed';
                        field.style.opacity = '0';
                        document.body.appendChild(field);
                        field.select();
                        document.execCommand('copy');
                        field.remove();
                    }
                    const done = button.getAttribute('data-inv-copied') || 'Copied';
                    const original = button.innerHTML;
                    button.innerHTML = done;
                    window.setTimeout(function () { button.innerHTML = original; }, 2000);
                    record('copy');
                } catch (error) { /* nothing we can do; the link is on screen */ }
            });
        });
    }

    // ------------------------------------------------------------------
    //  RSVP
    // ------------------------------------------------------------------

    function initRsvp() {
        const form = document.getElementById('inv-rsvp-form');
        if (!form) { return; }

        const guestsField = form.querySelector('[data-inv-guests]');
        form.querySelectorAll('input[name="response"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (guestsField) { guestsField.hidden = radio.value === 'no'; }
            });
        });

        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            const button = form.querySelector('[type="submit"]');
            const original = button ? button.innerHTML : '';
            if (button) {
                button.disabled = true;
                button.innerHTML = '…';
            }

            let result;
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                result = await response.json();
            } catch (error) {
                result = { success: false, message: 'Network error. Please try again.' };
            }

            if (button) {
                button.disabled = false;
                button.innerHTML = original;
            }

            const notice = document.getElementById('inv-rsvp-notice');
            if (notice) {
                notice.textContent = result.message || '';
                notice.hidden = false;
            }
            if (result.success) {
                form.reset();
                form.hidden = true;
            }
        });
    }

    // ------------------------------------------------------------------
    //  Boot
    // ------------------------------------------------------------------

    document.addEventListener('DOMContentLoaded', function () {
        initCover();
        initCountdown();
        initMusic();
        initBook();
        initReveal();
        initPetals();
        initShare();
        initRsvp();
    });
})();
