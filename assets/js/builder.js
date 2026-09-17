/* =========================================================================
   The invitation builder: live preview, photo upload and reorder, AI helper.
   Everything here is an enhancement - the forms post normally without it.
   ========================================================================= */
(function () {
    'use strict';

    const SK = window.SK || (window.SK = {});

    SK.builder = {
        init: function (options) {
            this.options = Object.assign({
                invitationId: 0,
                previewUrl: '',
                saveUrl: '',
                aiEnabled: false,
            }, options || {});

            this.form = document.getElementById('sk-content-form');
            this.frame = document.getElementById('sk-preview-frame');
            this.statusNode = document.getElementById('sk-save-status');

            this.bindLivePreview();
            this.bindAutoSave();
            this.bindPhotoUpload();
            this.bindPhotoReorder();
            this.bindDesignControls();
            this.bindAiButtons();
            this.bindSlugField();
            this.bindPublish();
        },

        status: function (message, type) {
            if (!this.statusNode) { return; }
            this.statusNode.textContent = message;
            this.statusNode.className = 'small ' + (type === 'error' ? 'text-danger' : 'text-muted');
        },

        // --------------------------------------------------------------
        //  Live preview
        // --------------------------------------------------------------

        collectValues: function () {
            const values = {};
            if (!this.form) { return values; }
            this.form.querySelectorAll('[data-sk-field]').forEach(function (input) {
                const key = input.getAttribute('data-sk-field');
                if (input.type === 'checkbox') {
                    values[key] = input.checked ? '1' : '0';
                } else {
                    values[key] = input.value;
                }
            });
            return values;
        },

        collectTheme: function () {
            const theme = {};
            document.querySelectorAll('[data-sk-theme]').forEach(function (input) {
                const key = input.getAttribute('data-sk-theme');
                if (input.type === 'radio' && !input.checked) { return; }
                if (input.value !== '') { theme[key] = input.value; }
            });
            return theme;
        },

        /**
         * Re-render the preview.
         *
         * The iframe is refreshed by POSTing the unsaved values, so the
         * preview is produced by the same server-side engine that renders the
         * real invitation - there is no second, drifting client renderer.
         */
        refreshPreview: function () {
            if (!this.frame || !this.options.previewUrl) { return; }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = this.options.previewUrl;
            form.target = this.frame.getAttribute('name');
            form.style.display = 'none';

            const append = function (name, value) {
                const field = document.createElement('input');
                field.type = 'hidden';
                field.name = name;
                field.value = value;
                form.appendChild(field);
            };

            append('_token', SK.config.csrfToken);
            const values = this.collectValues();
            Object.keys(values).forEach(function (key) { append('values[' + key + ']', values[key]); });
            const theme = this.collectTheme();
            Object.keys(theme).forEach(function (key) { append('theme[' + key + ']', theme[key]); });

            document.body.appendChild(form);
            form.submit();
            form.remove();
        },

        bindLivePreview: function () {
            const self = this;
            let timer = null;

            const schedule = function () {
                window.clearTimeout(timer);
                timer = window.setTimeout(function () { self.refreshPreview(); }, 700);
            };

            if (this.form) {
                this.form.addEventListener('input', schedule);
                this.form.addEventListener('change', schedule);
            }
            document.querySelectorAll('[data-sk-theme]').forEach(function (input) {
                input.addEventListener('change', schedule);
                input.addEventListener('input', schedule);
            });

            const refreshButton = document.querySelector('[data-sk-refresh-preview]');
            if (refreshButton) {
                refreshButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    self.refreshPreview();
                });
            }
        },

        // --------------------------------------------------------------
        //  Auto-save
        // --------------------------------------------------------------

        bindAutoSave: function () {
            if (!this.form || !this.options.saveUrl) { return; }
            const self = this;
            let timer = null;
            let dirty = false;

            const save = async function () {
                if (!dirty) { return; }
                dirty = false;
                self.status('Saving…');

                const body = new FormData();
                body.append('_token', SK.config.csrfToken);
                const values = self.collectValues();
                Object.keys(values).forEach(function (key) { body.append('fields[' + key + ']', values[key]); });

                const result = await SK.request(self.options.saveUrl, { method: 'POST', body: body });
                if (result.success) {
                    self.status('All changes saved');
                } else {
                    dirty = true;
                    self.status(result.message || 'Could not save', 'error');
                    self.showFieldErrors(result.errors || {});
                }
            };

            this.form.addEventListener('input', function () {
                dirty = true;
                self.status('Unsaved changes');
                window.clearTimeout(timer);
                timer = window.setTimeout(save, 2200);
            });

            // Save before the tab closes, so nothing is lost.
            window.addEventListener('beforeunload', function (event) {
                if (dirty) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });

            const saveButton = document.querySelector('[data-sk-save-now]');
            if (saveButton) {
                saveButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    dirty = true;
                    save();
                });
            }
        },

        showFieldErrors: function (errors) {
            if (!this.form) { return; }
            this.form.querySelectorAll('.is-invalid').forEach(function (node) {
                node.classList.remove('is-invalid');
            });
            this.form.querySelectorAll('[data-sk-error-for]').forEach(function (node) { node.textContent = ''; });

            Object.keys(errors).forEach(function (key) {
                const field = document.querySelector('[data-sk-field="' + key + '"]');
                if (field) { field.classList.add('is-invalid'); }
                const target = document.querySelector('[data-sk-error-for="' + key + '"]');
                if (target) {
                    target.textContent = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
                }
            });
        },

        // --------------------------------------------------------------
        //  Photos
        // --------------------------------------------------------------

        bindPhotoUpload: function () {
            const zone = document.getElementById('sk-dropzone');
            const input = document.getElementById('sk-photo-input');
            if (!zone || !input) { return; }
            const self = this;

            const upload = async function (files, role) {
                if (!files || files.length === 0) { return; }
                const body = new FormData();
                body.append('_token', SK.config.csrfToken);
                body.append('role', role || 'gallery');
                for (let i = 0; i < files.length; i++) { body.append('photos[]', files[i]); }

                zone.classList.add('is-over');
                SK.toast('Uploading ' + files.length + ' photo(s)…', 'info', 2500);

                const result = await SK.request('builder/' + self.options.invitationId + '/photos', {
                    method: 'POST',
                    body: body,
                });
                zone.classList.remove('is-over');

                if (!result.success) {
                    SK.toast(result.message || 'Upload failed', 'danger');
                    return;
                }
                (result.data && result.data.photos ? result.data.photos : []).forEach(function (photo) {
                    self.appendPhoto(photo);
                });
                if (result.data && result.data.errors && result.data.errors.length) {
                    SK.toast(result.data.errors[0], 'warning');
                } else {
                    SK.toast(result.message, 'success');
                }
                self.refreshPreview();
            };

            input.addEventListener('change', function () {
                upload(input.files, input.getAttribute('data-sk-role'));
                input.value = '';
            });

            ['dragenter', 'dragover'].forEach(function (name) {
                zone.addEventListener(name, function (event) {
                    event.preventDefault();
                    zone.classList.add('is-over');
                });
            });
            ['dragleave', 'drop'].forEach(function (name) {
                zone.addEventListener(name, function (event) {
                    event.preventDefault();
                    zone.classList.remove('is-over');
                });
            });
            zone.addEventListener('drop', function (event) {
                if (event.dataTransfer && event.dataTransfer.files) {
                    upload(event.dataTransfer.files, 'gallery');
                }
            });

            // Hero photo has its own input.
            const heroInput = document.getElementById('sk-hero-input');
            if (heroInput) {
                heroInput.addEventListener('change', function () {
                    upload(heroInput.files, 'hero');
                    heroInput.value = '';
                });
            }

            // Delete buttons, including on newly added tiles.
            document.addEventListener('click', async function (event) {
                const button = event.target.closest('[data-sk-remove-photo]');
                if (!button) { return; }
                event.preventDefault();
                const id = button.getAttribute('data-sk-remove-photo');

                const result = await SK.request('builder/' + self.options.invitationId + '/photos/' + id, {
                    method: 'POST',
                    body: (function () {
                        const body = new FormData();
                        body.append('_token', SK.config.csrfToken);
                        body.append('_method', 'DELETE');
                        return body;
                    })(),
                });
                if (result.success) {
                    const tile = button.closest('.sk-photo');
                    if (tile) { tile.remove(); }
                    SK.toast('Photo removed', 'success');
                    self.refreshPreview();
                } else {
                    SK.toast(result.message || 'Could not remove that photo', 'danger');
                }
            });
        },

        appendPhoto: function (photo) {
            const grid = document.getElementById(photo.role === 'hero' ? 'sk-hero-grid' : 'sk-photo-grid');
            if (!grid) { return; }
            if (photo.role === 'hero') { grid.innerHTML = ''; }

            const tile = document.createElement('div');
            tile.className = 'sk-photo';
            tile.setAttribute('data-id', photo.id);

            const img = document.createElement('img');
            img.src = photo.thumb;
            img.alt = '';
            img.loading = 'lazy';
            tile.appendChild(img);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'sk-photo__remove';
            remove.setAttribute('data-sk-remove-photo', photo.id);
            remove.setAttribute('aria-label', 'Remove photo');
            remove.innerHTML = '&times;';
            tile.appendChild(remove);

            grid.appendChild(tile);
        },

        bindPhotoReorder: function () {
            const grid = document.getElementById('sk-photo-grid');
            if (!grid || typeof window.Sortable === 'undefined') { return; }
            const self = this;

            window.Sortable.create(grid, {
                animation: 150,
                onEnd: async function () {
                    const order = Array.prototype.map.call(
                        grid.querySelectorAll('.sk-photo'),
                        function (tile) { return tile.getAttribute('data-id'); }
                    );
                    const body = new FormData();
                    body.append('_token', SK.config.csrfToken);
                    order.forEach(function (id) { body.append('order[]', id); });

                    const result = await SK.request('builder/' + self.options.invitationId + '/photos/reorder', {
                        method: 'POST',
                        body: body,
                    });
                    if (result.success) {
                        SK.toast('Order saved', 'success', 1800);
                        self.refreshPreview();
                    }
                },
            });
        },

        // --------------------------------------------------------------
        //  Design controls
        // --------------------------------------------------------------

        bindDesignControls: function () {
            const self = this;

            document.querySelectorAll('[data-sk-palette]').forEach(function (swatch) {
                swatch.addEventListener('click', function () {
                    const tokens = JSON.parse(swatch.getAttribute('data-sk-palette'));
                    Object.keys(tokens).forEach(function (key) {
                        const input = document.querySelector('[data-sk-theme="' + key + '"]');
                        if (input) { input.value = tokens[key]; }
                    });
                    document.querySelectorAll('[data-sk-palette]').forEach(function (other) {
                        other.classList.remove('active');
                    });
                    swatch.classList.add('active');
                    self.refreshPreview();
                });
            });

            // Colour inputs mirror their text field so both stay in step.
            document.querySelectorAll('[data-sk-color-sync]').forEach(function (picker) {
                const target = document.querySelector(picker.getAttribute('data-sk-color-sync'));
                if (!target) { return; }
                picker.addEventListener('input', function () {
                    target.value = picker.value.toUpperCase();
                    target.dispatchEvent(new Event('change', { bubbles: true }));
                });
                target.addEventListener('change', function () {
                    if (/^#[0-9a-fA-F]{6}$/.test(target.value)) { picker.value = target.value; }
                });
            });
        },

        // --------------------------------------------------------------
        //  AI helper
        // --------------------------------------------------------------

        bindAiButtons: function () {
            if (!this.options.aiEnabled) { return; }
            const self = this;

            document.querySelectorAll('[data-sk-ai]').forEach(function (button) {
                button.addEventListener('click', async function (event) {
                    event.preventDefault();

                    const action = button.getAttribute('data-sk-ai');
                    const targetSelector = button.getAttribute('data-sk-ai-target');
                    const target = targetSelector ? document.querySelector(targetSelector) : null;

                    const original = button.innerHTML;
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>';

                    const facts = {};
                    document.querySelectorAll('[data-sk-field]').forEach(function (input) {
                        const key = input.getAttribute('data-sk-field');
                        if (input.value) { facts[key] = input.value; }
                    });
                    // Normalise the date/venue keys the API expects.
                    facts.event_date = facts.wedding_date || facts.event_date || facts.opening_date || '';
                    facts.event_time = facts.wedding_time || facts.event_time || facts.opening_time || '';

                    const result = await SK.request('api/v1/ai/' + action, {
                        method: 'POST',
                        json: {
                            _token: SK.config.csrfToken,
                            facts: facts,
                            locale: button.getAttribute('data-sk-ai-locale') || SK.config.locale,
                            tone: (document.getElementById('sk-ai-tone') || {}).value || 'traditional',
                        },
                    });

                    button.disabled = false;
                    button.innerHTML = original;

                    if (!result.success) {
                        SK.toast(result.message || 'The AI helper is unavailable.', 'warning', 5000);
                        return;
                    }
                    if (target && result.data && result.data.text) {
                        target.value = result.data.text;
                        target.dispatchEvent(new Event('input', { bubbles: true }));
                        SK.toast('Wording added - edit it as you like.', 'success');
                        self.refreshPreview();
                    }
                });
            });
        },

        // --------------------------------------------------------------
        //  Link editing
        // --------------------------------------------------------------

        bindSlugField: function () {
            const form = document.getElementById('sk-slug-form');
            if (!form) { return; }
            const preview = document.getElementById('sk-slug-preview');
            const input = form.querySelector('[name="slug"]');
            if (input && preview) {
                const base = preview.getAttribute('data-base') || '';
                input.addEventListener('input', function () {
                    const clean = input.value.toLowerCase().replace(/[^a-z0-9\-]+/g, '-').replace(/^-+|-+$/g, '');
                    preview.textContent = base + clean;
                });
            }
        },

        // --------------------------------------------------------------
        //  Publish
        // --------------------------------------------------------------

        bindPublish: function () {
            const button = document.querySelector('[data-sk-publish]');
            if (!button) { return; }
            const self = this;

            button.addEventListener('click', async function (event) {
                event.preventDefault();
                const original = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Publishing…';

                const body = new FormData();
                body.append('_token', SK.config.csrfToken);

                const result = await SK.request('builder/' + self.options.invitationId + '/publish', {
                    method: 'POST',
                    body: body,
                });

                if (result.success && result.data && result.data.redirect) {
                    window.location.href = result.data.redirect;
                    return;
                }
                button.disabled = false;
                button.innerHTML = original;
                SK.toast(result.message || 'Could not publish', 'warning', 6000);
            });
        },
    };
})();
