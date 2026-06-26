document.addEventListener('alpine:init', () => {
    Alpine.data('informesDesempenoEditar', (idReporte) => ({

        loading: false,

        idReporte,

        fechaReporte: '',

        procedimientos: [],

        puestos: [
            { id: 1, nombre: 'Representante Técnico' },
            { id: 2, nombre: 'Gerente' },
            { id: 3, nombre: 'Jefe de Piso' },
            { id: 4, nombre: 'Facturista' },
            { id: 5, nombre: 'Despachador' },
            { id: 6, nombre: 'Auxiliar administrativo' },
            { id: 7, nombre: 'Mantenimiento' }
        ],

        async init() {
     
            await this.cargar();

        },

        async cargar() {

            this.loading = true;

            try {

                const { data } = await axios.get(
                    `/sasisopa/informes-desempeno/implementacion/editar/datatable/${this.idReporte}`
                );

                if (data.success) {

                    this.fechaReporte = data.fecha;

                    this.procedimientos = data.procedimientos;

                }

            } finally {

                this.loading = false;

            }

        },

        async actualizarFechaReporte() {

            await this.createAction({
                url: '/sasisopa/informes-desempeno/implementacion/editar/fecha-reporte',
                data: {
                    id: this.idReporte,
                    fecha: this.fechaReporte
                },
                notify: false

            });

        },

        async actualizarFecha(item) {

            await this.createAction({
                url: '/sasisopa/informes-desempeno/implementacion/editar/fecha-implementacion',
                data: {
                    id: item.id,
                    fecha: item.fecha_implementacion
                },
                notify: false

            });

        },

        async actualizarDescripcion(item) {

            await this.createAction({
                url: '/sasisopa/informes-desempeno/implementacion/editar/descripcion',
                data: {
                    id: item.id,
                    descripcion: item.descripcion
                },
                notify: false

            });

        },

        async actualizarObservaciones(item) {

            await this.createAction({
                url: '/sasisopa/informes-desempeno/implementacion/editar/observacion',
                data: {
                    id: item.id,
                    observaciones: item.observaciones
                },
                notify: false

            });

        },

        async actualizarInformacion(item){

        await this.createAction({

            url: '/sasisopa/informes-desempeno/implementacion/editar/informacion',
            data: {
                id: item.id,
                informacion: item.informacion
            },
                notify: false

        });

    },

       async togglePuesto(item, puesto){

    const index = item.puestos.findIndex(
        p => p.id_lista == puesto.id
    );

    if(index !== -1){

        const res = await this.createAction({

            url: '/sasisopa/informes-desempeno/implementacion/delete/puesto',

            data:{

                procedimiento: item.id,
                puesto: puesto.id

            },
                notify: false

        });

        if(res.success){

            item.puestos.splice(index,1);

        }

    }else{

        const res = await this.createAction({

            url: '/sasisopa/informes-desempeno/implementacion/create/puesto',

            data:{

                procedimiento: item.id,
                id_lista: puesto.id,
                puesto: puesto.nombre

            },
                notify: false

        });

        if(res.success){

            item.puestos.push({

                id_lista: puesto.id,
                puesto: puesto.nombre

            });

        }

    }

},

        tienePuesto(item,idLista){

    return item.puestos.some(
        p => p.id_lista == idLista
    );

},

finalizar(){
    window.history.back();
}


    }));

});

