<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;

use App\Services\ModuloService;

use App\Models\Junta;
use App\Models\JuntaComentario;

class SalaJuntasController extends BaseController
{
    protected string $modulo = 'sala-juntas';

    public function index()
    {
        $title = 'Sala de Juntas';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        // Buscar permisos de los modulos
        $permisos = ModuloService::getPermisos($this->userId());

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/fullcalendar/index.global.min.js',

                '/js/salajuntas/calendar-init.js?v=1.0.0'
            ],
            'help' => false

        ];

        View::render('sala-juntas/index', $data, 'main');
    }

    public function dataCalendario(): void
    {
        $inicio = Request::input('start');
        $fin    = Request::input('end');

        $juntas = Junta::query()
            ->with([
                'usuario:id,nombre,id_puesto',
                'usuario.puesto:id,tipo_puesto',
            ])
            ->withCount('comentarios')
            ->where(function ($query) {
                $query
                    ->where('estatus', '<>', 'Cancelada')
                    ->orWhereNull('estatus');
            })
            ->when(
                !empty($inicio),
                fn($query) => $query->whereDate(
                    'fecha',
                    '>=',
                    substr($inicio, 0, 10)
                )
            )
            ->when(
                !empty($fin),
                fn($query) => $query->whereDate(
                    'fecha',
                    '<',
                    substr($fin, 0, 10)
                )
            )
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        $eventos = $juntas->map(function (Junta $junta) {

            $fecha = $junta->fecha?->format('Y-m-d');

            $horaInicio =
                $junta->hora_inicio?->format('H:i:s');

            $horaTermino =
                $junta->hora_termino?->format('H:i:s');

            return [
                'id' => (string) $junta->id,

                'title' => $junta->descripcion
                    ?: 'Sin Descripción',

                'start' => $fecha && $horaInicio
                    ? $fecha . 'T' . $horaInicio
                    : $fecha,

                'end' => $fecha && $horaTermino
                    ? $fecha . 'T' . $horaTermino
                    : null,

                'extendedProps' => [
                    'idJunta' => (int) $junta->id,

                    'idPuesto' => (int) $junta->idPuesto,

                    'idUsuario' => (int) $junta->idUsuario,

                    'convoca' =>
                    $junta->usuario?->nombre ?? 'S/I',

                    'puesto' =>
                    $junta->usuario?->puesto?->tipo_puesto
                        ?? 'S/I',

                    'descripcion' =>
                    $junta->descripcion
                        ?: 'Sin Descripción',

                    'fecha' => $fecha,

                    'hora_inicio' =>
                    $junta->hora_inicio?->format('H:i'),

                    'hora_termino' =>
                    $junta->hora_termino?->format('H:i'),

                    'estatus' => $junta->estatus,

                    'comentarios' =>
                    (int) $junta->comentarios_count,
                ],
            ];
        });

        JsonResponse::custom([
            'eventos' => $eventos->values()->toArray(),
        ]);
    }

    public function diaCalendario(): void
    {
        $fecha = Request::input('fecha');

        if (empty($fecha)) {
            JsonResponse::custom([
                'success' => false,
                'data' => [],
            ]);

            return;
        }

        $juntas = Junta::query()
            ->with([
                'usuario:id,nombre,id_puesto',
                'usuario.puesto:id,tipo_puesto',
            ])
            ->withCount('comentarios')
            ->whereDate('fecha', $fecha)
            ->where(function ($query) {
                $query
                    ->where('estatus', '<>', 'Cancelada')
                    ->orWhereNull('estatus');
            })
            ->orderBy('hora_inicio')
            ->get();

        $data = $juntas->map(function (Junta $junta) {

            return [
                'id' => (int) $junta->id,

                'convoca' =>
                $junta->usuario?->nombre ?? 'S/I',

                'puesto' =>
                $junta->usuario?->puesto?->tipo_puesto
                    ?? 'S/I',

                'descripcion' =>
                $junta->descripcion
                    ?: 'Sin Descripción',

                'fecha' =>
                $junta->fecha?->format('Y-m-d'),

                'hora_inicio' =>
                $junta->hora_inicio?->format('H:i'),

                'hora_termino' =>
                $junta->hora_termino?->format('H:i'),

                'estatus' => $junta->estatus,

                'comentarios' =>
                (int) $junta->comentarios_count,
            ];
        });

        JsonResponse::custom([
            'success' => true,
            'data' => $data->values()->toArray(),
        ]);
    }

    public function detalleCalendario(int $id): void
    {
        $junta = Junta::query()
            ->with([
                'usuario:id,nombre,id_puesto',
                'usuario.puesto:id,tipo_puesto',
                'comentarios',
            ])
            ->find($id);

        if (!$junta) {
            JsonResponse::custom([
                'success' => false,
                'message' => 'Junta no encontrada',
            ]);

            return;
        }

        JsonResponse::custom([
            'success' => true,

            'data' => [
                'id' => (int) $junta->id,

                'convoca' =>
                $junta->usuario?->nombre ?? 'S/I',

                'puesto' =>
                $junta->usuario?->puesto?->tipo_puesto
                    ?? 'S/I',

                'descripcion' =>
                $junta->descripcion
                    ?: 'Sin Descripción',

                'fecha' =>
                $junta->fecha?->format('Y-m-d'),

                'hora_inicio' =>
                $junta->hora_inicio?->format('H:i'),

                'hora_termino' =>
                $junta->hora_termino?->format('H:i'),

                'estatus' =>
                $junta->estatus,

                'comentarios' =>
                $junta->comentarios
                    ->map(function ($comentario) {

                        return [
                            'id' =>
                            (int) $comentario->id,

                            'id_usuario' =>
                            (int) $comentario->id_usuario,

                            'fecha_hora' =>
                            $comentario->fecha_hora
                                ? $comentario
                                ->fecha_hora
                                ->format('Y-m-d H:i')
                                : null,

                            'comentario' =>
                            $comentario->comentario,
                        ];
                    })
                    ->values()
                    ->toArray(),
            ],
        ]);
    }
}
