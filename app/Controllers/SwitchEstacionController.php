<?php
namespace App\Controllers;
use App\Core\Session;
use App\Models\Estacion;

class SwitchEstacionController extends BaseController
{

    public function switchSessionEstacion()
    {
        header('Content-Type: application/json');

        try {

        $input = json_decode(
        file_get_contents('php://input'),
        true
        );

        $idEstacion = $input['id_estacion'] ?? null;

        if (!$idEstacion) {

        echo json_encode([
        'ok' => false,
        'type' => 'error',
        'message' => 'Estación inválida'
        ]);

        return;
        }

        $usuario = Session::get('usuario');

        if (!$usuario) {

        echo json_encode([
        'ok' => false,
        'type' => 'error',
        'message' => 'Sesión no válida'
        ]);

        return;
        }

        if (!$usuario['multiestacion']) {

        echo json_encode([
        'ok' => false,
        'type' => 'error',
        'message' => 'No autorizado'
        ]);

        return;
        }

       $estacion = Estacion::find($idEstacion);

        if (!$estacion) {

        echo json_encode([
        'ok' => false,
        'type' => 'error',
        'message' => 'La estación no existe'
        ]);

        return;
        }

        $usuario['id_estacion'] = (int) $idEstacion;
        $usuario['razonsocial'] = $estacion->razonsocial;

        Session::set(
        'usuario',
        $usuario
        );

        echo json_encode([
        'ok' => true,
        'type' => 'success',
        'message' => 'Estación cambiada'
        ]);

        } catch (\Throwable $e) {

        echo json_encode([
        'ok' => false,
        'type' => 'error',
        'message' => $e->getMessage()
        ]);

        }

    }

}