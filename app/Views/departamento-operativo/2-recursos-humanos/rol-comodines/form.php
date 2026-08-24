<div id="container" class="mt-4 mb-5"
data-id-reporte="<?= $detail['id'] ?>"
data-status="<?= $detail['status'] ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-fecha-inicio="<?= htmlspecialchars($detail['fecha_inicio']) ?>"
data-fecha-fin="<?= htmlspecialchars($detail['fecha_fin']) ?>"
x-data="{ ...actions(), ...rolComodinesForm() }">


<?php if ($puedeEditar && $detail['status'] == 0): ?>
<div class="row mb-4 ">
<div class="col-12 text-end">
<button type="button" class="btn btn-success" @click="finalizarRol()" :disabled="finalizando">
<i class="ti ti-check me-1"></i> <span x-text="finalizando ? 'Guardando...' : 'Finalizar'"></span>
</button>
</div>
</div>
<?php endif; ?>


<div class="card">
<div class="card-body">

<div class="row mb-3">
<div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
<label class="form-label">* Fecha de inicio:</label>
<input class="form-control" type="date" id="rcFechaInicio"
value="<?= $detail['fecha_inicio'] ?>"
x-model="fechaInicio"
@change="_guardarFechas()"
:readonly="esFinalizado"
:disabled="esFinalizado">
</div>
<div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
<label class="form-label">* Fecha de termino:</label>
<input class="form-control" type="date" id="rcFechaFin"
value="<?= $detail['fecha_fin'] ?>"
x-model="fechaFin"
@change="_guardarFechas()"
:readonly="esFinalizado"
:disabled="esFinalizado">
</div>
</div>

<div class="table-responsive">
<table class="table table-bordered mb-0 text-nowrap align-middle" width="100%">
<thead>
<tr>
<th class="text-center align-middle" width="50">#</th>
<th class="align-middle">Nombre completo</th>
<th class="text-center align-middle">Día</th>
<th class="align-middle">Estación</th>
</tr>
</thead>
<tbody>
<?php
$dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$esBorrador = $detail['status'] == 0;
foreach ($detail['empleados'] as $idx => $emp):
foreach ($dias as $diaIdx => $dia):
$val = $detail['asignaciones'][$emp['id']][$dia] ?? 0;
?>
<tr>
<?php if ($diaIdx === 0): ?>
<th class="text-center align-middle" rowspan="7"><?= $emp['id'] ?></th>
<th class="align-middle text-nowrap" rowspan="7"><?= htmlspecialchars($emp['nombre']) ?></th>
<?php endif; ?>
<td class="text-center align-middle fw-semibold"><?= $dia ?></td>
<td class="p-0 align-middle">
<?php if ($esBorrador): ?>
<select class="form-select form-select border-0"
onchange="rolComodinesFormActions.guardarAsignacion(<?= $detail['id'] ?>, <?= $emp['id'] ?>, <?= $diaIdx + 1 ?>, this.value)">
<option value="0" <?= $val === 0 ? ' selected' : '' ?>>Selecciona una opción...</option>
<?php foreach ($detail['estaciones'] as $est): ?>
<option value="<?= $est['id'] ?>" <?= $val === $est['id'] ? ' selected' : '' ?>><?= htmlspecialchars($est['nombre']) ?></option>
<?php endforeach; ?>
<option value="400" <?= $val === 400 ? ' selected' : '' ?>>Descanso</option>
</select>
<?php else:
$label = 'S/I';
if ($val === 400) { $label = 'Descanso'; }
elseif ($val > 0) {
foreach ($detail['estaciones'] as $est) {
if ($est['id'] === $val) { $label = $est['nombre']; break; }
}
}
?>
<span class="text-center d-block py-1"><?= htmlspecialchars($label) ?></span>
<?php endif; ?>
</td>
</tr>
<?php
endforeach;
endforeach;
?>
</tbody>
</table>
</div>


</div>
</div>

</div>
