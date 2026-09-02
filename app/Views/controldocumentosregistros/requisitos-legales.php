<div id="container">

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
    <div class="table-responsive">
<table class="table table-striped table-bordered mb-0  align-middle">
        
<thead>
            <tr>

                <th class="text-center">Dependencia</th>
                <th class="text-center">Permiso</th>
                <th class="text-center">Fundamento</th>
            </tr>
</thead>

        <tbody>

        <?php if ($requisitos[$key]->count() > 0): ?>

            <?php foreach ($requisitos[$key] as $item): ?>

    
                <tr>

                    <td class="align-middle text-center">
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

    
</div>

 
<?php endforeach; ?>



</div>