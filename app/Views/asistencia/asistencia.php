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

        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">
            <div class="row">
            <?php
            if ($asistencia->realizadopor != 0) {
                echo '<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 mb-3">';
                echo '<h5>Evidencia</h5>';
                echo '<small class="text-secondary">Agrega la evidencia del elemento lista de asistencia, un máximo de 3 imágenes</small>';
                echo '<hr>';
                echo '<div class="mt-0"><input type="file" id="evidencia"></div>';
                echo '<div class="text-center p-2" id="result"></div>';
                echo '<div class="mt-2 text-end"><button type="button" class="btn btn-info" onclick="agregarEvidencia(' . $idListaAsistencia . ')">Agregar evidencia</button></div>';
                echo '<div id="ListaEvidencia"></div>';
                echo '</div>';
            }

            ?>

            </div>
        </div>

        <hr>

            <div class="text-end mt-3">
                 <?= 
          !empty($permisos['crear']) ? 
          '<button class="btn btn-success"
                    @click="actualizar('.$idListaAsistencia.')"
                    :disabled="loading">

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