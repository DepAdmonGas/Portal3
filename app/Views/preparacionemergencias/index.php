<div id="container" class="pb-4"
x-data="{ ...actions(), ...preparacionEmergencias()}">


<div class="row mt-4">

  <div class="col-md-6 align-items-stretch">
    <div class="card">
  <div class="card-body">

  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0"> Protocolo de respuesta a emergencias </h4>
      <div class="ms-auto">
        <button type="button" class="btn btn-primary"
        @click="nuevoProtocolo()">
        <i class="ti ti-plus"></i> Nuevo
        </button>
      
      </div>
  </div>

<div class="table-responsive mt-3">

    <table class="table table-sm table-bordered table-hover">

        <thead>

            <tr>

                <th class="bg-primary text-white">
                    Fecha elaboración
                </th>

                <th
                    class="bg-primary text-white text-center"
                    width="60">

                    PDF

                </th>

                <th
                    class="bg-primary text-white text-center"
                    width="60">

                    Anexos

                </th>

                <th
                    class="bg-primary text-white text-center"
                    width="60">

                    Editar

                </th>

                <th
                    class="bg-primary text-white text-center"
                    width="60">

                    Eliminar

                </th>

            </tr>

        </thead>

        <tbody>

            <template x-if="protocolos.length === 0">

                <tr>

                    <td
                        colspan="5"
                        class="text-center py-2">

                        <small class="text-muted">
                            No se encontró información para mostrar
                        </small>

                    </td>

                </tr>

            </template>

            <template
                x-for="item in protocolos"
                :key="item.id">

                <tr>

                    <td
                        x-text="item.fecha_formateada">
                    </td>

                    <td class="text-center">

                        <template x-if="item.archivo">

                            <a
                                :href="'/' + item.archivo"
                                target="_blank">

                                <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                            </a>

                        </template>

                        <template x-if="!item.archivo">

                            <i class="ti ti-file-off text-muted fs-7"></i>

                        </template>

                    </td>

                    <td class="text-center">

                        <a
                        href="javascript:void(0)"
                            @click="abrirAnexos(item.id)">

                            <i class="ti ti-paperclip fs-7 text-info"></i>

                        </a>

                    </td>

                    <td class="text-center">

                        <a
                        href="javascript:void(0)"
                            @click="editarProtocolo(item)">

                            <i class="ti ti-edit fs-7 text-warning"></i>

                        </a>

                    </td>

                    <td class="text-center">

                        <a
                            href="javascript:void(0)"
                            @click="eliminarProtocolo(item.id)">

                            <i class="ti ti-trash text-danger fs-7"></i>

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

    <div class="col-md-4 align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title"> Teléfonos de emergencias </h4>

         <div class="text-end mt-4">
          <button type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info"
          @click="abrirModalTelefonos()">
            <i class="ti ti-eye"></i>
            Ver telefonos 
          </button>
        </div>

      </div>
    </div>
  </div>

  </div>




<div class="card">
  <div class="card-body">

  <div class="d-flex align-items-center">
      <div class="ms-auto">
      <div class="dropdown dropstart">
            <a href="javascript:void(0)" class="link text-dark" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="ti ti-dots fs-7"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
              <li>
                <a class="dropdown-item" href="javascript:void(0)" @click="nuevoPrograma()"><i class="ti ti-plus"></i> Agregar</a>
              </li>
              <li>
                <a class="dropdown-item" href="javascript:void(0)" @click="openBuscarModal()"><i class="ti ti-search"></i> Buscar</a>
              </li>
              <li>
                <a class="dropdown-item" :href="pdfUrl"><i class="ti ti-download"></i> Descargar</a>
              </li>
            </ul>
          </div>   
      </div>
  </div>

  <table class="table table-bordered table-sm mt-2 mb-2">
<tr>
<td class="text-center align-middle"><img class="text-center" src="<?= asset('images/logos/Logo.png') ?>" style="width: 200px;"></td>
<td colspan="2" class="text-center align-middle"><b>Programa anual de simulacros</b></td>
<td class="text-center align-middle">Fo.ADMONGAS.016</td>
</tr>
<tr>
<td class="text-center align-middle">Realizado por: Nelly Estrada Garcia </td>
<td class="text-center align-middle">Revisado por: Eduardo Galicia Flores </td>
<td class="text-center align-middle">Autorizado por: Tomas Tarno Quinzaños </td>
<td class="text-center align-middle">Fecha de autorizacion 01/10/2018</td>
</tr>
</table>

  <div class="datatables mt-3">
    <div class="table-responsive">
      <table id="table-programa-simulacro" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>Nombre del simulacro</th>
            <th>Periodicidad</th>
            <th>Fecha</th>
            <th colspan="2"><span class="mb-1 badge rounded-pill text-bg-dark">1</span> Personal que asiste</th>
            <th colspan="2"><span class="mb-1 badge rounded-pill text-bg-dark">2</span> Resumen</th>
            <th colspan="3"><span class="mb-1 badge rounded-pill text-bg-dark">3</span> Evaluación (Fo.ADMONGAS.016a)</th>
          <th class="text-center">
          <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
          </th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
                    
  </div>
</div>


<!--- Modal Protocolo -->

<div class="modal fade"
     id="modalProtocolo"
     tabindex="-1">

    <div class="modal-dialog">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">

                    <span x-show="modoProtocolo == 'create'">
                        Agregar protocolo de respuesta a emergencias
                    </span>

                    <span x-show="modoProtocolo == 'edit'">
                        Editar protocolo de respuesta a emergencias
                    </span>

                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

               <label class="form-label">
                  * Fecha elaboración
              </label>

              <input
                  type="date"
                  class="form-control"
                  x-model="protocolo.fecha"
                  :class="errors.protocolo.fecha ? 'is-invalid' : ''"
                  @input="errors.protocolo.fecha = false">

                  <label class="form-label mt-2">
                      * Archivo PDF
                  </label>

                  <input
                      type="file"
                      class="form-control"
                      accept=".pdf"
                      :class="errors.protocolo.archivo ? 'is-invalid' : ''"
                      @change="
                          protocolo.archivo = $event.target.files[0];
                          errors.protocolo.archivo = false;
                      ">

            </div>

            <div class="modal-footer">

                <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">

                    Cancelar

                </button>

                <button
                    class="btn btn-success"
                    @click="guardarProtocolo()">

                    <span x-show="modoProtocolo == 'create'">
                        Guardar
                    </span>

                    <span x-show="modoProtocolo == 'edit'">
                        Actualizar
                    </span>

                </button>

            </div>

        </div>

    </div>

</div>

<!--- Modal Protocolo Anexos -->
<div
    class="modal fade"
    id="modalAnexos"
    tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">

                    Anexos del protocolo

                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

              <label class="form-label">
                          * Nombre del Anexo:
                      </label>

                        <input
                            type="text"
                            class="form-control"
                            x-model="anexo.nombre"
                            :class="errors.anexo.nombre ? 'is-invalid' : ''"
                            @input="errors.anexo.nombre = false">

                 <label class="form-label mt-2">
                          * Anexo:
                      </label>

                        <input
                            id="archivoAnexo"
                            type="file"
                            class="form-control"
                            accept=".pdf"
                            :class="errors.anexo.archivo ? 'is-invalid' : ''"
                            @change="
                                anexo.archivo = $event.target.files[0];
                                errors.anexo.archivo = false;
                            ">

                <div class="mt-3 text-end">
                  <button
                            class="btn btn-success"
                            @click="guardarAnexo()">

                            Guardar

                        </button>
                </div>

                <hr>

                <table
                    class="table table-sm table-bordered table-hover">

                    <thead>

                        <tr>

                            <th>Nombre</th>
                            <th>Fecha</th>
                            <th
                                width="70"
                                class="text-center">
                                <i class="ti ti-file-type-pdf text-muted fs-7"></i>
                            </th>
                            <th
                                width="70"
                                class="text-center">
                                <i class="ti ti-trash fs-7 text-muted"></i>
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <template
                            x-if="anexos.length === 0">

                            <tr>

                                <td
                                    colspan="4"
                                    class="text-center">

                                    No se encontró información para mostrar

                                </td>

                            </tr>

                        </template>

                        <template
                            x-for="item in anexos"
                            :key="item.id">

                            <tr>

                                <td
                                    x-text="item.nombre_anexo">
                                </td>

                                <td
                                    x-text="item.fecha_formateada">
                                </td>

                                <td
                                    class="text-center">

                                    <a
                                        :href="'/' + item.archivo"
                                        target="_blank">

                                        <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                                    </a>

                                </td>

                                <td
                                    class="text-center">

                                    <a href="javascript:void(0)" @click="eliminarAnexo(item.id)"><i class="ti ti-trash fs-7 text-danger"></i></a>

                                </td>

                            </tr>

                        </template>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<!-- Modal  Teléfonos de emergencias --->
  <div class="modal fade"
         id="modalTelefonosEmergencia"
         tabindex="-1">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <div class="modal-header">

                    <h5 class="modal-title">
                        Teléfonos de emergencias
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <div class="text-end mb-3">

                        <button
                            class="btn btn-info"
                            @click="nuevoTelefono()">

                            <i class="ti ti-plus"></i>
                            Nuevo teléfono

                        </button>

                    </div>

                    <!-- FORMULARIO -->

                    <div
                        class="card border mb-4"
                        x-show="mostrarFormulario"
                        x-transition>

                        <div class="card-body">

                            <h5 class="mb-3">

                                <span x-show="modoTelefono == 'create'">
                                    Nuevo teléfono
                                </span>

                                <span x-show="modoTelefono == 'edit'">
                                    Editar teléfono
                                </span>

                            </h5>

                            <div class="row">

                                <div class="col-md-7">

                                    <label class="form-label">
                                        Servicio de emergencia
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        x-model="telefono.titulo">

                                </div>

                                <div class="col-md-5">

                                    <label class="form-label">
                                        Teléfono
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        x-model="telefono.telefono">

                                </div>

                            </div>

                            <div class="text-end mt-3">

                                <button
                                    class="btn btn-danger"
                                    @click="cancelarTelefono()">

                                    Cancelar

                                </button>

                                <button
                                    class="btn btn-success"
                                    @click="guardarTelefono()">

                                    <span x-show="modoTelefono == 'create'">
                                        Guardar
                                    </span>

                                    <span x-show="modoTelefono == 'edit'">
                                        Actualizar
                                    </span>

                                </button>

                            </div>

                        </div>

                    </div>

                    <!-- TABLA -->

                    <div class="table-responsive">

                        <table class="table table-bordered table-sm table-hover align-middle">

                            <thead>

                                <tr>
                                    <th>Servicio de emergencia</th>
                                    <th>Teléfono</th>
                                    <th class="text-center" width="120">Acciones</th>
                                </tr>

                            </thead>

                            <tbody>

                                <template
                                    x-for="item in telefonos"
                                    :key="item.id">

                                    <tr>

                                        <td>

                                            <span
                                                :class="item.prioridad == 1 ? 'fw-bold' : ''"
                                                x-text="item.titulo">
                                            </span>

                                        </td>

                                        <td>

                                            <a
                                                :href="'tel:'+item.telefono"
                                                x-text="item.telefono">
                                            </a>

                                        </td>

                                        <td class="text-center">
                                        <div class="btn-group btn-group-sm">

                                            <button
                                                class="btn btn-warning"
                                                @click="editarTelefono(item)">
                                                <i class="ti ti-pencil"></i>
                                            </button>

                                            <button
                                                class="btn btn-danger"
                                                @click="eliminarTelefono(item.id,item.titulo)">
                                                <i class="ti ti-trash"></i>
                                            </button>

                                        </div>
                                    </td>

                                    </tr>

                                </template>

                                <tr x-show="telefonos.length == 0">

                                    <td colspan="4" class="text-center">

                                        No hay teléfonos registrados

                                    </td>

                                </tr>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

  </div>

<!-- Modal Programa Anual -->
  <div
    class="modal fade"
    id="modalPrograma"
    tabindex="-1">

    <div class="modal-dialog">

        <div class="modal-content">

            <div class="modal-header">

                <h5
                    class="modal-title"
                    x-text="
                        modoPrograma === 'create'
                        ? 'Crear programa anual de simulacros'
                        : 'Editar programa anual de simulacros'
                    ">
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="mb-3">

                    <label class="form-label">

                        * Nombre del simulacro

                    </label>

                    <textarea
                        class="form-control"
                        rows="3"
                        x-model="programa.nombre_simulacro"
                        :class="errors.programa.nombre_simulacro ? 'is-invalid' : ''"
                        @input="errors.programa.nombre_simulacro = false">
                    </textarea>

                </div>

                <div class="mb-3">

                    <label class="form-label">

                        Periodicidad:

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="Trimestral"
                        disabled>

                </div>

                <div class="mb-3">

                    <label class="form-label">

                        * Fecha

                    </label>

                    <input
                        type="date"
                        class="form-control"
                        x-model="programa.fecha"
                        :class="errors.programa.fecha ? 'is-invalid' : ''"
                        @input="errors.programa.fecha = false">

                </div>

            </div>

            <div class="modal-footer">

                  <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">

                    Cancelar

                </button>

                <button
                    type="button"
                    class="btn btn-success"
                    @click="guardarPrograma()">

                    Guardar

                </button>

            </div>

        </div>

    </div>

</div>

<!-- Modal personal -->
<div
    class="modal fade"
    id="modalPersonal"
    tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">

                    Personal que asiste

                </h5>

                <button
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <label
                    class="form-label">

                    * Nombre completo

                </label>

                <div class="row">

                    <div class="col-md-10">

                        <select
                            id="selectPersonal"
                            multiple
                            class="form-select">

                        </select>

                    </div>

                    <div class="col-md-2">

                        <button
                            class="btn btn-primary w-100"
                            @click="agregarPersonal()">

                            Agregar

                        </button>

                    </div>

                </div>

                <hr>

                <div class="table-responsive">

                    <table
                        class="table table-bordered table-hover table-sm">

                        <thead>

                            <tr>

                                <th>

                                    Nombre completo

                                </th>

                                <th
                                    width="60"
                                    class="text-center">

                                    <i class="ti ti-trash text-muted fs-7"></i>

                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <template
                                x-if="personalAsistente.length === 0">

                                <tr>

                                    <td
                                        colspan="2"
                                        class="text-center">

                                        No se encontró información para mostrar

                                    </td>

                                </tr>

                            </template>

                            <template
                                x-for="item in personalAsistente"
                                :key="item.id">

                                <tr>

                                    <td
                                        x-text="item.nombre">
                                    </td>

                                    <td
                                        class="text-center">

                                        <a
                                        href="javascript:void(0)"
                                            @click="eliminarPersonal(item.id)">

                                            <i class="ti ti-trash text-danger fs-7"></i>

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

</div>

<!-- Modal Resumen -->
<div
    class="modal fade"
    id="modalResumen"
    tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">

                    Resumen

                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <label class="form-label">

                    * Resumen del programa anual de simulacros

                </label>

                <textarea
                    rows="8"
                    class="form-control"
                    x-model="resumenSimulacro.resumen"
                    :class="errors.resumen.resumen ? 'is-invalid' : ''"
                    @input="errors.resumen.resumen = false">
                </textarea>

            </div>

            <div class="modal-footer">

             <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">

                    Cancelar

                </button>

                <button
                    type="button"
                    class="btn btn-success"
                    @click="guardarResumen()">

                    Guardar

                </button>

            </div>

        </div>

    </div>

</div>

<!-- Modal evaluacion -->
<div
    class="modal fade"
    id="modalEvaluacion"
    tabindex="-1">

    <div class="modal-dialog">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">

                    Evaluación

                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <label class="form-label">

                    Adjunta la Evaluación de Simulacro
                    (Fo.ADMONGAS.016a)

                </label>

                <input
                    id="archivoEvaluacion"
                    type="file"
                    accept=".pdf"
                    class="form-control"
                    :class="
                        errors.evaluacion.archivo
                        ? 'is-invalid'
                        : ''
                    "
                    @change="
                        evaluacion.archivo =
                        $event.target.files[0];

                        errors.evaluacion.archivo = false;
                    ">

                <div
                    class="invalid-feedback">

                    Selecciona un archivo PDF

                </div>

            </div>

            <div class="modal-footer">

            <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">

                    Cancelar

                </button>

                <button
                    type="button"
                    class="btn btn-success"
                    @click="guardarEvaluacion()">

                    Guardar

                </button>

            </div>

        </div>

    </div>

</div>

    <!-- MODAL BUSCAR -->
  <div
      class="modal fade"
      id="ModalBuscar"
      tabindex="-1"
      aria-hidden="true">

      <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
              <div class="modal-header rounded-0 head-modal">
                  <h4 class="modal-title">Buscar</h4>

                  <button
                      type="button"
                      class="btn-close"
                      data-bs-dismiss="modal"
                      @click="limpiarBuscar()">
                  </button>

              </div>

              <!-- BODY -->
              <div class="modal-body">

                        <!-- YEAR -->                
                      
                      <label class="form-label mt-2">* Año:</label>
                      <select
                          class="form-control mb-3"
                          x-model="filtro.year"
                          :class="errorsBuscar.year ? 'is-invalid' : ''"
                          @input="errorsBuscar.year = false">

                          <option value="">Selecciona</option>

                          <template x-for="year in years">

                              <option
                                  :value="year"
                                  x-text="year">
                              </option>

                          </template>

                      </select>

                      <!-- MES -->
                      <label class="form-label mt-2">Mes:</label>

                      <select
                          class="form-control"
                          x-model="filtro.mes">

                          <option value="">
                              Todos
                          </option>

                          <option value="1">Enero</option>
                          <option value="2">Febrero</option>
                          <option value="3">Marzo</option>
                          <option value="4">Abril</option>
                          <option value="5">Mayo</option>
                          <option value="6">Junio</option>
                          <option value="7">Julio</option>
                          <option value="8">Agosto</option>
                          <option value="9">Septiembre</option>
                          <option value="10">Octubre</option>
                          <option value="11">Noviembre</option>
                          <option value="12">Diciembre</option>

                      </select>

              </div>

              <!-- FOOTER -->
                <div class="modal-footer">

                  <button
                  class="btn bg-danger-subtle text-danger"
                  data-bs-dismiss="modal"
                  @click="limpiarBuscar()">
                      Cancelar
                  </button>

                  <button
                  class="btn btn-primary"
                  @click="buscar()">
                      Buscar
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
            Bienvenido al elemento 13. PREPARACIÓN Y RESPUESTA A EMERGENCIAS, del Sistema de Administración
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

          <p>
           En este apartado podrás subir, descargar y/o consultar los protocolos de respuesta a emergencias que complementan a los cursos impartidos por el consultor de protección civil. Así como también realizar el registro de los simulacros que se lleven a cabo en la estación de servicio.
          </p>
         
          <hr>

          <label class="fw-bold">Como hacerlo:</label>
          <ul class="list-group list-group-flush">
            <li class="list-group-item">Da clic en el botón de PRE para consultar y/o descargar los protocolos de respuesta emergencias de tu estación (en caso de no contar con ellos sube el archivo en formato PDF para próximas consultas).</li>
            <li class="list-group-item">Planifica tu simulacro con el personal involucrado en las brigadas de atención a emergencias.Deveras designar a personal capacitado que fungirá como evaluador del simulacro que se esta llevando a cabo (Imprimir formato Fo.ADMONGAS.016a).</li>
            <li class="list-group-item">Da clic en el botón agregar para realizar el registro del simulacro y llena los campos se solicitan.</li>
          </ul>

          <hr>

          <label class="fw-bold">Responsables:</label>
          <p>Recuerda que es responsabilidad del <label class="text-danger fw-bold">Representante Técnico</label> (RT) y <label class="text-danger fw-bold">Gerente de la Estación</label> y de quienes conformen las Brigadas de atención a emergencias coordinar los simulacros en fechas y tiempos establecidos.</p>

    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->


</div>