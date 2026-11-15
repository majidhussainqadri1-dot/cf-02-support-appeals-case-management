(function () {
    'use strict';
    const root = document.querySelector('[data-cf02-admin]');
    if (!root) return;
    const base = String(root.dataset.endpoint || '').replace(/\/$/, '');
    const nonce = String(root.dataset.nonce || '');
    const output = root.querySelector('[data-output]');
    const status = root.querySelector('[data-status]');
    const tabs = Array.from(root.querySelectorAll('[data-viewkey]'));
    const terms = JSON.parse(root.dataset.i18n || '{}');
    const t = (key, fallback) => typeof terms[key] === 'string' && terms[key] ? terms[key] : fallback;
    let currentRequest = 0;

    function element(tag, text, className) {
        const el = document.createElement(tag);
        if (text !== null) el.textContent = String(text);
        if (className) el.className = className;
        return el;
    }

    async function api(path, options) {
        const input = options || {};
        const response = await fetch(base + path, {
            credentials: 'same-origin', cache: 'no-store',
            ...input,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
                'X-CF02-Purpose': 'support_operations',
                ...(input.headers || {})
            }
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(typeof data.message === 'string' ? data.message : t('failed', 'Request failed.'));
        return data;
    }

    function table(items) {
        const view = element('div', null, 'cf02-admin-table-scroll');
        const tableElement = element('table', null, 'widefat striped');
        const thead = element('thead', null);
        const tbody = element('tbody', null);
        const keys = items.length ? Object.keys(items[0]).slice(0, 8) : [];
        const heading = element('tr', null);
        keys.forEach(function (key) {
            const th = element('th', key);
            th.scope = 'col';
            heading.append(th);
        });
        thead.append(heading);
        items.forEach(function (item) {
            const tr = element('tr', null);
            keys.forEach(function (key) { tr.append(element('td', item[key] ?? '')); });
            tbody.append(tr);
        });
        tableElement.append(thead, tbody);
        view.append(tableElement);
        return view;
    }

    function governedRepair() {
        const form = element('form', null, 'cf02-repair-form');
        const label = element('label', t('repairReference', 'Approved repair reference'));
        const input = document.createElement('input');
        input.name = 'approval_ref';
        input.required = true;
        input.pattern = 'CF02-REPAIR-[A-Za-z0-9_-]{8,64}';
        input.setAttribute('autocomplete', 'off');
        const button = element('button', t('runRepair', 'Run governed repair'), 'button button-primary');
        button.type = 'submit';
        label.append(input);
        form.append(label, button);
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (!form.reportValidity() || !window.confirm(t('confirmRepair', 'Run the approved non-destructive repair now?'))) return;
            button.disabled = true;
            try {
                const value = await api('/staff/repair/run', {
                    method: 'POST', body: JSON.stringify({ approval_ref: input.value })
                });
                output.replaceChildren(element('pre', JSON.stringify(value, null, 2)));
                status.textContent = t('repairResult', 'Repair command returned. Verify server evidence.');
            } catch (error) {
                status.textContent = error.message || t('failed', 'Request failed.');
            } finally {
                button.disabled = false;
            }
        });
        return form;
    }

    async function load(key) {
        const serial = ++currentRequest;
        status.textContent = t('loading', 'Loading…');
        tabs.forEach(function (tab) {
            tab.setAttribute('aria-pressed', tab.dataset.viewkey === key ? 'true' : 'false');
            tab.disabled = true;
        });
        try {
            let data;
            switch (key) {
                case 'queue': data = await api('/staff/queue?limit=50'); break;
                case 'search': data = await api('/staff/cases/search?limit=50'); break;
                case 'appeals': data = await api('/staff/appeals?limit=50'); break;
                case 'sla':
                    data = {
                        at_risk: await api('/staff/sla/at-risk'),
                        metrics: await api('/staff/metrics/sla'),
                        backlog: await api('/staff/metrics/backlog')
                    };
                    break;
                case 'quality': data = await api('/staff/metrics/quality'); break;
                case 'retention': data = await api('/staff/retention/due'); break;
                case 'repair': data = await api('/staff/repair/inspect'); break;
                default: throw new Error(t('failed', 'Unknown support section.'));
            }
            if (serial !== currentRequest) return;
            output.replaceChildren(Array.isArray(data?.items)
                ? table(data.items)
                : element('pre', JSON.stringify(data, null, 2)));
            if (key === 'repair') output.append(governedRepair());
            status.textContent = t('ready', 'Ready');
        } catch (error) {
            if (serial === currentRequest) status.textContent = error.message || t('failed', 'Request failed.');
        } finally {
            if (serial === currentRequest) tabs.forEach(function (tab) { tab.disabled = false; });
        }
    }

    tabs.forEach(function (button) {
        button.addEventListener('click', function () { load(button.dataset.viewkey); });
    });
    load('queue');
}());
