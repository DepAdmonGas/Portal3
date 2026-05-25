<div class="mb-4" id="container"
x-data="{ ...actions(), ...configuracionBitacora() }">

  <div class="text-end">
      <?= 
        !empty($permisos['crear']) ? 
        '<button type="button" class="btn btn-primary" @click="modalNuevoOpen()">
        <i class="ti ti-plus"></i> Nuevo
        </button>' 
        : '' 
        ?>     
    </div>

<div class="datatables mt-2">
        <div class="table-responsive">
        <table id="table-trabajador-autorizado" class="table table-bordered mb-0 align-middle">
            <thead>
            <tr>
            <th>#</th>
                <th>Nombre Personal</th>
                <th>Puesto</th>
                <th>Trabajador Autorizado</th>
                <th class="text-center">
                <a class="text-muted"><i class="ti ti-trash fs-6"></i></a>
                </th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
        </div>
    </div>

    <!-- Modal nuevo -->

    <div class="modal fade" id="modalNuevo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header head-modal">
                <h4 class="modal-title">Agregar trabajador autorizado</h4>

                <button type="button"
                class="btn-close"
                data-bs-dismiss="modal"
                @click="limpiarNuevo()">
                </button>
            </div>

        <div class="modal-body">

            <label class="form-label mt-2">* Usuario:</label>
            <select
                class="form-select"
                x-model="usuarioSeleccionado"
                @change="changeUsuario">

                <option value="">Seleccione</option>

                <template 
                    x-for="usuario in usuarios"
                    :key="usuario.id">

                    <option 
                        :value="usuario.id"
                        x-text="usuario.nombre + ' - ' + usuario.puesto">
                        </option>

                </template>

            </select>            

                    <!-- CATEGORIAS -->
            <div x-show="categorias.length > 0">

                <label class="form-label mt-2">Categorías disponibles</label>

                <template 
                    x-for="categoria in categorias"
                    :key="categoria.codigo">

                    <div class="form-check mb-2">

                        <input
                            class="form-check-input"
                            type="checkbox"
                            :value="categoria.codigo"
                            x-model="categoriasSeleccionadas">

                        <label
                            class="form-check-label"
                            x-text="categoria.nombre">
                        </label>

                    </div>

                </template>

            </div>

                 
        </div>

            <div class="modal-footer">

                <button
                class="btn bg-danger-subtle text-danger"
                data-bs-dismiss="modal"
                @click="limpiarNuevo()">
                    Cancelar
                </button>

                <button
                class="btn btn-primary"
                @click="guardar()">
                    Guardar
                </button>
            </div>

        </div>
    </div>
    </div>

    <!-- Modal Eliminar -->

    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">

                <div class="modal-header head-modal">
                    <h4 class="modal-title"
                    x-text="titulo">
                    </h4>

                    <button type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    @click="limpiarEliminar()">
                    </button>
                </div>

                <div class="modal-body">

                <h4 class="text-center" x-text="text_usuario"></h4>

                <label class="form-label mt-2">* Comentario:</label>
                <textarea class="form-control"
                x-model="comentario"
                :class="errors.comentario ? 'is-invalid' : ''"
                @input="errors.comentario = false">

                </textarea>

                    
                </div>

                <div class="modal-footer">

                    <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal"
                    @click="limpiarEliminar()">
                        Cancelar
                    </button>

                    <button
                    class="btn btn-primary"
                    @click="eliminar()">
                        Eliminar
                    </button>

                </div>

            </div>
        </div>
    </div>


    <div class="card mt-4">
        <div class="card-body">

        <h4>Características de nueva actualización</h4>

        <div class="fw-bold text-secondary fs-5">Diseño</div>
        <ul>
        <li>Se realizaron mejoras en el diseño y visualización de los elementos en pantalla</li>
        </ul>

        <div class="fw-bold text-secondary fs-5">Geolocalización</div>
        <ul>
        <li>Se mejoró la precisión en la obtención de coordenadas desde dispositivos móviles</li>
        <li>Se configuró la ubicación inicial utilizando las coordenadas de la Estación de Servicio</li>
        </ul>


        <div class="fw-bold text-secondary fs-5">1. Recepción y descarga del producto</div>
        <ul>
        <li>Se permite editar los registros existentes</li>
        <li>Cuenta con buscador para localizar registros fácilmente</li>
        <li>Se pueden eliminar registros si es necesario</li>
        <li>Se puede agregar un nuevo registro con información sobre transporte, tanque, datos de seguridad y observaciones realizadas durante la descarga</li>
        <li>Incluye firma del personal que recibe la descarga</li>
        <li>Incluye firma del personal que supervisa la descarga</li>
        <li>Se permite agregar evidencia visual del proceso de descarga</li>
        </ul>


        <div class="fw-bold text-secondary fs-5">2. Mantenimiento Preventivo</div>
        <ul>
        <li>Ahora se puede seleccionar si el mantenimiento es realizado por personal interno o externo</li>
        <li>En caso de mantenimiento externo, se debe ingresar el nombre del responsable en la firma</li>
        <li>Se permite agregar evidencia fotográfica al finalizar el mantenimiento</li>
        <li>En caso de error, el mantenimiento puede ser cancelado y se generará con un nuevo folio</li>
        <li>Se actualizó el contenido de los mantenimientos</li>
        <li>Se actualizó el buscador de reportes</li>
        <li>Se mejoró el diseño de los reportes generados en formato PDF</li>
        <li>Se agregó la opción para editar los registros existentes</li>
        <li>Se incorporó un buscador para facilitar la localización de registros</li>
        <li>Se habilitó la búsqueda filtrada por registros pendientes o finalizados</li>
        </ul>

        <div class="fw-bold text-secondary fs-5">3. Mantenimiento Correctivo</div>
        <ul>
        <li>Ahora se puede seleccionar si el mantenimiento es realizado por personal interno o externo</li>
        <li>En caso de mantenimiento externo, se debe ingresar el nombre del responsable en la firma</li>
        <li>Se permite agregar evidencia fotográfica al finalizar el mantenimiento</li>
        <li>Se agregó un buscador para consultar reportes de manera más eficiente</li>
        <li>Se mejoró el diseño de los reportes generados en formato PDF</li>
        <li>Se incorporó la opción para editar y eliminar registros existentes</li>
        <li>Se incorporó un buscador para facilitar la localización de registros</li>
        </ul>



        <div class="fw-bold text-secondary fs-5">4. Bitácora de registros de eventos PROFECO</div>
        <ul>
        <li>Se permite eliminar registros existentes</li>
        <li>Se pueden agregar nuevos registros con datos como fecha, hora, dispensario, productos involucrados, motivo, responsable y observaciones</li>
        <li>Cuenta con buscador para facilitar la consulta de registros</li>
        </ul>



        </div>
    </div>

</div>

