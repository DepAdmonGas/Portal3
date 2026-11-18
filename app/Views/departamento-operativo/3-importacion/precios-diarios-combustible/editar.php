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

    .precios-cell-save-indicator {
        position: absolute;
        z-index: 2;
        top: 50%;
        right: .4rem;
        transform: translateY(-50%);
        padding: 0 .2rem;
        background: var(--bs-body-bg);
        color: var(--bs-success);
        pointer-events: none;
    }
    .precios-cell-save-indicator.text-danger { color: var(--bs-danger) !important; }
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
