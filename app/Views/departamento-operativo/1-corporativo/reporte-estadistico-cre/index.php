<?php
/**
 * Toda la configuración del componente viaja en un solo atributo JSON.
 * Así la vista no necesitabucles ni condicionales: el JS resuelve las listas.
 */
$configCre = [
    'baseUrl'      => \App\Services\ReporteEstadisticoCreService::BASE_URL,
    'yearMesTemplate' => $yearMesTemplate ?? '',
    'moduleStationKey' => $moduleStationKey ?? '',
    'year'         => (int) $idYear,
    'month'        => (int) $idMes,
    'estacionId'   => (int) ($idEstacion ?? 0),
    'estacion'     => $estacion ?? '',
    'anios'        => $anios ?? [],
    'productos'    => $productos ?? [],
    'meses'        => $mesesDisponibles ?? [],
    'puedeDescargar' => !empty($puedeDescargar),
];
?>
<div id="container" class="mt-3 mb-3"
data-cre-config="<?= htmlspecialchars(json_encode($configCre, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
data-base-url="<?= htmlspecialchars($configCre['baseUrl'], ENT_QUOTES, 'UTF-8') ?>"
data-year-mes-template="<?= htmlspecialchars($configCre['yearMesTemplate'], ENT_QUOTES, 'UTF-8') ?>"
data-module-station-key="<?= htmlspecialchars($configCre['moduleStationKey'], ENT_QUOTES, 'UTF-8') ?>"
data-id-year="<?= $configCre['year'] ?>"
data-id-mes="<?= $configCre['month'] ?>"
data-id-estacion="<?= $configCre['estacionId'] ?>"
data-estacion="<?= htmlspecialchars($configCre['estacion'], ENT_QUOTES, 'UTF-8') ?>"
data-anios="<?= htmlspecialchars(json_encode($configCre['anios'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
data-productos="<?= htmlspecialchars(json_encode($configCre['productos'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
data-meses="<?= htmlspecialchars(json_encode($configCre['meses'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
data-puede-descargar="<?= $configCre['puedeDescargar'] ? 'true' : 'false' ?>"
x-data="{ ...actions(), ...reporteEstadisticoCreComponent() }">

<!-- ============ SIN ESTACIÓN SELECCIONADA ============ -->
<div id="cre-sin-estacion" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
    Debes de seleccionar una estación del menú superior para poder visualizar el Reporte Estadístico de la CRE.
</div>

<!-- ============ CONTENIDO ============ -->
<div id="cre-contenido">

<!---------- Barra de herramientas ---------->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">

<h5 class="mb-0" x-text="vistaAnual ? 'Resumen Anual ' + year : 'Resumen Mensual ' + etiquetaPeriodo()"></h5>

<div class="d-flex align-items-center gap-2 ms-auto">
<button type="button" class="btn bg-success text-white" @click="descargarFacturas()" x-show="puedeDescargar" :disabled="descargando">
<template x-if="!descargando"><i class="ti ti-file-zip me-1"></i></template>
<template x-if="descargando"><span class="spinner-border spinner-border-sm me-1"></span></template>
<span x-text="descargando ? 'Generando...' : 'Descargar facturas del año'"></span>
</button>
<button type="button" class="btn bg-primary-subtle text-primary" @click="cambiarVistaAnual()" :disabled="cargando">
<i class="ti ti-chart-pie me-1"></i>
<span x-text="vistaAnual ? 'Detalle mensual' : 'Resumen anual'"></span>
</button>
</div>
</div>

<!-- ============ VISTA MENSUAL ============ -->
<div x-show="!vistaAnual">

<!-- Facturas por producto -->
<div class="row g-3 mb-4">
<template x-if="reporte">
<template x-for="p in reporte.productos" :key="p.numero">
<div class="col-md-4">
<div class="card h-100">
<!-- Card Header con el color dinámico del producto -->
<div class="card-header text-white" :class="'bg-' + p.color">
<div class="d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white fs-6">
<i class="ti ti-file-description fs-5"></i>
<span x-text="p.nombre"></span>
</h5>
</div>
</div>

<!-- Card Body -->
<div class="card-body pb-3">
<div class="row g-3">
<template x-for="d in p.documentos" :key="d.periodo">
<div class="col-4">
<a class="btn bg-primary-subtle text-primary border d-flex flex-column align-items-center justify-content-center py-2 w-100 h-100 gap-1"
x-show="d.existe"
:href="'/download?tipo=cre&file=' + encodeURIComponent(d.archivo) + '&view=1'"
target="_blank"
:title="d.titulo + ' · ' + d.archivo">
<i class="ti ti-file-description fs-5"></i>
<span class="small text-truncate w-100" x-text="etiquetaPeriodoFactura(d.periodo)"></span>
</a>
</div>
</template>
</div>

<!-- Mensaje si no hay facturas -->
<div class="alert alert-warning mt-3 mb-0 text-center" role="alert" x-show="p.documentos.length === 0">
    <i class="ti ti-file-off me-1"></i> No hay facturas disponibles
</div>
</div>
</div>
</div>
</template>
</template>
</div>

        <!-- Loader -->
        <template x-if="cargando">
            <div class="d-flex flex-column align-items-center justify-content-center py-5">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <span class="text-muted fw-semibold">Consultando el reporte...</span>
            </div>
        </template>

        <!-- Sin reporte -->
        <template x-if="!cargando && !reporte">
            <div class="text-center text-muted py-5">
                <i class="ti ti-calendar-off d-block mb-2" style="font-size: 48px;"></i>
                No se encontraron reportes
            </div>
        </template>

<!---------- Días ---------->
<template x-if="!cargando && reporte">
<div>
<template x-for="dia in reporte.dias" :key="dia.fecha">
<div class="card mb-4">

<!-- Card Header del Día -->
<div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-calendar-stats fs-5"></i>
<strong x-text="dia.dia"></strong>
</div>
<span class="opacity-75 small" x-text="dia.fecha_label"></span>
</div>

<!-- Card Body: Grid de Productos -->
<div class="card-body">
<div class="row g-3">

<template x-for="celda in dia.productos" :key="celda.id">
<div class="col-md-4">
<div class="card h-100">
<!-- Header del Producto con Color Dinámico -->
<div class="card-header text-white" :class="'bg-' + colorProducto(celda.producto)">
<i class="ti ti-gas-station fs-6"></i>
<span x-text="celda.producto"></span>
</div>

<div class="card-body">
<!-- Tabla de Volúmenes -->
<div class="table-responsive mb-3">
<table class="table table-striped table-bordered mb-0 align-middle reporte-cre-seleccionable portal-table portal-table--selectable">
<thead>
<tr>
<th class="text-center align-middle">Vo. (Lt) inicial</th>
<th class="text-center align-middle">Vo. (Lt) venta</th>
<th class="text-center align-middle">Vo. (Lt) final</th>
<th class="text-center align-middle">Merma</th>
</tr>
</thead>
<tbody>
<tr class="pointer" :class="{ selected: selectedRows['volumen-' + celda.id] === celda.id }" @click="seleccionarFila('volumen-' + celda.id, celda.id)">
<td class="text-center align-middle" x-text="formatearNumero(celda.volumen_inicial)"></td>
<td class="text-center align-middle" x-text="formatearNumero(celda.volumen_venta)"></td>
<td class="text-center align-middle" x-text="formatearNumero(celda.volumen_final)"></td>
<td class="text-center align-middle text-danger fw-bold" x-text="formatearNumero(celda.merma)"></td>
</tr>
</tbody>
</table>
</div>

<!-- Tabla de Pipas Registradas -->
<div x-show="celda.pipas.length > 0">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 align-middle reporte-cre-seleccionable portal-table portal-table--selectable">
<thead>
<tr>
<th class="text-center align-middle">Pipa</th>
<th class="text-center align-middle">Volumen</th>
<th class="text-center align-middle">Precio Lt</th>
<th class="text-center align-middle">Flete</th>
<th class="text-start align-middle">Razón Social</th>
</tr>
</thead>
<tbody>
<template x-for="pipa in celda.pipas" :key="pipa.id">
<tr class="pointer" :class="{ selected: selectedRows['pipas-' + celda.id] === pipa.id }" @click="seleccionarFila('pipas-' + celda.id, pipa.id)">
<td class="text-center align-middle" x-text="pipa.pipa_numero"></td>
<td class="text-center align-middle" x-text="formatearNumero(pipa.volumen)"></td>
<td class="text-center align-middle" x-text="formatearNumero(pipa.precio_litro)"></td>
<td class="text-center align-middle" x-text="formatearNumero(pipa.costo_flete)"></td>
<td class="text-start align-middle" x-text="pipa.nombre_razonsocial" :title="pipa.nombre_razonsocial"></td>
</tr>
</template>

<tr class="table-dark fw-semibold">
<td class="text-center align-middle">Total</td>
<td class="text-center align-middle" x-text="formatearNumero(celda.total_compra)"></td>
<td class="text-center align-middle" x-text="'$' + formatearNumero(celda.total_importe)"></td>
<td colspan="2" class="align-middle"></td>
</tr>
</tbody>
</table>
</div>
</div>

<!-- Mensaje sin pipas -->
<div class="alert alert-warning mb-0 text-center" role="alert" x-show="celda.pipas.length === 0">
    <i class="ti ti-truck-off me-1"></i> Sin pipas registradas
</div>
</div>
</div>
</div>
</template>
</div>

</div>

<!-- Footer / Botón de Comentarios del Día -->
<div class="card-footer">
<button type="button" class="btn bg-primary-subtle text-primary float-end btn-badge-historico" @click="abrirComentarios(dia)">
<template x-if="dia.total_mensajes > 0">
<span class="badge-historico position-absolute top-0 start-100 translate-middle" x-text="dia.total_mensajes"></span>
</template>
<i class="ti ti-message-2 me-1"></i> Comentarios
</button>
</div>


</div>
</template>
</div>
</template>


<!---------- Totales del mes ---------->
<template x-if="!cargando && reporte && reporte.productos.length > 0">
<div class="row g-3 mt-1">
<template x-for="p in reporte.productos" :key="'tot-' + p.numero">
<div class="col-md-4">
<div class="card h-100 shadow-sm">
<!-- Card Header con el color dinámico del producto -->
<div class="card-header text-white" :class="'bg-' + p.color">
<div class="d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white fs-6">
<i class="ti ti-chart-bar fs-5 me-1"></i>
<span x-text="p.nombre"></span>
</h5>
</div>
</div>

<!-- Card Body con los totales -->
<div class="card-body">
<div class="d-flex flex-column gap-2">
<div class="d-flex justify-content-between align-items-center border-bottom pb-3">
<span>Total venta</span>
<strong class="fw-semibold" x-text="formatearNumero(p.total_venta)"></strong>
</div>
<div class="d-flex justify-content-between align-items-center border-bottom pb-3">
<span>Total compra</span>
<strong class="fw-semibold" x-text="formatearNumero(p.total_compra)"></strong>
</div>
<div class="d-flex justify-content-between align-items-center">
<span>Total importe</span>
<strong class="fw-semibold" x-text="'$' + formatearNumero(p.total_importe)"></strong>
</div>
</div>
</div>
</div>
</div>
</template>
</div>
</template>
</div>

<!-- ============ VISTA ANUAL ============ -->
    <div x-show="vistaAnual">
        <template x-if="anuales.length === 0 && !cargando">
            <div class="text-center text-muted py-5">
                <i class="ti ti-chart-pie-off d-block mb-2" style="font-size: 48px;"></i>
                No hay información del año seleccionado
            </div>
        </template>

<div class="row g-3">
    <template x-if="anuales.length > 0">
        <template x-for="p in anuales" :key="'an-' + p.numero">
            <div class="col-md-4">
                <div class="card h-100">
                    <!-- Card Header con el color dinámico del producto -->
                    <div class="card-header text-white" :class="'bg-' + p.color">
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="mb-0 text-white fs-6">
                                <i class="ti ti-calendar-stats fs-5 me-1"></i>
                                <span x-text="p.nombre"></span>
                            </h5>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="card-body pb-3">
                        <!-- Totales -->
                        <div class="d-flex flex-column gap-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                                <span class="">Total venta (litros)</span>
                                <strong class="fw-semibold" x-text="formatearNumero(p.total_venta)"></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                                <span class="">Total compra (litros)</span>
                                <strong class="fw-semibold" x-text="formatearNumero(p.total_compra)"></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="">Total importe</span>
                                <strong class="fw-semibold" x-text="'$' + formatearNumero(p.total_importe)"></strong>
                            </div>
                        </div>

                        <hr>

                        <!-- Facturas registradas en formato grid (col-4) -->
                        <h6 class="d-block mb-2 fw-semibold">Facturas registradas:</h6>
                        <div class="row g-3">
                            <template x-for="f in p.facturas" :key="f.mes + '-' + f.periodo">
                                <div class="col-4" x-show="f.existe">
                                    <a class="btn bg-primary-subtle text-primary border d-flex flex-column align-items-center justify-content-center py-2 w-100 h-100 gap-1"
                                       :href="'/download?tipo=cre&file=' + encodeURIComponent(f.archivo) + '&view=1'"
                                       target="_blank"
                                       :title="f.titulo + ' · ' + f.archivo">
                                        <i class="ti ti-file-description fs-5"></i>
                                        <span class="small text-truncate w-100 text-center" x-text="f.mes_nombre"></span>
                                    </a>
                                </div>
                            </template>
                        </div>

                        <!-- Mensaje si no hay facturas -->
                        <div class="alert alert-warning mt-4 mb-0 text-center py-2" role="alert" x-show="p.facturas.length === 0 || !p.facturas.some(f => f.existe)">
                            <i class="ti ti-file-off me-1"></i> Sin facturas
                        </div>

                        <!-- Alerta si hay archivos sin físico disponible -->
                        <div class="mt-3 text-secondary" x-show="p.facturas.some(f => !f.existe)">
                            <i class="ti ti-alert-triangle me-1"></i>
                            <span x-text="p.facturas.filter(f => !f.existe).length"></span> archivo(s) sin físico disponible.
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </template>
</div>

    </div>

</div>

<!-- ============ OFF-CANVAS COMENTARIOS (dentro del scope Alpine) ============ -->
<div class="offcanvas offcanvas-end d-flex flex-column" tabindex="-1" id="offcanvasComentariosCre" style="width: min(480px, 100vw); height: 100dvh; max-height: 100dvh;">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary flex-shrink-0">
        <div class="hstack gap-3">
            <div class="rounded-circle bg-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px; height:48px;">
                <i class="ti ti-message-circle text-primary fs-7"></i>
            </div>
            <div class="overflow-hidden">
                <h5 class="mb-1 text-white">COMENTARIOS</h5>
                <p class="mb-0 text-white opacity-75 text-truncate" x-text="etiquetaDiaComentarios()"></p>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white flex-shrink-0" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>

    <?php \App\Core\View::renderPartial('partials/_cre-comment-summary', ['soloFecha' => true]); ?>
    <div class="d-flex flex-column flex-grow-1 overflow-hidden" style="min-height: 0;">
        <div class="chat-box w-100 flex-grow-1 d-flex flex-column" style="min-height: 0; overflow: hidden;">
            <div class="chat-box-inner p-3 flex-grow-1" style="min-height: 0; overflow-y: auto; overflow-x: hidden; overscroll-behavior: contain;" x-ref="chatCre">

                <template x-if="cargandoComentarios">
                    <div class="d-flex flex-column align-items-center justify-content-center text-center h-100">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <p class="text-muted mb-0">Cargando comentarios...</p>
                    </div>
                </template>

                <template x-if="!cargandoComentarios && comentarios.length === 0 && !guardandoComentario">
                    <div class="d-flex flex-column align-items-center justify-content-center text-center h-100">
                        <i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
                        <p class="text-muted mb-0 fs-5">Sin comentarios</p>
                    </div>
                </template>

                <template x-if="!cargandoComentarios">
                    <div class="chat-list active-chat p-2">
            <template x-for="c in ordenarComentariosChat(comentarios)" :key="c.id">
                            <div class="d-flex mb-3" :class="c.es_propio ? 'justify-content-end' : 'justify-content-start'">
                                <template x-if="!c.es_propio">
                                    <div class="d-flex gap-3 align-items-start" style="max-width: 88%;">
                                        <div class="flex-shrink-0">
                                            <div class="rounded-circle bg-dark d-flex align-items-center justify-content-center" style="width:45px; height:45px;">
                                                <i class="ti ti-user text-white fs-5"></i>
                                            </div>
                                        </div>
                                        <div class="overflow-hidden">
                                            <h6 class="fw-semibold mb-1 text-truncate" x-text="c.nombre"></h6>
                                            <div class="fs-3 text-muted mb-1">
                                                <span x-text="c.fecha_label"></span><span x-show="c.hora_label" x-text="', ' + c.hora_label"></span>
                                            </div>
                                            <div class="p-3 text-bg-success rounded-3 text-white mt-2" style="overflow-wrap: anywhere;" x-text="c.comentario"></div>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="c.es_propio">
                                    <div class="d-flex flex-column align-items-end" style="max-width: 88%;">
                                        <div class="fs-3 text-muted mb-1 text-end">
                                            <span x-text="c.fecha_label"></span><span x-show="c.hora_label" x-text="', ' + c.hora_label"></span>
                                        </div>
                                        <div class="p-3 bg-primary text-white rounded-3 mt-2" style="overflow-wrap: anywhere;" x-text="c.comentario"></div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>


</div>
        </div>


        <?php \App\Core\View::renderPartial('partials/_chat-comment-sending', ['estadoEnvio' => 'guardandoComentario']); ?>

<div class="px-3 py-3 border-top bg-white flex-shrink-0">
<div class="d-flex align-items-center gap-2">
<div class="flex-grow-1">
<textarea class="form-control border-0 bg-light rounded-pill"
rows="1"
placeholder="Escribe un comentario..."
style="resize:none;"
x-model="formComentario"
@keydown.enter.prevent="guardarComentario()"></textarea>
</div>
<div class="flex-shrink-1">
<button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
style="width:44px; height:44px;" type="button"
title="Guardar comentario"
@click="guardarComentario()"
:disabled="guardandoComentario || !formComentario.trim()">
<template x-if="!guardandoComentario"><i class="ti ti-send fs-5"></i></template>
<template x-if="guardandoComentario"><span class="spinner-border spinner-border-sm"></span></template>
</button>
</div>
</div>
</div>
    </div>



</div>

</div>
