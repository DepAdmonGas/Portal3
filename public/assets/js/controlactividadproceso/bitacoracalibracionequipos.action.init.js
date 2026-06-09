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
            <div class="row">

            <div class="col-md-3 mt-2">
                <label class="form-label">Temperatura ambiente:</label>
                <div>${this.detalle.temperatura_ambiente}</div>
            </div>

            <div class="col-md-3 mt-2">
                <label class="form-label">Presión atmosférica:</label>
                <div>${this.detalle.presion_atmosferica}</div>
            </div>

            <div class="col-md-3 mt-2">
                <label class="form-label">Humedad:</label>
                <div>${this.detalle.humedad}</div>
            </div>

            <div class="col-md-3 mt-2">
                <label class="form-label">Liquido usado en la calibración:</label>
                <div>${this.detalle.liquido_calibracion}</div>
            </div>

            <div class="col-md-3 mt-2">
                <label class="form-label">Temperatura del líquido:</label>
                <div>${this.detalle.temperatura_liquido}</div>
            </div>

            <div class="col-md-3 mt-2">
                <label class="form-label">Laboratorio de calibración:</label>
                <div>${this.detalle.laboratorio_calibracion}</div>
            </div>

            <div class="col-md-3 mt-2">
                <label class="form-label">No. de acreditación:</label>
                <div>${this.detalle.numero_acreditacion}</div>
            </div>

            <div class="col-md-3 mt-2">
                <label class="form-label">Método de calibración:</label>
                <div>${this.detalle.metodo_calibracion}</div>
            </div>

            </div>
            `;

            this.tablaDetalle = `
                <table class="table table-bordered">

                    <thead>
                        <tr>
                            <th>Marca</th>
                            <th>Serie</th>
                            <th>Capacidad</th>
                            <th>Incertidumbre</th>
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
            <div class="row">

            <div class="col-md-3 mt-2">
                <label class="form-label">Unidad de verificación:</label>
                <div>${this.detalle.unidad_verificacion}</div>
            </div>

            <div class="col-md-3 mt-2">
                <label class="form-label">No. de acreditación:</label>
                <div>${this.detalle.numero_acreditacion}</div>
            </div>

            <div class="col-md-3 mt-2">
                <label class="form-label">Método usado para la calibración:</label>
                <div>${this.detalle.metodo_usado_calibracion}</div>
            </div>

            </div>
            `;

            this.tablaDetalle = `
                <table class="table table-bordered">

                    <thead>
                        <tr>
                            <th>No. Sonda</th>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th>Incertidumbre</th>
                        </tr>
                    </thead>

                    <tbody>

                    ${this.detalle.sondas.map(s => `
                        <tr>
                            <td>${s.sonda?.no_sonda ?? ''}</td>
                            <td>${s.sonda?.marca ?? ''}</td>
                            <td>${s.sonda?.modelo ?? ''}</td>
                            <td>${s.resultado1 ?? ''}</td>
                        </tr>
                    `).join('')}

                    </tbody>

                </table>
            `;

            return;
        }

        if (equipo === 'Tanques de almacenamiento') {

            this.otrosDetalle = `
            <div class="row">

            <div class="col-md-3 mt-2">
                <label class="form-label">Unidad de verificación:</label>
                <div>${this.detalle.unidad_verificacion}</div>
            </div>

            <div class="col-md-3 mt-2">
                <label class="form-label">No. de acreditación:</label>
                <div>${this.detalle.numero_acreditacion}</div>
            </div>

            <div class="col-md-3 mt-2">
                <label class="form-label">Método usado para la calibración:</label>
                <div>${this.detalle.metodo_usado_calibracion}</div>
            </div>

            </div>
            `;

            this.tablaDetalle = `
                <table class="table table-bordered">

                    <thead>
                        <tr>
                            <th>Tanque</th>
                            <th>Capacidad</th>
                            <th>Producto</th>
                            <th>Incertidumbre</th>
                            <th>Cumple</th>
                        </tr>
                    </thead>

                    <tbody>

                    ${this.detalle.tanques.map(t => `
                        <tr>
                            <td>${t.tanque?.no_tanque ?? ''}</td>
                            <td>${t.tanque?.capacidad ?? ''}</td>
                            <td>${t.tanque?.producto ?? ''}</td>
                            <td>${t.resultado1 ?? ''}</td>
                            <td>${t.resultado2 ?? ''}</td>
                        </tr>
                    `).join('')}

                    </tbody>

                </table>
            `;

            return;
        }

        if (equipo === 'Dispensario') {

            this.otrosDetalle = `
            <div class="row">

            <div class="col-md-3 mt-2">
                <label class="form-label">Unidad de verificación:</label>
                <div>${this.detalle.unidad_verificacion}</div>
            </div>

            <div class="col-md-3 mt-2">
                <label class="form-label">No. de acreditación:</label>
                <div>${this.detalle.numero_acreditacion}</div>
            </div>

            <div class="col-md-3 mt-2">
                <label class="form-label">Tipo calibración:</label>
                <div>${this.detalle.categoria_detalle}</div>
            </div>

            </div>
            `;

            this.tablaDetalle = `
                <table class="table table-bordered">

                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th>Serie</th>
                            <th>Error máximo</th>
                            <th>Repetibilidad</th>
                            <th>Holograma</th>
                            <th>Distintivo</th>
                        </tr>
                    </thead>

                    <tbody>

                    ${this.detalle.dispensarios.map(d => `
                        <tr>
                            <td>${d.dispensario?.no_dispensario ?? ''}</td>
                            <td>${d.dispensario?.marca ?? ''}</td>
                            <td>${d.dispensario?.modelo ?? ''}</td>
                            <td>${d.dispensario?.serie ?? ''}</td>
                            <td>${d.resultado1 ?? ''}</td>
                            <td>${d.resultado2 ?? ''}</td>
                            <td>${d.resultado3 ?? ''}</td>
                            <td>${d.resultado4 ?? ''}</td>
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