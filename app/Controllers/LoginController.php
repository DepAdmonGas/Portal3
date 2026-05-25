<?php
namespace App\Controllers;
use App\Models\Usuario;
use App\Models\Estacion;
use App\Core\JWTService;
use App\Core\View;
use App\Services\ModuloService;
use App\Services\ModuloDptoOperativoService;
use App\Core\Session;
use App\Core\PasswordValidator;
use App\Core\Logger;
use App\Core\TwoFactorAuth;
use App\Middleware\RateLimitMiddleware;

class LoginController{


public function index(){

$data = [
'title' => 'Login Portal3',
'scripts' => []
];

View::render('login/index', $data,'auth');

}

public function login(){
header('Content-Type: application/json');

// Rate limiting para prevenir ataques de fuerza bruta
if (!RateLimitMiddleware::check('login')) {
return; // Ya responde con código 429
}

$data = json_decode(file_get_contents('php://input'), true);

$usuario  = $data['usuario'] ?? '';
$password = $data['password'] ?? '';
$twoFactorCode = $data['two_factor_code'] ?? null; // SECURITY: BAJO #32 - código 2FA
// SECURITY: BAJO #33 - Usar TTL del JWTService (1 hora en vez de 24)
$accessTokenTTL = JWTService::ACCESS_TOKEN_TTL;

// Validación backend (obligatoria)
if (!$usuario || !$password) {
echo json_encode([
'type' => 'error',
'message' => 'Usuario y contraseña son obligatorios'
]);
return;
}

// Buscar usuario con Eloquent PRIMERO (necesario para el log de contraseña débil)
$user = Usuario::activo()->where('usuario', $usuario)->first();

// ============================================================
// SECURITY: Validación de fortaleza de contraseña (BAJO)
// Loggear contraseñas débiles para auditoría sin bloquear login
// (usuarios existentes pueden tener contraseñas débiles)
// ============================================================
$passwordValidation = PasswordValidator::validate($password);

if (!$passwordValidation['valid']) {
Logger::getLogger()->warning('Intento de login con contraseña débil', [
'user_id' => $user->id ?? null,
'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
'timestamp' => date('Y-m-d H:i:s')
// ELIMINADO: usuario, password_score, password_issues (seguridad)
]);
}

// ============================================================
// SECURITY: Verificación de contraseña
// Soporta tanto hashes bcrypt (nuevo) como texto plano (legacy)
// NOTA: En producción, todas las contraseñas deben estar hasheadas
// ============================================================
$isValidPassword = false;

if ($user) {
$storedPassword = $user->password;

// Verificar si está hasheada con bcrypt (comienza con $2y$, $2a$, $2b$)
if (preg_match('/^\$2[ayb]\$\d{2}\$/', $storedPassword)) {
$isValidPassword = password_verify($password, $storedPassword);
} else {
// Legacy: comparación en texto plano (temporal - deprecated)
$isValidPassword = ($password === $storedPassword);

// Advertir en logs si sigue usando texto plano
if ($isValidPassword) {
Logger::getLogger()->warning('Usuario con contraseña no hasheada detectado', [
'user_id' => $user->id,
'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
'timestamp' => date('Y-m-d H:i:s')
// ELIMINADO: usuario (seguridad)
]);
}
}
}

if (!$user || !$isValidPassword) {
// Loggear intento de login fallido
Logger::getLogger()->info('Login fallido - credenciales inválidas', [
'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100),
'timestamp' => date('Y-m-d H:i:s')
// ELIMINADO: usuario (previene enumeration de usuarios)
]);

echo json_encode([
'type' => 'error',
'message' => 'Credenciales inválidas'
]);
return;
}

// ============================================================
// SECURITY: Verificación de 2FA (BAJO #32)
// Si el usuario tiene 2FA habilitado, validar código TOTP
// ============================================================
if ($user->hasTwoFactorEnabled()) {
// Si no se proporcionó código 2FA, solicitarlo
if (!$twoFactorCode) {
Logger::getLogger()->info('Login requiere 2FA', [
'user_id' => $user->id,
'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
// ELIMINADO: usuario (seguridad)
]);

echo json_encode([
'type' => 'two_factor_required',
'message' => 'Ingrese el código de autenticación de dos factores',
'requires_2fa' => true
]);
return;
}

// Verificar código TOTP
if (!$user->verifyTwoFactorCode($twoFactorCode)) {
// Loggear intento de 2FA fallido
Logger::getLogger()->warning('Intento de login 2FA fallido', [
'user_id' => $user->id,
'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
// ELIMINADO: usuario (seguridad)
]);

echo json_encode([
'type' => 'error',
'message' => 'Código de autenticación inválido'
]);
return;
}

// Loggear autenticación 2FA exitosa
Logger::getLogger()->info('Login 2FA exitoso', [
'user_id' => $user->id,
'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
// ELIMINADO: usuario (seguridad)
]);
}

$multiestacion = ($user->id_gas == 8);

// SECURITY: BAJO #33 - Crear access token con TTL reducido (1 hora)
$token = JWTService::createAccessToken([
'id' => $user->id,
'nombre' => $user->nombre
]);

// SECURITY: BAJO #33 - Crear refresh token (7 días)
$refreshToken = JWTService::createRefreshToken([
'id' => $user->id,
'nombre' => $user->nombre
]);

$estacion = Estacion::find($user->id_gas);
$nombreEstacion = $estacion->nombre ?? '';

// Guardar sesión
Session::set('usuario', [
'id' => $user->id,
'nombre' => $user->nombre,
'nombre_estacion' => $nombreEstacion,   
'id_estacion' => $user->id_gas,
'razonsocial' => 'Todas las estaciones',
'multiestacion' => $multiestacion
]);

// Control de tiempo
Session::set('LAST_ACTIVITY', time());

// SECURITY: Cookie con secure flag solo si está en HTTPS+Y producción (Vulnerabilidad #4)
$isSecure = ($_ENV['APP_ENV'] ?? 'dev') === 'prod' && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

// Access token cookie (1 hora de duración)
setcookie(
'token',
$token,
[
'expires'  => time() + $accessTokenTTL,
'path'     => '/',
'secure'   => $isSecure,  // Solo true en prod HTTPS
'httponly' => true,
'samesite' => 'Strict'          // Mayor protección CSRF
]
);

// Refresh token cookie (7 días de duración)
setcookie(
'refresh_token',
$refreshToken,
[
'expires'  => time() + JWTService::REFRESH_TOKEN_TTL,
'path'     => '/',
'secure'   => $isSecure,
'httponly' => true,
'samesite' => 'Strict'
]
);

ModuloService::guardarEnSesion($user->id);
ModuloDptoOperativoService::guardarEnSesion($user->id);

// Loggear login exitoso
Logger::getLogger()->info('Login exitoso', [
'user_id' => $user->id,
'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
'two_factor_used' => $user->hasTwoFactorEnabled(),
'timestamp' => date('Y-m-d H:i:s')
// ELIMINADO: usuario (seguridad)
]);

// Login correcto
echo json_encode([
'type' => 'success',
'message' => 'Login exitoso',
'token' => $token
]);
}

/**
* Refresca el access token usando el refresh token
* 
* SECURITY: BAJO #33 - Endpoint para renovar access token sin re-autenticar
*/
public function refreshToken()
{
header('Content-Type: application/json');

$refreshToken = $_COOKIE['refresh_token'] ?? null;

if (!$refreshToken) {
echo json_encode([
'type' => 'error',
'message' => 'Token de renovación no encontrado'
]);
return;
}

// Validar que es un refresh token
if (!JWTService::isRefreshToken($refreshToken)) {
Logger::getLogger()->warning('Intento de refresh con token inválido', [
'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);

echo json_encode([
'type' => 'error',
'message' => 'Token de renovación inválido'
]);
return;
}

try {
$payload = JWTService::validate($refreshToken);

// Obtener usuario
$user = Usuario::find($payload->sub);

if (!$user || $user->estatus !== 0) {
echo json_encode([
'type' => 'error',
'message' => 'Usuario no encontrado o inactivo'
]);
return;
}

// Crear nuevo access token
$newAccessToken = JWTService::createAccessToken([
'id' => $user->id,
'nombre' => $user->nombre
]);

// SECURITY: Cookie con secure flag solo si está en HTTPS+Y producción
$isSecure = ($_ENV['APP_ENV'] ?? 'dev') === 'prod' && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

// Actualizar cookie del access token
setcookie(
'token',
$newAccessToken,
[
'expires'  => time() + JWTService::ACCESS_TOKEN_TTL,
'path'     => '/',
'secure'   => $isSecure,
'httponly' => true,
'samesite' => 'Strict'
]
);

Logger::getLogger()->info('Token refrescado exitosamente', [
'user_id' => $user->id,
'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);

echo json_encode([
'type' => 'success',
'message' => 'Token refrescado',
'token' => $newAccessToken
]);

} catch (\Exception $e) {
Logger::getLogger()->warning('Refresh token expirado o inválido', [
'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
'timestamp' => date('Y-m-d H:i:s')
// ELIMINADO: $e->getMessage() (nunca exponer excepciones en producción)
]);

echo json_encode([
'type' => 'error',
'message' => 'Token de renovación expirado. Inicie sesión nuevamente.'
]);
}
}

}