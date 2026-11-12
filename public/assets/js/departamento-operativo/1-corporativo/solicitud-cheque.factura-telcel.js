function FacturaTelcelFecha(fecha) {
if (!fecha) return '';
var d = new Date(fecha);
var meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
return d.getDate() + ' de ' + meses[d.getMonth()] + ' del ' + d.getFullYear() + ', ' + d.toLocaleTimeString('es-MX', {hour:'numeric', minute:'2-digit'});
}

document.addEventListener('alpine:init', () => {
Alpine.data('facturaTelcelComponent', () => ({
idYear: 0,
idMes: 0,
idEstacion: 0,
directorio: [],
facturas: [],
comentario: '',
editandoDirectorio: null,
formCuenta: '',
formPuesto: '',
formClave: '',
formDetalle: '',
guardando: false,
erroresDirectorio: { cuenta: false, puesto: false, clave: false },
erroresFactura: { detalle: false, documento: false },

initData(year, mes, estacion, comentario) {
this.idYear = year;
this.idMes = mes;
this.idEstacion = estacion;
this.comentario = comentario || '';
    this.cargarDirectorio();
    this.cargarFacturas();
},

async cargarDirectorio() {
const resp = await fetch('/departamento-operativo/solicitud-cheque/factura-telcel/directorio/' + this.idYear + '/' + this.idMes + '/' + this.idEstacion);
const json = await resp.json();
if (json.success) this.directorio = json.data || [];
},

async cargarFacturas() {
const resp = await fetch('/departamento-operativo/solicitud-cheque/factura-telcel/list/' + this.idYear + '/' + this.idMes + '/' + this.idEstacion);
const json = await resp.json();
if (json.success) {
this.facturas = (json.data || []).map(function(f) {
f.fecha_formateada = FacturaTelcelFecha(f.fecha_hora);
return f;
});
}
},

abrirModalDirectorio() {
this.editandoDirectorio = null;
this.formCuenta = '';
this.formPuesto = '';
this.formClave = '';
this.erroresDirectorio = { cuenta: false, puesto: false, clave: false };
new bootstrap.Modal(document.getElementById('modalDirectorio')).show();
},

editarDirectorio(d) {
this.editandoDirectorio = { id: d.id };
this.formCuenta = d.cuenta;
this.formPuesto = d.puesto;
this.formClave = d.clave;
this.erroresDirectorio = { cuenta: false, puesto: false, clave: false };
new bootstrap.Modal(document.getElementById('modalDirectorio')).show();
},

async guardarDirectorio() {
this.erroresDirectorio = {
cuenta: !this.formCuenta.trim(),
puesto: !this.formPuesto.trim(),
clave: !this.formClave.trim()
};
if (Object.values(this.erroresDirectorio).some(Boolean)) {
if (window.Notify) Notify.error('Completa los campos obligatorios.');
return;
}
this.guardando = true;
try {
var url, method, body;
if (this.editandoDirectorio) {
url = '/departamento-operativo/solicitud-cheque/factura-telcel/update-directorio';
method = 'POST';
body = JSON.stringify({
id: this.editandoDirectorio.id,
cuenta: this.formCuenta,
puesto: this.formPuesto,
clave: this.formClave
});
} else {
url = '/departamento-operativo/solicitud-cheque/factura-telcel/store-directorio';
method = 'POST';
body = JSON.stringify({
idYear: this.idYear,
idMes: this.idMes,
idEstacion: this.idEstacion,
cuenta: this.formCuenta,
puesto: this.formPuesto,
clave: this.formClave
});
}
const resp = await fetch(url, { method: method, headers: {'Content-Type': 'application/json'}, body: body });
const json = await resp.json();
if (json.success) {
bootstrap.Modal.getInstance(document.getElementById('modalDirectorio')).hide();
this.erroresDirectorio = { cuenta: false, puesto: false, clave: false };
this.cargarDirectorio();
if (window.Notify) Notify.success(json.message);
} else {
if (json.validation) this.erroresDirectorio = { ...this.erroresDirectorio, ...json.validation };
if (window.Notify) Notify.error(json.message || 'Error');
}
} catch (e) {
console.error(e);
} finally {
this.guardando = false;
}
},

eliminarDirectorio(id) {
// replaced by deleteAction() in view
},

abrirModalFacturaTelcel() {
this.formDetalle = '';
this.erroresFactura = { detalle: false, documento: false };
if (this.$refs.facturaFileInput) this.$refs.facturaFileInput.value = '';
new bootstrap.Modal(document.getElementById('modalFacturaTelcel')).show();
},

async guardarFacturaTelcel() {
var fileInput = this.$refs.facturaFileInput;
this.erroresFactura.detalle = !this.formDetalle;
this.erroresFactura.documento = !fileInput || !fileInput.files || !fileInput.files[0];
if (this.erroresFactura.detalle || this.erroresFactura.documento) {
if (window.Notify) Notify.error('Completa los campos obligatorios.');
return;
}
this.guardando = true;
try {
const fd = new FormData();
fd.append('idEstacion', this.idEstacion);
fd.append('idYear', this.idYear);
fd.append('idMes', this.idMes);
fd.append('detalle', this.formDetalle);
fd.append('documento', fileInput.files[0]);
const resp = await fetch('/departamento-operativo/solicitud-cheque/factura-telcel/store', { method: 'POST', body: fd });
const json = await resp.json();
if (json.success) {
bootstrap.Modal.getInstance(document.getElementById('modalFacturaTelcel')).hide();
this.formDetalle = '';
this.erroresFactura = { detalle: false, documento: false };
if (fileInput) fileInput.value = '';
this.cargarFacturas();
if (window.Notify) Notify.success('Registro agregado exitosamente.');
} else {
if (json.validation) {
this.erroresFactura.detalle = !!json.validation.detalle;
this.erroresFactura.documento = !!json.validation.documento;
}
if (window.Notify) Notify.error(json.message || 'Error');
}
} catch (e) {
console.error(e);
} finally {
this.guardando = false;
}
},

eliminarFacturaTelcel(id) {
// replaced by deleteAction() in view
},

__timerComentario: null,

guardarComentarioDebounced() {
var self = this;
if (this.__timerComentario) clearTimeout(this.__timerComentario);
this.__timerComentario = setTimeout(function() { self.guardarComentario(); }, 500);
},

download(tipo, file) {
if (!file) return;
window.open('/download?tipo=' + encodeURIComponent(tipo) + '&file=' + encodeURIComponent(file), '_blank');
},

async guardarComentario() {
try {
const resp = await fetch('/departamento-operativo/solicitud-cheque/factura-telcel/store-comentario', {
method: 'POST',
headers: {'Content-Type': 'application/json'},
body: JSON.stringify({
idYear: this.idYear,
idMes: this.idMes,
idEstacion: this.idEstacion,
comentario: this.comentario
})
});
const json = await resp.json();
if (!json.success && window.Notify) {
Notify.error(json.message || 'Error al guardar');
}
} catch (e) {
console.error(e);
}
},
}));
});
