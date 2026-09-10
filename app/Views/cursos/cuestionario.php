<div
    id="container"
    class="mb-4"
    x-data="{
        ...actions(),
        ...cuestionario(
            <?= (int) $tema->id ?>
        )
    }">

    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 mt-3">

        <div>

            <h4 class="fw-semibold mb-1 text-muted">

                <?= htmlspecialchars(
                    $tema->titulo,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </h4>

        </div>


        <div class="dropdown">

            <button
                type="button"
                class="btn btn-primary"
                data-bs-toggle="dropdown">
                <i class="ti ti-settings me-1"></i>

                Administrar
            </button>


            <ul class="dropdown-menu dropdown-menu-end">

                <li>

                    <a
                        href="javascript:void(0)"
                        class="dropdown-item d-flex align-items-center gap-2"
                        @click="abrirModalRespuesta()">
                        <i class="ti ti-list"></i>

                        Respuesta
                    </a>

                </li>


                <li>

                    <a
                        href="javascript:void(0)"
                        class="dropdown-item d-flex align-items-center gap-2"
                        @click="abrirModalPregunta()">
                        <i class="ti ti-plus"></i>

                        Pregunta
                    </a>

                </li>

            </ul>

        </div>

    </div>


    <!-- =====================================================
    PREGUNTAS
    ====================================================== -->
    <div class="row g-4">

        <?php foreach ($preguntas as $pregunta): ?>

            <div class="col-xl-6 col-md-6 col-12">

                <div class="card h-100">

                    <div class="card-body">

                        <div
                            class="fw-semibold mb-3 pregunta-editable"
                            data-id="<?= (int) $pregunta->id ?>"
                            @dblclick="editarPregunta($event)"
                            @blur="guardarPregunta($event)"
                            @keydown.enter.prevent="$event.target.blur()">
                            <?= htmlspecialchars(
                                $pregunta->titulo,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </div>


                        <?php
                        $listaRespuestas =
                            $respuestas->get(
                                $pregunta->id,
                                collect()
                            );
                        ?>


                        <?php if ($listaRespuestas->isEmpty()): ?>

                            <p class="text-muted mb-0">
                                No hay respuestas registradas.
                            </p>

                        <?php else: ?>

                            <div class="d-flex flex-column gap-2">

                                <?php foreach ($listaRespuestas as $respuesta): ?>

                                    <label
                                        class="d-flex align-items-start gap-2">

                                        <input
                                            type="radio"
                                            class="form-check-input mt-1"

                                            name="pregunta-<?= (int) $pregunta->id ?>"

                                            value="<?= (int) $respuesta->id ?>"

                                            <?= (int) $respuesta->valor === 1
                                                ? 'checked'
                                                : '' ?>

                                            @change="
                                                marcarRespuesta(
                                                    <?= (int) $respuesta->id ?>,
                                                    <?= (int) $pregunta->id ?>
                                                )
                                            ">

                                        <span>
                                            <?= htmlspecialchars(
                                                $respuesta->titulo,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                    </label>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        <?php endforeach; ?>


        <?php if ($preguntas->isEmpty()): ?>

            <div class="col-12">

                <div class="card">

                    <div class="card-body text-center py-5">

                        <div
                            class="bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                            style="
                                width: 64px;
                                height: 64px;
                            ">
                            <i
                                class="ti ti-help-circle fs-7"></i>
                        </div>

                        <h5 class="fw-semibold">
                            No hay preguntas
                        </h5>

                        <p class="text-muted mb-0">
                            Agrega la primera pregunta al cuestionario.
                        </p>

                    </div>

                </div>

            </div>

        <?php endif; ?>

    </div>


    <!-- =====================================================
    MODAL PREGUNTA
    ====================================================== -->
    <div
        class="modal fade"
        id="modalPregunta"
        tabindex="-1">

        <div class="modal-dialog">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h5 class="modal-title text-white">
                        Nueva pregunta
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>

                </div>


                <div class="modal-body">

                    <label class="form-label fw-semibold">
                        Pregunta
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        x-model="nuevaPregunta"
                        @keydown.enter.prevent="guardarNuevaPregunta()">

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button>

                    <button
                        type="button"
                        class="btn btn-success"
                        @click="guardarNuevaPregunta()">
                        <i class="ti ti-plus me-1"></i>

                        Agregar
                    </button>

                </div>

            </div>

        </div>

    </div>

    <div
        class="modal fade"
        id="modalRespuesta"
        tabindex="-1">

        <div class="modal-dialog">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h5 class="modal-title text-white">
                        Nueva respuesta
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>

                </div>


                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Pregunta
                        </label>

                        <select
                            class="form-select"
                            x-model="nuevaRespuesta.id_pregunta">

                            <option value="">
                                Selecciona una pregunta
                            </option>

                            <?php foreach ($preguntas as $pregunta): ?>

                                <option
                                    value="<?= (int) $pregunta->id ?>">
                                    <?= htmlspecialchars(
                                        $pregunta->titulo,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div>

                        <label class="form-label fw-semibold">
                            Nueva respuesta
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            x-model="nuevaRespuesta.titulo"
                            @keydown.enter.prevent="guardarRespuesta()">

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button>

                    <button
                        type="button"
                        class="btn btn-success"
                        @click="guardarRespuesta()">
                        <i class="ti ti-plus me-1"></i>

                        Agregar
                    </button>

                </div>

            </div>

        </div>

    </div>

</div>