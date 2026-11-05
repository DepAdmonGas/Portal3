<style>
/* --- ESTILOS VANGUARD / HUD INTERACTIVO AJUSTADO --- */
.vanguard-profile-wrapper {
    --vanguard-primary: #6366f1; /* Tono índigo moderno */
    --vanguard-glow: rgba(99, 102, 241, 0.25);
}

/* Anillo contenedor con transición de brillo */
.vanguard-profile-wrapper .avatar-ring {
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    border: 2px solid rgba(226, 232, 240, 0.8);
}

.vanguard-profile-wrapper:hover .avatar-ring {
    border-color: var(--vanguard-primary);
    box-shadow: 0 0 0 6px var(--vanguard-glow) !important;
    transform: translateY(-2px);
}

/* HUD Flotante inferior posicionado más abajo */
.vanguard-profile-wrapper .vanguard-hud {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-color: rgba(255, 255, 255, 0.6) !important;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.08) !important;
    z-index: 5;
}

/* Botones dentro del HUD */
.vanguard-profile-wrapper .hud-action-btn {
    background: transparent;
    color: #334155;
    border: none;
    transition: all 0.2s ease;
}

.vanguard-profile-wrapper .hud-action-btn:hover:not(:disabled) {
    background: #6366f1;
    color: #ffffff;
    transform: scale(1.1);
}

.vanguard-profile-wrapper .hud-danger-btn {
    background: transparent;
    color: #ef4444;
    border: none;
    transition: all 0.2s ease;
}

.vanguard-profile-wrapper .hud-danger-btn:hover:not(:disabled) {
    background: #ef4444;
    color: #ffffff;
    transform: scale(1.1);
}
</style>


<?php
$fechaNacimientoPerfil = trim((string) ($user->fecha_nacimiento ?? ''));
if ($fechaNacimientoPerfil === '0000-00-00' || str_starts_with($fechaNacimientoPerfil, '0000-00-00 ')) {
    $fechaNacimientoPerfil = '';
} else {
    $fechaNacimientoPerfil = substr($fechaNacimientoPerfil, 0, 10);
}
$fotoPerfilPersonalizada = \App\Services\PerfilFotoService::tieneFotoPersonalizada($user ?? null);
?>

<div
x-data="perfilPage()"
x-on:abrir-modal-perfil.window="abrirModalPerfil()"
data-profile-photo="<?= $fotoPerfilPersonalizada ? '1' : '0' ?>"
data-profile-name="<?= htmlspecialchars((string) ($user->nombre ?? ''), ENT_QUOTES, 'UTF-8') ?>"
data-profile-birthdate="<?= htmlspecialchars($fechaNacimientoPerfil, ENT_QUOTES, 'UTF-8') ?>"
data-profile-marital-status="<?= htmlspecialchars((string) ($user->estado_civil ?? ''), ENT_QUOTES, 'UTF-8') ?>"
data-profile-social-security="<?= htmlspecialchars((string) ($user->seguro_social ?? ''), ENT_QUOTES, 'UTF-8') ?>"
data-profile-address="<?= htmlspecialchars((string) ($user->domicilio ?? ''), ENT_QUOTES, 'UTF-8') ?>"
data-profile-email="<?= htmlspecialchars((string) ($user->email ?? ''), ENT_QUOTES, 'UTF-8') ?>"
data-profile-phone="<?= htmlspecialchars((string) ($user->telefono ?? ''), ENT_QUOTES, 'UTF-8') ?>"
data-profile-username="<?= htmlspecialchars((string) ($user->usuario ?? ''), ENT_QUOTES, 'UTF-8') ?>"
data-profile-signature="<?= htmlspecialchars(!empty($user->firma) ? rtrim((string) ($_ENV['APP_URL'] ?? ''), '/') . '/uploads/firma-personal/' . rawurlencode((string) $user->firma) : '', ENT_QUOTES, 'UTF-8') ?>">

<div class="container-fluid mt-4">

<div class="row">

<div class="col-lg-4">

<div class="row">
<div class="col-12 mb-4">
<div class="card h-100">

<div class="card-body text-center">

<!-- Avatar -->
<div class="position-relative d-inline-block mb-4 vanguard-profile-wrapper">
    
    <!-- Contenedor del avatar con borde interactivo -->
    <div class="avatar-ring position-relative rounded-circle p-1 bg-white shadow-sm">
        <img src="<?= htmlspecialchars($fotoPerfilUrl, ENT_QUOTES, 'UTF-8') ?>"
             alt="Foto de <?= htmlspecialchars((string) ($user->nombre ?? 'usuario'), ENT_QUOTES, 'UTF-8') ?>"
             class="profile-avatar rounded-circle object-fit-cover"
             width="120"
             height="120">
    </div>

    <!-- Indicador de estado activo (Tech Badge superior derecho) -->
    <span class="position-absolute top-0 end-0 bg-success border border-3 border-white rounded-circle shadow-sm"
          style="transform: translate(-40%, 20%); width: 25px; height: 25px;"
          title="Usuario activo"></span>

    <!-- Barra de acciones flotante estilo HUD (Ajustada más abajo) -->
    <div class="vanguard-hud position-absolute start-50 d-flex align-items-center gap-1 p-1 rounded-pill shadow-lg border"
         style="bottom: -16px; transform: translateX(-50%);">
        
        <!-- Botón para cambiar foto -->
        <button type="button" 
                class="btn btn-sm rounded-circle d-flex align-items-center justify-content-center hud-action-btn"
                style="width: 32px; height: 32px;" 
                aria-label="Cambiar foto de perfil" 
                title="Cambiar foto" 
                @click="$refs.fotoPerfilInput.click()" 
                :disabled="guardandoFotoPerfil">
            <i class="ti fs-6" :class="guardandoFotoPerfil ? 'ti-loader-2 ti-spin' : 'ti-camera'"></i>
        </button>

        <!-- Botón para eliminar foto personalizada -->
        <template x-if="fotoPersonalizada">
            <button type="button" 
                    class="btn btn-sm rounded-circle d-flex align-items-center justify-content-center hud-danger-btn"
                    style="width: 32px; height: 32px;" 
                    aria-label="Eliminar foto personalizada" 
                    title="Volver al avatar predeterminado" 
                    x-cloak 
                    @click="eliminarFotoPerfil()" 
                    :disabled="guardandoFotoPerfil || eliminandoFotoPerfil">
                <i class="ti fs-6" :class="eliminandoFotoPerfil ? 'ti-loader-2 ti-spin' : 'ti-trash'"></i>
            </button>
        </template>
    </div>

    <!-- Input de archivo oculto -->
    <input x-ref="fotoPerfilInput" 
           type="file" 
           accept="image/jpeg,image/png,image/webp" 
           class="d-none" 
           @change="actualizarFotoPerfil($event)" 
           :disabled="guardandoFotoPerfil">

</div>


<div class="alert alert-danger py-2 mt-2" role="alert" x-show="errorFotoPerfil" x-cloak x-text="errorFotoPerfil"></div>


<!-- Nombre -->
<h4 class="fw-semibold mb-1" x-text="infoPersonal.nombre || 'No registrado'">
<?= htmlspecialchars(trim((string) ($user->nombre ?? '')) ?: 'No registrado', ENT_QUOTES, 'UTF-8') ?>
</h4>


<!-- Puesto -->
<p class="text-muted mb-3">
<?= htmlspecialchars(
trim((string) ($user->puesto?->tipo_puesto ?? '')) ?: 'No registrado'
) ?>
</p>


<!-- Datos rápidos -->
<div
class="d-flex justify-content-center gap-2 flex-wrap mb-4">

<span class="badge bg-primary-subtle text-primary">

<i class="ti ti-id me-1"></i>

ID: 00<?= (int) ($user->id ?? 0) ?>

</span>


<?php if ($user->estatus == 0): ?>

<span class="badge bg-success-subtle text-success">

<i class="ti ti-circle-check me-1"></i>

Activo

</span>

<?php else: ?>

<span class="badge bg-danger-subtle text-danger">

<i class="ti ti-circle-x me-1"></i>

Inactivo

</span>

<?php endif; ?>

</div>


<!-- Contacto -->
<div class="text-start">

<div
class="d-flex align-items-center gap-3 py-3 border-top">

<div
class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
style="width:42px;height:42px;">
<i class="ti ti-mail fs-5"></i>
</div>

<div class="overflow-hidden">

<small class="text-muted d-block">
Correo electrónico
</small>

<span class="fw-medium text-break">
<?= htmlspecialchars(
trim((string) ($user->email ?? '')) ?: 'No registrado'
) ?>
</span>

</div>

</div>


<div
class="d-flex align-items-center gap-3 py-3 border-top">

<div
class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
style="width:42px;height:42px;">
<i class="ti ti-phone fs-5"></i>
</div>

<div>

<small class="text-muted d-block">
Teléfono
</small>

<span class="fw-medium">
<?= htmlspecialchars(
trim((string) ($user->telefono ?? '')) ?: 'No registrado'
) ?>
</span>

</div>

</div>


<div
class="d-flex align-items-center gap-3 py-3 border-top">

<div
class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
style="width:42px;height:42px;">
<i class="ti ti-at fs-5"></i>
</div>

<div>

<small class="text-muted d-block">
Usuario
</small>

<span class="fw-medium">
<?= htmlspecialchars(
trim((string) ($user->usuario ?? '')) ?: 'No registrado'
) ?>
</span>

</div>

</div>

</div>


<!-- Acción -->
<div class="d-grid mt-3">

<button
type="button"
class="btn btn-primary"
@click="abrirModalPerfil()">
<i class="ti ti-edit me-2"></i>
Editar perfil
</button>

</div>

</div>

</div>
</div>



<div class="col-12">
<div class="card">

<div class="card-header">

<div class="d-flex align-items-center gap-2">

<span
class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center"
style="width:38px;height:38px;">
<i class="ti ti-brand-telegram fs-5"></i>

</span>

<div>

<h5 class="card-title mb-0">
Telegram
</h5>

<small class="text-muted">
Recepcion de notificaciones internas
</small>

</div>

</div>

</div>


<?php
$firmaPerfilArchivo = basename((string) ($user->firma ?? ''));
$firmaPerfilRuta = PUBLIC_PATH . '/uploads/firma-personal/' . $firmaPerfilArchivo;
$firmaPerfilUrl = $firmaPerfilArchivo !== '' && is_file($firmaPerfilRuta)
? rtrim((string) ($_ENV['APP_URL'] ?? ''), '/') . '/uploads/firma-personal/' . rawurlencode($firmaPerfilArchivo)
: '';
$escapePerfil = static fn($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
?>
<div
class="card-body"
x-data="telegramToken()">

<template x-if="cargando">

<div class="text-center py-4">

<span
class="spinner-border spinner-border-sm me-2"
aria-hidden="true"></span>

Cargando estado de Telegram...

</div>

</template>

<template x-if="!cargando && error">

<div class="alert alert-danger" role="alert">

<div class="d-flex align-items-center justify-content-between gap-3 w-100">

<i class="ti ti-alert-circle fs-5"></i>

<span x-text="error"></span>
<button type="button" class="btn btn-sm btn-outline-danger ms-auto" @click="cargar(true)" :disabled="consultando"><span x-show="!consultando"><i class="ti ti-refresh me-1"></i>Reintentar</span><span x-show="consultando"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Consultando</span></button>

</div>

</div>

</template>

<template
x-if="!cargando && (estado === 'sin-registro' || estado === 'pendiente')">

<div class="row g-3">

<div class="col-12">

<span class="d-block mb-3">
<b>1. DESCARGA DE MANUAL:</b>
En el siguiente botón podrás descargar el manual que
contiene los pasos detallados para configurar las
"Notificaciones Internas" del portal mediante la app
de Telegram.
</span>

<div class="text-center">

<a
href="/assets/img/pdf/Manual_Telegram_Admongas.pdf"
download
class="btn btn-sm btn-outline-primary">
<i class="ti ti-file-download me-1"></i>
Descarga Manual de Configuración de Notificaciones Internas
</a>

</div>

</div>

<div class="col-12">

<span class="d-block mb-2">
<b>2. ESCANEAR CÓDIGO QR:</b>
Al escanear el siguiente código QR serás dirigido al
chat de recepción de Notificaciones Internas.
</span>

<div class="text-center">

<img
src="/assets/img/telegramQR.png"
alt="Código QR de Telegram"
class="img-fluid"
style="max-width:160px;">

</div>

</div>

<div class="col-12">

<template x-if="estado === 'sin-registro'">

<div class="text-start mb-2">

<b>3. CÓDIGO DE VERIFICACIÓN:</b>



Para configurar la recepción de notificaciones
internas deberás generar tu token por primera vez.
Haz clic en el botón <b>"Generar un nuevo token"</b> para
iniciar el proceso.

</div>

</template>

<template x-if="estado === 'pendiente'">

<div>

<div class="text-start mb-3">

<b>3. CÓDIGO DE VERIFICACIÓN:</b>

El siguiente código de verificación debe ser
ingresado en el chat de recepción de
Notificaciones Internas.

</div>

<template x-if="digits.length">

<div
class="d-flex justify-content-center gap-2 mb-3">

<template
x-for="(d, i) in digits"
:key="i">

<span
class="d-flex align-items-center justify-content-center fw-bold text-dark"
style="width:40px;height:40px;border-radius:50%;background:#F2F2F2;font-size:18px;">
<span x-text="d"></span>
</span>

</template>

</div>

</template>

<template x-if="!expirado">

<div class="text-center">
<span
class="fw-semibold"

x-text="formatearTiempo()"></span>
</div>

</template>

<template x-if="expirado">

<div class="text-center text-danger fw-semibold">
El tiempo ha expirado.
</div>

</template>

<div class="text-center text-danger mt-3">
Nota: este código tiene un límite de tiempo de
2 minutos para ser utilizado, pero puedes
generar uno nuevo en caso de que expire.
</div>

</div>

</template>

<div class="d-grid mt-3">

<button
type="button"
class="btn btn-success"
@click="generar()"
:disabled="generando || (estado === 'pendiente' && segundos > 0)">

<template x-if="!generando">
<span>
<i class="ti ti-refresh me-2"></i>
<span x-text="estado === 'pendiente' && segundos > 0 ? 'En espera de verificación (restante ' + segundos + 's)' : 'Generar un nuevo token'"></span>
</span>
</template>

<template x-if="generando">
<span>
<span
class="spinner-border spinner-border-sm me-2"
aria-hidden="true"></span>
Generando...
</span>
</template>

</button>

</div>

</div>

</div>

</template>

<template x-if="!cargando && estado === 'verificado'">

<div class="text-center">

<div
class="bg-success-subtle text-success d-inline-flex align-items-center gap-2 rounded-3 px-3 py-2 mb-3">

<i class="ti ti-circle-check fs-5"></i>

<span class="fw-semibold">
¡Se ha completado la verificación!
</span>

</div>

<p class="mb-4">
Tu dispositivo se encuentra registrado y recibes las
notificaciones internas mediante la app de Telegram.
</p>

<div class="d-grid gap-2">

<button
type="button"
class="btn btn-success text-white"
@click="enviarPrueba()"
:disabled="enviando">

<template x-if="!enviando">
<span>
<i class="ti ti-send me-1"></i>
Enviar mensaje de prueba
</span>
</template>

<template x-if="enviando">
<span>
<span
class="spinner-border spinner-border-sm me-2"
aria-hidden="true"></span>
Enviando...
</span>
</template>

</button>

<button
type="button"
class="btn bg-danger-subtle text-danger"
@click="revocar()"
:disabled="revocando">

<template x-if="!revocando">
<span>
<i class="ti ti-shield-x me-2"></i>
Revocar Autorización
</span>
</template>

<template x-if="revocando">
<span>
<span
class="spinner-border spinner-border-sm me-2"
aria-hidden="true"></span>
Revocando...
</span>
</template>

</button>

</div>

</div>

</template>

</div>

</div>
</div>



</div>
</div>

<!-- ========================================================
| Columna derecha
======================================================== -->
<div class="col-lg-8">

<!-- ====================================================
| Información personal
==================================================== -->
<div class="card">

<div class="card-header">

<div class="d-flex align-items-center justify-content-between gap-3 w-100">

<div class="d-flex align-items-center gap-2">

<span
class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center"
style="width:38px;height:38px;">
<i class="ti ti-user-circle fs-5"></i>
</span>

<div>

<h5 class="card-title mb-0">
Información personal
</h5>

<small class="text-muted">
Datos generales del usuario
</small>

</div>

</div>

<button type="button" class="btn btn-outline-primary flex-shrink-0" @click="abrirModalInfoPersonal()" :disabled="guardandoInfoPersonal">
<i class="ti ti-edit me-1"></i>Actualizar
</button>

</div>

</div>


<div class="card-body">

<div class="row g-4">

<div class="col-md-6">

<label class="form-label text-muted">
Nombre completo
</label>

<div class="fw-semibold" x-text="infoPersonal.nombre || 'No registrado'"><?= htmlspecialchars(trim((string) ($user->nombre ?? '')) ?: 'No registrado', ENT_QUOTES, 'UTF-8') ?></div>

</div>


<div class="col-md-6">

<label class="form-label text-muted">
Fecha de nacimiento
</label>

<div class="fw-semibold" x-text="fechaNacimientoTexto()"><?= htmlspecialchars(formatearFecha($user->fecha_nacimiento ?? null) ?: 'No registrado', ENT_QUOTES, 'UTF-8') ?></div>

</div>


<div class="col-md-6">

<label class="form-label text-muted">
Estado civil
</label>

<div class="fw-semibold" x-text="infoPersonal.estado_civil || 'No registrado'"><?= htmlspecialchars(trim((string) ($user->estado_civil ?? '')) ?: 'No registrado', ENT_QUOTES, 'UTF-8') ?></div>

</div>


<div class="col-md-6">

<label class="form-label text-muted">
Seguro social
</label>

<div class="fw-semibold" x-text="infoPersonal.seguro_social || 'No registrado'"><?= htmlspecialchars(trim((string) ($user->seguro_social ?? '')) ?: 'No registrado', ENT_QUOTES, 'UTF-8') ?></div>

</div>


<div class="col-12">

<label class="form-label text-muted">
Domicilio
</label>

<div class="fw-semibold" x-text="infoPersonal.domicilio || 'No registrado'"><?= htmlspecialchars(trim((string) ($user->domicilio ?? '')) ?: 'No registrado', ENT_QUOTES, 'UTF-8') ?></div>

</div>

</div>

</div>

</div>


<!-- ====================================================
| Información laboral
==================================================== -->
<div class="card">

<div class="card-header">

<div class="d-flex align-items-center gap-2">

<span
class="bg-warning-subtle text-warning rounded-circle d-flex align-items-center justify-content-center"
style="width:38px;height:38px;">
<i class="ti ti-briefcase fs-5"></i>
</span>

<div>

<h5 class="card-title mb-0">
Información laboral
</h5>

<small class="text-muted">
Datos relacionados con su puesto y estación
</small>

</div>

</div>

</div>


<div class="card-body">

<div class="row g-4">

<div class="col-md-6">

<label class="form-label text-muted">
Estación
</label>

<div class="fw-semibold">

<?= htmlspecialchars(
trim((string) ($user->estacion?->nombre ?? $user->estacion?->localidad ?? '')) ?: 'No registrado'
) ?>

</div>

</div>


<div class="col-md-6">

<label class="form-label text-muted">
Puesto
</label>

<div class="fw-semibold">

<?= htmlspecialchars(
trim((string) ($user->puesto?->tipo_puesto ?? '')) ?: 'No registrado'
) ?>

</div>

</div>


<div class="col-md-6">

<label class="form-label text-muted">
Fecha de ingreso
</label>

<div class="fw-semibold">
<?php
$fechaIngreso = $user->fecha_ingreso ?? null;

if (
!empty($fechaIngreso) &&
$fechaIngreso !== '0000-00-00' &&
$fechaIngreso !== '0000-00-00 00:00:00' &&
strtotime($fechaIngreso) !== false
) {
echo htmlspecialchars(formatearFecha($fechaIngreso) ?: 'No registrado', ENT_QUOTES, 'UTF-8');
} else {
echo 'No registrado';
}
?>
</div>

</div>


<div class="col-md-6">

<label class="form-label text-muted">
Responsabilidad SGM
</label>

<div class="fw-semibold">

<?= htmlspecialchars(
trim((string) ($user->responsabilidad_sgm ?? '')) ?: 'No registrado'
) ?>

</div>

</div>

</div>

</div>

</div>


<!-- ====================================================
| Seguridad
==================================================== -->
<div class="card">

<div class="card-header">

<div class="d-flex align-items-center gap-2">

<span
class="bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center"
style="width:38px;height:38px;">
<i class="ti ti-shield-lock fs-5"></i>
</span>

<div>

<h5 class="card-title mb-0">
Seguridad
</h5>

<small class="text-muted">
Configuración de acceso a la cuenta
</small>

</div>

</div>

</div>


<div class="card-body">

<div
class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 py-2">

<div class="d-flex align-items-center gap-3">

<span
class="bg-light rounded-circle d-flex align-items-center justify-content-center"
style="width:46px;height:46px;">
<i class="ti ti-key fs-5"></i>
</span>

<div>

<h6 class="mb-1">
Contraseña
</h6>

<small class="text-muted">
Actualizar tu contraseña de acceso.
</small>

<?php if (!empty($passwordProtegida)): ?>
<small class="d-block text-success fw-semibold mt-1">Protegido</small>
<?php else: ?>
<small class="d-block text-danger mt-1">
<i class="ti ti-alert-circle me-1"></i>Tu contraseña requiere al menos 8 caracteres, mayúsculas, minúsculas, números y símbolos para ser más segura.
</small>
<?php endif; ?>

</div>

</div>


<button
type="button"
class="btn btn-outline-primary"
@click="$dispatch('abrir-modal-password')">
<i class="ti ti-key me-2"></i>
Actualizar
</button>

</div>


<hr>


<div
class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 py-2">

<div class="d-flex align-items-center gap-3">

<span
class="bg-light rounded-circle d-flex align-items-center justify-content-center"
style="width:46px;height:46px;">
<i class="ti ti-device-mobile-check fs-5"></i>
</span>

<div>

<div class="d-flex align-items-center gap-2">

<h6 class="mb-1">
Autenticación en dos pasos
</h6>


<?php if (!empty($user->two_factor_enabled)): ?>

<span
class="badge bg-success-subtle text-success">
Activa
</span>

<?php else: ?>

<span
class="badge bg-secondary-subtle text-secondary">
Inactiva
</span>

<?php endif; ?>

</div>

<small class="text-muted">
Agrega una capa adicional de seguridad.
</small>

</div>

</div>


<button type="button" class="btn btn-outline-secondary" disabled aria-disabled="true">
<i class="ti ti-clock me-1"></i>En desarrollo
</button>

</div>

</div>

</div>


<!-- ====================================================
| Firma
==================================================== -->
<div class="card">

<div class="card-header d-flex align-items-center justify-content-between gap-3">

<div class="d-flex align-items-center gap-2">

<span
class="bg-info-subtle text-info rounded-circle d-flex align-items-center justify-content-center"
style="width:38px;height:38px;">
<i class="ti ti-signature fs-5"></i>
</span>

<div>

<h5 class="card-title mb-0">
Firma
</h5>

<small class="text-muted">
Firma asociada al usuario
</small>

</div>

</div>

<div class="d-flex align-items-center gap-2">
<?php if ($firmaPerfilUrl !== ''): ?>
<button type="button" class="btn btn-outline-danger" @click="eliminarFirmaPerfil()" x-show="!editorFirmaAbierto" :disabled="guardandoFirma || eliminandoFirma"><i class="ti" :class="eliminandoFirma ? 'ti-loader-2 ti-spin me-1' : 'ti-trash me-1'"></i><span x-text="eliminandoFirma ? 'Eliminando...' : 'Eliminar firma'"></span></button>
<?php endif; ?>
<button type="button" class="btn" :class="editorFirmaAbierto ? 'bg-danger-subtle text-danger' : 'btn-outline-primary'" @click="alternarEditorFirma()" :disabled="guardandoFirma || eliminandoFirma">
<i class="ti me-1" :class="editorFirmaAbierto ? 'ti-x' : 'ti-signature fs-4'"></i><span x-text="editorFirmaAbierto ? 'Cancelar' : '<?= $firmaPerfilUrl !== '' ? 'Actualizar firma' : 'Nueva firma' ?>'"></span>
</button>
</div>

</div>


<div class="card-body">

<div class="border border-dashed rounded-3 p-4 text-center" x-show="!editorFirmaAbierto">
<?php if ($firmaPerfilUrl !== ''): ?>
<img src="<?= $escapePerfil($firmaPerfilUrl) ?>" alt="Firma registrada" class="img-fluid mb-3" style="max-height:200px;max-width:100%;object-fit:contain">
<?php else: ?>
<div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width:58px;height:58px"><i class="ti ti-signature fs-7 text-muted"></i></div>
<h6 class="mb-1">Sin firma registrada</h6>
<p class="text-muted mb-3">Este usuario aún no tiene una firma asociada.</p>
<?php endif; ?>
</div>

<div x-show="editorFirmaAbierto" x-cloak>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
<div class="btn-group" role="group" aria-label="Forma de agregar firma">
<button type="button" class="btn" :class="modoFirma === 'pad' ? 'btn-primary' : 'btn-outline-primary'" @click="seleccionarModoFirma('pad')"><i class="ti ti-pencil me-1"></i>Dibujar firma</button>
<button type="button" class="btn" :class="modoFirma === 'archivo' ? 'btn-primary' : 'btn-outline-primary'" @click="seleccionarModoFirma('archivo')"><i class="ti ti-upload me-1"></i>Subir imagen</button>
</div>
<button type="button" class="btn btn-success" @click="guardarFirma()" :disabled="guardandoFirma"><span x-show="!guardandoFirma"><i class="ti ti-check me-1"></i>Guardar firma</span><span x-show="guardandoFirma"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Guardando...</span></button>
</div>

<div x-show="modoFirma === 'pad'" x-cloak>
<div id="perfil-signature-pad" class="signature-pad-wrapper border border-dashed rounded-3 overflow-hidden" style="cursor:crosshair;touch-action:none">
<div class="signature-pad--body">
<canvas x-ref="canvasFirma" aria-label="Área para dibujar la firma" style="width:100%;height:250px;display:block;touch-action:none"></canvas>
</div>
</div>
<button type="button" class="btn bg-danger-subtle text-danger w-100 rounded-0 mt-2" @click="limpiarFirma()"><i class="ti ti-eraser me-1"></i>Limpiar firma</button>
</div>

<div x-show="modoFirma === 'archivo'" x-cloak>
<label for="perfil-archivo-firma" class="form-label mt-3 mb-2">Imagen de firma <span class="small">(PNG, JPG o WEBP; máximo 3 MB)</span>:</label>
<input id="perfil-archivo-firma" type="file" accept="image/png,image/jpeg,image/webp" class="form-control" :class="{'is-invalid': errorFirma}" @change="seleccionarArchivo($event)" :disabled="guardandoFirma">
<div class="invalid-feedback" x-text="errorFirma"></div>
<template x-if="vistaPreviaFirma"><img :src="vistaPreviaFirma" alt="Vista previa de la firma" class="img-fluid mt-3 border rounded p-2" style="max-height:160px"></template>
</div>

<div class="alert alert-danger mt-3 mb-0" x-show="errorFirma" x-text="errorFirma" role="alert"></div>
</div>

</div>

</div>

</div>

</div>

</div>

<div class="modal fade" id="modalFotoPerfilCrop" tabindex="-1" aria-labelledby="modalFotoPerfilCropLabel" aria-hidden="true" x-ref="modalFotoCropper" @shown.bs.modal="dibujarFotoCrop()" @hidden.bs.modal="liberarFotoCrop()">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-primary">
<h5 class="modal-title text-white" id="modalFotoPerfilCropLabel"><i class="ti ti-crop me-2"></i>Ajustar foto de perfil</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar" :disabled="guardandoFotoPerfil"></button>
</div>
<div class="modal-body">
<p class="text-muted text-center mb-3">Arrastra la imagen para encuadrarla y ajusta el zoom antes de guardar.</p>
<div class="rounded-circle overflow-hidden border bg-light mx-auto" style="width:min(100%, 320px);aspect-ratio:1;touch-action:none">
<canvas x-ref="canvasFotoCrop" class="d-block w-100 h-100" aria-label="Vista previa del recorte de la foto" style="cursor:grab;touch-action:none" @pointerdown="iniciarArrastreFotoCrop($event)" @pointermove="moverFotoCrop($event)" @pointerup="terminarArrastreFotoCrop()" @pointercancel="terminarArrastreFotoCrop()" @lostpointercapture="terminarArrastreFotoCrop()"></canvas>
</div>
<div class="mt-3">
<label for="perfil-foto-zoom" class="form-label d-flex justify-content-between"><span>Zoom</span><span x-text="`${Math.round(zoomFotoCrop * 100)}%`"></span></label>
<input id="perfil-foto-zoom" type="range" class="form-range" min="1" max="3" step="0.01" x-model.number="zoomFotoCrop" @input="cambiarZoomFotoCrop()" :disabled="guardandoFotoPerfil">
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" @click="cerrarCropFoto()" :disabled="guardandoFotoPerfil"><i class="ti ti-x me-1"></i>Cancelar</button>
<button type="button" class="btn btn-success" @click="guardarFotoPerfilRecortada()" :disabled="guardandoFotoPerfil || !imagenFotoCrop"><span x-show="!guardandoFotoPerfil"><i class="ti ti-check me-1"></i>Guardar foto</span><span x-show="guardandoFotoPerfil"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Guardando...</span></button>
</div>
</div>
</div>
</div>

<div class="modal fade" id="modalEditarInformacionPersonal" tabindex="-1" aria-labelledby="modalEditarInformacionPersonalLabel" aria-hidden="true" x-ref="modalInfoPersonal">
<div class="modal-dialog modal-dialog-centered modal-lg">
<div class="modal-content">
<div class="modal-header bg-primary">
<h5 class="modal-title text-white" id="modalEditarInformacionPersonalLabel"><i class="ti ti-user-edit me-2"></i>Editar información personal</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar" :disabled="guardandoInfoPersonal"></button>
</div>
<form @submit.prevent="guardarInfoPersonal()" novalidate>
<div class="modal-body">
<div class="row g-3">
<div class="col-12">
<label for="perfil-info-nombre" class="form-label mb-1"><span class="text-danger">*</span> Nombre completo:</label>
<input id="perfil-info-nombre" type="text" class="form-control" :class="{'is-invalid': erroresInfoPersonal.nombre}" x-model.trim="formInfoPersonal.nombre" maxlength="190" autocomplete="name" :disabled="guardandoInfoPersonal">
<div class="invalid-feedback" x-text="erroresInfoPersonal.nombre"></div>
</div>
<div class="col-md-12">
<label for="perfil-info-fecha-nacimiento" class="form-label mb-1">Fecha de nacimiento:</label>
<input id="perfil-info-fecha-nacimiento" type="date" class="form-control" :class="{'is-invalid': erroresInfoPersonal.fecha_nacimiento}" x-model="formInfoPersonal.fecha_nacimiento" :disabled="guardandoInfoPersonal">
<div class="invalid-feedback" x-text="erroresInfoPersonal.fecha_nacimiento"></div>
</div>
<div class="col-md-6">
<label for="perfil-info-estado-civil" class="form-label mb-1">Estado civil:</label>
<input id="perfil-info-estado-civil" type="text" class="form-control" :class="{'is-invalid': erroresInfoPersonal.estado_civil}" x-model.trim="formInfoPersonal.estado_civil" maxlength="50" :disabled="guardandoInfoPersonal">
<div class="invalid-feedback" x-text="erroresInfoPersonal.estado_civil"></div>
</div>
<div class="col-md-6">
<label for="perfil-info-seguro-social" class="form-label mb-1">Seguro social:</label>
<input id="perfil-info-seguro-social" type="text" class="form-control" :class="{'is-invalid': erroresInfoPersonal.seguro_social}" x-model.trim="formInfoPersonal.seguro_social" maxlength="30" :disabled="guardandoInfoPersonal">
<div class="invalid-feedback" x-text="erroresInfoPersonal.seguro_social"></div>
</div>
<div class="col-12">
<label for="perfil-info-domicilio" class="form-label mb-1">Domicilio:</label>
<textarea id="perfil-info-domicilio" class="form-control" :class="{'is-invalid': erroresInfoPersonal.domicilio}" x-model.trim="formInfoPersonal.domicilio" maxlength="500" rows="3" :disabled="guardandoInfoPersonal"></textarea>
<div class="invalid-feedback" x-text="erroresInfoPersonal.domicilio"></div>
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal" :disabled="guardandoInfoPersonal"><i class="ti ti-x me-1"></i>Cancelar</button>
<button type="submit" class="btn btn-success" :disabled="guardandoInfoPersonal"><span x-show="!guardandoInfoPersonal"><i class="ti ti-check me-1"></i>Actualizar</span><span x-show="guardandoInfoPersonal"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Guardando...</span></button>
</div>
</form>
</div>
</div>
</div>

<div class="modal fade" id="modalEditarPerfil" tabindex="-1" aria-labelledby="modalEditarPerfilLabel" aria-hidden="true" x-ref="modalPerfil">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-primary">
<h5 class="modal-title text-white" id="modalEditarPerfilLabel"><i class="ti ti-user-edit me-2"></i>Editar perfil</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar" :disabled="guardandoPerfil"></button>
</div>
<form @submit.prevent="guardarPerfil()" novalidate>
<div class="modal-body">
<div class="mb-3">
<label for="perfil-email" class="form-label mb-1">Correo electrónico:</label>
<input id="perfil-email" type="email" class="form-control" :class="{'is-invalid': erroresPerfil.email}" x-model.trim="formPerfil.email" maxlength="190" autocomplete="email" :disabled="guardandoPerfil">
<div class="invalid-feedback" x-text="erroresPerfil.email"></div>
</div>
<div class="mb-3">
<label for="perfil-telefono" class="form-label mb-1">Teléfono:</label>
<input id="perfil-telefono" type="tel" class="form-control" :class="{'is-invalid': erroresPerfil.telefono}" x-model.trim="formPerfil.telefono" maxlength="25" autocomplete="tel" :disabled="guardandoPerfil">
<div class="invalid-feedback" x-text="erroresPerfil.telefono"></div>
</div>
<div>
<label for="perfil-usuario" class="form-label mb-1"><span>*</span> Usuario:</label>
<input id="perfil-usuario" type="text" class="form-control" :class="{'is-invalid': erroresPerfil.usuario}" x-model.trim="formPerfil.usuario" minlength="3" maxlength="50" autocomplete="username" :disabled="guardandoPerfil" required>
<div class="invalid-feedback" x-text="erroresPerfil.usuario"></div>
<div class="form-text">Si cambias tu usuario, tendrás que iniciar sesión de nuevo.</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal" :disabled="guardandoPerfil"><i class="ti ti-x me-1"></i>Cancelar</button>
<button type="submit" class="btn btn-success" :disabled="guardandoPerfil"><span x-show="!guardandoPerfil"><i class="ti ti-check me-1"></i>Actualizar</span><span x-show="guardandoPerfil"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Guardando...</span></button>
</div>
</form>
</div>
</div>
</div>

<div
class="modal fade"
id="modalCambiarPassword"
tabindex="-1"
aria-labelledby="modalCambiarPasswordLabel"
aria-hidden="true"
x-data="cambiarPassword()"
x-on:abrir-modal-password.window="abrirModal()"
x-ref="modal">
<div class="modal-dialog modal-dialog-centered">

<div class="modal-content">

<!-- Header -->
<div class="modal-header bg-primary">

<div class="d-flex align-items-center gap-3">

<span
class="bg-light-subtle text-primary rounded-circle d-flex align-items-center justify-content-center"
style="width:42px;height:42px;">
<i class="ti ti-key fs-5"></i>
</span>

<div>
<h5
class="modal-title mb-0 text-white"
id="modalCambiarPasswordLabel">
Cambiar contraseña
</h5>

<small class="text-white">
Actualiza la contraseña de acceso a tu cuenta.
</small>
</div>

</div>

<button
type="button"
class="btn-close btn-close-white"
data-bs-dismiss="modal"
aria-label="Cerrar"
:disabled="guardando"></button>

</div>

<form @submit.prevent="guardar" novalidate>

<div class="modal-body">

<!-- Contraseña actual -->
<div class="mb-4">

<label
for="password_actual"
class="form-label mb-1">
<span>*</span> Contraseña actual:
</label>

<div class="input-group">

<input
:type="mostrarActual ? 'text' : 'password'"
id="password_actual"
class="form-control"
:class="{
'is-invalid': errores.password_actual
}"
x-model="form.password_actual"
autocomplete="current-password"
placeholder="Ingresa tu contraseña actual"
:disabled="guardando"
required
@input="errores.password_actual = ''">

<button
type="button"
class="btn btn-outline-secondary"
@click="mostrarActual = !mostrarActual"
tabindex="-1">
<i
class="ti"
:class="
mostrarActual
? 'ti-eye-off'
: 'ti-eye'
"></i>
</button>

</div>

<div
class="invalid-feedback d-block"
x-show="errores.password_actual"
x-text="errores.password_actual"></div>

<div class="form-text mt-2" x-show="form.password_actual.length">
Seguridad de la contraseña actual:
<span class="fw-semibold" :class="evaluarFortaleza(form.password_actual).textClass" x-text="evaluarFortaleza(form.password_actual).label"></span>
</div>

</div>


<!-- Nueva contraseña -->
<div class="mb-4">

<label
for="password_nueva"
class="form-label mb-1">
<span>*</span> Nueva contraseña:
</label>

<div class="input-group">

<input
:type="mostrarNueva ? 'text' : 'password'"
id="password_nueva"
class="form-control"
:class="{
'is-invalid': errores.password_nueva
}"
x-model="form.password_nueva"
autocomplete="new-password"
placeholder="Mínimo 8 caracteres"
minlength="8"
:disabled="guardando"
required
@input="errores.password_nueva = ''">

<button
type="button"
class="btn btn-outline-secondary"
@click="mostrarNueva = !mostrarNueva"
tabindex="-1">
<i
class="ti"
:class="
mostrarNueva
? 'ti-eye-off'
: 'ti-eye'
"></i>
</button>

</div>

<div
class="invalid-feedback d-block"
x-show="errores.password_nueva"
x-text="errores.password_nueva"></div>

<div class="form-text">
Para que sea muy fuerte, usa al menos 8 caracteres, mayúsculas, minúsculas, números y símbolos.
</div>
<div class="mt-2" x-show="form.password_nueva.length" aria-live="polite">
<div class="progress" style="height:6px" role="progressbar" :aria-valuenow="evaluarFortaleza(form.password_nueva).score" aria-valuemin="0" aria-valuemax="100">
<div class="progress-bar" :style="'width:' + evaluarFortaleza(form.password_nueva).score + '%;background-color:' + evaluarFortaleza(form.password_nueva).color"></div>
</div>
<small class="d-block mt-1" :class="evaluarFortaleza(form.password_nueva).textClass">Seguridad: <span class="fw-semibold" x-text="evaluarFortaleza(form.password_nueva).label"></span></small>
</div>

</div>


<!-- Confirmar contraseña -->
<div>

<label
for="password_confirmacion"
class="form-label mb-1">
<span>*</span> Confirmar nueva contraseña:
</label>

<div class="input-group">

<input
:type="mostrarConfirmacion ? 'text' : 'password'"
id="password_confirmacion"
class="form-control"
:class="{
'is-invalid': errores.password_confirmacion
}"
x-model="form.password_confirmacion"
autocomplete="new-password"
placeholder="Repite la nueva contraseña"
minlength="8"
:disabled="guardando"
required
@input="errores.password_confirmacion = ''">

<button
type="button"
class="btn btn-outline-secondary"
@click="
mostrarConfirmacion =
!mostrarConfirmacion
"
tabindex="-1">
<i
class="ti"
:class="
mostrarConfirmacion
? 'ti-eye-off'
: 'ti-eye'
"></i>
</button>

</div>

<div
class="invalid-feedback d-block"
x-show="errores.password_confirmacion"
x-text="errores.password_confirmacion"></div>

</div>


<!-- Error general -->
<div
class="alert alert-danger mt-4 mb-0"
role="alert"
x-show="errorGeneral"
x-transition>
<div class="d-flex align-items-start gap-2">

<i class="ti ti-alert-circle fs-5"></i>

<span x-text="errorGeneral"></span>

</div>
</div>

</div>


<!-- Footer -->
<div class="modal-footer">

<button
type="button"
class="btn bg-danger-subtle text-danger"
data-bs-dismiss="modal"
:disabled="guardando">
<i class="ti ti-x me-1"></i>Cancelar
</button>

<button
type="submit"
class="btn btn-success"
:disabled="guardando">
<template x-if="!guardando">
<span class="d-inline-flex align-items-center">
<i class="ti ti-check me-1"></i>
Actualizar
</span>
</template>

<template x-if="guardando">
<span class="d-inline-flex align-items-center">
<span
class="spinner-border spinner-border-sm me-1"
aria-hidden="true"></span>

Actualizando...
</span>
</template>
</button>

</div>

</form>

</div>

</div>

</div>

</div>
