<div
    class="container mt-3"
    x-data="evaluacion(<?= $calendario->id ?>)"
    x-init="init()"
>
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
    <div class="d-flex align-items-center justify-content-center mt-5">
        <div class="col-12 col-md-7 col-lg-5 mt-5">
            <div
                class="card border-0 shadow-lg overflow-hidden"
                x-transition
                x-show="resultado">

                <!-- HEADER COLOR -->
                <div
                    class="p-4 text-white text-center"
                    :class="'bg-' + resultado.color">
                    <i
                        :class="resultado.icono"
                        class="display-1 mb-3 d-block animate__animated animate__fadeInDown"
                    ></i>

                    <h2
                        class="mb-0 fw-bolder text-white"
                        x-text="resultado.titulo"
                    ></h2>
                </div>

                <!-- BODY -->
                <div class="card-body text-center p-3">

                    <!-- PORCENTAJE ANIMADO -->
                    <div class="mb-3">
                        <span
                            class="display-3 fw-bold text-dark"
                            x-text="resultado.porcentaje + '%'"
                        ></span>
                    </div>

                    <!-- MENSAJE -->
                    <p
                        class="lead text-muted mb-4"
                        x-text="resultado.mensaje"
                    ></p>

                    <!-- PROGRESO -->
                    <div class="progress mb-4" style="height: 12px;">

                        <div
                            class="progress-bar progress-bar-striped progress-bar-animated"
                            :class="'bg-' + resultado.color"
                            :style="'width:' + resultado.porcentaje + '%'"
                        ></div>

                    </div>

                    <!-- BADGE -->
                    <div class="mb-4">

                        <span
                            class="badge fs-6 px-3 py-2"
                            :class="resultado.aprobado ? 'bg-success' : 'bg-danger'"
                        >

                            <span x-text="resultado.aprobado ? 'Aprobado' : 'No aprobado'"></span>

                        </span>

                    </div>

                    <!-- BOTÓN -->
                    <a
                        href="/sasisopa/cursos"
                        class="btn btn-lg btn-primary w-100 rounded-pill shadow-sm"
                    >

                        Volver a cursos

                    </a>
                </div>
            </div>
        </div>
    </div>
</template>


    <!-- EXAMEN -->

    <template x-if="!loading && !resultado">

        <div>

            <!-- CABECERA -->

            <div class="card shadow-sm mb-4">

                <div class="card-body">

                    <div class="row align-items-center">

                        <div class="col-md">

                            <h3 class="mb-1">

                                Evaluación

                            </h3>

                            <div class="text-muted">

                                Tema

                                <strong x-text="tema.numero"></strong>

                                -

                                <span x-text="tema.titulo"></span>

                            </div>

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

                    <div class="card-body">

                        <h5 class="mb-3">
                            <span class="badge bg-primary me-2"
                                x-text="pregunta.numero"></span>

                            <span x-text="pregunta.titulo"></span>
                        </h5>

                        <template x-for="respuesta in pregunta.respuestas"
                                :key="respuesta.id">

                            <label class="card border mb-2 p-3 d-block"
                                style="cursor:pointer;">

                                <div class="form-check">

                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        :name="'pregunta_' + pregunta.id"
                                        :value="respuesta.valor"
                                        :checked="respuestas[pregunta.id] === respuesta.valor"

                                        @change="seleccionar(pregunta, respuesta)"
                                    >

                                    <label class="form-check-label ms-2">
                                        <span x-text="respuesta.titulo"></span>
                                    </label>

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