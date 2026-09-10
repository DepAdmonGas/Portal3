document.addEventListener('alpine:init', () => {

    Alpine.data(
        'cuestionario',
        idTema => ({

            idTema: idTema,

            nuevaPregunta: '',

            nuevaRespuesta: {
                id_pregunta: '',
                titulo: ''
            },


            abrirModalPregunta() {

                this.nuevaPregunta = '';

                bootstrap.Modal
                    .getOrCreateInstance(
                        document.getElementById(
                            'modalPregunta'
                        )
                    )
                    .show();
            },


            abrirModalRespuesta() {

                this.nuevaRespuesta = {
                    id_pregunta: '',
                    titulo: ''
                };

                bootstrap.Modal
                    .getOrCreateInstance(
                        document.getElementById(
                            'modalRespuesta'
                        )
                    )
                    .show();
            },


            async guardarNuevaPregunta() {

                const titulo =
                    this.nuevaPregunta.trim();


                if (!titulo) {

                    this.notify(
                        'error',
                        'Ingresa la pregunta'
                    );

                    return;
                }


                try {

                    const { data } =
                        await axios.post(
                            '/cursos/preguntas/create',
                            {
                                id_tema:
                                    this.idTema,

                                titulo:
                                    titulo
                            }
                        );


                    if (!data.success) {

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


                    window.location.reload();

                } catch (e) {

                    console.error(e);

                    this.notify(
                        'error',
                        'No fue posible agregar la pregunta'
                    );
                }
            },


            editarPregunta(event) {

                const element =
                    event.currentTarget;


                element.dataset.original =
                    element.textContent.trim();


                element.contentEditable =
                    'true';


                element.focus();
            },


            async guardarPregunta(event) {

                const element =
                    event.currentTarget;


                if (
                    element.contentEditable
                    !== 'true'
                ) {

                    return;
                }


                element.contentEditable =
                    'false';


                const titulo =
                    element.textContent.trim();


                const original =
                    element.dataset.original
                    || '';


                if (
                    titulo === original
                ) {

                    return;
                }


                try {

                    const { data } =
                        await axios.post(
                            '/cursos/preguntas/update',
                            {
                                id:
                                    element.dataset.id,

                                titulo:
                                    titulo
                            }
                        );


                    if (!data.success) {

                        element.textContent =
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

                    element.textContent =
                        original;


                    this.notify(
                        'error',
                        'No fue posible actualizar la pregunta'
                    );
                }
            },


            async guardarRespuesta() {

                if (
                    !this.nuevaRespuesta.id_pregunta
                ) {

                    this.notify(
                        'error',
                        'Selecciona una pregunta'
                    );

                    return;
                }


                const titulo =
                    this.nuevaRespuesta
                        .titulo
                        .trim();


                if (!titulo) {

                    this.notify(
                        'error',
                        'Ingresa la respuesta'
                    );

                    return;
                }


                try {

                    const { data } =
                        await axios.post(
                            '/cursos/respuestas/create',
                            {
                                id_pregunta:
                                    this.nuevaRespuesta
                                        .id_pregunta,

                                titulo:
                                    titulo
                            }
                        );


                    if (!data.success) {

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


                    window.location.reload();

                } catch (e) {

                    console.error(e);

                    this.notify(
                        'error',
                        'No fue posible agregar la respuesta'
                    );
                }
            },


            async marcarRespuesta(
                idRespuesta,
                idPregunta
            ) {

                try {

                    const { data } =
                        await axios.post(
                            '/cursos/respuestas/correcta',
                            {
                                id_respuesta:
                                    idRespuesta,

                                id_pregunta:
                                    idPregunta
                            }
                        );


                    if (!data.success) {

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

                    console.error(e);

                    this.notify(
                        'error',
                        'No fue posible asignar la respuesta'
                    );
                }
            }

        })
    );

});