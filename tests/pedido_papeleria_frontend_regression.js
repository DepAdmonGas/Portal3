const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const components = {}, alerts = [], requests = [];
const context = {
    window: {}, console, FormData, URLSearchParams, setTimeout, clearTimeout,
    document: { addEventListener(name, callback) { if (name === 'alpine:init') callback(); } },
    Alpine: { data(name, factory) { components[name] = factory; } },
    Notify: { error(message) { alerts.push(message); }, success() {} },
    axios: { async post(url, data) { requests.push({ url, data }); return { data: { success: false, message: 'fixture' } }; } },
};
context.window.Notify = context.Notify;
vm.createContext(context);
const root = path.join(__dirname, '../public/assets/js/departamento-operativo/5-comercializadora');
for (const file of ['form.js', 'catalogo.init.js', 'inventario.init.js', 'pedido.init.js', 'reporte-detalle.init.js', 'firma.init.js']) {
    vm.runInContext(fs.readFileSync(path.join(root, 'pedido-papeleria.' + file), 'utf8'), context);
}
async function emptyCase(name, action, setup) {
    const component = components[name]();
    setup(component);
    alerts.length = requests.length = 0;
    await component[action]();
    assert.equal(alerts.length, 1, name + ': una sola alerta');
    assert.equal(alerts[0], 'Completa los campos obligatorios.');
    assert.equal(requests.length, 0, name + ': no envía datos incompletos');
    assert.ok(Object.values(component.validationErrors).includes(false));
    console.log('PASS ' + name + ' vacío: una alerta exacta, errores y ninguna petición');
}
(async () => {
    await emptyCase('pedidoPapeleriaCatalogoComponent', 'guardarProducto', c => { c.productoForm = { producto: ' ', unidad: '' }; });
    await emptyCase('pedidoPapeleriaInventarioComponent', 'agregarInventario', c => {
        c.getContexto = () => ({id_estacion:1}); c.esTodasEstaciones = () => false; c.inventarioForm = { id_producto: 0, piezas: '' };
    });
    await emptyCase('pedidoPapeleriaPedidoComponent', 'agregarProducto', c => { c.nuevoItem = { id_producto: '', otro_producto: '', piezas: '' }; });
    await emptyCase('pedidoPapeleriaPedidoComponent', 'finalizarPedido', c => { c.pedidoActual = { detalle: [] }; c.signaturePad = null; });
    await emptyCase('pedidoPapeleriaReporteDetalleComponent', 'agregarProductoReporte', c => { c.reporteItem = { id_producto: 0, unidad: '' }; });
    await emptyCase('pedidoPapeleriaReporteDetalleComponent', 'guardarReporteDatos', c => { c.reporteForm = { fecha: '', hora: '' }; });
    await emptyCase('pedidoPapeleriaFirmaComponent', 'firmarVobo', c => { c.token = ''; });
    await emptyCase('pedidoPapeleriaPedidoComponent', 'finalizarPedido', c => { c.pedidoActual = { detalle: [{piezas:1}] }; c.signaturePad = { isEmpty: () => true }; });
    const form = context.window.PedidoPapeleriaForm();
    for (const value of [undefined, null, {}, { firmas: null }, { firmas: [] }, { firmas: {} }]) {
        form.pedidoActual = value;
        assert.equal(form.getFirma('A'), null);
        assert.equal(form.getFirma('B'), null);
    }
    const registered = { usuario_nombre: 'Firmante fixture', firma_img_url: '', archivo_faltante: true };
    form.pedidoActual = { firmas: { A: registered } };
    assert.equal(form.getFirma('A'), registered);
    assert.equal(form.getFirma('B'), null);
    for (const file of ['index.php', 'pedido.php']) {
        const view = fs.readFileSync(path.join(__dirname, '../app/Views/departamento-operativo/5-comercializadora/pedido-papeleria', file), 'utf8');
        assert.ok(view.includes('La firma está registrada, pero su imagen no está disponible.'));
        assert.ok(view.includes("getFirma('A')?.usuario_nombre || pedidoActual.personal"));
        assert.ok(view.includes('Sin firma registrada.'));
        const messageExpression = [...view.matchAll(/x-text="([^"]*)"/g)].find(m => m[1].includes('Sin firma registrada.'))[1];
        const renderMessage = new Function('getFirma', 'pedidoActual', 'return (' + messageExpression + ')');
        assert.equal(renderMessage(() => null, { status: 0 }), 'Sin firma registrada.');
        for (const status of [1, 2]) assert.equal(renderMessage(() => null, { status }), 'La firma está registrada, pero su imagen no está disponible.');
        assert.ok(view.includes('text-muted mb-0 fw-normal'));
        assert.equal(renderMessage(() => registered, { status: 1 }), 'La firma está registrada, pero su imagen no está disponible.');
        assert.equal((view.match(/<template\b/g) || []).length, (view.match(/<\/template>/g) || []).length, 'templates equilibrados en ' + file);
        assert.ok(!/pedidoActual\.firmas\.[AB]/.test(view));
        // Ejecutar las expresiones reales de las tarjetas con y sin firmas.
        const block = view.slice(view.indexOf('<!---------- FIRMAS'));
        for (const match of block.matchAll(/(?:x-if|x-text|:src|:style)="([^"]*)"/g)) {
            if (!match[1].includes('getFirma') && !match[1].includes('firmas')) continue;
            for (const value of [{}, { firmas: {} }, { firmas: { A: registered } }]) {
                form.pedidoActual = value;
                new Function('pedidoActual', 'getFirma', 'return (' + match[1] + ')')(value, form.getFirma.bind(form));
            }
        }
    }
    const tableScript = fs.readFileSync(path.join(root, 'pedido-papeleria.datatable.init.js'), 'utf8');
    const signatureRenderer = tableScript.slice(tableScript.indexOf('function renderFirma'), tableScript.indexOf('function renderAccionesPedido'));
    assert.equal((signatureRenderer.match(/fs-8/g) || []).length, 3);
    assert.ok(signatureRenderer.includes('ti-signature text-success fs-9'));
    assert.ok(!/fs-6/.test(signatureRenderer));
    console.log('PASS tarjetas de firmas: elaborador siempre visible, sin inventar firma y columna fs-8');
    const row = {};
    const input = { value: '1234567', selectionStart: 4, setSelectionRange(a, b) { this.cursor = [a, b]; } };
    form.formatNumericInput({ target: input }, row, 'piezas');
    assert.equal(input.value, '1,234,567'); assert.equal(row.piezas, '1234567'); assert.equal(input.cursor[0], 5);
    assert.equal(form.formatInputNumber('1234.50'), '1,234.50');
    assert.equal(form.displayQuantity('1234'), '1,234.00');
    form.validateFields({ piezas: false }, false); form.clearField('piezas'); assert.equal(form.invalid('piezas'), false);
    console.log('PASS formato de Ventas: miles, cursor, decimales de presentación y corrección de error');
    const pedido = components.pedidoPapeleriaPedidoComponent();
    pedido.nuevoItem = { id_producto: '2', otro_producto: '', piezas: '1,234', para_que: '' };
    pedido.pedidoActual = { id: 5 }; requests.length = 0;
    await pedido.agregarProducto();
    assert.equal(requests[0].data.get('piezas'), '1234');
    console.log('PASS envío normalizado de cantidades desde el modal');
    context.Swal = { async fire() { return { isConfirmed: true }; } };
    const signing = components.pedidoPapeleriaPedidoComponent();
    signing.pedidoActual = { id: 368, detalle: [{piezas:1}] };
    signing.signaturePad = { isEmpty: () => false, toDataURL: () => 'data:image/png;base64,fixture' };
    requests.length = 0; await signing.finalizarPedido();
    assert.equal(requests[0].data.firma, 'data:image/png;base64,fixture');
    assert.equal(requests[0].data.id, 368);
    console.log('PASS finalizar envía la firma del canvas junto al pedido');

    context.axios.post = async (url, data) => { requests.push({ url, data }); return { data: { success: url.endsWith('/guardar-reporte-datos') } }; };
    const reporte = components.pedidoPapeleriaReporteDetalleComponent();
    reporte.reporteForm = { fecha: '2026-11-17', hora: '12:00', detalle: '' };
    reporte.reporteActual = { id: 1, detalle_items: [{ id: 1 }] }; reporte.formularioSucio = true;
    requests.length = 0;
    await reporte.aprobarReporte();
    assert.equal(requests.length, 2);
    assert.ok(requests[0].url.endsWith('/guardar-reporte-datos'));
    assert.ok(requests[1].url.endsWith('/aprobar-reporte'));
    console.log('PASS finalizar reporte guarda los cambios pendientes antes de aprobar');
    let now = 100000, tick = null;
    const storage = new Map();
    context.Date = class extends Date { constructor(...args) { super(...(args.length ? args : [now])); } static now() { return now; } };
    context.localStorage = { getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) };
    context.window.setInterval = callback => { tick = callback; return 1; };
    context.window.clearInterval = () => { tick = null; };
    const firma = components.pedidoPapeleriaFirmaComponent();
    firma.pedido = { id: 157 };
    context.fetch = async () => ({ json: async () => ({ success: true }) });
    context.Notify.warning = () => {};
    await firma.crearToken('email');
    assert.equal(firma.segundosRestantes, 30); assert.equal(firma.botonesDeshabilitados, true);
    now += 1100; tick(); assert.equal(firma.segundosRestantes, 29);
    context.document.getElementById = () => ({ dataset: { pedido: '{"id":157}' } });
    const resumed = components.pedidoPapeleriaFirmaComponent(); resumed.init();
    assert.equal(resumed.segundosRestantes, 29);
    now += 30000; tick(); assert.equal(resumed.botonesDeshabilitados, false); assert.equal(storage.size, 0);
    context.window.location = { href: '', origin: 'https://portal3.admongas.com.mx' };
    context.document.referrer = context.window.location.origin + firma.BASE + '-firma/157';
    firma.token = '123456'; await firma.firmarVobo();
    assert.equal(context.window.location.href, firma.BASE);
    resumed.destroy(); assert.equal(tick, null);
    console.log('PASS token: contador 30→29→0, recarga, desbloqueo y redirección al listado');

})().catch(error => { console.error(error); process.exitCode = 1; });
