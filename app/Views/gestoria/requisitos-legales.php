<div id="container" class="pb-4"
    x-data="{ ...actions(), ...requisitoLegal()}">

    <div class="row mt-3">

        <div class="col-sm-5 col-12">

            <!-- Nivel de gobierno -->
            <div class="card">
                <div class="card-body">

                    <div class="float-end">
                        <?=
                        !empty($permisos['crear']) ?
                            '<button type="button" class="btn btn-primary" @click="openNivelGobierno()" >
                            <i class="ti ti-plus"></i> Nuevo
                            </button>'
                            : ''
                        ?>
                    </div>

                    <h4 class="card-title mb-0">Nivel de gobierno</h4>


                    <div class="datatables mt-4">

                        <div class="table-responsive">
                            <table id="table-nivel-gobierno" class="table table-sm table-striped table-bordered mb-0 text-nowrap align-middle">
                                <thead>

                                    <tr>
                                        <th>Nivel de gobierno</th>
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
            <!-- Nivel de gobierno -->

            <!-- Municipio, Alcaldía y Estado -->
            <div class="card">
                <div class="card-body">

                    <div class="float-end">
                        <?=
                        !empty($permisos['crear']) ?
                            '<button type="button" class="btn btn-primary" @click="openMunicipioAlcaldiaEstado()" >
                            <i class="ti ti-plus"></i> Nuevo
                            </button>'
                            : ''
                        ?>
                    </div>

                    <h4 class="card-title mb-0">Municipio, Alcaldía y Estado</h4>


                    <div class="datatables mt-4">

                        <div class="table-responsive">
                            <table id="table-municipio-alcaldia-estado" class="table table-sm table-striped table-bordered mb-0 text-nowrap align-middle">
                                <thead>

                                    <tr>
                                        <th>Municipio, Alcaldía y Estado </th>
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
            <!-- Municipio, Alcaldía y Estado -->

            <!-- Dependencias -->
            <div class="card">
                <div class="card-body">

                    <div class="float-end">
                        <?=
                        !empty($permisos['crear']) ?
                            '<button type="button" class="btn btn-primary" @click="openDependencias()">
                            <i class="ti ti-plus"></i> Nuevo
                            </button>'
                            : ''
                        ?>
                    </div>

                    <h4 class="card-title mb-0">Dependencias</h4>


                    <div class="datatables mt-4">

                        <div class="table-responsive">
                            <table id="table-dependencias" class="table table-sm table-striped table-bordered mb-0 align-middle">
                                <thead>

                                    <tr>
                                        <th>Dependencia</th>
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
            <!-- Dependencias -->

        </div>

        <div class="col-sm-7 col-12">
            <div class="card">
                <div class="card-body">

                    <div class="float-end">
                        <?=
                        !empty($permisos['crear']) ?
                            '<button type="button" class="btn btn-primary" @click="openRequisitoLegal()">
                            <i class="ti ti-plus"></i> Nuevo
                            </button>'
                            : ''
                        ?>
                    </div>

                    <h4 class="card-title mb-0">Requisitos Legales</h4>


                    <div class="datatables mt-4">

                        <div class="table-responsive">
                            <table id="table-requisito-legal" class="table table-sm table-striped table-bordered mb-0 align-middle">
                                <thead>

                                    <tr>
                                        <th class="align-middle">Nivel de gobierno</th>
                                        <th class="align-middle">Municipio, Alcaldía y Estado</th>
                                        <th class="align-middle">Dependencias</th>
                                        <th class="align-middle">Permiso</th>
                                        <th class="align-middle">Responsable</th>
                                        <th class="text-center">
                                            <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
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

    </div>

    <!-- Modal Nivel de Gobierno -->

    <div
        class="modal fade"
        id="modalNivelGobierno"
        tabindex="-1">

        <div class="modal-dialog modal-md modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h4 class="modal-title text-white">
                        Nivel de gobierno
                    </h4>

                    <button
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <textarea class="form-control"
                        x-model="nivelgobierno"
                        @input="errors.nivelgobierno = false"
                        :class="errors.nivelgobierno ? 'is-invalid' : ''"></textarea>

                </div>

                <div class="modal-footer">

                    <button
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                        <i class="ti ti-x"></i>
                        Cancelar

                    </button>

                    <button
                        class="btn btn-success"
                        @click="guardarNivelGobierno()">
                        <i class="ti ti-check"></i>
                        Guardar

                    </button>

                </div>

            </div>

        </div>

    </div>

    <!-- Modal Nivel de Gobierno -->

    <!-- Municipio, Alcaldía y Estado -->
    <div
        class="modal fade"
        id="modalMunicipioAlcaldiaEstado"
        tabindex="-1">

        <div class="modal-dialog modal-md modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h4 class="modal-title text-white">
                        Municipio, Alcaldía y Estado
                    </h4>

                    <button
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <textarea class="form-control"
                        x-model="municipioalcaldiaestado"
                        @input="errors.municipioalcaldiaestado = false"
                        :class="errors.municipioalcaldiaestado ? 'is-invalid' : ''"></textarea>

                </div>

                <div class="modal-footer">

                    <button
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                        <i class="ti ti-x"></i>
                        Cancelar

                    </button>

                    <button
                        class="btn btn-success"
                        @click="guardarMunicipalAlcaldiaEstado()">
                        <i class="ti ti-check"></i>
                        Guardar

                    </button>

                </div>

            </div>

        </div>

    </div>
    <!-- Municipio, Alcaldía y Estado -->

    <!-- Dependencias -->
    <div
        class="modal fade"
        id="modalDependencias"
        tabindex="-1">

        <div class="modal-dialog modal-md modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h4 class="modal-title text-white">
                        Dependencias
                    </h4>

                    <button
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <textarea class="form-control"
                        x-model="dependencias"
                        @input="errors.dependencias = false"
                        :class="errors.dependencias ? 'is-invalid' : ''"></textarea>

                </div>

                <div class="modal-footer">

                    <button
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                        <i class="ti ti-x"></i>
                        Cancelar

                    </button>

                    <button
                        class="btn btn-success"
                        @click="guardarDependencias()">
                        <i class="ti ti-check"></i>
                        Guardar

                    </button>

                </div>

            </div>

        </div>

    </div>
    <!-- Dependencias -->


    <!-- Modal Requisitos Legales -->
    <div
        class="modal fade"
        id="modalRequisitoLegal"
        tabindex="-1"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h4
                        class="modal-title text-white"
                        x-text="form.mode === 'edit'
                        ? 'Editar Requisito Legal'
                        : 'Nuevo Requisito Legal'"></h4>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>

                </div>

                <div class="modal-body">

                    <!-- Nivel de gobierno -->
                    <div class="mb-3">

                        <label class="form-label">
                            Nivel de gobierno
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            list="nivelesGobierno"
                            x-model="form.nivelGobierno"
                            @input="errors.nivelGobierno = false"
                            :class="errors.nivelGobierno ? 'is-invalid' : ''"
                            placeholder="Escribe para buscar..."
                            autocomplete="off">

                        <datalist id="nivelesGobierno">

                            <?php foreach ($nivelesGobierno as $nivel): ?>

                                <option value="<?= htmlspecialchars($nivel->gobierno) ?>">

                                <?php endforeach; ?>

                        </datalist>

                    </div>


                    <!-- Municipio / Alcaldía / Estado -->
                    <div class="mb-3">

                        <label class="form-label">
                            Municipio, Alcaldía y Estado
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            list="municipios"
                            x-model="form.municipioAlcaldiaEstado"
                            placeholder="Escribe para buscar...">

                        <datalist id="municipios">

                            <?php foreach ($municipiosAlcaldiasEstados as $municipio): ?>

                                <option value="<?= htmlspecialchars($municipio->mun_alc_est) ?>">

                                <?php endforeach; ?>

                        </datalist>

                    </div>


                    <!-- Dependencia -->
                    <div class="mb-3">

                        <label class="form-label">
                            Dependencia
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            list="dependencias"
                            x-model="form.dependencia"
                            placeholder="Escribe para buscar...">

                        <datalist id="dependencias">

                            <?php foreach ($dependencias as $dependencia): ?>

                                <option value="<?= htmlspecialchars($dependencia->dependencia) ?>">

                                <?php endforeach; ?>

                        </datalist>

                    </div>


                    <!-- Permiso -->
                    <div class="mb-3">

                        <label class="form-label">
                            Permiso
                        </label>

                        <textarea
                            class="form-control"
                            rows="3"
                            x-model="form.permiso"
                            :class="errors.permiso ? 'is-invalid' : ''"
                            @input="errors.permiso = false"></textarea>

                    </div>


                    <!-- Fundamento -->
                    <div class="mb-3">

                        <label class="form-label">
                            Fundamento
                        </label>

                        <textarea
                            class="form-control"
                            rows="3"
                            x-model="form.fundamento"
                            :class="errors.fundamento ? 'is-invalid' : ''"
                            @input="errors.fundamento = false"></textarea>

                    </div>


                    <!-- Responsable -->
                    <div class="mb-3">

                        <label class="form-label">
                            Responsable
                        </label>

                        <select
                            class="form-select"
                            x-model="form.idPersonal">
                            <option value="0">
                                <span x-text="form.responsable || 'Selecciona una opción...'"></span>
                            </option>

                            <?php foreach ($personal as $usuario): ?>

                                <option value="<?= $usuario->id ?>">
                                    <?= htmlspecialchars($usuario->nombre) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- SGM -->
                    <?php if (!empty($permisos['crear'])): ?>

                        <div class="mb-3">

                            <label class="form-label">
                                SGM
                            </label>

                            <select
                                class="form-select"
                                x-model="form.sgm">
                                <option value="0">
                                    NO
                                </option>

                                <option value="1">
                                    SI
                                </option>

                            </select>

                        </div>

                    <?php endif; ?>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                        <i class="ti ti-x"></i>
                        Cancelar
                    </button>


                    <button
                        type="button"
                        class="btn btn-success"
                        @click="guardarRequisitoLegal()">

                        <i class="ti ti-check"></i> Guardar

                    </button>

                </div>

            </div>

        </div>
    </div>
    <!-- Modal Requisitos Legales -->


</div>