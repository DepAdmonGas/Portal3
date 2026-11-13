'use strict';

const fs = require('node:fs');
const vm = require('node:vm');

(async function () {
const source = fs.readFileSync(new URL('../public/assets/js/core/csrf-request-setup.js', `file://${__filename}`), 'utf8');
let token = 'csrf-initial';
let reloads = 0;
let axiosCalls = 0;
const fetchCalls = [];
const meta = {
    getAttribute: () => token,
    setAttribute: (_name, value) => { token = value; }
};
const requestInterceptors = [];
const responseInterceptors = [];
const axios = function () { axiosCalls++; return Promise.resolve({}); };
axios.interceptors = {
    request: { use: (fulfilled, rejected) => requestInterceptors.push({ fulfilled, rejected }) },
    response: { use: (fulfilled, rejected) => responseInterceptors.push({ fulfilled, rejected }) }
};
const windowObject = {
    location: {
        origin: 'https://portal3.test',
        href: 'https://portal3.test/home',
        reload: () => { reloads++; }
    },
    axios,
    fetch(input, init) {
        fetchCalls.push({ input, init });
        return Promise.resolve({ ok: true });
    }
};
const documentObject = { querySelector: () => meta };
vm.runInNewContext(source, { window: windowObject, document: documentObject, URL, Headers, Promise });

const assert = (condition, label) => {
    console.log(`${condition ? 'PASS' : 'FAIL'} ${label}`);
    if (!condition) process.exitCode = 1;
};
const header = (headers, name) => headers instanceof Headers
    ? headers.get(name)
    : (headers && (headers[name] || headers[name.toLowerCase()]));

const axiosRequest = requestInterceptors[0].fulfilled({ method: 'post', url: '/api/module-context/set', headers: {} });
assert(header(axiosRequest.headers, 'X-CSRF-TOKEN') === token, 'Axios same-origin POST receives the current CSRF token');
const crossOriginAxios = requestInterceptors[0].fulfilled({ method: 'post', url: 'https://api.example.test/mutate', headers: {} });
assert(header(crossOriginAxios.headers, 'X-CSRF-TOKEN') === undefined, 'Axios cross-origin POST does not receive the CSRF token');

await windowObject.fetch('/api/module-context/set', { method: 'POST' });
assert(header(fetchCalls.at(-1).init.headers, 'X-CSRF-TOKEN') === token, 'fetch same-origin POST receives the current CSRF token');
await windowObject.fetch('//api.example.test/mutate', { method: 'POST' });
assert(header(fetchCalls.at(-1).init.headers, 'X-CSRF-TOKEN') === undefined, 'protocol-relative cross-origin fetch does not receive the CSRF token');

const expiredError = { response: { status: 419, data: { new_token: 'csrf-rotated' } }, config: { method: 'post', url: '/api/module-context/set' } };
await responseInterceptors[0].rejected(expiredError).catch(() => {});
assert(reloads === 1 && token === 'csrf-rotated', '419 refreshes the token and reloads the page');
assert(axiosCalls === 0, 'CSRF handling does not automatically replay a failed mutation');

console.log(`CSRF_REQUEST_SETUP_RUNTIME_RESULT: ${process.exitCode ? 'FAIL' : 'PASS'}`);
})();
