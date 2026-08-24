<div id="container" data-elemento="5" data-herramienta="1">



<div class="row mt-3">

  <div class="col-md-9 col-12">
    <div class="card">
      <div class="card-header bg-primary">
<h4 class="card-title  text-white">Organigrama</h4>
      </div>
<div class="card-body">
    <img src="<?= $organigrama ?? '' ?>" class="w-100" alt="">
    </div>

    </div>

  </div>


  <div class="col-md-3 col-12">
    <div class="card">
      <div class="card-header bg-primary">
<h4 class="card-title text-center text-white">Responsabilidades</h4>

      </div>
      <div class="card-body">
        
        <button type="button" class="btn bg-info-subtle text-info w-100 " data-bs-toggle="modal" data-bs-target="#ModalReTe">Representante Técnico</button>
        <button type="button" class="btn bg-info-subtle text-info w-100 mt-2" data-bs-toggle="modal" data-bs-target="#ModalGerente">Gerente</button>
        <button type="button" class="btn bg-info-subtle text-info w-100 mt-2" data-bs-toggle="modal" data-bs-target="#ModalJefePiso">Jefe de Piso</button>
        <button type="button" class="btn bg-info-subtle text-info w-100 mt-2" data-bs-toggle="modal" data-bs-target="#ModalFacturista">Facturista</button>
        <button type="button" class="btn bg-info-subtle text-info w-100 mt-2" data-bs-toggle="modal" data-bs-target="#ModalDespachador">Despachador</button>
        <button type="button" class="btn bg-info-subtle text-info w-100 mt-2" data-bs-toggle="modal" data-bs-target="#ModalAuxiliar">Auxiliar administrativo</button>
        <button type="button" class="btn bg-info-subtle text-info w-100 mt-2" data-bs-toggle="modal" data-bs-target="#ModalMantenimiento">Mantenimiento</button>
         
      </div>
    </div>
  </div>
</div>

<div class="row mt-3">

<div class="col-md-6">
  <div x-data="{ ...actions(), ...representanteTecnicoForm() }">

    <div class="card">
      <div class="card-header">

      <div class="d-flex align-items-center">
        <h4 class="card-title mb-0">Formato de asignación de representante técnico</h4>
          <div class="ms-auto">
        <?= 
              !empty($permisos['crear']) ? 
              '<button type="button"  class="btn bg-primary-subtle text-primary" href="javascript:void(0)" @click="openNuevo()">
              <i class="ti ti-plus"></i> Nuevo
              </button>' 
              : '' 
            ?>   
          </div>
      </div>

      </div>
      <div class="card-body">

      <div class="datatables">
        <div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
          <table id="table-lista-representante-tecnico" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
            <thead>
              <tr>
              <th>#</th>
              <th>Nombre del representante técnico</th>
              <th>Fecha</th>
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

      <!-- MODAL -->
      <div class="modal fade" id="openNuevoModal">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">
              <h4 class="modal-title text-white"> <i class="ti ti-user-plus ams-2"></i>
              Nuevo representante técnico</h4>
              <button class="btn-close btn-close-white"
              data-bs-dismiss="modal"
              @click="resetModal()"></button>
            </div>

            <div class="modal-body">

              <div class="row">
                <div class="col-md-6">
                  <label class="form-label">* Nombre:</label>
                  <input type="text"
                        class="form-control"
                        x-model="form.nombre"
                        :class="{'is-invalid': errors.nombre}"
                        @input="errors.nombre = false">

                </div>

                <div class="col-md-6">
                  <label class="form-label">* Fecha:</label>
                  <input type="date"
                        class="form-control"
                        x-model="form.fecha"
                        :class="{'is-invalid': errors.fecha}"
                        @input="errors.fecha = false">

                </div>
              </div>

              <label class="form-label mt-3">* Archivo:</label>
              <input type="file"
                class="form-control"
                x-ref="pdfInput"
                @change="handleFile($event)"
                :class="{'is-invalid': errors.pdf}">

            </div>

            <div class="modal-footer">
             <button class="btn bg-danger-subtle text-danger"
              data-bs-dismiss="modal"
              @click="resetModal()"><i class="ti ti-x"></i> Cancelar</button>

              <button class="btn btn-success" @click="submit()" :disabled="loading">
                <i class="ti ti-check"></i>
                <span x-show="!loading">Guardar</span>
                <span x-show="loading">Guardando...</span>
              </button>
            </div>

          </div>
        </div>
      </div>

  </div>
  </div>


<div class="col-md-6">

<div class="card">
  <div class="card-header">
   <div class="float-end">
      <div x-data="{ ...actions(), ...listaasistenciaForm() }">
        <?= 
          !empty($permisos['crear']) ? 
          '<button type="button" class="btn bg-primary-subtle text-primary" @click="crearAsistencia()">
          <i class="ti ti-plus"></i> Nuevo
          </button>' 
          : '' 
        ?>   
      </div>  
    </div>

    <h4 class="card-title mb-0">Fo.ADMONGAS.010 (Registro de la atención y el seguimiento a la comunicación interna y externa.)</h4>
      

  </div>
  <div class="card-body">

 
  <div class="datatables">
     <div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
      <table id="table-lista-asistencia" class="table table-bordered table-striped mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>#</th>
            <th>Fecha</th>
            <th>Estatus</th>
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

</div>

</div>


</div>

<!-- ------------------------- -->
<!-- inicio offcanvas -------- -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasExampleLabel">
            Bienvenido al elemento 5 FUNCIONES, RESPONSABILIDADES Y AUTORIDAD, del Sistema de Administración
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

         <p>
            Aquí vas a poder consultar la estructura orgánica de la empresa, así como las funciones, responsabilidades y autoridad de cada puesto sobre el sistema de Administración.
          </p>
          
          <hr>

          <label class="fw-bold">Como hacerlo:</label>
          <ul class="list-group list-group-flush">
            <li class="list-group-item">Dar clic sobre el puesto para conocer las funciones, responsabilidades y autoridad</li>
          </ul>

          <hr>

          <label class="fw-bold">Responsables:</label>
          <p>Recuerda que es responsabilidad del <span class="text-danger fw-bold">Representante Técnico (RT)</span>, <span class="text-danger fw-bold">Gerente de la Estación</span>, el dar a conocer a cada uno de los puestos sus funciones, responsabilidades dentro del Sistema de Administración. </p>

    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->

<div id="ModalReTe" class="modal fade" tabindex="-1" aria-labelledby="bs-example-modal-md" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
        <div class="modal-content">
              <div class="modal-header modal-colored-header bg-primary text-white">
                    <h4 class="modal-title text-white" id="myModalLabel">
                    <i class="ti ti-user"></i>   
                    Representante Técnico
                    </h4>
                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">

              <table class="table table-bordered mb-0 align-middle">
            <thead>
              <tr>
                <th colspan="3" class="text-center" >Funciones Responsabilidades y autoridad del RT</th>
              </tr>              
              <tr>
                <th class="text-center">Autoridad</th>
                <th class="text-center">Funciones</th>
                <th class="text-center">Responsabilidades</th>
              </tr>
            </thead>
            <tbody >
              <tr>
                <td rowspan="5" class="text-center align-middle">Alta</td>
                <td class="text-center">Asegurar que el SA es conforme con los requisitos establecidos en los lineamientos y demás normativa aplicable.</td>
                <td rowspan="5" class="text-center align-middle">Organizar y
                coordinar las
                actividades que se
                desprendan de
                asuntos
                relacionados con el
                SA</td>
              </tr>
              <tr>
                <td class="text-center">Informar a la alta dirección del Regulado acerca del desempeño del SA.</td>
              </tr>
              <tr>
                <td class="text-center">Proponer la adopción de medidas para aplicar las mejores prácticas nacionales e internacionales en la implementación del SA.</td>
              </tr>
              <tr>
                <td class="text-center">Coordinar y apoyar al resto de las áreas en la definición e implementación de las acciones necesarias para subsanar los incumplimientos de los requisitos del SA.
                </td>
              </tr>
              <tr>
                <td class="text-center">Informar a la Agencia de cualquier situación crítica relativa al proyecto que pudiera poner en riesgo la SISOPA.</td>
              </tr>
            </tbody>
          </table>

                      
              </div>
                   
        </div>
        </div>
    </div>
</div>

<!-- ------------------------- -->

<div id="ModalGerente" class="modal fade" tabindex="-1" aria-labelledby="bs-example-modal-md" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
        <div class="modal-content">
              <div class="modal-header modal-colored-header bg-primary text-white">
                    <h4 class="modal-title text-white" id="myModalLabel">
                    <i class="ti ti-user"></i>    
                    Gerente
                    </h4>
                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">

              <table class="table  mb-0 aling-middle">
            <thead>
              <tr>
                <th colspan="3" class="text-center" >Funciones y responsabilidades del personal</th>
              </tr>              
              <tr>
                <th class="text-center">Autoridad</th>
                <th class="text-center">Funciones</th>
                <th class="text-center">Responsabilidades</th>
              </tr>
            </thead>
            <tbody >
              <tr>
                <td class="text-center align-middle">Media-Alta</td>
                <td class="text-center">Revisar y opinar sobre el SA a
                implementar, Informar y compartir puntos
                de vista sobre el desempeño del SA,
                colaborar activamente para el éxito de la
                implementación del SA, proponer
                opciones para el seguimiento al
                cumplimiento al SA, Coordina las acciones
                necesarias para el cumplimiento de
                hallazgos </td>
                                <td class="text-center align-middle">Organizar y
                                Informar al RT
                cualquier situación
                referente al SA</td>
                              </tr>              
                            </tbody>
                          </table>

                      
              </div>
                   
        </div>
        </div>
    </div>
</div>
<!-- ------------------------- -->
 <div id="ModalJefePiso" class="modal fade" tabindex="-1" aria-labelledby="bs-example-modal-md" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
        <div class="modal-content">
              <div class="modal-header modal-colored-header bg-primary text-white">
                    <h4 class="modal-title text-white" id="myModalLabel">
                        <i class="ti ti-user"></i>
                    Jefe de Piso
                    </h4>
                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">

              <table class="table table-bordered mb-0 aling-middle">
            <thead>
              <tr>
                <th colspan="3" class="text-center" >Funciones y responsabilidades del personal</th>
              </tr>              
              <tr>
                <th class="text-center">Autoridad</th>
                <th class="text-center">Funciones</th>
                <th class="text-center">Responsabilidades</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="text-center align-middle">Media</td>
                <td class="text-center">Revisar el SA, integrarse activamente a la
implementación del SA, transmitir
información a área de despacho</td>
                <td class="text-center align-middle">Informar al gerente
cualquier situación
referente al SA</td>
              </tr>              
            </tbody>
          </table>
                      
              </div>
                   
        </div>
        </div>
    </div>
</div>

<!-- ------------------------- -->
 <div id="ModalFacturista" class="modal fade" tabindex="-1" aria-labelledby="bs-example-modal-md" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
        <div class="modal-content">
              <div class="modal-header modal-colored-header bg-primary text-white">
                    <h4 class="modal-title text-white" id="myModalLabel">
                    <i class="ti ti-user"></i>    
                    Facturista
                    </h4>
                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">

              <table class="table table-bordered  mb-0 align-middle">
            <thead>
              <tr>
                <th colspan="3" class="text-center" >Funciones y responsabilidades del personal</th>
              </tr>              
              <tr>
                <th class="text-center">Autoridad</th>
                <th class="text-center">Funciones</th>
                <th class="text-center">Responsabilidades</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="text-center align-middle">Media</td>
                <td class="text-center">Revisar el SA, integrarse activamente a la
implementación del SA, transmitir
información a área de despacho</td>
                <td class="text-center align-middle">Informar al gerente
cualquier situación
referente al SA</td>
              </tr>              
            </tbody>
          </table>
                      
              </div>
                   
        </div>
        </div>
    </div>
</div>
<!-- ------------------------- -->
 <div id="ModalDespachador" class="modal fade" tabindex="-1" aria-labelledby="bs-example-modal-md" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
        <div class="modal-content">
              <div class="modal-header modal-colored-header bg-primary text-white">
                    <h4 class="modal-title text-white" id="myModalLabel">
                    <i class="ti ti-user"></i>   
                    Despachador
                    </h4>
                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">

            <table class="table table-bordered mb-0 align-middle">
            <thead>
              <tr>
                <th colspan="3" class="text-center" >Funciones y responsabilidades del personal</th>
              </tr>              
              <tr>
                <th class="text-center">Autoridad</th>
                <th class="text-center">Funciones</th>
                <th class="text-center">Responsabilidades</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="text-center align-middle">Baja</td>
                <td class="text-center">Estar informado sobre el SA, participar
activamente el capacitaciones
</td>
                <td class="text-center align-middle">Informar al gerente
cualquier situación
referente al SA</td>
              </tr>              
            </tbody>
          </table>
                      
              </div>
                   
        </div>
        </div>
    </div>
</div>

<!-- ------------------------- -->
 <div id="ModalAuxiliar" class="modal fade" tabindex="-1" aria-labelledby="bs-example-modal-md" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
        <div class="modal-content">
              <div class="modal-header modal-colored-header bg-primary text-white">
                    <h4 class="modal-title text-white" id="myModalLabel">
                    <i class="ti ti-user"></i>    
                    Auxiliar administrativo
                    </h4>
                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">

            <table class="table table-bordered mb-0 align-middle">
            <thead>
              <tr>
                <th colspan="3" class="text-center" >Funciones y responsabilidades del personal</th>
              </tr>              
              <tr>
                <th class="text-center">Autoridad</th>
                <th class="text-center">Funciones</th>
                <th class="text-center">Responsabilidades</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="text-center align-middle">Baja</td>
                <td class="text-center">Estar informado sobre el SA, participar
activamente las capacitaciones
</td>
                <td class="text-center align-middle">Informar al gerente
cualquier situación
referente al SA</td>
              </tr>              
            </tbody>
          </table>
                      
              </div>
                   
        </div>
        </div>
    </div>
</div>
<!-- ------------------------- -->
 <div id="ModalMantenimiento" class="modal fade" tabindex="-1" aria-labelledby="bs-example-modal-md" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
        <div class="modal-content">
              <div class="modal-header modal-colored-header bg-primary text-white">
                    <h4 class="modal-title text-white" id="myModalLabel">
                    <i class="ti ti-user"></i>   
                    Mantenimiento
                    </h4>
                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">

           <table class="table table-bordered mb-0 align-middle">
            <thead>
              <tr>
                <th colspan="3" class="text-center" >Funciones y responsabilidades del personal</th>
              </tr>              
              <tr>
                <th class="text-center">Autoridad</th>
                <th class="text-center">Funciones</th>
                <th class="text-center">Responsabilidades</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="text-center align-middle">Media</td>
                <td class="text-center">Participar de manera activa en la
implementación del SA, opinar y compartir
puntos de vista, proponer opciones de
cumplimiento de hallazgos
</td>
                <td class="text-center align-middle">Informar al gerente
y al RT cualquier
situación referente
al SA</td>
              </tr>              
            </tbody>
          </table>
                      
              </div>
                   
        </div>
        </div>
    </div>
</div>