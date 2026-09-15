<div id="container" class="pb-4"
data-module-station-key="sasisopa"
data-estacion-id="<?= e($estacionId ?? '') ?>"
x-data="calibracionVerificacion()">


<?php if (empty($estacionId)): ?>
 
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

    <div class="row mt-3">

<div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 d-flex align-items-stretch mb-4">
    <a href="/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos" 
       class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
        
        <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <!-- Icono circular -->
                <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width: 60px; height: 60px;">
                    <i class="ti ti-notebook text-white display-6"></i> 
                </div>

                <!-- Título a la derecha -->
                <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                    <h4 class="fw-bold text-dark mb-0 lh-sm">
                        Bitácora calibración de equipos
                    </h4>
                </div>
            </div>
        </div>

        <!-- Pie de la tarjeta -->
        <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
            <span class="small">Ver detalle</span>
            <div class="icon-transition">
                <i class="ti ti-arrow-right fs-5"></i>
            </div>
        </div>
    </a>
</div>


<div class="col-12">

<div class="card">  

<div class="card-header">
              <button class="btn bg-primary-subtle text-primary float-end">
                <a class="dropdown-item pointer" href="/sasisopa/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos/pdf-equipos-calibracion"><i class="ti ti-download"></i> Descargar</a>
              </button>
            
</div>

<div class="card-body">
    
    <div class="table-responsive">
          <table class="table table-bordered mb-3">
<tr>
<td class="text-center align-middle"><img class="text-center" src="<?= asset('images/logos/Logo.png') ?>" style="width: 200px;"></td>
<td colspan="2" class="text-center align-middle"><b>Equipos sometidos a calibración</b></td>
<td class="text-center align-middle">Fo.ADMONGAS.019</td>
</tr>
<tr>
<td class="text-center align-middle">Realizado por: Nelly Estrada Garcia </td>
<td class="text-center align-middle">Revisado por: Eduardo Galicia Flores </td>
<td class="text-center align-middle">Autorizado por: Tomas Tarno Quinzaños </td>
<td class="text-center align-middle">Fecha de autorizacion 01/10/2018</td>
</tr>
</table>
</div>

    <div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">

        <thead>

            <tr>

                <th class="text-center align-middle" width="150px">
                    Número de identificación
                </th>

                <th class="text-center align-middle">
                    Nombre del equipo (marca y modelo)
                </th>

                <th class="text-center align-middle">
                    Descripcion del equipo
                </th>

                <th class="text-center  align-middle">
                    Frecuencia de la calibración
                </th>

            </tr>

        </thead>

        <tbody>

            <template
                x-for="equipo in equipos"
                :key="equipo.identificacion + equipo.descripcion">

                <tr>

                    <td class="text-center fw-bolder align-middle"
                        x-text="equipo.identificacion">
                    </td>

                    <td class="align-middle text-center"
                        x-text="equipo.nombre">
                    </td>

                    <td class="align-middle text-center"
                        x-text="equipo.descripcion">
                    </td>

                    <td class="text-center align-middle">

                        <span
                            class="badge bg-primary-subtle text-primary"
                            x-text="equipo.frecuencia">
                        </span>

                    </td>

                </tr>

            </template>

            <tr x-show="!loading && equipos.length === 0">

                <td colspan="4" class="text-center">

                    No se encontraron equipos

                </td>

            </tr>

        </tbody>

    </table>
    </div>
</div>

</div>
</div>



    <div class="col-12 ">


    <div class="card">
<div class="card-header">
      <div class="d-flex align-items-center">
      <div class="ms-auto">
              <button class="btn bg-primary-subtle text-primary float-end">
                <a class="dropdown-item pointer" href="/sasisopa/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos/pdf-calendario-calibracion"><i class="ti ti-download"></i> Descargar</a>
              </button>
      </div>
  </div>
</div>

  <div class="card-body">


<div class="table-responsive">
  <table class="table table-bordered mb-3">
  <div class="table-responsive overflow-x-auto overflow-y-hidden">
<tr>
<td class="text-center align-middle"><img class="text-center" src="<?= asset('images/logos/Logo.png') ?>" style="width: 200px;"></td>
<td colspan="2" class="text-center align-middle"><b>Calendario de calibraciones</b></td>
<td class="text-center align-middle">Fo.ADMONGAS.020</td>
</tr>
<tr>
<td class="text-center align-middle">Realizado por: Nelly Estrada Garcia </td>
<td class="text-center align-middle">Revisado por: Eduardo Galicia Flores </td>
<td class="text-center align-middle">Autorizado por: Tomas Tarno Quinzaños </td>
<td class="text-center align-middle">Fecha de autorizacion 01/10/2018</td>
</tr>
</table>
</div>

<div class="table-responsive">
<table class="table table-striped pb-4 table-bordered  text-nowrap align-middle">

    <thead>

        <tr>

            <th class="text-center align-middle ">Número de identificación</th>
            <th class="text-center align-middle ">Nombre del equipo</th>
            <th class="text-center align-middle ">Frecuencia de la calibración</th>

            <th class="text-center align-middle ">Ene</th>
            <th class="text-center align-middle ">Feb</th>
            <th class="text-center align-middle ">Mar</th>
            <th class="text-center align-middle ">Abr</th>
            <th class="text-center align-middle ">May</th>
            <th class="text-center align-middle ">Jun</th>
            <th class="text-center align-middle ">Jul</th>
            <th class="text-center align-middle ">Ago</th>
            <th class="text-center align-middle ">Sep</th>
            <th class="text-center align-middle ">Oct</th>
            <th class="text-center align-middle ">Nov</th>
            <th class="text-center align-middle ">Dic</th>

        </tr>

    </thead>

    <tbody>

        <template
    x-for="equipo in calendario"
    :key="equipo.numero">

    <tr>

        <td class="text-center fw-bolder align-middle"
            x-text="equipo.numero">
        </td>

        <td class="text-center align-middle" x-text="equipo.equipo"></td>

        <td class="text-center align-middle"
            x-text="equipo.frecuencia">
        </td>

        <template
            x-for="(mes,index) in equipo.meses"
            :key="index">

            <td class="text-center align-middle"
                :class="mes.color"
                x-text="mes.year">
            </td>

        </template>

    </tr>

</template>

        <tr x-show="!loadingCalendario && calendario.length === 0">

    <td colspan="15"
        class="text-center text-muted">

        No se encontró información

    </td>

</tr>

    </tbody>

</table>
</div>
  </div>
</div>


    </div>
</di>


    
<?php endif; ?>

</div>