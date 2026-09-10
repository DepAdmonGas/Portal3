<div
    id="container"
    class="mb-4"
    x-data="{
        ...actions(),
        ...cursos()
    }">

    <div
        class="text-end mt-4 mb-4">

        <div class="dropdown">

            <button
                type="button"
                class="btn btn-primary"
                data-bs-toggle="dropdown"
                aria-expanded="false">
                <i class="ti ti-settings me-1"></i>

                Administrar
            </button>


            <ul class="dropdown-menu dropdown-menu-end">

                <li>
                    <a
                        href="javascript:void(0)"
                        class="dropdown-item d-flex align-items-center gap-2"
                        @click="abrirModalModulo()">
                        <i class="ti ti-folders"></i>

                        Módulos
                    </a>
                </li>

                <li>
                    <a
                        href="javascript:void(0)"
                        class="dropdown-item d-flex align-items-center gap-2"
                        @click="abrirModalTema()">
                        <i class="ti ti-plus"></i>

                        Nuevo tema
                    </a>
                </li>

            </ul>

        </div>

    </div>

    <div class="datatables">
        <div class="table-responsive">

            <table
                id="table-cursos"
                class="table table-striped table-bordered align-middle w-100">

                <thead>

                    <tr>

                        <th class="text-center">
                            #
                        </th>

                        <th>
                            Módulo
                        </th>

                        <th>
                            Nombre título
                        </th>

                        <th>
                            Categoría
                        </th>

                        <th class="text-center">
                            <i class="ti ti-file-type-pdf"></i>
                        </th>

                        <th class="text-center">
                            <i class="ti ti-dots-vertical fs-6 text-muted"></i>
                        </th>

                    </tr>

                </thead>

                <tbody></tbody>

            </table>

        </div>
    </div>


    <!-- =====================================================
    MODAL MÓDULOS
    ====================================================== -->
    <div
        class="modal fade"
        id="modalModulo"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h5 class="modal-title text-white">
                        Módulos
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>

                </div>


                <div class="modal-body">

                    <div class="row g-3">

                        <div class="col-md-9">

                            <label class="form-label fw-semibold">
                                Título
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                x-model="nuevoModulo"
                                @keydown.enter.prevent="guardarModulo()">

                        </div>


                        <div
                            class="col-md-3 d-flex align-items-end">

                            <button
                                type="button"
                                class="btn btn-success w-100"
                                @click="guardarModulo()">
                                <i class="ti ti-plus me-1"></i>

                                Agregar
                            </button>

                        </div>

                    </div>


                    <hr>


                    <div class="table-responsive">

                        <table class="table table-bordered align-middle mb-0">

                            <thead>

                                <tr>

                                    <th
                                        class="text-center"
                                        width="80">
                                        #
                                    </th>

                                    <th>
                                        Título
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($modulos as $modulo): ?>

                                    <tr>

                                        <td
                                            class="text-center fw-semibold">
                                            <?= (int) $modulo->num_modulo ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $modulo->titulo,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
    MODAL NUEVO TEMA
    ====================================================== -->
    <div
        class="modal fade"
        id="modalTema"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h5 class="modal-title text-white">
                        Nuevo tema
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>

                </div>


                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Módulo
                        </label>

                        <select
                            class="form-select"
                            x-model="nuevoTema.id_modulo">

                            <option value="">
                                Selecciona un módulo
                            </option>

                            <?php foreach ($modulos as $modulo): ?>

                                <option
                                    value="<?= (int) $modulo->id ?>">
                                    <?= (int) $modulo->num_modulo ?>
                                    -
                                    <?= htmlspecialchars(
                                        $modulo->titulo,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div>

                        <label class="form-label fw-semibold">
                            Nombre tema
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            x-model="nuevoTema.titulo"
                            @keydown.enter.prevent="guardarTema()">

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
                        @click="guardarTema()">
                        <i class="ti ti-check me-1"></i>

                        Agregar
                    </button>

                </div>

            </div>

        </div>

    </div>

</div>