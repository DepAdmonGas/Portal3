<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Core\Request;
use App\Core\JsonResponse;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sasisopa\TanqueAlmacenamiento;
use App\Models\Sgm\Autorizado;
use App\Models\Sgm\ProgramaAnualCalibracionVerificacion;
use App\Models\Sgm\InventarioEquipo;
use App\Models\Sgm\BitacoraVerificacionResultado;
use App\Models\Sgm\BitacoraVerificacionSensores;
use App\Models\Sgm\BitacoraVerificacionLista;
use App\Models\Sgm\BitacoraVerificacionDispensario;
use App\Models\Sgm\BitacoraVerificacionDispensarioDetalle;

use Dompdf\Dompdf;
use Dompdf\Options;

class SgmBitacoraVerificacionController extends BaseController
{
    protected string $modulo = 'sgm';

    public function datatable()
    {
        $fecha = date('Y-m-d');

        $permisoEliminar  = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar    = ModuloService::validaPermiso($this->modulo, 'editar');
        $permisoDescargar = ModuloService::validaPermiso($this->modulo, 'descargar');

        $programas = ProgramaAnualCalibracionVerificacion::query()

            ->with([
                'equipo:id,nombre,periodicidad,categoria'
            ])

            ->where('id_estacion', $this->estacionId())

            ->whereDate('fecha', '<=', $fecha)

            ->whereHas('equipo', function ($query) {

                $query->where(
                    'categoria',
                    'Equipo sometido a verificación'
                );
            })

            ->orderByDesc('fecha')

            ->get()

            ->map(function ($item) {

                return [

                    'id' => $item->id,

                    'equipo' => $item->equipo->nombre,

                    'periodicidad' => $item->equipo->periodicidad,

                    'fecha' => $item->fecha->format('Y-m-d'),

                    'estado' => $item->estado,

                    'color' => match ($item->estado) {
                        0 => '#fbf8ce',
                        1 => '#cffbce',
                        default => '#ffffff',
                    },

                    'acciones' => [

                        'editar' => true,

                        'detalle' => $item->estado === 1,

                        'descargar' => $item->estado === 1,

                    ],

                ];
            })

            ->values();

        JsonResponse::custom([

            'data' => $programas,

            'permisos' => [

                'eliminar' => $permisoEliminar,

                'editar' => $permisoEditar,

                'descargar' => $permisoDescargar,

            ],

        ]);
    }

    public function editarBitacoraVerificacionEquipos(int $id)
    {
        $title = 'Editar';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add('7. Procesos de medición', '/sgm/procesos-medicion');
        Breadcrumb::add('Bitácora para la verificación de equipos de medicion', '/sgm/procesos-medicion/bitacora-verificacion-equipo-medicion');
        Breadcrumb::add($title, '');
        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'id' => $id,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/sgm/procesos-medicion/editarbitacoraverificacion.actions.init.js?v=' . time(),

            ],
            'help' => false
        ];

        View::render('sgm/procesos-medicion/editar-bitacora-verificacion-equipos', $data, 'sgm');
    }

    private function programaAnual(int $id): ProgramaAnualCalibracionVerificacion
    {

        return ProgramaAnualCalibracionVerificacion::query()

            ->with('equipo')

            ->findOrFail($id);
    }

    private function responsableSGM(int $idEstacion): int
    {
        return Autorizado::query()
            ->where('estado', 1)
            ->whereHas('usuario', function ($q) use ($idEstacion) {
                $q->where('id_gas', $idEstacion);
            })
            ->value('id_usuario') ?? 0;
    }

    private function tanqueInformacion(
        ProgramaAnualCalibracionVerificacion $programa
    ): array {

        $inventario = InventarioEquipo::find(
            $programa->id_verificar
        );

        if (!$inventario) {

            return [
                'no_tanque' => '',
                'capacidad' => '',
                'producto' => ''
            ];
        }

        $tanque = TanqueAlmacenamiento::query()

            ->where(
                'id_estacion',
                $programa->id_estacion
            )

            ->where(
                'no_tanque',
                $inventario->identificacion
            )

            ->first();

        return [

            'no_tanque' => $inventario->identificacion,

            'capacidad' => $tanque?->capacidad ?? '',

            'producto' => $tanque?->producto ?? ''

        ];
    }

    private function obtenerBitacoraSensor(
        ProgramaAnualCalibracionVerificacion $programa
    ): BitacoraVerificacionSensores {

        $tanque = $this->tanqueInformacion($programa);

        return BitacoraVerificacionSensores::firstOrCreate(

            [
                'id_programa' => $programa->id
            ],

            [

                'fecha' => date('Y-m-d'),

                'hora' => date('H:i:s'),

                'no_tanque' => $tanque['no_tanque'],

                'marca' => '',

                'capacidad' => $tanque['capacidad'],

                'producto' => $tanque['producto'],

                'interno_externo' => '',

                'verificacion_movimiento' => '',

                'metodo_nivel' => '',

                'realizadopor' => $this->responsableSGM(
                    $programa->id_estacion
                )

            ]

        );
    }

    private function crearResultados(
        ProgramaAnualCalibracionVerificacion $programa
    ): void {

        BitacoraVerificacionLista::all()->each(function ($item) use ($programa) {

            BitacoraVerificacionResultado::firstOrCreate(
                [
                    'id_programa' => $programa->id,
                    'id_lista'    => $item->id,
                ],
                [
                    'resultado' => '',
                ]
            );
        });
    }

    private function numeroDispensario(
        ProgramaAnualCalibracionVerificacion $programa
    ): string {

        return InventarioEquipo::find(
            $programa->id_verificar
        )?->identificacion ?? '';
    }

    private function obtenerBitacoraDispensario(
        ProgramaAnualCalibracionVerificacion $programa
    ): BitacoraVerificacionDispensario {

        return BitacoraVerificacionDispensario::firstOrCreate(

            [

                'id_programa' => $programa->id

            ],

            [

                'fecha' => date('Y-m-d'),
                'hora' => date('H:i:s'),
                'marca_modelo_jarra_patron' => '',
                'capacidad' => '',
                'jarra_patron_calibrada' => '',
                'no_dispensario' => $this->numeroDispensario($programa),
                'realizadopor' => $this->responsableSGM(
                    $programa->id_estacion
                )

            ]

        );
    }

    private function resultadosCategoria(
        int $idPrograma,
        string $categoria
    ) {

        return BitacoraVerificacionResultado::query()
            ->with('lista')
            ->where('id_programa', $idPrograma)
            ->get()
            ->filter(function ($item) use ($categoria) {
                return $item->lista &&
                    $item->lista->categoria === $categoria;
            })
            ->values();
    }

    private function datosBitacora(int $id): array
    {
        $programa = $this->programaAnual($id);

        if ($programa->equipo->nombre === 'Sensor de nivel y temperatura') {

            $bitacora = $this->obtenerBitacoraSensor($programa);

            $this->crearResultados($programa);

            return [
                'tipo' => 'sensor',
                'programa' => $programa,
                'bitacora' => $bitacora,
                'categorias' => [
                    [
                        'titulo' => '1. Aspecto a verificar en los patrones de referencia',
                        'preguntas' => $this->resultadosCategoria(
                            $id,
                            '1. Aspecto a verificar en los patrones de referencia'
                        ),
                    ],
                    [
                        'titulo' => '2. Sistema de nivel automático (tirilla del Sistema de Control de Inventarios)',
                        'preguntas' => $this->resultadosCategoria(
                            $id,
                            '2. Sistema de nivel automático (tirilla del Sistema de Control de Inventarios)'
                        ),
                    ],
                    [
                        'titulo' => '3. Medición de la cinta petrolera (en mm) y termómetro (en °C)',
                        'preguntas' => $this->resultadosCategoria(
                            $id,
                            '3. Medición de la cinta petrolera (en mm) y termómetro (en °C)'
                        ),
                    ],
                    [
                        'titulo' => '4. Resultado: Diferencia entre ambas mediciones',
                        'preguntas' => $this->resultadosCategoria(
                            $id,
                            '4. Resultado: Diferencia entre ambas mediciones'
                        ),
                    ],
                ],
            ];
        }

        $bitacora = $this->obtenerBitacoraDispensario($programa);

        $bitacora->load('detalles');

        $bitacora->detalles->transform(function ($detalle) {

            $detalle->diferencia =
                (float)$detalle->medida_comparar -
                (float)$detalle->medicion_jarra_patron;

            $detalle->resultado =
                abs($detalle->diferencia) <= 100
                ? 'Favorable'
                : 'No Favorable';

            return $detalle;
        });

        return [
            'tipo' => 'dispensario',
            'programa' => $programa,
            'bitacora' => $bitacora,
            'detalles' => $bitacora->detalles,
        ];
    }

    public function obtenerBitacora(int $id)
    {
        JsonResponse::custom(
            $this->datosBitacora($id)
        );
    }

    public function actualizarCampo()
    {
        try {

            $id    = Request::jsonInput('id');
            $campo = Request::jsonInput('campo');
            $valor = Request::jsonInput('valor');
            $tipo  = Request::jsonInput('tipo');

            $camposSensores = [
                'fecha',
                'hora',
                'no_tanque',
                'marca',
                'capacidad',
                'producto',
                'interno_externo',
                'verificacion_movimiento',
                'metodo_nivel',
            ];

            $camposDispensarios = [
                'fecha',
                'hora',
                'marca_modelo_jarra_patron',
                'capacidad',
                'jarra_patron_calibrada',
                'no_dispensario',
            ];

            if ($tipo == 'sensor') {

                if (!in_array($campo, $camposSensores)) {
                    JsonResponse::error('Campo no permitido.');
                    return;
                }

                $bitacora = BitacoraVerificacionSensores::findOrFail($id);
            } elseif ($tipo == 'dispensario') {

                if (!in_array($campo, $camposDispensarios)) {
                    JsonResponse::error('Campo no permitido.');
                    return;
                }

                $bitacora = BitacoraVerificacionDispensario::findOrFail($id);
            } else {

                JsonResponse::error('Tipo de bitácora inválido.');
                return;
            }

            $bitacora->$campo = $valor;

            if ($bitacora->save()) {

                JsonResponse::success('Campo actualizado correctamente.');
                return;
            }

            JsonResponse::error('No se pudo actualizar el campo.');
        } catch (\Throwable $e) {

            JsonResponse::error('Error al actualizar el registro.');
        }
    }

    public function actualizarResultado()
    {
        try {

            $resultado = BitacoraVerificacionResultado::findOrFail(
                Request::input('id')
            );

            $resultado->resultado = Request::input('resultado');

            $resultado->save();

            JsonResponse::success(
                'Resultado actualizado'
            );
        } catch (\Throwable $e) {

            JsonResponse::error(
                $e->getMessage()
            );
        }
    }

    public function agregarManguera()
    {
        try {

            $detalle = new BitacoraVerificacionDispensarioDetalle();

            $detalle->id_verificacion = Request::jsonInput('id_verificacion');
            $detalle->lado = Request::jsonInput('lado');
            $detalle->producto = Request::jsonInput('producto');
            $detalle->medida_comparar = Request::jsonInput('medida_comparar');
            $detalle->medicion_jarra_patron = Request::jsonInput('medicion_jarra_patron');

            if (!$detalle->save()) {
                JsonResponse::error('No se pudo guardar el registro.');
                return;
            }

            $diferenciaReal = $detalle->medida_comparar - $detalle->medicion_jarra_patron;

            $detalle->diferencia = $diferenciaReal;

            $detalle->resultado =
                abs($diferenciaReal) <= 100
                ? 'Favorable'
                : 'No favorable';

            JsonResponse::success(
                'Registro agregado correctamente.',
                [
                    'detalle' => $detalle
                ]
            );
        } catch (\Throwable $e) {

            JsonResponse::error('Error al guardar el registro.');
        }
    }

    public function eliminarManguera()
    {
        try {

            BitacoraVerificacionDispensarioDetalle::findOrFail(
                Request::input('id')
            )->delete();

            JsonResponse::success(
                'Registro eliminado'
            );
        } catch (\Throwable $e) {

            JsonResponse::error(
                $e->getMessage()
            );
        }
    }

    public function finalizar()
    {
        try {

            $programa = ProgramaAnualCalibracionVerificacion::findOrFail(
                Request::input('id')
            );

            $programa->estado = 1;

            $programa->save();

            JsonResponse::success(
                'Bitácora finalizada'
            );
        } catch (\Throwable $e) {

            JsonResponse::error(
                $e->getMessage()
            );
        }
    }

    public function pdfBitacora(int $id)
    {
        header('Content-Type: application/pdf');

        $estacion = Estacion::findOrFail(
            $this->estacionId()
        );

        $css = file_get_contents(
            'assets/css/pdf.css'
        );

        $datos = $this->datosBitacora($id);
        $tipo = $datos['tipo'];
        $programa = $datos['programa'];
        $bitacora = $datos['bitacora'];

        $responsable = Usuario::find($bitacora->realizadopor);

        $html = '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Bitácora para la verificación de equipos de medicion</title>
        <style>
            ' . $css . '
        </style>
    </head>
    <body>

    <table class="table table-bordered">
        <tr>
            <td rowspan="2" class="text-center align-middle">
                ' . $estacion->razonsocial . '
            </td>

            <td rowspan="2" class="text-center align-middle">
                <strong>Bitácora para la verificación de equipos de medicion</strong>
            </td>

            <td class="text-center align-middle">
                <strong>Fecha de autorización: 01-01-2025</strong>
            </td>
        </tr>

        <tr>
            <td class="text-center align-middle">
                Fo.SGM.016
            </td>
        </tr>

        <tr>
            <td class="text-center align-middle">
                Realizado por:<br>
                ' . ($responsable->nombre ?? 'S/I') . '
            </td>

            <td class="text-center align-middle">
                Revisado por:<br>
                Eduardo Galicia Flores
            </td>

            <td class="text-center align-middle">
                Autorizado por:<br>
                ' . $estacion->apoderado_legal . '
            </td>
        </tr>
    </table>';

        if ($tipo === 'sensor') {

            $categorias = $datos['categorias'];

            $html .= '
            <table class="table table-bordered">
                <tr><td>Fecha</td><td>' . formatearFecha($bitacora->fecha) . '</td></tr>
                <tr><td>Hora</td><td>' . date('g:i a', strtotime($bitacora->hora)) . '</td></tr>
                <tr><td colspan="2" class="bg-muted text-white">Verificación de sensores de nivel y temperatura</td></tr>
                <tr><td>No. de tanque</td><td>' . $bitacora->no_tanque . '</td></tr>
                <tr><td>Marca</td><td>' . $bitacora->marca . '</td></tr>
                <tr><td>Capacidad</td><td>' . $bitacora->capacidad . '</td></tr>
                <tr><td>Producto que almacena</td><td>' . $bitacora->producto . '</td></tr>
                <tr><td>La verificación es realizada por personal interno o externo (en caso de ser externo indicar la empresa).</td><td>' . $bitacora->interno_externo . '</td></tr>
                <tr><td>Al iniciar la calibración se asegura que el producto se encuentre sin movimiento</td><td>' . $bitacora->verificacion_movimiento . '</td></tr>
                <tr><td>Método para determinar el nivel líquido dentro del tanque (Inmersión o medida seca)</td><td>' . $bitacora->metodo_nivel . '</td></tr>
            </table>';

            foreach ($categorias as $categoria) {

                $html .= '
    <table class="table table-bordered">
        <tr>
            <td class="bg-muted text-white">' . $categoria['titulo'] . '</td>
            <td class="bg-muted text-white">Resultado</td>
        </tr>';

                foreach ($categoria['preguntas'] as $pregunta) {

                    $html .= '
        <tr>
            <td>' . $pregunta->lista->pregunta . '</td>
            <td>' . $pregunta->resultado . '</td>
        </tr>';
                }

                $html .= '</table><br>';
            }

            $html .= '<div class="bg-light p-2"><b>Nota 1:</b> Referente al nivel puede existir una variación de +/- 3 mm, sin embargo, para aplicaciones fiscales o de transferencia de custodia,
                        los equipos deben cumplir con un EMP de Â± 4 mm, en todo el intervalo de medición.<br>
                        <b>Nota 2:</b> referente a la temperatura puede existir una variación igual o menor de 0.5 °C</div>';
        } else {

            $detalles = $datos['detalles'];

            $html .= '
                <table class="table table-bordered">

                <tr><td>Fecha</td><td>' . formatearFecha($bitacora->fecha) . '</td></tr>
                <tr><td>Hora</td><td>' . date('g:i a', strtotime($bitacora->hora)) . '</td></tr>
                <tr><td class="bg-muted text-white" colspan="2">Verificacion de dispensarios</td></tr>
                <tr><td class="bg-light">1. Aspecto a verificar en los patrones de referencia</td><td class="bg-light">Resultado</td></tr>
                <tr><td>Marca y modelo de la jarra patrón</td><td>' . $bitacora->marca_modelo_jarra_patron . '</td></tr>
                <tr><td>Capacidad</td><td>' . $bitacora->capacidad . '</td></tr>
                <tr><td>Jarra patrón calibrada</td><td>' . $bitacora->jarra_patron_calibrada . '</td></tr>
                <tr><td class="bg-light">2. Aspecto a verificar</td><td class="bg-light">Resultado</td></tr>
                <tr><td>No. dispensario</td><td>' . $bitacora->no_dispensario . '</td></tr>

                </table>';

            $html .= '
            <table class="table table-bordered">

            <tr>
                <th>Lado</th>
                <th>Producto</th>
                <th>Medida</th>
                <th>Jarra patrón</th>
                <th>Diferencia</th>
                <th>Resultado</th>
            </tr>';

            foreach ($detalles as $detalle) {

                $html .= '
                <tr>
                    <td>' . $detalle->lado . '</td>
                    <td>' . $detalle->producto . '</td>
                    <td>' . $detalle->medida_comparar . ' ml</td>
                    <td>' . $detalle->medicion_jarra_patron . ' ml</td>
                    <td>' . $detalle->diferencia . ' ml</td>
                    <td>' . $detalle->resultado . '</td>
                </tr>';
            }

            $html .= '</table>';
        }

        $html .= '
    </body>
    </html>
';

        $options = new Options();
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);

        $dompdf->setPaper('A4', 'portrait');

        $dompdf->render();

        $dompdf->stream(
            'Bitácora para la verificación de equipos de medicion.pdf',
            [
                'Attachment' => true,
            ]
        );

        exit;
    }
}
