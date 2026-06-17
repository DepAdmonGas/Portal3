document.addEventListener('alpine:init', () => {

    Alpine.data('preparacionEmergencias', () => ({

        //  Protocolo de respuestas a emergencias

        modoProtocolo: 'create',
        editIdProtocolo: null,
        protocoloModal: null,
        protocolos: [],

        protocolo: {
            fecha: '',
            archivo: null
        },

        idProtocoloActual: null,

        errors: {
            protocolo: {
                fecha: false,
                archivo: false
            }
        },

        anexoModal: null,
        protocoloAnexoId: null,
        anexos: [],

        anexo: {
            nombre: '',
            archivo: null

        },

        errors: {
            protocolo: {
                fecha: false,
                archivo: false
            },

            anexo: {
                nombre: false,
                archivo: false
            },

            programa: {
                nombre_simulacro: false,
                fecha: false
            },

            personal: {

                usuarios: false
            },

            resumen: {
                resumen: false
            },

             evaluacion: {
                archivo: false
            }

        },

        // Telefonos de emergencia
        modalTelefonos: null,
        telefonos: [],
        mostrarFormulario: false,
        modoTelefono: 'create',
        telefono: {
            id: null,
            titulo: '',
            telefono: ''
        },

        //  Programa anual

        programaModal: null,
        modoPrograma: 'create',
        editIdPrograma: null,

        programa: {
            nombre_simulacro: '',
            periodicidad: 'Trimestral',
            fecha: ''
        },

        // Modal personal

        personalModal: null,
        idProgramaPersonal: null,
        personalAsistente: [],
        usuariosDisponibles: [],
        usuariosSeleccionados: [],

        // Modal Resumen

        resumenModal: null,
        idProgramaResumen: null,
        resumenSimulacro: {
            resumen: ''
        },

        // Modal evaluacion

        evaluacionModal: null,
        idProgramaEvaluacion: null,
        evaluacion: {
            archivo: null
        },

        pdfUrl: '',
        modalBuscar: null,
        errorsBuscar: {
        year: false
        },   
        years: [],

        filtro: {
            year: '',
            mes: ''
        },

        init() {
             const currentYear = new Date().getFullYear();

            this.obtenerProtocolos();
            window.preparacionEmergencias = this;

            this.protocoloModal = new bootstrap.Modal(document.getElementById('modalProtocolo'));
            this.modalTelefonos = new bootstrap.Modal(document.getElementById('modalTelefonosEmergencia'));
            this.anexoModal = new bootstrap.Modal(document.getElementById('modalAnexos'));
            this.programaModal = new bootstrap.Modal(document.getElementById('modalPrograma'));
            this.personalModal = new bootstrap.Modal(document.getElementById('modalPersonal')); 
            this.resumenModal = new bootstrap.Modal(document.getElementById('modalResumen'));
            this.evaluacionModal = new bootstrap.Modal(document.getElementById('modalEvaluacion'));     
             this.modalBuscar = new bootstrap.Modal(document.getElementById('ModalBuscar'));
            
             this.pdfUrl = '/sasisopa/preparacion-emergencias/simulacro/pdf';

            for(let i = 2020; i <= currentYear; i++){
                this.years.push(i);
            }

        },

    //------- Protocolos

    async obtenerProtocolos()
    {
        try {

            const response = await axios.get(
                    '/sasisopa/preparacion-emergencias/protocolo/get'
                );

            if(!response.data.success){
                this.notify(
                    'error',
                    'No fue posible consultar'
                );
                return;
            }

            this.protocolos = response.data.data ?? [];

        } catch(error){
            this.notify(
                'error',
                'Error al consultar protocolos'
            );
        }
    },

    nuevoProtocolo()
    {
        this.limpiarProtocolo();
        this.modoProtocolo = 'create';

        this.editIdProtocolo = null;

        this.protocolo = {
            fecha: '',
            archivo: null
        };

        const file = document.getElementById('archivoProtocolo');

        if(file){
            file.value = '';
        }

        this.protocoloModal.show();
    },

    validarProtocolo()
    {
        let valido = true;

        this.errors.protocolo.fecha = false;
        this.errors.protocolo.archivo = false;

        if (!this.protocolo.fecha) {

            this.errors.protocolo.fecha = true;
            valido = false;
        }

        // Solo obligatorio al crear
        if (
            this.modoProtocolo === 'create'
            &&
            !this.protocolo.archivo
        ) {

            this.errors.protocolo.archivo = true;
            valido = false;
        }

        return valido;
    },

    limpiarProtocolo()
    {
        this.modoProtocolo = 'create';

        this.editIdProtocolo = null;

        this.protocolo = {
            fecha: '',
            archivo: null
        };

        this.errors.protocolo.fecha = false;
        this.errors.protocolo.archivo = false;

        const archivo =
            document.getElementById(
                'archivoProtocolo'
            );

        if (archivo) {
            archivo.value = '';
        }
    },

    editarProtocolo(item)
    {
        this.modoProtocolo = 'edit';

        this.editIdProtocolo = item.id;

        this.protocolo = {
            fecha: item.fechacreacion,
            archivo: null
        };

        const file = document.getElementById( 'archivoProtocolo');

        if(file){
            file.value = '';
        }

        this.protocoloModal.show();
    },

    async guardarProtocolo()
    {

        if (!this.validarProtocolo()) {

            this.notify(
                'error',
                'Completa los campos obligatorios'
            );

            return;
        }

        try {

            const formData = new FormData();

            formData.append(
                'fecha',
                this.protocolo.fecha
            );

            if(this.editIdProtocolo){

                formData.append(
                    'id',
                    this.editIdProtocolo
                );
            }

            if(this.protocolo.archivo){

                formData.append(
                    'archivo',
                    this.protocolo.archivo
                );
            }

            const url =
                this.modoProtocolo === 'create'
                ? '/sasisopa/preparacion-emergencias/protocolo/create'
                : '/sasisopa/preparacion-emergencias/protocolo/update';

            const res =
                await this.createAction({
                    url,
                    data: formData
                });

            if (res && res.success) {

            await this.obtenerProtocolos();
            this.protocoloModal.hide();
            this.limpiarProtocolo();

            }

        } catch(error){

            this.notify(
                'error',
                'Error al guardar protocolo'
            );
        }
    },

    async eliminarProtocolo(id)
    {
        const res =
            await this.deleteAction({
                url:'/sasisopa/preparacion-emergencias/protocolo/delete',
                id,
                name: 'Protocolo'
            });

        if(!res?.success){
            return;
        }

        await this.obtenerProtocolos();
    },

    async abrirAnexos(idProtocolo)
    {
        this.limpiarAnexo();
        this.protocoloAnexoId = idProtocolo;

        await this.obtenerAnexos();

        this.anexoModal.show();
    },

    async obtenerAnexos()
    {
        try {

            const response =
                await axios.get(
                    '/sasisopa/preparacion-emergencias/protocolo/anexos/get/' +
                    this.protocoloAnexoId
                );

            this.anexos =
                response.data.data ?? [];

        } catch(error){

            this.notify(
                'error',
                'Error al consultar anexos'
            );
        }
    },

    validarAnexo()
    {
        let valido = true;

        this.errors.anexo.nombre = false;
        this.errors.anexo.archivo = false;

        if (!this.anexo.nombre) {

            this.errors.anexo.nombre = true;

            valido = false;
        }

        if (!this.anexo.archivo) {

            this.errors.anexo.archivo = true;

            valido = false;
        }

        return valido;
    },

    limpiarAnexo()
    {
        this.anexo = {

            nombre: '',

            archivo: null
        };

        this.errors.anexo.nombre = false;
        this.errors.anexo.archivo = false;

        const archivo =
            document.getElementById(
                'archivoAnexo'
            );

        if (archivo) {

            archivo.value = '';
        }
    },

    async guardarAnexo()
    {
        if (!this.validarAnexo()) {

            this.notify(
                'error',
                'Completa los campos obligatorios'
            );

            return;
        }

        try {

            const formData =
                new FormData();

            formData.append(
                'id_protocolo',
                this.protocoloAnexoId
            );

            formData.append(
                'nombre_anexo',
                this.anexo.nombre
            );

            formData.append(
                'archivo',
                this.anexo.archivo
            );

            const res =
                await this.createAction({

                    url:
                    '/sasisopa/preparacion-emergencias/protocolo/anexos/create',

                    data:
                    formData
                });

            if (!res?.success) {

                return;
            }

            this.limpiarAnexo();

            await this.obtenerAnexos();

        } catch (error) {

            console.error(error);

            this.notify(
                'error',
                error?.message ??
                'Error al guardar anexo'
            );
        }
    },

    async eliminarAnexo(id)
    {
        const res =
            await this.deleteAction({

                url:
                '/sasisopa/preparacion-emergencias/protocolo/anexos/delete',

                id,

                name:
                'Anexo'
            });

        if(!res?.success){
            return;
        }

        await this.obtenerAnexos();
    },
        
        //---- Telefonos de emergencia
        async abrirModalTelefonos() {

            await this.obtenerTelefonos();

            this.modalTelefonos.show();

        },

        async obtenerTelefonos() {

            try {

                const response = await axios.get(
                    '/sasisopa/preparacion-emergencias/telefonos/get'
                );

                this.telefonos = response.data.data || [];

            } catch (error) {

                this.notify(
                    'error',
                    'Error al cargar teléfonos'
                );

            }

        },

        nuevoTelefono() {

            this.modoTelefono = 'create';

            this.telefono = {
                id: null,
                titulo: '',
                telefono: ''
            };

            this.mostrarFormulario = true;

        },

        editarTelefono(item) {

            this.modoTelefono = 'edit';

            this.telefono = {
                id: item.id,
                titulo: item.titulo,
                telefono: item.telefono
            };

            this.mostrarFormulario = true;

        },

        cancelarTelefono() {

            this.telefono = {
                id: null,
                titulo: '',
                telefono: ''
            };

            this.mostrarFormulario = false;

        },

        async guardarTelefono() {

            if (
                !this.telefono.titulo.trim() ||
                !this.telefono.telefono.trim()
            ) {

                this.notify(
                    'error',
                    'Completa todos los campos'
                );

                return;
            }

            let url =
                '/sasisopa/preparacion-emergencias/telefonos/create';

            if (this.modoTelefono === 'edit') {

                url =
                    '/sasisopa/preparacion-emergencias/telefonos/update';
            }

            try {

                const response = await axios.post(
                    url,
                    this.telefono
                );

                if (!response.data.success) {

                    this.notify(
                        'error',
                        response.data.message
                    );

                    return;
                }

                this.notify(
                    'success',
                    response.data.message
                );

                this.cancelarTelefono();

                await this.obtenerTelefonos();

            } catch (error) {

                this.notify(
                    'error',
                    'Error al guardar'
                );

            }

        },

        async eliminarTelefono(id, nombre) {

            const response = await this.deleteAction({

                url:
                    '/sasisopa/preparacion-emergencias/telefonos/delete',

                id,

                name: nombre

            });

            if (response?.success) {

                await this.obtenerTelefonos();

            }

        },
    //-----------------------------------------------
    //------ programa Anual -----------------------

    nuevoPrograma()
    {
        this.modoPrograma = 'create';

        this.limpiarPrograma();

        this.programaModal.show();
    },

    limpiarPrograma()
    {
        this.editIdPrograma = null;

        this.programa = {

            nombre_simulacro: '',

            periodicidad: 'Trimestral',

            fecha: ''
        };

        this.errors.programa.nombre_simulacro = false;

        this.errors.programa.fecha = false;
    },

    validarPrograma()
    {
        let valido = true;

        this.errors.programa.nombre_simulacro = false;

        this.errors.programa.fecha = false;

        if (!this.programa.nombre_simulacro) {

            this.errors.programa.nombre_simulacro = true;

            valido = false;
        }

        if (!this.programa.fecha) {

            this.errors.programa.fecha = true;

            valido = false;
        }

        return valido;
    },

    editarPrograma(item)
    {
        this.limpiarPrograma();

        this.modoPrograma = 'edit';

        this.editIdPrograma =
            item.id;

        this.programa.nombre_simulacro =
            item.nombre_simulacro;

        this.programa.fecha =
            item.fecha;

        this.programaModal.show();
    },

    async guardarPrograma()
    {
        if (!this.validarPrograma()) {

            this.notify(
                'error',
                'Completa los campos obligatorios'
            );

            return;
        }

        const payload = {

            nombre_simulacro:
                this.programa.nombre_simulacro,

            periodicidad:
                'Trimestral',

            fecha:
                this.programa.fecha
        };

        let url =
            '/sasisopa/preparacion-emergencias/simulacro/create';

        if (
            this.modoPrograma === 'edit'
        ) {

            payload.id =
                this.editIdPrograma;

            url =
                '/sasisopa/preparacion-emergencias/simulacro/update';
        }

        const res =
            await this.createAction({

                url,

                data: payload,

                table:'#table-programa-simulacro'
            });

        if (!res?.success) {

            return;
        }

        this.programaModal.hide();

    },

    async eliminarPrograma(id, nombre) {

        const response = await this.deleteAction({

            url: '/sasisopa/preparacion-emergencias/simulacro/delete',
            id,
            name: nombre,
            table:'#table-programa-simulacro'

        });

    },

    async abrirPersonal(idPrograma)
    {
        this.idProgramaPersonal =
            idPrograma;

        await Promise.all([

            this.obtenerPersonal(),

            this.obtenerUsuariosDisponibles()
        ]);

        this.personalModal.show();

        this.$nextTick(() => {

            this.inicializarSelect2();

        });
    },

    inicializarSelect2()
    {
        const self = this;

        $('#selectPersonal')
            .off()
            .empty();

        this.usuariosDisponibles
            .forEach(usuario => {

                $('#selectPersonal').append(

                    new Option(
                        usuario.nombre,
                        usuario.nombre
                    )
                );
            });

        $('#selectPersonal')
            .select2({

                width: '100%',

                dropdownParent:
                    $('#modalPersonal')
            });

        $('#selectPersonal')
            .on(
                'change',
                function () {

                    self.usuariosSeleccionados =
                        $(this).val() || [];

                    self.errors.personal.usuarios =
                        false;
                }
            );
    },

    async obtenerPersonal()
    {
        const response =
            await axios.get(

                '/sasisopa/preparacion-emergencias/simulacro/personal/get/' +

                this.idProgramaPersonal
            );

        this.personalAsistente =
            response.data.data || [];
    },

    async obtenerUsuariosDisponibles()
    {
        const response =
            await axios.get(

                '/sasisopa/preparacion-emergencias/simulacro/personal/usuarios/' +

                this.idProgramaPersonal
            );

        this.usuariosDisponibles =
            response.data.data || [];
    },

    async agregarPersonal()
    {
        if (
            this.usuariosSeleccionados.length === 0
        ) {

            this.errors.personal.usuarios =
                true;

            this.notify(
                'error',
                'Seleccione al menos un usuario'
            );

            return;
        }

        const res =
            await this.createAction({

                url:
                '/sasisopa/preparacion-emergencias/simulacro/personal/create',

                data: {

                    id_programa:
                        this.idProgramaPersonal,

                    usuarios:
                        this.usuariosSeleccionados
                },
                table:'#table-programa-simulacro'
            });

        if (!res?.success) {

            return;
        }

        this.usuariosSeleccionados = [];

        await this.obtenerPersonal();

        await this.obtenerUsuariosDisponibles();

        this.inicializarSelect2();
    },

    async eliminarPersonal(id)
    {
        const res =
            await this.deleteAction({

                url:
                '/sasisopa/preparacion-emergencias/simulacro/personal/delete',

                id,

                name: 'Personal',
                table:'#table-programa-simulacro'
            });

        if (!res?.success) {

            return;
        }

        await this.obtenerPersonal();

        await this.obtenerUsuariosDisponibles();

        this.inicializarSelect2();
    },

    async abrirResumen(idPrograma)
    {
        this.idProgramaResumen =
            idPrograma;

        this.resumenSimulacro = {

            resumen: ''
        };

        this.errors.resumen.resumen =
            false;

        try {

            const response =
                await axios.get(

                    '/sasisopa/preparacion-emergencias/simulacro/resumen/get/' +

                    idPrograma
                );

            if(response.data.success){

                this.resumenSimulacro.resumen =
                    response.data.data?.resumen ?? '';
            }

            this.resumenModal.show();

        } catch(error){

            this.notify(
                'error',
                'Error al obtener resumen'
            );
        }
    },

    validarResumen()
    {
        let valido = true;

        this.errors.resumen.resumen =
            false;

        if(
            !this.resumenSimulacro.resumen ||
            !this.resumenSimulacro.resumen.trim()
        ){

            this.errors.resumen.resumen =
                true;

            valido = false;
        }

        return valido;
    },

    async guardarResumen()
    {
        if(!this.validarResumen()){

            this.notify(
                'error',
                'Completa los campos obligatorios'
            );

            return;
        }

        const res =
            await this.createAction({

                url:
                '/sasisopa/preparacion-emergencias/simulacro/resumen/create',

                data: {

                    id_programa:
                        this.idProgramaResumen,

                    resumen:
                        this.resumenSimulacro.resumen
                },
                table:'#table-programa-simulacro'
            });

        if(!res?.success){

            return;
        }

        this.resumenModal.hide();
    },

    abrirEvaluacion(idPrograma)
    {
        this.idProgramaEvaluacion =
            idPrograma;

        this.evaluacion = {

            archivo: null
        };

        this.errors.evaluacion.archivo =
            false;

        const file =
            document.getElementById(
                'archivoEvaluacion'
            );

        if(file){

            file.value = '';
        }

        this.evaluacionModal.show();
    },

    validarEvaluacion()
    {
        let valido = true;

        this.errors.evaluacion.archivo =
            false;

        if(!this.evaluacion.archivo){

            this.errors.evaluacion.archivo =
                true;

            valido = false;
        }

        return valido;
    },

    async guardarEvaluacion()
    {
        if(
            !this.validarEvaluacion()
        ){

            this.notify(
                'error',
                'Selecciona un archivo PDF'
            );

            return;
        }

        try {

            const formData =
                new FormData();

            formData.append(

                'id_programa',

                this.idProgramaEvaluacion
            );

            formData.append(

                'archivo',

                this.evaluacion.archivo
            );

            const res =
                await this.createAction({

                    url: '/sasisopa/preparacion-emergencias/simulacro/evaluacion/create',
                    data: formData,
                    table:'#table-programa-simulacro'
                });

            if(!res?.success){

                return;
            }

            this.evaluacionModal.hide();

            this.evaluacion.archivo =
                null;

            document.getElementById(
                'archivoEvaluacion'
            ).value = '';


        } catch(error){

            this.notify(
                'error',
                'Error al guardar evaluación'
            );
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

        const url = '/sasisopa/preparacion-emergencias/simulacro/datatable'
            + '?year=' + this.filtro.year
            + '&mes=' + this.filtro.mes;

            table1
            .ajax
            .url(url)
            .load();

            bootstrap.Modal
            .getInstance(document.getElementById('ModalBuscar'))
            .hide();

            this.pdfUrl = '/sasisopa/preparacion-emergencias/simulacro/pdf?year=' + this.filtro.year + '&mes=' + this.filtro.mes;

    },

    }));

});