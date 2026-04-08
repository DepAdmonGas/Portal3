<div class="mb-4" x-data="{ ...actions(), ...seguimientoForm() }"
x-init="init()" id="container" data-estacion="<?= $idEstacion ?>"  data-solicitud="<?= $noSolicitud ?>" data-puesto="<?= $utilitiesUser['idPuestoUser'] ?>">
 
<div class="row">
<div class="col-12 mt-3 mb-3 "> <span class="badge rounded-pill bg-success">No. de Solicitud: <?=$noSolicitud?></span></div>

<div class="col-xl-4 col-lg-4 col-md-6 col-sm-12">
<div class="row">

<!---------- CARD - COMENTARIOS ---------->
<div class="col-12">
<div class="card">
<div class="card-header text-bg-primary">
<h5 class="mb-0 text-white"><i class="fs-4 ti ti-message"></i> Comentarios</h5></div>
<div id="DivComentariosBody" class="card-body p-3"></div>
<div id="DivComentariosFooter" class="card-footer"></div>
</div>
</div>

<!---------- CARD - SEGUIMIENTO ---------->
<div class="col-12">
<div class="card">
<div class="card-header text-bg-primary">
<h5 class="mb-0 text-white"><i class="fs-4 ti ti-chart-line"></i> Seguimiento</h5>
</div>
<div class="card-body">
<ul id="timeline-seguimiento" class="timeline-widget mb-0 position-relative mb-n4"></ul>
</div>
<div id="seguimientoFooter" class="card-footer"></div>
</div>
</div>

</div>
</div>

<div class="col-xl-8 col-lg-8 col-md-6 col-sm-12">
<div class="card">
<div class="card-header text-bg-primary">
<h5 class="mb-0 text-white"><i class="fs-4 ti ti-credit-card"></i> Tarjetas solicitadas</h5>
</div>
<div class="card-body">

<div id="botonDescargaFile"></div>

<div class="datatables">
<div class="table-responsive">
<table id="table-tarjetas-detalle" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>
</div>
</div>
</div>

</div>
</div>