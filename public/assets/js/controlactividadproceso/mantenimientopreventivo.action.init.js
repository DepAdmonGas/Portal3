document.addEventListener('alpine:init', () => {

    Alpine.data('mantenimientoPreventivo', () => ({

        pdfUrl: '',

        modalBuscar: null,

        equipos: [],

        years: [],

        filtro: {
            equipo: '',
            year: '',
            mes: ''
        },

        modalDetalle: null,

         detalle: {
            folio: '',
            fechacreacion: '',
            horacreacion: '',
            nombre_equipo: '',
            evidencias: []
        },

        modalEvidencia: null,
        idMantenimiento: null,
        files: [],
        previewImages: [],
        evidencias: [],

        init() {

            const currentYear = new Date().getFullYear();
            window.mantenimientoPreventivo = this;

            this.modalBuscar = new bootstrap.Modal(
                this.$refs.ModalBuscar
            );

            this.modalDetalle = new bootstrap.Modal(document.getElementById('ModalDetalle'));
            this.modalEvidencia = new bootstrap.Modal(document.getElementById('ModalEvidencia'));

            // YEARS
            for (let i = 2020; i <= currentYear; i++) {
                this.years.push(i);
            }

            // PDF DEFAULT
            this.updatePdfUrl();

            // EQUIPOS
            this.getEquipos();

        },

        // =====================================================
        // MODAL
        // =====================================================

        openBuscarModal() {
            this.limpiarBuscar();
            this.modalBuscar.show();
        },

        limpiarBuscar() {

            this.filtro = {
                equipo: '',
                year: '',
                mes: ''
            };

            $('#selectEquipo')
                .val(null)
                .trigger('change');

            this.updatePdfUrl();
        },

        // =====================================================
        // EQUIPOS
        // =====================================================

        async getEquipos() {

            try {

                const response = await fetch(
                    '/sasisopa/control-actividades-procesos/mantenimiento-preventivo/get'
                );

                this.equipos = await response.json();

                this.$nextTick(() => {

                    $('#selectEquipo').select2({
                        dropdownParent: $('#ModalBuscar'),
                        placeholder: 'Todos',
                        width: '100%'
                    });

                    $('#selectEquipo').on(
                        'change',
                        (e) => {
                            this.filtro.equipo = e.target.value;
                        }
                    );

                });

            } catch (error) {

                this.notify(
                    'error',
                    'Error al cargar equipos'
                );
            }
        },

        // =====================================================
        // BUSCAR
        // =====================================================

        async buscar() {
            const params = new URLSearchParams();
            if (this.filtro.equipo) {
                params.set('id_equipo', this.filtro.equipo);
            }

            if (this.filtro.year) {
                params.set('year', this.filtro.year);
            }

            if (this.filtro.mes) {
                params.set('mes', this.filtro.mes);
            }

            const query = params.toString();
            const url = '/sasisopa/control-actividades-procesos/mantenimiento-preventivo/datatable'
                + (query ? `?${query}` : '');

            table1
                .ajax
                .url(url)
                .load();

            this.updatePdfUrl(false);

            this.modalBuscar.hide();
        },

        // =====================================================
        // PDF
        // =====================================================

        updatePdfUrl(usarAnioActual = true) {
            const currentYear = new Date().getFullYear();
            const baseUrl = '/sasisopa/control-actividades-procesos/mantenimiento-preventivo/pdf';

            const params = new URLSearchParams();

            if (this.filtro.equipo) {
                params.set('equipo', this.filtro.equipo);
            }

            if (this.filtro.year) {
                params.set('year', this.filtro.year);
            } else if (usarAnioActual) {
                params.set('year', currentYear);
            }

            if (this.filtro.mes) {
                params.set('mes', this.filtro.mes);
            }

            const query = params.toString();
            this.pdfUrl = baseUrl + (query ? `?${query}` : '');
        },

        async openModalDetalle(row){

             try {
                const response = await fetch(
                    `/sasisopa/control-actividades-procesos/mantenimiento-preventivo/evidencias/${row.id}`
                );
                const json = await response.json();
                this.detalle = {
                    folio: row.folio ?? '',
                    fechacreacion: row.fechacreacion_larga ?? '',
                    horacreacion: row.horacreacion ?? '',
                    nombre_equipo: row.detalle ?? '',
                    evidencias: json.data ?? []
                };
                this.modalDetalle.show();
            } catch (e) {
                this.notify('error', 'Error al cargar evidencias');
            }
        },

        async evidencia(row){

            this.idMantenimiento = row.id;

            this.files = [];
            this.previewImages = [];

            try{

                const response = await fetch(
                    `/sasisopa/control-actividades-procesos/mantenimiento-preventivo/evidencias/${row.id}`
                );

                const json = await response.json();

                this.evidencias = json.data ?? [];

                console.log(json.data);

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

                url: '/sasisopa/control-actividades-procesos/mantenimiento-preventivo/evidencias/create',

                data: formData,

                isFile: true
            });

            if(res && res.success){

                // LIMPIAR INPUTS
                this.files = [];
                this.previewImages = [];

                // RECARGAR EVIDENCIAS
                const response = await fetch(

                    `/sasisopa/control-actividades-procesos/mantenimiento-preventivo/evidencias/${this.idMantenimiento}`
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
                url: '/sasisopa/control-actividades-procesos/mantenimiento-preventivo/evidencias/delete',
                id,
                name
            });


            if(res.success){
                this.evidencias.splice(index,1);
            }
    },

    }));

});
