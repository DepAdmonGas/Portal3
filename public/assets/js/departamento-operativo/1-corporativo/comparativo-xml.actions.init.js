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
documentos: [],
comentarios: [],
mesActual: 0,
mesActualNombre: '',
nuevoAnexo: '',
nuevoComentario: '',
idUsuario: 0,
editorContent: '',
guardandoComentario: false,
guardandoDocumento: false,

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
                            'fontSize', 'fontFamily', '|',
                            'alignment', '|',
                            'bold', 'italic', 'underline', 'strikethrough', '|',
                            'subscript', 'superscript', '|',
                            'link', 'blockQuote', 'code', 'codeBlock', '|',
                            'bulletedList', 'numberedList', 'todoList', '|',
                            'imageUpload', 'insertTable', 'tableColumn', 'tableRow', 'mergeTableCells', '|',
                            'highlight', 'horizontalLine', 'pageBreak', '|',
                            'removeFormat', 'sourceEditing', 'undo', 'redo'
                        ]
                    },
                    language: 'es',
                    image: {
                        toolbar: [
                            'imageTextAlternative',
                            'imageStyle:alignLeft', 'imageStyle:full', 'imageStyle:alignRight', 'imageStyle:side'
                        ]
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

_inputTimer: null,

formatInput(val) {
return parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
},

_rawFromDom(el) {
return parseFloat(el.value.replace(/,/g, '')) || 0;
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
const v = parseFloat(el.value.replace(/,/g, '')) || 0;
if (this._inputTimer) clearTimeout(this._inputTimer);
const args = [v, row.id, cell.idTipo, this.idYear, this.idEstacion, row.mes, cell.seccion, cell.nombre];
this._inputTimer = setTimeout(() => {
this._inputTimer = null;
this.editarTabla(...args);
}, 400);
},

_onBlur(el, row, cell) {
const v = parseFloat(el.value.replace(/,/g, '')) || 0;
if (this._inputTimer) {
clearTimeout(this._inputTimer);
this._inputTimer = null;
}
this.editarTabla(v, row.id, cell.idTipo, this.idYear, this.idEstacion, row.mes, cell.seccion, cell.nombre);
el.value = this.formatInput(v);
},

editarTabla(valor, idCampo, idTipo, idYear, idEstacion, idMes, idSeccion, idDescripcion) {
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
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify(params)
})
.then(r => r.json())
.then(data => {
if (data.success == 1) {
this.actualizarTotales(idEstacion, idYear);
} else {
window.alerts.error('Error al actualizar');
}
})
.catch(() => {
window.alerts.error('Error al actualizar');
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
const v = parseFloat(el.value.replace(/,/g, '')) || 0;
if (this._inputTimer) clearTimeout(this._inputTimer);
const args = [v, item.id, item.categoria, tipo, this.idYear, this.idEstacion, month.mes];
this._inputTimer = setTimeout(() => {
this._inputTimer = null;
this.actualizarComparativo(...args);
}, 400);
},

_onBlurComparativo(el, item, month, tipo) {
const v = parseFloat(el.value.replace(/,/g, '')) || 0;
if (tipo === 1) item.sat_monto = v;
else item.despacho_monto = v;
item.diferencia = (parseFloat(item.sat_monto) || 0) - (parseFloat(item.despacho_monto) || 0);
if (this._inputTimer) {
clearTimeout(this._inputTimer);
this._inputTimer = null;
}
this.actualizarComparativo(v, item.id, item.categoria, tipo, this.idYear, this.idEstacion, month.mes);
el.value = this.formatInput(v);
},

actualizarComparativo(valor, idCampo, categoria, idTipo, idYear, idEstacion, idMes) {
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
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify(params)
})
.then(r => r.json())
.then(data => {
if (data.success == 1) {
this._recalcularTotalesSat();
} else {
window.alerts.error('Error al actualizar');
}
})
.catch(() => {
window.alerts.error('Error al actualizar');
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
window.location.href = '/departamento-operativo/comparativo-xml/excel/' + this.idEstacion + '/' + this.idYear;
}
},

verSeguimiento() {
    window.location.href = '/departamento-operativo/corporativo/comparativo-xml/seguimiento/' + this.idYear + '/' + this.idEstacion;
},

abrirModalDocumentos(mes) {
this.mesActual = mes;
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
const anexo = this.nuevoAnexo;
const fileInput = this.$refs.fileInput;
const file = fileInput?.files[0];

if (!anexo) {
window.alerts.error('El campo Anexo es requerido');
return;
}
if (!file) {
window.alerts.error('Debe seleccionar un archivo');
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
window.alerts.success('Comentario agregado exitosamente');
this.nuevoComentario = '';
await this.cargarComentarios(this.mesActual);
await this.cargarSatData(this.idEstacion, this.idYear);
} else {
window.alerts.error('Error al guardar el comentario');
}
} catch (e) {
this.guardandoComentario = false;
window.alerts.error('Error al guardar el comentario');
}
},

guardarObservaciones() {
const contenido = window.editorInstance ? window.editorInstance.getData() : '';

const params = {
idEstacion: this.idEstacion,
idYear: this.idYear,
content: contenido
};

window.loader.show();

fetch('/departamento-operativo/comparativo-xml/save-observations', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify(params)
})
.then(r => r.json())
.then(data => {
window.loader.hide();
if (data.success == 1) {
window.alerts.success('Comentario guardado exitosamente.');
} else {
window.alerts.error('Error al guardar el comentario');
}
})
.catch(() => {
window.loader.hide();
window.alerts.error('Error al guardar el comentario');
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
const num = parseFloat(value) || 0;
return num.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
};
}
