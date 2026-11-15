'use strict';

const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const SCRIPT = fs.readFileSync(path.join(__dirname, '..', 'assets/js/cf02-guest.js'), 'utf8');
const STORAGE_KEY = 'cf02_guest_continuation_v1';

function makeElement(tag = 'div') {
    return {
        tagName: tag,
        dataset: {},
        children: [],
        listeners: {},
        textContent: '',
        disabled: false,
        removed: false,
        setAttribute(name, value) { this[name] = value; },
        append(...nodes) { this.children.push(...nodes); },
        prepend(node) { this.children.unshift(node); },
        remove() { this.removed = true; },
        addEventListener(name, listener) { this.listeners[name] = listener; }
    };
}

function makeSessionStorage(initial = {}) {
    const values = new Map(Object.entries(initial));
    return {
        getItem(key) { return values.has(key) ? values.get(key) : null; },
        setItem(key, value) { values.set(key, String(value)); },
        removeItem(key) { values.delete(key); }
    };
}

function setupGuest(response) {
    const status = makeElement('p');
    const submit = makeElement('button');
    let loginFocused = false;
    const login = { focus() { loginFocused = true; } };
    const form = makeElement('form');
    form.dataset = {
        endpoint: '/wp-json/api/support/v1/guest/intake',
        readyText: 'READY_SAFE',
        emergencyText: 'DIVERT_TO_SPECIALIST',
        storageText: 'NO_SESSION_STORAGE'
    };
    form.parentElement = {
        querySelector(selector) {
            if (selector === '[data-cf02-guest-status]') return status;
            if (selector === '[data-cf02-guest-login]') return login;
            return null;
        }
    };
    form.querySelector = () => submit;
    form.reportValidity = () => true;
    const storage = makeSessionStorage();
    const network = [];
    const context = {
        window: { sessionStorage: storage },
        navigator: { onLine: true },
        document: {
            querySelectorAll(selector) {
                return selector === '[data-cf02-guest]' ? [form] : [];
            }
        },
        FormData: class {
            get(key) {
                return {
                    category: 'technical',
                    subject: 'A slow loading page',
                    description: 'Non-sensitive page issue',
                    impact: 'single_action',
                    urgency: 'normal',
                    locale: 'ur-PK'
                }[key] || null;
            }
        },
        fetch: async (url, options) => {
            network.push({ url, options });
            return {
                ok: response.ok ?? true,
                json: async () => response.body
            };
        },
        Date, JSON, Number, String
    };
    vm.runInNewContext(SCRIPT, context, { filename: 'cf02-guest.js' });
    return { form, status, submit, storage, network, get loginFocused() { return loginFocused; } };
}

test('guest submission prepares encrypted continuation without claiming a created case', async () => {
    const expires = new Date(Date.now() + 9 * 60_000).toISOString();
    const state = setupGuest({
        body: { status: 'step_up_required', case_created: false, continuation_token: 'CF02G1.mock-cipher', expires_at: expires }
    });
    await state.form.listeners.submit({ preventDefault() {} });
    const saved = JSON.parse(state.storage.getItem(STORAGE_KEY));
    assert.equal(saved.token, 'CF02G1.mock-cipher');
    assert.equal(state.status.textContent, 'READY_SAFE');
    assert.equal(state.status.dataset.state, 'success');
    assert.equal(state.loginFocused, true);
    assert.equal(state.submit.disabled, false);
    assert.equal(state.network.length, 1);
    assert.equal(state.network[0].url, '/wp-json/api/support/v1/guest/intake');
    assert.equal(state.network[0].options.cache, 'no-store');
    assert.equal(JSON.parse(state.network[0].options.body).category, 'technical');
});

test('422 emergency diversion never saves a token or claims a case', async () => {
    const state = setupGuest({
        ok: false,
        body: { status: 'diverted', case_created: false, requires_immediate_direction: true, runbook_type: 'clinical_red_flag' }
    });
    await state.form.listeners.submit({ preventDefault() {} });
    assert.equal(state.status.textContent, 'DIVERT_TO_SPECIALIST');
    assert.equal(state.status.dataset.state, 'error');
    assert.equal(state.storage.getItem(STORAGE_KEY), null);
});

test('invalid guest response is not stored as a successful receipt', async () => {
    const state = setupGuest({ body: { status: 'accepted', case_created: true } });
    await state.form.listeners.submit({ preventDefault() {} });
    assert.equal(state.status.dataset.state, 'error');
    assert.equal(state.storage.getItem(STORAGE_KEY), null);
});

function setupContinuation(initialRecord, response) {
    const storage = makeSessionStorage({ [STORAGE_KEY]: JSON.stringify(initialRecord) });
    const root = makeElement('div');
    let casesClicked = false;
    const cases = { click() { casesClicked = true; } };
    root.dataset = { endpoint: '/wp-json/api/support/v1', nonce: 'valid-nonce' };
    root.querySelector = selector => selector === '[data-tab="cases"]' ? cases : null;
    const requests = [];
    const doc = {
        querySelectorAll(selector) {
            return selector === '[data-cf02-complete]' ? [root] : [];
        },
        createElement: makeElement
    };
    const context = {
        window: { sessionStorage: storage },
        navigator: { onLine: true },
        document: doc,
        fetch: async (url, options) => {
            requests.push({ url, options });
            return { ok: response.ok ?? true, json: async () => response.body };
        },
        Date, JSON, Number, String
    };
    vm.runInNewContext(SCRIPT, context, { filename: 'cf02-guest.js' });
    return { root, storage, requests, get casesClicked() { return casesClicked; } };
}

test('authenticated step-up explicitly creates a case and clears same-tab token', async () => {
    const state = setupContinuation({
        token: 'CF02G1.valid-mock',
        expires: Date.now() + 4 * 60_000
    }, {
        body: { continuation_completed: true, case: { case_uuid: 'CF02-example' } }
    });
    assert.equal(state.root.children.length, 1);
    const panel = state.root.children[0];
    const actions = panel.children.find(node => node.className === 'cf02-actions');
    assert.ok(actions);
    await actions.children[0].listeners.click();
    assert.equal(state.requests[0].url, '/wp-json/api/support/v1/guest/continue');
    assert.equal(state.requests[0].options.headers['X-WP-Nonce'], 'valid-nonce');
    assert.equal(state.storage.getItem(STORAGE_KEY), null);
    assert.equal(state.casesClicked, true);
});

test('expired continuation is erased without offering case creation', () => {
    const state = setupContinuation({
        token: 'CF02G1.expired-mock',
        expires: Date.now() - 1000
    }, { body: {} });
    assert.equal(state.root.children.length, 0);
    assert.equal(state.storage.getItem(STORAGE_KEY), null);
    assert.equal(state.requests.length, 0);
});

test('a failed authenticated continuation preserves the live token for an explicit retry', async () => {
    const state = setupContinuation({
        token: 'CF02G1.retry-mock',
        expires: Date.now() + 5 * 60_000
    }, { ok: false, body: { message: 'Temporary provider outage' } });
    const actions = state.root.children[0].children.find(node => node.className === 'cf02-actions');
    await actions.children[0].listeners.click();
    assert.ok(state.storage.getItem(STORAGE_KEY));
    assert.equal(actions.children[0].disabled, false);
});
