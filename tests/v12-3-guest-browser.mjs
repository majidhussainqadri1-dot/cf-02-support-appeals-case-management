// Executable browser-contract regression without jsdom or external network.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const script = fs.readFileSync(new URL('../assets/js/cf02-frontend.js', import.meta.url), 'utf8');

async function scenario(action) {
    const key = 'cf02_guest_stepup_v1';
    const values = new Map([[key, JSON.stringify({
        token: 'CF02G1.encrypted-test-fixture',
        expires: Date.now() + 600_000
    })]]);
    const handlers = {};
    const confirm = { disabled: false, addEventListener(name, callback) { handlers.confirm = callback; } };
    const discard = { disabled: false, addEventListener(name, callback) { handlers.discard = callback; } };
    const panel = {
        hidden: true,
        querySelector(selector) {
            if (selector === '[data-action="guest-convert"]') return confirm;
            if (selector === '[data-action="guest-discard"]') return discard;
            return null;
        }
    };
    const liveStatus = { textContent: '', dataset: {} };
    const root = {
        dataset: { i18n: '{}', endpoint: '/wp-json/api/support/v1', nonce: 'nonce' },
        querySelector(selector) {
            if (selector === '.cf02-status') return liveStatus;
            if (selector === '[data-cf02-guest-confirm]') return panel;
            return null;
        },
        querySelectorAll() { return []; },
        setAttribute() {}
    };
    const calls = [];
    const storage = {
        getItem(k) { return values.has(k) ? values.get(k) : null; },
        setItem(k, v) { values.set(k, v); },
        removeItem(k) { values.delete(k); }
    };
    const ctx = {
        document: {
            querySelectorAll(selector) {
                return selector === '[data-cf02-complete]' ? [root] : [];
            }
        },
        window: {
            sessionStorage: storage,
            addEventListener() {},
            crypto: { randomUUID() { return 'stable-test-uuid'; } }
        },
        navigator: { onLine: true },
        fetch: async (url, options) => {
            calls.push({ url, method: options && options.method });
            return {
                ok: true,
                async json() {
                    return url.includes('/guest/continue')
                        ? { case: { case_uuid: 'CF02-00000000-0000-4000-8000-000000000001' } }
                        : { items: [], next_cursor: null };
                }
            };
        },
        URLSearchParams,
        console
    };

    vm.runInNewContext(script, ctx, { filename: 'cf02-frontend.js' });
    assert.equal(panel.hidden, false, 'Pending request must show a confirmation');
    assert.equal(calls.length, 0, 'Rendering must NEVER auto-create a case');
    assert.equal(typeof handlers.confirm, 'function');
    assert.equal(typeof handlers.discard, 'function');

    if (action === 'discard') {
        handlers.discard();
        assert.equal(storage.getItem(key), null, 'Discard must remove encrypted token');
        assert.equal(panel.hidden, true);
        assert.equal(calls.length, 0, 'Discard must not submit');
    } else {
        handlers.confirm();
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(calls.filter(c => c.url.endsWith('/guest/continue')).length, 1,
            'One deliberate confirmation must create at most one guest continuation');
        assert.equal(storage.getItem(key), null, 'Successful continuation must clear token');
        assert.equal(panel.hidden, true);
        assert.equal(calls.filter(c => c.url.endsWith('/guest/intake')).length, 0);
    }
}
await scenario('discard');
await scenario('confirm');
console.log('PASS V12.3 guest browser requires explicit confirmation and supports safe discard');
