<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Core\Auth;
use App\Core\PasswordService;
use App\Core\PasswordValidator;
use App\Services\PerfilFotoService;
use Throwable;

use App\Models\Usuario;

class PerfilController extends BaseController
{

    /** Entrega la foto de perfil de un autor de comentario a usuarios autenticados. */
    public function avatarComentario(int $id): void
    {
        $usuario = $id > 0 ? Usuario::query()->find($id) : null;
        $url = PerfilFotoService::url($usuario);

        header('Cache-Control: private, no-store, max-age=0');
        header('Location: ' . $url, true, 302);
        exit;
    }

    public function index()
    {
        $title = 'Perfil de usuario';
        $usuarioActual = Auth::user();
        $passwordActual = (string) ($usuarioActual?->password ?? '');
        $passwordProtegida = $passwordActual !== ''
            && PasswordValidator::validate($passwordActual)['valid'];

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,
            'filtro_usuario' => $this->filtro_usuario,
            'passwordProtegida' => $passwordProtegida,
            'fotoPerfilUrl' => PerfilFotoService::url($usuarioActual),
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/signature_pad/docs/js/signature_pad.umd.min.js',
                '/js/perfil/index.actions.init.js?v=' . time()
            ],
            'help' => false
        ];

        View::render('perfil/index', $data, 'main');
    }

    public function cambiarPassword()
    {
        try {

            $usuarioId = $this->userId();

            if (!$usuarioId) {

                JsonResponse::error(
                    'No fue posible identificar al usuario autenticado.'
                );
            }


            $passwordActual = trim(
                (string) Request::jsonInput('password_actual', '')
            );

            $passwordNueva = (string) Request::jsonInput(
                'password_nueva',
                ''
            );

            $passwordConfirmacion = (string) Request::jsonInput(
                'password_confirmacion',
                ''
            );

            if (
                $passwordActual === '' ||
                $passwordNueva === '' ||
                $passwordConfirmacion === ''
            ) {

                JsonResponse::error(
                    'Todos los campos son obligatorios.'
                );
            }

            $validacionPassword = PasswordValidator::validate($passwordNueva);
            if (!$validacionPassword['valid']) {
                JsonResponse::validation(
                    'La nueva contraseña debe ser segura.',
                    ['password_nueva' => $validacionPassword['errors']]
                );
            }

            if ($passwordNueva !== $passwordConfirmacion) {

                JsonResponse::error(
                    'La confirmación de la contraseña no coincide.'
                );
            }

            $usuario = Usuario::query()
                ->find($usuarioId);

            if (!$usuario) {

                JsonResponse::error(
                    'Usuario no encontrado.'
                );
            }

            if (!PasswordService::verify($passwordActual, $usuario->password)) {

                JsonResponse::error(
                    'La contraseña actual es incorrecta.'
                );
            }


            if (PasswordService::verify($passwordNueva, $usuario->password)) {

                JsonResponse::error(
                    'La nueva contraseña debe ser diferente a la contraseña actual.'
                );
            }

            /*
            $appEnv = strtolower(trim((string) ($_ENV['APP_ENV'] ?? (getenv('APP_ENV') ?: 'prod'))));
            $usuario->password = $appEnv === 'local'
                ? $passwordNueva
                : PasswordService::hash($passwordNueva);
            */

            $usuario->password = $passwordNueva;
            $usuario->save();

            JsonResponse::success(
                'La contraseña se actualizó correctamente. Inicia sesión nuevamente.',
                ['logout_required' => true]
            );
        } catch (Throwable $e) {


            JsonResponse::error(
                'Ocurrió un error al actualizar la contraseña.'
            );
        }
    }

    public function actualizarDatos()
    {
        try {
            $usuarioId = $this->userId();
            $nombreUsuario = trim((string) Request::jsonInput('usuario', ''));
            $email = trim((string) Request::jsonInput('email', ''));
            $telefono = trim((string) Request::jsonInput('telefono', ''));

            $errores = [];
            $longitudUsuario = function_exists('mb_strlen') ? mb_strlen($nombreUsuario, 'UTF-8') : strlen($nombreUsuario);
            if ($nombreUsuario === '' || $longitudUsuario < 3 || $longitudUsuario > 50 || !preg_match('/^[\pL\pN._-]+$/u', $nombreUsuario)) {
                $errores['usuario'] = 'Escribe un usuario de 3 a 50 caracteres; utiliza letras, números, punto, guion o guion bajo.';
            } elseif (Usuario::query()->where('usuario', $nombreUsuario)->where('id', '!=', $usuarioId)->exists()) {
                $errores['usuario'] = 'Ese nombre de usuario ya está registrado.';
            }

            $longitudEmail = function_exists('mb_strlen') ? mb_strlen($email, 'UTF-8') : strlen($email);
            if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || $longitudEmail > 190)) {
                $errores['email'] = 'Escribe un correo electrónico válido.';
            }
            if ($telefono !== '' && (!preg_match('/^[0-9+() .-]{7,25}$/', $telefono) || strlen(preg_replace('/\D/', '', $telefono)) < 7)) {
                $errores['telefono'] = 'Escribe un teléfono válido de al menos 7 dígitos.';
            }
            if ($errores) {
                JsonResponse::validation('Revisa los datos señalados.', $errores);
            }

            $usuario = Usuario::query()->find($usuarioId);
            if (!$usuario) {
                JsonResponse::notFound('No se encontró el usuario autenticado.');
            }

            $cambioUsuario = (string) $usuario->usuario !== $nombreUsuario;
            $usuario->usuario = $nombreUsuario;
            $usuario->email = $email !== '' ? $email : null;
            $usuario->telefono = $telefono !== '' ? $telefono : null;
            $usuario->save();

            JsonResponse::success(
                $cambioUsuario
                    ? 'Perfil actualizado. Inicia sesión nuevamente con tu nuevo usuario.'
                    : 'Perfil actualizado correctamente.',
                ['logout_required' => $cambioUsuario]
            );
        } catch (Throwable $e) {
            JsonResponse::error('No fue posible actualizar los datos del perfil.', 500);
        }
    }

    public function actualizarInformacionPersonal(): void
    {
        try {
            $usuario = Usuario::query()->find($this->userId());
            if (!$usuario) {
                JsonResponse::notFound('No se encontró el usuario autenticado.');
            }

            $nombre = trim((string) Request::jsonInput('nombre', ''));
            $fechaNacimiento = trim((string) Request::jsonInput('fecha_nacimiento', ''));
            $estadoCivil = trim((string) Request::jsonInput('estado_civil', ''));
            $seguroSocial = trim((string) Request::jsonInput('seguro_social', ''));
            $domicilio = trim((string) Request::jsonInput('domicilio', ''));
            $longitud = static fn (string $valor): int => function_exists('mb_strlen') ? mb_strlen($valor, 'UTF-8') : strlen($valor);

            $errores = [];
            if ($nombre === '' || $longitud($nombre) > 190) {
                $errores['nombre'] = $nombre === ''
                    ? 'El nombre completo es obligatorio.'
                    : 'El nombre completo no puede exceder 190 caracteres.';
            }

            if ($fechaNacimiento !== '') {
                $fechaValida = \DateTimeImmutable::createFromFormat('!Y-m-d', $fechaNacimiento);
                $erroresFecha = \DateTimeImmutable::getLastErrors();
                if (!$fechaValida || ($erroresFecha !== false && ($erroresFecha['warning_count'] > 0 || $erroresFecha['error_count'] > 0)) || $fechaValida->format('Y-m-d') !== $fechaNacimiento) {
                    $errores['fecha_nacimiento'] = 'Selecciona una fecha de nacimiento válida.';
                }
            }
            if ($longitud($estadoCivil) > 50) {
                $errores['estado_civil'] = 'El estado civil no puede exceder 50 caracteres.';
            }
            if ($longitud($seguroSocial) > 30) {
                $errores['seguro_social'] = 'El seguro social no puede exceder 30 caracteres.';
            }
            if ($longitud($domicilio) > 500) {
                $errores['domicilio'] = 'El domicilio no puede exceder 500 caracteres.';
            }
            if ($errores) {
                JsonResponse::validation('Revisa los datos señalados.', $errores);
            }

            $usuario->nombre = $nombre;
            $usuario->fecha_nacimiento = $fechaNacimiento !== '' ? $fechaNacimiento : null;
            $usuario->estado_civil = $estadoCivil !== '' ? $estadoCivil : null;
            $usuario->seguro_social = $seguroSocial !== '' ? $seguroSocial : null;
            $usuario->domicilio = $domicilio !== '' ? $domicilio : null;
            $usuario->save();

            JsonResponse::success('La información personal se actualizó correctamente.', [
                'informacion' => [
                    'nombre' => $nombre,
                    'fecha_nacimiento' => $fechaNacimiento,
                    'estado_civil' => $estadoCivil,
                    'seguro_social' => $seguroSocial,
                    'domicilio' => $domicilio,
                ],
            ]);
        } catch (Throwable $e) {
            JsonResponse::error('No fue posible actualizar la información personal.', 500);
        }
    }

    public function actualizarFirma()
    {
        try {
            $usuario = Usuario::query()->find($this->userId());
            if (!$usuario) {
                JsonResponse::notFound('No se encontró el usuario autenticado.');
            }

            $bytes = false;
            $extension = '';
            if (isset($_FILES['firma']) && is_array($_FILES['firma'])) {
                $archivo = $_FILES['firma'];
                if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($archivo['size'] ?? 0) > 3 * 1024 * 1024) {
                    JsonResponse::validation('Selecciona una imagen de firma de hasta 3 MB.', ['firma' => 'El archivo no se pudo leer o excede el límite de 3 MB.']);
                }
                $info = @getimagesize($archivo['tmp_name']);
                $mime = $info['mime'] ?? '';
                $extension = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$mime] ?? '';
                if ($extension === '' || ($info[0] ?? 0) > 5000 || ($info[1] ?? 0) > 3000) {
                    JsonResponse::validation('El archivo debe ser una imagen PNG, JPG o WEBP.', ['firma' => 'Formato de imagen no válido.']);
                }
                $bytes = file_get_contents($archivo['tmp_name']);
            } else {
                $dataUrl = (string) Request::jsonInput('firma', '');
                if (!preg_match('#^data:image/(png|jpeg|webp);base64,#', $dataUrl, $matches)) {
                    JsonResponse::validation('Dibuja tu firma o selecciona una imagen.', ['firma' => 'No se recibió una firma válida.']);
                }
                $encoded = substr($dataUrl, strpos($dataUrl, ',') + 1);
                if (strlen($encoded) > 4 * 1024 * 1024) {
                    JsonResponse::validation('La imagen de firma excede el tamaño permitido.', ['firma' => 'La imagen debe ser de hasta 3 MB.']);
                }
                $bytes = base64_decode($encoded, true);
                $info = is_string($bytes) ? @getimagesizefromstring($bytes) : false;
                $mime = $info['mime'] ?? '';
                $extension = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$mime] ?? '';
                if ($extension === '' || ($info[0] ?? 0) > 5000 || ($info[1] ?? 0) > 3000) {
                    JsonResponse::validation('La imagen debe ser PNG, JPG o WEBP y tener dimensiones válidas.', ['firma' => 'Formato o dimensiones de imagen no válidos.']);
                }
            }

            if (!is_string($bytes) || $bytes === '' || @getimagesizefromstring($bytes) === false) {
                JsonResponse::validation('La imagen de firma no es válida.', ['firma' => 'Selecciona una imagen PNG, JPG o WEBP válida.']);
            }

            $directorio = PUBLIC_PATH . '/uploads/firma-personal/';
            if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
                JsonResponse::serverError('No fue posible preparar el almacenamiento de firmas.');
            }
            $nombreArchivo = 'firma_' . (int) $usuario->id . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
            if (file_put_contents($directorio . $nombreArchivo, $bytes, LOCK_EX) === false) {
                JsonResponse::serverError('No fue posible guardar la imagen de firma.');
            }

            // Se conserva el archivo anterior porque otras constancias pueden referenciarlo.
            $usuario->firma = $nombreArchivo;
            $usuario->save();

            JsonResponse::success('La firma se actualizó correctamente.', [
                'firma' => rtrim((string) ($_ENV['APP_URL'] ?? ''), '/') . '/uploads/firma-personal/' . $nombreArchivo,
                'firma_archivo' => $nombreArchivo,
            ]);
        } catch (Throwable $e) {
            JsonResponse::error('No fue posible actualizar la firma.', 500);
        }
    }

    public function eliminarFirma()
    {
        try {
            $usuario = Usuario::query()->find($this->userId());
            if (!$usuario) {
                JsonResponse::notFound('No se encontró el usuario autenticado.');
            }

            // Se quita la firma del perfil, pero se conserva el archivo histórico
            // por si algún documento firmado todavía lo referencia.
            $usuario->firma = null;
            $usuario->save();

            JsonResponse::success('La firma se eliminó del perfil correctamente.');
        } catch (Throwable $e) {
            JsonResponse::error('No fue posible eliminar la firma del perfil.', 500);
        }
    }

    public function actualizarFoto()
    {
        try {
            $usuario = Usuario::query()->find($this->userId());
            if (!$usuario) {
                JsonResponse::notFound('No se encontró el usuario autenticado.');
            }

            $archivo = $_FILES['foto'] ?? null;
            if (!is_array($archivo) || (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                JsonResponse::validation('Selecciona una foto de perfil.', ['foto' => 'La foto es obligatoria.']);
            }
            if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($archivo['tmp_name'] ?? ''))) {
                JsonResponse::validation('No fue posible leer la foto seleccionada.', ['foto' => 'Vuelve a seleccionar una imagen válida.']);
            }
            if ((int) ($archivo['size'] ?? 0) > 5 * 1024 * 1024) {
                JsonResponse::validation('La foto no debe exceder 5 MB.', ['foto' => 'El tamaño máximo permitido es 5 MB.']);
            }

            $informacion = @getimagesize((string) $archivo['tmp_name']);
            $mime = $informacion['mime'] ?? '';
            $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? '';
            if ($extension === '' || ($informacion[0] ?? 0) > 6000 || ($informacion[1] ?? 0) > 6000) {
                JsonResponse::validation('La foto debe ser JPG, PNG o WEBP y tener dimensiones válidas.', ['foto' => 'Selecciona una imagen válida.']);
            }

            $directorio = PUBLIC_PATH . '/uploads/foto-perfil/';
            if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
                JsonResponse::serverError('No fue posible preparar el almacenamiento de fotos.');
            }

            $nombreArchivo = 'perfil_' . (int) $usuario->id . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
            if (!move_uploaded_file((string) $archivo['tmp_name'], $directorio . $nombreArchivo)) {
                JsonResponse::serverError('No fue posible guardar la foto de perfil.');
            }

            $usuario->foto = $nombreArchivo;
            $usuario->save();

            JsonResponse::success('La foto de perfil se actualizó correctamente.', [
                'foto' => PerfilFotoService::url($usuario),
            ]);
        } catch (Throwable $e) {
            JsonResponse::error('No fue posible actualizar la foto de perfil.', 500);
        }
    }

    public function eliminarFoto()
    {
        try {
            $usuario = Usuario::query()->find($this->userId());
            if (!$usuario) {
                JsonResponse::notFound('No se encontró el usuario autenticado.');
            }

            $nombreArchivo = basename(trim((string) ($usuario->foto ?? '')));
            $usuario->foto = null;
            $usuario->save();

            // Sólo se eliminan archivos que coinciden con el patrón creado para este usuario.
            // Los avatares predeterminados no se guardan aquí y nunca se tocan.
            $patronPropio = '/^perfil_' . preg_quote((string) $usuario->id, '/') . '_[a-f0-9]{16}\.(?:jpg|png|webp)$/i';
            if ($nombreArchivo !== '' && preg_match($patronPropio, $nombreArchivo) === 1) {
                $rutaArchivo = PUBLIC_PATH . '/uploads/foto-perfil/' . $nombreArchivo;
                if (is_file($rutaArchivo)) {
                    @unlink($rutaArchivo);
                }
            }

            JsonResponse::success('La foto personalizada se eliminó correctamente.', [
                'foto' => PerfilFotoService::url($usuario),
            ]);
        } catch (Throwable $e) {
            JsonResponse::error('No fue posible eliminar la foto de perfil.', 500);
        }
    }
}
