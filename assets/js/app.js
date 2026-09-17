/* =========================================================================
   Shubh Kankotri - application JavaScript.
   Vanilla, no framework, no build step. Progressive enhancement only: every
   form works without this file.
   ========================================================================= */
(function () {
    'use strict';

    const SK = window.SK || {};
    window.SK = SK;

    SK.config = Object.assign({
        baseUrl: '',
        csrfToken: '',
        locale: 'en',
    }, window.SK_CONFIG || {});

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    SK.url = function (path) {
        const base = SK.config.baseUrl.replace(/\/$/, '');
        return base + '/' + String(path).replace(/^\//, '');
    };

    /** fetch() wrapper that always sends the CSRF token and parses JSON. */
    SK.request = async function (path, options) {
        options = options || {};
        const headers = Object.assign({
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-CSRF-Token': SK.config.csrfToken,
        }, options.headers || {});

        if (options.json !== undefined) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.json);
            delete options.json;
        }

        let response;
        try {
            response = await fetch(SK.url(path), Object.assign({}, options, {
                headers: headers,
                credentials: 'same-origin',
            }));
        } catch (error) {
            return { success: false, message: 'Network error. Please check your connection.' };
        }

        let payload = {};
        try {
            payload = await response.json();
        } catch (error) {
            payload = { success: response.ok, message: response.ok ? '' : 'Unexpected server response.' };
        }
        payload.status = response.status;
        return payload;
    };

    // ------------------------------------------------------------------
    //  Toasts
    // ------------------------------------------------------------------

    SK.toast = function (message, type, timeout) {
        if (!message) { return; }
        let stack = document.querySelector('.sk-toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'sk-toast-stack';
            stack.setAttribute('role', 'status');
            stack.setAttribute('aria-live', 'polite');
            document.body.appendChild(stack);
        }

        const toast = document.createElement('div');
        toast.className = 'sk-toast sk-toast--' + (type || 'info');
        toast.textContent = message;
        stack.appendChild(toast);

        window.setTimeout(function () {
            toast.style.opacity = '0';
            window.setTimeout(function () { toast.remove(); }, 250);
        }, timeout || 4200);
    };

    // ------------------------------------------------------------------
    //  Clipboard
    // ------------------------------------------------------------------

    SK.copy = async function (text, successMessage) {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
            } else {
                // Fallback for http or older browsers.
                const field = document.createElement('textarea');
                field.value = text;
                field.setAttribute('readonly', '');
                field.style.position = 'fixed';
                field.style.opacity = '0';
                document.body.appendChild(field);
                field.select();
                document.execCommand('copy');
                field.remove();
            }
            SK.toast(successMessage || 'Copied', 'success', 2200);
            return true;
        } catch (error) {
            SK.toast('Could not copy. Please select the text and copy manually.', 'warning');
            return false;
        }
    };

    // ------------------------------------------------------------------
    //  Confirmation dialogs
    // ------------------------------------------------------------------

    /** data-sk-confirm="Are you sure?" on any form or link. */
    function bindConfirmations() {
        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-sk-confirm]');
            if (!trigger) { return; }
            const message = trigger.getAttribute('data-sk-confirm');
            if (!window.confirm(message)) {
                event.preventDefault();
                event.stopPropagation();
            }
        });

        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!form.hasAttribute('data-sk-confirm-form')) { return; }
            if (!window.confirm(form.getAttribute('data-sk-confirm-form'))) {
                event.preventDefault();
            }
        });
    }

    // ------------------------------------------------------------------
    //  Method spoofing for DELETE links
    // ------------------------------------------------------------------

    function bindMethodLinks() {
        document.addEventListener('click', function (event) {
            const link = event.target.closest('[data-sk-method]');
            if (!link) { return; }
            event.preventDefault();

            const message = link.getAttribute('data-sk-confirm');
            if (message && !window.confirm(message)) { return; }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = link.getAttribute('href') || link.getAttribute('data-sk-action');
            form.style.display = 'none';

            const method = document.createElement('input');
            method.type = 'hidden';
            method.name = '_method';
            method.value = link.getAttribute('data-sk-method').toUpperCase();
            form.appendChild(method);

            const token = document.createElement('input');
            token.type = 'hidden';
            token.name = '_token';
            token.value = SK.config.csrfToken;
            form.appendChild(token);

            document.body.appendChild(form);
            form.submit();
        });
    }

    // ------------------------------------------------------------------
    //  Copy buttons
    // ------------------------------------------------------------------

    function bindCopyButtons() {
        document.addEventListener('click', function (event) {
            const button = event.target.closest('[data-sk-copy]');
            if (!button) { return; }
            event.preventDefault();

            const selector = button.getAttribute('data-sk-copy');
            let text = selector;
            if (selector && selector.charAt(0) === '#') {
                const source = document.querySelector(selector);
                if (source) { text = source.value !== undefined ? source.value : source.textContent; }
            }
            SK.copy(text, button.getAttribute('data-sk-copy-message') || 'Link copied');
        });
    }

    // ------------------------------------------------------------------
    //  Submit buttons: prevent a double post
    // ------------------------------------------------------------------

    function bindSubmitGuards() {
        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (form.hasAttribute('data-sk-no-guard') || !(form instanceof HTMLFormElement)) { return; }
            const button = form.querySelector('[type="submit"]');
            if (!button || button.disabled) { return; }

            // Let native validation run first.
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) { return; }

            const original = button.innerHTML;
            window.setTimeout(function () {
                button.disabled = true;
                button.dataset.skOriginal = original;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>'
                    + (button.getAttribute('data-sk-loading') || 'Working…');
            }, 0);

            // If the page does not navigate (a validation error rendered
            // inline), restore the button so the form stays usable.
            window.setTimeout(function () {
                if (document.body.contains(button) && button.disabled) {
                    button.disabled = false;
                    button.innerHTML = button.dataset.skOriginal || original;
                }
            }, 12000);
        });
    }

    // ------------------------------------------------------------------
    //  Auto-submitting filter controls
    // ------------------------------------------------------------------

    function bindAutoFilters() {
        document.querySelectorAll('[data-sk-auto-submit]').forEach(function (control) {
            control.addEventListener('change', function () {
                const form = control.closest('form');
                if (form) { form.submit(); }
            });
        });

        // Debounced search inputs.
        document.querySelectorAll('[data-sk-search]').forEach(function (input) {
            let timer = null;
            input.addEventListener('input', function () {
                window.clearTimeout(timer);
                timer = window.setTimeout(function () {
                    const form = input.closest('form');
                    if (form) { form.submit(); }
                }, 550);
            });
        });
    }

    // ------------------------------------------------------------------
    //  Admin sidebar
    // ------------------------------------------------------------------

    function bindAdminSidebar() {
        const sidebar = document.querySelector('.sk-admin__sidebar');
        const overlay = document.querySelector('.sk-admin__overlay');
        const toggle = document.querySelector('[data-sk-sidebar-toggle]');
        if (!sidebar || !toggle) { return; }

        function close() {
            sidebar.classList.remove('is-open');
            if (overlay) { overlay.classList.remove('is-open'); }
            toggle.setAttribute('aria-expanded', 'false');
        }

        toggle.addEventListener('click', function () {
            const open = sidebar.classList.toggle('is-open');
            if (overlay) { overlay.classList.toggle('is-open', open); }
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        if (overlay) { overlay.addEventListener('click', close); }
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { close(); }
        });
    }

    // ------------------------------------------------------------------
    //  Lazy images
    // ------------------------------------------------------------------

    function bindLazyImages() {
        const images = document.querySelectorAll('img[data-src]');
        if (images.length === 0) { return; }

        if (!('IntersectionObserver' in window)) {
            images.forEach(function (img) { img.src = img.dataset.src; });
            return;
        }
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) { return; }
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                observer.unobserve(img);
            });
        }, { rootMargin: '200px' });

        images.forEach(function (img) { observer.observe(img); });
    }

    // ------------------------------------------------------------------
    //  Service worker
    // ------------------------------------------------------------------

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator) || !SK.config.serviceWorker) { return; }
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(SK.config.serviceWorker, { scope: SK.config.baseUrl + '/' })
                .catch(function () { /* offline support is a bonus, never a requirement */ });
        });
    }

    // ------------------------------------------------------------------
    //  Boot
    // ------------------------------------------------------------------

    document.addEventListener('DOMContentLoaded', function () {
        bindConfirmations();
        bindMethodLinks();
        bindCopyButtons();
        bindSubmitGuards();
        bindAutoFilters();
        bindAdminSidebar();
        bindLazyImages();
        registerServiceWorker();

        // Server-rendered flash messages become toasts.
        document.querySelectorAll('[data-sk-flash]').forEach(function (node) {
            SK.toast(node.textContent.trim(), node.getAttribute('data-sk-flash'));
            node.remove();
        });
    });
})();
