(function () {
    'use strict';

    const iconPaths = {
        plus: '<path d="M12 5v14M5 12h14"/>',
        cases: '<path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h8M8 17h5"/>',
        appeal: '<path d="M4 12a8 8 0 1 0 2.3-5.7"/><path d="M4 4v6h6"/>',
        open: '<path d="M3 12s3.5-6 9-6 9 6 9 6-3.5 6-9 6-9-6-9-6z"/><circle cx="12" cy="12" r="2.5"/>',
        refresh: '<path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/>',
        send: '<path d="m3 3 18 9-18 9 4-9-4-9z"/><path d="M7 12h14"/>',
        upload: '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M5 20h14"/>',
        withdraw: '<path d="M5 12h14"/>',
        reopen: '<path d="M4 12a8 8 0 1 0 2.3-5.7"/><path d="M4 4v6h6"/>',
        feedback: '<path d="M4 4h16v12H8l-4 4z"/><path d="M8 8h8M8 12h5"/>',
        shield: '<path d="M12 3 5 6v5c0 4.8 3 8 7 10 4-2 7-5.2 7-10V6z"/><path d="m9 12 2 2 4-4"/>'
    };

    function init(root) {
        let strings = {};
        try {
            strings = JSON.parse(root.dataset.i18n || '{}');
        } catch (error) {
            strings = {};
        }

        const text = (key, fallback) => typeof strings[key] === 'string' && strings[key] !== '' ? strings[key] : fallback;
        const base = String(root.dataset.endpoint || '').replace(/\/$/, '');
        const nonce = String(root.dataset.nonce || '');
        const status = root.querySelector('.cf02-status');
        let cursor = null;

        function setStatus(message, state) {
            if (!status) return;
            status.textContent = message || '';
            status.dataset.state = state || 'info';
        }

        function icon(name) {
            const span = document.createElement('span');
            span.className = 'cf02-icon';
            span.setAttribute('aria-hidden', 'true');
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('viewBox', '0 0 24 24');
            svg.innerHTML = iconPaths[name] || iconPaths.shield;
            span.append(svg);
            return span;
        }

        function node(tag, value, className) {
            const element = document.createElement(tag);
            if (value !== null && value !== undefined) element.textContent = String(value);
            if (className) element.className = className;
            return element;
        }

        function button(label, iconName) {
            const element = document.createElement('button');
            element.type = 'button';
            element.append(icon(iconName), document.createTextNode(label));
            return element;
        }

        function uniqueId(prefix) {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                return prefix + ':' + window.crypto.randomUUID();
            }
            if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
                const bytes = new Uint8Array(16);
                window.crypto.getRandomValues(bytes);
                return prefix + ':' + Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
            }
            throw new Error(text('secureBrowserRequired', 'A secure modern browser is required for this action.'));
        }

        function errorMessage(error) {
            return error instanceof Error && error.message ? error.message : text('unexpectedError', 'An unexpected error occurred.');
        }

        async function withBusy(task) {
            root.setAttribute('aria-busy', 'true');
            const controls = Array.from(root.querySelectorAll('button, input[type="submit"]'));
            const states = controls.map(control => control.disabled);
            controls.forEach(control => { control.disabled = true; });
            try {
                return await task();
            } catch (error) {
                setStatus(errorMessage(error), 'error');
                throw error;
            } finally {
                controls.forEach((control, index) => { control.disabled = states[index]; });
                root.setAttribute('aria-busy', 'false');
            }
        }

        async function api(path, options) {
            if (!navigator.onLine) {
                throw new Error(text('offline', 'You are offline. Reconnect and retry.'));
            }
            const response = await fetch(base + path, {
                credentials: 'same-origin',
                ...options,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                    ...((options && options.headers) || {})
                }
            });
            let body = {};
            try {
                body = await response.json();
            } catch (error) {
                body = {};
            }
            if (!response.ok) {
                const trace = body && body.data && body.data.trace_id ? ' [' + body.data.trace_id + ']' : '';
                throw new Error((body.message || text('requestFailed', 'The request failed.')) + trace);
            }
            return body;
        }

        function field(labelText, control) {
            const label = document.createElement('label');
            label.append(document.createTextNode(labelText), control);
            return label;
        }

        function input(type, name, required) {
            const control = document.createElement('input');
            control.type = type;
            control.name = name;
            control.required = Boolean(required);
            return control;
        }

        function textarea(name, required) {
            const control = document.createElement('textarea');
            control.name = name;
            control.rows = 5;
            control.maxLength = 20000;
            control.required = Boolean(required);
            return control;
        }

        function select(name, values) {
            const control = document.createElement('select');
            control.name = name;
            values.forEach(value => {
                const option = document.createElement('option');
                option.value = String(value.value);
                option.textContent = String(value.label);
                control.append(option);
            });
            return control;
        }

        function focusPanel(panel) {
            const heading = panel.querySelector('[data-panel-heading]');
            if (heading) heading.focus({ preventScroll: false });
        }

        async function showPanel(name) {
            root.querySelectorAll('[role="tabpanel"]').forEach(panel => {
                panel.hidden = panel.dataset.panel !== name;
            });
            root.querySelectorAll('[role="tab"]').forEach(tab => {
                const selected = tab.dataset.tab === name;
                tab.setAttribute('aria-selected', selected ? 'true' : 'false');
                tab.tabIndex = selected ? 0 : -1;
            });
            const panel = root.querySelector('[data-panel="' + name + '"]');
            if (panel) focusPanel(panel);
            if (name === 'cases') await loadCases(true);
        }

        root.querySelectorAll('[role="tab"]').forEach(tab => {
            tab.addEventListener('click', () => {
                showPanel(tab.dataset.tab).catch(() => {});
            });
            tab.addEventListener('keydown', event => {
                if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
                const tabs = Array.from(root.querySelectorAll('[role="tab"]'));
                const current = tabs.indexOf(tab);
                const delta = event.key === 'ArrowRight' ? 1 : -1;
                const next = tabs[(current + delta + tabs.length) % tabs.length];
                next.focus();
                next.click();
            });
        });

        async function loadCases(reset) {
            return withBusy(async () => {
                setStatus(text('loading', 'Loading…'), 'info');
                if (reset) cursor = null;
                const query = new URLSearchParams({ limit: '25' });
                if (cursor) query.set('cursor', cursor);
                const result = await api('/cases?' + query.toString());
                const view = root.querySelector('[data-view="cases"]');
                if (!view) return;
                if (reset) view.replaceChildren();
                const oldMore = view.querySelector('[data-load-more]');
                if (oldMore) oldMore.remove();
                cursor = result.next_cursor || null;
                (result.items || []).forEach(caseItem => {
                    const article = node('article', null, 'cf02-case');
                    article.append(
                        node('h3', caseItem.safe_subject || caseItem.case_uuid),
                        node('p', caseItem.case_uuid + ' — ' + caseItem.state + ' — ' + caseItem.priority)
                    );
                    const openButton = button(text('open', 'Open'), 'open');
                    openButton.addEventListener('click', () => openCase(caseItem.case_uuid).catch(() => {}));
                    article.append(openButton);
                    view.append(article);
                });
                if (cursor) {
                    const more = button(text('loadMore', 'Load more'), 'cases');
                    more.dataset.loadMore = 'true';
                    more.addEventListener('click', () => loadCases(false).catch(() => {}));
                    view.append(more);
                }
                if (reset && !(result.items || []).length) {
                    view.append(node('p', text('noCases', 'No cases found.'), 'cf02-empty'));
                }
                setStatus('', 'info');
            });
        }

        function replyForm(caseId, caseVersion, refresh) {
            const form = node('form', null, 'cf02-form');
            const body = textarea('body', true);
            form.append(field(text('reply', 'Reply'), body));
            const submit = button(text('sendReply', 'Send reply'), 'send');
            submit.type = 'submit';
            form.append(submit);
            form.addEventListener('submit', event => {
                event.preventDefault();
                withBusy(async () => {
                    await api('/cases/' + encodeURIComponent(caseId) + '/messages', {
                        method: 'POST',
                        headers: { 'Idempotency-Key': uniqueId('reply') },
                        body: JSON.stringify({ body: body.value, channel: 'web', record_version: Number(caseVersion || 1) })
                    });
                    setStatus(text('replySent', 'Reply sent.'), 'success');
                    await refresh();
                }).catch(() => {});
            });
            return form;
        }

        function attachmentForm(caseId) {
            const form = node('form', null, 'cf02-form');
            form.append(node('h4', text('secureAttachment', 'Secure attachment')));
            const file = input('file', 'file', true);
            const purpose = input('text', 'purpose', true);
            purpose.value = 'case_evidence';
            const privacy = select('privacy', [
                { value: 'C3', label: 'C3' },
                { value: 'C4', label: 'C4' }
            ]);
            const consent = input('checkbox', 'consented', true);
            const consentLabel = node('label', null, 'cf02-check');
            consentLabel.append(consent, document.createTextNode(text('uploadConsent', 'I consent to this case-specific upload.')));
            form.append(
                field(text('file', 'File'), file),
                field(text('purpose', 'Purpose'), purpose),
                field(text('privacy', 'Privacy'), privacy),
                consentLabel
            );
            const submit = button(text('uploadQuarantine', 'Upload to quarantine'), 'upload');
            submit.type = 'submit';
            form.append(submit);
            form.addEventListener('submit', event => {
                event.preventDefault();
                withBusy(async () => {
                    const selected = file.files && file.files[0];
                    if (!selected) return;
                    if (!window.crypto || !window.crypto.subtle) {
                        throw new Error(text('secureBrowserRequired', 'A secure modern browser is required for this action.'));
                    }
                    const digest = await window.crypto.subtle.digest('SHA-256', await selected.arrayBuffer());
                    const hash = Array.from(new Uint8Array(digest), value => value.toString(16).padStart(2, '0')).join('');
                    const response = await api('/cases/' + encodeURIComponent(caseId) + '/attachments', {
                        method: 'POST',
                        headers: {
                            'Idempotency-Key': uniqueId('attachment'),
                            'X-CF02-Purpose': purpose.value
                        },
                        body: JSON.stringify({
                            mime_type: selected.type || 'application/octet-stream',
                            size: selected.size,
                            sha256: hash,
                            purpose: purpose.value,
                            privacy_class: privacy.value,
                            consented: consent.checked
                        })
                    });
                    if (response.upload_session && response.upload_session.upload_url) {
                        const upload = await fetch(response.upload_session.upload_url, {
                            method: 'PUT',
                            headers: response.upload_session.headers || {},
                            body: selected
                        });
                        if (!upload.ok) throw new Error(text('quarantineFailed', 'Quarantine upload failed.'));
                    }
                    setStatus(text('attachmentQuarantined', 'Attachment quarantined; scan pending.'), 'success');
                    form.reset();
                    purpose.value = 'case_evidence';
                }).catch(() => {});
            });
            return form;
        }

        function feedbackForm(caseId) {
            const form = node('form', null, 'cf02-form');
            form.append(node('h4', text('optionalFeedback', 'Optional feedback')));
            const rating = select('rating', [5, 4, 3, 2, 1].map(value => ({ value, label: String(value) })));
            const comment = textarea('comment', false);
            const optOut = input('checkbox', 'opted_out', false);
            const optOutLabel = node('label', null, 'cf02-check');
            optOutLabel.append(optOut, document.createTextNode(text('optOut', 'Opt out')));
            form.append(field(text('rating', 'Rating'), rating), field(text('comment', 'Comment'), comment), optOutLabel);
            const submit = button(text('submit', 'Submit'), 'feedback');
            submit.type = 'submit';
            form.append(submit);
            form.addEventListener('submit', event => {
                event.preventDefault();
                withBusy(async () => {
                    await api('/cases/' + encodeURIComponent(caseId) + '/feedback', {
                        method: 'POST',
                        body: JSON.stringify({ rating: Number(rating.value), comment: comment.value, opted_out: optOut.checked })
                    });
                    setStatus(text('feedbackRecorded', 'Feedback recorded.'), 'success');
                    form.reset();
                }).catch(() => {});
            });
            return form;
        }

        function appealForm(caseId) {
            const form = node('form', null, 'cf02-form');
            form.append(node('h4', text('submitAppeal', 'Submit appeal')));
            const decision = input('text', 'original_decision_ref', true);
            const policy = input('text', 'policy_version', true);
            const grounds = textarea('grounds', true);
            const evidence = textarea('evidence', false);
            form.append(
                field(text('decisionReference', 'Decision reference'), decision),
                field(text('policyVersion', 'Policy version'), policy),
                field(text('grounds', 'Grounds'), grounds),
                field(text('evidenceReferences', 'Evidence references'), evidence)
            );
            const submit = button(text('submitAppeal', 'Submit appeal'), 'appeal');
            submit.type = 'submit';
            form.append(submit);
            form.addEventListener('submit', event => {
                event.preventDefault();
                withBusy(async () => {
                    const evidenceRefs = evidence.value.split(/\n/).map(value => value.trim()).filter(Boolean);
                    const response = await api('/appeals', {
                        method: 'POST',
                        headers: { 'Idempotency-Key': uniqueId('appeal') },
                        body: JSON.stringify({
                            case_id: caseId,
                            original_decision_ref: decision.value,
                            policy_version: policy.value,
                            grounds: grounds.value,
                            evidence_refs: evidenceRefs
                        })
                    });
                    const id = response.appeal_uuid || (response.appeal && response.appeal.appeal_uuid) || text('accepted', 'accepted');
                    setStatus(text('appealSubmitted', 'Appeal submitted:') + ' ' + id, 'success');
                    form.reset();
                }).catch(() => {});
            });
            return form;
        }

        async function openCase(id) {
            return withBusy(async () => {
                setStatus(text('loading', 'Loading…'), 'info');
                const result = await api('/cases/' + encodeURIComponent(id));
                const view = root.querySelector('[data-view="case"]');
                if (!view) return;
                view.replaceChildren();
                const caseItem = result.case || {};
                const box = node('section', null, 'cf02-detail');
                box.tabIndex = -1;
                box.append(
                    node('h3', caseItem.safe_subject || id),
                    node('p', id + ' — ' + caseItem.state + ' — ' + caseItem.priority)
                );
                const thread = node('div', null, 'cf02-thread');
                (result.messages || []).forEach(message => {
                    const article = node('article', null, 'cf02-message');
                    article.append(
                        node('strong', message.author_ref === 'Support Team' ? text('supportTeam', 'Support Team') : text('requester', 'Requester')),
                        node('p', message.body || text('unavailable', '[Unavailable]'))
                    );
                    thread.append(article);
                });
                box.append(thread, replyForm(id, caseItem.record_version, () => openCase(id)), attachmentForm(id));
                const actions = node('div', null, 'cf02-actions');
                function action(label, path, iconName) {
                    const control = button(label, iconName);
                    control.addEventListener('click', () => {
                        withBusy(async () => {
                            await api('/cases/' + encodeURIComponent(id) + path, {
                                method: 'POST',
                                headers: {
                                    'Idempotency-Key': uniqueId(path.replace(/\W+/g, '')),
                                    'If-Match': '"' + Number(caseItem.record_version || 1) + '"'
                                },
                                body: JSON.stringify({ record_version: Number(caseItem.record_version || 1), reason: 'requester_action' })
                            });
                            await openCase(id);
                        }).catch(() => {});
                    });
                    actions.append(control);
                }
                if (!['closed', 'withdrawn'].includes(caseItem.state)) action(text('withdraw', 'Withdraw'), '/withdraw', 'withdraw');
                if (['resolved', 'closed'].includes(caseItem.state)) action(text('reopen', 'Reopen'), '/reopen', 'reopen');
                box.append(actions);
                if (['resolved', 'closed'].includes(caseItem.state)) box.append(feedbackForm(id), appealForm(id));
                view.append(box);
                box.focus();
                setStatus('', 'info');
            });
        }

        const caseForm = root.querySelector('[data-form="case"]');
        if (caseForm) {
            caseForm.addEventListener('submit', event => {
                event.preventDefault();
                withBusy(async () => {
                    const data = new FormData(caseForm);
                    const payload = Object.fromEntries(data.entries());
                    payload.diagnostics_consented = data.has('diagnostics_consented');
                    payload.locale = document.documentElement.lang || 'ur-PK';
                    if (payload.object_owner && payload.object_type && payload.object_ref && payload.object_version) {
                        payload.affected_object = {
                            owner: payload.object_owner,
                            type: payload.object_type,
                            ref: payload.object_ref,
                            version: payload.object_version,
                            privacy_class: 'C2',
                            safe_projection: {}
                        };
                    }
                    delete payload.object_owner;
                    delete payload.object_type;
                    delete payload.object_ref;
                    delete payload.object_version;
                    const response = await api('/cases', {
                        method: 'POST',
                        headers: { 'Idempotency-Key': uniqueId('intake') },
                        body: JSON.stringify(payload)
                    });
                    setStatus(text('caseAccepted', 'Case accepted:') + ' ' + response.case.case_uuid, 'success');
                    caseForm.reset();
                    await showPanel('cases');
                }).catch(() => {});
            });
        }

        const refresh = root.querySelector('[data-action="cases"]');
        if (refresh) refresh.addEventListener('click', () => loadCases(true).catch(() => {}));

        const appealLoad = root.querySelector('[data-form="appeal-load"]');
        if (appealLoad) {
            appealLoad.addEventListener('submit', event => {
                event.preventDefault();
                withBusy(async () => {
                    const id = String(new FormData(appealLoad).get('id') || '');
                    const response = await api('/appeals/' + encodeURIComponent(id));
                    const appeal = response.appeal || response;
                    const article = node('article', null, 'cf02-appeal');
                    article.append(
                        node('h3', appeal.appeal_uuid || id),
                        node('p', (appeal.state || 'unknown') + ' — ' + (appeal.outcome || 'pending')),
                        node('p', appeal.reasoned_summary || appeal.further_rights || text('statusAvailable', 'Status available.'))
                    );
                    const view = root.querySelector('[data-view="appeal"]');
                    if (view) view.replaceChildren(article);
                    setStatus('', 'info');
                }).catch(() => {});
            });
        }

        window.addEventListener('offline', () => setStatus(text('offline', 'You are offline. Reconnect and retry.'), 'error'));
        window.addEventListener('online', () => setStatus(text('online', 'Connection restored.'), 'success'));

        if (root.dataset.initialCase) {
            showPanel('cases').then(() => openCase(root.dataset.initialCase)).catch(() => {});
        } else if (root.dataset.initialAppeal) {
            showPanel('appeals').then(() => {
                if (appealLoad) {
                    appealLoad.elements.id.value = root.dataset.initialAppeal;
                    appealLoad.requestSubmit();
                }
            }).catch(() => {});
        } else {
            showPanel('new').catch(() => {});
        }
    }

    document.querySelectorAll('[data-cf02-complete]').forEach(init);
}());
