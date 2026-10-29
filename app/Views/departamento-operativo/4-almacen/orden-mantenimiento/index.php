<?php $baseUrl = '/departamento-operativo/almacen/orden-mantenimiento'; ?>
<style>
#offcanvas-comentarios-orden .actividad-ticket-field {
    min-height: 62px;
    background: var(--bs-body-bg);
    font-size: .82rem;
}
</style>

<div id="orden-mantenimiento-app"
     class="pb-4"
     data-base-url="<?= $baseUrl ?>"
     data-module-key="<?= htmlspecialchars((string)$moduleStationKey, ENT_QUOTES, 'UTF-8') ?>"
     data-record-id="<?= (int)($registro['id'] ?? 0) ?>"
     data-record="<?= htmlspecialchars(json_encode($registro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null', ENT_QUOTES, 'UTF-8') ?>"
     data-puede-leer="<?= !empty($permisos['leer']) ? 'true' : 'false' ?>"
     data-puede-crear="<?= !empty($permisos['crear']) ? 'true' : 'false' ?>"
     data-puede-editar="<?= !empty($permisos['editar']) ? 'true' : 'false' ?>"
     data-puede-eliminar="<?= !empty($permisos['eliminar']) ? 'true' : 'false' ?>"
     x-data="{ ...actions() }">

    <section id="orden-listado" class="row mt-3">
        <div class="col-12 mb-3">
            <button id="orden-nuevo" type="button" class="btn bg-primary-subtle text-primary float-end"
                    <?= empty($permisos['crear']) || empty($estacionFija) ? 'hidden' : '' ?>>
                <i class="ti ti-plus me-1"></i> Nuevo
            </button>
        </div>
        <div class="col-12">
                    <div class="datatables">
                        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
                            <table id="tabla-orden-mantenimiento" class="table table-striped table-bordered mb-0 align-middle w-100">
                                <thead></thead><tbody></tbody>
                            </table>
                        </div>
                    </div>
        </div>
    </section>
<?php require __DIR__ . '/_modales.php'; ?>

</div>
