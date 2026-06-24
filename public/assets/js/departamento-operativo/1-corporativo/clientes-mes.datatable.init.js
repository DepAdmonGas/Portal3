document.addEventListener('DOMContentLoaded', () => {

const c = document.getElementById('container');
if (!c) return;

const idYear = parseInt(c.dataset.idYear);
const idMes = parseInt(c.dataset.idMes);
if (!idYear || !idMes) return;

let finalizado = c.dataset.finalizado === 'true';

function formatMoney(val) {
return '$ ' + parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function initTable(selector, data) {
if ($.fn.DataTable && $.fn.DataTable.isDataTable(selector)) {
$(selector).DataTable().destroy();
}

$(selector).DataTable({
data: data,
processing: false,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'asc']],
language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},
lengthMenu: [15, 30, 50, 100],
columns: [
{
title: '#',
data: null,
className: 'text-center align-middle fw-normal',
render: function (data, type, row, meta) {
return meta.row + 1;
}
},
{ title: 'Cuenta', data: 'cuenta', className: 'text-center align-middle' },
{ title: 'Cliente', data: 'nombre', className: 'text-start align-middle' },
{
title: 'Saldo inicio',
data: null,
className: 'text-end',
render: function (data, type) {
if (type !== 'display') return parseFloat(data.saldo_inicial) || 0;

if (!finalizado) {
return `
<div class="position-relative saldo-input">
<span class="position-absolute top-50 start-0 translate-middle-y ps-3">$</span>
<input 
id="ESI${data.id}" 
type="number" 
class="border-0 p-3 text-end w-100 bg-transparent"
value="${parseFloat(data.saldo_inicial).toFixed(2)}"/>
</div>
`;
}

return formatMoney(data.saldo_inicial);
},

createdCell: function (td, cellData, rowData, row, col) {
if (!finalizado) {
$(td).addClass('p-0');
}
}
},
{
title: 'Consumos',
data: 'consumos',
className: 'text-end align-middle',
render: function (data, type) {
if (type !== 'display') return parseFloat(data) || 0;
return formatMoney(data);
}
},
{
title: 'Pagos',
data: 'pagos',
className: 'text-end align-middle',
render: function (data, type) {
if (type !== 'display') return parseFloat(data) || 0;
return formatMoney(data);
}
},
{
title: 'Saldo final',
data: null,
className: 'text-end align-middle',
render: function (data, type) {
const sf = parseFloat(data.saldo_inicial || 0) + parseFloat(data.consumos || 0) - parseFloat(data.pagos || 0);
if (type !== 'display') return sf;
return '<span id="SF' + data.id + '">' + formatMoney(sf) + '</span>';
}
}
],
drawCallback: function () {
if (window.Alpine) Alpine.initTree(this);
}
});
}

function renderGranTotal(totals) {
const t = totals;
document.getElementById('granTotalContainer').innerHTML =
'<div class="card mb-4">' +
'<div class="card-header text-bg-secondary">' +
'<h5 class="mb-0 text-white"><i class="ti ti-sum me-2"></i>Gran Total</h5>' +
'</div>' +
'<div class="card-body p-0">' +
'<div class="table-responsive">' +
'<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">' +
'<thead><tr>' +
'<th class="text-center align-middle"></th>' +
'<th class="text-end align-middle">Saldo inicio</th>' +
'<th class="text-end align-middle">Consumos</th>' +
'<th class="text-end align-middle">Pagos</th>' +
'<th class="text-end align-middle">Saldo final</th>' +
'</tr></thead>' +
'<tbody>' +
'<tr>' +
'<th class="text-start">Crédito</th>' +
'<td class="text-end">' + formatMoney(t.credito.saldo_inicial) + '</td>' +
'<td class="text-end">' + formatMoney(t.credito.consumos) + '</td>' +
'<td class="text-end">' + formatMoney(t.credito.pagos) + '</td>' +
'<td class="text-end">' + formatMoney(t.credito.saldo_final) + '</td>' +
'</tr>' +
'<tr>' +
'<th class="text-start">Débito</th>' +
'<td class="text-end">' + formatMoney(t.debito.saldo_inicial) + '</td>' +
'<td class="text-end">' + formatMoney(t.debito.consumos) + '</td>' +
'<td class="text-end">' + formatMoney(t.debito.pagos) + '</td>' +
'<td class="text-end">' + formatMoney(t.debito.saldo_final) + '</td>' +
'</tr>' +
'</tbody>' +
'<tfoot>' +
'<tr class="fw-semibold table-dark">' +
'<td class="text-start">TOTAL</td>' +
'<td class="text-end">' + formatMoney(t.gran_total.saldo_inicial) + '</td>' +
'<td class="text-end">' + formatMoney(t.gran_total.consumos) + '</td>' +
'<td class="text-end">' + formatMoney(t.gran_total.pagos) + '</td>' +
'<td class="text-end">' + formatMoney(t.gran_total.saldo_final) + '</td>' +
'</tr>' +
'</tfoot>' +
'</table>' +
'</div>' +
'</div>' +
'</div>';
}

fetch('/departamento-operativo/clientes-mes/data/' + idYear + '/' + idMes)
.then(r => r.json())
.then(json => {
if (!json.success) return;
finalizado = json.finalizado;
initTable('#tablaCredito', json.credito);
initTable('#tablaDebito', json.debito);
renderGranTotal(json.totals);
});

$(document).on('keyup', '#tablaCredito input[id^="ESI"], #tablaDebito input[id^="ESI"]', function () {
const id = parseInt(this.id.replace('ESI', ''));
const val = this.value;
if (val === '' || isNaN(parseFloat(val))) return;

fetch('/departamento-operativo/clientes-mes/editar-saldo-inicial', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id, saldo: parseFloat(val) })
})
.then(r => r.json())
.then(json => {
if (!json.success) return;
    const sfEl = document.getElementById('SF' + id);
    if (sfEl) {
        sfEl.textContent = formatMoney(json.saldo_final);
    }
    if (json.totals) {
        renderGranTotal(json.totals);
    }
});
});

});
