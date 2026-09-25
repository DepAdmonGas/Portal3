<div class="pb-4" x-data="{...actions(), ...reporte(<?= $id ?>)}">

   <div class="text-end">

            <button
                class="btn btn-success"
                @click="window.history.back()">
                <i class="ti ti-check"></i>
                Finalizar Reporte
            </button>

        </div>

    <div class="mt-4">
        <div class="card">
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
                            Nombre, denominación o razón social
                        </td>
                        <td class="bg-light text-center fw-bolder">
                            Permiso CRE
                        </td>
                        <td class="bg-light text-center fw-bolder">
                            Fecha de elaboración
                        </td>
                    </tr>
                    <tr>
                        <td
                            class="text-center"
                            x-text="hallazgo.razon_social"></td>
                        <td
                            class="text-center"
                            x-text="hallazgo.permiso_cre"></td>
                        <td class="p-0 m-0">
                            <input
                                type="date"
                                class="form-control border-0 rounded-0 text-center"
                                x-model="hallazgo.fecha"
                                @change="editar('fecha')">
                        </td>
                    </tr>

                    <tr>

                        <td
                            class="bg-light text-center fw-bolder align-middle">
                            Nombres del responsable del SGM:
                        </td>

                       <td colspan="2" class="p-0 m-0 bg-light">

                            <select
                                class="form-select border rounded-0 text-start"
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

                            <ul class="list-group list-group-flush">

                                <template
                                    x-for="responsable in responsables"
                                    :key="responsable.id">
                                  <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center p-2">
                                    
                                  <span 
                                        class="flex-grow-1 text-start"
                                        x-text="responsable.nombre"></span>

                                        <a
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

                </tbody>

            </table>

        </div>
            </div>
  
        </div>



    </div>

    <div class="bg-white mt-3">
        <div class="card">
            <div class="card-header bg-primary">
   <span class="card-title text-white">
       I. DATOS DE LA AUDITORÍA
   </span>
    
            </div>
            <div class="card-body p-0">
                <div class="table-responsve">
 <table class="table table-striped table-bordered text-nowrap align-middle mb-0">

            <tbody>

   


                <tr>

                    <td class="bg-light fw-bolder">
                        Fecha y ubicación de la auditoría:
                    </td>

                    <td class="p-0 m-0">

                        <input
                            type="text"
                            class="form-control border-0"
                            x-model="hallazgo.fecha_ubicacion"
                            @keyup="editar('fecha_ubicacion')">

                    </td>

                </tr>


                <tr>

                    <td class="bg-light fw-bolder">
                        Objetivo de la auditoría:
                    </td>

                    <td class="p-0 m-0">

                        <input
                            type="text"
                            class="form-control border-0"
                            x-model="hallazgo.objetivo_auditoria"
                            @keyup="editar('objetivo_auditoria')">

                    </td>

                </tr>


                <tr>

                    <td class="bg-light fw-bolder">
                        Alcance de la auditoría:
                    </td>

                    <td class="p-0 m-0">

                        <input
                            type="text"
                            class="form-control border-0"
                            x-model="hallazgo.alcance_auditoria"
                            @keyup="editar('alcance_auditoria')">

                    </td>

                </tr>

            </tbody>

        </table>

                </div>
            </div>
        </div>

       
    </div>

    <!-- -- PERSONAL ENTREVISTADO -- -->
    <div class="bg-white mt-3">
        <div class="card">
            <div class="card-header bg-primary">

 <div class="row align-items-center">

    <div class="col-md-8">
        <span class="card-title text-white mb-0">
            <i class="ti ti-user-question"></i>
            PERSONAL ENTREVISTADO
        </span>
    </div>

    <div class="col-12 col-md-4 d-grid d-md-block text-end">
        <button
            type="button"
            class="btn bg-success text-white"
            @click="abrirEntrevistador()">
            <i class="ti ti-plus"></i>
            Nuevo personal
        </button>
    </div>

</div>



            </div>


            <div class="card-body p-0">
            <table class="table table-striped table-bordered text-nowrap align-middle mb-0">
                <tbody>

                

                    <tr>

                        <td class="text-start fw-bolder bg-light">
                            Nombre
                        </td>

                        <td class="text-center fw-bolder bg-light">
                            Puesto
                        </td>

                        <td class="text-center fw-bolder bg-light">
                            Área de adquisisión
                        </td>

                        <td
                            class="text-center fw-bolder bg-light"
                            width="32">
                            <i class="ti ti-trash fs-6 text-danger"></i>
                        </td>

                    </tr>

                    <template
                        x-if="entrevistados.length === 0">

                        <tr>

                            <td
                                colspan="4"
                                class="text-center text-muted">

                                <small>
                                    No se encontró información para mostrar
                                </small>

                            </td>

                        </tr>

                    </template>

                    <template
                        x-for="entrevistado in entrevistados"
                        :key="entrevistado.id">

                        <tr>

                            <td
                                class="align-middle text-start fw-bold"
                                x-text="entrevistado.nombre">
                            </td>

                            <td
                                class="align-middle text-center"
                                x-text="entrevistado.puesto">
                            </td>

                            <td
                                class="align-middle text-center"
                                x-text="entrevistado.area_descripcion">
                            </td>

                            <td class="text-center align-middle">

                                <a
                                    class="pointer"
                                    title="Eliminar agenda"
                                    @click="eliminarEntrevistado(entrevistado.id)">

                                    <i class="ti ti-trash fs-5 text-danger"></i>

                                </a>

                            </td>

                        </tr>

                    </template>

                </tbody>

            </table>

            </div>
        </div>




     

    </div>
    <!-- -- PERSONAL ENTREVISTADO -- -->

    <!-- -- EQUIPO AUDITOR -- -->
    <div class="bg-white mt-3">
<div class="card">
    <div class="card-header bg-primary">
           

               <div class="row align-items-center">

    <div class="col-md-8">
        <span class="card-title text-white">
            <i class="ti ti-users-group"></i>    

EQUIPO AUDITOR
        </span>
    </div>

    <div class="col-12 col-md-4 d-grid d-md-block text-end">
   <button
                type="button"
                class="btn bg-success text-white"
                @click="abrirEquipoAuditor()">
                <i class="ti ti-plus"></i>
                Nuevo equipo
            </button>

    </div>

</div>

    </div>
    <div class="card-body p-0">
            <div class="table-responsive">

                <table class="table table-striped table-bordered text-nowrap align-middle mb-0">

                    <tbody>


                        <tr>

                            <td class="text-start fw-bolder bg-light">
                                Nombre
                            </td>

                            <td class="text-center fw-bolder bg-light">
                                Rol (auditor líder, auditor experto técnico, auditor especialista)
                            </td>

                            <td
                                width="32"
                                class="text-center bg-light">
                                <i class="ti ti-trash fs-6 text-danger"></i>
                            </td>

                        </tr>


                        <template
                            x-for="auditor in equipoauditor"
                            :key="auditor.id">

                            <tr>

                                <td
                                    class="align-middle text-start fw-bold"
                                    x-text="auditor.nombre">
                                </td>

                                <td
                                    class="align-middle text-center"
                                    x-text="auditor.rol">
                                </td>

                                <td class="text-center align-middle">

                                    <a
                                        class="pointer"
                                        @click="eliminarAuditor(auditor.id)">

                                        <i class="ti ti-trash fs-6 text-danger"></i>

                                    </a>

                                </td>

                            </tr>

                        </template>


                        <template x-if="equipoauditor.length === 0">

                            <tr>

                                <td
                                    colspan="3"
                                    class="text-center text-muted">

                                    <small>
                                        No se encontró información para mostrar
                                    </small>

                                </td>

                            </tr>

                        </template>

                    </tbody>

                </table>

            </div>
    </div>
</div>

    </div>
    <!-- -- EQUIPO AUDITOR -- -->






    <!-- -- RESULTADO DE LA AUDITORÍA -- -->
    <div class="bg-white mt-3">
        <div class="card">
            <div class="card-header bg-primary">

               <span class="card-title text-white">
     II. RESULTADO DE LA AUDITORÍA
               </span>
                           
            </div>
            <div class="card-body p-0">
<div class="table-responsive">

            <table class="table table-striped table-bordered text-nowrap align-middle mb-0">

                <tbody>


                    <tr>

                        <td
                            colspan="3"
                            class="bg-light text-center">

                            ¿Durante la auditoría se revisaron
                            los siguientes elementos?

                            <br>

                            Marcar el resultado como
                            C= Conforme,
                            NC= No Conforme,
                            OM= Oportunidad de Mejora

                        </td>

                    </tr>

                    <tr class="bg-light">

                        <td
                            class="text-center align-middle fw-bolder">

                            No.

                        </td>

                        <td class="align-middle fw-bolder text-start">

                            Criterio

                        </td>

                        <td
                            class="text-center align-middle fw-bolder">

                            Resultado

                        </td>

                    </tr>


                    <template
                        x-for="resultado in resultados"
                        :key="resultado.id">

                        <tr>

                            <td
                                class="text-center align-middle fw-bolder"
                                x-text="resultado.no">
                            </td>


                            <td
                                class="align-middle text-start"
                                x-text="resultado.criterio">
                            </td>


                            <td class="m-0 p-0">

                                <select
                                    class="form-select rounded-0 border-0 text-center"
                                    x-model="resultado.resultado"
                                    @change="editarResultado(resultado)">

                                    <option value="">
                                        Seleccionar una opcion...
                                    </option>

                                    <option value="C">
                                        C= Conforme
                                    </option>

                                    <option value="NC">
                                        NC= No Conforme
                                    </option>

                                    <option value="OM">
                                        OM= Oportunidad de Mejora
                                    </option>

                                </select>

                            </td>

                        </tr>

                    </template>


                    <template
                        x-if="resultados.length === 0">

                        <tr>

                            <td
                                colspan="3"
                                class="text-center text-muted">

                                <small>
                                    No se encontró información para mostrar
                                </small>

                            </td>

                        </tr>

                    </template>

                </tbody>

            </table>

        </div>
            </div>
        </div>

        

    </div>
    <!-- -- RESULTADO DE LA AUDITORÍA -- -->





    <!-- -- III. DOCUMENTACIÓN DE LOS HALLAZGOS NO CONFORMES -- -->
    <div class="bg-white mt-3">
        <div class="card">
            <div class="card-header bg-primary">
 
            <div class="row align-items-center">

    <div class="col-md-8">
        <span class="card-title text-white">
             III. DOCUMENTACIÓN DE LOS HALLAZGOS NO CONFORMES
        </span>
    </div>

    <div class="col-12 col-md-4 d-grid d-md-block text-end">
         <button
                type="button"
                class="btn bg-success text-white"
                @click="abrirConforme()">
                <i class="ti ti-plus"></i>
                Nueva documentación
            </button>

    </div>

</div>
       

    
            </div>
            <div class="card-body p-0">
 <div class="table-responsive">

            <table class="table table-striped table-bordered text-nowrap align-middle mb-0">

                <tbody>

                    <tr>

                        <td
                            class="text-center align-middle fw-bolder bg-light"
                            width="96px">
                            No.
                        </td>

                        <td
                            class="text-center align-middle fw-bolder bg-light">
                            Descripción del hallazgo
                        </td>

                        <td
                            class="text-center align-middle fw-bolder bg-light">
                            Evidencia
                        </td>

                        <td
                            class="text-center align-middle fw-bolder bg-light">
                            Criterio
                        </td>

                        <td width="48px" class="text-center align-middle bg-light">
                            <i class="ti ti-trash fs-6 text-danger"></i>
                        </td>

                    </tr>


                    <template
                        x-for="(conforme, index) in conformes"
                        :key="conforme.id">

                        <tr>

                            <td
                                class="text-center align-middle fw-bold"
                                x-text="index + 1">
                            </td>

                            <td
                                class="text-center align-middle"
                                x-text="conforme.descripcion">
                            </td>

                            <td
                                class="text-center align-middle"
                                x-text="conforme.evidencia">
                            </td>

                            <td
                                class="text-center align-middle"
                                x-text="conforme.criterio">
                            </td>

                            <td
                                class="text-center align-middle">

                                <a
                                    class="pointer"
                                    @click="eliminarConforme(conforme.id)">

                                    <i class="ti ti-trash fs-6 text-danger"></i>

                                </a>

                            </td>

                        </tr>

                    </template>


                    <tr
                        x-show="conformes.length === 0">

                        <td
                            colspan="5"
                            class="text-center text-muted"
                            style="font-size: .8em;">

                            No se encontró información para mostrar

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>
            </div>
        </div>

       

    </div>
    <!-- -- III. DOCUMENTACIÓN DE LOS HALLAZGOS NO CONFORMES -- -->





    <!-- -- IV. OPORTUNIDADES DE MEJORA/OBSERVACIONES -- -->

    <div class="bg-white mt-3">
<div class="card">
    <div class="card-header bg-primary">
<div class="row align-items-center">

    <div class="col-md-8">
        <span class="card-title text-white">
            IV. OPORTUNIDADES DE MEJORA/OBSERVACIONES
        </span>
    </div>

    <div class="col-12 col-md-4 d-grid d-md-block text-end">
        <button
            type="button"
            class="btn bg-success text-white"
            @click="abrirMejoras()">
            <i class="ti ti-plus"></i>
            Nueva descripción
        </button>
    </div>

</div>
       
    </div>
    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-striped table-bordered align-middle mb-0">

                <tbody>

                    <tr>

                        <td
                            class="text-center align-middle fw-bolder bg-light"
                            width="96px">

                            No.

                        </td>

                        <td
                            class="text-start align-middle fw-bolder bg-light">

                            Descripción

                        </td>

                        <td width="48px" class="text-center bg-light">
                            <i class="ti ti-trash fs-6 text-danger"></i>
                        </td>

                    </tr>


                    <template
                        x-for="(mejora, index) in mejoras"
                        :key="mejora.id">

                        <tr>

                            <td
                                class="text-center align-middle fw-bolder"
                                x-text="index + 1">
                            </td>

                            <td
                                class="text-start align-middle"
                                x-text="mejora.descripcion">
                            </td>

                            <td
                                class="text-center align-middle">

                                <a
                                    class="pointer"
                                    @click="eliminarMejora(mejora.id)">

                                    <i class="ti ti-trash fs-6 text-danger"></i>

                                </a>

                            </td>

                        </tr>

                    </template>


                    <tr
                        x-show="mejoras.length === 0">

                        <td
                            colspan="3"
                            class="text-center text-muted"
                            style="font-size: .8em;">

                            No se encontró información para mostrar

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>
</div>
     
    </div>
    <!-- -- IV. OPORTUNIDADES DE MEJORA/OBSERVACIONES -- -->




    <!-- -- V. COMENTARIOS -- -->
    <div class="bg-white mt-3">
<div class="card">
    <div class="card-header bg-primary">
        <span class="card-title text-white">
                                V. COMENTARIOS
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">

            <table class="table table-striped table-bordered align-middle mb-0">

                <tbody>                
                    <tr>

                        <td
                            colspan="2"
                            class="p-0 m-0">

                            <textarea
                                class="form-control border-0"
                                x-model="hallazgo.comentarios"
                                @keyup="editar('comentarios')"></textarea>

                        </td>

                    </tr>


                    <tr>

                        <td
                            colspan="2"
                            class="bg-light form-label">
                            Nota: en caso de que durante la auditoría,
                            el equipo auditor detecte una situación de
                            riesgo para la seguridad industrial,
                            seguridad operativa o para el medio ambiente
                            en las instalaciones del regulado, deberá
                            reportarla en esta sección.

                        </td>

                    </tr>


                    <tr>

                        <td
                            colspan="2"
                            class="p-0 m-0">

                            <textarea
                                class="form-control border-0"
                                x-model="hallazgo.nota"
                                @keyup="editar('nota')"></textarea>

                        </td>

                    </tr>


                    <tr>

                        <td
                            colspan="2"
                            class="bg-light form-label">
                            Motivos de finalización de auditoría
                            antes de tiempo (si aplica):

                        </td>

                    </tr>


                    <tr>

                        <td
                            colspan="2"
                            class="p-0 m-0">

                            <textarea
                                class="form-control border-0"
                                x-model="hallazgo.motivos"
                                @keyup="editar('motivos')"></textarea>

                        </td>

                    </tr>


               

                </tbody>

            </table>

        </div>
    </div>
</div>


    </div>
    <!-- -- V. COMENTARIOS -- -->



    <!-- -- VI. CONCLUSIONES -- -->
    <div class="bg-white mt-3">
<div class="card">
    <div class="card-header bg-primary">
 <span class="card-title text-white">
                                VI. CONCLUSIONES
 </span>

                         
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">

            <table class="table table-striped table-bordered align-middle mb-0">

                <tbody>



                    <tr>

                        <td
                            colspan="3"
                            class="p-0 m-0">

                            <textarea
                                class="form-control border-0"
                                x-model="hallazgo.conclusiones"
                                @keyup="editar('conclusiones')"></textarea>

                        </td>

                    </tr>


                    <tr>

                        <td class="text-center align-middle fw-bolder bg-light">
                            Lugar y fecha
                        </td>

                        <td class="text-center align-middle fw-bolder bg-light">
                            Auditor lider
                        </td>

                        <td class="text-center align-middle fw-bolder bg-light">
                            Responsable del SGM
                        </td>

                    </tr>


                    <tr>

                        <td class="p-0 m-0">

                            <input
                                type="text"
                                class="form-control text-center border-0"
                                x-model="hallazgo.lugar_fecha"
                                @keyup="editar('lugar_fecha')">

                        </td>


                        <td class="p-0 m-0">

                            <select
                                class="form-select text-center rounded-0 border-0"
                                x-model="hallazgo.auditor_lider"
                                @change="editar('auditor_lider')">

                                <option value="0">
                                    Seleccionar
                                </option>

                                <template
                                    x-for="usuario in usuarios"
                                    :key="usuario.id">

                                    <option
                                        :value="usuario.id"
                                        x-text="usuario.nombre"></option>

                                </template>

                            </select>

                        </td>


                        <td class="p-0 m-0">

                            <select
                                class="form-select text-center rounded-0 border-0"
                                x-model="hallazgo.responsable_sgm"
                                @change="editar('responsable_sgm')">

                                <option value="0">
                                    Seleccionar
                                </option>

                                <template
                                    x-for="usuario in usuarios"
                                    :key="usuario.id">

                                    <option
                                        :value="usuario.id"
                                        x-text="usuario.nombre"></option>

                                </template>

                            </select>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>
</div>


     
    </div>
    <!-- -- VI. CONCLUSIONES -- -->



    <!-- Modal Personal Entrevistado -->
    <div
        class="modal fade"
        id="modalPersonalEntrevistado"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <div class="modal-header bg-primary head-modal">

                    <h5 class="modal-title text-white">
                        <i class="ti ti-user-question"></i>
                        PERSONAL ENTREVISTADO
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>


                <div class="modal-body">

                  

                        <label class="form-label mb-1">
                            * Nombre:
                        </label>

                        <select
                            class="form-select mb-3"
                            x-model="formEntrevistador.id_usuario"
                            @change="errorsEntrevistador.id_usuario = false"
                            :class="errorsEntrevistador.id_usuario ? 'is-invalid' : ''">

                            <option value="">
                                Selecciona una opcion...
                            </option>

                            <template
                                x-for="usuario in usuarios"
                                :key="usuario.id">

                                <option
                                    :value="usuario.id"
                                    x-text="usuario.nombre">
                                </option>

                            </template>

                        </select>



                 

                        <label class="form-label mb-1">
                            * Área de descripción:
                        </label>

                        <textarea
                            class="form-control"
                            rows="3"
                            x-model="formEntrevistador.area_descripcion"
                            @input="errorsEntrevistador.area_descripcion = false"
                            :class="errorsEntrevistador.area_descripcion ? 'is-invalid' : ''">
                    </textarea>

      

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
                        @click="guardarEntrevistador()">

                        <i class="ti ti-check"></i>

                        Guardar

                    </button>

                </div>

            </div>

        </div>

    </div>
    <!-- Modal Personal Entrevistado -->

    <!-- Modal Auditor Equipo -->
    <div
        class="modal fade"
        id="modalEquipoAuditor"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <div class="modal-header bg-primary head-modal">

                    <h5 class="modal-title text-white">
                        <i class="ti ti-users-group"></i>
                        EQUIPO AUDITOR
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>


                <div class="modal-body">

                    <!-- PERSONAL EXTERNO -->

                   

                        <label class="form-label mb-1">
                            * Nombre (Personal Externo):
                        </label>

                        <textarea
                            class="form-control mb-3"
                            rows="2"
                            x-model="formEquipoAuditor.nombre"
                            @input="
                            errorsEquipoAuditor.nombre = false;
                            if (formEquipoAuditor.nombre.trim() !== '') {
                                formEquipoAuditor.id_usuario = '';
                                errorsEquipoAuditor.id_usuario = false;
                            }
                        "
                            :class="errorsEquipoAuditor.nombre ? 'is-invalid' : ''"
                            :disabled="!!formEquipoAuditor.id_usuario">
                    </textarea>

                

                        <label class="form-label mb-1">
                            * Rol (auditor líder, auditor experto técnico, auditor especialista):
                        </label>

                        <textarea
                            class="form-control mb-3"
                            rows="2"
                            x-model="formEquipoAuditor.rol"
                            @input="
                            errorsEquipoAuditor.rol = false;
                            if (formEquipoAuditor.rol.trim() !== '') {
                                formEquipoAuditor.id_usuario = '';
                                errorsEquipoAuditor.id_usuario = false;
                            }
                        "
                            :class="errorsEquipoAuditor.rol ? 'is-invalid' : ''"
                            :disabled="!!formEquipoAuditor.id_usuario">
                    </textarea>



                    <div class="text-center my-3">

                        <span class="text-secondary">
                            O
                        </span>

                    </div>


                    <!-- PERSONAL INTERNO -->

          

                        <label class="form-label mb-1">
                            * Nombre (Personal Interno):
                        </label>

                        <select
                            class="form-select"
                            x-model="formEquipoAuditor.id_usuario"
                            @change="
                            errorsEquipoAuditor.id_usuario = false;

                            if (formEquipoAuditor.id_usuario) {
                                formEquipoAuditor.nombre = '';
                                formEquipoAuditor.rol = '';

                                errorsEquipoAuditor.nombre = false;
                                errorsEquipoAuditor.rol = false;
                            }
                        "
                            :class="errorsEquipoAuditor.id_usuario ? 'is-invalid' : ''"
                            :disabled="
                            formEquipoAuditor.nombre.trim() !== '' ||
                            formEquipoAuditor.rol.trim() !== ''
                        ">

                            <option value="">
                                Selecciona una opcion...
                            </option>

                            <template
                                x-for="usuario in usuarios"
                                :key="usuario.id">

                                <option
                                    :value="usuario.id"
                                    x-text="
                                    usuario.nombre +
                                    (usuario.puesto
                                        ? ' (' + usuario.puesto + ')'
                                        : '')
                                ">
                                </option>

                            </template>

                        </select>

                 

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
                        @click="guardarAuditor()">

                        <i class="ti ti-check"></i>

                        Guardar

                    </button>

                </div>

            </div>

        </div>

    </div>
    <!-- Modal Auditor Equipo -->

    <!-- Modal Documentación de Hallazgo -->
    <div
        class="modal fade"
        id="modalConforme"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h5 class="modal-title text-white">
                        III. DOCUMENTACIÓN DE LOS HALLAZGOS NO CONFORMES
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>


                <div class="modal-body">

               

                        <label class="form-label mb-1">
                            * Descripción del hallazgo:
                        </label>

                        <textarea
                            class="form-control rounded-0 mb-3"
                            rows="3"
                            x-model="formConforme.descripcion"
                            @input="errorsConforme.descripcion = false"
                            :class="
                            errorsConforme.descripcion
                                ? 'is-invalid'
                                : ''
                        ">
                    </textarea>

                 


            

                        <label class="form-label mb-1">
                            * Evidencia:
                        </label>

                        <textarea
                            class="form-control rounded-0 mb-3"
                            rows="3"
                            x-model="formConforme.evidencia"
                            @input="errorsConforme.evidencia = false"
                            :class="
                            errorsConforme.evidencia
                                ? 'is-invalid'
                                : ''
                        ">
                    </textarea>

              


              
                        <label class="form-label mb-1">
                            * Criterio:
                        </label>

                        <textarea
                            class="form-control rounded-0"
                            rows="3"
                            x-model="formConforme.criterio"
                            @input="errorsConforme.criterio = false"
                            :class="
                            errorsConforme.criterio
                                ? 'is-invalid'
                                : ''
                        ">
                    </textarea>

           

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
                        @click="guardarConforme()">

                        <i class="ti ti-check"></i>

                        Guardar

                    </button>

                </div>

            </div>

        </div>

    </div>
    <!-- Modal Documentación de Hallazgo -->

    <!-- Modal Oportunidad de Mejora -->

    <div
        class="modal fade"
        id="modalMejora"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h5 class="modal-title text-white">
                        IV. OPORTUNIDADES DE MEJORA/OBSERVACIONES
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>


                <div class="modal-body">


                        <label class="form-label mb-1">
                            * Descripción:
                        </label>

                        <textarea
                            class="form-control"
                            rows="4"
                            x-model="formMejora.descripcion"
                            @input="errorsMejora.descripcion = false"
                            :class="
                            errorsMejora.descripcion
                                ? 'is-invalid'
                                : ''
                        ">
                    </textarea>

            

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
                        @click="guardarMejora()">

                        <i class="ti ti-check"></i>

                        Guardar

                    </button>

                </div>

            </div>

        </div>

    </div>

    <!-- Modal Oportunidad de Mejora -->

</div>