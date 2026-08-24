document.addEventListener('alpine:init', () => {
    Alpine.data('capacitacionInterna', () => ({

        programacion: {
        id_usuario: null,
        id_modulo: null,
        id_tema: null,
        fecha: '',
        titulo: ''
        },

        errorsProgramacion: {
            fecha: false
        },

        modals: {},

        cursos: [],
        cursosTitulo: '.',
        nombreUsuarioCursos: '',
        cursoSeleccion: {
            id_usuario: null,
            id_tema: null
        },

        year: new Date().getFullYear(),
        htmlReporte: '',

        init() {

             if (!this.modals) this.modals = {};

            const modalProgramar = document.getElementById('modalProgramar');
            if (modalProgramar) {
                this.modals.programar = new bootstrap.Modal(modalProgramar);
            }

            const modalCursos = document.getElementById('modalCursos');
            if (modalCursos) {
                this.modals.cursos = new bootstrap.Modal(modalCursos);
            }

            const modalBuscar = document.getElementById('modalBuscar');
            if (modalBuscar) {
                this.modals.buscar = new bootstrap.Modal(modalBuscar);
            }

            window.addEventListener('ver-cursos', (e) => {
                this.openModalCursos(e.detail.idUsuario, e.detail.idTema, e.detail.nombre);
            });

        },

        irATema(e) {

            const option = e.target.selectedOptions[0];

            const idTema = option.value;
            const idModulo = option.dataset.modulo;

            if (!idTema) return;

            window.location.href = `/sasisopa/competencia-personal-capacitacion-entrenamiento/capacitacion-interna/${idModulo}/${idTema}`;
        },

        openModal(nombre) {
        this.modals[nombre].show();
        },
        closeModal(nombre) {
            if (this.modals[nombre]) {
            this.modals[nombre].hide();
            }
        },

        getModal(id) {
            return new bootstrap.Modal(document.getElementById(id));
        },

        openModalProgramar(idUsuario, idModulo, idTema) {

            this.programacion = {
                id_usuario: idUsuario,
                id_modulo: idModulo,
                id_tema: idTema,
                fecha: '',
                titulo: this.getTituloTema(idTema)
            };

            this.errorsProgramacion.fecha = false;

            this.openModal('programar');
        },

        getTituloTema(idTema) {

            const option = document.querySelector(
                `option[value="${idTema}"]`
            );

            return option ? option.textContent : 'Tema';
        },

        validarProgramacion() {

        let valid = true;

        this.errorsProgramacion.fecha = false;

        if (!this.programacion.fecha) {
            this.errorsProgramacion.fecha = true;
            valid = false;
        }

        return valid;
    },

    async guardarProgramacion() {

        if (!this.validarProgramacion()){
            this.notify('error', 'Completa los campos obligatorios');
            return;
        }

        try {

            const res = await this.createAction({
                url: '/sasisopa/competencia-personal-capacitacion-entrenamiento/create-programacion-interna',
                data: {
                    id_usuario: this.programacion.id_usuario,
                    id_tema: this.programacion.id_tema,
                    fecha_programada: this.programacion.fecha
                },
                table: '#table-capacitacion-interna'
            });

            if (res.success) {
                this.closeModal('programar');
            }

        } catch (e) {
            this.notify('error', 'Error al guardar');
        }
    },
    //-----------------------------------------------------------------

    async openModalCursos(idUsuario, idTema, nombre) {

        this.cursoSeleccion = {
            id_usuario: idUsuario,
            id_tema: idTema
        };

        this.nombreUsuarioCursos = nombre;
        this.cursosTitulo = this.getTituloTema(idTema);

        await this.getCursos();

        this.openModal('cursos');
    },

    async getCursos() {

        try {

            const res = await fetch(
                `/sasisopa/competencia-personal-capacitacion-entrenamiento/get-cursos-internos/${this.cursoSeleccion.id_usuario}/${this.cursoSeleccion.id_tema}`
            );

            const data = await res.json();

            this.cursos = data.map(c => {

                let texto = 'Pendiente';
                let color = '';

                if (c.estado === 1) {

                    if (c.resultado >= 90) {
                        texto = `${c.resultado}% Excelente`;
                        color = 'text-success';
                    } else if (c.resultado >= 80) {
                        texto = `${c.resultado}% Bueno`;
                        color = 'text-primary';
                    } else if (c.resultado >= 60) {
                        texto = `${c.resultado}% Regular`;
                        color = 'text-warning';
                    } else {
                        texto = `${c.resultado}% Malo`;
                        color = 'text-danger';
                    }

                }

                return {
                    ...c,
                    texto,
                    color
                };

            });

        } catch (e) {
           this.notify('error', 'Error al visualizar los cursos');
        }
    },

    async eliminarCurso(id) {

        const res = await this.deleteAction({
            url: '/sasisopa/competencia-personal-capacitacion-entrenamiento/delete-curso-interno',
            id: id,
            name: 'curso',
            table: '#table-capacitacion-interna'

        });

        if (res && res.success) {
            await this.getCursos();
        }
    },

    formatearFecha(fecha) {

        if (
            !fecha ||
            fecha === '0000-00-00' ||
            fecha === '0000-00-00 00:00:00' ||
            fecha.includes('-000001') ||
            fecha.startsWith('0000')
        ) {
            return 'S/I';
        }

        let limpia = fecha.split('T')[0];

        const partes = limpia.split('-');

        if (partes.length !== 3) return 'S/I';

        const year = parseInt(partes[0]);
        const month = parseInt(partes[1]);
        const day = parseInt(partes[2]);

        if (!year || year < 1900 || month < 1 || month > 12 || day < 1 || day > 31) {
            return 'S/I';
        }

        const f = new Date(year, month - 1, day);

        if (isNaN(f.getTime())) return 'S/I';

        return f.toLocaleDateString('es-MX', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    },
    //--------------------------------------------------------------------------------------

    openModalBuscar(){
        this.year = new Date().getFullYear();
        this.htmlReporte = '';
        this.openModal('buscar');
    },

    async buscar() {

        if (!this.year) {
            alert('Ingresa un año válido');
            return;
        }

        try {

            const res = await fetch(`/sasisopa/competencia-personal-capacitacion-entrenamiento/buscar-capacitacion-interna/${this.year}`);
            this.htmlReporte = await res.text();
            this.closeModal('buscar');

        } catch (e) {
             this.notify('error', 'Error al visualizar los cursos');
        }
    },

    limpiarBusqueda() {
        this.htmlReporte = '';
        this.year = null;
    }


    }));
});