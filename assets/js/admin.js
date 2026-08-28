(function () {
    function getComposerModalNodes() {
        var nodes = [];
        try {
            nodes = Array.prototype.slice.call(document.querySelectorAll('#cbia-ai-composer-modal, .cbia-composer-modal'));
        } catch (e) {
            nodes = [];
        }
        return nodes.filter(function (node, index, arr) {
            return !!node && arr.indexOf(node) === index;
        });
    }

    function isComposerModalOpen(modal) {
        if (modal) {
            if (modal.style.display && modal.style.display !== 'none') return true;
            try {
                return window.getComputedStyle(modal).display !== 'none';
            } catch (e) {
                return false;
            }
        }
        return getComposerModalNodes().some(function (node) {
            if (node.style.display && node.style.display !== 'none') return true;
            try {
                return window.getComputedStyle(node).display !== 'none';
            } catch (e2) {
                return false;
            }
        });
    }

    function closeComposerModalFallback() {
        var modals = getComposerModalNodes();
        if (!modals.length) return false;
        modals.forEach(function (modal) {
            modal.style.display = 'none';
        });
        try {
            document.querySelectorAll('#cbia-ai-composer').forEach(function (root) {
                var livesInsideModal = modals.some(function (modal) { return modal.contains(root); });
                if (!livesInsideModal) {
                    root.style.display = 'none';
                }
            });
        } catch (e) {}
        document.body.classList.remove('cbia-modal-open');
        return true;
    }

    function initGlobalComposerModalFallback() {
        var rootFlag = document.documentElement;
        if (!rootFlag || rootFlag.getAttribute('data-cbia-composer-global-close') === '1') return;
        rootFlag.setAttribute('data-cbia-composer-global-close', '1');

        function maybeCloseComposer(e) {
            var target = e.target;
            var element = target && target.nodeType === 1 ? target : (target && target.parentElement ? target.parentElement : null);
            if (!element || !element.closest) return;

            var closeBtn = element.closest('#cbia-ai-composer-close, .cbia-composer-modal .cbia-modal-close');
            if (closeBtn) {
                e.preventDefault();
                e.stopPropagation();
                closeComposerModalFallback();
                return;
            }

            var modal = element.closest ? element.closest('#cbia-ai-composer-modal, .cbia-composer-modal') : null;
            if (modal && element === modal) {
                e.preventDefault();
                e.stopPropagation();
            }
        }

        function maybeCloseComposerByKey(e) {
            if (!e) return;
            var key = typeof e.key !== 'undefined' ? e.key : '';
            var keyCode = typeof e.keyCode !== 'undefined' ? e.keyCode : 0;
            if (key !== 'Escape' && key !== 'Esc' && keyCode !== 27) return;
            if (!isComposerModalOpen()) return;
            e.preventDefault();
            e.stopPropagation();
            closeComposerModalFallback();
        }

        document.addEventListener('click', maybeCloseComposer, true);
        document.addEventListener('pointerdown', maybeCloseComposer, true);
        document.addEventListener('mousedown', maybeCloseComposer, true);
        document.addEventListener('keydown', maybeCloseComposerByKey, true);
        window.addEventListener('keyup', maybeCloseComposerByKey, true);
        window.addEventListener('keydown', maybeCloseComposerByKey, true);
    }

    function initGlobalComposerActionFallback() {
        var rootFlag = document.documentElement;
        if (!rootFlag || rootFlag.getAttribute('data-cbia-composer-global-actions') === '1') return;
        rootFlag.setAttribute('data-cbia-composer-global-actions', '1');

        document.addEventListener('click', function (e) {
            var target = e.target && e.target.closest
                ? e.target.closest('#cbia-ai-generate, #cbia-ai-improve-text, #cbia-ai-insert, #cbia-ai-complete-missing')
                : null;
            if (!target) return;
            if (target.disabled) return;

            var inComposer = target.closest
                ? target.closest('#cbia-ai-composer, #cbia-ai-composer-modal, .cbia-composer-modal')
                : null;
            if (!inComposer) return;

            if (target.id === 'cbia-ai-generate' && typeof window.CBIA_AI_COMPOSER_RUN === 'function') {
                e.preventDefault();
                e.stopPropagation();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                window.CBIA_AI_COMPOSER_RUN();
                return;
            }
            if (target.id === 'cbia-ai-improve-text' && typeof window.CBIA_AI_COMPOSER_IMPROVE === 'function') {
                e.preventDefault();
                e.stopPropagation();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                window.CBIA_AI_COMPOSER_IMPROVE();
                return;
            }
            if (target.id === 'cbia-ai-insert' && typeof window.CBIA_AI_COMPOSER_INSERT === 'function') {
                e.preventDefault();
                e.stopPropagation();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                window.CBIA_AI_COMPOSER_INSERT();
                return;
            }
            if (target.id === 'cbia-ai-complete-missing' && typeof window.CBIA_AI_COMPOSER_COMPLETE_MISSING === 'function') {
                e.preventDefault();
                e.stopPropagation();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                window.CBIA_AI_COMPOSER_COMPLETE_MISSING();
            }
        }, true);
    }

    function initProUpgradeLinkFallback() {
        var rootFlag = document.documentElement;
        if (!rootFlag || rootFlag.getAttribute('data-cbia-pro-upgrade-links') === '1') return;
        rootFlag.setAttribute('data-cbia-pro-upgrade-links', '1');

        var links = [];
        try {
            links = Array.prototype.slice.call(document.querySelectorAll('a.cbia-pro-upgrade-link'));
        } catch (e) {
            links = [];
        }
        links.forEach(function (link) {
            var href = String(link.getAttribute('href') || '').trim();
            if (!href) return;
            link.setAttribute('target', '_blank');
            link.setAttribute('rel', 'noopener noreferrer');
        });
    }

    initGlobalComposerModalFallback();
    initGlobalComposerActionFallback();
    initProUpgradeLinkFallback();

    function safeInit(fn) {
        try { fn(); } catch (e) {
            if (window.console && console.error) console.error('CBIA init error:', e);
        }
    }
    function addButton() {
        var adminData = window.CBIAAdmin;
        if (!adminData || !adminData.addPostButton || !adminData.addPostButton.enabled) return;
        var target = document.querySelector('.wrap .page-title-action');
        if (!target || document.querySelector('.cbia-add-ai')) return;

        var a = document.createElement('a');
        a.className = 'page-title-action cbia-add-ai';
        a.href = adminData.addPostButton.url;
        a.textContent = adminData.addPostButton.label || 'Add AI Post';
        target.insertAdjacentElement('afterend', a);
    }

    function initAiComposerLauncher() {
        // Launcher placement is handled in initAiComposer().
    }

    function initPromptEditor() {
        var modal = document.getElementById('cbia-prompt-modal');
        var adminData = window.CBIAAdmin;
        if (!modal || !adminData) return;
        // NOTE: Keep modal outside table flow so it is not visually tied to featured image.
        if (modal.parentNode !== document.body) {
            document.body.appendChild(modal);
        }

        var textarea = document.getElementById('cbia-prompt-text');
        var status = document.getElementById('cbia-prompt-status');
        var title = document.getElementById('cbia-prompt-title');
        var btnSave = document.getElementById('cbia-prompt-save');
        var btnClose = modal.querySelector('.cbia-modal-close');

        var current = { postId: 0, type: '', idx: 0 };
        var currentTrigger = null;

        function setStatus(msg, isOk) {
            if (!status) return;
            status.textContent = msg || '';
            status.className = 'cbia-modal-status' + (isOk ? ' is-ok' : ' is-error');
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        function flashSavedTrigger() {
            if (!currentTrigger) return;
            var original = currentTrigger.getAttribute('data-cbia-original-label') || currentTrigger.textContent || '';
            if (!currentTrigger.getAttribute('data-cbia-original-label')) {
                currentTrigger.setAttribute('data-cbia-original-label', original);
            }
            currentTrigger.textContent = 'Saved';
            currentTrigger.disabled = true;
            currentTrigger.classList.add('is-saved');
            window.setTimeout(function () {
                if (!currentTrigger) return;
                currentTrigger.textContent = original;
                currentTrigger.disabled = false;
                currentTrigger.classList.remove('is-saved');
            }, 1200);
        }

        function openModal(type, idx, trigger) {
            current.postId = 0;
            current.type = type;
            current.idx = parseInt(idx || '0', 10) || 0;
            currentTrigger = trigger || null;
            if (title) {
                var label = type === 'featured' ? 'Featured' : ('Internal ' + current.idx);
                title.textContent = 'Edit Base Prompt - ' + label;
            }
            if (textarea) textarea.value = '';
            setStatus('Loading prompt...', true);
            modal.style.display = 'flex';

            var params = new URLSearchParams();
            params.append('action', 'cbia_get_img_prompt');
            params.append('_ajax_nonce', adminData.nonce);
            params.append('post_id', 0);
            params.append('type', type);
            params.append('idx', idx);

            fetch(adminData.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.success && data.data) {
                    if (textarea) textarea.value = data.data.prompt || '';
                    setStatus('Base prompt loaded.', true);
                } else {
                    setStatus('Could not load prompt.', false);
                }
            })
            .catch(function () { setStatus('Network error while loading prompt.', false); });
        }

        function savePrompt() {
            var prompt = textarea ? textarea.value : '';
            if (!current.type) return;

            setStatus('Saving...', true);

            var params = new URLSearchParams();
            params.append('action', 'cbia_save_img_prompt_override');
            params.append('_ajax_nonce', adminData.nonce);
            params.append('post_id', 0);
            params.append('type', current.type);
            params.append('idx', current.idx);
            params.append('prompt', prompt);

            fetch(adminData.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    setStatus((data && data.data && data.data.message) ? data.data.message : 'Could not save prompt.', false);
                    return;
                }
                setStatus('Base prompt saved.', true);
                flashSavedTrigger();
                closeModal();
            })
            .catch(function () { setStatus('Network error while saving.', false); });
        }

        document.querySelectorAll('.cbia-prompt-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openModal(btn.getAttribute('data-type'), btn.getAttribute('data-idx'), btn);
            });
        });

        if (btnSave) btnSave.addEventListener('click', function () { savePrompt(); });
        if (btnClose) btnClose.addEventListener('click', function () { closeModal(); });

        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
    }

    // CAMBIO: soportar selectores de proveedor por scope (texto/imagen)
    function initProviderSelects() {
        var wrappers = document.querySelectorAll('.abb-provider-select');
        if (!wrappers.length) return;

        function getProvider(scope) {
            var select = document.querySelector('.abb-provider-select-input[data-scope="' + scope + '"]');
            return select ? (select.value || 'openai') : 'openai';
        }

        // NOTE: Google Imagen (Vertex AI) extra fields
        function updateGoogleImageExtras() {
            var imageProvider = getProvider('image');
            document.querySelectorAll('.abb-google-imagen-fields[data-scope="image"]').forEach(function (el) {
                el.style.display = (imageProvider === 'google') ? '' : 'none';
            });
            if (imageProvider !== 'google') return;
            var modelSelect = document.querySelector('.abb-provider-model[data-scope="image"][data-provider="google"] select');
            var model = modelSelect ? modelSelect.value : '';
            var isImagen = model === 'imagen-2';
            document.querySelectorAll('.abb-google-imagen-note-imagen').forEach(function (el) {
                el.style.display = isImagen ? '' : 'none';
            });
            document.querySelectorAll('.abb-google-imagen-note-gemini').forEach(function (el) {
                el.style.display = isImagen ? 'none' : '';
            });
        }

        function updateScope(scope) {
            var select = document.querySelector('.abb-provider-select-input[data-scope="' + scope + '"]');
            if (!select) return;
            var provider = select.value || 'openai';
            document.querySelectorAll('.abb-provider-model[data-scope="' + scope + '"]').forEach(function (el) {
                var p = el.getAttribute('data-provider') || 'openai';
                el.style.display = (p === provider) ? '' : 'none';
            });
        }

        function updateDeepSeekSettings() {
            var visible = getProvider('text') === 'deepseek';
            var thinking = document.getElementById('cbia-deepseek-thinking');
            var enabled = visible && thinking && thinking.value === 'enabled';
            document.querySelectorAll('.abb-deepseek-settings').forEach(function (el) {
                el.style.display = visible ? '' : 'none';
            });
            document.querySelectorAll('.abb-deepseek-effort').forEach(function (el) {
                el.style.display = enabled ? '' : 'none';
            });
            var effort = document.getElementById('cbia-deepseek-effort');
            if (effort) {
                effort.disabled = !enabled;
                var effortWrap = effort.closest('.abb-select-wrap');
                var effortTrigger = effortWrap ? effortWrap.querySelector('.abb-select-trigger') : null;
                if (effortWrap) effortWrap.classList.toggle('is-disabled', !enabled);
                if (effortTrigger) effortTrigger.disabled = !enabled;
            }
        }

        function updateKeys() {
            var textProvider = getProvider('text');
            var imageProvider = getProvider('image');
            document.querySelectorAll('.abb-provider-key[data-scope="text"]').forEach(function (el) {
                var p = el.getAttribute('data-provider') || 'openai';
                el.style.display = (p === textProvider) ? '' : 'none';
            });
            document.querySelectorAll('.abb-provider-key[data-scope="image"]').forEach(function (el) {
                var p = el.getAttribute('data-provider') || 'openai';
                var show = (p === imageProvider) && (imageProvider !== textProvider);
                el.style.display = show ? '' : 'none';
            });
            updateGoogleImageExtras();
            updateDeepSeekSettings();
        }

        wrappers.forEach(function (wrapper) {
            var scope = wrapper.getAttribute('data-scope') || 'text';
            var select = wrapper.querySelector('.abb-provider-select-input');
            var logo = wrapper.querySelector('.abb-provider-logo');
            var label = wrapper.querySelector('.abb-provider-label');
            var trigger = wrapper.querySelector('.abb-provider-trigger');
            var menu = wrapper.querySelector('.abb-provider-menu');
            var options = wrapper.querySelectorAll('.abb-provider-option');

            function update() {
                var opt = select.options[select.selectedIndex];
                var optLogo = opt ? opt.getAttribute('data-logo') : '';
                if (logo && optLogo) {
                    logo.src = optLogo;
                }
                if (label && opt) {
                    label.textContent = opt.textContent;
                }
                options.forEach(function (btn) {
                    var val = btn.getAttribute('data-value');
                    btn.classList.toggle('is-active', val === select.value);
                });
                updateScope(scope);
                updateKeys();
            }

            function closeMenu() {
                if (menu) menu.classList.remove('is-open');
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
            }

            if (trigger && menu) {
                trigger.addEventListener('click', function () {
                    var isOpen = menu.classList.contains('is-open');
                    if (isOpen) {
                        closeMenu();
                    } else {
                        menu.classList.add('is-open');
                        trigger.setAttribute('aria-expanded', 'true');
                    }
                });
            }

            options.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var val = btn.getAttribute('data-value') || 'openai';
                    select.value = val;
                    update();
                    closeMenu();
                });
            });

            document.addEventListener('click', function (e) {
                if (!menu || !trigger) return;
                if (menu.contains(e.target) || trigger.contains(e.target)) return;
                closeMenu();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeMenu();
            });

            select.addEventListener('change', update);
            update();
        });

        document.querySelectorAll('.abb-provider-model[data-scope="image"] select').forEach(function (sel) {
            sel.addEventListener('change', updateGoogleImageExtras);
        });
        var deepSeekThinking = document.getElementById('cbia-deepseek-thinking');
        if (deepSeekThinking) deepSeekThinking.addEventListener('change', updateDeepSeekSettings);
        updateGoogleImageExtras();
        updateDeepSeekSettings();
    }

    function initAbbSelects() {
        var selects = document.querySelectorAll('select.abb-select');
        if (!selects.length) return;

        function closeAll() {
            document.querySelectorAll('.abb-select-menu.is-open').forEach(function (menu) {
                menu.classList.remove('is-open');
            });
            document.querySelectorAll('.abb-select-trigger[aria-expanded="true"]').forEach(function (btn) {
                btn.setAttribute('aria-expanded', 'false');
            });
        }

        selects.forEach(function (select) {
            if (select.closest('.abb-select-wrap')) return;

            var wrapper = document.createElement('div');
            wrapper.className = 'abb-select-wrap';
            if (select.style && select.style.width) {
                wrapper.style.width = select.style.width;
            }

            var trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'abb-select-trigger';
            trigger.setAttribute('aria-expanded', 'false');

            var label = document.createElement('span');
            label.className = 'abb-select-label';

            var caret = document.createElement('span');
            caret.className = 'abb-select-caret';
            // Use HTML entity to avoid mojibake when file encoding is not UTF-8.
            caret.innerHTML = '&#9662;';

            trigger.appendChild(label);
            trigger.appendChild(caret);

            var menu = document.createElement('div');
            menu.className = 'abb-select-menu';

            function rebuildOptions() {
                menu.innerHTML = '';
                Array.prototype.slice.call(select.options).forEach(function (opt) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'abb-select-option';
                    btn.setAttribute('data-value', opt.value);
                    btn.textContent = opt.textContent;
                    btn.addEventListener('click', function () {
                        select.value = opt.value;
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                        update();
                        closeAll();
                    });
                    menu.appendChild(btn);
                });
            }

            function update() {
                var opt = select.options[select.selectedIndex];
                if (opt) {
                    label.textContent = opt.textContent;
                }
                Array.prototype.slice.call(menu.querySelectorAll('.abb-select-option')).forEach(function (btn) {
                    btn.classList.toggle('is-active', btn.getAttribute('data-value') === select.value);
                });
                var isDisabled = !!select.disabled;
                wrapper.classList.toggle('is-disabled', isDisabled);
                trigger.disabled = isDisabled;
            }

            rebuildOptions();
            update();

            trigger.addEventListener('click', function (e) {
                e.preventDefault();
                if (trigger.disabled) return;
                var isOpen = menu.classList.contains('is-open');
                closeAll();
                if (!isOpen) {
                    menu.classList.add('is-open');
                    trigger.setAttribute('aria-expanded', 'true');
                }
            });

            select.addEventListener('change', update);

            select.parentNode.insertBefore(wrapper, select);
            wrapper.appendChild(trigger);
            wrapper.appendChild(menu);
            wrapper.appendChild(select);
        });

        document.addEventListener('click', function (e) {
            if (e.target.closest('.abb-select-wrap')) return;
            closeAll();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeAll();
        });
    }

    function initUsageModelSync() {
        var btn = document.getElementById('cbia-sync-models-btn');
        var adminData = window.CBIAAdmin;
        if (!btn || !adminData) return;

        btn.addEventListener('click', function () {
            var provider = btn.getAttribute('data-provider') || '';
            btn.disabled = true;
            var oldText = btn.textContent;
            btn.textContent = 'Sincronizando...';

            var params = new URLSearchParams();
            params.append('action', 'cbia_sync_models');
            params.append('_ajax_nonce', adminData.nonce);
            params.append('provider', provider);

            fetch(adminData.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.success) {
                    btn.textContent = 'Sync OK (' + (data.data.count || 0) + ')';
                    var status = document.getElementById('cbia-sync-models-status');
                    if (status && data.data.meta && data.data.meta.ts) {
                        status.textContent = 'Last sync: ' + data.data.meta.ts;
                    }
                } else {
                    btn.textContent = 'Sync failed';
                    var status = document.getElementById('cbia-sync-models-status');
                    if (status && data && data.data && data.data.result && data.data.result.error) {
                        status.textContent = 'Last sync: error (' + data.data.result.error + ')';
                    }
                }
                setTimeout(function () {
                    btn.disabled = false;
                    btn.textContent = oldText;
                }, 2000);
            })
            .catch(function () {
                btn.disabled = false;
                btn.textContent = oldText;
            });
        });
    }

    function initUsageDashboard() {
        var root = document.getElementById('cbia-usage-dashboard');
        var dataNode = document.getElementById('cbia-usage-data');
        if (!root || !dataNode || root.getAttribute('data-cbia-usage-v2-bound') === '1') return;
        root.setAttribute('data-cbia-usage-v2-bound', '1');

        var payload = {};
        try {
            payload = JSON.parse(dataNode.textContent || '{}');
        } catch (_error) {
            payload = {};
        }

        var rows = Array.isArray(payload.rows) ? payload.rows.slice() : [];
        var summariesByModel = payload.summariesByModel && typeof payload.summariesByModel === 'object' ? payload.summariesByModel : {};
        var previousSummariesByModel = payload.previousSummariesByModel && typeof payload.previousSummariesByModel === 'object' ? payload.previousSummariesByModel : {};
        var modelOptions = Array.isArray(payload.modelOptions) ? payload.modelOptions.slice() : [];
        var totalRows = Number(payload.totalRows || rows.length || 0);
        var rowsLimited = !!payload.rowsLimited;
        var recentRowsLimit = Number(payload.recentRowsLimit || rows.length || 0);
        var canViewCosts = !!payload.canViewCosts;
        var i18n = payload.i18n && typeof payload.i18n === 'object' ? payload.i18n : {};
        var locale = String(payload.locale || document.documentElement.lang || 'en-US').replace('_', '-');
        var selectedKey = '';
        var currentMetric = canViewCosts ? 'cost' : 'calls';
        var lastFilteredRows = [];
        var resizeFrame = 0;

        var controls = {
            model: document.getElementById('cbia-usage-model-filter'),
            type: document.getElementById('cbia-usage-type-filter'),
            provider: document.getElementById('cbia-usage-provider-filter'),
            costStatus: document.getElementById('cbia-usage-status-filter'),
            requestStatus: document.getElementById('cbia-usage-request-status-filter'),
            quality: document.getElementById('cbia-usage-quality-filter'),
            role: document.getElementById('cbia-usage-image-role-filter'),
            from: document.getElementById('cbia-usage-from'),
            to: document.getElementById('cbia-usage-to'),
            search: document.getElementById('cbia-usage-search')
        };

        function t(key) {
            return Object.prototype.hasOwnProperty.call(i18n, key) ? String(i18n[key]) : '';
        }

        function formatTemplate(template, values) {
            return String(template || '').replace(/%(\d+)\$s/g, function (_match, index) {
                return values[Number(index) - 1] !== undefined ? String(values[Number(index) - 1]) : '';
            });
        }

        function escapeHtml(value) {
            return String(value === undefined || value === null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function safeAdminUrl(value) {
            try {
                var url = new URL(String(value || ''), window.location.origin);
                return (url.protocol === 'http:' || url.protocol === 'https:') && url.origin === window.location.origin ? url.toString() : '';
            } catch (_error) {
                return '';
            }
        }

        function hasNumericValue(value) {
            return value !== null && value !== '' && isFinite(Number(value));
        }

        function numberFormat(value, maximumFractionDigits) {
            try {
                return new Intl.NumberFormat(locale, {
                    maximumFractionDigits: maximumFractionDigits === undefined ? 0 : maximumFractionDigits
                }).format(Number(value || 0));
            } catch (_error) {
                return String(Number(value || 0).toFixed(maximumFractionDigits || 0));
            }
        }

        function currencyFormat(value) {
            try {
                return new Intl.NumberFormat(locale, {
                    style: 'currency',
                    currency: 'USD',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 4
                }).format(Number(value || 0));
            } catch (_error) {
                return '$' + Number(value || 0).toFixed(2);
            }
        }

        function dateFromIso(day) {
            var parts = String(day || '').split('-');
            if (parts.length !== 3) return null;
            var parsed = new Date(Date.UTC(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]), 12));
            return isNaN(parsed.getTime()) ? null : parsed;
        }

        function formatDay(day) {
            var parsed = dateFromIso(String(day || '').slice(0, 10));
            if (!parsed) return String(day || '');
            try {
                return new Intl.DateTimeFormat(locale, {day: '2-digit', month: 'short'}).format(parsed);
            } catch (_error) {
                return String(day || '').slice(5);
            }
        }

        function formatMonth(month) {
            var parsed = dateFromIso(String(month || '') + '-01');
            if (!parsed) return String(month || '');
            try {
                return new Intl.DateTimeFormat(locale, {month: 'short', year: '2-digit'}).format(parsed);
            } catch (_error) {
                return String(month || '');
            }
        }

        function formatDateTime(value) {
            var raw = String(value || '').trim();
            if (!raw) return '-';
            var hasExplicitZone = /(?:z|[+-]\d{2}:?\d{2})$/i.test(raw);
            var parsed = null;
            var timeZone = hasExplicitZone ? String(payload.siteTimezone || '') : 'UTC';
            if (hasExplicitZone) {
                parsed = new Date(raw);
            } else {
                var match = raw.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
                if (match) parsed = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]), Number(match[4] || 0), Number(match[5] || 0), Number(match[6] || 0)));
            }
            if (!parsed || isNaN(parsed.getTime())) return raw;
            try {
                var options = {dateStyle: 'medium', timeStyle: 'short'};
                if (timeZone) options.timeZone = timeZone;
                return new Intl.DateTimeFormat(locale, options).format(parsed);
            } catch (_error) {
                return raw;
            }
        }

        function rowKey(row) {
            return [row.ts, row.post_id, row.type, row.model, row.section, row.attach_id, row.request_id].join('|');
        }

        function normalizeQuality(row) {
            var quality = String((row && (row.effective_quality || row.quality || row.requested_quality)) || '').toLowerCase();
            return ['auto', 'low', 'medium', 'high'].indexOf(quality) >= 0 ? quality : 'unknown';
        }

        function normalizeRole(row) {
            var role = String((row && row.image_type) || '').toLowerCase();
            var section = String((row && row.section) || '').toLowerCase();
            var sectionLabel = String((row && row.section_label) || '').toLowerCase();
            if (role === 'featured' || section === 'featured' || section === 'intro' || sectionLabel === 'featured') return 'featured';
            if (role === 'content' || role === 'internal' || sectionLabel === 'internal' || ['body', 'closing', 'faq'].indexOf(section) >= 0) return 'content';
            return 'other';
        }

        function periodInfo() {
            var days = Number(payload.periodDays || 30);
            if (!isFinite(days) || days < 1) days = 30;
            var start = String(payload.periodStartDay || '');
            var end = String(payload.periodEndDay || '');
            var label = t('periodLabel') + ': ' + formatDay(start) + ' – ' + formatDay(end) + ' (' + numberFormat(days) + ' ' + t('daysLabel') + ')';
            return {days: days, start: start, end: end, label: label};
        }

        function populateModels() {
            if (!controls.model) return;
            var initial = String(payload.defaultModel || controls.model.value || '');
            var firstLabel = controls.model.options.length ? controls.model.options[0].text : '';
            controls.model.innerHTML = '<option value="">' + escapeHtml(firstLabel) + '</option>' + modelOptions.map(function (model) {
                var value = String(model || '');
                return value ? '<option value="' + escapeHtml(value) + '">' + escapeHtml(value) + '</option>' : '';
            }).join('');
            controls.model.value = initial;
        }

        function getSelectedModelKey() {
            var model = controls.model ? String(controls.model.value || '') : '';
            return model || '__all__';
        }

        function secondaryFilterCount() {
            return [controls.quality, controls.role, controls.costStatus, controls.requestStatus, controls.from, controls.to].reduce(function (count, control) {
                return count + (control && String(control.value || '') ? 1 : 0);
            }, 0);
        }

        function hasRowFilters() {
            return !!(
                (controls.type && controls.type.value) ||
                (controls.provider && controls.provider.value) ||
                (controls.costStatus && controls.costStatus.value) ||
                (controls.requestStatus && controls.requestStatus.value) ||
                (controls.quality && controls.quality.value) ||
                (controls.role && controls.role.value) ||
                (controls.from && controls.from.value) ||
                (controls.to && controls.to.value) ||
                (controls.search && controls.search.value.trim())
            );
        }

        function canUseSummaryDataset() {
            return !hasRowFilters();
        }

        function filteredRows() {
            var values = {};
            Object.keys(controls).forEach(function (key) {
                values[key] = controls[key] ? String(controls[key].value || '').trim() : '';
            });
            var term = values.search.toLowerCase();
            return rows.filter(function (row) {
                if (values.model && String(row.model || '') !== values.model) return false;
                if (values.type && String(row.type || '') !== values.type) return false;
                if (values.provider && String(row.provider || '') !== values.provider) return false;
                if (values.costStatus && String(row.cost_status || 'unknown') !== values.costStatus) return false;
                var requestStatus = String(row.status || (row.ok ? 'success' : 'error'));
                if (values.requestStatus && requestStatus !== values.requestStatus) return false;
                if (values.quality && (String(row.type || '') !== 'image' || normalizeQuality(row) !== values.quality)) return false;
                if (values.role && (String(row.type || '') !== 'image' || normalizeRole(row) !== values.role)) return false;
                var localTs = String(row.ts || '').replace(' ', 'T').slice(0, 16);
                if (values.from && localTs && localTs < values.from) return false;
                if (values.to && localTs && localTs > values.to) return false;
                if (!term) return true;
                return [
                    row.ts, row.user_name, row.post_title, row.model, row.provider, row.batch_id,
                    row.request_id, row.type_label, row.message_preview, row.status_label,
                    normalizeQuality(row), normalizeRole(row)
                ].join(' ').toLowerCase().indexOf(term) >= 0;
            });
        }

        function emptySummary() {
            return {
                totalCalls: 0,
                uniquePosts: 0,
                uniqueUsers: 0,
                totalTokens: 0,
                avgTokens: 0,
                totalCost: 0,
                avgCostPerPost: 0,
                knownCostEvents: 0,
                unknownCostEvents: 0,
                costCoveragePercent: 0,
                typeCounts: {text: 0, image: 0, seo: 0},
                dailySeries: []
            };
        }

        function summaryFromRows(list) {
            var postIds = {};
            var userIds = {};
            var totalTokens = 0;
            var totalCost = 0;
            var known = 0;
            var types = {text: 0, image: 0, seo: 0};
            list.forEach(function (row) {
                if (Number(row.post_id || 0) > 0) postIds[String(row.post_id)] = true;
                if (String(row.user_id || row.user_name || '')) userIds[String(row.user_id || row.user_name)] = true;
                totalTokens += Number(row.tokens_total || 0);
                var type = String(row.type || '');
                if (Object.prototype.hasOwnProperty.call(types, type)) types[type] += 1;
                if (hasNumericValue(row.cost_eur)) {
                    totalCost += Number(row.cost_eur);
                    known += 1;
                }
            });
            var calls = list.length;
            var posts = Object.keys(postIds).length;
            return {
                totalCalls: calls,
                uniquePosts: posts,
                uniqueUsers: Object.keys(userIds).length,
                totalTokens: totalTokens,
                avgTokens: calls ? Math.round(totalTokens / calls) : 0,
                totalCost: totalCost,
                avgCostPerPost: posts ? totalCost / posts : 0,
                knownCostEvents: known,
                unknownCostEvents: calls - known,
                costCoveragePercent: calls ? 100 * known / calls : 0,
                typeCounts: types,
                dailySeries: seriesFromRows(list)
            };
        }

        function activeSummary(filtered) {
            if (canUseSummaryDataset()) {
                var modelKey = getSelectedModelKey();
                return summariesByModel[modelKey] || (modelKey === '__all__' ? summariesByModel.__all__ : null) || emptySummary();
            }
            return summaryFromRows(filtered);
        }

        function previousSummary() {
            if (!canUseSummaryDataset()) return null;
            var modelKey = getSelectedModelKey();
            if (previousSummariesByModel[modelKey]) return previousSummariesByModel[modelKey];
            return modelKey === '__all__' ? (previousSummariesByModel.__all__ || null) : emptySummary();
        }

        function seriesFromRows(list) {
            var map = {};
            list.forEach(function (row) {
                var day = String(row.ts || '').slice(0, 10);
                if (!/^\d{4}-\d{2}-\d{2}$/.test(day)) return;
                if (!map[day]) map[day] = {day: day, calls: 0, cost: 0, textCalls: 0, imageCalls: 0, seoCalls: 0};
                map[day].calls += 1;
                var type = String(row.type || 'text');
                var countKey = type === 'image' ? 'imageCalls' : (type === 'seo' ? 'seoCalls' : 'textCalls');
                map[day][countKey] += 1;
                if (hasNumericValue(row.cost_eur)) map[day].cost += Number(row.cost_eur);
            });
            var info = periodInfo();
            var output = [];
            var cursor = dateFromIso(info.start);
            var end = dateFromIso(info.end);
            if (!cursor || !end) return Object.keys(map).sort().map(function (key) { return map[key]; });
            while (cursor.getTime() <= end.getTime()) {
                var day = cursor.toISOString().slice(0, 10);
                output.push(map[day] || {day: day, calls: 0, cost: 0, textCalls: 0, imageCalls: 0, seoCalls: 0});
                cursor.setUTCDate(cursor.getUTCDate() + 1);
            }
            return output;
        }

        function aggregateSeries(series) {
            var granularity = String(payload.granularity || 'day');
            var groups = {};
            (Array.isArray(series) ? series : []).forEach(function (item, index) {
                var day = String(item.day || '');
                var key = day;
                if (granularity === 'month') {
                    key = day.slice(0, 7);
                } else if (granularity === 'week') {
                    var start = dateFromIso(periodInfo().start);
                    var current = dateFromIso(day);
                    var offset = start && current ? Math.floor((current.getTime() - start.getTime()) / 604800000) : Math.floor(index / 7);
                    var weekStart = start ? new Date(start.getTime() + Math.max(0, offset) * 604800000) : current;
                    key = weekStart ? weekStart.toISOString().slice(0, 10) : day;
                }
                if (!groups[key]) groups[key] = {key: key, calls: 0, cost: 0, textCalls: 0, imageCalls: 0, seoCalls: 0};
                groups[key].calls += Number(item.calls || 0);
                groups[key].cost += Number(item.cost || item.cost_eur || 0);
                groups[key].textCalls += Number(item.textCalls || item.text_calls || 0);
                groups[key].imageCalls += Number(item.imageCalls || item.image_calls || 0);
                groups[key].seoCalls += Number(item.seoCalls || item.seo_calls || 0);
            });
            return Object.keys(groups).sort().map(function (key) {
                var item = groups[key];
                item.label = granularity === 'month' ? formatMonth(key) : formatDay(key);
                return item;
            });
        }

        function setupCanvas(canvas, height) {
            if (!canvas) return null;
            var width = Math.max(280, Math.floor((canvas.parentElement && canvas.parentElement.clientWidth) || canvas.clientWidth || 720));
            var ratio = Math.min(2, window.devicePixelRatio || 1);
            canvas.width = Math.floor(width * ratio);
            canvas.height = Math.floor(height * ratio);
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';
            var context = canvas.getContext('2d');
            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            context.clearRect(0, 0, width, height);
            return {ctx: context, width: width, height: height};
        }

        function responsiveChartHeight(desktopHeight) {
            if (window.matchMedia('(max-width: 480px)').matches) return desktopHeight === 320 ? 250 : 240;
            if (window.matchMedia('(max-width: 782px)').matches) return desktopHeight === 320 ? 280 : 270;
            return desktopHeight;
        }

        function drawAxes(frame, max, formatter) {
            var ctx = frame.ctx;
            var left = 56;
            var right = frame.width - 16;
            var top = 18;
            var bottom = frame.height - 42;
            ctx.font = '12px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';
            for (var step = 0; step <= 4; step += 1) {
                var y = bottom - (bottom - top) * step / 4;
                ctx.strokeStyle = '#e2e8f0';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(left, y);
                ctx.lineTo(right, y);
                ctx.stroke();
                ctx.fillStyle = '#64748b';
                ctx.fillText(formatter(max * step / 4), left - 8, y);
            }
            return {left: left, right: right, top: top, bottom: bottom};
        }

        function setChartTooltip(canvas, series, box, slot, describe) {
            if (!canvas || !canvas.parentElement) return;
            var wrap = canvas.parentElement;
            var tooltip = wrap.querySelector('.cbia-usage-chart-tooltip');
            if (!tooltip) {
                tooltip = document.createElement('div');
                tooltip.className = 'cbia-usage-chart-tooltip';
                tooltip.hidden = true;
                tooltip.setAttribute('aria-hidden', 'true');
                wrap.appendChild(tooltip);
            }
            canvas._cbiaUsageTooltip = {series: series, box: box, slot: slot, describe: describe, tooltip: tooltip};
            if (canvas.getAttribute('data-cbia-tooltip-bound') === '1') return;
            canvas.setAttribute('data-cbia-tooltip-bound', '1');
            function hideTooltip() {
                var state = canvas._cbiaUsageTooltip;
                if (state && state.tooltip) state.tooltip.hidden = true;
            }
            function showTooltip(event) {
                var state = canvas._cbiaUsageTooltip;
                if (!state || !state.series.length || !state.slot) return hideTooltip();
                var rect = canvas.getBoundingClientRect();
                var x = event.clientX - rect.left;
                var y = event.clientY - rect.top;
                if (x < state.box.left || x > state.box.right || y < state.box.top || y > state.box.bottom) return hideTooltip();
                var index = Math.max(0, Math.min(state.series.length - 1, Math.floor((x - state.box.left) / state.slot)));
                var lines = state.describe(state.series[index], index);
                state.tooltip.innerHTML = lines.map(function (line, lineIndex) {
                    return lineIndex === 0 ? '<strong>' + escapeHtml(line) + '</strong>' : '<span>' + escapeHtml(line) + '</span>';
                }).join('');
                state.tooltip.style.left = Math.max(12, Math.min(rect.width - 12, x)) + 'px';
                state.tooltip.style.top = Math.max(12, y - 10) + 'px';
                state.tooltip.hidden = false;
            }
            canvas.addEventListener('pointermove', showTooltip);
            canvas.addEventListener('pointerdown', showTooltip);
            canvas.addEventListener('pointerleave', hideTooltip);
        }

        function renderActivity(filtered, summary) {
            var canvas = document.getElementById('cbia-usage-activity-chart');
            var empty = document.getElementById('cbia-usage-activity-empty');
            var accessible = document.getElementById('cbia-usage-activity-data');
            var hint = document.getElementById('cbia-usage-activity-hint');
            if (!canvas) return;
            var raw = canUseSummaryDataset() && Array.isArray(summary.dailySeries) ? summary.dailySeries : seriesFromRows(filtered);
            var series = aggregateSeries(raw);
            var values = series.map(function (item) { return Number(item[currentMetric] || 0); });
            var max = Math.max.apply(null, values.concat([0]));
            if (empty) empty.hidden = max > 0;
            if (empty) {
                var emptyLabel = empty.querySelector('span');
                if (emptyLabel) emptyLabel.textContent = currentMetric === 'cost' && Number(summary.totalCalls || 0) > 0 ? t('costUnavailable') : t('noEventsPeriod');
            }
            canvas.hidden = max <= 0;
            if (max <= 0 && canvas.parentElement) {
                var staleActivityTooltip = canvas.parentElement.querySelector('.cbia-usage-chart-tooltip');
                if (staleActivityTooltip) staleActivityTooltip.hidden = true;
            }
            var granularityKey = 'granularity' + String(payload.granularity || 'day').charAt(0).toUpperCase() + String(payload.granularity || 'day').slice(1);
            if (hint) hint.textContent = (currentMetric === 'cost' ? t('activityCostHint') : t('activityCallsHint')) + ' ' + t(granularityKey) + '. ' + periodInfo().label + '.';
            canvas.setAttribute('aria-label', (currentMetric === 'cost' ? t('cost') : t('calls')) + '. ' + periodInfo().label);
            if (accessible) {
                accessible.innerHTML = series.map(function (item) {
                    var value = currentMetric === 'cost' ? currencyFormat(item.cost) : numberFormat(item.calls);
                    return '<li>' + escapeHtml(item.label + ': ' + value + '; ' + t('text') + ' ' + numberFormat(item.textCalls) + '; ' + t('image') + ' ' + numberFormat(item.imageCalls)) + '</li>';
                }).join('');
            }
            if (max <= 0) return;
            var frame = setupCanvas(canvas, responsiveChartHeight(320));
            if (!frame) return;
            var box = drawAxes(frame, max, currentMetric === 'cost' ? currencyFormat : numberFormat);
            var count = series.length;
            var slot = (box.right - box.left) / Math.max(1, count);
            var ctx = frame.ctx;
            if (currentMetric === 'cost') {
                series.forEach(function (item, index) {
                    var value = Number(item.cost || 0);
                    var height = (box.bottom - box.top) * value / max;
                    var barWidth = Math.max(2, Math.min(24, slot * 0.62));
                    ctx.fillStyle = '#2563eb';
                    ctx.fillRect(box.left + slot * index + (slot - barWidth) / 2, box.bottom - height, barWidth, height);
                });
            } else {
                ctx.beginPath();
                series.forEach(function (item, index) {
                    var x = box.left + slot * (index + 0.5);
                    var y = box.bottom - (box.bottom - box.top) * Number(item.calls || 0) / max;
                    if (index === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
                });
                ctx.lineTo(box.left + slot * (count - 0.5), box.bottom);
                ctx.lineTo(box.left + slot * 0.5, box.bottom);
                ctx.closePath();
                ctx.fillStyle = 'rgba(37, 99, 235, 0.12)';
                ctx.fill();
                ctx.beginPath();
                series.forEach(function (item, index) {
                    var x = box.left + slot * (index + 0.5);
                    var y = box.bottom - (box.bottom - box.top) * Number(item.calls || 0) / max;
                    if (index === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
                });
                ctx.strokeStyle = '#2563eb';
                ctx.lineWidth = 2.5;
                ctx.stroke();
            }
            var labelEvery = Math.max(1, Math.ceil(count / 7));
            ctx.fillStyle = '#64748b';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'top';
            series.forEach(function (item, index) {
                if (index % labelEvery !== 0 && index !== count - 1) return;
                ctx.fillText(item.label, box.left + slot * (index + 0.5), box.bottom + 10);
            });
            setChartTooltip(canvas, series, box, slot, function (item) {
                return [
                    item.label,
                    (currentMetric === 'cost' ? t('cost') + ': ' + currencyFormat(item.cost) : t('calls') + ': ' + numberFormat(item.calls)),
                    t('text') + ': ' + numberFormat(item.textCalls),
                    t('image') + ': ' + numberFormat(item.imageCalls)
                ];
            });
        }

        function renderType(summary) {
            var chart = document.getElementById('cbia-usage-type-chart');
            var empty = document.getElementById('cbia-usage-type-empty');
            if (!chart) return;
            var counts = summary.typeCounts || {};
            var textCount = Number(counts.text || 0);
            var imageCount = Number(counts.image || 0);
            var total = textCount + imageCount;
            if (empty) empty.hidden = total > 0;
            chart.hidden = total <= 0;
            var textPercent = total ? 100 * textCount / total : 0;
            var imagePercent = total ? 100 * imageCount / total : 0;
            var textBar = chart.querySelector('.cbia-usage-type-bar .is-text');
            var imageBar = chart.querySelector('.cbia-usage-type-bar .is-image');
            var textValue = chart.querySelector('[data-usage-type="text"]');
            var imageValue = chart.querySelector('[data-usage-type="image"]');
            if (textBar) textBar.style.width = textPercent + '%';
            if (imageBar) imageBar.style.width = imagePercent + '%';
            if (textValue) textValue.textContent = numberFormat(textCount) + ' · ' + numberFormat(textPercent, 1) + '%';
            if (imageValue) imageValue.textContent = numberFormat(imageCount) + ' · ' + numberFormat(imagePercent, 1) + '%';
            chart.setAttribute('aria-label', t('typeDistributionLabel') + ': ' + t('text') + ' ' + numberFormat(textPercent, 1) + '%, ' + t('image') + ' ' + numberFormat(imagePercent, 1) + '%.');
        }

        function renderHorizontalBars(nodeId, emptyId, values, labels) {
            var node = document.getElementById(nodeId);
            var empty = document.getElementById(emptyId);
            if (!node) return;
            var sorted = Object.keys(values).map(function (key) {
                return {key: key, label: labels[key] || key, value: Number(values[key] || 0)};
            }).sort(function (a, b) {
                return b.value - a.value || a.label.localeCompare(b.label);
            });
            var total = sorted.reduce(function (sum, item) { return sum + item.value; }, 0);
            node.hidden = total <= 0;
            if (empty) empty.hidden = total > 0;
            node.innerHTML = sorted.map(function (item) {
                var percent = total ? 100 * item.value / total : 0;
                return '<div class="cbia-usage-horizontal-row"><div><span>' + escapeHtml(item.label) + '</span><strong>' + escapeHtml(numberFormat(item.value) + ' · ' + numberFormat(percent, 1) + '%') + '</strong></div><span class="cbia-usage-horizontal-track"><i style="width:' + percent + '%"></i></span></div>';
            }).join('');
        }

        function imageBreakdowns(filtered) {
            var qualities = {auto: 0, low: 0, medium: 0, high: 0, unknown: 0};
            var roles = {featured: 0, content: 0, other: 0};
            filtered.forEach(function (row) {
                if (String(row.type || '') !== 'image') return;
                qualities[normalizeQuality(row)] += 1;
                roles[normalizeRole(row)] += 1;
            });
            renderHorizontalBars('cbia-usage-image-quality-chart', 'cbia-usage-image-quality-empty', qualities, {
                auto: t('automatic'), low: t('low'), medium: t('medium'), high: t('high'), unknown: t('unknown')
            });
            renderHorizontalBars('cbia-usage-image-role-chart', 'cbia-usage-image-role-empty', roles, {
                featured: t('featured'), content: t('internal'), other: t('other')
            });
        }

        function monthlySeries() {
            var provider = controls.provider ? String(controls.provider.value || '') : '';
            var model = controls.model ? String(controls.model.value || '') : '';
            var emptySeries = (Array.isArray(payload.monthlySeries) ? payload.monthlySeries : []).map(function (item) {
                return {month: item.month, text_cost_eur: 0, image_cost_eur: 0, seo_cost_eur: 0, cost_eur: 0};
            });
            if (provider && model) {
                return payload.monthlySeriesByProviderModel && payload.monthlySeriesByProviderModel[provider] && payload.monthlySeriesByProviderModel[provider][model]
                    ? payload.monthlySeriesByProviderModel[provider][model]
                    : emptySeries;
            }
            if (provider) {
                return payload.monthlySeriesByProvider && payload.monthlySeriesByProvider[provider]
                    ? payload.monthlySeriesByProvider[provider]
                    : emptySeries;
            }
            if (model) {
                return summariesByModel[model] && Array.isArray(summariesByModel[model].monthlySeries)
                    ? summariesByModel[model].monthlySeries
                    : emptySeries;
            }
            return Array.isArray(payload.monthlySeries) ? payload.monthlySeries : [];
        }

        function monthlyValue(item, key) {
            if (hasNumericValue(item[key])) return Number(item[key]);
            var camel = key.replace(/_([a-z])/g, function (_match, letter) { return letter.toUpperCase(); });
            return Number(item[camel] || 0);
        }

        function renderMonthly() {
            var canvas = document.getElementById('cbia-usage-monthly-chart');
            var empty = document.getElementById('cbia-usage-monthly-empty');
            var accessible = document.getElementById('cbia-usage-monthly-data');
            if (!canvas || !canViewCosts) return;
            var series = monthlySeries();
            var totals = series.map(function (item) {
                return monthlyValue(item, 'text_cost_eur') + monthlyValue(item, 'image_cost_eur') + monthlyValue(item, 'seo_cost_eur');
            });
            var max = Math.max.apply(null, totals.concat([0]));
            canvas.hidden = max <= 0;
            if (max <= 0 && canvas.parentElement) {
                var staleMonthlyTooltip = canvas.parentElement.querySelector('.cbia-usage-chart-tooltip');
                if (staleMonthlyTooltip) staleMonthlyTooltip.hidden = true;
            }
            if (empty) empty.hidden = max > 0;
            if (accessible) {
                accessible.innerHTML = series.map(function (item, index) {
                    return '<li>' + escapeHtml(formatMonth(item.month) + ': ' + t('text') + ' ' + currencyFormat(monthlyValue(item, 'text_cost_eur')) + '; ' + t('image') + ' ' + currencyFormat(monthlyValue(item, 'image_cost_eur')) + '; ' + t('seo') + ' ' + currencyFormat(monthlyValue(item, 'seo_cost_eur')) + '; ' + t('total') + ' ' + currencyFormat(totals[index])) + '</li>';
                }).join('');
            }
            canvas.setAttribute('aria-label', t('rollingCostChart'));
            if (max <= 0) return;
            var frame = setupCanvas(canvas, responsiveChartHeight(300));
            if (!frame) return;
            var box = drawAxes(frame, max, currencyFormat);
            var slot = (box.right - box.left) / Math.max(1, series.length);
            var barWidth = Math.max(6, Math.min(34, slot * 0.62));
            var ctx = frame.ctx;
            series.forEach(function (item, index) {
                var parts = [
                    {value: monthlyValue(item, 'text_cost_eur'), color: '#2563eb'},
                    {value: monthlyValue(item, 'image_cost_eur'), color: '#8b5cf6'},
                    {value: monthlyValue(item, 'seo_cost_eur'), color: '#14b8a6'}
                ];
                var cursor = box.bottom;
                parts.forEach(function (part) {
                    var height = (box.bottom - box.top) * part.value / max;
                    ctx.fillStyle = part.color;
                    ctx.fillRect(box.left + slot * index + (slot - barWidth) / 2, cursor - height, barWidth, height);
                    cursor -= height;
                });
                ctx.fillStyle = '#64748b';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'top';
                ctx.fillText(formatMonth(item.month), box.left + slot * (index + 0.5), box.bottom + 10);
            });
            setChartTooltip(canvas, series, box, slot, function (item, index) {
                return [
                    formatMonth(item.month),
                    t('text') + ': ' + currencyFormat(monthlyValue(item, 'text_cost_eur')),
                    t('image') + ': ' + currencyFormat(monthlyValue(item, 'image_cost_eur')),
                    t('seo') + ': ' + currencyFormat(monthlyValue(item, 'seo_cost_eur')),
                    t('total') + ': ' + currencyFormat(totals[index])
                ];
            });
        }

        function comparison(current, previous) {
            var currentValue = Number(current || 0);
            var previousValue = Number(previous || 0);
            if (!isFinite(currentValue) || !isFinite(previousValue)) return {text: t('noComparison'), status: 'none'};
            if (previousValue === 0) {
                return currentValue > 0 ? {text: '▲ ' + t('newActivity'), status: 'up'} : {text: t('noComparison'), status: 'none'};
            }
            var percent = 100 * (currentValue - previousValue) / Math.abs(previousValue);
            if (!isFinite(percent)) return {text: t('noComparison'), status: 'none'};
            var status = Math.abs(percent) < 0.05 ? 'same' : (percent > 0 ? 'up' : 'down');
            var arrow = status === 'up' ? '▲' : (status === 'down' ? '▼' : '●');
            var word = status === 'up' ? t('increased') : (status === 'down' ? t('decreased') : t('unchanged'));
            return {text: arrow + ' ' + numberFormat(Math.abs(percent), 1) + '% ' + word + ' ' + t('vsPreviousPeriod'), status: status};
        }

        function updateKpi(id, value, current, previous, comparable) {
            var valueNode = document.getElementById('cbia-usage-kpi-' + id);
            var compareNode = document.getElementById('cbia-usage-kpi-' + id + '-comparison');
            if (valueNode) valueNode.textContent = value;
            if (!compareNode) return;
            var result = comparable ? comparison(current, previous) : {text: t('noComparison'), status: 'none'};
            compareNode.textContent = result.text;
            compareNode.className = 'cbia-usage-kpi-comparison is-' + result.status;
        }

        function renderKpis(summary) {
            var previous = previousSummary();
            var comparable = !!previous;
            var types = summary.typeCounts || {};
            var previousTypes = previous && previous.typeCounts ? previous.typeCounts : {};
            var calls = Number(summary.totalCalls || 0);
            var known = Number(summary.knownCostEvents || 0);
            var unknown = Number(summary.unknownCostEvents || Math.max(0, calls - known));
            var coverage = calls ? Number(summary.costCoveragePercent || (100 * known / calls)) : 0;
            var previousCoverage = previous ? Number(previous.costCoveragePercent || 0) : 0;
            var costUnavailable = canViewCosts && known === 0 && unknown > 0;
            updateKpi('posts', numberFormat(summary.uniquePosts), summary.uniquePosts, previous && previous.uniquePosts, comparable);
            updateKpi('calls', numberFormat(calls), calls, previous && previous.totalCalls, comparable);
            updateKpi('images', numberFormat(types.image || 0), types.image, previousTypes.image, comparable);
            updateKpi('cost-total', costUnavailable ? t('costUnavailable') : currencyFormat(summary.totalCost), summary.totalCost, previous && previous.totalCost, comparable);
            updateKpi('cost-blog', costUnavailable ? t('costUnavailable') : currencyFormat(summary.avgCostPerPost), summary.avgCostPerPost, previous && previous.avgCostPerPost, comparable);
            updateKpi('coverage', numberFormat(coverage, 1) + '%', coverage, previousCoverage, comparable);
            var users = document.getElementById('cbia-usage-kpi-users');
            var avg = document.getElementById('cbia-usage-kpi-avg');
            if (users) users.textContent = numberFormat(summary.uniqueUsers);
            if (avg) avg.textContent = numberFormat(summary.avgTokens);
            var donut = document.getElementById('cbia-usage-coverage-donut');
            var badge = document.getElementById('cbia-usage-cost-coverage-badge');
            var coverageText = document.getElementById('cbia-usage-cost-coverage');
            if (donut) {
                donut.style.setProperty('--cbia-coverage', Math.max(0, Math.min(100, coverage)) + '%');
                donut.setAttribute('aria-label', t('costCoverage') + ': ' + numberFormat(coverage, 1) + '%. ' + numberFormat(known) + ' ' + t('knownEvents') + ', ' + numberFormat(unknown) + ' ' + t('unknownEvents') + '.');
            }
            if (badge) badge.textContent = numberFormat(coverage, 1) + '%';
            if (coverageText) coverageText.textContent = numberFormat(known) + ' ' + t('knownEvents') + ' · ' + numberFormat(unknown) + ' ' + t('unknownEvents');
        }

        function updateCostControl(filtered, summary) {
            var drawer = document.getElementById('cbia-usage-cost-drawer');
            if (!drawer || !canViewCosts) return;
            var totals = {text: 0, image: 0, seo: 0};
            filtered.forEach(function (row) {
                var type = String(row.type || '');
                if (Object.prototype.hasOwnProperty.call(totals, type) && hasNumericValue(row.cost_eur)) totals[type] += Number(row.cost_eur);
            });
            var driver = Object.keys(totals).sort(function (a, b) { return totals[b] - totals[a]; })[0];
            var driverValue = totals[driver] || 0;
            var label = document.getElementById('cbia-usage-cost-driver-label');
            var value = document.getElementById('cbia-usage-cost-driver-value');
            if (label) label.textContent = driverValue > 0 ? (driver === 'image' ? t('imageGeneration') : (driver === 'text' ? t('textGeneration') : t('seo'))) : t('noKnownCostDriver');
            if (value) value.textContent = driverValue > 0 ? currencyFormat(driverValue) : '';
            var config = payload.costControl || {};
            var configNode = document.getElementById('cbia-usage-cost-config');
            if (configNode) {
                configNode.innerHTML = '<h4>' + escapeHtml(t('currentConfiguration')) + '</h4><dl>'
                    + '<div><dt>' + escapeHtml(t('text')) + '</dt><dd>' + escapeHtml([config.textProvider, config.textModel].filter(Boolean).join(' · ')) + '</dd></div>'
                    + '<div><dt>' + escapeHtml(t('image')) + '</dt><dd>' + escapeHtml([config.imageProvider, config.imageModel].filter(Boolean).join(' · ')) + '</dd></div>'
                    + '<div><dt>' + escapeHtml(t('defaultImageQuality')) + '</dt><dd>' + escapeHtml(String(config.defaultImageQuality || '') === 'auto' ? t('automatic') : (config.defaultImageQuality || t('automatic'))) + '</dd></div>'
                    + '<div><dt>' + escapeHtml(t('internalImages')) + '</dt><dd>' + escapeHtml(numberFormat(config.internalImageCount || 0)) + '</dd></div>'
                    + '</dl>';
            }
            var recs = [];
            if (Number(summary.unknownCostEvents || 0) > 0) recs.push(t('unknownCostPriority'));
            if (driver === 'image' && driverValue > 0) recs.push(t('imageCostPriority'));
            if (driver === 'text' && driverValue > 0) recs.push(t('textCostPriority'));
            if (String(config.defaultImageQuality || '') === 'auto') recs.push(t('automaticQualityNote'));
            if (Number(config.internalImageCount || 0) > 1) recs.push(t('multipleImagesNote'));
            if (!recs.length) recs.push(t('balancedCostPriority'));
            var list = document.getElementById('cbia-usage-cost-recommendations');
            if (list) list.innerHTML = recs.map(function (rec) { return '<li>' + escapeHtml(rec) + '</li>'; }).join('');
        }

        function renderDetail(row, filtered) {
            var panel = document.getElementById('cbia-usage-detail');
            if (!panel) return;
            if (!row) {
                panel.innerHTML = '<div class="cbia-usage-detail-empty">' + escapeHtml(t('selectEvent')) + '</div>';
                return;
            }
            var postRows = filtered.filter(function (candidate) {
                return Number(candidate.post_id || 0) > 0 && Number(candidate.post_id) === Number(row.post_id);
            });
            if (!postRows.length) postRows = [row];
            var tokensIn = 0;
            var tokensOut = 0;
            var totalTokens = 0;
            var knownCost = 0;
            var knownCostEvents = 0;
            var failed = 0;
            var types = {};
            var models = {};
            postRows.forEach(function (item) {
                tokensIn += Number(item.tokens_in || item.input_tokens || 0);
                tokensOut += Number(item.tokens_out || item.output_tokens || 0);
                totalTokens += Number(item.tokens_total || 0);
                if (hasNumericValue(item.cost_eur)) {
                    knownCost += Number(item.cost_eur);
                    knownCostEvents += 1;
                }
                if (!item.ok) failed += 1;
                if (item.type_label || item.type) types[String(item.type_label || item.type)] = true;
                if (item.model) models[String(item.model)] = true;
            });
            var latestActivity = postRows.reduce(function (latest, item) {
                var value = String(item.ts || '');
                return value > latest ? value : latest;
            }, '');
            var title = row.post_title || ('#' + String(row.post_id || 0));
            var editUrl = safeAdminUrl(row.post_edit_url || row.edit_url || '');
            var editAction = editUrl ? '<a class="button" href="' + escapeHtml(editUrl) + '">' + escapeHtml(t('openPost')) + '</a>' : '';
            var eventCost = hasNumericValue(row.cost_eur) ? currencyFormat(row.cost_eur) : t('unknownCost');
            var tokenValue = Number(row.tokens_total || 0) > 0 ? numberFormat(row.tokens_total) : t('tokensNotApplicable');
            var requestStatusKey = String(row.status || (row.ok ? 'success' : 'error'));
            var requestStatusLabel = row.status_label || t(requestStatusKey) || requestStatusKey;
            var costStatusKey = String(row.cost_status || 'unknown');
            var costStatusLabel = costStatusKey === 'official_reconciled' ? t('officialReconciled') : (t(costStatusKey) || costStatusKey);
            var detailRows = [
                [t('date'), formatDateTime(row.ts)],
                [t('user'), row.user_name || '-'],
                [t('provider'), row.provider_label || row.provider || '-'],
                [t('model'), row.model || '-'],
                [t('type'), row.type_label || row.type || '-'],
                [t('section'), row.section_detail || row.section_label || row.section || '-'],
                [t('totalTokens'), tokenValue],
                [t('eventCost'), canViewCosts ? eventCost : ''],
                [t('costStatus'), canViewCosts ? costStatusLabel : ''],
                [t('status'), requestStatusLabel],
                [t('request'), ['HTTP ' + String(row.http_code || '-'), String(row.elapsed_ms || 0) + ' ms', row.request_id || '-'].join(' · ')],
                [t('batchFallback'), [row.batch_id || '-', row.fallback_from || '-'].join(' · ')],
                [t('summary'), row.message_preview || '-']
            ].filter(function (pair) { return pair[1] !== ''; });
            if (String(row.type || '') === 'image') {
                detailRows.splice(5, 0, [t('imageQuality'), row.effective_quality_label || row.quality_label || normalizeQuality(row)]);
                detailRows.splice(6, 0, [t('imageRole'), normalizeRole(row) === 'content' ? t('internal') : t(normalizeRole(row))]);
            }
            panel.innerHTML = '<div class="cbia-usage-detail-content">'
                + '<span class="cbia-usage-detail-eyebrow">' + escapeHtml(t('selectedEvent')) + '</span>'
                + '<h3>' + escapeHtml(title) + '</h3>'
                + '<p class="description">' + escapeHtml(t('realPostSummary')) + '</p>'
                + '<div class="cbia-usage-post-summary"><h4>' + escapeHtml(t('postSummary')) + '</h4><dl>'
                + '<div><dt>' + escapeHtml(t('calls')) + '</dt><dd>' + numberFormat(postRows.length) + '</dd></div>'
                + '<div><dt>' + escapeHtml(t('inputTokens')) + '</dt><dd>' + numberFormat(tokensIn) + '</dd></div>'
                + '<div><dt>' + escapeHtml(t('outputTokens')) + '</dt><dd>' + numberFormat(tokensOut) + '</dd></div>'
                + '<div><dt>' + escapeHtml(t('totalTokens')) + '</dt><dd>' + numberFormat(totalTokens) + '</dd></div>'
                + (canViewCosts ? '<div><dt>' + escapeHtml(t('totalCost')) + '</dt><dd>' + escapeHtml(knownCostEvents > 0 ? currencyFormat(knownCost) : t('costUnavailable')) + '</dd></div>' : '')
                + '<div><dt>' + escapeHtml(t('failed')) + '</dt><dd>' + numberFormat(failed) + '</dd></div>'
                + '<div><dt>' + escapeHtml(t('lastActivity')) + '</dt><dd>' + escapeHtml(formatDateTime(latestActivity)) + '</dd></div>'
                + '<div><dt>' + escapeHtml(t('types')) + '</dt><dd>' + escapeHtml(Object.keys(types).join(', ') || '-') + '</dd></div>'
                + '<div><dt>' + escapeHtml(t('modelsUsed')) + '</dt><dd>' + escapeHtml(Object.keys(models).join(', ') || '-') + '</dd></div>'
                + '</dl></div>'
                + '<div class="cbia-usage-detail-rows">' + detailRows.map(function (pair) {
                    return '<div class="cbia-usage-detail-row"><div class="cbia-usage-detail-label">' + escapeHtml(pair[0]) + '</div><div class="cbia-usage-detail-value">' + escapeHtml(pair[1]) + '</div></div>';
                }).join('') + '</div>'
                + '<div class="cbia-usage-detail-actions">' + editAction + '</div>'
                + '</div>';
        }

        function renderTable(filtered) {
            var body = document.getElementById('cbia-usage-table-body');
            var meta = document.getElementById('cbia-usage-table-meta');
            if (!body) return;
            var visible = filtered.slice(0, 50);
            if (meta) {
                meta.textContent = rowsLimited
                    ? formatTemplate(t('quickTable'), [numberFormat(visible.length), numberFormat(filtered.length), numberFormat(Math.min(rows.length, recentRowsLimit)), numberFormat(totalRows)])
                    : formatTemplate(t('showingEvents'), [numberFormat(visible.length), numberFormat(filtered.length)]);
            }
            if (!visible.length) {
                body.innerHTML = '<tr><td colspan="' + (canViewCosts ? '7' : '6') + '" class="cbia-usage-table-placeholder">' + escapeHtml(t('noLogs')) + '</td></tr>';
                selectedKey = '';
                renderDetail(null, filtered);
                return;
            }
            body.innerHTML = visible.map(function (row) {
                var typeLabel = row.type_label || row.type || '-';
                var post = '<span class="cbia-usage-source-title">' + escapeHtml(row.post_title || '-') + '</span><small class="cbia-usage-source-meta">#' + escapeHtml(row.post_id || 0) + (row.user_name ? ' · ' + escapeHtml(row.user_name) : '') + '</small>';
                var tokens = Number(row.tokens_total || 0) > 0 ? numberFormat(row.tokens_total) : t('tokensNotApplicable');
                var cost = hasNumericValue(row.cost_eur) ? currencyFormat(row.cost_eur) : t('unknownCost');
                var statusKey = String(row.status || (row.ok ? 'success' : 'error'));
                var status = row.status_label || t(statusKey) || statusKey;
                return '<tr data-row-key="' + escapeHtml(rowKey(row)) + '" tabindex="0" role="button" aria-label="' + escapeHtml(typeLabel + ': ' + (row.post_title || '-')) + '">'
                    + '<td><span class="cbia-usage-date">' + escapeHtml(formatDateTime(row.ts)) + '</span></td>'
                    + '<td><div class="cbia-usage-source">' + post + '</div></td>'
                    + '<td><span class="cbia-usage-type-badge type-' + escapeHtml(row.type || '') + '">' + escapeHtml(typeLabel) + '</span></td>'
                    + '<td><code class="cbia-usage-model-code">' + escapeHtml(row.model || '-') + '</code></td>'
                    + '<td><span class="cbia-usage-metric">' + escapeHtml(tokens) + '</span></td>'
                    + (canViewCosts ? '<td><span class="cbia-usage-cost">' + escapeHtml(cost) + '</span></td>' : '')
                    + '<td><span class="cbia-usage-status-badge status-' + (row.ok ? 'ok' : 'error') + '">' + escapeHtml(status) + '</span></td>'
                    + '</tr>';
            }).join('');
            if (!selectedKey || !visible.some(function (row) { return rowKey(row) === selectedKey; })) selectedKey = rowKey(visible[0]);
            Array.prototype.slice.call(body.querySelectorAll('tr[data-row-key]')).forEach(function (node) {
                function select() {
                    selectedKey = node.getAttribute('data-row-key') || '';
                    Array.prototype.slice.call(body.querySelectorAll('tr[data-row-key]')).forEach(function (candidate) {
                        candidate.classList.toggle('is-active', candidate === node);
                    });
                    var selected = visible.find(function (row) { return rowKey(row) === selectedKey; }) || visible[0];
                    renderDetail(selected, filtered);
                }
                node.addEventListener('click', select);
                node.addEventListener('keydown', function (event) {
                    if (event.key !== 'Enter' && event.key !== ' ') return;
                    event.preventDefault();
                    select();
                });
                node.classList.toggle('is-active', node.getAttribute('data-row-key') === selectedKey);
            });
            renderDetail(visible.find(function (row) { return rowKey(row) === selectedKey; }) || visible[0], filtered);
        }

        function updateFilterUi(filtered) {
            var count = secondaryFilterCount();
            var countNode = document.getElementById('cbia-usage-more-count');
            var details = document.getElementById('cbia-usage-more-filters');
            var summary = document.getElementById('cbia-usage-filter-summary');
            if (countNode) countNode.textContent = numberFormat(count);
            if (details && count > 0) details.open = true;
            if (summary) summary.textContent = numberFormat(filtered.length) + ' ' + t('events') + (count ? ' · ' + numberFormat(count) + ' ' + t('activeFilters') : ' · ' + t('noActiveFilters'));
        }

        function updateExport() {
            var button = document.getElementById('cbia-usage-export');
            var model = controls.model ? controls.model.value : '';
            if (button) {
                try {
                    var url = new URL(button.href, window.location.origin);
                    url.searchParams.set('usage_model', model);
                    button.href = url.toString();
                } catch (_error) {}
            }
            Array.prototype.slice.call(root.querySelectorAll('a.cbia-usage-range-button')).forEach(function (link) {
                try {
                    var url = new URL(link.href, window.location.origin);
                    url.searchParams.set('usage_model', model);
                    link.href = url.toString();
                } catch (_error) {}
            });
            var customModel = document.querySelector('#cbia-usage-custom-range input[name="usage_model"]');
            if (customModel) customModel.value = model;
            try {
                var pageUrl = new URL(window.location.href);
                if (model) pageUrl.searchParams.set('usage_model', model); else pageUrl.searchParams.delete('usage_model');
                window.history.replaceState(window.history.state, '', pageUrl.toString());
            } catch (_error) {}
        }

        function refresh() {
            var filtered = filteredRows();
            var summary = activeSummary(filtered);
            lastFilteredRows = filtered;
            updateExport();
            updateFilterUi(filtered);
            renderKpis(summary);
            renderActivity(filtered, summary);
            renderType(summary);
            imageBreakdowns(filtered);
            renderMonthly();
            updateCostControl(filtered, summary);
            renderTable(filtered);
        }

        function bindCostDrawer() {
            var drawer = document.getElementById('cbia-usage-cost-drawer');
            var backdrop = document.getElementById('cbia-usage-cost-drawer-backdrop');
            var openButton = document.getElementById('cbia-usage-open-cost-drawer');
            var closeButton = document.getElementById('cbia-usage-close-cost-drawer');
            if (!drawer || !openButton) return;
            var previousFocus = null;
            function setOpen(open) {
                drawer.hidden = !open;
                drawer.classList.toggle('is-open', open);
                if (backdrop) backdrop.hidden = !open;
                if (backdrop) backdrop.classList.toggle('is-open', open);
                document.body.classList.toggle('cbia-usage-drawer-open', open);
                openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) {
                    previousFocus = document.activeElement;
                    if (closeButton) closeButton.focus();
                } else if (previousFocus && previousFocus.focus) {
                    previousFocus.focus();
                }
            }
            openButton.addEventListener('click', function () { setOpen(true); });
            if (closeButton) closeButton.addEventListener('click', function () { setOpen(false); });
            if (backdrop) backdrop.addEventListener('click', function () { setOpen(false); });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !drawer.hidden) setOpen(false);
                if (event.key !== 'Tab' || drawer.hidden) return;
                var focusable = drawer.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled])');
                if (!focusable.length) return;
                var first = focusable[0];
                var last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });
            var unknownButtons = [
                document.getElementById('cbia-usage-show-unknown-costs'),
                document.getElementById('cbia-usage-drawer-show-unknown')
            ];
            unknownButtons.forEach(function (button) {
                if (!button) return;
                button.addEventListener('click', function () {
                    if (controls.costStatus) controls.costStatus.value = 'unknown';
                    setOpen(false);
                    refresh();
                    var table = document.getElementById('cbia-usage-events-title');
                    if (table) table.scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
                });
            });
        }

        Object.keys(controls).forEach(function (key) {
            var control = controls[key];
            if (!control) return;
            control.addEventListener(key === 'search' ? 'input' : 'change', refresh);
        });

        var clear = document.getElementById('cbia-usage-clear-filters');
        if (clear) clear.addEventListener('click', function () {
            Object.keys(controls).forEach(function (key) {
                if (controls[key]) controls[key].value = '';
            });
            refresh();
            if (controls.model) controls.model.focus();
        });

        var customToggle = document.getElementById('cbia-usage-custom-toggle');
        var customRange = document.getElementById('cbia-usage-custom-range');
        if (customToggle && customRange) customToggle.addEventListener('click', function () {
            var willOpen = customRange.hidden;
            customRange.hidden = !willOpen;
            customToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            if (willOpen) {
                var input = customRange.querySelector('input[type="date"]');
                if (input) input.focus();
            }
        });

        Array.prototype.slice.call(root.querySelectorAll('[data-usage-metric]')).forEach(function (button) {
            if (button.getAttribute('data-usage-metric') === 'cost' && !canViewCosts) {
                button.hidden = true;
                return;
            }
            button.classList.toggle('is-active', button.getAttribute('data-usage-metric') === currentMetric);
            button.setAttribute('aria-pressed', button.getAttribute('data-usage-metric') === currentMetric ? 'true' : 'false');
            button.addEventListener('click', function () {
                currentMetric = button.getAttribute('data-usage-metric') || 'calls';
                Array.prototype.slice.call(root.querySelectorAll('[data-usage-metric]')).forEach(function (candidate) {
                    var active = candidate === button;
                    candidate.classList.toggle('is-active', active);
                    candidate.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
                renderActivity(lastFilteredRows, activeSummary(lastFilteredRows));
            });
        });

        Array.prototype.slice.call(root.querySelectorAll('.cbia-usage-info-toggle')).forEach(function (button) {
            button.addEventListener('click', function () {
                var target = document.getElementById(button.getAttribute('aria-controls'));
                if (!target) return;
                target.hidden = !target.hidden;
                button.setAttribute('aria-expanded', target.hidden ? 'false' : 'true');
            });
        });

        Array.prototype.slice.call(root.querySelectorAll('.cbia-usage-change-period')).forEach(function (button) {
            button.addEventListener('click', function () {
                var ranges = root.querySelector('.cbia-usage-quick-ranges');
                if (!ranges) return;
                ranges.scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center'});
                var active = ranges.querySelector('.is-active') || ranges.querySelector('button, a');
                if (active) active.focus();
            });
        });

        window.addEventListener('resize', function () {
            if (resizeFrame) window.cancelAnimationFrame(resizeFrame);
            resizeFrame = window.requestAnimationFrame(function () {
                resizeFrame = 0;
                var summary = activeSummary(lastFilteredRows);
                renderActivity(lastFilteredRows, summary);
                renderMonthly();
            });
        });

        populateModels();
        bindCostDrawer();
        root.classList.remove('is-loading');
        root.setAttribute('aria-busy', 'false');
        var loading = document.getElementById('cbia-usage-loading-banner');
        if (loading) loading.hidden = true;
        refresh();
    }

    function initUsageRecalculationActions() {
        var section = document.querySelector('.cbia-usage-recalculation-actions');
        if (!section || section.getAttribute('data-cbia-recalc-bound') === '1') return;
        section.setAttribute('data-cbia-recalc-bound', '1');
        var ajaxUrl = String(section.getAttribute('data-ajax-url') || '');
        var nonce = String(section.getAttribute('data-nonce') || '');
        var result = document.getElementById('cbia-usage-recalc-result');
        var dryRun = document.getElementById('cbia-usage-recalc-dry-run');
        var applyButton = document.getElementById('cbia-usage-recalc-apply');
        function run(apply) {
            if (apply && !window.confirm(section.getAttribute('data-confirm') || '')) return;
            var body = new URLSearchParams();
            body.set('action', 'cbia_usage_recalculate_history');
            body.set('_ajax_nonce', nonce);
            body.set('apply', apply ? '1' : '0');
            if (apply) body.set('confirm', 'RECALCULATE');
            if (result) result.textContent = section.getAttribute('data-running') || '';
            [dryRun, applyButton].forEach(function (button) { if (button) button.disabled = true; });
            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: body.toString()
            }).then(function (response) {
                return response.json();
            }).then(function (json) {
                if (!json || !json.success) throw new Error(json && json.data && json.data.message ? json.data.message : section.getAttribute('data-failed'));
                var data = json.data || {};
                var template = section.getAttribute('data-result') || '';
                var message = template
                    .replace('%1$s', String(Number(data.rows_scanned || 0)))
                    .replace('%2$s', String(Number(data.exact || 0)))
                    .replace('%3$s', String(Number(data.estimated || 0)))
                    .replace('%4$s', String(Number(data.unknown || 0)));
                if (data.backup_option) message += ' · ' + (section.getAttribute('data-backup') || '') + ': ' + data.backup_option;
                if (result) result.textContent = message;
                if (apply) window.location.reload();
            }).catch(function (error) {
                if (result) result.textContent = error.message || section.getAttribute('data-failed') || '';
            }).finally(function () {
                [dryRun, applyButton].forEach(function (button) { if (button) button.disabled = false; });
            });
        }
        if (dryRun) dryRun.addEventListener('click', function () { run(false); });
        if (applyButton) applyButton.addEventListener('click', function () { run(true); });
    }

    function initAiComposer() {
        var metabox = document.getElementById('cbia_ai_composer_box');
        var root = document.getElementById('cbia-ai-composer');
        if (!root) {
            // Postbox can render late in some admin/editor combinations.
            setTimeout(initAiComposer, 250);
            return;
        }
        if (root.getAttribute('data-cbia-bound') === '1') return;
        root.setAttribute('data-cbia-bound', '1');
        var modal = document.getElementById('cbia-ai-composer-modal');
        var modalClose = document.getElementById('cbia-ai-composer-close');
        var modalOpeners = [];
        if (modal && modal.parentNode !== document.body) {
            try { document.body.appendChild(modal); } catch (eMove) {}
        }

        function openComposerModal() {
            modal = document.getElementById('cbia-ai-composer-modal') || modal;
            root = document.getElementById('cbia-ai-composer') || root;
            if (!modal && !root) return false;
            if (modal && modal.parentNode !== document.body) {
                try { document.body.appendChild(modal); } catch (eMove2) {}
            }
            if (!isGenerating) {
                hydrateComposerSnapshot();
            }
            if (titleInput) {
                var editorTitleNow = getCurrentEditorTitle();
                if (editorTitleNow && !String(titleInput.value || '').trim()) {
                    titleInput.value = editorTitleNow;
                }
                if (!editorTitleNow && String(titleInput.value || '').trim()) {
                    syncEditorTitle(String(titleInput.value || '').trim());
                }
            }
            if (modal) {
                if (root) {
                    root.style.display = '';
                }
                modal.style.display = 'flex';
                document.body.classList.add('cbia-modal-open');
                try { modal.focus(); } catch (eFocus) {}
                return true;
            }
            if (root) {
                root.style.display = '';
                try { root.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (eS) {}
                return true;
            }
            return false;
        }
        window.CBIA_AI_OPEN_MODAL = function (e) {
            if (e && e.preventDefault) e.preventDefault();
            return openComposerModal();
        };
        if (!document.body.getAttribute('data-cbia-composer-delegated')) {
            document.body.setAttribute('data-cbia-composer-delegated', '1');
            document.addEventListener('click', function (e) {
                var t = e.target;
                if (!t || !t.closest) return;
                var trigger = t.closest('#cbia-ai-composer-open, #cbia-ai-composer-open-server, .cbia-ai-launch-btn');
                if (!trigger) return;
                e.preventDefault();
                openComposerModal();
            }, true);
        }

        function getEditorElementorRef() {
            // Elementor can live in the top header, not only inside post-body-content.
            var scoped = document.querySelector('#elementor-switch-mode-button');
            if (scoped) return scoped.closest('a,button') || scoped;
            scoped = document.querySelector('.elementor-edit-with-button');
            if (scoped) return scoped.closest('a,button') || scoped;
            scoped = document.querySelector('a[href*="action=elementor"], a[href*="elementor"], button[data-cta-link*="elementor"]');
            if (scoped) return scoped.closest('a,button') || scoped;
            scoped = document.querySelector('a[href*=\"elementor\"], button[href*=\"elementor\"], [data-elementor-open=\"1\"]');
            if (scoped) return scoped.closest('a,button') || scoped;
            var nodes = document.querySelectorAll('button, a');
            for (var i = 0; i < nodes.length; i++) {
                var txt = String(nodes[i].textContent || '').trim().toLowerCase();
                if (txt.indexOf('elementor') >= 0) return nodes[i];
            }
            return null;
        }
        function getEditorFallbackRef() {
            // Gutenberg fallback anchors (when Elementor button is not present).
            var scoped = document.querySelector('.edit-post-header-toolbar__left');
            if (scoped) return scoped;
            scoped = document.querySelector('.interface-interface-skeleton__header .editor-header');
            if (scoped) return scoped;
            scoped = document.querySelector('.editor-header__settings');
            if (scoped) return scoped;
            scoped = document.querySelector('.editor-post-title__block');
            if (scoped) return scoped;
            scoped = document.querySelector('.interface-interface-skeleton__content');
            if (scoped) return scoped;
            return null;
        }
        function getWriteForMeRef() {
            var candidates = document.querySelectorAll(
                '.interface-interface-skeleton__header button, ' +
                '.interface-interface-skeleton__header a, ' +
                '.edit-post-header button, .edit-post-header a, ' +
                '.editor-header button, .editor-header a'
            );
            for (var i = 0; i < candidates.length; i++) {
                var node = candidates[i];
                var txt = String(node.textContent || '').trim().toLowerCase();
                if (txt.indexOf('write for me') >= 0) return node;
            }
            return null;
        }
        function placeAsFirstHeaderAction(btn, container) {
            if (!btn || !container) return false;
            var firstAction = container.querySelector('button, a');
            if (firstAction && firstAction !== btn) {
                container.insertBefore(btn, firstAction);
                return true;
            }
            if (container.firstElementChild !== btn) {
                if (typeof container.prepend === 'function') container.prepend(btn);
                else container.insertBefore(btn, container.firstChild);
                return true;
            }
            return true;
        }

        function normalizeLauncherContainer(container) {
            if (!container || !container.style) return;
            try {
                var computed = window.getComputedStyle(container);
                if (!computed || computed.display === 'block' || computed.display === 'inline' || computed.display === 'inline-block') {
                    container.style.display = 'inline-flex';
                }
            } catch (_eStyle) {
                container.style.display = 'inline-flex';
            }
            container.style.alignItems = 'center';
            container.style.flexDirection = 'row';
            container.style.gap = '8px';
            container.style.flexWrap = 'nowrap';
        }

        function closeComposerModal() {
            try { persistComposerSnapshot(); } catch (eSave) {}
            if (modal) {
                modal.style.display = 'none';
            }
            if (typeof closeComposerModalFallback === 'function') {
                closeComposerModalFallback();
            } else if (root && (!modal || !modal.contains(root))) {
                root.style.display = 'none';
            }
            document.body.classList.remove('cbia-modal-open');
        }

        function ensureComposerLauncher() {
            var serverBtn = document.getElementById('cbia-ai-composer-open-server');
            var ref = getEditorElementorRef();
            var writeForMeRef = getWriteForMeRef();
            var fallbackRef = getEditorFallbackRef();

            var btn = document.getElementById('cbia-ai-composer-open');
            if (!btn) {
                btn = document.createElement('button');
                btn.type = 'button';
                btn.id = 'cbia-ai-composer-open';
                btn.textContent = 'Crear con IA';
            }
            btn.id = 'cbia-ai-composer-open';
            if (ref) {
                btn.className = ((ref.className || '').trim() || 'button button-primary') + ' cbia-ai-launch-btn';
                btn.style.display = 'inline-flex';
                btn.style.verticalAlign = 'middle';
                btn.style.marginRight = '8px';
                btn.style.marginLeft = '0';
                btn.style.marginBottom = '0';
                btn.style.whiteSpace = 'nowrap';
                btn.style.flex = '0 0 auto';
            } else if (writeForMeRef) {
                btn.className = (writeForMeRef.className || 'components-button is-secondary') + ' cbia-ai-launch-btn cbia-ai-launch-btn-gb';
            } else if (fallbackRef && (fallbackRef.classList.contains('edit-post-header-toolbar__left') || fallbackRef.classList.contains('editor-header__settings') || fallbackRef.classList.contains('editor-header'))) {
                btn.className = 'components-button is-secondary cbia-ai-launch-btn cbia-ai-launch-btn-gb';
            } else {
                btn.className = 'button button-primary cbia-ai-launch-btn';
            }
            if (!btn.getAttribute('data-cbia-bound')) {
                btn.setAttribute('data-cbia-bound', '1');
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    window.CBIA_AI_OPEN_MODAL(e);
                });
            }
            // Keep it right next to Elementor CTA in the same row when available.
            if (ref && ref.parentNode) {
                normalizeLauncherContainer(ref.parentNode);
                if (btn.parentNode !== ref.parentNode) {
                    ref.parentNode.insertBefore(btn, ref);
                } else if (btn.nextElementSibling !== ref) {
                    ref.insertAdjacentElement('beforebegin', btn);
                }
            } else if (writeForMeRef && writeForMeRef.parentNode) {
                normalizeLauncherContainer(writeForMeRef.parentNode);
                if (btn.parentNode !== writeForMeRef.parentNode) {
                    writeForMeRef.parentNode.insertBefore(btn, writeForMeRef);
                } else if (btn.nextElementSibling !== writeForMeRef) {
                    writeForMeRef.insertAdjacentElement('beforebegin', btn);
                }
            } else if (fallbackRef) {
                // Gutenberg: place as first action in the detected header area.
                normalizeLauncherContainer(fallbackRef);
                if (btn.parentNode !== fallbackRef) {
                    fallbackRef.appendChild(btn);
                }
                placeAsFirstHeaderAction(btn, fallbackRef);
            } else if (serverBtn) {
                if (!serverBtn.getAttribute('data-cbia-bound')) {
                    serverBtn.setAttribute('data-cbia-bound', '1');
                    serverBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        window.CBIA_AI_OPEN_MODAL(e);
                    });
                }
                btn = serverBtn;
            } else {
                return;
            }
            if (modalOpeners.indexOf(btn) === -1) {
                modalOpeners.push(btn);
            }

            // Remove any extra launcher copies (keep only the one next to Elementor).
            var allLaunchers = document.querySelectorAll('#cbia-ai-composer-open, #cbia-ai-composer-open-server, .cbia-ai-launch-btn');
            allLaunchers.forEach(function (node) {
                if (!node || node === btn) return;
                if (node.id === 'elementor-switch-mode-button') return;
                try { node.remove(); } catch (e) { node.style.display = 'none'; }
            });
        }

        function bindComposerCloseButton() {
            modalClose = document.getElementById('cbia-ai-composer-close') || modalClose;
            if (!modalClose || modalClose.getAttribute('data-cbia-close-bound') === '1') return;
            modalClose.setAttribute('data-cbia-close-bound', '1');
            modalClose.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                closeComposerModal();
            });
        }
        bindComposerCloseButton();
        if (modal) {
            modal.addEventListener('click', function (e) {
                var closeTrigger = e.target && e.target.closest ? e.target.closest('#cbia-ai-composer-close') : null;
                if (closeTrigger) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeComposerModal();
                    return;
                }
                if (e.target === modal) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (imageModal && imageModal.style.display === 'flex') {
                closeImageModal();
                return;
            }
            if (keyModal && keyModal.style.display === 'flex') {
                closeKeyModal();
                return;
            }
            closeComposerModal();
        });
        ensureComposerLauncher();
        bindComposerCloseButton();
        setTimeout(ensureComposerLauncher, 400);
        setTimeout(bindComposerCloseButton, 400);
        setTimeout(ensureComposerLauncher, 1200);
        setTimeout(bindComposerCloseButton, 1200);
        setTimeout(ensureComposerLauncher, 2500);
        setTimeout(bindComposerCloseButton, 2500);
        if (window.MutationObserver) {
            var launcherObserver = new MutationObserver(function () {
                ensureComposerLauncher();
                bindComposerCloseButton();
            });
            launcherObserver.observe(document.body, { childList: true, subtree: true });
            setTimeout(function () {
                try { launcherObserver.disconnect(); } catch (e) {}
            }, 10000);
        }

        if (metabox) {
            metabox.style.display = 'none';
        }
        var abb = window.CBIAAdmin || {};
        var aiCfg = abb.aiComposer || {};
        var ajaxUrl = abb.ajaxUrl || root.getAttribute('data-ajax-url') || window.ajaxurl || '';
        var nonce = abb.nonce || root.getAttribute('data-nonce') || '';
        if (!aiCfg.defaultLength) aiCfg.defaultLength = root.getAttribute('data-default-length') || 'medium';
        if (!aiCfg.defaultLanguage) aiCfg.defaultLanguage = root.getAttribute('data-default-language') || 'English';
        if (!aiCfg.defaultImagesLimit) aiCfg.defaultImagesLimit = parseInt(root.getAttribute('data-default-images-limit') || '3', 10) || 3;
        if (!aiCfg.textProvider) aiCfg.textProvider = root.getAttribute('data-text-provider') || 'openai';
        if (!aiCfg.imageProvider) aiCfg.imageProvider = root.getAttribute('data-image-provider') || 'openai';
        if (!aiCfg.textModelLists) {
            try { aiCfg.textModelLists = JSON.parse(root.getAttribute('data-text-model-lists') || '{}') || {}; } catch (e0) { aiCfg.textModelLists = {}; }
        }
        if (!aiCfg.imageModelLists) {
            try { aiCfg.imageModelLists = JSON.parse(root.getAttribute('data-image-model-lists') || '{}') || {}; } catch (e1) { aiCfg.imageModelLists = {}; }
        }
        if (!aiCfg.currentTextModels) {
            try { aiCfg.currentTextModels = JSON.parse(root.getAttribute('data-current-text-models') || '{}') || {}; } catch (e2) { aiCfg.currentTextModels = {}; }
        }
        if (!aiCfg.currentImageModels) {
            try { aiCfg.currentImageModels = JSON.parse(root.getAttribute('data-current-image-models') || '{}') || {}; } catch (e3) { aiCfg.currentImageModels = {}; }
        }
        if (!aiCfg.providerKeyState) {
            try { aiCfg.providerKeyState = JSON.parse(root.getAttribute('data-provider-key-state') || '{}') || {}; } catch (e4) { aiCfg.providerKeyState = {}; }
        }
        if (typeof aiCfg.textApiConfigured === 'undefined') aiCfg.textApiConfigured = root.getAttribute('data-text-api-configured') === '1';
        if (typeof aiCfg.imageApiConfigured === 'undefined') aiCfg.imageApiConfigured = root.getAttribute('data-image-api-configured') === '1';
        if (!aiCfg.settingsUrl) aiCfg.settingsUrl = root.getAttribute('data-settings-url') || '';
        if (typeof aiCfg.internalImagesEnabled === 'undefined') {
            aiCfg.internalImagesEnabled = root.getAttribute('data-cap-internal-images') !== '0';
        }

        var titleInput = document.getElementById('cbia-ai-title');
        var lengthSelect = document.getElementById('cbia-ai-length');
        var imagesSelect = document.getElementById('cbia-ai-images');
        var internalStyleSelect = document.getElementById('cbia-ai-internal-style');
        var promptProfileSelect = document.getElementById('cbia-ai-prompt-profile');
        var includeFaqToggle = document.getElementById('cbia-ai-include-faq');
        var includeExamplesToggle = document.getElementById('cbia-ai-include-examples');
        var lengthHelp = document.getElementById('cbia-ai-length-help');
        var temperatureInput = document.getElementById('cbia-ai-temperature');
        var languageSelect = document.getElementById('cbia-ai-language');
        var summary = document.getElementById('cbia-ai-summary');
        var generateBtn = document.getElementById('cbia-ai-generate');
        var improveTextBtn = document.getElementById('cbia-ai-improve-text');
        var insertBtn = document.getElementById('cbia-ai-insert');
        var completeMissingBtn = document.getElementById('cbia-ai-complete-missing');
        var cfgTextBtn = document.getElementById('cbia-ai-config-text');
        var cfgImageBtn = document.getElementById('cbia-ai-config-image');
        var chipText = document.getElementById('cbia-ai-chip-text');
        var chipImage = document.getElementById('cbia-ai-chip-image');
        var allowNoImageToggle = document.getElementById('cbia-ai-allow-no-image');
        var settingsBtn = document.getElementById('cbia-ai-open-settings');
        var status = document.getElementById('cbia-ai-status');
        var preview = document.getElementById('cbia-ai-preview');
        var featuredPreview = document.getElementById('cbia-ai-featured-preview');
        var wordCount = document.getElementById('cbia-ai-word-count');
        var keyModal = document.getElementById('cbia-ai-key-modal');
        var keyTitle = document.getElementById('cbia-ai-key-title');
        var keyHelp = document.getElementById('cbia-ai-key-help');
        var keyProviderSelect = document.getElementById('cbia-ai-key-provider');
        var keyModelSelect = document.getElementById('cbia-ai-key-model');
        var keyInput = document.getElementById('cbia-ai-key-input');
        var keySave = document.getElementById('cbia-ai-key-save');
        var keyTest = document.getElementById('cbia-ai-key-test');
        var keyCancel = document.getElementById('cbia-ai-key-cancel');
        var keyClose = document.getElementById('cbia-ai-key-close');
        var keyStatus = document.getElementById('cbia-ai-key-status');
        var imageCardsRoot = document.getElementById('cbia-ai-image-cards');
        var imageModal = document.getElementById('cbia-ai-image-modal');
        var imageModalTitle = document.getElementById('cbia-ai-image-title');
        var imageModalHelp = document.getElementById('cbia-ai-image-help');
        var imageModalPrompt = document.getElementById('cbia-ai-image-prompt');
        var imageModalRegenerate = document.getElementById('cbia-ai-image-regenerate');
        var imageModalLibrary = document.getElementById('cbia-ai-image-library');
        var imageModalCancel = document.getElementById('cbia-ai-image-cancel');
        var imageModalClose = document.getElementById('cbia-ai-image-close');
        var imageModalStatus = document.getElementById('cbia-ai-image-status');
        var activeKeyScope = '';
        var activeKeyProvider = '';
        var finalHtml = '';
        var finalTitle = '';
        var finalFocusKeyphrase = '';
        var finalMetaDescription = '';
        var finalCategoryIds = [];
        var finalTagIds = [];
        var finalTagNames = [];
        var finalFeaturedAttachId = 0;
        var finalFeaturedImage = null;
        var previewToken = '';
        var receivedInternalImages = [];
        var changingImageSlot = null;
        var isGenerating = false;
        var phaseTimer = null;
        var phaseStartedAt = 0;
        var typingTimer = null;
        var typingSeq = 0;
        var progressiveQueue = [];
        var progressiveTimer = null;
        var progressiveLastHtml = '';
        var latestStreamHtml = '';
        var freezeTextOnQueueDrain = false;
        var streamTextLocked = false;
        var streamHasTextProgress = false;
        var streamContentSeeded = false;
        var suppressComposerUnloadPrompt = false;

        function refreshKeyModalRefs() {
            keyModal = document.getElementById('cbia-ai-key-modal') || keyModal;
            keyTitle = document.getElementById('cbia-ai-key-title') || keyTitle;
            keyHelp = document.getElementById('cbia-ai-key-help') || keyHelp;
            keyProviderSelect = document.getElementById('cbia-ai-key-provider') || keyProviderSelect;
            keyModelSelect = document.getElementById('cbia-ai-key-model') || keyModelSelect;
            keyInput = document.getElementById('cbia-ai-key-input') || keyInput;
            keySave = document.getElementById('cbia-ai-key-save') || keySave;
            keyTest = document.getElementById('cbia-ai-key-test') || keyTest;
            keyCancel = document.getElementById('cbia-ai-key-cancel') || keyCancel;
            keyClose = document.getElementById('cbia-ai-key-close') || keyClose;
            keyStatus = document.getElementById('cbia-ai-key-status') || keyStatus;
            if (keyModal && keyModal.parentNode !== document.body) {
                try { document.body.appendChild(keyModal); } catch (_eMoveKeyModal) {}
            }
            return !!keyModal;
        }

        try {
            window.addEventListener('beforeunload', function (evt) {
                if (!suppressComposerUnloadPrompt) return;
                try { evt.stopImmediatePropagation(); } catch (_eStopUnload) {}
                try { delete evt.returnValue; } catch (_eReturnUnload) {}
            }, true);
        } catch (_eBeforeUnload) {}

        if (!aiCfg.internalImagesEnabled) {
            if (imagesSelect) {
                imagesSelect.value = '0';
                imagesSelect.disabled = true;
            }
            if (internalStyleSelect) {
                internalStyleSelect.disabled = true;
            }
        }

        function setStatus(msg, isError) {
            if (!status) return;
            status.textContent = msg || '';
            status.style.color = isError ? '#b32d2e' : '#50575e';
        }

        function setWordCount(value) {
            if (!wordCount) return;
            var count = parseInt(value || 0, 10);
            if (isNaN(count) || count < 0) count = 0;
            wordCount.textContent = count + ' palabras';
        }

        function countWordsFromHtml(html) {
            var plain = String(html || '')
                .replace(/<style[\s\S]*?<\/style>/gi, ' ')
                .replace(/<script[\s\S]*?<\/script>/gi, ' ')
                .replace(/<[^>]+>/g, ' ')
                .replace(/&nbsp;/gi, ' ')
                .replace(/\s+/g, ' ')
                .trim();
            if (!plain) return 0;
            var tokens = plain.match(/[A-Za-z0-9\u00C0-\u024F]+/g);
            return tokens ? tokens.length : 0;
        }

        function escAttr(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function getInternalImageClassAttr() {
            return (internalStyleSelect && internalStyleSelect.value === 'banner')
                ? ' class="cbia-banner lazyloaded"'
                : '';
        }

        function buildInternalImageWrap(url, alt, slotIndex) {
            var slotAttr = (parseInt(slotIndex || 0, 10) > 0) ? (' data-cbia-slot="' + String(parseInt(slotIndex, 10)) + '"') : '';
            return '<div class="cbia-inline-image-wrap"' + slotAttr + ' style="margin:30px 0;"><img decoding="async" loading="lazy"' + getInternalImageClassAttr() + ' src="' + escAttr(url) + '" alt="' + escAttr(alt || 'Generated image') + '" style="display:block;width:100%;height:auto;margin:20px 0;" /></div>';
        }

        function getOrderedInternalImages(images) {
            if (!Array.isArray(images) || !images.length) return [];
            var byIdx = {};
            var noIdx = [];
            var seenNoIdxUrl = {};
            images.forEach(function (img) {
                if (!img || !img.url) return;
                var url = String(img.url || '').trim();
                if (!url) return;
                var alt = String(img.alt || img.desc || 'Generated image');
                var idx = parseInt(img.idx || 0, 10) || 0;
                if (idx > 0) {
                    byIdx[idx] = { url: url, alt: alt, idx: idx };
                    return;
                }
                if (seenNoIdxUrl[url]) return;
                seenNoIdxUrl[url] = true;
                noIdx.push({ url: url, alt: alt, idx: 0 });
            });
            var out = [];
            var seenOutUrl = {};
            Object.keys(byIdx)
                .map(function (k) { return parseInt(k, 10) || 0; })
                .filter(function (k) { return k > 0; })
                .sort(function (a, b) { return a - b; })
                .forEach(function (k) {
                    var row = byIdx[k];
                    if (!row) return;
                    if (seenOutUrl[row.url]) return;
                    seenOutUrl[row.url] = true;
                    out.push(row);
                });
            noIdx.forEach(function (row) {
                if (!row || !row.url) return;
                if (seenOutUrl[row.url]) return;
                seenOutUrl[row.url] = true;
                out.push(row);
            });
            return out;
        }

        function normalizeInternalImageSlots(images, maxSlots) {
            var ordered = getOrderedInternalImages(images || []);
            var limit = parseInt(maxSlots || 0, 10) || 0;
            if (limit > 0) {
                ordered = ordered.slice(0, limit);
            }
            return ordered.map(function (img, idx) {
                var next = Object.assign({}, img || {});
                next.idx = idx + 1;
                if (!next.section) {
                    next.section = (idx >= 2) ? 'faq' : 'body';
                }
                return next;
            });
        }

        function resetFeaturedPreview() {
            if (featuredPreview) {
                featuredPreview.innerHTML = '<em>Pending generation...</em>';
            }
            if (!finalFeaturedImage || typeof finalFeaturedImage !== 'object') {
                finalFeaturedImage = {};
            }
            finalFeaturedImage.url = '';
            renderImageCards();
        }

        function setFeaturedPreview(url, message) {
            var imgUrl = String(url || '').trim();
            if (imgUrl) {
                finalFeaturedImage = finalFeaturedImage || {};
                finalFeaturedImage.url = imgUrl;
                finalFeaturedImage.attach_id = parseInt(finalFeaturedAttachId || (finalFeaturedImage.attach_id || 0), 10) || 0;
                finalFeaturedImage.desc = String(finalFeaturedImage.desc || finalTitle || 'Featured image');
                if (featuredPreview) {
                    featuredPreview.innerHTML = '<img src="' + escAttr(imgUrl) + '" alt="Featured image preview" />';
                }
                renderImageCards();
                return;
            }
            if (featuredPreview) {
                featuredPreview.textContent = '';
                var em = document.createElement('em');
                em.textContent = String(message || '').trim() || 'Pending generation...';
                featuredPreview.appendChild(em);
            }
            renderImageCards();
        }

        function resolveAttachmentUrlById(attachId) {
            var id = parseInt(attachId || 0, 10) || 0;
            if (id <= 0) return Promise.resolve('');
            try {
                if (window.wp && window.wp.media && typeof window.wp.media.attachment === 'function') {
                    var attachment = window.wp.media.attachment(id);
                    if (attachment) {
                        var json = attachment.toJSON ? attachment.toJSON() : null;
                        if (json && json.url) {
                            return Promise.resolve(String(json.url || ''));
                        }
                        if (typeof attachment.fetch === 'function') {
                            return attachment.fetch().then(function () {
                                var next = attachment.toJSON ? attachment.toJSON() : null;
                                return next && next.url ? String(next.url || '') : '';
                            }).catch(function () {
                                return '';
                            });
                        }
                    }
                }
            } catch (_e) {}
            return Promise.resolve('');
        }

        function enforceInternalImageStyle(html, forcedMode) {
            var source = String(html || '');
            if (!source) return source;
            var wrap = document.createElement('div');
            wrap.innerHTML = source;
            var mode = String(forcedMode || (internalStyleSelect ? (internalStyleSelect.value || '') : '') || 'banner');
            var isBanner = (mode === 'banner');
            wrap.querySelectorAll('img').forEach(function (img) {
                if (isBanner) {
                    img.classList.add('cbia-banner');
                    img.classList.add('lazyloaded');
                } else {
                    img.classList.remove('cbia-banner');
                    img.classList.remove('lazyloaded');
                    if (!img.className) img.removeAttribute('class');
                }
            });
            return wrap.innerHTML;
        }

        function fillPreviewSlotsFromImages(images) {
            if (!preview || !Array.isArray(images) || !images.length) return;
            var internals = getOrderedInternalImages(images.filter(function (img) {
                return img && img.url && String(img.section || '') !== 'featured';
            }));
            if (!internals.length) return;
            var allSlots = preview.querySelectorAll('.cbia-ai-image-slot, .cbia-inline-image-wrap');
            var maxSlot = 0;
            Array.prototype.forEach.call(allSlots, function (node) {
                var current = parseInt(node.getAttribute('data-cbia-slot') || 0, 10) || 0;
                if (current > maxSlot) {
                    maxSlot = current;
                    return;
                }
                maxSlot += 1;
                node.setAttribute('data-cbia-slot', String(maxSlot));
            });
            internals.forEach(function (img, idx) {
                var slotIdx = (parseInt(img.idx || 0, 10) > 0) ? parseInt(img.idx || 0, 10) : (idx + 1);
                var html = buildInternalImageWrap(String(img.url || ''), String(img.alt || img.desc || 'Generated image'), slotIdx);
                var slotNode = preview.querySelector('[data-cbia-slot="' + String(slotIdx) + '"]');
                if (slotNode) {
                    var existing = slotNode.querySelector ? slotNode.querySelector('img') : null;
                    if (existing && String(existing.getAttribute('src') || '') === String(img.url || '')) return;
                    slotNode.outerHTML = html;
                    return;
                }
                var existsInPreview = Array.prototype.some.call(preview.querySelectorAll('img'), function (i) {
                    return String(i.getAttribute('src') || '') === String(img.url || '');
                });
                if (!existsInPreview) {
                    preview.insertAdjacentHTML('beforeend', html);
                }
            });
            repositionInternalSlotsInRoot(preview);
        }

        function findFaqHeading(rootNode) {
            if (!rootNode || !rootNode.querySelectorAll) return null;
            var headings = rootNode.querySelectorAll('h2,h3,h4');
            for (var i = 0; i < headings.length; i++) {
                var text = String(headings[i].textContent || '').toLowerCase().trim();
                if (!text) continue;
                if (text.indexOf('frequently asked questions') !== -1 || text.indexOf('preguntas frecuentes') !== -1) return headings[i];
                if (/\bfaq\b/.test(text)) return headings[i];
            }
            return null;
        }

        function findBlockAtRatio(rootNode, ratio) {
            if (!rootNode || !rootNode.querySelectorAll) return null;
            var blocks = Array.prototype.slice.call(rootNode.querySelectorAll('p,h2,h3,h4,ul,ol,blockquote,figure,table,div')).filter(function (node) {
                if (!node) return false;
                if (node.classList && node.classList.contains('cbia-inline-image-wrap')) return false;
                if (node.closest && node.closest('.cbia-inline-image-wrap')) return false;
                if (node.classList && node.classList.contains('cbia-ai-image-slot')) return false;
                var text = String(node.textContent || '').trim();
                return text.length > 0;
            });
            if (!blocks.length) return null;
            var total = 0;
            blocks.forEach(function (node) { total += String(node.textContent || '').trim().length; });
            if (total <= 0) return blocks[Math.floor(blocks.length / 2)];
            var target = Math.max(1, Math.floor(total * ratio));
            var acc = 0;
            for (var i = 0; i < blocks.length; i++) {
                acc += String(blocks[i].textContent || '').trim().length;
                if (acc >= target) return blocks[i];
            }
            return blocks[blocks.length - 1];
        }

        function repositionInternalSlotsInRoot(rootNode) {
            if (!rootNode || !rootNode.querySelector) return;
            var slot1 = rootNode.querySelector('.cbia-inline-image-wrap[data-cbia-slot="1"]');
            var slot2 = rootNode.querySelector('.cbia-inline-image-wrap[data-cbia-slot="2"]');
            var slot3 = rootNode.querySelector('.cbia-inline-image-wrap[data-cbia-slot="3"]');

            var firstH3 = rootNode.querySelector('h3');
            if (slot1) {
                var slot1Anchor = firstH3;
                if (!slot1Anchor) {
                    var firstParagraph = rootNode.querySelector('p');
                    if (firstParagraph && firstParagraph.parentNode) {
                        // Preferred fallback without H3: after first paragraph.
                        slot1Anchor = firstParagraph.nextElementSibling || null;
                        if (!slot1Anchor || slot1Anchor === slot1) {
                            // Secondary fallback: around first third of content.
                            slot1Anchor = findBlockAtRatio(rootNode, 0.35);
                        }
                    }
                }
                if (!slot1Anchor) {
                    slot1Anchor = findBlockAtRatio(rootNode, 0.35);
                }
                if (slot1Anchor && slot1Anchor.parentNode && slot1Anchor !== slot1 && !(slot1.contains && slot1.contains(slot1Anchor))) {
                    slot1Anchor.parentNode.insertBefore(slot1, slot1Anchor);
                }
            }

            var faqHeading = findFaqHeading(rootNode);
            if (slot3 && faqHeading && faqHeading.parentNode) {
                faqHeading.parentNode.insertBefore(slot3, faqHeading);
            }

            if (slot2) {
                var anchor = findBlockAtRatio(rootNode, 0.55);
                if (anchor && anchor.parentNode && anchor !== slot2 && !(slot2.contains && slot2.contains(anchor))) {
                    anchor.parentNode.insertBefore(slot2, anchor);
                }
            }
        }

        function repositionInternalSlotsInHtml(sourceHtml) {
            var source = String(sourceHtml || '');
            if (!source) return source;
            var wrap = document.createElement('div');
            wrap.innerHTML = source;
            repositionInternalSlotsInRoot(wrap);
            return wrap.innerHTML;
        }

        function injectInternalImagesIntoHtml(html, images) {
            var source = String(html || '');
            if (!source || !Array.isArray(images) || !images.length) return source;
            var internals = getOrderedInternalImages(images.filter(function (img) {
                return img && img.url && String(img.section || '') !== 'featured';
            }));
            if (!internals.length) return source;
            var internalsBySlot = {};
            internals.forEach(function (img, itemIdx) {
                var slot = parseInt(img.idx || 0, 10) || (itemIdx + 1);
                internalsBySlot[slot] = img;
            });
            var usedSlots = {};
            function pickInternal(preferredSlot) {
                var pref = parseInt(preferredSlot || 0, 10) || 0;
                if (pref > 0 && internalsBySlot[pref] && !usedSlots[pref]) {
                    usedSlots[pref] = 1;
                    return { img: internalsBySlot[pref], slot: pref };
                }
                for (var i = 0; i < internals.length; i++) {
                    var slot = parseInt(internals[i].idx || 0, 10) || (i + 1);
                    if (usedSlots[slot]) continue;
                    usedSlots[slot] = 1;
                    return { img: internals[i], slot: slot };
                }
                return null;
            }

            // 1) Replace existing internal wrappers by slot when present.
            source = source.replace(/<div[^>]*class=("|\')[^"\']*cbia-inline-image-wrap[^"\']*\1[^>]*>[\s\S]*?<\/div>/gi, function (block) {
                var m = block.match(/data-cbia-slot=("|\')(\d+)\1/i);
                var preferred = m ? (parseInt(m[2] || '0', 10) || 0) : 0;
                var picked = pickInternal(preferred);
                if (!picked || !picked.img) return '';
                return '\n' + buildInternalImageWrap(String(picked.img.url || ''), String(picked.img.alt || picked.img.desc || 'Generated image'), picked.slot) + '\n';
            });

            // 2) Replace live placeholders in preview/html.
            source = source.replace(/<div class="cbia-ai-image-slot">[\s\S]*?<\/div>/gi, function () {
                var picked = pickInternal(0);
                if (!picked || !picked.img) return '';
                return '\n' + buildInternalImageWrap(String(picked.img.url || ''), String(picked.img.alt || picked.img.desc || 'Generated image'), picked.slot) + '\n';
            });

            // 3) Replace text markers from AI output.
            source = source.replace(/\[(?:IMAGE|IMAGEN)\s*:[\s\S]*?\]\s*\.?/gi, function () {
                var picked = pickInternal(0);
                if (!picked || !picked.img) return '';
                return '\n' + buildInternalImageWrap(String(picked.img.url || ''), String(picked.img.alt || picked.img.desc || 'Generated image'), picked.slot) + '\n';
            });

            // 4) Legacy fallback: if HTML has plain <img> and no wrappers/markers, replace first N by order.
            var hasWrappers = /cbia-inline-image-wrap/i.test(source);
            var hasMarkers = /\[(?:IMAGE|IMAGEN)\s*:/i.test(source);
            if (!hasWrappers && !hasMarkers && /<img\b/i.test(source)) {
                source = source.replace(/<img\b[^>]*>/gi, function () {
                    var picked = pickInternal(0);
                    if (!picked || !picked.img) return '';
                    return buildInternalImageWrap(String(picked.img.url || ''), String(picked.img.alt || picked.img.desc || 'Generated image'), picked.slot);
                });
            }

            // 5) Append any remaining internals not injected yet.
            // (Important when source already contains one wrapper/image: we still need slot 2/3.)
            internals.forEach(function (img, itemIdx) {
                var slotIdx = parseInt(img.idx || 0, 10) || (itemIdx + 1);
                if (!usedSlots[slotIdx]) {
                    usedSlots[slotIdx] = 1;
                    source += buildInternalImageWrap(String(img.url || ''), String(img.alt || img.desc || 'Generated image'), slotIdx);
                }
            });
            // Clean orphan punctuation left after marker replacement.
            source = source.replace(/(<\/div>)\s*\.\s*(?=(?:<\/p>|<p|<h|<div|<figure|$))/gi, '$1');
            source = source.replace(/<p>\s*[.,;:]\s*<\/p>/gi, '');
            source = repositionInternalSlotsInHtml(source);
            return source;
        }

        function stopPhaseTimer() {
            if (phaseTimer) {
                clearInterval(phaseTimer);
                phaseTimer = null;
            }
        }

        function startPhaseTimer(label) {
            stopPhaseTimer();
            phaseStartedAt = Date.now();
            var base = String(label || 'Generating');
            setStatus(base + '... 0s', false);
            phaseTimer = setInterval(function () {
                var sec = Math.floor((Date.now() - phaseStartedAt) / 1000);
                setStatus(base + '... ' + sec + 's', false);
            }, 1000);
        }

        function getLengthInstruction() {
            var mode = lengthSelect ? (lengthSelect.value || 'medium') : 'medium';
            var faqOn = isComposerFaqEnabled();
            if (mode === 'short') {
                return faqOn
                    ? 'ABSOLUTE LENGTH PRIORITY (overrides any previous range): 950-1100 words, minimum 950. With FAQ enabled: opening 150-210, each main block 150-190, each FAQ answer 45-65, closing 80-120 words.'
                    : 'ABSOLUTE LENGTH PRIORITY (overrides any previous range): 950-1100 words, minimum 950. Split as: opening 180-240, each main block 220-280, closing 100-160 words.';
            }
            if (mode === 'long') {
                return faqOn
                    ? 'ABSOLUTE LENGTH PRIORITY (overrides any previous range): 2000-2200 words, minimum 2000. With FAQ enabled: opening 240-320, each main block 300-380, each FAQ answer 80-110, closing 140-220 words.'
                    : 'ABSOLUTE LENGTH PRIORITY (overrides any previous range): 2000-2200 words, minimum 2000. Split as: opening 280-360, each main block 460-560, closing 180-260 words.';
            }
            return faqOn
                ? 'ABSOLUTE LENGTH PRIORITY (overrides any previous range): 1800-2000 words, minimum 1800. With FAQ enabled: opening 220-300, each main block 280-360, each FAQ answer 75-105, closing 130-190 words.'
                : 'ABSOLUTE LENGTH PRIORITY (overrides any previous range): 1800-2000 words, minimum 1800. Split as: opening 260-340, each main block 420-520, closing 150-220 words.';
        }

        function getLengthLabel() {
            var mode = lengthSelect ? (lengthSelect.value || 'medium') : 'medium';
            if (mode === 'short') return 'Short';
            if (mode === 'long') return 'Long';
            return 'Medium';
        }

        function getComposerTemperature() {
            var raw = temperatureInput ? String(temperatureInput.value || '').trim() : '';
            raw = raw.replace(',', '.');
            var value = parseFloat(raw);
            if (isNaN(value)) value = 0.7;
            if (value < 0) value = 0;
            if (value > 2) value = 2;
            return value;
        }

        function getPromptProfileValue() {
            var profile = promptProfileSelect ? String(promptProfileSelect.value || '') : '';
            if (profile !== 'auto_by_title' && profile !== 'seo_balanced' && profile !== 'how_to' && profile !== 'discover_editorial') {
                profile = 'discover_editorial';
            }
            return profile;
        }

        function getPromptProfileLabel() {
            var profile = getPromptProfileValue();
            if (profile === 'auto_by_title') return 'Auto';
            if (profile === 'seo_balanced') return 'SEO Standard';
            if (profile === 'how_to') return 'How-to';
            return 'Discover';
        }

        function isComposerFaqEnabled() {
            return !!(includeFaqToggle && includeFaqToggle.checked);
        }

        function isComposerExamplesEnabled() {
            return !!(includeExamplesToggle && includeExamplesToggle.checked);
        }

        function normalizeLanguageValue(value) {
            var raw = String(value || '').trim().toLowerCase();
            if (!raw) return '';
            if (raw === 'es' || raw === 'es-es' || raw === 'español' || raw === 'espanol' || raw === 'spanish' || raw === 'castellano') return 'es';
            if (raw === 'en' || raw === 'en-us' || raw === 'en-gb' || raw === 'english' || raw === 'ingles' || raw === 'inglés') return 'en';
            return '';
        }

        function detectLanguageFromText(title, html) {
            var source = (String(title || '') + ' ' + String(html || '')).toLowerCase().replace(/<[^>]+>/g, ' ');
            if (!source.trim()) return '';
            function countHits(words) {
                var n = 0;
                for (var i = 0; i < words.length; i++) {
                    var token = words[i];
                    if (!token) continue;
                    var escaped = token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    var re = new RegExp('(^|\\s)' + escaped + '(\\s|$)', 'g');
                    var m = source.match(re);
                    if (m && m.length) n += m.length;
                }
                return n;
            }
            var es = countHits(['el', 'la', 'los', 'las', 'de', 'del', 'al', 'que', 'y', 'en', 'con', 'para', 'por', 'como', 'cómo', 'qué', 'una', 'un']);
            var en = countHits(['the', 'and', 'of', 'to', 'in', 'with', 'for', 'is', 'on', 'that', 'what', 'how', 'from', 'this', 'an', 'a']);
            if (/[áéíóúñ¿¡]/.test(source)) es += 2;
            if (/\btion\b|\bing\b/.test(source)) en += 1;
            if (es >= 3 && es >= (en + 1)) return 'es';
            if (en >= 3 && en >= (es + 1)) return 'en';
            return '';
        }

        function ensureLanguageSelected(fallbackHtml) {
            if (!languageSelect) return;
            var selected = normalizeLanguageValue(languageSelect.value || '');
            if (!selected && languageSelect.selectedIndex >= 0) {
                selected = normalizeLanguageValue(languageSelect.options[languageSelect.selectedIndex].value || '');
            }
            if (!selected) {
                selected = detectLanguageFromText(titleInput ? titleInput.value : finalTitle, fallbackHtml || finalHtml || (preview ? preview.innerHTML : ''));
            }
            if (!selected) {
                selected = normalizeLanguageValue(aiCfg.defaultLanguage || '') || 'en';
            }
            languageSelect.value = selected;
        }

        function getLanguageLabel() {
            if (!languageSelect) return 'English';
            ensureLanguageSelected();
            var opt = languageSelect.options[languageSelect.selectedIndex];
            return opt ? opt.textContent : (languageSelect.value || 'English');
        }

        // Override legacy es/en-only language handling with the full Config language set.
        function normalizeLanguageValue(value) {
            var raw = String(value || '').trim().toLowerCase();
            if (!raw) return '';
            var aliases = {
                Spanish: ['spanish', 'es', 'es-es', 'español', 'espanol', 'castellano'],
                Portuguese: ['portuguese', 'pt', 'pt-pt', 'pt-br', 'portugues', 'português'],
                English: ['english', 'en', 'en-us', 'en-gb', 'ingles', 'inglés'],
                French: ['french', 'fr', 'fr-fr', 'frances', 'francés', 'francais', 'français'],
                Italian: ['italian', 'it', 'it-it', 'italiano'],
                German: ['german', 'de', 'de-de', 'aleman', 'alemán', 'deutsch'],
                Dutch: ['dutch', 'nl', 'nl-nl', 'holandes', 'holandés', 'nederlands'],
                Swedish: ['swedish', 'sv', 'sv-se', 'sueco'],
                Danish: ['danish', 'da', 'da-dk', 'danes', 'danés'],
                Norwegian: ['norwegian', 'no', 'nb', 'nn', 'noruego'],
                Finnish: ['finnish', 'fi', 'fi-fi', 'finlandes', 'finlandés', 'suomi'],
                Polish: ['polish', 'pl', 'pl-pl', 'polaco'],
                Czech: ['czech', 'cs', 'cs-cz', 'checo'],
                Slovak: ['slovak', 'sk', 'sk-sk', 'eslovaco'],
                Hungarian: ['hungarian', 'hu', 'hu-hu', 'hungaro', 'húngaro', 'magyar'],
                Romanian: ['romanian', 'ro', 'ro-ro', 'rumano'],
                Bulgarian: ['bulgarian', 'bg', 'bg-bg', 'bulgaro', 'búlgaro'],
                Greek: ['greek', 'el', 'el-gr', 'griego'],
                Croatian: ['croatian', 'hr', 'hr-hr', 'croata'],
                Slovenian: ['slovenian', 'sl', 'sl-si', 'esloveno'],
                Estonian: ['estonian', 'et', 'et-ee', 'estonio'],
                Latvian: ['latvian', 'lv', 'lv-lv', 'leton'],
                Lithuanian: ['lithuanian', 'lt', 'lt-lt', 'lituano'],
                Irish: ['irish', 'ga', 'ga-ie', 'irlandes', 'irlandés'],
                Maltese: ['maltese', 'mt', 'mt-mt', 'maltes', 'maltés'],
                Romansh: ['romansh', 'rm', 'rm-ch', 'romanche']
            };
            var labels = Object.keys(aliases);
            for (var i = 0; i < labels.length; i++) {
                var label = labels[i];
                if (aliases[label].indexOf(raw) !== -1) return label;
            }
            return '';
        }

        function ensureLanguageSelected(fallbackHtml) {
            if (!languageSelect) return;
            var selected = normalizeLanguageValue(languageSelect.value || '');
            if (!selected && languageSelect.selectedIndex >= 0) {
                selected = normalizeLanguageValue(languageSelect.options[languageSelect.selectedIndex].value || '');
            }
            if (!selected) {
                selected = detectLanguageFromText(titleInput ? titleInput.value : finalTitle, fallbackHtml || finalHtml || (preview ? preview.innerHTML : ''));
            }
            if (!selected) {
                selected = normalizeLanguageValue(aiCfg.defaultLanguage || '') || 'English';
            }
            languageSelect.value = selected;
        }

        function getLanguageLabel() {
            if (!languageSelect) return 'Spanish';
            ensureLanguageSelected();
            var opt = languageSelect.options[languageSelect.selectedIndex];
            return opt ? opt.textContent : (languageSelect.value || 'English');
        }

        function getInternalImagesCount() {
            if (!aiCfg.internalImagesEnabled) return 0;
            var val = imagesSelect ? parseInt(imagesSelect.value || '0', 10) : parseInt(aiCfg.defaultImagesLimit || 2, 10);
            if (isNaN(val) || val < 0) val = 0;
            if (val > 3) val = 3;
            return val;
        }

        function getTotalImagesForEngine() {
            // Engine expects total image slots (featured + internal).
            return getInternalImagesCount() + 1;
        }

        function updateSummary() {
            if (!summary) return;
            summary.textContent = 'Mode: ' + getPromptProfileLabel()
                + ' | FAQ: ' + (isComposerFaqEnabled() ? 'On' : 'Off')
                + ' | Examples: ' + (isComposerExamplesEnabled() ? 'On' : 'Off')
                + ' | Language: ' + getLanguageLabel()
                + ' | Length: ' + getLengthLabel()
                + ' | Temp: ' + String(getComposerTemperature());
        }

        function updateLengthHelp() {
            if (!lengthHelp) return;
            var mode = lengthSelect ? (lengthSelect.value || 'medium') : 'medium';
            if (mode === 'short') {
                lengthHelp.textContent = 'Target: ~1000 words (950-1100).';
                return;
            }
            if (mode === 'long') {
                lengthHelp.textContent = 'Target: 2000-2200 words.';
                return;
            }
            lengthHelp.textContent = 'Target: 1800-2000 words.';
        }

        function getComposerStorageKey() {
            var postId = parseInt(resolveCurrentPostId() || root.getAttribute('data-current-post-id') || 0, 10) || 0;
            return 'cbia_ai_composer_snapshot_' + String(postId > 0 ? postId : 'new');
        }

        function buildComposerSnapshot() {
            return {
                version: 1,
                post_id: parseInt(resolveCurrentPostId() || root.getAttribute('data-current-post-id') || 0, 10) || 0,
                title: String((titleInput ? titleInput.value : finalTitle) || finalTitle || '').trim(),
                controls: {
                    length: String(lengthSelect ? (lengthSelect.value || 'medium') : 'medium'),
                    internal_images: getInternalImagesCount(),
                    language: String(languageSelect ? (normalizeLanguageValue(languageSelect.value || '') || normalizeLanguageValue(aiCfg.defaultLanguage || '') || 'English') : (normalizeLanguageValue(aiCfg.defaultLanguage || '') || 'English')),
                    internal_style: String(internalStyleSelect ? (internalStyleSelect.value || 'banner') : 'banner'),
                    temperature: getComposerTemperature(),
                    prompt_profile: getPromptProfileValue(),
                    include_faq: isComposerFaqEnabled() ? 1 : 0,
                    include_practical_examples: isComposerExamplesEnabled() ? 1 : 0
                },
                final_html: String(finalHtml || ''),
                preview_html: String(preview ? (preview.innerHTML || '') : ''),
                featured: finalFeaturedImage ? {
                    url: String(finalFeaturedImage.url || ''),
                    attach_id: parseInt(finalFeaturedImage.attach_id || finalFeaturedAttachId || 0, 10) || 0,
                    desc: String(finalFeaturedImage.desc || '')
                } : null,
                internal_images: Array.isArray(receivedInternalImages) ? receivedInternalImages.map(function (img) {
                    return {
                        idx: parseInt(img && img.idx ? img.idx : 0, 10) || 0,
                        url: String((img && img.url) ? img.url : ''),
                        attach_id: parseInt((img && img.attach_id) ? img.attach_id : 0, 10) || 0,
                        desc: String((img && (img.desc || img.alt)) ? (img.desc || img.alt) : ''),
                        prompt: String((img && img.prompt) ? img.prompt : ''),
                        section: String((img && img.section) ? img.section : '')
                    };
                }).filter(function (img) { return !!img.url; }) : [],
                featured_attach_id: parseInt(finalFeaturedAttachId || 0, 10) || 0,
                focus_keyphrase: String(finalFocusKeyphrase || ''),
                meta_description: String(finalMetaDescription || ''),
                category_ids: Array.isArray(finalCategoryIds) ? finalCategoryIds.slice() : [],
                tag_ids: Array.isArray(finalTagIds) ? finalTagIds.slice() : [],
                tag_names: Array.isArray(finalTagNames) ? finalTagNames.slice() : [],
                preview_token: String(previewToken || ''),
                word_count: parseInt((wordCount && wordCount.textContent ? wordCount.textContent : '0') || '0', 10) || 0,
                updated_at: Date.now()
            };
        }

        function persistComposerSnapshot() {
            var snapshot = buildComposerSnapshot();
            try {
                localStorage.setItem(getComposerStorageKey(), JSON.stringify(snapshot));
            } catch (eStore) {}

            var postId = parseInt(snapshot.post_id || 0, 10) || 0;
            if (!postId || !ajaxUrl || !nonce) return;

            var params = new URLSearchParams();
            params.append('action', 'cbia_ai_composer_save_snapshot');
            params.append('_ajax_nonce', nonce);
            params.append('post_id', String(postId));
            params.append('snapshot', JSON.stringify(snapshot));
            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            }).catch(function () {});
        }

        function applyComposerSnapshot(rawSnapshot) {
            if (!rawSnapshot || typeof rawSnapshot !== 'object') return false;
            var snapshot = rawSnapshot;
            var snapshotVersion = parseInt(snapshot.version || 1, 10) || 1;
            var defaultInternalImages = Math.max(0, Math.min(3, (parseInt(aiCfg.defaultImagesLimit || 3, 10) || 3) - 1));
            var snapshotTitle = String(snapshot.title || '').trim();
            var snapshotHasMeaningfulContent = !!(
                String(snapshot.final_html || '').trim() ||
                String(snapshot.preview_html || '').trim() ||
                (snapshot.featured && String(snapshot.featured.url || '').trim()) ||
                (Array.isArray(snapshot.internal_images) && snapshot.internal_images.length) ||
                String(snapshot.preview_token || '').trim() ||
                (parseInt(snapshot.word_count || 0, 10) || 0) > 0
            );
            if (snapshotTitle && titleInput) {
                titleInput.value = snapshotTitle;
                finalTitle = snapshotTitle;
            }
            var controls = snapshot.controls || {};
            if (snapshotVersion < 2 && !snapshotHasMeaningfulContent) {
                controls = Object.assign({}, controls, {
                    length: String(aiCfg.defaultLength || 'medium'),
                    internal_images: defaultInternalImages,
                    language: String(normalizeLanguageValue(aiCfg.defaultLanguage || '') || 'English'),
                    internal_style: String(internalStyleSelect ? (internalStyleSelect.value || 'banner') : 'banner'),
                    temperature: getComposerTemperature()
                });
            }
            if (lengthSelect && controls.length) lengthSelect.value = String(controls.length);
            if (imagesSelect && typeof controls.internal_images !== 'undefined') {
                imagesSelect.value = aiCfg.internalImagesEnabled
                    ? String(parseInt(controls.internal_images || 0, 10) || 0)
                    : '0';
            }
            if (languageSelect && typeof controls.language !== 'undefined') {
                var mappedLang = normalizeLanguageValue(controls.language);
                if (!mappedLang) {
                    mappedLang = detectLanguageFromText(snapshotTitle, String(snapshot.final_html || snapshot.preview_html || ''));
                }
                if (!mappedLang) mappedLang = normalizeLanguageValue(aiCfg.defaultLanguage || '') || 'English';
                languageSelect.value = mappedLang;
            }
            if (internalStyleSelect && controls.internal_style) internalStyleSelect.value = String(controls.internal_style);
            if (temperatureInput && typeof controls.temperature !== 'undefined') {
                var tempValue = parseFloat(String(controls.temperature).replace(',', '.'));
                if (!isNaN(tempValue)) {
                    if (tempValue < 0) tempValue = 0;
                    if (tempValue > 2) tempValue = 2;
                    temperatureInput.value = String(tempValue);
                }
            }
            if (promptProfileSelect && typeof controls.prompt_profile !== 'undefined') {
                promptProfileSelect.value = String(controls.prompt_profile || 'discover_editorial');
            }
            if (includeFaqToggle && typeof controls.include_faq !== 'undefined') {
                includeFaqToggle.checked = !!parseInt(controls.include_faq || 0, 10);
            }
            if (includeExamplesToggle && typeof controls.include_practical_examples !== 'undefined') {
                includeExamplesToggle.checked = !!parseInt(controls.include_practical_examples || 0, 10);
            }

            finalHtml = String(snapshot.final_html || '');
            finalFocusKeyphrase = String(snapshot.focus_keyphrase || '');
            finalMetaDescription = String(snapshot.meta_description || '');
            finalCategoryIds = Array.isArray(snapshot.category_ids) ? snapshot.category_ids : [];
            finalTagIds = Array.isArray(snapshot.tag_ids) ? snapshot.tag_ids : [];
            finalTagNames = Array.isArray(snapshot.tag_names) ? snapshot.tag_names : [];
            previewToken = String(snapshot.preview_token || '');
            finalFeaturedAttachId = parseInt(snapshot.featured_attach_id || 0, 10) || 0;
            if (finalFeaturedAttachId > 0) {
                syncFeaturedMediaUi(finalFeaturedAttachId);
                setTimeout(function () { syncFeaturedMediaUi(finalFeaturedAttachId); }, 600);
                setTimeout(function () { syncFeaturedMediaUi(finalFeaturedAttachId); }, 1800);
            }

            receivedInternalImages = normalizeInternalImageSlots(Array.isArray(snapshot.internal_images) ? snapshot.internal_images : [], getInternalImagesCount());
            finalFeaturedImage = snapshot.featured && typeof snapshot.featured === 'object' ? snapshot.featured : null;
            if (finalFeaturedImage && finalFeaturedImage.url) {
                setFeaturedPreview(String(finalFeaturedImage.url || ''), 'Featured image ready.');
            } else if (finalFeaturedAttachId > 0) {
                resolveAttachmentUrlById(finalFeaturedAttachId).then(function (resolvedUrl) {
                    if (!resolvedUrl) {
                        resetFeaturedPreview();
                        return;
                    }
                    finalFeaturedImage = finalFeaturedImage || {};
                    finalFeaturedImage.url = String(resolvedUrl || '');
                    finalFeaturedImage.attach_id = finalFeaturedAttachId;
                    setFeaturedPreview(String(resolvedUrl || ''), 'Featured image ready.');
                });
            } else {
                resetFeaturedPreview();
            }

            var previewHtml = String(snapshot.preview_html || finalHtml || '');
            if (preview && canUsePreviewHtml(previewHtml)) {
                preview.innerHTML = enforceInternalImageStyle(normalizePreviewHtml(previewHtml));
            }
            if (finalHtml && !canUsePreviewHtml(previewHtml) && preview) {
                preview.innerHTML = enforceInternalImageStyle(normalizePreviewHtml(finalHtml));
            }
            if (receivedInternalImages.length && preview) {
                fillPreviewSlotsFromImages(receivedInternalImages);
            }
            ensureLanguageSelected(finalHtml || previewHtml);
            if (insertBtn) insertBtn.disabled = !canUsePreviewHtml(finalHtml || (preview ? preview.innerHTML : ''));
            if (snapshot.word_count) setWordCount(snapshot.word_count);
            updateSummary();
            renderImageCards();
            var hasContent = !!String(finalHtml || '').trim();
            var hasPreview = !!String(previewHtml || '').trim();
            var hasTitle = !!String(snapshotTitle || '').trim();
            var hasFeatured = !!(finalFeaturedImage && String(finalFeaturedImage.url || '').trim());
            var hasInternal = Array.isArray(receivedInternalImages) && receivedInternalImages.length > 0;
            return !!(hasContent || hasPreview || hasTitle || hasFeatured || hasInternal);
        }

        function loadComposerSnapshotFromServer() {
            var postId = parseInt(resolveCurrentPostId() || root.getAttribute('data-current-post-id') || 0, 10) || 0;
            if (!postId || !ajaxUrl || !nonce) {
                return Promise.resolve(false);
            }
            var params = new URLSearchParams();
            params.append('action', 'cbia_ai_composer_load_snapshot');
            params.append('_ajax_nonce', nonce);
            params.append('post_id', String(postId));
            return fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success || !res.data || !res.data.snapshot) return false;
                var loaded = applyComposerSnapshot(res.data.snapshot);
                if (loaded) {
                    try {
                        localStorage.setItem(getComposerStorageKey(), JSON.stringify(buildComposerSnapshot()));
                    } catch (eStore) {}
                    setStatus('State loaded from current post.', false);
                }
                return loaded;
            })
            .catch(function () { return false; });
        }

        function hydrateComposerSnapshot() {
            var loaded = false;
            var initialRaw = root.getAttribute('data-initial-snapshot') || '';
            if (initialRaw && initialRaw !== '[]' && initialRaw !== '{}') {
                try {
                    var initialSnapshot = JSON.parse(initialRaw);
                    loaded = applyComposerSnapshot(initialSnapshot) || loaded;
                } catch (eInit) {}
            }
            if (!loaded) {
                try {
                    var localRaw = localStorage.getItem(getComposerStorageKey());
                    if (localRaw) {
                        loaded = applyComposerSnapshot(JSON.parse(localRaw)) || loaded;
                    }
                } catch (eLocal) {}
            }
            if (loaded) {
                setStatus('Previous composer state loaded.', false);
                return;
            }
            setStatus('Loading post state...', false);
            loadComposerSnapshotFromServer().then(function (serverLoaded) {
                if (!serverLoaded) {
                    setStatus('Ready to generate. Enter a title and click "Generate with AI".', false);
                }
            });
        }

        function getInternalImageByIdx(slotIdx) {
            var idx = parseInt(slotIdx || 0, 10) || 0;
            if (idx <= 0) return null;
            var found = null;
            receivedInternalImages.forEach(function (img) {
                if (!img) return;
                var current = parseInt(img.idx || 0, 10) || 0;
                if (current === idx) found = img;
            });
            return found;
        }

        function upsertInternalImage(nextImage) {
            if (!nextImage || !nextImage.url) return;
            var idx = parseInt(nextImage.idx || 0, 10) || 0;
            if (idx <= 0) return;
            var replaced = false;
            receivedInternalImages = (Array.isArray(receivedInternalImages) ? receivedInternalImages : []).map(function (img) {
                var current = parseInt((img && img.idx) ? img.idx : 0, 10) || 0;
                if (current !== idx) return img;
                replaced = true;
                return Object.assign({}, img || {}, nextImage);
            });
            if (!replaced) {
                receivedInternalImages.push(Object.assign({}, nextImage));
            }
            receivedInternalImages = getOrderedInternalImages(receivedInternalImages);
        }

        function renderImageCards() {
            if (!imageCardsRoot) return;
            var cards = [];
            var featuredUrl = finalFeaturedImage && finalFeaturedImage.url ? String(finalFeaturedImage.url) : '';
            cards.push(
                '<div class="cbia-ai-image-card is-featured" data-slot-type="featured" data-slot-idx="0">' +
                    '<div class="cbia-ai-image-thumb">' + (featuredUrl ? ('<img src="' + escAttr(featuredUrl) + '" alt="Featured" />') : '<em>Featured pending</em>') + '</div>' +
                    '<div class="cbia-ai-image-meta">' +
                        '<strong>Featured</strong>' +
                        '<button type="button" class="button button-small cbia-ai-image-change">Regenerate image</button>' +
                    '</div>' +
                '</div>'
            );

            var internals = getOrderedInternalImages(receivedInternalImages || []);
            internals.forEach(function (img, arrIdx) {
                var slot = parseInt(img.idx || 0, 10) || (arrIdx + 1);
                var desc = String(img.desc || img.alt || 'Internal image ' + slot);
                cards.push(
                    '<div class="cbia-ai-image-card" data-slot-type="internal" data-slot-idx="' + escAttr(slot) + '">' +
                        '<div class="cbia-ai-image-thumb">' + (img.url ? ('<img src="' + escAttr(String(img.url)) + '" alt="' + escAttr(desc) + '" />') : '<em>Pending</em>') + '</div>' +
                        '<div class="cbia-ai-image-meta">' +
                            '<strong>Internal ' + escAttr(slot) + '</strong>' +
                            '<span class="description">' + escAttr(desc) + '</span>' +
                            '<button type="button" class="button button-small cbia-ai-image-change">Regenerate image</button>' +
                        '</div>' +
                    '</div>'
                );
            });
            imageCardsRoot.innerHTML = cards.join('');
        }

        function setImageModalStatus(msg, isError) {
            if (!imageModalStatus) return;
            imageModalStatus.textContent = msg || '';
            imageModalStatus.style.color = isError ? '#b32d2e' : '#2271b1';
        }

        function openImageModal(slotType, slotIdx) {
            if (!imageModal) return;
            var type = String(slotType || 'internal');
            if (type === 'internal' && !aiCfg.internalImagesEnabled) {
                setStatus('Internal images are available in Pro only.', false);
                return;
            }
            var idx = parseInt(slotIdx || 0, 10) || 0;
            changingImageSlot = { type: type, idx: idx };
            var existing = (type === 'featured') ? (finalFeaturedImage || {}) : (getInternalImageByIdx(idx) || {});
            var defaultPrompt = String(existing.prompt || existing.desc || existing.alt || finalTitle || '').trim();
            if (imageModalTitle) {
                imageModalTitle.textContent = (type === 'featured') ? 'Regenerate featured image' : ('Regenerate internal image ' + idx);
            }
            if (imageModalHelp) {
                imageModalHelp.textContent = 'Regenerate only this image with AI or choose one from your media library.';
            }
            if (imageModalPrompt) imageModalPrompt.value = defaultPrompt;
            setImageModalStatus('', false);
            imageModal.style.display = 'flex';
            if (imageModalPrompt) imageModalPrompt.focus();
        }

        function closeImageModal() {
            if (!imageModal) return;
            imageModal.style.display = 'none';
            changingImageSlot = null;
        }

        function refreshHtmlWithCurrentImages() {
            var source = String(finalHtml || (preview ? preview.innerHTML : '') || '');
            if (!source) return;
            var styleMode = internalStyleSelect ? (internalStyleSelect.value || 'banner') : 'banner';
            finalHtml = enforceInternalImageStyle(injectInternalImagesIntoHtml(source, receivedInternalImages), styleMode);
            if (preview) {
                preview.innerHTML = finalHtml;
                fillPreviewSlotsFromImages(receivedInternalImages);
            }
        }

        function resolveSlotSection(slot) {
            if (!slot) return 'body';
            if (slot.type === 'featured') return 'featured';
            var idx = parseInt(slot.idx || 0, 10) || 0;
            if (idx >= 3) return 'faq';
            return 'body';
        }

        function buildMissingImagePrompt(slotType, slotIdx, title) {
            var baseTitle = String(title || '').trim();
            if (!baseTitle) baseTitle = 'article';
            if (slotType === 'featured') {
                return 'Realistic editorial featured image about "' + baseTitle + '", no text in the image.';
            }
            return 'Internal image ' + String(slotIdx || 1) + ' for "' + baseTitle + '", realistic editorial style, no text in the image.';
        }

        function regenerateSlotWithPrompt(slot, prompt, sectionHint) {
            if (!slot || !ajaxUrl || !nonce) {
                return Promise.reject(new Error('Could not prepare slot regeneration.'));
            }
            var cleanPrompt = String(prompt || '').trim();
            if (!cleanPrompt) {
                return Promise.reject(new Error('Empty prompt for image regeneration.'));
            }
            var params = new URLSearchParams();
            params.append('action', 'cbia_ai_composer_regenerate_image_slot');
            params.append('_ajax_nonce', nonce);
            params.append('post_id', String(resolveCurrentPostId() || root.getAttribute('data-current-post-id') || 0));
            params.append('title', String((titleInput ? titleInput.value : finalTitle) || finalTitle || '').trim());
            params.append('slot_type', String(slot.type || 'internal'));
            params.append('slot_idx', String(slot.idx || 0));
            params.append('prompt', cleanPrompt);
            params.append('section', String(sectionHint || resolveSlotSection(slot) || 'body'));
            params.append('internal_image_style', internalStyleSelect ? (internalStyleSelect.value || 'banner') : 'banner');

            return fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            }).then(function (r) { return r.json(); })
              .then(function (res) {
                if (!res || !res.success || !res.data || !res.data.url) {
                    throw new Error((res && res.data && res.data.message) ? res.data.message : 'Could not regenerate image.');
                }
                var data = res.data;
                if (slot.type === 'featured') {
                    finalFeaturedAttachId = parseInt(data.attach_id || 0, 10) || 0;
                    finalFeaturedImage = {
                        url: String(data.url || ''),
                        attach_id: finalFeaturedAttachId,
                        desc: String(data.desc || ''),
                        prompt: cleanPrompt,
                        section: 'featured'
                    };
                    setFeaturedPreview(finalFeaturedImage.url, 'Featured image updated.');
                } else {
                    var nextInternal = {
                        idx: parseInt(data.idx || slot.idx || 0, 10) || parseInt(slot.idx || 0, 10) || 0,
                        url: String(data.url || ''),
                        attach_id: parseInt(data.attach_id || 0, 10) || 0,
                        desc: String(data.desc || ''),
                        prompt: cleanPrompt,
                        section: String(data.section || sectionHint || resolveSlotSection(slot) || 'body')
                    };
                    upsertInternalImage(nextInternal);
                    refreshHtmlWithCurrentImages();
                }
                renderImageCards();
                persistComposerSnapshot();
                return data;
              });
        }

        function regenerateImageSlot() {
            if (!changingImageSlot || !ajaxUrl || !nonce) return;
            var slot = changingImageSlot;
            var prompt = String(imageModalPrompt ? imageModalPrompt.value : '').trim();
            if (!prompt) {
                setImageModalStatus('Enter a prompt to regenerate the image.', true);
                return;
            }
            if (imageModalRegenerate) imageModalRegenerate.disabled = true;
            var regenStartedAt = Date.now();
            setImageModalStatus('Regenerating image... 0s', false);
            var regenTimer = setInterval(function () {
                var sec = Math.floor((Date.now() - regenStartedAt) / 1000);
                setImageModalStatus('Regenerating image... ' + sec + 's', false);
            }, 1000);
            var sectionHint = resolveSlotSection(slot);
            if (slot.type === 'internal') {
                var current = getInternalImageByIdx(slot.idx);
                if (current && current.section) sectionHint = String(current.section);
            }
            regenerateSlotWithPrompt(slot, prompt, sectionHint)
                .then(function () {
                    setImageModalStatus('Image regenerated successfully.', false);
                    setTimeout(closeImageModal, 220);
                })
                .catch(function (err) {
                    setImageModalStatus((err && err.message) ? err.message : 'Could not regenerate the image.', true);
                })
                .finally(function () {
                    clearInterval(regenTimer);
                    if (imageModalRegenerate) imageModalRegenerate.disabled = false;
                });
        }

        function applyLibraryImageToSlot(slot, attachment) {
            if (!slot || !attachment) return;
            var url = String(attachment.url || '').trim();
            if (!url) {
                throw new Error('The selected image does not have a valid URL.');
            }
            var attachId = parseInt(attachment.id || 0, 10) || 0;
            var altText = String(attachment.alt || attachment.title || attachment.filename || '').trim();
            if (slot.type === 'featured') {
                finalFeaturedAttachId = attachId;
                finalFeaturedImage = {
                    url: url,
                    attach_id: attachId,
                    desc: altText || String(finalTitle || 'Featured image'),
                    section: 'featured'
                };
                setFeaturedPreview(url, 'Featured image updated from library.');
            } else {
                var current = getInternalImageByIdx(slot.idx);
                var section = (current && current.section) ? String(current.section) : resolveSlotSection(slot);
                var slotIdx = parseInt(slot.idx || 0, 10) || 1;
                upsertInternalImage({
                    idx: slotIdx,
                    url: url,
                    attach_id: attachId,
                    desc: altText || ('Internal image ' + slotIdx),
                    alt: altText || ('Internal image ' + slotIdx),
                    section: section
                });
                refreshHtmlWithCurrentImages();
            }
            renderImageCards();
            persistComposerSnapshot();
            if (insertBtn) insertBtn.disabled = !canUsePreviewHtml(finalHtml);
        }

        function pickImageFromLibraryForCurrentSlot() {
            if (!changingImageSlot) {
                setImageModalStatus('Image slot was not detected.', true);
                return;
            }
            if (!(window.wp && window.wp.media)) {
                setImageModalStatus('Could not open media library on this screen.', true);
                return;
            }
            var slot = { type: String(changingImageSlot.type || 'internal'), idx: parseInt(changingImageSlot.idx || 0, 10) || 0 };
            var frameTitle = (slot.type === 'featured') ? 'Select featured image' : ('Select internal image ' + String(slot.idx || 1));
            var frame = window.wp.media({
                title: frameTitle,
                button: { text: 'Use this image' },
                library: { type: 'image' },
                multiple: false
            });
            frame.on('select', function () {
                var selection = frame.state().get('selection');
                var first = selection && selection.first ? selection.first() : null;
                if (!first) return;
                var attachment = first.toJSON ? first.toJSON() : null;
                if (!attachment) return;
                try {
                    applyLibraryImageToSlot(slot, attachment);
                    setImageModalStatus('Image selected from library.', false);
                    setTimeout(closeImageModal, 220);
                } catch (eApply) {
                    setImageModalStatus((eApply && eApply.message) ? eApply.message : 'Could not apply selected image.', true);
                }
            });
            frame.open();
        }

        function getMissingImageSlots() {
            var slots = [];
            var hasFeatured = !!(finalFeaturedImage && String(finalFeaturedImage.url || '').trim());
            if (!hasFeatured) {
                slots.push({ type: 'featured', idx: 0 });
            }
            var wanted = getInternalImagesCount();
            for (var i = 1; i <= wanted; i++) {
                var row = getInternalImageByIdx(i);
                var ok = !!(row && String(row.url || '').trim());
                if (!ok) slots.push({ type: 'internal', idx: i });
            }
            return slots;
        }

        function onCompleteMissingClick() {
            if (isGenerating) {
                setStatus('Generation is in progress. Wait until it finishes.', true);
                return;
            }
            applyApiConfigurationState(true);
            var imageOk = isProviderConfigured('image');
            if (!imageOk) {
                setStatus('Image API is missing. Configure it to complete missing images.', true);
                return;
            }
            var title = String((titleInput ? titleInput.value : finalTitle) || finalTitle || '').trim();
            if (!title) {
                setStatus('Enter a title before completing missing items.', true);
                return;
            }
            var htmlCandidate = String(finalHtml || (preview ? preview.innerHTML : '') || '').trim();
            if (!htmlCandidate) {
                setStatus('No content loaded in the composer to complete.', true);
                return;
            }
            var missingSlots = imageOk ? getMissingImageSlots() : [];
            var total = missingSlots.length;
            if (total <= 0) {
                if (getInternalImagesCount() <= 0) {
                    if (!aiCfg.internalImagesEnabled) {
                        setStatus('Internal images are available in Pro only.', false);
                    } else {
                        setStatus('No internal images are configured. To create them, select a number greater than 0 in "Number of internal images" and click "Complete missing" again.', false);
                    }
                } else {
                    setStatus('There are no missing images to complete with the current configuration.', false);
                }
                return;
            }
            if (completeMissingBtn) completeMissingBtn.disabled = true;
            if (insertBtn) insertBtn.disabled = true;
            if (generateBtn) generateBtn.disabled = true;
            setStatus('Completing missing items (images + SEO + categories/tags)...', false);

            var queue = Promise.resolve();
            if (total > 0) {
                startPhaseTimer('Completing missing images');
                missingSlots.forEach(function (slot, idx) {
                    queue = queue.then(function () {
                        var n = idx + 1;
                        var label = 'Regenerating missing image ' + n + '/' + total;
                        startPhaseTimer(label);
                        return regenerateSlotWithPrompt(
                            slot,
                            buildMissingImagePrompt(slot.type, slot.idx, title),
                            resolveSlotSection(slot)
                        );
                    });
                });
            }

            queue.then(function () {
                stopPhaseTimer();
                setStatus('Applying missing content and metadata...', false);
                var modeForApply = internalStyleSelect ? String(internalStyleSelect.value || 'banner') : 'banner';
                var sourceHtml = String(finalHtml || (preview ? preview.innerHTML : '') || '');
                var internalsForApply = normalizeInternalImageSlots(receivedInternalImages, getInternalImagesCount());
                receivedInternalImages = internalsForApply;
                sourceHtml = enforceInternalImageStyle(injectInternalImagesIntoHtml(stripLeadingTitleFromHtml(title, sourceHtml), internalsForApply), modeForApply);
                finalHtml = sourceHtml;
                if (preview) {
                    preview.innerHTML = finalHtml;
                    fillPreviewSlotsFromImages(receivedInternalImages);
                }
                var metad = clampMetaDescription(finalMetaDescription || '');
                if (!metad) {
                    metad = clampMetaDescription(String(sourceHtml || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' '));
                }
                var focus = String(finalFocusKeyphrase || '').trim();
                if (!focus) focus = title;
                return applyFullPostAtomic({
                    title: title,
                    content_html: sourceHtml,
                    focus_keyphrase: focus,
                    meta_description: metad,
                    category_ids: finalCategoryIds,
                    tag_ids: finalTagIds,
                    tag_names: finalTagNames,
                    featured_attach_id: finalFeaturedAttachId,
                    internal_image_style: modeForApply
                });
            }).then(function (res) {
                clearProviderRuntimeState('image');
                applyApiConfigurationState(true);
                if (!res || !res.success || !res.data) {
                    throw new Error((res && res.data && res.data.message) ? res.data.message : 'Could not apply missing items.');
                }
                finalCategoryIds = Array.isArray(res.data.category_ids)
                    ? res.data.category_ids.map(function (v) { return parseInt(v, 10) || 0; }).filter(function (v) { return v > 0; })
                    : finalCategoryIds;
                finalTagIds = Array.isArray(res.data.tag_ids)
                    ? res.data.tag_ids.map(function (v) { return parseInt(v, 10) || 0; }).filter(function (v) { return v > 0; })
                    : finalTagIds;
                if (Array.isArray(res.data.tag_names)) {
                    finalTagNames = res.data.tag_names.map(function (v) { return String(v || '').trim(); }).filter(function (v) { return !!v; });
                }
                if (res.data && typeof res.data.content_html === 'string' && canUsePreviewHtml(res.data.content_html)) {
                    finalHtml = res.data.content_html;
                    if (preview) {
                        preview.innerHTML = finalHtml;
                        fillPreviewSlotsFromImages(receivedInternalImages);
                    }
                }
                if (res.data.featured_attach_id) {
                    finalFeaturedAttachId = parseInt(res.data.featured_attach_id || 0, 10) || finalFeaturedAttachId;
                    syncFeaturedMediaUi(finalFeaturedAttachId);
                    if ((!finalFeaturedImage || !finalFeaturedImage.url) && finalFeaturedAttachId > 0) {
                        resolveAttachmentUrlById(finalFeaturedAttachId).then(function (resolvedUrl) {
                            if (!resolvedUrl) return;
                            finalFeaturedImage = finalFeaturedImage || {};
                            finalFeaturedImage.url = String(resolvedUrl || '');
                            finalFeaturedImage.attach_id = finalFeaturedAttachId;
                            setFeaturedPreview(String(resolvedUrl || ''), 'Featured image updated.');
                        });
                    }
                }
                try {
                    insertInEditor(title, finalHtml, finalFocusKeyphrase || title, finalMetaDescription, finalCategoryIds, finalTagIds, finalTagNames, finalFeaturedAttachId);
                } catch (eInsert) {}
                persistComposerSnapshot();
                setStatus('Missing items completed and applied to the post.', false);
            }).catch(function (err) {
                syncApiStateFromErrorMessage('image', err && err.message ? err.message : '');
                applyApiConfigurationState(true);
                setStatus((err && err.message) ? err.message : 'Could not complete missing items.', true);
            }).finally(function () {
                stopPhaseTimer();
                if (completeMissingBtn) completeMissingBtn.disabled = false;
                if (generateBtn) generateBtn.disabled = false;
                if (insertBtn) insertBtn.disabled = !canUsePreviewHtml(finalHtml);
            });
        }

        function getMissingProviders() {
            var missing = [];
            var textOk = isProviderConfigured('text');
            var imageOk = isProviderConfigured('image');
            if (!textOk) {
                missing.push('text (' + (aiCfg.textProvider || 'openai') + ')');
            }
            if (!imageOk) {
                missing.push('images (' + (aiCfg.imageProvider || 'openai') + ')');
            }
            return missing;
        }

        function isProviderConfigured(scope) {
            var isText = (scope === 'text');
            var provider = isText ? (aiCfg.textProvider || 'openai') : (aiCfg.imageProvider || 'openai');
            var keyMap = aiCfg.providerKeyState || {};
            if (Object.prototype.hasOwnProperty.call(keyMap, provider)) {
                return !!keyMap[provider];
            }
            return isText ? !(aiCfg && aiCfg.textApiConfigured === false) : !(aiCfg && aiCfg.imageApiConfigured === false);
        }

        function markProviderRuntimeState(scope, state, message) {
            if (!aiCfg.runtimeApiState) aiCfg.runtimeApiState = {};
            aiCfg.runtimeApiState[String(scope || '')] = {
                state: String(state || ''),
                message: String(message || '')
            };
        }

        function clearProviderRuntimeState(scope) {
            if (!aiCfg.runtimeApiState) return;
            if (scope) {
                delete aiCfg.runtimeApiState[String(scope)];
                return;
            }
            aiCfg.runtimeApiState = {};
        }

        function syncApiStateFromErrorMessage(scopeHint, msg) {
            var text = String(msg || '').toLowerCase();
            if (!text) return;
            var invalidKey = text.indexOf('incorrect api key') !== -1 || text.indexOf('http 401') !== -1 || text.indexOf('401') !== -1;
            if (!invalidKey) return;
            markProviderRuntimeState(scopeHint || 'text', 'invalid', String(msg || 'Invalid API key.'));
        }

        function formatProviderRuntimeMessage(scope, rawMessage) {
            var msg = String(rawMessage || '').trim();
            var normalizedScope = (scope === 'image') ? 'image' : 'text';
            var provider = normalizedScope === 'image'
                ? String(aiCfg.imageProvider || 'openai')
                : String(aiCfg.textProvider || 'openai');
            var providerLabel = provider === 'google'
                ? 'Google'
                : (provider === 'deepseek' ? 'DeepSeek' : 'OpenAI');
            var lower = msg.toLowerCase();
            if (lower.indexOf('incorrect api key') !== -1 || lower.indexOf('http 401') !== -1 || lower.indexOf('401') !== -1) {
                return providerLabel + ' API key is invalid. Configure it again.';
            }
            if (lower.indexOf('403') !== -1 || lower.indexOf('forbidden') !== -1 || lower.indexOf('unauthorized') !== -1) {
                return providerLabel + ' API rejected authentication. Check the key and permissions.';
            }
            if (lower.indexOf('404') !== -1) {
                return providerLabel + ' endpoint or model was not found. Check configuration.';
            }
            return msg || ('Error de API en ' + providerLabel + '.');
        }

        function applyApiConfigurationState(silentStatus) {
            var missing = getMissingProviders();
            if (chipText) {
                var textOk = isProviderConfigured('text');
                var textRuntime = aiCfg.runtimeApiState && aiCfg.runtimeApiState.text ? aiCfg.runtimeApiState.text : null;
                var textBad = !textOk || (textRuntime && textRuntime.state === 'invalid');
                chipText.textContent = !textOk ? 'Text API: MISSING' : (textBad ? 'Text API: ERROR' : 'Text API: Configured');
                chipText.classList.toggle('is-ok', textOk && !textBad);
                chipText.classList.toggle('is-bad', textBad);
                chipText.title = textRuntime && textRuntime.message ? formatProviderRuntimeMessage('text', textRuntime.message) : (textOk ? 'API key is configured. Real validity is confirmed when using the provider.' : 'You must configure an API key for the text provider.');
            }
            if (chipImage) {
                var imageOk = isProviderConfigured('image');
                var imageRuntime = aiCfg.runtimeApiState && aiCfg.runtimeApiState.image ? aiCfg.runtimeApiState.image : null;
                var imageBad = !imageOk || (imageRuntime && imageRuntime.state === 'invalid');
                chipImage.textContent = !imageOk ? 'Image API: MISSING' : (imageBad ? 'Image API: ERROR' : 'Image API: Configured');
                chipImage.classList.toggle('is-ok', imageOk && !imageBad);
                chipImage.classList.toggle('is-bad', imageBad);
                chipImage.title = imageRuntime && imageRuntime.message ? formatProviderRuntimeMessage('image', imageRuntime.message) : (imageOk ? 'API key is configured. Real validity is confirmed when using the provider.' : 'You must configure an API key for the image provider.');
            }
            if (settingsBtn && aiCfg && aiCfg.settingsUrl) {
                settingsBtn.href = aiCfg.settingsUrl;
            }
            var textInvalid = !!(aiCfg.runtimeApiState && aiCfg.runtimeApiState.text && aiCfg.runtimeApiState.text.state === 'invalid');
            var imageInvalid = !!(aiCfg.runtimeApiState && aiCfg.runtimeApiState.image && aiCfg.runtimeApiState.image.state === 'invalid');
            if (cfgTextBtn) cfgTextBtn.style.display = (!isProviderConfigured('text') || textInvalid) ? '' : 'none';
            if (cfgImageBtn) cfgImageBtn.style.display = (!isProviderConfigured('image') || imageInvalid) ? '' : 'none';
            if (missing.length) {
                if (settingsBtn) settingsBtn.style.display = '';
                // Keep warning on chips/buttons only; avoid transient red status flicker.
                return true;
            }
            if (textInvalid || imageInvalid) {
                if (settingsBtn) settingsBtn.style.display = '';
                return true;
            }
            if (settingsBtn) settingsBtn.style.display = 'none';
            if (generateBtn) generateBtn.disabled = false;
            return true;
        }

        function shouldSkipImages() {
            return !!(allowNoImageToggle && allowNoImageToggle.checked && !isProviderConfigured('image'));
        }

        function setKeyStatus(msg, isError) {
            if (!keyStatus) return;
            keyStatus.textContent = msg || '';
            keyStatus.style.color = isError ? '#b32d2e' : '#2271b1';
        }

        function preflightConfiguredProvider(scope) {
            var normalizedScope = (scope === 'image') ? 'image' : 'text';
            var provider = normalizedScope === 'image'
                ? String(aiCfg.imageProvider || 'openai')
                : String(aiCfg.textProvider || 'openai');
            if (!providerSupportsScope(provider, normalizedScope)) {
                return Promise.reject(new Error('Provider is not compatible with this mode.'));
            }
            if (!isProviderConfigured(normalizedScope)) {
                return Promise.reject(new Error(normalizedScope === 'image'
                    ? 'You must configure an API key for the image provider.'
                    : 'You must configure an API key for the text provider.'));
            }
            var params = new URLSearchParams();
            params.append('action', 'cbia_ai_composer_test_api_key');
            params.append('_ajax_nonce', nonce);
            params.append('provider', provider);
            params.append('scope', normalizedScope);
            params.append('api_key', '');
            params.append('use_existing_key', '1');
            return fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            }).then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || !res.success) {
                        throw new Error((res && res.data && res.data.message) ? res.data.message : 'Could not validate configured API.');
                    }
                    clearProviderRuntimeState(normalizedScope);
                    applyApiConfigurationState(true);
                    return true;
                })
                .catch(function (err) {
                    var msg = (err && err.message) ? String(err.message) : 'Could not validate configured API.';
                    syncApiStateFromErrorMessage(normalizedScope, msg);
                    if (!(String(msg).toLowerCase().indexOf('401') !== -1 || String(msg).toLowerCase().indexOf('incorrect api key') !== -1)) {
                        markProviderRuntimeState(normalizedScope, 'invalid', msg);
                    }
                    applyApiConfigurationState(true);
                    throw new Error(formatProviderRuntimeMessage(normalizedScope, msg));
                });
        }

        function openKeyModalForConfigurationError(message) {
            var text = String(message || '').toLowerCase();
            if (text.indexOf('image provider') !== -1 || text.indexOf('image api') !== -1) {
                openKeyModal('image');
                return true;
            }
            if (text.indexOf('text provider') !== -1 || text.indexOf('text api') !== -1 || text.indexOf('api key') !== -1) {
                openKeyModal('text');
                return true;
            }
            return false;
        }

        function updateKeyHelpText() {
            if (!keyHelp) return;
            var provider = activeKeyProvider || 'openai';
            keyHelp.textContent = 'Current provider: ' + provider + '. Paste the API key and click Save key.';
            if (aiCfg.providerKeyState && aiCfg.providerKeyState[provider]) {
                setKeyStatus('API key is already saved for this provider. You can leave the field empty to reuse it.', false);
            }
        }

        function providerSupportsScope(provider, scope) {
            var p = String(provider || '');
            if (scope === 'image') return p === 'openai' || p === 'google';
            return p === 'openai' || p === 'google' || p === 'deepseek';
        }

        function filterModelsByProvider(provider, scope, models) {
            var p = String(provider || '').toLowerCase();
            var s = String(scope || '').toLowerCase();
            var list = Array.isArray(models) ? models.slice() : [];
            if (!list.length) return list;
            var filtered = list.filter(function (m) {
                var name = String(m || '').toLowerCase();
                if (!name) return false;
                if (p === 'openai') {
                    return s === 'image'
                        ? /^gpt-image|^dall-e/.test(name)
                        : /^gpt-|^o\d|^chatgpt/.test(name);
                }
                if (p === 'google') {
                    return /^gemini|^imagen/.test(name);
                }
                if (p === 'deepseek') {
                    return /^deepseek/.test(name);
                }
                return true;
            });
            return filtered.length ? filtered : list;
        }

        function updateKeyModelOptions() {
            if (!keyModelSelect) return;
            var provider = String(activeKeyProvider || 'openai');
            var isImage = (activeKeyScope === 'image');
            var modelLists = isImage ? (aiCfg.imageModelLists || {}) : (aiCfg.textModelLists || {});
            var currentModels = isImage ? (aiCfg.currentImageModels || {}) : (aiCfg.currentTextModels || {});
            var models = Array.isArray(modelLists[provider]) ? modelLists[provider].slice() : [];
            models = filterModelsByProvider(provider, activeKeyScope, models);
            var current = String(currentModels[provider] || '');
            if (current && models.indexOf(current) === -1) {
                models.unshift(current);
            }

            keyModelSelect.innerHTML = '';
            if (!models.length) {
                var optEmpty = document.createElement('option');
                optEmpty.value = '';
                optEmpty.textContent = 'Modelo por defecto';
                keyModelSelect.appendChild(optEmpty);
                keyModelSelect.value = '';
                if (keySave) keySave.disabled = true;
                if (keyTest) keyTest.disabled = true;
                setKeyStatus('This provider has no models available for this mode.', true);
                return;
            }

            models.forEach(function (m) {
                var opt = document.createElement('option');
                opt.value = String(m || '');
                opt.textContent = String(m || '');
                keyModelSelect.appendChild(opt);
            });
            if (current) {
                keyModelSelect.value = current;
            } else {
                keyModelSelect.selectedIndex = 0;
            }
            if (keySave) keySave.disabled = false;
            if (keyTest) keyTest.disabled = false;
        }

        function openKeyModal(scope) {
            refreshKeyModalRefs();
            if (!keyModal) return;
            activeKeyScope = scope === 'image' ? 'image' : 'text';
            activeKeyProvider = activeKeyScope === 'image' ? (aiCfg.imageProvider || 'openai') : (aiCfg.textProvider || 'openai');
            if (!providerSupportsScope(activeKeyProvider, activeKeyScope)) {
                activeKeyProvider = 'openai';
            }
            if (keyTitle) keyTitle.textContent = activeKeyScope === 'image' ? 'Configure image API' : 'Configure text API';
            if (keyProviderSelect) {
                Array.prototype.slice.call(keyProviderSelect.options || []).forEach(function (opt) {
                    opt.disabled = !providerSupportsScope(String(opt.value || ''), activeKeyScope);
                    opt.style.display = opt.disabled ? 'none' : '';
                });
                keyProviderSelect.value = activeKeyProvider;
            }
            updateKeyHelpText();
            updateKeyModelOptions();
            if (keyInput) keyInput.value = '';
            if (!(aiCfg.providerKeyState && aiCfg.providerKeyState[activeKeyProvider])) {
                setKeyStatus('', false);
            }
            keyModal.style.display = 'flex';
            document.body.classList.add('cbia-modal-open');
            if (keyInput) keyInput.focus();
        }

        function closeKeyModal() {
            refreshKeyModalRefs();
            if (!keyModal) return;
            keyModal.style.display = 'none';
        }

        function saveKeyFromModal() {
            refreshKeyModalRefs();
            if (keyProviderSelect && keyProviderSelect.value) {
                activeKeyProvider = String(keyProviderSelect.value || activeKeyProvider || 'openai');
            }
            if (!providerSupportsScope(activeKeyProvider, activeKeyScope)) {
                setKeyStatus('Provider is not compatible with this mode.', true);
                return;
            }
            if (!activeKeyProvider) return;
            var key = keyInput ? String(keyInput.value || '').trim() : '';
            var model = keyModelSelect ? String(keyModelSelect.value || '').trim() : '';
            var hasStoredKey = !!(aiCfg.providerKeyState && aiCfg.providerKeyState[activeKeyProvider]);
            if (!key && !hasStoredKey) {
                setKeyStatus('Enter an API key.', true);
                return;
            }
            setKeyStatus('Saving...', false);
            if (keySave) keySave.disabled = true;
            var params = new URLSearchParams();
            params.append('action', 'cbia_ai_composer_save_api_key');
            params.append('_ajax_nonce', nonce);
            params.append('provider', activeKeyProvider);
            params.append('scope', activeKeyScope);
            params.append('model', model);
            params.append('api_key', key);
            params.append('use_existing_key', (!key && hasStoredKey) ? '1' : '0');
            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || !res.success) {
                        throw new Error((res && res.data && res.data.message) ? res.data.message : 'Could not save key.');
                    }
                    if (res.data && res.data.providerKeyState) aiCfg.providerKeyState = res.data.providerKeyState;
                    if (res.data && res.data.textProvider) aiCfg.textProvider = String(res.data.textProvider || aiCfg.textProvider || 'openai');
                    if (res.data && res.data.imageProvider) aiCfg.imageProvider = String(res.data.imageProvider || aiCfg.imageProvider || 'openai');
                    if (res.data && res.data.textModel && aiCfg.currentTextModels) {
                        aiCfg.currentTextModels[aiCfg.textProvider || 'openai'] = String(res.data.textModel || '');
                    }
                    if (res.data && res.data.imageModel && aiCfg.currentImageModels) {
                        aiCfg.currentImageModels[aiCfg.imageProvider || 'openai'] = String(res.data.imageModel || '');
                    }
                    aiCfg.textApiConfigured = !!(res.data && res.data.textApiConfigured);
                    aiCfg.imageApiConfigured = !!(res.data && res.data.imageApiConfigured);
                    if (aiCfg.providerKeyState) {
                        aiCfg.textApiConfigured = !!aiCfg.providerKeyState[aiCfg.textProvider || 'openai'];
                        aiCfg.imageApiConfigured = !!aiCfg.providerKeyState[aiCfg.imageProvider || 'openai'];
                    }
                    setKeyStatus('Key saved successfully.', false);
                    applyApiConfigurationState();
                    setTimeout(closeKeyModal, 350);
                })
                .catch(function (err) {
                    setKeyStatus((err && err.message) ? err.message : 'Error saving key.', true);
                })
                .finally(function () {
                    if (keySave) keySave.disabled = false;
                });
        }

        function splitChunks(html) {
            var source = String(html || '');
            if (!source.trim()) return [];
            var parts = source.split(/(<[^>]+>)/g);
            var chunks = [];
            var acc = '';
            var words = 0;
            for (var i = 0; i < parts.length; i++) {
                var part = String(parts[i] || '');
                if (!part) continue;
                if (part.charAt(0) === '<') {
                    acc += part;
                    if (/<\/(?:p|h1|h2|h3|h4|h5|h6|ul|ol|li|figure|div)>/i.test(part) || /<br\s*\/?>/i.test(part) || /<img\b/i.test(part)) {
                        chunks.push(acc);
                        words = 0;
                    }
                    continue;
                }
                var textPieces = part.split(/(\s+)/);
                for (var j = 0; j < textPieces.length; j++) {
                    var piece = String(textPieces[j] || '');
                    if (!piece) continue;
                    acc += piece;
                    if (/\S/.test(piece)) words += 1;
                    if (words >= 3) {
                        chunks.push(acc);
                        words = 0;
                    }
                }
            }
            if (!chunks.length && acc) chunks.push(acc);
            if (chunks.length && chunks[chunks.length - 1] !== acc) chunks.push(acc);
            return chunks;
        }

        function normalizePreviewHtml(html) {
            var source = String(html || '');
            if (!source) return source;
            source = source.replace(/\[(?:IMAGE_PENDING|IMAGEN_PENDIENTE)\s*:[^\]]+\]\s*\.?/gi, '<div class=\"cbia-ai-image-slot\"><span class=\"dashicons dashicons-format-image\" aria-hidden=\"true\"></span><span>Generating image...</span></div>');
            return source.replace(/\[(?:IMAGE|IMAGEN)\s*:[^\]]+\]\s*\.?/gi, '<div class=\"cbia-ai-image-slot\"><span class=\"dashicons dashicons-format-image\" aria-hidden=\"true\"></span><span>Generating image...</span></div>');
        }

        function animatePreview(html) {
            if (!preview) return;
            html = normalizePreviewHtml(html);
            typingSeq += 1;
            var seq = typingSeq;
            if (typingTimer) {
                clearTimeout(typingTimer);
                typingTimer = null;
            }
            var chunks = splitChunks(html);
            if (!chunks.length) {
                preview.innerHTML = html || '';
                return;
            }
            var idx = 0;
            function tick() {
                if (seq !== typingSeq) return;
                if (!preview) return;
                preview.innerHTML = chunks[idx] || '';
                idx += 1;
                if (idx < chunks.length) {
                    typingTimer = setTimeout(tick, 320);
                } else {
                    typingTimer = null;
                }
            }
            tick();
        }

        function clearComposerProgressiveQueue() {
            progressiveQueue = [];
            progressiveLastHtml = '';
            latestStreamHtml = '';
            freezeTextOnQueueDrain = false;
            if (progressiveTimer) {
                clearTimeout(progressiveTimer);
                progressiveTimer = null;
            }
        }

        function runComposerProgressiveQueue() {
            if (progressiveTimer || !progressiveQueue.length) return;
            function nextDelayByRemaining(remaining) {
                if (remaining > 24) return 36;
                if (remaining > 16) return 48;
                if (remaining > 10) return 62;
                if (remaining > 6) return 76;
                if (remaining > 3) return 92;
                return 108;
            }
            var tick = function () {
                if (!progressiveQueue.length) {
                    progressiveTimer = null;
                    if (freezeTextOnQueueDrain && latestStreamHtml && preview) {
                        preview.innerHTML = latestStreamHtml;
                        freezeTextOnQueueDrain = false;
                    }
                    return;
                }
                var next = progressiveQueue.shift();
                if (preview && typeof next.html === 'string') {
                    preview.innerHTML = next.html;
                }
                var liveCount = parseInt(next.word_count || 0, 10);
                if (!isNaN(liveCount) && liveCount > 0) {
                    setWordCount(liveCount);
                }
                progressiveTimer = setTimeout(tick, nextDelayByRemaining(progressiveQueue.length));
            };
            progressiveTimer = setTimeout(tick, 40);
        }

        function enqueueComposerProgressiveHtml(html, wordCount) {
            if (typeof html !== 'string') return;
            var clean = html.trim();
            if (!clean || clean === progressiveLastHtml) return;
            progressiveLastHtml = clean;
            latestStreamHtml = clean;
            progressiveQueue.push({
                html: clean,
                word_count: parseInt(wordCount || 0, 10) || 0
            });
            runComposerProgressiveQueue();
        }

        function stopTypingAnimation() {
            typingSeq += 1;
            if (typingTimer) {
                clearTimeout(typingTimer);
                typingTimer = null;
            }
        }

        function canUsePreviewHtml(html) {
            var s = String(html || '').trim();
            if (!s) return false;
            if (s === '<em>No preview yet.</em>' || s === '<em>Generating...</em>') return false;
            return true;
        }

        function clampMetaDescription(value) {
            var text = String(value || '').trim();
            if (!text) return '';
            if (text.length <= 139) return text;
            var limit = 139;
            var hardCut = text.substring(0, Math.max(0, limit - 3)).trim();
            var softCut = hardCut.replace(/\s+\S*$/, '').trim();
            var out = (softCut.length >= 40 ? softCut : hardCut).replace(/[.\s]+$/, '');
            return (out ? out : hardCut) + '...';
        }

        function normalizeTitleForCompare(value) {
            return String(value || '')
                .toLowerCase()
                .replace(/<[^>]+>/g, ' ')
                .replace(/[^\p{L}\p{N}\s]/gu, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function stripLeadingTitleFromHtml(postTitle, html) {
            var source = String(html || '');
            var titleNorm = normalizeTitleForCompare(postTitle);
            if (!source || !titleNorm) return source;
            var wrap = document.createElement('div');
            wrap.innerHTML = source;
            var first = wrap.firstElementChild;
            if (!first) return source;
            var tag = String(first.tagName || '').toLowerCase();
            var textNorm = normalizeTitleForCompare(first.textContent || '');
            var isTitleBlock = (tag === 'h1' || tag === 'h2' || tag === 'p') && textNorm === titleNorm;
            if (isTitleBlock) {
                first.remove();
            }
            return wrap.innerHTML;
        }

        var pendingYoastPayload = null;
        var pendingYoastTimer = null;

        function persistYoastMetaForPost(postId, payload, attempt) {
            var pid = parseInt(postId || 0, 10);
            var tries = parseInt(attempt || 0, 10);
            if (!ajaxUrl || !nonce || !payload) {
                return Promise.resolve(false);
            }
            if (!pid) {
                pid = resolveCurrentPostId();
            }
            if (!pid && window.wp && window.wp.data && window.wp.data.dispatch) {
                try { window.wp.data.dispatch('core/editor').savePost(); } catch (e0) {}
            }
            if (!pid) {
                if (tries < 20) {
                    return new Promise(function (resolve) {
                        setTimeout(function () {
                            resolve(persistYoastMetaForPost(resolveCurrentPostId(), payload, tries + 1));
                        }, 1000);
                    });
                }
                return Promise.resolve(false);
            }
            var params = new URLSearchParams();
            params.append('action', 'cbia_ai_composer_apply_yoast_meta');
            params.append('_ajax_nonce', nonce);
            params.append('post_id', String(pid));
            params.append('focus_keyphrase', String(payload.focus_keyphrase || ''));
            params.append('meta_description', String(payload.meta_description || ''));
            params.append('seo_title', String(payload.seo_title || ''));
            params.append('og_title', String(payload.og_title || ''));
            params.append('og_description', String(payload.og_description || ''));
            params.append('tw_title', String(payload.tw_title || ''));
            params.append('tw_description', String(payload.tw_description || ''));
            params.append('primary_category', String(payload.primary_category || 0));
            var titleHint = String(payload.seo_title || '');
            var contentHtml = '';
            try {
                if (window.wp && window.wp.data && window.wp.data.select) {
                    titleHint = String(window.wp.data.select('core/editor').getEditedPostAttribute('title') || titleHint || '');
                    contentHtml = String(window.wp.data.select('core/editor').getEditedPostContent() || '');
                }
            } catch (e0) {}
            params.append('post_title_hint', titleHint);
            params.append('content_html', contentHtml);
            return fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            }).then(function (r) { return r.json(); })
              .then(function (res) {
                  var ok = !!(res && res.success);
                  if (ok) return true;
                  if (tries < 20) {
                      return new Promise(function (resolve) {
                          setTimeout(function () {
                              resolve(persistYoastMetaForPost(pid, payload, tries + 1));
                          }, 1000);
                      });
                  }
                  return false;
              })
              .catch(function () {
                  if (tries < 20) {
                      return new Promise(function (resolve) {
                          setTimeout(function () {
                              resolve(persistYoastMetaForPost(pid, payload, tries + 1));
                          }, 1000);
                      });
                  }
                  return false;
              });
        }

        function ensureYoastMetaPersisted(payload, postIdOverride) {
            if (!payload) return;
            pendingYoastPayload = payload;
            var pid = parseInt(postIdOverride || 0, 10) || resolveCurrentPostId();
            persistYoastMetaForPost(pid, payload).then(function (ok) {
                if (!ok) {
                    setStatus('Contenido insertado. Reintentando sincronizar Yoast...', false);
                }
            });
            setTimeout(function () {
                persistYoastMetaForPost(parseInt(postIdOverride || 0, 10) || resolveCurrentPostId(), payload);
            }, 2500);
            setTimeout(function () {
                persistYoastMetaForPost(parseInt(postIdOverride || 0, 10) || resolveCurrentPostId(), payload);
            }, 6000);
            setTimeout(function () {
                persistYoastMetaForPost(parseInt(postIdOverride || 0, 10) || resolveCurrentPostId(), payload);
            }, 12000);
        }

        function persistTermsForPost(postId, cats, tags, attempt, tagNamesOverride, featuredOverride) {
            var pid = parseInt(postId || 0, 10);
            var tries = parseInt(attempt || 0, 10);
            var featuredId = parseInt(featuredOverride || finalFeaturedAttachId || 0, 10) || 0;
            if (!ajaxUrl || !nonce) {
                return Promise.resolve(false);
            }
            if (!pid) pid = resolveCurrentPostId();
            if (!pid && window.wp && window.wp.data && window.wp.data.dispatch) {
                try { window.wp.data.dispatch('core/editor').savePost(); } catch (e0) {}
            }
            if (!pid) {
                if (tries < 20) {
                    return new Promise(function (resolve) {
                        setTimeout(function () {
                            resolve(persistTermsForPost(resolveCurrentPostId(), cats, tags, tries + 1, tagNamesOverride, featuredId));
                        }, 1000);
                    });
                }
                return Promise.resolve(false);
            }
            var params = new URLSearchParams();
            params.append('action', 'cbia_ai_composer_apply_terms');
            params.append('_ajax_nonce', nonce);
            params.append('post_id', String(pid));
            if (featuredId > 0) params.append('featured_attach_id', String(featuredId));
            (Array.isArray(cats) ? cats : []).forEach(function (id) {
                var v = parseInt(id, 10) || 0;
                if (v > 0) params.append('category_ids[]', String(v));
            });
            (Array.isArray(tags) ? tags : []).forEach(function (id) {
                var v = parseInt(id, 10) || 0;
                if (v > 0) params.append('tag_ids[]', String(v));
            });
            (Array.isArray(tagNamesOverride) ? tagNamesOverride : (Array.isArray(finalTagNames) ? finalTagNames : [])).forEach(function (name) {
                var v = String(name || '').trim();
                if (v) params.append('tag_names[]', v);
            });
            return fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            }).then(function (r) { return r.json(); })
              .then(function (res) {
                  var ok = !!(res && res.success);
                  if (ok) return true;
                  if (tries < 20) {
                      return new Promise(function (resolve) {
                          setTimeout(function () {
                              resolve(persistTermsForPost(pid, cats, tags, tries + 1, tagNamesOverride, featuredId));
                          }, 1000);
                      });
                  }
                  return false;
              })
              .catch(function () {
                  if (tries < 20) {
                      return new Promise(function (resolve) {
                          setTimeout(function () {
                              resolve(persistTermsForPost(pid, cats, tags, tries + 1, tagNamesOverride, featuredId));
                          }, 1000);
                      });
                  }
                  return false;
              });
        }

        function ensureTermsPersisted(cats, tags, postIdOverride, tagNamesOverride, featuredOverride) {
            var arrCats = Array.isArray(cats) ? cats : [];
            var arrTags = Array.isArray(tags) ? tags : [];
            var arrTagNames = Array.isArray(tagNamesOverride) ? tagNamesOverride : (Array.isArray(finalTagNames) ? finalTagNames : []);
            var featuredId = parseInt(featuredOverride || finalFeaturedAttachId || 0, 10) || 0;
            if (!arrCats.length && !arrTags.length && !arrTagNames.length && featuredId <= 0) return;
            var pid = parseInt(postIdOverride || 0, 10) || resolveCurrentPostId();
            persistTermsForPost(pid, arrCats, arrTags, 0, arrTagNames, featuredId);
            setTimeout(function () { persistTermsForPost(parseInt(postIdOverride || 0, 10) || resolveCurrentPostId(), arrCats, arrTags, 0, arrTagNames, featuredId); }, 2500);
            setTimeout(function () { persistTermsForPost(parseInt(postIdOverride || 0, 10) || resolveCurrentPostId(), arrCats, arrTags, 0, arrTagNames, featuredId); }, 6000);
        }

        function schedulePendingYoastSync() {
            if (pendingYoastTimer) return;
            pendingYoastTimer = setInterval(function () {
                if (!pendingYoastPayload) return;
                var pid = resolveCurrentPostId();
                if (!pid) return;
                persistYoastMetaForPost(pid, pendingYoastPayload).then(function (ok) {
                    if (ok) {
                        pendingYoastPayload = null;
                    }
                });
            }, 2000);
        }

        function resolveCurrentPostId() {
            try {
                if (window.wp && window.wp.data && window.wp.data.select) {
                    var pid = parseInt(window.wp.data.select('core/editor').getCurrentPostId() || 0, 10);
                    if (pid > 0) return pid;
                }
            } catch (e) {}
            var hidden = document.getElementById('post_ID');
            var legacy = parseInt((hidden && hidden.value) ? hidden.value : 0, 10);
            return legacy > 0 ? legacy : 0;
        }

        function isCurrentEditorNewPost() {
            try {
                if (window.wp && window.wp.data && window.wp.data.select) {
                    var editor = window.wp.data.select('core/editor');
                    if (editor && typeof editor.isEditedPostNew === 'function') {
                        return !!editor.isEditedPostNew();
                    }
                    if (editor && typeof editor.getCurrentPost === 'function') {
                        var post = editor.getCurrentPost();
                        var status = String((post && post.status) || '').toLowerCase();
                        if (status === 'auto-draft') return true;
                    }
                }
            } catch (_eNewPost) {}
            return (parseInt(resolveCurrentPostId() || 0, 10) || 0) <= 0;
        }

        function setEffectivePostIdForComposer(postId) {
            var pid = parseInt(postId || 0, 10) || 0;
            if (pid <= 0) return 0;
            try { root.setAttribute('data-current-post-id', String(pid)); } catch (_eRootPid) {}
            var hiddenPostId = document.getElementById('post_ID');
            if (hiddenPostId) {
                hiddenPostId.value = String(pid);
                triggerInputSync(hiddenPostId);
            }
            return pid;
        }

        function saveEditorDraftAfterComposer() {
            try {
                if (window.wp && window.wp.data && window.wp.data.dispatch) {
                    var editorDispatch = window.wp.data.dispatch('core/editor');
                    if (editorDispatch && typeof editorDispatch.savePost === 'function') {
                        var saved = editorDispatch.savePost();
                        if (saved && typeof saved.then === 'function') {
                            return saved;
                        }
                        return Promise.resolve(true);
                    }
                }
            } catch (_eSaveComposer) {}
            return Promise.resolve(false);
        }

        function restoreRedirectTitleSync() {
            try {
                var raw = sessionStorage.getItem('cbia_ai_title_after_redirect');
                if (!raw) return;
                var payload = JSON.parse(raw);
                var forcedTitle = String((payload && payload.title) ? payload.title : '').trim();
                var forcedPostId = parseInt((payload && payload.post_id) ? payload.post_id : 0, 10) || 0;
                var currentPostId = parseInt(resolveCurrentPostId() || 0, 10) || 0;
                if (!forcedTitle) {
                    sessionStorage.removeItem('cbia_ai_title_after_redirect');
                    return;
                }
                if (forcedPostId > 0 && currentPostId > 0 && forcedPostId !== currentPostId) {
                    return;
                }
                syncEditorTitle(forcedTitle);
                setTimeout(function () { syncEditorTitle(forcedTitle); }, 180);
                setTimeout(function () { syncEditorTitle(forcedTitle); }, 900);
                sessionStorage.removeItem('cbia_ai_title_after_redirect');
            } catch (e) {}
        }

        function triggerInputSync(el) {
            if (!el) return;
            try { el.dispatchEvent(new Event('input', { bubbles: true })); } catch (e1) {}
            try { el.dispatchEvent(new Event('change', { bubbles: true })); } catch (e2) {}
        }

        function setFieldValue(selector, value) {
            var el = document.querySelector(selector);
            if (!el) return;
            var next = String(value || '');
            if (el.value === next) return;
            el.value = next;
            triggerInputSync(el);
        }

        function ensureClassicPostField(name, id, value, type) {
            var form = document.getElementById('post') || document.querySelector('form[name="post"]') || document.querySelector('form#post');
            var selector = id ? ('#' + id) : '';
            var field = selector ? document.querySelector(selector) : null;
            if (!field && name) field = document.querySelector('[name="' + name.replace(/"/g, '\\"') + '"]');
            if (!field) {
                field = document.createElement('input');
                field.type = type || 'hidden';
                field.name = name;
                if (id) field.id = id;
                (form || document.body).appendChild(field);
            }
            field.value = String(value || '');
            triggerInputSync(field);
            return field;
        }

        function syncEditorTitle(title) {
            var t = String(title || '').trim();
            if (!t) return;
            if (titleInput) titleInput.value = t;
            setFieldValue('#title', t);
            setFieldValue('input[name=\"post_title\"]', t);
            setFieldValue('#post-title-0', t);
            setFieldValue('.editor-post-title__input', t);
            var classicTitle = document.getElementById('title');
            if (classicTitle) {
                classicTitle.value = t;
                triggerInputSync(classicTitle);
            }
            if (window.jQuery) {
                try {
                    window.jQuery('#title').val(t).trigger('input').trigger('change').trigger('keyup');
                } catch (eJq) {}
            }
            try {
                if (window.wp && window.wp.data && window.wp.data.dispatch) {
                    window.wp.data.dispatch('core/editor').editPost({ title: t });
                }
            } catch (e0) {}
        }

        function syncFeaturedMediaUi(attachId) {
            var mediaId = parseInt(attachId || 0, 10) || 0;
            if (mediaId <= 0) return;
            try {
                ensureClassicPostField('_thumbnail_id', '_thumbnail_id', String(mediaId), 'hidden');
            } catch (_eThumbHidden) {}
            try {
                if (window.wp && window.wp.data && window.wp.data.dispatch) {
                    window.wp.data.dispatch('core/editor').editPost({ featured_media: mediaId });
                    var coreDispatch = window.wp.data.dispatch('core');
                    if (coreDispatch && typeof coreDispatch.invalidateResolution === 'function') {
                        coreDispatch.invalidateResolution('getEntityRecord', ['postType', 'attachment', mediaId]);
                    }
                }
            } catch (e0) {}
            try {
                if (window.wp && window.wp.data && window.wp.data.resolveSelect) {
                    window.wp.data.resolveSelect('core').getEntityRecord('postType', 'attachment', mediaId);
                }
            } catch (e1) {}
            try {
                if (window.wp && window.wp.apiFetch) {
                    window.wp.apiFetch({ path: '/wp/v2/media/' + mediaId + '?context=edit' }).then(function (record) {
                        try {
                            var coreDispatch = (window.wp && window.wp.data && window.wp.data.dispatch)
                                ? window.wp.data.dispatch('core')
                                : null;
                            if (coreDispatch && typeof coreDispatch.receiveEntityRecords === 'function' && record) {
                                coreDispatch.receiveEntityRecords('postType', 'attachment', [record], undefined, true);
                            }
                        } catch (_eReceive) {}
                    }).catch(function () {});
                }
            } catch (e2) {}
        }

        function receiveTaxonomyRecords(taxonomy, ids) {
            var cleanIds = Array.isArray(ids)
                ? ids.map(function (v) { return parseInt(v, 10) || 0; }).filter(function (v) { return v > 0; })
                : [];
            if (!cleanIds.length || !window.wp || !window.wp.apiFetch) return Promise.resolve(false);
            var endpoint = taxonomy === 'category' ? 'categories' : 'tags';
            return window.wp.apiFetch({ path: '/wp/v2/' + endpoint + '?context=edit&include=' + encodeURIComponent(cleanIds.join(',')) + '&per_page=100' })
                .then(function (records) {
                    try {
                        var coreDispatch = (window.wp && window.wp.data && window.wp.data.dispatch)
                            ? window.wp.data.dispatch('core')
                            : null;
                        if (coreDispatch && typeof coreDispatch.receiveEntityRecords === 'function' && Array.isArray(records)) {
                            coreDispatch.receiveEntityRecords('taxonomy', taxonomy, records, undefined, true);
                        }
                    } catch (_eReceiveTerms) {}
                    return true;
                })
                .catch(function () { return false; });
        }

        function refreshEditorPostStateFromServer(postId, fallbackCats, fallbackTags, fallbackTagNames, fallbackFeatured) {
            var pid = parseInt(postId || 0, 10) || resolveCurrentPostId();
            if (!pid || !window.wp || !window.wp.apiFetch || !window.wp.data || !window.wp.data.dispatch) {
                syncEditorTermsUi(fallbackCats || [], fallbackTags || [], fallbackTagNames || []);
                if (parseInt(fallbackFeatured || 0, 10) > 0) syncFeaturedMediaUi(fallbackFeatured);
                return Promise.resolve(false);
            }
            return window.wp.apiFetch({ path: '/wp/v2/posts/' + pid + '?context=edit&_fields=id,featured_media,categories,tags,meta' })
                .then(function (post) {
                    var cats = Array.isArray(post && post.categories) ? post.categories : (fallbackCats || []);
                    var tags = Array.isArray(post && post.tags) ? post.tags : (fallbackTags || []);
                    var featured = parseInt((post && post.featured_media) || fallbackFeatured || 0, 10) || 0;
                    var patch = {};
                    if (cats.length) patch.categories = cats.map(function (v) { return parseInt(v, 10) || 0; }).filter(function (v) { return v > 0; });
                    if (tags.length) patch.tags = tags.map(function (v) { return parseInt(v, 10) || 0; }).filter(function (v) { return v > 0; });
                    if (featured > 0) patch.featured_media = featured;
                    if (post && post.meta && typeof post.meta === 'object') patch.meta = post.meta;
                    try {
                        var editorDispatch = window.wp.data.dispatch('core/editor');
                        if (editorDispatch && typeof editorDispatch.editPost === 'function' && Object.keys(patch).length) {
                            editorDispatch.editPost(patch);
                        }
                        var coreDispatch = window.wp.data.dispatch('core');
                        if (coreDispatch && typeof coreDispatch.editEntityRecord === 'function' && Object.keys(patch).length) {
                            coreDispatch.editEntityRecord('postType', 'post', pid, patch);
                        }
                    } catch (_eRefreshStore) {}
                    syncEditorTermsUi(patch.categories || cats, patch.tags || tags, fallbackTagNames || []);
                    if (featured > 0) {
                        syncFeaturedMediaUi(featured);
                        setTimeout(function () { syncFeaturedMediaUi(featured); }, 500);
                        setTimeout(function () { syncFeaturedMediaUi(featured); }, 1600);
                    }
                    return Promise.all([
                        receiveTaxonomyRecords('category', patch.categories || cats),
                        receiveTaxonomyRecords('post_tag', patch.tags || tags)
                    ]).then(function () { return true; });
                })
                .catch(function () {
                    syncEditorTermsUi(fallbackCats || [], fallbackTags || [], fallbackTagNames || []);
                    if (parseInt(fallbackFeatured || 0, 10) > 0) syncFeaturedMediaUi(fallbackFeatured);
                    return false;
                });
        }

        function syncEditorTermsUi(categoryIds, tagIds, tagNames) {
            var cats = Array.isArray(categoryIds)
                ? categoryIds.map(function (v) { return parseInt(v, 10) || 0; }).filter(function (v) { return v > 0; })
                : [];
            var tags = Array.isArray(tagIds)
                ? tagIds.map(function (v) { return parseInt(v, 10) || 0; }).filter(function (v) { return v > 0; })
                : [];
            var labels = Array.isArray(tagNames)
                ? tagNames.map(function (v) { return String(v || '').trim(); }).filter(function (v) { return !!v; })
                : [];
            if (!cats.length && !tags.length && !labels.length) return;

            try {
                if (window.wp && window.wp.data && window.wp.data.dispatch) {
                    var patch = {};
                    if (cats.length) patch.categories = cats.slice();
                    if (tags.length) patch.tags = tags.slice();
                    if (Object.keys(patch).length) {
                        var editorDispatch = window.wp.data.dispatch('core/editor');
                        if (editorDispatch && typeof editorDispatch.editPost === 'function') {
                            editorDispatch.editPost(patch);
                        }
                        var postId = resolveCurrentPostId();
                        var coreDispatch = window.wp.data.dispatch('core');
                        if (postId > 0 && coreDispatch && typeof coreDispatch.editEntityRecord === 'function') {
                            coreDispatch.editEntityRecord('postType', 'post', postId, patch);
                        }
                    }
                }
            } catch (e0) {}

            cats.forEach(function (id) {
                try {
                    var selector = '#categorychecklist input[type="checkbox"][value="' + id + '"], #categorychecklist-pop input[type="checkbox"][value="' + id + '"]';
                    var boxes = document.querySelectorAll(selector);
                    Array.prototype.forEach.call(boxes, function (cb) {
                        cb.checked = true;
                        triggerInputSync(cb);
                    });
                } catch (e1) {}
            });

            if (labels.length) {
                var form = document.getElementById('post') || document.querySelector('form[name="post"]') || document.querySelector('form#post');
                var holder = document.getElementById('cbia-ai-composer-hidden-tags');
                if (!holder) {
                    holder = document.createElement('div');
                    holder.id = 'cbia-ai-composer-hidden-tags';
                    holder.style.display = 'none';
                    (form || document.body).appendChild(holder);
                }
                holder.innerHTML = '';
                labels.forEach(function (name) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'tax_input[post_tag][]';
                    input.value = name;
                    input.className = 'cbia-ai-term-hidden';
                    holder.appendChild(input);
                });
                ensureClassicPostField('tax_input[post_tag]', 'tax-input-post_tag', labels.join(', '), 'hidden');
                var newTagInput = document.getElementById('new-tag-post_tag');
                if (newTagInput) {
                    newTagInput.value = '';
                    triggerInputSync(newTagInput);
                }
            }
        }

        function getCurrentEditorTitle() {
            try {
                if (window.wp && window.wp.data && window.wp.data.select) {
                    var t = String(window.wp.data.select('core/editor').getEditedPostAttribute('title') || '').trim();
                    if (t) return t;
                }
            } catch (e0) {}
            var classic = document.getElementById('title');
            if (classic && String(classic.value || '').trim()) return String(classic.value || '').trim();
            var gb = document.querySelector('#post-title-0');
            if (gb && String(gb.value || '').trim()) return String(gb.value || '').trim();
            return '';
        }

        function updateYoastUiFields(payload) {
            if (!payload) return;
            setFieldValue('input[name=\"yoast_wpseo_focuskw_text_input\"], input[name=\"yoast_wpseo_focuskw\"], #yoast_wpseo_focuskw', payload.focus_keyphrase || '');
            setFieldValue('textarea[name=\"yoast_wpseo_metadesc\"], #yoast_wpseo_metadesc', payload.meta_description || '');
            setFieldValue('input[name=\"yoast_wpseo_title\"], #yoast_wpseo_title', payload.seo_title || '');
            setFieldValue('input[name=\"yoast_wpseo_opengraph-title\"], #yoast_wpseo_opengraph-title', payload.og_title || '');
            setFieldValue('textarea[name=\"yoast_wpseo_opengraph-description\"], #yoast_wpseo_opengraph-description', payload.og_description || '');
            setFieldValue('input[name=\"yoast_wpseo_twitter-title\"], #yoast_wpseo_twitter-title', payload.tw_title || '');
            setFieldValue('textarea[name=\"yoast_wpseo_twitter-description\"], #yoast_wpseo_twitter-description', payload.tw_description || '');
            if (parseInt(payload.primary_category || 0, 10) > 0) {
                setFieldValue('input[name=\"yoast_wpseo_primary_category\"], #yoast_wpseo_primary_category', String(payload.primary_category));
            }
            try {
                if (window.wp && window.wp.data && window.wp.data.dispatch) {
                    var yoastDispatch = window.wp.data.dispatch('yoast-seo/editor');
                    if (yoastDispatch) {
                        if (typeof yoastDispatch.setFocusKeyphrase === 'function') yoastDispatch.setFocusKeyphrase(String(payload.focus_keyphrase || ''));
                        if (typeof yoastDispatch.setFocusKeyword === 'function') yoastDispatch.setFocusKeyword(String(payload.focus_keyphrase || ''));
                        if (typeof yoastDispatch.setMetaDescription === 'function') yoastDispatch.setMetaDescription(String(payload.meta_description || ''));
                        if (typeof yoastDispatch.setSnippetTitle === 'function') yoastDispatch.setSnippetTitle(String(payload.seo_title || ''));
                        if (typeof yoastDispatch.setTitle === 'function') yoastDispatch.setTitle(String(payload.seo_title || ''));
                    }
                }
            } catch (e3) {}
        }

        function insertInEditor(title, html, focusKeyphrase, metaDescription, categoryIds, tagIds, tagNames, featuredAttachId) {
            var content = String(html || '');
            var postTitle = String(title || '');
            var focus = String(focusKeyphrase || '');
            var metad = clampMetaDescription(metaDescription || '');
            if (!focus) focus = postTitle;
            if (!metad) metad = clampMetaDescription(String(content.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()));
            var cats = Array.isArray(categoryIds) ? categoryIds.map(function (v) { return parseInt(v, 10) || 0; }).filter(function (v) { return v > 0; }) : [];
            var tags = Array.isArray(tagIds) ? tagIds.map(function (v) { return parseInt(v, 10) || 0; }).filter(function (v) { return v > 0; }) : [];
            var tagLabels = Array.isArray(tagNames)
                ? tagNames.map(function (v) { return String(v || '').trim(); }).filter(function (v) { return !!v; })
                : [];
            var featuredId = parseInt(featuredAttachId || finalFeaturedAttachId || 0, 10) || 0;
            var yoastPayload = {
                focus_keyphrase: focus,
                meta_description: metad,
                seo_title: postTitle,
                og_title: postTitle,
                og_description: metad,
                tw_title: postTitle,
                tw_description: metad,
                primary_category: cats.length ? cats[0] : 0
            };

            function forceSetContentEverywhere() {
                var classicField = document.getElementById('content');
                if (classicField) {
                    classicField.value = content;
                    triggerInputSync(classicField);
                }
                var classicTitle = document.getElementById('title');
                if (classicTitle) {
                    classicTitle.value = postTitle;
                    triggerInputSync(classicTitle);
                }
                var gbTitle = document.querySelector('#post-title-0');
                if (gbTitle) {
                    gbTitle.value = postTitle;
                    triggerInputSync(gbTitle);
                }
                if (window.tinyMCE && window.tinyMCE.get('content')) {
                    try { window.tinyMCE.get('content').setContent(content); } catch (eTmce) {}
                }
            }

            if (window.wp && window.wp.data && window.wp.data.dispatch) {
                try {
                    var patch = { title: postTitle, content: content };
                    if (focus || metad) {
                        patch.meta = {};
                        if (focus) patch.meta._yoast_wpseo_focuskw = focus;
                        if (focus) patch.meta._yoast_wpseo_focuskw_text_input = focus;
                        if (metad) patch.meta._yoast_wpseo_metadesc = metad;
                        patch.meta._yoast_wpseo_title = postTitle;
                        if (metad) patch.meta['_yoast_wpseo_opengraph-description'] = metad;
                        patch.meta['_yoast_wpseo_opengraph-title'] = postTitle;
                        if (metad) patch.meta['_yoast_wpseo_twitter-description'] = metad;
                        patch.meta['_yoast_wpseo_twitter-title'] = postTitle;
                    }
                    if (cats.length) patch.categories = cats;
                    if (cats.length) {
                        patch.meta = patch.meta || {};
                        patch.meta._yoast_wpseo_primary_category = cats[0];
                    }
                    if (tags.length) patch.tags = tags;
                    if (featuredId > 0) patch.featured_media = featuredId;
                    window.wp.data.dispatch('core/editor').editPost(patch);
                    syncEditorTermsUi(cats, tags, tagLabels);
                    if (featuredId > 0) {
                        syncFeaturedMediaUi(featuredId);
                        setTimeout(function () { syncFeaturedMediaUi(featuredId); }, 500);
                        setTimeout(function () { syncFeaturedMediaUi(featuredId); }, 1800);
                    }
                    try {
                        if (window.wp.blocks && window.wp.blocks.parse && window.wp.data && window.wp.data.dispatch) {
                            window.wp.data.dispatch('core/block-editor').resetBlocks(window.wp.blocks.parse(content));
                        }
                    } catch (eReset) {}
                    forceSetContentEverywhere();
                    updateYoastUiFields(yoastPayload);
                    try { ensureYoastMetaPersisted(yoastPayload); } catch (e2) {}
                    try { ensureTermsPersisted(cats, tags, 0, tagLabels, featuredId); } catch (e3) {}
                    return true;
                } catch (e) {}
            }

            function applyClassicSideEffects() {
                updateYoastUiFields(yoastPayload);
                ensureClassicPostField('yoast_wpseo_focuskw', 'yoast_wpseo_focuskw', yoastPayload.focus_keyphrase || '');
                ensureClassicPostField('yoast_wpseo_focuskw_text_input', 'yoast_wpseo_focuskw_text_input', yoastPayload.focus_keyphrase || '');
                ensureClassicPostField('yoast_wpseo_metadesc', 'yoast_wpseo_metadesc', yoastPayload.meta_description || '');
                ensureClassicPostField('yoast_wpseo_title', 'yoast_wpseo_title', yoastPayload.seo_title || '');
                ensureClassicPostField('yoast_wpseo_opengraph-title', 'yoast_wpseo_opengraph-title', yoastPayload.og_title || '');
                ensureClassicPostField('yoast_wpseo_opengraph-description', 'yoast_wpseo_opengraph-description', yoastPayload.og_description || '');
                ensureClassicPostField('yoast_wpseo_twitter-title', 'yoast_wpseo_twitter-title', yoastPayload.tw_title || '');
                ensureClassicPostField('yoast_wpseo_twitter-description', 'yoast_wpseo_twitter-description', yoastPayload.tw_description || '');
                if (parseInt(yoastPayload.primary_category || 0, 10) > 0) {
                    ensureClassicPostField('yoast_wpseo_primary_category', 'yoast_wpseo_primary_category', String(yoastPayload.primary_category || ''));
                }
                if (cats.length) {
                    cats.forEach(function (id) {
                        var cb = document.querySelector('#categorychecklist input[type=\"checkbox\"][value=\"' + id + '\"], #categorychecklist-pop input[type=\"checkbox\"][value=\"' + id + '\"]');
                        if (cb) {
                            cb.checked = true;
                            triggerInputSync(cb);
                        }
                    });
                }
                if (featuredId > 0) {
                    ensureClassicPostField('_thumbnail_id', '_thumbnail_id', String(featuredId));
                    syncFeaturedMediaUi(featuredId);
                }
                if (tagLabels.length) {
                    syncEditorTermsUi(cats, tags, tagLabels);
                }
                ensureYoastMetaPersisted(yoastPayload);
                ensureTermsPersisted(cats, tags, 0, tagLabels, featuredId);
            }

            var titleField = document.getElementById('title');
            if (titleField) titleField.value = postTitle;

            if (window.tinyMCE && window.tinyMCE.get('content')) {
                try {
                    window.tinyMCE.get('content').setContent(content);
                    var hiddenContent = document.getElementById('content');
                    if (hiddenContent) {
                        hiddenContent.value = content;
                        triggerInputSync(hiddenContent);
                    }
                    applyClassicSideEffects();
                    return true;
                } catch (e) {}
            }

            var classic = document.getElementById('content');
            if (classic) {
                classic.value = content;
                triggerInputSync(classic);
                applyClassicSideEffects();
                return true;
            }
            return false;
        }

        function runComposerClassic(title, options) {
            var opts = options || {};
            var forceSkipImages = !!opts.skipImages;
            startPhaseTimer(forceSkipImages ? 'Generating text (classic mode)' : 'Generating text and images (classic mode)');
            var fd = new FormData();
            fd.append('action', 'cbia_preview_article');
            fd.append('_ajax_nonce', nonce);
            fd.append('title', title);
            fd.append('preview_mode', 'full');
            fd.append('composer_mode', '1');
            fd.append('current_post_id', String(resolveCurrentPostId() || 0));
            fd.append('post_language', languageSelect ? (languageSelect.value || (aiCfg.defaultLanguage || 'English')) : (aiCfg.defaultLanguage || 'English'));
            fd.append('images_limit', String(getTotalImagesForEngine()));
            fd.append('internal_image_style', internalStyleSelect ? (internalStyleSelect.value || 'banner') : 'banner');
            fd.append('skip_images', (forceSkipImages || shouldSkipImages()) ? '1' : '0');
            fd.append('post_length_variant', lengthSelect ? (lengthSelect.value || 'medium') : 'medium');
            fd.append('openai_temperature', String(getComposerTemperature()));
            fd.append('blog_prompt_mode', 'recommended');
            fd.append('blog_prompt_profile', getPromptProfileValue());
            fd.append('include_faq', isComposerFaqEnabled() ? '1' : '0');
            fd.append('include_practical_examples', isComposerExamplesEnabled() ? '1' : '0');
            fd.append('blog_prompt_custom_instructions', getLengthInstruction());

            return fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(function (r) { return r.text(); })
                .then(function (text) {
                    var data = null;
                    try { data = JSON.parse(text); } catch (e) { data = null; }
                    if (!data || !data.success || !data.data) {
                        throw new Error((data && data.data && data.data.message) ? data.data.message : 'Could not generate preview.');
                    }
                    var classicCount = parseInt(data.data.word_count || 0, 10) || 0;
                    if (classicCount <= 0) {
                        classicCount = countWordsFromHtml(data.data.raw_html || data.data.preview_html || '');
                    }
                    setWordCount(classicCount);
                    return data.data;
                })
                .finally(function () {
                    stopPhaseTimer();
                });
        }

        function testKeyFromModal() {
            refreshKeyModalRefs();
            if (keyProviderSelect && keyProviderSelect.value) {
                activeKeyProvider = String(keyProviderSelect.value || activeKeyProvider || 'openai');
            }
            if (!providerSupportsScope(activeKeyProvider, activeKeyScope)) {
                setKeyStatus('Provider is not compatible with this mode.', true);
                return;
            }
            if (!activeKeyProvider) return;
            var key = keyInput ? String(keyInput.value || '').trim() : '';
            var model = keyModelSelect ? String(keyModelSelect.value || '').trim() : '';
            if (!key) {
                setKeyStatus('Enter an API key to test.', true);
                return;
            }
            setKeyStatus('Testing connection...', false);
            if (keyTest) keyTest.disabled = true;
            var params = new URLSearchParams();
            params.append('action', 'cbia_ai_composer_test_api_key');
            params.append('_ajax_nonce', nonce);
            params.append('provider', activeKeyProvider);
            params.append('scope', activeKeyScope);
            params.append('model', model);
            params.append('api_key', key);
            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || !res.success) {
                        throw new Error((res && res.data && res.data.message) ? res.data.message : 'Could not validate key.');
                    }
                    setKeyStatus((res.data && res.data.message) ? res.data.message : 'Conexion correcta.', false);
                })
                .catch(function (err) {
                    setKeyStatus((err && err.message) ? err.message : 'Error en validacion.', true);
                })
                .finally(function () {
                    if (keyTest) keyTest.disabled = false;
                });
        }

        function runComposerStream(title, options) {
            var opts = options || {};
            var forceSkipImages = !!opts.skipImages;
            if (typeof EventSource === 'undefined') {
                return runComposerClassic(title, opts);
            }
            return new Promise(function (resolve, reject) {
                var params = new URLSearchParams();
                params.append('action', 'cbia_preview_article_stream');
                params.append('_ajax_nonce', nonce);
                params.append('title', title);
                params.append('preview_mode', 'full');
                params.append('composer_mode', '1');
                params.append('current_post_id', String(resolveCurrentPostId() || 0));
                params.append('post_language', languageSelect ? (languageSelect.value || (aiCfg.defaultLanguage || 'English')) : (aiCfg.defaultLanguage || 'English'));
                params.append('images_limit', String(getTotalImagesForEngine()));
                params.append('internal_image_style', internalStyleSelect ? (internalStyleSelect.value || 'banner') : 'banner');
                params.append('skip_images', (forceSkipImages || shouldSkipImages()) ? '1' : '0');
                params.append('post_length_variant', lengthSelect ? (lengthSelect.value || 'medium') : 'medium');
                params.append('openai_temperature', String(getComposerTemperature()));
                params.append('blog_prompt_mode', 'recommended');
                params.append('blog_prompt_profile', getPromptProfileValue());
                params.append('include_faq', isComposerFaqEnabled() ? '1' : '0');
                params.append('include_practical_examples', isComposerExamplesEnabled() ? '1' : '0');
                params.append('blog_prompt_custom_instructions', getLengthInstruction());

                var done = false;
                var lastEventAt = Date.now();
                var totalInternal = getInternalImagesCount();
                var source = new EventSource(ajaxUrl + '?' + params.toString());
                source.onopen = function () {
                    startPhaseTimer('Generating text');
                };
                startPhaseTimer('Generating text');
                source.addEventListener('text_progress', function (evt) {
                    if (streamTextLocked) return;
                    lastEventAt = Date.now();
                    var data = {};
                    try { data = JSON.parse(evt.data || '{}'); } catch (e) {}
                    var htmlProg = enforceInternalImageStyle(normalizePreviewHtml(data.html || ''));
                    if (String(htmlProg || '').trim() !== '') {
                        streamHasTextProgress = true;
                        streamContentSeeded = true;
                        enqueueComposerProgressiveHtml(htmlProg, data.word_count || 0);
                    }
                    setWordCount(data.word_count || 0);
                });
                source.addEventListener('cbia_content', function (evt) {
                    lastEventAt = Date.now();
                var data = {};
                try { data = JSON.parse(evt.data || '{}'); } catch (e) {}
                var htmlNow = enforceInternalImageStyle(normalizePreviewHtml(data.html || ''));
                if (!streamTextLocked) {
                    if (String(htmlNow || '').trim() !== '') {
                        streamContentSeeded = true;
                        enqueueComposerProgressiveHtml(htmlNow, data.word_count || 0);
                    }
                } else {
                    stopTypingAnimation();
                    if (receivedInternalImages.length) {
                            htmlNow = injectInternalImagesIntoHtml(htmlNow, receivedInternalImages);
                        }
                        preview.innerHTML = htmlNow;
                    }
                    if (!(forceSkipImages || shouldSkipImages())) {
                        startPhaseTimer('Preparing image generation');
                    }
                    if (receivedInternalImages.length) {
                        fillPreviewSlotsFromImages(receivedInternalImages);
                    }
                });
                source.addEventListener('word_count', function (evt) {
                    lastEventAt = Date.now();
                    var data = {};
                    try { data = JSON.parse(evt.data || '{}'); } catch (e) {}
                    setWordCount(data.count || 0);
                });
                source.addEventListener('cbia_image', function (evt) {
                    if (forceSkipImages) return;
                    lastEventAt = Date.now();
                    var data = {};
                    try { data = JSON.parse(evt.data || '{}'); } catch (e) {}
                    if (data && data.status === 'processing') {
                        startPhaseTimer('Generating image ' + (data.idx || '?') + '/' + totalInternal);
                    } else if (data && data.status === 'done') {
                        if (!streamTextLocked) {
                            streamTextLocked = true;
                            if (latestStreamHtml) {
                                stopTypingAnimation();
                                if (progressiveQueue.length > 0 || progressiveTimer) {
                                    freezeTextOnQueueDrain = true;
                                } else if (preview) {
                                    preview.innerHTML = latestStreamHtml;
                                }
                            }
                        }
                        setStatus('Image ' + (data.idx || '?') + '/' + totalInternal + ' ready.', false);
                        if (data.url) {
                            receivedInternalImages = getOrderedInternalImages(receivedInternalImages.concat([{
                                url: String(data.url || ''),
                                alt: String(data.desc || 'Generated image'),
                                idx: parseInt(data.idx || 0, 10) || 0
                            }]));
                            fillPreviewSlotsFromImages(receivedInternalImages);
                        }
                    }
                });
                source.addEventListener('featured_image_status', function (evt) {
                    if (forceSkipImages) return;
                    lastEventAt = Date.now();
                    var data = {};
                    try { data = JSON.parse(evt.data || '{}'); } catch (e) {}
                    if (data && data.status === 'processing') {
                        startPhaseTimer('Generating featured image');
                        setFeaturedPreview('', 'Generating featured image...');
                    }
                    if (data && data.status === 'done') {
                        setStatus('Featured image ready.', false);
                        finalFeaturedAttachId = parseInt(data.attach_id || finalFeaturedAttachId || 0, 10) || finalFeaturedAttachId;
                        if (finalFeaturedAttachId > 0) {
                            finalFeaturedImage = finalFeaturedImage || {};
                            finalFeaturedImage.attach_id = finalFeaturedAttachId;
                        }
                        setFeaturedPreview(String(data.url || ''), 'Featured image ready.');
                    }
                    if (data && data.status === 'error') {
                        setFeaturedPreview('', String(data.message || 'Could not generate featured image.'));
                    }
                });
                source.addEventListener('cbia_status', function (evt) {
                    lastEventAt = Date.now();
                    var data = {};
                    try { data = JSON.parse(evt.data || '{}'); } catch (e) {}
                    if (data && data.message) setStatus(data.message, false);
                });
                source.addEventListener('preview_done', function (evt) {
                    if (done) return;
                    done = true;
                    source.close();
                    var data = {};
                    try { data = JSON.parse(evt.data || '{}'); } catch (e) {}
                    if (!data || !data.result) {
                        reject(new Error('Respuesta incompleta de streaming.'));
                        return;
                    }
                    stopPhaseTimer();
                    resolve(data.result);
                });
                source.addEventListener('cbia_done', function (evt) {
                    if (done) return;
                    done = true;
                    source.close();
                    var data = {};
                    try { data = JSON.parse(evt.data || '{}'); } catch (e) {}
                    if (!data || !data.result) {
                        reject(new Error('Respuesta incompleta de streaming.'));
                        return;
                    }
                    stopPhaseTimer();
                    resolve(data.result);
                });
                source.addEventListener('preview_error', function (evt) {
                    if (done) return;
                    done = true;
                    source.close();
                    var data = {};
                    try { data = JSON.parse(evt.data || '{}'); } catch (e) {}
                    var previewErrMessage = (data && data.message) ? String(data.message) : 'Could not generate preview.';
                    syncApiStateFromErrorMessage('text', previewErrMessage);
                    applyApiConfigurationState(true);
                    stopPhaseTimer();
                    reject(new Error(previewErrMessage));
                });
                source.onerror = function () {
                    if (done) return;
                    if ((Date.now() - lastEventAt) < 15000) {
                        return;
                    }
                    done = true;
                    source.close();
                    stopPhaseTimer();
                    reject(new Error('Streaming failed.'));
                };
            }).catch(function (streamErr) {
                stopPhaseTimer();
                throw new Error((streamErr && streamErr.message) ? streamErr.message : 'Streaming failed.');
            });
        }

        function onGenerateClick(options) {
                var opts = options || {};
                var textOnlyMode = !!opts.textOnly;
                if (isGenerating) {
                    setStatus('Generation is in progress. Wait until it finishes.', true);
                    return;
                }
                applyApiConfigurationState(true);
                if (!ajaxUrl || !nonce) {
                    setStatus('Plugin AJAX configuration is missing. Reload the page or check settings.', true);
                    return;
                }
                var title = titleInput ? (titleInput.value || '').trim() : '';
                if (!title) {
                    var directComposerTitle = document.getElementById('cbia-ai-title');
                    title = directComposerTitle ? String(directComposerTitle.value || '').trim() : '';
                }
                if (!title) {
                    setStatus('Enter a title first.', true);
                    return;
                }
                ensureLanguageSelected();
                isGenerating = true;
                setStatus('Validating configured API...', false);
                preflightConfiguredProvider('text')
                    .then(function () {
                        if (textOnlyMode || shouldSkipImages()) return true;
                        return preflightConfiguredProvider('image');
                    })
                    .then(function () {
                generateBtn.disabled = true;
                if (improveTextBtn) improveTextBtn.disabled = true;
                if (insertBtn) insertBtn.disabled = true;
                setStatus(textOnlyMode ? 'Improving text (keeping images)...' : 'Generating preview...', false);
                setWordCount(0);
                streamTextLocked = false;
                streamHasTextProgress = false;
                streamContentSeeded = false;
                stopTypingAnimation();
                clearComposerProgressiveQueue();
                var preservedInternals = textOnlyMode ? normalizeInternalImageSlots(receivedInternalImages, getInternalImagesCount()) : [];
                var preservedFeaturedImage = (textOnlyMode && finalFeaturedImage && String(finalFeaturedImage.url || '').trim())
                    ? Object.assign({}, finalFeaturedImage)
                    : null;
                var preservedFeaturedAttachId = textOnlyMode ? (parseInt(finalFeaturedAttachId || 0, 10) || 0) : 0;
                if (!textOnlyMode) {
                    receivedInternalImages = [];
                    finalFeaturedImage = null;
                    finalFeaturedAttachId = 0;
                }
                finalTagNames = [];
                finalTitle = String(title || '').trim();
                if (!textOnlyMode) {
                    resetFeaturedPreview();
                    renderImageCards();
                }
                if (preview) preview.innerHTML = '<em>Generating...</em>';

                var longRunPing = setTimeout(function () {
                    setStatus(textOnlyMode ? 'Still improving text...' : 'Still generating (may take 1-2 minutes with images)...', false);
                }, 9000);

                runComposerStream(title, { skipImages: textOnlyMode })
                    .then(function (data) {
                        clearProviderRuntimeState('text');
                        if (!textOnlyMode) clearProviderRuntimeState('image');
                        applyApiConfigurationState(true);
                        var finalSourceHtml = String(data.raw_html || data.final_html || data.html || data.preview_html || '');
                        if (textOnlyMode) {
                            receivedInternalImages = normalizeInternalImageSlots(preservedInternals, getInternalImagesCount());
                            finalFeaturedImage = preservedFeaturedImage;
                            finalFeaturedAttachId = preservedFeaturedAttachId;
                            finalHtml = enforceInternalImageStyle(injectInternalImagesIntoHtml(finalSourceHtml, receivedInternalImages));
                        } else {
                            finalHtml = enforceInternalImageStyle(injectInternalImagesIntoHtml(finalSourceHtml, data.images || []));
                        }
                        finalTitle = String(data.title || title || finalTitle || '').trim();
                        if (titleInput && finalTitle) {
                            titleInput.value = finalTitle;
                        }
                        finalFocusKeyphrase = String(data.focus_keyphrase || '');
                        finalMetaDescription = clampMetaDescription(data.meta_description || '');
                        previewToken = String(data.preview_token || '');
                        finalCategoryIds = Array.isArray(data.category_ids) ? data.category_ids.map(function (v) { return parseInt(v, 10) || 0; }).filter(function (v) { return v > 0; }) : [];
                        finalTagIds = Array.isArray(data.tag_ids) ? data.tag_ids.map(function (v) { return parseInt(v, 10) || 0; }).filter(function (v) { return v > 0; }) : [];
                        finalTagNames = Array.isArray(data.tags) ? data.tags.map(function (v) { return String(v || '').trim(); }).filter(function (v) { return !!v; }) : [];
                        if (!textOnlyMode) {
                            finalFeaturedAttachId = parseInt(data.featured_attach_id || 0, 10) || 0;
                            if (!finalFeaturedAttachId && Array.isArray(data.images)) {
                                var featuredRow = data.images.find(function (img) {
                                    return img && String(img.section || '') === 'featured' && parseInt(img.attach_id || 0, 10) > 0;
                                });
                                if (featuredRow) {
                                    finalFeaturedAttachId = parseInt(featuredRow.attach_id || 0, 10) || 0;
                                }
                            }
                        }
                        if (finalTitle) syncEditorTitle(finalTitle);
                        finalHtml = textOnlyMode
                            ? injectInternalImagesIntoHtml(finalHtml, receivedInternalImages)
                            : injectInternalImagesIntoHtml(finalHtml, data.images || []);
                        var resolvedWordCount = parseInt(data.word_count || 0, 10) || 0;
                        if (resolvedWordCount <= 0) {
                            resolvedWordCount = countWordsFromHtml(finalHtml);
                        }
                        setWordCount(resolvedWordCount);
                        if (preview) {
                            if (textOnlyMode) {
                                // In text-only mode the stream has no image events, so force
                                // a final repaint with preserved internal images.
                                stopTypingAnimation();
                                clearComposerProgressiveQueue();
                                preview.innerHTML = finalHtml;
                            } else {
                                if (!streamContentSeeded) {
                                    enqueueComposerProgressiveHtml(finalHtml, data.word_count || 0);
                                } else if (!canUsePreviewHtml(preview.innerHTML)) {
                                    enqueueComposerProgressiveHtml(finalHtml, data.word_count || 0);
                                }
                            }
                            fillPreviewSlotsFromImages(textOnlyMode ? receivedInternalImages : (data.images || []));
                        }
                        if (!textOnlyMode) {
                            receivedInternalImages = normalizeInternalImageSlots((Array.isArray(data.images) ? data.images : []).filter(function (img) {
                                return img && img.url && String(img.section || '') !== 'featured';
                            }).map(function (img, arrIdx) {
                                var idxVal = parseInt(img.idx || 0, 10) || (arrIdx + 1);
                                return {
                                    idx: idxVal,
                                    url: String(img.url || ''),
                                    attach_id: parseInt(img.attach_id || 0, 10) || 0,
                                    desc: String(img.desc || img.alt || ('Internal image ' + idxVal)),
                                    alt: String(img.alt || img.desc || ('Internal image ' + idxVal)),
                                    section: String(img.section || 'body')
                                };
                            }), getInternalImagesCount());
                            if (Array.isArray(data.images)) {
                                var featuredObj = data.images.find(function (img) {
                                    return img && (img.section === 'featured' || String(img.status || '') === 'featured');
                                });
                                if (featuredObj && featuredObj.url) {
                                    finalFeaturedImage = {
                                        url: String(featuredObj.url || ''),
                                        attach_id: parseInt(featuredObj.attach_id || finalFeaturedAttachId || 0, 10) || 0,
                                        desc: String(featuredObj.desc || finalTitle || 'Featured image'),
                                        section: 'featured'
                                    };
                                    setFeaturedPreview(String(featuredObj.url), 'Featured image ready.');
                                }
                            }
                        } else {
                            if (finalFeaturedImage && finalFeaturedImage.url) {
                                setFeaturedPreview(String(finalFeaturedImage.url || ''), 'Featured image preserved.');
                            } else {
                                resetFeaturedPreview();
                            }
                        }
                        renderImageCards();
                        persistComposerSnapshot();
                        if (insertBtn) insertBtn.disabled = !canUsePreviewHtml(finalHtml);
                        setStatus(textOnlyMode ? 'Text improved. You can insert it into the editor.' : 'Preview generated. You can insert it into the editor.', false);
                    })
                    .catch(function (err) {
                        syncApiStateFromErrorMessage(textOnlyMode ? 'text' : 'text', err && err.message ? err.message : '');
                        applyApiConfigurationState(true);
                        setStatus((err && err.message) ? err.message : 'Could not generate preview.', true);
                        if (preview) preview.innerHTML = '<em>Could not generate preview.</em>';
                    })
                    .finally(function () {
                        clearTimeout(longRunPing);
                        stopPhaseTimer();
                        isGenerating = false;
                        generateBtn.disabled = false;
                        if (improveTextBtn) improveTextBtn.disabled = false;
                        if (insertBtn) insertBtn.disabled = !canUsePreviewHtml(finalHtml);
                    });
                    })
                    .catch(function (err) {
                        isGenerating = false;
                        generateBtn.disabled = false;
                        if (improveTextBtn) improveTextBtn.disabled = false;
                        if (insertBtn) insertBtn.disabled = !canUsePreviewHtml(finalHtml);
                        var validationMessage = (err && err.message) ? err.message : 'Could not validate configured API.';
                        setStatus(validationMessage, true);
                        openKeyModalForConfigurationError(validationMessage);
                    });
        }

        function applyFullPostAtomic(payload) {
                var params = new URLSearchParams();
                params.append('action', 'cbia_ai_composer_apply_full_post');
                params.append('_ajax_nonce', nonce);
                var forceNewDraft = !!(payload && payload.force_new_draft);
                params.append('post_id', String(forceNewDraft ? 0 : (parseInt(payload && payload.post_id ? payload.post_id : 0, 10) || resolveCurrentPostId() || 0)));
                params.append('title', String(payload.title || ''));
                params.append('content_html', String(payload.content_html || ''));
                params.append('focus_keyphrase', String(payload.focus_keyphrase || ''));
                params.append('meta_description', String(payload.meta_description || ''));
                params.append('include_faq', isComposerFaqEnabled() ? '1' : '0');
                params.append('featured_attach_id', String(parseInt(payload.featured_attach_id || (finalFeaturedImage && finalFeaturedImage.attach_id) || finalFeaturedAttachId || 0, 10) || 0));
                params.append('internal_image_style', String(payload.internal_image_style || ''));
                params.append('post_language', languageSelect ? (languageSelect.value || (aiCfg.defaultLanguage || 'English')) : (aiCfg.defaultLanguage || 'English'));
                (Array.isArray(payload.category_ids) ? payload.category_ids : []).forEach(function (id) {
                    var v = parseInt(id, 10) || 0;
                    if (v > 0) params.append('category_ids[]', String(v));
                });
                (Array.isArray(payload.tag_ids) ? payload.tag_ids : []).forEach(function (id) {
                    var v = parseInt(id, 10) || 0;
                    if (v > 0) params.append('tag_ids[]', String(v));
                });
                (Array.isArray(payload.tag_names) ? payload.tag_names : []).forEach(function (name) {
                    var v = String(name || '').trim();
                    if (v) params.append('tag_names[]', v);
                });
                return fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: params.toString()
                }).then(function (r) { return r.json(); });
        }

        function ensureEditorPostIdForApply() {
                var pid = parseInt(resolveCurrentPostId() || 0, 10) || 0;
                if (pid > 0) {
                    return Promise.resolve(pid);
                }
                var canSave =
                    !!(window.wp && window.wp.data && window.wp.data.dispatch && window.wp.data.dispatch('core/editor') &&
                    typeof window.wp.data.dispatch('core/editor').savePost === 'function');
                if (!canSave) {
                    return Promise.resolve(0);
                }
                try {
                    window.wp.data.dispatch('core/editor').savePost();
                } catch (_eSaveStart) {}
                return new Promise(function (resolve) {
                    var maxTries = 40;
                    var tries = 0;
                    var timer = setInterval(function () {
                        tries++;
                        var nextId = parseInt(resolveCurrentPostId() || 0, 10) || 0;
                        if (nextId > 0 || tries >= maxTries) {
                            clearInterval(timer);
                            resolve(nextId > 0 ? nextId : 0);
                        }
                    }, 300);
                });
        }

        function onInsertClick() {
                setStatus('Inserting into the editor...', false);
                var htmlCandidate = finalHtml;
                if (!canUsePreviewHtml(htmlCandidate)) {
                    // Fallback to current preview HTML if finalHtml got out of sync.
                    htmlCandidate = preview ? String(preview.innerHTML || '') : '';
                    if (!canUsePreviewHtml(htmlCandidate)) {
                        setStatus('Generate a full preview first (final HTML).', true);
                        return;
                    }
                }
                if (insertBtn) insertBtn.disabled = true;
                var titleForApply = '';
                var directComposerTitle = document.getElementById('cbia-ai-title');
                if (directComposerTitle) {
                    titleForApply = String(directComposerTitle.value || '').trim();
                }
                if (!titleForApply) titleForApply = String((finalTitle || '')).trim();
                if (!titleForApply) titleForApply = String((titleInput ? titleInput.value : '') || '').trim();
                if (!titleForApply) titleForApply = getCurrentEditorTitle();
                if (titleInput) titleInput.value = titleForApply;
                if (!titleForApply) {
                    setStatus('Enter a title before inserting.', true);
                    if (insertBtn) insertBtn.disabled = false;
                    return;
                }
                syncEditorTitle(titleForApply);
                var modeForApply = internalStyleSelect ? String(internalStyleSelect.value || 'banner') : 'banner';
                var internalsForApply = normalizeInternalImageSlots(receivedInternalImages, getInternalImagesCount());
                receivedInternalImages = internalsForApply;
                htmlCandidate = enforceInternalImageStyle(injectInternalImagesIntoHtml(stripLeadingTitleFromHtml(titleForApply, htmlCandidate), internalsForApply), modeForApply);
                var metadForApply = clampMetaDescription(finalMetaDescription || '');
                if (!metadForApply) {
                    metadForApply = clampMetaDescription(String(htmlCandidate || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' '));
                }
                var focusForApply = String(finalFocusKeyphrase || '').trim();
                if (!focusForApply) focusForApply = String(titleForApply || '').trim();
                var applyEffectivePostId = 0;
                ensureEditorPostIdForApply().then(function (effectivePostId) {
                    applyEffectivePostId = parseInt(effectivePostId || 0, 10) || 0;
                    return applyFullPostAtomic({
                        post_id: effectivePostId,
                        title: titleForApply,
                        content_html: htmlCandidate,
                        focus_keyphrase: focusForApply,
                        meta_description: metadForApply,
                        category_ids: finalCategoryIds,
                        tag_ids: finalTagIds,
                        tag_names: finalTagNames,
                        featured_attach_id: finalFeaturedAttachId,
                        internal_image_style: modeForApply
                    }).then(function (res) {
                        var message = String((res && res.data && res.data.message) ? res.data.message : '').toLowerCase();
                        if (res && res.success) return res;
                        if (applyEffectivePostId > 0 && isCurrentEditorNewPost() && message.indexOf('unauthorized') !== -1) {
                            setStatus('WordPress rejected the provisional draft ID. Creating a fresh draft...', false);
                            return applyFullPostAtomic({
                                post_id: 0,
                                force_new_draft: true,
                                title: titleForApply,
                                content_html: htmlCandidate,
                                focus_keyphrase: focusForApply,
                                meta_description: metadForApply,
                                category_ids: finalCategoryIds,
                                tag_ids: finalTagIds,
                                tag_names: finalTagNames,
                                featured_attach_id: finalFeaturedAttachId,
                                internal_image_style: modeForApply
                            });
                        }
                        return res;
                    });
                }).then(function (res) {
                    if (!res || !res.success || !res.data) {
                        throw new Error((res && res.data && res.data.message) ? res.data.message : 'Could not apply content to the post.');
                    }
                    var appliedTitle = String((res.data.title || titleForApply || '')).trim();
                    if (appliedTitle) {
                        titleForApply = appliedTitle;
                        finalTitle = appliedTitle;
                    }
                    var appliedPostId = parseInt((res.data && res.data.post_id) ? res.data.post_id : 0, 10) || 0;
                    if (appliedPostId > 0) {
                        setEffectivePostIdForComposer(appliedPostId);
                    }
                    var appliedCats = Array.isArray(res.data.category_ids)
                        ? res.data.category_ids.map(function (v) { return parseInt(v, 10) || 0; }).filter(function (v) { return v > 0; })
                        : finalCategoryIds;
                    var appliedTags = Array.isArray(res.data.tag_ids)
                        ? res.data.tag_ids.map(function (v) { return parseInt(v, 10) || 0; }).filter(function (v) { return v > 0; })
                        : finalTagIds;
                    var appliedTagNames = Array.isArray(res.data.tag_names)
                        ? res.data.tag_names.map(function (v) { return String(v || '').trim(); }).filter(function (v) { return !!v; })
                        : finalTagNames;
                    if (res.data && res.data.featured_attach_id) {
                        finalFeaturedAttachId = parseInt(res.data.featured_attach_id || 0, 10) || finalFeaturedAttachId;
                        syncFeaturedMediaUi(finalFeaturedAttachId);
                        if ((!finalFeaturedImage || !finalFeaturedImage.url) && finalFeaturedAttachId > 0) {
                            resolveAttachmentUrlById(finalFeaturedAttachId).then(function (resolvedUrl) {
                                if (!resolvedUrl) return;
                                finalFeaturedImage = finalFeaturedImage || {};
                                finalFeaturedImage.url = String(resolvedUrl || '');
                                finalFeaturedImage.attach_id = finalFeaturedAttachId;
                                setFeaturedPreview(String(resolvedUrl || ''), 'Featured image updated.');
                            });
                        }
                    }
                    if (res.data && typeof res.data.content_html === 'string' && canUsePreviewHtml(res.data.content_html)) {
                        htmlCandidate = res.data.content_html;
                        finalHtml = res.data.content_html;
                    }
                    finalCategoryIds = appliedCats.slice();
                    finalTagIds = appliedTags.slice();
                    finalTagNames = appliedTagNames.slice();
                    var appliedFocus = String((res.data && res.data.focus_keyphrase) ? res.data.focus_keyphrase : focusForApply).trim();
                    var appliedMeta = clampMetaDescription((res.data && res.data.meta_description) ? res.data.meta_description : metadForApply);
                    finalFocusKeyphrase = appliedFocus || focusForApply;
                    finalMetaDescription = appliedMeta || metadForApply;
                    // Keep editor UI in sync after server-side atomic write.
                    syncEditorTitle(titleForApply);
                    // Classic editor can re-render title late; enforce once more from backend-applied title.
                    setTimeout(function () { syncEditorTitle(titleForApply); }, 80);
                    setTimeout(function () { syncEditorTitle(titleForApply); }, 250);
                    setTimeout(function () { syncEditorTitle(titleForApply); }, 1000);
                    setTimeout(function () { syncEditorTitle(titleForApply); }, 2200);
                    // Secondary persistence pass (Yoast + terms) for editor/plugin stacks that lag.
                    try {
                        syncEditorTermsUi(appliedCats, appliedTags, appliedTagNames);
                        setTimeout(function () { syncEditorTermsUi(appliedCats, appliedTags, appliedTagNames); }, 250);
                    } catch (eTermsUi) {}
                    try {
                        ensureTermsPersisted(appliedCats, appliedTags, appliedPostId, appliedTagNames, finalFeaturedAttachId);
                    } catch (eTerms) {}
                    try {
                        ensureYoastMetaPersisted({
                            focus_keyphrase: finalFocusKeyphrase,
                            meta_description: finalMetaDescription,
                            seo_title: titleForApply,
                            og_title: titleForApply,
                            og_description: finalMetaDescription,
                            tw_title: titleForApply,
                            tw_description: finalMetaDescription,
                            primary_category: appliedCats.length ? appliedCats[0] : 0
                        }, appliedPostId);
                    } catch (eYoast) {}
                    persistComposerSnapshot();
                    setStatus('Contenido aplicado en servidor. Guardando borrador...', false);
                    try { insertInEditor(titleForApply, htmlCandidate, finalFocusKeyphrase, finalMetaDescription, appliedCats, appliedTags, appliedTagNames, finalFeaturedAttachId); } catch (eInsertUi) {}
                    return persistTermsForPost(appliedPostId, appliedCats, appliedTags, 0, appliedTagNames, finalFeaturedAttachId).catch(function () { return false; }).then(function () {
                        syncEditorTermsUi(appliedCats, appliedTags, appliedTagNames);
                        if (finalFeaturedAttachId > 0) syncFeaturedMediaUi(finalFeaturedAttachId);
                        return saveEditorDraftAfterComposer().catch(function(){ return false; });
                    }).then(function(){
                        return persistTermsForPost(appliedPostId, appliedCats, appliedTags, 0, appliedTagNames, finalFeaturedAttachId).catch(function () { return false; });
                    }).then(function(){
                        return refreshEditorPostStateFromServer(appliedPostId, appliedCats, appliedTags, appliedTagNames, finalFeaturedAttachId).catch(function () { return false; });
                    }).then(function(){
                        return saveEditorDraftAfterComposer().catch(function(){ return false; });
                    }).then(function(){
                        syncEditorTermsUi(appliedCats, appliedTags, appliedTagNames);
                        if (finalFeaturedAttachId > 0) {
                            syncFeaturedMediaUi(finalFeaturedAttachId);
                            setTimeout(function () { syncFeaturedMediaUi(finalFeaturedAttachId); }, 600);
                            setTimeout(function () { syncFeaturedMediaUi(finalFeaturedAttachId); }, 1800);
                        }
                        setStatus('Content inserted and saved. Reloading editor to refresh WordPress panels...', false);
                        if (appliedPostId > 0 && res.data && res.data.edit_url) {
                            try {
                                sessionStorage.setItem('cbia_ai_title_after_redirect', JSON.stringify({ title: titleForApply, post_id: appliedPostId }));
                            } catch (_eStoreRedirect) {}
                            try {
                                suppressComposerUnloadPrompt = true;
                                window.onbeforeunload = null;
                            } catch (_eUnloadPrompt) {}
                            setTimeout(function () {
                                window.location.replace(String(res.data.edit_url));
                            }, 300);
                            return;
                        }
                        setStatus('Content inserted and saved in the editor.', false);
                        closeComposerModal();
                        setTimeout(function () { try { closeComposerModal(); } catch (_e1) {} }, 80);
                        setTimeout(function () { try { closeComposerModalFallback(); } catch (_e2) {} }, 260);
                    });
                }).catch(function (err) {
                    setStatus((err && err.message) ? err.message : 'Could not insert automatically. Copy and paste manually.', true);
                }).finally(function () {
                    if (insertBtn) insertBtn.disabled = !canUsePreviewHtml(finalHtml);
                    if (improveTextBtn) improveTextBtn.disabled = false;
                });
        }

        function onImproveTextClick() {
            if (isGenerating) {
                setStatus('Generation is in progress. Wait until it finishes.', true);
                return;
            }
            var title = titleInput ? String(titleInput.value || '').trim() : '';
            if (!title) {
                setStatus('Enter a title before improving text.', true);
                return;
            }
            var ok = window.confirm('This will regenerate only the article text and keep current images. On insert, SEO, categories, and tags will be recalculated. Continue?');
            if (!ok) return;
            setStatus('Improving text with AI (without regenerating images)...', false);
            onGenerateClick({ textOnly: true });
        }

        // Global fallback hooks for stubborn editor integrations that swallow normal events.
        window.CBIA_AI_COMPOSER_RUN = function () {
            try {
                onGenerateClick();
            } catch (e) {
                if (window.console && console.error) console.error('CBIA run error:', e);
                if (window.CBIA_AI_COMPOSER_FALLBACK_RUN) {
                    window.CBIA_AI_COMPOSER_FALLBACK_RUN();
                }
            }
        };
        window.CBIA_AI_COMPOSER_INSERT = function () {
            try {
                onInsertClick();
            } catch (e) {
                if (window.console && console.error) console.error('CBIA insert error:', e);
                if (window.CBIA_AI_COMPOSER_FALLBACK_INSERT) {
                    window.CBIA_AI_COMPOSER_FALLBACK_INSERT();
                }
            }
        };
        window.CBIA_AI_COMPOSER_IMPROVE = function () {
            try {
                onImproveTextClick();
            } catch (e) {
                if (window.console && console.error) console.error('CBIA improve error:', e);
            }
        };
        window.CBIA_AI_COMPOSER_COMPLETE_MISSING = function () {
            try {
                onCompleteMissingClick();
            } catch (e) {
                if (window.console && console.error) console.error('CBIA complete-missing error:', e);
            }
        };

        if (generateBtn) {
            generateBtn.addEventListener('click', function (e) {
                e.preventDefault();
                onGenerateClick();
            });
            generateBtn.onclick = function (e) {
                if (e && e.preventDefault) e.preventDefault();
                onGenerateClick();
                return false;
            };
        }

        if (insertBtn) {
            insertBtn.addEventListener('click', function (e) {
                e.preventDefault();
                onInsertClick();
            });
            insertBtn.onclick = function (e) {
                if (e && e.preventDefault) e.preventDefault();
                onInsertClick();
                return false;
            };
        }
        if (improveTextBtn) {
            improveTextBtn.addEventListener('click', function (e) {
                e.preventDefault();
                onImproveTextClick();
            });
            improveTextBtn.onclick = function (e) {
                if (e && e.preventDefault) e.preventDefault();
                onImproveTextClick();
                return false;
            };
        }
        if (completeMissingBtn) {
            completeMissingBtn.addEventListener('click', function (e) {
                e.preventDefault();
                onCompleteMissingClick();
            });
            completeMissingBtn.onclick = function (e) {
                if (e && e.preventDefault) e.preventDefault();
                onCompleteMissingClick();
                return false;
            };
        }

        if (cfgTextBtn) {
            cfgTextBtn.addEventListener('click', function (e) {
                e.preventDefault();
                openKeyModal('text');
            });
        }
        if (cfgImageBtn) {
            cfgImageBtn.addEventListener('click', function (e) {
                e.preventDefault();
                openKeyModal('image');
            });
        }
        if (keyProviderSelect) {
            keyProviderSelect.addEventListener('change', function () {
                activeKeyProvider = String(keyProviderSelect.value || 'openai');
                updateKeyHelpText();
                updateKeyModelOptions();
                if (!(aiCfg.providerKeyState && aiCfg.providerKeyState[activeKeyProvider])) {
                    setKeyStatus('', false);
                }
            });
        }
        if (keyTest) keyTest.addEventListener('click', function (e) { e.preventDefault(); testKeyFromModal(); });
        if (keySave) keySave.addEventListener('click', function (e) { e.preventDefault(); saveKeyFromModal(); });
        if (keyCancel) keyCancel.addEventListener('click', function (e) { e.preventDefault(); closeKeyModal(); });
        if (keyClose) keyClose.addEventListener('click', function (e) { e.preventDefault(); closeKeyModal(); });
        if (keyModal) {
            keyModal.addEventListener('click', function (e) {
                if (e.target === keyModal) closeKeyModal();
            });
        }

        if (imageCardsRoot) {
            imageCardsRoot.addEventListener('click', function (e) {
                var card = e.target && e.target.closest ? e.target.closest('.cbia-ai-image-card') : null;
                if (!card) return;
                e.preventDefault();
                openImageModal(card.getAttribute('data-slot-type') || 'internal', card.getAttribute('data-slot-idx') || '0');
            });
        }
        if (imageModalRegenerate) {
            imageModalRegenerate.addEventListener('click', function (e) {
                e.preventDefault();
                regenerateImageSlot();
            });
        }
        if (imageModalLibrary) {
            imageModalLibrary.addEventListener('click', function (e) {
                e.preventDefault();
                pickImageFromLibraryForCurrentSlot();
            });
        }
        if (imageModalCancel) {
            imageModalCancel.addEventListener('click', function (e) {
                e.preventDefault();
                closeImageModal();
            });
        }
        if (imageModalClose) {
            imageModalClose.addEventListener('click', function (e) {
                e.preventDefault();
                closeImageModal();
            });
        }
        if (imageModal) {
            imageModal.addEventListener('click', function (e) {
                if (e.target === imageModal) {
                    closeImageModal();
                }
            });
        }

        // Fallback: in some Gutenberg/Elementor admin setups direct listeners can be lost.
        root.addEventListener('click', function (e) {
            var target = e.target && e.target.closest ? e.target.closest('button') : null;
            if (!target) return;
            if (target.id === 'cbia-ai-generate') {
                e.preventDefault();
                onGenerateClick();
            } else if (target.id === 'cbia-ai-improve-text') {
                e.preventDefault();
                onImproveTextClick();
            } else if (target.id === 'cbia-ai-insert') {
                e.preventDefault();
                onInsertClick();
            } else if (target.id === 'cbia-ai-complete-missing') {
                e.preventDefault();
                onCompleteMissingClick();
            } else if (target.id === 'cbia-ai-config-text') {
                e.preventDefault();
                openKeyModal('text');
            } else if (target.id === 'cbia-ai-config-image') {
                e.preventDefault();
                openKeyModal('image');
            }
        });

        // Extra fallback for editors/plugins that stop bubbling on button clicks.
        document.addEventListener('click', function (e) {
            var target = e.target && e.target.closest ? e.target.closest('#cbia-ai-generate, #cbia-ai-improve-text, #cbia-ai-insert, #cbia-ai-complete-missing, #cbia-ai-config-text, #cbia-ai-config-image, #cbia-ai-key-save, #cbia-ai-key-test, #cbia-ai-key-cancel, #cbia-ai-key-close') : null;
            if (!target) return;
            var isKeyModalButton = target.id === 'cbia-ai-key-save' || target.id === 'cbia-ai-key-test' || target.id === 'cbia-ai-key-cancel' || target.id === 'cbia-ai-key-close';
            if (!isKeyModalButton && !root.contains(target)) return;
            e.preventDefault();
            if (e.stopPropagation) e.stopPropagation();
            if (target.id === 'cbia-ai-generate') {
                onGenerateClick();
            } else if (target.id === 'cbia-ai-improve-text') {
                onImproveTextClick();
            } else if (target.id === 'cbia-ai-insert') {
                onInsertClick();
            } else if (target.id === 'cbia-ai-complete-missing') {
                onCompleteMissingClick();
            } else if (target.id === 'cbia-ai-config-text') {
                openKeyModal('text');
            } else if (target.id === 'cbia-ai-config-image') {
                openKeyModal('image');
            } else if (target.id === 'cbia-ai-key-save') {
                saveKeyFromModal();
            } else if (target.id === 'cbia-ai-key-test') {
                testKeyFromModal();
            } else if (target.id === 'cbia-ai-key-cancel' || target.id === 'cbia-ai-key-close') {
                closeKeyModal();
            }
        }, true);

        root.addEventListener('submit', function (e) {
            e.preventDefault();
            onGenerateClick();
        });

        if (generateBtn) {
            generateBtn.type = 'button';
        }
        if (insertBtn) {
            insertBtn.type = 'button';
        }
        if (improveTextBtn) {
            improveTextBtn.type = 'button';
        }
        if (completeMissingBtn) {
            completeMissingBtn.type = 'button';
        }

        if (lengthSelect) lengthSelect.addEventListener('change', function () {
            updateSummary();
            updateLengthHelp();
        });
        if (temperatureInput) {
            temperatureInput.addEventListener('input', updateSummary);
            temperatureInput.addEventListener('change', updateSummary);
        }
        if (imagesSelect) imagesSelect.addEventListener('change', updateSummary);
        if (internalStyleSelect) internalStyleSelect.addEventListener('change', updateSummary);
        if (promptProfileSelect) promptProfileSelect.addEventListener('change', updateSummary);
        if (includeFaqToggle) includeFaqToggle.addEventListener('change', updateSummary);
        if (includeExamplesToggle) includeExamplesToggle.addEventListener('change', updateSummary);
        if (titleInput) {
            titleInput.addEventListener('input', function () {
                syncEditorTitle(titleInput.value || '');
            });
            titleInput.addEventListener('change', function () {
                syncEditorTitle(titleInput.value || '');
            });
        }
        schedulePendingYoastSync();
        if (languageSelect) languageSelect.addEventListener('change', updateSummary);
        updateSummary();
        updateLengthHelp();
        renderImageCards();
        hydrateComposerSnapshot();
        restoreRedirectTitleSync();

        applyApiConfigurationState();
        if (!canUsePreviewHtml(finalHtml) && !(preview && canUsePreviewHtml(preview.innerHTML))) {
            setStatus('Ready to generate. Enter a title and click "Generate with AI".', false);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            safeInit(addButton);
            safeInit(initAiComposerLauncher);
            safeInit(initProviderSelects);
            safeInit(initPromptEditor);
            safeInit(initAbbSelects);
            safeInit(initUsageModelSync);
            safeInit(initUsageDashboard);
            safeInit(initUsageRecalculationActions);
            safeInit(initAiComposer);
        });
    } else {
        safeInit(addButton);
        safeInit(initAiComposerLauncher);
        safeInit(initProviderSelects);
        safeInit(initPromptEditor);
        safeInit(initAbbSelects);
        safeInit(initUsageModelSync);
        safeInit(initUsageDashboard);
        safeInit(initUsageRecalculationActions);
        safeInit(initAiComposer);
    }
})();



(function () {
    'use strict';

    function initProviderConnections() {
        var root = document.getElementById('cbia-provider-connections');
        if (!root || !window.CBIAAdmin || !CBIAAdmin.ajaxUrl || !CBIAAdmin.nonce) return;

        function setPanel(card, open, focusInput) {
            var panel = card.querySelector('.cbia-provider-connection-panel');
            if (!panel) return;
            if (open) {
                root.querySelectorAll('.cbia-provider-connection-card.is-expanded').forEach(function (other) {
                    if (other !== card) setPanel(other, false, false);
                });
            }
            card.classList.toggle('is-expanded', open);
            panel.hidden = !open;
            card.querySelectorAll('[aria-controls="' + panel.id + '"]').forEach(function (button) {
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            if (open && focusInput) {
                var input = card.querySelector('.cbia-provider-credential-input');
                if (input) input.focus();
            }
        }

        function setBusy(card, busy) {
            card.setAttribute('aria-busy', busy ? 'true' : 'false');
            card.querySelectorAll('button').forEach(function (button) {
                button.disabled = busy || (button.getAttribute('data-action') === 'test' && card.classList.contains('is-not_configured'));
            });
        }

        function render(card, connection) {
            if (!connection) return;
            ['not_configured', 'not_tested', 'verified', 'authentication_error'].forEach(function (state) {
                card.classList.remove('is-' + state);
            });
            card.classList.add('is-' + connection.status);
            var stateNode = card.querySelector('[data-role="state"]');
            var lastNode = card.querySelector('[data-role="last-success"]');
            var modelsNode = card.querySelector('[data-role="models-count"]');
            var input = card.querySelector('.cbia-provider-credential-input');
            var openEditor = card.querySelector('[data-action="open-editor"]');
            var saveKey = card.querySelector('[data-action="save-key"]');
            var test = card.querySelector('[data-action="test"]');
            var disconnect = card.querySelector('[data-action="disconnect"]');
            if (stateNode) stateNode.textContent = connection.statusLabel || '';
            if (lastNode) lastNode.textContent = connection.lastSuccessLabel || '';
            if (modelsNode) modelsNode.textContent = connection.modelsCountLabel || '';
            if (input) {
                input.value = '';
                input.placeholder = connection.placeholder || '';
            }
            if (openEditor) openEditor.textContent = connection.connectLabel || openEditor.textContent;
            if (saveKey) saveKey.textContent = connection.configured ? (saveKey.getAttribute('data-update-label') || 'Save new key') : (connection.connectLabel || 'Connect');
            if (test) test.disabled = !connection.configured;
            if (connection.configured && !disconnect) {
                disconnect = document.createElement('button');
                disconnect.type = 'button';
                disconnect.className = 'button-link-delete';
                disconnect.setAttribute('data-action', 'disconnect');
                disconnect.textContent = connection.disconnectLabel || 'Disconnect';
                card.querySelector('.cbia-provider-connection-actions').appendChild(disconnect);
            } else if (!connection.configured && disconnect) {
                disconnect.remove();
            }
        }

        function request(card, action, apiKey) {
            var message = card.querySelector('[data-role="message"]');
            var params = new URLSearchParams();
            params.append('action', action);
            params.append('_ajax_nonce', CBIAAdmin.nonce);
            params.append('provider', card.getAttribute('data-provider') || '');
            if (typeof apiKey === 'string') params.append('api_key', apiKey);
            if (message) {
                message.textContent = '';
                message.classList.remove('is-error', 'is-success');
            }
            setBusy(card, true);
            return fetch(CBIAAdmin.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: params.toString()
            }).then(function (response) {
                return response.json().catch(function () { return null; });
            }).then(function (response) {
                if (!response || !response.success) {
                    var error = response && response.data && response.data.message ? response.data.message : root.getAttribute('data-network-error');
                    if (response && response.data && response.data.connection) render(card, response.data.connection);
                    throw new Error(error);
                }
                render(card, response.data.connection);
                if (message) {
                    message.textContent = response.data.message || '';
                    message.classList.add('is-success');
                }
                if (action === 'cbia_provider_connection_disconnect') setPanel(card, false, false);
            }).catch(function (error) {
                if (message) {
                    message.textContent = error && error.message ? error.message : root.getAttribute('data-network-error');
                    message.classList.add('is-error');
                }
            }).finally(function () {
                setBusy(card, false);
            });
        }

        root.addEventListener('click', function (event) {
            var button = event.target.closest('[data-action]');
            if (!button) return;
            var card = button.closest('.cbia-provider-connection-card');
            if (!card) return;
            event.preventDefault();
            var action = button.getAttribute('data-action');
            var input = card.querySelector('.cbia-provider-credential-input');
            if (action === 'open-editor') setPanel(card, true, true);
            if (action === 'toggle-details') setPanel(card, !card.classList.contains('is-expanded'), false);
            if (action === 'save-key') request(card, 'cbia_provider_connection_save', input ? input.value : '');
            if (action === 'test') request(card, 'cbia_provider_connection_test');
            if (action === 'disconnect' && window.confirm(card.getAttribute('data-disconnect-confirm') || '')) request(card, 'cbia_provider_connection_disconnect');
        });

        root.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            var card = event.target.closest('.cbia-provider-connection-card.is-expanded');
            if (!card) return;
            setPanel(card, false, false);
            var toggle = card.querySelector('[data-action="toggle-details"]');
            if (toggle) toggle.focus();
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initProviderConnections);
    else initProviderConnections();
}());
