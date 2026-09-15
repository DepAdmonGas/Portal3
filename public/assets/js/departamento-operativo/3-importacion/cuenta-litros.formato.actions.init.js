document.addEventListener('alpine:init', () => {

    Alpine.data('cuentaLitrosFormatoComponent', () => ({

        id: 0,
        estatus: 0,
        editable: false,
        puedeCrear: false,
        puedeFinalizar: false,
        puedeEditarFecha: false,
        puedeEliminarDet: false,
        fechaLarga: '',
        filas: [],
        guardando: false,
        form: {},
        fechaForm: { fecha: '' },

        init() {
            const c = document.getElementById('container');
            if (!c) return;

            this.id = parseInt(c.dataset.id) || 0;
            this.estatus = parseInt(c.dataset.estatus || '0');
            this.editable = c.dataset.editable === 'true';
            this.puedeCrear = c.dataset.puedeCrear === 'true';
            this.puedeFinalizar = c.dataset.puedeFinalizar === 'true';
            this.puedeEditarFecha = c.dataset.puedeEditarFecha === 'true';
            this.puedeEliminarDet = c.dataset.puedeEliminarDet === 'true';
            this.fechaLarga = c.dataset.fechaLarga || '';

            try {
                const parseado = JSON.parse(c.dataset.filas || '[]');
                this.filas = Array.isArray(parseado) ? parseado : [];
            } catch (e) {
                this.filas = [];
            }

            this.fechaForm = { fecha: c.dataset.fecha || '' };
            this.resetearForm();

            this.$nextTick(() => {
                this.bindModalSelect2({
                    modalRef: 'modalAgregar',
                    selectRef: 'selTransporteAgregar',
                    wrapperRef: 'wrapTransporteAgregar',
                    model: 'form.transporte',
                    options: {
                        tags: true,
                        placeholder: 'Selecciona o escribe...',
                        allowClear: true,
                        createTag: (params) => ({
                            id: String(params.term || '').toUpperCase(),
                            text: String(params.term || '').toUpperCase()
                        })
                    },
                    namespace: 'transporteAgregar'
                });
                this.bindModalSelect2({
                    modalRef: 'modalAgregar',
                    selectRef: 'selUnidadAgregar',
                    wrapperRef: 'wrapUnidadAgregar',
                    model: 'form.unidad',
                    options: {
                        tags: true,
                        placeholder: 'Selecciona o escribe...',
                        allowClear: true,
                        createTag: (params) => ({
                            id: String(params.term || '').toUpperCase(),
                            text: String(params.term || '').toUpperCase()
                        })
                    },
                    namespace: 'unidadAgregar'
                });
                this.bindModalSelect2({
                    modalRef: 'modalEditar',
                    selectRef: 'selTransporteEditar',
                    wrapperRef: 'wrapTransporteEditar',
                    model: 'form.transporte',
                    options: {
                        tags: true,
                        placeholder: 'Selecciona o escribe...',
                        allowClear: true,
                        createTag: (params) => ({
                            id: String(params.term || '').toUpperCase(),
                            text: String(params.term || '').toUpperCase()
                        })
                    },
                    namespace: 'transporteEditar'
                });
                this.bindModalSelect2({
                    modalRef: 'modalEditar',
                    selectRef: 'selUnidadEditar',
                    wrapperRef: 'wrapUnidadEditar',
                    model: 'form.unidad',
                    options: {
                        tags: true,
                        placeholder: 'Selecciona o escribe...',
                        allowClear: true,
                        createTag: (params) => ({
                            id: String(params.term || '').toUpperCase(),
                            text: String(params.term || '').toUpperCase()
                        })
                    },
                    namespace: 'unidadEditar'
                });
            });
        },

        resetearForm() {
            this.form = {
                id_detalle: null,
                hora: '',
                embarque: '',
                tanque: '',
                tad: '',
                transporte: '',
                producto: '',
                unidad: '',
                litros: '',
                descarga_neto: '',
                descarga_bruto: '',
                litros_c: '',
                venta_momento: '',
                folio_merma: '',
                comentario: '',
                archivo_actual: false,
                archivo_url: ''
            };
        },

        abrirAgregar() {
            if (!this.puedeCrear) return;
            this.resetearForm();
            new bootstrap.Modal(document.getElementById('modalAgregar')).show();
        },

        asegurarOpcion(select, valor) {
            if (!select || valor === null || valor === undefined || valor === '') return;
            const busca = String(valor);
            const existe = Array.from(select.options).some((o) => o.value === busca);
            if (!existe) {
                const opt = document.createElement('option');
                opt.value = busca;
                opt.text = busca;
                select.add(opt);
            }
        },

        abrirEditar(idDetalle) {
            if (!this.editable) return;
            const fila = this.filas.find((f) => parseInt(f.id_detalle) === parseInt(idDetalle));
            if (!fila) return;

            this.asegurarOpcion(this.$refs.selTransporteEditar, fila.transporte);
            this.asegurarOpcion(this.$refs.selUnidadEditar, fila.unidad);

            this.resetearForm();
            this.form = {
                id_detalle: fila.id_detalle,
                hora: fila.hora,
                embarque: fila.embarque,
                tanque: fila.tanque,
                tad: fila.tad,
                transporte: fila.transporte,
                producto: fila.producto,
                unidad: fila.unidad,
                litros: fila.litros,
                descarga_neto: fila.descarga_neto,
                descarga_bruto: fila.descarga_bruto,
                litros_c: fila.litros_c,
                venta_momento: fila.venta_momento,
                folio_merma: fila.folio_merma,
                comentario: fila.comentario,
                archivo_actual: !!fila.archivo_existe,
                archivo_url: fila.archivo_url || ''
            };

            new bootstrap.Modal(document.getElementById('modalEditar')).show();
        },

        async confirmarEliminarDescarga(idDetalle) {
            if (!this.puedeEliminarDet) return;
            const fila = this.filas.find((f) => parseInt(f.id_detalle) === parseInt(idDetalle));
            const nombre = this.fechaLarga
                ? (this.fechaLarga + ', ' + (fila ? fila.hora_display : ''))
                : 'Descarga';
            const res = await this.deleteAction({
                url: '/departamento-operativo/importacion/cuenta-litros/eliminar-detalle',
                data: { id_detalle: idDetalle },
                name: nombre
            });
            if (res && res.success) {
                window.location.reload();
            }
        },

        abrirFecha() {
            if (!this.puedeEditarFecha) return;
            this.fechaForm.fecha = document.getElementById('container').dataset.fecha || '';
            new bootstrap.Modal(document.getElementById('modalFecha')).show();
        },

        guardarFecha() {
            if (!this.fechaForm.fecha) {
                this.notify('error', 'La fecha es obligatoria.');
                return;
            }
            this.guardando = true;
            return this.createAction({
                url: '/departamento-operativo/importacion/cuenta-litros/editar-fecha',
                data: { id: this.id, fecha: this.fechaForm.fecha },
                notify: true,
                onSuccess: () => window.location.reload()
            }).finally(() => { this.guardando = false; });
        },

        async guardarDescarga() {
            this.guardando = true;
            try {
                const esNuevo = !this.form.id_detalle;
                const archivoRef = esNuevo ? this.$refs.archivoAgregar : this.$refs.archivoEditar;

                const cuerpo = {
                    hora: this.form.hora,
                    embarque: this.form.embarque,
                    tanque: this.form.tanque,
                    tad: this.form.tad,
                    transporte: this.form.transporte,
                    producto: this.form.producto,
                    unidad: this.form.unidad,
                    litros: this.form.litros,
                    descarga_neto: this.form.descarga_neto,
                    descarga_bruto: this.form.descarga_bruto,
                    litros_c: this.form.litros_c,
                    venta_momento: this.form.venta_momento,
                    folio_merma: this.form.folio_merma,
                    comentario: this.form.comentario
                };

                const formData = new FormData();
                if (esNuevo) {
                    formData.append('id_cuenta_litros', String(this.id));
                } else {
                    formData.append('id_detalle', String(this.form.id_detalle));
                }
                Object.keys(cuerpo).forEach((clave) => {
                    formData.append(clave, String(cuerpo[clave] ?? ''));
                });
                if (archivoRef && archivoRef.files && archivoRef.files.length > 0) {
                    formData.append('archivo', archivoRef.files[0]);
                }

                await this.createAction({
                    url: esNuevo
                        ? '/departamento-operativo/importacion/cuenta-litros/agregar-detalle'
                        : '/departamento-operativo/importacion/cuenta-litros/editar-detalle',
                    data: formData,
                    notify: true,
                    onSuccess: () => window.location.reload()
                });
            } finally {
                this.guardando = false;
            }
        },

async confirmarFinalizar() {
            if (!this.puedeFinalizar) return;
            
            Swal.fire({
                title: '¿Finalizar formato?',
                text: 'Una vez finalizado, el formato ya no podrá editarse.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, finalizar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745'
            }).then(async (result) => {
                if (!result.isConfirmed) return;

                // 1. Mostramos el loader justo antes de la acción
                window.loader.show();

                try {
                    await this.createAction({
                        url: '/departamento-operativo/importacion/cuenta-litros/finalizar',
                        data: { id: this.id },
                        notify: true,
                        onSuccess: () => {
                            // Nota: Si vas a redirigir inmediatamente, el loader seguirá visible 
                            // hasta que cargue la nueva página, lo cual es ideal visualmente.
                            window.location.href = '/departamento-operativo/importacion/cuenta-litros';
                        }
                    });
                } catch (error) {
                    // 2. Si ocurre un error, ocultamos el loader para que la interfaz no quede bloqueada
                    window.loader.hide();
                    console.error(error);
                }
            });
        }
        
    }));
});

document.addEventListener('DOMContentLoaded', function () {
    var visorModal = null;

    function abrirVisor(src) {
        if (!src) return;
        var img = document.getElementById('imgVisor');
        if (img) {
            img.src = src;
        }
        if (!visorModal) {
            var modalVisor = document.getElementById('modalVisorImagen');
            if (!modalVisor) return;
            visorModal = new bootstrap.Modal(modalVisor);
        }
        visorModal.show();
    }

    document.addEventListener('click', function (e) {
        var img = e.target.closest('[data-img-viewer="1"]');
        if (img) {
            abrirVisor(img.getAttribute('src'));
        }
    });
});