<div
    x-data="{ ...actions(), ...objetivosForm() }"
    class="card mt-4"
>

    <div class="card-body">

        <div
            x-ref="editor"
            style="height:300px"
        ></div>

        <div class="text-end mt-3">

            <button
                class="btn btn-primary"
                @click="guardar()"
            >
                Guardar objetivos
            </button>

        </div>

    </div>

</div>