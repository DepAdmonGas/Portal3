<div id="container" class="pb-4"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>"
    x-data="{ ...actions(), ...capacitacionInterna() }">



<?php if (empty($estacionId)): ?>
 
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>


    <div class="row mt-3 d-flex justify-content-between align-items-center">

        <div class="col-12 mb-3">
    <div class="text-end">
    
        <a type="button" class="btn bg-primary-subtle text-primary" @click="openModalBuscar()">
        <i class="ti ti-search"></i> Buscar</a>


    </div>
</div>
    

        <div class="mt-0" x-html="DOMPurify.sanitize(htmlReporte)"></div>
        
</div>
   

<div class="row mt-3">
    <?php foreach ($cursos ?? [] as $curso): ?>
        <div class="col-xl-4 col-lg-6 mb-4 d-flex align-items-stretch">
            <div class="card h-100 w-100 overflow-hidden position-relative d-flex flex-column">
                
                <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                    <!-- Bloque Superior: Icono + Título y badge alineados a la derecha -->
                    <div class="d-flex align-items-center gap-3">
                        <!-- Icono de Categoría (izquierda) -->
                        <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width: 60px; height: 60px; font-size: 1.1rem;">
                            <i class="ti ti-books text-white display-6"></i> 
                        </div>

                        <!-- Título y Badge (derecha, alineados al texto) -->
                        <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                            <span class="badge bg-primary-subtle text-primary fw-bold mb-2">
                                <?= $curso->temas_count ?> Temas
                            </span>
                            <h4 class="fw-bold text-dark mb-0 lh-sm">
                                <?= $curso->titulo ?>
                            </h4>
                        </div>
                    </div>
                </div>

                <!-- Pie de la tarjeta: Select de temas -->
                <div class="card-footer bg-transparent border-top border-light px-4 py-3 mt-auto">
                    <select class="form-select" @change="irATema($event)">
                        <option value="">Selecciona un tema...</option>
                        <optgroup label="<?= $curso->titulo ?>">
                            <?php foreach ($curso->temas as $tema): ?>
                                <option value="<?= $tema->id ?>" data-modulo="<?= $curso->id ?>">
                                    <?= $tema->num_tema ?>. <?= $tema->titulo ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>

            </div>
        </div>
    <?php endforeach; ?>
</div>

    <!-- MODAL -->
    <div class="modal fade" id="modalBuscar" tabindex="-1">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h4 class="modal-title text-white">
                    <i class="ti ti-search"></i>    
                    Buscar</h4>
                    <button class="btn-close btn-close-white" @click="closeModal('buscar')"></button>
                </div>

                <div class="modal-body">
                    <label class="fw-bold">Año:</label>
                    <input type="number" class="form-control mt-2"
                        x-model="year">
                </div>

                <div class="modal-footer">
                    <button class="btn bg-danger-subtle text-danger" @click="closeModal('buscar')"><i class="ti ti-x"></i> Cancelar</button>
                    <button class="btn btn-success" @click="buscar()"><i class="ti ti-search"></i> Buscar</button>
                </div>

            </div>
        </div>
    </div>
<?php endif; ?>
</div>