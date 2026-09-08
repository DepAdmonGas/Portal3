<div id="container" class="pb-4"
data-module-station-key="sasisopa"
data-estacion-id="<?= e($estacionId ?? '') ?>"
x-data="{ ...actions(), ...preparacionEmergencias()}">

<?php if (empty($estacionId)): ?>
 
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

<div class="row mt-4">

  <div class="col-md-8 align-items-stretch">
    <div class="card">
        <div class="card-header">

  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0"> 
        
        Protocolo de respuesta a emergencias
    </h4>
      <div class="ms-auto">
        <button type="button" class="btn bg-primary-subtle text-primary"
        @click="nuevoProtocolo()">
        <i class="ti ti-plus"></i>
         Nuevo
        </button>
      
      </div>
  </div>


        </div>
  <div class="card-body">

<div class="table-responsive">

    <table class="table table-striped table-bordered mb-0 text-nowrap align-middle">

        <thead>

            <tr>

                <th class="">
                    Fecha elaboración
                </th>

                <th
                    class=""
                    width="60">

                    PDF

                </th>

                <th
                    class=""
                    width="60">

                    Anexos

                </th>

                <th class="text-center"><i class="ti ti-dots-vertical fs-6"></i></th>

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

                    <td class="text-center" >

                        <a
                        href="javascript:void(0)"
                            @click="abrirAnexos(item.id)">

                            <i class="ti ti-paperclip fs-6 text-info"></i>

                        </a>

                    </td>

                    <td class="text-center" width="48px">

                        <div class="dropcen">
                            <a href="javascript:void(0)"  id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                        
                    <div class="dropcen">
                        <a href="javascript:void(0)"  id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
             <i class="ti ti-dots-vertical fs-6"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <li>
             <a class="dropdown-item pointer d-flex align-items-center gap-3 " href="javascript:void(0)"  @click="editarProtocolo(item)"><i class="ti ti-edit fs-4"></i> Editar</a>

        </li>
        <li>
             <a class="dropdown-item pointer d-flex align-items-center gap-3 " href="javascript:void(0)"  @click="eliminarProtocolo(item.id)"><i class="ti ti-trash fs-4"></i> Eliminar</a>

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

    <div class="col-md-4 align-items-stretch">
    <div class="card w-100">
        <div class="card-header">
       <h4 class="card-title"> Teléfonos de emergencias </h4>


        </div>
      <div class="card-body">
 
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
      <div class="dropdown dropcenter">
            <a href="javascript:void(0)" class="btn btn-light dropdown-toggle" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
             <i class="ti ti-dots-vertical fs-4"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
              <li>
                <a class="dropdown-item pointer" href="javascript:void(0)" @click="nuevoPrograma()"><i class="ti ti-plus"></i> Agregar</a>
              </li>
              <li>
                <a class="dropdown-item pointer" href="javascript:void(0)" @click="openBuscarModal()"><i class="ti ti-search"></i> Buscar</a>
              </li>
              <li>
                <a class="dropdown-item pointer" :href="pdfUrl"><i class="ti ti-download"></i> Descargar</a>
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

  <div class="datatables">
    <div class="table-responsive pb-4 overflow-x-auto overflow-hidden">
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

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

              <h4 class="modal-title text-white">

    <template x-if="modoProtocolo === 'create'">
        <span class="d-inline-flex align-items-center">
            <i class="ti ti-alert-circle me-2"></i>
            Agregar protocolo de respuesta a emergencias
        </span>
    </template>

    <template x-if="modoProtocolo === 'edit'">
        <span class="d-inline-flex align-items-center">
            <i class="ti ti-edit me-2"></i>
            Editar protocolo de respuesta a emergencias
        </span>
    </template>

</h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

               <label class="form-label">
                  * Fecha elaboración:
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

                    <i class="ti ti-x"></i> Cancelar

                </button>

                <button
                    class="btn btn-success"
                    @click="guardarProtocolo()">
                    <i class="ti ti-check"></i>
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

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
                  <i class="ti ti-paperclip"></i>
                    Anexos del protocolo

                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
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

                <div class="mt-3 mb-3 text-end">
                  <button
                            class="btn btn-success"
                            @click="guardarAnexo()">

                            <i class="ti ti-check"></i> Guardar

                        </button>
                </div>


                <table
                    class="table table-striped table-bordered mb-0 text-nowrap align-middle">

                    <thead>

                        <tr>

                            <th>Nombre:</th>
                            <th>Fecha:</th>
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
<div class="modal-footer">
     <button type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button>
</div>
        </div>

    </div>

</div>

<!-- Modal  Teléfonos de emergencias --->
  <div class="modal fade"
         id="modalTelefonosEmergencia"
         tabindex="-1">

        <div class="modal-dialog modal-lg modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header modal-colored-header bg-primary text-white">

                    <h4 class="modal-title text-white">
                        <i class="ti ti-phone-call"></i>
                        Teléfonos de emergencias
                    </h4>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body pb-0">

                    <div class="text-end ">

                        <button
                            class="btn bg-primary-subtle text-primary"
                            @click="nuevoTelefono()" x-show="!mostrarFormulario">

                            <i class="ti ti-plus"></i>
                            Nuevo

                        </button>

                    </div>

                    <!-- FORMULARIO -->

                    <div
                        x-show="mostrarFormulario"
                        x-transition>

                      
                          <h5 class="mb-3 pt-0 mt-0">

                                <span x-show="modoTelefono == 'create'">
                                    <i class="ti ti-phone-plus"></i>
                                    Nuevo teléfono
                                </span>

                                <span x-show="modoTelefono == 'edit'">
                                    <i class="ti ti-phone-outgoing"></i>
                                    Editar teléfono
                                </span>

                            </h5>
                          

                        <div>

                            

                            <div class="row">

                                <div class="col-md-7">

                                    <label class="form-label">
                                        Servicio de emergencia:
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        x-model="telefono.titulo">

                                </div>

                                <div class="col-md-5">

                                    <label class="form-label">
                                        Teléfono:
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        x-model="telefono.telefono">

                                </div>

                            </div>



                        </div>

                    </div>

                    <!-- TABLA -->

                    <div x-show="!mostrarFormulario">
                    <div class="table-responsive mt-3 overflow-x-auto overflow-hidden">

                        <table class="table table-bordered table-striped align-middle">

                            <thead>

                                <tr>
                                    <th>Servicio de emergencia</th>
                                    <th>Teléfono</th>
                                    <th class="text-center" width="120"><i class="ti ti-edit fs-7 text-primary"></i></th>
                                    <th class="text-center" width="120"><i class="ti ti-trash fs-7 text-danger"></i> </th>
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

                                            <span
                                                @click="editarTelefono(item)">
                                                <i class="ti ti-edit fs-7 text-primary pointer"></i>
                                            </span>
                                              </td>


                                              <td class="text-center">
                                                <span
                                                @click="eliminarTelefono(item.id,item.titulo)">
                                                <i class="ti ti-trash fs-7 text-danger pointer"></i>
                                            </span>
</td>
                                            
                                        
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


                   <div class="modal-footer" >

                <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal" x-show="!mostrarFormulario"
                        x-transition>

                    <i class="ti ti-x"></i> Cerrar
                </button>


                            <div x-show="mostrarFormulario"
                        x-transition>

                                <button
                                    class="btn bg-danger-subtle text-danger"
                                    @click="cancelarTelefono()">

                                    <i class="ti ti-x"></i> Cancelar

                                </button>

                                <button
                                    class="btn btn-success"
                                    @click="guardarTelefono()">

                                    <i class="ti ti-check"></i>

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

        </div>

  </div>

<!-- Modal Programa Anual -->
  <div
    class="modal fade"
    id="modalPrograma"
    tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
                    <i class="ti" :class="modoPrograma ==='create' ? 'ti-calendar-plus' : 'ti-edit'"></i>
                   <span 
                    x-text="modoPrograma === 'create'
                        ? 'Nuevo programa anual de simulacros'
                        : 'Editar programa anual de simulacros'"></span>
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

                        * Nombre del simulacro:

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

                        * Periodicidad:

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="Trimestral"
                        disabled>

                </div>

                <div class="mb-3">

                    <label class="form-label">

                        * Fecha:

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
<i class="ti ti-x"></i>
                    Cancelar

                </button>

                <button
                    type="button"
                    class="btn btn-success"
                    @click="guardarPrograma()">
<i class="ti ti-check"></i>
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

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
                   <i class="ti ti-user"></i>
                    Personal que asiste

                </h4>

                <button
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

           
                <label
                    class="form-label">

                    * Nombre del personal:

                </label>



           
<div class="input-group mb-3 rounded overflow-hidden"
     style="display: flex; flex-wrap: nowrap;">

    <select class="form-select"
            id="selectPersonal"
            multiple
            style="min-width: 0;">
    </select>

    <button type="button"
            class="btn btn-success"
            style="flex-shrink: 0;"
            @click="agregarPersonal()">
        <i class="ti ti-check"></i> Agregar
    </button>

</div>
                



                <div class="table-responsive overflow-x-auto overflow-hidden">

                    <table
                        class="table table-striped table-bordered mb-0 text-nowrap align-middle">

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
<div class="modal-footer">
      <button type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button>
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

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
                  <i class="ti ti-file-description"></i>
                    Resumen

                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <label class="form-label">

                    * Resumen del programa anual de simulacros:

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

                    <i class="ti ti-x"></i> Cancelar

                </button>

                <button
                    type="button"
                    class="btn btn-success"
                    @click="guardarResumen()">

                    <i class="ti ti-check"></i> Actualizar

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

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
   <i class="ti ti-file-check"></i>
                    Evaluación

                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <label class="form-label">

                    * Adjunta la Evaluación de Simulacro
                    (Fo.ADMONGAS.016a):

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

                    <i class="ti ti-x"></i> Cancelar

                </button>

                <button
                    type="button"
                    class="btn btn-success"
                    @click="guardarEvaluacion()">

                    <i class="ti ti-check"></i> Guardar

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
              <div class="modal-header modal-colored-header bg-primary text-white">
                  <h4 class="modal-title text-white">
                  <i class="ti ti-search"></i>  
                  Buscar
                </h4>
                  <button
                      type="button"
                      class="btn-close btn-close-white"
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

                          <option value="">Selecciona una opción...</option>

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
                    <i class="ti ti-x"></i> Cancelar
                  </button>

                  <button
                  class="btn btn-success"
                  @click="buscar()">
                    <i class="ti ti-search"></i> Buscar
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

<?php endif; ?>

</div>