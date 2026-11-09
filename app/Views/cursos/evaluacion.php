<div
    class=" mt-3 pb-5 cursos-evaluacion"
    x-data="evaluacion(<?= $calendario->id ?>)"
    x-init="init()"
>
    <style>
        .cursos-evaluacion .opcion-evaluacion {
            border: 1px solid #e3e8ef;
            border-radius: .75rem;
            transition: border-color .18s ease, background-color .18s ease, box-shadow .18s ease, transform .18s ease;
            cursor: pointer;
        }
        .cursos-evaluacion .opcion-evaluacion:hover {
            border-color: #91a4ff;
            background-color: #f8f9ff;
            transform: translateY(-1px);
        }
        .cursos-evaluacion .opcion-evaluacion.seleccionada {
            border-color: #637bff;
            background-color: #f0f3ff;
            box-shadow: 0 0 0 .15rem rgba(99, 123, 255, .12);
        }
        .cursos-evaluacion .opcion-evaluacion .form-check-input {
            width: 1.15rem;
            height: 1.15rem;
            margin: 0;
            flex: 0 0 auto;
            accent-color: #637bff;
        }
        .cursos-evaluacion .opcion-evaluacion .form-check-input:checked {
            background-color: #637bff;
            border-color: #637bff;
        }
        .cursos-evaluacion .resultado-evaluacion {
            min-height: calc(100vh - 14rem);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid #e8edf5;
            border-radius: .85rem;
            background: linear-gradient(135deg, #f8faff 0%, #fff 55%, #f4f7ff 100%);
        }
        .cursos-evaluacion .resultado-cabecera {
            padding: clamp(1.5rem, 4vw, 3.5rem);
            background: var(--bs-primary, #637bff);
        }
        .cursos-evaluacion .resultado-icono {
            width: clamp(4.5rem, 9vw, 7rem);
            height: clamp(4.5rem, 9vw, 7rem);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, .18);
            font-size: clamp(2.5rem, 5vw, 4rem);
            flex: 0 0 auto;
        }
        .cursos-evaluacion .resultado-cuerpo {
            flex: 1 1 auto;
            display: flex;
            align-items: center;
            padding: clamp(1.5rem, 4vw, 4rem);
        }
        .cursos-evaluacion .resultado-panel {
            height: 100%;
            padding: clamp(1.5rem, 3vw, 2.5rem);
            border: 1px solid #e8edf5;
            border-radius: .85rem;
            background: #fff;
            box-shadow: 0 .5rem 1.5rem rgba(38, 57, 100, .06);
        }
        .cursos-evaluacion .resultado-puntaje {
            font-size: clamp(4.5rem, 10vw, 8rem);
            line-height: 1;
            letter-spacing: -.06em;
        }
        @media (max-width: 767.98px) {
            .cursos-evaluacion .resultado-evaluacion { min-height: calc(100vh - 12rem); }
            .cursos-evaluacion .resultado-cuerpo { padding: 1rem; }
        }
    </style>

    <!-- LOADING -->
    <template x-if="loading">
        <div class="text-center py-5">
            <div class="spinner-border text-primary"></div>
            <p class="mt-3 mb-0">
                Cargando evaluación...
            </p>
        </div>
    </template>




    <!-- RESULTADO -->
    <template x-if="resultado">
        <section class="resultado-evaluacion shadow-sm" x-transition>
            <header class="resultado-cabecera text-white">
                <div class="d-flex flex-column flex-md-row align-items-md-center gap-4">
                    <div class="resultado-icono">
                        <i class="ti ti-school-off" aria-hidden="true"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="small text-white-50 text-uppercase fw-semibold mb-2">
                            Evaluación completada
                        </div>
                        <h1 class="text-white fw-bold mb-2" x-text="resultado.titulo"></h1>
                    </div>
                    <span class="badge rounded-pill fs-5 px-4 py-3 align-self-md-center"
                        :class="resultado.aprobado ? 'bg-success' : 'bg-danger'"
                        x-text="resultado.aprobado ? 'Aprobado' : 'No aprobado'">
                    </span>
                </div>
            </header>

            <div class="resultado-cuerpo">
                <div class="row g-4 g-xl-5 align-items-stretch w-100">
                    <div class="col-12 col-xl-4">
                        <div class="resultado-panel d-flex flex-column justify-content-center text-center">
                            <div class="text-uppercase small fw-semibold text-muted mb-3">
                                Calificación obtenida
                            </div>
                            <div class="resultado-puntaje fw-bold text-primary"
                                x-text="resultado.porcentaje + '%'">
                            </div>
                            <div class="text-muted mt-3">Puntaje final</div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-8">
                        <div class="resultado-panel d-flex flex-column justify-content-center">
                            <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                                <h3 class="fw-semibold mb-0">Resultado de la evaluación</h3>
                                <span class="text-muted fw-semibold" x-text="resultado.porcentaje + '%'" ></span>
                            </div>
                            <div class="progress mb-4" style="height: 1rem;">
                                <div class="progress-bar progress-bar-striped"
                                    :class="'bg-' + resultado.color"
                                    role="progressbar"
                                    :style="'width:' + resultado.porcentaje + '%'"
                                    :aria-valuenow="resultado.porcentaje"
                                    aria-valuemin="0"
                                    aria-valuemax="100">
                                </div>
                            </div>
                            <p class="lead text-body-secondary mb-0" x-text="resultado.mensaje"></p>
                        </div>
                    </div>
                </div>
            </div>

            <footer class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 border-top bg-white px-4 px-xl-5 py-4">
                <div class="text-muted">
                    Evaluación finalizada
                </div>
                <a href="/sasisopa/cursos" class="btn btn-primary btn-lg px-5">
                   <i class="ti ti-arrow-left"></i> Volver a cursos
                </a>
            </footer>
        </section>
    </template>

    <!-- EXAMEN -->

    <template x-if="!loading && !resultado">

        <div>

            <!-- CABECERA -->

<div class="card shadow-sm mb-4">
    <div class="card-header bg-primary">
        <h4 class="mb-0 text-white card-title">
            <i class="ti ti-clipboard-check"></i> Evaluación
        </h4>
    </div>
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md">
                <h4 class="text-muted fw-semibold">
                    Tema
                    <strong x-text="tema.numero"></strong>
                    -
                    <span x-text="tema.titulo"></span>
                </h4>
            </div>
            <div class="col-md-auto text-end">
                <div class="display-6">
                    <span x-text="contestadas"></span>
                    /
                    <span x-text="total"></span>
                </div>
                <small class="text-muted">
                    Respondidas
                </small>
            </div>
        </div>
        <div class="progress mt-4" style="height:12px;">
            <div
                class="progress-bar progress-bar-striped progress-bar-animated"
                :style="'width:'+porcentaje+'%'"
            ></div>
        </div>
    </div>
</div>


            <!-- PREGUNTAS -->

            <template x-for="pregunta in preguntas" :key="pregunta.id">

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-primary">
        <h5 class="mb-0 text-white card-title">
            <span class="badge bg-white text-primary me-2" x-text="pregunta.numero"></span>
            <span x-text="pregunta.titulo"></span>
        </h5>
    </div>


<div class="card-body">
    <template x-for="respuesta in pregunta.respuestas" :key="respuesta.id">
        <label
            class="card opcion-evaluacion mb-2 p-3 d-block"
            :class="selecciones[pregunta.id] === respuesta.id ? 'seleccionada' : ''"
        >
            <div class="d-flex align-items-center gap-3">
                <input
                    class="form-check-input"
                    type="radio"
                    :name="'pregunta_' + pregunta.id"
                    :value="respuesta.valor"
                    :checked="selecciones[pregunta.id] === respuesta.id"
                    @change="seleccionar(pregunta, respuesta)"
                >
                <span class="fw-medium text-dark flex-grow-1">
                    <span x-text="respuesta.titulo"></span>
                </span>
            </div>
        </label>
    </template>
</div>

</div>

            </template>


            <!-- FOOTER -->

            <div

                class="sticky-bottom bg-white border-top py-3"

            >

                <div class="container">

                    <button

                        class="btn btn-success btn-lg w-100"

                        @click="finalizar()"

                        :disabled="contestadas<total || finalizando"

                    >

                        <span

                            x-show="!finalizando"

                        >

                            Finalizar evaluación

                        </span>

                        <span

                            x-show="finalizando"

                        >

                            Finalizando...

                        </span>

                    </button>

                </div>

            </div>

        </div>

    </template>

</div>
