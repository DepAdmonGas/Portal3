<div id="container" class="pb-4"
x-data="reporte('<?= $fechaInicio ?>', '<?= $fechaTermino ?>')">

<!-- 1. POLÍTICA -->
<div class="card mt-3">
    <div class="card-header">
        <div class="d-flex align-items-start">
            <h4 class="card-title">1. POLÍTICA</h4>
            <div class="ms-auto">
                <div class="">
                    <a href="/sasisopa/politica/pdf" class="btn bg-primary-subtle text-primary">
                      <i class="ti ti-file-type-pdf fs-4"></i> Politica
                    </a>
                </div>
            </div>
        </div>
    </div>



    <div class="card-body">
<div class="row mt-2">
    <div class="col-md-6">
<div class="card">
<div class="card-header bg-primary align-middle">
      <h6 class="text-white mb-0">
      <i class="ti ti-label"></i> 
      Fo.ADMONGAS.001 (Lista de comprobación)</h6>
</div>


<div class="card-body p-0">
        <table class="table table-bordered table-striped mb-0">
            <thead>
                <th class="text-center">#</th>
                <th class="text-center">Fecha</th>
                <th class="text-center"><i class="ti ti-file-type-pdf text-muted fs-7"></i></th>
            </thead>
            <tbody>
                <template
                    x-for="(item,index) in politica.listas"
                    :key="item.id">
                    <tr>
                        <td class="text-center align-middle fw-bolder" x-text="index+1"></td>
                        <td class="text-center align-middle" x-text="item.fecha"></td>
                        <td class="text-center align-middle">
                        <a :href="`/sasisopa/politica/lista-comprobacion/pdf/${item.id}`" target="_blank">
                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>
                        </a>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    </div>
</div>

<div class="col-md-6">
<div class="card">
    
 <div class="card-header bg-primary">
    <div class="text-white mb-0 d-flex align-items-center">
        <i class="ti ti-label me-2"></i>
        <span>Fo.ADMONGAS.010 (Registro de la atención y el seguimiento a la comunicación interna y externa.)</span>
    </div>
</div>

    

        <div class="card-body p-0">
        <table class="table table-bordered table-striped mb-0">
            <thead>
                <th class="text-center">#</th>
                <th class="text-center">Fecha</th>
                <th class="text-center">Hora</th>
                <th class="text-center"><i class="ti ti-file-type-pdf text-muted fs-7"></i></th>
            </thead>
            <tbody>
                <template
                    x-for="(item,index) in politica.asistencias"
                    :key="item.id">
                    <tr>
                        <td class="text-center align-middle fw-bolder" x-text="index+1"></td>
                        <td class="text-center align-middle" x-text="item.fecha"></td>
                        <td class="text-center align-middle" x-text="item.hora"></td>
                        <td class="text-center align-middle">
                        <a :href="`/lista-asistencia/pdf/${item.id}`" target="_blank">
                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>
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
</div>
<!-- 1. POLÍTICA -->


<!-- 2. ANÁLISIS DE RIESGO Y EVALUACIÓN DE IMPACTOS AMBIENTALES -->
<!---- card principal header----->
<div class="card">

    <div class="card-header">
 <h4 class="card-title">2. ANÁLISIS DE RIESGO Y EVALUACIÓN DE IMPACTOS AMBIENTALES</h4>
    </div>

<!----card  principal body---->
    <div class="card-body">
    <div class="row mt-2">

    <div class="col-md-6">

        <table class="table table-bordered  mb-3 pb-0">

            <tbody>
                <tr>
                    <td class="bg-light align-middle fw-bolder">
                    Identificación y evaluación de Aspectos e Impactos Ambientales.
                    </td>
                    <td width="40" class="text-center align-middle">
                        <a href="/sasisopa/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales/aspectos-ambientales-pdf"
                        download>
                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>
                        </a>
                    </td>
                </tr>
            </tbody>
        </table>

<!------Card 1----->
        <div class="card">
            <div class="card-header bg-primary align-middle">
        <h6 class="text-white mb-0">
            <i class="ti ti-label"></i>
            Análisis de Riesgo del Sector Hidrocarburos (ARSH)
        </h6>
        </div>
<div class="card-body p-0">
        <table class="table table-bordered table-striped mb-0">

            <thead>
                <tr>
                    <th class="text-center">#</th>
                    <th class="text-center">Fecha</th>
                    <th class="text-center">Descripción</th>
                    <th width="40" class="text-center">
                         <i class="ti ti-file-type-pdf text-muted fs-7"></i>
                    </th>
                </tr>
            </thead>
            <tbody>
                <template
                    x-for="(item,index) in elemento2.analisis"
                    :key="item.id">
                    <tr>
                        <td class="text-center align-middle" x-text="index+1"></td>
                        <td class="text-center align-middle" x-text="item.fecha"></td>
                        <td class="text-center align-middle" x-text="item.descripcion"></td>
                        <td class="text-center align-middle">
                            <a
                                :href="`/uploads/analisis-riesgo/${item.documento}`"
                                target="_blank">
                                <i class="ti ti-file-type-pdf text-danger fs-7"></i>
                            </a>
                        </td>
                    </tr>
                </template>
                <template
                    x-if="elemento2.analisis.length==0">
                    <tr>
                        <td colspan="4" class="text-center text-muted">
                            No se encontró información.
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>
</div>

<div class="col-md-6">


            <table class="table table-bordered mb-3">

            <tbody>

                <tr>

                    <td class="fw-bolder bg-light">
                    Identificación y evaluación de Riesgos y Peligros para registrar el análisis.
                    </td>

                    <td width="40" class="text-center">

                        <a href="/sasisopa/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales/riesgos-peligros-pdf">

                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                        </a>

                    </td>

                </tr>

            </tbody>

        </table>



<!-------card 2----->
<div class="card">
    <div class="card-header bg-primary align-middle">
        <div class="text-white  d-flex align-items-center ">
            <i class="ti ti-label me-2"></i>
            <span> (Registro de la atención y el seguimiento a la comunicación interna y externa.)</span>
        </div>
</div>
<div class="card-body p-0">
        <table class="table table-bordered table-striped mb-0">
            <thead>
                <tr>
                    <th class="text-center align-middle">#</th>
                    <th class="text-center align-middle">Fecha</th>
                    <th class="text-center align-middle">Hora</th>
                    <th width="40">
                        <i class="ti ti-file-type-pdf text-muted fs-7"></i>
                    </th>
                </tr>
            </thead>
            <tbody>
                <template
                    x-for="(item,index) in elemento2.asistencias"
                    :key="item.id">
                    <tr>
                        <td class="text-center align-middle" x-text="index+1"></td>
                        <td class="text-center align-middle" x-text="item.fecha"></td>
                        <td class="text-center align-middle" x-text="item.hora"></td>
                        <td class="text-center align-middle">
                            <a
                                :href="`/lista-asistencia/pdf/${item.id}`"
                                target="_blank">

                                <i class="ti ti-file-type-pdf text-danger fs-7"></i>
                            </a>

                        </td>

                    </tr>

                </template>

                <template
                    x-if="elemento2.asistencias.length==0">

                    <tr>

                        <td colspan="4" class="text-center text-muted">

                            No se encontró información.

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
</div>
<!-- 2. ANÁLISIS DE RIESGO Y EVALUACIÓN DE IMPACTOS AMBIENTALES -->



<!-- 3. REQUISITOS LEGALES -->
<div class="card">
    <div class="card-header">
    <h4 class="card-title">3. REQUISITOS LEGALES</h4>
    </div>
    <div class="card-body">


    <template
    x-for="nivel in [
        { titulo: 'Municipal', data: elemento3.municipal },
        { titulo: 'Estatal', data: elemento3.estatal },
        { titulo: 'Federal', data: elemento3.federal },
        { titulo: 'Varios', data: elemento3.varios }
    ]"
    :key="nivel.titulo"
>
<!------titulo card--->
<div class="card">
    <div class="card-header">
<div class="mb-3">

    <h5 x-text="nivel.titulo"></h5>
</div>


<!------cuerpo card----->
<div class="card-body p-0">
    <div class="table-responsive">
    <table class="table table-bordered table-striped">

        <thead>

            <tr>

                <th class="text-center align-middle">Dependencia</th>
                <th class="text-center align-middle">Permiso</th>
                <th class="text-center align-middle">Vigencia</th>
                <th class="text-center align-middle">Fecha emisión</th>
                <th class="text-center align-middle">Fecha vencimiento</th>
                <th class="text-center align-middle">Acuse</th>
                <th class="text-center align-middle">Requisito Legal</th>
                <th class="text-center align-middle">% Cumplimiento</th>
                <th class="text-center align-middle">Renovación</th>

            </tr>

        </thead>

        <tbody>

            <template
                x-for="item in (nivel.data?.items ?? [])"
                :key="item.id"
            >

                <tr :class="item.estatus.color == 'warning' ? 'table-warning' : ''">

                    <td class="text-center align-middle">
                        <b x-text="item.dependencia"></b>
                    </td>

                    <td class="text-center align-middle">
                        <b x-text="item.permiso"></b>
                    </td>

                    <td class="text-center align-middle" x-text="item.vigencia"></td>

                    <td class="text-center align-middle" x-text="item.fecha_emision ?? 'S/I'"></td>

                    <td class="text-center align-middle" x-text="item.fecha_vencimiento ?? 'S/I'"></td>

                    <td class="text-center align-middle">

                        <template x-if="item.acusepdf">

                            <a
                                :href="'/uploads/' + item.acusepdf"
                                target="_blank">

                                <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                            </a>

                        </template>

                        <template x-if="!item.acusepdf">

                            <i class="ti ti-x fs-7"></i>

                        </template>

                    </td>

                    <td class="text-center align-middle">

                        <template x-if="item.requisitolegalpdf">

                            <a
                                :href="'/uploads/' + item.requisitolegalpdf"
                                target="_blank">

                                <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                            </a>

                        </template>

                        <template x-if="!item.requisitolegalpdf">

                            <i class="ti ti-x fs-7"></i>

                        </template>

                    </td>

                    <td class="text-center align-middle">

                        <b x-text="item.cumplimiento.texto"></b>

                    </td>

                    <td class="text-center align-middle">

                        <small x-text="item.renovacion"></small>

                    </td>

                </tr>

            </template>

            <tr x-show="(nivel.data?.items ?? []).length == 0">

                <td colspan="9" class="text-center text-muted">

                    No se encontró información para mostrar

                </td>

            </tr>

        </tbody>

    </table>
</div>
    <div class="mt-3 mb-4">

        <label class="text-muted">

            % de cumplimiento por nivel de gobierno

        </label>

<div class="progress" style="height: 20px;">

    <div
        class="progress-bar progress-bar-striped progress-bar-animated"
        :class="{
            'text-bg-success': (nivel.data?.porcentaje?.cumple ?? 0) == 100,
            'text-bg-warning': (nivel.data?.porcentaje?.cumple ?? 0) >= 50 && (nivel.data?.porcentaje?.cumple ?? 0) < 100,
            'text-bg-danger': (nivel.data?.porcentaje?.cumple ?? 0) < 50
        }"
        role="progressbar"
        :aria-valuenow="nivel.data?.porcentaje?.cumple ?? 0"
        aria-valuemin="0"
        aria-valuemax="100"
        :style="'width:' + (nivel.data?.porcentaje?.cumple ?? 0) + '%'">

        <span>
            Cumple
            <span x-text="nivel.data?.porcentaje?.cumple ?? 0"></span>%
        </span>
</div>
    </div>

</div>
</div>
</div>

</div>

</template>

    <div class="row mt-3">

    <div class="col-md-6">
        <table class="table table-bordered table-sm mb-0 pb-0">

            <tbody>

                <tr>

                    <td class="fw-bolder bg-light">
                    Calendario anual de renovacion de Requisitos Legales
                    </td>

                    <td width="40" class="text-center">

                        <a href="/sasisopa/reporte/elemento3/pdf?inicio=<?= $fechaInicio ?>&fin=<?= $fechaTermino ?>">

                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                        </a>

                    </td>

                </tr>

            </tbody>

        </table>
    </div>

        <div class="col-md-6">

        <div class="fw-bolder mb-2">
            Fo.ADMONGAS.010 (Registro de la atención y el seguimiento a la comunicación interna y externa.)
        </div>

        <table class="table table-sm table-bordered mb-0 pb-0">
            <thead>
                <tr>
                    <th class="text-center align-middle">#</th>
                    <th class="text-center align-middle">Fecha</th>
                    <th class="text-center align-middle">Hora</th>
                    <th width="40">
                        <i class="ti ti-file-type-pdf text-muted fs-7"></i>
                    </th>
                </tr>
            </thead>
            <tbody>
                <template
                    x-for="(item,index) in elemento3.asistencias"
                    :key="item.id">
                    <tr>
                        <td class="text-center align-middle" x-text="index+1"></td>
                        <td class="text-center align-middle" x-text="item.fecha"></td>
                        <td class="text-center align-middle" x-text="item.hora"></td>
                        <td class="text-center align-middle">
                            <a
                                :href="`/lista-asistencia/pdf/${item.id}`"
                                target="_blank">

                                <i class="ti ti-file-type-pdf text-danger fs-7"></i>
                            </a>

                        </td>

                    </tr>

                </template>

                <template
                    x-if="elemento2.asistencias.length==0">

                    <tr>

                        <td colspan="4" class="text-center text-muted">

                            No se encontró información.

                        </td>

                    </tr>

                </template>

            </tbody>

        </table>

    </div>

<div>





</div>
    </div>

    </div>
</div>
<!-- 3. REQUISITOS LEGALES -->

<!-- 4. OBJETIVOS, METAS E INDICADORES -->
<div class="card">
    <div class="card-body">
    <h4 class="card-title">4. OBJETIVOS, METAS E INDICADORES</h4>

    <div class="row mt-3">

    <div class="col-md-6">
        <table class="table table-bordered table-sm mb-0 pb-0">

            <tbody>

                <tr>

                    <td class="fw-bolder bg-light">
                    Seguimiento de objetivos y metas
                    </td>

                    <td width="40" class="text-center">

                        <a href="/sasisopa/objetivos-metas-indicadores/pdf-objetivos-metas?inicio=<?= $fechaInicio ?>&fin=<?= $fechaTermino ?>">

                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                        </a>

                    </td>

                </tr>

            </tbody>

        </table>
    </div>

        <div class="col-md-6">
        <table class="table table-bordered table-sm mb-0 pb-0">

            <tbody>

                <tr>

                    <td class="fw-bolder bg-light">
                    Seguimiento y reporte de indicadores
                    </td>

                    <td width="40" class="text-center">

                        <a href="/sasisopa/objetivos-metas-indicadores/pdf-reporte-indicadores?inicio=<?= $fechaInicio ?>&fin=<?= $fechaTermino ?>">

                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                        </a>

                    </td>

                </tr>

            </tbody>

        </table>
    </div>

    </div>
    </div>
</div>
<!-- 4. OBJETIVOS, METAS E INDICADORES -->

<!-- 5. FUNCIONES, RESPONSABILIDADES Y AUTORIDAD -->
<div class="card">
    <div class="card-body">
    <h4 class="card-title">5. FUNCIONES, RESPONSABILIDADES Y AUTORIDAD</h4>

    <a class="btn btn-light" href="<?= $organigrama ?? '' ?>" download> <i class="ti ti-download"></i> Descargar Organigrama</a>

    <div class="fw-bolder mt-3 mb-2">
            Fo.ADMONGAS.010 (Registro de la atención y el seguimiento a la comunicación interna y externa.)
        </div>

        <table class="table table-sm table-bordered mb-0 pb-0">
            <thead>
                <tr>
                    <th class="text-center align-middle">#</th>
                    <th class="text-center align-middle">Fecha</th>
                    <th class="text-center align-middle">Hora</th>
                    <th width="40">
                        <i class="ti ti-file-type-pdf text-muted fs-7"></i>
                    </th>
                </tr>
            </thead>
            <tbody>
                <template
                    x-for="(item,index) in elemento5.asistencias"
                    :key="item.id">
                    <tr>
                        <td class="text-center align-middle" x-text="index+1"></td>
                        <td class="text-center align-middle" x-text="item.fecha"></td>
                        <td class="text-center align-middle" x-text="item.hora"></td>
                        <td class="text-center align-middle">
                            <a
                                :href="`/lista-asistencia/pdf/${item.id}`"
                                target="_blank">

                                <i class="ti ti-file-type-pdf text-danger fs-7"></i>
                            </a>

                        </td>

                    </tr>

                </template>

                <template
                    x-if="elemento2.asistencias.length==0">

                    <tr>

                        <td colspan="4" class="text-center text-muted">

                            No se encontró información.

                        </td>

                    </tr>

                </template>

            </tbody>

        </table>

    </div>
</div>
<!-- 5. FUNCIONES, RESPONSABILIDADES Y AUTORIDAD -->

<!-- 6. COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO -->
<div class="card">
    <div class="card-body">
    <h4 class="card-title">6. COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO</h4>

     <a class="btn btn-light" href="/sasisopa/competencia-personal-capacitacion-entrenamiento/ficha-personal-pdf" download> <i class="ti ti-download"></i> Fo.ADMONGAS.008 (Fichas de personal)</a>
    
     <div class="fw-bolder mt-3">
    Fo.ADMONGAS.009 (Programa de Capacitación y Adiestramiento)
    </div>

<div class="text-end mb-2">

    <a
        :href="`/sasisopa/competencia-personal-capacitacion-entrenamiento/pdf-capacitacion-externa?inicio=${fechaInicio}&fin=${fechaTermino}`">

        <i class="ti ti-file-type-pdf text-danger fs-7"></i>

    </a>

</div>

<table class="table table-bordered table-striped table-sm">

    <thead>

        <tr>
            <th class="text-center bg-primary text-white">#</th>
            <th class="text-center bg-primary text-white">Curso</th>
            <th class="text-center bg-primary text-white">Fecha programada</th>
            <th class="text-center bg-primary text-white">Duración</th>
            <th class="text-center bg-primary text-white">Instructor</th>
            <th class="text-center bg-primary text-white">Fecha real</th>
            <th class="text-center bg-primary text-white" width="40"> <i class="ti ti-file-type-pdf text-white fs-7"></i></th>

        </tr>

    </thead>

    <tbody>

        <template
            x-if="elemento6.length==0">

            <tr>

                <td
                    colspan="7"
                    class="text-center text-nuted">

                    No se encontró información para mostrar

                </td>

            </tr>

        </template>

        <template
            x-for="(item,index) in elemento6"
            :key="item.id">

            <tr>

                <td
                    class="text-center"
                    x-text="index+1">
                </td>

                <td
                    class="text-center"
                    x-text="item.curso">
                </td>

                <td
                    class="text-center"
                    x-text="item.fecha_programada">
                </td>

                <td
                    class="text-center"
                    x-text="item.duracion">
                </td>

                <td
                    class="text-center"
                    x-text="item.instructor">
                </td>

                <td class="text-center">

                    <template x-if="item.fecha_real">
                        <span
                            x-text="item.fecha_real">
                        </span>
                    </template>

                    <template x-if="!item.fecha_real">
                        <small class="text-danger">
                            Falta editar la fecha real del curso
                        </small>
                    </template>

                </td>

                <td class="text-center">
                    <a
                        :href="`/sasisopa/competencia-personal-capacitacion-entrenamiento/pdf-capacitacion-externa/${item.id}`"
                        target="_blank">
                        <i class="ti ti-file-type-pdf text-danger fs-7"></i>
                    </a>
                </td>
            </tr>
        </template>
    </tbody>

</table>

    </div>
</div>
<!-- 6. COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO -->

<!-- 7. COMUNICACIÓN, PARTICIPACIÓN Y CONSULTA -->
<div class="card">
    <div class="card-body">
    <h4 class="card-title">7. COMUNICACIÓN, PARTICIPACIÓN Y CONSULTA</h4>

 <div class="row">

    <div class="col-lg-9">

            <b>Registro de la atención y el seguimiento a la comunicación interna y externa.</b>

            <a
                class="float-end"
                :href="`/sasisopa/comunicacion-participacion-consulta/pdf-registro-comunicacion?inicio=${fechaInicio}&fin=${fechaTermino}`">

                <i class="ti ti-file-type-pdf text-danger fs-7"></i>

            </a>

        <table class="table table-bordered table-striped table-sm">

            <thead>

                <tr>

                    <th class="text-center align-middle bg-primary text-white">No.</th>
                    <th class="text-center align-middle bg-primary text-white">Fecha</th>
                    <th class="text-center align-middle bg-primary text-white">Tema</th>
                    <th class="text-center align-middle bg-primary text-white">Encargado</th>
                    <th class="text-center align-middle bg-primary text-white">Tipo</th>
                    <th class="text-center align-middle bg-primary text-white">Material</th>
                    <th class="text-center align-middle bg-primary text-white">Seguimiento</th>
                    <th class="text-center align-middle bg-primary text-white"><i class="ti ti-file-type-pdf text-white fs-7"></i></th>
                </tr>

            </thead>

            <tbody>

                <template
                    x-if="elemento7.comunicaciones.length==0">

                    <tr>

                        <td
                            colspan="8"
                            class="text-center text-muted">

                            No se encontró comunicación interna o externa

                        </td>

                    </tr>

                </template>

                <template
                    x-for="item in elemento7.comunicaciones"
                    :key="item.id">

                    <tr>

                        <td
                            class="text-center align-middle"
                            x-text="item.numero">
                        </td>

                        <td
                            class="text-center align-middle"
                            x-text="item.fecha">
                        </td>

                        <td class="text-center align-middle"
                            x-text="item.tema">
                        </td>

                        <td class="text-center align-middle"
                            x-text="item.encargado">
                        </td>

                        <td class="text-center align-middle"
                            x-text="item.tipo">
                        </td>

                        <td class="text-center align-middle"
                            x-text="item.material">
                        </td>

                        <td class="text-center align-middle"
                            x-text="item.seguimiento">
                        </td>

                        <td class="text-center align-middle">

                            <a
                                :href="`/sasisopa/comunicacion-participacion-consulta/pdf-registro-comunicacion?id=${item.id}`"
                                target="_blank">

                                <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                            </a>

                        </td>

                    </tr>

                </template>

            </tbody>

        </table>

    </div>

    <div class="col-lg-3">

        <div class="fw-bolder">

            Quejas y sugerencias

        </div>

        <table class="table table-bordered table-striped table-sm mt-2">

            <thead>

                <tr>

                    <th class="text-center align-middle">#</th>

                    <th class="text-center align-middle">Fecha</th>

                    <th class="text-center align-middle"><i class="ti ti-file-type-pdf text-muted fs-7"></i></th>

                </tr>

            </thead>

            <tbody>

                <template
                    x-if="elemento7.quejas.length==0">

                    <tr>

                        <td
                            colspan="3"
                            class="text-center text-muted">

                            No se encontró información para mostrar

                        </td>

                    </tr>

                </template>

                <template
                    x-for="item in elemento7.quejas"
                    :key="item.id">

                    <tr>

                        <td
                            class="text-center"
                            x-text="item.numero">
                        </td>

                        <td
                            class="text-center"
                            x-text="item.fecha">
                        </td>

                        <td class="text-center">

                            <a
                                :href="`/sasisopa/comunicacion-participacion-consulta/pdf-quejas-sugerencias/${item.id}`"
                                target="_blank">

                                <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                            </a>

                        </td>

                    </tr>

                </template>

            </tbody>

        </table>

    </div>

</div>

<hr>
    </div>
</div>
<!-- 7. COMUNICACIÓN, PARTICIPACIÓN Y CONSULTA -->

<!-- 8. CONTROL DE DOCUMENTOS Y REGISTROS -->
<div class="card">
    <div class="card-body">
    <h4 class="card-title">8. CONTROL DE DOCUMENTOS Y REGISTROS</h4>

        <div class="row mt-3">

    <div class="col-md-6">
        <table class="table table-bordered table-sm mb-0 pb-0">

            <tbody>

                <tr>

                    <td class="fw-bolder bg-light">
                    Control y documentos de Requisitos Legales
                    </td>

                    <td width="40" class="text-center">

                        <a href="/sasisopa/control-documentos-registros/pdf-requisitos-legales">

                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                        </a>

                    </td>

                </tr>

            </tbody>

        </table>
    </div>

        <div class="col-md-6">
        <table class="table table-bordered table-sm mb-0 pb-0">

            <tbody>

                <tr>

                    <td class="fw-bolder bg-light">
                    Control y documentos del Sistema de Administración
                    </td>

                    <td width="40" class="text-center">

                        <a href="/sasisopa/control-documentos-registros/pdf-sistema-administracion">

                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                        </a>

                    </td>

                </tr>

            </tbody>

        </table>
    </div>

    </div>

    </div>
</div>
<!-- 8. CONTROL DE DOCUMENTOS Y REGISTROS -->

<!-- 9. MEJORES PRÁCTICAS Y ESTÁNDARES -->
<div class="card">
    <div class="card-body">
    <h4 class="card-title">9. MEJORES PRÁCTICAS Y ESTÁNDARES</h4>

    <div class="row mt-3">

    <div class="col-md-6">
        <table class="table table-bordered table-sm mb-0 pb-0">

            <tbody>

                <tr>

                    <td class="fw-bolder bg-light">
                    Diseño y construcción
                    </td>

                    <td width="40" class="text-center">

                        <a href="/sasisopa/mejores-practicas-estandares/pdf-diseno-construccion">

                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                        </a>

                    </td>

                </tr>

            </tbody>

        </table>
    </div>

        <div class="col-md-6">
        <table class="table table-bordered table-sm mb-0 pb-0">

            <tbody>

                <tr>

                    <td class="fw-bolder bg-light">
                    Operación y Mantenimiento
                    </td>

                    <td width="40" class="text-center">

                        <a href="/sasisopa/mejores-practicas-estandares/pdf-operacion-mantenimiento">

                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                        </a>

                    </td>

                </tr>

            </tbody>

        </table>
    </div>

    </div>
    </div>
</div>
<!-- 9. MEJORES PRÁCTICAS Y ESTÁNDARES -->

<!-- 10. CONTROL DE ACTIVIDADES Y PROCESOS -->
<div class="card">
    <div class="card-body">
    <h4 class="card-title">10. CONTROL DE ACTIVIDADES Y PROCESOS</h4>

    <div class="d-flex justify-content-between align-items-center">

                <div class="mb-0">
                    Fo.ADMONGAS.011 (Programa Anual de Mantenimiento)
                    <span x-text="elemento10.year"></span>
                </div>

                <template x-if="elemento10.programa">

                    <a
                        :href="'/sasisopa/control-actividades-procesos/pdf-programa-anual-mantenimiento/' + elemento10.programa.id"
                        target="_blank">

                        <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                    </a>

                </template>

    </div>

    </div>
</div>
<!-- 10. CONTROL DE ACTIVIDADES Y PROCESOS -->

<!-- 11. INTEGRIDAD MECÁNICA Y ASEGURAMIENTO DE LA CALIDAD -->
<div class="card">
    <div class="card-body">
    <h4 class="card-title">11. INTEGRIDAD MECÁNICA Y ASEGURAMIENTO DE LA CALIDAD</h4>

        <div class="d-flex justify-content-between align-items-center">

                <div class="mb-0">
                    Fo.ADMONGAS.011 (Programa Anual de Mantenimiento)
                    <span x-text="elemento10.year"></span>
                </div>

                <template x-if="elemento10.programa">

                    <a
                        :href="'/sasisopa/control-actividades-procesos/pdf-programa-anual-mantenimiento/' + elemento10.programa.id"
                        target="_blank">

                        <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                    </a>

                </template>

    </div>

    <div class="d-flex justify-content-between align-items-center">

                <div class="mb-0">
                    Lista de equipos críticos
                </div>

                <a
                        href="/sasisopa/integridad-mecanica-aseguramiento/pdf-equipo-critico"
                        target="_blank">

                        <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                    </a>

    </div>

    </div>
</div>
<!-- 11. INTEGRIDAD MECÁNICA Y ASEGURAMIENTO DE LA CALIDAD -->

<!-- 12. SEGURIDAD DE CONTRATISTAS -->
<div class="card">
    <div class="card-body">
    <h4 class="card-title">12. SEGURIDAD DE CONTRATISTAS</h4>

    <table class="table table-bordered table-striped table-sm">

        <thead>

        <tr>

            <th class="text-center">Folio</th>
            <th>Fecha</th>
            <th>Solicitante</th>
            <th class="text-center">Fo.ADMONGAS.012</th>
            <th class="text-center">Fo.ADMONGAS.013</th>
            <th class="text-center">Fo.ADMONGAS.014</th>
            <th class="text-center">Fo.ADMONGAS.015</th>
            <th class="text-center">Carta responsiva</th>

        </tr>

        </thead>

        <tbody>

        <template x-if="elemento12.length==0">

            <tr>

                <td colspan="8" class="text-center">

                    <small>No se encontró información para mostrar</small>

                </td>

            </tr>

        </template>

        <template x-for="item in elemento12" :key="item.id">

            <tr>

                <td class="text-center">

                    <b x-text="item.folio"></b>

                </td>

                <td x-text="item.fecha"></td>

                <td x-text="item.solicitante"></td>

                <!-- Formato 12 -->

                <td class="text-center">

                    <template x-if="item.formato12.existe">

                        <a :href="'/sasisopa/seguridad-contratistas/formato12/pdf/' + item.formato12.id">

                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                        </a>

                    </template>

                    <template x-if="!item.formato12.existe">

                        <i class="ti ti-x text-muted"></i>

                    </template>

                </td>

                <!-- Formato 13 -->

                <td class="text-center">

                    <a :href="'/sasisopa/seguridad-contratistas/formato13/' + item.formato13.id">

                        <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                    </a>

                </td>

                <!-- Formato 14 -->

                <td class="text-center">

                    <template x-if="item.formato14.existe">

                        <a :href="'/uploads/'+item.formato14.archivo" target="_blank">

                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                        </a>

                    </template>

                    <template x-if="!item.formato14.existe">

                        <i class="ti ti-x text-muted"></i>

                    </template>

                </td>

                <!-- Formato 15 -->

                <td class="text-center">

                    <template x-if="item.formato15.existe">

                        <a :href="'/sasisopa/seguridad-contratistas/formato15/pdf/' + item.formato15.id">

                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                        </a>

                    </template>

                    <template x-if="!item.formato15.existe">

                        <i class="ti ti-x text-muted"></i>

                    </template>

                </td>

                <!-- Carta -->

                <td class="text-center">

                    <template x-if="item.carta.existe">

                        <a :href="'/sasisopa/seguridad-contratistas/carta-responsiva/pdf/' + item.carta.id">

                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                        </a>

                    </template>

                    <template x-if="!item.carta.existe">

                        <i class="ti ti-x text-muted"></i>

                    </template>

                </td>

            </tr>

        </template>

        </tbody>

    </table>


    </div>
</div>
<!-- 12. SEGURIDAD DE CONTRATISTAS -->

<!-- 13. PREPARACIÓN Y RESPUESTA A EMERGENCIAS -->
<div class="card">
    <div class="card-body">
    <h4 class="card-title">13. PREPARACIÓN Y RESPUESTA A EMERGENCIAS</h4>

    <div x-data="elemento13">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <b>
            Programa anual de simulacros
        </b>

        <a
            :href="`/sasisopa/preparacion-emergencias/simulacro/pdf?inicio=${fechaInicio}&fin=${fechaTermino}`"
            target="_blank">

            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

        </a>

    </div>

    <table class="table table-bordered table-hover table-striped table-sm">

        <thead>

        <tr>

            <th class="text-center align-middle">
                Nombre del simulacro
            </th>

            <th class="text-center align-middle">
                Periodicidad
            </th>

            <th class="text-center align-middle">
                Fecha
            </th>

            <th class="text-center align-middle">
                Personal que asiste
            </th>

            <th class="text-center align-middle">
                Resumen
            </th>

            <th class="text-center align-middle">
                Evaluación (Fo.ADMONGAS.016a)
            </th>

        </tr>

        </thead>

        <tbody>

        <template
            x-if="!loading && elemento13.length==0">

            <tr>

                <td
                    colspan="6"
                    class="text-center align-middle">

                    <small>
                        No se encontró información para mostrar
                    </small>

                </td>

            </tr>

        </template>

        <template
            x-for="item in elemento13"
            :key="item.id">

            <tr>

                <td
                    class="text-center align-middle"
                    x-text="item.nombre_simulacro">
                </td>

                <td
                    class="text-center align-middle"
                    x-text="item.periodicidad">
                </td>

                <td
                    class="text-center align-middle"
                    x-text="item.fecha">
                </td>

                <td
                    class="text-center align-middle"
                    x-text="item.personal.texto">
                </td>

                <td
                    class="text-center align-middle"
                    x-text="item.resumen">
                </td>

                <td class="text-center align-middle">

                    <template
                        x-if="item.evaluacion.existe">

                        <a
                            :href="'/'+item.evaluacion.archivo"
                            target="_blank">

                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                        </a>

                    </template>

                    <template
                        x-if="!item.evaluacion.existe">

                        <i class="ti ti-file-off text-muted fs-4"></i>

                    </template>

                </td>

            </tr>

        </template>

        </tbody>

    </table>

</div>
    </div>
</div>
<!-- 13. PREPARACIÓN Y RESPUESTA A EMERGENCIAS -->

<!-- 14. MONITOREO, VERIFICACIÓN Y EVALUACIÓN -->
<div class="card">
    <div class="card-body">
    <h4 class="card-title">14. MONITOREO, VERIFICACIÓN Y EVALUACIÓN</h4>

<table class="table table-bordered table-sm">
<tr>
<td>
<h6>
Resumen de revisión de resultados
<span x-text="year"></span>
</h6>
</td>

<td width="40">

<a :href="`/sasisopa/monitoreo-verificacion-evaluacion/descargar-revision-resultados-detalle/${year}`">

<i class="ti ti-file-type-pdf text-danger fs-7"></i>

</a>

</td>
</tr>
</table>

<table class="table table-bordered table-sm">

<tr>

<td>

<h6>Programa de implementación del Sistema de Administración</h6>

</td>

<td width="40">

<a href="/sasisopa/monitoreo-verificacion-evaluacion/descargar-programa-implementacion-s-a">

<i class="ti ti-file-type-pdf text-danger fs-7"></i>

</a>

</td>

</tr>

</table>

<h5>Calibración, Verificación y mantenimiento de equipos</h5>

<table class="table table-bordered table-sm">

<tr>

<td>Equipos sometidos a calibración</td>

<td width="40">

<a href="/sasisopa/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos/pdf-equipos-calibracion">

<i class="ti ti-file-type-pdf text-danger fs-7"></i>

</a>

</td>

</tr>

<tr>

<td>Calendario de calibraciones</td>

<td width="40">

<a :href="`/sasisopa/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos/pdf-calendario-calibracion?year=${year}`">

<i class="ti ti-file-type-pdf text-danger fs-7"></i>

</a>

</td>

</tr>

</table>

<h5>Evaluación y cumplimiento de requisitos legales</h5>

<table class="table table-bordered table-sm">

<tr>

<td>Matriz de evaluación del cumplimiento legal</td>

<td width="40">
<a href="/sasisopa/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales/pdf?inicio=<?= $fechaInicio ?>&fin=<?= $fechaTermino ?>">

<i class="ti ti-file-type-pdf text-danger fs-7"></i>

</a>

</td>

</tr>

</table>

<h5>Informe de revisión de resultados</h5>
<table class="table table-bordered table-striped table-sm">
<thead>
    <tr>
    <th class="text-center">#</th>
    <th class="text-center">Fecha</th>
    <th class="text-center"><i class="ti ti-file-type-pdf text-muted fs-7"></i></th>
    </tr>
</thead>

<tbody>
    <template x-if="informes.length===0">
        <tr>
        <td colspan="3" class="text-center text-muted">
        No se encontró información para mostrar
        </td>
        </tr>
    </template>
    <template x-for="(item,index) in informes" :key="item.id">
        <tr>
        <td class="text-center" x-text="index+1"></td>
        <td class="text-center" x-text="item.fecha"></td>
        <td class="text-center">
        <a :href="`/uploads/archivos/informe-revision-resultados/${item.archivo}`" download>
        <i class="ti ti-file-type-pdf text-danger fs-7"></i>
        </a>
        </td>
        </tr>
    </template>
</tbody>
</table>

    <h5>Administración de hallazgos derivados del monitoreo del sistema de administración</h5>
    <table class="table table-bordered table-striped table-sm">
    <thead>
    <tr>
    <th class="text-center">Folio</th>
    <th class="text-center">Fecha auditoría</th>
    <th class="text-center">No. Control</th>
    <th class="text-center">Tipo auditoría</th>
    <th class="text-center"><i class="ti ti-file-type-pdf text-muted fs-7"></i></th>
    </tr>
    </thead>
    <tbody>
    <template x-if="hallazgos.length===0">
    <tr>
    <td colspan="5" class="text-center text-muted">
    No se encontró información para mostrar
    </td>
    </tr>
    </template>

    <template x-for="item in hallazgos" :key="item.id">
        <tr>

            <td class="text-center">
            <b x-text="item.folio"></b>
            </td>

            <td class="text-center" x-text="item.fecha_auditoria"></td>
            <td class="text-center" x-text="item.no_control"></td>
            <td class="text-center" x-text="item.tipo_auditoria"></td>

            <td class="text-center">
            <a :href="`/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos/pdf/${item.id}`">
            <i class="ti ti-file-type-pdf text-danger fs-7"></i>
            </a>
            </td>

        </tr>
    </template>

    </tbody>

    </table>



    </div>
</div>
<!-- 14. MONITOREO, VERIFICACIÓN Y EVALUACIÓN -->

<!-- 15. AUDITORÍAS -->
<div class="card">
    <div class="card-body">
    <h4 class="card-title">15. AUDITORÍAS</h4>

    <table class="table table-bordered table-sm">
    <tr>
    <td class="bg-light align-middle">
    <b>
    Formato Programa de auditorias (Internas y externas)
    <span x-text="year"></span>
    </b>
    </td>

    <td width="40">

    <a :href="`/sasisopa/auditorias/programa/pdf/${yearInicio}/${yearFin}`">

    <i class="ti ti-file-type-pdf text-danger fs-7"></i>

    </a>

    </td>
    </tr>
    </table>

    <table class="table table-sm table-bordered">
    <thead>
    <tr>
    <th class="text-center align-middle">#</th>
    <th class="text-center align-middle">Fecha</th>
    <th class="text-center align-middle">Auditor</th>
    <th class="text-center align-middle">Fo.ADMONGAS.024 (INFORME DE AUDITORÍA)</th>
    <th class="text-center align-middle">Fo.ADMONGAS.025 (PLAN DE ATENCIÓN DE HALLAZGOS)</th>
    </tr>
    </thead>
    <tbody>
    <template
    x-for="item in auditoriasInternas">

    <tr>

    <td class="text-center align-middle" x-text="item.id"></td>
    <td class="text-center align-middle" x-text="item.fecha"></td>
    <td class="text-center align-middle" x-text="item.auditor"></td>
    <td class="text-center align-middle">

        <template x-if="item.formato024">
            <a
                :href="'/uploads/' + item.formato024"
                target="_blank">
                <i class="ti ti-file-type-pdf text-danger fs-7"></i>
            </a>
        </template>

        <template x-if="!item.formato024">
            <i class="ti ti-x text-muted fs-7"></i>
        </template>

    </td>

    <td class="text-center align-middle">
        <template x-if="item.formato025">
        <a
        :href="'/uploads/'+item.formato025"
        target="_blank">
        <i class="ti ti-file-type-pdf text-danger fs-7"></i>
        </a>
        </template>
        <template x-if="!item.formato025">
            <i class="ti ti-x text-muted fs-7"></i>
        </template>

    </td>

    </tr>

    </template>

    </tbody>

    </table>

    <table class="table table-sm table-bordered">
    <thead>
    <tr>
    <th class="text-center align-middle">#</th>
    <th class="text-center align-middle">Fecha</th>
    <th class="text-center align-middle">Prestador de servicio</th>
    <th class="text-center align-middle">Fo.ADMONGAS.024 (INFORME DE AUDITORÍA)</th>
    <th class="text-center align-middle">Fo.ADMONGAS.025 (PLAN DE ATENCIÓN DE HALLAZGOS)</th>
    <th class="text-center align-middle">ASEA</th>
    </tr>
    </thead>
    <tbody>
    <template
    x-for="item in auditoriasExternas">
    <tr>
    <td class="text-center align-middle" x-text="item.id"></td>
    <td class="text-center align-middle" x-text="item.fecha"></td>
    <td class="text-center align-middle" x-text="item.prestador"></td>
    <td class="text-center align-middle">
        <template x-if="item.formato024">
        <a
        :href="'/uploads/'+item.formato024"
        target="_blank">
        <i class="ti ti-file-type-pdf text-danger fs-7"></i>
        </a>
        </template>
        <template x-if="!item.formato024">
            <i class="ti ti-x text-muted fs-7"></i>
        </template>
    </td>
    <td class="text-center align-middle">
        <template x-if="item.formato025">
        <a
        :href="'/uploads/'+item.formato025"
        target="_blank">
        <i class="ti ti-file-type-pdf text-danger fs-7"></i>
        </a>
        </template>
        <template x-if="!item.formato025">
            <i class="ti ti-x text-muted fs-7"></i>
        </template>

    </td>
    <td class="text-center align-middle">
    <button
    class="btn btn-sm btn-primary"
    @click="abrirAsea(item)">
    ASEA
    </button>
    </td>
    </tr>
    </template>

    </tbody>

    </table>

    </div>
</div>
<!-- 15. AUDITORÍAS -->

<!-- 16. INVESTIGACIÓN DE INCIDENTES Y ACCIDENTES -->
<div class="card">
    <div class="card-body">
    <h4 class="card-title">16. INVESTIGACIÓN DE INCIDENTES Y ACCIDENTES</h4>

    <div class="text-end">

        <a href="/sasisopa/investigacion-incidentes-accidentes/pdf?inicio=<?= $fechaInicio ?>&fin=<?= $fechaTermino ?>">
            <i class="ti ti-file-type-pdf text-danger fs-7"></i>
        </a>

    </div>

    <div class="table-responsive mt-2">

        <table class="table table-sm table-bordered table-hover align-middle">

            <thead class="table-light">

                <tr>

                    <th class="text-center">#</th>

                    <th class="text-center">
                        Fecha
                    </th>

                    <th class="text-center">
                        Nombre
                    </th>

                    <th class="text-center">
                        Puesto
                    </th>

                    <th class="text-center">
                        Descripción
                    </th>

                    <th class="text-center">
                        Tipo evento
                    </th>

                    <th class="text-center">
                        Muertes
                    </th>

                    <th class="text-center">
                        Grupo interdisciplinario
                    </th>

                    <th class="text-center">
                        Fo.ADMONGAS.026
                    </th>

                    <th class="text-center">
                        Tercer autorizado
                    </th>

                </tr>

            </thead>

            <tbody>

                <template
                    x-if="loading">
                    <tr>

                        <td
                            colspan="10"
                            class="text-center">
                            Cargando...
                        </td>
                    </tr>
                </template>
                <template
                    x-if="!loading && registros.length==0">
                    <tr>
                        <td
                            colspan="10"
                            class="text-center text-muted">
                            No se encontró información.
                        </td>
                    </tr>
                </template>

                <template
                    x-for="item in registros"
                    :key="item.id">

                    <tr>

                        <td
                            class="text-center fw-bolder"
                            x-text="item.id">
                        </td>

                        <td
                            class="text-center"
                            x-text="item.fecha">
                        </td>

                        <td
                            class="text-center"
                            x-text="item.nombre">
                        </td>

                        <td
                            class="text-center"
                            x-text="item.puesto">
                        </td>

                        <td class="text-center"
                            x-text="item.descripcion">
                        </td>

                        <td
                            class="text-center fw-bold"
                            x-text="item.tipo_evento">
                        </td>

                        <td
                            class="text-center fw-bold"
                            x-text="item.muertes">
                        </td>

                        <!-- Grupo -->

                        <td class="text-center">

                            <button
                                class="btn btn-sm btn-outline-primary"
                                @click="abrirGrupo(item.id)">

                                <i class="ti ti-users fs-4"></i>

                            </button>

                        </td>

                        <!-- PDF -->

                        <td class="text-center">

                            <template
                                x-if="item.formato026">

                                <a
                                    :href="'/uploads/'+item.formato026"
                                    target="_blank">

                                    <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                                </a>

                            </template>

                            <template
                                x-if="!item.formato026">

                                <i
                                    class="ti ti-x text-muted fs-7">
                                </i>

                            </template>

                        </td>

                        <!-- Tercer -->

                        <td class="text-center">

                            <template
                                x-if="item.tercer_autorizado">

                                <button
                                    class="btn btn-sm btn-outline-success"
                                    @click="abrirTercer(item.id)">

                                    <i class="ti ti-user-check fs-4"></i>

                                </button>

                            </template>

                            <template
                                x-if="!item.tercer_autorizado">

                                <i
                                    class="ti ti-x text-muted fs-7">
                                </i>

                            </template>

                        </td>

                    </tr>

                </template>

            </tbody>

        </table>

    </div>


    <h5 class="mt-4">

        Sin accidentes a la fecha

    </h5>

    <div class="table-responsive">

        <table class="table table-sm table-bordered table-hover">

            <thead>

                <tr>

                    <th class="text-center">
                        #
                    </th>
                    <th class="text-center">
                        Fecha
                    </th>
                    <th class="text-center">
                        Responsable
                    </th>
                    <th class="text-center">
                         <i class="ti ti-file-type-pdf text-muted fs-7"></i>
                    </th>
                </tr>
            </thead>
            <tbody>

                <template
                    x-for="item in sinAccidentes"
                    :key="'sa'+item.id">

                    <tr
                        :class="item.estatus==0 ? 'table-warning' : ''">

                        <td
                            class="text-center align-middle fw-bolder"
                            x-text="item.id">
                        </td>

                        <td
                            class="text-center align-middle"
                            x-text="item.fecha">
                        </td>

                        <td
                            class="text-center align-middle"
                            x-text="item.usuario">
                        </td>

                        <td class="text-center align-middle">

                            <a
                                :href="'/sasisopa/investigacion-incidentes-accidentes/no/pdf?id=' + item.id"
                                target="_blank">

                                <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                            </a>

                        </td>

                    </tr>

                </template>

            </tbody>

        </table>

    </div>

    </div>
</div>
<!-- 16. INVESTIGACIÓN DE INCIDENTES Y ACCIDENTES -->

<!-- 17. REVISIÓN DE RESULTADOS -->
<div class="card">
    <div class="card-body">
    <h4 class="card-title">17. REVISIÓN DE RESULTADOS</h4>


    <table class="table table-bordered table-sm">
    <tr>
    <td class="bg-light align-middle">
    <b>
    Resumen de revisión de resultados
    <span x-text="year"></span>
    </b>
    </td>

    <td width="40">

    <a :href="`/sasisopa/monitoreo-verificacion-evaluacion/descargar-revision-resultados-detalle/${year}`">

    <i class="ti ti-file-type-pdf text-danger fs-7"></i>

    </a>

    </td>
    </tr>
    </table>

<div class="table-responsive">

<b>Informe de revisión de resultados</b>
<table class="table table-sm table-bordered table-hover mt-2">

    <thead>

        <tr>

            <th class="text-center">#</th>

            <th class="text-center">
                Fecha
            </th>

            <th class="text-center">
                Nombre completo
            </th>

            <th class="text-center"><i class="ti ti-file-type-pdf text-muted fs-7"></i></th>

        </tr>

    </thead>

    <tbody>

        <template
            x-if="loadingRevision">

            <tr>

                <td
                    colspan="4"
                    class="text-center">

                    Cargando...

                </td>

            </tr>

        </template>

        <template
            x-if="!loadingRevision && revisionResultados.length==0">

            <tr>

                <td
                    colspan="4"
                    class="text-center">

                    No se encontró información.

                </td>

            </tr>

        </template>

        <template
            x-for="item in revisionResultados"
            :key="item.id">

            <tr>

                <td
                    class="text-center align-middle"
                    x-text="item.id">
                </td>

                <td
                    class="text-center align-middle"
                    x-text="item.fecha">
                </td>

                <td
                    class="text-center align-middle"
                    x-text="item.nombre">
                </td>

                <td class="text-center align-middle">

                    <template
                        x-if="item.archivo">

                        <a
                            :href="'/uploads/'+item.archivo"
                            target="_blank">

                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                        </a>

                    </template>

                    <template
                        x-if="!item.archivo">

                        <i
                            class="ti ti-x text-muted fs-7">
                        </i>

                    </template>

                </td>

            </tr>

        </template>

    </tbody>

</table>

</div>
    </div>
</div>
<!-- 17. REVISIÓN DE RESULTADOS -->

<!-- 18. INFORMES DE DESEMPEÑO -->
<div class="card">
    <div class="card-body">
    <h4 class="card-title">18. INFORMES DE DESEMPEÑO</h4>

    <b>Informe de Evaluación de Desempeño (IED)</b>

<table class="table table-sm table-bordered table-hover mt-2">

    <thead>

        <tr>

            <th>#</th>

            <th>Fecha</th>

            <th>Nombre completo</th>

            <th class="text-center"><i class="ti ti-file-type-pdf text-muted fs-7"></i></th>

        </tr>

    </thead>

    <tbody>

        <template
            x-if="!loading18 && evaluaciones.length==0">

            <tr>

                <td
                    colspan="4"
                    class="text-center">

                    No se encontró información.

                </td>

            </tr>

        </template>

        <template
            x-for="item in evaluaciones"
            :key="item.id">

            <tr>

                <td
                    x-text="item.numero">
                </td>

                <td
                    x-text="item.fecha">
                </td>

                <td
                    x-text="item.nombre">
                </td>

                <td class="text-center">

                    <template x-if="item.archivo">

                        <a
                            :href="'/uploads/'+item.archivo"
                            target="_blank">

                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                        </a>

                    </template>

                    <template x-if="!item.archivo">

                        <i class="ti ti-x text-secondary fs-5"></i>

                    </template>

                </td>

            </tr>

        </template>

    </tbody>

</table>

<b>
Control de la implementación de los procedimientos del SASISOPA
(Fo.ADMONGAS.029)
</b>

<table class="table table-sm table-bordered table-hover mt-2">

    <thead>

        <tr>

            <th>#</th>

            <th>Fecha</th>

            <th>Nombre completo</th>

            <th class="text-center"><i class="ti ti-file-type-pdf text-muted fs-7"></i></th>

        </tr>

    </thead>

    <tbody>

        <template
            x-if="!loading18 && implementaciones.length==0">

            <tr>

                <td
                    colspan="4"
                    class="text-center">

                    No se encontró información.

                </td>

            </tr>

        </template>

        <template
            x-for="item in implementaciones"
            :key="item.id">

            <tr>

                <td
                    x-text="item.numero">
                </td>

                <td
                    x-text="item.fecha">
                </td>

                <td
                    x-text="item.nombre">
                </td>

                <td class="text-center">

                    <a
                        :href="'/sasisopa/informes-desempeno/implementacion/pdf/' + item.id">

                        <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                    </a>

                </td>

            </tr>

        </template>

    </tbody>

</table>
    </div>
</div>
<!-- 18. INFORMES DE DESEMPEÑO -->


<div
class="modal fade"
id="modalAsea"
tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

            <h4 class="modal-title text-white">

            Ingresos a la ASEA

            </h4>

            <button
            class="btn-close btn-close-white"
            data-bs-dismiss="modal">
            </button>

            </div>

            <div class="modal-body">

                <table class="table table-bordered">

                    <thead>
                    <tr>
                    <th class="text-center align-middle">#</th>
                    <th class="text-center align-middle">Fecha</th>
                    <th class="text-center align-middle">Comentario</th>
                    <th class="text-center align-middle">Archivo</th>
                    </tr>
                    </thead>

                    <tbody>

                    <template
                    x-for="item in ingresosAsea">

                    <tr>

                    <td class="text-center align-middle" x-text="item.id"></td>

                    <td class="text-center align-middle" x-text="item.fecha"></td>

                    <td class="text-center align-middle" x-text="item.comentario"></td>

                    <td class="text-center align-middle">

                    <a
                    :href="'/uploads/'+item.archivo"
                    target="_blank">

                    <i class="ti ti-file-type-pdf text-danger fs-7"></i>

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

<!-- ========================================================= -->
<!-- MODAL GRUPO INTERDISCIPLINARIO -->
<!-- ========================================================= -->

<div
    class="modal fade"
    id="modalGrupoInterdisciplinario"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
                    Grupo interdisciplinario
                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="table-responsive">

                    <table class="table table-sm table-bordered table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th class="text-center">
                                    #
                                </th>

                                <th class="text-center">
                                    Nombre
                                </th>

                                <th class="text-center">
                                    Puesto
                                </th>

                                <th class="text-center">
                                    Especialidad
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <template
                                x-if="grupo.length==0">

                                <tr>

                                    <td
                                        colspan="4"
                                        class="text-center text-muted">

                                        No se encontró información

                                    </td>

                                </tr>

                            </template>

                            <template
                                x-for="item in grupo"
                                :key="item.id">

                                <tr>

                                    <td
                                        class="text-center"
                                        x-text="item.id">
                                    </td>

                                    <td class="text-center"
                                        x-text="item.nombre">
                                    </td>

                                    <td class="text-center"
                                        x-text="item.puesto">
                                    </td>

                                    <td class="text-center"
                                        x-text="item.especialidad">
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

<!-- ========================================================= -->
<!-- MODAL TERCER AUTORIZADO -->
<!-- ========================================================= -->

<div
    class="modal fade"
    id="modalTercerAutorizado"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h5 class="modal-title text-white">

                    Tercer autorizado

                </h5>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div
                class="modal-body"
                x-show="tercer">

                <div class="row mb-4">

                    <div class="col-md-6">

                        <label class="form-label fw-bold">

                            * Nombre del tercer autorizado: 

                        </label>

                        <div
                            class="border rounded p-2"
                            x-text="tercer.nombre">
                        </div>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label fw-bold">

                             * Numero de autorización: 

                        </label>

                        <div
                            class="border rounded p-2"
                            x-text="tercer.numero">
                        </div>

                    </div>

                    <div class="col-md-6 mt-2">

                        <label class="form-label fw-bold">

                             * Nombre del líder de la investigación: 
                        </label>

                        <div
                            class="border rounded p-2"
                            x-text="tercer.lider">
                        </div>

                    </div>

                </div>

                <h6>

                     Informe final de la investigación causa raíz 

                </h6>

                <div class="table-responsive mt-3">

                    <table class="table table-sm table-bordered table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th class="text-center">

                                    #

                                </th>

                                <th class="text-center">

                                    Fecha

                                </th>

                                <th class="text-center">

                                    Archivo

                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <template
                                x-if="!tercer.archivo">

                                <tr>

                                    <td
                                        colspan="3"
                                        class="text-center text-muted">

                                        No se encontró información

                                    </td>

                                </tr>

                            </template>

                            <template
                                x-if="tercer.archivo">

                                <tr>

                                    <td
                                        class="text-center"
                                        x-text="tercer.id">
                                    </td>

                                    <td
                                        class="text-center"
                                        x-text="tercer.fecha">
                                    </td>

                                    <td class="text-center">

                                        <a
                                            :href="'/uploads/'+tercer.archivo"
                                            target="_blank">

                                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                                        </a>

                                    </td>

                                </tr>

                            </template>

                        </tbody>

                    </table>

                </div>

            </div>

            <div
                class="modal-body text-center text-muted"
                x-show="!tercer">

                No se encontró información

            </div>

        </div>

    </div>

</div>

</div>