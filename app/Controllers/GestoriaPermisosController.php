<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ModuloService;

use App\Models\Usuario;
use App\Models\Estacion;
use Carbon\Carbon;

class GestoriaPermisosController extends BaseController
{
    protected string $modulo = 'gestoria';

    public function index()
    {
        $title = 'Permisos';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Gestoria', '/gestoria');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/gestoria/permisos/index.actions.init.js?v=1.5.0'
            ],
            'help' => false
        ];

        View::render('gestoria/permisos', $data, 'main');
    }

    public function table(): mixed
    {
        $idUsuario = (int) ($_GET['idUsuario'] ?? 0);

        if ($idUsuario <= 0) {
            return JsonResponse::custom([
                'success' => false,
                'message' => 'El usuario es requerido.'
            ], 400);
        }

        $usuario = Usuario::query()
            ->select([
                'id',
                'nombre'
            ])
            ->find($idUsuario);

        if (!$usuario) {
            return JsonResponse::custom([
                'success' => false,
                'message' => 'El usuario no existe.'
            ], 404);
        }

        $estaciones = Estacion::query()
            ->select([
                'id',
                'nombre',
                'numlista'
            ])
            ->where('numlista', '<=', 8)
            ->orderBy('numlista')
            ->get();

        $personal = Usuario::query()
            ->where('id_puesto', 5)
            ->where('estatus', 0)
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre'
            ]);

        $data = $estaciones->map(function ($estacion) use ($idUsuario) {

            $calendarios = $estacion->calendarios()
                ->where('estado', 1)
                ->whereHas('requisito', function ($query) use ($idUsuario) {
                    $query->where('id_usuario', $idUsuario);
                })
                ->with([
                    'requisito',
                    'matrizReciente'
                ])
                ->get([
                    'id',
                    'id_estacion',
                    'id_requisito_legal',
                    'nivel_gobierno',
                    'requisito_legal',
                    'vigencia'
                ]);

            $calendarios = $calendarios
                ->sortBy(function ($calendario) {

                    return $calendario->requisito?->nivel_gobierno ?? '';
                })
                ->values();

            return [
                'id' => $estacion->id,

                'nombre' => $estacion->nombre,

                'requisitos' => $calendarios->map(
                    function ($calendario) use ($estacion) {

                        $requisito = $calendario->requisito;

                        $matriz = $calendario->matrizReciente;

                        /*
                     * ------------------------------------------
                     * Datos del requisito
                     * ------------------------------------------
                     */

                        if ($calendario->id_requisito_legal == 0) {

                            $dependencia = 'S/I';

                            $permiso = $calendario->requisito_legal;
                        } else {

                            $dependencia =
                                $requisito?->dependencia ?? 'S/I';

                            $permiso =
                                $requisito?->permiso ?? 'S/I';
                        }

                        /*
                     * ------------------------------------------
                     * Archivos
                     * ------------------------------------------
                     */

                        $acuse = trim(
                            $matriz?->acusepdf ?? ''
                        );

                        $requisitoLegal = trim(
                            $matriz?->requisitolegalpdf ?? ''
                        );

                        /*
                     * ------------------------------------------
                     * Cumplimiento
                     * ------------------------------------------
                     */

                        if ($acuse === '' && $requisitoLegal === '') {

                            $cumplimiento = 0;
                        } elseif (
                            $acuse !== '' &&
                            $requisitoLegal === ''
                        ) {

                            $cumplimiento = 50;
                        } else {

                            $cumplimiento = 100;
                        }

                        /*
                     * ------------------------------------------
                     * Fechas
                     * ------------------------------------------
                     */

                        $fechaEmision = $matriz?->fecha_emision
                            ? $matriz->fecha_emision->format('Y-m-d')
                            : null;

                        $fechaVencimiento = $matriz?->fecha_vencimiento
                            ? $matriz->fecha_vencimiento->format('Y-m-d')
                            : null;

                        /*
                     * ------------------------------------------
                     * Formato para mostrar
                     * ------------------------------------------
                     */

                        $fechaEmisionTexto = $matriz?->fecha_emision
                            ? formatearFecha($matriz->fecha_emision->format('Y-m-d'))
                            : 'S/I';

                        $fechaVencimientoTexto = $matriz?->fecha_vencimiento
                            ? formatearFecha($matriz->fecha_vencimiento->format('Y-m-d'))
                            : 'S/I';

                        /*
                     * ------------------------------------------
                     * Validación de vencimiento
                     *
                     * El código original marca warning cuando:
                     *
                     * fecha_vencimiento - 30 días <= hoy
                     *
                     * excepto:
                     *
                     * Permanente
                     * Cuando se realice cambio
                     * ------------------------------------------
                     */

                        $tableWarning = false;

                        if (
                            $calendario->vigencia !== 'Permanente' &&
                            $calendario->vigencia !== 'Cuando se realice cambio' &&
                            $matriz?->fecha_vencimiento
                        ) {

                            $fechaNotificacion = $matriz
                                ->fecha_vencimiento
                                ->copy()
                                ->subDays(30);

                            $tableWarning =
                                $fechaNotificacion->lte(
                                    date('Y-m-d')
                                );
                        }

                        return [

                            'id' => $calendario->id,

                            'id_estacion' => $estacion->id,

                            'id_usuario' =>
                            $requisito?->id_usuario,

                            'id_requisito_legal' =>
                            $calendario->id_requisito_legal,

                            'nivel_gobierno' =>
                            $requisito?->nivel_gobierno
                                ?? $calendario->nivel_gobierno,

                            'dependencia' =>
                            $dependencia,

                            'permiso' =>
                            $permiso,

                            'vigencia' =>
                            $calendario->vigencia,

                            'fecha_emision' =>
                            $fechaEmision,

                            'fecha_emision_texto' =>
                            $fechaEmisionTexto,

                            'fecha_vencimiento' =>
                            $fechaVencimiento,

                            'fecha_vencimiento_texto' =>
                            $fechaVencimientoTexto,

                            'acusepdf' =>
                            $acuse,

                            'requisitolegalpdf' =>
                            $requisitoLegal,

                            'acuse_url' =>
                            $acuse !== ''
                                ? basename($acuse)
                                : null,

                            'requisitolegal_url' =>
                            $requisitoLegal !== ''
                                ? basename($requisitoLegal)
                                : null,

                            'cumplimiento' =>
                            $cumplimiento . ' %',

                            'toCumpli' =>
                            $cumplimiento,

                            'table_warning' =>
                            $tableWarning,

                            'documento' => [

                                'id' =>
                                $calendario->id,

                                'id_usuario' =>
                                $requisito?->id_usuario,

                                'id_estacion' =>
                                $estacion->id

                            ],



                        ];
                    }
                )->values()
            ];
        })->values();

        return JsonResponse::custom([

            'success' => true,

            'data' => [

                'usuario' => [

                    'id' =>
                    $usuario->id,

                    'nombre' =>
                    $usuario->nombre

                ],

                'estaciones' =>
                $data,

                'personal' => $personal->map(fn($persona) => [
                    'id' => $persona->id,
                    'nombre' => $persona->nombre
                ])->values(),

            ]

        ], 200);
    }
}
