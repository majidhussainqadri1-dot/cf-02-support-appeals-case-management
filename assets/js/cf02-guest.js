(function () {
    'use strict';

    // Encrypted, low-sensitivity guest pre-intake only. Never place the token
    // in a URL, cookie, localStorage, service worker cache, or an analytics event.
    const STORAGE_KEY = 'cf02_guest_continuation_v1';

    function readPending() {
        try {
            const raw = window.sessionStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            const pending = JSON.parse(raw);
            if (!pending || typeof pending.token !== 'string' ||
                !Number.isFinite(pending.expires) || Date.now() >= pending.expires ||
                !pending.token.startsWith('CF02G1.')) {
                window.sessionStorage.removeItem(STORAGE_KEY);
                return null;
            }
            return pending;
        } catch (_) {
            return null;
        }
    }

    function clearPending() {
        try { window.sessionStorage.removeItem(STORAGE_KEY); } catch (_) {}
    }

    function writePending(token, expiresAt) {
        const expiry = Date.parse(expiresAt);
        if (!token.startsWith('CF02G1.') || !Number.isFinite(expiry) ||
            expiry <= Date.now() || expiry > Date.now() + 15 * 60 * 1000) {
            return false;
        }
        try {
            window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ token: token, expires: expiry }));
            return true;
        } catch (_) {
            return false;
        }
    }

    async function post(endpoint, payload, nonce) {
        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        if (nonce) headers['X-WP-Nonce'] = nonce;
        const result = await fetch(endpoint, {
            method: 'POST', credentials: 'same-origin', cache: 'no-store',
            headers: headers, body: JSON.stringify(payload)
        });
        const body = await result.json().catch(() => ({}));
        if (!result.ok) {
            throw new Error(typeof body.message === 'string' ? body.message : 'Request could not be completed.');
        }
        return body;
    }

    document.querySelectorAll('[data-cf02-guest]').forEach(function (form) {
        const status = form.parentElement.querySelector('[data-cf02-guest-status]');
        const login = form.parentElement.querySelector('[data-cf02-guest-login]');
        const submit = form.querySelector('[type="submit"]');
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (!form.reportValidity()) return;
            if (!navigator.onLine) {
                status.textContent = form.dataset.offlineText || 'Please reconnect before submitting.';
                status.dataset.state = 'error';
                return;
            }
            submit.disabled = true;
            status.textContent = form.dataset.pendingText || 'Preparing a safe request…';
            status.dataset.state = 'info';
            const fd = new FormData(form);
            const fields = ['category', 'subject', 'description', 'impact', 'urgency', 'locale'];
            const payload = {};
            fields.forEach(function (key) { payload[key] = String(fd.get(key) || ''); });
            try {
                const response = await post(form.dataset.endpoint, payload, '');
                if (response.case_created !== false || response.status !== 'step_up_required') {
                    throw new Error(form.dataset.unavailableText || 'This request needs a different support route.');
                }
                const saved = writePending(String(response.continuation_token || ''), response.expires_at);
                status.textContent = saved
                    ? (form.dataset.readyText || 'Request prepared. Sign in in this tab within 10 minutes to complete it. No case has been created yet.')
                    : (form.dataset.storageText || 'Your browser does not support this secure continuation. Sign in and submit a new case.');
                status.dataset.state = saved ? 'success' : 'error';
                if (login) login.focus();
            } catch (error) {
                status.textContent = error.message || form.dataset.errorText || 'Request could not be prepared.';
                status.dataset.state = 'error';
            } finally {
                submit.disabled = false;
            }
        });
    });

    document.querySelectorAll('[data-cf02-complete]').forEach(function (root) {
        const pending = readPending();
        if (!pending) return;
        const panel = document.createElement('section');
        panel.className = 'cf02-card cf02-guest-resume';
        panel.setAttribute('role', 'region');
        panel.setAttribute('aria-label', root.dataset.guestResumeTitle || 'Continue previous support request');

        const title = document.createElement('h2');
        title.textContent = root.dataset.guestResumeTitle || 'Continue previous support request';
        const description = document.createElement('p');
        description.textContent = root.dataset.guestResumeDescription ||
            'An earlier low-sensitivity request is ready to submit after sign-in. No case exists until you confirm.';
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = root.dataset.guestResumeAction || 'Create this support case';
        const dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'cf02-button-muted';
        dismiss.textContent = root.dataset.guestResumeDismiss || 'Discard';
        const feedback = document.createElement('p');
        feedback.setAttribute('role', 'status');
        feedback.setAttribute('aria-live', 'polite');
        const actions = document.createElement('div');
        actions.className = 'cf02-actions';
        actions.append(button, dismiss);
        panel.append(title, description, actions, feedback);
        root.prepend(panel);

        dismiss.addEventListener('click', function () {
            clearPending();
            panel.remove();
        });
        button.addEventListener('click', async function () {
            button.disabled = true;
            feedback.textContent = root.dataset.guestResumeWorking || 'Submitting…';
            try {
                const result = await post(
                    String(root.dataset.endpoint || '').replace(/\/$/, '') + '/guest/continue',
                    { continuation_token: pending.token },
                    root.dataset.nonce || ''
                );
                if (!result.continuation_completed || !result.case) {
                    throw new Error(root.dataset.guestResumeFailed || 'The case could not be created.');
                }
                clearPending();
                feedback.textContent = root.dataset.guestResumeSuccess || 'Support case created.';
                const casesTab = root.querySelector('[data-tab="cases"]');
                if (casesTab) casesTab.click();
                button.remove();
                dismiss.remove();
            } catch (error) {
                if (!readPending()) panel.remove();
                else {
                    feedback.textContent = error.message || root.dataset.guestResumeFailed || 'Unable to continue.';
                    button.disabled = false;
                }
            }
        });
    });
}());
