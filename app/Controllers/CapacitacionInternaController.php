<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sasisopa\CursoModulo;
use App\Models\Sasisopa\CursoTema;
use App\Models\Sasisopa\CursoCalendario;
use Dompdf\Dompdf;
use Dompdf\Options;

class CapacitacionInternaController extends BaseController
{

    protected string $modulo = 'sasisopa';
    public function index(){

        $title = 'Programa de capacitación interna';
         // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('6. COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO', '/sasisopa/competencia-personal-capacitacion-entrenamiento');
        Breadcrumb::add($title, '');

        $cursos = CursoModulo::withCount('temas')
        ->orderBy('num_modulo', 'asc')
        ->get();

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'cursos' => $cursos,
             'links' =>[],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/capacitacioninterna/capacitacioninterna.actions.init.js',
            ],
            'help' => false
        ];
        
        View::render('capacitacioninterna/index', $data,'sasisopa');
        
    }

    public function capacitacionInterna(int $idModulo,int $idTema){

        $modulo = CursoModulo::find($idModulo);
        $tema = CursoTema::find($idTema);
        $temas = CursoTema::where('id_modulo', $idModulo)->get();

        $title = $modulo->titulo;
         // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('6. COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO', '/sasisopa/competencia-personal-capacitacion-entrenamiento');
        Breadcrumb::add('Capacitación Interna', '/sasisopa/competencia-personal-capacitacion-entrenamiento/capacitacion-interna');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'idModulo' => $idModulo,
            'nom_modulo' => $modulo->titulo,
            'idTema' => $idTema,
            'nom_tema' => $tema->titulo,
            'temas' => $temas,
             'links' =>[
                 '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
                
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/capacitacioninterna/capacitacioninterna.datatable.init.js?v=' . time(),
                '/js/capacitacioninterna/capacitacioninterna.actions.init.js?v=' . time(),
            ],
            'help' => false
        ];
        
        View::render('capacitacioninterna/capacitacion-interna', $data,'sasisopa');
       

    }

    public function datatableCapacitacionInterna(int $idTema, int $year = null)
    {
        $permisoCrear   = ModuloService::validaPermiso($this->modulo, 'crear');

        $anio = $year ?? date('Y');

        $usuarios = Usuario::with([
        'puesto',
        'capacitaciones' => function ($q) use ($idTema, $anio) {
            $q->where('id_tema', $idTema)
              ->whereYear('fecha_programada', $anio)
              ->latest('fecha_programada')
              ->limit(1);
            }
        ])
        ->where('id_gas', $this->estacionId())
        ->where('id_puesto', '!=', 1)
        ->activo()
        ->get();

        $data = $usuarios->map(function ($user) {

        $curso = $user->capacitaciones->first();

        $resultado = $curso->resultado ?? null;
        $estado = $curso->estado ?? null;

        if ($estado === 0 || is_null($resultado)) {
            $color = '';
            $texto = 'S/I';
        } elseif ($resultado >= 90) {
            $color = 'text-success';
            $texto = "{$resultado}% Excelente";
        } elseif ($resultado >= 80) {
            $color = 'text-primary';
            $texto = "{$resultado}% Bueno";
        } elseif ($resultado >= 60) {
            $color = 'text-warning';
            $texto = "{$resultado}% Regular";
        } else {
            $color = 'text-danger';
            $texto = "{$resultado}% Malo";
        }

        return [
            'id' => $user->id,
            'nombre' => $user->nombre,
            'telefono' => $user->telefono,
            'email' => $user->email,
            'puesto' => $user->puesto->tipo_puesto ?? 'S/I',

            'fecha_programada' => $curso?->fecha_programada,
            'resultado' => $resultado,
            'texto_resultado' => $texto,
            'color' => $color,
        ];
    });

        echo json_encode([
            'data' => $data,
            "permisos" => [
                "crear" => $permisoCrear
            ]
        ]);
    }

    public function createProgramacionInterna(){

     header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

         if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }    

       
        $fecha_programada = sanitize_input($data['fecha_programada'] ?? null, 'string');
        $id_usuario = sanitize_input($data['id_usuario'] ?? null, 'int');
        $id_tema = sanitize_input($data['id_tema'] ?? null, 'int');

         if (!$fecha_programada) {
        echo json_encode([
            'success' => false,
            'message' => 'Campos obligatorios'
        ]);
        exit;
        }

    CursoCalendario::create([
        'fecha_programada' => $fecha_programada,
        'fecha_real' => '',
        'id_estacion' => $this->estacionId(),
        'id_personal' => $id_usuario,
        'id_tema' => $id_tema,
        'resultado' => 0,
        'estado' => 0,
    ]);

    echo json_encode([        
        'success' => true,
        'message' => 'Capacitación interna creada correctamente',
        ]);

    }

    public function getCursosInternos(int $idUsuario, int $idTema)
    {
        $data = CursoCalendario::where('id_personal', $idUsuario)
            ->where('id_tema', $idTema)
            ->orderByDesc('fecha_programada')
            ->get();

        echo json_encode($data);
    }

    public function deleteCursosInterno()
    {

    $data = json_decode(file_get_contents("php://input"), true);

        try {

            CursoCalendario::where('id', $data['id'])->delete();

            echo json_encode([
                'success' => true,
                'message'=> 'Capacitación interna eliminada correctamente'
                ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message'=> 'Error al eliminar la capacitación interna'
                ]);
        }
       
    }

    public function buscarCapacitacionInterna(int $year)
    {
        $idEstacion = $this->estacionId();

        $modulos = CursoModulo::whereHas('temas.calendarios', function ($q) use ($year, $idEstacion) {
            $q->whereYear('fecha_programada', $year)
            ->whereHas('usuario', function ($q2) use ($idEstacion) {
                $q2->where('id_gas', $idEstacion)
                    ->where('id_puesto', '<>', 1)
                    ->where('estatus', 0);
            });
        })
        ->with([
            'temas.calendarios' => function ($q) use ($year, $idEstacion) {
                $q->whereYear('fecha_programada', $year)
                ->whereHas('usuario', function ($q2) use ($idEstacion) {
                    $q2->where('id_gas', $idEstacion)
                        ->where('id_puesto', '<>', 1)
                        ->where('estatus', 0);
                })
                ->orderBy('fecha_programada', 'desc');
            },
            'temas.calendarios.usuario.puesto'
        ])
        ->orderBy('num_modulo')
        ->get();

    

        echo '<div>';
   
        echo '<h4>Año: '.$year.'</h4>';

        foreach ($modulos as $modulo) {

            echo '<div class="bg-info p-2 mt-2">';
            echo '<div class="text-white fw-bold fs-6">'.$modulo->num_modulo.'. '.$modulo->titulo.'</div>';
            echo '</div>';

            echo '<a class="btn btn-light mt-3" target="_blank" href="/sasisopa/competencia-personal-capacitacion-entrenamiento/descargar-capacitacion-interna/'.$year.'/'.$modulo->id.'">
                    <i class="ti ti-download"></i> Programa de Capacitacion y adiestramiento
                </a>';

            echo '<a class="btn btn-light mt-3" target="_blank" href="/cursos/descargar/'.$year.'/'.$modulo->id.'">
                    <i class="ti ti-download"></i> Reconocimientos personal
                </a>';

            foreach ($modulo->temas as $tema) {

                if ($tema->calendarios->isEmpty()) continue;

                echo '<div class="mt-3">';
                echo '<div class="fs-5">'.$tema->num_tema.'. '.$tema->titulo.'</div>';

                echo '<table class="table table-sm table-bordered mt-2">';
                echo '<thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Puesto</th>
                            <th>Fecha</th>
                            <th>Resultado</th>
                            <th class="text-center align-middle"><i class="ti ti-download fs-6"></i></th>
                        </tr>
                    </thead>';
                echo '<tbody>';

                $calendarios = $tema->calendarios
                    ->groupBy('id_personal')
                    ->map(function ($items) {
                        return $items->first();
                    });

                foreach ($calendarios as $c) {

                    if (!$c->usuario) continue;

                    echo '<tr>';
                    echo '<td>'.$c->usuario->nombre.'</td>';
                    echo '<td>'.($c->usuario->puesto->tipo_puesto ?? '').'</td>';
                    echo '<td>'.formatearFecha($c->fecha_programada).'</td>';
                    echo '<td>'.$this->resultadoHTML($c).'</td>';
                    echo '<td class="text-center align-middle">'.$this->reconocimiento($c).'</td>';
                    echo '</tr>';
                }

                echo '</tbody>';
                echo '</table>';
                echo '</div>';
            }
        }

        echo '</div>';


    }

public function resultadoHTML($c)
{
    if ($c->estado == 0) return 'S/I';

    $r = $c->resultado;

    if ($r >= 90) return "<span class='text-success'>{$r}% Excelente</span>";
    if ($r >= 80) return "<span class='text-primary'>{$r}% Bueno</span>";
    if ($r >= 60) return "<span class='text-warning'>{$r}% Regular</span>";

    return "<span class='text-danger'>{$r}% Malo</span>";
}

public function reconocimiento($c){

 if ($c->estado == 0) return '<i class="ti ti-x fs-6"></i>';

    $r = $c->resultado;

    if ($r >= 90) return '<a href="/cursos/descargar/'.$c->id.'" target="_blank"><i class="ti ti-download fs-6 text-danger"></i></a>';
    if ($r >= 80) return '<a href="/cursos/descargar/'.$c->id.'" target="_blank"><i class="ti ti-download fs-6 text-danger"></i></a>';
    if ($r >= 60) return '<a href="/cursos/descargar/'.$c->id.'" target="_blank"><i class="ti ti-download fs-6 text-danger"></i></a>';

    return '<i class="ti ti-x fs-6"></i>';

}


public function descargarCapacitacionInterna(int $year, int $idModulo)
{
    $idEstacion = $this->estacionId();

    $registro = Estacion::find($idEstacion);

    if (!$registro) {
        return "No se encontró la información";
    }

    $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';
    $apoderadolegal = $registro->apoderado_legal;

    // ======================
    // DATA (OPTIMIZADA)
    // ======================
    $modulo = CursoModulo::with([
        'temas',
    ])->findOrFail($idModulo);

    $usuarios = Usuario::with('puesto')
        ->where('id_gas', $idEstacion)
        ->where('id_puesto', '<>', 1)
        ->where('estatus', 0)
        ->get();

    // ======================
    // HTML
    // ======================
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <title>Capacitación interna</title>
    <link rel="stylesheet" href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">
    </head>
    <body>

    <table class="table">
        <tr>
            <td class="text-center">
                <img src="'.$logo.'" style="width:130px">
            </td>
            <td colspan="2" class="text-center">
                <b>Programa de Capacitacion y adiestramiento</b>
            </td>
            <td class="text-center"><b>Fo.ADMONGAS.009</b></td>
        </tr>
        <tr>
            <td class="text-center">Realizado por:<br>Nelly Estrada Garcia</td>
            <td class="text-center">Revisado por:<br>Eduardo Galicia Flores</td>
            <td class="text-center">Autorizado por:<br>'.$apoderadolegal.'</td>
            <td class="text-center">Fecha de aprobación:<br>01/10/2018</td>
        </tr>
    </table>
    ';

    // ======================
    // MODULO
    // ======================
    $html .= '
    <div class="bg-info text-white p-2 mt-3">
        <b>'.$modulo->num_modulo.'. '.$modulo->titulo.'</b>
    </div>
    ';

    foreach ($modulo->temas as $tema) {

        $html .= '
        <div class="bg-light p-2 mt-2 mb-2">
            <b>'.$tema->num_tema.'. '.$tema->titulo.'</b>
        </div>

        <table class="table table-bordered table-sm" style="font-size:.6em;">
        <thead>
            <tr>
                <th>No</th>
                <th>Nombre Usuario</th>
                <th>Puesto</th>
                <th>Fecha Programada</th>
                <th>Observaciones</th>
                <th>Resultado</th>
            </tr>
        </thead>
        <tbody>
        ';

        $num = 1;

        foreach ($usuarios as $usuario) {

            // 🔥 reemplazo de FechaProgramada()
            $cal = CursoCalendario::where('id_personal', $usuario->id)
                ->where('id_tema', $tema->id)
                ->whereYear('fecha_programada', $year)
                ->orderBy('fecha_programada')
                ->first();

            if ($cal) {
                $estado = $cal->estado;
                $resultado = $cal->resultado;
                $fecha = formatearFecha($cal->fecha_programada);
                $observaciones = $cal->observaciones ?? '';
            } else {
                $estado = 0;
                $resultado = null;
                $fecha = 'S/I';
                $observaciones = '';
            }

            // ======================
            // RESULTADO
            // ======================
            if ($estado == 1) {
                if ($resultado >= 90) {
                    $title = "<span class='text-success'>{$resultado}% Excelente</span>";
                } elseif ($resultado >= 80) {
                    $title = "<span class='text-primary'>{$resultado}% Bueno</span>";
                } elseif ($resultado >= 60) {
                    $title = "<span class='text-warning'>{$resultado}% Regular</span>";
                } else {
                    $title = "<span class='text-danger'>{$resultado}% Malo</span>";
                }
            } else {
                $title = "<b>Pendiente</b>";
            }

            $html .= "
            <tr>
                <td class='text-center'>{$num}</td>
                <td>{$usuario->nombre}</td>
                <td class='text-center'>".($usuario->puesto->tipo_puesto ?? '')."</td>
                <td class='text-center'>{$fecha}</td>
                <td class='text-center'>{$observaciones}</td>
                <td class='text-center'>{$title}</td>
            </tr>
            ";

            $num++;
        }

        $html .= '</tbody></table>';
    }

    $html .= '</body></html>';

    // ======================
    // PDF
    // ======================
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->stream(
        "Capacitacion-interna-{$year}.pdf",
        ["Attachment" => true]
    );
}

}