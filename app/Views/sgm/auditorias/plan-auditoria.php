<div
    x-data="{...actions(), ...planauditoria(<?= $id ?>)}">

        <div class="text-end">
            <button class="btn btn-success mt-3" @click="finalizar"><i class="ti ti-check"></i> Finalizar Auditoria</button>
        </div>


    <div class="bg-white  mt-3">

        <!-- PLAN -->
<div class="card mb-4">
    <div class="card-header bg-primary">
                    <span class="card-title text-white">
                        I. DATOS GENERALES DEL PERMISIONARIO
                    </span>
                
    </div>

<div class="card-body p-0">
    <div class="table-responsive">
<table class="table table-striped table-bordered text-nowrap align-middle mb-0">

            <tbody>
                <tr>

                    <td class="bg-light text-center fw-bolder">
Nombre, denominación o razón social                    </td>

                    <td class="bg-light text-center fw-bolder">
                        Permiso CRE
                    </td>

                    <td class="bg-light text-center fw-bolder">
                        Fecha de elaboración
                    </td>

                </tr>

                <tr>

                    <td class="text-center">
                        <span x-text="plan.razon_social"></span>
                    </td>

                    <td class="text-center">
                        <span x-text="plan.permiso_cre"></span>
                    </td>

                    <td class="p-0">

                        <input
                            type="date"
                            class="form-control border-0 text-center"
                            x-model="plan.fecha"
                            @change="editar('fecha')">

                    </td>

                </tr>

                <tr>

                    <td class="bg-light fw-bolder text-center">
                        Nombre del director (alta dirección):
                    </td>

                    <td colspan="2" class="p-0">

                        <input
                            type="text"
                            class="form-control border-0 text-center"
                            x-model="plan.nom_director"
                            @change="editar('nom_director')">

                    </td>

                </tr>

                <tr>

                    <td class="bg-light fw-bolder align-middle text-center">
                        Nombre del(los) responsable del sgm:
                    </td>

                    <td colspan="2" class="p-0">

                        <select
                            class="form-select border rounded-0 text-center "
                            x-model="usuarioResponsable"
                            @change="agregarResponsable()">
                            <option value="">Seleccione una opcion...</option>

                            <template
                                x-for="usuario in usuariosDisponibles"
                                :key="usuario.id">
                                <option
                                    :value="usuario.id"
                                    x-text="usuario.nombre"></option>
                            </template>
                        </select>

                        <ul>

                            <template
                                x-for="responsable in responsables"
                                :key="responsable.id">
                                <li
                                    class="list-group-item d-flex justify-content-between align-items-center p-2">

                                    <span 
                                    class="flex-grow-1 text-center"
                                    x-text="responsable.nombre"></span>

                                    <a
                                        href="javascript:void(0)"
                                        class="pointer"
                                        @click="eliminarResponsable(responsable.id)"
                                        title="Eliminar responsable">
                                        <i class="ti ti-trash fs-6 text-danger"></i>
                                    </a>

                                </li>
                            </template>

                        </ul>

                    </td>

                </tr>

                <tr>

                    <td class="bg-light fw-bolder text-center">
                        Ubicación de la instalación:
                    </td>

                    <td colspan="2" class="p-0">

                        <input
                            type="text"
                            class="form-control border-0 text-center"
                            x-model="plan.ubicacion_instalacion"
                            @change="editar('ubicacion_instalacion')">

                    </td>

                </tr>

            </tbody>

        </table>
    </div>
 
</div>
      

</div>
 

                
<!---------termina la primera card----->

        <!-- AUDITORES -->
<div class="card mb-4">

    <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                    <span class="card-title text-white">
                        II. DATOS DEL AUDITOR
                    </span>

            

   <button
                type="button"
                class="btn bg-success text-white"
                @click="abrirAuditor()">
                <i class="ti ti-plus"></i>
                Nuevo auditor
            </button>
    </div>
 


<div class="card-body p-0">
    <div class="table-responsive">
<table class="table table-striped table-bordered text-nowrap align-middle mb-0">

            <tbody>

                

                <tr>

                    <td class="fw-bolder bg-light text-center">
                        Equipo auditor
                    </td>

                    <td class="fw-bolder bg-light text-center">
                        Nombre del auditor
                    </td>

                    <td class="fw-bolder bg-light text-center">
                        Área/proceso/actividad que audita
                    </td>

                    <td class="text-center bg-light" width="48px"><i class="ti ti-trash fs-6 text-danger"></i></td>

                </tr>

                <template
                    x-for="auditor in auditores"
                    :key="auditor.id">

                    <tr>

                        <td class="align-middle text-center" x-text="auditor.categoria"></td>

                        <td class="align-middle text-center" x-text="auditor.nombre"></td>

                        <td class="align-middle text-center" x-text="auditor.area_actividad"></td>

                        <td class="text-center">

                            <a
                                class="pointer"
                                @click="eliminarAuditor(auditor.id)">
                                <i class="ti ti-trash fs-6 text-danger"></i>
                            </a>

                        </td>

                    </tr>

                </template>

                <template x-if="auditores.length === 0">

                    <tr>

                        <td
                            colspan="4"
                            class="text-center text-muted">
                            No hay auditores registrados.
                        </td>

                    </tr>

                </template>

            </tbody>

        </table>
    </div>
  
</div>  
</div>
      

<!-------termina la segunda card----->



        <!-- AUXILIARES -->
         <div class="card mb-4">
            <div class="card-header bg-primary d-flex justify-content-between align-items-center">


                    <span class="card-title text-white">
                            III. DATOS DEL EQUIPO AUXILIAR DEL AUDITOR
                        
                    </span>

              


            <button
                type="button"
                class="btn bg-success text-white"
                @click="abrirAuxiliar()">
                <i class="ti ti-plus"></i>
                Nuevo auxiliar
            </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
<table class="table table-striped table-bordered text-nowrap align-middle mb-0">

            <tbody>

                <tr>

                    <td class="fw-bolder bg-light text-center">
                        Equipo auditor
                    </td>

                    <td class="fw-bolder bg-light text-center">
                        Nombre del auxiliar
                    </td>

                    <td class="text-center bg-light" width="48px"><i class="ti ti-trash fs-6 text-danger"></i></td>

                </tr>

                <template
                    x-for="auxiliar in auxiliares"
                    :key="auxiliar.id">

                    <tr>

                        <td 
                        class="text-center"
                        x-text="auxiliar.categoria"></td>

                        <td 
                        class="text-center"
                        x-text="auxiliar.nombre"></td>

                        <td class="text-center">

                            <a
                                class="pointer"
                                @click="eliminarAuxiliar(auxiliar.id)">
                                <i class="ti ti-trash fs-6 text-danger">
                                    </button>

                        </td>

                    </tr>

                </template>

                <template x-if="auxiliares.length === 0">

                    <tr>

                        <td
                            colspan="3"
                            class="text-center text-muted">

                            No hay auxiliares registrados.

                        </td>

                    </tr>

                </template>

            </tbody>

        </table>
                </div>
  
            </div>
         </div>


<!--------termina la tercera card----->



        <!-- AUDITORÍA -->
<div class="card mb-4">
    <div class="card-header bg-primary">


                    <span class="card-title text-white">
                      IV. AUDITORÍA
                    </span>

          
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
<table class="table table-striped table-bordered text-nowrap align-middle mb-0">

            <tbody>

               

                <tr>
                    <td colspan="3" class="bg-light fw-bolder">
                        Objetivos de la auditoría:
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="p-0">

                        <textarea
                            class="form-control border-0"
                            x-model="plan.objetivo_auditoria"
                            @change="editar('objetivo_auditoria')"></textarea>

                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="bg-light fw-bolder">
                        Alcance de la auditoría:
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="p-0">

                        <textarea
                            class="form-control border-0"
                            x-model="plan.alcance_auditoria"
                            @change="editar('alcance_auditoria')"></textarea>

                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="bg-light fw-bolder">
                        Fecha programada de auditoría:
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="p-0">

                        <input
                            type="date"
                            class="form-control border-0"
                            x-model="plan.fecha_programada"
                            @change="editar('fecha_programada')">

                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="bg-light fw-bolder">
                        Sitio:
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="p-0">

                        <input
                            type="text"
                            class="form-control border-0"
                            x-model="plan.sitio"
                            @change="editar('sitio')">

                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="bg-light fw-bolder">
                        Métodos de auditoría:
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="p-0">

                        <textarea
                            class="form-control border-0"
                            x-model="plan.metodo_auditoria"
                            @change="editar('metodo_auditoria')"></textarea>

                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="bg-light fw-bolder">
                        Ajustes al plan:
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="p-0">

                        <textarea
                            class="form-control border-0"
                            x-model="plan.ajuste_plan"
                            @change="editar('ajuste_plan')"></textarea>

                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="bg-light fw-bolder">
                        Asignación de recursos apropiados:
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="p-0">

                        <textarea
                            class="form-control border-0"
                            x-model="plan.asignacion_recursos"
                            @change="editar('asignacion_recursos')"></textarea>

                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="bg-light fw-bolder">
                        Preparativos logísticos y de comunicaciones:
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="p-0">

                        <textarea
                            class="form-control border-0"
                            x-model="plan.preparativos_logisticos"
                            @change="editar('preparativos_logisticos')"></textarea>

                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="bg-light fw-bolder">
                        Acciones de seguimiento:
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="p-0">

                        <textarea
                            class="form-control border-0"
                            x-model="plan.acciones"
                            @change="editar('acciones')"></textarea>

                    </td>
                </tr>

            </tbody>

        </table>
        </div>

    </div>
</div>
        
        <!-------aqui termina la cuarta card---->




        <!-- AGENDA -->
<div class="card mb-4">
    <div class="card-header bg-primary d-flex justify-content-between align-items-center">
<div class="d-flex flex-column">
    <span class="card-title text-white pb-0 mb-0">               
    V. Agenda
    </span>
    <small class="text-white">Nota: Elaborar una agenda para cada sitio a ser auditado.</small>
    
</div>
 <button
                type="button"
                class="btn bg-success text-white"
                @click="abrirAgenda()">
                <i class="ti ti-plus"></i>
                Nueva agenda
            </button>

      
    </div>
    <div class="card-body p-0">

<div class="table-responsive">
        <table class="table table-striped table-bordered  align-middle mb-0">

            <thead>

               

                <tr>

                    <th class="text-center align-middle bg-light">
                        Horario
                    </th>

                    <th class="text-center align-middle bg-light">
                        Proceso
                    </th>

                    <th class="text-center align-middle bg-light">
                        Elemento del sistema de gestion de meición
                    </th>

                    <th class="text-center align-middle bg-light">
                        Nombre y rol del auditor
                    </th>

                    <th class="text-center align-middle bg-light">
                        Guía
                    </th>

                    <th
                        class="text-center align-middle bg-light"
                        width="48px">
                        <i class="ti ti-trash fs-5 text-danger"></i>
                    </th>

                </tr>

            </thead>


            <tbody>

                <template
                    x-for="item in agenda"
                    :key="item.id">

                    <tr>

                        <td
                            class="text-center align-middle fw-bold">

                            De
                            <span x-text="item.hora_inicio"></span>
                            a
                            <span x-text="item.hora_termino"></span>

                        </td>


                        <td
                            class="text-center align-middle"
                            x-text="item.proceso">
                        </td>


                        <td class="text-center align-middle" x-text="item.elemento_sistema"></td>


                        <td
                            class="text-center align-middle"
                            x-text="item.nombre_rol">
                        </td>


                        <td
                            class="text-center align-middle"
                            x-text="item.guia">
                        </td>


                        <td class="text-center align-middle">

                            <a
                                href="javascript:void(0)"
                                class="pointer"
                                title="Eliminar agenda"
                                @click="eliminarAgenda(item.id)">

                                <i class="ti ti-trash fs-5 text-danger"></i>

                            </a>

                        </td>

                    </tr>

                </template>


                <template x-if="agenda.length === 0">

                    <tr>

                        <td
                            colspan="6"
                            class="text-center text-muted">

                            <small>
                                No se encontró información
                            </small>

                        </td>

                    </tr>

                </template>

            </tbody>

        </table>
</div>

    </div>
</div>
        
<!---------aqio termina la quinta y ultima card------->
    
    </div>

    <!-- Modal -->
    <div
        class="modal fade"
        id="modalPrincipal"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">




                <!-- AUDITOR -->

                <template x-if="modalTipo === 'auditor'">

                    <div>

                        <div class="modal-header bg-primary head-modal">

                            <h4 class="modal-title text-white">
                                II.  Nuevo auditor
                            </h4>

                            <button
                                class="btn-close btn-close-white"
                                data-bs-dismiss="modal">
                            </button>

                        </div>


                        <div class="modal-body">

                            <div class="mb-2">

                                <label class="form-label mb-1">
                                    * Equipo auditor:
                                </label>

                                <select
                                    class="form-select mb-3"
                                    x-model="formAuditor.categoria"
                                    @change="errors.categoria = false"
                                    :class="errors.categoria ? 'is-invalid' : ''">

                                    <option value="">
                                        Selecciona una opcion...
                                    </option>

                                    <option value="AUDITOR LÍDER">
                                        AUDITOR LÍDER
                                    </option>

                                    <option value="AUDITOR">
                                        AUDITOR
                                    </option>

                                </select>

                            </div>


                            <div class="mb-2">

                                <label class="form-label mb-2">
                                    * Nombre del auditor:
                                </label>

                                <input
                                    type="text"
                                    class="form-control mb-3"
                                    x-model="formAuditor.nombre"
                                    @input="limpiarAuditorInterno()"
                                    @change="errors.nombre = false"
                                    :disabled="tieneAuditorInterno"
                                    :class="errors.nombre ? 'is-invalid' : ''">

                            </div>

                            <div class="mb-2">

                                <label class="form-label mb-1">
                                    * Nombre (Auditor Interno):
                                </label>

                                <select
                                    class="form-select mb-3"
                                    x-model="formAuditor.auditorInterno"
                                    @change="limpiarNombreAuditor()"
                                    :disabled="tieneNombreAuditor">

                                    <option value="">
                                        Seleccione una opcion...
                                    </option>

                                    <template
                                        x-for="auditor in usuarios"
                                        :key="auditor.id">
                                        <option
                                            :value="auditor.id"
                                            x-text="auditor.nombre"></option>
                                    </template>

                                </select>

                            </div>



                                <label class="form-label mb-1">
                                    * Área/proceso/actividad que audita:
                                </label>

                                <textarea
                                    class="form-control mb-3"
                                    x-model="formAuditor.area_actividad"
                                    @change="errors.area_actividad = false"
                                    :class="errors.area_actividad ? 'is-invalid' : ''">
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
                                @click="guardarAuditor">
                                <i class="ti ti-check"></i>

                                Guardar

                            </button>

                        </div>

                    </div>

                </template>


                <!-- AUXILIAR -->

                <template x-if="modalTipo === 'auxiliar'">

                    <div>

                        <div class="modal-header bg-primary head-modal">

                            <h5 class="modal-title text-white">
                                III. Nuevo auxiliar
                            </h5>

                            <button
                                class="btn-close btn-close-white"
                                data-bs-dismiss="modal">
                            </button>

                        </div>


                        <div class="modal-body">

                          

                                <label class="form-label mb-1">
                                    * Equipo auditor:
                                </label>

                                <select
                                    class="form-select mb-3"
                                    x-model="formAuxiliar.categoria"
                                    @change="errors.categoria = false"
                                    :class="errors.categoria ? 'is-invalid' : ''">

                                    <option value="">
                                        Selecciona una opcion...
                                    </option>

                                    <option value="GUÍAS">
                                        GUÍAS
                                    </option>

                                    <option value="OBSERVADORES">
                                        OBSERVADORES
                                    </option>

                                    <option value="EXPERTO(S) TÉCNICO(S)">
                                        EXPERTO(S) TÉCNICO(S)
                                    </option>

                                </select>

                        



                                <label class="form-label mb-1">
                                    * Nombre del auxiliar:
                                </label>

                                <textarea
                                    class=" form-control mb-3"
                                    x-model="formAuxiliar.nombre"
                                    @change="errors.nombre = false"
                                    :class="errors.nombre ? 'is-invalid' : ''">
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
                                @click="guardarAuxiliar">
                                <i class="ti ti-check"></i>

                                Guardar

                            </button>

                        </div>

                    </div>

                </template>

            </div>

        </div>

    </div>

    <!-- Modal Agenda -->
    <div
        class="modal fade"
        id="modalAgenda"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <div class="modal-header bg-primary head-modal">

                    <h5 class="modal-title text-white">
                       
                         V. Nueva agenda
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>


                <div class="modal-body">

                    <div class="row">

                        <div class="col-md-6">

                            <label class="form-label mb-1">
                                * Hora inicio:
                            </label>

                            <input
                                type="time"
                                class="form-control mb-3"
                                x-model="formAgenda.hora_inicio"
                                @change="errors.hora_inicio = false"
                                :class="errors.hora_inicio ? 'is-invalid' : ''">

                        </div>


                        <div class="col-md-6">

                            <label class="form-label mb-1">
                                * Hora término:
                            </label>

                            <input
                                type="time"
                                class="form-control mb-3"
                                x-model="formAgenda.hora_termino"
                                @change="errors.hora_termino = false"
                                :class="errors.hora_termino ? 'is-invalid' : ''">

                        </div>

                    </div>


                 

                        <label class="form-label mb-1">
                            * Proceso:
                        </label>

                        <textarea
                            class="form-control mb-3"
                            rows="2"
                            x-model="formAgenda.proceso"
                            @change="errors.proceso = false"
                            :class="errors.proceso ? 'is-invalid' : ''">
            </textarea>

           


                    <div class="mb-2">

                        <label class="form-label mb-1">
                            * Elemento del sistema de gestión de medición:
                        </label>

                        <select
                            class="form-select mb-3"
                            x-model="formAgenda.elemento_sistema"
                            @change="errors.elemento_sistema = false"
                            :class="errors.elemento_sistema ? 'is-invalid' : ''">

                            <option value="">
                                Selecciona una opción...
                            </option>

                            <template
                                x-for="elemento in elementos"
                                :key="elemento.id">

                                <option
                                    :value="elemento.no + ' ' + elemento.criterio"
                                    x-text="elemento.no + ' ' + elemento.criterio">
                                </option>

                            </template>

                        </select>

                    </div>


                   

                        <label class="form-label mb-1">
                            * Nombre y rol del auditor:
                        </label>

                        <textarea
                            class="form-control  mb-3"
                            rows="2"
                            x-model="formAgenda.nombre_rol"
                            @change="errors.nombre_rol = false"
                            :class="errors.nombre_rol ? 'is-invalid' : ''">
            </textarea>



                    <div class="mb-2">

                        <label class="form-label mb-1">
                            Guía:
                        </label>

                        <textarea
                            class="form-control mb-3"
                            rows="2"
                            x-model="formAgenda.guia">
            </textarea>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">

                        <i class="ti ti-x"></i>

                        Cancelar

                    </button>


                    <button
                        type="button"
                        class="btn btn-success"
                        @click="guardarAgenda">

                        <i class="ti ti-check"></i>

                        Guardar

                    </button>

                </div>

            </div>
        </div>
    </div>

</div>