document.addEventListener('alpine:init', () => {
Alpine.data('aceitesMesComponent', () => ({
year: null,
mes: null,
idMesDb: null,
idEstacion: null,
multiestacion: false,
finalizado: false,
puedeEditar: false,
puedeEliminar: false,
totalDias: 0,
nombreEstacion: '',

loading: false,
rows: [],
totals: {},
documentos: [],
facturas: [],
diferencias: [],
puntajes: { promedio_ficha: 0, promedio_factura_doc: 0, promedio_factura_anexo: 0 },

tempValues: {},
documentoEditId: null,
documentoEdit: {},
documentoFiles: { ficha_deposito: null, imagen_bodega: null, factura_venta: null },
facturaForm: { fecha: '', concepto: '' },
facturaArchivo: null,
csvFile: null,
diferenciaForm: { id_aceite: 0, idAceiteCode: 0, concepto: '', diferencia: 0, comentario: '' },
detalleDiferencia: { id: 0, fecha: '', fecha_formateada: '', concepto: '', diferencia: 0, documento: '', comentario: '' },

subiendoDocumento: false,
subiendoFactura: false,
importando: false,
_editTimeout: null,

get nombreMes() {
return ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'][this.mes - 1] || '';
},

init() {
const c = document.getElementById('aceites-mes-container');
if (!c) return;
this.year = parseInt(c.dataset.year);
this.mes = parseInt(c.dataset.mes);
this.idMesDb = parseInt(c.dataset.idMesDb);
this.idEstacion = parseInt(c.dataset.idEstacion);
this.multiestacion = c.dataset.multiestacion === 'true';
this.finalizado = c.dataset.finalizado === 'true';
this.puedeEditar = c.dataset.puedeEditar === 'true';
this.puedeEliminar = c.dataset.puedeEliminar === 'true';
this.totalDias = parseInt(c.dataset.diasEnMes);
this.nombreEstacion = c.dataset.nombreEstacion || '';
this.cargarDatos();
this.cargarDiferencias();
},

async cargarDatos() {
this.loading = true;
try {
const resp = await axios.get('/departamento-operativo/aceites-mes/data', {
params: { id_mes: this.idMesDb }
});
if (resp.data.success) {
this.rows = resp.data.data;
this.totals = resp.data.totals;
this.finalizado = resp.data.finalizado;
this.initTempValues();
}
} catch (err) {
console.error('Error cargando datos de aceites', err);
} finally {
this.loading = false;
}
},

initTempValues() {
const tv = {};
for (const row of this.rows) {
tv[row.id] = {
pedido: row.pedido || 0,
inventario_bodega: row.inventario_bodega || 0,
inventario_exibidores: row.inventario_exibidores || 0,
producto_facturado: row.producto_facturado || 0,
factura_venta_mostrador: row.factura_venta_mostrador || 0,
};
}
this.tempValues = tv;
},

getDiaria(row, dia, campo) {
if (!row.diarias || !row.diarias[dia]) return campo === 'importe' ? '0.00' : 0;
return campo === 'importe' ? formatNum(row.diarias[dia].importe) : (row.diarias[dia].cantidad || 0);
},

totalDiariaCantidad(row) {
if (!row.diarias) return '0';
let total = 0;
for (let d = 1; d <= this.totalDias; d++) {
if (row.diarias[d]) total += parseFloat(row.diarias[d].cantidad) || 0;
}
return (total);
},

totalDiariaImporte(row) {
if (!row.diarias) return '0.00';
let total = 0;
for (let d = 1; d <= this.totalDias; d++) {
if (row.diarias[d]) total += parseFloat(row.diarias[d].importe) || 0;
}
return formatNum(total);
},

sumDiaria(dia, campo) {
let total = 0;
for (const row of this.rows) {
if (row.diarias && row.diarias[dia]) {
total += parseFloat(row.diarias[dia][campo]) || 0;
}
}
return total;
},

sumDiariaTotal(campo) {
let total = 0;
for (const row of this.rows) {
if (!row.diarias) continue;
for (let d = 1; d <= this.totalDias; d++) {
if (row.diarias[d]) total += parseFloat(row.diarias[d][campo]) || 0;
}
}
return total;
},

inventarioInicial(row) {
return (parseInt(row.bodega) || 0) + (parseInt(row.exibidores) || 0);
},

inventarioFinal(row) {
return this.inventarioInicial(row) + (parseInt(row.pedido) || 0) - (parseInt(row.ventas_mes) || 0);
},

fisicoFinal(row) {
return (parseInt(row.inventario_bodega) || 0) + (parseInt(row.inventario_exibidores) || 0);
},

diferencia(row) {
return this.fisicoFinal(row) - this.inventarioFinal(row);
},

diferenciaPrecio(row) {
return (parseFloat(row.precio) || 0) * this.diferencia(row);
},

factotal(row) {
return (parseFloat(row.producto_facturado) || 0) + (parseFloat(row.factura_venta_mostrador) || 0);
},

diffactura(row) {
return this.factotal(row);
},

sum(campo) {
let total = 0;
for (const row of this.rows) {
switch (campo) {
case 'bodega': total += parseInt(row.bodega) || 0; break;
case 'exibidores': total += parseInt(row.exibidores) || 0; break;
case 'inventarioI': total += this.inventarioInicial(row); break;
case 'pedido': total += parseInt(row.pedido) || 0; break;
case 'ventasM': total += parseInt(row.ventas_mes) || 0; break;
case 'inventarioF': total += this.inventarioFinal(row); break;
case 'inventario_bodega': total += parseInt(row.inventario_bodega) || 0; break;
case 'inventario_exibidores': total += parseInt(row.inventario_exibidores) || 0; break;
case 'inventario_final': total += this.fisicoFinal(row); break;
case 'diferencia': total += this.diferencia(row); break;
case 'difPrecio': total += this.diferenciaPrecio(row); break;
case 'factotal': total += this.factotal(row); break;
}
}
return total;
},

guardarCampo(row, campo) {
if (this.finalizado || !this.puedeEditar) return;
const tv = this.tempValues[row.id];
if (!tv) return;
const valor = parseFloat(tv[campo]) || 0;
row[campo] = valor;
if (this._editTimeout) clearTimeout(this._editTimeout);
this._editTimeout = setTimeout(() => {
axios.post('/departamento-operativo/aceites-mes/editar-campo', {
id: row.id, campo: campo, valor: valor, log: 1
}).catch(err => console.error('Error editando campo', err));
}, 400);
},

abrirModalDocumentos() { this.cargarDocumentos(); new bootstrap.Modal(this.$refs.modalDocumentos).show(); },

async cargarDocumentos() {
this.documentos = [];
try {
const resp = await axios.get('/departamento-operativo/aceites-mes/documentos', { params: { id_mes: this.idMesDb } });
if (resp.data.success) this.documentos = resp.data.data;
} catch (err) { console.error('Error cargando documentos', err); }
},

async subirDocumento() {
if (this.subiendoDocumento) return;
if (!this.documentoFiles.ficha_deposito && !this.documentoFiles.imagen_bodega && !this.documentoFiles.factura_venta && !this.documentoEditId) {
Notify['error']('Selecciona al menos un archivo');
return;
}
this.subiendoDocumento = true;
const data = new FormData();
data.append('id_mes', this.idMesDb);
if (this.documentoEditId) {
data.append('id', this.documentoEditId);
}
const campos = ['ficha_deposito', 'imagen_bodega', 'factura_venta'];
for (const c of campos) {
if (this.documentoFiles[c]) data.append(c, this.documentoFiles[c]);
}
try {
const url = this.documentoEditId
? '/departamento-operativo/aceites-mes/actualizar-documento'
: '/departamento-operativo/aceites-mes/upload-documento';
const resp = await axios.post(url, data);
if (resp.data.success) {
Notify['success'](resp.data.message);
this.documentoFiles = { ficha_deposito: null, imagen_bodega: null, factura_venta: null };
this.documentoEditId = null;
this.documentoEdit = {};
await this.cargarDocumentos();
} else {
Notify['error'](resp.data.message);
}
} catch (err) {
Notify['error']('Error al subir documento');
} finally {
this.subiendoDocumento = false;
}
},

editarDocumento(doc) {
this.documentoEdit = Object.assign({}, doc);
this.documentoEditId = doc.id;
this.documentoFiles = { ficha_deposito: null, imagen_bodega: null, factura_venta: null };
},

cancelarEdicionDocumento() {
this.documentoEditId = null;
this.documentoEdit = {};
this.documentoFiles = { ficha_deposito: null, imagen_bodega: null, factura_venta: null };
},

abrirModalFacturas() { this.cargarFacturas(); new bootstrap.Modal(this.$refs.modalFacturas).show(); },

async cargarFacturas() {
this.facturas = [];
try {
const resp = await axios.get('/departamento-operativo/aceites-mes/facturas', { params: { id_mes: this.idMesDb } });
if (resp.data.success) this.facturas = resp.data.data;
} catch (err) { console.error('Error cargando facturas', err); }
},

async subirFactura() {
if (this.subiendoFactura) return;
if (!this.facturaForm.fecha) { Notify['error']('Selecciona una fecha'); return; }
if (!this.facturaForm.concepto) { Notify['error']('Selecciona un concepto'); return; }
if (!this.facturaArchivo) { Notify['error']('Selecciona un archivo'); return; }
this.subiendoFactura = true;
const data = new FormData();
data.append('id_mes', this.idMesDb);
data.append('fecha', this.facturaForm.fecha);
data.append('concepto', this.facturaForm.concepto);
data.append('archivo', this.facturaArchivo);
try {
const resp = await axios.post('/departamento-operativo/aceites-mes/upload-factura', data);
if (resp.data.success) {
Notify['success'](resp.data.message);
this.facturaForm = { fecha: '', concepto: '' };
this.facturaArchivo = null;
await this.cargarFacturas();
} else {
Notify['error'](resp.data.message);
}
} catch (err) {
Notify['error']('Error al subir factura');
} finally {
this.subiendoFactura = false;
}
},

abrirModalDiferencias() { this.cargarDiferencias(); new bootstrap.Modal(this.$refs.modalDiferencias).show(); },

async cargarDiferencias() {
this.diferencias = [];
try {
const resp = await axios.get('/departamento-operativo/aceites-mes/diferencias', { params: { id_mes: this.idMesDb } });
if (resp.data.success) this.diferencias = resp.data.data;
} catch (err) { console.error('Error cargando diferencias', err); }
},

pagoExiste(reportId, idAceite) {
return this.diferencias.some(d =>
String(d.id_aceite) === String(reportId) ||
String(d.nomaceite) === String(idAceite)
);
},

abrirModalDiferencia(reportId, noAceite, concepto, diferencia) {
this.diferenciaForm = { id_aceite: reportId, idAceiteCode: noAceite, concepto: concepto, diferencia: diferencia, comentario: '' };
new bootstrap.Modal(this.$refs.modalPagoDiferencia).show();
},

verDetalleDiferencia(reportId, idAceite, concepto) {
const diff = this.diferencias.find(d =>
String(d.id_aceite) === String(reportId) ||
String(d.nomaceite) === String(idAceite)
);
if (diff) {
this.detalleDiferencia = {
id: diff.id || 0,
fecha: diff.fecha || '',
fecha_formateada: diff.fecha_formateada || '',
concepto: concepto || '',
diferencia: diff.diferencia || 0,
documento: diff.documento || '',
comentario: diff.comentario || ''
};
new bootstrap.Modal(this.$refs.modalDetalleDiferencia).show();
}
},

async pagarDiferencia() {
const fileInput = document.getElementById('docDiferencia');
if (!fileInput || !fileInput.files[0]) {
Notify['error']('Selecciona un archivo PDF');
fileInput.style.border = '2px solid #dc3545';
return;
}
const ext = fileInput.value.split('.').pop().toLowerCase();
if (ext !== 'pdf') {
Notify['error']('Solo se permiten archivos PDF');
fileInput.style.border = '2px solid #dc3545';
return;
}
fileInput.style.border = '';
const data = new FormData();
data.append('id_aceite', this.diferenciaForm.id_aceite);
data.append('id_mes', this.idMesDb);
data.append('nombre_aceite', this.diferenciaForm.idAceiteCode);
data.append('diferencia', Math.abs(this.diferenciaForm.diferencia));
data.append('comentario', this.diferenciaForm.comentario);
data.append('documento', fileInput.files[0]);
this.loading = true;
try {
const resp = await axios.post('/departamento-operativo/aceites-mes/agregar-diferencia', data);
if (resp.data.success) {
Notify['success'](resp.data.message);
bootstrap.Modal.getInstance(this.$refs.modalPagoDiferencia).hide();
await this.cargarDatos();
await this.cargarDiferencias();
} else {
Notify['error'](resp.data.message);
}
} catch (err) {
Notify['error']('Error al guardar diferencia');
} finally {
this.loading = false;
}
},

async actualizarDocumentoDiferencia() {
const fileInput = document.getElementById('docDiferenciaUpdate');
if (!fileInput || !fileInput.files[0]) {
Notify['error']('Selecciona un archivo PDF');
return;
}
const ext = fileInput.value.split('.').pop().toLowerCase();
if (ext !== 'pdf') {
Notify['error']('Solo se permiten archivos PDF');
return;
}
const data = new FormData();
data.append('id', this.detalleDiferencia.id);
data.append('documento', fileInput.files[0]);
this.loading = true;
try {
const resp = await axios.post('/departamento-operativo/aceites-mes/actualizar-documento-diferencia', data);
if (resp.data.success) {
Notify['success'](resp.data.message);
bootstrap.Modal.getInstance(this.$refs.modalDetalleDiferencia).hide();
await this.cargarDiferencias();
} else {
Notify['error'](resp.data.message);
}
} catch (err) {
Notify['error']('Error al subir documento');
} finally {
this.loading = false;
}
},

async finalizarInventario() {
const result = await Swal.fire({
title: '¿Finalizar inventario?',
text: 'Una vez finalizado no podrá editar los campos',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'Sí, finalizar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#28a745',
});
if (!result.isConfirmed) return;

const warning = await Swal.fire({
title: 'Mensaje',
html: '<p style="font-size: 1.2em">En caso de tener alguna diferencia en algún aceite o lubricante, se contara con 5 días para realizar los pagos de los mismos.</p>',
icon: 'info',
showCancelButton: false,
confirmButtonText: 'Aceptar y continuar',
confirmButtonColor: '#28a745',
});
if (!warning.isConfirmed) return;

this.loading = true;
try {
const resp = await axios.post('/departamento-operativo/aceites-mes/finalizar', { id_mes: this.idMesDb });
if (resp.data.success) {
Notify['success'](resp.data.message);
this.finalizado = true;
await this.cargarDatos();
} else {
Notify['error'](resp.data.message);
}
} catch (err) {
Notify['error']('Error al finalizar');
} finally {
this.loading = false;
}
},

descargarResumenExcel() {
    window.open('/departamento-operativo/aceites-mes/' + this.year + '/' + this.mes + '/excel', '_blank');
},

abrirResumenImpuestos() {
    window.location.href = '/departamento-operativo/resumen-aceites-mes/' + this.year + '/' + this.mes;
},

abrirEvaluacionKpi() {
    window.location.href = '/departamento-operativo/resumen-kpi-aceites/' + this.year;
},

async abrirListaAceites() {
try {
await fetch('/departamento-operativo/corporativo/lista-aceites/guardar-contexto', {
method: 'POST',
headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
body: 'idEstacion=' + this.idEstacion + '&idYear=' + this.year + '&idMes=' + this.mes
});
} catch (e) { console.error(e); }
window.location.href = '/departamento-operativo/corporativo/lista-aceites';
},

abrirModalPuntajes() { this.cargarPuntajes(); new bootstrap.Modal(this.$refs.modalPuntajes).show(); },

async cargarPuntajes() {
this.puntajes = { promedio_ficha: 0, promedio_factura_doc: 0, promedio_factura_anexo: 0 };
try {
const resp = await axios.get('/departamento-operativo/aceites-mes/resumen-puntajes', { params: { id_mes: this.idMesDb } });
if (resp.data.success) this.puntajes = resp.data.data;
} catch (err) { console.error('Error cargando puntajes', err); }
},

abrirModalImportar() {
this.csvFile = null;
new bootstrap.Modal(this.$refs.modalImportar).show();
},

async importarFacturas() {
if (this.importando) return;
if (!this.csvFile) { Notify['error']('Selecciona un archivo CSV'); return; }
this.importando = true;
try {
const text = await this.csvFile.text();
const data = text.split('\n').filter(l => l.trim()).map(l => { const p = l.split(','); return p.length >= 3 ? { fecha: p[0].trim(), concepto: p[1].trim(), archivo: p[2].trim() } : null; }).filter(Boolean);
if (data.length === 0) { Notify['error']('No se encontraron datos válidos en el CSV'); this.importando = false; return; }
const resp = await axios.post('/departamento-operativo/aceites-mes/importar-facturas', { id_mes: this.idMesDb, data });
if (resp.data.success) {
Notify['success'](resp.data.message);
bootstrap.Modal.getInstance(this.$refs.modalImportar).hide();
this.csvFile = null;
await this.cargarFacturas();
} else {
Notify['error'](resp.data.message);
}
} catch (err) {
Notify['error']('Error al importar');
} finally {
this.importando = false;
}
},

}));
});
