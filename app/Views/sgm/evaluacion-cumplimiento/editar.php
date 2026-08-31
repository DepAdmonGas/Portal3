<div id="container" class="pb-4"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int)($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>
    <div id="sgm-empty-message" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.
    </div>
<?php else: ?>

<div id="sgm-content" x-data="{ ...actions(), ...evaluacionForm(<?= $id ?>) }">

<table class="table table-sm table-bordered mt-4">
    <tr>
        <td class="fw-bolder align-middle fs-3">Fecha:</td>
        <td class="p-0">
            <input
            type="date"
            class="form-control border-0"
            x-model="revision.fecha"
            @change="guardarCampo()">
        </td>
    </tr>
    <tr>
        <td class="fw-bolder align-middle fs-3">Hora:</td>
        <td class="p-0">
            <input
            type="time"
            class="form-control border-0"
            x-model="revision.hora"
            @change="guardarCampo()">
        </td>
    </tr>
    <tr>
        <td class="fw-bolder align-middle fs-3">Lugar:</td>
        <td class="p-0">
            <input
            type="text"
            class="form-control border-0"
            x-model="revision.lugar"
            @input.debounce.600ms="guardarCampo()">
        </td>
    </tr>
    <tr>
        <td class="fw-bolder align-middle fs-3">Responsable de la medición:</td>
        <td class="p-0">
            <select
    class="form-select border-0"
    x-model="revision.responsable"
    @change="guardarCampo()">

    <template
        x-for="usuario in usuarios"
        :key="usuario.nombre">

        <option
            :value="usuario.nombre"
            x-text="usuario.nombre">
        </option>

    </template>

</select>

        </td>
    </tr>
</table>


<template
    x-for="(detalle, index) in revision.detalles"
    :key="detalle.id">

    <div class="card mb-3">

        <div class="card-header fs-5">
            <strong x-text="detalle.categoria"></strong>
        </div>

        <div class="card-body">

        <table class="table table-sm table-bordered pb-0 mb-0">
            <tr>
                <td class="fw-bolder align-middle fs-3"
                x-text="
                    index === revision.detalles.length - 1
                        ? 'Meta: disminuir 30% de reclamaciones contra el año inmediato anterior'
                        : 'Meta: 100%'
                ">
            </td>
                <td class="fw-bolder align-middle fs-3">Resultado</td>
                <td class="p-0">
                    <input
                    class="form-control border-0"
                    x-model="detalle.resultado1"
                    @input.debounce.600ms="guardarCampo()">
                </td>
            </tr>

            <tr>
                <td class="fw-bolder align-middle fs-3">Comentarios y observaciones:</td>
                <td class="p-0" colspan="2">
                    <textarea
                        class="form-control border-0"
                        x-model="detalle.resultado2"
                        @input.debounce.600ms="guardarCampo()">
                    </textarea>
                </td>
            </tr>

            <tr>
                <td class="fw-bolder align-middle fs-3">Acciones a tomar para mejorar o mantener el resultado:</td>
                <td class="p-0" colspan="2">
                    <textarea
                        class="form-control border-0"
                        x-model="detalle.resultado3"
                        @input.debounce.600ms="guardarCampo()">
                    </textarea>
                </td>
            </tr>

            <tr>
                <td class="fw-bolder align-middle fs-3">Responsable de realizar las acciones a tomar para mejorar o mantener los resultados:</td>
                <td class="p-0" colspan="2">
                    <textarea
                        class="form-control border-0"
                        x-model="detalle.resultado4"
                        @input.debounce.600ms="guardarCampo()">
                    </textarea>
                </td>
            </tr>

            <tr>
                <td class="fw-bolder align-middle fs-3">Recursos necesarios para ejecutar las acciones a tomar para mejorar o mantener los resultados:</td>  
                <td class="p-0" colspan="2">
                    <textarea
                        class="form-control border-0"
                        x-model="detalle.resultado5"
                        @input.debounce.600ms="guardarCampo()">
                    </textarea>
                </td>
            </tr>


        </table>


        </div>

    </div>

</template>

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
                x-for="usuario in usuariosDisponibles"
                :key="usuario.id"
            >
                <option
                    :value="usuario.id"
                    x-text="usuario.nombre">
                </option>
            </template>
        </select>

        </div>

        <div class="col-md-1 d-grid">

            <button
                class="btn btn-info"
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
                <th width="40" class="text-center"><i class="ti ti-trash fs-7 text-muted"></i></th>
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


<div class="text-end mt-3">

<button class="btn btn-success" @click="finalizar()"><i class="ti ti-check"></i> Finalizar</button>

</div>

</div>
<?php endif; ?>

</div>