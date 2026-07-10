<script>
window.temas = <?= json_encode($temas->values()) ?>;
</script>

<div class="mt-4 pb-4" x-data="modulos()">

<div class="row g-4">

    <template x-for="tema in temas" :key="tema.id">

        <div class="col-xl-3 col-lg-4 col-md-6">

            <div class="card border-0 shadow-sm h-100 rounded-4 overflow-hidden">

                <!-- Encabezado -->
                <div class="bg-info bg-gradient text-white p-2">

                    <div class="d-flex justify-content-between align-items-center">


                        <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center"
                             style="width:50px;height:50px;">

                        <div>
                            <h4 class="fw-bolder text-white mb-0"
                                x-text="tema.numero">
                            </h4>
                        </div>

                        </div>

                    </div>

                </div>

                <!-- Contenido -->
                <div class="card-body d-flex flex-column">

                    <h5 class="fs-7 text-center mb-3"
                        style="min-height:60px;"
                        x-text="tema.titulo">
                    </h5>

                    <div class="border rounded-3 p-3 bg-light">

                        <div class="d-flex justify-content-between mb-2">

                            <span class="text-muted">
                                Cursos
                            </span>

                            <span class="fw-bolder"
                                  x-text="tema.total">
                            </span>

                        </div>

                        <div class="d-flex justify-content-between">

                            <span class="text-muted">
                                Pendientes
                            </span>

                            <span class="badge rounded-pill"
                                  :class="tema.pendientes>0 ? 'bg-danger' : 'bg-success'"
                                  x-text="tema.pendientes">
                            </span>

                        </div>

                    </div>

                    <div class="mt-auto pt-4">

                        <button
                            class="btn btn-primary w-100 rounded-pill shadow-sm"
                            @click="verDetalle(tema.id)">

                            <i class="ti ti-eye"></i>

                            Ver detalle

                        </button>

                    </div>

                </div>

            </div>

        </div>

    </template>

</div>

    <div class="modal fade"
     id="detalleTemaModal"
     tabindex="-1"
     data-bs-backdrop="static">

    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">

        <div class="modal-content">

             <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white" x-text="detalle.modulo"></h4>

                <button
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <h6 class="mb-3"
                    x-text="detalle.tema">
                </h6>

                <table class="table table-striped">

                    <thead>

                        <tr>

                            <th>Fecha</th>

                            <th class="text-center">
                                Resultado
                            </th>

                            <th class="text-center">
                                Estado
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                <template x-for="item in detalle.calendarios" :key="item.id">

                <tr>

                    <td x-text="item.fecha"></td>

                    <td class="text-center">

                        <span class="fw-semibold"
                            :class="item.resultado_color"
                            x-text="item.resultado_texto">
                        </span>

                    </td>

                    <td class="text-center">

                        <template x-if="item.reconocimiento">

                            <a :href="'/cursos/descargar/'+item.id"
                            target="_blank">

                                <i class="ti ti-file-type-pdf text-danger fs-6"></i>

                            </a>

                        </template>

                        <template x-if="!item.reconocimiento">

                            <i class="ti ti-x text-muted fs-6"></i>

                        </template>

                    </td>

                </tr>

                </template>

                </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</div>