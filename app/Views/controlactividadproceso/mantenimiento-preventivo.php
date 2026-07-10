<div id="container" class="mb-4"
x-data="{ ...actions(), ...mantenimientoPreventivo()}">

<div class="text-end">
    <div class="btn-group">
        <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="ti ti-dots-vertical fs-4"></i>
        </button>
        <ul class="dropdown-menu animated rubberBand">
         <li><a class="dropdown-item"  href="javascript:void(0)" @click="openBuscarModal()"><i class="ti ti-search"></i> Buscar </a></li>
          <li>
              <a class="dropdown-item" :href="pdfUrl"><i class="ti ti-download"></i> Descargar</a>
          </li>
        
          <li>
            <hr class="dropdown-divider">
          </li>
          <li>
              <a class="dropdown-item" href="/sasisopa/control-actividades-procesos/detector-humo"><i class="ti ti-alarm-smoke"></i> Detector de Humo</a>
          </li>

          <li>
              <a class="dropdown-item" href="/sasisopa/control-actividades-procesos/extintores"><i class="ti ti-fire-extinguisher"></i> Extintores</a>
          </li>

          <li>
              <a class="dropdown-item" href="/sasisopa/control-actividades-procesos/mantenimiento-correctivo"><i class="ti ti-tool"></i> Mantenimiento Correctivo</a>
          </li>
        </ul>
    </div>
</div>

    <div class="datatables">
        <div class="table-responsive">
        <table id="table-mantenimiento-preventivo" class="table table-bordered text-nowrap align-middle w-100">
            <thead>
            <tr>
            <th>Folio</th>
			<th>Equipo o instalación</th>
			<th>Fecha</th>
            <th>Hora</th>
            <th>Estatdo</th>
            <th class="text-center" width="100px">
            <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
            </th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
        </div>
    </div>

   <!-- MODAL BUSCAR -->
    <div
    x-ref="ModalBuscar"
    class="modal fade"
    id="ModalBuscar"
    tabindex="-1"
    aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content border-0 shadow-lg">

                <!-- HEADER -->
                <div class="modal-header head-modal">

                    <div>

                        <h4 class="modal-title">
                            Buscar registros
                        </h4>

                        <small class="">
                            Filtra la información del mantenimiento preventivo
                        </small>

                    </div>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <!-- BODY -->
                <div class="modal-body">

                    <!-- EQUIPO -->
  
                        <label class="form-label fw-semibold">
                            Equipo o instalación:
                        </label>

                        <div
                            class="select2-modal-field mb-2"
                            x-ref="equipoWrapper">

                            <select
                                id="selectEquipo"
                                x-ref="selectEquipo"
                                data-width="100%">

                                <option value="">
                                    Todos
                                </option>

                                <template
                                    x-for="equipo in equipos"
                                    :key="equipo.id">

                                    <option
                                        :value="equipo.id"
                                        x-text="equipo.detalle">
                                    </option>

                                </template>

                            </select>

                        </div>

                        <label class="form-label fw-semibold">
                                Año:
                            </label>

                            <select
                                class="form-select mb-2"
                                x-model="filtro.year"
                                :class="errors.year ? 'is-invalid' : ''"
                                @input="errors.year = false">

                                <option value="">
                                    Todos
                                </option>

                                <template
                                    x-for="year in years">

                                    <option
                                        :value="year"
                                        x-text="year">
                                    </option>

                                </template>

                            </select>

                <label class="form-label fw-semibold">
                                Mes:
                            </label>

                            <select
                                class="form-select mb-2"
                                x-model="filtro.mes"
                                :class="errors.mes ? 'is-invalid' : ''"
                                @input="errors.mes = false">

                                <option value="">
                                    Todos
                                </option>

                                <option value="1">Enero</option>
                                <option value="2">Febrero</option>
                                <option value="3">Marzo</option>
                                <option value="4">Abril</option>
                                <option value="5">Mayo</option>
                                <option value="6">Junio</option>
                                <option value="7">Julio</option>
                                <option value="8">Agosto</option>
                                <option value="9">Septiembre</option>
                                <option value="10">Octubre</option>
                                <option value="11">Noviembre</option>
                                <option value="12">Diciembre</option>

                            </select>    

                </div>

                <!-- FOOTER -->
                <div class="modal-footer">

                    <button
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal"
                        @click="limpiarBuscar()">

                        Cancelar

                    </button>

                    <button
                        class="btn btn-primary"
                        @click="buscar()">

                        <i class="ti ti-search me-1"></i>
                        Buscar

                    </button>

                </div>

            </div>

        </div>

    </div>

    <!-- MODAL DETALLE -->
<div
    class="modal fade"
    id="ModalDetalle"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
                    Detalle de Mantenimiento Preventivo
                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                 <div class="row">

                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-bolder">
                            Folio
                        </label>

                        <div
                            class="border rounded p-2 bg-light"
                            x-text="detalle.folio">
                        </div>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-bolder">
                            Fecha
                        </label>

                        <div
                            class="border rounded p-2 bg-light"
                            x-text="detalle.fechacreacion">
                        </div>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-bolder">
                            Hora
                        </label>

                        <div
                            class="border rounded p-2 bg-light"
                            x-text="detalle.horacreacion">
                        </div>

                    </div>

                    <div class="col-md-12 mb-3">

                        <label class="form-label fw-bolder">
                            Equipo
                        </label>

                        <div
                            class="border rounded p-2 bg-light"
                            x-text="detalle.nombre_equipo">
                        </div>

                    </div>

                </div>

                <div class="d-flex align-items-center mb-3">

                    <h5 class="mb-0">
                        Evidencias
                    </h5>

                    <span
                        class="badge bg-primary ms-2"
                        x-text="detalle.evidencias.length">
                    </span>

                </div>

                <div
                    class="text-center py-5 border rounded"
                    x-show="detalle.evidencias.length <= 0">

                    <i class="ti ti-photo-off fs-7 text-muted"></i>

                    <div class="mt-2 text-muted">
                        No hay evidencias registradas
                    </div>

                </div>

                <div
                    class="row"
                    x-show="detalle.evidencias.length > 0">

                    <template
                        x-for="item in detalle.evidencias"
                        :key="item.id">

                        <div class="col-md-3 mb-3">

                            <div class="card shadow-sm overflow-hidden h-100">

                                <a
                                    :href="item.url"
                                    target="_blank"
                                    class="d-block">

                                    <img
                                        :src="item.url"
                                        class="w-100"
                                        style="
                                            height:240px;
                                            object-fit:cover;">

                                </a>

                            </div>

                        </div>

                    </template>

                </div>


            </div>

            <div class="modal-footer">

                <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">

                    <i class="ti ti-x"></i> Cerrar
                </button>

            </div>

        </div>

    </div>

</div>

    <!-- MODAL EVIDENCIAS -->
    <div
        class="modal fade"
        id="ModalEvidencia"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-lg modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header modal-colored-header bg-primary text-white">

                    <h4 class="modal-title text-white">
                        Evidencias
                    </h4>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label">
                            Seleccionar imágenes
                        </label>

                        <input
                            type="file"
                            class="form-control"
                            accept="image/*"
                            multiple
                            @change="handleFiles($event)">
                    </div>

                    <!-- PREVIEW -->
    <div
        class="row"
        x-show="previewImages.length > 0">

        <template
            x-for="(img,index) in previewImages"
            :key="index">

            <div class="col-md-3 mb-3">

                <div class="card shadow-sm border border-warning position-relative overflow-hidden">

                    <!-- BADGE -->
                    <span
                        class="badge bg-warning text-dark position-absolute top-0 end-0 m-2">
                        Nueva
                    </span>

                    <img
                        :src="img"
                        class="w-100"
                        style="
                            height:220px;
                            object-fit:cover;
                        ">

                    <div class="card-body p-2">

                        <button
                            class="btn btn-danger btn-sm w-100"
                            @click="removePreview(index)">

                            <i class="ti ti-x"></i>
                            Eliminar
                        </button>

                    </div>

                </div>

            </div>

        </template>

    </div>

    <!-- EVIDENCIAS -->
    <div class="row mt-4">

        <template
            x-for="(item,index) in evidencias"
            :key="item.id">

            <div class="col-md-3 mb-3">

                <div class="card shadow-sm position-relative overflow-hidden">

                    <!-- BADGE -->
                    <span
                        class="badge bg-success position-absolute top-0 end-0 m-2">
                        Guardada
                    </span>

                    <a
                        :href="item.url"
                        target="_blank"
                        class="d-block w-100">

                        <img
                            :src="item.url"
                            class="w-100"
                            style="
                                height:220px;
                                object-fit:cover;
                            ">
                    </a>

                    <div class="card-body p-2">

                        <button
                            class="btn btn-danger btn-sm w-100"
                            @click="eliminarEvidencia(item.id,index)">

                            <i class="ti ti-trash"></i>
                            Eliminar
                        </button>

                    </div>

                </div>

            </div>

        </template>

    </div>

                </div>

                <div class="modal-footer">

                    <button
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">

                        <i class="ti ti-x"></i> Cerrar
                    </button>

                    <button
                        class="btn btn-primary"
                        @click="guardarEvidencias()">

                        <i class="ti ti-check"></i> Guardar
                    </button>

                </div>

            </div>

        </div>

    </div>

</div>