<div id="container"
x-data="{ ...actions(), ...incidentesAccidentes()}"
x-init="
municipio='<?= $estacion['di_municipio']; ?>';
estado='<?= $estacion['di_estado']; ?>';
nombre='<?= $filtro_usuario['nombre']; ?>';
puesto='<?= $user->puesto->tipo_puesto ?>';
razon_social='<?= $estacion['razonsocial']; ?>';
direccion='<?= $estacion['direccioncompleta']; ?>';
">

<div class="card mt-2">
    <div class="card-header">
  <div class="d-flex align-items-center">
      <div class="ms-auto">
      <div class="dropdown dropstart">
            <a href="javascript:void(0)" class="btn btn-light text-dark" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="ti ti-dots-vertical fs-6"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
              <li>
                <a class="dropdown-item pointer" href="javascript:void(0)" @click="openModalInvestigacion()"><i class="ti ti-plus"></i> Nuevo</a>
              </li>
              <li>
                <a class="dropdown-item pointer" href="/sasisopa/investigacion-incidentes-accidentes/pdf"><i class="ti ti-download"></i> Descargar</a>
              </li>
            </ul>
          </div>   
      </div>
  </div>
    </div>
  <div class="card-body">



  <div class="datatables mt-3">
    <div class="table-responsive overflow-x-auto overflow-hidden P-3">
      <table class="table table-striped table-bordered text-nowrap align-middle">
        <thead>
          <tr>
            <th class="text-center">#</th>
            <th class="text-center">Fecha</th>
            <th class="text-center">Nombre</th>
            <th class="text-center">Puesto</th>
            <th class="text-center">Descripción evento</th>
            <th class="text-center">Tipo evento</th>
            <th class="text-center">Muertes</th>
            <th class="text-center" colspan="2">
                <span class="badge rounded-pill text-bg-primary me-1"><small>1</small></span> 
                Grupo interdiciplinario</th>
            <th class="text-center" colspan="3">
                <span class="badge rounded-pill text-bg-primary me-1"><small>2</small></span> 
                Fo.ADMONGAS.026</th>
            <th class="text-center">
                <span class="badge rounded-pill text-bg-primary me-1"><small>3</small></span> 
                Tercer Autorizado</th>
          <th class="text-center">
          <a class="text-danger"><i class="ti ti-trash fs-6"></i></a>
          </th>
          </tr>
        </thead>
        <tbody>

        <template
            x-if="incidentes.length === 0">
            <tr>
                <td
                    colspan="14"
                    class="text-center">
                    No se encontró información
                </td>
            </tr>
        </template>

        <template
            x-for="(item,index) in incidentes"
            :key="item.id">

            <tr>

                <td class="text-center align-middle"
                    x-text="index + 1">
                </td>

                <td class="text-center align-middle"
                    x-text="item.fecha_larga">
                </td>

                <td class="text-center align-middle"
                    x-text="item.usuario">
                </td>

                <td class="text-center align-middle"
                    x-text="item.puesto">
                </td>

                <td class="text-center align-middle"
                    x-text="item.descripcion">
                </td>

                <td class="text-center align-middle"
                    x-text="item.tipo_evento">
                </td>

                <td class="text-center">
                    <span
                        x-text="
                            item.muertes
                            ? 'SI'
                            : 'NO'
                        ">
                    </span>
                </td>

                <!-- Grupo -->

                <td
                    class="text-center">

                    <i x-show="item.grupo > 0"
                        class="ti ti-circle-check text-success fs-6">
                    </i>

                    <i x-show="item.grupo == 0"
                        class="ti ti-x text-danger fs-6">
                    </i>

                </td>

                <td
                    class="text-center">

                    <a
                        @click="
                            grupoInterdisciplinario(
                                item.id
                            )
                        ">

                        <i class="pointer ti ti-users fs-6">
                        </i>

                    </a>

                </td>

                <!-- Formato 026 -->

                <td
                    class="text-center">

                    <template
                        x-if="
                            !item.tercer_autorizado
                        ">

                        <a
                            href="/uploads/archivos/Fo.ADMONGAS/Fo.ADMONGAS.026.docx"
                            download>

                            <i class="ti ti-download text-primary fs-6">
                            </i>

                        </a>

                    </template>

                    <template
                        x-if="
                            item.tercer_autorizado
                        ">

                        <i
                            class="ti ti-x fs-6">
                        </i>

                    </template>

                </td>

                <td
                    class="text-center">

                    <template
                        x-if="
                            !item.tercer_autorizado
                        ">

                        <a
                            @click="
                                subir026(
                                    item.id
                                )
                            ">

                            <i class="pointer ti ti-upload text-success fs-6">
                            </i>

                        </a>

                    </template>

                    <template
                        x-if="
                            item.tercer_autorizado
                        ">

                        <i
                            class="ti ti-x fs-6">
                        </i>

                    </template>

                </td>

                <td
                    class="text-center">

                    <template
                        x-if="
                            item.formato026.existe
                        ">

                        <a
                            :href="`/uploads/${item.formato026.archivo}`"
                            target="_blank">

                            <i class="ti ti-file-type-pdf text-danger fs-6">
                            </i>

                        </a>

                    </template>

                    <template
                        x-if="
                            !item.formato026.existe
                        ">

                        <i
                            class="ti ti-x text-danger fs-6">
                        </i>

                    </template>

                </td>

                <!-- Tercero -->

                <td
                    class="text-center">

                    <template
                        x-if="
                            item.tercer_autorizado
                        ">

                        <a
                            @click="
                                openModalTercero(
                                    item
                                )
                            ">

                            <i
                                class="pointer ti ti-shield-check text-success fs-6">
                            </i>

                        </a>

                    </template>

                    <template
                        x-if="
                            !item.tercer_autorizado
                        ">

                        <i
                            class="ti ti-x text-danger fs-6">
                        </i>

                    </template>

                </td>

                <!-- Eliminar -->

                <td
                    class="text-center">

                    <a href="javascript:void(0)"
                        @click="
                            eliminarInvestigacion(
                                item.id
                            )
                        ">

                        <i
                            class="ti ti-trash text-danger fs-6">
                        </i>

                    </a>

                </td>

            </tr>

        </template>

        </tbody>
      </table>
    </div>
  </div>
                    
  </div>
</div>

<div class="card">
    <div class="card-header">
    <div class="d-flex align-items-center">
        <h4>
            
        Sin accidentes a la fecha</h4>
      <div class="ms-auto">
        <button type="button" class="btn bg-primary-subtle text-primary">
                <a class="dropdown-item pointer" href="javascript:void(0)" @click="openModalNoAccidentes()"><i class="ti ti-plus"></i> Nuevo</a>
</button>
      </div>
  </div>
    </div>
  <div class="card-body">

  <div class="datatables">
    <div class="table-responsive p-0 overflow-x-auto overflow-hidden">

      <table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th class="text-center">#</th>
            <th class="text-center">Fecha</th>
            <th class="text-center">Nombre completo</th>
          <th class="text-center" width="36">
          <a class="text-white"><i class="ti ti-dots-vertical fs-6"></i></a>
          </th>
          </tr>
        </thead>
       <tbody>

    <template
        x-if="registros.length === 0">

        <tr>
            <td colspan="4"
                class="text-center">

                <small>
                    No se encontró información
                </small>

            </td>
        </tr>

    </template>

    <template
        x-for="item in registros"
        :key="item.id">

        <tr :class="item.row_class">

            <td class="text-center fw-bold"
                x-text="item.id">
            </td>

            <td class="text-center"
                x-text="item.fecha_larga">
            </td>

            <td class="text-center"
                x-text="item.usuario">
            </td>

            <td class="text-center">

                <div class="dropdown dropstart">
                    <a href="javascript:void(0)" data-bs-toggle="dropdown">
                         <i class="ti ti-dots-vertical fs-6"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item pointer d-flex align-items-center gap-3"
                            @click="editarNoAccidentes(item)">
                            <i class="fs-4 ti ti-edit"></i>Editar
                            </a>
                            </li>
                            <li>
                            <a class="dropdown-item pointer d-flex align-items-center gap-3"
                            :href="`/sasisopa/investigacion-incidentes-accidentes/no/pdf?id=${item.id}`">
                            <i class="fs-4 ti ti-download"></i>Descargar
                            </a>
                            </li>
                            <li>
                            <a href="javascript:void(0)" class="dropdown-item pointer d-flex align-items-center gap-3"
                            @click="eliminarNo(item.id)">
                            <i class="fs-4 ti ti-trash"></i>Eliminar
                            </a>
                        </li>
                    </ul>
                </div>

            </td>

        </tr>

    </template>

</tbody>
      </table>

    </div>
  </div>
                    
  </div>
</div>

<!-- Modal Investigacion -->
<div class="modal fade"
     id="modalInvestigacion"
     tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <div class="modal-header head-modal bg-primary">

                <h4 class="modal-title text-white">
<i class="ti ti-report"></i>
                    Investigación de Incidentes y Accidentes

                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="mb-3">

                    <label class="form-label">
                        * Fecha:
                    </label>

                    <input
                        type="date"
                        class="form-control"
                        x-model="form.fecha"
                        :class="errors.form.fecha ? 'is-invalid' : ''"
                        @input="errors.form.fecha = false">

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        * Descripción del evento:
                    </label>

                    <textarea
                        class="form-control"
                        rows="3"
                        x-model="form.descripcion"
                        :class="errors.form.descripcion ? 'is-invalid' : ''"
                        @input="errors.form.descripcion = false">
                    </textarea>

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        * Tipo de evento:
                    </label>

                    <select
                        class="form-select"
                        x-model="form.tipo_evento"
                        @change="cambioTipoEvento()"
                        :class="errors.form.tipo_evento ? 'is-invalid' : ''"
                        @input="errors.form.tipo_evento = false">

                        <option value="">
                            Selecciona una opcion...
                        </option>

                        <option value="1">
                            Tipo 1
                        </option>

                        <option value="2">
                            Tipo 2
                        </option>

                        <option value="3">
                            Tipo 3
                        </option>

                    </select>

                </div>

                <!-- Tipo 1 -->

                <template x-if="form.tipo_evento == '1'">

                
                <div class="alert alert-success mt-3">
                    <h6 class="fw-bolder">Evento Tipo 1</h6>
                    <ol type="a">
                        <li>
                            Lesiones del personal que requieran incapacidad médica causadas en el ejercicio o con motivo de las actividades que realiza en el Sector Hidrocarburos.
                        </li>
                        <li>
                            Daños a las instalaciones, sin interrupción de operaciones de las Actividades del Sector Hidrocarburos.
                        </li>
                        <li>
                            Fallas o errores en la operación de equipos en las que se involucren Equipos de Fuerza.
                        </li>
                    </ol>
                </div>

                </template>

                <!-- Tipo 2 -->
                
                <template x-if="form.tipo_evento == '2'">

                <div class="alert alert-success mt-3">

                    <h6 class="fw-bolder">Evento Tipo 2</h6>

                    <ol type="a">

                        <li>
                            Muerte de una o más personas dentro de las instalaciones del Regulado.
                        </li>

                        <li>
                            Simultáneamente, daños a las instalaciones e interrupción de operaciones de las Actividades del Sector Hidrocarburos.
                        </li>

                        <li>
                            Exista la liberación al Ambiente de una sustancia o material peligroso dentro de los límites de la Instalación del Regulado
                        </li>

                    </ol>

                </div>

                </template>

                <!-- Tipo 3 -->

                <template x-if="form.tipo_evento == '3'">

                <div class="alert alert-danger mt-3">

                    <h6 class="fw-bolder">Evento Tipo 3</h6>

                    <ol type="a">

                        <li>
                            Simultáneamente, una o más muertes de personal, daño a las instalaciones e interrupción de operaciones de las actividades del Sector Hidrocarburos.
                        </li>

                        <li>
                            Simultáneamente, lesiones al personal, daño a las instalaciones e interrupción de operaciones de las actividades del Sector Hidrocarburos.
                        </li>

                        <li>
                            Simultáneamente, evacuación de personal, daños a las instalaciones e interrupción de operaciones de las actividades del Sector Hidrocarburos.
                        </li>

                        <li>
                            Muertes o lesionados de la Población.
                        </li>

                        <li>
                            Se requiera la evacuación de la Población, y
                        </li>

                        <li>
                            Exista la liberación al Ambiente de una sustancia o material peligroso que rebase los límites de las instalaciones del Regulado.
                        </li>

                    </ol>

                </div>

                </template>

                <!-- Hubo muertes de personal -->
                <template x-if="form.tipo_evento == '2'">

                <div class="form-check mb-0">

                    <input
                        class="form-check-input"
                        type="checkbox"
                        x-model="form.hubo_muertes"
                        @change="toggleMuertes()">

                    <label class=" form-label form-check-label">

                        Hubo muertes de personal

                    </label>

                </div>

                </template>
                <!-- Contratar tercer autorizado -->
                <template
                    x-if="
                        form.tipo_evento == '1' ||
                        form.tipo_evento == '2'
                    ">

                <div class="form-check mb-3">

                    <input
                        type="checkbox"
                        class="form-check-input"
                        x-model="form.contratar_tercero"
                        :disabled="form.hubo_muertes">
                    <label class="form-label form-check-label">
                        Contratar tercer autorizado
                    </label>
                </div>
                </template>

                <template
                    x-if="
                        form.contratar_tercero ||
                        form.tipo_evento == '3'
                    ">

                <div class="mt-3">

                    <div class="mb-3">

                        <label class="form-label">
                            * Nombre del tercer autorizado:
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            x-model="form.nombre_ta">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            * Número de autorización:
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            x-model="form.numero_autorizacion">

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            * Nombre del líder de la investigación:
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            x-model="form.lider">

                    </div>

                </div>

                </template>

                <template x-if="form.tipo_evento == '3' || form.hubo_muertes">
                <div class="mb-3">

                    <label class="form-label">
                        * Número de muertes

                    </label>

                    <input
                        type="number"
                        min="0"
                        class="form-control"
                        x-model="form.muertes">

                </div>

                </template>

            </div>

            <div class="modal-footer">

            <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">
<i class="ti ti-x"></i>
                    Cancelar

                </button>

                <button
                    type="button"
                    class="btn btn-success"
                    @click="guardarInvestigacion()">
<i class="ti ti-check"></i>
                    Guardar

                </button>

            </div>

        </div>

    </div>

</div>

<!-- Modal Grupo interdiciplinario -->

<div class="modal fade" id="modalGrupo" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header modal-colored-header bg-primary">
        <h5 class="modal-title text-white">
            <i class="ti ti-users"></i>
            Grupo interdiciplinario</h5>
       <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                </button>
      </div>

      <div class="modal-body">


        <label class="form-label">* Nombre:</label>

        <input type="text"
               class="form-control mb-3"
               x-model="grupo.nombre"
               :class="errors.grupo.nombre ? 'is-invalid' : ''"
               @input="errors.grupo.nombre = false">

        <label class="form-label">* Puesto:</label>

        <input type="text"
               class="form-control mb-3"
               x-model="grupo.puesto"
               :class="errors.grupo.puesto ? 'is-invalid' : ''"
               @input="errors.grupo.puesto = false">    

               <label class="form-label">* Especialidad:</label>

        <input type="text"
               class="form-control mb-3"
               x-model="grupo.especialidad"
               :class="errors.grupo.especialidad ? 'is-invalid' : ''"
               @input="errors.grupo.especialidad = false">    

        

        <div class="text-end">
            <button class="btn btn-success"
                    @click="guardarPersonal()">
                <i class="ti ti-check"></i>
                    Guardar
            </button>
        </div>

        <table class="mt-3 table  table-bordered table-striped">
            <thead>
                <th class="text-center">#</th>
                <th class="text-center">Nombre</th>
                <th class="text-center">Puesto</th>
                <th class="text-center">Especialidad</th>
            </thead>
            <tbody>

                <template x-if="grupo_personal.length === 0">
                    <tr>
                        <td colspan="4" class="text-center text-muted">
                            No se encontro informacion
                        </td>
                    </tr>
                </template>

                <template x-for="a in grupo_personal" :key="a.id">
                    <tr>
                        <td class="align-middle text-center fw-bolder" x-text="a.id"></td>
                        <td class="align-middle text-center" x-text="a.nombre"></td>
                        <td class="align-middle text-center" x-text="a.puesto"></td>
                         <td class="align-middle text-center" x-text="a.especialidad"></td>
                        
                    </tr>
                </template>

            </tbody>
        </table>

      </div>
      <div class="modal-footer">
                <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">
<i class="ti ti-x"></i>
                    Cancelar

                </button>
      </div>
    </div>
  </div>
</div>

<!-- Fo.ADMONGAS.026 (Formato para el informe detallado de la Investigación de Causa Raíz de los Eventos tipo 1) -->
<div
    class="modal fade"
    id="modal026"
    tabindex="-1">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary">

                <h4 class="modal-title text-white d-flex align-items-center">
                    <i class="ti ti-file-upload me-1"></i>
                    Fo.ADMONGAS.026 (Formato para el informe detallado de la Investigación de Causa Raíz de los Eventos tipo 1)
                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="mb-1">

                    <label class="form-label">
                        * Formato (Fo.ADMONGAS.026):
                    </label>

                </div>

                <input
                    id="archivo026"
                    type="file"
                    accept=".pdf"
                    class="form-control"
                    :class="errors.archivo026 ? 'is-invalid' : ''"
                    @change="
                        archivo026 = $event.target.files[0];
                        errors.archivo026 = false;
                    ">
            </div>

            <div class="modal-footer">

             <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">
<i class="ti ti-x"></i>
                    Cancelar

                </button>

                <button
                    type="button"
                    class="btn btn-success"
                    @click="guardar026()">
<i class="ti ti-check"></i>
                    Guardar

                </button>

            </div>

        </div>

    </div>

</div>

<!-- Tercer Autorizado -->

<div
    class="modal fade"
    id="modalTercero"
    tabindex="-1">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary">

                <h4 class="modal-title text-white">
                    <i class="ti ti-user"></i>
                    Tercer Autorizado
                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

            <b class="form-label">Nombre del tercer autorizado:</b>
            <div class="mb-3" x-text="nombreTercer"></div>

            <b class="form-label">Numero de autorización:</b>
            <div class="mb-3" x-text="numeroTercer"></div>

            <b class="form-label">Nombre del líder de la investigación: </b>
            <div class="mb-3" x-text="liderTercer"></div>


                    <label class="form-label mb-0">
                        * Informe final:
                    </label>


                <input
                    id="archivoTercer"
                    type="file"
                    accept=".pdf"
                    class="form-control"
                    :class="errors.archivoTercer ? 'is-invalid' : ''"
                    @change="
                        archivoTercer = $event.target.files[0];
                        errors.archivoTercer = false;
                    ">
                <div class="text-end mt-3 mb-3">
                    <button
                    type="button"
                    class="btn btn-success"
                    @click="guardarTercero">
<i class="ti ti-check"></i>
                    Guardar

                </button>
                </div>

                <b class="form-label"> Informe final de la investigación causa raíz </b>

               <table class="table table-bordered mt-2">

    <thead>
        <tr>
            <th class="text-start">Fecha</th>
            <th class="text-center align-middle" width="36">
                <i class="ti ti-file-type-pdf text-muted fs-7"></i>
            </th>
        </tr>
    </thead>

    <tbody>

        <template x-if="uploadarchivoTercer">

            <tr>

                <td class="text-center align-middle"
                    x-text="fechaTercer">
                </td>

                <td class="text-center align-middle">

                    <a :href="`/uploads/${uploadarchivoTercer}`"
                       target="_blank">

                        <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                    </a>

                </td>

            </tr>

        </template>

        <template x-if="!uploadarchivoTercer">

            <tr>

                <td colspan="2"
                    class="text-center text-muted">

                    No se encontró información

                </td>

            </tr>

        </template>

    </tbody>

</table>

            </div>
<div class="modal-footer">
         <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">
<i class="ti ti-x"></i>
                    Cancelar

                </button>
</div>
        </div>

    </div>

</div>

<!-- Modal No Accidentes -->
<div class="modal fade"
     id="modalNoAccidentes"
     tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content rounded-0">

            <div class="modal-header modal-colored-header bg-primary">
<h4 class="modal-title text-white">

                 <i  class="ti" :class="modoModal ==='create' ? 'ti-alert-triangle' :'ti-edit'"></i>
                    Sin accidentes a la fecha
                </h4>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body fs-4">

                <table class="w-100">

                    <tr class="text-end">

                    <td>
                        <span x-text="municipio"></span>
                        <span x-text="estado"></span>
                        <span class="me-2">, a    </span>
                    </td>



                        <td>

                            <input type="date"
                                   class="form-control"
                                   x-model="fechaNoAccidentes"
                                   :class="errors.fechaNoAccidentes ? 'is-invalid' : ''"
                                    @input="errors.fechaNoAccidentes = false">

                        </td>

                    </tr>

                </table>

                <p class="mt-3">

                    <b x-text="nombre"></b>,
                    en carácter de Representante técnico del regulado
                    <b x-text="razon_social"></b>,
                    con ubicación en
                    <b x-text="direccion"></b>
                    manifiesto bajo protesta de decir verdad y sabedor
                    de la pena que conlleva a quienes actúan de mala fe
                    o declaran con falsedad, manifiesto que en las
                    instalaciones antes mencionadas a la fecha del
                    presente no han ocurrido ningún tipo de incidentes
                    o accidentes.

                </p>

                <p>

                    Lo anterior en cumplimiento a las
                    DISPOSICIONES administrativas de carácter general
                    que establecen los Lineamientos para Informar la
                    ocurrencia de incidentes y accidentes a la ASEA.

                </p>

                <p class="text-center align-middle"><b>Atentamente</b></p>

                <div class="text-center align-middle" x-text="nombre"></div>

                <div class="text-center align-middle" x-text="puesto"></div>

            </div>

            <div class="modal-footer">

            <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">
<i class="ti ti-x"></i>
                    Cancelar

                </button>

            <button
                type="button"
                class="btn btn-success"
                @click="guardarNoAccidentes()">
<i class="ti ti-check"></i>
                <span
                    x-text="
                        idNoAccidentes
                            ? 'Actualizar'
                            : 'Guardar'
                    ">
                </span>

            </button>

            </div>

        </div>

    </div>

</div>

<!-- ------------------------- -->
<!-- inicio offcanvas -------- -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasExampleLabel">
            Bienvenido al elemento 16. INVESTIGACIÓN DE INCIDENTES Y ACCIDENTES, del Sistema de Administración
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

          <p>
            En este apartado podrás registrar los accidentes ocurridos dentro de la estación de servicio.
          </p>

          <hr>

          <label class="fw-bold">Como hacerlo:</label>
          <ul>
            <li>Da clic en el botón <i class="ti ti-plus fs-6 text-primary"></i> para agregar un nuevo registro sobre algún incidente o accidente ocurrido.</small></li>
            <li>La investigación e informe de los eventos tipo 1 y 2 (Excepto cuando exista muerte de una o mas personas dentro de las instalaciones) puede realizarse por personal interno especializado utilizando un procedimiento para identificar la causa raíz de los accidentes, sin embargo también se podrá contratar un tercer autorizado ante la ASEA.</li>
            <li>Cuando el evento es tipo 2 (Existe muerte de una o mas personas dentro de las instalaciones) y tipo 3 se deberá contratar aun tercer autorizado para realizar la investigación causa raíz.</li>
          </ul>

          <hr>

          <label class="fw-bold">Responsables:</label>
          <p>Recuerda que es responsabilidad del <label class="text-danger fw-bold">Representante Técnico</label> (RT), <label class="text-danger fw-bold">Gerente de la Estación</label>, <label class="text-danger fw-bold">Representante Legal</label> y departamento de mantenimiento realizar la investigación causa raíz así como el informe detallado.</p>

          <small>
            <div>Nota:</div>
            <p>No olvides los siguientes conceptos:</p>
            <b>Accidente:</b> Evento que ocasiona afectaciones al personal, a la Población, a los bienes propiedad de la Nación, a los equipos e instalaciones, a los sistemas y/o procesos operativos y al medio ambiente.<br>
            <b>Incidente:</b> Evento o combinación de eventos inesperados no deseados que alteran el funcionamiento normal de las Instalaciones, del proceso o de la industria; acompañado o no de afectación al Ambiente, a las Instalaciones, a la Población y/o al personal del Regulado, así como al personal de contratistas, subcontratistas, proveedores y prestadores de servicios.

          </small>

    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->

</div>