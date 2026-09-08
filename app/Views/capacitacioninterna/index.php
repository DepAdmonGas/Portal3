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
            <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 mt-2 mb-2 ">
                <div class="card">
                    <div class="card-body text-center">
                        <h4><?= $curso->titulo ?></h4>

                        <label class="text-success fs-13 fw-bold"><?= $curso->temas_count ?></label> <small class="text-muted">Temas</small>

                        <select class="form-select mt-2" @change="irATema($event)">
                            <option value="">Selecciona un tema</option>
                            <optgroup label="<?= $curso->titulo ?>">
                                <?php foreach ($curso->temas as $tema): ?>
                                    <option value="<?= $tema->id ?>"
                                        data-modulo="<?= $curso->id ?>">
                                        <?= $tema->num_tema ?> - <?= $tema->titulo ?>
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