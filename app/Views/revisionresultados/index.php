<div id="container" class="pb-4"
x-data="{ ...actions(), ...revisionResultados()}">

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


<div class="card mt-4">
  <div class="card-body">
  <div class="d-flex align-items-center">
      <div class="ms-auto">
      <div class="dropdown dropstart">
            <a href="javascript:void(0)" class="link text-dark" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="ti ti-dots fs-7"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
              <li>
                <a class="dropdown-item" href="javascript:void(0)" @click="openModalRevisionResultado()"><i class="ti ti-plus"></i> Agregar</a>
              </li>
              <li>
                <a class="dropdown-item" href="/uploads/archivos/Fo.ADMONGAS/Fo.ADMONGAS.027.docx" download><i class="ti ti-file-word"></i> Descargar</a>
              </li>
            </ul>
          </div>   
      </div>
  </div>

    <table class="table table-sm table-bordered table-striped table-hover">

        <thead>

            <tr>
                <th class="text-center align-middle bg-primary text-white">
                    #
                </th>
                <th class="text-center align-middle bg-primary text-white">
                    Fecha
                </th>
                <th class="text-center align-middle bg-primary text-white">
                    Nombre completo
                </th>
                <th
                    width="35"
                    class="text-center align-middle bg-primary text-white">
                    <i class="fas fa-ellipsis-v"></i>
                </th>
            </tr>
        </thead>
        <tbody>
            <template
                x-if="revisionResultados.length === 0">
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
                x-for="item in revisionResultados"
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
                            <a class="dropdown-item d-flex align-items-center gap-3"
                            @click="editarRevision(item)">
                            <i class="fs-4 ti ti-edit"></i>Editar
                            </a>
                            </li>
                            <li>
                            <a class="dropdown-item d-flex align-items-center gap-3"
                            :href="item.archivo" download>
                            <i class="fs-4 ti ti-download"></i>Descargar
                            </a>
                            </li>
                            <li>
                            <a href="javascript:void(0)" class="dropdown-item d-flex align-items-center gap-3"
                            @click="eliminar(item.id)">
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

<!-- ModalNuevo -->
<div
    class="modal fade"
    id="modalRevisionResultado"
    tabindex="-1">

    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">

                    <span
                        x-text="
                            modoRevision === 'create'
                                ? 'Agregar Archivo'
                                : 'Editar Archivo'
                        ">
                    </span>

                </h4>

               <button
                    type="button"
                    class="btn-close"
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
                        x-model="revision.fecha"
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
                            revision.archivo =
                            $event.target.files[0]
                        ">

                </div>

                <template
                    x-if="
                        modoRevision === 'edit'
                        && revision.archivo_actual
                    ">

                    <div>

                        <a
                            :href="`${revision.archivo_actual}`"
                            target="_blank">

                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                            Ver archivo actual

                        </a>

                    </div>

                </template>

            </div>

            <div class="modal-footer">

               <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">

                    Cancelar

                </button>

                <button
                    class="btn btn-success"
                    @click="guardarRevisionResultado()">

                    <span
                        x-text="
                            modoRevision === 'create'
                                ? 'Guardar'
                                : 'Actualizar'
                        ">
                    </span>

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
            Bienvenido al elemento 17. REVISIÓN DE RESULTADOS, del Sistema de Administración
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

          <p>
            En este apartado podrás verificar los resultados arrojados en el elemento <b>14. MONITOREO, VERIFICACIÓN Y EVALUACIÓN</b>, así como también proponer acciones de mejora para poder cumplir los objetivos y las metas.
          </p>
         

          <hr>

          <label class="fw-bold">Como hacerlo:</label>
          <ul class="list-group list-group-flush">
            <li class="list-group-item">Da clic en el icono de descargar para llenar el informe de revisión de resultados <b>(Fo.ADMONGAS.027)</b>.</li>
            <li class="list-group-item">Lee detenidamente cada uno de los puntos del formato y realiza el llenado como se te indica.</li>
            <li class="list-group-item">Dicha plantilla deberá ser retroalimentada en cuanto al contenido asegúrate de no dejar ningún punto en blanco.</li>
            <li class="list-group-item">El informe deberá ser firmado por el Representante legal de la estación.</li>
            <li class="list-group-item">Escanea y sube tu archivo en formato PDF, dando clic en el icono <i class="ti ti-plus fs-6 text-primary"></i> agregar documento.</li>
            <li class="list-group-item">Podrás verificar el estado de tu documento en el icono PDF.</li>
          </ul>

          <hr>

          <label class="fw-bold">Responsables:</label>
          <p>Recuerda que es responsabilidad del <label class="text-danger fw-bold">Representante Técnico</label> (RT), <label class="text-danger fw-bold">Gerente de la Estación</label>, el interpretar los resultados obtenidos durante el tiempo de implementación y el generar propuestas de mejora para obtener los resultados deseados.</p>

          <small>Nota: El informe de revisión de resultados deberá ser actualizado anualmente </small>

    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->

</div>