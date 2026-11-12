document.addEventListener('DOMContentLoaded', () => {

const c = document.getElementById('container');
if (!c) return;

const idYear = parseInt(c.dataset.idYear);
const idMes = parseInt(c.dataset.idMes);
if (!idYear || !idMes) return;

let finalizado = c.dataset.finalizado === 'true';
const selectedRows = {};
const clienteCollator = new Intl.Collator('es-MX', { sensitivity: 'base', numeric: true });

if ($.fn.DataTable?.ext?.type?.order) {
$.fn.DataTable.ext.type.order['portal3-spanish-asc'] = (a, b) => clienteCollator.compare(String(a ?? ''), String(b ?? ''));
$.fn.DataTable.ext.type.order['portal3-spanish-desc'] = (a, b) => clienteCollator.compare(String(b ?? ''), String(a ?? ''));
}

function formatMoney(val) {
return '$ ' + parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatEditableAmount(value) {
const raw = String(value ?? '');
const cursor = document.activeElement?.selectionStart ?? raw.length;
const numericBeforeCursor = raw.slice(0, cursor).replace(/[^0-9]/g, '').length;
let normalized = raw.replace(/,/g, '').replace(/[^0-9.-]/g, '');
const negative = normalized.startsWith('-');
const numeric = normalized.replace(/-/g, '');
if (!numeric) return { formatted: negative ? '-' : '', numericBeforeCursor };
const parts = numeric.split('.');
normalized = parts[0] + (parts.length > 1 ? '.' + parts.slice(1).join('') : '');
const [integerPart = '', ...decimalParts] = normalized.split('.');
const integer = integerPart.replace(/^0+(?=\d)/, '') || '0';
const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
const formatted = (negative ? '-' : '') + grouped + (normalized.includes('.') ? '.' + decimalParts.join('') : '');

return { formatted, numericBeforeCursor };
}

function restoreAmountCursor(input, numericBeforeCursor) {
if (numericBeforeCursor === 0) {
const position = input.value.startsWith('-') ? 1 : 0;
input.setSelectionRange(position, position);
return;
}
let digitsSeen = 0;
let position = input.value.length;
for (let i = 0; i < input.value.length; i += 1) {
if (/\d/.test(input.value[i])) digitsSeen += 1;
if (digitsSeen >= numericBeforeCursor) {
position = i + 1;
break;
}
}
input.setSelectionRange(position, position);
}

function parseEditableAmount(value) {
const normalized = String(value ?? '').replace(/,/g, '').trim();
if (!normalized || normalized === '-' || normalized === '.' || normalized === '-.') return null;
const amount = Number(normalized);
return Number.isFinite(amount) ? amount : null;
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
{
title: 'Cliente',
data: 'nombre',
type: 'portal3-spanish',
className: 'text-start align-middle',
render: function (data) {
return String(data ?? '').trim();
}
},
{
title: 'Saldo inicio',
data: null,
className: 'text-center',
render: function (data, type) {
if (type !== 'display') return parseFloat(data.saldo_inicial) || 0;

if (!finalizado) {
return `
<div class="position-relative saldo-input">
<span class="position-absolute top-50 start-0 translate-middle-y ps-3">$</span>
<input
id="ESI${data.id}"
type="text"
inputmode="decimal"
autocomplete="off"
aria-label="Saldo inicial de ${String(data.nombre || '').replace(/[&<>\"']/g, '')}"
class="border-0 p-3 text-center w-100 bg-transparent"
value="${(parseFloat(data.saldo_inicial) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}"/>
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
className: 'text-center align-middle',
render: function (data, type) {
if (type !== 'display') return parseFloat(data) || 0;
return formatMoney(data);
}
},
{
title: 'Pagos',
data: 'pagos',
className: 'text-center align-middle',
render: function (data, type) {
if (type !== 'display') return parseFloat(data) || 0;
return formatMoney(data);
}
},
{
title: 'Saldo final',
data: null,
className: 'text-center align-middle',
render: function (data, type) {
const saldoFinal = parseFloat(data.saldo_final || 0);
if (type !== 'display') return saldoFinal;
return '<span id="SF' + data.id + '">' + formatMoney(saldoFinal) + '</span>';
}
}
],
rowCallback: function (row, rowData) {
$(row).addClass('pointer').toggleClass('selected', selectedRows[selector] === rowData.id);
},
drawCallback: function () {
if (window.Alpine) Alpine.initTree(this);
}
});

$(selector + ' tbody')
.off('click.rowSelection')
.on('click.rowSelection', 'tr', function () {
const rowData = $(selector).DataTable().row(this).data();
if (!rowData) return;

selectedRows[selector] = rowData.id;
$(selector + ' tbody tr').removeClass('selected');
$(this).addClass('selected');
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
'<div class="table-responsive overflow-x-auto overflow-y-hidden">' +
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

const pendingSaves = new Map();

$(document).on('input', '#tablaCredito input[id^="ESI"], #tablaDebito input[id^="ESI"]', function () {
const input = this;
const id = parseInt(input.id.replace('ESI', ''), 10);
const { formatted, numericBeforeCursor } = formatEditableAmount(input.value);
input.value = formatted;
restoreAmountCursor(input, numericBeforeCursor);

const saldo = parseEditableAmount(formatted);
if (!id || saldo === null) return;

clearTimeout(pendingSaves.get(id));
pendingSaves.set(id, setTimeout(() => {
fetch('/departamento-operativo/clientes-mes/editar-saldo-inicial', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id, saldo })
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
})
.catch(() => {});
}, 400));
});

});
