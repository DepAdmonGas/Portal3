<?php
/**
 * Bitácora de Maquinaria y Equipos — Almacén
 * Nuevo mantenimiento (pantalla completa).
 *
 * Réplica del legacy `maquinaria-equipos-nuevo/{idEquipo}`:
 *   - El select de FRECUENCIA sólo existe para Hidrolavadora / Planta de
 *     emergencia y sólo cuando el mantenimiento es Preventivo.
 *       · Hidrolavadora        → Diario, Semanal, Por horas
 *       · Planta de emergencia → Diario, Semanal, Mensual
 *     El resto de la maquinaria no lo muestra (no genera checklist).
 *   - La descripción de la FALLA sólo aparece en Correctivo.
 *   - El resumen de actividades se arma según maquinaria + frecuencia.
 *   - Estado actual y tipo son obligatorios; la fecha de inicio también.
 *
 * @var string $title
 * @var int    $idEquipo
 * @var array  $equipo
 * @var string $moduleStationKey
 * @var bool   $esUsuarioEstacion
 * @var bool   $usaFrecuencia
 * @var array  $frecuencias
 */

$baseUrl = '/departamento-operativo/almacen/maquinaria-equipos-bitacora';
$volverUrl = $baseUrl . '/' . (int)$idEquipo;
?>

<div id="container"
class="pb-4"
data-base-url="<?= $baseUrl ?>"
data-volver="<?= $volverUrl ?>"
data-id-equipo="<?= (int)$idEquipo ?>"
data-equipo="<?= htmlspecialchars(json_encode($equipo ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
data-usa-frecuencia="<?= !empty($usaFrecuencia) ? 'true' : 'false' ?>"
x-data="{ ...actions(), ...bitacoraNuevo() }"
x-init="initNuevo()">

<div class="row g-3 mt-3">

<div class="col-12">
<button type="button" class="btn btn-success float-end" @click="guardarNuevo()" :disabled="guardando">
<span class="spinner-border spinner-border-sm me-1" x-show="guardando"></span>
<i class="ti ti-check me-1" x-show="!guardando"></i> Guardar
</button>
</div>

<!------------ CARD INFORMACION ------------>
<div class="col-12">
<div class="card">

<div class="card-header bg-primary">
<h4  class="mb-0 text-white card-title"><i class="ti ti-info-circle"></i> Informacion (Maquinaria / Equipo)</h4>
</div>

<div class="card-body">
<div class="row">
<div class="col-md-3">
<label class="form-label mb-1">Maquinaria:</label>
<div x-text="equipo.maquinaria || ''"></div>
</div>

<div class="col-md-3">
<label class="form-label mb-1">Descripción:</label>
<div x-text="equipo.descripcion || 'Equipo'"></div>
</div>

<div class="col-md-3">
<label class="form-label mb-1">Marca:</label>
<div x-text="equipo.marca || 'Sin Información'"></div>
</div>

<div class="col-md-3">
<label class="form-label mb-1">Modelo:</label>
<div x-text="equipo.modelo || 'Sin Información'"></div>
</div>

</div>
</div>

</div>
</div>

<!------------ CARD INFORMACION ------------>
<div class="col-12">
<div class="card">

<div class="card-header bg-primary">
<h4  class="mb-0 text-white card-title"><i class="ti ti-settings-check"></i> Descripción del mantenimiento</h4>
</div>

<div class="card-body">
<div class="row g-3">

<div class="col-12">
<label class="form-label mb-1">* Estado actual:</label>
<select class="form-select" x-model="nuevo.estadoActual">
<option value="">Selecciona una opcion...</option>
<template x-for="op in estados" :key="op">
<option :value="op" x-text="op"></option>
</template>
</select>
</div>

<div class="col-md-4">
<label class="form-label mb-1">* Fecha de inicio:</label>
<input type="date" class="form-control" x-model="nuevo.fechaInicio">
</div>

<div class="col-md-4">
<label class="form-label mb-1">* Tipo de mantenimiento:</label>
<select class="form-select" x-model="nuevo.tipoMantenimiento" @change="cambioTipo()">
<option value="">Selecciona una opcion...</option>
<option value="1">Preventivo</option>
<option value="2">Correctivo</option>
</select>
</div>

<div class="col-md-4">
<label class="form-label mb-1">Costo del mantenimiento:</label>
<input type="number" step="0.01" min="0" class="form-control" x-model="nuevo.costoMantenimiento" placeholder="0.00">
</div>

<!-- FRECUENCIA: sólo Preventivo y sólo si la maquinaria la usa -->
<?php if (!empty($usaFrecuencia)): ?>
<div class="col-12" x-show="nuevo.tipoMantenimiento === '1'">
<label class="form-label mb-1">* Frecuencia del mantenimiento:</label>
<select class="form-select" x-model="nuevo.frecuencia" @change="cambioFrecuencia()">
<option value="">Selecciona una opcion...</option>
<?php foreach ($frecuencias as $f): ?>
<option value="<?= (int)$f['valor'] ?>"><?= htmlspecialchars($f['label'], ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
</select>
</div>
<?php endif; ?>

<!-- FALLA: sólo Correctivo -->
<div class="col-12" x-show="nuevo.tipoMantenimiento === '2'">
<label class="form-label mb-1">* Descripción de la falla:</label>
<textarea class="form-control" rows="3" x-model="nuevo.fallaDescripcion"></textarea>
</div>

</div>
</div>

</div>
</div>

<!------------ CARD INFORMACION ------------>
<div class="col-12">
<div class="card">

<div class="card-header bg-primary">
<h4  class="mb-0 text-white card-title"><i class="ti ti-eye"></i> Observaciones</h4>
</div>

<div class="card-body p-0">
<textarea class="form-control border-0 p-3" rows="4" x-model="nuevo.observaciones" placeholder="Ingresa aqui tus comentarios..."></textarea>
</div>
</div>
</div>

<!------------ CARD INFORMACION ------------>
<div class="col-12" x-show="previewFilas.length > 0">
<div class="card">

<div class="card-header bg-primary">
<h4 class="mb-0 text-white card-title"><i class="ti ti-tool"></i> Actividades programadas</h4>
</div>

<div class="card-body" id="resumenActividades">
  <template x-for="(f, i) in previewFilas" :key="i">
    <div>
      <!-- TÍTULO -->
      <template x-if="f.tipo === 'titulo'">
        <div class="alert alert-dark text-white py-2 px-3 mb-3">
          <strong x-text="f.texto"></strong>
        </div>
      </template>

      <!-- SUBTÍTULO -->
      <template x-if="f.tipo === 'subtitulo'">
        <div class="mb-3 fw-semibold text-dark" x-text="f.texto"></div>
      </template>

      <!-- NOTA -->
      <template x-if="f.tipo === 'nota'">
        <div class="mb-3 text-danger" x-text="f.texto"></div>
      </template>

      <!-- ACTIVIDAD CON CHECKBOX -->
      <template x-if="f.tipo === 'actividad' && f.campo === 'checkbox'">
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" disabled>
          <label class="form-check-label" x-text="f.texto"></label>
        </div>
      </template>

      <!-- ACTIVIDAD POR HORAS -->
      <template x-if="f.tipo === 'actividad' && f.campo !== 'checkbox'">
        <div class="mb-1 d-flex align-items-center gap-2">
          <span class="badge bg-secondary" x-show="f.bloque" x-text="f.bloque + 'h'"></span>
          <label class="form-check-label" x-text="f.texto"></label>
        </div>
      </template>
    </div>
  </template>
</div>
</div>
</div>


</div>
</div>
