<div id="container" class="pb-4 sasisopa-reporte"
x-data="reporte('<?= $fechaInicio ?>', '<?= $fechaTermino ?>')">

<!-- 1. POLÍTICA -->
<div class="sasisopa-report-block mt-3">
    <div class="sasisopa-report-heading">
        <h4 class="card-title sasisopa-section-title w-100"><span class="badge rounded-pill text-bg-primary">1</span><span>POLÍTICA</span></h4>
    </div>



    <div class="sasisopa-report-content">
        <a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" href="/sasisopa/politica/pdf">
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Política</h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>

<div class="row mt-2">
    <div class="col-md-6">
<div class="card sasisopa-report-block">
<div class="card-header sasisopa-table-heading bg-primary align-middle">
      <h5 class="text-white mb-0">
      <i class="ti ti-label"></i>
      Fo.ADMONGAS.001 (Lista de comprobación)</h5>
</div>


<div class="card-body sasisopa-report-content">
        <div class="table-responsive pb-2 overflow-x-auto overflow-y-hidden"><table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">
            <thead>
                <th class="text-center">#</th>
                <th class="text-center">Fecha</th>
                <th class="text-center sasisopa-pdf-column" style="width:48px; min-width:48px; max-width:48px" data-orderable="false" data-searchable="false"><i class="ti ti-file-type-pdf text-muted fs-7"></i></th>
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
</div>

<div class="col-md-6">
<div class="card sasisopa-report-block">

<div class="card-header sasisopa-table-heading bg-primary align-middle">
      <h5 class="text-white mb-0">
      <i class="ti ti-label"></i>
      Fo.ADMONGAS.010 (Registro de la atención y el seguimiento a la comunicación interna y externa.)</h5>
</div>



        <div class="card-body sasisopa-report-content">
        <div class="table-responsive pb-2 overflow-x-auto overflow-y-hidden"><table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">
            <thead>
                <th class="text-center">#</th>
                <th class="text-center">Fecha</th>
                <th class="text-center">Hora</th>
                <th class="text-center sasisopa-pdf-column" style="width:48px; min-width:48px; max-width:48px" data-orderable="false" data-searchable="false"><i class="ti ti-file-type-pdf text-muted fs-7"></i></th>
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
</div>
<!-- 1. POLÍTICA -->


<!-- 2. ANÁLISIS DE RIESGO Y EVALUACIÓN DE IMPACTOS AMBIENTALES -->
<!---- card principal header----->
<div class="sasisopa-report-block">

    <div class="sasisopa-report-heading">
 <h4 class="card-title sasisopa-section-title"><span class="badge rounded-pill text-bg-primary">2</span><span>ANÁLISIS DE RIESGO Y EVALUACIÓN DE IMPACTOS AMBIENTALES</span></h4>
    </div>

<!----card  principal body---->
    <div class="sasisopa-report-content">
    <div class="row mt-2">

    <div class="col-md-6">

        <a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" href="/sasisopa/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales/aspectos-ambientales-pdf" download>
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Identificación y evaluación de Aspectos e Impactos Ambientales.</h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>

<!------Card 1----->
        <div class="card sasisopa-report-block">
            <div class="card-header sasisopa-table-heading bg-primary align-middle">
        <h5 class="text-white mb-0">
            <i class="ti ti-label"></i>
            Análisis de Riesgo del Sector Hidrocarburos (ARSH)
        </h5>
        </div>
<div class="card-body sasisopa-report-content">
        <div class="table-responsive pb-2 overflow-x-auto overflow-y-hidden"><table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">

            <thead>
                <tr>
                    <th class="text-center">#</th>
                    <th class="text-center">Fecha</th>
                    <th class="text-center">Descripción</th>
                    <th class="text-center sasisopa-pdf-column" style="width:48px; min-width:48px; max-width:48px" data-orderable="false" data-searchable="false">
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
                            <template x-if="item.documento_disponible">
                            <a :href="item.documento_url" target="_blank">
                                <i class="ti ti-file-type-pdf text-danger fs-6"></i>
                            </a>
                            </template>
                            <template x-if="!item.documento_disponible">
                                <i class="ti ti-file-off fs-6 text-muted" title="Archivo no disponible"></i>
                            </template>
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
</div>

<div class="col-md-6">


            <a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" href="/sasisopa/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales/riesgos-peligros-pdf">
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Identificación y evaluación de Riesgos y Peligros para registrar el análisis.</h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>



<!-------card 2----->
<div class="card sasisopa-report-block">
    <div class="card-header sasisopa-table-heading bg-primary align-middle">
        <h5 class="text-white mb-0 d-flex align-items-center">
            <i class="ti ti-label me-2"></i>
            <span> (Registro de la atención y el seguimiento a la comunicación interna y externa.)</span>
        </h5>
</div>
<div class="card-body sasisopa-report-content">
        <div class="table-responsive pb-2 overflow-x-auto overflow-y-hidden"><table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">
            <thead>
                <tr>
                    <th class="text-center align-middle">#</th>
                    <th class="text-center align-middle">Fecha</th>
                    <th class="text-center align-middle">Hora</th>
                    <th class="sasisopa-pdf-column text-center" style="width:48px; min-width:48px; max-width:48px" data-orderable="false" data-searchable="false">
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
</div>
<!-- 2. ANÁLISIS DE RIESGO Y EVALUACIÓN DE IMPACTOS AMBIENTALES -->



<!-- 3. REQUISITOS LEGALES -->
<div class="sasisopa-report-block">

    <div class="sasisopa-report-heading">
    <h4 class="card-title sasisopa-section-title"><span class="badge rounded-pill text-bg-primary">3</span><span>REQUISITOS LEGALES</span></h4>
    </div>


    <div class="sasisopa-report-content">

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
<div class="sasisopa-report-block">
<div class="sasisopa-report-heading">


<div class="card">

            <div class="card-header sasisopa-table-heading bg-primary align-middle">
    <h5 class="text-white" x-text="nivel.titulo"></h5>

        </div>


<div class="card-body">




<!------cuerpo card----->
    <div class="sasisopa-report-content">
    <div class="table-responsive">
    <table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">

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

                        <template x-if="item.acusepdf_disponible">

                            <a
                                :href="item.acusepdf_url"
                                target="_blank">

                                <i class="ti ti-file-type-pdf text-danger fs-6"></i>

                            </a>

                        </template>

                        <template x-if="!item.acusepdf_disponible">

                            <i class="ti ti-file-off fs-6 text-muted" title="Archivo no disponible"></i>

                        </template>

                    </td>

                    <td class="text-center align-middle">

                        <template x-if="item.requisitolegalpdf_disponible">

                            <a
                                :href="item.requisitolegalpdf_url"
                                target="_blank">

                                <i class="ti ti-file-type-pdf text-danger fs-6"></i>

                            </a>

                        </template>

                        <template x-if="!item.requisitolegalpdf_disponible">

                            <i class="ti ti-file-off fs-6 text-muted" title="Archivo no disponible"></i>

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
</div>

</div>

</template>

    <div class="row mt-3">

    <div class="col-12">
        <a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" href="/sasisopa/reporte/elemento3/pdf?inicio=<?= $fechaInicio ?>&amp;fin=<?= $fechaTermino ?>">
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Calendario anual de renovacion de Requisitos Legales</h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>
    </div>

        <div class="col-md-6">

        <div class="card sasisopa-report-table-card mb-3">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title text-white mb-0"><i class="ti ti-label me-2"></i>Fo.ADMONGAS.010 (Registro de la atención y el seguimiento a la comunicación interna y externa.)</h5>
            </div>
            <div class="card-body">
        <div class="table-responsive pb-2 overflow-x-auto overflow-y-hidden"><table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">
            <thead>
                <tr>
                    <th class="text-center align-middle">#</th>
                    <th class="text-center align-middle">Fecha</th>
                    <th class="text-center align-middle">Hora</th>
                    <th class="sasisopa-pdf-column text-center" style="width:48px; min-width:48px; max-width:48px" data-orderable="false" data-searchable="false">
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
            </div>
        </div>

    </div>

<div>





</div>
    </div>

    </div>
</div>
<!-- 3. REQUISITOS LEGALES -->

<!-- 4. OBJETIVOS, METAS E INDICADORES -->
<div class="sasisopa-report-block">
    <div class="sasisopa-report-content">
    <h4 class="card-title sasisopa-section-title"><span class="badge rounded-pill text-bg-primary">4</span><span>OBJETIVOS, METAS E INDICADORES</span></h4>

    <div class="row mt-3">

    <div class="col-md-6">
        <a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" href="/sasisopa/objetivos-metas-indicadores/pdf-objetivos-metas?inicio=<?= $fechaInicio ?>&amp;fin=<?= $fechaTermino ?>">
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Seguimiento de objetivos y metas</h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>
    </div>

        <div class="col-md-6">
        <a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" href="/sasisopa/objetivos-metas-indicadores/pdf-reporte-indicadores?inicio=<?= $fechaInicio ?>&amp;fin=<?= $fechaTermino ?>">
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Seguimiento y reporte de indicadores</h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>
    </div>

    </div>
    </div>
</div>
<!-- 4. OBJETIVOS, METAS E INDICADORES -->

<!-- 5. FUNCIONES, RESPONSABILIDADES Y AUTORIDAD -->
<div class="sasisopa-report-block">
    <div class="sasisopa-report-content">
    <h4 class="card-title sasisopa-section-title"><span class="badge rounded-pill text-bg-primary">5</span><span>FUNCIONES, RESPONSABILIDADES Y AUTORIDAD</span></h4>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 d-flex">
            <a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" href="<?= $organigrama ?? '' ?>" download>
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-download text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Organigrama</h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>
        </div>
    </div>

    <div class="card sasisopa-report-table-card mt-3 mb-3">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title text-white mb-0"><i class="ti ti-label me-2"></i>Fo.ADMONGAS.010 (Registro de la atención y el seguimiento a la comunicación interna y externa.)</h5>
        </div>
        <div class="card-body">
        <div class="table-responsive pb-2 overflow-x-auto overflow-y-hidden"><table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">
            <thead>
                <tr>
                    <th class="text-center align-middle">#</th>
                    <th class="text-center align-middle">Fecha</th>
                    <th class="text-center align-middle">Hora</th>
                    <th class="sasisopa-pdf-column text-center" style="width:48px; min-width:48px; max-width:48px" data-orderable="false" data-searchable="false">
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
    </div>

    </div>
</div>
<!-- 5. FUNCIONES, RESPONSABILIDADES Y AUTORIDAD -->

<!-- 6. COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO -->
<div class="sasisopa-report-block">
    <div class="sasisopa-report-content">
    <h4 class="card-title sasisopa-section-title"><span class="badge rounded-pill text-bg-primary">6</span><span>COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO</span></h4>

     <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 d-flex">
            <a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" href="/sasisopa/competencia-personal-capacitacion-entrenamiento/ficha-personal-pdf" download>
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-download text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Fo.ADMONGAS.008 (Fichas de personal)</h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>
        </div>
    </div>

     <a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" :href="`/sasisopa/competencia-personal-capacitacion-entrenamiento/pdf-capacitacion-externa?inicio=${fechaInicio}&fin=${fechaTermino}`">
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Fo.ADMONGAS.009 (Programa de Capacitación y Adiestramiento)</h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>

<div class="table-responsive pb-2 overflow-x-auto overflow-y-hidden"><table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">

    <thead>

        <tr>
            <th class="text-center">#</th>
            <th class="text-center">Curso</th>
            <th class="text-center">Fecha programada</th>
            <th class="text-center">Duración</th>
            <th class="text-center">Instructor</th>
            <th class="text-center">Fecha real</th>
            <th class="text-center sasisopa-pdf-column" style="width:48px; min-width:48px; max-width:48px" data-orderable="false" data-searchable="false"> <i class="ti ti-file-type-pdf text-white fs-7"></i></th>

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
</div>
<!-- 6. COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO -->

<!-- 7. COMUNICACIÓN, PARTICIPACIÓN Y CONSULTA -->
<div class="sasisopa-report-block">
    <div class="sasisopa-report-content">
    <h4 class="card-title sasisopa-section-title"><span class="badge rounded-pill text-bg-primary">7</span><span>COMUNICACIÓN, PARTICIPACIÓN Y CONSULTA</span></h4>

 <div class="row">

    <div class="col-lg-9">

            <a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" :href="`/sasisopa/comunicacion-participacion-consulta/pdf-registro-comunicacion?inicio=${fechaInicio}&fin=${fechaTermino}`">
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Registro de la atención y el seguimiento a la comunicación interna y externa.</h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>

        <div class="table-responsive pb-2 overflow-x-auto overflow-y-hidden"><table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">

            <thead>

                <tr>

                    <th class="text-center align-middle">No.</th>
                    <th class="text-center align-middle">Fecha</th>
                    <th class="text-center align-middle">Tema</th>
                    <th class="text-center align-middle">Encargado</th>
                    <th class="text-center align-middle">Tipo</th>
                    <th class="text-center align-middle">Material</th>
                    <th class="text-center align-middle">Seguimiento</th>
                    <th class="text-center align-middle sasisopa-pdf-column" style="width:48px; min-width:48px; max-width:48px" data-orderable="false" data-searchable="false"><i class="ti ti-file-type-pdf text-white fs-7"></i></th>
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

    </div>

    <div class="col-lg-3">

        <div class="card sasisopa-report-table-card mb-3">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title text-white mb-0"><i class="ti ti-message me-2"></i>Quejas y sugerencias</h5>
            </div>
            <div class="card-body">
        <div class="table-responsive pb-2 overflow-x-auto overflow-y-hidden"><table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">

            <thead>

                <tr>

                    <th class="text-center align-middle">#</th>

                    <th class="text-center align-middle">Fecha</th>

                    <th class="text-center align-middle sasisopa-pdf-column" style="width:48px; min-width:48px; max-width:48px" data-orderable="false" data-searchable="false"><i class="ti ti-file-type-pdf text-muted fs-7"></i></th>

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
        </div>

    </div>

</div>

<hr>
    </div>
</div>
<!-- 7. COMUNICACIÓN, PARTICIPACIÓN Y CONSULTA -->

<!-- 8. CONTROL DE DOCUMENTOS Y REGISTROS -->
<div class="sasisopa-report-block">
    <div class="sasisopa-report-content">
    <h4 class="card-title sasisopa-section-title"><span class="badge rounded-pill text-bg-primary">8</span><span>CONTROL DE DOCUMENTOS Y REGISTROS</span></h4>

        <div class="row mt-3">

    <div class="col-md-6">
        <a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" href="/sasisopa/control-documentos-registros/pdf-requisitos-legales">
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Control y documentos de Requisitos Legales</h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>
    </div>

        <div class="col-md-6">
        <a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" href="/sasisopa/control-documentos-registros/pdf-sistema-administracion">
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Control y documentos del Sistema de Administración</h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>
    </div>

    </div>

    </div>
</div>
<!-- 8. CONTROL DE DOCUMENTOS Y REGISTROS -->

<!-- 9. MEJORES PRÁCTICAS Y ESTÁNDARES -->
<div class="sasisopa-report-block">
    <div class="sasisopa-report-content">
    <h4 class="card-title sasisopa-section-title"><span class="badge rounded-pill text-bg-primary">9</span><span>MEJORES PRÁCTICAS Y ESTÁNDARES</span></h4>

    <div class="row mt-3">

    <div class="col-md-6">
        <a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" href="/sasisopa/mejores-practicas-estandares/pdf-diseno-construccion">
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Diseño y construcción</h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>
    </div>

        <div class="col-md-6">
        <a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" href="/sasisopa/mejores-practicas-estandares/pdf-operacion-mantenimiento">
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Operación y Mantenimiento</h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>
    </div>

    </div>
    </div>
</div>
<!-- 9. MEJORES PRÁCTICAS Y ESTÁNDARES -->

<!-- 10. CONTROL DE ACTIVIDADES Y PROCESOS -->
<div class="sasisopa-report-block">
    <div class="sasisopa-report-content">
    <h4 class="card-title sasisopa-section-title"><span class="badge rounded-pill text-bg-primary">10</span><span>CONTROL DE ACTIVIDADES Y PROCESOS</span></h4>

    <div class="card w-100 border-0 overflow-hidden position-relative card-hover">
        <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                    <i class="ti ti-file-type-pdf text-white display-6"></i>
                </div>
                <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                    <h4 class="fw-bold text-dark mb-0 lh-sm">Fo.ADMONGAS.011 (Programa Anual de Mantenimiento) <span x-text="elemento10.year"></span></h4>
                </div>
            </div>
        </div>
        <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
            <span class="small">Descargar documento</span>
            <div class="icon-transition"><template x-if="elemento10.programa"><a :href="'/sasisopa/control-actividades-procesos/pdf-programa-anual-mantenimiento/' + elemento10.programa.id" target="_blank" aria-label="Descargar programa anual de mantenimiento"><i class="ti ti-download fs-5"></i></a></template></div>
        </div>
    </div>

    </div>
</div>
<!-- 10. CONTROL DE ACTIVIDADES Y PROCESOS -->

<!-- 11. INTEGRIDAD MECÁNICA Y ASEGURAMIENTO DE LA CALIDAD -->
<div class="sasisopa-report-block">
    <div class="sasisopa-report-content">
    <h4 class="card-title sasisopa-section-title"><span class="badge rounded-pill text-bg-primary">11</span><span>INTEGRIDAD MECÁNICA Y ASEGURAMIENTO DE LA CALIDAD</span></h4>

        <div class="card w-100 border-0 overflow-hidden position-relative card-hover">
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                        <i class="ti ti-file-type-pdf text-white display-6"></i>
                    </div>
                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">Fo.ADMONGAS.011 (Programa Anual de Mantenimiento) <span x-text="elemento10.year"></span></h4>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Descargar documento</span>
                <div class="icon-transition"><template x-if="elemento10.programa"><a :href="'/sasisopa/control-actividades-procesos/pdf-programa-anual-mantenimiento/' + elemento10.programa.id" target="_blank" aria-label="Descargar programa anual de mantenimiento"><i class="ti ti-download fs-5"></i></a></template></div>
            </div>
        </div>

        <a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" href="/sasisopa/integridad-mecanica-aseguramiento/pdf-equipo-critico" target="_blank">
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Lista de equipos críticos</h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>

    </div>
</div>
<!-- 11. INTEGRIDAD MECÁNICA Y ASEGURAMIENTO DE LA CALIDAD -->

<!-- 12. SEGURIDAD DE CONTRATISTAS -->
<div class="sasisopa-report-block">
    <div class="sasisopa-report-content">
    <h4 class="card-title sasisopa-section-title"><span class="badge rounded-pill text-bg-primary">12</span><span>SEGURIDAD DE CONTRATISTAS</span></h4>

    <div class="table-responsive pb-2 overflow-x-auto overflow-y-hidden"><table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">

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

                    <template x-if="item.formato14.archivo_disponible">

                        <a :href="item.formato14.archivo_url" target="_blank">

                            <i class="ti ti-file-type-pdf text-danger fs-6"></i>

                        </a>

                    </template>

                    <template x-if="!item.formato14.archivo_disponible">

                        <i class="ti ti-file-off fs-6 text-muted" title="Archivo no disponible"></i>

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
</div>
<!-- 12. SEGURIDAD DE CONTRATISTAS -->

<!-- 13. PREPARACIÓN Y RESPUESTA A EMERGENCIAS -->
<div class="sasisopa-report-block">
    <div class="sasisopa-report-content">
    <h4 class="card-title sasisopa-section-title"><span class="badge rounded-pill text-bg-primary">13</span><span>PREPARACIÓN Y RESPUESTA A EMERGENCIAS</span></h4>

    <div x-data="elemento13">

    <a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" :href="`/sasisopa/preparacion-emergencias/simulacro/pdf?inicio=${fechaInicio}&fin=${fechaTermino}`" target="_blank">
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Programa anual de simulacros</h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>

    <div class="table-responsive pb-2 overflow-x-auto overflow-y-hidden"><table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">

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
                            :href="item.evaluacion.archivo_url"
                            target="_blank">

                            <i class="ti ti-file-type-pdf text-danger fs-6"></i>

                        </a>

                    </template>

                    <template
                        x-if="!item.evaluacion.existe">

                        <i class="ti ti-file-off text-muted fs-6" title="Archivo no disponible"></i>

                    </template>

                </td>

            </tr>

        </template>

        </tbody>

    </table>
</div>

</div>
    </div>
</div>
<!-- 13. PREPARACIÓN Y RESPUESTA A EMERGENCIAS -->

<!-- 14. MONITOREO, VERIFICACIÓN Y EVALUACIÓN -->
<div class="sasisopa-report-block">
    <div class="sasisopa-report-content">
    <h4 class="card-title sasisopa-section-title"><span class="badge rounded-pill text-bg-primary">14</span><span>MONITOREO, VERIFICACIÓN Y EVALUACIÓN</span></h4>

<a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" :href="`/sasisopa/monitoreo-verificacion-evaluacion/descargar-revision-resultados-detalle/${year}`">
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Resumen de revisión de resultados <span x-text="year"></span></h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>

<a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" href="/sasisopa/monitoreo-verificacion-evaluacion/descargar-programa-implementacion-s-a">
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Programa de implementación del Sistema de Administración</h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>

<div class="card sasisopa-report-table-card mt-3 mb-3">
    <div class="card-header bg-primary text-white">
        <h5 class="card-title text-white mb-0"><i class="ti ti-tool me-2"></i>Calibración, Verificación y mantenimiento de equipos</h5>
    </div>
    <div class="card-body">
<div class="table-responsive pb-2 overflow-x-auto overflow-y-hidden"><table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">

<tr>

<td>Equipos sometidos a calibración</td>

<td class="sasisopa-pdf-column text-center align-middle" style="width:48px">

<a href="/sasisopa/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos/pdf-equipos-calibracion">

<i class="ti ti-file-type-pdf text-danger fs-7"></i>

</a>

</td>

</tr>

<tr>

<td>Calendario de calibraciones</td>

<td class="sasisopa-pdf-column text-center align-middle" style="width:48px">

<a :href="`/sasisopa/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos/pdf-calendario-calibracion?year=${year}`">

<i class="ti ti-file-type-pdf text-danger fs-7"></i>

</a>

</td>

</tr>

</table>
</div>

</div>
</div>

<h5>Evaluación y cumplimiento de requisitos legales</h5>

<a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" href="/sasisopa/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales/pdf?inicio=<?= $fechaInicio ?>&amp;fin=<?= $fechaTermino ?>">
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Matriz de evaluación del cumplimiento legal</h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>

<div class="card sasisopa-report-table-card mt-3 mb-3">
    <div class="card-header bg-primary text-white">
        <h5 class="card-title text-white mb-0"><i class="ti ti-file-report me-2"></i>Informe de revisión de resultados</h5>
    </div>
    <div class="card-body">
<div class="table-responsive pb-2 overflow-x-auto overflow-y-hidden"><table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">
<thead>
    <tr>
    <th class="text-center">#</th>
    <th class="text-center">Fecha</th>
    <th class="text-center sasisopa-pdf-column" style="width:48px; min-width:48px; max-width:48px" data-orderable="false" data-searchable="false"><i class="ti ti-file-type-pdf text-muted fs-7"></i></th>
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
        <template x-if="item.archivo_disponible">
            <a :href="item.archivo_url" download>
                <i class="ti ti-file-type-pdf text-danger fs-6"></i>
            </a>
        </template>
        <template x-if="!item.archivo_disponible">
            <i class="ti ti-file-off fs-6 text-muted" title="Archivo no disponible"></i>
        </template>
        </td>
        </tr>
    </template>
</tbody>
</table>
</div>

    </div>
</div>

    <div class="card sasisopa-report-table-card mt-3 mb-3">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title text-white mb-0"><i class="ti ti-alert-triangle me-2"></i>Administración de hallazgos derivados del monitoreo del sistema de administración</h5>
        </div>
        <div class="card-body">
    <div class="table-responsive pb-2 overflow-x-auto overflow-y-hidden"><table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">
    <thead>
    <tr>
    <th class="text-center">Folio</th>
    <th class="text-center">Fecha auditoría</th>
    <th class="text-center">No. Control</th>
    <th class="text-center">Tipo auditoría</th>
    <th class="text-center sasisopa-pdf-column" style="width:48px; min-width:48px; max-width:48px" data-orderable="false" data-searchable="false"><i class="ti ti-file-type-pdf text-muted fs-7"></i></th>
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
</div>
<!-- 14. MONITOREO, VERIFICACIÓN Y EVALUACIÓN -->

<!-- 15. AUDITORÍAS -->
<div class="sasisopa-report-block">
    <div class="sasisopa-report-content">
    <h4 class="card-title sasisopa-section-title"><span class="badge rounded-pill text-bg-primary">15</span><span>AUDITORÍAS</span></h4>

    <a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" :href="`/sasisopa/auditorias/programa/pdf/${yearInicio}/${yearFin}`">
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Formato Programa de auditorias (Internas y externas) <span x-text="year"></span></h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>

    <div class="table-responsive pb-2 overflow-x-auto overflow-y-hidden"><table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">
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

        <template x-if="item.formato024_disponible">
            <a
                :href="item.formato024_url"
                target="_blank">
                <i class="ti ti-file-type-pdf text-danger fs-6"></i>
            </a>
        </template>

        <template x-if="!item.formato024_disponible">
            <i class="ti ti-file-off fs-6 text-muted" title="Archivo no disponible"></i>
        </template>

    </td>

    <td class="text-center align-middle">
        <template x-if="item.formato025_disponible">
        <a
        :href="item.formato025_url"
        target="_blank">
        <i class="ti ti-file-type-pdf text-danger fs-6"></i>
        </a>
        </template>
        <template x-if="!item.formato025_disponible">
            <i class="ti ti-file-off fs-6 text-muted" title="Archivo no disponible"></i>
        </template>

    </td>

    </tr>

    </template>

    </tbody>

    </table>
</div>

    <div class="table-responsive pb-2 overflow-x-auto overflow-y-hidden"><table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">
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
        <template x-if="item.formato024_disponible">
        <a
        :href="item.formato024_url"
        target="_blank">
        <i class="ti ti-file-type-pdf text-danger fs-6"></i>
        </a>
        </template>
        <template x-if="!item.formato024_disponible">
            <i class="ti ti-file-off fs-6 text-muted" title="Archivo no disponible"></i>
        </template>
    </td>
    <td class="text-center align-middle">
        <template x-if="item.formato025_disponible">
        <a
        :href="item.formato025_url"
        target="_blank">
        <i class="ti ti-file-type-pdf text-danger fs-6"></i>
        </a>
        </template>
        <template x-if="!item.formato025_disponible">
            <i class="ti ti-file-off fs-6 text-muted" title="Archivo no disponible"></i>
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
</div>
<!-- 15. AUDITORÍAS -->

<!-- 16. INVESTIGACIÓN DE INCIDENTES Y ACCIDENTES -->
<div class="sasisopa-report-block">
    <div class="sasisopa-report-content">
    <h4 class="card-title sasisopa-section-title"><span class="badge rounded-pill text-bg-primary">16</span><span>INVESTIGACIÓN DE INCIDENTES Y ACCIDENTES</span></h4>

    <div class="text-end">

        <a href="/sasisopa/investigacion-incidentes-accidentes/pdf?inicio=<?= $fechaInicio ?>&fin=<?= $fechaTermino ?>">
            <i class="ti ti-file-type-pdf text-danger fs-7"></i>
        </a>

    </div>

    <div class="table-responsive mt-2">

        <table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">

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
                                x-if="item.archivo_disponible">

                                <a
                                    :href="item.archivo_url"
                                    target="_blank">

                                    <i class="ti ti-file-type-pdf text-danger fs-6"></i>

                                </a>

                            </template>

                            <template
                                x-if="!item.archivo_disponible">

                                <i class="ti ti-file-off fs-6 text-muted" title="Archivo no disponible"></i>

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


    <div class="card sasisopa-report-table-card mt-3 mb-3">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title text-white mb-0"><i class="ti ti-alert-circle me-2"></i>Sin accidentes a la fecha</h5>
        </div>
        <div class="card-body">
    <div class="table-responsive">

        <table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">

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
                    <th class="text-center sasisopa-pdf-column" style="width:48px; min-width:48px; max-width:48px" data-orderable="false" data-searchable="false">
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

    </div>
</div>
<!-- 16. INVESTIGACIÓN DE INCIDENTES Y ACCIDENTES -->

<!-- 17. REVISIÓN DE RESULTADOS -->
<div class="sasisopa-report-block">
    <div class="sasisopa-report-content">
    <h4 class="card-title sasisopa-section-title"><span class="badge rounded-pill text-bg-primary">17</span><span>REVISIÓN DE RESULTADOS</span></h4>


    <a class="card w-100 border-0 overflow-hidden position-relative card-hover text-decoration-none" :href="`/sasisopa/monitoreo-verificacion-evaluacion/descargar-revision-resultados-detalle/${year}`">
    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.1rem;">
                <i class="ti ti-file-type-pdf text-white display-6"></i>
            </div>
            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                <h4 class="fw-bold text-dark mb-0 lh-sm">Resumen de revisión de resultados <span x-text="year"></span></h4>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
        <span class="small">Descargar documento</span>
        <div class="icon-transition"><i class="ti ti-download fs-5"></i></div>
    </div>
</a>

<div class="card sasisopa-report-table-card mt-3 mb-3">
    <div class="card-header bg-primary text-white">
        <h5 class="card-title text-white mb-0"><i class="ti ti-file-report me-2"></i>Informe de revisión de resultados</h5>
    </div>
    <div class="card-body">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">

    <thead>

        <tr>

            <th class="text-center">#</th>

            <th class="text-center">
                Fecha
            </th>

            <th class="text-center">
                Nombre completo
            </th>

            <th class="text-center sasisopa-pdf-column" style="width:48px; min-width:48px; max-width:48px" data-orderable="false" data-searchable="false"><i class="ti ti-file-type-pdf text-muted fs-7"></i></th>

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
                        x-if="item.archivo_disponible">

                        <a
                            :href="item.archivo_url"
                            target="_blank">

                            <i class="ti ti-file-type-pdf text-danger fs-6"></i>

                        </a>

                    </template>

                    <template
                        x-if="!item.archivo_disponible">

                        <i class="ti ti-file-off fs-6 text-muted" title="Archivo no disponible"></i>

                    </template>

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
<!-- 17. REVISIÓN DE RESULTADOS -->

<!-- 18. INFORMES DE DESEMPEÑO -->
<div class="sasisopa-report-block">
    <div class="sasisopa-report-content">
    <h4 class="card-title sasisopa-section-title"><span class="badge rounded-pill text-bg-primary">18</span><span>INFORMES DE DESEMPEÑO</span></h4>

    <div class="card sasisopa-report-table-card mt-3 mb-3">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title text-white mb-0"><i class="ti ti-file-report me-2"></i>Informe de Evaluación de Desempeño (IED)</h5>
        </div>
        <div class="card-body">
    <div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">

    <thead>

        <tr>

            <th>#</th>

            <th>Fecha</th>

            <th>Nombre completo</th>

            <th class="text-center sasisopa-pdf-column" style="width:48px; min-width:48px; max-width:48px" data-orderable="false" data-searchable="false"><i class="ti ti-file-type-pdf text-muted fs-7"></i></th>

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

                    <template x-if="item.archivo_disponible">

                        <a
                            :href="item.archivo_url"
                            target="_blank">

                            <i class="ti ti-file-type-pdf text-danger fs-6"></i>

                        </a>

                    </template>

                    <template x-if="!item.archivo_disponible">

                        <i class="ti ti-file-off fs-6 text-muted" title="Archivo no disponible"></i>

                    </template>

                </td>

            </tr>

        </template>

    </tbody>

</table>
</div>
    </div>
    </div>

    <div class="card sasisopa-report-table-card mt-3 mb-3">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title text-white mb-0"><i class="ti ti-list-check me-2"></i>Control de la implementación de los procedimientos del SASISOPA (Fo.ADMONGAS.029)</h5>
        </div>
        <div class="card-body">

<div class="table-responsive pb-2 overflow-x-auto overflow-y-hidden"><table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">

    <thead>

        <tr>

            <th>#</th>

            <th>Fecha</th>

            <th>Nombre completo</th>

            <th class="text-center sasisopa-pdf-column" style="width:48px; min-width:48px; max-width:48px" data-orderable="false" data-searchable="false"><i class="ti ti-file-type-pdf text-muted fs-7"></i></th>

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
</div>
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

                <div class="table-responsive pb-2 overflow-x-auto overflow-y-hidden"><table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">

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

                    <template x-if="item.archivo_disponible">
                        <a :href="item.archivo_url" target="_blank">
                            <i class="ti ti-file-type-pdf text-danger fs-6"></i>
                        </a>
                    </template>
                    <template x-if="!item.archivo_disponible">
                        <i class="ti ti-file-off fs-6 text-muted" title="Archivo no disponible"></i>
                    </template>

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

                    <table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">

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

                    <table class="table table-striped table-bordered mb-0 text-nowrap align-middle sasisopa-reporte-datatable">

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
                                x-if="!tercer.archivo_disponible">

                                <tr>

                                    <td
                                        colspan="3"
                                        class="text-center text-muted">

                                        No se encontró información

                                    </td>

                                </tr>

                            </template>

                            <template
                                x-if="tercer.archivo_disponible">

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
                                            :href="tercer.archivo_url"
                                            target="_blank">

                                            <i class="ti ti-file-type-pdf text-danger fs-6"></i>

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
