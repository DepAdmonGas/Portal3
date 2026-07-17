document.addEventListener('DOMContentLoaded', () => {
    ModuleStationSelector.init('despacho-ventas', {
        customReload: function (ms) {
            var v = ms.getValue();
            if (v.id_estacion === null && v.id_depto === null) {
                ms.hideBadge();
                document.getElementById('despacho-ventas-content').style.display = 'none';
                document.getElementById('despacho-ventas-empty-message').style.display = '';
                return;
            }
            document.getElementById('despacho-ventas-empty-message').style.display = 'none';
            document.getElementById('despacho-ventas-content').style.display = '';
            document.dispatchEvent(new CustomEvent('dv:station-change'));
        }
    });
});

document.addEventListener('alpine:init', () => {
Alpine.data('despachoVentasComponent', () => ({
loading: false,
error: null,
dias: [],
totales: {
ventas: { l1: 0, p1: 0, l2: 0, p2: 0, l3: 0, p3: 0, lt: 0, pt: 0 },
despacho: { l1: 0, p1: 0, l2: 0, p2: 0, l3: 0, p3: 0, lt: 0, pt: 0 },
diff: { l1: 0, p1: 0, l2: 0, p2: 0, l3: 0, p3: 0, lt: 0, pt: 0 }
},
productos: [],
prodCols: [],
subHeaders: [],
totalRows: [],
idYear: 0,
idMes: 0,

init() {
const container = document.getElementById('container');
if (container) {
this.idYear = parseInt(container.dataset.idYear) || 0;
this.idMes = parseInt(container.dataset.idMes) || 0;
}
const idEstacion = parseInt(this.$el.dataset.idEstacion) || 0;
document.addEventListener('dv:station-change', () => this.loadData());
if (idEstacion) this.loadData();
},

loadData() {
if (!this.idYear || !this.idMes) return;
this.loading = true;
this.error = null;
var self = this;
axios.get('/departamento-operativo/despacho-ventas/data', {
params: { id_year: this.idYear, id_mes: this.idMes }
}).then(function (res) {
if (res.data.success) {
self.dias = res.data.dias.map(function (d) {
return {
...d,
ventas: { ...d.ventas },
despacho: { ...d.despacho },
diff: { ...d.diff }
};
});
self.productos = res.data.productos || [];
self.totales = res.data.totales || self.totales;
self.buildProdCols();
self.buildTotalRows();
} else {
self.error = res.data.message || 'Error al cargar datos';
}
}).catch(function () {
self.error = 'Error de conexión al cargar datos';
}).then(function () {
self.loading = false;
});
},

buildProdCols() {
this.prodCols = [];
this.subHeaders = [];
var self = this;
this.productos.forEach(function (_, i) {
self.prodCols.push({ key: 'l' + (i + 1), tipo: 'l', col: i + 1 });
self.prodCols.push({ key: 'p' + (i + 1), tipo: 'p', col: i + 4 });
self.subHeaders.push({ label: 'Litros', style: self.coloresProducto(i) });
self.subHeaders.push({ label: 'Pesos', style: self.coloresProducto(i) });
});
},

buildTotalRows() {
this.totalRows = [
{ type: 'ventas', label: 'Ventas', thClass: 'table-info fw-semibold', fecha: 'Total', showDate: true, data: this.totales.ventas },
{ type: 'despacho', label: 'Despacho', thClass: 'fw-semibold', fecha: '', showDate: false, data: this.totales.despacho },
{ type: 'diff', label: 'Diferencia', thClass: 'table-success fw-semibold', fecha: '', showDate: false, data: this.totales.diff },
];
},

coloresProducto(i) {
var cols = ['#74bc1f', '#e01883', '#5c108c'];
return cols[i] || '#6c757d';
},

formatNumber(val) {
return parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
},

formatMoney(val) {
return '$ ' + parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
},

formatInput(val) {
return parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
},

formatValue(pc, val) {
return pc.tipo === 'l' ? this.formatNumber(val) : this.formatMoney(val);
},

esNegativo(val) {
return parseFloat(val || 0) < 0 ? 'text-danger fw-bold' : '';
},

actualizarDespacho(dia, columna, el) {
var v = parseFloat(el.value.replace(/,/g, '')) || 0;
var key = '';
if (columna === 1) key = 'l1';
else if (columna === 2) key = 'l2';
else if (columna === 3) key = 'l3';
else if (columna === 4) key = 'p1';
else if (columna === 5) key = 'p2';
else if (columna === 6) key = 'p3';
if (!key) return;
dia.despacho[key] = v;
el.value = this.formatInput(v);
this.recalcular(dia);
fetch('/departamento-operativo/despacho-ventas/update-cell', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id_dia: dia.id_dia, valor: v, despacho: columna })
}).then(function (r) { return r.json(); }).then(function (data) {
if (!data.success) console.error('Error al guardar');
});
},

recalcular(dia) {
for (var i = 1; i <= 3; i++) {
dia.diff['l' + i] = (dia.ventas['l' + i] || 0) - (dia.despacho['l' + i] || 0);
dia.diff['p' + i] = (dia.ventas['p' + i] || 0) - (dia.despacho['p' + i] || 0);
}
dia.diff.lt = (dia.ventas.lt || 0) - (dia.despacho.lt || 0);
dia.diff.pt = (dia.ventas.pt || 0) - (dia.despacho.pt || 0);
this.recalcularTotales();
this.buildTotalRows();
},

recalcularTotales() {
var t = { ventas: {}, despacho: {}, diff: {} };
var keys = ['l1', 'p1', 'l2', 'p2', 'l3', 'p3', 'lt', 'pt'];
var self = this;
keys.forEach(function (k) {
t.ventas[k] = 0;
t.despacho[k] = 0;
t.diff[k] = 0;
});
this.dias.forEach(function (d) {
keys.forEach(function (k) {
t.ventas[k] += d.ventas[k] || 0;
t.despacho[k] += d.despacho[k] || 0;
t.diff[k] += d.diff[k] || 0;
});
});
this.totales = t;
},


}));
});
