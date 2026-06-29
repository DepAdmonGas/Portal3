<div id="container" class="pb-4"
x-data="{ ...actions(), ...comunicados()}">

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
                <a class="dropdown-item" href="javascript:void(0)" @click="openModalComunicado()"><i class="ti ti-plus"></i> Agregar</a>
              </li>
            </ul>
          </div>   
      </div>
  </div>

<table class="table table-sm table-bordered table-striped table-hover mb-0 pb-0">

        <thead>

            <tr>
                <th class="text-center align-middle bg-primary text-white">
                    #
                </th>
                <th class="text-center align-middle bg-primary text-white">
                    Fecha
                </th>
                <th class="text-center align-middle bg-primary text-white">
                    Tema
                </th>
                <th class="text-center align-middle bg-primary text-white">
                    Detalle
                </th>
                <th class="text-center align-middle bg-primary text-white">
                    Dirigido a
                </th>
                <th
                    width="35"
                    class="text-center align-middle bg-primary text-white">
                    <i class="ti ti-dots-vertical fs-7"></i>
                </th>
            </tr>
        </thead>
        <tbody>
            <template
                x-if="comunicados.length === 0">
                <tr>
                    <td
                        colspan="7"
                        class="text-center">
                        <small>
                            No se encontró información para mostrar
                        </small>
                    </td>
                </tr>
            </template>
            <template
                x-for="item in comunicados"
                :key="item.id">
                <tr>
                    <td
                        class="text-center align-middle fw-bolder"
                        x-text="item.id">
                    </td>
                    <td
                        class="text-center align-middle"
                        x-text="item.fecha_larga">
                    </td>
                    <td
                        class="text-center align-middle"
                        x-text="item.tema">
                    </td>
                    <td
                        class="text-center align-middle"
                        x-text="item.detalle">
                    </td>

                    <td class="text-center align-middle">

                    <template
                        x-for="puesto in item.dirigidoa"
                        :key="puesto.id">

                        <span
                            class="badge bg-info-subtle text-info me-1 mb-1"
                            x-text="puesto.puesto">
                        </span>

                    </template>

                    </td>

                     <td
                        class="text-center align-middle">

                        <div class="dropdown dropstart">
                    <a href="javascript:void(0)" data-bs-toggle="dropdown">
                         <i class="ti ti-dots-vertical fs-7"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-3"
                            href="javascript:void(0)"
                            @click="verDetalle(item)">
                            <i class="fs-4 ti ti-eye"></i>Detalle
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
                            @click="eliminar(item)">
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


<div
class="modal fade"
id="modalComunicado"
tabindex="-1">

<div class="modal-dialog modal-lg">

<div class="modal-content">

<div class="modal-header">

<h5>Crear comunicado</h5>

<button
class="btn-close"
data-bs-dismiss="modal">
</button>

</div>

<div class="modal-body">

<div class="mb-3">

<label class="form-label fw-bolder">Tema:</label>

<input
class="form-control"
x-model="comunicado.tema"
:class="errors.tema ? 'is-invalid' : ''"
@input="errors.tema = false">

</div>

<div class="mb-3">

<label class="form-label fw-bolder">Detalle:</label>

<textarea
class="form-control"
rows="5"
x-model="comunicado.detalle"
:class="errors.detalle ? 'is-invalid' : ''"
@input="errors.detalle = false">
</textarea>

</div>

<div class="mb-3">

    <label class="form-label fw-bolder">Dirigido a:</label>
    <div class="select2-modal-field is-select2-pending"
    x-ref="dirigidoaWrapper"
    :class="errors.dirigidoa ? 'is-invalid' : ''">

    <select id="dirigidoa"
    x-ref="selectDirigidoa"
    multiple>

    <template x-for="puesto in puestos" :key="puesto.id">

    <option :value="puesto.id"
     x-text="puesto.tipo_puesto">
    </option>

    </template>

    </select>
    </div>

</div>

<div class="mb-3">

<label class="form-label fw-bolder">Archivo:</label>

<input
type="file"
class="form-control"
id="archivoComunicado"

@change="
comunicado.archivo =
$event.target.files[0]
">

</div>

</div>

<div class="modal-footer">

                <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">

                    Cancelar

                </button>

                <button
                class="btn btn-success"
                @click="guardarComunicado()">

                Crear comunicado

                </button>

</div>

</div>

</div>

</div>

<div class="modal fade"
     id="modalDetalleComunicado"
     tabindex="-1">

    <div class="modal-dialog modal-lg modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    Detalle del comunicado
                </h5>

                <button
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="row mb-3">

                    <div class="col-md-6">

                        <label class="fw-bolder">
                            Fecha:
                        </label>

                        <div class="mt-2" x-text="detalleComunicado.fecha_larga"></div>

                    </div>

                    <div class="col-md-6">

                        <label class="fw-bolder">
                            Tema:
                        </label>

                        <div class="mt-2" x-text="detalleComunicado.tema"></div>

                    </div>

                </div>

                <div class="mb-3">

                    <label class="fw-bolder">
                        Dirigido a:
                    </label>

                    <div class="mt-2">

                        <template
                            x-for="puesto in detalleComunicado.dirigidoa"
                            :key="puesto.id">

                            <span
                                class="badge bg-info-subtle text-info me-1 mb-1"
                                x-text="puesto.puesto">
                            </span>

                        </template>

                    </div>

                </div>

                <div class="mb-3">

                    <label class="fw-bolder">
                        Detalle:
                    </label>

                    <div class="border rounded p-3 bg-light mt-2"
                         style="white-space:pre-wrap;"
                         x-text="detalleComunicado.detalle">
                    </div>

                </div>

                <template x-if="detalleComunicado.archivo">

                    <div class="mb-3">

                        <label class="fw-bolder">
                            Archivo:
                        </label>

                        <div>

                            <a
                                :href="detalleComunicado.archivo"
                                target="_blank"
                                class="btn btn-outline-primary mt-2">

                                <i class="ti ti-file-download"></i>

                                Descargar archivo

                            </a>

                        </div>

                    </div>

                </template>

            </div>

        </div>

    </div>

</div>

</div>