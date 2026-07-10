<div id="container" class="pb-4"
x-data="{ ...actions(), ...comunicacionParticipacionConsulta() }">

<table class="table table-bordered table-sm mt-3">
    <tr>
    <td class="text-center align-middle"><img class="text-center" src="<?= $_ENV['APP_URL'] . '/assets/images/logos/Logo.png' ?>" style="width: 200px;"></td>
    <td colspan="2" class="text-center align-middle"><b>Registro de la atención y el seguimiento a la comunicación interna y externa.</b></td>
    <td class="text-center align-middle">Fo.ADMONGAS.010</td>
    </tr>
    <tr>
    <td class="text-center align-middle">Realizado por: Nelly Estrada Garcia </td>
    <td class="text-center align-middle">Revisado por: Eduardo Galicia Flores </td>
    <td class="text-center align-middle">Autorizado por: Tomas Tarno Quinzaños </td>
    <td class="text-center align-middle">Fecha de autorizacion 01/10/2018</td>
    </tr>
</table>

<div class="text-end">
    <div class="btn-group">
        <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="ti ti-dots-vertical fs-4"></i>
        </button>
        <ul class="dropdown-menu animated rubberBand">
          <?= !empty($permisos['crear']) ? 
          '<li><a class="dropdown-item"  href="javascript:void(0)" @click="openModalComunicacion()"><i class="ti ti-plus"></i> Nuevo </a></li>' 
          : '' 
          ?>
          <li>
              <a class="dropdown-item" href="javascript:void(0)" @click="openModalBuscar()"><i class="ti ti-search"></i> Buscar</a>
          </li>
          <li>
            <a class="dropdown-item" 
            :href="pdfUrl">
                <i class="ti ti-download"></i>
                Descargar
            </a>
        </li>
        </ul>
    </div>
</div>

  <div class="datatables">
    <div class="table-responsive">
      <table id="table-registro-comunicacion" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>No.</th>
            <th>Fecha</th>
            <th>Tema a comunicar</th>
            <th>Encargado de la comunicación</th>
            <th>Tipo de comunicación</th>
            <th width="200px">Material utilizado para la comunicación</th>
            <th width="200px">Seguimiento de la comunicación</th>
          <th class="text-center">
          <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
          </th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>


<!-- -->
<div class="row mt-4">

<div class="col-md-6">

<div class="card">
  <div class="card-body">

  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Quejas y sugerencias</h4>
      <div class="ms-auto">
      <?= 
        !empty($permisos['crear']) ? 
        '<a class="btn btn-primary" href="javascript:void(0)" @click="openModalQS()"  >
        <i class="ti ti-plus"></i> Nuevo
        </a>' 
        : '' 
        ?>   
      </div>
  </div>

  <div class="datatables mt-3">
    <div class="table-responsive">
      <table id="table-quejas-sugerencia" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>#</th>
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
 

</div>

</div>

<!-- Modal Nuevo -->

<div class="modal fade" id="modalComunicacion" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">
                <h4 class="modal-title text-white">
                    Registro de la atención y el seguimiento a la comunicación interna y externa.
                </h4>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                        @click="limpiarModalComunicacion()">
                </button>
            </div>

            <div class="modal-body">

                <!-- Tema -->
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        * Tema a comunicar:
                    </label>

                    <input type="text"
                           class="form-control"
                           placeholder="Agregar tema a comunicar"
                           x-model="comie.tema"
                           :class="errorscomie.tema ? 'is-invalid' : ''"
                           @input="errorscomie.tema = false">
                </div>

                <!-- Detalle -->
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        * Detalle:
                    </label>

                    <textarea class="form-control"
                              rows="6"
                              placeholder="Agregar detalle"
                              x-model="comie.detalle"
                              :class="errorscomie.detalle ? 'is-invalid' : ''"
                              @input="errorscomie.detalle = false"></textarea>
                </div>

                <!-- Tipo comunicación -->
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        * Tipo de comunicación:
                    </label>

                    <select class="form-select"
                            x-model="comie.tipo_comunicacion"
                            :class="errorscomie.tipo_comunicacion ? 'is-invalid' : ''"
                            @input="errorscomie.tipo_comunicacion = false"
                            @change="tipoComunicacion()">

                        <option value="">Selecciona</option>
                        <option value="Interna">Interna</option>
                        <option value="Externa">Externa</option>
                    </select>
                </div>

                <!-- Material -->
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        * Material utilizado para la comunicación:
                    </label>

                    <select class="form-select"
                            x-model="comie.material"
                            :class="errorscomie.material ? 'is-invalid' : ''"
                            @change="errorscomie.material = false">

                        <option value="">Selecciona</option>
                        <option value="Correo electrónico">
                            Correo electrónico
                        </option>

                        <option value="Vía telefónica">
                            Vía telefónica
                        </option>

                        <option value="Minutas y actas de reuniones">
                            Minutas y actas de reuniones
                        </option>

                        <option value="Tableros, carteles, trípticos">
                            Tableros, carteles, trípticos
                        </option>

                        <option value="Portal AdmonGas">
                            Portal AdmonGas
                        </option>
                    </select>
                </div>

                <div class="row">

                <!-- Dirigido a -->
                <div class="col-md-6 mb-3">

                    <label class="form-label fw-bold">
                        Dirigido a:
                    </label>

                    <select id="dirigidoa"
                        class="form-select"
                        multiple
                        :disabled="comie.tipo_comunicacion !== 'Interna'">

                    <template x-for="puesto in puestos" :key="puesto.id">

                        <option :value="puesto.id"
                                x-text="puesto.tipo_puesto">
                        </option>

                    </template>

                </select>

                </div>
                    <!-- Seguimiento -->
                    <div class="col-md-6 mb-3">

                        <label class="form-label fw-bold">
                            Seguimiento de la comunicación:
                        </label>

                        <select class="form-select"
                                x-model="comie.seguimiento"
                                :disabled="comie.tipo_comunicacion !== 'Externa'">

                            <option value="">Selecciona</option>

                            <option value="Correo electrónico">
                                Correo electrónico
                            </option>

                            <option value="Vía telefónica">
                                Vía telefónica
                            </option>

                        </select>

                    </div>

                </div>

            </div>

            <div class="modal-footer">

                <button class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal"
                        @click="limpiarModalComunicacion()">
                    <i class="ti ti-x"></i> Cancelar
                </button>

                <button class="btn btn-success"
                @click="mode === 'edit'
                    ? updateComunicacion()
                    : guardarComunicacion()">
                    <i class="ti ti-check"></i>
                 <span x-text="mode === 'edit' ? 'Actualizar' : 'Crear'"></span>

                </button>

            </div>

        </div>
    </div>
</div>

<!-- Modal Evidencia -->

<div class="modal fade" id="modalEvidencia" tabindex="-1">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
                    Evidencias
                </h4>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <h5 class="mb-3" x-text="evidencia.tema"></h5>

                <input type="file"
                       class="form-control"
                       x-ref="fileEvidencia"
                       accept=".jpg,.jpeg,.png,.gif">

                <div class="text-end mt-3">

                    <button class="btn btn-primary"
                            @click="guardarEvidencia()">

                        Agregar evidencia
                    </button>

                </div>

                <hr>

                <template x-if="evidencias.length === 0">

                    <div class="text-center text-muted">
                        No se encontraron evidencias
                    </div>

                </template>

                <div class="row">

                    <template x-for="item in evidencias" :key="item.id">

                        <div class="col-md-4 mb-3">

                            <div class="border p-2 position-relative">

                                <button class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1"
                                        @click="eliminarEvidencia(item.id)">
                                    <i class="ti ti-trash"></i>
                                </button>

                                <img :src="item.url"
                                     class="img-fluid">

                            </div>

                        </div>

                    </template>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- modal Detalle -->

<div class="modal fade"
     id="modalDetalleComunicacion"
     tabindex="-1">

    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white"
                    x-text="detalle.tema || 'Detalle comunicación'">
                </h4>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                        @click="limpiarDetalle()">
                </button>

            </div>

            <div class="modal-body">

                <!-- ROW 1 -->
                <div class="row">

                    <div class="col-md-6 mb-2">

                        <div class="border p-2 h-100">

                            <div class="text-secondary small mb-1">
                                Encargado de la comunicación
                            </div>

                            <div class="fw-bold"
                                 x-text="detalle.encargado_comunicacion || 'S/I'">
                            </div>

                        </div>

                    </div>

                    <div class="col-md-6 mb-3">

                        <div class="border p-2 h-100">

                            <div class="text-secondary small mb-1">
                                Fecha
                            </div>

                            <div class="fw-bold"
                                 x-text="formatFecha(detalle.fecha)">
                            </div>

                        </div>

                    </div>

                </div>

                <!-- ROW 2 -->
                <div class="row">

                    <div class="col-md-6 mb-3">

                        <div class="border p-2 h-100">

                            <div class="text-secondary small mb-1">
                                Tipo de comunicación
                            </div>

                            <div x-text="detalle.tipo_comunicacion || 'S/I'"></div>

                        </div>

                    </div>

                    <div class="col-md-6 mb-3">

                        <div class="border p-2 h-100">

                            <div class="text-secondary small mb-1">
                                Material utilizado para la comunicación
                            </div>

                            <div x-text="detalle.material || 'S/I'"></div>

                        </div>

                    </div>

                </div>

                <!-- ROW 3 -->
                <div class="row">

                    <div class="col-md-6 mb-3">

                        <div class="border p-2 h-100">

                            <div class="text-secondary small mb-1">
                                Seguimiento de la comunicación
                            </div>

                            <div x-text="detalle.seguimiento || 'S/I'"></div>

                        </div>

                    </div>

                    <div class="col-md-6 mb-3">

                        <div class="border p-2 h-100">

                            <div class="text-secondary small mb-2">
                                Dirigido a
                            </div>

                            <template
                                x-if="detalle.puestos && detalle.puestos.length > 0">

                                <div class="d-flex flex-wrap gap-2">

                                    <template
                                        x-for="puesto in detalle.puestos"
                                        :key="puesto">

                                        <span class="badge bg-primary"
                                              x-text="puesto">
                                        </span>

                                    </template>

                                </div>

                            </template>

                            <template
                                x-if="!detalle.puestos || detalle.puestos.length === 0">

                                <div class="text-muted">
                                    S/I
                                </div>

                            </template>

                        </div>

                    </div>

                </div>

                <!-- DETALLE -->
                <template x-if="detalle.detalle">

                    <div>

                        <hr>

                        <div class="d-flex justify-content-between align-items-center mb-2">

                            <label class="text-secondary small mb-0">
                                Detalle
                            </label>

                            <template x-if="detalle.url">

                                <a :href="detalle.url"
                                   target="_blank"
                                   class="btn btn-sm btn-light">

                                    <i class="ti ti-download"></i>
                                    Descargar archivo

                                </a>

                            </template>

                        </div>

                        <div class="border p-2"
                             style="white-space: pre-wrap;"
                             x-text="detalle.detalle">
                        </div>

                    </div>

                </template>

                <!-- ASISTENCIA -->
                <template x-if="detalle.asistencia_url">

                    <div>

                        <hr>

                        <div class="d-flex align-items-center gap-2">

                            <span class="text-secondary small">
                                Fo.ADMONGAS.010 (Comunicación interna)
                            </span>

                            <a :href="detalle.asistencia_url"
                               target="_blank"
                               class="btn btn-sm btn-primary">

                                <i class="ti ti-download"></i>
                                Descargar asistencia

                            </a>

                        </div>

                    </div>

                </template>

            </div>

        </div>

    </div>

</div>

<!-- Modal Buscar -->

<div class="modal fade" id="modalBuscar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

    <div class="modal-header modal-colored-header bg-primary text-white">
        <h4 class="modal-title text-white">Buscar</h4>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" @click="limpiarBusqueda()"></button>
    </div>

    <div class="modal-body">


        <label class="form-label">* Agregar Año:</label>
        <input type="number" class="form-control mb-2" x-model="year"
                :class="errors.year ? 'is-invalid' : ''"
                @input="errors.year = false">

        </div>

    <div class="modal-footer">
        <button 
            class="btn bg-danger-subtle text-danger"
            data-bs-dismiss="modal"
            aria-label="Close"
            @click="year ? resetBusqueda() : limpiarBusqueda()">
            <i class="ti ti-x"></i>
            <span x-text="year ? 'Cancelar búsqueda' : 'Cancelar'"></span>
            
        </button>
        <button class="btn btn-success" @click="buscarYear()">
            <i class="ti ti-search"></i> Buscar
        </button>
    </div>

    </div>
    </div>
</div>

    <!-- -- -->

    <!-- Modal Quejas y Sugerencias -->

<div class="modal fade" id="modalQS" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="scroll-long-outer-modal" aria-hidden="true">

    <div class="modal-dialog modal-dialog-scrollable modal-lg">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">
                <h4 class="modal-title text-white">
                    Quejas y sugerencias
                </h4>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                        @click="limpiarQS()">
                </button>
            </div>

            <div class="modal-body">


                    <h5 class="mb-3 fw-bold">
                        1. Datos para ser llenados por el cliente
                    </h5>

                    <div class="row">

                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                * Fecha:
                            </label>

                            <input type="date"
                                   class="form-control"
                                   x-model="qs.fecha"
                                   :class="errorsqs.fecha ? 'is-invalid' : ''"
                                   @input="errorsqs.fecha = false">
                        </div>

                        <div class="col-md-8 mb-3">
                            <label class="form-label">
                                * Nombre:
                            </label>

                            <input type="text"
                                   class="form-control"
                                   x-model="qs.nombre"
                                   :class="errorsqs.nombre ? 'is-invalid' : ''"
                                   @input="errorsqs.nombre = false">
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">
                                * Exposición de los motivos y del hecho causante:
                            </label>

                            <textarea class="form-control"
                                      rows="4"
                                      x-model="qs.motivos"
                                      :class="errorsqs.motivos ? 'is-invalid' : ''"
                                      @input="errorsqs.motivos = false">
                            </textarea>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                * Nombre de a quien va dirigida la queja:
                            </label>

                            <input type="text"
                                   class="form-control"
                                   x-model="qs.dirigido"
                                   :class="errorsqs.dirigido ? 'is-invalid' : ''"
                                   @input="errorsqs.dirigido = false">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                * Datos de contacto:
                            </label>

                            <input type="text"
                                   class="form-control"
                                   x-model="qs.contacto"
                                   :class="errorsqs.contacto ? 'is-invalid' : ''"
                                   @input="errorsqs.contacto = false">
                        </div>

                    </div>

                <!-- ===================================== -->
                <!-- RESPONSABLE -->
                <!-- ===================================== -->


                    <h5 class="mb-3 fw-bold">
                        2. Datos a ser llenados por quien atiende la queja
                    </h5>

                    <div class="row">

                        <div class="col-12 mb-3">
                            <label class="form-label">
                                * Nombre y puesto de quien atiende la queja:
                            </label>

                            <input type="text"
                                   class="form-control"
                                   x-model="qs.nombre_puesto"
                                   :class="errorsqs.nombre_puesto ? 'is-invalid' : ''"
                                   @input="errorsqs.nombre_puesto = false">
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">
                                * Efectos o consecuencias de la queja:
                            </label>

                            <textarea class="form-control"
                                      rows="3"
                                      x-model="qs.efectos"
                                      :class="errorsqs.efectos ? 'is-invalid' : ''"
                                      @input="errorsqs.efectos = false">
                            </textarea>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">
                                * Solución propuesta y adoptada:
                            </label>

                            <textarea class="form-control"
                                      rows="3"
                                      x-model="qs.solucion"
                                      :class="errorsqs.solucion ? 'is-invalid' : ''"
                                      @input="errorsqs.solucion = false">
                            </textarea>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                * Plazo para llevarla a cabo:
                            </label>

                            <input type="text"
                                   class="form-control"
                                   x-model="qs.plazo"
                                   :class="errorsqs.plazo ? 'is-invalid' : ''"
                                   @input="errorsqs.plazo = false">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                * Confirmación de la resolución:
                            </label>

                            <input type="text"
                                   class="form-control"
                                   x-model="qs.confirmacion"
                                   :class="errorsqs.confirmacion ? 'is-invalid' : ''"
                                   @input="errorsqs.confirmacion = false">
                        </div>

                    </div>


            </div>

            <div class="modal-footer">

                <button class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                        @click="limpiarQS()">

                    <i class="ti ti-x"></i> Cancelar
                </button>

                <button class="btn btn-success"
                        @click="guardarQS()">

                    <i class="ti ti-check"></i> Crear
                </button>

            </div>

        </div>

    </div>

</div>

<!-- FIN MODAL -->

</div>

<!-- ------------------------- -->
<!-- inicio offcanvas -------- -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasExampleLabel">
            Bienvenido al elemento 7. COMUNICACIÓN, PARTICIPACIÓN Y CONSULTA, del Sistema de Administración
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

      <p>Aquí vas a encontrar el formato para el registro y seguimiento de la comunicación interna y externa de la empresa.</b>.</p>
          <hr>
          <label class="fw-bold">Que se comunica:</label>
          <ul class="list-group list-group-flush">
          <li class="list-group-item">Implementación del Sistema de Administración</li>
          <li class="list-group-item">Política, objetivos y Metas</li>
          <li class="list-group-item">Cumplimiento de requisitos legales</li>
          <li class="list-group-item">Actos y condiciones inseguras</li>
          <li class="list-group-item">Situaciones de emergencia</li>
          <li class="list-group-item">Respuesta a quejas</li>
          </ul>

          <hr>
          <label class="fw-bold">Como hacerlo:</label>
          <ul class="list-group list-group-flush">
          <li >Atreves del correo electrónico</li>
          <li class="list-group-item">Vía telefónica</li>
          <li class="list-group-item">Distribución de minutas y actas de reuniones</li>
          <li class="list-group-item">Tableros, carteles, trípticos</li>
          <li class="list-group-item">Portal AdmonGas</li>
          </ul>
          <hr>

          <label class="fw-bold">Responsables:</label>
          <p>Recuerda que es responsabilidad del <a class="text-danger fw-bold">Representante Técnico</a> (RT), <a class="text-danger fw-bold">Gerente de la Estación</a>, hacer los registros del seguimiento a quejas y sugerencias de los clientes (Comunicación Externa), así como también registrar la comunicación interna que no se halla ejecutado a través del portal.
          </p>
          <p>
            <label class="text-danger fw-bold">¡Importante!</label><br>
            Las quejas son una oportunidad para afianzar nuestra relación con el cliente, se sentirá atendido, escuchado y como parte valiosa que aporta información de la empresa, por lo que, si aún no cuentas con un <b>buzón de quejas y sugerencias</b>, es momento de hacerlo.
          </p>
          <p class="text-muted"><small>*El buzón debe ser colocado en una parte visible de la estación y debe de contar en todo momento con papel y pluma, recuerda revisar el contenido una vez al mes y dar seguimiento.</small></p>

    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->

