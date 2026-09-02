<div
    id="container"
    class="pb-4"
    x-data="{ ...actions(), ...calibracionTanques(<?= $idEstacion ?>) }"
    data-idestacion="<?= $idEstacion ?>">

    <!-- Acciones -->
    <div
        class="d-flex justify-content-end align-items-center mt-4 mb-3">

        <button
            type="button"
            class="btn btn-primary"
            @click="crear()"
            :disabled="guardando">

            <template x-if="!guardando">

                <span
                    class="d-flex align-items-center gap-1">

                    <i class="ti ti-plus fs-5"></i>

                    Nueva calibración

                </span>

            </template>


            <template x-if="guardando">

                <span
                    class="d-flex align-items-center gap-2">

                    <span
                        class="spinner-border spinner-border-sm"
                        role="status">
                    </span>

                    Creando...

                </span>

            </template>

        </button>

    </div>


    <!-- Tabla -->
    <div class="datatables">

        <div class="table-responsive">

            <table
                id="table-calibracion-tanques"
                class="table table-striped table-bordered mb-0 text-nowrap align-middle">

                <thead>

                    <tr>

                        <th
                            class="text-center"
                            width="50px">

                            #

                        </th>

                        <th>
                            Fecha
                        </th>

                        <th
                            class="text-center"
                            width="1%">

                            <a class="text-muted">

                                <i
                                    class="ti ti-dots-vertical fs-6">
                                </i>

                            </a>

                        </th>

                    </tr>

                </thead>

                <tbody></tbody>

            </table>

        </div>

    </div>

</div>