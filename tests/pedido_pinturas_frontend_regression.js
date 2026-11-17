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
    vm.runInContext(fs.readFileSync(path.join(root, 'pedido-pinturas.' + file), 'utf8'), context);
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
    await emptyCase('pedidoPinturasCatalogoComponent', 'guardarProducto', c => { c.productoForm = { producto: ' ', unidad: '' }; });
    await emptyCase('pedidoPinturasInventarioComponent', 'agregarInventario', c => {
        c.getIdEstacionContexto = () => 1; c.esTodasEstaciones = () => false; c.inventarioForm = { id_producto: 0, piezas: '' };
    });
    await emptyCase('pedidoPinturasPedidoComponent', 'agregarProducto', c => { c.nuevoItem = { id_producto: '', otro_producto: '', piezas: '' }; });
    await emptyCase('pedidoPinturasPedidoComponent', 'finalizarPedido', c => { c.pedidoActual = { detalle: [] }; c.signaturePad = null; });
    await emptyCase('pedidoPinturasReporteDetalleComponent', 'agregarProductoReporte', c => { c.reporteItem = { id_producto: 0, unidad: '' }; });
    await emptyCase('pedidoPinturasReporteDetalleComponent', 'guardarReporteDatos', c => { c.reporteForm = { fecha: '', hora: '' }; });
    await emptyCase('pedidoPinturasFirmaComponent', 'firmarVobo', c => { c.token = ''; });
    const form = context.window.PedidoPinturasForm();
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
        const view = fs.readFileSync(path.join(__dirname, '../app/Views/departamento-operativo/5-comercializadora/pedido-pinturas', file), 'utf8');
        assert.ok(view.includes('La firma está registrada, pero su imagen no está disponible.'));
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
    console.log('PASS tarjetas de firmas: registro sin imagen y expresiones seguras sin A/B');
    const row = {};
    const input = { value: '1234567', selectionStart: 4, setSelectionRange(a, b) { this.cursor = [a, b]; } };
    form.formatNumericInput({ target: input }, row, 'piezas');
    assert.equal(input.value, '1,234,567'); assert.equal(row.piezas, '1234567'); assert.equal(input.cursor[0], 5);
    assert.equal(form.formatInputNumber('1234.50'), '1,234.50');
    assert.equal(form.displayQuantity('1234'), '1,234.00');
    form.validateFields({ piezas: false }, false); form.clearField('piezas'); assert.equal(form.invalid('piezas'), false);
    console.log('PASS formato de Ventas: miles, cursor, decimales de presentación y corrección de error');
    const pedido = components.pedidoPinturasPedidoComponent();
    pedido.nuevoItem = { id_producto: '2', otro_producto: '', piezas: '1,234', para_que: '' };
    pedido.pedidoActual = { id: 5 }; requests.length = 0;
    await pedido.agregarProducto();
    assert.equal(requests[0].data.get('piezas'), '1234');
    console.log('PASS envío normalizado de cantidades desde el modal');
    context.Swal = { async fire() { return { isConfirmed: true }; } };
    context.axios.post = async (url, data) => { requests.push({ url, data }); return { data: { success: url.endsWith('/guardar-reporte-datos') } }; };
    const reporte = components.pedidoPinturasReporteDetalleComponent();
    reporte.reporteForm = { fecha: '2026-11-17', hora: '12:00', detalle: '' };
    reporte.reporteActual = { id: 1, detalle_items: [{ id: 1 }] }; reporte.formularioSucio = true;
    requests.length = 0;
    await reporte.aprobarReporte();
    assert.equal(requests.length, 2);
    assert.ok(requests[0].url.endsWith('/guardar-reporte-datos'));
    assert.ok(requests[1].url.endsWith('/aprobar-reporte'));
    console.log('PASS finalizar reporte guarda los cambios pendientes antes de aprobar');
})().catch(error => { console.error(error); process.exitCode = 1; });
