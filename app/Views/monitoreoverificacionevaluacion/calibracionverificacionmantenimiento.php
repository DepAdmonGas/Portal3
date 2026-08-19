<div id="container" class="pb-4"
x-data="calibracionVerificacion()">


    <div class="row">
    <div class="col-5 mt-3">
      <div class="card">
      <div class="card-body">
      <h5>Bitácora calibración de equipos</h5>
      <div class="text-end mt-4">
      <a type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info" href="/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos"><i class="ti ti-eye"></i> Ver detalle</a>
      </div>
      </div>
      </div>
    </div>


<div class="col-12 mt-3">

<div class="card">  

<div class="card-header">
  <div class="d-flex align-items-center">
      <div class="ms-auto">
      <div class="dropdown dropstart">
            <a href="javascript:void(0)" class="btn btn-light" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="ti ti-dots-vertical fs-6"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
              <li>
                <a class="dropdown-item pointer" href="/sasisopa/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos/pdf-equipos-calibracion"><i class="ti ti-download"></i> Descargar</a>
              </li>
            </ul>
          </div>   
      </div>
  </div>
</div>

<div class="card-body">
    
    <div class="table-responsive">
          <table class="table table-bordered table-sm mt-2 mb-4">
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
<table class="table table-bordered table-sm">

        <thead>

            <tr>

                <th class="text-center bg-primary text-white align-middle">
                    Número de identificación
                </th>

                <th class="text-center bg-primary text-white align-middle">
                    Nombre del equipo (marca y modelo)
                </th>

                <th class="text-center bg-primary text-white align-middle">
                    Descripcion del equipo
                </th>

                <th class="text-center bg-primary text-white align-middle">
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
      <div class="dropdown dropstart">
            <a href="javascript:void(0)" class="btn btn-light" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="ti ti-dots-vertical fs-6"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
              <li>
                <a class="dropdown-item pointer" href="/sasisopa/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos/pdf-calendario-calibracion"><i class="ti ti-download"></i> Descargar</a>
              </li>
            </ul>
          </div>   
      </div>
  </div>
</div>

  <div class="card-body p-3">


<div class="table-responsive">
  <table class="table table-bordered table-sm mt-2 mb-2" <div class="table-responsive overflow-x-auto overflow-y-hidden">
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

<div class="table-responsive mt-3">
<table class="table table-bordered table-sm">

    <thead>

        <tr>

            <th class="text-center align-middle bg-primary text-white">Número de identificación</th>
            <th class="text-center align-middle bg-primary text-white">Nombre del equipo</th>
            <th class="text-center align-middle bg-primary text-white">Frecuencia de la calibración</th>

            <th class="text-center align-middle bg-primary text-white">Ene</th>
            <th class="text-center align-middle bg-primary text-white">Feb</th>
            <th class="text-center align-middle bg-primary text-white">Mar</th>
            <th class="text-center align-middle bg-primary text-white">Abr</th>
            <th class="text-center align-middle bg-primary text-white">May</th>
            <th class="text-center align-middle bg-primary text-white">Jun</th>
            <th class="text-center align-middle bg-primary text-white">Jul</th>
            <th class="text-center align-middle bg-primary text-white">Ago</th>
            <th class="text-center align-middle bg-primary text-white">Sep</th>
            <th class="text-center align-middle bg-primary text-white">Oct</th>
            <th class="text-center align-middle bg-primary text-white">Nov</th>
            <th class="text-center align-middle bg-primary text-white">Dic</th>

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


    

</div>