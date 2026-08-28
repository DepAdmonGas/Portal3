document.addEventListener('alpine:init', () => {
    Alpine.data('cursos', (categoria, multiestacion) => ({

        categoria,
        multiestacion: !!multiestacion,

        loadingModulos: true,
        loadingPendientes: true,
        modulos: [],

        total: 0,
        cursos: [],

        async init() {

            await Promise.all([
                this.cargar(),
                this.pendientes()
            ]);

        },

         async cargar() {

            this.loadingModulos = true;

            try {

                const { data } = await axios.get(
                    '/sasisopa/cursos/modulos/get?categoria=' + this.categoria
                );

                if (data.success) {

                    this.modulos = data.data;

                }

            } finally {

                this.loadingModulos = false;

            }

        },

        detalle(modulo) {

            window.location =
                '/sasisopa/cursos/modulos/' + modulo.id;

        },

        async pendientes() {

            this.loadingPendientes = true;

            try {

                const { data } = await axios.get(
                    '/sasisopa/cursos/pendientes/get?categoria=' + this.categoria
                );

                if (data.success) {

                    this.cursos = data.data;

                    this.total = data.total;

                }

            } finally {

                this.loadingPendientes = false;

            }

        },

        iniciar(curso) {

            window.location =
                '/sasisopa/cursos/iniciar/' + curso.id;

        }

    }));
});