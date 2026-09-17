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
                
</button>
<button class="btn bg-primary-subtle text-primary "@click="abrirModalBuscar()">
<i class="ti ti-search"></i> Buscar
</button>

             
            

            <div class="row mt-4">
                <?php foreach ($elementos as $elemento): ?>

<div class="col-xl-4 col-lg-6 mb-4 d-flex align-items-stretch">
    <a href="sasisopa/<?= $elemento->url ?>" 
       class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
        
        <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
            <!-- Bloque Superior: Icono + Título alineados en Grid/Flex -->
            <div class="d-flex align-items-start gap-3">
                <!-- Icono de Categoría -->
                       <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width: 60px; height: 60px; font-size: 1.1rem;">
                    <i class="ti ti-layout-grid text-white display-6"></i> 
                </div>

                <!-- Título y Número -->
                <div class="flex-grow-1">
                    <span class="badge bg-primary-subtle text-primary fw-bold mb-3">
                        Elemento No. <?= $elemento->numero_sasisopa ?>
                    </span>
                    <h4 class="fw-bold text-dark mb-0 lh-sm">
                        <?= $elemento->nombre ?>
                    </h4>
                </div>
            </div>

        </div>

                <!-- Pie de la tarjeta: Acción -->
        <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
            <span class="small">Ver elemento</span>
            <div class="icon-transition">
                <i class="ti ti-arrow-right fs-5"></i>
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
                            * Fecha inicio:
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
                            * Fecha término:
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