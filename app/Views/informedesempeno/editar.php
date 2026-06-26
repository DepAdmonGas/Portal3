<div id="container" class="pb-4"
x-data="{ ...actions(), ...informesDesempenoEditar(<?= $idReporte ?>)}">

<label class="form-label mt-3">Fecha: </label>
<input type="date" class="form-control w-40"
x-model="fechaReporte"
@change="actualizarFechaReporte()" />

<table class="table table-bordered table-sm mt-3">
<thead>
    <tr>
    <th class="text-center align-middle bg-primary text-white">Fecha de implementación</th>
    <th class="text-center align-middle bg-primary text-white">Nombre del procedimiento</th>
    <th class="text-center align-middle bg-primary text-white" width="300px">Breve descripción de la implementación </th>
    <th class="text-center align-middle bg-primary text-white">
        <div class="border-bottom pb-1">Se dio a conocer la implementación</div>
        <div><label class="border-right pr-3 pl-2">Si</label> <label class="pl-2 pr-2">No</label></div>
    </th>
    <th class="text-center align-middle bg-primary text-white">Puestos de personal enterados de la implementación</th>
    <th class="text-center align-middle bg-primary text-white" width="300px">Observaciones</th>
    </tr>
</thead>

    <tbody>

        <template
            x-for="item in procedimientos"
            :key="item.id">

            <tr>
                <td class="align-middle">

                    <input
                        type="date"
                        class="form-control border-0"
                        x-model="item.fecha_implementacion"
                        @change="actualizarFecha(item)">
                </td>

                <td class="align-middle">
                    <b x-text="item.procedimiento"></b>
                </td>

                <td>

                    <textarea
                        class="form-control border-0" rows="6"
                        x-model="item.descripcion"
                        @change="actualizarDescripcion(item)"></textarea>

                </td>

                <td class="text-center align-middle">

                    <label class="me-2">

                        <input
                            type="radio"
                            :name="'info-'+item.id"
                            value="Si"
                            x-model="item.informacion"
                            @change="actualizarInformacion(item)">

                        Sí

                    </label>

                    <label>

                        <input
                            type="radio"
                            :name="'info-'+item.id"
                            value="No"
                            x-model="item.informacion"
                            @change="actualizarInformacion(item)">

                        No

                    </label>

                </td>

                <td>

                    <template
                        x-for="puesto in puestos"
                        :key="puesto.id">

                        <div>

                            <input
                                type="checkbox"
                                :checked="tienePuesto(item,puesto.id)"
                                @change="togglePuesto(item,puesto)">

                            <span x-text="puesto.nombre"></span>

                        </div>

                    </template>

                </td>

                <td>

                    <textarea
                        class="form-control border-0" rows="6"
                        x-model="item.observaciones"
                        @change="actualizarObservaciones(item)"></textarea>

                </td>

            </tr>

        </template>

    </tbody>

</table>

<div class="text-end">
    <button class="btn btn-success" @click="finalizar()">Finalizar</button>
</div>

</div>