<div class="mb-4" id="container"
x-data="{ ...actions(), ...configuracionBitacora() }">

  <div class="text-end">
      <?= 
        !empty($permisos['crear']) ? 
        '<button type="button" class="btn bg-primary-subtle texte-primary" @click="modalNuevoOpen()">
        <i class="ti ti-plus"></i> Nuevo
        </button>' 
        : '' 
        ?>     
    </div>

<div class="datatables mt-2">
            <div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
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

            <div class="modal-header modal-colored-header bg-primary">
                <h4 class="modal-title text-white">
                 <i class="ti ti-user-plus ms-2"></i>   
                Nuevo trabajador autorizado</h4>

                <button type="button"
                class="btn-close btn-close-white"
                data-bs-dismiss="modal"
                @click="limpiarNuevo()">
                </button>
            
            </div>

        <div class="modal-body">

            <label class="form-label mt-2">* Nombre del trabajador:</label>
            <select
                class="form-select"
                x-model="usuarioSeleccionado"
                @change="changeUsuario">

                <option value="">Selecciona una opción...</option>

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
                <i class="ti ti-x"></i>
                    Cancelar
                </button>

                <button
                class="btn btn-success"
                @click="guardar()">
                <i class="ti ti-check"></i>
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

<div class="modal-header modal-colored-header bg-primary text-white d-flex align-items-center justify-content-between">

    <h4 class="modal-title text-white d-flex align-items-center mb-0">
        <i class="ti ti-trash fs-6 me-1"></i>
        <span x-text="titulo"></span>
    </h4>

    <button type="button"
            class="btn-close btn-close-white"
            data-bs-dismiss="modal"
            @click="limpiarEliminar()">
    </button>

</div>

                <div class="modal-body">

                <label class=" form-label" x-text="text_usuario"></label>
                <br>

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
                    <i class="ti ti-x"></i>
                        Cancelar
                    </button>

                    <button
                    type="button"
                    class="btn btn-success"
                    @click="eliminar()">
                    <i class="ti ti-check"></i>
                        Eliminar
                    </button>

                </div>

            </div>
        </div>
    </div>


    <div class="card mt-4">
        <div class="card-header bg-primary ">
  <h4 class="mb-0 text-white card-title">
    <i class="ti ti-label"></i>
  Características de nueva actualización</h4>
      

            </div>
          
        <div class="card-body">

        

        <h4>Diseño:</h4>
        <ul>
        <li>Se realizaron mejoras en el diseño y visualización de los elementos en pantalla</li>
        </ul>

        <h4 ">Geolocalización:</h4>
        <ul>
        <li>Se mejoró la precisión en la obtención de coordenadas desde dispositivos móviles</li>
        <li>Se configuró la ubicación inicial utilizando las coordenadas de la Estación de Servicio</li>
        </ul>


        <h4>1. Recepción y descarga del producto:</h4>
        <ul>
        <li>Se permite editar los registros existentes</li>
        <li>Cuenta con buscador para localizar registros fácilmente</li>
        <li>Se pueden eliminar registros si es necesario</li>
        <li>Se puede agregar un nuevo registro con información sobre transporte, tanque, datos de seguridad y observaciones realizadas durante la descarga</li>
        <li>Incluye firma del personal que recibe la descarga</li>
        <li>Incluye firma del personal que supervisa la descarga</li>
        <li>Se permite agregar evidencia visual del proceso de descarga</li>
        </ul>


        <h4>2. Mantenimiento Preventivo:</h4>
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

        <h4>3. Mantenimiento Correctivo:</h4>
        <ul>
        <li>Ahora se puede seleccionar si el mantenimiento es realizado por personal interno o externo</li>
        <li>En caso de mantenimiento externo, se debe ingresar el nombre del responsable en la firma</li>
        <li>Se permite agregar evidencia fotográfica al finalizar el mantenimiento</li>
        <li>Se agregó un buscador para consultar reportes de manera más eficiente</li>
        <li>Se mejoró el diseño de los reportes generados en formato PDF</li>
        <li>Se incorporó la opción para editar y eliminar registros existentes</li>
        <li>Se incorporó un buscador para facilitar la localización de registros</li>
        </ul>



        <h4>4. Bitácora de registros de eventos PROFECO:</h4>
        <ul>
        <li>Se permite eliminar registros existentes</li>
        <li>Se pueden agregar nuevos registros con datos como fecha, hora, dispensario, productos involucrados, motivo, responsable y observaciones</li>
        <li>Cuenta con buscador para facilitar la consulta de registros</li>
        </ul>



        </div>
    </div>

</div>

