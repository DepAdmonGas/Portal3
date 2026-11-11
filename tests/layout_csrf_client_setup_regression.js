'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const csrfSetupSource = fs.readFileSync(`${root}/public/assets/js/core/csrf-request-setup.js`, 'utf8');
const jquerySetupSource = fs.readFileSync(`${root}/public/assets/js/core/jquery-csrf-setup.js`, 'utf8');
const fallbackSource = fs.readFileSync(`${root}/public/assets/js/core/script-fallbacks.js`, 'utf8');

async function runCsrfSetup() {
    let token = 'csrf-initial';
    const requestInterceptors = [];
    const responseInterceptors = [];
    const axiosRetries = [];
    const fetchCalls = [];
    const dispatchedEvents = [];
    let reloadCount = 0;
    const meta = {
        getAttribute() { return token; },
        setAttribute(_name, value) { token = value; }
    };
    const document = {
        querySelector(selector) { return selector === 'meta[name="csrf-token"]' ? meta : null; }
    };
    function axiosClient(config) {
        axiosRetries.push(config);
        return Promise.resolve({ data: 'retry succeeded' });
    }
    axiosClient.defaults = { headers: { common: {} } };
    axiosClient.interceptors = {
        request: { use(onFulfilled, onRejected) { requestInterceptors.push({ onFulfilled, onRejected }); } },
        response: { use(onFulfilled, onRejected) { responseInterceptors.push({ onFulfilled, onRejected }); } }
    };
    const window = {
        axios: axiosClient,
        location: {
            href: 'http://portal3.test/asistencia',
            origin: 'http://portal3.test',
            reload() { reloadCount++; }
        },
        dispatchEvent(event) { dispatchedEvents.push(event); },
        fetch(input, init) {
            fetchCalls.push({ input, init });
            return Promise.resolve('fetch succeeded');
        },
        addEventListener() {}
    };
    class FakeCustomEvent {
        constructor(type, init) { this.type = type; this.detail = init.detail; }
    }
    vm.runInNewContext(csrfSetupSource, { window, document, Headers, URL, Promise, CustomEvent: FakeCustomEvent });

    assert.equal(axiosClient.defaults.headers.common['X-CSRF-TOKEN'], 'csrf-initial');
    const request = requestInterceptors[0].onFulfilled({ headers: {} });
    assert.equal(request.headers['X-CSRF-TOKEN'], 'csrf-initial');
    token = 'csrf-refreshed';
    assert.equal(requestInterceptors[0].onFulfilled({ headers: {} }).headers['X-CSRF-TOKEN'], 'csrf-refreshed');

    const csrfError = {
        response: { status: 419, data: { new_token: 'csrf-rotated' } },
        config: { method: 'post', headers: {} }
    };
    let rejectedWrite;
    await responseInterceptors[0].onRejected(csrfError).catch(error => { rejectedWrite = error; });
    assert.equal(rejectedWrite, csrfError);
    assert.equal(token, 'csrf-rotated');
    assert.equal(axiosRetries.length, 0);
    assert.equal(dispatchedEvents[0].type, 'portal3:csrf-rejected');
    assert.equal(dispatchedEvents[0].detail.status, 419);
    assert.equal(dispatchedEvents[0].detail.method, 'POST');

    const safeReadError = {
        response: { status: 419, data: { new_token: 'csrf-read-rotated' } },
        config: { method: 'get', headers: {} }
    };
    const retryResult = await responseInterceptors[0].onRejected(safeReadError);
    assert.equal(retryResult.data, 'retry succeeded');
    assert.equal(token, 'csrf-read-rotated');
    assert.equal(axiosRetries.length, 1);
    assert.equal(axiosRetries[0]._csrfRetried, true);

    await window.fetch('/write', { method: 'POST' });
    assert.equal(fetchCalls[0].init.headers.get('X-CSRF-TOKEN'), 'csrf-read-rotated');
    await window.fetch('https://external.test/write', { method: 'POST' });
    assert.equal(fetchCalls[1].init.headers, undefined);

    await responseInterceptors[0].onRejected({ response: { status: 419, data: {} }, config: { method: 'post' } }).catch(() => {});
    assert.equal(reloadCount, 0);
    assert.equal(dispatchedEvents.length, 2);
}

function runJQuerySetup() {
    let configured;
    let sentHeader;
    const meta = { getAttribute() { return 'jquery-csrf'; } };
    const window = {
        jQuery: { ajaxSetup(config) { configured = config; } }
    };
    const document = { querySelector() { return meta; } };
    vm.runInNewContext(jquerySetupSource, { window, document });
    configured.beforeSend({ setRequestHeader(name, value) { sentHeader = [name, value]; } });
    assert.deepEqual(sentHeader, ['X-CSRF-TOKEN', 'jquery-csrf']);
}

function runScriptFallback() {
    let errorListener;
    let appendedScript;
    let fallbackLoaded;
    const dispatchedEvents = [];
    const window = { dispatchEvent(event) { dispatchedEvents.push(event); } };
    const document = {
        addEventListener(name, callback, capture) {
            if (name === 'error' && capture === true) errorListener = callback;
        },
        createElement(name) {
            assert.equal(name, 'script');
            return {
                addEventListener(eventName, callback) {
                    if (eventName === 'load') fallbackLoaded = callback;
                }
            };
        },
        head: { appendChild(script) { appendedScript = script; } }
    };
    class FakeCustomEvent {
        constructor(type, init) { this.type = type; this.detail = init.detail; }
    }
    vm.runInNewContext(fallbackSource, { window, document, CustomEvent: FakeCustomEvent });

    const failedScript = {
        tagName: 'SCRIPT',
        src: '/assets/libs/axios/dist/axios.min.js',
        defer: false,
        dataset: { fallbackSrc: 'https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js' }
    };
    errorListener({ target: failedScript });
    assert.equal(appendedScript.src, failedScript.dataset.fallbackSrc);
    assert.equal(failedScript.dataset.fallbackAttempted, 'true');
    fallbackLoaded();
    assert.equal(dispatchedEvents[0].type, 'portal3:script-fallback-loaded');
    assert.equal(dispatchedEvents[0].detail.failedSource, failedScript.src);
}

(async function run() {
    await runCsrfSetup();
    runJQuerySetup();
    runScriptFallback();
    console.log('PASS external Axios, fetch, jQuery CSRF setup and script fallback behavior');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
