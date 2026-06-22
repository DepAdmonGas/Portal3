document.addEventListener('alpine:init', () => {
    Alpine.data('implementacionSA', () => ({

        mode: 'create',
        editId: null,
        modalNuevo: null,
        preguntas: [],
        fecha: '',

        errors: {
            fecha: false
        },

        detalleFecha: '',
        detallePreguntas: [],
        modalDetalle: null,

        init(){

            window.implementacionsa = this;
            this.modalNuevo = new bootstrap.Modal(document.getElementById('modalImplementacion'));
            this.modalDetalle = new bootstrap.Modal(document.getElementById('modalDetalleImplementacion'));
            this.cargarPreguntas();

        },

    cargarPreguntas() {

    this.preguntas = [

        {
            titulo: 'POLÍTICA',
            preguntas: [
                {
                    id: 1,
                    texto: 'La empresa cuenta con una política documentada y autorizada por el Representante legal',
                    respuesta: null
                },
                {
                    id: 2,
                    texto: 'La política fue comunicada a todo el personal interno, externo, clientes, prestadores de servicio etc',
                    respuesta: null
                }
            ]
        },

        {
            titulo: 'ANÁLISIS DE RIESGO Y EVALUACIÓN DE IMPACTOS AMBIENTALES',
            preguntas: [
                {
                    id: 3,
                    texto: 'Se tienen identificados los riesgos y peligros de la estación de servicio',
                    respuesta: null
                },
                {
                    id: 4,
                    texto: 'Se tienen identificados los aspectos e impactos ambientales',
                    respuesta: null
                }
            ]
        },

        {
            titulo: 'REQUISITOS LEGALES',
            preguntas: [
                {
                    id: 5,
                    texto: 'Se cuenta con el listado de Requisitos legales aplicables a la empresa',
                    respuesta: null
                },
                {
                    id: 6,
                    texto: 'Se cuenta y se tiene acceso a los requisitos legales de la empresa',
                    respuesta: null
                }
            ]
        },

        {
            titulo: 'OBJETIVOS, METAS E INDICADORES',
            preguntas: [
                {
                    id: 7,
                    texto: 'Se cuenta con objetivos, metas e indicadores claramente identificados',
                    respuesta: null
                },
                {
                    id: 8,
                    texto: 'Se da seguimiento para la obtención de objetivos y metas',
                    respuesta: null
                }
            ]
        },

        {
            titulo: 'FUNCIONES, RESPONSABILIDADES Y AUTORIDAD',
            preguntas: [
                {
                    id: 9,
                    texto: 'Se conoce y tiene identificada la estructura orgánica de la empresa',
                    respuesta: null
                },
                {
                    id: 10,
                    texto: 'Cada puesto conoce sus funciones y responsabilidades con respecto a la implementación del Sistema de Administración',
                    respuesta: null
                }
            ]
        },

        {
            titulo: 'COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO',
            preguntas: [
                {
                    id: 11,
                    texto: 'Se implementó de manera satisfactoria el programa de capacitación',
                    respuesta: null
                },
                {
                    id: 12,
                    texto: 'Se capacito a todo el personal sobre aspectos básicos de la operación',
                    respuesta: null
                }
            ]
        },

        {
            titulo: 'COMUNICACIÓN, PARTICIPACIÓN Y CONSULTA',
            preguntas: [
                {
                    id: 13,
                    texto: 'Se implementó algún procedimiento interno para la comunicación.',
                    respuesta: null
                },
                {
                    id: 14,
                    texto: 'Se implementó y dio seguimiento a la comunicación externa',
                    respuesta: null
                }
            ]
        },

        {
            titulo: 'CONTROL DE DOCUMENTOS Y REGISTROS',
            preguntas: [
                {
                    id: 15,
                    texto: 'Se cuenta con un control para la identificación de documentos y registros del SA',
                    respuesta: null
                },
                {
                    id: 16,
                    texto: 'Se cuenta con un control para resguardar los documentos y registros del SA',
                    respuesta: null
                }
            ]
        },

        {
            titulo: 'MEJORES PRÁCTICAS Y ESTÁNDARES',
            preguntas: [
                {
                    id: 17,
                    texto: 'Identifica el listado de las mejores prácticas para el diseño y construcción',
                    respuesta: null
                },
                {
                    id: 18,
                    texto: 'Identifica el listado de códigos y estándares para la etapa de operación y mantenimiento',
                    respuesta: null
                }
            ]
        },

        {
            titulo: 'CONTROL DE ACTIVIDADES Y PROCESOS',
            preguntas: [
                {
                    id: 19,
                    texto: 'Cuenta con procedimientos de seguridad, operación y mantenimiento',
                    respuesta: null
                },
                {
                    id: 20,
                    texto: 'Las actividades de mantenimiento preventivo y correctivo se registran en bitácora de acuerdo al programa anual de mantenimiento',
                    respuesta: null
                }
            ]
        },

        {
            titulo: 'INTEGRIDAD MECÁNICA Y ASEGURAMIENTO DE LA CALIDAD',
            preguntas: [
                {
                    id: 21,
                    texto: 'Se cuenta con el listado de equipos críticos',
                    respuesta: null
                },
                {
                    id: 22,
                    texto: 'Se conoce la razón del porque se le llama equipo critico',
                    respuesta: null
                }
            ]
        },
        {
            titulo: 'SEGURIDAD DE CONTRATISTAS',
            preguntas: [
                {
                    id: 23,
                    texto: 'Todos los trabajos catalogados como actividad altamente riesgosa fue autorizada previamente por el representante legal',
                    respuesta: null
                },
                {
                    id: 24,
                    texto: 'A los trabajos de mantenimiento realizados por externos se realizan conforme a procedimiento y registros establecidos en el SA',
                    respuesta: null
                }
            ]
        },
        {
            titulo: 'PREPARACIÓN Y RESPUESTA A EMERGENCIAS',
            preguntas: [
                {
                    id: 25,
                    texto: 'Se conocen los procedimientos para atender emergencias',
                    respuesta: null
                },
                {
                    id: 26,
                    texto: 'Los simulacros se realizaron y se evaluaron conforme al programa',
                    respuesta: null
                }
            ]
        },
        {
            titulo: 'MONITOREO, VERIFICACIÓN Y EVALUACIÓN',
            preguntas: [
                {
                    id: 27,
                    texto: 'Se cuenta con datos cualitativos y cuantitativos para identificar el cumplimiento de metas',
                    respuesta: null
                },
                {
                    id: 28,
                    texto: 'Se propusieron actividades para la mejora continua',
                    respuesta: null
                }
            ]
        },
        {
            titulo: 'AUDITORÍAS',
            preguntas: [
                {
                    id: 29,
                    texto: 'Se realizan las auditorías internas conforme al programa',
                    respuesta: null
                },
                {
                    id: 30,
                    texto: 'El personal interno atendió de manera satisfactoria la auditoria interna',
                    respuesta: null
                }
            ]
        },
        {
            titulo: 'INVESTIGACIÓN DE INCIDENTES Y ACCIDENTES',
            preguntas: [
                {
                    id: 31,
                    texto: 'Se conoce el cómo actuar para cada tipo de evento',
                    respuesta: null
                },
                {
                    id: 32,
                    texto: 'Se realizó el registro de todos los accidentes ocurridos en el año inmediato anterior',
                    respuesta: null
                }
            ]
        },
        {
            titulo: 'REVISIÓN DE RESULTADOS',
            preguntas: [
                {
                    id: 33,
                    texto: 'Se conoce a detalle el ciclo de mejora continua',
                    respuesta: null
                },
                {
                    id: 34,
                    texto: 'Se conoce y realizo el informe de revisión de resultados',
                    respuesta: null
                }
            ]
        },
        {
            titulo: 'INFORMES DE DESEMPEÑO',
            preguntas: [
                {
                    id: 35,
                    texto: 'Se comunicó a todo el personal los resultados de la evaluación de desempeño',
                    respuesta: null
                },
                {
                    id: 36,
                    texto: 'Se entrega a la Agencia el informe de evaluación de desempeño del SA',
                    respuesta: null
                }
            ]
        }
    ];

    },

    openModalNuevo() {

        this.mode = 'create';
        this.editId = null;
        this.cargarPreguntas();
        this.modalNuevo.show();

    },

    validate() {

            let valid = true;

            Object.keys(this.errors)
                .forEach(key => this.errors[key] = false);

            if (!this.fecha) {
                this.errors.fecha = true;
                valid = false;
            }

            const preguntasSinResponder = this.preguntas.some(grupo =>
                grupo.preguntas.some(pregunta =>
                    pregunta.respuesta === null ||
                    pregunta.respuesta === ''
                )
            );

            if (preguntasSinResponder) {
                valid = false;
            }

            return valid;
    },

    async guardar() {

            if (!this.validate()) {

                this.notify(
                    'error',
                    'Debes responder todas las preguntas'
                );

                return;

            }

            const payload = {
                fecha: this.fecha,
                preguntas: this.preguntas

            };

            let url = '/sasisopa/monitoreo-verificacion-evaluacion/implementacion-sa/create';

            if (this.mode === 'edit') {

                payload.id = this.editId;

                url = '/sasisopa/monitoreo-verificacion-evaluacion/implementacion-sa/update';

            }

            try {

                const res = await this.createAction({

                    url,
                    data: payload,
                    table: '#table-implementacionsa'

                });

                if (!res.success) {
                    return;
                }

                this.modalNuevo.hide();

            } catch (error) {

                this.notify(
                    'error',
                    'Error al guardar'
                );

            }

    },

    mapearRespuestas(detalle) {

    let index = 0;

    this.preguntas.forEach(grupo => {

        grupo.preguntas.forEach(pregunta => {

            pregunta.respuesta =
                String(detalle[index].resultado);

            index++;

        });

    });

    },

    async abrirEditar(id) {

        try {

            const response = await fetch(
                `/sasisopa/monitoreo-verificacion-evaluacion/implementacion-sa/get/${id}`
            );

            const json = await response.json();

            if (!json.success) {

                this.notify(
                    'error',
                    json.message
                );

                return;
            }

            this.mode = 'edit';
            this.editId = id;

            this.cargarPreguntas();

            this.fecha = json.data.fecha;

            this.mapearRespuestas(
                json.data.detalle
            );

            this.modalNuevo.show();

        } catch (error) {

            this.notify(
                'error',
                'Error al cargar información'
            );

        }

    },

    async abrirDetalle(id) {

    try {

        const response = await fetch(
            `/sasisopa/monitoreo-verificacion-evaluacion/implementacion-sa/get/${id}`
        );

        const res = await response.json();

        if (!res.success) {

            this.notify(
                'error',
                'No fue posible obtener la información'
            );

            return;
        }

        this.detalleFecha = res.data.fecha_larga;

        this.detallePreguntas = res.data.detalle;

        this.modalDetalle.show();

    } catch (error) {

        this.notify(
            'error',
            'Error al consultar información'
        );

    }
}

    }));
});