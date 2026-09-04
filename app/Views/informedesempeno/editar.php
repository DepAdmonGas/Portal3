<div id="container" class="pb-4"
data-module-station-key="sasisopa"
data-estacion-id="<?= e($estacionId ?? '') ?>"
x-data="{ ...actions(), ...informesDesempenoEditar(<?= $idReporte ?>)}">


<div class="row  mb-3 mt-3">
    <div class="col-6">
<input type="date" class="form-control"
x-model="fechaReporte"
@change="actualizarFechaReporte()" />
</div>


<div class="col-6">
<div class="text-end">
    <button class="btn btn-success" @click="finalizar()">
        <i class="ti ti-check"></i>
    Finalizar</button>
</div>
</div>
</div>


<div class="table-responsive">

<table class="table table-responsive p-0 table-striped table-bordered mt-2 align-middle">
<thead>
    <tr>
    <th class="text-center align-middle" rowspan="2">Fecha de implementación</th>
    <th class="text-center align-middle" rowspan="2">Nombre del procedimiento</th>
    <th class="text-center align-middle" rowspan="2" width="300px">Breve descripción de la implementación </th>
    <th class="text-center align-middle" colspan="2">Se dio a conocer la implementación</th>
    <th class="text-center align-middle" rowspan="2" width="200px">Puestos de personal enterados de la implementación</th>
    <th class="text-center align-middle" rowspan="2" width="300px">Observaciones</th>
    </tr>
    </tr>
<tr>
    <th class="text-center align-middle" >SI</th>
    <th class="text-center align-middle" >NO</th>

 </tr>
</thead>

    <tbody>

        <template
            x-for="item in procedimientos"
            :key="item.id">

            <tr>
                <td class="text-center">

                    <input
                        type="date"
                        class="form-control border-0"
                        x-model="item.fecha_implementacion"
                        @change="actualizarFecha(item)">
                </td>

                <td class="text-center">
                    <b x-text="item.procedimiento"></b>
                </td>

                <td>

                    <textarea
                        class="form-control border-0" rows="6"
                        x-model="item.descripcion"
                        @change="actualizarDescripcion(item)"></textarea>

                </td>

                <td class="text-center align-middle">

                    <label class=" pointer me-2">

                        <input
                            type="radio"
                            :name="'info-'+item.id"
                            value="Si"
                            x-model="item.informacion"
                            @change="actualizarInformacion(item)">

                        Sí

                    </label>
</td>
<td>
                    <label class="pointer">

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
    <template x-for="puesto in puestos" :key="puesto.id">
        <div style="display: flex; align-items: flex-center; margin-left: 0px; margin-bottom: 5px;">
            <input
                class="pointer me-1"
                type="checkbox"
                :checked="tienePuesto(item, puesto.id)"
                @change="togglePuesto(item, puesto)"
                style="flex-shrink: 0;"
            >

            <span
                x-text="puesto.nombre"
                style="
                    margin-left: 1px;
                    overflow-wrap: anywhere;
                    word-break: break-word;
                    white-space: normal;
                "
            ></span>
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
</div>
</div>