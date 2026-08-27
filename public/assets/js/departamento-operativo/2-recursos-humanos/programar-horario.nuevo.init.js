document.addEventListener('alpine:init', () => {

    Alpine.data('programarHorarioNuevoComponent', () => ({

        cargando: true,
        guardando: false,
        fechaSeleccionada: '',
        secciones: [],
        reporte: null,
        localShifts: [],

        DIAS: [
            { key: 'lunes', num: 1, label: 'Lunes' },
            { key: 'martes', num: 2, label: 'Martes' },
            { key: 'miercoles', num: 3, label: 'Miércoles' },
            { key: 'jueves', num: 4, label: 'Jueves' },
            { key: 'viernes', num: 5, label: 'Viernes' },
            { key: 'sabado', num: 6, label: 'Sábado' },
            { key: 'domingo', num: 7, label: 'Domingo' }
        ],

        async init() {
            var c = document.getElementById('container');
            if (!c) return;
            var idReporte = c.dataset.idReporte;
            await this.cargarDetalle(idReporte || '0');
            this._bindSelectEvents();
        },

        async cargarDetalle(idReporte) {
            this.cargando = true;
            try {
                var res = await fetch('/departamento-operativo/recursos-humanos/programar-horario/get-detalle?id=' + idReporte);
                var json = await res.json();
                if (json.success) {
                    this.reporte = json.reporte;
                    if (json.reporte && json.reporte.fecha_raw) {
                        this.fechaSeleccionada = json.reporte.fecha_raw;
                    } else {
                        this.fechaSeleccionada = new Date().toISOString().split('T')[0];
                    }
                    this.agruparPorEstacion(json.data);
                    var self = this;
                    this.$nextTick(function() {
                        requestAnimationFrame(function() {
                            self.initDataTables();
                        });
                    });
                }
            } catch (e) {
                console.error('[ProgramarHorarioNuevo] Error cargando detalle:', e);
            }
            this.cargando = false;
        },

        agruparPorEstacion(personal) {
            var map = {};
            var order = [];
            for (var i = 0; i < personal.length; i++) {
                var p = personal[i];
                var key = p.id_estacion + '_' + p.nombre_estacion;
                if (!map[key]) {
                    map[key] = { nombre: p.nombre_estacion, personal: [] };
                    order.push(key);
                }
                map[key].personal.push(p);
            }
            this.secciones = order.map(function(k) { return map[k]; });
        },

        _getDiaKeyByNum(num) {
            for (var i = 0; i < this.DIAS.length; i++) {
                if (this.DIAS[i].num === num) return this.DIAS[i].key;
            }
            return '';
        },

        _actualizarDatoLocal(idPersonal, dia, horario) {
            var diaKey = this._getDiaKeyByNum(dia);
            if (!diaKey) return;
            var self = this;
            this.secciones.forEach(function(sec) {
                sec.personal.forEach(function(p) {
                    if (p.id === idPersonal) {
                        if (!p[diaKey]) p[diaKey] = {};
                        p[diaKey].horario = horario;
                        p[diaKey].formateado = horario || 'S/I';
                    }
                });
            });
            this.secciones.forEach(function(sec, idx) {
                var tableEl = document.getElementById('ph-form-table-' + idx);
                if (tableEl && $.fn.DataTable.isDataTable(tableEl)) {
                    var dt = $(tableEl).DataTable();
                    dt.rows().every(function() {
                        var rowData = this.data();
                        if (rowData.id === idPersonal) {
                            if (!rowData[diaKey]) rowData[diaKey] = {};
                            rowData[diaKey].horario = horario;
                            rowData[diaKey].formateado = horario || 'S/I';
                            this.data(rowData);
                        }
                    });
                }
            });
        },

        initDataTables() {
            var self = this;
            var DIAS = this.DIAS;
            var reporte = this.reporte;

            this.secciones.forEach(function(seccion, idx) {
                var tableEl = document.getElementById('ph-form-table-' + idx);
                if (!tableEl) return;
                if ($.fn.DataTable.isDataTable(tableEl)) {
                    $(tableEl).DataTable().destroy();
                    tableEl.innerHTML = '<thead>' + tableEl.querySelector('thead').outerHTML + '</thead><tbody></tbody>';
                }

                var columns = [
                    { title: '#', data: 'id', className: 'align-middle text-center fw-normal', width: '48px'},
                    { title: 'Nombre completo', data: 'nombre_completo', className: 'align-middle text-start' }
                ];

                DIAS.forEach(function(dia) {
                    columns.push({
                        title: dia.label,
                        data: dia.key,
                        className: 'p-0 m-0 align-middle text-center no-hover',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return self._buildTurnoSelect(row, dia, reporte);
                        }
                    });
                });

                $(tableEl).DataTable({
                    data: seccion.personal,
                    columns: columns,
                    destroy: true,
                    processing: true,
                    autoWidth: false,
                    ordering: true,
                    searching: true,
                    paging: true,
                    info: true,
                    pageLength: 10,
                    lengthMenu: [10, 25, 50, 100],
                    language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
                    order: [[1, 'asc']],
                    drawCallback: function() {
                        var dropdowns = tableEl.querySelectorAll('.dropdown-toggle');
                        dropdowns.forEach(function(el) {
                            new bootstrap.Dropdown(el);
                        });
                    }
                });
            });
        },

        _buildTurnoSelect(persona, dia, reporte) {
            var currentVal = '';
            if (this.esNuevo()) {
                var local = this.localShifts.find(function(s) {
                    return s.id_personal === persona.id && s.dia === dia.num;
                });
                currentVal = local ? local.horario : (persona[dia.key] ? (persona[dia.key].horario || '') : '');
            } else {
                currentVal = persona[dia.key] ? (persona[dia.key].horario || '') : '';
            }
            var disabled = (reporte && reporte.id && reporte.estado != 0) ? ' disabled' : '';

            var html = '<select class="form-select form-select-sm border-0 bg-transparent ph-turno-select"'
                + ' style="font-size:0.82em;"'
                + ' data-personal="' + persona.id + '"'
                + ' data-dia="' + dia.num + '"'
                + ' data-id-estacion="' + persona.id_estacion + '"'
                + disabled + '>';

            if (!currentVal) {
                html += '<option value="" selected>Sin asignar</option>';
            } else {
                html += '<option value="">Sin asignar</option>';
            }

            if (persona.turnos && persona.turnos.length > 0) {
                persona.turnos.forEach(function(turno) {
                    var sel = (currentVal === turno.titulo) ? ' selected' : '';
                    html += '<option value="' + turno.titulo + '"' + sel + '>' + turno.titulo + '</option>';
                });
            }

            var descansoSel = (currentVal === 'Descanso') ? ' selected' : '';
            html += '<option value="Descanso"' + descansoSel + '>Descanso</option>';
            html += '</select>';
            return html;
        },

        _bindSelectEvents() {
            var self = this;
            var container = document.getElementById('container');
            if (!container || container._phDtBound) return;
            container._phDtBound = true;

            container.addEventListener('change', function(e) {
                if (!e.target.classList.contains('ph-turno-select')) return;
                var idPersonal = parseInt(e.target.dataset.personal);
                var dia = parseInt(e.target.dataset.dia);
                var idEstacion = parseInt(e.target.dataset.idEstacion);
                var horario = e.target.value;
                self.editarTurnoDirect(idPersonal, dia, horario, idEstacion);
            });
        },

        editarTurnoDirect(idPersonal, dia, horario, idEstacion) {
            if (this.esNuevo()) {
                var existing = this.localShifts.find(function(s) {
                    return s.id_personal === idPersonal && s.dia === dia;
                });
                if (horario === '') {
                    if (existing) {
                        this.localShifts = this.localShifts.filter(function(s) {
                            return !(s.id_personal === idPersonal && s.dia === dia);
                        });
                    }
                } else if (existing) {
                    existing.horario = horario;
                } else {
                    this.localShifts.push({
                        id_personal: idPersonal,
                        dia: dia,
                        horario: horario,
                        id_estacion: idEstacion
                    });
                }
            } else {
                this.editarTurnoRemoto(idPersonal, dia, horario, idEstacion);
            }
        },

        esNuevo() {
            return !this.reporte || this.reporte.id === 0;
        },

        changeFecha(event) {
            var nuevaFecha = event.target.value;
            var idReporte = parseInt(document.getElementById('container').dataset.idReporte || '0');
            var anterior = this.fechaSeleccionada;

            if (!nuevaFecha) {
                event.target.value = anterior;
                return;
            }

            this.fechaSeleccionada = nuevaFecha;

            if (!idReporte) return;

            axios.post('/departamento-operativo/recursos-humanos/programar-horario/actualizar-fecha', {
                id_reporte: idReporte,
                fecha: nuevaFecha
            })
            .then(function(res) {
                var json = res.data;
                if (json.success) {
                    Swal.fire({ icon: 'success', title: 'Fecha actualizada', timer: 1500, showConfirmButton: false });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: json.message });
                    this.fechaSeleccionada = anterior;
                }
            }.bind(this))
            .catch(function() {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo actualizar la fecha.' });
                this.fechaSeleccionada = anterior;
            }.bind(this));
        },

        async editarTurnoRemoto(idPersonal, dia, horario, idEstacion) {
            var idReporte = parseInt(document.getElementById('container').dataset.idReporte);
            try {
                var res = await fetch('/departamento-operativo/recursos-humanos/programar-horario/editar-turno', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        id_reporte: idReporte,
                        id_personal: idPersonal,
                        dia: dia,
                        horario: horario,
                        id_estacion: idEstacion
                    })
                });
                var json = await res.json();
                if (json.success) {
                    this._actualizarDatoLocal(idPersonal, dia, horario);
                } else {
                    console.error('[ProgramarHorarioNuevo] Error:', json.message);
                }
            } catch (e) {
                console.error('[ProgramarHorarioNuevo] Error editando turno:', e);
            }
        },

        async guardarReporte() {
            if (this.guardando) return;
            if (!this.fechaSeleccionada) {
                Swal.fire({ icon: 'warning', title: 'Fecha requerida', text: 'Selecciona una fecha antes de guardar.' });
                return;
            }

            this.guardando = true;
            var idReporte = parseInt(document.getElementById('container').dataset.idReporte || '0');

            var body = {
                id_reporte: idReporte,
                fecha: this.fechaSeleccionada
            };

            if (idReporte === 0) {
                if (this.localShifts.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Sin turnos', text: 'Asigna al menos un turno antes de guardar.' });
                    this.guardando = false;
                    return;
                }
                body.detalles = this.localShifts;
            }

            try {
                var res = await fetch('/departamento-operativo/recursos-humanos/programar-horario/guardar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(body)
                });
                var json = await res.json();
                if (json.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Guardado',
                        text: json.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = '/departamento-operativo/recursos-humanos/programar-horario';
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: json.message });
                }
            } catch (e) {
                console.error('[ProgramarHorarioNuevo] Error guardando:', e);
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo guardar el reporte.' });
            }
            this.guardando = false;
        },

    }));
});
