<?php if (!empty($soloFecha)): ?>
<div class="px-3 pt-3 pb-3 border-bottom flex-shrink-0"
     x-show="resumenDiaComentario && resumenDiaComentario.idReporte"
     x-cloak>
    <div class="row g-2">
        <div class="col-12">
            <div class="border rounded-2 bg-body px-2 py-2 d-flex align-items-center gap-2" style="font-size:.82rem;">
                <span class="d-inline-flex align-items-center justify-content-center rounded bg-primary-subtle text-primary flex-shrink-0" style="width:34px;height:34px;">
                    <i class="ti ti-calendar-event fs-5"></i>
                </span>
                <div class="flex-grow-1">
                    <div class="form-label small mb-0">Fecha:</div>
                    <div x-text="resumenDiaComentario ? (resumenDiaComentario.fecha_label || resumenDiaComentario.fechaEtiqueta || resumenDiaComentario.fecha || 'S/I') : 'S/I'"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="px-3 pt-3 pb-3 border-bottom flex-shrink-0"
     x-show="resumenDiaComentario && resumenDiaComentario.idReporte"
     x-cloak>
    <div class="card border shadow-sm mb-0">
        <div class="card-header bg-primary-subtle py-2 px-3 d-flex align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2 text-primary fw-semibold">
                <i class="ti ti-list-details fs-5"></i>
                <span>Detalle del día</span>
            </div>
            <span class="badge text-bg-primary d-inline-flex align-items-center gap-1 px-2 py-1">
                <i class="ti ti-hash"></i>
                <span x-text="resumenDiaComentario.idReporte"></span>
            </span>
        </div>
        <div class="card-body p-3">
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <div class="border rounded-2 bg-body px-2 py-2 h-100">
                        <div class="form-label small mb-0">Fecha:</div>
                        <div class="small text-body text-break" x-text="resumenDiaComentario.fechaEtiqueta || resumenDiaComentario.fecha || '—'"></div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border rounded-2 bg-body px-2 py-2 h-100">
                        <div class="form-label small mb-0">Periodo:</div>
                        <div class="small text-body text-break" x-text="resumenDiaComentario.periodo || '—'"></div>
                    </div>
                </div>
                <div class="col-12" x-show="resumenDiaComentario.estacion">
                    <div class="border rounded-2 bg-body px-2 py-2">
                        <div class="form-label small mb-0">Estación:</div>
                        <div class="small text-body text-break" x-text="resumenDiaComentario.estacion"></div>
                    </div>
                </div>
            </div>
            <div class="rounded-2 border bg-light-subtle px-3 py-2">
                <div class="d-flex align-items-center gap-2 text-primary fw-semibold small mb-2">
                    <i class="ti ti-gas-station fs-5"></i>
                    <span>Ventas y compras por producto</span>
                </div>
                <div class="small text-body mb-2">Resumen diario de los volúmenes de venta y compra, e importes capturados.</div>
                <div class="d-flex flex-column gap-2" style="max-height:150px;overflow-y:auto;">
                    <template x-for="(producto, indice) in (resumenDiaComentario.productos || [])" :key="producto.id || indice">
                        <div class="border rounded-2 bg-white px-2 py-2">
                            <div class="fw-semibold small text-body mb-1" x-text="producto.producto || 'Producto'"></div>
                            <div class="d-flex flex-wrap gap-2 small text-muted">
                                <span>Venta: <strong class="text-body" x-text="Number(producto.volumen_venta || 0).toLocaleString('es-MX') + ' L'"></strong></span>
                                <span>Compra: <strong class="text-body" x-text="Number(producto.total_compra || 0).toLocaleString('es-MX') + ' L'"></strong></span>
                                <span>Importe: <strong class="text-body" x-text="Number(producto.total_importe || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })"></strong></span>
                            </div>
                        </div>
                    </template>
                    <div class="small text-muted" x-show="!(resumenDiaComentario.productos || []).length">No hay productos registrados para este día.</div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
