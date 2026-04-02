<div class="mb-4" x-data="{ ...actions(), ...seguimientoForm() }"
x-init="init()" id="container" data-estacion="<?= $idEstacion ?>"  data-reporte="<?= $noReporte ?>" data-puesto="<?= $utilitiesUser['idPuestoUser'] ?>">
 
<div class="row">
<div class="col-12 mt-3 mb-3 "> <span class="badge rounded-pill bg-success">No. de Solicitud: <?=$noReporte?></span></div>

<div class="col-xl-4 col-lg-4 col-md-6 col-sm-12">
<div class="card">
<div class="card-header text-bg-primary">
<h5 class="mb-0 text-white"><i class="fs-4 ti ti-chart-line"></i> Seguimiento</h5>
</div>
<div class="card-body">
<ul id="timeline-seguimiento" class="timeline-widget mb-0 position-relative mb-n4"></ul>
</div>

<div class="card-footer"></div>

</div>
</div>

<div class="col-xl-8 col-lg-8 col-md-6 col-sm-12">
<div class="card">
<div class="card-header text-bg-primary">
<h5 class="mb-0 text-white"><i class="fs-4 ti ti-chart-line"></i> Gafetes solicitados</h5>
</div>
<div class="card-body">
<div class="datatables">
<div class="table-responsive">
<table id="table-gafetes-detalle" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>
</div>
</div>
</div>

</div>
</div>