<div id="container" class="pb-4"
data-module-station-key="sasisopa"
data-estacion-id="<?= e($estacionId ?? '') ?>"
x-data="monitoreoEvaluacion()">

<?php if (empty($estacionId)): ?>
 
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

<div x-data="monitoreoEvaluacion">

<div class="row mt-3 mb-3">
<div class="col-6">
            <!-- <label class="form-label">Año</label> -->
            <select
                class="form-select"
                x-model.number="year"
                @change="buscar()">
                <?php for($i = date('Y'); $i >= 2019; $i--): ?>
                    <option value="<?= $i ?>">
                        <?= $i ?>
                    </option>
                <?php endfor; ?>
            </select>
</div>

<div class="col-6">
    <div class="text-end">
   <button type="button" class="btn bg-primary-subtle text-primary">
                    <a class="dropdown-item pointer " :href="pdfUrl" target="_blank"><i class="ti ti-download"></i> Descargar</a>
</button>
</div>
</div>
</div>

<div class="row">

<!-- Implementación del SA -->
<div class="col-12">
<div class="card">
<div class="card-body p-0">
    <div class="table-responsive">
<table class="table table-responsive table-striped mb-0 text-nowrap align-middle">
        <tbody>
            <tr>
                <td class="align-middle text-center">
                    <b>Objeto</b>
                </td>
                <td class="align-middle">
                    Implementación del SA
                </td>
                <td class="align-middle text-center">
                    <b>Indicador</b>
                </td>
                <td class="align-middle">
                    No. Total de elementos implementados VS No. de elementos del SA
                </td>
            </tr>

            <tr>
                <td class="align-middle text-center">
                    <b>Meta</b>
                </td>
                <td
                    class="align-middle"
                    x-text="implementacion.meta">
                </td>
                <td class="align-middle text-center">
                    <b>Frecuencia de medición</b>
                </td>
                <td class="align-middle">
                    Anual
                </td>
            </tr>

            <tr>
                <td colspan="4">
                    <div class="mt-1">
                        <b>Resultado:</b>
                        <span
                            x-html="DOMPurify.sanitize(implementacion.resultado)">
                        </span>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>
</div>
<div class="card-footer">
        <button type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info float-end" @click="implementacionDetalle()"><i class="ti ti-eye"></i> Ver detalle</button>

</div>
</div>
</div>
</div>  
    <!-- Implementación del SA -->
    
    <!-- Implementación del SA -->
    <!-- Ventas -->
<div class="col-12">
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
    <table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
    <tbody>
      <tr>
        <td class="align-middle text-center"><b>Objeto</b></td>
        <td class="align-middle">Ventas</td>
        <td class="align-middle text-center"><b>Indicador</b></td>
        <td class="align-middle">Venta del mes inmediato anterior VS venta del mes actual</td>
      </tr>
      <tr>
        <td class="align-middle text-center"><b>Meta</b></td>
        <td class="align-middle"
        x-text="ventas.meta">
        </td>
        <td class="align-middle text-center"><b>Frecuencia de medición</b></td>
        <td class="align-middle">Mensual</td>
      </tr>
    </tbody>
  </table>
  </div>
  
  <div class="mt-3 md-3"><b>Resultado:</b></div>

  <div class="row">

    <template
        x-for="(item,index) in ventas.detalle"
        :key="index">

        <div class="col-md-2 mt-3 ">
<div class="table-responsive">
            <table
                class="table table-responsive table-striped mb-0 text-nowrap align-middle">

                <thead>

                    <tr>

                        <th class="text-center bg-light">

                            <span x-text="item.mes_anterior"></span>
                            <span x-text="item.year_anterior"></span>

                        </th>

                        <th class="text-center bg-light">

                            <span x-text="item.mes_actual"></span>
                            <span x-text="item.year_actual"></span>

                        </th>

                    </tr>

                </thead>

                <tbody>

                    <tr>

                        <td class="text-center bg-light">

                            <span x-text="
                                Number(item.valor_anterior)
                                .toLocaleString(
                                    'es-MX',
                                    {
                                        minimumFractionDigits:2
                                    }
                                )
                            "></span>

                        </td>

                        <td class="text-center bg-light">

                            <span x-text="
                                Number(item.valor_actual)
                                .toLocaleString(
                                    'es-MX',
                                    {
                                        minimumFractionDigits:2
                                    }
                                )
                            "></span>

                        </td>

                    </tr>

                    <tr>

                        <td
                            colspan="2"
                            class="text-center bg-light">

                            <b
                                :class="item.tc.clase"
                                x-text="item.tc.texto">
                            </b>

                        </td>

                    </tr>

                </tbody>

            </table>
</div>
        </div>

    </template>

  </div>
    
    </div>
    <div class="card-footer">
<button type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info float-end" @click="VentasDetalle()"><i class="ti ti-eye"></i> Ver detalle</button>
    </div>
</div>
</div>


  <!-- Ventas -->
  <!-- Capacitación -->
   <div class="col-12">
<div class="card">
<div class="card-body p-0">
    <div class="table-responsive">
<table class="table table-striped mb-0 text-nowrap align-middle">
          <tbody>
            <tr>
              <td class="align-middle text-center"><b>Objeto</b></td>
              <td class="align-middle">Capacitación</td>
              <td class="align-middle text-center"><b>Indicador</b></td>
              <td class="align-middle">No. de personal capacitado vs No. de personal de la estación</td>
            </tr>
            <tr>
              <td class="align-middle text-center"><b>Meta</b></td>
              <td class="align-middle" x-text="capacitacion.meta"></td>
              <td class="align-middle text-center"><b>Frecuencia de medición</b></td>
              <td class="align-middle">Semestral</td>
            </tr>
            <tr>
              <td colspan="4">

              <div class="mt-1">
                  <b>Resultado:</b>
              </div>

              <div class="row">

                  <div class="col-6">

                      <div class="text-secondary">
                          Primer semestre
                      </div>

                      <b
                          :class="capacitacion.semestre1?.clase"
                          x-text="capacitacion.semestre1?.texto">
                      </b>

                  </div>

                  <template x-if="capacitacion.semestre2">

                      <div class="col-6">

                          <div class="text-secondary">
                              Segundo semestre
                          </div>

                          <b
                              :class="capacitacion.semestre2.clase"
                              x-text="capacitacion.semestre2.texto">
                          </b>

                      </div>

                  </template>

              </div>

              </td>
            </tr>
          </tbody>
        </table>
        </div>
</div>
<div class="card-footer">
<button type="button" class="btn bg-primary-subtle text-primary float-end" data-bs-toggle="modal" data-bs-target="#modal-capacitacion"><i class="ti ti-eye"></i> Ver detalle</button>
</div>
</div>

   </div>
  
   <!-- Capacitación -->
  <!-- Satisfacción del cliente -->
   <div class="col-12">
<div class="card">
<div class="card-body p-0">
    <div class="table-responsive">
<table class="table table-striped mb-0 text-nowrap align-middle">

    <tbody>

        <tr>
            <td class="align-middle text-center">
                <b>Objeto</b>
            </td>

            <td>
                Satisfacción del cliente
            </td>

            <td class="align-middle text-center">
                <b>Indicador</b>
            </td>

            <td>
                Media del total de clientes con experiencia:
                Mala, Regular, Buena y Excelente
            </td>
        </tr>

        <tr>
            <td class="align-middle text-center">
                <b>Meta</b>
            </td>

            <td x-text="satisfaccion.meta"></td>

            <td class="align-middle text-center">
                <b>Frecuencia de medición</b>
            </td>

            <td>
                Semestral
            </td>
        </tr>

        <tr>

            <td colspan="4">

                <b>Resultado:</b>

                <div class="row mt-2">

                    <div class="col-md-6"
                        x-show="satisfaccion.semestre1">

                        <div class="text-secondary">
                            Primer semestre
                        </div>

                        <div class="text-danger">
                            Mala:
                            <b x-text="satisfaccion.semestre1?.mala ?? 0"></b>
                        </div>

                        <div class="text-warning">
                            Regular:
                            <b x-text="satisfaccion.semestre1?.regular ?? 0"></b>
                        </div>

                        <div class="text-info">
                            Buena:
                            <b x-text="satisfaccion.semestre1?.buena ?? 0"></b>
                        </div>

                        <div class="text-success">
                            Excelente:
                            <b x-text="satisfaccion.semestre1?.excelente ?? 0"></b>
                        </div>

                    </div>

                    <div class="col-md-6"
                        x-show="satisfaccion.semestre2">

                        <div class="text-secondary">
                            Segundo semestre
                        </div>

                        <div class="text-danger">
                            Mala:
                            <b x-text="satisfaccion.semestre2?.mala ?? 0"></b>
                        </div>

                        <div class="text-warning">
                            Regular:
                            <b x-text="satisfaccion.semestre2?.regular ?? 0"></b>
                        </div>

                        <div class="text-info">
                            Buena:
                            <b x-text="satisfaccion.semestre2?.buena ?? 0"></b>
                        </div>

                        <div class="text-success">
                            Excelente:
                            <b x-text="satisfaccion.semestre2?.excelente ?? 0"></b>
                        </div>

                    </div>

                </div>

            </td>

        </tr>

    </tbody>

</table>
</div>
</div>
<div class="card-footer">
<button type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info float-end" @click="satisfaccionClientes()"><i class="ti ti-eye"></i> Ver detalle</button>
</div>

</div>

   </div>

  <!-- Satisfacción del cliente -->
  <!-- Incidentes y accidentes -->
   <div class="col-12">
<div class="card">
    <div class="card-body p-0">


<table class="table table-striped mb-0 text-nowrap align-middle">

    <tbody>

        <tr>
            <td class="align-middle text-center">
                <b>Objeto</b>
            </td>

            <td>
                Incidentes y accidentes
            </td>

            <td class="align-middle text-center">
                <b>Indicador</b>
            </td>

            <td>
                No total de accidentes e incidentes ocurridos VS
                número total de accidentes e incidentes atendidos
            </td>
        </tr>

        <tr>

            <td class="align-middle text-center">
                <b>Meta</b>
            </td>

            <td x-text="incidentes.meta"></td>

            <td class="align-middle text-center">
                <b>Frecuencia de medición</b>
            </td>

            <td>
                Semestral
            </td>

        </tr>

        <tr>

            <td colspan="4">

                <div class="mt-1">
                    <b>Resultado:</b>
                </div>

                <div class="row">

                    <div class="col-md-6">

                        <div class="text-secondary">
                            Primer semestre:
                        </div>

                        <div
                            x-html="DOMPurify.sanitize(incidentes.semestre1)">
                        </div>

                    </div>

                    <template
                        x-if="incidentes.semestre2">

                        <div class="col-md-6">

                            <div class="text-secondary">
                                Segundo semestre:
                            </div>

                            <div
                            x-html="DOMPurify.sanitize(incidentes.semestre2)">
                            </div>

                        </div>

                    </template>

                </div>

            </td>

        </tr>

    </tbody>

</table>
    </div>
<div class="card-footer">
    <button type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info float-end" @click="IncidentesAccidentes()"><i class="ti ti-eye"></i> Ver detalle</button>
</div>
</div>

   </div>
<!-- Incidentes y accidentes -->


</div>

<div class="row">
    <!-- Programa de implementación del Sistema de Administración -->
    <div class="col-12 mb-4">
        <a href="/sasisopa/monitoreo-verificacion-evaluacion/descargar-programa-implementacion-s-a" 
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-file-type-pdf text-white display-6"></i> 
                    </div>

                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            Programa de implementación del Sistema de Administración
                        </h4>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Descargar documento</span>
                <div class="icon-transition">
                    <i class="ti ti-download fs-5"></i>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row">

    <!-- Monitoreo de aspectos ambientales y riesgos -->
    <div class="col-md-3 d-flex align-items-stretch mb-4">
        <a href="/sasisopa/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales" 
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-chart-radar text-white display-6"></i> 
                    </div>

                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            Monitoreo de aspectos ambientales y riesgos
                        </h4>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Calibración, Verificación y mantenimiento de equipos -->
    <div class="col-md-3 d-flex align-items-stretch mb-4">
        <a href="/sasisopa/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos" 
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-adjustments-horizontal text-white display-6"></i> 
                    </div>

                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            Calibración, Verificación y mantenimiento de equipos
                        </h4>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Evaluación y cumplimiento de requisitos legales -->
    <div class="col-md-3 d-flex align-items-stretch mb-4">
        <a href="/sasisopa/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales" 
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-gavel text-white display-6"></i> 
                    </div>

                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            Evaluación y cumplimiento de requisitos legales
                        </h4>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Administración de hallazgos derivados del monitoreo del sistema de administración -->
    <div class="col-md-3 d-flex align-items-stretch mb-4">
        <a href="/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos" 
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-search text-white display-6"></i> 
                    </div>

                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            Administración de hallazgos derivados del monitoreo del sistema de administración
                        </h4>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>

</div>

<div class="modal fade" id="modal-capacitacion" tabindex="-1" aria-labelledby="mySmallModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header mb-0 bg-primary d-flex align-items-center">
 
                        <h4 class="modal-title text-white" id="myModalLabel">
                            <i class="ti ti-settings"></i>
                            Capacitación
                        </h4>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    

                    </div>
                   
                    <div class="modal-body pb-0">
      <div class="row">

    <!-- Programa de capacitación interna -->
    <div class="col-md-6  mb-3">
        <a href="/sasisopa/competencia-personal-capacitacion-entrenamiento/capacitacion-interna" 
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-school text-white display-6"></i> 
                    </div>

                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            Programa de capacitación interna
                        </h4>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Programa de capacitación externa -->
    <div class="col-md-6  mb-3">
        <a href="/sasisopa/competencia-personal-capacitacion-entrenamiento/capacitacion-externa" 
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-certificate text-white display-6"></i> 
                    </div>

                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            Programa de capacitación externa
                        </h4>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>

</div>
                    
                        
                    
                </div>

                <div class="modal-footer">
           <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">

                    <i class="ti ti-x"></i> Cancelar

                </button>
                </div>
                
                  
       
            <!-- /.modal-dialog -->
        </div>
        <!-- /.modal -->
    </div>
</div>

<!-- ------------------------- -->
<!-- inicio offcanvas -------- -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasExampleLabel">
            Bienvenido al elemento 14. MONITOREO, VERIFICACIÓN Y EVALUACIÓN, del Sistema de Administración
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

          <p>
            En este apartado podrás monitorear y evaluar el cumplimiento de los indicadores mas relevantes del sistema de administración..
          </p>
          <hr>

          <label class="fw-bold">Como hacerlo:</label>
          <ul class="list-group list-group-flush">
            <li class="list-group-item">En la tabla medición de indicadores y frecuencia da clic en objeto y llena los campos que se solicitan y da clic en aceptar</li>
            <li class="list-group-item">En la columna acciones a implementar genera un resumen detallado de aquellas actividades a implementar para cumplir la meta (en caso de no haber llegado al objetivo)</li>
            <li class="list-group-item">Da clic en objeto para entrar a detalle de cada uno de los indicadores</li>
          </ul>

          <hr>

          <label class="fw-bold">Responsables:</label>
          <p>Recuerda que es responsabilidad del <label class="text-danger fw-bold">Representante Técnico</label> (RT), <label class="text-danger fw-bold">Gerente de la Estación</label> y quienes estén involucrados en la implementación del sistema de administración el monitoreo y comportamiento de los resultados así como de proponer las acciones a implementar para la obtención de las metas.</p>

          <small>Nota: Las acciones a implementar también podrán ser propuestas para el mejor desempeño del sistema de administración.</small>

    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->

<?php endif; ?>

</div>
