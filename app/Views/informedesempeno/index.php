<div id="container" class="pb-4"
x-data="{ ...actions(), ...informesDesempeno()}">

<div class="card mt-4">
    <div class="card-header">
<div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Informe de Evaluación de Desempeño (IED)</h4>
      <div class="ms-auto">
     <div class="dropdown center">
            <a href="javascript:void(0)" class="btn btn-light dropdown-toggle text-dark" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="ti ti-dots-vertical fs-4"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
              <li>
                <a class="dropdown-item pointer" href="javascript:void(0)" @click="openModalEvaluacion()"><i class="ti ti-plus"></i> Nuevo</a>
              </li>
              <li>
                <a class="dropdown-item pointer" href="/uploads/archivos/Fo.ADMONGAS/Fo.ADMONGAS.028.docx"><i class="ti ti-download"></i> Descargar</a>
              </li>
            </ul>
          </div>   
      </div>
  </div>
    </div>
  <div class="card-body">

<table class="table table-responsive table-striped table-bordered mb-0  align-middle">

        <thead>

            <tr>
                <th class="text-center align-middle">
                    #
                </th>
                <th class="text-center align-middle">
                    Fecha
                </th>
                <th class="text-center align-middle">
                    Nombre completo
                </th>
                <th
                    width="35"
                    class="text-center align-middle">
                    <i class="fas fa-ellipsis-v"></i>
                </th>
            </tr>
        </thead>
        <tbody>
            <template
                x-if="evaluaciones.length === 0">
                <tr>
                    <td
                        colspan="4"
                        class="text-center">
                        <small>
                            No se encontró información
                        </small>
                    </td>
                </tr>
            </template>
            <template
                x-for="item in evaluaciones"
                :key="item.id">
                <tr>
                    <td
                        class="text-center fw-bolder"
                        x-text="item.id">
                    </td>
                    <td
                        class="text-center"
                        x-text="item.fecha_larga">
                    </td>
                    <td
                        class="text-center"
                        x-text="item.usuario">
                    </td>
                    <td
                        class="text-center align-middle">

                        <div class="dropdown dropstart">
                    <a href="javascript:void(0)" data-bs-toggle="dropdown">
                         <i class="ti ti-dots-vertical fs-6"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item pointer d-flex align-items-center gap-3"
                            @click="editarEvaluacion(item)">
                            <i class="fs-4 ti ti-edit"></i>Editar
                            </a>
                            </li>
                            <li>
                            <a class="dropdown-item pointer d-flex align-items-center gap-3"
                            :href="item.archivo" download>
                            <i class="fs-4 ti ti-download"></i>Descargar
                            </a>
                            </li>
                            <li>
                            <a href="javascript:void(0)" class="dropdown-item pointer d-flex align-items-center gap-3"
                            @click="eliminarEvaluacion(item.id)">
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

<div class="card">
    <div class="card-header">
    <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Control de la implementación de los procedimientos del SASISOPA (Fo.ADMONGAS.029)</h4>
      <div class="ms-auto">

              <button class="btn bg-primary-subtle text-primary">
                <a class="dropdown-item pointer" href="javascript:void(0)" @click="createImplementacion()"><i class="ti ti-plus"></i> Nuevo</a>
              </button>
  
 
      </div>
  </div>
    </div>
  <div class="card-body">
      <table class="table table-responsive table-striped table-bordered mb-0  align-middle">
        <thead>
          <tr>
           <th class="text-center">#</th>
            <th class="text-center">Fecha</th>
            <th class="text-center">Nombre completo</th>
          <th class="text-center">
          <a><i class="ti ti-dots-vertical fs-6"></i></a>
          </th>
          </tr>
        </thead>
        <tbody>
            <template
                x-if="implementaciones.length === 0">
                <tr>
                    <td
                        colspan="4"
                        class="text-center">
                        <small>
                            No se encontró información para mostrar
                        </small>
                    </td>
                </tr>
            </template>
            <template
                x-for="item in implementaciones"
                :key="item.id">
                <tr>
                    <td
                        class="text-center fw-bolder"
                        x-text="item.id">
                    </td>
                    <td
                        class="text-center"
                        x-text="item.fecha_larga">
                    </td>
                    <td
                        class="text-center"
                        x-text="item.usuario">
                    </td>
                    <td
                        class="text-center align-middle">

                        <div class="dropdown dropstart">
                    <a href="javascript:void(0)" data-bs-toggle="dropdown">
                         <i class="ti ti-dots-vertical fs-6"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item pointer d-flex align-items-center gap-3"
                            href="javascript:void(0)"
                            @click="verImplementacion(item.id)">
                            <i class="fs-4 ti ti-eye"></i>Detalle
                            </a>
                            </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-3"
                            :href="`/sasisopa/informes-desempeno/implementacion/pdf/${item.id}`" download>
                            <i class="fs-4 ti ti-download"></i>Descargar
                            </a>
                            </li>
                        <li>
                            <a class="dropdown-item pointer d-flex align-items-center gap-3" 
                            href="javascript:void(0)"
                            @click="editarImplementacion(item.id)">
                            <i class="fs-4 ti ti-edit"></i>Editar
                            </a>
                            </li>
                            <li>
                            <a href="javascript:void(0)" class="dropdown-item pointer d-flex align-items-center gap-3"
                            @click="eliminarImplementacion(item.id)">
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

<!-- Modal Informe de Evaluación de Desempeño (IED) -->
<div
    class="modal fade"
    id="modalEvaluacion"
    tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-colored-header bg-primary text-white">
                <h4 class="modal-title text-white">
<i class="ti" :class="modoEvaluacion=== 'create' ? 'ti-clipboard-plus' :'ti-edit'"></i>
                    <span
                        x-text="
                            modoEvaluacion === 'create'
                                ? 'Nuevo Fo.ADMONGAS.028'
                                : 'Editar Fo.ADMONGAS.028'
                        ">
                    </span>

                </h4>

               <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="mb-3">

                    <label class="form-label fw-bolder">
                        * Fecha:
                    </label>

                    <input
                        type="date"
                        class="form-control"
                        x-model="evaluacion.fecha"
                        :class="errors.fecha ? 'is-invalid' : ''"
                        @input="errors.fecha = false">

                </div>

                <div class="mb-3">

                    <label class="form-label fw-bolder">

                         Revisión de resultados en formato PDF:

                    </label>

                    <input
                    id="archivo"
                        type="file"
                        accept=".pdf"
                        class="form-control"
                        @change="
                            evaluacion.archivo =
                            $event.target.files[0]
                        ">

                </div>

                
            </div>

            <div class="modal-footer">

               <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">

                    <i class="ti ti-x"></i> Cancelar

                </button>
<template
                    x-if="
                        modoEvaluacion === 'edit'
                        && evaluacion.archivo_actual
                    ">

                    <div>

                        <a class="btn bg-primary-subtle text-primary"
                            :href="`${evaluacion.archivo_actual}`"
                            target="_blank">

                            <i class="ti ti-file-type-pdf text-danger fs-6"></i>

                            Ver archivo actual

                        </a>

                    </div>

                </template>


                <button
                    class="btn btn-success"
                    @click="guardarEvaluacion()">
                    
                    <i class="ti ti-check"></i>
                    <span
                        x-text="
                            modoEvaluacion === 'create'
                                ? 'Guardar'
                                : 'Actualizar'
                        ">
                    </span>

                </button>

            </div>

        </div>

    </div>

</div>

<!-- Modal detalle implementacion -->
<div
    class="modal fade"
    id="modalDetalleImplementacion"
    tabindex="-1">

    <div class="modal-dialog modal-fullscreen">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
                    <i class="ti ti-eye"></i>
                    Detalle de la implementación de los procedimientos del SASISOPA
                </h4>

                <button
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <table class="table table-bordered table-sm">

                    <tr>

                        <td class="text-center align-middle" width="220">

                            <img
                                src="<?=asset('images/logos/Logo.png')?>"
                                width="180">

                        </td>

                        <td
                            colspan="2"
                            class="text-center align-middle">

                            <b>
                                Control de la implementación de los procedimientos del SASISOPA
                            </b>

                        </td>

                        <td class="text-center align-middle">

                            Fo.ADMONGAS.029

                        </td>

                    </tr>

                    <tr>

                        <td class="text-center align-middle">

                            Realizado por:
                            Nelly Estrada García

                        </td>

                        <td class="text-center align-middle">

                            Revisado por:
                            Eduardo Galicia Flores

                        </td>

                        <td class="text-center align-middle">

                            Autorizado por:
                            Tomas Tarno Quinzaños

                        </td>

                        <td class="text-center align-middle">

                            01/10/2018

                        </td>

                    </tr>

                </table>

                <table class="table table-responsive table-striped table-bordered mb-0  align-middle">

                    <thead>

                        <tr>

                            <th class="text-center align-middle">
                                Fecha de implementación
                            </th>

                            <th class="text-center align-middle">
                                Nombre del procedimiento
                            </th>

                            <th class="text-center align-middle">
                                Breve descripción de la implementación
                            </th>

                            <th class="text-center align-middle">
                                <div class="border-bottom pb-1">Se dio a conocer la implementación</div>
                                <div><label class="border-right pr-3 pl-2">Si</label>   / <label class="pl-2 pr-2">No</label></div>
                            </th>

                            <th class="text-center align-middle">
                                Puestos de personal enterados de la implementación 	
                            </th>

                            <th class="text-center align-middle">
                                Observaciones
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <template
                            x-for="item in detalleImplementacion"
                            :key="item.id">

                            <tr>

                                <td
                                    class="text-center"
                                    x-text="item.fecha_implementacion_larga">
                                </td>

                                <td>

                                    <b
                                        x-text="item.procedimiento">
                                    </b>

                                </td>

                                <td
                                    x-text="item.descripcion">
                                </td>

                                <td
                                    class="text-center"
                                    x-text="item.informacion">
                                </td>

                                <td>

                                    <template
                                        x-for="puesto in item.puestos"
                                        :key="puesto.id">

                                        <span
                                            class="badge bg-info me-1 mb-1"
                                            x-text="puesto.puesto">
                                        </span>

                                    </template>

                                </td>

                                <td
                                    x-text="item.observaciones">
                                </td>

                            </tr>

                        </template>

                        <template
                            x-if="detalleImplementacion.length==0">

                            <tr>

                                <td
                                    colspan="6"
                                    class="text-center">

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

                    <i class="ti ti-x"></i> Cancelar

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
            Bienvenido al elemento 18. INFORMES DE DESEMPEÑO, del Sistema de Administración
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

          <p>
            En este apartado se debe realizar el registro de la implementación real de los procedimientos técnicos y administrativos del sistema de administración, así como también generar el informe de evaluación del  desempeño que se ingresara a la Agencia de Seguridad, Energía y Ambiente en los primeros tres meses de cada año, siempre y cuando el sistema de administración haya sido aprobado por dicha agencia.
          </p>
         
          <hr>

          <label class="fw-bold">Como hacerlo:</label>
          <ul class="list-group list-group-flush">
            <li class="list-group-item">Da clic en el icono descargar formato <b>Fo.ADMONGAS.028</b> para llenar el Informe de <b>Evaluación de Desempeño (IED)</b>.</li>
            <li class="list-group-item">Lee detenidamente cada uno de los puntos y realiza lo que se te indica.</li>
            <li class="list-group-item">Podrán ser utilizadas imágenes para detallar de manera mas precisa cada uno de los puntos.</li>
            <li class="list-group-item">Dicho informe debe ser firmado por el representante legal y deberá ser enviado en original a la agencia (<b>Periferico Sur 4209, Jardines en la Montaña, Tlalpan, 14210 Ciudad de México, CDMX</b>) en los primeros tres meses de cada año.</li>
            <li class="list-group-item">Escanea y sube tu acuse de ingreso en formato PDF (Subir documento completo).</li>
            <li class="list-group-item">En el formato <b>Fo.ADMONGAS.029</b> da clic en agregar para realizar el registro de la implementación real de los procedimientos del sistema de administración.</li>
          </ul>

          <hr>

          <label class="fw-bold" >Responsables:</label>
          <p>Recuerda que es responsabilidad del <label class="text-danger fw-bold">Representante Técnico</label> (RT), <label class="text-danger fw-bold">Gerente de la Estación</label>, generar el informe de evaluación de desempeño de manera anual así como los registros del presente procedimiento.</p>

    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->

</div>