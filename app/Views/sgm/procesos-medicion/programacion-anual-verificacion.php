<div class="pb-4" x-data="{ ...actions(), ...programacionAnual() }">

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
                                <a class="dropdown-item" href="javascript:void(0)" @click="openModalNuevo()"><i class="ti ti-plus"></i> Agregar</a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="javascript:void(0)" @click="openModalBuscar()"><i class="ti ti-search"></i> Buscar</a>
                            </li>
                            <li>
                                <a class="dropdown-item" :href="pdf"><i class="ti ti-download"></i> Descargar</a>
                            </li>
                        </ul>
                    </div>

                </div>
            </div>

            <table class="table table-sm table-bordered align-middle mt-3">

                <template x-if="lista.length === 0">
                    <tbody>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                No existe información para mostrar.
                            </td>
                        </tr>
                    </tbody>
                </template>

                <template x-for="grupo in lista" :key="grupo.categoria">

                    <tbody>

                        <tr>
                            <td class="bg-muted text-white"><strong x-text="grupo.categoria"></strong></td>
                            <td class="bg-muted text-white"><strong>Periodicidad</strong></td>
                            <td class="bg-muted text-white"><strong>Fecha</strong></td>
                            <td class="bg-muted text-center text-white"><i class="ti ti-trash fs-7"></td>
                        </tr>

                        <template x-if="grupo.items.length === 0">
                            <tr>
                                <td colspan="4" class="text-center text-muted">
                                    No existe información para esta categoría.
                                </td>
                            </tr>
                        </template>

                        <template x-for="item in grupo.items" :key="item.id">

                            <tr>

                                <td x-text="item.nombre + ' ' + item.detalle"></td>

                                <td x-text="item.periodicidad"></td>

                                <td x-text="item.fecha"></td>

                                <td class="text-center"><a @click="eliminar(item.id, item.nombre)"><i class="ti ti-trash text-danger fs-7"></a></td>


                            </tr>

                        </template>

                    </tbody>

                </template>

            </table>

        </div>
    </div>

    <div
        class="modal fade"
        id="modalNuevo"
        tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h4 class="modal-title text-white">
                        Agregar
                    </h4>
                    <button
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    <label class="form-label">* Instrumento:</label>

                    <select
                        class="form-select"
                        x-model="formNuevo.equipo_id"
                        @change="errors.equipo_id = false"
                        :class="errors.equipo_id ? 'is-invalid' : ''">

                        <option value="">Seleccione...</option>

                        <template
                            x-for="equipo in equipos"
                            :key="equipo.id">

                            <option
                                :value="equipo.id"
                                x-text="equipo.nombre">
                            </option>

                        </template>

                    </select>

                    <label class="form-label mt-3">
                        * Fecha programada:
                    </label>

                    <input
                        type="date"
                        class="form-control"
                        x-model="formNuevo.fecha"
                        @change="errors.fecha = false"
                        :class="errors.fecha ? 'is-invalid' : ''">

                </div>

                <div class="modal-footer">

                    <button type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button>

                    <button
                        class="btn btn-success"
                        @click="guardar()">

                        <i class="ti ti-check"></i> Guardar

                    </button>

                </div>

            </div>

        </div>

    </div>

    <div
        class="modal fade"
        id="modalBuscar"
        tabindex="-1">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h4 class="modal-title text-white">
                        Buscar
                    </h4>
                    <button
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    <label class="form-label">* Año:</label>

                    <input
                        type="number"
                        class="form-control"
                        x-model="formBuscar.fecha_year"
                        @change="errors.fecha_year = false"
                        :class="errors.fecha_year ? 'is-invalid' : ''">

                </div>

                <div class="modal-footer">

                    <button type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button>

                    <button
                        class="btn btn-success"
                        @click="buscar()">

                        <i class="ti ti-check"></i> Buscar

                    </button>

                </div>

            </div>

        </div>

    </div>

</div>