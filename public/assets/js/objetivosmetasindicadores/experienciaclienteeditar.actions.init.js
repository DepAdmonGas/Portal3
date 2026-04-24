document.addEventListener('alpine:init', () => {
    Alpine.data('experienciaClienteEditar', () => ({

        fecha: '',
        nombre: '',
        comentario: '',
        preguntas: [],
        clientes: [],
        detalle: {
            nombre: '',
            comentario: '',
            preguntas: []
        },

        loading: false,

        errors: {
           fecha: false, 
           nombre: false,
           preguntas: []
        },

        validate() {

           
            this.errors.fecha = false;
            this.errors.nombre = false;
            this.errors.preguntas = [];

            let valid = true;

             if (!this.nombre.trim()) {
                this.errors.nombre = true;
                valid = false;
            }

            this.preguntas.forEach((p, index) => {
                if (!p.respuesta || p.respuesta == 0) {
                    this.errors.preguntas.push(index);
                    valid = false;
                }
            });

            return valid;
        },

        init() {

            const fecha = document
                .getElementById('container')
                .dataset.fecha;

                this.fecha = fecha;
           
            this.preguntas = Array.from(document.querySelectorAll('[data-pregunta]')).map(el => ({
                id_pregunta: el.dataset.pregunta,
                respuesta: 0
            }));

            this.getClientes();
        },

        async getClientes() {

            const id = document.getElementById('container').dataset.id;

            const res = await fetch(`/sasisopa/objetivos-metas-indicadores/lista-encuesta-cliente?id=${id}`);
            const json = await res.json();

            if (json.success) {
                this.clientes = json.data;
            }
        },

        async guardar() {

            if (!this.validate()) {
                this.notify('error', 'Completa todos los campos');
                return;
            }

            const id = document
                .getElementById('container')
                .dataset.id;

    
            try {

                const res = await this.createAction({
                url: '/sasisopa/objetivos-metas-indicadores/agregar-encuesta-cliente',
                data: {
                    id: id,
                    nombre: this.nombre,
                    comentario: this.comentario,
                    preguntas: this.preguntas,
                    }
                });

                if (res && res.success) {
                    
                    this.nombre = '';
                    this.comentario = '';
                    this.preguntas.forEach(p => p.respuesta = 0);
                    await this.getClientes();
                }

            } catch (error) {
                this.notify('error', 'Error al guardar');
            }

        },

        async Detalle(id) {

            const res = await fetch(`/sasisopa/objetivos-metas-indicadores/detalle-encuesta-cliente?id=${id}`);
            const json = await res.json();

            if (json.success) {

                this.detalle = json.data;

                const modal = new bootstrap.Modal(document.getElementById('modalDetalle'));
                modal.show();
            }
        },

        getColor(resultado) {
            switch (parseInt(resultado)) {
                case 4: return '#0099F0';
                case 3: return '#1EAD4E';
                case 2: return '#F3C000';
                case 1: return '#E70606';
                default: return '#000';
            }
        },

        getTexto(resultado) {
            switch (parseInt(resultado)) {
                case 4: return 'Excelente';
                case 3: return 'Bueno';
                case 2: return 'Regular';
                case 1: return 'Malo';
                default: return '';
            }
        },

        async finalizarEncuesta() {

            if (!this.fecha) {
                this.notify('warning', 'Selecciona una fecha');
                return;
            }

            const id = document.getElementById('container').dataset.id;

            try {

                const res = await this.createAction({
                    url: '/sasisopa/objetivos-metas-indicadores/finalizar-encuesta',
                    data: {
                        id: id,
                        fecha: this.fecha + ' ' + new Date().toLocaleTimeString('es-MX', { hour12: false })
                    }
                });

                if (res && res.success) {
                    this.notify('success', 'Encuesta finalizada');
                    window.location.href = '/sasisopa/objetivos-metas-indicadores/experiencia-cliente';
                }

            } catch (error) {
                this.notify('error', 'Error al finalizar');
            }
        }
      

    }));
});