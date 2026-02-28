/* ===============================
FUNCIÓN PARA CARGA NOMBRE DE LOS MODULOS O SUBMODULOS EN EL MODAL
=================================*/
document.addEventListener('click', function(e) {

if (e.target.closest('.btnAbrirAsignarSubmodulo')) {
let button = e.target.closest('.btnAbrirAsignarSubmodulo');

document.getElementById('idModuloPrincipal').value =
button.getAttribute('data-id-estructura');

document.getElementById('nombreModuloPadre').value =
button.getAttribute('data-nombre');
}

// 🔹 Asignar Submódulo
if (e.target.closest('.btnAbrirAsignarSubmodulo')) {
let button = e.target.closest('.btnAbrirAsignarSubmodulo');

window.dispatchEvent(new CustomEvent('asignar-submodulo', {
detail: {
idEstructura: button.dataset.idEstructura,
nombre: button.dataset.nombre
}
}));
}

// 🔹 Editar Permisos
if (e.target.closest('.btnEditarPermisos')) {
let button = e.target.closest('.btnEditarPermisos');

window.dispatchEvent(new CustomEvent('editar-permisos', {
detail: {
idEstructura: button.dataset.idEstructura,
idUsuario: button.dataset.idUsuario,
nombre: button.dataset.nombre
}
}));
}

});

/* ===============================
FUNCIÓN GLOBAL REUTILIZABLE
=================================*/
function recargarEstructura(idUsuario) {

axios.get('/configuracion-sistemas/configuracion-modulos-usuario/' + idUsuario)
.then(response => {

const parser = new DOMParser();
const doc = parser.parseFromString(response.data, 'text/html');

const nuevoContenedor = doc.querySelector('#estructuraContainer');
const contenedorActual = document.querySelector('#estructuraContainer');

if (nuevoContenedor && contenedorActual) {
contenedorActual.innerHTML = nuevoContenedor.innerHTML;
} else {
console.warn('No se encontró #estructuraContainer.');
}

})
.catch(error => {
console.log('Error al recargar estructura:', error);
});
}


/* ===============================
NOTYF
=================================*/
const notyf = new Notyf({
duration: 3000,
position: {
x: 'right',
y: 'top'
},
dismissible: true
});


/* ===============================
AGREGAR MODULOS 
=================================*/
function moduloUsuarioForm() {
return {

idModulo: '',
enviando: false,
error: false,

guardar() {

if (!this.idModulo) {
this.error = true;
return;
}

this.enviando = true;

const modal = document.getElementById('modalAgregarModulo');
const idUsuario = modal.dataset.idUsuario;

axios.post('/configuracion-sistemas/configuracion-modulos-usuario/modulos/create', {
id_usuario: idUsuario,   
id_modulo_principal: this.idModulo
})
.then(response => {

this.cerrarModal();

Swal.fire({
icon: 'success',
title: 'Creado',
text: 'Módulo asignado correctamente al puesto',
timer: 2000,
showConfirmButton: false
});

notyf.success('Módulo asignado correctamente');

// 👇 Ahora llamamos función GLOBAL
recargarEstructura(idUsuario);

})
.catch(error => {

console.log(error.response?.data);

Swal.fire({
icon: 'error',
title: 'Error',
text: error.response?.data?.message ?? 'Error del servidor'
});

})
.finally(() => {
this.enviando = false;
});
},

cerrarModal() {
const modalElement = document.getElementById('modalAgregarModulo');
const modalInstance = bootstrap.Modal.getInstance(modalElement);
if (modalInstance) {
modalInstance.hide();
}
this.resetForm();
},

resetForm() {
this.idModulo = '';
this.error = false;
this.enviando = false;
}

}
}


/* ===============================
AGREGAR SUBMODULOS 
=================================*/
function submoduloUsuarioForm() {
return {

idModulo: '',
enviando: false,
error: false,

guardar() {

if (!this.idModulo) {
this.error = true;
return;
}

this.enviando = true;

const modal = document.getElementById('modalAgregarSubmodulo');
const idUsuario = modal.dataset.idUsuario;
const idModuloPrincipal = document.getElementById('idModuloPrincipal').value;

axios.post('/configuracion-sistemas/configuracion-modulos-usuario/submodulos/create', {
id_usuario: idUsuario,
id_modulo: this.idModulo,
id_modulo_principal: idModuloPrincipal
})
.then(response => {

this.cerrarModal();

Swal.fire({
icon: 'success',
title: 'Creado',
text: 'Submódulo asignado correctamente',
timer: 2000,
showConfirmButton: false
});

notyf.success('Módulo / Submódulo asignado correctamente');

recargarEstructura(idUsuario);

})
.catch(error => {

Swal.fire({
icon: 'error',
title: 'Error',
text: error.response?.data?.message ?? 'Error del servidor'
});

})
.finally(() => {
this.enviando = false;
});

},

cerrarModal() {
const modalElement = document.getElementById('modalAgregarSubmodulo');
const modalInstance = bootstrap.Modal.getInstance(modalElement);
if (modalInstance) {
modalInstance.hide();
}
this.resetForm();
},

resetForm() {
this.idModulo = '';
this.error = false;
this.enviando = false;
}

}
}


/* ===============================
ELIMINAR MODULOS O SUBMODULOS 
=================================*/
document.addEventListener('click', function (e) {

const btn = e.target.closest('.btn-delete');
if (!btn || btn.classList.contains('disabled')) return;

// ✅ Obtener correctamente los data-attributes
const idEstructura = btn.dataset.idEstructura;
const idUsuario = btn.dataset.idUsuario;
const idModulo = btn.dataset.idModulo;
const idModuloPrincipal = btn.dataset.idModuloPrincipal;

// 🔎 Validación extra por seguridad
if (!idEstructura || !idUsuario) {
console.error('Datos inválidos:', {
idEstructura,
idUsuario,
idModulo,
idModuloPrincipal
});

Swal.fire({
icon: 'error',
title: 'Error',
text: 'No se pudo obtener la información del módulo'
});
return;
}

Swal.fire({
title: '¿Eliminar módulo?',
text: 'Se eliminarán también todos sus submódulos',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'Sí, eliminar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#d33',
cancelButtonColor: '#6c757d'
}).then((result) => {

if (!result.isConfirmed) return;

// 🔒 Deshabilitar botón mientras procesa
btn.classList.add('disabled');

axios.post('/configuracion-sistemas/configuracion-modulos-usuario/submodulos/delete', {
idEstructura: parseInt(idEstructura),
idUsuario: parseInt(idUsuario),
idModulo: parseInt(idModulo),
idModuloPrincipal: parseInt(idModuloPrincipal)
})
.then(response => {

Swal.fire({
icon: 'success',
title: 'Eliminado',
text: response.data.message ?? 'Eliminado correctamente',
timer: 2000,
showConfirmButton: false
});

notyf.success('Eliminado correctamente');

// 🔄 Recargar estructura
recargarEstructura(idUsuario);

})
.catch(error => {

btn.classList.remove('disabled');

const mensaje =
error.response?.data?.message ||
'Error al eliminar';

Swal.fire({
icon: 'error',
title: 'Error',
text: mensaje
});

notyf.error(mensaje);

});

});

});


/* ===============================
MODAL PERMISOS DEL MODULO
=================================*/
function permisosForm() {
return {

idPermiso: null,  
idEstructura: null,
idUsuario: null,
nombreModulo: '',

ver:false,
descargar:false,
agregar:false,
editar:false,
eliminar:false,

enviando:false,
cargando:false,

/* =========================
ABRIR MODAL Y CARGAR DATA
========================= */
/* =========================
ABRIR MODAL Y CARGAR DATA
========================= */
async abrirEditar(data) {

this.idEstructura = data.idEstructura;
this.idUsuario = data.idUsuario;
this.nombreModulo = data.nombre;

this.cargando = true;

try {

const response = await axios.get(
`/configuracion-sistemas/configuracion-modulos-usuario/${this.idUsuario}/permisos-modulos/${this.idEstructura}`
);

const permisos = response.data ?? {};
this.idPermiso = permisos.id ?? null;
this.ver = permisos.ver == 1;
this.descargar = permisos.descargar == 1;
this.agregar = permisos.agregar == 1;
this.editar = permisos.editar == 1;
this.eliminar = permisos.eliminar == 1;

} catch (error) {

console.error(error);

Swal.fire({
icon: 'error',
title: 'Error',
text: 'Error al cargar permisos'
});

}

this.cargando = false;

const modalElement = document.getElementById('modalEditarPermisos');
const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
modal.show();

},


/* =========================
GUARDAR PERMISOS
========================= */
async guardar() {

this.enviando = true;

try {
    
const payload = {
id_usuario: this.idUsuario,
id_modulo_estructura: this.idEstructura,
ver: this.ver ? 1 : 0,
descargar: this.descargar ? 1 : 0,
agregar: this.agregar ? 1 : 0,
editar: this.editar ? 1 : 0,
eliminar: this.eliminar ? 1 : 0
};

if (this.idPermiso) {

await axios.put(
`/configuracion-sistemas/configuracion-modulos-usuario-permiso/${this.idPermiso}`,
payload
);

} 

// 🔥 ALERTA DE ÉXITO
Swal.fire({
icon: 'success',
title: 'Permisos actualizados',
text: 'Los permisos fueron actualizados correctamente',
timer: 1800,
showConfirmButton: false
});

bootstrap.Modal.getInstance(
document.getElementById('modalEditarPermisos')
).hide();

this.resetForm();

} catch (error) {

console.log("STATUS:", error.response?.status);
console.log("DATA:", error.response?.data);
console.error(error);

Swal.fire({
icon: 'error',
title: 'Error',
text: 'No se pudieron actualizar los permisos'
});

}

this.enviando = false;

},


/* =========================
RESET FORM
========================= */
resetForm() {
this.idPermiso = null;
this.idEstructura = null;
this.idUsuario = null;
this.nombreModulo = '';
this.ver=false;
this.descargar=false;
this.agregar=false;
this.editar=false;
this.eliminar=false;
this.cargando=false;
this.enviando=false;

}

}
}