<div id="container" class="pb-4"
x-data="monitoreoEvaluacion()">

<div x-data="monitoreoEvaluacion">


<div class="text-end mt-2">
   <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
                 <li>
                    <a class="dropdown-item" :href="pdfUrl" target="_blank"><i class="ti ti-download"></i> Descargar</a>
                </li>
            </ul>
        </div>
</div>


    <div class="row mb-3">
        <div class="col-md-3">
            <label class="form-label">Año</label>
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
    </div>
    
    <!-- Implementación del SA -->

    <table class="table table-bordered table-sm pb-0 mb-0 mt-2">
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
                    ANUAL
                </td>
            </tr>

            <tr>
                <td colspan="4">
                    <div class="mt-1">
                        <b>Resultado:</b>
                        <span
                            x-html="implementacion.resultado">
                        </span>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="text-end mt-2"><button type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info" @click="implementacionDetalle()"><i class="ti ti-eye"></i>Ver detalle</button></div>
    <!-- Implementación del SA -->
    <!-- Ventas -->
    <hr>

    <table class="table table-bordered table-sm pb-0 mb-0">
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
  <div class="mt-1"><b>Resultado:</b></div>

  <div class="row">

    <template
        x-for="(item,index) in ventas.detalle"
        :key="index">

        <div class="col-2">

            <table
                class="table table-sm table-bordered"
                style="font-size:.9em;">

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

    </template>

  </div>

  <div class="text-end"><button type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info" @click="VentasDetalle()"><i class="ti ti-eye"></i>Ver detalle</button></div>
  <hr>
  <!-- Ventas -->
  <!-- Capacitación -->
  <table class="table table-bordered table-sm pb-0 mb-0">
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

        <div class="text-end mt-2"><button type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info" data-bs-toggle="modal" data-bs-target="#modal-capacitacion"><i class="ti ti-eye"></i>Ver detalle</button></div>
        <hr>
   <!-- Capacitación -->
  <!-- Satisfacción del cliente -->
<table class="table table-bordered table-sm">

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

        <div class="text-end mt-2"><button type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info" @click="satisfaccionClientes()"><i class="ti ti-eye"></i>Ver detalle</button></div>
        <hr>

  <!-- Satisfacción del cliente -->
  <!-- Incidentes y accidentes -->
<table class="table table-bordered table-sm pb-0 mb-0">

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
                            x-html="incidentes.semestre1">
                        </div>

                    </div>

                    <template
                        x-if="incidentes.semestre2">

                        <div class="col-md-6">

                            <div class="text-secondary">
                                Segundo semestre:
                            </div>

                            <div
                                x-html="incidentes.semestre2">
                            </div>

                        </div>

                    </template>

                </div>

            </td>

        </tr>

    </tbody>

</table>

<div class="text-end mt-2"><button type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info" @click="IncidentesAccidentes()"><i class="ti ti-eye"></i>Ver detalle</button></div>
<hr>
<!-- Incidentes y accidentes -->

<table class="table table-sm table-bordered pb-0 mb-0">
        <tbody>
          <tr>
            <td class="align-middle"><b>Programa de implementación del Sistema de Administración</b></td>
            <td class="text-center align-middle" width="40px"><a href="/sasisopa/monitoreo-verificacion-evaluacion/descargar-programa-implementacion-s-a"><i class="ti ti-file-type-pdf fs-7 text-danger"></i></a></td>

          </tr>
        </tbody>
      </table>

</div>


<div class="row mt-4">

  <div class="col-md-3 align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title">Monitoreo de aspectos ambientales y riesgos</h4>

         <div class="text-end mt-4">
          <a href="/sasisopa/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales" type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info">
            <i class="ti ti-eye"></i>
            Ver detalle 
          </a>
        </div>

      </div>
    </div>
  </div>

  <div class="col-md-3 align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title">Calibración, Verificación y mantenimiento de equipos</h4>
          
          <div class="text-end mt-4">
          <a href="/sasisopa/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos" type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info">
            <i class="ti ti-eye"></i>
            Ver detalle
          </a>
        </div>

      </div>
    </div>
  </div>

  <div class="col-md-3 align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title">Evaluación y cumplimiento de requisitos legales</h4>
          
          <div class="text-end mt-4">
          <a href="/sasisopa/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales" 
          class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info">
            <i class="ti ti-eye"></i>
            Ver detalle
          </a>
        </div>

      </div>
    </div>
  </div>

  <div class="col-md-3 align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title">Administración de hallazgos derivados del monitoreo del sistema de administración</h4>
          
          <div class="text-end mt-4">
          <a href="/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos"
           class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info">
            <i class="ti ti-eye"></i>
            Ver detalle
          </a>
        </div>

      </div>
    </div>
  </div>

</div>


<div class="modal fade" id="modal-capacitacion" tabindex="-1" aria-labelledby="mySmallModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-md">
                <div class="modal-content">
                    <div class="modal-header d-flex align-items-center">
                        <h4 class="modal-title" id="myModalLabel">
                            Capacitación
                        </h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">

                    
                    <a href="/sasisopa/competencia-personal-capacitacion-entrenamiento/capacitacion-interna" 
                    class="btn btn-lg bg-info-subtle text-info p-2 w-100">
                        <i class="ti ti-device-imac"></i> Programa de capacitación interna
                    </a>

                    <a href="/sasisopa/competencia-personal-capacitacion-entrenamiento/capacitacion-externa" 
                    class="btn btn-lg bg-info-subtle text-info mt-3 p-2 w-100">
                        <i class="ti ti-device-imac"></i> Programa de capacitación externa
                    </a>

                    
                        
                    </div>
                </div>
                <!-- /.modal-content -->
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


</div>