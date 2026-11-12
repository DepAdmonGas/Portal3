document.addEventListener('alpine:init', function () {
Alpine.data('estimuloFiscalComponent', function () {
return {
resumen: {
fecha_inicio: '',
fecha_termino: '',
g_super: 0,
g_premium: 0,
g_diesel: 0,
total_litros: 0,
total_pagar: 0,
},
pagos: [],
permisos: {},
selectedRows: { resumen: null, pagos: null },
editId: 0,
form: {
fecha_inicio: '',
fecha_termino: '',
},
editForm: {
fecha_inicio: '',
fecha_termino: '',
},
buscarForm: {
fecha_inicio: '',
fecha_termino: '',
},
validacion: {
buscar: { fecha_inicio: false, fecha_termino: false },
nuevo: { fecha_inicio: false, fecha_termino: false, pdf: false, xml: false },
editar: { fecha_inicio: false, fecha_termino: false },
},

init: function () {
this.cargarData();
},

seleccionarFila: function (tabla, id) {
this.selectedRows[tabla] = this.selectedRows[tabla] === id ? null : id;
},

cargarData: function (fechaInicio, fechaTermino) {
var self = this;
var params = {};
if (fechaInicio) params.fecha_inicio = fechaInicio;
if (fechaTermino) params.fecha_termino = fechaTermino;
axios.get('/departamento-operativo/estimulo-fiscal/data', {
params: params
}).then(function (res) {
if (res.data.success) {
self.resumen = res.data.resumen;
self.pagos = res.data.pagos;
self.permisos = res.data.permisos || {};
}
}).catch(function () {
self.notify('error', 'Error al cargar datos');
});
},

buscarReporte: function () {
var periodoInvertido = Boolean(this.buscarForm.fecha_inicio && this.buscarForm.fecha_termino && this.buscarForm.fecha_inicio > this.buscarForm.fecha_termino);
this.validacion.buscar.fecha_inicio = !this.buscarForm.fecha_inicio || periodoInvertido;
this.validacion.buscar.fecha_termino = !this.buscarForm.fecha_termino || periodoInvertido;
if (this.validacion.buscar.fecha_inicio || this.validacion.buscar.fecha_termino) {
this.notify('error', 'Completa los campos obligatorios.');
return;
}
var modal = bootstrap.Modal.getInstance(document.getElementById('modal-buscar'));
if (modal) modal.hide();
this.cargarData(this.buscarForm.fecha_inicio, this.buscarForm.fecha_termino);
},

guardarPago: function () {
var pdfInput = this.$refs.EPDF_file_input;
var xmlInput = this.$refs.EXML_file_input;
var periodoInvertido = Boolean(this.form.fecha_inicio && this.form.fecha_termino && this.form.fecha_inicio > this.form.fecha_termino);
this.validacion.nuevo = {
fecha_inicio: !this.form.fecha_inicio || periodoInvertido,
fecha_termino: !this.form.fecha_termino || periodoInvertido,
pdf: !pdfInput || !pdfInput.files || !pdfInput.files[0],
xml: !xmlInput || !xmlInput.files || !xmlInput.files[0],
};
if (Object.values(this.validacion.nuevo).some(Boolean)) {
this.notify('error', 'Completa los campos obligatorios.');
return;
}
this.loading = true;
var self = this;
var fd = new FormData();
fd.append('fecha_inicio', this.form.fecha_inicio);
fd.append('fecha_termino', this.form.fecha_termino);
fd.append('EPDF_file', pdfInput.files[0]);
fd.append('EXML_file', xmlInput.files[0]);

axios.post('/departamento-operativo/estimulo-fiscal/guardar', fd, {
headers: { 'Content-Type': 'multipart/form-data' }
}).then(function (res) {
if (res.data.success) {
self.notify('success', 'Comprobante agregado exitosamente.');
var modal = bootstrap.Modal.getInstance(document.getElementById('modal-agregar'));
if (modal) modal.hide();
self.cargarData();
self.form = { fecha_inicio: '', fecha_termino: '' };
if (pdfInput) pdfInput.value = '';
if (xmlInput) xmlInput.value = '';
} else {
self.notify('error', res.data.message || 'Error al guardar');
}
}).catch(function () {
self.notify('error', 'Error de conexión');
}).then(function () {
self.loading = false;
});
},

abrirModalEditar: function (id) {
this.editId = id;
this.validacion.editar = { fecha_inicio: false, fecha_termino: false };
var self = this;
axios.get('/departamento-operativo/estimulo-fiscal/detalle', {
params: { id: id }
}).then(function (res) {
if (res.data.success) {
var d = res.data.data;
self.editForm = {
fecha_inicio: d.fecha_inicio || '',
fecha_termino: d.fecha_termino || '',
};
var modal = new bootstrap.Modal(document.getElementById('modal-editar'));
modal.show();
}
}).catch(function () {
self.notify('error', 'Error al obtener datos');
});
},

editarPago: function () {
var periodoInvertido = Boolean(this.editForm.fecha_inicio && this.editForm.fecha_termino && this.editForm.fecha_inicio > this.editForm.fecha_termino);
this.validacion.editar.fecha_inicio = !this.editForm.fecha_inicio || periodoInvertido;
this.validacion.editar.fecha_termino = !this.editForm.fecha_termino || periodoInvertido;
if (this.validacion.editar.fecha_inicio || this.validacion.editar.fecha_termino) {
this.notify('error', 'Completa los campos obligatorios.');
return;
}
this.loading = true;
var self = this;
var fd = new FormData();
fd.append('id', this.editId);
fd.append('fecha_inicio', this.editForm.fecha_inicio);
fd.append('fecha_termino', this.editForm.fecha_termino);

var fileRefs = {
EPDF_file: 'edit_EPDF_file_input',
EXML_file: 'edit_EXML_file_input',
CPDF_file: 'edit_CPDF_file_input',
CXML_file: 'edit_CXML_file_input',
};
Object.keys(fileRefs).forEach(function (key) {
var input = self.$refs[fileRefs[key]];
if (input && input.files && input.files[0]) {
fd.append(key, input.files[0]);
}
});

axios.post('/departamento-operativo/estimulo-fiscal/editar', fd, {
headers: { 'Content-Type': 'multipart/form-data' }
}).then(function (res) {
if (res.data.success) {
self.notify('success', 'Comprobante editado exitosamente.');
var modal = bootstrap.Modal.getInstance(document.getElementById('modal-editar'));
if (modal) modal.hide();
self.cargarData();
} else {
self.notify('error', res.data.message || 'Error al editar');
}
}).catch(function () {
self.notify('error', 'Error de conexión');
}).then(function () {
self.loading = false;
});
},

async eliminarPago(id) {
var res = await this.deleteAction({
url: '/departamento-operativo/estimulo-fiscal/eliminar',
id: id,
name: 'Comprobante #' + id,
table: null
});
if (res && res.success) {
this.cargarData();
}
},

formatearFecha: function (fecha) {
if (!fecha) return '';
var partes = fecha.split('-');
if (partes.length !== 3) return fecha;
var meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
return parseInt(partes[2]) + ' de ' + meses[parseInt(partes[1]) - 1] + ' del ' + partes[0];
},

numberFormat: function (n) {
if (n === null || n === undefined) return '0';
return Number(n).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
},
formatMoney: function (n) {
if (n === null || n === undefined) return '0.00';
return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
},

notify: function (tipo, mensaje) {
if (typeof Notify !== 'undefined') {
if (tipo === 'success') Notify.success(mensaje);
else Notify.error(mensaje);
} else {
alert(mensaje);
}
}
};
});
});
