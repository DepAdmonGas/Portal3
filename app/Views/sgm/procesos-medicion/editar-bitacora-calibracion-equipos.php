<div class="pb-4" x-data="{ ...actions(), ...editarBitacoraCalibracion(<?= $id ?>) }">


    <table class="table table-bordered table-sm mt-4">
        <tbody>
            <tr>
                <td class="align-middle" width="700"><b>Fecha:</b></td>
                <td class="p-0 m-0"><input type="date" class="form-control border-0" x-model="bitacora.fecha" @change="guardar('fecha')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700"><b>Hora:</b></td>
                <td class="p-0 m-0"><input type="time" class="form-control border-0" x-model="bitacora.hora" @change="guardar('hora')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700"><b>Nombre del equipo a calibrar:</b></td>
                <td class="align-middle p-2"><label class="fw-bolder" x-text="bitacora.nombre_equipo"></label></td>
            </tr>
            <tr>
                <td class="align-middle" width="700"><b>Marca:</b></td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0" x-model="bitacora.marca" @change="guardar('marca')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700"><b>Capacidad:</b></td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0" x-model="bitacora.capacidad" @change="guardar('capacidad')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700"><b>Producto que almacena:</b></td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0" x-model="bitacora.almacena" @change="guardar('almacena')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700"><b>Nombre del laboratorio o unidad de verificación encargada de la calibración:</b></td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0" x-model="bitacora.nombre_laboratorio" @change="guardar('nombre_laboratorio')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700"><b>No de acreditación o aprobación:</b></td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0" x-model="bitacora.no_acreditacion" @change="guardar('no_acreditacion')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700"><b>Método utilizado para la calibración:</b></td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0" x-model="bitacora.metodo_calibracion" @change="guardar('metodo_calibracion')"></td>
            </tr>
        </tbody>
    </table>

    <h5>Descripción de patrones utilizados</h5>

    <table class="table table-bordered table-sm mt-3">
        <tbody>
            <tr>
                <td class="align-middle" width="700"><b>Nombre del patrón</b></td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0" x-model="bitacora.nombre_patron" @change="guardar('nombre_patron')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700"><b>Marca y modelo y serie</b></td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0" x-model="bitacora.marca_modelo_serie" @change="guardar('marca_modelo_serie')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700"><b>Resolución</b></td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0" x-model="bitacora.resolucion" @change="guardar('resolucion')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700"><b>Incertidumbre</b></td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0" x-model="bitacora.incertidumbre" @change="guardar('incertidumbre')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700"><b>Vigencia de su certificado de calibración</b></td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0" x-model="bitacora.vigencia_certificado" @change="guardar('vigencia_certificado')"></td>
            </tr>
        </tbody>
    </table>

    <table class="table table-bordered table-sm align-middle"
        x-show="detalles.length > 0">

        <thead>
            <tr>
                <th>Equipo</th>
                <th>Identificación</th>
                <th>Resultado</th>
            </tr>
        </thead>
        <tbody>
            <template
                x-for="detalle in detalles"
                :key="detalle.id">
                <tr>
                    <td
                        x-text="detalle.equipo.nombre">
                    </td>
                    <td
                        x-text="detalle.equipo.identificacion">
                    </td>
                    <td class="p-0 m-0">
                        <input
                            class="form-control border-0"
                            x-model="detalle.resultado"
                            @change="guardarResultado(detalle)">
                    </td>
                </tr>
            </template>
        </tbody>
    </table>

    <div class="text-end">

        <button

            class="btn btn-primary"

            @click="finalizar()">

            Finalizar bitácora

        </button>

    </div>

</div>