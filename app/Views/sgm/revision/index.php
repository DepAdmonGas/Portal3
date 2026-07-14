<div id="container" class="pb-4" x-data="{ ...actions(), ...editRevision(<?= $id ?>)}">


    <div class="row mt-3">

    <div class="col">

    <label class="fw-bolder">Fecha:</label>

    <input
    type="date"
    class="form-control mt-2"

    x-model="revision.fecha"

    @change="actualizar('fecha',revision.fecha)"
    >

    </div>

    <div class="col">

    <label class="fw-bolder">Hora:</label>

    <input
    type="time"
    class="form-control mt-2"
    x-model="revision.hora"
    @change="actualizar('hora',revision.hora)"
    >

    </div>

    <div class="col">

    <label class="fw-bolder">Lugar:</label>

    <input
    class="form-control mt-2"
    x-model="revision.lugar"
    @blur="actualizar('lugar',revision.lugar)"
    >
    </div>

    </div>

    <div class="mt-3">
    <template
        x-for="(items,categoria) in revision.categorias"
        :key="categoria"
    >

    <div>

        <h4 class="text-secondary" x-text="categoria"></h4>

        <template
            x-for="item in items"
            :key="item.id"
        >

            <div class="mb-3">

                <label class="fw-bolder fs-4" x-text="item.pregunta"></label>

                <textarea
                    class="form-control mt-2"
                    x-model="item.respuesta"
                    @blur="actualizarDetalle(item)"

                ></textarea>

            </div>

        </template>

    </div>

    </template>
    </div>

<div
class="text-end"
x-show="revision.estado==0"
>

<button
class="btn btn-primary"
@click="finalizar()"
>
Finalizar revisión
</button>

</div>
</div>