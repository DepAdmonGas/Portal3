<div id="container" class="mb-4"
x-data="{ ...actions(), ...mantenimientoCorrectivo()}"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>
 
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

<div class="text-end">
    <div class="btn-group">
        <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="ti ti-dots-vertical fs-4"></i>
        </button>
        <ul class="dropdown-menu animated rubberBand">
          <li><a class="dropdown-item pointer"  href="javascript:void(0)" @click="openBuscarModal()"><i class="ti ti-search"></i> Buscar </a></li>
          <li>
              <a class="dropdown-item pointer" :href="pdfUrl"><i class="ti ti-download"></i> Descargar</a>
          </li>
        </ul>
    </div>
</div>

  <div class="datatables">
        <div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
      <table id="table-mantenimiento-correctivo" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>Folio</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Nombre del equipo o área donde se detecta la no conformidad</th>
            <th>Descripción breve del hallazgo detectado que requiere mantenimiento</th>
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
    class="modal fade"
    id="ModalBuscar"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">
            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
                <i class="ti ti-search"></i>   
                Buscar
                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    @click="limpiarBuscar()">
                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body">

  
                    <!-- YEAR -->                
                    
                    <label class="form-label mt-2">* Año:</label>
                    <select
                        class="form-select mb-3"
                        x-model="filtro.year"
                        :class="errorsBuscar.year ? 'is-invalid' : ''"
                        @input="errorsBuscar.year = false">

                        <option value="">
                            Selecciona una opción...
                        </option>

                        <template x-for="year in years">

                            <option
                                :value="year"
                                x-text="year">
                            </option>

                        </template>

                    </select>

                    <!-- MES -->
                    <label class="form-label mt-2">Mes:</label>

                    <select
                        class="form-select"
                        x-model="filtro.mes">

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
                    <i class="ti ti-x"></i> Cancelar
                </button>

                <button
                class="btn btn-success"
                @click="buscar()">
                    <i class="ti ti-search"></i></i> Buscar
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
                    <i class="ti ti-eye"></i>
                    Detalle de Mantenimiento Correctivo
                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <!-- INFORMACION -->
                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-bolder">
                            Folio:
                        </label>

                        <div
                            x-text="detalle.folio">
                        </div>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-bolder">
                            Fecha:
                        </label>

                        <div
                            x-text="detalle.fechacreacion">
                        </div>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-bolder">
                            Hora:
                        </label>

                        <div
                            x-text="detalle.horacreacion">
                        </div>

                    </div>

                    <div class="col-md-12 mb-3">

                        <label class="form-label fw-bolder">
                            Equipo o Área:
                        </label>

                        <div
                            x-text="detalle.nombre_equipo">
                        </div>

                    </div>

                    <div class="col-md-12 mb-3">

                        <label class="form-label fw-bolder">
                            Hallazgo Detectado:
                        </label>

                        <div
                            x-text="detalle.descripcion_hallazgo">
                        </div>

                    </div>

                    <div class="col-md-12 mb-3">

                        <label class="form-label fw-bolder">
                            Actividad Realizada:
                        </label>

                        <div
                            x-text="detalle.descripcion_actividad">
                        </div>

                    </div>

                    <div class="col-md-12 mb-4">

                        <label class="form-label fw-bolder">
                            Herramienta Utilizada:
                        </label>

                        <div
                            x-text="detalle.herramienta">
                        </div>

                    </div>

                </div>

                <!-- TITULO EVIDENCIAS -->
                <div class="d-flex align-items-center mb-3">

                    <h5 class="mb-0">
                        Evidencias
                    </h5>

                    <span
                        class="badge bg-primary ms-2"
                        x-text="detalle.evidencias.length">
                    </span>

                </div>

                <!-- SIN EVIDENCIAS -->
                <div
                    class="text-center py-5 border rounded"
                    x-show="detalle.evidencias.length <= 0">

                    <i class="ti ti-photo-off fs-7 text-muted"></i>

                    <div class="mt-2 text-muted">
                        No hay evidencias registradas
                    </div>

                </div>

                <!-- EVIDENCIAS -->
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
                                            object-fit:cover;
                                        ">

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

<!-- MODAL EDITAR -->
<div
    class="modal fade"
    id="ModalEditar"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
                    <i class="ti ti-edit"></i>
                    Editar Mantenimiento Correctivo
                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <label class="form-label">
                    * Nombre del equipo:
                </label>

                <input
                    type="text"
                    class="form-control mb-3"
                    x-model="form.nombre_equipo">

                <label class="form-label">
                    * Hallazgo detectado:
                </label>

                <textarea
                    class="form-control mb-3"
                    rows="3"
                    x-model="form.descripcion_hallazgo">
                </textarea>

                <label class="form-label">
                    * Actividad realizada:
                </label>

                <textarea
                    class="form-control mb-3"
                    rows="3"
                    x-model="form.descripcion_actividad">
                </textarea>

                <label class="form-label">
                    * Herramienta utilizada:
                </label>

                <textarea
                    class="form-control"
                    rows="2"
                    x-model="form.herramienta">
                </textarea>

            </div>

            <div class="modal-footer">

                <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">

                    <i class="ti ti-x"></i> Cancelar
                </button>

                <button
                    class="btn btn-success"
                    @click="guardarEditar()">

                    <i class="ti ti-check"></i> Actualizar
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
                    <i class="ti ti-camera"></i>
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
                        Subir imágenes:
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
                    class="btn btn-success"
                    @click="guardarEvidencias()">

                    <i class="ti ti-check"></i> Guardar
                </button>

            </div>

        </div>

    </div>

</div>
<?php endif; ?>

</div>