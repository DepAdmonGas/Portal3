<div id="container"
    class="pb-4"
    x-data="sasisopa()"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

    <?php if (empty($estacionId)): ?>

        <div id="sasisopa-empty-message"
            class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
            Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
        </div>

    <?php else: ?>

        <div id="sasisopa-content">

            <div class="text-end mt-2">
                <div class="btn-group">
                    <button type="button"
                        class="btn btn-light dropdown-toggle text-dark"
                        data-bs-toggle="dropdown"
                        aria-haspopup="true"
                        aria-expanded="false">
                        <i class="ti ti-dots-vertical fs-4"></i>
                    </button>

                    <ul class="dropdown-menu animated rubberBand">
                        <li>
                            <a class="dropdown-item pointer"
                                @click="abrirModalBuscar()">
                                <i class="ti ti-search"></i> Buscar
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="row mt-4">
                <?php foreach ($elementos as $elemento): ?>

                    <div class="col-md-4 d-flex align-items-stretch">
                        <a href="sasisopa/<?= $elemento->url ?>"
                            class="card w-100 card-hover">

                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <i class="ti ti-layout-grid text-primary display-6"></i>

                                    <div class="ms-auto">
                                        <i class="ti ti-arrow-right text-primary fs-7"></i>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <h4 class="card-title mb-1 opacity-80">
                                        <?= $elemento->numero_sasisopa . '. ' . $elemento->nombre ?>
                                    </h4>
                                </div>
                            </div>

                        </a>
                    </div>

                <?php endforeach; ?>
            </div>

        </div>

    <?php endif; ?>


    <!-- Modal Buscar -->

    <div
        class="modal fade"
        id="modalBuscar"
        tabindex="-1"
        aria-labelledby="modalBuscarLabel"
        aria-hidden="true">

        <div class="modal-dialog modal-md modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header modal-colored-header bg-primary text-white">

                    <h4
                        class="modal-title text-white"
                        id="modalBuscarLabel">
                        <i class="ti ti-search"></i>
                        Buscar
                    </h4>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label">
                            * Fecha inicio
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            x-model="filtro.fechaInicio"
                            :class="{
                            'border border-danger':
                            errors.fechaInicio
                        }">

                    </div>

                    <div>

                        <label class="form-label">
                            * Fecha término
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            x-model="filtro.fechaTermino"
                            :class="{
                            'border border-danger':
                            errors.fechaTermino
                        }">

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button>

                    <button
                        type="button"
                        class="btn btn-success"
                        @click="buscarRegistros()">
                        <i class="ti ti-search"></i>
                        Buscar

                    </button>

                </div>

            </div>

        </div>

    </div>

</div>