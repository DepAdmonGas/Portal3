<div
    x-data="{ ...actions(), ...politicaForm() }"
    class="card mt-4"
>
    <div class="card-body">

        <input
            type="date"
            class="form-control mb-3"
            x-model="fecha"
        >

        <div
            x-ref="editor"
            style="height:350px"
        ></div>

        <div class="text-end mt-3">
            <button
                class="btn btn-primary"
                @click="guardar"
            >
                Guardar
            </button>
        </div>

    </div>
</div>