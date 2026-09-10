document.addEventListener('alpine:init', () => {

    Alpine.data('cursos', () => ({

        nuevoModulo: '',

        nuevoTema: {
            id_modulo: '',
            titulo: ''
        },


        init() {

            this.inicializarEdicionTabla();
        },


        abrirModalModulo() {

            this.nuevoModulo = '';

            bootstrap.Modal
                .getOrCreateInstance(
                    document.getElementById('modalModulo')
                )
                .show();
        },


        abrirModalTema() {

            this.nuevoTema = {
                id_modulo: '',
                titulo: ''
            };

            bootstrap.Modal
                .getOrCreateInstance(
                    document.getElementById('modalTema')
                )
                .show();
        },


        async guardarModulo() {

            const titulo =
                this.nuevoModulo.trim();

            if (!titulo) {

                this.notify(
                    'error',
                    'Ingresa el título del módulo'
                );

                return;
            }


            try {

                const res = await this.createAction({

                    url:
                        '/cursos/modulos/create',

                    data: {
                        titulo: titulo
                    },

                    table:
                        '#table-cursos'
                });


                if (res.success) {

                    this.nuevoModulo = '';

                    /*
                     * El nuevo módulo debe aparecer también
                     * en el select de Nuevo Tema.
                     *
                     * Como el listado PHP fue generado en servidor,
                     * recargamos únicamente en este caso.
                     */
                    window.location.reload();
                }

            } catch (e) {

                console.error(e);

                this.notify(
                    'error',
                    'No fue posible agregar el módulo'
                );
            }
        },


        async guardarTema() {

            if (!this.nuevoTema.id_modulo) {

                this.notify(
                    'error',
                    'Selecciona un módulo'
                );

                return;
            }


            if (!this.nuevoTema.titulo.trim()) {

                this.notify(
                    'error',
                    'Ingresa el nombre del tema'
                );

                return;
            }


            try {

                const res = await this.createAction({

                    url:
                        '/cursos/temas/create',

                    data: {
                        id_modulo:
                            this.nuevoTema.id_modulo,

                        titulo:
                            this.nuevoTema.titulo.trim()
                    },

                    table:
                        '#table-cursos'
                });


                if (res.success) {

                    bootstrap.Modal
                        .getInstance(
                            document.getElementById('modalTema')
                        )
                        ?.hide();


                    this.nuevoTema = {
                        id_modulo: '',
                        titulo: ''
                    };
                }

            } catch (e) {

                console.error(e);

                this.notify(
                    'error',
                    'No fue posible agregar el tema'
                );
            }
        },


        inicializarEdicionTabla() {

            const table =
                document.getElementById(
                    'table-cursos'
                );


            table.addEventListener(
                'dblclick',
                event => {

                    const cell =
                        event.target.closest(
                            '.editable-curso'
                        );


                    if (!cell) {
                        return;
                    }


                    cell.dataset.original =
                        cell.textContent.trim();


                    cell.contentEditable =
                        'true';


                    cell.focus();


                    const range =
                        document.createRange();

                    range.selectNodeContents(
                        cell
                    );

                    range.collapse(
                        false
                    );


                    const selection =
                        window.getSelection();

                    selection.removeAllRanges();

                    selection.addRange(
                        range
                    );
                }
            );


            table.addEventListener(
                'keydown',
                event => {

                    const cell =
                        event.target.closest(
                            '.editable-curso'
                        );


                    if (!cell) {
                        return;
                    }


                    if (
                        event.key === 'Enter'
                    ) {

                        event.preventDefault();

                        cell.blur();
                    }


                    if (
                        event.key === 'Escape'
                    ) {

                        event.preventDefault();


                        cell.textContent =
                            cell.dataset.original
                            || '';


                        cell.contentEditable =
                            'false';
                    }
                }
            );


            table.addEventListener(
                'focusout',
                async event => {

                    const cell =
                        event.target.closest(
                            '.editable-curso'
                        );


                    if (!cell) {
                        return;
                    }


                    if (
                        cell.contentEditable
                        !== 'true'
                    ) {

                        return;
                    }


                    cell.contentEditable =
                        'false';


                    const nuevoValor =
                        cell.textContent.trim();


                    const original =
                        cell.dataset.original
                        || '';


                    if (
                        nuevoValor === original
                    ) {

                        return;
                    }


                    if (
                        cell.dataset.tipo
                        === 'modulo'
                    ) {

                        await this.actualizarModulo(
                            cell,
                            nuevoValor,
                            original
                        );

                        return;
                    }


                    await this.actualizarTema(
                        cell,
                        nuevoValor,
                        original
                    );
                },
                true
            );
        },


        async actualizarModulo(
            cell,
            valor,
            original
        ) {

            try {

                const { data } =
                    await axios.post(
                        '/cursos/modulos/update',
                        {
                            id:
                                cell.dataset.id,

                            titulo:
                                valor
                        }
                    );


                if (!data.success) {

                    cell.textContent =
                        original;


                    this.notify(
                        'error',
                        data.message
                    );

                    return;
                }


                this.notify(
                    'success',
                    data.message
                );


                $('#table-cursos')
                    .DataTable()
                    .ajax
                    .reload(
                        null,
                        false
                    );

            } catch (e) {

                cell.textContent =
                    original;


                this.notify(
                    'error',
                    'No fue posible actualizar el módulo'
                );
            }
        },


        async actualizarTema(
            cell,
            valor,
            original
        ) {

            try {

                const { data } =
                    await axios.post(
                        '/cursos/temas/update',
                        {
                            id:
                                cell.dataset.id,

                            campo:
                                cell.dataset.campo,

                            valor:
                                valor
                        }
                    );


                if (!data.success) {

                    cell.textContent =
                        original;


                    this.notify(
                        'error',
                        data.message
                    );

                    return;
                }


                this.notify(
                    'success',
                    data.message
                );

            } catch (e) {

                cell.textContent =
                    original;


                this.notify(
                    'error',
                    'No fue posible actualizar el tema'
                );
            }
        }

    }));

});