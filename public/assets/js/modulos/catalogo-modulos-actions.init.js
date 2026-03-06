const notyf = new Notyf({
duration: 3000,
position: {
x: 'right',
y: 'top'
},
dismissible: true
});


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

let modoActual = this.modo; // 🔥 guardamos antes de resetear

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

// 🔥 Primero cerramos modal (recarga incluida)
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

let tabla = $('#table-modulos').DataTable();

if (tabla) {
tabla.ajax.reload(null, false); // 🔥 recarga sin perder paginación
}

bootstrap.Modal.getInstance(
document.getElementById('modalAgregarModulo')
).hide();

this.resetForm();
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
EDITAR DESDE DATATABLE (DELEGACION)
========================================== */
$(document).on('click', '.btn-edit', function () {

let modulo = {
id: $(this).data('id'),
nombre_modulo: $(this).data('nombre'),
url: $(this).data('url')
};

let modal = document.getElementById('modalAgregarModulo');
let component = Alpine.$data(modal);

component.abrirEditar(modulo);
});