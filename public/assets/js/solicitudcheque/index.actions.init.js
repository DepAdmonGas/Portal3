document.addEventListener(
    'alpine:init',
    () => {

        Alpine.data(
            'solicitudCheques',
            () => ({

                saving:
                    false,

                editingElement:
                    null,

                originalValue:
                    null,

                batchStorageKey:
                    'portal3:solicitud-cheques:firma-batch',

                batchRecoveryPending:
                    false,


                init() {

                    const tabla =
                        document.getElementById(
                            'table-cheques'
                        );


                    if (!tabla) {
                        return;
                    }


                    document
                        .getElementById('btn-firmar-todas')
                        ?.addEventListener('click', () => this.firmarTodas());

                    document.addEventListener(
                        'solicitud-cheques:datatable-loaded',
                        () => this.reanudarLoteFirmas()
                    );

                    if (window.solicitudChequesDataReady) {
                        this.reanudarLoteFirmas();
                    }


                    /*
                     * Editar.
                     */
                    tabla.addEventListener(
                        'dblclick',
                        event => {

                            const elemento =
                                event.target.closest(
                                    '[data-editable="true"]'
                                );


                            if (!elemento) {
                                return;
                            }


                            this.iniciarEdicion(
                                elemento
                            );
                        }
                    );


                    /*
                     * Guardar al salir.
                     */
                    tabla.addEventListener(
                        'focusout',
                        event => {

                            const elemento =
                                event.target.closest(
                                    '[data-editable="true"]'
                                );


                            if (
                                !elemento
                                || elemento
                                    !== this.editingElement
                            ) {

                                return;
                            }


                            this.finalizarEdicion(
                                elemento
                            );
                        }
                    );


                    /*
                     * Enter / Escape.
                     */
                    tabla.addEventListener(
                        'keydown',
                        event => {

                            const elemento =
                                event.target.closest(
                                    '[data-editable="true"]'
                                );


                            if (
                                !elemento
                                || elemento
                                    !== this.editingElement
                            ) {

                                return;
                            }


                            if (
                                event.key === 'Enter'
                            ) {

                                event.preventDefault();

                                elemento.blur();

                                return;
                            }


                            if (
                                event.key === 'Escape'
                            ) {

                                event.preventDefault();

                                this.cancelarEdicion(
                                    elemento
                                );
                            }
                        }
                    );


                    /*
                     * Firma.
                     */
                    tabla.addEventListener(
                        'click',
                        event => {

                            const boton =
                                event.target.closest(
                                    '.btn-sign'
                                );


                            if (!boton) {
                                return;
                            }


                            event.preventDefault();


                            const id =
                                parseInt(
                                    boton.dataset.id,
                                    10
                                );


                            const opcion =
                                parseInt(
                                    boton.dataset.opcion,
                                    10
                                );


                            if (
                                id > 0
                                && opcion > 0
                            ) {

                                this.firmar(
                                    id,
                                    opcion
                                );
                            }
                        }
                    );
                },


                /*
                 * -----------------------------------------
                 * INICIAR EDICIÓN
                 * -----------------------------------------
                 */
                iniciarEdicion(
                    elemento
                ) {

                    if (
                        this.editingElement
                        && this.editingElement
                            !== elemento
                    ) {

                        this.editingElement.blur();
                    }


                    this.editingElement =
                        elemento;


                    this.originalValue =
                        elemento.dataset.value
                            ?? elemento.textContent.trim();


                    if (
                        elemento.dataset.campo
                        === 'monto'
                    ) {

                        elemento.textContent =
                            elemento.dataset.value
                                ?? '';
                    }


                    elemento.contentEditable =
                        'true';


                    elemento.classList.add(
                        'border',
                        'border-primary',
                        'bg-light'
                    );


                    elemento.focus();


                    this.seleccionarContenido(
                        elemento
                    );
                },


                /*
                 * -----------------------------------------
                 * FINALIZAR EDICIÓN
                 * -----------------------------------------
                 */
                async finalizarEdicion(
                    elemento
                ) {

                    if (
                        elemento
                        !== this.editingElement
                    ) {

                        return;
                    }


                    const nuevoValor =
                        elemento.textContent.trim();


                    const original =
                        String(
                            this.originalValue ?? ''
                        ).trim();


                    this.desactivarEdicion(
                        elemento
                    );


                    if (
                        nuevoValor === original
                    ) {

                        this.restaurarVisual(
                            elemento,
                            original
                        );


                        this.limpiarEstadoEdicion();

                        return;
                    }


                    await this.guardarCampo(
                        elemento,
                        nuevoValor,
                        original
                    );


                    this.limpiarEstadoEdicion();
                },


                /*
                 * -----------------------------------------
                 * GUARDAR CAMPO
                 * -----------------------------------------
                 */
                async guardarCampo(
                    elemento,
                    valor,
                    original
                ) {

                    this.saving =
                        true;


                    try {

                        const id =
                            parseInt(
                                elemento.dataset.id,
                                10
                            );


                        const campo =
                            elemento.dataset.campo;


                        const { data } =
                            await axios.post(
                                '/solicitud-cheques/update-field',
                                {
                                    id:
                                        id,

                                    campo:
                                        campo,

                                    valor:
                                        valor
                                }
                            );


                        if (
                            !data.success
                        ) {

                            this.restaurarVisual(
                                elemento,
                                original
                            );


                            this.notify(
                                data.type
                                    || 'error',

                                data.message
                            );

                            return;
                        }


                        const nuevoValor =
                            data.data.valor;


                        elemento.dataset.value =
                            nuevoValor ?? '';


                        this.restaurarVisual(
                            elemento,
                            nuevoValor
                        );


                        this.notify(
                            'success',
                            data.message
                        );

                    } catch (error) {

                        console.error(
                            error
                        );


                        this.restaurarVisual(
                            elemento,
                            original
                        );


                        this.notify(
                            'error',
                            'No fue posible actualizar la solicitud'
                        );

                    } finally {

                        this.saving =
                            false;
                    }
                },


                /*
                 * -----------------------------------------
                 * FIRMA
                 * -----------------------------------------
                 */
                async firmar(
                    id,
                    opcion
                ) {

                    if (this.saving) {
                        return;
                    }

                    const result =
                        await Swal.fire({

                            title:
                                'Firmar solicitud',

                            text:
                                '¿Estás seguro de que deseas firmar esta solicitud?',

                            icon:
                                'question',

                            showCancelButton:
                                true,

                            confirmButtonText:
                                'Sí, firmar',

                            cancelButtonText:
                                'Cancelar',

                            reverseButtons:
                                true
                        });


                    if (
                        !result.isConfirmed
                    ) {

                        return;
                    }


                    this.saving =
                        true;


                    try {

                        const { data } =
                            await axios.post(
                                '/solicitud-cheques/sign',
                                {
                                    id:
                                        id,

                                    opcion:
                                        opcion
                                }
                            );


                        this.notify(
                            data.type
                                || (
                                    data.success
                                        ? 'success'
                                        : 'error'
                                ),

                            data.message
                        );


                        if (
                            data.success
                        ) {

                            this.recargarTabla();
                        }

                    } catch (error) {

                        console.error(
                            error
                        );


                        this.notify(
                            'error',
                            'No fue posible firmar la solicitud'
                        );

                    } finally {

                        this.saving =
                            false;
                    }
                },


                /*
                 * -----------------------------------------
                 * FIRMA SECUENCIAL DE AUTORIZACIONES
                 * -----------------------------------------
                 */
                async firmarTodas() {

                    const solicitudes = [...new Map(
                        (window.solicitudesPendientesAutorizacion || [])
                            .map(solicitud => [solicitud.id, solicitud])
                    ).values()];

                    if (solicitudes.length < 2 || this.saving) {
                        return;
                    }

                    const confirmacion = await Swal.fire({
                        title: 'Firmar todas las solicitudes',
                        text: `Se procesarán ${solicitudes.length} solicitudes. Se enviará cada firma con una pausa aproximada de 10 segundos.`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, firmar todas',
                        cancelButtonText: 'Cancelar',
                        reverseButtons: true
                    });

                    if (!confirmacion.isConfirmed) {
                        return;
                    }

                    const lote = {
                        estado: 'en_proceso',
                        solicitudes,
                        enCurso: null,
                        siguienteEn: null,
                        completadas: 0,
                        fallidas: [],
                        total: solicitudes.length
                    };

                    if (!this.guardarLoteFirmas(lote)) {
                        return;
                    }
                    await this.procesarLoteFirmas();
                },


                leerLoteFirmas() {
                    try {
                        const contenido = localStorage.getItem(this.batchStorageKey);
                        return contenido ? JSON.parse(contenido) : null;
                    } catch (error) {
                        console.error('No fue posible leer el lote de firmas guardado.', error);
                        return null;
                    }
                },


                guardarLoteFirmas(lote) {
                    try {
                        localStorage.setItem(this.batchStorageKey, JSON.stringify(lote));
                        return true;
                    } catch (error) {
                        console.error('No fue posible guardar el avance del lote de firmas.', error);
                        this.notify('error', 'No se pudo guardar el avance para reanudarlo si recargas la página.');
                        return false;
                    }
                },


                async reanudarLoteFirmas() {

                    if (this.saving || this.batchRecoveryPending) {
                        return;
                    }

                    const lote = this.leerLoteFirmas();
                    if (!lote || lote.estado !== 'en_proceso' || !Array.isArray(lote.solicitudes)) {
                        return;
                    }

                    this.batchRecoveryPending = true;

                    const disponibles = new Map(
                        (window.solicitudesPendientesAutorizacion || [])
                            .map(solicitud => [Number(solicitud.id), Number(solicitud.opcion)])
                    );
                    if (lote.enCurso) {
                        const opcionActual = disponibles.get(Number(lote.enCurso.id));
                        const mismaFirmaPendiente = opcionActual === Number(lote.enCurso.opcion);

                        if (!mismaFirmaPendiente) {
                            lote.solicitudes = lote.solicitudes.filter(
                                solicitud => Number(solicitud.id) !== Number(lote.enCurso.id)
                            );
                            lote.completadas = Number(lote.completadas || 0) + 1;
                        } else {
                            lote.siguienteEn = Date.now() + 10000;
                        }

                        lote.enCurso = null;
                    }

                    const totalAntesDeReconciliar = lote.solicitudes.length;
                    lote.solicitudes = lote.solicitudes.filter(solicitud => {
                        const opcionActual = disponibles.get(Number(solicitud.id));
                        return opcionActual === Number(solicitud.opcion);
                    });
                    lote.completadas = Number(lote.completadas || 0)
                        + (totalAntesDeReconciliar - lote.solicitudes.length);
                    lote.fallidas = Array.isArray(lote.fallidas) ? lote.fallidas : [];

                    this.guardarLoteFirmas(lote);
                    this.batchRecoveryPending = false;
                    await this.procesarLoteFirmas(true);
                },


                async procesarLoteFirmas(reanudado = false) {

                    if (this.saving) {
                        return;
                    }

                    const button = document.getElementById('btn-firmar-todas');
                    const progress = document.getElementById('firmar-todas-progress');
                    const progressTitle = document.getElementById('firmar-todas-progress-title');
                    const progressBar = document.getElementById('firmar-todas-progress-bar');
                    const timer = document.getElementById('firmar-todas-timer');
                    const step = document.getElementById('firmar-todas-step');
                    const label = button?.querySelector('.firmar-todas-label');
                    const rowButtons = document.querySelectorAll('#table-cheques .btn-sign');
                    const originalLabel = label?.textContent || 'Firmar todas las solicitudes';
                    let lote = this.leerLoteFirmas();
                    if (!lote || !Array.isArray(lote.solicitudes)) {
                        return;
                    }
                    let loteFinal = null;

                    this.saving = true;
                    if (button) button.disabled = true;
                    progress?.classList.remove('d-none');
                    progress?.classList.add('d-flex');
                    rowButtons.forEach(rowButton => { rowButton.disabled = true; });

                    try {
                        while (lote.solicitudes.length > 0) {
                            lote = this.leerLoteFirmas();
                            if (!lote || lote.solicitudes.length === 0) {
                                break;
                            }

                            if (lote.siguienteEn) {
                                let segundos = Math.max(
                                    0,
                                    Math.ceil((Number(lote.siguienteEn) - Date.now()) / 1000)
                                );

                                if (progressTitle) progressTitle.textContent = 'Siguiente firma en';
                                if (step) step.textContent = `Solicitud ${Number(lote.completadas || 0) + 1} de ${lote.total}`;
                                if (timer) timer.classList.remove('d-none');
                                if (progressBar) progressBar.style.width = `${Math.round((Number(lote.completadas || 0) / lote.total) * 100)}%`;

                                while (segundos > 0) {
                                    if (timer) timer.textContent = `${segundos} s`;
                                    await new Promise(resolve => window.setTimeout(resolve, 1000));
                                    segundos -= 1;
                                }

                                lote.siguienteEn = null;
                                this.guardarLoteFirmas(lote);
                            }

                            const solicitud = lote.solicitudes[0];
                            const indice = Number(lote.completadas || 0) + 1;
                            if (progressTitle) progressTitle.textContent = 'Enviando firma';
                            if (timer) timer.classList.add('d-none');
                            if (step) step.textContent = `Solicitud ${indice} de ${lote.total}`;
                            if (progressBar) progressBar.style.width = `${Math.round((Number(lote.completadas || 0) / lote.total) * 100)}%`;
                            if (label) {
                                label.textContent = `Firmando ${indice} de ${lote.total}`;
                            }

                            lote.enCurso = solicitud;
                            this.guardarLoteFirmas(lote);

                            try {
                                const { data } = await axios.post(
                                    '/solicitud-cheques/sign',
                                    {
                                        id: solicitud.id,
                                        opcion: solicitud.opcion
                                    }
                                );

                                if (data?.success) {
                                    lote.completadas = Number(lote.completadas || 0) + 1;
                                } else {
                                    lote.fallidas.push(Number(solicitud.id));
                                }
                            } catch (error) {
                                console.error(error);
                                lote.fallidas.push(Number(solicitud.id));
                            }

                            lote.solicitudes.shift();
                            lote.enCurso = null;
                            lote.siguienteEn = lote.solicitudes.length > 0
                                ? Date.now() + 10000
                                : null;
                            this.guardarLoteFirmas(lote);
                        }
                    } finally {
                        loteFinal = this.leerLoteFirmas() || lote;
                        if (Array.isArray(loteFinal.solicitudes) && loteFinal.solicitudes.length === 0) {
                            localStorage.removeItem(this.batchStorageKey);
                        }
                        this.saving = false;
                        if (label) label.textContent = originalLabel;
                        progress?.classList.add('d-none');
                        progress?.classList.remove('d-flex');
                        if (timer) timer.textContent = '';
                        if (step) step.textContent = '';
                        if (progressBar) progressBar.style.width = '0%';
                        rowButtons.forEach(rowButton => { rowButton.disabled = false; });
                        this.recargarTabla();
                    }

                    lote = loteFinal || lote;
                    const errores = Array.isArray(lote.fallidas) ? lote.fallidas : [];
                    const firmadas = Number(lote.completadas || 0);
                    const total = Number(lote.total || 0);

                    if (errores.length > 0) {
                        this.notify(
                            'warning',
                            `Se procesaron ${firmadas} de ${total} solicitudes. ${errores.length} no se pudieron firmar; revisa la tabla.`
                        );
                    } else {
                        this.notify(
                            'success',
                            reanudado
                                ? `Se reanudó el lote y se firmaron ${firmadas} solicitudes.`
                                : `Se firmaron ${firmadas} solicitudes.`
                        );
                    }
                },


                /*
                 * -----------------------------------------
                 * CANCELAR EDICIÓN
                 * -----------------------------------------
                 */
                cancelarEdicion(
                    elemento
                ) {

                    const original =
                        this.originalValue;


                    this.desactivarEdicion(
                        elemento
                    );


                    this.restaurarVisual(
                        elemento,
                        original
                    );


                    this.limpiarEstadoEdicion();
                },


                /*
                 * -----------------------------------------
                 * VISUAL
                 * -----------------------------------------
                 */
                desactivarEdicion(
                    elemento
                ) {

                    elemento.contentEditable =
                        'false';


                    elemento.classList.remove(
                        'border',
                        'border-primary',
                        'bg-light'
                    );
                },


                restaurarVisual(
                    elemento,
                    valor
                ) {

                    const campo =
                        elemento.dataset.campo;


                    elemento.dataset.value =
                        valor ?? '';


                    if (
                        campo === 'monto'
                    ) {

                        const monto =
                            Number(
                                valor || 0
                            );


                        elemento.textContent =
                            monto.toLocaleString(
                                'es-MX',
                                {
                                    style:
                                        'currency',

                                    currency:
                                        'MXN'
                                }
                            );


                        return;
                    }


                    elemento.textContent =
                        valor ?? '';
                },


                seleccionarContenido(
                    elemento
                ) {

                    const selection =
                        window.getSelection();


                    const range =
                        document.createRange();


                    range.selectNodeContents(
                        elemento
                    );


                    selection.removeAllRanges();


                    selection.addRange(
                        range
                    );
                },


                limpiarEstadoEdicion() {

                    this.editingElement =
                        null;


                    this.originalValue =
                        null;
                },


                /*
                 * -----------------------------------------
                 * DATATABLE
                 * -----------------------------------------
                 */
                recargarTabla() {

                    if (
                        $.fn.DataTable.isDataTable(
                            '#table-cheques'
                        )
                    ) {

                        $('#table-cheques')
                            .DataTable()
                            .ajax
                            .reload(
                                null,
                                false
                            );
                    }
                }

            })
        );

    }
);
