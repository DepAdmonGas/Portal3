<div id="container" data-elemento="110" data-herramienta="2" data-id="0">
    <div class="card mt-4">
        <div class="card-body">
            <div x-data="{ ...actions(), ...auditorias() }">
                <table class="table table-bordered table-striped table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <th class="text-center">Año</th>

                            <th colspan="2" class="text-center">
                                Fo.SGM.018 Plan de Auditoria
                            </th>

                            <th colspan="2" class="text-center">
                                Fo.SGM.019 Reporte e Hallazgos de Auditoria
                            </th>

                            <th colspan="2" class="text-center">
                                Fo.SGM.020 Plan de atencion de Hallazgos
                            </th>
                        </tr>
                    </thead>

                    <tbody>

                        <tr x-show="loading">
                            <td colspan="8" class="text-center">
                                Cargando...
                            </td>
                        </tr>

                        <tr x-show="error">
                            <td colspan="8"
                                class="text-center text-danger"
                                x-text="error">
                            </td>
                        </tr>

                        <template x-for="item in auditorias" :key="item.id">
                            <tr>
                                <td class="text-center" x-text="item.numero"></td>

                                <td class="text-center" x-text="item.year"></td>

                                <!-- Plan 18 -->
                                <td class="text-center">
                                    <a
                                        class="pointer"
                                        @click="editar(item.id, 18)">
                                        <i class="ti ti-edit fs-7"></i>
                                    </a>
                                </td>

                                <td class="text-center">
                                    <template x-if="item.plan18.pdf">
                                        <a
                                            class="pointer"
                                            @click="descargar(item.id, 18)">
                                            <i class="ti ti-file-type-pdf fs-7 text-danger"></i>
                                        </a>
                                    </template>

                                    <template x-if="!item.plan18.pdf">
                                        <i class="ti ti-x text-muted fs-7"></i>
                                    </template>
                                </td>

                                <!-- Hallazgos 19 -->
                                <td class="text-center">
                                    <a
                                        class="pointer"
                                        @click="editar(item.id, 19)">
                                        <i class="ti ti-edit fs-7"></i>
                                    </a>
                                </td>

                                <td class="text-center">
                                    <template x-if="item.hallazgo19.pdf">
                                        <a
                                            class="pointer"
                                            @click="descargar(item.id, 19)">
                                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>
                                        </a>
                                    </template>

                                    <template x-if="!item.hallazgo19.pdf">
                                        <i class="ti ti-x text-muted fs-7"></i>
                                    </template>
                                </td>

                                <!-- Plan 20 -->
                                <td class="text-center">
                                    <a
                                        class="pointer"
                                        @click="editar(item.id, 20)">
                                        <i class="ti ti-edit fs-7"></i>
                                    </a>
                                </td>

                                <td class="text-center">
                                    <template x-if="item.plan20.pdf">
                                        <a
                                            class="pointer"
                                            @click="descargar(item.id, 20)">
                                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>
                                        </a>
                                    </template>

                                    <template x-if="!item.plan20.pdf">
                                        <i class="ti ti-x text-muted fs-7"></i>
                                    </template>
                                </td>
                            </tr>
                        </template>

                        <tr
                            x-show="!loading && auditorias.length === 0">

                            <td colspan="8"
                                class="text-center text-secondary">

                                No se encontró información.

                            </td>

                        </tr>

                    </tbody>

                </table>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">

            <div class="card">
                <div x-data="{ ...actions(), ...listaasistenciaForm() }">
                    <div class="card-body">

                        <div class="float-end">
                            <?=
                            !empty($permisos['crear']) ?
                                '<button type="button" class="btn btn-primary" @click="crearAsistencia()">
          <i class="ti ti-plus"></i> Nuevo
          </button>'
                                : ''
                            ?>
                        </div>

                        <h4 class="card-title mb-0">Fo.SGM.001 Lista de asistencia</h4>

                        <div class="datatables mt-4">
                            <div class="table-responsive">
                                <table id="table-lista-asistencia" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Fecha</th>
                                            <th>Estatus</th>
                                            <th class="text-center">
                                                <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ------------------------- -->
    <!-- inicio offcanvas -------- -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="offcanvasExampleLabel">
                Ayuda
            </h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body fs-4">

            <p>Una vez cumplido el primer año de implementación de tu SGM se te activara el presente elemento para que de manera anual se realice la auditoria interna o externa. Recuerda realizar el registro mediante los formatos 017, 018, 019, 001</p>

        </div>
    </div>
    <!-- ------------------------- -->
    <!-- fin offcanvas -------- -->

</div>