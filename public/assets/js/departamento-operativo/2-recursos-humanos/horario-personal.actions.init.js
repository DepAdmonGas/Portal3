document.addEventListener('alpine:init', () => {

Alpine.data('horarioPersonalComponent', () => ({

puedeEditar: false,
puedeEliminar: false,
puedeDescargar: false,
nombreContexto: '',

init() {
var c = document.getElementById('container');
if (!c) return;

this.puedeEditar = c.dataset.puedeEditar === 'true';
this.puedeEliminar = c.dataset.puedeEliminar === 'true';
this.puedeDescargar = c.dataset.puedeDescargar === 'true';

var self = this;
document.addEventListener('hp:estacion-cambio', () => {
self.actualizarContexto();
});

this.actualizarContexto();

window.horarioPersonalComponentInstance = this;
},

actualizarContexto() {
var sel = document.getElementById('module-station-selector-horario-personal');
if (sel && sel.selectedIndex >= 0) {
this.nombreContexto = sel.options[sel.selectedIndex].text || '';
} else {
this.nombreContexto = '';
}
},

async guardarHorario(idPersonal, dia, horario) {
if (!this.puedeEditar) return;

await this.createAction({
url: '/departamento-operativo/recursos-humanos/horario-personal/editar',
data: { id_personal: idPersonal, dia: dia, horario: horario },
table: '#tabla-horario-personal'
});
},

async eliminarHorarioPersonal(idPersonal, nombre) {
if (!this.puedeEliminar) return;

await this.deleteAction({
url: '/departamento-operativo/recursos-humanos/horario-personal/eliminar',
id: idPersonal,
name: 'el horario de ' + nombre,
table: '#tabla-horario-personal'
});
},

}));
});
