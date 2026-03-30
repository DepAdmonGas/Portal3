<div id="container" class="mt-4">

<div class="row">
<?php foreach ($modulousuario as $clave => $mimodulo): ?>

    <?php if ($mimodulo['leer']): ?>
        
        <div class="col-md-4 d-flex align-items-stretch">
            <a href="<?= $mimodulo['ruta'] ?>" class="card w-100 card-hover">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                    <i class="ti <?= $mimodulo['icono'] ?> text-primary display-6"></i>
                    <div class="ms-auto">
                    <i class="ti ti-arrow-right text-primary fs-7"></i>
                    </div>
                    </div>
                    <div class="mt-4">
                    <h4 class="card-title mb-1 opacity-80">
                        <?= $mimodulo['nombre'] ?>
                    </h4>
                    </div>
                </div>
            </a>
        </div>

    <?php endif; ?>

<?php endforeach; ?>

</div>

</div>

