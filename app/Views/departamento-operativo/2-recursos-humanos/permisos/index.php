<div id="container" class="mt-4 mb-5"
     data-id-usuario="<?= (int)$idUsuario ?>"
     data-multiestacion="<?= !empty($multiestacion) ? 'true' : 'false' ?>"
     data-id-estacion="<?= (int)($idEstacion ?? 0) ?>"
     data-contexto-nombre="<?= htmlspecialchars($contextoNombre ?? '', ENT_QUOTES, 'UTF-8') ?>"
     data-puede-crear="<?= isset($puedeCrear) && $puedeCrear ? 'true' : 'false' ?>"
     data-puede-eliminar="<?= isset($puedeEliminar) && $puedeEliminar ? 'true' : 'false' ?>"
     data-puede-descargar="<?= isset($puedeDescargar) && $puedeDescargar ? 'true' : 'false' ?>"
     data-puede-vobo="<?= isset($puedeVoBo) && $puedeVoBo ? 'true' : 'false' ?>"
     data-total-pendientes="<?= (int)($totalPendientes ?? 0) ?>"
     x-data="{ ...actions(), ...permisosComponent() }">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis d-inline-flex align-items-center gap-1 px-3 py-2 fs-2 fw-semibold">
                <i class="ti ti-alert-circle fs-4"></i>
                <span>Pendientes: <span id="permisos-pending-count"><?= (int)($totalPendientes ?? 0) ?></span></span>
            </span>
        </div>
        <a href="/departamento-operativo/recursos-humanos/permisos-nuevo"
           class="btn bg-primary-subtle text-primary"
           x-show="contextoEspecifico && puedeCrear">
            <i class="ti ti-plus"></i> Nuevo
        </a>
    </div>

    <div class="datatables">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
            <table id="tabla-permisos" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                <tbody></tbody>
            </table>
        </div>
    </div>

<!-- Modal Detalle -->
<div class="modal fade" id="modalDetallePermiso" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-colored-header bg-primary text-white d-flex align-items-center">
                <h5 class="modal-title text-white mb-0">
                    <i class="ti ti-eye me-2"></i>Detalle Permiso <span id="detalle-permiso-folio"></span>
                </h5>

                <!-- ESTATUS FLOTANTE A LA DERECHA -->
                <div id="detalle-permiso-estatus" class="ms-auto me-3"></div>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pb-0" id="detalle-permiso-body">
                <div class="text-end py-4"><div class="spinner-border text-primary" role="status"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                    <i class="ti ti-x"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

</div>