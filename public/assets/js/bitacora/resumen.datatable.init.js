document.addEventListener('DOMContentLoaded', () => {

var table = null;

function getBaseUrl() {
var el = document.getElementById('container');
return el && el.dataset.baseUrl ? el.dataset.baseUrl : '/bitacora-aditivo';
}

function initTable() {
return $('#table-aditivo-resumen').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
paging: false,
searching: false,
info: false,
ordering: false,
language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},
ajax: {
url: getBaseUrl() + '/resumen/datatable',
type: 'GET',
dataSrc: function (json) {
return json.data;
}
},
columns: [
{ data: 'estacion', className: 'text-center align-middle' },
{
data: 'gasolina', className: 'text-center align-middle',
render: function (data, type) {
if (type !== 'display') return data;
return Number(data || 0).toLocaleString('es-MX') + ' <small class="text-primary">(Galones)</small>';
}
},
{
data: 'diesel', className: 'text-center align-middle',
render: function (data, type) {
if (type !== 'display') return data;
return Number(data || 0).toLocaleString('es-MX') + ' <small class="text-primary">(Galones)</small>';
}
}
],
footerCallback: function (row, data, start, end, display) {
var api = this.api();
var totalGasolina = api.column(1).data().reduce(function (acc, val) {
return acc + (Number(val) || 0);
}, 0);
var totalDiesel = api.column(2).data().reduce(function (acc, val) {
return acc + (Number(val) || 0);
}, 0);
document.getElementById('total-gasolina').innerHTML = totalGasolina.toLocaleString('es-MX') + ' <small class="text-primary">(Galones)</small>';
document.getElementById('total-diesel').innerHTML = totalDiesel.toLocaleString('es-MX') + ' <small class="text-primary">(Galones)</small>';
}
});
}

function getOrCreateTable() {
if (!table) {
table = initTable();
}
return table;
}

getOrCreateTable();

});