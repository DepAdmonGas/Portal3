function comparativoXmlComponent() {
return {
idYear: 0,
idEstacion: 0,
nombreES: '',
loading: true,
error: null,
canEdit: false,
esDireccionOperaciones: false,
idPuesto: 0,
campos: [],
mapaNombres: {},
rows: [],
totales: {},
satData: { months: [], total_anual_sat: 0, total_anual_despacho: 0, total_anual_diferencia: 0 },
config: { colspanDespachos: 4, colspanVentas: 9, colspanFacturacion: 8, colspanDiferencia: 3 },
tb: {},
selectedRows: {},
documentos: [],
comentarios: [],
mesActual: 0,
mesActualNombre: '',
nuevoAnexo: '',
errorsDocumento: {},
nuevoComentario: '',
idUsuario: 0,
editorContent: '',
guardandoComentario: false,
guardandoDocumento: false,
guardandoObservaciones: false,

seleccionarFila(tabla, id) {
this.selectedRows[tabla] = id;
},

async cargarDatos(idYear, idEstacion) {
this.idYear = idYear;
this.idEstacion = idEstacion;

if (!idEstacion || !idYear) {
this.loading = false;
this.error = 'Selecciona una estaci\u00f3n para ver el Comparativo XML.';
return;
}

this.loading = true;
this.error = null;

this.idUsuario = 0;

try {
const [dataResp, satResp] = await Promise.all([
fetch('/departamento-operativo/comparativo-xml/data?year=' + idYear).then(r => r.json()),
fetch('/departamento-operativo/comparativo-xml/sat-data?year=' + idYear).then(r => r.json())
]);

if (!dataResp.success) {
this.error = 'Error al cargar los datos.';
this.loading = false;
return;
}

this.campos = dataResp.campos || [];
this.mapaNombres = dataResp.mapaNombres || {};
this.rows = dataResp.rows || [];
this.totales = dataResp.totales || {};
this.config = dataResp.config?.config || this.config;
this.tb = dataResp.config?.tb || {};
this.canEdit = dataResp.canEdit || false;
this.nombreES = dataResp.nombreES || '';
this.esDireccionOperaciones = dataResp.esDireccionOperaciones || false;
this.idPuesto = dataResp.idPuesto || 0;
this.idUsuario = dataResp.idUsuario || 0;
this.editorContent = dataResp.editorContent || '';

if (satResp.success && satResp.satData) {
this.satData = satResp.satData;
}

this.loading = false;

this.$nextTick(() => {
this.inicializarEditor();
});
} catch (e) {
this.error = 'Error de conexi\u00f3n al cargar los datos.';
this.loading = false;
}
},

        inicializarEditor() {
            if (window.editorInstance && typeof window.editorInstance.destroy === 'function') {
                window.editorInstance.destroy().catch(() => {});
                window.editorInstance = null;
            }

            const editorEl = document.querySelector('#editor');
            if (!editorEl) return;

            const content = this.editorContent || '';

            ClassicEditor
                .create(editorEl, {
                    toolbar: {
                        items: [
                            'heading', '|',
                            'bold', 'italic', 'link', '|',
                            'bulletedList', 'numberedList', 'blockQuote', '|',
                            'imageUpload', 'insertTable', '|',
                            'undo', 'redo'
                        ]
                    },
                    language: 'es',
                    image: {
                        toolbar: ['imageTextAlternative']
                    },
                    table: {
                        contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
                    },
                    alignment: {
                        options: ['left', 'center', 'right', 'justify']
                    },
                    extraPlugins: [function temporaryUploadAdapterPlugin(editor) {
                        editor.plugins.get('FileRepository').createUploadAdapter = loader => {
                            return {
                                upload: () => loader.file.then(file => new Promise(resolve => {
                                    const reader = new FileReader();
                                    reader.onload = () => resolve({ default: reader.result });
                                    reader.readAsDataURL(file);
                                })),
                                abort: () => {}
                            };
                        };
                    }],
                    htmlSupport: {
                        allow: [
                            { name: /.*/, attributes: true, classes: true, styles: true }
                        ]
                    }
                })
                .then(editor => {
                    window.editorInstance = editor;
                    const editable = editor.ui.view.editable.element;
                    if (editable) {
                        editable.classList.add('p-3');
                        editable.style.setProperty('padding', '1rem', 'important');
                    }
                    if (content) {
                        editor.setData(content);
                    }

                    editor.on('ready', () => {
                        const editable = editor.ui.view.editable.element;
                        if (editable) {
                            editable.style.minHeight = '500px';
                        }
                    });
                })
                .catch(error => {
                    console.error(error);
                });
        },

formatInput(val) {
const num = this.parseAmount(val);
return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
},

parseAmount(value) {
const normalized = String(value ?? '0').replace(/,/g, '').replace(/[^0-9.\-]/g, '');
const num = Number(normalized);
return Number.isFinite(num) ? num : 0;
},

_rawFromDom(el) {
return this.parseAmount(el.value);
},

_indicarGuardado(el, estado) {
const indicador = el?.parentElement?.querySelector('.comparativo-cell-save-indicator');
if (!indicador) return;
indicador.classList.remove('d-none', 'text-success', 'text-danger', 'text-primary');
if (estado === 'saving') {
 indicador.innerHTML = '';
 indicador.classList.add('d-none');
} else if (estado === 'saved') {
indicador.classList.add('text-success');
indicador.innerHTML = '<i class="ti ti-check" aria-label="Cambio guardado"></i>';
window.setTimeout(() => {
if (indicador.dataset.estado === 'saved') indicador.classList.add('d-none');
}, 1800);
} else {
indicador.classList.add('text-danger');
indicador.innerHTML = '<i class="ti ti-alert-circle" aria-label="No se pudo guardar el cambio"></i>';
}
indicador.dataset.estado = estado;
},

_programarGuardado(el, callback) {
if (el.__comparativoInputTimer) window.clearTimeout(el.__comparativoInputTimer);
this._indicarGuardado(el, 'saving');
el.__comparativoInputTimer = window.setTimeout(() => {
delete el.__comparativoInputTimer;
callback();
}, 400);
},

_cancelarGuardado(el) {
if (el.__comparativoInputTimer) window.clearTimeout(el.__comparativoInputTimer);
delete el.__comparativoInputTimer;
},

limpiarNumero(el) {
const start = el.selectionStart;
const end = el.selectionEnd;
const lenBefore = el.value.length;
let valor = el.value;
valor = valor.replace(/[^0-9.,\-]/g, '');
if (valor.includes('-')) {
valor = valor.replace(/-/g, '');
valor = '-' + valor;
}
valor = valor.replace(/,/g, '');
const removed = lenBefore - valor.length;
el.value = valor;
el.setSelectionRange(Math.max(0, start - removed), Math.max(0, end - removed));
},

_onInput(el, row, cell) {
const v = this._rawFromDom(el);
const args = [v, row.id, cell.idTipo, this.idYear, this.idEstacion, row.mes, cell.seccion, cell.nombre];
this._programarGuardado(el, () => this.editarTabla(...args, el));
},

_onBlur(el, row, cell) {
const v = this._rawFromDom(el);
this._cancelarGuardado(el);
this._indicarGuardado(el, 'saving');
this.editarTabla(v, row.id, cell.idTipo, this.idYear, this.idEstacion, row.mes, cell.seccion, cell.nombre, el);
el.value = this.formatInput(v);
},

editarTabla(valor, idCampo, idTipo, idYear, idEstacion, idMes, idSeccion, idDescripcion, input = null) {
let valorStr = String(valor);
let valorLimpio = valorStr.replace(/[^0-9.\-]/g, '');
if (valorLimpio.indexOf('-') > 0) {
valorLimpio = valorLimpio.replace(/-/g, '');
}
if (valorStr.startsWith('-')) {
valorLimpio = '-' + valorLimpio.replace(/-/g, '');
}

const params = {
idCampo: idCampo,
descripcion: valorLimpio,
idTipo: idTipo,
idEstacion: idEstacion,
idYear: idYear,
idMes: idMes,
idSeccion: idSeccion,
idDescripcion: idDescripcion
};

fetch('/departamento-operativo/comparativo-xml/update-cell', {
method: 'POST',
headers: { 'Content-Type': 'application/json', 'X-Portal3-Skip-Action-Loader': '1' },
body: JSON.stringify(params)
})
.then(r => r.json())
.then(data => {
if (data.success == 1) {
this._indicarGuardado(input, 'saved');
this.actualizarTotales(idEstacion, idYear);
} else {
this._indicarGuardado(input, 'error');
}
})
.catch(() => {
this._indicarGuardado(input, 'error');
});
},

actualizarTotales(idEstacion, idYear) {
fetch('/departamento-operativo/comparativo-xml/data?year=' + idYear)
.then(r => r.json())
.then(data => {
if (data.success) {
this.totales = data.totales || {};
}
})
.catch(() => {});
},

_onInputComparativo(el, item, month, tipo) {
const v = this._rawFromDom(el);
const args = [v, item.id, item.categoria, tipo, this.idYear, this.idEstacion, month.mes];
this._programarGuardado(el, () => this.actualizarComparativo(...args, el));
},

_onBlurComparativo(el, item, month, tipo) {
const v = this._rawFromDom(el);
if (tipo === 1) item.sat_monto = v;
else item.despacho_monto = v;
item.diferencia = (parseFloat(item.sat_monto) || 0) - (parseFloat(item.despacho_monto) || 0);
this._cancelarGuardado(el);
this._indicarGuardado(el, 'saving');
this.actualizarComparativo(v, item.id, item.categoria, tipo, this.idYear, this.idEstacion, month.mes, el);
el.value = this.formatInput(v);
},

actualizarComparativo(valor, idCampo, categoria, idTipo, idYear, idEstacion, idMes, input = null) {
let valorStr = String(valor);
let valorLimpio = valorStr.replace(/[^0-9.\-]/g, '');
if (valorLimpio.indexOf('-') > 0) {
valorLimpio = valorLimpio.replace(/-/g, '');
}
if (valorStr.startsWith('-')) {
valorLimpio = '-' + valorLimpio.replace(/-/g, '');
}

const params = {
idCampo: idCampo,
descripcion: valorLimpio,
categoria: categoria,
idTipo: idTipo,
idYear: idYear,
idMes: idMes,
idEstacion: idEstacion
};

fetch('/departamento-operativo/comparativo-xml/update-sat-cell', {
method: 'POST',
headers: { 'Content-Type': 'application/json', 'X-Portal3-Skip-Action-Loader': '1' },
body: JSON.stringify(params)
})
.then(r => r.json())
.then(data => {
if (data.success == 1) {
this._indicarGuardado(input, 'saved');
this._recalcularTotalesSat();
} else {
this._indicarGuardado(input, 'error');
}
})
.catch(() => {
this._indicarGuardado(input, 'error');
});
},

async cargarSatData(idEstacion, idYear) {
try {
const resp = await fetch('/departamento-operativo/comparativo-xml/sat-data?year=' + idYear);
const data = await resp.json();
if (data.success) {
this.satData = data.satData || { months: [], total_anual_sat: 0, total_anual_despacho: 0, total_anual_diferencia: 0 };
}
} catch (e) {}
},

_recalcularTotalesSat() {
if (!this.satData || !this.satData.months) return;
this.satData.months.forEach(month => {
month.items.forEach(item => {
item.diferencia = (parseFloat(item.sat_monto) || 0) - (parseFloat(item.despacho_monto) || 0);
});
month.total_sat = month.items.reduce((sum, item) => sum + (parseFloat(item.sat_monto) || 0), 0);
month.total_despacho = month.items.reduce((sum, item) => sum + (parseFloat(item.despacho_monto) || 0), 0);
month.total_diferencia = month.total_sat - month.total_despacho;
});
this.satData.total_anual_sat = this.satData.months.reduce((sum, m) => sum + m.total_sat, 0);
this.satData.total_anual_despacho = this.satData.months.reduce((sum, m) => sum + m.total_despacho, 0);
this.satData.total_anual_diferencia = this.satData.total_anual_sat - this.satData.total_anual_despacho;
},

descargarExcel() {
if (this.idEstacion > 0) {
return '/departamento-operativo/comparativo-xml/excel/' + this.idEstacion + '/' + this.idYear;
}
return '#';
},

verSeguimiento() {
    window.location.href = '/departamento-operativo/corporativo/comparativo-xml/seguimiento/' + this.idYear + '/' + this.idEstacion;
},

abrirModalDocumentos(mes) {
this.mesActual = mes;
this.selectedRows.documentos = null;
this.errorsDocumento = {};
const meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
this.mesActualNombre = meses[mes] || mes;
this.nuevoAnexo = '';
this.cargarDocumentos(mes);
const modalEl = document.getElementById('modalDocumentos');
if (modalEl) {
bootstrap.Modal.getOrCreateInstance(modalEl).show();
}
},

cargarDocumentos(mes) {
fetch('/departamento-operativo/comparativo-xml/documents?idEstacion=' + this.idEstacion + '&year=' + this.idYear + '&mes=' + mes)
.then(r => r.json())
.then(data => {
if (data.success) {
this.documentos = data.data || [];
}
})
.catch(() => {});
},

guardarDocumento() {
const anexo = String(this.nuevoAnexo || '').trim();
const fileInput = this.$refs.fileInput;
const file = fileInput?.files[0];
this.errorsDocumento = {};
let valido = true;

if (!anexo) {
this.errorsDocumento.Anexos = true;
valido = false;
}
if (!file) {
this.errorsDocumento.Archivo_file = true;
valido = false;
}
if (!valido) {
if (window.Notify) Notify.error('Completa los campos obligatorios.');
else if (window.alerts) window.alerts.error('Completa los campos obligatorios.');
return;
}

this.guardandoDocumento = true;

const formData = new FormData();
formData.append('Anexos', anexo);
formData.append('Archivo_file', file);
formData.append('idEstacion', this.idEstacion);
formData.append('mes', this.mesActual);
formData.append('year', this.idYear);

window.loader.show();

fetch('/departamento-operativo/comparativo-xml/add-document', {
method: 'POST',
body: formData
})
.then(r => r.json())
.then(data => {
window.loader.hide();
this.guardandoDocumento = false;
if (data.success) {
window.alerts.success('Documento agregado exitosamente.');
this.nuevoAnexo = '';
if (fileInput) fileInput.value = '';
this.cargarDocumentos(this.mesActual);
} else {
if (Array.isArray(data.campos)) data.campos.forEach(campo => { this.errorsDocumento[campo] = true; });
window.alerts.error(data.message || 'Error al subir el archivo.');
}
})
.catch(() => {
window.loader.hide();
this.guardandoDocumento = false;
window.alerts.error('Error al subir el archivo.');
});
},

eliminarDocumento(id) {
window.alerts.confirm('Confirmacion', '\u00bfEst\u00e1s seguro de que deseas eliminar este documento?', () => {
window.loader.show();
fetch('/departamento-operativo/comparativo-xml/delete-document', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id: id })
})
.then(r => r.json())
.then(data => {
window.loader.hide();
if (data.success) {
window.alerts.success('Documento eliminado exitosamente.');
this.cargarDocumentos(this.mesActual);
} else {
window.alerts.error('Error al eliminar el archivo.');
}
})
.catch(() => {
window.loader.hide();
window.alerts.error('Error al eliminar el archivo.');
});
});
},

abrirModalComentarios(mes) {
this.mesActual = mes;
const meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
this.mesActualNombre = meses[mes] || mes;
this.nuevoComentario = '';
this.cargarComentarios(mes);
const offcanvasEl = document.getElementById('offcanvasComentarios');
if (offcanvasEl) {
bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl).show();
}
},

async cargarComentarios(mes) {
try {
const resp = await fetch('/departamento-operativo/comparativo-xml/comments?idEstacion=' + this.idEstacion + '&year=' + this.idYear + '&mes=' + mes);
const data = await resp.json();
if (data.success) {
const raw = data.data || [];
const userId = this.idUsuario;
this.comentarios = raw.map(c => ({
...c,
esMio: c.id_usuario === userId,
usuario_nombre: c.usuario || 'Usuario',
fecha_formateada: (c.fecha || '') + ', ' + (c.hora || '')
}));
this.$nextTick(() => {
const container = this.$refs.chatContainer;
if (container) container.scrollTop = container.scrollHeight;
});
}
} catch (e) {}
},



async agregarComentario() {
if (this.guardandoComentario || !this.nuevoComentario.trim()) return;

this.guardandoComentario = true;

const params = {
idEstacion: this.idEstacion,
idYear: this.idYear,
idMes: this.mesActual,
comentario: this.nuevoComentario
};

try {
const resp = await fetch('/departamento-operativo/comparativo-xml/add-comment', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify(params)
});

const data = await resp.json();
this.guardandoComentario = false;
if (data.success == 1) {
this.nuevoComentario = '';
await this.cargarComentarios(this.mesActual);
await this.cargarSatData(this.idEstacion, this.idYear);
} else {
window.alerts.error('Error al guardar el comentario');
}

}


catch (e) {
this.guardandoComentario = false;
window.alerts.error('Error al guardar el comentario');
}
},





guardarObservaciones() {
if (this.guardandoObservaciones) return;
const contenido = window.editorInstance ? window.editorInstance.getData() : '';

const params = {
idEstacion: this.idEstacion,
idYear: this.idYear,
content: contenido
};

this.guardandoObservaciones = true;
fetch('/departamento-operativo/comparativo-xml/save-observations', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify(params)
})
.then(r => r.json())
.then(data => {
if (data.success != 1) {
window.alerts.error('Error al guardar el comentario');
} else {
window.alerts.success('Observaciones guardadas');
}
})
.catch(() => {
window.alerts.error('Error al guardar el comentario');
})
.finally(() => {
this.guardandoObservaciones = false;
});
},

totalFila(row) {
if (!row || !row.cells) return 0;
return row.cells.reduce((sum, cell) => sum + (parseFloat(cell.valor) || 0), 0);
},

totalGeneral() {
if (!this.rows || !this.rows.length) return 0;
return this.rows.reduce((sum, row) => sum + this.totalFila(row), 0);
},

formatNumber(value) {
const num = this.parseAmount(value);
return num.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
};
}
