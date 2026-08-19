<?php

namespace App\Services;

use App\Core\Session;
use App\Models\Sasisopa\CalendarioActividad;
use App\Models\Sasisopa\CursoCalendario;
use App\Models\Sgm\CalendarioActividadSgm;

class CalendarioService
{
    public static function pendientes(): array
    {

        return [
            'sasisopa' => self::sasisopa(),
            'sgm' => self::sgm(),
        ];
    }

    public static function sasisopa(): int
    {
        $categoria = 'SASISOPA';
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
            ->when($categoria, function ($query) use ($categoria) {
                $query->whereHas('tema', function ($query) use ($categoria) {
                    $query->where('categoria', $categoria);
                });
            })
            ->where('estado', 0)
            ->count();

        return $actividades + $cursos;
    }

    public static function sgm(): int
    {

        $categoria = 'SGM';
        $usuario = Session::get('usuario');
        if (!$usuario) {
            return 0;
        }

        $idEstacion = $usuario['id_estacion'];
        if (!$idEstacion) {
            return 0;
        }

        $actividades = CalendarioActividadSgm::query()
            ->where('id_estacion', $idEstacion)
            ->where('estado', 0)
            ->count();

        $cursos = CursoCalendario::query()
            ->with([
                'tema:id,num_tema,titulo,categoria'
            ])
            ->where('id_estacion', $idEstacion)
            ->when($categoria, function ($query) use ($categoria) {
                $query->whereHas('tema', function ($query) use ($categoria) {
                    $query->where('categoria', $categoria);
                });
            })
            ->where('estado', 0)
            ->count();

        return $actividades + $cursos;
    }
}
