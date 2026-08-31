<div id="container"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>
 
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>
<div class="text-end mt-4">
          <a type="button" class="btn bg-primary-subtle text-primary" 
          href="/sasisopa/control-documentos-registros/pdf-requisitos-legales">
            <i class="ti ti-download"></i>
            Descargar
          </a>
        </div>

<?php foreach ($niveles as $key => $titulo): ?>
<div class="card mt-3">
    <div class="card-header bg-primary">
          <div class="mb-0 card-title text-white">
            <i class="ti ti-label"></i>
        Nivel de gobierno <b><?= $titulo ?></b>
    </div> 
    </div>
<div class="card-body p-0">
<table class="table table-striped table-bordered mb-0  align-middle">
        
<thead>
            <tr>

                <th>Dependencia</th>
                <th>Permiso</th>
                <th>Fundamento</th>
            </tr>
</thead>

        <tbody>

        <?php if ($requisitos[$key]->count() > 0): ?>

            <?php foreach ($requisitos[$key] as $item): ?>

    
                <tr>

                    <td class="align-middle">
                        <b>
                            <?= $item->requisito->dependencia ?? 'S/I' ?>
                        </b>
                    </td>

                    <td class="align-middle">
                        <b>
                            <?= $item->requisito->permiso ?? $item->requisito_legal ?>
                        </b>
                    </td class="align-middle">

                    <td class="align-middle">
                        <?= $item->requisito->fundamento ?? 'S/I' ?>
                    </td>

                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>

                <td colspan="3" class="text-center align-middle">
                    No hay registros disponibles
                </td>

            </tr>

        <?php endif; ?>

        </tbody>

    </table>

</div>

    
</div>

 
<?php endforeach; ?>


<?php endif; ?>
</div>