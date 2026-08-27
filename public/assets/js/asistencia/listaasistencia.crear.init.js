document.addEventListener('alpine:init', () => {

    Alpine.data('listaasistenciaForm', () => ({

        
        async crearAsistencia() {

            const punto = document
                .getElementById('container')
                .dataset.elemento;

            const herramienta = document
                .getElementById('container')
                .dataset.herramienta;

            const res = await this.createAction({
                url: '/lista-asistencia/create',
                data: {
                    punto_sasisopa: punto,
                    herramienta: herramienta
                },
                onSuccess: (res) => {
                    // redirección solo si todo salió bien
                    window.location.href = "/lista-asistencia/" + res.id;
                }
            });

        },
    }));
});