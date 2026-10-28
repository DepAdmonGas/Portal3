<?php $esImportacion = ($contexto ?? '') === 'importacion'; ?>
<div id="container" class="mb-4" data-base-url="<?= $baseUrl ?>" data-importacion="<?= $esImportacion ? '1' : '0' ?>">
<div class="row mt-4 mb-4">
    <?php if ($esImportacion): ?>
    <div class="col-12 d-flex flex-column flex-md-row justify-content-md-end gap-2">
        <div class="d-grid">
            <a href="<?= $baseUrl ?>" class="btn bg-danger-subtle text-danger">
                <i class="ti ti-arrow-left"></i> Regresar
            </a>
        </div>
        
        <div class="d-grid">
            <a href="<?= $baseUrl ?>/resumen/pdf" class="btn btn-success">
                <i class="ti ti-file-download"></i> Descargar PDF
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

  <div class="datatables">

      <div class="table-responsive overflow-x-auto overflow-y-hidden pb-2">
        <table id="table-aditivo-resumen" class="table table-striped table-bordered text-nowrap align-middle">
          <thead>

            <tr>
              <th class="text-center align-middle">Estación</th>
              <th class="text-center align-middle">Gasolina Hitec 6590C</th>
              <th class="text-center align-middle">Diesel Hitec 4133G</th>
            </tr>

          </thead>
          <tbody></tbody>
          <tfoot>
            <tr class="table-dark ">
              <th class="text-center align-middle text-white">Total</th>
              <th class="text-center align-middle text-white" id="total-gasolina"></th>
              <th class="text-center align-middle text-white" id="total-diesel"></th>
            </tr>
          </tfoot>
        </table>
        </div>

  </div>

</div>