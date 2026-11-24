let sasisopaReporteDataTableSequence = 0;

window.destroySasisopaReporteDataTables = (root = document) => {
    if (!window.jQuery?.fn?.DataTable) return;

    window.jQuery(root).find('table.sasisopa-reporte-datatable').each(function () {
        if (window.jQuery.fn.DataTable.isDataTable(this)) {
            window.jQuery(this).DataTable().destroy();
        }
    });
};

window.initSasisopaReporteDataTables = (root = document, includeEmpty = false) => {
    const $ = window.jQuery;
    if (!$) return;

    const tables = $(root).find('table.sasisopa-reporte-datatable');
    if (!$.fn?.DataTable) return;

    tables.each(function () {
        const table = this;
        // Las tablas dentro de modales son reactivas (x-for) y DataTables
        // altera los nodos que Bootstrap/Alpine necesitan conservar.
        if (table.closest('.modal')) return;
        if ($.fn.DataTable.isDataTable(table)) return;

        if (!table.id) table.id = `tabla-sasisopa-reporte-${++sasisopaReporteDataTableSequence}`;

        const columnCount = table.tHead?.rows[0]?.cells.length ?? 0;
        const body = table.tBodies[0];
        if (!columnCount || !body) return;

        const columnasIndice = Array.from(table.tHead.rows[0].cells)
            .map((cell, index) => ({
                index,
                titulo: cell.textContent.trim().toLocaleLowerCase('es-MX'),
            }))
            .filter(({ titulo }) => /#|folio/i.test(titulo))
            .map(({ index }) => index);

        const hasDataRows = Array.from(body.rows).some((row) => row.cells.length === columnCount);
        if (!hasDataRows && !includeEmpty) return;

        // x-if puede dejar una fila de estado con colspan que DataTables no puede leer.
        table.tBodies[0]?.querySelectorAll('tr').forEach((row) => {
            if (row.cells.length === 1 && row.cells[0].colSpan > 1) {
                row.remove();
            }
        });

        try {
            const initialize = () => $(table).DataTable({
                    autoWidth: false,
                    stateSave: false,
                    order: [],
                    columnDefs: [
                        {
                            targets: columnasIndice,
                            width: '96px',
                            className: 'sasisopa-index-column text-center align-middle',
                        },
                        {
                            targets: '.sasisopa-pdf-column',
                            orderable: false,
                            searchable: false,
                            width: '48px',
                            className: 'sasisopa-pdf-column text-center align-middle',
                        },
                    ],
                    pageLength: 10,
                    lengthMenu: [10, 25, 50, 100],
                    language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
                });

            let dataTable;
            if (window.Alpine?.mutateDom) {
                dataTable = window.Alpine.mutateDom(initialize);
            } else {
                dataTable = initialize();
            }

            // El aislamiento global mueve esta tabla y Alpine elimina sus filas x-for.
            // Mantener el wrapper en su sitio preserva el DOM reactivo del reporte.
            const wrapper = table.closest('.dataTables_wrapper');
            if (wrapper) wrapper.dataset.scrollIsolated = 'true';

            // Reinsertar las filas desde la caché de DataTables tras el primer dibujo.
            const draw = () => dataTable.draw(false);
            if (window.Alpine?.mutateDom) {
                window.Alpine.mutateDom(draw);
            } else {
                draw();
            }
        } catch (error) {
            console.error('No se pudo inicializar la tabla del reporte SASISOPA.', error);
        }
    });
};

// Los DataTables pueden reconstruir las filas x-for; delegar el clic evita que
// Alpine evalúe el handler con un alcance `item` que ya fue retirado del DOM.
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-auditoria-asea-id]');
    if (!button) return;

    window.sasisopaReporte?.abrirAsea(button.dataset.auditoriaAseaId);
});

document.addEventListener('alpine:init', () => {
    Alpine.data('reporte', (fechaInicio, fechaTermino) => ({

        loading: false,
        fechaInicio,
        fechaTermino,

        get yearInicio() {
        return this.fechaInicio
            ? this.fechaInicio.split('-')[0]
            : '';
        },

        get yearFin() {
            return this.fechaTermino
                ? this.fechaTermino.split('-')[0]
                : '';
        },

        politica: [],

        elemento2:{
            analisis:[],
            asistencias:[]
        },

        elemento3: [],
        elemento5: [],
        elemento6: [],
        elemento7: {
            comunicaciones: [],
            quejas: []
        },
        elemento10: {},
        elemento12: [],
        elemento13: [],

        year:null,
        informes:[],
        hallazgos:[],

        auditoriasInternas:[],
        auditoriasExternas:[],
        ingresosAsea:[],
        modalAsea:null,

        registros: [],
        grupo: [],
        tercer: {
            id: '',
            nombre: '',
            numero: '',
            lider: '',
            fecha: '',
            archivo: ''
        },
        modalGrupo: null,
        modalTercer: null,
        sinAccidentes: [],

        revisionResultados: [],
        loadingRevision: false,

        evaluaciones: [],
        implementaciones: [],
        loading18: false,

    async init(){

        window.sasisopaReporte = this;
        this.modalAsea = new bootstrap.Modal(document.getElementById('modalAsea'));
        this.modalGrupo = new bootstrap.Modal(document.getElementById('modalGrupoInterdisciplinario'));
        this.modalTercer = new bootstrap.Modal(document.getElementById('modalTercerAutorizado'));

        await Promise.allSettled([
            this.cargarElemento1(),
            this.cargarElemento2(),
            this.cargarElemento3(),
            this.cargarElemento5(),
            this.cargarElemento6(),
            this.cargarElemento7(),
            this.cargarElemento10(),
            this.cargarElemento12(),
            this.cargarElemento13(),
            this.cargarElemento14(),
            this.cargarElemento15(),
            this.cargarElemento16(),
            this.cargarElemento17(),
            this.cargarElemento18(),
        ]);

        await this.actualizarTablasReporte(true);

    },

    async actualizarTablasReporte(incluirVacias = false) {
        await this.$nextTick();
        window.initSasisopaReporteDataTables(
            document.querySelector('#container.sasisopa-reporte'),
            incluirVacias
        );
    },

    async cargarElemento1(){
    this.loading=true;

    try{
        const res=await axios.get(
            '/sasisopa/reporte/elemento1',
            {
                params:{
                    inicio: this.fechaInicio,
                    fin: this.fechaTermino
                }
            }
        );

        this.politica=res.data;
        await this.actualizarTablasReporte();

    }finally{

        this.loading=false;

    }

    },

    async cargarElemento2(){
    try{

        const {data} = await axios.get(
            '/sasisopa/reporte/elemento2',
            {
                params:{
                    inicio:this.fechaInicio,
                    fin:this.fechaTermino
                }
            }
        );

        this.elemento2 = data;
        await this.actualizarTablasReporte();

    }catch(e){

        this.loading=false;

    }
    },

    async cargarElemento3(){

    try{

        const {data} = await axios.get(
            '/sasisopa/reporte/elemento3',
            {
                params:{
                    inicio:this.fechaInicio,
                    fin:this.fechaTermino
                }
            }
        );


        this.elemento3 = data;
        await this.actualizarTablasReporte();


    }catch(e){

        console.error(e);

    }finally{

        this.loading = false;

    }

    },

    async cargarElemento5(){

    try{

        const {data} = await axios.get(
            '/sasisopa/reporte/elemento5',
            {
                params:{
                    inicio:this.fechaInicio,
                    fin:this.fechaTermino
                }
            }
        );


        this.elemento5 = data;
        await this.actualizarTablasReporte();


    }catch(e){

        console.error(e);

    }finally{

        this.loading = false;

    }

    },

    async cargarElemento6(){
        try{

        const {data} = await axios.get(
            '/sasisopa/reporte/elemento6',
            {
                params:{
                    inicio:this.fechaInicio,
                    fin:this.fechaTermino
                }
            }
        );


        this.elemento6 = data;
        await this.actualizarTablasReporte();


    }catch(e){

        console.error(e);

    }finally{

        this.loading = false;

    }
    },

    async cargarElemento7(){

        try{

            const {data} = await axios.get(
                '/sasisopa/reporte/elemento7',
                {
                    params:{
                        inicio:this.fechaInicio,
                        fin:this.fechaTermino
                    }
                }
            );

            this.elemento7 = data;
            await this.actualizarTablasReporte();

        }catch(e){

            console.error(e);

        }

    },

    async cargarElemento10() {

    try {

        const { data } = await axios.get(
            '/sasisopa/reporte/elemento10',
            {
                params: {
                    inicio: this.fechaInicio,
                    fin: this.fechaTermino
                }
            }
        );

        this.elemento10 = data;

    } catch (e) {

        console.error(e);

    }

    },

    async cargarElemento12(){

        this.loading = true;

        try{

            const {data} = await axios.get(
                '/sasisopa/reporte/elemento12',
                {
                    params: {
                    inicio: this.fechaInicio,
                    fin: this.fechaTermino
                }
                }
            );

            this.elemento12 = data.data;
            await this.actualizarTablasReporte();

        }finally{

            this.loading = false;

        }

    },

    async cargarElemento13(){

        this.loading = true;

        try{

            const {data} = await axios.get(
                '/sasisopa/reporte/elemento13',
                {
                    params: {
                    inicio: this.fechaInicio,
                    fin: this.fechaTermino
                    }
                }
            );

            this.elemento13 = data.data;
            await this.actualizarTablasReporte();

        }finally{

            this.loading = false;

        }

    },

    async cargarElemento14(){

        this.loading=true;
        try{
           const {data} = await axios.get('/sasisopa/reporte/elemento14',
            {
            params:{
                inicio: this.fechaInicio,
                fin: this.fechaTermino

            }

        });

            this.year=data.year;

            this.informes=data.informes;

            this.hallazgos=data.hallazgos;

            await this.actualizarTablasReporte();

            this.loading=false;

        }finally{

            this.loading = false;

        }

    },

    async cargarElemento15(){

        this.loading=true;

        const {data}=await axios.get(
            '/sasisopa/reporte/elemento15',
            {
                params:{
                    inicio:this.fechaInicio,
                    fin:this.fechaFin
                }
            }
        );

        this.auditoriasInternas=data.interna;

        this.auditoriasExternas=data.externa;

        await this.actualizarTablasReporte();

        this.loading=false;

    },

    abrirAsea(idAuditoria){

        const registro = this.auditoriasExternas.find(
            item => String(item.id) === String(idAuditoria)
        );
        if (!registro) {
            window.Notify?.error('No se encontró la auditoría externa seleccionada.');
            return;
        }

        this.ingresosAsea = Array.isArray(registro.asea) ? registro.asea : [];
        this.$nextTick(() => {
            this.modalAsea.show();
        });

    },

    async cargarElemento16(){

    this.loading = true;

    try{

        const { data } = await axios.get(
            '/sasisopa/reporte/elemento16',
            {
                params:{
                    inicio:this.fechaInicio,
                    fin:this.fechaTermino
                }
            }
        );

        this.registros = data.investigaciones ?? [];
        this.sinAccidentes = data.sin_accidentes ?? [];
        await this.actualizarTablasReporte();

    }catch(e){

        this.registros = [];
        this.sinAccidentes = [];

    }finally{

        this.loading = false;

    }
    },

    async abrirGrupo(id) {

            try {

                const { data } = await axios.get(
                    `/sasisopa/reporte/elemento16/grupo/${id}`
                );

                this.grupo = data.data ?? [];
                await this.$nextTick();
                this.modalGrupo.show();

            } catch (e) {

                console.error(e);

            }

    },

    async abrirTercer(id) {

            try {

                const { data } = await axios.get(
                    `/sasisopa/reporte/elemento16/tercer/${id}`
                );

                this.tercer = data.data;
                await this.$nextTick();
                this.modalTercer.show();

            } catch (e) {

                console.error(e);

            }

    },

    async cargarElemento17(){

        this.loadingRevision = true;

        try{

            const {data} = await axios.get(

                '/sasisopa/reporte/elemento17',

                {
                    params:{

                        inicio:this.fechaInicio,

                        fin:this.fechaTermino

                    }
                }

            );

            this.revisionResultados = data.data ?? [];
            await this.actualizarTablasReporte();

        }catch(e){

            this.revisionResultados=[];

        }finally{

            this.loadingRevision=false;

        }

    },

    async cargarElemento18(){

    this.loading18 = true;

    try{

        const {data} = await axios.get(

            '/sasisopa/reporte/elemento18',

            {
                params:{
                    inicio:this.fechaInicio,
                    fin:this.fechaTermino
                }
            }

        );

        this.evaluaciones = data.evaluaciones ?? [];

        this.implementaciones = data.implementaciones ?? [];
        await this.actualizarTablasReporte();

    }catch(e){

        this.evaluaciones = [];

        this.implementaciones = [];

    }finally{

        this.loading18 = false;

    }

    },

    }));

});
