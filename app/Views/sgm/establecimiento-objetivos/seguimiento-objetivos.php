<div id="container"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>

    <div id="sgm-empty-message"
        class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.
    </div>

<?php else: ?>

<div id="sgm-content">

 <div class="pb-4" x-data="{ ...actions(), ...seguimientoObjetivos() }"
 data-id="<?= $id ?>">

    <div class="row mt-4">

        <div class="col-md-4">

            <label class="form-label">
                Fecha:
            </label>

            <input
                type="date"
                class="form-control"
                x-model="seguimiento.fecha"
            >

        </div>

        <div class="col-md-4">

            <label class="form-label">
                Hora:
            </label>

            <input
                type="time"
                class="form-control"
                x-model="seguimiento.hora"
            >

        </div>

        <div class="col-md-4">

            <label class="form-label">
                Lugar:
            </label>

            <input
                class="form-control"
                x-model="seguimiento.lugar"
            >

        </div>

    </div>


    <h5 class="mt-4 bg-light p-3">
        Indicador: Implementacion del SGM
    </h5>

    <table class="table table-sm table-bordered">
        <tbody>
            <tr>
                <td class="align-middle fs-4">
                    Porcentaje de procedimientos implementados durante el año inmediato anterior
                </td>
                <td class="p-0">
                    <input type="number"
                        class="form-control border-0"
                        x-model="implementacion.respuesta_uno"
                        @change="guardarCampo(1,1,implementacion.respuesta_uno)">
                </td>
            </tr>
            <tr>
                <td class="align-middle fs-4">
                    Porcentaje de procedimientos documentados durante el año inmediato anterior
                </td>
                <td class="p-0">
                    <input type="number"
                        class="form-control border-0"
                        x-model="implementacion.respuesta_dos"
                        @change="guardarCampo(1,2,implementacion.respuesta_dos)">

                </td>

            </tr>

            <tr>

                <td class="align-middle fs-4">
                    Comentarios y observaciones:
                </td>

                <td class="p-0">

                    <textarea
                        class="form-control border-0"
                        rows="3"
                        x-model="implementacion.respuesta_tres"
                        @change="guardarCampo(1,3,implementacion.respuesta_tres)"
                    ></textarea>

                </td>

            </tr>

            <tr>

                <td class="align-middle fs-4">
                    En caso de no obtener resultados favorables, describa las acciones a tomar junto con los recursos que necesita con la finalidad de cambiar los resultados obtenidos para la siguiente evaluacion
                </td>

                <td class="p-0" width="500px">

                    <textarea
                        class="form-control border-0"
                        rows="3"
                        x-model="implementacion.respuesta_cuatro"
                        @change="guardarCampo(1,4,implementacion.respuesta_cuatro)"
                    ></textarea>

                </td>

            </tr>

        </tbody>

    </table>

    <hr>

    <h5 class="mt-4 bg-light p-3">
        Indicador: Calibracion de equipos
    </h5>

    <table class="table table-sm table-bordered">

        <tbody>
            <tr>
                <td class="align-middle fs-4">
                    Porcentaje de quipos calibrados durante el año 
                    <span x-text="seguimiento.fecha?.substring(0, 4)"></span>
                </td>
                <td class="p-0" width="500px">
                    <input type="number"
                        class="form-control border-0"
                        x-model="calibracion.respuesta_uno"
                        @change="guardarCampo(2,1,calibracion.respuesta_uno)"
                    >

                </td>

            </tr>

            <tr>

                <td class="align-middle fs-4">
                    Comentarios y observaciones:
                </td>

                <td class="p-0" width="500px">
                    <textarea
                        class="form-control border-0"
                        x-model="calibracion.respuesta_dos"
                        @change="guardarCampo(2,2,calibracion.respuesta_dos)"
                    ></textarea>

                </td>

            </tr>

            <tr>

                <td class="align-middle fs-4">
                    En caso de no obtener resultados favorables, describa las acciones a tomar junto con los recursos que necesita con la finalidad de cambiar los resultados obtenidos para la siguiente evaluacion
                </td>

                <td class="p-0" width="500px">
                    <textarea
                        class="form-control border-0"
                        x-model="calibracion.respuesta_tres"
                        @change="guardarCampo(2,3,calibracion.respuesta_tres)"
                    ></textarea>

                </td>

            </tr>

        </tbody>

    </table>

    <hr>

    <h5 class="mt-4 bg-light p-3">
        Indicador: Satisfaccion del cliente
    </h5>

    <table class="table table-sm table-bordered">
        <tbody>
            <tr>
                <td class="align-middle fs-4">
                    Número de quejas por parte de los clientes
                </td>
                <td class="p-0" width="500px">
                    <input
                        type="number"
                        class="form-control border-0"
                        x-model="satisfaccion.respuesta_uno"
                        @change="guardarCampo(3,1,satisfaccion.respuesta_uno)"
                    >
                </td>
            </tr>
            <tr>
                <td class="align-middle fs-4">
                    Número de quejas atendidas de manera satisfactoria
                </td>

                <td class="p-0" width="500px">
                    <input
                        type="number"
                        class="form-control border-0"
                        x-model="satisfaccion.respuesta_dos"
                        @change="guardarCampo(3,2,satisfaccion.respuesta_dos)"
                    >

                </td>

            </tr>

            <tr>

                <td class="align-middle fs-4">
                    Si ya se cuenta con resultados del año inmediato anterior, determinar el porcentaje que representan las quejas del año inmediato anterior contra los resultados con los que cuenta la estación de servicio.
                </td>

                <td class="p-0" width="500px">

                    <input
                        type="number"
                        class="form-control border-0"
                        x-model="satisfaccion.respuesta_tres"
                        @change="guardarCampo(3,3,satisfaccion.respuesta_tres)"
                    >

                </td>

            </tr>

            <tr>

                <td class="align-middle fs-4">
                    Comentarios y observaciones
                </td>
                <td class="p-0" width="500px">

                    <textarea
                        class="form-control border-0"
                        rows="3"
                        x-model="satisfaccion.respuesta_cuatro"
                        @change="guardarCampo(3,4,satisfaccion.respuesta_cuatro)"
                    ></textarea>

                </td>

            </tr>

            <tr>

                <td class="align-middle fs-4">
                    En caso de no obtener resultados favorables, describa las acciones a tomar junto con los recursos que necesita con la finalidad de cambiar los resultados obtenidos para la siguiente evaluación.
                </td>

                <td class="p-0" width="500px">
                    <textarea
                        class="form-control border-0"
                        rows="3"
                        x-model="satisfaccion.respuesta_cinco"
                        @change="guardarCampo(3,5,satisfaccion.respuesta_cinco)"
                    ></textarea>

                </td>

            </tr>

        </tbody>

    </table>

    <div class="row">
    <div class="col-md-7">
    <h5 class="mt-4 bg-light p-3">
        Asistentes
    </h5>
    </div>
    </div>

    <div class="row">

        <div class="col-md-6">

        <select
            x-ref="usuarios"
            class="select2 form-control"
            multiple
        >
            <template
                x-for="usuario in usuarios"
                :key="usuario.id"
            >
                <option
                    :value="usuario.id"
                    x-text="usuario.nombre"
                ></option>
            </template>
        </select>

        </div>

        <div class="col-md-1 d-grid">

            <button
                class="btn btn-success"
                @click="agregarAsistentes()"
            >
                Agregar
            </button>

        </div>

    </div>

    <div class="row">
    <div class="col-md-7">
    <table class="table table-sm table-bordered table-sm mt-3">
        <thead>
            <tr>
                <th>#</th>
                <th>Nombre</th>
                <th class="text-center">
                    Firma
                </th>
                <th width="40"></th>
            </tr>
        </thead>
        <tbody>
            <template
                x-if="asistentes.length===0"
            >
                <tr>
                    <td
                        colspan="4"
                        class="text-center text-muted"
                    >
                        Sin asistentes
                    </td>
                </tr>
            </template>
            <template
                x-for="(asistente,index) in asistentes"
                :key="asistente.id"
            >
                <tr>
                    <td class="align-middle"
                        x-text="index+1"
                    ></td>
                    <td class="align-middle"
                        x-text="asistente.usuario.nombre"
                    ></td>
                    <td class="text-center align-middle">
                        <img
                            x-show="asistente.usuario.firma"
                            :src="'<?= $_ENV['APP_URL'] ?>/uploads/firma-personal/' + asistente.usuario.firma"
                            width="70"
                        >
                    </td>
                    <td class="text-center align-middle">

                        <a  @click="eliminarAsistente(asistente.id)">
                            <i class="ti ti-trash fs-7 text-danger"></i>
                        </a>

                    </td>

                </tr>

            </template>

        </tbody>

    </table>
    </div>
    </div>

    <div class="text-end mt-4">

        <button
            class="btn btn-primary"
            @click="finalizar()"
        >

            Finalizar seguimiento

        </button>

    </div>

</div>

</div>

<?php endif; ?>

</div>