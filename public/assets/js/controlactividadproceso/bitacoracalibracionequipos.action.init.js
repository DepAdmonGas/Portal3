document.addEventListener('alpine:init', () => {
    Alpine.data('bitacoraCalibracionEquipos', () => ({

       modalNuevo: null,
       modalResultados: null,

       equipo: '',
       errorNuevo: {
        equipo: false
       },

       modalResultados: null,
       archivoResultado: null,
       resultadoSeleccionado: {
            id: null,
            equipo: '',
            fecha: '',
            resultados: ''
        },

        modalDetalle: null,
        detalle: null,
        otrosDetalle: null,
        tablaDetalle: '',

         pdfUrl: '',
        modalBuscar: null,
        years: [],

         filtro: {
            year: '',
            mes: ''
        },

         errorsBuscar: {
        year: false
        },       

        init() {
        const currentYear = new Date().getFullYear();
        window.bitacoraCalibracionEquipos = this;
        this.modalNuevo = new bootstrap.Modal(document.getElementById('modalNuevo'));
        this.modalResultados = new bootstrap.Modal(document.getElementById('modalResultados'));
        this.modalDetalle = new bootstrap.Modal(document.getElementById('modalDetalle'));
        this.modalBuscar = new bootstrap.Modal(document.getElementById('ModalBuscar'));

        this.pdfUrl = '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos/pdf';

            for (let i = 2020; i <= currentYear; i++) {
                this.years.push(i);
            }

        },

        validateNuevo(){
            let valid = true;

            Object.keys(this.errorNuevo)
            .forEach(k => this.errorNuevo[k] = false);

            if (!this.equipo) {
                this.errorNuevo.equipo = true;
                valid = false;
            }

            return valid;

        },

         closeModalNuevo() {

            if (this.modalNuevo) {
                this.modalNuevo.hide();
            }
        },

        limpiarNuevo() {
            this.equipo = '';

            Object.keys(this.errorNuevo)
            .forEach(k => this.errorNuevo[k] = false);
        },

        modalNuevoOpen(){

            this.limpiarNuevo();
            this.modalNuevo.show();

        },

        async guardarNuevo() {

             if (!this.validateNuevo()) {
                this.notify('error','Completa todos los campos');
                return;
            }

             try {

                const url = '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos/create';

                const payload = {
                    equipo: this.equipo
                };

                const res = await this.createAction({
                    url,
                    data: payload
                });

                if (res?.success) {
                    window.location.href = res.data.redirect;
                }

            } catch (e) {

                this.notify('error','Error al guardar');
            }

        },

        abrirModalResultados(item)
        {
            this.resultadoSeleccionado = {
                id: item.id,
                equipo: item.equipo,
                fecha: item.fecha_larga,
                resultado: item.resultado
            };

            this.archivoResultado = null;

            this.modalResultados.show();
        },

    async guardarResultado()
    {
        if (!this.archivoResultado) {

            this.notify(
                'error',
                'Seleccione un PDF'
            );

            return;
        }

        try {

            const formData = new FormData();
            formData.append('id',this.resultadoSeleccionado.id);
            formData.append('documento',this.archivoResultado);

            const response = await this.createAction({
                    url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos/upload-resultado',
                    data: formData,
                    isFile: true
                });


            this.resultadoSeleccionado.resultados = response.archivo;
            this.modalResultados.hide();
            table1.ajax.reload(null,false);

        } catch (e) {

            this.notify(
                'error',
                'Error al guardar archivo'
            );
        }
    },

    async abrirDetalle(id)
    {
        try {

            const response =
                await fetch(
                    `/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos/detalle/${id}`
                );

            const data =
                await response.json();

            if (!data.success) {
                return;
            }

            this.detalle = data.data;

            this.generarTabla();

            this.modalDetalle.show();

        } catch (e) {

            this.notify(
                'error',
                'Error al cargar detalle'
            );
        }
    },

    generarTabla()
    {
        const equipo = this.detalle.equipo;

        if (equipo === 'Jarra patron') {

            this.otrosDetalle = `
            <div class="row g-3">

            <div class="col-md-3">
                <label class="form-label mb-1">Temperatura ambiente:</label>
                <div class="">${this.detalle.temperatura_ambiente}</div>
            </div>

            <div class="col-md-3">
                <label class="form-label mb-1">Presión atmosférica:</label>
                <div class="">${this.detalle.presion_atmosferica}</div>
            </div>

            <div class="col-md-3">
                <label class="form-label mb-1">Humedad:</label>
                <div class="">${this.detalle.humedad}</div>
            </div>

            <div class="col-md-3">
                <label class="form-label mb-1">Liquido usado en la calibración:</label>
                <div class="">${this.detalle.liquido_calibracion}</div>
            </div>

            <div class="col-md-3">
                <label class="form-label mb-1">Temperatura del líquido:</label>
                <div class="">${this.detalle.temperatura_liquido}</div>
            </div>

            <div class="col-md-3">
                <label class="form-label mb-1">Laboratorio de calibración:</label>
                <div class="">${this.detalle.laboratorio_calibracion}</div>
            </div>

            <div class="col-md-3">
                <label class="form-label mb-1">No. de acreditación:</label>
                <div class="">${this.detalle.numero_acreditacion}</div>
            </div>

            <div class="col-md-3">
                <label class="form-label mb-1">Método de calibración:</label>
                <div class="">${this.detalle.metodo_calibracion}</div>
            </div>

            </div>
            `;

            this.tablaDetalle = `
                <table class="table table-striped table-bordered mb-0 text-nowrap align-middle">

                    <thead>
                        <tr>
                            <th class="text-center align-middle">Marca</th>
                            <th class="text-center align-middle">Serie</th>
                            <th class="text-center align-middle">Capacidad</th>
                            <th class="text-center align-middle">Incertidumbre</th>
                        </tr>
                    </thead>

                    <tbody>

                    ${this.detalle.jarras.map(j => `
                        <tr>
                            <td>${j.jarra?.marca ?? ''}</td>
                            <td>${j.jarra?.no_serie ?? ''}</td>
                            <td>${j.jarra?.capacidad ?? ''}</td>
                            <td>${j.resultado1 ?? ''}</td>
                        </tr>
                    `).join('')}

                    </tbody>

                </table>
            `;

            return;
        }

        if (equipo === 'Sondas de medición') {

            this.otrosDetalle = `
            <div class="row g-3">

            <div class="col-md-3">
                <label class="form-label mb-1">Unidad de verificación:</label>
                <div class="">${this.detalle.unidad_verificacion}</div>
            </div>

            <div class="col-md-3">
                <label class="form-label mb-1">No. de acreditación:</label>
                <div class="">${this.detalle.numero_acreditacion}</div>
            </div>

            <div class="col-md-3">
                <label class="form-label mb-1">Método usado para la calibración:</label>
                <div class="">${this.detalle.metodo_usado_calibracion}</div>
            </div>

            </div>
            `;

            this.tablaDetalle = `
                <table class="table table-striped table-bordered mb-0 text-nowrap align-middle">

                    <thead>
                        <tr>
                            <th class="text-center align-middle">No. Sonda</th>
                            <th class="text-center align-middle">Marca</th>
                            <th class="text-center align-middle">Modelo</th>
                            <th class="text-center align-middle">Incertidumbre</th>
                        </tr>
                    </thead>

                    <tbody>

                    ${this.detalle.sondas.map(s => `
                        <tr>
                            <td class="text-center align-middle">${s.sonda?.no_sonda ?? ''}</td>
                            <td class="text-center align-middle">${s.sonda?.marca ?? ''}</td>
                            <td class="text-center align-middle">${s.sonda?.modelo ?? ''}</td>
                            <td class="text-center align-middle">${s.resultado1 ?? ''}</td>
                        </tr>
                    `).join('')}

                    </tbody>

                </table>
            `;

            return;
        }

        if (equipo === 'Tanques de almacenamiento') {

            this.otrosDetalle = `
            <div class="row g-3">

            <div class="col-md-3">
                <label class="form-label mb-1">Unidad de verificación:</label>
                <div class="">${this.detalle.unidad_verificacion}</div>
            </div>

            <div class="col-md-3">
                <label class="form-label mb-1">No. de acreditación:</label>
                <div class="">${this.detalle.numero_acreditacion}</div>
            </div>

            <div class="col-md-3">
                <label class="form-label mb-1">Método usado para la calibración:</label>
                <div class="">${this.detalle.metodo_usado_calibracion}</div>
            </div>

            </div>
            `;

            this.tablaDetalle = `
                <table class="table table-striped table-bordered mb-0 text-nowrap align-middle">

                    <thead>
                        <tr>
                            <th class="text-center align-middle">Tanque</th>
                            <th class="text-center align-middle">Capacidad</th>
                            <th class="text-center align-middle">Producto</th>
                            <th class="text-center align-middle">Incertidumbre</th>
                            <th class="text-center align-middle">Cumple</th>
                        </tr>
                    </thead>

                    <tbody>

                    ${this.detalle.tanques.map(t => `
                        <tr>
                            <td class="text-center align-middle">${t.tanque?.no_tanque ?? ''}</td>
                            <td class="text-center align-middle">${t.tanque?.capacidad ?? ''}</td>
                            <td class="text-center align-middle">${t.tanque?.producto ?? ''}</td>
                            <td class="text-center align-middle">${t.resultado1 ?? ''}</td>
                            <td class="text-center align-middle">${t.resultado2 ?? ''}</td>
                        </tr>
                    `).join('')}

                    </tbody>

                </table>
            `;

            return;
        }

        if (equipo === 'Dispensario') {

            this.otrosDetalle = `
            <div class="row g-3">

            <div class="col-md-3">
                <label class="form-label mb-1">Unidad de verificación:</label>
                <div class="">${this.detalle.unidad_verificacion}</div>
            </div>

            <div class="col-md-3">
                <label class="form-label mb-1">No. de acreditación:</label>
                <div class="">${this.detalle.numero_acreditacion}</div>
            </div>

            <div class="col-md-3">
                <label class="form-label mb-1">Tipo calibración:</label>
                <div class="">${this.detalle.categoria_detalle}</div>
            </div>

            </div>
            `;

            this.tablaDetalle = `
                <table class="table table-striped table-bordered mb-0 text-nowrap align-middle">

                    <thead>
                        <tr>
                            <th class="text-center align-middle">No.</th>
                            <th class="text-center align-middle">Marca</th>
                            <th class="text-center align-middle">Modelo</th>
                            <th class="text-center align-middle">Serie</th>
                            <th class="text-center align-middle">Error máximo</th>
                            <th class="text-center align-middle">Repetibilidad</th>
                            <th class="text-center align-middle">Holograma</th>
                            <th class="text-center align-middle">Distintivo</th>
                        </tr>
                    </thead>

                    <tbody>

                    ${this.detalle.dispensarios.map(d => `
                        <tr>
                            <td class="text-center align-middle">${d.dispensario?.no_dispensario ?? ''}</td>
                            <td class="text-center align-middle">${d.dispensario?.marca ?? ''}</td>
                            <td class="text-center align-middle">${d.dispensario?.modelo ?? ''}</td>
                            <td class="text-center align-middle">${d.dispensario?.serie ?? ''}</td>
                            <td class="text-center align-middle">${d.resultado1 ?? ''}</td>
                            <td class="text-center align-middle">${d.resultado2 ?? ''}</td>
                            <td class="text-center align-middle">${d.resultado3 ?? ''}</td>
                            <td class="text-center align-middle">${d.resultado4 ?? ''}</td>
                        </tr>
                    `).join('')}

                    </tbody>

                </table>
            `;
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
                    '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos/datatable'
                    + '?year=' + this.filtro.year
                    + '&mes=' + this.filtro.mes;

                table1
                    .ajax
                    .url(url)
                    .load();

                bootstrap.Modal
                .getInstance(document.getElementById('ModalBuscar'))
                .hide();

                this.pdfUrl = '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos/pdf?year=' + this.filtro.year + '&mes=' + this.filtro.mes;
            

        },


    }));
});