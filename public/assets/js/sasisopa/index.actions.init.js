document.addEventListener('alpine:init', () => {
    Alpine.data('sasisopa', () => ({

    filtro: {
    fechaInicio: '',
    fechaTermino: ''
    },

    errors: {
        fechaInicio: false,
        fechaTermino: false
    },

    modalBuscar: null,

    init() {

    this.modalBuscar = new bootstrap.Modal(
        document.getElementById('modalBuscar')
    );

    },

    abrirModalBuscar() {

    this.errors.fechaInicio = false;
    this.errors.fechaTermino = false;

    this.modalBuscar.show();

    },

    buscarRegistros() {

    this.errors.fechaInicio = !this.filtro.fechaInicio;
    this.errors.fechaTermino = !this.filtro.fechaTermino;

    if (
        this.errors.fechaInicio ||
        this.errors.fechaTermino
    ) {
        return;
    }

    window.location.href =
        `/sasisopa/reporte/${this.filtro.fechaInicio}/${this.filtro.fechaTermino}`;

    },

    }));

});