<div
    id="portal3TutorialBienvenida"
    class="modal fade portal3-tutorial-modal"
    tabindex="-1"
    aria-labelledby="portal3TutorialTitulo"
    aria-hidden="true"
    data-multiestacion="<?= !empty($tutorialMultiestacion) ? '1' : '0' ?>"
    data-autostart="<?= !empty($tutorialPendiente) ? '1' : '0' ?>"
    data-obligatorio="<?= !empty($tutorialPendiente) ? '1' : '0' ?>"
    data-capturas="<?= htmlspecialchars(json_encode($tutorialCapturasDisponibles ?? [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES, 'UTF-8') ?>"
    data-capturas-animadas="<?= htmlspecialchars(json_encode($tutorialCapturasAnimadas ?? [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES, 'UTF-8') ?>"
    data-capturas-animadas-alta-calidad="<?= htmlspecialchars(json_encode($tutorialCapturasAnimadasAltaCalidad ?? [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES, 'UTF-8') ?>"
    data-complete-url="/home/tutorial/complete">
    <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
        <div class="modal-content" x-data="tutorialBienvenida()" x-init="init()" @keydown.arrow-right.window="siguienteDesdeTeclado($event)" @keydown.arrow-left.window="anteriorDesdeTeclado($event)">
            <div class="portal3-tutorial-topline" aria-hidden="true"><span :style="`width: ${progreso}%`"></span></div>

            <div class="modal-header portal3-tutorial-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="portal3-tutorial-brand-icon"><i class="ti ti-sparkles"></i></span>
                    <div>
                        <p class="portal3-tutorial-kicker mb-1">Portal3 · recorrido de bienvenida</p>
                        <h2 class="modal-title fs-5 mb-0" id="portal3TutorialTitulo" x-text="pasoActual.titulo"></h2>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar tutorial" x-show="!obligatorio"></button>
            </div>

            <div class="modal-body p-0">
                <div class="portal3-tutorial-layout" x-cloak>
                    <section class="portal3-tutorial-copy" aria-live="polite" aria-atomic="true">
                        <div class="portal3-tutorial-step-label">
                            <span class="badge rounded-pill text-bg-primary" x-text="pasoActual.seccion"></span>
                            <span class="text-body-secondary" x-text="etiquetaPaso"></span>
                        </div>
                        <div class="portal3-tutorial-copy-content" :key="indice" x-transition.opacity.duration.180ms>
                            <h3 class="portal3-tutorial-heading"><i class="ti portal3-tutorial-topic-icon" :class="pasoActual.icono" aria-hidden="true"></i><span x-text="pasoActual.encabezado"></span></h3>
                            <p class="portal3-tutorial-description" x-text="pasoActual.descripcion"></p>
                            <ul class="portal3-tutorial-points" x-show="pasoActual.puntos.length">
                                <template x-for="punto in pasoActual.puntos" :key="punto">
                                    <li><i class="ti ti-circle-check" aria-hidden="true"></i><span x-text="punto"></span></li>
                                </template>
                            </ul>
                            <p class="portal3-tutorial-hint" x-show="pasoActual.nota" x-text="pasoActual.nota"></p>
                        </div>
                    </section>

                    <aside class="portal3-tutorial-visual" :data-image-slot="pasoActual.imagen" :aria-label="`Espacio para captura: ${pasoActual.titulo}`">
                        <div class="portal3-tutorial-image-frame">
                            <template x-if="capturaDisponible(pasoActual.imagen)">
                                <div class="portal3-tutorial-image-stack">
                                    <img class="portal3-tutorial-image" :src="capturaRuta(pasoActual.imagen)" :alt="`Captura de Portal3: ${pasoActual.titulo}`" @error="marcarCapturaNoDisponible(pasoActual.imagen)">
                                    <template x-if="capturaAnimadaDisponible(pasoActual.imagen)">
                                        <img class="portal3-tutorial-image portal3-tutorial-image-overlay" :src="capturaAnimadaRuta(pasoActual.imagen)" @error="usarAnimacionAlternativa($event, pasoActual.imagen)" alt="" aria-hidden="true">
                                    </template>
                                </div>
                            </template>
                            <div class="portal3-tutorial-image-placeholder" x-show="!capturaDisponible(pasoActual.imagen)">
                                <span class="portal3-tutorial-image-icon"><i class="ti ti-photo" aria-hidden="true"></i></span>
                                <span class="portal3-tutorial-image-title">Captura de Portal3</span>
                                <small>Imagen pendiente de integrar</small>
                                <code x-text="`/assets/images/tutorial-bienvenida/${pasoActual.imagen}.webp`"></code>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>

            <div class="modal-footer portal3-tutorial-footer">
                <div class="portal3-tutorial-progress-copy">
                    <span class="portal3-tutorial-dots" aria-hidden="true">
                        <template x-for="(paso, posicion) in pasos" :key="paso.id">
                            <span :class="{'is-active': posicion === indice, 'is-done': posicion < indice}"></span>
                        </template>
                    </span>
                    <small class="text-body-secondary" x-text="`Paso ${indice + 1} de ${pasos.length}`"></small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-light portal3-tutorial-previous" @click="anterior()" x-show="indice > 0">
                        <i class="ti ti-arrow-left me-1" aria-hidden="true"></i>Anterior
                    </button>
                    <button type="button" class="btn btn-primary" @click="siguiente()" x-show="!esUltimo">
                        Continuar<i class="ti ti-arrow-right ms-2" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="btn btn-primary" @click="finalizar()" :disabled="guardando" x-show="esUltimo">
                        <span x-show="!guardando"><i class="ti ti-check me-1" aria-hidden="true"></i>Finalizar recorrido</span>
                        <span x-show="guardando"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Guardando…</span>
                    </button>
                </div>
                <p class="portal3-tutorial-error text-danger small mb-0 w-100" role="alert" x-show="error" x-text="error"></p>
            </div>
        </div>
    </div>
</div>
