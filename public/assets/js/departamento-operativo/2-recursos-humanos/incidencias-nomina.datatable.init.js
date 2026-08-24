document.addEventListener('DOMContentLoaded', function () {

    var c = document.getElementById('container');
    if (!c) return;

    var moduleStationKey = c.dataset.moduleStationKey || 'incidencias-nomina';
    var idYear = parseInt(c.dataset.idYear || new Date().getFullYear());

    var messageEl = document.getElementById('incidencias-empty-message');
    var contentEl = document.getElementById('incidencias-content');
    var loadingEl = document.getElementById('incidencias-loading');

    var lastDias = [];
    var lastWeekRange = {};
    var lastShowEst = false;
    var tableInstance = null;
    var loadingSeq = 0;

    function showEmptyMessage() {
        if (contentEl) contentEl.style.display = 'none';
        if (messageEl) messageEl.style.display = '';
    }

    function showTable() {
        if (contentEl) contentEl.style.display = '';
        if (messageEl) messageEl.style.display = 'none';
    }

    function getEstacionParam() {
        var sel = document.getElementById('module-station-selector-' + moduleStationKey);
        if (sel && sel.value) {
            var val = sel.value;
            if (val.indexOf('depto_') === 0) return parseInt(val.replace('depto_', ''), 10);
            return parseInt(val.replace('estacion_', ''), 10);
        }
        return parseInt(c.dataset.idEstacion || '0');
    }

    function isTodasEstaciones() {
        var sel = document.getElementById('module-station-selector-' + moduleStationKey);
        if (!sel) return false;
        return sel.value === '';
    }

    function getSemanaValue() {
        var sel = document.getElementById('incidencias-semana-selector');
        return sel ? parseInt(sel.value) || 1 : 1;
    }

    function buildUrl() {
        var est = getEstacionParam();
        var sem = getSemanaValue();
        var url = '/departamento-operativo/recursos-humanos/incidencias-nomina/' + idYear + '/data?semana=' + sem;

        if (isTodasEstaciones()) {
            url += '&tipo=todas&id_estacion=0';
        } else if (est) {
            url += '&tipo=estacion&id_estacion=' + est;
        } else {
            return null;
        }

        return url;
    }

    function escHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function buildColumns(numDias, showEstacionCol) {
        var diasColOffset = showEstacionCol ? 4 : 3;
        var cols = [
            { data: 'id', className: 'align-middle text-center', width: '48px', orderable: false, searchable: false,
              render: function(v, t, row, meta) { return meta.row + 1; } },
            { data: 'nombre_completo', className: 'align-middle text-start text-nowrap' },
            { data: 'puesto_nombre', className: 'align-middle text-center text-nowrap' }
        ];

        if (showEstacionCol) {
            cols.push({ data: 'estacion_nombre', className: 'align-middle text-start text-nowrap' });
        }

        for (var i = 0; i < numDias; i++) {
            cols.push({
                data: 'dias',
                className: 'align-middle text-center',
                render: function(v, t, row, meta) {
                    var diaIdx = meta.col - diasColOffset;
                    if (!v || !v[diaIdx]) return '';
                    var dia = v[diaIdx];
                    return '<span class="' + escHtml(dia.color) + '">' + escHtml(dia.detalle) + '</span>';
                }
            });
        }

        cols.push({ data: 'retardos', className: 'align-middle text-center', render: function(v) { return v || 0; } });
        cols.push({ data: 'faltas', className: 'align-middle text-center', render: function(v) { return v || 0; } });
        cols.push({ data: 'dia_doble', className: 'align-middle text-center', render: function(v) { return v || 0; } });

        return cols;
    }

    function buildCompleteThead(dias, showEst) {
        var numDias = dias ? dias.length : 7;

        var html = '';
        html += '<tr class="title-table-bg">';
        html += '<th class="text-center align-middle fw-bold" width="48px">No.</th>';
        html += '<th class="text-start align-middle">Nombre</th>';
        html += '<th class="text-center align-middle">Puesto</th>';
        if (showEst) {
            html += '<th class="text-start align-middle">Estación / Departamento</th>';
        }
        for (var i = 0; i < numDias; i++) {
            html += '<th class="align-middle text-center">';
            if (dias && dias[i]) {
                html += '<div class="lh-1">' + escHtml(dias[i].nombre) + '</div>';
                html += '<div class="lh-1 small text-muted">' + escHtml(dias[i].fecha) + '</div>';
            } else {
                html += '<div class="lh-1">--</div>';
                html += '<div class="lh-1 small text-muted">--</div>';
            }
            html += '</th>';
        }
        html += '<th class="text-center align-middle">Retardos</th>';
        html += '<th class="text-center align-middle">Faltas</th>';
        html += '<th class="text-center align-middle fw-bold">Días Dobles</th>';
        html += '</tr>';
        return html;
    }

    function initDataTable(rows) {
        if (tableInstance) {
            tableInstance.destroy();
            tableInstance = null;
        }

        var tableEl = document.getElementById('tabla-incidencias-nomina');
        if (!tableEl) return;

        var numDias = lastDias ? lastDias.length : 7;
        var showEst = lastShowEst;
        var columns = buildColumns(numDias, showEst);

        tableEl.innerHTML = '<thead>' + buildCompleteThead(lastDias, showEst) + '</thead><tbody></tbody>';

        tableInstance = $(tableEl).DataTable({
            data: rows,
            processing: false,
            serverSide: false,
            deferRender: true,
            autoWidth: false,
            stateSave: false,
            order: [[1, 'asc']],
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
            columns: columns
        });
    }

    function loadData() {
        var url = buildUrl();
        if (!url) {
            showEmptyMessage();
            lastDias = [];
            lastWeekRange = {};
            if (tableInstance) {
                tableInstance.clear().draw();
            }
            return;
        }

        showTable();
        if (loadingEl) loadingEl.style.display = '';
        var tableWrap = document.querySelector('#container .datatables');
        if (tableWrap) tableWrap.style.display = 'none';

        var seq = ++loadingSeq;

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(json) {
                if (seq !== loadingSeq) return;

                if (loadingEl) loadingEl.style.display = 'none';
                if (tableWrap) tableWrap.style.display = '';

                if (!json.success) {
                    lastDias = [];
                    lastWeekRange = {};
                    if (tableInstance) {
                        tableInstance.clear().draw();
                    }
                    return;
                }

                lastDias = json.dias || [];
                lastWeekRange = json.weekRange || {};
                lastShowEst = isTodasEstaciones();

                var weekTitleEl = document.getElementById('incidencias-week-title');
                if (weekTitleEl && json.weekTitle) {
                    weekTitleEl.textContent = json.weekTitle;
                }

                var rows = json.data || [];
                initDataTable(rows);
            })
            .catch(function() {
                if (seq !== loadingSeq) return;
                if (loadingEl) loadingEl.style.display = 'none';
                var tableWrap = document.querySelector('#container .datatables');
                if (tableWrap) tableWrap.style.display = '';
            });
    }

    document.getElementById('incidencias-semana-selector').addEventListener('change', function() {
        var semana = this.value;
        if (semana) {
            fetch('/departamento-operativo/recursos-humanos/incidencias-nomina/guardar-contexto', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ semana: parseInt(semana, 10), id_year: idYear })
            });
        }
        loadData();
    });

    var hasSelector = !!document.getElementById('module-station-selector-' + moduleStationKey);

    if (hasSelector) {
        showTable();
        loadData();
    } else {
        var dataEst = parseInt(c.dataset.idEstacion || '0');
        if (dataEst) {
            showTable();
            loadData();
        } else {
            showEmptyMessage();
        }
    }

    if (moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
        ModuleStationSelector.init(moduleStationKey, {
            customReload: function(ms) {
                ms.updateBadge();
                showTable();
                loadData();
            }
        });
    }

});
