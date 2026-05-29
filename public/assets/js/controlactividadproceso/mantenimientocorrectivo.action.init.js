document.addEventListener('alpine:init', () => {

    Alpine.data('mantenimientoCorrectivo', () => ({
       
        pdfUrl: '',
        modalBuscar: null,
        years: [],

        filtro: {
            year: '',
            mes: ''
        },

        modalDetalle: null,

        detalle: {
            folio: '',
            fechacreacion: '',
            horacreacion: '',
            nombre_equipo: '',
            descripcion_hallazgo: '',
            descripcion_actividad: '',
            herramienta: ''
        },

        errorsBuscar: {
        year: false
        },

        modalEditar: null,
        form: {
            id: '',
            nombre_equipo: '',
            descripcion_hallazgo: '',
            descripcion_actividad: '',
            herramienta: ''
        },

        //-------------------------------

        modalEvidencia: null,
        idMantenimiento: null,
        files: [],
        previewImages: [],
        evidencias: [],

        init() {
            const currentYear = new Date().getFullYear();
            window.mantenimientoCorrectivo = this;
            this.modalBuscar = new bootstrap.Modal(document.getElementById('ModalBuscar'));
            this.modalDetalle = new bootstrap.Modal(document.getElementById('ModalDetalle'));
            this.modalEditar = new bootstrap.Modal(document.getElementById('ModalEditar'));
            this.modalEvidencia = new bootstrap.Modal(document.getElementById('ModalEvidencia'));

            this.pdfUrl = '/sasisopa/control-actividades-procesos/mantenimiento-correctivo/pdf';

            for (let i = 2020; i <= currentYear; i++) {
                this.years.push(i);
            }

        },

        limpiarBuscar(){
             Object.keys(this.errorsBuscar).forEach(k => this.errorsBuscar[k] = false);
        },

        openBuscarModal(){
            this.modalBuscar.show();
        },

        validateBuscar() {
            Object.keys(this.errorsBuscar).forEach(k => this.errorsBuscar[k] = false);
            let valid = true;

            if (!this.filtro.year) {
            this.errorsBuscar.year = true;
            valid = false;
            }

            return valid;
        },

        async buscar(){

        if (!this.validateBuscar()) {
                this.notify('error', 'Completa todos los campos obligatorios');
                return;
            }

               const url =
                    '/sasisopa/control-actividades-procesos/mantenimiento-correctivo/datatable'
                    + '?year=' + this.filtro.year
                    + '&mes=' + this.filtro.mes;

                table1
                    .ajax
                    .url(url)
                    .load();

                bootstrap.Modal
                .getInstance(document.getElementById('ModalBuscar'))
                .hide();

                this.pdfUrl = '/sasisopa/control-actividades-procesos/mantenimiento-correctivo/pdf?year=' + this.filtro.year + '&mes=' + this.filtro.mes;
            

        },

        async openModalDetalle(row){

    try{

        const response = await fetch(

            `/sasisopa/control-actividades-procesos/mantenimiento-correctivo/evidencias/${row.id}`
        );

        const json = await response.json();

        this.detalle = {

            folio: ('00' + row.folio) ?? '',

            fechacreacion:
            row.fechacreacion_larga ?? '',

            horacreacion:
            row.horacreacion ?? '',

            nombre_equipo:
            row.nombre_equipo ?? '',

            descripcion_hallazgo:
            row.descripcion_hallazgo ?? '',

            descripcion_actividad:
            row.descripcion_actividad ?? '',

            herramienta:
            row.herramienta ?? '',

            evidencias:
            json.data ?? []
        };

        this.modalDetalle.show();

    }catch(e){

        this.notify(
            'error',
            'Error al cargar evidencias'
        );
    }
},

        openModalEditar(row){

            this.form = {

                id: row.id,
                nombre_equipo: row.nombre_equipo ?? '',
                descripcion_hallazgo: row.descripcion_hallazgo ?? '',
                descripcion_actividad: row.descripcion_actividad ?? '',
                herramienta: row.herramienta ?? ''
            };

            this.modalEditar.show();
        },

        async guardarEditar(){

        if(
            !this.form.nombre_equipo ||
            !this.form.descripcion_hallazgo ||
            !this.form.descripcion_actividad ||
            !this.form.herramienta
        ){
            this.notify('error','Completa todos los campos');
            return;
        }

        try{

            let url = '/sasisopa/control-actividades-procesos/mantenimiento-correctivo/update';

            const res = await this.createAction({

                url,
                data: {
                    id: this.form.id,
                    nombre_equipo: this.form.nombre_equipo,
                    descripcion_hallazgo: this.form.descripcion_hallazgo,
                    descripcion_actividad: this.form.descripcion_actividad,
                    herramienta: this.form.herramienta
                },
                table: '#table-mantenimiento-correctivo'
            });

            if (res && res.success) {
                this.modalEditar.hide();
            }

        }catch(e){
            this.notify('error','Error al guardar');
        }
    },

        async evidencia(row){

        this.idMantenimiento = row.id;

        this.files = [];
        this.previewImages = [];

        try{

            const response = await fetch(
                `/sasisopa/control-actividades-procesos/mantenimiento-correctivo/evidencias/${row.id}`
            );

            const json = await response.json();

            this.evidencias = json.data ?? [];

            this.modalEvidencia.show();

        }catch(e){

            this.notify(
                'error',
                'Error al cargar evidencias'
            );
        }
    },

    handleFiles(event){

        const files = Array.from(event.target.files);

        files.forEach(file => {

            // VALIDAR SOLO IMAGENES
            if(!file.type.startsWith('image/')){

                this.notify(
                    'error',
                    'Solo se permiten imágenes'
                );

                return;
            }

            this.files.push(file);

            this.previewImages.push(
                URL.createObjectURL(file)
            );
        });
    },

    removePreview(index){

        this.files.splice(index,1);

        this.previewImages.splice(index,1);
    },

    async guardarEvidencias(){

    if(this.files.length <= 0){

        this.notify(
            'error',
            'Selecciona al menos una imagen'
        );

        return;
    }

    try{

        const formData = new FormData();

        formData.append(
            'id_mantenimiento',
            this.idMantenimiento
        );

        this.files.forEach(file => {

            formData.append(
                'imagenes[]',
                file
            );
        });

        const res = await this.createAction({

            url: '/sasisopa/control-actividades-procesos/mantenimiento-correctivo/evidencias/create',

            data: formData,

            isFile: true
        });

        if(res && res.success){

            // LIMPIAR INPUTS
            this.files = [];
            this.previewImages = [];

            // RECARGAR EVIDENCIAS
            const response = await fetch(

                `/sasisopa/control-actividades-procesos/mantenimiento-correctivo/evidencias/${this.idMantenimiento}`
            );

            const json = await response.json();

            this.evidencias = json.data ?? [];

            // LIMPIAR INPUT FILE
            const input = document.querySelector(
                '#ModalEvidencia input[type="file"]'
            );

            if(input){
                input.value = '';
            }

        }else{

            this.notify(
                'error',
                res.message || 'Error al guardar'
            );
        }

    }catch(e){

        this.notify(
            'error',
            'Error al guardar evidencias'
        );
    }
},

    async eliminarEvidencia(id,index){

        let name = 'evidencia';

            const res = await this.deleteAction({
                url: '/sasisopa/control-actividades-procesos/mantenimiento-correctivo/evidencias/delete',
                id,
                name
            });


            if(res.success){
                this.evidencias.splice(index,1);
            }
    },

       

    }));

});