document.addEventListener('alpine:init', () => {

Alpine.data('yearMesComponent', () => ({

year: null,
mes: null,

init() {
const container = document.getElementById('container');

if (container) {
this.year = container.dataset.year || null;
this.mes = container.dataset.mes || null;
}
},

cambiarYearMes(year, mes) {
const url = `/departamento-operativo/corporativo/corte-diario/${year}/${mes}`;

history.replaceState(
{ year, mes },
"",
url
);

location.reload();
}

}));

    




});