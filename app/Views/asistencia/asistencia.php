<div class="mt-4 pb-4" id="container" data-id="<?= $idListaAsistencia ?>"
x-data="{ ...actions(), ...listaasistenciaForm() }"
x-init="
        fecha='<?= $asistencia->fecha->format('Y-m-d') ?>';
        hora='<?= $asistencia->hora ? $asistencia->hora->format('H:i:s') : '' ?>';
        lugar='<?= htmlspecialchars($asistencia->lugar) ?>';
        encargado='<?= htmlspecialchars($asistencia->encargado) ?>';
        tema='<?= htmlspecialchars($asistencia->tema) ?>';
        finalidad='<?= htmlspecialchars($asistencia->finalidad) ?>';
     ">

<div class="row">
    <div class="col-sm-6 col-12">
        <div class="row">

          <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
            <div class="form-group">
              <label class="form-label">Fecha:</label>
              <input type="date" class="form-control" x-model="fecha" :class="errors.fecha ? 'is-invalid' : ''">
            </div>
          </div>

          <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
            <div class="form-group">
              <label class="form-label">Hora:</label>
              <input type="time" step="1" class="form-control" x-model="hora" :class="errors.hora ? 'is-invalid' : ''">
            </div>
          </div>

          <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mt-3">
            <div class="form-group">
              <label class="form-label">Lugar:</label>
              <input type="text" class="form-control" x-model="lugar" :class="errors.lugar ? 'is-invalid' : ''">
            </div>
          </div>

          <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mt-3">
            <div class="form-group">
                <label class="form-label">Nombre del encargado de la comunicación:</label>

                <select class="form-control" x-model="encargado" :class="errors.encargado ? 'is-invalid' : ''">

                    <option value="">Selecciona el encargado</option>

                     <?php foreach ($encargados as $encargado): ?>
                        <option value="<?= $encargado->nombre ?>">
                            <?= $encargado->nombre ?>
                        </option>
                    <?php endforeach; ?>                   

                </select>
            </div>
          </div>

          <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mt-3">
            <div class="form-group">
              <label class="form-label">Tema a comunicar:</label>
              <textarea class="form-control" x-model="tema" :class="errors.tema ? 'is-invalid' : ''"></textarea>
            </div>
          </div>

          <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mt-3">
            <div class="form-group">
              <label class="form-label">Finalidad de la comunicación:</label>
              <textarea class="form-control" x-model="finalidad" :class="errors.finalidad ? 'is-invalid' : ''"></textarea>
            </div>
          </div>
          

        </div>

        <div class="mt-4">
            <?php
            if ($asistencia->realizadopor != 0) { ?>
                <h5>Evidencia</h5>

                <small class="text-muted">Agrega la evidencia del elemento lista de asistencia, un máximo de 3 imágenes</small>
                <hr>

               <input
                type="file"
                class="form-control"
                :class="{ 'border-danger': errorArchivo }"
                x-ref="file"
                @change="
                    archivo = $event.target.files[0];
                    errorArchivo = false;
                    error = '';
                "
            >

            <small
                class="text-danger"
                x-show="error"
                x-text="error"
            ></small>

              <div class="mt-2 text-end">

                  <button
                      class="btn bg-info-subtle text-info"
                      @click="subir()"
                  >
                      <i class="ti ti-upload"></i>
                      Agregar evidencia
                  </button>

              </div>


    <table class="table table-sm table-bordered mt-3">

        <thead>

        <tr>

            <th class="text-center align-middle">Fecha</th>

            <th class="text-center align-middle">Evidencia</th>

            <th class="text-center" width="40">
              <i class="ti ti-trash fs-7 text-muted"></i>
            </th>

        </tr>

        </thead>

        <tbody>

        <template x-if="lista.length==0">

            <tr>

                <td colspan="3" class="text-center text-muted">

                    No hay evidencias

                </td>

            </tr>

        </template>

        <template
            x-for="item in lista"
            :key="item.id"
        >

            <tr>

                <td class="text-center align-middle" x-text="item.fecha"></td>

                <td class="text-center">

                    <img
                        :src="'/uploads' + item.url"
                        width="80"
                    >

                </td>

                <td class="text-center align-middle">

                    <button
                        class="btn btn-sm btn-danger"
                        @click="eliminar(item.id)"
                    >

                        <i class="ti ti-trash"></i>

                    </button>

                </td>

            </tr>

        </template>

        </tbody>

    </table>


            <?php } ?>
        </div>

        <hr>

            <div class="text-end mt-3">
                 <?= 
          !empty($permisos['crear']) ? 
          '<button class="btn btn-success"
                    @click="actualizar('.$idListaAsistencia.')"
                    :disabled="loading">
                <i class="ti ti-check"></i> 
                <span x-show="!loading">Finalizar Registro</span>
                <span x-show="loading">Guardando...</span>

            </button>' 
          : '' 
        ?>   
        </div>


    </div>

    <div class="col-sm-6 col-12">
        <label class="form-label">Selecciona el personal:</label>

        <div class="input-group">
            <select class="select2 form-control" multiple="multiple" id="selectPersonal">
                    <?php foreach ($personal as $persona): ?>
                        <option value="<?= $persona->nombre ?>"
                            <?= ($asistencia->persona == $persona->nombre) ? 'selected' : '' ?>>
                            <?= $persona->nombre ?>
                        </option>
                    <?php endforeach; ?>   
            </select>
             <?= 
          !empty($permisos['crear']) ? 
          '<button class="btn bg-info-subtle text-info" type="button" @click="guardarPersonal('.$idListaAsistencia.')">Agregar</button>' 
          : '' 
        ?>   
        
        </div>

        <div class="datatables mt-4">
            <div class="table-responsive">
                <table id="table-lista-asistencia-firma" class="table table-bordered table-striped mb-0 text-nowrap align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Puesto</th>
                        <th>Firma</th>
                    <th class="text-center">
                    <a class="text-muted"><i class="ti ti-trash fs-6"></i></a>
                    </th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        

    </div>
</div>

</div>