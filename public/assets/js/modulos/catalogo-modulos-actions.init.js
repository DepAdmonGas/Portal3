const notyf = new Notyf({
duration: 3000,
position: {
x: 'right',
y: 'top'
},
dismissible: true
});

/* ==========================================
FUNCION GLOBAL PARA RECARGAR TABLA
========================================== */
function recargarTablaCatalogo(){
$('#table-catalogo').DataTable().ajax.reload(null, false);
}


/* ==========================================
FORMULARIO MODULO / SUBMODULO
========================================== */
function moduloPuestoForm() {
return {

idModulo: null,
nombreModulo: '',
nombreURL: '',
modo: 'create', // create | edit
enviando: false,
error: false,

guardar() {

if (!this.nombreModulo.trim() || !this.nombreURL.trim()) {
this.error = true;
return;
}

this.enviando = true;

let modoActual = this.modo;

let url = modoActual === 'edit'
? '/configuracion-sistemas/catalogo-modulos/update'
: '/configuracion-sistemas/catalogo-modulos/create';

let data = {
nombre_modulo: this.nombreModulo,
url: this.nombreURL
};

if (modoActual === 'edit') {
data.id = this.idModulo;
}

axios.post(url, data)

.then(() => {

this.cerrarModal();

Swal.fire({
icon: 'success',
title: modoActual === 'edit' ? 'Actualizado' : 'Creado',
text: modoActual === 'edit'
? 'Módulo actualizado correctamente'
: 'Módulo creado correctamente',
timer: 2000,
showConfirmButton: false
});

notyf.success(
modoActual === 'edit'
? 'Módulo actualizado correctamente'
: 'Módulo creado correctamente'
);

})

.catch(() => {

notyf.error('Ocurrió un error');

})

.finally(() => {

this.enviando = false;

});

},


abrirEditar(modulo) {

this.modo = 'edit';
this.idModulo = modulo.id;
this.nombreModulo = modulo.nombre_modulo;
this.nombreURL = modulo.url;

new bootstrap.Modal(
document.getElementById('modalAgregarModulo')
).show();

},


cerrarModal() {

bootstrap.Modal.getInstance(
document.getElementById('modalAgregarModulo')
).hide();

this.resetForm();

/* recargar tabla */
recargarTablaCatalogo();

},


resetForm() {

this.idModulo = null;
this.nombreModulo = '';
this.nombreURL = '';
this.modo = 'create';
this.error = false;
this.enviando = false;

}

}
}



/* ==========================================
PERMISOS FUNCION DE AGREGAR
========================================== */
$(document).on('click', '.btn-agregar', function () {

let tienePermiso = $(this).data('permiso-agregar');

if (!tienePermiso) {
notyf.error('No cuentas con permisos para agregar');
return;
}

new bootstrap.Modal(
document.getElementById('modalAgregarModulo')
).show();

});



/* ==========================================
PERMISOS FUNCION DE EDITAR
========================================== */
$(document).on('click', '.btn-edit', function () {

let tienePermiso = $(this).data('permiso-editar');

if (!tienePermiso) {
notyf.error('No cuentas con permisos para editar');
return;
}

let modulo = {
id: $(this).data('id'),
nombre_modulo: $(this).data('nombre'),
url: $(this).data('url')
};

let modal = document.getElementById('modalAgregarModulo');
let component = Alpine.$data(modal);

component.abrirEditar(modulo);

});



/* ==========================================
PERMISOS FUNCION DE ELIMINAR
========================================== */
$(document).on('click', '.btn-delete', function () {

const tienePermiso = $(this).data('permiso-eliminar');

if (!tienePermiso) {
notyf.error('No cuentas con permisos para eliminar');
return;
}

const idModulo = $(this).data('id');

Swal.fire({
title: '¿Eliminar módulo/submódulo?',
text: 'Se eliminarán también todos sus submódulos',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'Sí, eliminar',
cancelButtonText: 'Cancelar'
})

.then((result) => {

if (!result.isConfirmed) return;

axios.post('/configuracion-sistemas/catalogo-modulos/delete', {
id: idModulo
})

.then((response) => {

if (response.data.success) {

recargarTablaCatalogo();

Swal.fire({
icon: 'success',
title: 'Eliminado',
text: 'El módulo fue eliminado correctamente',
timer: 2000,
showConfirmButton: false
});

notyf.success('Módulo eliminado correctamente');

} else {

notyf.error(response.data.message || 'No se pudo eliminar');

}

})

.catch(() => {

notyf.error('Error al eliminar el módulo');

});

});

});