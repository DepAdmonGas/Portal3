<div id="container" class="pb-4"
x-data="{ ...actions(), ...capacitacionInterna() }">

    <div class="text-end">
        <a type="button" class="btn btn-light" @click="openModalBuscar()">
        <i class="ti ti-search"></i> Buscar</a>
    </div>

    <div class="position-relative">

    <button 
        x-show="htmlReporte"
        @click="limpiarBusqueda()"
        class="btn btn-sm btn-danger position-absolute top-0 end-0">
        <i class="ti ti-x"></i>
    </button>
    

    <!-- SECURITY: Sanitizar con DOMPurify para prevenir XSS -->
    <div class="mt-4" x-html="DOMPurify.sanitize(htmlReporte)"></div>

    </div>

    <div class="row mt-3">
        <?php foreach($cursos ?? [] as $curso): ?>
            <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 mt-2 mb-2 ">
                <div class="card">
                    <div class="card-body text-center">
                        <h4><?= $curso->titulo ?></h4>
                        
                        <label class="text-success fs-13 fw-bold"><?= $curso->temas_count ?></label> <small class="text-muted">Temas</small>

                         <select class="form-select mt-2"  @change="irATema($event)">
                            <option value="">Selecciona un tema</option>
                            <optgroup label="<?= $curso->titulo ?>">
                            <?php foreach($curso->temas as $tema): ?>
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

                <div class="modal-header">
                    <h5 class="modal-title">Buscar</h5>
                    <button class="btn-close" @click="closeModal('buscar')"></button>
                </div>

                <div class="modal-body">
                    <label class="fw-bold">Agregar Año:</label>
                    <input type="number" class="form-control mt-2"
                           x-model="year">
                </div>

                <div class="modal-footer">
                    <button class="btn bg-danger-subtle text-danger" @click="closeModal('buscar')">Cancelar</button>
                    <button class="btn btn-primary" @click="buscar()">Buscar</button>
                </div>

            </div>
        </div>
    </div>

</div>