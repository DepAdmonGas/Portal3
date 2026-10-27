<div class="row mb-4">
    <?= $accionesHeader ?>
</div>

<style>
    .table-responsive > table th,
    .table-responsive > table td {
        text-align: center;
    }

    .table-precios-combustible {
        width: max-content;
        min-width: 1900px;
        table-layout: auto;
    }

    .table-precios-combustible th,
    .table-precios-combustible td {
        padding: .75rem .65rem !important;
        font-size: .9rem;
        vertical-align: middle;
    }

    .table-precios-combustible td {
        padding: 0 !important;
    }

    .table-precios-combustible th {
        white-space: normal !important;
        overflow-wrap: normal;
    }
</style>

<div class="col-12 mb-3">
    <div class="table-responsive">
        <?= $tablas['transporte'] ?>
    </div>
</div>

<div class="col-12 mb-3">
    <div class="table-responsive">
        <?= $tablas['detalle'] ?>
    </div>
</div>
