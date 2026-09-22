<?php $esImportacion = ($contexto ?? '') === 'importacion'; ?>
<div id="container" class="mb-4" data-base-url="<?= $baseUrl ?>" data-importacion="<?= $esImportacion ? '1' : '0' ?>">

<div class="row mt-4 mb-4">
    <?php if ($esImportacion): ?>
    <div class="col-12 text-end">
        <div class="d-flex align-items-center justify-content-end gap-2">
        <a href="<?= $baseUrl ?>" class="btn bg-danger-subtle text-danger">
            <i class="ti ti-arrow-left"></i> Regresar
        </a>
        <a href="<?= $baseUrl ?>/resumen/pdf" class="btn btn-success">
            <i class="ti ti-file-download"></i> Descargar PDF
        </a>
        </div>
    </div>
    <?php endif; ?>
</div>

  <div class="datatables">

      <div class="table-responsive">
        <table id="table-aditivo-resumen" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
          <thead>

            <tr>
              <th class="text-center align-middle">Estación</th>
              <th class="text-center align-middle">Gasolina Hitec 6590C</th>
              <th class="text-center align-middle">Diesel Hitec 4133G</th>
            </tr>

          </thead>
          <tbody></tbody>
          <tfoot>
            <tr class="table-dark">
              <th class="text-center align-middle">Total</th>
              <th class="text-center align-middle" id="total-gasolina"></th>
              <th class="text-center align-middle" id="total-diesel"></th>
            </tr>
          </tfoot>
        </table>
        </div>

  </div>

</div>