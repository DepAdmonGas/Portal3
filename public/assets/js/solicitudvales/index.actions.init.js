document.addEventListener(
    'alpine:init',
    () => {

        Alpine.data(
            'solicitudVales',
            () => ({

                saving:
                    false,

                editingElement:
                    null,

                originalValue:
                    null,


                /**
                 * ---------------------------------------------
                 * INIT
                 * ---------------------------------------------
                 */
                init() {

                    const tabla =
                        document.getElementById(
                            'table-vales'
                        );


                    if (!tabla) {
                        return;
                    }


                    /*
                     * Edición por doble clic.
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
                     * Guardar al perder foco.
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
                     * Enter = guardar.
                     * Escape = cancelar.
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
                },


                /**
                 * ---------------------------------------------
                 * INICIAR EDICIÓN
                 * ---------------------------------------------
                 */
                iniciarEdicion(
                    elemento
                ) {

                    /*
                     * Si ya existe otra celda en edición,
                     * primero la finalizamos.
                     */
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


                    const campo =
                        elemento.dataset.campo;


                    /*
                     * Para monto mostramos el valor crudo
                     * durante la edición.
                     */
                    if (
                        campo === 'monto'
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


                /**
                 * ---------------------------------------------
                 * FINALIZAR / GUARDAR
                 * ---------------------------------------------
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


                    const valor =
                        elemento.textContent.trim();


                    const original =
                        String(
                            this.originalValue ?? ''
                        ).trim();


                    this.desactivarEdicion(
                        elemento
                    );


                    /*
                     * Si no hubo cambios no hacemos petición.
                     */
                    if (
                        valor === original
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
                        valor,
                        original
                    );


                    this.limpiarEstadoEdicion();
                },


                /**
                 * ---------------------------------------------
                 * AXIOS
                 * ---------------------------------------------
                 */
                async guardarCampo(
                    elemento,
                    valor,
                    original
                ) {

                    this.saving =
                        true;


                    try {

                        const folio =
                            parseInt(
                                elemento.dataset.folio,
                                10
                            );


                        const campo =
                            elemento.dataset.campo;


                        const { data } =
                            await axios.post(
                                '/solicitud-vales/update-field',
                                {
                                    folio:
                                        folio,

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
                            'No fue posible actualizar la información'
                        );

                    } finally {

                        this.saving =
                            false;
                    }
                },


                /**
                 * ---------------------------------------------
                 * CANCELAR
                 * ---------------------------------------------
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


                /**
                 * ---------------------------------------------
                 * DESACTIVAR CONTENTEDITABLE
                 * ---------------------------------------------
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


                /**
                 * ---------------------------------------------
                 * RESTAURAR REPRESENTACIÓN
                 * ---------------------------------------------
                 */
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


                /**
                 * ---------------------------------------------
                 * SELECCIONAR TEXTO
                 * ---------------------------------------------
                 */
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


                /**
                 * ---------------------------------------------
                 * LIMPIAR ESTADO
                 * ---------------------------------------------
                 */
                limpiarEstadoEdicion() {

                    this.editingElement =
                        null;


                    this.originalValue =
                        null;
                }

            })
        );

    }
);