<div id="container" class="pb-4"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int)($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>
    <div id="sgm-empty-message" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.
    </div>
<?php else: ?>

<div id="sgm-content" x-data="{ ...actions(), ...evaluacionForm(<?= $id ?>) }">
<div class="text-end mt-3">

<button class="btn btn-success" @click="finalizar()"><i class="ti ti-check"></i> Finalizar</button>

</div>


<div class="card mt-3">
    <div class="card-header bg-primary">
        <span class="card-title text-white">
            <i class="ti ti-clipboard-data fs-6"></i>
            Informacion de Cumplimiento de objetivos 
        </span>
    </div>
    <div class="card-body p-0">
<table class="table table-striped table-bordered text-nowrap align-middle mb-0">
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
    </div>
</div>




<template
    x-for="(detalle, index) in revision.detalles"
    :key="detalle.id">

    <div class="card mb-3">

        <div class="card-header bg-primary">
        <i class="ti ti-progress-alert fs-6 text-white"></i>    
        <span 
            class="card-title text-white"
            x-text="detalle.categoria">
        </span>
        </div>

        <div class="card-body p-0">
<div class="table-responsive">
            <table class="table table-striped table-bordered align-middle mb-0">
            <tr>
                <td class="fw-bolder align-middle fs-3"
                x-text="
                    index === revision.detalles.length - 1
                        ? 'Meta: disminuir 30% de reclamaciones contra el año inmediato anterior'
                        : 'Meta: 100%'
                ">
            </td>
                <td class="fw-bolder align-middle fs-3">Resultado:</td>
                <td class="p-0">
                    <input
                    class="form-control border-0 text-center"
                    x-model="detalle.resultado1"
                    @input.debounce.600ms="guardarCampo()">
                </td>
            </tr>

            <tr>
                <td class="fw-bolder align-middle fs-3">Comentarios y observaciones:</td>
                <td class="p-0" colspan="2">
                    <textarea
                        class="form-control border-0 text-center"
                        x-model="detalle.resultado2"
                        @input.debounce.600ms="guardarCampo()">
                    </textarea>
                </td>
            </tr>

            <tr>
                <td class="fw-bolder align-middle fs-3">Acciones a tomar para mejorar o mantener el resultado:</td>
                <td class="p-0" colspan="2">
                    <textarea
                        class="form-control border-0 text-center"
                        x-model="detalle.resultado3"
                        @input.debounce.600ms="guardarCampo()">
                    </textarea>
                </td>
            </tr>

            <tr>
                <td class="fw-bolder align-middle fs-3">Responsable de realizar las acciones a tomar para mejorar o mantener los resultados:</td>
                <td class="p-0" colspan="2">
                    <textarea
                        class="form-control border-0 text-center"
                        x-model="detalle.resultado4"
                        @input.debounce.600ms="guardarCampo()">
                    </textarea>
                </td>
            </tr>

            <tr>
                <td class="fw-bolder align-middle fs-3">Recursos necesarios para ejecutar las acciones a tomar para mejorar o mantener los resultados:</td>  
                <td class="p-0" colspan="2">
                    <textarea
                        class="form-control border-0 text-center"
                        x-model="detalle.resultado5"
                        @input.debounce.600ms="guardarCampo()">
                    </textarea>
                </td>
            </tr>


        </table>

</div>


        </div>

    </div>

</template>



<!---------inicio de la card asistentes------>

<div class="card">
    <div class="card-header bg-primary">
  <span class="card-title text-white">
    <i class="ti ti-user-plus"></i>
        Asistentes
    </span>
    </div>
    
<div class="card-body pb-2">
  <div class="d-flex align-items-stretch">

    <div class="flex-grow-1">
        <select
            x-ref="usuarios"
            class="select2 form-control"
            multiple>

            <template
                x-for="usuario in usuariosDisponibles"
                :key="usuario.id">

                <option
                    :value="usuario.id"
                    x-text="usuario.nombre">
                </option>

            </template>
        </select>
    </div>

    <button
        type="button"
        class="btn bg-primary-subtle text-primary text-nowrap"
        @click="agregarAsistentes()">
        <i class="ti ti-plus"></i>
        Nuevo
    </button>

</div>
      
<div class="table responsive">
      <table class="table table-striped table-bordered  align-middle mb-0 mt-3">
        <thead>
            <tr>
                <th whidth="96px" class="text-center">#</th>
                <th>Nombre</th>
                <th class="text-center">
                    Firma
                </th>
                <th width="48px" class="text-center"><i class="ti ti-trash fs-6 text-danger"></i></th>
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
                    <td class="align-middle text-center"
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
                            <i class="ti ti-trash fs-6 pointer text-danger"></i>
                        </a>

                    </td>

                </tr>

            </template>

        </tbody>

    </table>
</div>


</div>
</div>


</div>
<?php endif; ?>

</div>