document.addEventListener('alpine:init', () => {
    Alpine.data('recibosNominaRevisionComponent', () => ({
        idYear: 0,
        idEstacion: 0,
        mes: 0,
        estacionLabel: '',
        finalizando: false,
        revision: { hay_estacion: false, mostrar_excel: false, excel_url: '', bloques: [] },

        get baseUrl() {
            return window.location.pathname.includes('/departamento-operativo')
                ? '/departamento-operativo/recursos-humanos/recibos-nomina'
                : '/recursos-humanos/recibos-nomina';
        },

        init() {
            const c = document.getElementById('revision-container');
            if (!c) return;

            this.idYear = parseInt(c.dataset.idYear);
            this.idEstacion = parseInt(c.dataset.idEstacion);
            this.mes = parseInt(c.dataset.mes);

            // El service ya deja resueltos los badges, iconos, URLs e importes,
            // asi que la vista unicamente los imprime.
            const datos = document.getElementById('revision-data');
            if (datos) {
                this.revision = JSON.parse(datos.textContent);
            }

            // La estacion viene del contexto del selector compartido en el
            // servidor, asi que el nombre se toma del badge que ese selector
            // publica en el layout.
            const key = c.dataset.moduleStationKey;
            const badge = key ? document.getElementById('module-station-badge-' + key) : null;
            this.estacionLabel = badge ? badge.textContent.trim() : '';

            window.rnRevisionComponent = this;
        },

        /**
         * Cierre de la actividad desde la Revision. Mismo patron que el index:
         * alerta centrada de window.alerts en lugar de un modal propio, porque
         * el modal ocupa media pantalla para una confirmacion de una sola linea.
         */
        finalizarOperativo(bloque) {
            if (this.finalizando) return;

            const periodo = (bloque.descripcion === 'Semana' ? 'Semana ' : 'Quincena ') + bloque.periodo;
            const texto = '¿Deseas finalizar la actividad de recibos de nómina de la estación '
                + (this.estacionLabel || 'seleccionada') + ' para el periodo ' + periodo + '?';

            window.alerts.confirm('Finalizar actividad', texto, async () => {
                this.finalizando = true;
                try {
                    const resp = await axios.post(`${this.baseUrl}/finalizar`, {
                        idEstacion: this.idEstacion,
                        year: this.idYear,
                        periodo: bloque.periodo,
                        descripcion: bloque.descripcion,
                        idResponsable: 3
                    });
                    if (resp.data.success) {
                        window.alerts.success(resp.data.message || 'Actividad finalizada correctamente');
                        window.location.reload();
                    } else {
                        window.alerts.error(resp.data.message || 'No se pudo finalizar la actividad');
                    }
                } catch (e) {
                    console.error(e);
                } finally {
                    this.finalizando = false;
                }
            });
        }
    }));
});
