'use strict';

const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const SCRIPT = fs.readFileSync(path.join(__dirname, '..', 'assets/js/cf02-admin.js'), 'utf8');

function el(tag) {
    return {
        tag,
        children: [],
        listeners: {},
        dataset: {},
        disabled: false,
        textContent: '',
        className: '',
        value: '',
        setAttribute(name, value) { this[name] = value; },
        append(...nodes) { this.children.push(...nodes); },
        replaceChildren(...nodes) { this.children = nodes; },
        addEventListener(name, callback) { this.listeners[name] = callback; },
        reportValidity() { return true; }
    };
}

async function flush() {
    await new Promise(resolve => setTimeout(resolve, 0));
}

function boot({ authorized = true, confirmed = false, dataByPath = {} } = {}) {
    const requests = [];
    const output = el('section');
    const status = el('div');
    const buttons = ['queue', 'search', 'appeals', 'sla', 'quality', 'retention', 'repair'].map(key => {
        const button = el('button');
        button.dataset.viewkey = key;
        return button;
    });
    const root = el('div');
    root.dataset = { endpoint: '/wp-json/api/support/v1/', nonce: 'nonce', i18n: '{"ready":"READY"}' };
    root.querySelector = key => key === '[data-output]' ? output : key === '[data-status]' ? status : null;
    root.querySelectorAll = key => key === '[data-viewkey]' ? buttons : [];
    const document = {
        querySelector: key => authorized && key === '[data-cf02-admin]' ? root : null,
        createElement: el
    };
    const context = {
        document,
        window: { confirm: () => confirmed },
        fetch: async (url, options) => {
            requests.push({ url, options });
            return { ok: true, json: async () => dataByPath[url] ?? { items: [] } };
        },
        JSON, String, Object, Array
    };
    vm.runInNewContext(SCRIPT, context, { filename: 'cf02-admin.js' });
    return { requests, output, status, buttons, root };
}

test('admin JS does nothing without the capability-rendered surface', async () => {
    const ui = boot({ authorized: false });
    await flush();
    assert.equal(ui.requests.length, 0);
});

test('admin reads scoped queue with nonce and renders escaped text nodes', async () => {
    const ui = boot({
        dataByPath: {
            '/wp-json/api/support/v1/staff/queue?limit=50': { items: [{ subject: '<img onerror=alert(1)>', priority: 'P2' }] }
        }
    });
    await flush();
    assert.equal(ui.requests.length, 1);
    assert.equal(ui.requests[0].url, '/wp-json/api/support/v1/staff/queue?limit=50');
    assert.equal(ui.requests[0].options.headers['X-WP-Nonce'], 'nonce');
    assert.equal(ui.requests[0].options.headers['X-CF02-Purpose'], 'support_operations');
    assert.equal(ui.requests[0].options.cache, 'no-store');
    const scroll = ui.output.children[0];
    const table = scroll.children[0];
    const row = table.children[1].children[0];
    assert.equal(row.children[0].textContent, '<img onerror=alert(1)>');
    assert.equal(ui.status.textContent, 'READY');
});

test('governed repair cannot submit unless user explicitly confirms', async () => {
    const ui = boot({ confirmed: false });
    await flush();
    const repair = ui.buttons.find(b => b.dataset.viewkey === 'repair');
    repair.listeners.click();
    await flush();
    const form = ui.output.children.find(c => c.tag === 'form');
    assert.ok(form);
    const input = form.children[0].children[0];
    input.value = 'CF02-REPAIR-12345678';
    await form.listeners.submit({ preventDefault() {} });
    assert.ok(ui.requests.some(x => x.url.endsWith('/staff/repair/inspect')));
    assert.ok(!ui.requests.some(x => x.url.endsWith('/staff/repair/run')));
});

test('governed repair sends explicit reference only after confirmation', async () => {
    const ui = boot({
        confirmed: true,
        dataByPath: { '/wp-json/api/support/v1/staff/repair/run': { status: 'accepted' } }
    });
    await flush();
    ui.buttons.find(b => b.dataset.viewkey === 'repair').listeners.click();
    await flush();
    const form = ui.output.children.find(c => c.tag === 'form');
    form.children[0].children[0].value = 'CF02-REPAIR-12345678';
    await form.listeners.submit({ preventDefault() {} });
    const result = ui.requests.find(x => x.url.endsWith('/staff/repair/run'));
    assert.ok(result);
    assert.deepEqual(JSON.parse(result.options.body), { approval_ref: 'CF02-REPAIR-12345678' });
    assert.equal(result.options.headers['X-CF02-Purpose'], 'support_operations');
});
