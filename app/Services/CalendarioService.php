<?php
namespace App\Services;

use App\Core\Session;
use App\Models\Sasisopa\CalendarioActividad;
use App\Models\Sasisopa\CursoCalendario;

class CalendarioService
{
    public static function pendientes(): int
    {
        $usuario = Session::get('usuario');        
        if (!$usuario) {
        return 0;
        }

        $idEstacion = $usuario['id_estacion'];
        if (!$idEstacion) {
            return 0;
        }

        $actividades = CalendarioActividad::query()
            ->where('id_estacion', $idEstacion)
            ->where('estado', 0)
            ->count();

        $cursos = CursoCalendario::query()
            ->where('id_estacion', $idEstacion)
            ->where('estado', 0)
            ->count();

        return $actividades + $cursos;
    }
}