<div id="container">

<div class="text-end mt-4">
          <a type="button" class="btn btn-light" 
          href="/sasisopa/control-documentos-registros/pdf-requisitos-legales">
            <i class="ti ti-download"></i>
            Descargar
          </a>
        </div>

<div class="bg-white mt-3 p-3">

<?php foreach ($niveles as $key => $titulo): ?>

    <div class="p-2 bg-primary text-white mb-2">
        Nivel de gobierno <b><?= $titulo ?></b>
    </div>

    <table class="table table-bordered table-sm">

        <thead>

            <tr class="bg-light">

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

<?php endforeach; ?>

</div>

</div>