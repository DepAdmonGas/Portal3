document.addEventListener('alpine:init', () => {

    Alpine.data('evaluacion', (idCalendario) => ({

        idCalendario,

        loading: true,
        finalizando: false,

        tema: {},
        preguntas: [],
        total: 0,

        // 👇 aquí guardamos:
        // { [numeroPregunta]: valor }
        respuestas: {},

        resultado: null,

        init() {
            this.cargar();
        },

        /*
        |--------------------------------------------------------------------------
        | CARGAR EVALUACIÓN
        |--------------------------------------------------------------------------
        */
        async cargar() {

            this.loading = true;

            try {

                const { data } = await axios.get(
                    '/sasisopa/cursos/evaluacion/get/' + this.idCalendario
                );

                if (!data.success) {
                    this.loading = false;
                    return;
                }

                this.tema = data.tema;
                this.preguntas = data.preguntas;
                this.total = this.preguntas.length;

                // reset limpio
                this.respuestas = {};

            } catch (e) {
                this.notify('error', 'Error al cargar evaluación');
            }

            this.loading = false;
        },

        /*
        |--------------------------------------------------------------------------
        | SELECCIONAR RESPUESTA
        |--------------------------------------------------------------------------
        */
        async seleccionar(pregunta, respuesta) {

            // guardamos SOLO valor por pregunta.numero
            this.respuestas[pregunta.numero] = respuesta.valor;

            try {

                await axios.post(
                    '/sasisopa/cursos/evaluacion/respuesta',
                    {
                        calendario: this.idCalendario,
                        pregunta: pregunta.numero,
                        valor: respuesta.valor
                    }
                );

            } catch (e) {

                this.notify('error', 'No se pudo guardar respuesta');

            }
        },

        /*
        |--------------------------------------------------------------------------
        | CONTADOR RESPONDIDAS
        |--------------------------------------------------------------------------
        */
        get contestadas() {
            return Object.keys(this.respuestas).length;
        },

        /*
        |--------------------------------------------------------------------------
        | PORCENTAJE
        |--------------------------------------------------------------------------
        */
        get porcentaje() {

            if (this.total === 0) return 0;

            return Math.round(
                (this.contestadas / this.total) * 100
            );
        },

        /*
        |--------------------------------------------------------------------------
        | VALIDAR
        |--------------------------------------------------------------------------
        */
        validar() {

            if (this.contestadas !== this.total) {

                this.notify(
                    'warning',
                    'Debes responder todas las preguntas'
                );

                return false;
            }

            return true;
        },

        /*
        |--------------------------------------------------------------------------
        | FINALIZAR
        |--------------------------------------------------------------------------
        */
        async finalizar() {

            if (!this.validar()) return;

            this.finalizando = true;

            try {

                const res = await axios.post(
                    '/sasisopa/cursos/evaluacion/finalizar',
                    {
                        calendario: this.idCalendario
                    }
                );

                if (res.data && res.data.success) {

                    this.resultado = res.data.resultado;

                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });

                }

            } catch (e) {

                this.notify('error', 'Error al finalizar evaluación');

            } finally {

                this.finalizando = false;
            }
        }

    }));

});