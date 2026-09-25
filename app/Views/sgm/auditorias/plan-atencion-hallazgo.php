<div x-data="{...actions(), ...planatencion(<?= $id ?>)}">



  <div class="text-end mt-3">

                <button
                    type="button"
                    class="btn btn-success"
                    @click="finalizar">

                    <i class="ti ti-check"></i>
                    Finalizar Plan

                </button>

            </div>

    <!-- ===================================================== -->
    <!-- I. DATOS GENERALES -->
    <!-- ===================================================== -->

    <div class="bg-white mt-3">

   <div class="card">
    <div class="card-header bg-primary">
<span class="card-title text-white">
    
                                I. DATOS GENERALES DEL PERMISIONARIO
                         
</span>
    </div>
    <div class="card-body p-0">
     <table class="table table-striped table-bordered  align-middle mb-0">
            <tbody>


                    <tr>

                        <td class="text-center align-middle fw-bolder">
                            Nombre, denominación o razón social:
                        </td>

                        <td class="text-center align-middle fw-bolder">
                            Permiso CRE:
                        </td>

                        <td class="text-center align-middle fw-bolder">
                            Fecha del informe de auditoría
                            (Reporte de hallazgos de auditorias):
                        </td>

                    </tr>


                    <tr>

                        <td class="text-center align-middle"
                            x-text="plan.razon_social || ''">
                        </td>

                        <td class="text-center align-middle"
                            x-text="plan.permiso_cre || ''">
                        </td>

                        <td class="p-0 m-0">

                            <input
                                type="date"
                                class="form-control text-center border-0 rounded-0"
                                x-model="plan.fecha"
                                @change="editar('fecha')">

                        </td>

                    </tr>


                    <tr>

                        <td
                            colspan="2"
                            class="text-center align-middle fw-bolder">
                            Sitio/Área:
                        </td>

                        <td
                            class="text-center align-middle fw-bolder">
                            Responsable:
                        </td>

                    </tr>


                    <tr>

                        <td
                            class="p-0 m-0"
                            colspan="2">

                            <input
                                type="text"
                                class="form-control border-0 rounded-0 text-center"
                                x-model="plan.sitio_area"
                                @change="editar('sitio_area')">

                        </td>


                        <td class="p-0 m-0">

                            <select
                                class="form-select rounded-0 border-0"
                                x-model="plan.responsable"
                                @change="editar('responsable')">

                                <option 
                                class="text-center"
                                value="0">
                                    Seleccione una opcion...
                                </option>

                                <template
                                    x-for="usuario in usuarios"
                                    :key="usuario.id">

                                    <option
                                    class="text-center"
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
                    


                    <!-- ================================================= -->
                    <!-- II. HALLAZGO -->
                    <!-- ================================================= -->
<div class="card">
    <div class="card-header bg-primary">
        <span class="card-title text-white">
    II. HALLAZGO:
                                (DESCRIPCIÓN/EVIDENCIA/CRITERIO)
                            </span>
    </div>
    <div class=" card-body p-0">

                            <textarea
                                class="form-control rounded-0 border-0"
                                rows="5"
                                x-model="plan.hallazgo"
                                @change="editar('hallazgo')"></textarea>
    </div>
</div>
                


                    <!-- ================================================= -->
                    <!-- III. CAUSA RAÍZ -->
                    <!-- ================================================= -->
<div class="card">
    <div class="card-header bg-primary">
        <span class="card-title text-white">
  III. ANÁLISIS DE LA CAUSA RAÍZ
        </span>
    </div>
    <div class="card-body p-0">

                            <textarea
                                class="form-control rounded-0 border-0"
                                rows="5"
                                x-model="plan.analisis_causa"
                                @change="editar(
                                    'analisis_causa'
                                )"></textarea>

               
    </div>
</div>

                    <!-- ================================================= -->
                    <!-- IV. ACCIONES -->
                    <!-- ================================================= -->
<div class="card">
    <div class="card-header bg-primary">
<span class="card-title text-white">

                                IV. ACCIONES PARA LA ATENCIÓN
                                DE LOS HALLAZGOS NO CONFORMES
                         
    </div>
    <div class="card-body p-0">

                            <textarea
                                class="form-control rounded-0 border-0"
                                rows="5"
                                x-model="plan.acciones_hallazgos"
                                @change="editar(
                                    'acciones_hallazgos'
                                )"></textarea>
    </div>
</div>
                   


                    <!-- ================================================= -->
                    <!-- V. RESPONSABLES -->
                    <!-- ================================================= -->
<div class="card">
    <div class="card-header bg-primary">
<span class="card-title text-white">
 
                                V. NOMBRE DE LOS RESPONSABLES
                                DEL CUMPLIMIENTO DE LAS ACCIONES
                         
</span>
    </div>
    <div class="card-body p-0">
     

                            <select
                                class="form-select border rounded-0"
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
                                    <li
                                        class="list-group-item bg-transparent d-flex justify-content-between align-items-center p-2">

                                        <span x-text="responsable.nombre"></span>

                                        <a
                                            class="pointer"
                                            @click="eliminarResponsable(responsable.id)"
                                            title="Eliminar responsable">
                                            <i class="ti ti-trash fs-6 text-danger"></i>
                                        </a>

                                    </li>
                                </template>

                            </ul>

    </div>
</div>
           

                    <!-- ================================================= -->
                    <!-- VI. FECHA COMPROMISO -->
                    <!-- ================================================= -->
<div class="card">
    <div class="card-header bg-primary">
        <span class="card-title text-white">

                                VI. FECHAS COMPROMISO PARA EL CUMPLIMIENTO
                                DE LA IMPLEMENTACIÓN DE ACCIONES
                        
        </span>
    </div>
    <div class="card-body p-0">
     

                            <textarea
                                class="form-control rounded-0 border-0"
                                rows="3"
                                x-model="plan.fecha_complimiento"
                                @change="editar(
                                    'fecha_complimiento'
                                )"></textarea>
    </div>
</div>
               

                    <!-- ================================================= -->
                    <!-- VII. RECURSOS -->
                    <!-- ================================================= -->
<div class="card">
    <div class="card-header bg-primary">
<span class="card-title text-white">
                                VII. RECURSOS ASIGNADOS PARA
                                LA IMPLEMENTACIÓN DE ACCIONES
                         
</span>
    </div>
    <div class="card-body p-0">
        

                            <textarea
                                class="form-control rounded-0 border-0"
                                rows="3"
                                x-model="plan.recursos_implementacion"
                                @change="editar(
                                    'recursos_implementacion'
                                )"></textarea>
    </div>
</div>
                  

    </div>


    <!-- ===================================================== -->
    <!-- PARTE FINAL -->
    <!-- ===================================================== -->

    <div class="bg-white mt-3 mb-4">
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">

             <table class="table table-striped table-bordered text-nowrap align-middle mb-0">

                <tbody>

                    <tr>

                        <td class="fw-bolder bg-light">

                            Fecha del plan de atención
                            de hallazgos
:

                        </td>

                        <td class="p-0 m-0">

                            <input
                                type="date"
                                class="form-control border-0 rounded-0"
                                x-model="plan.fecha_atencion_hallazgos"
                                @change="editar(
                                    'fecha_atencion_hallazgos'
                                )">

                        </td>

                    </tr>


                    <tr>

                        <td class="fw-bolder bg-light">

                            Responsable del SGM:

                        </td>

                        <td class="p-0 m-0">

                            <select
                                class="form-select rounded-0 border-0"
                                x-model="plan.responsable_sgm"
                                @change="editar(
                                    'responsable_sgm'
                                )">

                                <option value="0">
                                    Seleccione responsable
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

</div>