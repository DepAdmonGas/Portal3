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


                init() {

                    const tabla =
                        document.getElementById(
                            'table-cheques'
                        );


                    if (!tabla) {
                        return;
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