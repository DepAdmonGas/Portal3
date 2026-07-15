<div
    x-data="{ ...actions(), ...politicaForm() }"
    class="card mt-4"
>

    <div class="card-body">

        <div class="mb-3">

            <label class="form-label">
                Fecha:
            </label>

            <input
                type="date"
                class="form-control w-30"
                x-model="fecha"
            >

        </div>

        <div
            id="editor"
            style="height:300px"
        ></div>

        <div class="text-end mt-3">

            <button
                class="btn btn-primary"
                @click="guardar()"
            >
                Guardar política
            </button>

        </div>

    </div>

</div>