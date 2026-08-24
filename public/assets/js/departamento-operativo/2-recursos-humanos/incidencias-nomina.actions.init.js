function incidenciasNominaComponent() {
    var c = document.getElementById('container');

    return {
        puedeDescargar: c ? c.dataset.puedeDescargar === 'true' : false,
        idYear: c ? parseInt(c.dataset.idYear || new Date().getFullYear()) : new Date().getFullYear(),
        estacionEspecifica: false,

        init() {
            var self = this;
            var sel = document.getElementById('module-station-selector-incidencias-nomina');
            if (sel) {
                self.estacionEspecifica = !!sel.value;
                sel.addEventListener('change', function () {
                    self.estacionEspecifica = !!sel.value;
                });
            }
        },

        descargarReporteEstaciones() {
            var semana = this.getSemana();
            var url = '/departamento-operativo/recursos-humanos/incidencias-nomina/' + this.idYear + '/pdf-estaciones?semana=' + semana;
            window.open(url, '_blank');
        },

        descargarReporteIndividual() {
            var sel = document.getElementById('module-station-selector-incidencias-nomina');
            if (!sel || !sel.value) return;
            var val = sel.value;
            var idEst = 0;
            if (val.indexOf('depto_') === 0) {
                idEst = parseInt(val.replace('depto_', ''), 10);
            } else {
                idEst = parseInt(val.replace('estacion_', ''), 10);
            }
            if (!idEst) return;
            var semana = this.getSemana();
            var url = '/departamento-operativo/recursos-humanos/incidencias-nomina/' + this.idYear + '/pdf-individual?id_estacion=' + idEst + '&semana=' + semana;
            window.open(url, '_blank');
        },

        getSemana() {
            var sel = document.getElementById('incidencias-semana-selector');
            return sel ? (sel.value || 1) : 1;
        }
    };
}
