<div id="container"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
data-estacion-id="<?= $estacionId ?? '' ?>"
class="pb-4"
x-data="{ ...actions(), ...seguridadContratistas()}">

<div class="text-end">
      <?= 
        !empty($permisos['crear']) ? 
        '<button type="button" class="btn bg-primary-subtle text-primary" @click="openModalRequisicion()">
        <i class="ti ti-plus"></i> Nuevo
        </button>' 
        : '' 
        ?>     
    </div>

      <div class="datatables">
    <div class="table-responsive pb-3 overflow-x-auto overflow-y-hidden">
      <table id="table-seguridad-contratista" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>Folio</th>
            <th>Fecha</th>
            <th>Solicitante</th>
            <th>Proveedor</th>
            <th colspan="2">Fo.ADMONGAS.0012</th>
            <th>Fo.ADMONGAS.0013</th>
            <th colspan="2">Fo.ADMONGAS.014</th>
            <th colspan="2">Fo.ADMONGAS.015</th>
            <th colspan="2">Carta responsiva</th>
          <th class="text-center">
          <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
          </th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

<!-- Modal Nuevo, editar -->
<div
    class="modal fade"
    id="ModalRequisicionObra"
    tabindex="-1">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content border-0 rounded-0">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">

                <span x-show="mode === 'create'">
<i class="ti ti-briefcase-2"></i>
                    Nueva requisición de obra o servicio
                </span>

                <span x-show="mode === 'edit'">
                    <i class="ti ti-edit"></i>
                    Editar requisición de obra o servicio
                </span>

            </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    @click="limpiarRequisicion()">
                </button>

            </div>

            <div class="modal-body">

                    <label class="form-label">
                        * Fecha:
                    </label>

                    <input
                        type="date"
                        class="form-control"
                        x-model="requisicion.fecha"
                        :class="errors.fecha ? 'is-invalid' : ''">

                    <div class="mt-3">
                    <label class="form-label">
                        * Descripción detallada del servicio:
                    </label>

                    <textarea
                        rows="4"
                        class="form-control"
                        x-model="requisicion.descripcion"
                        :class="errors.descripcion ? 'is-invalid' : ''">
                    </textarea>

                    </div>

                <div class="mt-3">

                    <label class="form-label">
                        * Justificación del servicio solicitado:
                    </label>

                    <textarea
                        rows="4"
                        class="form-control"
                        x-model="requisicion.justificacion"
                        :class="errors.justificacion ? 'is-invalid' : ''">
                    </textarea>

                </div>

                <div class="mt-3">

                    <label class="form-label">
                        Proveedor:
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        x-model="requisicion.proveedor">

                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal"
                    @click="limpiarRequisicion()">
                    <i class="ti ti-x"></i> 
                    Cancelar

                </button>

                <button
                    type="button"
                    class="btn btn-success"
                    @click="guardarRequisicion()">
                    <i class="ti ti-check"></i>

                    <span x-text="mode === 'create'
                        ? 'Guardar'
                        : 'Actualizar'">
                    </span>

                </button>

            </div>

        </div>

    </div>

</div>

<!-- Modal formato 12 -->
<div class="modal fade"
     id="ModalFormato12"
     tabindex="-1">

    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">

        <div class="modal-content">

            <!-- HEADER IGUAL -->
            <div class="modal-header modal-colored-header bg-primary text-white">
                <h4 class="modal-title text-white">
                   <i class="ti ti-clipboard-check"></i>
                    Autorizacion para realizar trabajos peligrosos
                </h4>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
        </div>

            <div class="modal-body">

                <!-- FECHA / UBICACIÓN (TABLA IGUAL QUE PHP) -->
                <table style="width: 100%">
                    <tr>
                        <td>
                            <input type="text"
                                   class="form-control"
                                   placeholder="Municipio"
                                   x-model="formato12.municipio">
                        </td>

                        <td>
                            <input type="text"
                                   class="form-control"
                                   placeholder="Estado"
                                   x-model="formato12.estado">
                        </td>

                        <td>, a </td>

                        <td width="80px">
                            <input type="text"
                                   class="form-control"
                                   placeholder="Día"
                                   x-model="formato12.dia">
                        </td>

                        <td> de </td>

                        <td width="80px">
                            <input type="text"
                                   class="form-control"
                                   placeholder="Mes"
                                   x-model="formato12.mes">
                        </td>

                        <td> del </td>

                        <td width="100px">
                            <input type="text"
                                   class="form-control"
                                   placeholder="Año"
                                   x-model="formato12.year">
                        </td>
                    </tr>
                </table>

                <div class="mt-1 mb-1 form-label"><b>Trabajo a realizar:</b></div>
                <textarea class="form-control"
                          rows="1"
                          x-model="formato12.trabajo_realizar"></textarea>

                <div class="mt-1 mb-1 form-label"><b>Descripcion:</b></div>
                <textarea class="form-control"
                          rows="1"
                          x-model="formato12.descripcion"></textarea>

                <div class="mt-1 mb-1 form-label"><b>Área:</b></div>
                <textarea class="form-control"
                          rows="1"
                          x-model="formato12.area"></textarea>

                <!-- FECHAS -->
                <table class="mt-3" style="width: 100%">
                    <tr>
                        <td><b class="form-label">Fecha de inicio:</b></td>
                        <td>
                            <input type="date"
                                   class="form-control"
                                   x-model="formato12.fecha_inicio">
                        </td>

                        <td><b class="form-label p-3">Fecha de término:</b></td>
                        <td>
                            <input type="date"
                                   class="form-control"
                                   x-model="formato12.fecha_termino">
                        </td>
                    </tr>

                    <tr>
                        <td><b class="form-label">Hora de Inicio:</b></td>
                        <td>
                            <input type="time"
                                   class="form-control"
                                   x-model="formato12.hora_inicio">
                        </td>

                        <td><b class="form-label p-3">Hora de Termino:</b></td>
                        <td>
                            <input type="time"
                                   class="form-control"
                                   x-model="formato12.hora_termino">
                        </td>
                    </tr>
                </table>

                <br>

                <b class="form-label">El trabajo a realizar contempla alguno de los siguientes procedimientos:</b>

                <table class="table table-bordered table-sm mt-2">
                    <tbody>

                        <template x-for="p in formato12.procedimientos"
                                  :key="p.id">

                            <tr>
                                <td x-text="p.detalle"></td>

                                <td class="text-center">
                                    <input
                                    class="pointer" 
                                    type="checkbox"
                                           :checked="p.valor == 1"
                                           @change="toggleProcedimiento(p, $event)">
                                </td>
                            </tr>

                        </template>

                    </tbody>
                </table>

                <div class="mt-1 mb-1">
                    <b class="form-label">Nombre del prestador de servicios:</b>
                </div>

                <textarea class="form-control"
                          rows="1"
                          x-model="formato12.prestador_servicio"></textarea>

                <!-- RADIO BUTTONS IGUAL -->
                <table style="width: 100%" class="mt-2">

                    <tr>
                        <td class="form-label">Cuenta con capacitación para realizar trabajos peligrosos:</td>
                        <td>
                            Si
                            <input class="pointer" type="radio"
                                   value="1"
                                   x-model="formato12.cprtp">

                            No
                            <input class="pointer" type="radio"
                                   value="0"
                                   x-model="formato12.cprtp">
                        </td>
                    </tr>

                    <tr>
                        <td class="form-label">Cuenta con todo el Equipo de Protección Personal correspondiente (EPP):</td>
                        <td>
                            Si
                            <input class="pointer" type="radio"
                                   value="1"
                                   x-model="formato12.cteppc">

                            No
                            <input class="pointer" 
                            type="radio"
                                   value="0"
                                   x-model="formato12.cteppc">
                        </td>
                    </tr>

                </table>

                <div class="text-center">
                    <small class="form-label">*De no contar con capacitación, bajo ninguna circunstancia realizara los trabajos</small>
                </div>

                <br>

                <!-- TRABAJADORES -->
                <div class="border p-2">

                    <b class="form-label">Datos de los trabajadores que acuden al servicio:</b>

                    <div class="row mt-2">

                        <div class="col-4 form-label">
                            * Nombre:
                            <input type="text"
                                   class="form-control"
                                   x-model="trabajador.nombre">
                        </div>

                        <div class="col-4 form-label">
                            * Puesto:
                            <input type="text"
                                   class="form-control"
                                   x-model="trabajador.puesto">
                        </div>

                        <div class="col-4 form-label">
                            * No. De Seguro:
                            <input type="text"
                                   class="form-control"
                                   x-model="trabajador.no_seguro">
                        </div>

                    </div>

                    <div class="text-end mt-2">
                        <button type="button"
                                class="btn bg-primary-subtle text-primary"
                                @click="agregarTrabajador()">
                            Agregar
                        </button>
                    </div>

                    <table class="table table-bordered table-sm mt-2" id="table-trabajadores">

                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Puesto</th>
                            <th>No. De Seguro</th>
                            <th class="text-center"><i class="ti ti-trash fs-6 text-muted"></i></th>
                        </tr>
                    </thead>

                    <tbody>

                        <template x-for="t in formato12.trabajadores"
                                :key="t.id">

                            <tr>
                                <td x-text="t.nombre"></td>
                                <td x-text="t.puesto"></td>
                                <td x-text="t.no_seguro"></td>

                                <td class="text-center">

                                <a @click="eliminarTrabajador(t.id, t.nombre)"><i class="ti ti-trash fs-6 text-danger"></i></a>

                                </td>
                            </tr>

                        </template>

                    </tbody>

                </table>

                </div>

                <hr>

                <!-- ENCARGADOS -->
                <b class="form-label">* Encargado de la estación de servicio de darle seguimiento al servicio:</b>

                <div class="row mt-2">

                    <div class="col-10">

                      <select class="form-select"
                            x-model="encargado.id_personal">

                        <option value="">Seleccione una opcion...</option>

                        <template x-for="e in formato12.encargadosList" :key="e.id">
                            <option :value="e.id" x-text="e.nombre"></option>
                        </template>

                    </select>

                    </div>

                    <div class="col-2 text-end">

                        <button type="button"
                                class="btn bg-primary-subtle text-primary"
                                @click="agregarEncargado()">
                            Agregar
                        </button>

                    </div>

                </div>

                <table class="table table-bordered table-sm mt-2" id="table-encargados">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Puesto</th>
                        <th>No. De Seguro</th>
                        <th class="text-center"><i class="ti ti-trash fs-6 text-muted"></i></th>
                    </tr>
                    </thead>
                <tbody>

                   <template x-for="e in formato12.encargados" :key="e.id">

                        <tr>
                            <td x-text="e.nombre"></td>
                            <td x-text="e.puesto"></td>
                            <td x-text="e.seguro_social"></td>

                            <td class="text-center">
                                <a @click="eliminarEncargado(e.id, e.nombre)"><i class="ti ti-trash fs-6 text-danger"></i></a>
                            </td>
                        </tr>

                        </template>

                </tbody>

            </table>

                <hr>

                <!-- EXTERNO -->
                <div class="border p-2 mt-2">

                    <small class="form-label">Trabajo realizado por un externo</small>

                    <div class="mt-1 form-label"><b>Nombre empresa:</b></div>
                    <textarea class="form-control mt-1"
                              rows="1"
                              x-model="formato12.nombre_empresa"></textarea>

                    <div class="mt-1 form-label"><b>Nombre del responsable:</b></div>
                    <textarea class="form-control mt-1"
                              rows="1"
                              x-model="formato12.nombre_responsable"></textarea>

                    <div class="text-center mt-3">
                        <small class="form-label">Nota: Si el personal es externo deberá presentar su procedimiento para realizar la actividad</small>
                    </div>

                </div>

            </div>

            <div class="modal-footer">

            <button
                      class="btn bg-danger-subtle text-danger"
                      data-bs-dismiss="modal">

                      <i class="ti ti-x"></i> Cancelar

                  </button>

                <button type="button"
                        class="btn btn-success"
                        @click="guardarFormato12">
                    <i class="ti ti-check"></i> Actualizar
                </button>

            </div>

        </div>

    </div>

</div>

<!-- Modal Formato 14 -->
<div
    class="modal fade"
    id="modalFormato14"
    tabindex="-1">

    <div class="modal-dialog modal-xl modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
                    <i class="ti ti-file"></i>
                    Fo.ADMONGAS.014
                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <!-- Folio / Fecha -->

                <div class="row mt-2">

                    <div class="col-md-6">

                        <div class="mb-0">
                            <label class="form-label">
                                No. de folio:
                            </label>
                        </div>

                        <input
                            class="form-control"
                            x-model="formato14.folio"
                            disabled>

                    </div>

                    <div class="col-md-6">

                        <div class="mb-0">
                            <label class="form-label">
                                Fecha:
                            </label>
                        </div>

                        <input
                            class="form-control"
                            x-model="formato14.fecha"
                            disabled>

                    </div>

                </div>

                <!-- Solicitante -->

                <div class="row mt-3">

                    <div class="col-md-6">

                        <div class="mb-0">
                            <label class="form-label">
                                Nombre del solicitante:
                            </label>
                        </div>

                        <input
                            class="form-control"
                            x-model="formato14.nombre_solicitante"
                            disabled>

                    </div>

                    <div class="col-md-6">

                        <div class="mb-0">
                            <label class="fw-bolder form-label">
                                Empresa solicitante:
                            </label>
                        </div>

                        <input
                            class="form-control"
                            x-model="formato14.empresa"
                            disabled>

                    </div>

                </div>

                <!-- Descripción -->

                <div class="row mt-3">

                    <div class="col-12">

                        <div class="mb-0">
                            <label class="form-label">
                                Descripción detallada del servicio que requiere:
                            </label>
                        </div>

                        <div
                            
                            x-html="formato14.descripcion">
                        </div>

                    </div>

                </div>

                <!-- Justificación -->

                <div class="row mt-3">

                    <div class="col-12">

                        <div class="mb-0">
                            <label class="form-label">
                                Justificación del servicio solicitado:
                            </label>
                        </div>

                        <div
                            x-html="formato14.justificacion">
                        </div>

                    </div>

                </div>

                <!-- Formato -->

                <div
                    class=" form-label  mt-4">

                    Entrega de información al contratista
                    Fo.ADMONGAS.014

                </div>

                <div class="pb-2">

                    <small form-label>

                        Descarga el archivo, llena la información
                        solicitada y adjunta el PDF generado.

                    </small>

                </div>

                <table
                    class="table table-bordered table-striped table-sm">

                    <tr>

                        <td
                            width="50"
                            class="text-center align-middle">

                            <a
                                href="/uploads/archivos/Fo.ADMONGAS/Fo.ADMONGAS.014.xlsx"
                                download>

                                <i class="ti ti-file-type-xls text-success fs-7"></i>

                            </a>

                        </td>

                        <td class="align-middle">

                            <input
                                type="file"
                                class="form-control"
                                accept=".pdf"
                                @change="
                                formato14Archivo =
                                $event.target.files[0]">

                        </td>

                        <td
                            width="80"
                            class="text-center align-middle">

                            <template
                                x-if="formato14.archivo_url">

                                <a
                                    :href="formato14.archivo_url"
                                    target="_blank">

                                    <i
                                        class="ti ti-file-type-pdf text-danger fs-7">
                                    </i>

                                </a>

                            </template>

                            <template
                                x-if="!formato14.archivo_url">

                                <i
                                    class="ti ti-circle-x text-muted fs-7">
                                </i>

                            </template>

                        </td>

                    </tr>

                </table>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">

                    <i class="ti ti-x"></i> Cancelar

                </button>

                <button
                    type="button"
                    class="btn btn-success"
                    @click="guardarFormato14()">

                    <i class="ti ti-check"></i> Actualizar

                </button>

            </div>

        </div>

    </div>

</div>

<!-- Modal Formato 15 -->

    <div
    class="modal fade"
    id="modalFormato15"
    tabindex="-1">

    <div class="modal-dialog modal-xl modal-dialog-centered">

    <div class="modal-content">

    <div class="modal-header modal-colored-header bg-primary text-white">

    <h4 class="modal-title text-white">
        <i class="ti ti-list-check"></i>
    Lista de Verificación
    </h4>

    <button
    type="button"
    class="btn-close btn-close-white"
    data-bs-dismiss="modal">
    </button>

    </div>

    <div class="modal-body">

    <div class="row">

    <div class="col-md-6">

    <label class="form-label">Fecha:</label>

    <input
    type="date"
    class="form-control"
    x-model="formato15.fecha_lv">

    </div>

    <div class="col-md-6">

    <label class="form-label">Hora:</label>

    <input
    type="time"
    class="form-control"
    x-model="formato15.hora_lv">

    </div>

    </div>

    <table class="table table-bordered mt-4 table-striped">

    <thead>

    <tr>
    <th>Pregunta</th>
    <th class="text-center">SI</th>
    <th class="text-center">NO</th>
    </tr>

    </thead>

    <tbody>

    <tr>
    <td>El trabajo fue completado conforme a lo solicitado</td>

    <td class="text-center">
    <input
    class="pointer"
    type="radio"
    value="1"
    x-model="formato15.pregunta1">
    </td>

    <td class="text-center">
    <input
    class="pointer"
    type="radio"
    value="0"
    x-model="formato15.pregunta1">
    </td>
    </tr>

    <tr>
    <td>El trabajo se realizó conforme al procedimiento</td>

    <td class="text-center">
    <input 
    class="pointer"
    type="radio"
    value="1"
    x-model="formato15.pregunta2">
    </td>

    <td class="text-center">
    <input 
    class="pointer"
    type="radio"
    value="0"
    x-model="formato15.pregunta2">
    </td>
    </tr>

    <tr>
    <td>En todo momento se utilizó el EPP</td>

    <td class="text-center">
    <input 
    class="pointer"
    type="radio"
    value="1"
    x-model="formato15.pregunta3">
    </td>

    <td class="text-center">
    <input 
    class="pointer"
    type="radio"
    value="0"
    x-model="formato15.pregunta3">
    </td>
    </tr>

    <tr>
    <td>Los trabajadores tomaron en cuenta los procedimientos de seguridad</td>

    <td class="text-center">
    <input 
    class="pointer"
    type="radio"
    value="1"
    x-model="formato15.pregunta4">
    </td>

    <td class="text-center">
    <input 
    class="pointer"
    type="radio"
    value="0"
    x-model="formato15.pregunta4">
    </td>
    </tr>

    <tr>
    <td>Ocurrió algún accidente durante el servicio realizado</td>

    <td class="text-center">
    <input 
    class="pointer"
    type="radio"
    value="1"
    x-model="formato15.pregunta5">
    </td>

    <td class="text-center">
    <input
    class="pointer"
    type="radio"
    value="0"
    x-model="formato15.pregunta5">
    </td>
    </tr>

    </tbody>

    </table>

    <div>

    <label class="form-label">Supervisó:</label>

<select
    class="form-select"
    x-model.number="formato15.id_usuario">

    <option value="">
        Seleccione una opcion...
    </option>

    <template
        x-for="usuario in supervisores"
        :key="usuario.id">

        <option
            :value="usuario.id"
            x-text="usuario.nombre">
        </option>

    </template>

</select>

    </div>

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
    @click="guardarFormato15()">

    <i class="ti ti-check"></i> Actualizar

    </button>

    </div>

    </div>
    </div>
    </div>

<!-- Carta Responsiva -->
<div
    class="modal fade"
    id="ModalCartaResponsiva"
    tabindex="-1">

    <div class="modal-dialog modal-xl modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
                    <i class="ti ti-mail-opened"></i>
                    Carta Responsiva
                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <table class="w-100">
                    <tr>

                        <td>
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Municipio"
                                x-model="cartaResponsiva.municipio">
                        </td>

                        <td>
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Estado"
                                x-model="cartaResponsiva.estado">
                        </td>

                        <td class="px-2">, a</td>

                        <td width="90">
                            <input
                                type="text"
                                class="form-control"
                                x-model="cartaResponsiva.dia">
                        </td>

                        <td class="px-2">de</td>

                        <td width="120">
                            <input
                                type="text"
                                class="form-control"
                                x-model="cartaResponsiva.mes">
                        </td>

                        <td class="px-2">del</td>

                        <td width="100">
                            <input
                                type="text"
                                class="form-control"
                                x-model="cartaResponsiva.year">
                        </td>

                    </tr>
                </table>

                <h6 class="mt-4">
                    A QUIEN CORRESPONDA.
                </h6>

                <p class="mt-1 mb-1">
                    Por este conducto le mando un cordial saludo,
                    a su vez:
                </p>

                <input
                    type="text"
                    class="form-control"
                    placeholder="Representante legal"
                    x-model="cartaResponsiva.representante_legal">

                <p class="mt-3 mb-1">
                    Representante legal de:
                </p>

                <input
                    type="text"
                    class="form-control"
                    placeholder="Razón social"
                    x-model="cartaResponsiva.razon_social">

                <p class="mt-3 mb-1">
                    Con domicilio en:
                </p>

                <input
                    type="text"
                    class="form-control"
                    placeholder="Domicilio"
                    x-model="cartaResponsiva.domicilio">

                <div class="mt-3">

                    Doy mi responsivo total de los daños o
                    perjuicios de riesgos y aspectos ambientales
                    presentados durante las actividades u
                    operaciones derivadas de los contratistas,
                    subcontratistas, prestadores de servicio y
                    personal interno que labore dentro de la
                    estación de servicio antes mencionada.

                    <br><br>

                    Por último, ratifico mi voluntad a efecto
                    de cubrir con todas las obligaciones
                    correspondientes.

                    <br><br>

                    Sirva la presente para todos los fines
                    legales a que haya lugar.

                </div>


                <input
                    type="text"
                    class="form-control text-center"
                    placeholder="Apoderado legal"
                    x-model="cartaResponsiva.apoderado_legal">

                <div class="text-center mt-2 form-label">
                    Nombre del apoderado legal
                </div>

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
                    @click="guardarCartaResponsiva()">

                    <i class="ti ti-check"></i> Actualizar

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
            Bienvenido al elemento 12 SEGURIDAD DE CONTRATISTAS
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

      <p>
            Aquí vas a poder visualizar los formatos de registro que tendrás que realizar cada vez que se requiera alguna obra o servicio por un contratista, prestador de servicio o proveedor dentro de la estación.
          </p>
          <p>
            La política debe ser comunicada a todo el personal incluyendo clientes, prestadores de servicios y proveedores.
          </p>

          <hr>

          <label class="fw-bold">Como hacerlo:</label>
          <ul class="list-group list-group-flush">
            <li class="list-group-item">Da clic en el icono de mas para generar el registro de una obra o servicio (contempla que el procedimiento para llevar acabo el presente registro se requiere de los siguientes pasos).
              <ul class="list-group list-group-flush">
                <li class="list-group-item">1. Requisición de obra o servicio</li>
                 <li class="list-group-item">2. Autorización para realizar trabajos peligrosos (Solo si aplica)</li>
                 <li class="list-group-item">3. Carta responsiva</li>
                 <li class="list-group-item">4. Entrega de información al contratista</li>
                 <li class="list-group-item">5. Listas de verificación</li>
              </ul>
            </li>
            <li class="list-group-item">Los formatos que se encuentran en la parte superior derecha deberás  descargarlos, llenarlos, firmarlos, para posteriormente subirlos en el icono que corresponde.</li>
          </ul>

          <hr>

          <label class="fw-bold">Responsables:</label>
          <p>Recuerda que es responsabilidad del <label class="text-danger fw-bold">Representante Técnico</label> (RT), <label class="text-danger fw-bold">Gerente de la Estación</label> conocer y realizar los registros correspondientes de cada elemento del SA.</p>

    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->

</div>