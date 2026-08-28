<div id="container" class="mb-3" x-data="{ ...actions(), ...capacitacionPersonal}"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<div x-data="capacitacionPersonal">

<!-- SELECT AÑO -->

<div class="row mt-3 mb-3">
<div class="col-md-9 col-xl-9"> <h4 x-text="resumen.year"></h4> </div>
<div class="col-md-3 col-xl-3">
<select class="form-select" x-model="year" @change="getResumen()">
<template x-for="y in [2026,2025,2024,2023,2022,2021,2020]" :key="y">
<option :value="y" x-text="y"></option>
</template>
</select>
</div>
</div>


<!-- LOADING -->
<div x-show="loading" class="text-center">
<div class="spinner-border" role="status">
<span class="visually-hidden">Cargando...</span>
</div>
</div>

<!-- CONTENIDO -->
<div x-show="!loading">

<div class="row">



<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">

<div class="card">
<div class="card-header bg-primary">
<h4 class="text-white">Programa de capacitación interna</h4>
</div>

<div class="card-body">
<!-- MODULOS -->
<template x-for="item in resumen.modulos" :key="item.modulo">
<div class="mb-4">

<h6 x-text="item.modulo"></h6>

<div class="row mt-3">
<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12">  
<div class="p-3 bg-light text-center">                  
<small>Número de personas capacitadas</small>
<div class="fs-12 text-secondary fw-bold" x-text="item.total"></div>
</div>
</div>

<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12">
<div class="p-3 bg-light text-center">
<small>Porcentaje de acreditación</small>
<div class="fs-12 text-success fw-bold" x-text="porcentaje(item.acreditado)"></div>
</div>
</div>

<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12">
<div class="p-3 bg-light text-center">
<small>Porcentaje de no acreditación</small>
<div class="fs-12 text-danger fw-bold" x-text="porcentaje(item.no_acreditado)"></div>
</div>
</div>
</div>

</div>
</template>
</div>

</div>

<!-- TOTALES -->

<div class="card">
<div class="card-header bg-primary">
<h4 class="text-white">Porcentaje total de capacitación</h4>
</div>
<div class="card-body">
<div class="row">
<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12">  
<div class="p-3 bg-light text-center">                  
<small>Total cursos tomados por personal</small>
<div class="fs-12 text-secondary fw-bold" x-text="resumen.totales.total_cursos"></div>
</div>
</div>

<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12">
<div class="p-3 bg-light text-center">
<small>Porcentaje de acreditación</small>
<div class="fs-12 text-success fw-bold" x-text="porcentaje(resumen.totales.acreditado)"></div>
</div>
</div>

<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12">
<div class="p-3 bg-light text-center">
<small>Porcentaje de no acreditación</small>
<div class="fs-12 text-danger fw-bold" x-text="porcentaje(resumen.totales.no_acreditado)"></div>
</div>
</div>
</div>
</div>
</div>

</div>


<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">

<div class="card">
<div class="card-header bg-primary">
<h4 class="text-white"> Programa de capacitación externa</h4>
</div>
<div class="card-body">
<div class="row">
<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12">  
<div class="p-3 bg-light text-center">                  
<small>Total Personal</small>
<div class="fs-12 text-secondary fw-bold" x-text="resumen.externa.total_personal"></div>
</div>
</div>

<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12">
<div class="p-3 bg-light text-center">
<small>Total capacitaciones</small>
<div class="fs-12 text-success fw-bold" x-text="resumen.externa.total_capacitaciones"></div>
</div>
</div>

<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12">
<div class="p-3 bg-light text-center">
<small>Personal capacitado</small>
<div class="fs-12 text-danger fw-bold" x-text="resumen.externa.personal_capacitado"></div>
</div>
</div>
</div>



<div class="text-center mt-3">

<div class="p-3 bg-light text-center">
<small>Personal capacitado</small>
<div class="fs-12 text-success fw-bold" x-text="porcentaje(resumen.externa.porcentaje)"></div>

</div>

</div>
</div>
</div>


<!-- EXTERNA -->



</div>
</div>

</div>

</div>

</div>