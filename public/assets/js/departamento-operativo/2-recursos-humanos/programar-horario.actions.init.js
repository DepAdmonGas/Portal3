document.addEventListener('alpine:init', () => {

    Alpine.data('programarHorarioComponent', () => ({

        init() {
            var c = document.getElementById('container');
            if (!c) return;
            window.programarHorarioComponentInstance = this;
        },

        async agregarReporte() {
            window.location.href = '/departamento-operativo/recursos-humanos/programar-horario/nuevo';
        },

        async eliminarReporte(id, name) {
            await this.deleteAction({
                url: '/departamento-operativo/recursos-humanos/programar-horario/eliminar',
                id: id,
                name: name || 'Reporte #' + id,
                table: '#tabla-programar-horario'
            });
        },

    }));
});
