<div id="container" class="pb-4"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>"
    x-data="{ ...actions(), ...fichaPersonalForm() }"
    data-id="<?= $usuario->id ?? '' ?>"
    x-init="
    nombre='<?= htmlspecialchars($usuario->nombre ?? '') ?>';
    domicilio='<?= htmlspecialchars($usuario->domicilio ?? '') ?>';
    fechaNacimiento='<?= $usuario->fecha_nacimiento ?? '' ?>';   
    estadoCivil='<?= $usuario->estado_civil ?? '' ?>';
    seguroSocial='<?= htmlspecialchars($usuario->seguro_social ?? '') ?>'; 
    telefono='<?= htmlspecialchars($usuario->telefono ?? '') ?>';
    email='<?= htmlspecialchars($usuario->email ?? '') ?>';
    firmaPreview = '<?= !empty($usuario->firma)
                        ? $_ENV['APP_URL'] . '/uploads/firma-personal/' . $usuario->firma
                        : '' ?>';
">

    <div class="text-end">
        <a href="/sasisopa/competencia-personal-capacitacion-entrenamiento/ficha-personal-pdf/<?= $usuario->id ?? '' ?>" type="button" class="btn bg-primary-subtle text-primary">
            <i class="ti ti-download"></i> Descargar</a>
    </div>

    <!-- 1. Datos del personal -->
    <div class="row mt-4">
        <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
            <div class="card">
                <div class="card-header">
                    <h5>1. Datos del personal</h5>
                </div>
                <div class="card-body">



                    <label class="form-label">* Nombre completo:</label>
                    <input type="text" class="form-control" x-model="nombre" :class="errors.nombre ? 'is-invalid' : ''">

                    <label class="form-label mt-3">* Domicilio( Calle, Numero, Colonia, Municipio, Estado, C.P.):</label>
                    <input type="text" class="form-control" x-model="domicilio" :class="errors.domicilio ? 'is-invalid' : ''">

                    <div class="row">
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 mt-3">
                            <label class="form-label">* Fecha de nacimiento:</label>
                            <input type="date" class="form-control" x-model="fechaNacimiento" :class="errors.fechaNacimiento ? 'is-invalid' : ''">
                        </div>

                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 mt-3">
                            <label class="form-label">* Estado civil:</label>
                            <select class="form-select" x-model="estadoCivil" :class="errors.estadoCivil ? 'is-invalid' : ''">
                                <option value="">Selecciona una opción</option>
                                <option value="Soltero(a)">Soltero(a)</option>
                                <option value="Casado(a)">Casado(a)</option>
                                <option value="Divorciado(a)">Divorciado(a)</option>
                                <option value="Viudo(a)">Viudo(a)</option>
                            </select>
                        </div>

                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 mt-3">
                            <label class="form-label">* No. De seguro social:</label>
                            <input type="text" class="form-control" x-model="seguroSocial" :class="errors.seguroSocial ? 'is-invalid' : ''">
                        </div>

                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 mt-3">
                            <label class="form-label">Telefono:</label>
                            <input type="number" class="form-control" x-model="telefono" :class="errors.telefono ? 'is-invalid' : ''">
                        </div>

                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 mt-3">
                            <label class="form-label">Correo electrónico:</label>
                            <input type="text" class="form-control" x-model="email" :class="errors.email ? 'is-invalid' : ''">
                        </div>

                    </div>



                </div>

                <div class="card-footer">
                    <div class="text-end">
                        <button class="btn btn-success" @click="actualizar()">
                            <i class="ti ti-check"></i>
                            Actualizar</button>
                    </div>
                </div>
            </div>
        </div>


        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
            <div class="card border-0 bg-white h-100">

                <!-- Encabezado con ícono circular -->
                <div class="card-header text-bg-primary py-3 border-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:45px;height:45px;">
                                <i class="ti ti-signature fs-6"></i>
                            </div>
                            <div class="ms-3">
                                <h5 class="mb-0 text-white">FIRMA</h5>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cuerpo con el pad de firma punteado -->
                <div class="card-body p-3 pb-0">
                    <div id="signature-pad" class="signature-pad-wrapper" style="border: 2px dashed #adb5bd; border-radius: 6px; cursor: crosshair;">
                        <div class="signature-pad--body">
                            <!-- Se agregaron width/height nativos para resolver el fallo al borrar -->
                            <canvas x-ref="canvas" id="canvas" width="500" height="250" style="width:100%; height:250px; display: block;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Botón a lo ancho pegado a la base del área de dibujo -->
                <button type="button" class="btn bg-danger-subtle text-danger w-100 rounded-0" @click="limpiarFirma(); firmaPreview = null; firmaError = false;">
                    <i class="ti ti-eraser me-1"></i> Limpiar firma
                </button>

                <button type="button" class="btn btn-success mt-3 w-100 rounded-0" @click="guardarFirma()">
                    <i class="ti ti-check me-1"></i> Guardar Firma
                </button>

                <!-- Bloque inferior: Guardar y Vista previa -->
                <div class="card-body p-3 border-top mt-auto">


                    <!-- Preview de la firma -->
                    <div class="mt-3 text-center" x-show="firmaPreview && !firmaError">
                        <span class="d-block small text-muted mb-1">Vista previa:</span>
                        <img :src="firmaPreview" @error="firmaError = true" class="img-fluid border rounded p-1" style="max-height: 100px;">
                    </div>

                    <!-- Estado sin firma -->
                    <div x-show="!firmaPreview || firmaError" class="text-muted text-center mt-3">
                        <small><i class="ti ti-info-circle me-1"></i>No se ha registrado una firma</small>
                    </div>
                </div>

            </div>
        </div>



    </div>

    <!-- 2. Datos de familiares -->

    <div class="card">
        <div class="card-header">
            <div class="d-flex align-items-center">
                <h4 class="card-title mb-0">2. Datos de familiares</h4>
                <div class="ms-auto">
                    <?=
                    !empty($permisos['crear']) ?
                        '<button type="button" class="btn bg-primary-subtle text-primary" @click="openModalFamiliar()">
                    <i class="ti ti-plus"></i> Nuevo
                    </button>'
                        : ''
                    ?>
                </div>
            </div>
        </div>
        <div class="card-body pb-2">

            <div class="table-responsive overflow-x-auto overflow-y-hidden">
                <table class="table table-bordered table-striped table-sm">
                    <thead>
                        <tr class="text-center align-middle">
                            <th>Nombre</th>
                            <th>Parentesco</th>
                            <th>Dirección</th>
                            <th>Teléfono</th>
                            <th><a class="text-muted"><i class="ti ti-trash fs-6"></i></a></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="familiares.length === 0">
                            <tr>
                                <td colspan="5" class="text-center">
                                    No se encontraron datos familiares
                                </td>
                            </tr>
                        </template>

                        <template x-for="f in familiares" :key="f.id">
                            <tr class="text-center align-middle">
                                <td x-text="f.nombrecompleto"></td>
                                <td x-text="f.parentesco"></td>
                                <td x-text="f.domicilio"></td>
                                <td x-text="f.telefono"></td>
                                <td width="40px">
                                    <a class="pointer"
                                        @click="eliminarFamiliar(f.id, f.nombrecompleto)">
                                        <i class="ti ti-trash text-danger fs-6"></i>
                                    </a>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- 3. Formación académica -->

    <div class="card">
        <div class="card-header">
            <div class="d-flex align-items-center">
                <h4 class="card-title mb-0">3. Formación académica</h4>
                <div class="ms-auto">
                    <?=
                    !empty($permisos['crear']) ?
                        '<button type="button" class="btn bg-primary-subtle text-primary" @click="openModalFormacion()">
                    <i class="ti ti-plus"></i> Nuevo
                    </button>'
                        : ''
                    ?>

                </div>
            </div>
        </div>
        <div class="card-body pb-2">



            <div class="table-responsive overflow-x-auto overflow-y-hidden">
                <table class="table table-bordered table-striped table-sm">
                    <thead class="navbar-bg">
                        <tr class="text-center align-middle">
                            <th>Nivel</th>
                            <th>Institución</th>
                            <th class="text-center"><a class="text-muted"><i class="ti ti-trash fs-6"></i></a></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="formaciones.length === 0">
                            <tr>
                                <td colspan="3" class="text-center">
                                    Se se encontraron datos de formación académica
                                </td>
                            </tr>
                        </template>

                        <template x-for="fa in formaciones" :key="fa.id">
                            <tr class="text-center align-middle">
                                <td x-text="fa.nivel"></td>
                                <td x-text="fa.detalle"></td>
                                <td width="40px">
                                    <a class="pointer"
                                        @click="eliminarFormacion(fa.id, fa.nivel)">
                                        <i class="ti ti-trash text-danger fs-6"></i>
                                    </a>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- 4. Experiencia laboral -->

    <div class="card">
        <div class="card-header">

            <div class="d-flex align-items-center">
                <h4 class="card-title mb-0">4. Experiencia laboral</h4>
                <div class="ms-auto">
                    <?=
                    !empty($permisos['crear']) ?
                        '<button type="button" class="btn bg-primary-subtle text-primary" @click="openModalExperiencia()">
                    <i class="ti ti-plus"></i> Nuevo
                    </button>'
                        : ''
                    ?>
                </div>
            </div>

            <h5>4.1 En otras empresas</h5>
        </div>
        <div class="card-body pb-2">


            <div class="table-responsive overflow-x-auto overflow-y-hidden">
                <table class="table table-bordered table-striped table-sm">
                    <tbody>

                        <template x-if="experiencias.length === 0">
                            <tr>
                                <td colspan="2" class="text-center">
                                    No se encontró información de experiencia laboral en otras empresas
                                </td>
                            </tr>
                        </template>

                        <template x-for="exp in experiencias" :key="exp.id">
                            <tr>
                                <td x-text="exp.detalle"></td>

                                <td class="align-middle text-center" width="40px">
                                    <a class="pointer"
                                        @click="eliminarExperiencia(exp.id, exp.detalle)">
                                        <i class="ti ti-trash text-danger fs-6"></i>
                                    </a>
                                </td>
                            </tr>
                        </template>

                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- 4.2 En la empresa  -->

    <div class="card">
        <div class="card-header">
            <div class="d-flex align-items-center">
                <h4 class="card-title mb-0">4.2 En la empresa </h4>
                <div class="ms-auto">
                    <?=
                    !empty($permisos['crear']) ?
                        '<button type="button" class="btn bg-primary-subtle text-primary" @click="openModalEmpresa()">
                    <i class="ti ti-plus"></i> Nuevo
                    </button>'
                        : ''
                    ?>
                </div>
            </div>
        </div>
        <div class="card-body pb-2">



            <div class="table-responsive overflow-x-auto overflow-y-hidden">
                <table class="table table-bordered table-striped table-sm">
                    <thead>

                        <tr>
                            <th class="text-center align-middle" rowspan="2">Razón social</th>
                            <th class="text-center align-middle" rowspan="2">Puesto</th>
                            <th colspan="4" class="text-center">Periodo</th>
                        </tr>
                        <tr>
                            <th class="text-center">Inicio</th>
                            <th class="text-center">Termino</th>
                            <th width="40px" class="text-center"><a class="text-muted"><i class="ti ti-edit fs-6"></i></a></th>
                            <th width="40px" class="text-center"><a class="text-muted"><i class="ti ti-trash fs-6"></i></a></th>
                        </tr>
                    </thead>

                    <tbody>
                        <template x-for="e in empresas" :key="e.id">
                            <tr class="text-center">
                                <td x-text="e.razon_social"></td>
                                <td x-text="e.puesto"></td>
                                <td x-text="formatearFecha(e.periodo_inicio)"></td>
                                <td x-text="formatearFecha(e.periodo_fin)"></td>
                                <td>
                                    <a @click="editarEmpresa(e.id)">
                                        <i class="ti ti-edit fs-7 pointer"></i>
                                    </a>
                                </td>
                                <td>
                                    <a @click="eliminarEmpresa(e.id, e.razon_social)">
                                        <i class="ti ti-trash text-danger fs-7 pointer"></i>
                                    </a>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="empresas.length === 0">
                            <td colspan="6" class="text-center text-muted">
                                No se encontró información de experiencia laboral en la empresa
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>


        </div>
    </div>

    <!-- Modal para agregar familiar -->

    <div class="modal fade" id="modalFamiliar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h5 class="modal-title text-white">
                        <i class="ti ti-user-plus"></i>
                        Nuevo familiar
                    </h5>
                    <button type="button" class="btn-close btn-close-white" @click="closeModal('familiar')"></button>
                </div>

                <div class="modal-body">
                    <label class="form-label">* Nombre de mi familiar:</label>
                    <input class="form-control mb-2" placeholder="Nombre"
                        x-model="familiar.nombrecompleto"
                        :class="errorsFamiliar.nombrecompleto ? 'is-invalid' : ''"
                        @input="errorsFamiliar.nombrecompleto = false">

                    <label class="form-label">* Parentesco:</label>
                    <input class="form-control mb-2" placeholder="Parentesco"
                        x-model="familiar.parentesco"
                        :class="errorsFamiliar.parentesco ? 'is-invalid' : ''"
                        @input="errorsFamiliar.parentesco = false">

                    <label class="form-label">* Dirección completa:</label>
                    <input class="form-control mb-2" placeholder="Domicilio"
                        x-model="familiar.domicilio"
                        :class="errorsFamiliar.domicilio ? 'is-invalid' : ''"
                        @input="errorsFamiliar.domicilio = false">

                    <label class="form-label">* Teléfono:</label>
                    <input class="form-control" placeholder="Teléfono"
                        x-model="familiar.telefono"
                        :class="errorsFamiliar.telefono ? 'is-invalid' : ''"
                        @input="errorsFamiliar.telefono = false">
                </div>

                <div class="modal-footer">
                    <button class="btn bg-danger-subtle text-danger" @click="closeModal('familiar')"><i class="ti ti-x"></i> Cancelar</button>
                    <button class="btn btn-success" @click="guardarFamiliar()"><i class="ti ti-check"></i> Guardar</button>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal para agregar formación académica -->

    <div class="modal fade" id="modalFormacion" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h5 class="modal-title text-white">
                        <i class="ti ti-school"></i>
                        Nueva formación académica
                    </h5>
                    <button type="button" class="btn-close btn-close-white" @click="closeModal('formacion')"></button>
                </div>

                <div class="modal-body">

                    <label class="form-label">* Nivel:</label>

                    <select class="form-control"
                        x-model="formacion.nivel" placeholder="Nivel"
                        :class="errorsFormacion.nivel ? 'is-invalid' : ''"
                        @input="errorsFormacion.nivel = false">
                        <option value="">Selecciona una opción...</option>
                        <option value="Primaria">Primaria</option>
                        <option value="Secundaria">Secundaria</option>
                        <option value="Bachillerato">Bachillerato</option>
                        <option value="Licenciatura">Licenciatura</option>
                    </select>

                    <label class="form-label mt-3">* Institución:</label>
                    <input class="form-control"
                        x-model="formacion.detalle" placeholder="Nombre de la institución"
                        :class="errorsFormacion.detalle ? 'is-invalid' : ''"
                        @input="errorsFormacion.detalle = false">

                </div>

                <div class="modal-footer">
                    <button class="btn bg-danger-subtle text-danger" @click="closeModal('formacion')"><i class="ti ti-x"></i> Cancelar</button>
                    <button class="btn btn-success" @click="guardarFormacion()"><i class="ti ti-check"></i> Guardar</button>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal para agregar experiencia laboral -->

    <div class="modal fade" id="modalExperiencia" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h5 class="modal-title text-white">
                        <i class="ti ti-briefcase-2"></i>
                        Nueva experiencia laboral
                    </h5>
                    <button type="button" class="btn-close btn-close-white" @click="closeModal('experiencia')"></button>
                </div>

                <div class="modal-body">

                    <label class="form-label">* Experiencia laboral:</label>
                    <textarea class="form-control"
                        rows="3"
                        x-model="experiencia.detalle"
                        :class="errorsExperiencia.detalle ? 'is-invalid' : ''"
                        placeholder="Describe la experiencia"></textarea>

                </div>

                <div class="modal-footer">
                    <button class="btn bg-danger-subtle text-danger" @click="closeModal('experiencia')"><i class="ti ti-x"></i> Cancelar</button>
                    <button class="btn btn-success" @click="guardarExperiencia()"><i class="ti ti-check"></i> Guardar</button>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal para agregar experiencia en la empresa -->

    <div class="modal fade" id="modalEmpresa" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h5 class="modal-title text-white">
                        <i class="ti ti-briefcase-2"></i>
                        <label x-text="editandoEmpresa ? 'Editar experiencia' : 'Nueva experiencia en la empresa'"></label>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" @click="closeModalEmpresa()"></button>
                </div>

                <div class="modal-body">

                    <label class="form-label">* Razón social:</label>
                    <input class="form-control mb-2"
                        x-model="empresa.razon_social"
                        :class="errorsEmpresa.razon_social ? 'is-invalid' : ''">

                    <label class="form-label">* Puesto:</label>
                    <input class="form-control mb-2"
                        x-model="empresa.puesto"
                        :class="errorsEmpresa.puesto ? 'is-invalid' : ''">

                    <label class="form-label">* Fecha inicio:</label>
                    <input type="date" class="form-control mb-2"
                        x-model="empresa.periodo_inicio"
                        :class="errorsEmpresa.periodo_inicio ? 'is-invalid' : ''">

                    <label class="form-label">Fecha fin:</label>
                    <input type="date" class="form-control"
                        x-model="empresa.periodo_fin">

                </div>

                <div class="modal-footer">
                    <button class="btn bg-danger-subtle text-danger" @click="closeModalEmpresa()"><i class="ti ti-x"></i> Cancelar</button>
                    <button class="btn btn-success" @click="guardarEmpresa()"><i class="ti ti-check"></i> Guardar</button>
                </div>

            </div>
        </div>
    </div>

</div>