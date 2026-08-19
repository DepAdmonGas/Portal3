<?php

namespace App\Controllers;

use App\Core\View;
use App\Services\ModuloService;
use App\Core\Breadcrumb;
use App\Models\Estacion;
use App\Models\Sasisopa\CursoCalendario;
use App\Models\Sasisopa\CursoModulo;
use App\Models\Sasisopa\CursoTemaPregunta;
use App\Models\Sasisopa\CursoTemaPreguntaRespuesta;
use App\Models\Sasisopa\CursoTema;
use App\Models\Sasisopa\CursoEvaluacion;
use FPDF;

class CursosController extends BaseController
{

    protected string $modulo = 'sasisopa';

    public function cursosIndex()
    {

        $title = 'Cursos';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'categoria' => 'SASISOPA',
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',

                '/js/cursos/index.action.init.js?v=1.5',

            ]
        ];

        View::render('cursos/index', $data, 'sasisopa');
    }

    public function cursosSgmIndex()
    {

        $title = 'Cursos';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => 'sgm',
            'categoria' => 'SGM',
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/cursos/index.action.init.js?v=1.5',
            ]
        ];

        View::render('cursos/index', $data, 'sgm');
    }




    public function getModulos(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $categoria = $_GET['categoria'] ?? null;

        try {

            $modulos = CursoModulo::query()
                ->whereHas('temas', function ($query) use ($categoria) {
                    $query->where('categoria', $categoria);
                })
                ->withCount([
                    'temas as temas_count' => function ($query) use ($categoria) {
                        $query->where('categoria', $categoria);
                    }
                ])
                ->orderBy('num_modulo')
                ->get();

            $data = [];

            foreach ($modulos as $index => $modulo) {

                $data[] = [
                    'id'         => $modulo->id,
                    'numero'     => $index + 1,
                    'num_modulo' => $modulo->num_modulo,
                    'titulo'     => $modulo->titulo,
                    'totalTemas' => $modulo->temas_count,
                ];
            }

            echo json_encode([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function getCursosPendientes(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $categoria = $_GET['categoria'] ?? null;

        try {

            $cursos = CursoCalendario::query()
                ->with([
                    'tema:id,num_tema,titulo,categoria'
                ])

                ->where('id_personal', $this->userId())
                ->where('estado', 0)
                ->whereDate('fecha_programada', '<=', date('Y-m-d'))

                ->when($categoria, function ($query) use ($categoria) {
                    $query->whereHas('tema', function ($query) use ($categoria) {
                        $query->where('categoria', $categoria);
                    });
                })

                ->orderBy('fecha_programada')
                ->get();

            $data = [];

            foreach ($cursos as $curso) {

                $data[] = [

                    'id' => $curso->id,

                    'fecha' => formatearFecha(
                        $curso->fecha_programada
                    ),

                    'fecha_raw' => $curso->fecha_programada->format('Y-m-d'),

                    'tema' => $curso->tema?->num_tema,

                    'titulo' => $curso->tema?->titulo,

                    'categoria' => $curso->tema?->categoria,

                ];
            }

            echo json_encode([
                'success' => true,
                'total'   => count($data),
                'data'    => $data
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function cursosIniciar(int $id): void
    {
        $calendario = CursoCalendario::query()
            ->with([
                'tema'
            ])
            ->where('id', $id)
            ->where('id_personal', $this->userId())
            ->firstOrFail();

        if ($calendario->estado == 1) {
            header('Location: /sasisopa/cursos');
            exit;
        }


        $tema = $calendario->tema;

        $title = $tema->num_tema . ' - ' . $tema->titulo;

        $layout = 'sasisopa';

        if ($tema->categoria == 'SASISOPA') {
            $layout = 'sasisopa';
        } else if ($tema->categoria == 'SGM') {
            $layout = 'sgm';
        }

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($tema->categoria, '/' . mb_strtolower($tema->categoria));
        Breadcrumb::add('Cursos', '/' . mb_strtolower($tema->categoria) . '/cursos');
        Breadcrumb::add($title, '');

        View::render(
            'cursos/iniciar',
            [

                'title' => $title,
                'tema' => $tema,
                'calendario' => $calendario,
                'scripts' => [
                    '/js/vendor.min.js'
                ]

            ],
            $layout
        );
    }

    public function cursosEvaluacion(int $id): void
    {
        $calendario = CursoCalendario::query()
            ->with([
                'tema'
            ])
            ->where('id', $id)
            ->where('id_personal', $this->userId())
            ->firstOrFail();

        if ($calendario->estado == 1) {
            header('Location: /sasisopa/cursos');
            exit;
        }

        $tema = $calendario->tema;

        $title = 'Evaluacion, ' . $tema->num_tema . ' - ' . $tema->titulo;

        $layout = 'sasisopa';

        if ($tema->categoria == 'SASISOPA') {
            $layout = 'sasisopa';
        } else if ($tema->categoria == 'SGM') {
            $layout = 'sgm';
        }

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($tema->categoria, '/' . mb_strtolower($tema->categoria));
        Breadcrumb::add('Cursos', '/' . mb_strtolower($tema->categoria) . '/cursos');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'tema' => $tema,
            'calendario' => $calendario,

            'scripts' => [
                '/js/vendor.min.js',
                '/js/cursos/evaluacion.action.init.js?v=1.1',
            ]
        ];

        View::render('cursos/evaluacion', $data, $layout);
    }

    public function getEvaluacion(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $calendario = CursoCalendario::query()
                ->with('tema')
                ->where('id', $id)
                ->where('id_personal', $this->userId())
                ->firstOrFail();

            if ($calendario->estado == 1) {

                echo json_encode([
                    'success' => false,
                    'message' => 'La evaluación ya fue finalizada.'
                ]);
                return;
            }

            $preguntas = CursoTemaPregunta::query()
                ->with('respuestas')
                ->where('id_tema', $calendario->id_tema)
                ->orderBy('num_pregunta')
                ->get();

            $data = $preguntas->map(function ($pregunta) {

                return [
                    'id' => $pregunta->id,
                    'numero' => $pregunta->num_pregunta, // 👈 CLAVE DEL FRONTEND
                    'titulo' => $pregunta->titulo,
                    'respuestas' => $pregunta->respuestas->map(function ($r) {
                        return [
                            'id' => $r->id,
                            'titulo' => $r->titulo,
                            'valor' => (int) $r->valor
                        ];
                    })->values()
                ];
            })->values();

            echo json_encode([
                'success' => true,
                'tema' => [
                    'id' => $calendario->tema->id,
                    'numero' => $calendario->tema->num_tema,
                    'titulo' => $calendario->tema->titulo
                ],
                'preguntas' => $data
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function guardarRespuesta(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $data = json_decode(file_get_contents('php://input'), true);

            $idCalendario = (int) ($data['calendario'] ?? 0);
            $pregunta     = (int) ($data['pregunta'] ?? 0);
            $resultado    = (int) ($data['valor'] ?? null);

            if ($idCalendario <= 0 || $pregunta <= 0 || $resultado === null) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Datos inválidos'
                ]);
                return;
            }

            $calendario = CursoCalendario::query()
                ->where('id', $idCalendario)
                ->where('id_personal', $this->userId())
                ->where('estado', 0)
                ->firstOrFail();

            CursoEvaluacion::updateOrCreate(
                [
                    'id_calendario' => $calendario->id,
                    'no_pregunta'   => $pregunta
                ],
                [
                    'resultado' => $resultado
                ]
            );

            echo json_encode([
                'success' => true
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function finalizarEvaluacion(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $input = json_decode(file_get_contents('php://input'), true);
            $idCalendario = (int) ($input['calendario'] ?? 0);
            $calendario = CursoCalendario::query()

                ->where('id', $idCalendario)
                ->where('id_personal', $this->userId())
                ->where('estado', 0)
                ->firstOrFail();

            $preguntas = CursoTemaPregunta::query()
                ->where('id_tema', $calendario->id_tema)
                ->count();

            $contestadas = CursoEvaluacion::query()
                ->where('id_calendario', $calendario->id)
                ->count();

            if ($contestadas < $preguntas) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Debes responder todas las preguntas.'

                ]);
            }

            $puntos = CursoEvaluacion::query()
                ->where('id_calendario', $calendario->id)
                ->sum('resultado');
            $porcentaje = (int) round(
                ($puntos / $preguntas) * 100
            );

            $calendario->update([
                'fecha_real' => date('Y-m-d'),
                'resultado' => $porcentaje,
                'estado' => 1
            ]);

            echo json_encode([
                'success' => true,
                'resultado' => [
                    'porcentaje' => $porcentaje,
                    'titulo' => $this->tituloResultado($porcentaje),
                    'mensaje' => $this->mensajeResultado($porcentaje),
                    'icono' => $this->iconoResultado($porcentaje),
                    'color' => $this->colorResultado($porcentaje),
                    'aprobado' => $porcentaje >= 60
                ]

            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    private function tituloResultado(int $resultado): string
    {
        return $resultado >= 60
            ? '¡Felicidades!'
            : 'Evaluación no acreditada';
    }

    private function mensajeResultado(int $resultado): string
    {
        return match (true) {
            $resultado >= 90 => 'Excelente desempeño.',
            $resultado >= 80 => 'Muy buen trabajo.',
            $resultado >= 60 => 'Has acreditado la evaluación.',
            default => 'Puedes volver a intentarlo posteriormente.'
        };
    }

    private function colorResultado(int $resultado): string
    {
        return match (true) {
            $resultado >= 90 => 'success',
            $resultado >= 80 => 'primary',
            $resultado >= 60 => 'warning',
            default => 'danger'
        };
    }

    private function iconoResultado(int $resultado): string
    {
        return $resultado >= 60
            ? 'ti ti-rosette-discount-check-filled'
            : 'ti ti-circle-x-filled';
    }

    //----------------------------------------------------------------------------
    //----------------------------------------------------------------------------

    public function cursosModulos(int $idModulo): void
    {
        $modulo = CursoModulo::query()
            ->findOrFail($idModulo);

        $temas = CursoTema::query()
            ->where('id_modulo', $idModulo)
            ->orderBy('num_tema')
            ->get();

        $categoria = $temas->first()?->categoria;

        $layout = match ($categoria) {
            'SASISOPA' => 'sasisopa',
            'SGM'      => 'sgm',
            default    => 'sasisopa',
        };

        $temas = $temas->map(function ($tema) {

            $calendarios = CursoCalendario::query()
                ->where('id_tema', $tema->id)
                ->where('id_personal', $this->userId())
                ->get();

            return [
                'id'         => $tema->id,
                'numero'     => $tema->num_tema,
                'titulo'     => $tema->titulo,
                'total'      => $calendarios->count(),
                'pendientes' => $calendarios->where('estado', 0)->count(),
                'categoria'  => $tema->categoria,
            ];
        });


        $title = "MÓDULO {$modulo->num_modulo} - {$modulo->titulo}";


        Breadcrumb::add('Home', '/home');
        Breadcrumb::add(
            $categoria ?? 'Cursos',
            '/' . mb_strtolower($categoria ?? 'sasisopa', 'UTF-8')
        );
        Breadcrumb::add(
            'Cursos',
            '/' . mb_strtolower($categoria ?? 'sasisopa', 'UTF-8') . '/cursos'
        );
        Breadcrumb::add(
            $title,
            ''
        );

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title'          => $title,
            'permisos'       => $permisos,
            'filtro_usuario' => $this->filtro_usuario,
            'modulo'         => $modulo,
            'temas'          => $temas,
            'categoria'      => $categoria,
            'scripts'        => [
                '/js/vendor.min.js',
                '/js/cursos/modulo.action.init.js?v=' . time(),
            ]
        ];

        View::render(
            'cursos/modulo',
            $data,
            $layout
        );
    }

    public function detalleTema(int $idTema): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $tema = CursoTema::query()
                ->with('modulo')
                ->findOrFail($idTema);

            $calendarios = CursoCalendario::query()
                ->where('id_personal', $this->userId())
                ->where('id_tema', $idTema)
                ->orderByDesc('fecha_programada')
                ->get()
                ->map(function ($c) {

                    if ($c->resultado == 0) {

                        $titulo = 'Pendiente';
                        $color = 'text-danger';
                        $pdf = false;
                    } elseif ($c->resultado >= 90) {

                        $titulo = $c->resultado . ' (Excelente)';
                        $color = 'text-success';
                        $pdf = true;
                    } elseif ($c->resultado >= 80) {

                        $titulo = $c->resultado . ' (Bueno)';
                        $color = 'text-primary';
                        $pdf = true;
                    } elseif ($c->resultado >= 60) {

                        $titulo = $c->resultado . ' (Regular)';
                        $color = 'text-warning';
                        $pdf = true;
                    } else {

                        $titulo = $c->resultado . ' (Malo)';
                        $color = 'text-danger';
                        $pdf = false;
                    }

                    return [
                        'id' => $c->id,
                        'fecha' => formatearFecha(
                            $c->fecha_programada->format('Y-m-d')
                        ),
                        'resultado' => $c->resultado,
                        'resultado_texto' => $titulo,
                        'resultado_color' => $color,
                        'estado' => $c->estado,
                        'reconocimiento' => $pdf
                    ];
                });

            echo json_encode([
                'success' => true,
                'modulo' => $tema->modulo->num_modulo . '. ' . $tema->modulo->titulo,
                'tema' => $tema->num_tema . '. ' . $tema->titulo,
                'calendarios' => $calendarios
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    //----------------------------------------------------------------------------
    //----------------------------------------------------------------------------

    public function descargar(int $id)
    {

        $pdf = new FPDF();

        // ======================
        // OBTENER DATOS
        // ======================
        $cal = CursoCalendario::with([
            'tema.modulo',
            'usuario'
        ])->findOrFail($id);

        $titulo = $cal->tema->titulo;
        $modulo = $cal->tema->modulo->titulo ?? '';
        $fecha = $cal->fecha_programada;
        $nombre = $cal->usuario->nombre;

        $observacion = $cal->observaciones
            ? ' (' . $cal->observaciones . ')'
            : '';

        // ======================
        // PDF
        // ======================
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->AddPage();

        // Fondo
        $pdf->Image(
            asset('images/cursos/fondo-2024.jpg'),
            0,
            0,
            300,
            210
        );

        // Helper encoding
        $txt = fn($t) => mb_convert_encoding($t, 'ISO-8859-1', 'UTF-8');

        // ======================
        // NOMBRE
        // ======================
        $pdf->SetFont('Arial', '', 30);
        $pdf->SetY(100);
        $pdf->Cell(0, 10, $txt($nombre), 0, 0, 'C');

        // ======================
        // TEMA
        // ======================
        $pdf->SetY(133);

        $pdf->SetFont('Arial', '', 17);
        $pdf->SetX(70);
        $pdf->SetMargins(68, 0);

        $pdf->MultiCell(
            0,
            6,
            $txt($titulo . $observacion),
            0,
            'C'
        );

        // ======================
        // FECHA
        // ======================
        $pdf->SetY(160);   // Siempre en la misma posición
        $pdf->SetX(77);

        $pdf->SetFont('Arial', '', 12);

        $pdf->Cell(
            0,
            10,
            $txt(formatearFecha($fecha)),
            0,
            0
        );

        if (isset($estacion->firma)) {
            $extension = pathinfo($estacion->firma, PATHINFO_EXTENSION);
            $pdf->Image(PUBLIC_PATH . '/uploads/firma-personal/' . $estacion->firma, '175', '151', '50', '0', $extension);
        }

        // ======================
        // OUTPUT
        // ======================
        return $pdf->Output('I', 'reconocimiento.pdf');
    }

    public function descargarAll(int $year, int $idModulo)
    {
        $idEstacion = $this->estacionId();
        $estacion = Estacion::findOrFail($idEstacion);

        // ======================
        // HELPER TEXTO
        // ======================
        $safeText = function ($text) {
            if (!$text) return '';

            $text = trim($text);
            $text = strip_tags($text);
            $text = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text);

            return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        };

        // ======================
        // CONSULTA (IGUAL QUE TU SQL)
        // ======================
        $calendarios = CursoCalendario::with([
            'tema.modulo',
            'usuario'
        ])
            ->whereYear('fecha_programada', $year)
            ->where('id_estacion', $idEstacion)
            ->where('resultado', '>=', 60)
            ->whereHas('tema', function ($q) use ($idModulo) {
                $q->where('id_modulo', $idModulo);
            })
            ->orderBy('fecha_programada', 'desc')
            ->get();

        // ======================
        // PDF
        // ======================
        $pdf = new FPDF('L', 'mm', 'A4');

        foreach ($calendarios as $cal) {

            if (!$cal->usuario || !$cal->tema) continue;

            $nombre = $safeText($cal->usuario->nombre);
            $titulo = $safeText($cal->tema->titulo);

            // ⚠️ IMPORTANTE: primero formateas, luego conviertes
            $fechaFormateada = formatearFecha($cal->fecha_programada);
            $fecha = $safeText($fechaFormateada);

            $observacion = $cal->observaciones
                ? ' (' . $safeText($cal->observaciones) . ')'
                : '';

            // ======================
            // HOJA
            // ======================
            $pdf->AddPage();

            $pdf->SetTitle(substr($titulo, 0, 100));

            $pdf->Image(
                asset('images/cursos/fondo-2024.jpg'),
                0,
                0,
                300,
                210
            );

            // ======================
            // NOMBRE
            // ======================
            $pdf->SetFont('Arial', '', 30);
            $pdf->SetY(100);
            $pdf->Cell(0, 10, $nombre, 0, 0, 'C');

            // ======================
            // TEMA
            // ======================
            $pdf->Ln(33);

            $pdf->SetFont('Arial', '', 17);
            $pdf->SetX(70);
            $pdf->SetMargins(68, 0);

            $pdf->MultiCell(
                0,
                6,
                $titulo . $observacion,
                0,
                'C'
            );

            // ======================
            // FECHA
            // ======================
            $pdf->Ln(20);

            $pdf->SetFont('Arial', '', 12);
            $pdf->SetX(77);

            $pdf->Cell(
                0,
                10,
                $fecha,
                0,
                0
            );
        }

        if (isset($estacion->firma)) {
            $extension = pathinfo($estacion->firma, PATHINFO_EXTENSION);
            $pdf->Image(PUBLIC_PATH . '/uploads/firma-personal/' . $estacion->firma, '175', '151', '50', '0', $extension);
        }

        return $pdf->Output(
            'I',
            "reconocimientos_modulo_{$idModulo}_{$year}.pdf"
        );
    }
}
