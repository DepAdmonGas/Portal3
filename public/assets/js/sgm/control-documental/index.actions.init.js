document.addEventListener('alpine:init', () => {

    Alpine.data('controlDocumental', () => ({

        documentos: [],

        async init() {

            await this.cargar();

        },

        async cargar() {

            const { data } = await axios.get(
                '/sgm/control-documental-sistema-gestion-medicion/documentos'
            );

            this.documentos = data;

        },

        documentosPorSeccion(seccion) {

            return this.documentos.filter(x => x.seccion == seccion);

        },

        titulo(seccion) {

            switch (seccion) {

                case 1:
                    return 'Manual de procedimientos del Sistema de Gestión de Medición ';

                case 2:
                    return 'Formatos del Sistema de Gestión de Medición';

                case 3:
                    return 'Documentos del Sistema de Gestión';

            }

            return '';

        }

    }));

});