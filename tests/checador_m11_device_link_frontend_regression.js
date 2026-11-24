'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

let factory;
const calls = [];
let failure = null;
const context = {
    document: { addEventListener: (event, callback) => callback() },
    Alpine: { data: (name, callback) => { factory = callback; } },
    axios: { post: async (...args) => {
        calls.push(args);
        if (failure) throw failure;
        return { data: { ok: true, token: 'a'.repeat(64), device_id: args[1].device_id, expires_at_utc: '2026-11-24 18:15:00' } };
    } },
    navigator: { clipboard: { writeText: async () => {} } },
};
const source = fs.readFileSync(path.join(__dirname, '../public/assets/js/departamento-operativo/2-recursos-humanos/checador.m11-device-link.init.js'), 'utf8');
vm.runInNewContext(source, context);
const root = '/departamento-operativo/recursos-humanos/checador/configuracion/vinculacion-lector/';
const component = (workplace, overrides = {}) => {
    const obj = factory();
    obj.$el = { dataset: { devices: '[]', generateUrl: root + 'generar', revokeUrl: root + 'revocar', workplaceId: workplace, csrfToken: 'csrf-test', ...overrides } };
    obj.init();
    obj.$el = { dataset: {} }; // Alpine event context may refer to the clicked button.
    return obj;
};
(async () => {
    for (const station of [1, 9]) {
        const obj = component(String(station));
        await obj.issue(2);
        const [url, payload, options] = calls.at(-1);
        assert.equal(url, root + 'generar');
        assert.equal(payload.workplace_id, station);
        assert.equal(payload.device_id, 2);
        assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf-test');
        assert.equal(obj.tokens[2].value, 'a'.repeat(64));
        await obj.revoke(2);
        assert.equal(calls.at(-1)[0], root + 'revocar');
        assert.equal(calls.at(-1)[1].workplace_id, station);
        assert.equal(calls.at(-1)[2].headers['X-CSRF-TOKEN'], 'csrf-test');
    }
    for (const overrides of [{ generateUrl: undefined }, { workplaceId: '' }, { csrfToken: '' }, { revokeUrl: 'undefined' }]) {
        const obj = component('1', overrides);
        const before = calls.length;
        if ('revokeUrl' in overrides) await obj.revoke(2); else await obj.issue(2);
        assert.equal(calls.length, before);
        assert.match(obj.error, /Actualiza la página/);
    }
    failure = { response: { status: 404, data: {} } };
    const obj = component('1');
    await obj.issue(2);
    assert.match(obj.error, /servicio/);
    failure.response.data = { message: 'El dispositivo no pertenece a la estación seleccionada.' };
    await obj.issue(2);
    assert.match(obj.error, /dispositivo/);
    console.log('M11 frontend: PASS (URL, localidad, CSRF, revocación, validación y errores 404)');
})().catch(error => { console.error(error); process.exitCode = 1; });
