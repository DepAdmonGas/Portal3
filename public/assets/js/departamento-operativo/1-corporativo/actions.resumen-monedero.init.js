function resumenMonederoComponent() {
return {
loading: true,
error: null,
rows: [],
totales: {},
selectedRowFecha: null,
selectedFacturaId: null,
documentos: [],
verProsegur: false,
puedeEliminarDoc: false,
puedeCrear: false,
multiestacion: false,
idPuesto: 0,

modalFacturaVista: 'lista',
modalFacturaTitulo: 'Facturas',

facturaForm: { id: null, fecha: '', monedero: '', diferencia: '' },
erroresFactura: {},
guardandoFactura: false,

ediForm: { idDocumento: null, complemento: '' },
erroresEdi: {},
ediLista: [],
ediDocumentoNombre: '',
guardandoEdi: false,

docForm: { idMonedero: null, descripcion: '' },
erroresDoc: {},
docLista: [],
guardandoDoc: false,

init() {
const container = document.getElementById('container');
if (container) {
this.multiestacion = container.dataset.multiestacion === 'true';
this.idPuesto = parseInt(container.dataset.idPuesto) || 0;
}
this.cargarDatos();
},

cargarDatos() {
this.loading = true;
this.error = null;
const idMesDb = document.getElementById('container').dataset.idMesDb;
const verProsegur = document.getElementById('container').dataset.verProsegur === 'true';
this.verProsegur = verProsegur;

axios.get('/departamento-operativo/resumen-monedero/data', {
params: { id_mes: idMesDb }
}).then(res => {
if (res.data.success) {
this.rows = res.data.rows;
this.totales = res.data.totales;
this.documentos = res.data.documentos;
this.verProsegur = res.data.ver_prosegur || verProsegur;
this.puedeEliminarDoc = res.data.puede_eliminar_doc !== undefined ? res.data.puede_eliminar_doc : true;
this.multiestacion = res.data.multiestacion !== undefined ? res.data.multiestacion : this.multiestacion;
this.idPuesto = res.data.id_puesto !== undefined ? res.data.id_puesto : this.idPuesto;
this.puedeCrear = res.data.puede_crear !== undefined ? res.data.puede_crear : false;
} else {
this.error = res.data.message || 'Error al cargar datos';
}
}).catch(err => {
this.error = 'Error de conexión: ' + (err.response?.data?.message || err.message);
}).finally(() => {
this.loading = false;
});
},

formato(val) {
if (val === null || val === undefined) return '0.00';
return Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
},

formatearNumero(value) {
if (value === null || value === undefined || value === '') return '';
const normalized = String(value).replace(/,/g, '').replace(/[^0-9.]/g, '');
const parts = normalized.split('.');
const integer = parts.shift() || '';
const decimals = parts.join('');
const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
return normalized.includes('.') ? grouped + '.' + decimals : grouped;
},

formatearDiferencia(event) {
const input = event.target;
const raw = input.value;
const cursor = input.selectionStart ?? raw.length;
const caracteresAntes = raw.slice(0, cursor).replace(/,/g, '').replace(/[^0-9.]/g, '').length;
const normalized = raw.replace(/,/g, '').replace(/[^0-9.]/g, '');
const parts = normalized.split('.');
const integer = parts.shift() || '';
const decimals = parts.join('');
const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
this.facturaForm.diferencia = normalized.includes('.') ? grouped.replace(/,/g, '') + '.' + decimals : grouped.replace(/,/g, '');
input.value = normalized.includes('.') ? grouped + '.' + decimals : grouped;
let nextCursor = 0;
let numericChars = 0;
while (nextCursor < input.value.length && numericChars < caracteresAntes) {
if (/[0-9.]/.test(input.value[nextCursor])) numericChars++;
nextCursor++;
}
input.setSelectionRange(nextCursor, nextCursor);
this.erroresFactura.diferencia = false;
},

seleccionarFila(row) {
if (this.selectedRowFecha === row.fecha) return;
this.selectedRowFecha = row.fecha;
},

seleccionarFactura(doc) {
if (this.selectedFacturaId === doc.id) return;
this.selectedFacturaId = doc.id;
},

resumenPorPeriodo() {
const container = document.getElementById('container');
const idYear = container.dataset.idYear;
const idMes = container.dataset.idMes;
window.location.href = '/departamento-operativo/resumen-monedero/periodo/' + idYear + '/' + idMes;
},

evaluacionKPI() {
const container = document.getElementById('container');
const idYear = container.dataset.idYear;
window.location.href = '/departamento-operativo/resumen-monedero/kpi-evaluacion/' + idYear;
},

abrirModalFacturas() {
this.modalFacturaVista = 'lista';
this.modalFacturaTitulo = 'Facturas';
this.selectedFacturaId = null;
this.cargarDocumentos();
new bootstrap.Modal(document.getElementById('modalFacturas')).show();
},

cargarDocumentos() {
const idMesDb = document.getElementById('container').dataset.idMesDb;
axios.get('/departamento-operativo/resumen-monedero/documentos', {
params: { id_mes: idMesDb }
}).then(res => {
if (res.data.success) {
this.documentos = res.data.documentos;
}
});
},

nuevaFactura() {
this.facturaForm = { id: null, fecha: '', monedero: '', diferencia: '' };
this.erroresFactura = {};
this.modalFacturaVista = 'form';
this.modalFacturaTitulo = 'Agregar factura';
},

editarFactura(doc) {
this.facturaForm = {
id: doc.id,
fecha: doc.fecha_input || doc.fecha,
monedero: doc.monedero,
diferencia: doc.diferencia
};
this.erroresFactura = {};
this.modalFacturaVista = 'form';
this.modalFacturaTitulo = 'Editar factura (' + (doc.monedero || '') + ')';
},

cancelarFormulario() {
this.modalFacturaVista = 'lista';
this.modalFacturaTitulo = 'Facturas';
this.erroresFactura = {};
this.cargarDocumentos();
},

guardarFactura() {
const f = this.facturaForm;
const pdfInput = document.getElementById('facturaPDF');
const xmlInput = document.getElementById('facturaXML');
const requiereArchivos = this.multiestacion && !f.id;
this.erroresFactura = {
fecha: !f.fecha,
monedero: !f.monedero,
diferencia: f.diferencia === '' || !Number.isFinite(Number(String(f.diferencia).replace(/,/g, ''))),
pdf: requiereArchivos && !(pdfInput && pdfInput.files[0]),
xml: requiereArchivos && !(xmlInput && xmlInput.files[0]),
};
if (Object.values(this.erroresFactura).some(Boolean)) {
Notify.error('Completa los campos obligatorios.');
return;
}

this.guardandoFactura = true;
const container = document.getElementById('container');
const formData = new FormData();
formData.append('id_mes', container.dataset.idMesDb);
formData.append('fecha', f.fecha);
formData.append('monedero', f.monedero);
formData.append('diferencia', f.diferencia);
formData.append('year', container.dataset.idYear);
formData.append('mes', container.dataset.idMes);

if (pdfInput && pdfInput.files[0]) formData.append('PDF_file', pdfInput.files[0]);
if (xmlInput && xmlInput.files[0]) formData.append('XML_file', xmlInput.files[0]);

let url = '/departamento-operativo/resumen-monedero/crear-documento';
if (f.id) {
url = '/departamento-operativo/resumen-monedero/editar-documento';
formData.append('id', f.id);
const excelInput = document.getElementById('facturaEXCEL');
const soporteInput = document.getElementById('facturaSoporteD');
if (excelInput && excelInput.files[0]) formData.append('EXCEL_file', excelInput.files[0]);
if (soporteInput && soporteInput.files[0]) formData.append('SoporteD_file', soporteInput.files[0]);
}

axios.post(url, formData, {
headers: { 'Content-Type': 'multipart/form-data' }
}).then(res => {
if (res.data.success) {
Notify.success(res.data.message);
this.cancelarFormulario();
} else {
Notify.error(res.data.message);
}
}).catch(err => {
Notify.error('Error al guardar: ' + (err.response?.data?.message || err.message));
}).finally(() => {
this.guardandoFactura = false;
});
},

volverListaFacturas() {
this.modalFacturaVista = 'lista';
this.modalFacturaTitulo = 'Facturas';
this.cargarDocumentos();
},

abrirEdi(idDocumento) {
this.ediForm = { idDocumento: idDocumento, complemento: '' };
this.erroresEdi = {};
this.modalFacturaVista = 'edi';

const doc = this.documentos.find(d => d.id === idDocumento);
this.ediDocumentoNombre = doc ? doc.monedero : '';
this.modalFacturaTitulo = 'Documentos EDI (' + (doc ? doc.monedero : '') + ')';

axios.get('/departamento-operativo/resumen-monedero/edi', {
params: { id_documento: idDocumento }
}).then(res => {
if (res.data.success) {
this.ediLista = res.data.edi;
}
});
},

limpiarErrorEdiArchivo() {
const pdfInput = document.getElementById('ediPDF');
const xmlInput = document.getElementById('ediXML');
if ((pdfInput && pdfInput.files[0]) || (xmlInput && xmlInput.files[0])) {
this.erroresEdi.archivos = false;
}
},

guardarEdi() {
const pdfInput = document.getElementById('ediPDF');
const xmlInput = document.getElementById('ediXML');
const tieneArchivo = !!(pdfInput && pdfInput.files[0]) || !!(xmlInput && xmlInput.files[0]);
this.erroresEdi = { complemento: !this.ediForm.complemento, archivos: !tieneArchivo };
if (Object.values(this.erroresEdi).some(Boolean)) {
Notify.error('Completa los campos obligatorios.');
return;
}

const hasPdf = pdfInput && pdfInput.files[0];
const hasXml = xmlInput && xmlInput.files[0];

this.guardandoEdi = true;
const formData = new FormData();
formData.append('id', this.ediForm.idDocumento);
formData.append('complemento', this.ediForm.complemento);

if (hasPdf) formData.append('PDF_file', pdfInput.files[0]);
if (hasXml) formData.append('XML_file', xmlInput.files[0]);

axios.post('/departamento-operativo/resumen-monedero/crear-edi', formData, {
headers: { 'Content-Type': 'multipart/form-data' }
}).then(res => {
if (res.data.success) {
Notify.success(res.data.message);
this.ediForm.complemento = '';
if (pdfInput) pdfInput.value = '';
if (xmlInput) xmlInput.value = '';
this.abrirEdi(this.ediForm.idDocumento);
} else {
Notify.error(res.data.message);
}
}).catch(err => {
Notify.error('Error al guardar: ' + (err.response?.data?.message || err.message));
}).finally(() => {
this.guardandoEdi = false;
});
},

abrirDocumentacion(idMonedero) {
this.docForm = { idMonedero: idMonedero, descripcion: '' };
this.erroresDoc = {};
this.modalFacturaVista = 'documentacion';

const doc = this.documentos.find(d => d.id === idMonedero);
this.modalFacturaTitulo = 'Documentación Monederos (' + (doc ? doc.monedero : '') + ')';

axios.get('/departamento-operativo/resumen-monedero/lista-documentos', {
params: { id_monedero: idMonedero }
}).then(res => {
if (res.data.success) {
this.docLista = res.data.lista;
}
});
},

guardarDocumentacion() {
const fileInput = document.getElementById('docArchivoPDF');
this.erroresDoc = {
descripcion: !String(this.docForm.descripcion || '').trim(),
archivo: !(fileInput && fileInput.files[0]),
};
if (Object.values(this.erroresDoc).some(Boolean)) {
Notify.error('Completa los campos obligatorios.');
return;
}

const ext = fileInput.files[0].name.split('.').pop().toLowerCase();
if (ext !== 'pdf' && ext !== 'xlsx') {
Notify.error('El archivo debe ser PDF o Excel');
return;
}

this.guardandoDoc = true;
const formData = new FormData();
formData.append('id_monedero', this.docForm.idMonedero);
formData.append('descripcion', this.docForm.descripcion);
formData.append('ArchivoPDF_file', fileInput.files[0]);

axios.post('/departamento-operativo/resumen-monedero/crear-lista-documento', formData, {
headers: { 'Content-Type': 'multipart/form-data' }
}).then(res => {
if (res.data.success) {
Notify.success(res.data.message);
this.docForm.descripcion = '';
fileInput.value = '';
this.abrirDocumentacion(this.docForm.idMonedero);
} else {
Notify.error(res.data.message);
}
}).catch(err => {
Notify.error('Error al guardar: ' + (err.response?.data?.message || err.message));
}).finally(() => {
this.guardandoDoc = false;
});
},

};
}

document.addEventListener('alpine:init', () => {
Alpine.data('resumenMonederoComponent', resumenMonederoComponent);
});
