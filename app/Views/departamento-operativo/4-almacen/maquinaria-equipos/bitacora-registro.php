<?php
/**
 * Bitácora de Maquinaria y Equipos — Almacén
 * Pantallas del registro (una vista, cuatro rutas): detalle, editar,
 * mantenimiento y firma.
 *
 * Réplica del legacy (form-detalle / form-mantenimiento / form-firmar /
 * modal-editar):
 *   - INFORMACION (MAQUINARIA Y EQUIPO): MAQUINARIA, DESCRIPCION DEL EQUIPO,
 *     MARCA, MODELO.
 *   - MANTENIMIENTO: FECHA, ESTADO ACTUAL, TIPO DE MANTENIMIENTO, FRECUENCIA,
 *     COSTO y FALLA (sólo si el mantenimiento es Correctivo).
 *   - detalle       → checklist de consulta (SI/NO + Revisado por / Cambiado
 *     por cuando el formato lo usa) y firmas. Sin edición.
 *   - editar        → costo del registro con los bloqueos del legacy.
 *   - mantenimiento → checklist editable + evidencias (tarjetas del legacy).
 *   - firma         → checklist de consulta + firmas (pad A / B / tokens).
 *
 * @var string $title
 * @var int    $idEquipo
 * @var array  $registro
 * @var string $modo
 * @var string $moduleStationKey
 */

$baseUrl = '/departamento-operativo/almacen/maquinaria-equipos-bitacora';
$rutaPdfBase = $baseUrl . '/pdf';
$volverUrl = $baseUrl . '/' . (int)$idEquipo;
?>

<div id="container"
class="pb-5"
data-base-url="<?= $baseUrl ?>"
data-pdf-base="<?= $rutaPdfBase ?>"
data-volver="<?= $volverUrl ?>"
data-modo="<?= htmlspecialchars($modo ?? 'detalle', ENT_QUOTES, 'UTF-8') ?>"
data-registro="<?= htmlspecialchars(json_encode($registro ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
x-data="{ ...actions(), ...bitacoraRegistro() }"
x-init="initRegistro()">

<div class="row g-3 mt-3">

<!------------ CARD INFORMACION ------------>
<div class="col-12" x-show="reg.id">
<div class="card">

<div class="card-header bg-primary">
<h4  class="mb-0 text-white card-title"><i class="ti ti-info-circle"></i> Informacion (Maquinaria / Equipo)</h4>
</div>

<div class="card-body">
<div class="row">
<div class="col-md-3">
<label class="form-label mb-1">Maquinaria:</label>
<div x-text="reg.maquinaria || ''"></div>
</div>

<div class="col-md-3">
<label class="form-label mb-1">Descripción:</label>
<div x-text="reg.descripcion || 'Equipo'"></div>
</div>

<div class="col-md-3">
<label class="form-label mb-1">Marca:</label>
<div x-text="reg.marca || 'Sin Información'"></div>
</div>

<div class="col-md-3">
<label class="form-label mb-1">Modelo:</label>
<div x-text="reg.modelo || 'Sin Información'"></div>
</div>

</div>
</div>

</div>
</div>

<!------------ DESCRIPCION DEL MANTENIMIENTO ------------>
<div class="col-12" x-show="reg.id">
<div class="card">

<div class="card-header bg-primary">
<h4  class="mb-0 text-white card-title"><i class="ti ti-settings-check"></i> Descripción del mantenimiento</h4>
</div>

<div class="card-body">
<div class="row g-3">

<div class="col-md-4">
<label class="form-label mb-1">Fecha:</label>
<div x-text="reg.fecha_label || 'Sin información'"></div>
</div>  

<div class="col-md-4">
<label class="form-label mb-1">Estado actual:</label>
<div x-text="reg.estado_actual_label || 'Sin información'"></div>
</div> 

<div class="col-md-4">
<label class="form-label mb-1">Tipo de mantenimiento:</label>
<div x-text="reg.tipo_label || 'Sin información'"></div>
</div> 

<div class="col-md-4">
<label class="form-label mb-1">Frecuencia:</label>
<div x-text="reg.frecuencia_label || 'Sin información'"></div>
</div> 

<div class="col-md-4">
<label class="form-label mb-1">Costo:</label>
<div x-text="reg.costo_label || 'Sin información'"></div>
</div> 

</div>
</div>
</div>
</div>

<!------------ ACTIVIDADES DEL MANTENIMIENTO ------------>
<div class="col-12" x-show="reg.id">
<div class="card">
<div class="card-header bg-primary d-flex align-items-center justify-content-between">
<h4 class="mb-0 text-white card-title"><i class="ti ti-tool"></i> Actividades programadas</h4>
<span class="badge bg-success text-white" x-text="reg.actividades_total + ' actividades'"></span>
</div>
<div class="card-body p-0">
<?php if ($modo !== 'editar'): ?>
<div id="seccionChecklist" class="row g-3">

<!-- MANTENIMIENTO -->
<?php if ($modo === 'mantenimiento'): ?>
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 align-middle">
<thead>
<tr>
<th class="text-center align-middle" width="48px">#</th>
<th class="text-start align-middle">Actividad</th>
<th class="text-center align-middle" width="96px">SI / NO</th>
</tr>
</thead>
<template x-for="(a, i) in reg.actividades" :key="a.id">
<tbody>
<!-- Fila de sección (título / subtítulo / nota): ocupa toda la fila -->
<tr x-show="a.es_fila_seccion">
<td colspan="3" class="text-start align-middle" :class="claseSeccion(a.revision_tipo)" x-text="a.descripcion"></td>
</tr>

<tr x-show="!a.es_fila_seccion">
<td class="text-center align-middle" x-text="numeroActividad(i)"></td>
<td class="text-start align-middle">
<span class="badge bg-light text-dark border me-1" x-show="a.bloque_horas" x-text="a.bloque_horas + 'h'"></span>
<span x-text="a.descripcion"></span>
</td>
<td class="text-center align-middle">
<!-- checkbox -->
<input x-show="a.es_checkbox" type="checkbox" class="form-check-input" style="transform: scale(1.3);"
:checked="a.resultado == 1"
:disabled="!reg.puede_checklist"
@change="guardarActividad(a, { completada: $event.target.checked ? 1 : 0, revisadoPor: '' })">

<!-- texto libre (cambiado por) -->
<input x-show="a.es_texto" type="text" class="form-control form-control-sm" style="width: 200px;"
placeholder="Captura…" x-model="a.cambiado_por_texto" :disabled="!reg.puede_checklist" @change="cambiarTexto(a)">

<!-- revisado / select: persona de la estación -->
<select x-show="(a.es_revisado || a.es_select) && (reg.usuarios_estacion || []).length > 0"
class="form-select form-select-sm" style="width: 150px;"
x-model="revisadoPor[a.id]" :disabled="!reg.puede_checklist" @change="cambiarRevisado(a, $event)">
<option value="">Revisado por…</option>
<template x-for="u in reg.usuarios_estacion || []" :key="u.id">
<option :value="u.id" x-text="u.nombre"></option>
</template>
</select>

</td>
</tr>
</tbody>
</template>
</table>
</div>
<?php endif; ?>

<!-- MANTENIMIENTO -->
<?php if ($modo === 'detalle' || $modo === 'firma'): ?>
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 align-middle">
<thead>
<tr>
<th class="text-center align-middle" width="48px">#</th>
<th class="text-start align-middle">Actividad</th>
<th class="text-center align-middle" width="96px">SI / NO</th>
<th class="text-center align-middle" x-show="tieneRevisado()">Revisado por</th>
<th class="text-center align-middle" x-show="tieneCambio()">Cambiado por</th>
</tr>
</thead>
<template x-for="(a, i) in reg.actividades" :key="a.id">
<tbody>
<!-- Fila de sección (título / subtítulo / nota): ocupa toda la fila -->
<tr x-show="a.es_fila_seccion">
<td :colspan="3 + (tieneRevisado() ? 1 : 0) + (tieneCambio() ? 1 : 0)" class="text-start" :class="claseSeccion(a.revision_tipo)" x-text="a.descripcion"></td>
</tr>

<tr x-show="!a.es_fila_seccion">
<th class="text-center fw-normal" x-text="numeroActividad(i)"></th>
<td class="text-start">
<span class="badge bg-light text-dark border me-1" x-show="a.bloque_horas" x-text="a.bloque_horas + 'h'"></span>
<span x-text="a.descripcion"></span>
</td>
<td class="text-center">
<span class="badge" :class="valorSiNo(a) ? 'bg-success' : 'bg-danger'" x-text="valorSiNo(a) ? 'SI' : 'NO'"></span>
</td>
<td class="text-center" x-show="tieneRevisado()">
<span x-show="a.revision_tipo === 'revisado'" x-text="nombreUsuario(a.revisado_por)"></span>
<span class="text-muted" x-show="a.revision_tipo !== 'revisado'">Sin informacion</span>
</td>
<td class="text-center" x-show="tieneCambio()">
<span x-show="a.revision_tipo === 'select'" x-text="nombreUsuario(a.revisado_por)"></span>
<span x-show="a.revision_tipo === 'texto'" x-text="a.cambiado_por_texto || 'Sin informacion'"></span>
<span class="text-muted" x-show="a.revision_tipo !== 'select' && a.revision_tipo !== 'texto'">Sin informacion</span>
</td>
</tr>
</tbody>
</template>
</table>
</div>
<?php endif; ?>

<div class="text-muted small py-2" x-show="reg.actividades.filter(x => !x.es_fila_seccion).length === 0">
Este registro no tiene actividades programadas.
</div>

</div>
<?php endif; ?>
</div>
</div>
</div>


<!------------ ACTIVIDADES DEL MANTENIMIENTO ------------>
<?php if ($modo === 'mantenimiento'): ?>
<div class="col-12 id="seccionEvidencias"">
<div class="card">
<div class="card-header bg-primary d-flex align-items-center justify-content-between">
<h4 class="mb-0 text-white card-title"><i class="ti ti-camera"></i> Evidencias</h4>
<span class="badge bg-success text-white" x-text="reg.evidencias_total"></span>

</div>
<div class="card-body mb-0">
<div class="row mb-3">
<div class="col-12">
<label class="form-label mb-1">Evidencia:</label>
<div class="input-group">
<input type="file" class="form-control" id="inputEvidenciaBitacora"
accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" :disabled="!reg.puede_checklist && !reg.es_elaborador">
<button type="button" class="btn btn-success" @click="subirEvidencia()" :disabled="subiendo">
<i class="ti ti-check me-1"></i> Guardar
</button>
</div>
</div>

<template x-for="e in reg.evidencias" :key="e.id">
<div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 mt-4">
    <div class="card h-100 shadow-sm border bg-light position-relative">
        
        <!-- Badge redondo en la esquina superior derecha -->
        <div class="position-absolute top-0 end-0 p-2" style="z-index: 2;">
            <span class="badge bg-white text-dark rounded-circle shadow-sm d-flex align-items-center justify-content-center p-2" style="width: 32px; height: 32px;">
                <i :class="(e.es_pdf == 1 || e.es_pdf === true) ? 'ti ti-file-type-pdf text-danger' : 'ti ti-photo text-primary'" class="fs-6"></i>
            </span>
        </div>

        <!-- Vista previa exclusiva: Imagen o PDF dentro del rectángulo de 140px -->
        <a :href="e.url + '&view=1'" target="_blank" class="text-decoration-none overflow-hidden d-block position-relative" style="height: 140px;">
            <!-- Si NO es PDF, muestra la imagen -->
            <img x-show="!(e.es_pdf == 1 || e.es_pdf === true)" :src="e.url + '&view=1'" class="w-100 h-100" style="object-fit: cover;" alt="">
            
            <!-- Si SÍ es PDF, muestra el bloque de PDF -->
            <div x-show="e.es_pdf == 1 || e.es_pdf === true" class="text-center p-4 bg-white d-flex align-items-center justify-content-center h-100 w-100">
                <i class="ti ti-file-type-pdf text-danger display-4"></i>
            </div>
        </a>

        <!-- Cuerpo de la tarjeta -->
        <div class="card-body border-top pb-2 bg-white">
            <div>
                <a :href="e.url + '&view=1'" target="_blank" class="text-dark text-decoration-none">
                    <p class="small fw-bold text-truncate mb-0" :title="e.nombre_original" x-text="e.nombre_original"></p>
                </a>
                <span class="small text-muted d-block text-truncate">
                    <i class="ti ti-user me-1"></i><span x-text="e.subido_por_nombre"></span>
                </span>
            </div>

        </div>

        <div class="card-footer">
            <!-- Botones de Acción: Descargar y Eliminar al 50% -->
            <div class="d-flex align-items-center gap-2">
                <a class="btn btn-sm btn-outline-secondary w-50 d-flex align-items-center justify-content-center gap-1" :href="e.url" target="_blank" title="Descargar">
                    <i class="ti ti-download"></i> <span class="small">Descargar</span>
                </a>
                
                <button class="btn btn-sm btn-outline-danger w-50 d-flex align-items-center justify-content-center gap-1" x-show="reg.puede_checklist || reg.es_elaborador" @click.prevent="eliminarEvidencia(e)" title="Eliminar">
                    <i class="ti ti-trash"></i> <span class="small">Eliminar</span>
                </button>
            </div>
        </div>
    </div>
</div>
</template>

<div class="col-12 mt-4" x-show="reg.evidencias.length === 0">
<div class="alert alert-warning text-center mb-0" role="alert">
No se han subido evidencias
</div>
</div>

</div>
</div>
</div>
</div>
<?php endif; ?>


<!------------ FIRMAS ------------>
<?php if ($modo === 'detalle' || $modo === 'firma' || $modo === 'mantenimiento'): ?>
<div class="col-12" id="seccionFirmas" >
<h5 class="mb-3 fw-semibold">Firmas:</h5>

<div class="row g-3">
<!-- En mantenimiento sólo se firma "quien elabora" (A); Vo.Bo. y Autorización
     se firman desde la pantalla de Firma. -->
<!-- Mismo diseño que solicitud-cheque-firmar: 3 tarjetas, encabezado con
     círculo blanco, cuerpo centrado y pie con el nombre/"Pendiente". -->
<!-- Mismo diseño que solicitud-cheque-firmar: 3 tarjetas, encabezado con
     círculo blanco, cuerpo centrado y pie con el nombre/"Pendiente".
     Cada estado usa x-if (inserta/elimina el bloque) para que el centrado
     con d-flex no pelee con el ocultamiento. -->
<template x-for="tipo in ['A', 'B', 'C']" :key="tipo">
<div class="col-xl-4 col-lg-6 col-md-6 col-sm-12 mb-4">
<div class="card border h-100">

<!-- Encabezado: SIEMPRE el mismo título (firmado o no) -->
<div class="card-header bg-primary text-white py-3 border-0 text-uppercase">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-6" x-show="estadoFirma(tipo) === 'firmada'"></i>
<i class="ti ti-clock-hour-4 fs-6" x-show="estadoFirma(tipo) === 'pendiente'"></i>
<i class="ti ti-circle-check fs-6" x-show="estadoFirma(tipo) === 'pad' || estadoFirma(tipo) === 'token'"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white" x-text="tituloFirmaAccion(tipo)"></h6>
</div>
</div>
</div>

<!-- ===== FIRMADA: imagen centrada + medio de firma ===== -->
<template x-if="estadoFirma(tipo) === 'firmada'">
<div class="card-body d-flex align-items-center justify-content-center text-center p-3">
    <div class="w-100">
        <img x-show="firmaDe(tipo).es_imagen" :src="firmaDe(tipo).url" class="img-fluid mb-2" style="max-height: 110px; max-width: 100%; object-fit: contain;">
        <i x-show="!firmaDe(tipo).es_imagen" class="ti ti-signature text-primary mb-2" style="font-size: 80px;"></i>
        <div class="mt-1">
            <small class="text-dark d-block" x-text="mensajeMedioFirma(firmaDe(tipo))"></small>
            <small class="text-muted d-block" x-text="fechaFirma(firmaDe(tipo))"></small>
        </div>
    </div>
</div>
</template>
<template x-if="estadoFirma(tipo) === 'firmada'">
<div class="card-footer bg-light text-center">
<h6 class="mb-0 fw-semibold text-truncate" x-text="firmaDe(tipo).nombre"></h6>
</div>
</template>

<!-- ===== POR FIRMAR: SIGNATURE PAD (A y B por puesto) ===== -->
<!-- En Mantenimiento (y en Firma) el pad va centrado vertical y horizontalmente -->
<template x-if="estadoFirma(tipo) === 'pad'">
<div class="card-body p-3 d-flex align-items-center justify-content-center">
<div id="signature-pad" class="signature-pad-wrapper w-100" style="border: 2px dashed #adb5bd; border-radius: 6px; cursor: crosshair;">
<div class="signature-pad--body">
<canvas :id="'canvas-' + tipo" x-init="prepararPad(tipo)" style="width:100%; height:250px; display: block;"></canvas>
</div>
</div>
</div>
</template>

<div class="d-flex flex-column flex-md-row w-100">
    <template x-if="estadoFirma(tipo) === 'pad'">
        <button type="button" class="btn bg-danger-subtle text-danger w-100 w-md-50 rounded-top-0" style="border-bottom-left-radius: 6px; border-bottom-right-radius: 6px;" @click="limpiarPad(tipo)">
            <i class="ti ti-eraser me-1"></i> Limpiar firma
        </button>
    </template>
    
    <template x-if="estadoFirma(tipo) === 'pad'">
        <button type="button" class="btn btn-success w-100 w-md-50 rounded-0" @click="guardarFirmaPad(tipo)">
            <i class="ti ti-check me-1"></i> Guardar Firma
        </button>
    </template>
</div>

<!-- ===== POR FIRMAR: TOKEN (usuario 19 en VoBo, usuario 21 en Autorización) ===== -->
<template x-if="estadoFirma(tipo) === 'token'">
<div class="card-body text-center p-4 d-flex flex-column justify-content-center">
<h4 class="text-primary mb-3">Recepción de Token</h4>
<small class="text-primary mb-4">
Ingrese el token de seguridad que recibió por Telegram o correo electrónico.
Si aún no cuenta con uno, haga clic en alguno de los siguientes botones para generarlo.
</small>
<div class="row mt-3 w-100">
<div class="col-md-6 mb-3">
<button type="button" class="btn btn-success w-100" @click="crearTokenTelegram(tipo)" :disabled="tokenOcupado">
<i class="ti ti-brand-telegram me-1"></i> Generar token vía Telegram
</button>
</div>
<div class="col-md-6 mb-3">
<button type="button" class="btn btn-info text-white w-100" @click="crearTokenEmail(tipo)" :disabled="tokenOcupado">
<i class="ti ti-mail me-1"></i> Generar token vía Email
</button>
</div>
<div class="col-12">
<div class="input-group">
<input type="text" class="form-control" placeholder="Token de seguridad" x-model="token" @keyup.enter="firmarSolicitud(tipo)">
<button class="btn btn-outline-success" type="button" @click="firmarSolicitud(tipo)" :disabled="!token.trim()">
Firmar solicitud
</button>
</div>
</div>
</div>
</div>
</template>
<template x-if="estadoFirma(tipo) === 'token'">
<div class="card-footer bg-light text-center">
<small class="text-muted">Pendiente de firma electronica</small>
</div>
</template>

<!-- ===== PENDIENTE (no le toca firmar) ===== -->
<template x-if="estadoFirma(tipo) === 'pendiente'">
<div class="card-body p-4">
<div class="d-flex flex-column justify-content-center align-items-center text-center">
<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
<h6 class="text-muted mb-0" x-text="mensajeFaltaFirma(tipo)"></h6>
</div>
</div>
</template>
<template x-if="estadoFirma(tipo) === 'pendiente'">
<div class="card-footer bg-light text-center">
<small class="text-muted">Pendiente de firma electronica</small>
</div>
</template>

</div>
</div>
</template>

</div>

</div>
<?php endif; ?>

</div>
</div>
