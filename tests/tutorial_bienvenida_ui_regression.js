'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync(
    `${__dirname}/../public/assets/js/home/tutorial-bienvenida.init.js`,
    'utf8'
);

let alpineInit;
let factory;
let fetchCalls = 0;
let modalShows = 0;
let modalHides = 0;
let modalOptions;
let fetchShouldFail = false;
const replayButton = { hidden: true, classList: { remove() {} } };
let hideHandler;

const modal = {
    show() { modalShows += 1; },
    hide() { modalHides += 1; },
};

const root = { dataset: { multiestacion: '0', autostart: '1', obligatorio: '1', capturas: '["navegacion-sidebar","cards-componentes"]', capturasAnimadas: '["navegacion-sidebar","cards-componentes"]', completeUrl: '/home/tutorial/complete' } };
root.closest = () => root;
root.addEventListener = (event, callback) => {
    if (event === 'hide.bs.modal') hideHandler = callback;
};
const sandbox = {
    console,
    document: {
        addEventListener(event, callback) {
            if (event === 'alpine:init') alpineInit = callback;
        },
        querySelector(selector) {
            if (selector === 'meta[name="csrf-token"]') return { content: 'test-csrf' };
            return selector === '#portal3TutorialReplay' ? replayButton : null;
        },
    },
    Alpine: {
        data(name, callback) {
            assert.equal(name, 'tutorialBienvenida');
            factory = callback;
        },
    },
    window: {
        bootstrap: {
            Modal: { getOrCreateInstance: (_elemento, options) => { modalOptions = options; return modal; } },
        },
    },
    bootstrap: {
        Modal: { getOrCreateInstance: (_elemento, options) => { modalOptions = options; return modal; } },
    },
    fetch: async (url, options) => {
        fetchCalls += 1;
        assert.equal(url, '/home/tutorial/complete');
        assert.equal(options.method, 'POST');
        assert.equal(options.headers['X-CSRF-TOKEN'], 'test-csrf');
        return {
            ok: !fetchShouldFail,
            json: async () => fetchShouldFail
                ? { success: false, message: 'Error de prueba' }
                : { success: true, completed: true },
        };
    },
};

vm.runInNewContext(source, sandbox, { filename: 'tutorial-bienvenida.init.js' });
alpineInit();

const instance = factory();
instance.$el = root;
instance.$nextTick = callback => callback();
instance.init();

assert.equal(instance.pasos.length, 14, 'The universal journey includes the account topics.');
assert.deepEqual(
    Array.from(instance.pasos.slice(1, 6), step => step.id),
    ['navegacion', 'cards', 'periodo', 'controles', 'alertas'],
    'The tutorial topics must follow the requested learning order.'
);
assert.equal(instance.pasos[1].icono, 'ti-menu-2', 'The navigation step must use the visible sidebar icon.');
assert.equal(instance.capturaDisponible('navegacion-sidebar'), true);
assert.equal(instance.capturaRuta('navegacion-sidebar'), '/assets/images/tutorial-bienvenida/navegacion-sidebar.webp?v=3');
assert.equal(instance.capturaAnimadaDisponible('navegacion-sidebar'), true);
assert.equal(instance.capturaAnimadaRuta('navegacion-sidebar'), '/assets/images/tutorial-bienvenida/navegacion-sidebar.gif');
assert.equal(instance.capturaDisponible('cards-componentes'), true);
assert.equal(instance.capturaRuta('cards-componentes'), '/assets/images/tutorial-bienvenida/cards-componentes.webp?v=3');
assert.equal(instance.capturaAnimadaDisponible('cards-componentes'), true);
assert.equal(instance.capturaAnimadaRuta('cards-componentes'), '/assets/images/tutorial-bienvenida/cards-componentes.gif');
assert.equal(instance.capturaAnimadaRuta('multiestacion'), '/assets/images/tutorial-bienvenida/multiestacion.gif?v=4');
instance.capturasAnimadasAltaCalidad = ['navegacion-sidebar', 'cards-componentes', 'breadcrumbs-periodo', 'dropdowns-botones-iconos', 'alertas-notificaciones', 'datatables', 'multiestacion'];
assert.equal(instance.capturaAnimadaRuta('navegacion-sidebar'), '/assets/images/tutorial-bienvenida/navegacion-sidebar.anim.webp?v=3');
assert.equal(instance.capturaAnimadaRuta('cards-componentes'), '/assets/images/tutorial-bienvenida/cards-componentes.anim.webp?v=3');
assert.equal(instance.capturaRuta('breadcrumbs-periodo'), '/assets/images/tutorial-bienvenida/breadcrumbs-periodo.webp?v=3');
assert.equal(instance.capturaAnimadaRuta('breadcrumbs-periodo'), '/assets/images/tutorial-bienvenida/breadcrumbs-periodo.anim.webp?v=3');
assert.equal(instance.capturaRuta('dropdowns-botones-iconos'), '/assets/images/tutorial-bienvenida/dropdowns-botones-iconos.webp?v=3');
assert.equal(instance.capturaAnimadaRuta('dropdowns-botones-iconos'), '/assets/images/tutorial-bienvenida/dropdowns-botones-iconos.anim.webp?v=3');
assert.equal(instance.capturaRuta('alertas-notificaciones'), '/assets/images/tutorial-bienvenida/alertas-notificaciones.webp?v=3');
assert.equal(instance.capturaAnimadaRuta('alertas-notificaciones'), '/assets/images/tutorial-bienvenida/alertas-notificaciones.anim.webp?v=3');
assert.equal(instance.capturaRuta('datatables'), '/assets/images/tutorial-bienvenida/datatables.webp?v=3');
assert.equal(instance.capturaAnimadaRuta('datatables'), '/assets/images/tutorial-bienvenida/datatables.anim.webp?v=3');
assert.equal(instance.capturaRuta('multiestacion'), '/assets/images/tutorial-bienvenida/multiestacion.webp?v=4');
assert.equal(instance.capturaAnimadaRuta('multiestacion'), '/assets/images/tutorial-bienvenida/multiestacion.gif?v=4');
const imagenConError = { src: '' };
instance.usarAnimacionAlternativa({ target: imagenConError }, 'navegacion-sidebar');
assert.equal(imagenConError.src, '/assets/images/tutorial-bienvenida/navegacion-sidebar.gif');
assert.equal(instance.pasos.some(step => step.id === 'multiestacion'), false);
assert.deepEqual(Array.from(instance.pasos.slice(-5), step => step.id), ['checador', 'perfil', 'perfil-datos', 'perfil-telegram', 'perfil-firma']);
assert.equal(modalShows, 1, 'The tutorial opens automatically on Home.');
assert.equal(instance.obligatorio, true, 'The first run must be mandatory.');
assert.equal(modalOptions.backdrop, true);
assert.equal(modalOptions.keyboard, true, 'Hide events are guarded while mandatory so the same modal can later be replayed normally.');
let hidePrevented = false;
hideHandler({ preventDefault() { hidePrevented = true; } });
assert.equal(hidePrevented, true, 'The mandatory first run must block modal hide requests.');
assert.equal(fetchCalls, 0, 'Opening or closing the modal must not mark it complete.');
instance.siguiente();
assert.equal(instance.indice, 1);
instance.anterior();
assert.equal(instance.indice, 0);

root.dataset.multiestacion = '1';
const multiInstance = factory();
assert.equal(multiInstance.crearPasos(true).length, 16);
assert.deepEqual(Array.from(multiInstance.crearPasos(true).slice(-7), step => step.id), ['checador', 'multiestacion', 'multiestacion-todas', 'perfil', 'perfil-datos', 'perfil-telegram', 'perfil-firma']);
assert.equal(instance.pasos.some(step => step.id === 'multiestacion-todas'), false);

async function verifyCompletion() {
    instance.indice = instance.pasos.length - 1;
    fetchShouldFail = true;
    await instance.finalizar();
    assert.equal(instance.error, 'Error de prueba');
    assert.equal(modalHides, 0, 'A failed save must keep the tutorial open.');

    fetchShouldFail = false;
    await instance.finalizar();
    assert.equal(instance.error, '');
    assert.equal(modalHides, 1, 'A successful save closes the tutorial.');
    assert.equal(instance.obligatorio, false, 'A completed tutorial becomes dismissible.');
    assert.equal(replayButton.hidden, false, 'A successful completion reveals the Home replay button.');
    hidePrevented = false;
    hideHandler({ preventDefault() { hidePrevented = true; } });
    assert.equal(hidePrevented, false, 'A completed tutorial must allow the modal to close.');
    assert.equal(fetchCalls, 2);

    root.dataset.autostart = '0';
    root.dataset.obligatorio = '0';
    const replayInstance = factory();
    replayInstance.$el = root;
    replayInstance.$nextTick = callback => callback();
    replayInstance.init();
    assert.equal(modalShows, 1, 'A completed user must not see the tutorial automatically.');
    assert.equal(replayInstance.obligatorio, false, 'A manually reopened tutorial can be dismissed.');
    process.stdout.write('Tutorial Home UI regression checks passed.\n');
}

verifyCompletion().catch(error => {
    process.stderr.write(`${error.stack}\n`);
    process.exitCode = 1;
});
