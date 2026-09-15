<div id="container"
x-data="{...actions(),...cursos('<?= $categoria ?>', <?= $multiestacion ? 'true' : 'false' ?>)}"
class="container-fluid pb-4"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>

<div id="<?= $modulo === 'sgm' ? 'sgm' : 'sasisopa' ?>-empty-message"
class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
<?= $modulo === 'sgm'
? 'Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.'
: 'Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.' ?>
</div>

<?php else: ?>

<div id="<?= $modulo === 'sgm' ? 'sgm' : 'sasisopa' ?>-content">




<div class="row mt-3">

<!----------------------- Módulos ----------------------->
<div class="col-12 p-0">

<div class="d-flex justify-content-between align-items-center mb-3">
<div><h5 class="fw-semibold">Módulos</h5></div>

<span class="badge text-bg-primary border">
<span x-text="modulos.length"></span> módulos
</span>

</div>

<div class="row g-3">

<template x-for="modulo in modulos" :key="modulo.id">
    <div class="col-xl-4 col-lg-6 mb-4">
        <div class="card shadow-sm h-100  overflow-hidden card-hover "
             @click="detalle(modulo)"
             style="cursor: pointer;">
            
            <!-- Encabezado de la tarjeta: Icono/Número y Badge -->
            <div class="card-header bg-transparent pt-4 px-4 pb-0">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center"
                         style="width: 48px; height: 48px; font-size: 1.1rem;">
                        <span x-text="modulo.numero"></span>
                    </div>
                    <span class="badge bg-primary-subtle text-primary px-3 py-2 fw-normal rounded-pill">
                        <i class="ti ti-book me-1 text-primary"></i>
                        <span x-text="modulo.totalTemas + ' temas'"></span>
                    </span>
                </div>
            </div>

            <!-- Cuerpo de la tarjeta: Título del Módulo -->
            <div class="card-body px-4 py-3 d-flex align-items-center">
                <h6 class="fw-bold text-dark mb-0" style="line-height: 1.5; font-size: 1.05rem;" x-text="modulo.titulo"></h6>
            </div>

            <!-- Pie de la tarjeta: Acción -->
            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold">
                <span class="small">Explorar temas del módulo</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-right fs-5"></i>
                </div>
            </div>
        </div>
    </div>
</template>

</div>


</div>

<!----------------------- Pendientes ----------------------->
<div class="col-12 p-0">
<template x-if="!loading && cursos.length">

<div class="card border-primary">

<div class="card-header bg-primary">
<div class="d-flex justify-content-between align-items-center">
<h4  class="mb-0 text-white card-title">
<i class="ti ti-school"></i> Cursos pendientes
</h4>
<span class="badge bg-success text-white"
x-text="total">
</span>
</div>
</div>

<div class="table-responsive">

<table class="table table-hover align-middle mb-0">

<thead>

<tr>

<th class="text-center align-middle">Fecha</th>

<th class="text-start align-middle" x-show="multiestacion">Nombre del personal</th>

<th class="text-start align-middle">Tema</th>

<th class="text-center align-middle">Categoria</th>

<th class="text-center" x-show="!multiestacion">Iniciar</th>

</tr>

</thead>

<tbody>

<template x-for="curso in cursos" :key="curso.id">

<tr>

<td class="text-center align-middle" width="250px">
<span x-text="curso.fecha"></span>
</td>

<td class="text-center align-middle" x-show="multiestacion">
<span x-text="curso.personal"></span>
</td>

<td class="text-start align-middle">
<span x-text="curso.titulo"></span>
</td>

<td class="text-center align-middle" width="98px">
<span class="badge bg-primary" x-text="curso.categoria">
</span>

</td>

<td width="140" class="text-end" x-show="!multiestacion">

<button
class="btn btn-success"
@click="iniciar(curso)">
<i class="ti ti-player-play me-1"></i>
Iniciar
</button>

</td>

</tr>

</template>

</tbody>

</table>

</div>

</div>

</template>

</div>




</div>







</div>

<?php endif; ?>

</div>