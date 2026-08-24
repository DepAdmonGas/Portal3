var rolComodinesFormActions = {};

function rolComodinesForm() {
    var c = document.getElementById('container');

    return {
        idReporte: c ? parseInt(c.dataset.idReporte || '0') : 0,
        status: c ? parseInt(c.dataset.status || '0') : 0,
        puedeEditar: c ? c.dataset.puedeEditar === 'true' : false,
        esFinalizado: c ? parseInt(c.dataset.status || '0') === 1 : false,
        fechaInicio: c ? (c.dataset.fechaInicio || '') : '',
        fechaFin: c ? (c.dataset.fechaFin || '') : '',
        finalizando: false,
        guardandoFechas: false,

        init() {
            var self = this;
            rolComodinesFormActions.guardarAsignacion = function(idReporte, idUsuario, dia, idEstacion) {
                self._guardarAsignacion(idReporte, idUsuario, dia, idEstacion);
            };
        },

        _guardarAsignacion(idReporte, idUsuario, dia, idEstacion) {
            axios.post('/departamento-operativo/recursos-humanos/rol-comodines/edit-assignment', {
                id_reporte: idReporte,
                id_usuario: idUsuario,
                id_estacion: parseInt(idEstacion),
                dia: dia
            })
            .then(function (res) {
                var json = res.data;
                if (!json.success) {
                    Notify.error(json.message || 'Error al guardar.');
                }
            })
            .catch(function () { Notify.error('Error de conexión.'); });
        },

        _guardarFechas() {
            var self = this;
            self.guardandoFechas = true;

            axios.post('/departamento-operativo/recursos-humanos/rol-comodines/save-dates', {
                id_reporte: self.idReporte,
                fecha_inicio: self.fechaInicio,
                fecha_fin: self.fechaFin
            })
            .then(function (res) {
                var json = res.data;
                self.guardandoFechas = false;
                if (!json.success) {
                    Notify.error(json.message || 'Error al guardar fechas.');
                }
            })
            .catch(function () {
                self.guardandoFechas = false;
                Notify.error('Error de conexión.');
            });
        },

        finalizarRol() {
            var self = this;

            if (!self.fechaInicio) {
                Notify.error('La fecha de inicio es obligatoria.');
                return;
            }
            if (!self.fechaFin) {
                Notify.error('La fecha de término es obligatoria.');
                return;
            }

            self.finalizando = true;

            axios.post('/departamento-operativo/recursos-humanos/rol-comodines/finalize', {
                id_reporte: self.idReporte,
                fecha_inicio: self.fechaInicio,
                fecha_fin: self.fechaFin
            })
            .then(function (res) {
                var json = res.data;
                self.finalizando = false;
                if (json.success) {
                    Notify.success('Rol finalizado correctamente.');
                    setTimeout(function () {
                        window.location.href = '/departamento-operativo/recursos-humanos/rol-comodines';
                    }, 1000);
                } else {
                    Notify.error(json.message || 'Error al finalizar.');
                }
            })
            .catch(function () {
                self.finalizando = false;
                Notify.error('Error de conexión.');
            });
        }
    };
}
