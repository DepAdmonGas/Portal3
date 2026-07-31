<?php

namespace App\Services;

use App\Core\Logger;
use App\Core\PasswordService;
use App\Core\Request;
use App\Core\Session;

use App\Core\TwoFactorService;

use App\DTO\LoginResult;

use App\Repositories\UsuarioRepository;
use App\Repositories\EstacionRepository;

use App\Services\SessionService;

class AuthenticationService
{
public function __construct(
private UsuarioRepository $usuarioRepository,
private EstacionRepository $estacionRepository,
private TokenService $tokenService
) {}

/**
 * Iniciar sesión.
 */
public function login(
?string $usuario,
?string $password,
?string $twoFactorCode
): LoginResult {

/*
* Validación básica
*/
if (!$usuario || !$password) {

return new LoginResult(
type: 'error',
message: 'Usuario y contraseña son obligatorios',
status: 401
);
}

/*
* Buscar usuario
*/
$user = $this->usuarioRepository
->findActiveByUsername($usuario);

/*
* Validar contraseña
*/
$isValidPassword = PasswordService::verify(
$password,
$user?->password
);

if (!$user || !$isValidPassword) {

Logger::info(
'Login fallido',
[
'usuario' => $usuario,
'ip' => Request::ip()
]
);

return new LoginResult(
type: 'error',
message: 'Credenciales inválidas',
status: 401
);
}

/*
* Password legacy
*/
if (PasswordService::isLegacy($user->password)) {

Logger::warning(
'Usuario con contraseña no hasheada detectado',
[
'user_id' => $user->id,
'ip' => Request::ip()
]
);
}

/*
* Segundo factor
*/
$status = TwoFactorService::validate(
$user,
$twoFactorCode
);

if ($status === TwoFactorService::REQUIRED) {

return new LoginResult(
type: 'two_factor_required',
message: 'Ingrese el código de autenticación',
data: [
'requires_2fa' => true
]
);
}

if ($status === TwoFactorService::INVALID) {

Logger::warning(
'2FA inválido',
[
'user_id' => $user->id,
'ip' => Request::ip()
]
);

return new LoginResult(
type: 'error',
message: 'Código inválido',
status: 401
);
}

/*
* Obtener estación
*/
$estacion = $this->estacionRepository
->findById($user->id_gas);

/*
* Crear sesión
*/
SessionService::start([
'id'               => $user->id,
'usuario'          => $user->usuario,
'nombre'           => $user->nombre,
'id_estacion'      => $user->id_gas,
'nombre_estacion'  => $estacion?->nombre,
'multiestacion'    => MultiestacionService::isEnabled($user)
]);

/*
* Cargar módulos del usuario
*/
ModuloService::guardarEnSesion($user->id);

ModuloDptoOperativoService::guardarEnSesion($user->id);

/*
* Emitir JWT
*/
$accessToken = $this->tokenService
->issue($user);

Logger::info(
'Login exitoso',
[
'user_id' => $user->id,
'ip' => Request::ip()
]
);

return new LoginResult(
type: 'success',
message: 'Login exitoso',
data: [
'token' => $accessToken
]
);
}

/**
 * Cerrar sesión.
 */
public function logout(): void
{
Logger::info(
'Logout',
[
'user_id' => Session::get('usuario')['id'] ?? null,
'ip' => Request::ip()
]
);

$this->tokenService->forgetCookies();

SessionService::logout();
}
}
