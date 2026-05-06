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
                closeComposerModalFallback();
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
        updateGoogleImageExtras();
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
        if (!root || !dataNode || root.getAttribute('data-cbia-usage-bound') === '1') return;
        root.setAttribute('data-cbia-usage-bound', '1');

        var payload = {};
        try {
            payload = JSON.parse(dataNode.textContent || '{}');
        } catch (e) {
            payload = {};
        }

        var rows = Array.isArray(payload.rows) ? payload.rows.slice() : [];
        var totalRows = Number(payload.totalRows || rows.length || 0);
        var rowsLimited = !!payload.rowsLimited;
        var recentRowsLimit = Number(payload.recentRowsLimit || rows.length || 0);
        var summariesByModel = (payload.summariesByModel && typeof payload.summariesByModel === 'object') ? payload.summariesByModel : {};
        var modelOptions = Array.isArray(payload.modelOptions) ? payload.modelOptions.slice() : [];
        var initialDailySeries = Array.isArray(payload.dailySeries) ? payload.dailySeries.slice() : [];
        var i18n = (payload.i18n && typeof payload.i18n === 'object') ? payload.i18n : {};
        var lazyLoad = !!payload.lazyLoad;
        var ajaxUrl = String(payload.ajaxUrl || '');
        var ajaxNonce = String(payload.ajaxNonce || '');
        var usdToEur = Number(payload.usdToEur || 0.92);
        if (!isFinite(usdToEur) || usdToEur <= 0) usdToEur = 0.92;
        var modelSelect = document.getElementById('cbia-usage-model-filter');
        var typeSelect = document.getElementById('cbia-usage-type-filter');
        var searchInput = document.getElementById('cbia-usage-search');
        var daysSelect = document.getElementById('cbia-usage-days');
        var hiddenModel = document.getElementById('cbia-usage-model-hidden');
        var periodForm = document.getElementById('cbia-usage-period-form');
        var exportBtn = document.getElementById('cbia-usage-export');
        var tableBody = document.getElementById('cbia-usage-table-body');
        var detailPanel = document.getElementById('cbia-usage-detail');
        var activityCanvas = document.getElementById('cbia-usage-activity-chart');
        var activityEmpty = document.getElementById('cbia-usage-activity-empty');
        var typeCanvas = document.getElementById('cbia-usage-type-chart');
        var typeEmpty = document.getElementById('cbia-usage-type-empty');
        var monthlyCanvas = document.getElementById('cbia-usage-monthly-chart');
        var monthlyEmpty = document.getElementById('cbia-usage-monthly-empty');
        var activityHint = document.getElementById('cbia-usage-activity-hint');
        var typeHint = document.getElementById('cbia-usage-type-hint');
        var monthlyHint = document.getElementById('cbia-usage-monthly-hint');
        var tableMeta = document.getElementById('cbia-usage-table-meta');
        var loadingBanner = document.getElementById('cbia-usage-loading-banner');
        var loadingTitle = document.getElementById('cbia-usage-loading-title');
        var loadingHint = document.getElementById('cbia-usage-loading-hint');
        var selectedKey = '';
        var allFilteredRows = [];
        var loadingRemote = false;

        function t(key, fallback) {
            var value = i18n && Object.prototype.hasOwnProperty.call(i18n, key) ? i18n[key] : fallback;
            return String(value || fallback || '');
        }

        function numberFormat(value) {
            return new Intl.NumberFormat('es-ES').format(Number(value || 0));
        }

        function hasNumericValue(value) {
            return value !== null && value !== '' && isFinite(Number(value));
        }

        function currencyFormat(value) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 2,
                maximumFractionDigits: 4
            }).format(Number(value || 0));
        }

        function eurToUsd(value) {
            var num = Number(value || 0);
            if (!isFinite(num)) num = 0;
            if (!isFinite(usdToEur) || usdToEur <= 0) return num;
            return num / usdToEur;
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function rowKey(row) {
            return [
                String(row.ts || ''),
                String(row.post_id || ''),
                String(row.type || ''),
                String(row.model || ''),
                String(row.section || ''),
                String(row.attach_id || '')
            ].join('|');
        }

        function formatShortDate(value) {
            var text = String(value || '');
            if (/^\d{4}-\d{2}$/.test(text)) {
                return formatMonth(text);
            }
            var datePart = text.slice(0, 10);
            var bits = datePart.split('-');
            if (bits.length === 3) {
                return bits[2] + '/' + bits[1];
            }
            return text || '-';
        }

        function formatMonth(value) {
            var text = String(value || '').trim();
            var bits = text.split('-');
            if (bits.length !== 2) return text || '-';
            var monthIndex = parseInt(bits[1], 10) - 1;
            var names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            var monthLabel = (monthIndex >= 0 && monthIndex < names.length) ? names[monthIndex] : bits[1];
            return monthLabel + ' ' + bits[0];
        }

        function formatMonthTick(value) {
            var text = String(value || '').trim();
            var bits = text.split('-');
            if (bits.length !== 2) return text || '-';
            var monthIndex = parseInt(bits[1], 10) - 1;
            var names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return (monthIndex >= 0 && monthIndex < names.length) ? names[monthIndex] : bits[1];
        }

        function formatDateTime(value) {
            var text = String(value || '');
            if (!text) return '-';
            return text.replace(' ', ' · ');
        }

        // Override with clean separator to avoid mojibake artifacts in tables.
        function formatDateTime(value) {
            var text = String(value || '');
            if (!text) return '-';
            return text.replace(' ', ' · ');
        }

        function getSearchValue() {
            return String((searchInput && searchInput.value) || '').trim().toLowerCase();
        }

        function parseIsoDay(text) {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(String(text || ''))) return null;
            var parts = String(text).split('-');
            var y = parseInt(parts[0], 10);
            var m = parseInt(parts[1], 10) - 1;
            var d = parseInt(parts[2], 10);
            if (!isFinite(y) || !isFinite(m) || !isFinite(d)) return null;
            var dt = new Date(Date.UTC(y, m, d));
            return isNaN(dt.getTime()) ? null : dt;
        }

        function formatLocalIsoDay(date) {
            if (!(date instanceof Date) || isNaN(date.getTime())) return '';
            var y = date.getFullYear();
            var m = date.getMonth() + 1;
            var d = date.getDate();
            return String(y) + '-' + (m < 10 ? '0' + m : String(m)) + '-' + (d < 10 ? '0' + d : String(d));
        }

        function addDaysIso(day, amount) {
            var base = parseIsoDay(day);
            if (!base) return '';
            base.setUTCDate(base.getUTCDate() + Number(amount || 0));
            return base.toISOString().slice(0, 10);
        }

        function getPeriodInfo() {
            var days = Number(payload.periodDays || 30);
            if (!isFinite(days) || days <= 0) days = 30;
            var startIso = String(payload.periodStartDay || '').trim();
            var endIso = String(payload.periodEndDay || '').trim();
            if (!/^\d{4}-\d{2}-\d{2}$/.test(startIso) || !/^\d{4}-\d{2}-\d{2}$/.test(endIso)) {
                var endDate = new Date();
                var startDate = new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate());
                startDate.setDate(startDate.getDate() - (days - 1));
                startIso = formatLocalIsoDay(startDate);
                endIso = formatLocalIsoDay(endDate);
            }
            return {
                days: days,
                startIso: startIso,
                endIso: endIso,
                startLabel: formatShortDate(startIso),
                endLabel: formatShortDate(endIso),
                label: 'Period: ' + formatShortDate(startIso) + ' - ' + formatShortDate(endIso) + ' (' + numberFormat(days) + ' days)'
            };
        }

        function updateChartHints() {
            var period = getPeriodInfo();
            if (activityHint) {
                activityHint.textContent = 'Y axis: number of AI events recorded per day in the current filter. ' + period.label + '.';
            }
            if (typeHint) {
                typeHint.textContent = 'Y axis: number of text and image calls in the current filter. ' + period.label + '.';
            }
            if (monthlyHint) {
                var year = String(period.endIso || '').slice(0, 4);
                if (!/^\d{4}$/.test(year)) year = String(new Date().getFullYear());
                monthlyHint.textContent = 'Y axis: dollars (USD). Jan-Dec ' + year + ' timeline (months without data are shown as zero).';
            }
        }

        function populateModelOptions(options) {
            if (!modelSelect) return;
            var list = Array.isArray(options) ? options.slice() : [];
            var selectedValue = String((payload.defaultModel || modelSelect.value || '')).trim();
            var allModelsLabel = (modelSelect.options && modelSelect.options.length)
                ? String(modelSelect.options[0].text || 'All models')
                : 'All models';
            var html = '<option value="">' + escapeHtml(allModelsLabel) + '</option>';
            list.forEach(function (modelName) {
                var name = String(modelName || '').trim();
                if (!name) return;
                html += '<option value="' + escapeHtml(name) + '"' + (name === selectedValue ? ' selected' : '') + '>' + escapeHtml(name) + '</option>';
            });
            modelSelect.innerHTML = html;
            modelSelect.value = selectedValue;
        }

        function setLoadingState(message) {
            setDashboardLoading(true, message, t('loadingHint', 'Charts and table will fill in automatically in a few seconds.'));
            if (tableMeta) {
                tableMeta.textContent = message || t('loadingData', 'Loading real usage data...');
            }
            if (tableBody) {
                tableBody.innerHTML = '<tr><td colspan="7" class="cbia-usage-table-placeholder">' + escapeHtml(t('loadingLogs', 'Loading logs...')) + '</td></tr>';
            }
            if (detailPanel) {
                detailPanel.innerHTML = '<div class="cbia-usage-detail-skeleton" id="cbia-usage-detail-skeleton-runtime" aria-hidden="true"><span class="is-title"></span><span></span><span></span><span class="is-wide"></span><span></span><span class="is-wide"></span></div><div class="cbia-usage-detail-empty">' + escapeHtml(t('loadingDetail', 'Preparing detail view...')) + '</div>';
            }
        }

        function setDashboardLoading(isLoading, titleText, hintText) {
            root.classList.toggle('is-loading', !!isLoading);
            root.setAttribute('aria-busy', isLoading ? 'true' : 'false');
            if (loadingBanner) {
                loadingBanner.hidden = !isLoading;
            }
            if (loadingTitle) {
                loadingTitle.textContent = titleText || t('loadingData', 'Loading real usage data...');
            }
            if (loadingHint) {
                loadingHint.textContent = hintText || t('loadingHint', 'Charts and table will fill in automatically in a few seconds.');
            }
        }

        function getSelectedModelKey() {
            var value = String((modelSelect && modelSelect.value) || '').trim();
            return value || '__all__';
        }

        function getActiveSummary() {
            var modelKey = getSelectedModelKey();
            return summariesByModel[modelKey] || summariesByModel.__all__ || null;
        }

        function canUseSummaryDataset() {
            var hasTypeFilter = !!String((typeSelect && typeSelect.value) || '').trim();
            var hasSearchFilter = !!getSearchValue();
            return !hasTypeFilter && !hasSearchFilter;
        }

        function getFilteredRows() {
            var model = String((modelSelect && modelSelect.value) || '').trim();
            var type = String((typeSelect && typeSelect.value) || '').trim();
            var term = getSearchValue();

            return rows.filter(function (row) {
                if (model && String(row.model || '') !== model) return false;
                if (type && String(row.type || '') !== type) return false;
                if (!term) return true;

                var haystack = [
                    row.ts,
                    row.user_name,
                    row.post_title,
                    row.model,
                    row.type_label,
                    row.message_preview,
                    row.status_label
                ].join(' ').toLowerCase();
                return haystack.indexOf(term) !== -1;
            });
        }

        function updateExportLink() {
            if (!exportBtn) return;
            if (hiddenModel && modelSelect) {
                hiddenModel.value = modelSelect.value || '';
            }
            try {
                var url = new URL(exportBtn.href, window.location.origin);
                url.searchParams.set('usage_model', (modelSelect && modelSelect.value) ? modelSelect.value : '');
                exportBtn.href = url.toString();
            } catch (_e) {}
        }

        function applyRemotePayload(remotePayload) {
            payload = remotePayload || {};
            rows = Array.isArray(payload.rows) ? payload.rows.slice() : [];
            totalRows = Number(payload.totalRows || rows.length || 0);
            rowsLimited = !!payload.rowsLimited;
            recentRowsLimit = Number(payload.recentRowsLimit || rows.length || 0);
            summariesByModel = (payload.summariesByModel && typeof payload.summariesByModel === 'object') ? payload.summariesByModel : {};
            modelOptions = Array.isArray(payload.modelOptions) ? payload.modelOptions.slice() : [];
            initialDailySeries = Array.isArray(payload.dailySeries) ? payload.dailySeries.slice() : [];
            if (payload.usdToEur !== undefined) {
                var parsedRate = Number(payload.usdToEur);
                if (isFinite(parsedRate) && parsedRate > 0) {
                    usdToEur = parsedRate;
                }
            }
            if (payload.defaultModel !== undefined) {
                payload.defaultModel = String(payload.defaultModel || '');
            }
            populateModelOptions(modelOptions);
        }

        function loadUsageData() {
            if (!lazyLoad || !ajaxUrl || loadingRemote) {
                return;
            }
            loadingRemote = true;
            setLoadingState(t('loadingData', 'Loading real usage data...'));

            var body = new URLSearchParams();
            body.set('action', 'cbia_usage_overview_data');
            body.set('nonce', ajaxNonce);
            body.set('days', String(payload.periodDays || 30));
            body.set('usage_model', String(payload.defaultModel || ''));

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (json) {
                    if (!json || !json.success || !json.data) {
                        throw new Error('Invalid usage payload');
                    }
                    applyRemotePayload(json.data);
                    loadingRemote = false;
                    lazyLoad = false;
                    setDashboardLoading(false);
                    refresh();
                })
                .catch(function () {
                    loadingRemote = false;
                    setDashboardLoading(false);
                    if (tableMeta) {
                        tableMeta.textContent = t('loadErrorMeta', 'Could not load usage data right now.');
                    }
                    if (tableBody) {
                        tableBody.innerHTML = '<tr><td colspan="7" class="cbia-usage-table-placeholder">' + escapeHtml(t('loadErrorRow', 'Could not load usage data.')) + '</td></tr>';
                    }
                    if (detailPanel) {
                        detailPanel.innerHTML = '<div class="cbia-usage-detail-empty">' + escapeHtml(t('loadErrorDetail', 'Could not load usage data.')) + '</div>';
                    }
                });
        }

        function getDailyBuckets(filtered) {
            if (Array.isArray(filtered) && filtered.length === rows.length && initialDailySeries.length) {
                return initialDailySeries.slice();
            }
            var buckets = {};
            var fallbackDay = formatLocalIsoDay(new Date());
            filtered.forEach(function (row) {
                var day = String(row.day || '').trim();
                if (!day && row.ts) {
                    var tsMatch = String(row.ts || '').match(/\d{4}-\d{2}-\d{2}/);
                    day = tsMatch ? String(tsMatch[0] || '').trim() : '';
                }
                if (!day) {
                    day = fallbackDay;
                }
                if (!day) return;
                if (!buckets[day]) {
                    buckets[day] = { calls: 0, text: 0, image: 0, seo: 0, textCalls: 0, imageCalls: 0, seoCalls: 0 };
                }
                buckets[day].calls += 1;
                var bucketKey = String(row.type || 'text');
                var bucketValue = bucketKey === 'image'
                    ? 1
                    : Number(row.tokens_total || 0);
                if (!isFinite(bucketValue) || bucketValue < 0) bucketValue = 0;
                buckets[day][bucketKey] += bucketValue;
                var countKey = bucketKey + 'Calls';
                if (Object.prototype.hasOwnProperty.call(buckets[day], countKey)) {
                    buckets[day][countKey] += 1;
                }
            });
            return Object.keys(buckets).sort().map(function (day) {
                var item = buckets[day];
                item.day = day;
                item.totalTokens = item.text + item.image + item.seo;
                return item;
            });
        }

        function getMonthlyBuckets() {
            var summary = getActiveSummary();
            var series = (summary && Array.isArray(summary.monthlySeries))
                ? summary.monthlySeries.slice()
                : (Array.isArray(payload.monthlySeries) ? payload.monthlySeries.slice() : []);
            var monthMap = {};
            series.forEach(function (item) {
                if (!item) return;
                var monthKey = String(item.month || '').trim();
                if (!/^\d{4}-\d{2}$/.test(monthKey)) return;
                monthMap[monthKey] = item;
            });
            var period = getPeriodInfo();
            var year = String(period.endIso || '').slice(0, 4);
            if (!/^\d{4}$/.test(year)) year = String(new Date().getFullYear());
            var filled = [];
            for (var m = 1; m <= 12; m++) {
                var mm = m < 10 ? ('0' + m) : String(m);
                var key = year + '-' + mm;
                var src = monthMap[key] || {};
                filled.push({
                    month: key,
                    calls: Number(src.calls || 0),
                    text_cost_eur: Number(src.text_cost_eur || 0),
                    image_cost_eur: Number(src.image_cost_eur || 0),
                    seo_cost_eur: Number(src.seo_cost_eur || 0),
                    cost_eur: Number(src.cost_eur || 0)
                });
            }
            return filled;
        }

        function getActivitySeries(filtered) {
            var baseSeries = [];
            if (canUseSummaryDataset()) {
                var summary = getActiveSummary();
                if (summary && Array.isArray(summary.dailySeries) && summary.dailySeries.length) {
                    baseSeries = summary.dailySeries.slice();
                }
            }
            if (!baseSeries.length) {
                baseSeries = getDailyBuckets(filtered);
            }

            if (!baseSeries.length) return [];

            var period = getPeriodInfo();
            var byDay = {};
            baseSeries.forEach(function (item) {
                if (!item) return;
                var day = String(item.day || '').trim();
                if (!/^\d{4}-\d{2}-\d{2}$/.test(day)) return;
                byDay[day] = item;
            });

            var series = [];
            for (var d = 0; d < period.days; d++) {
                var dayKey = addDaysIso(period.startIso, d);
                if (!dayKey) continue;
                var src = byDay[dayKey] || {};
                series.push({
                    day: dayKey,
                    calls: Number(src.calls || 0),
                    text: Number(src.text || 0),
                    image: Number(src.image || 0),
                    seo: Number(src.seo || 0),
                    textCalls: Number(src.textCalls || 0),
                    imageCalls: Number(src.imageCalls || 0),
                    seoCalls: Number(src.seoCalls || 0)
                });
            }

            var paddingDays = 3;
            for (var i = 1; i <= paddingDays; i++) {
                var nextDay = addDaysIso(period.endIso, i);
                if (!nextDay) continue;
                series.push({
                    day: nextDay,
                    calls: null,
                    text: 0,
                    image: 0,
                    seo: 0,
                    textCalls: 0,
                    imageCalls: 0,
                    seoCalls: 0,
                    _future: true
                });
            }
            return series;
        }

        function getTypeSeries(filtered) {
            if (canUseSummaryDataset()) {
                var summary = getActiveSummary();
                if (summary && summary.typeCounts) {
                    return [
                        { key: 'text', label: 'Text', value: Number(summary.typeCounts.text || 0) },
                        { key: 'image', label: 'Image', value: Number(summary.typeCounts.image || 0) }
                    ];
                }
            }
            return getTypeActivityBuckets(filtered);
        }

        function setupCanvas(canvas) {
            var parent = canvas.parentElement;
            var width = Math.max(280, (parent ? parent.clientWidth : canvas.clientWidth || 280) - 2);
            var height = 260;
            var ratio = window.devicePixelRatio || 1;
            canvas.width = Math.round(width * ratio);
            canvas.height = Math.round(height * ratio);
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';
            var ctx = canvas.getContext('2d');
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            return { ctx: ctx, width: width, height: height };
        }

        function formatAxisNumber(value) {
            var num = Number(value || 0);
            if (!isFinite(num)) num = 0;
            if (Math.abs(num) < 10) {
                return new Intl.NumberFormat('es-ES', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 1
                }).format(num);
            }
            return numberFormat(Math.round(num));
        }

        function formatAxisCurrency(value) {
            var num = Number(value || 0);
            if (!isFinite(num)) num = 0;
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: num < 0.1 ? 3 : 2,
                maximumFractionDigits: num < 0.1 ? 3 : 2
            }).format(num);
        }

        function drawAxes(ctx, width, height, maxValue, labels, options) {
            var opts = options || {};
            var left = 56;
            var right = width - 28;
            var top = 24;
            var bottom = height - 42;
            var plotW = right - left;
            var plotH = bottom - top;
            var steps = 4;
            var categorical = !!opts.categorical;
            var valueFormatter = typeof opts.valueFormatter === 'function' ? opts.valueFormatter : formatAxisNumber;
            var labelFormatter = typeof opts.labelFormatter === 'function' ? opts.labelFormatter : formatShortDate;

            ctx.clearRect(0, 0, width, height);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, width, height);
            ctx.strokeStyle = '#e8edf4';
            ctx.lineWidth = 1;

            for (var i = 0; i <= steps; i++) {
                var y = top + ((plotH / steps) * i);
                ctx.beginPath();
                ctx.moveTo(left, y);
                ctx.lineTo(right, y);
                ctx.stroke();

                var value = maxValue - ((maxValue / steps) * i);
                ctx.fillStyle = '#8ca0b8';
                ctx.font = '11px Segoe UI, Arial, sans-serif';
                ctx.textAlign = 'right';
                ctx.fillText(String(valueFormatter(value)), left - 8, y + 4);
            }

            var requestedLabels = Number(opts.maxLabels || (categorical ? (labels.length <= 12 ? labels.length : 8) : 7));
            if (!isFinite(requestedLabels) || requestedLabels < 2) {
                requestedLabels = categorical ? 8 : 7;
            }
            var targetLabels = Math.max(2, Math.min(labels.length || 1, Math.round(requestedLabels)));
            var labelStep = Math.max(1, Math.ceil(labels.length / Math.max(1, targetLabels)));
            var bandW = labels.length ? (plotW / Math.max(1, labels.length)) : plotW;
            var lastDrawnX = null;
            var minGap = categorical ? 38 : 34;
            labels.forEach(function (label, index) {
                if (index % labelStep !== 0 && index !== labels.length - 1) return;
                var x = categorical
                    ? left + (bandW * index) + (bandW / 2)
                    : (labels.length === 1
                        ? left + (plotW / 2)
                        : left + ((plotW / Math.max(1, labels.length - 1)) * index));
                if (lastDrawnX !== null && Math.abs(x - lastDrawnX) < minGap && index !== labels.length - 1) return;
                lastDrawnX = x;
                ctx.fillStyle = '#8ca0b8';
                ctx.font = '11px Segoe UI, Arial, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(String(labelFormatter(label, index, labels)), x, height - 8);
            });

            return {
                left: left,
                right: right,
                top: top,
                bottom: bottom,
                width: plotW,
                height: plotH
            };
        }

        function getNiceCountAxisMax(value, minimum) {
            var num = Number(value || 0);
            if (!isFinite(num) || num <= 0) num = 1;
            var min = Number(minimum || 0);
            if (!isFinite(min) || min < 0) min = 0;
            num = Math.max(num, min);

            if (num <= 4) return 4;
            if (num <= 8) return 8;
            if (num <= 12) return 12;

            var magnitude = Math.pow(10, Math.floor(Math.log(num) / Math.LN10));
            var residual = num / magnitude;
            var niceResidual = 10;
            if (residual <= 1) {
                niceResidual = 1;
            } else if (residual <= 2) {
                niceResidual = 2;
            } else if (residual <= 5) {
                niceResidual = 5;
            }
            return niceResidual * magnitude;
        }

        function getNiceCurrencyAxisMax(value, minimum) {
            var num = Number(value || 0);
            if (!isFinite(num) || num <= 0) num = 0.01;
            var min = Number(minimum || 0);
            if (!isFinite(min) || min < 0) min = 0;
            num = Math.max(num, min);

            if (num <= 0.02) return 0.02;
            if (num <= 0.05) return 0.05;
            if (num <= 0.10) return 0.10;
            if (num <= 0.20) return 0.20;
            if (num <= 0.50) return 0.50;
            if (num <= 1.00) return 1.00;
            if (num <= 2.00) return 2.00;
            if (num <= 5.00) return 5.00;

            var magnitude = Math.pow(10, Math.floor(Math.log(num) / Math.LN10));
            var residual = num / magnitude;
            var niceResidual = 10;
            if (residual <= 1) {
                niceResidual = 1;
            } else if (residual <= 2) {
                niceResidual = 2;
            } else if (residual <= 5) {
                niceResidual = 5;
            }
            return niceResidual * magnitude;
        }

        function getBandCenter(area, count, index) {
            var bandW = area.width / Math.max(1, count);
            return area.left + (bandW * index) + (bandW / 2);
        }

        function getUsageChartColors() {
            return {
                text: '#5B8DEF',
                image: '#7BC67B'
            };
        }

        function getTypeActivityBuckets(filtered) {
            var counts = { text: 0, image: 0 };
            filtered.forEach(function (row) {
                var key = String(row.type || 'text');
                if (!Object.prototype.hasOwnProperty.call(counts, key)) key = 'text';
                counts[key] += 1;
            });
            return [
                { key: 'text', label: 'Text', value: counts.text },
                { key: 'image', label: 'Image', value: counts.image }
            ];
        }

        function renderActivityChart(filtered) {
            var series = getActivitySeries(filtered);
            if (!activityCanvas || !activityEmpty) return;
            if (!series.length) {
                activityCanvas.hidden = true;
                activityEmpty.hidden = false;
                return;
            }

            activityCanvas.hidden = false;
            activityEmpty.hidden = true;

            var chart = setupCanvas(activityCanvas);
            var ctx = chart.ctx;
            var labels = series.map(function (item) { return item.day; });
            var numericTotals = series.map(function (item) {
                if (item && item.calls !== null && item.calls !== '' && isFinite(Number(item.calls))) {
                    return Math.max(0, Number(item.calls));
                }
                return null;
            });
            var positiveTotals = numericTotals.filter(function (value) {
                return value !== null && value > 0;
            });
            if (!positiveTotals.length) {
                activityCanvas.hidden = true;
                activityEmpty.hidden = false;
                return;
            }
            var rawMaxValue = Math.max.apply(null, positiveTotals.concat([1]));
            var maxValue = getNiceCountAxisMax(rawMaxValue, 4);
            var area = drawAxes(ctx, chart.width, chart.height, maxValue, labels, {
                categorical: false,
                valueFormatter: formatAxisNumber,
                maxLabels: 8
            });
            var points = series.map(function (item, index) {
                var total = numericTotals[index];
                var x = (series.length === 1)
                    ? area.left + (area.width / 2)
                    : area.left + ((area.width / Math.max(1, series.length - 1)) * index);
                var y = total === null ? null : area.bottom - ((Math.max(0, total) / maxValue) * area.height);
                return { x: x, y: y, total: total };
            });

            if (!points.length) {
                return;
            }

            var gradient = ctx.createLinearGradient(0, area.top, 0, area.bottom);
            gradient.addColorStop(0, 'rgba(91, 141, 239, 0.28)');
            gradient.addColorStop(1, 'rgba(91, 141, 239, 0.02)');

            var validPoints = points.filter(function (point) {
                return point && point.y !== null;
            });

            if (validPoints.length > 1) {
                ctx.beginPath();
                ctx.moveTo(validPoints[0].x, area.bottom);
                validPoints.forEach(function (point) {
                    ctx.lineTo(point.x, point.y);
                });
                ctx.lineTo(validPoints[validPoints.length - 1].x, area.bottom);
                ctx.closePath();
                ctx.fillStyle = gradient;
                ctx.fill();

                ctx.beginPath();
                validPoints.forEach(function (point, index) {
                    if (index === 0) {
                        ctx.moveTo(point.x, point.y);
                    } else {
                        ctx.lineTo(point.x, point.y);
                    }
                });
                ctx.strokeStyle = '#5B8DEF';
                ctx.lineWidth = 3;
                ctx.stroke();
            } else if (validPoints.length === 1) {
                ctx.beginPath();
                ctx.moveTo(validPoints[0].x, area.bottom);
                ctx.lineTo(validPoints[0].x, validPoints[0].y);
                ctx.strokeStyle = 'rgba(91, 141, 239, 0.35)';
                ctx.lineWidth = 2;
                ctx.stroke();
            }

            var lastLabelX = -9999;
            points.forEach(function (point) {
                if (point.y === null || !isFinite(point.total) || point.total <= 0) return;
                ctx.beginPath();
                ctx.arc(point.x, point.y, 4, 0, Math.PI * 2);
                ctx.fillStyle = '#ffffff';
                ctx.fill();
                ctx.lineWidth = 2;
                ctx.strokeStyle = '#5B8DEF';
                ctx.stroke();

                ctx.fillStyle = '#627892';
                ctx.font = '11px Segoe UI, Arial, sans-serif';
                ctx.textAlign = 'center';
                var labelX = Math.max(area.left + 14, Math.min(area.right - 14, point.x));
                if (Math.abs(labelX - lastLabelX) < 26) return;
                lastLabelX = labelX;
                ctx.fillText(numberFormat(point.total), labelX, Math.max(area.top + 10, point.y - 8));
            });
        }

        function renderTypeChart(filtered) {
            var series = getTypeSeries(filtered);
            if (!typeCanvas || !typeEmpty) return;
            if (!series.length || !series.some(function (item) { return Number(item.value || 0) > 0; })) {
                typeCanvas.hidden = true;
                typeEmpty.hidden = false;
                return;
            }

            typeCanvas.hidden = false;
            typeEmpty.hidden = true;

            var chart = setupCanvas(typeCanvas);
            var ctx = chart.ctx;
            var labels = series.map(function (item) { return item.label; });
            var rawMaxValue = Math.max.apply(null, series.map(function (item) { return Number(item.value || 0); }).concat([1]));
            var maxValue = getNiceCountAxisMax(rawMaxValue, 4);
            var area = drawAxes(ctx, chart.width, chart.height, maxValue, labels, {
                categorical: true,
                valueFormatter: formatAxisNumber,
                maxLabels: labels.length
            });
            var bandW = area.width / Math.max(1, series.length);
            var barWidth = Math.max(34, Math.min(86, Math.floor(bandW * 0.58)));
            var colors = getUsageChartColors();

            series.forEach(function (item, index) {
                var xCenter = getBandCenter(area, series.length, index);
                var x = xCenter - (barWidth / 2);
                var value = Number(item.value || 0);
                if (!isFinite(value) || value < 0) value = 0;
                if (value <= 0) return;
                var barH = Math.max(1, (value / maxValue) * area.height);
                var y = area.bottom - barH;
                ctx.fillStyle = colors[item.key] || colors.text;
                ctx.fillRect(x, y, barWidth, barH);
                var labelY = y + Math.min(22, Math.max(15, (barH * 0.45)));
                var inside = barH >= 28;
                ctx.fillStyle = inside ? '#ffffff' : '#627892';
                ctx.font = inside ? '600 12px Segoe UI, Arial, sans-serif' : '11px Segoe UI, Arial, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(numberFormat(value), xCenter, inside ? labelY : Math.max(area.top + 10, y - 6));
            });
        }

        function renderMonthlyChart() {
            var series = getMonthlyBuckets();
            if (!monthlyCanvas || !monthlyEmpty) return;
            if (!series.length) {
                monthlyCanvas.hidden = true;
                monthlyEmpty.hidden = false;
                return;
            }

            monthlyCanvas.hidden = false;
            monthlyEmpty.hidden = true;

            var chart = setupCanvas(monthlyCanvas);
            var ctx = chart.ctx;
            var labels = series.map(function (item) { return item.month; });
            var rawMaxValue = Math.max.apply(null, series.map(function (item) {
                return eurToUsd(Number(item.text_cost_eur || 0) + Number(item.image_cost_eur || 0));
            }).concat([0.01]));
            var maxValue = getNiceCurrencyAxisMax(rawMaxValue, 0.05);
            var area = drawAxes(ctx, chart.width, chart.height, maxValue, labels, {
                categorical: true,
                valueFormatter: formatAxisCurrency,
                labelFormatter: formatMonthTick,
                maxLabels: labels.length
            });
            var bandW = area.width / Math.max(1, series.length);
            var barWidth = Math.max(12, Math.min(44, Math.floor(bandW * 0.56)));
            var colors = getUsageChartColors();

            series.forEach(function (item, index) {
                var xCenter = getBandCenter(area, series.length, index);
                var x = xCenter - (barWidth / 2);
                var stack = [
                    { value: eurToUsd(Number(item.text_cost_eur || 0)), color: colors.text },
                    { value: eurToUsd(Number(item.image_cost_eur || 0)), color: colors.image }
                ];
                var total = 0;
                stack.forEach(function (seg) {
                    total += Number(seg.value || 0);
                });
                if (!isFinite(total) || total <= 0) return;

                var currentBottom = area.bottom;
                stack.forEach(function (seg) {
                    var value = Number(seg.value || 0);
                    if (!isFinite(value) || value <= 0) return;
                    var barH = Math.max(2, (value / maxValue) * area.height);
                    var y = currentBottom - barH;
                    ctx.fillStyle = seg.color;
                    ctx.fillRect(x, y, barWidth, barH);
                    currentBottom = y;
                });
                var totalBarHeight = area.bottom - currentBottom;
                var labelY = currentBottom + Math.min(22, Math.max(15, (totalBarHeight * 0.45)));
                var inside = totalBarHeight >= 34;
                var showLabel = inside && barWidth >= 24;
                if (!showLabel) return;
                ctx.fillStyle = inside ? '#ffffff' : '#627892';
                ctx.font = inside ? '600 12px Segoe UI, Arial, sans-serif' : '11px Segoe UI, Arial, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(formatAxisCurrency(total), xCenter, inside ? labelY : Math.max(area.top + 10, currentBottom - 6));
            });
        }

        function renderKpis(filtered) {
            var totalCalls = filtered.length;
            var uniquePostsCount = 0;
            var uniqueUsersCount = 0;
            var totalTokens = 0;
            var totalCost = 0;
            var avg = 0;
            var avgCostPerPost = 0;

            if (canUseSummaryDataset()) {
                var summary = getActiveSummary();
                if (summary) {
                    totalCalls = Number(summary.totalCalls || 0);
                    uniquePostsCount = Number(summary.uniquePosts || 0);
                    uniqueUsersCount = Number(summary.uniqueUsers || 0);
                    totalTokens = Number(summary.totalTokens || 0);
                    totalCost = Number(summary.totalCost || 0);
                    avg = Number(summary.avgTokens || 0);
                    avgCostPerPost = Number(summary.avgCostPerPost || 0);
                }
            } else {
                var uniquePosts = new Set();
                var uniqueUsers = new Set();
                filtered.forEach(function (row) {
                    if (row.post_id) uniquePosts.add(String(row.post_id));
                    if (row.user_id) uniqueUsers.add(String(row.user_id));
                    totalTokens += Number(row.tokens_total || 0);
                    if (hasNumericValue(row.cost_eur)) {
                        totalCost += Number(row.cost_eur);
                    }
                });
                uniquePostsCount = uniquePosts.size;
                uniqueUsersCount = uniqueUsers.size;
                avg = totalCalls ? Math.round(totalTokens / totalCalls) : 0;
                avgCostPerPost = uniquePostsCount ? (totalCost / uniquePostsCount) : 0;
            }
            totalCost = eurToUsd(totalCost);
            avgCostPerPost = eurToUsd(avgCostPerPost);

            var callsNode = document.getElementById('cbia-usage-kpi-calls');
            var postsNode = document.getElementById('cbia-usage-kpi-posts');
            var usersNode = document.getElementById('cbia-usage-kpi-users');
            var avgNode = document.getElementById('cbia-usage-kpi-avg');
            var costTotalNode = document.getElementById('cbia-usage-kpi-cost-total');
            var costBlogNode = document.getElementById('cbia-usage-kpi-cost-blog');

            if (callsNode) callsNode.textContent = numberFormat(totalCalls);
            if (postsNode) postsNode.textContent = numberFormat(uniquePostsCount);
            if (usersNode) usersNode.textContent = numberFormat(uniqueUsersCount);
            if (avgNode) avgNode.textContent = numberFormat(avg);
            if (costTotalNode) costTotalNode.textContent = currencyFormat(totalCost);
            if (costBlogNode) costBlogNode.textContent = currencyFormat(avgCostPerPost);
        }

        function aggregatePostSummary(row, filteredRows) {
            if (!row) return null;
            var postId = Number(row.post_id || 0);
            var relatedRows = (Array.isArray(filteredRows) ? filteredRows : []).filter(function (item) {
                return Number(item.post_id || 0) === postId;
            });
            if (!relatedRows.length) {
                relatedRows = [row];
            }

            var summary = {
                post_id: postId,
                post_title: row.post_title || 'Post summary',
                post_edit_url: row.post_edit_url || '',
                latest_ts: row.ts || '',
                latest_user_name: row.user_name || '-',
                models: [],
                types: [],
                call_count: relatedRows.length,
                ok_count: 0,
                fail_count: 0,
                billable_fail_count: 0,
                text_calls: 0,
                seo_calls: 0,
                image_calls: 0,
                featured_images: 0,
                internal_images: 0,
                token_in: 0,
                token_out: 0,
                token_total: 0,
                token_events: 0,
                total_cost: 0
            };

            var modelsSeen = {};
            var typesSeen = {};
            relatedRows.forEach(function (item, index) {
                if (index === 0 && item.ts) {
                    summary.latest_ts = item.ts;
                }
                if (item.ok) summary.ok_count += 1;
                else summary.fail_count += 1;
                if (!item.ok && hasNumericValue(item.cost_eur) && Number(item.cost_eur) > 0) {
                    summary.billable_fail_count += 1;
                }
                if (hasNumericValue(item.cost_eur)) {
                    summary.total_cost += Number(item.cost_eur || 0);
                }

                var itemType = String(item.type || 'text');
                if (!typesSeen[itemType]) {
                    typesSeen[itemType] = true;
                    summary.types.push(itemType);
                }
                var itemModel = String(item.model || '').trim();
                if (itemModel && !modelsSeen[itemModel]) {
                    modelsSeen[itemModel] = true;
                    summary.models.push(itemModel);
                }

                if (itemType === 'image') {
                    summary.image_calls += 1;
                    var section = String(item.section || '').trim();
                    var sectionLabel = String(item.section_label || '').trim();
                    if (section === 'featured' || section === 'intro' || sectionLabel === 'featured' || sectionLabel === 'destacada') {
                        summary.featured_images += 1;
                    } else {
                        summary.internal_images += 1;
                    }
                } else {
                    if (itemType === 'seo') summary.seo_calls += 1;
                    else summary.text_calls += 1;
                    summary.token_in += Number(item.tokens_in || 0);
                    summary.token_out += Number(item.tokens_out || 0);
                    summary.token_total += Number(item.tokens_total || 0);
                    summary.token_events += 1;
                }
            });

            return summary;
        }

        function renderDetail(row, filteredRows) {
            if (!detailPanel) return;
            if (!row) {
                detailPanel.innerHTML = '<div class="cbia-usage-detail-empty">No detail available for the current selection.</div>';
                return;
            }

            var summary = aggregatePostSummary(row, filteredRows);
            var actions = '';
            if (summary && summary.post_edit_url) {
                actions = '<div class="cbia-usage-detail-actions"><a class="button button-secondary" href="' + escapeHtml(summary.post_edit_url) + '">Open post</a></div>';
            }

            var summaryTokenApplicable = !!(summary && summary.token_events > 0);
            var summaryTokenIn = summaryTokenApplicable ? numberFormat(summary.token_in) : 'N/A';
            var summaryTokenOut = summaryTokenApplicable ? numberFormat(summary.token_out) : 'N/A';
            var summaryTokenTotal = summaryTokenApplicable ? numberFormat(summary.token_total) : 'N/A';
            var summaryTypes = summary && summary.types.length
                ? summary.types.map(function (type) {
                    return type === 'image' ? 'Image' : (type === 'seo' ? 'SEO' : 'Text');
                }).join(', ')
                : '-';
            var imageSummary = summary
                ? (summary.image_calls > 0
                    ? (numberFormat(summary.image_calls) + ' (' + numberFormat(summary.featured_images) + ' featured + ' + numberFormat(summary.internal_images) + ' internal)')
                    : '0')
                : '0';
            var modelSummary = summary && summary.models.length
                ? summary.models.map(function (model) {
                    return '<code>' + escapeHtml(model) + '</code>';
                }).join(', ')
                : '-';

            var tokenMetricsApplicable = !(String(row.type || '') === 'image') && row.token_metrics_applicable !== false;
            var tokenInText = tokenMetricsApplicable ? numberFormat(row.tokens_in) : 'N/A';
            var tokenOutText = tokenMetricsApplicable ? numberFormat(row.tokens_out) : 'N/A';
            var tokenTotalText = tokenMetricsApplicable ? numberFormat(row.tokens_total) : 'N/A';

            var applicabilityNote = tokenMetricsApplicable
                ? ''
                : '<div class="cbia-usage-detail-note">Image APIs do not provide reliable token breakdowns. This view compares only cost and section.</div>';

            detailPanel.innerHTML = ''
                + '<div class="cbia-usage-detail-card">'
                + '  <div>'
                + '    <h3>' + escapeHtml((summary && summary.post_title) || row.post_title || 'Post summary') + '</h3>'
                + '    <div class="cbia-usage-detail-subtitle">Real post summary for the current filter. Aggregates text, images, and billable failures.</div>'
                + '  </div>'
                + '  <div class="cbia-usage-detail-stats">'
                + '    <div class="cbia-usage-detail-stat"><span>Input</span><strong>' + summaryTokenIn + '</strong></div>'
                + '    <div class="cbia-usage-detail-stat"><span>Output</span><strong>' + summaryTokenOut + '</strong></div>'
                + '    <div class="cbia-usage-detail-stat"><span>Total</span><strong>' + summaryTokenTotal + '</strong></div>'
                + '    <div class="cbia-usage-detail-stat"><span>Total cost</span><strong>' + (summary ? currencyFormat(eurToUsd(summary.total_cost)) : '-') + '</strong></div>'
                + '  </div>'
                + '  <div class="cbia-usage-detail-meta">'
                + '    <div class="cbia-usage-detail-row"><div class="cbia-usage-detail-label">Last activity</div><div class="cbia-usage-detail-value">' + escapeHtml(formatDateTime((summary && summary.latest_ts) || row.ts)) + '</div></div>'
                + '    <div class="cbia-usage-detail-row"><div class="cbia-usage-detail-label">User</div><div class="cbia-usage-detail-value">' + escapeHtml((summary && summary.latest_user_name) || row.user_name) + '</div></div>'
                + '    <div class="cbia-usage-detail-row"><div class="cbia-usage-detail-label">Types</div><div class="cbia-usage-detail-value">' + escapeHtml(summaryTypes) + '</div></div>'
                + '    <div class="cbia-usage-detail-row"><div class="cbia-usage-detail-label">Models used</div><div class="cbia-usage-detail-value">' + modelSummary + '</div></div>'
                + '    <div class="cbia-usage-detail-row"><div class="cbia-usage-detail-label">Images</div><div class="cbia-usage-detail-value">' + escapeHtml(imageSummary) + '</div></div>'
                + '    <div class="cbia-usage-detail-row"><div class="cbia-usage-detail-label">Events</div><div class="cbia-usage-detail-value">' + numberFormat(summary.call_count) + ' total · ' + numberFormat(summary.ok_count) + ' OK · ' + numberFormat(summary.fail_count) + ' failed</div></div>'
                + '    <div class="cbia-usage-detail-row"><div class="cbia-usage-detail-label">Billable failures</div><div class="cbia-usage-detail-value">' + numberFormat(summary.billable_fail_count) + '</div></div>'
                + '  </div>'
                + '  <div class="cbia-usage-detail-separator"></div>'
                + '  <div class="cbia-usage-detail-subtitle">Selected event</div>'
                + '  <div class="cbia-usage-detail-stats">'
                + '    <div class="cbia-usage-detail-stat"><span>Input</span><strong>' + tokenInText + '</strong></div>'
                + '    <div class="cbia-usage-detail-stat"><span>Output</span><strong>' + tokenOutText + '</strong></div>'
                + '    <div class="cbia-usage-detail-stat"><span>Total</span><strong>' + tokenTotalText + '</strong></div>'
                + '    <div class="cbia-usage-detail-stat"><span>Event cost</span><strong>' + (hasNumericValue(row.cost_eur) ? currencyFormat(eurToUsd(row.cost_eur)) : '-') + '</strong></div>'
                + '  </div>'
                + '  <div class="cbia-usage-detail-meta">'
                + '    <div class="cbia-usage-detail-row"><div class="cbia-usage-detail-label">Date</div><div class="cbia-usage-detail-value">' + escapeHtml(formatDateTime(row.ts)) + '</div></div>'
                + '    <div class="cbia-usage-detail-row"><div class="cbia-usage-detail-label">Model</div><div class="cbia-usage-detail-value"><code>' + escapeHtml(row.model) + '</code></div></div>'
                + '    <div class="cbia-usage-detail-row"><div class="cbia-usage-detail-label">Type</div><div class="cbia-usage-detail-value"><span class="cbia-usage-type-badge type-' + escapeHtml(row.type) + '">' + escapeHtml(row.type_label) + '</span></div></div>'
                + '    <div class="cbia-usage-detail-row"><div class="cbia-usage-detail-label">Section</div><div class="cbia-usage-detail-value">' + escapeHtml(row.section_detail || row.section_label || '-') + '</div></div>'
                + '    <div class="cbia-usage-detail-row"><div class="cbia-usage-detail-label">Status</div><div class="cbia-usage-detail-value"><span class="cbia-usage-status-badge status-' + (row.ok ? 'ok' : 'error') + '">' + escapeHtml(row.status_label) + '</span></div></div>'
                + '    <div class="cbia-usage-detail-row"><div class="cbia-usage-detail-label">Summary</div><div class="cbia-usage-detail-value">' + escapeHtml(row.message_preview || '-') + '</div></div>'
                + '  </div>'
                + applicabilityNote
                + actions
                + '</div>';
        }

        function renderTable(filtered) {
            if (!tableBody) return;
            var periodInfo = getPeriodInfo();
            var periodLabel = periodInfo.label;

            if (tableMeta) {
                if (rowsLimited) {
                    tableMeta.textContent = periodLabel + ' · Quick table: showing the ' + numberFormat(Math.min(rows.length, recentRowsLimit)) + ' most recent events out of ' + numberFormat(totalRows) + '. KPIs and charts still use the full current period.';
                } else {
                    tableMeta.textContent = periodLabel + ' · Showing ' + numberFormat(filtered.length) + ' event(s) in the current period.';
                }
            }

            if (!filtered.length) {
                allFilteredRows = [];
                tableBody.innerHTML = '<tr><td colspan="7" class="cbia-usage-table-placeholder">No logs found for this filter.</td></tr>';
                renderDetail(null, []);
                return;
            }

            var displayRows = filtered.slice(0, 14);
            allFilteredRows = filtered.slice();
            tableBody.innerHTML = displayRows.map(function (row) {
                var activeClass = rowKey(row) === selectedKey ? ' is-active' : '';
                var tokenMetricsApplicable = !(String(row.type || '') === 'image') && row.token_metrics_applicable !== false;
                var typeLabel = String(row.type_label || '');
                if (row.section_detail || row.section_label) {
                    typeLabel += ' · ' + String(row.section_detail || row.section_label || '');
                }
                typeLabel = String(typeLabel || '').replace(/\s*Â·\s*/g, ' · ').replace(/\s*·\s*/g, ' · ');
                return ''
                    + '<tr class="' + activeClass + '" data-row-key="' + escapeHtml(rowKey(row)) + '">'
                    + '  <td><span class="cbia-usage-date">' + escapeHtml(formatDateTime(row.ts)) + '</span></td>'
                    + '  <td>' + escapeHtml(row.user_name || '-') + '</td>'
                    + '  <td><div class="cbia-usage-source"><span class="cbia-usage-source-title">' + escapeHtml(row.post_title || '-') + '</span><span class="cbia-usage-source-meta">#' + escapeHtml(String(row.post_id || 0)) + '</span></div></td>'
                    + '  <td><span class="cbia-usage-type-badge type-' + escapeHtml(row.type) + '">' + escapeHtml(typeLabel) + '</span></td>'
                    + '  <td><span class="cbia-usage-metric">' + (tokenMetricsApplicable ? numberFormat(row.tokens_total) : 'N/A') + '</span></td>'
                    + '  <td><span class="cbia-usage-cost">' + (hasNumericValue(row.cost_eur) ? currencyFormat(eurToUsd(row.cost_eur)) : '-') + '</span></td>'
                    + '  <td><code class="cbia-usage-model-code">' + escapeHtml(row.model || '-') + '</code></td>'
                    + '</tr>';
            }).join('');

            if (!selectedKey || !displayRows.some(function (row) { return rowKey(row) === selectedKey; })) {
                selectedKey = rowKey(displayRows[0]);
            }

            Array.prototype.slice.call(tableBody.querySelectorAll('tr[data-row-key]')).forEach(function (tr) {
                tr.addEventListener('click', function () {
                    selectedKey = tr.getAttribute('data-row-key') || '';
                    Array.prototype.slice.call(tableBody.querySelectorAll('tr[data-row-key]')).forEach(function (node) {
                        node.classList.toggle('is-active', node === tr);
                    });
                    var selected = allFilteredRows.find(function (row) {
                        return rowKey(row) === selectedKey;
                    }) || null;
                    renderDetail(selected, allFilteredRows);
                });
            });

            var selectedRow = allFilteredRows.find(function (row) {
                return rowKey(row) === selectedKey;
            }) || displayRows[0];

            Array.prototype.slice.call(tableBody.querySelectorAll('tr[data-row-key]')).forEach(function (node) {
                node.classList.toggle('is-active', node.getAttribute('data-row-key') === rowKey(selectedRow));
            });
            renderDetail(selectedRow, allFilteredRows);
        }

        function refresh() {
            updateExportLink();
            updateChartHints();
            var filtered = getFilteredRows();
            renderKpis(filtered);
            renderActivityChart(filtered);
            renderTypeChart(filtered);
            renderMonthlyChart();
            renderTable(filtered);
        }

        if (modelSelect) {
            modelSelect.addEventListener('change', function () {
                refresh();
            });
        }

        if (typeSelect) {
            typeSelect.addEventListener('change', refresh);
        }

        if (searchInput) {
            searchInput.addEventListener('input', refresh);
        }

        if (daysSelect && periodForm) {
            daysSelect.addEventListener('change', function () {
                updateExportLink();
                periodForm.submit();
            });
        }

        window.addEventListener('resize', function () {
            if (!loadingRemote) {
                refresh();
            }
        });

        populateModelOptions(modelOptions);
        updateExportLink();
        if (lazyLoad) {
            setDashboardLoading(true, t('loadingData', 'Loading real usage data...'), t('loadingHint', 'Charts and table will fill in automatically in a few seconds.'));
            loadUsageData();
        } else {
            setDashboardLoading(false);
            refresh();
        }
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
                if (e.target === modal) closeComposerModal();
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
            if (profile !== 'seo_balanced' && profile !== 'how_to' && profile !== 'discover_editorial') {
                profile = 'discover_editorial';
            }
            return profile;
        }

        function getPromptProfileLabel() {
            var profile = getPromptProfileValue();
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
                    insertInEditor(title, finalHtml, finalFocusKeyphrase || title, finalMetaDescription, finalCategoryIds, finalTagIds);
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
            if (keyInput) keyInput.focus();
        }

        function closeKeyModal() {
            if (!keyModal) return;
            keyModal.style.display = 'none';
        }

        function saveKeyFromModal() {
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

        function persistTermsForPost(postId, cats, tags, attempt) {
            var pid = parseInt(postId || 0, 10);
            var tries = parseInt(attempt || 0, 10);
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
                            resolve(persistTermsForPost(resolveCurrentPostId(), cats, tags, tries + 1));
                        }, 1000);
                    });
                }
                return Promise.resolve(false);
            }
            var params = new URLSearchParams();
            params.append('action', 'cbia_ai_composer_apply_terms');
            params.append('_ajax_nonce', nonce);
            params.append('post_id', String(pid));
            (Array.isArray(cats) ? cats : []).forEach(function (id) {
                var v = parseInt(id, 10) || 0;
                if (v > 0) params.append('category_ids[]', String(v));
            });
            (Array.isArray(tags) ? tags : []).forEach(function (id) {
                var v = parseInt(id, 10) || 0;
                if (v > 0) params.append('tag_ids[]', String(v));
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
                              resolve(persistTermsForPost(pid, cats, tags, tries + 1));
                          }, 1000);
                      });
                  }
                  return false;
              })
              .catch(function () {
                  if (tries < 20) {
                      return new Promise(function (resolve) {
                          setTimeout(function () {
                              resolve(persistTermsForPost(pid, cats, tags, tries + 1));
                          }, 1000);
                      });
                  }
                  return false;
              });
        }

        function ensureTermsPersisted(cats, tags, postIdOverride) {
            var arrCats = Array.isArray(cats) ? cats : [];
            var arrTags = Array.isArray(tags) ? tags : [];
            if (!arrCats.length && !arrTags.length) return;
            var pid = parseInt(postIdOverride || 0, 10) || resolveCurrentPostId();
            persistTermsForPost(pid, arrCats, arrTags);
            setTimeout(function () { persistTermsForPost(parseInt(postIdOverride || 0, 10) || resolveCurrentPostId(), arrCats, arrTags); }, 2500);
            setTimeout(function () { persistTermsForPost(parseInt(postIdOverride || 0, 10) || resolveCurrentPostId(), arrCats, arrTags); }, 6000);
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

        function insertInEditor(title, html, focusKeyphrase, metaDescription, categoryIds, tagIds, tagNames) {
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
                    window.wp.data.dispatch('core/editor').editPost(patch);
                    try {
                        if (window.wp.blocks && window.wp.blocks.parse && window.wp.data && window.wp.data.dispatch) {
                            window.wp.data.dispatch('core/block-editor').resetBlocks(window.wp.blocks.parse(content));
                        }
                    } catch (eReset) {}
                    forceSetContentEverywhere();
                    updateYoastUiFields(yoastPayload);
                    try { ensureYoastMetaPersisted(yoastPayload); } catch (e2) {}
                    try { ensureTermsPersisted(cats, tags); } catch (e3) {}
                    return true;
                } catch (e) {}
            }

            var titleField = document.getElementById('title');
            if (titleField) titleField.value = postTitle;

            if (window.tinyMCE && window.tinyMCE.get('content')) {
                try {
                    window.tinyMCE.get('content').setContent(content);
                    return true;
                } catch (e) {}
            }

            var classic = document.getElementById('content');
            if (classic) {
                classic.value = content;
                triggerInputSync(classic);
                // Yoast classic fields (best-effort).
                updateYoastUiFields(yoastPayload);
                // Classic taxonomy fields (best-effort).
                if (cats.length) {
                    cats.forEach(function (id) {
                        var cb = document.querySelector('#categorychecklist input[type=\"checkbox\"][value=\"' + id + '\"]');
                        if (cb) cb.checked = true;
                    });
                }
                if (tagLabels.length) {
                    var tagsInput = document.getElementById('tax-input-post_tag');
                    if (tagsInput) {
                        tagsInput.value = tagLabels.join(', ');
                        triggerInputSync(tagsInput);
                    }
                }
                ensureYoastMetaPersisted(yoastPayload);
                ensureTermsPersisted(cats, tags);
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
                        setStatus((err && err.message) ? err.message : 'Could not validate configured API.', true);
                    });
        }

        function applyFullPostAtomic(payload) {
                var params = new URLSearchParams();
                params.append('action', 'cbia_ai_composer_apply_full_post');
                params.append('_ajax_nonce', nonce);
                params.append('post_id', String(resolveCurrentPostId() || 0));
                params.append('title', String(payload.title || ''));
                params.append('content_html', String(payload.content_html || ''));
                params.append('focus_keyphrase', String(payload.focus_keyphrase || ''));
                params.append('meta_description', String(payload.meta_description || ''));
                params.append('featured_attach_id', String(payload.featured_attach_id || 0));
                params.append('internal_image_style', String(payload.internal_image_style || ''));
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
                applyFullPostAtomic({
                    title: titleForApply,
                    content_html: htmlCandidate,
                    focus_keyphrase: focusForApply,
                    meta_description: metadForApply,
                    category_ids: finalCategoryIds,
                    tag_ids: finalTagIds,
                    tag_names: finalTagNames,
                    featured_attach_id: finalFeaturedAttachId
                    ,internal_image_style: modeForApply
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
                    finalCategoryIds = appliedCats.slice();
                    finalTagIds = appliedTags.slice();
                    finalTagNames = appliedTagNames.slice();
                    finalFocusKeyphrase = focusForApply;
                    finalMetaDescription = metadForApply;
                    // Keep editor UI in sync after server-side atomic write.
                    syncEditorTitle(titleForApply);
                    // Classic editor can re-render title late; enforce once more from backend-applied title.
                    setTimeout(function () { syncEditorTitle(titleForApply); }, 80);
                    setTimeout(function () { syncEditorTitle(titleForApply); }, 250);
                    setTimeout(function () { syncEditorTitle(titleForApply); }, 1000);
                    setTimeout(function () { syncEditorTitle(titleForApply); }, 2200);
                    // Secondary persistence pass (Yoast + terms) for editor/plugin stacks that lag.
                    try {
                        ensureTermsPersisted(appliedCats, appliedTags, appliedPostId);
                    } catch (eTerms) {}
                    try {
                        ensureYoastMetaPersisted({
                            focus_keyphrase: focusForApply,
                            meta_description: metadForApply,
                            seo_title: titleForApply,
                            og_title: titleForApply,
                            og_description: metadForApply,
                            tw_title: titleForApply,
                            tw_description: metadForApply,
                            primary_category: appliedCats.length ? appliedCats[0] : 0
                        }, appliedPostId);
                    } catch (eYoast) {}
                    persistComposerSnapshot();
                    setStatus('Contenido aplicado en servidor.', false);
                    try { insertInEditor(titleForApply, htmlCandidate, focusForApply, metadForApply, appliedCats, appliedTags, appliedTagNames); } catch (eInsertUi) {}
                    setStatus('Content inserted into editor.', false);
                    closeComposerModal();
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
            }
        });

        // Extra fallback for editors/plugins that stop bubbling on button clicks.
        document.addEventListener('click', function (e) {
            var target = e.target && e.target.closest ? e.target.closest('#cbia-ai-generate, #cbia-ai-improve-text, #cbia-ai-insert, #cbia-ai-complete-missing') : null;
            if (!target || !root.contains(target)) return;
            e.preventDefault();
            if (target.id === 'cbia-ai-generate') {
                onGenerateClick();
            } else if (target.id === 'cbia-ai-improve-text') {
                onImproveTextClick();
            } else if (target.id === 'cbia-ai-insert') {
                onInsertClick();
            } else if (target.id === 'cbia-ai-complete-missing') {
                onCompleteMissingClick();
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
        safeInit(initAiComposer);
    }
})();


