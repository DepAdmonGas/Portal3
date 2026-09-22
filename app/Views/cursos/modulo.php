<script type="application/json" id="modulos-data"><?= json_encode($temas->values(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<div class="mt-4 pb-4"
     data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
     data-estacion-id="<?= (int) ($estacionId ?? 0) ?>"
     x-data="modulos()">

<?php if (empty($estacionId)): ?>

    <div id="<?= ($categoria ?? '') === 'SGM' ? 'sgm' : 'sasisopa' ?>-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        <?= ($categoria ?? '') === 'SGM'
            ? 'Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.'
            : 'Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.' ?>
    </div>

<?php else: ?>

    <div id="<?= ($categoria ?? '') === 'SGM' ? 'sgm' : 'sasisopa' ?>-content">

<div class="row g-4">

<template x-for="tema in temas" :key="tema.id">
    <div class="col-xl-4 col-lg-6 mb-4">
        <div class="card shadow-sm h-100 overflow-hidden card-hover"
             @click="verDetalle(tema.id)"
             style="cursor: pointer;">
            
            <!-- Encabezado: Círculo y Título a la izquierda -->
            <div class="card-header bg-transparent pt-4 px-4 pb-0 border-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 48px; height: 48px; font-size: 1.1rem;">
                        <span x-text="tema.numero"></span>
                    </div>
                    <h6 class="fw-bold text-dark mb-0" style="line-height: 1.4; font-size: 1.05rem;" x-text="tema.titulo"></h6>
                </div>
            </div>

            <!-- Cuerpo de la tarjeta: Métricas rediseñadas -->
            <div class="card-body pb-0">
                <div class="row g-3">
                    <!-- Métrico Cursos -->
                    <div class="col-sm-6">
                        <div class="border rounded-3 p-2 text-center bg-body-tertiary">
                            <span class="d-block text-muted text-uppercase fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Cursos</span>
                            <span class="fs-4 fw-bold text-dark" x-text="tema.total"></span>
                        </div>
                    </div>

                    <!-- Métrico Pendientes -->
                    <div class="col-sm-6">
                        <div class="border rounded-3 p-2 text-center"
                             :class="tema.pendientes > 0 ? 'bg-danger-subtle border-danger-subtle' : 'bg-body-tertiary'">
                            <span class="d-block text-uppercase fw-semibold"
                                  :class="tema.pendientes > 0 ? 'text-danger' : 'text-muted'"
                                  style="font-size: 0.7rem; letter-spacing: 0.5px;">Pendientes</span>
                            <span class="fs-4 fw-bold"
                                  :class="tema.pendientes > 0 ? 'text-danger' : 'text-dark'"
                                  x-text="tema.pendientes">
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pie de la tarjeta: Acción -->
            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-right fs-5"></i>
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

                            <?php if ($multiestacion): ?>

                            <th>
                                Nombre del personal
                            </th>

                            <?php endif; ?>

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

                    <?php if ($multiestacion): ?>

                    <td x-text="item.personal"></td>

                    <?php endif; ?>

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

    <?php endif; ?>

</div>
