<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use Throwable;

use App\Models\Usuario;

class PerfilController extends BaseController
{

    public function index()
    {
        $title = 'Perfil de usuario';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
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

            if (strlen($passwordNueva) < 8) {

                JsonResponse::error(
                    'La nueva contraseña debe contener al menos 8 caracteres.'
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

            if (password_verify($passwordActual, $usuario->password)) {

                JsonResponse::error(
                    'La contraseña actual es incorrecta.'
                );
            }


            if (password_verify($passwordNueva, $usuario->password)) {

                JsonResponse::error(
                    'La nueva contraseña debe ser diferente a la contraseña actual.'
                );
            }

            $usuario->password = $passwordNueva;

            $usuario->save();

            JsonResponse::success('La contraseña se actualizó correctamente.');
        } catch (Throwable $e) {


            JsonResponse::error(
                'Ocurrió un error al actualizar la contraseña.'
            );
        }
    }
}
