document.addEventListener('DOMContentLoaded', () => {

let permisos = {};
const messageEl = document.getElementById('aditivo-empty-message');
const contentEl = document.getElementById('aditivo-content');
var table = null;

function getBaseUrl() {
var el = document.getElementById('container');
return el && el.dataset.baseUrl ? el.dataset.baseUrl : '/bitacora-aditivo';
}

function esImportacion() {
var el = document.getElementById('container');
return el && el.dataset.importacion === '1';
}

function showEmptyMessage() {
if (contentEl) contentEl.style.display = 'none';
if (messageEl) messageEl.style.display = '';
}

function showTable() {
if (contentEl) contentEl.style.display = '';
if (messageEl) messageEl.style.display = 'none';
}

function initTable() {
return $('#table-aditivo').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: !esImportacion(),
order: [[0, 'desc']],
language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},
ajax: {
url: getBaseUrl() + '/datatable',
type: 'GET',
dataSrc: function (json) {
permisos = json.permisos;
return json.data;
}
},
columns: (function () {
    const cols = [
        {
            data: 'folio', className: 'text-center align-middle',
            render: function(data, type, row) {
                return '00' + data;
            }
        },
        {
            data: 'fecha', className: 'text-center align-middle',
            render: function (data, type) {
                if (!data) return '';
                const fecha = new Date(data);
                const formateada = fecha.toLocaleDateString('es-MX', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });
                if (type === 'display') return formateada;
                if (type === 'filter') return formateada + ' ' + data;
                return data;
            }
        },
        {
            data: 'litros', className: 'text-center align-middle',
            render: function (data, type) {
                if (!data) return '0';
                if (type !== 'display') return data;
                return Number(data).toLocaleString('es-MX');
            }
        },
        { data: 'no_factura', className: 'text-center align-middle', },
        { data: 'producto', className: 'text-center align-middle', },
        { data: 'galones', className: 'text-center align-middle', },
        { data: 'inventario_fisico', className: 'text-center align-middle', }
    ];
    if (!esImportacion()) {
        cols.push({
            data: 'estado', className: 'text-center align-middle',
            render: function (data) {
                return data == 1
                    ? '<span class="badge text-bg-success">Activo</span>'
                    : '<span class="badge text-bg-danger">Eliminado</span>';
            }
        });
        cols.push({
            data: null,
            orderable: false,
            searchable: false, 
            className: 'text-center align-middle',
            render: function(data, type, row) {
                const disabled = row.estado === 0;
                const noEdit = !permisos.editar || disabled;
                const noDelete = !permisos.eliminar || disabled;
                return `
                <div x-data="actions()" class="d-flex gap-1 justify-content-center">
                <div class="dropdown dropstart">
                <a href="javascript:void(0)" data-bs-toggle="dropdown">
                <i class="ti ti-dots-vertical fs-6"></i>
                </a>
                <ul class="dropdown-menu">
                <li>
                <a href="javascript:void(0)"
                class="dropdown-item pointer ${noEdit ? 'disabled' : ''}"
                ${noEdit ? '' : `
                    @click='\$dispatch("open-edit", {
                        id: ${row.id},
                        litros: ${row.litros},
                        producto: "${row.producto}",
                        galones: ${row.galones},
                        fecha: "${row.fecha}",
                        no_factura: "${row.no_factura}"
                    })'
                `}
                >
                <i class="ti ti-edit"></i> Editar
                </a>
                </li>
                <li>
                <a href="javascript:void(0)"
                class="dropdown-item pointer ${noDelete ? 'disabled' : ''}"
                ${noDelete ? '' : `
                    @click='async () => {
                        const res = await deleteAction({
                            url: "${getBaseUrl()}/delete",
                            id: ${row.id},
                            name: "${row.folio}",
                            table: "#table-aditivo"
                        });
                        if (res && res.success) {
                            window.aditivoInstance.updateInventario();
                        }
                    }'
                `}
                >
                <i class="ti ti-trash"></i> Eliminar
                </a>
                </li>
                </ul>
                </div>
                </div>
                `;
            }
        });
    }
    return cols;
}()),
drawCallback: function () {
if (window.Alpine) {
Alpine.initTree(document.querySelector('#table-aditivo'));
}
}
});
}

function destroyTable() {
if (table) {
table.destroy();
table = null;
}
}

function getOrCreateTable() {
if (!table) {
table = initTable();
}
return table;
}

if (messageEl && messageEl.style.display !== 'none') {
showEmptyMessage();
} else {
showTable();
getOrCreateTable();
}

ModuleStationSelector.init('bitacora-aditivo', {
customReload: function (ms) {
var v = ms.getValue();
if (v.id_estacion === null && v.id_depto === null) {
ms.hideBadge();
showEmptyMessage();
return;
}
showTable();
if (table) {
table.ajax.reload(null, false);
} else {
getOrCreateTable();
}
if (window.aditivoInstance && window.aditivoInstance.updateInventario) {
window.aditivoInstance.updateInventario();
}
}
});

});
