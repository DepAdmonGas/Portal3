<?php
namespace App\Controllers;
use Illuminate\Database\Capsule\Manager as DB;
use App\Models\Estacion;
use App\Core\View;

class EstacionController extends BaseController{

    public function viewIndex(){
        
        $data = [
            'title' => 'Estaciones',
            'scripts' => []
        ];
        
        View::render('estaciones/index', $data,'main');
    }

    public function viewCrear(){
        
         $data = [
            'title' => 'Crear Estacion',
            'scripts' => []
        ];
        
        View::render('estaciones/crear', $data,'main');
    }

    public function listar()
    {

    $estaciones = Estacion::orderBy('numlista')->get();

    header('Content-Type: application/json');
    echo json_encode($estaciones);
    exit;

    }


    public function crearEstacion()
    {
    header('Content-Type: application/json');

    try {

        /* =====================================================
         * 1️⃣ Obtener datos (JSON o POST)
         * ===================================================== */
        $input = json_decode(file_get_contents('php://input'), true);
        $data = is_array($input) && !empty($input) ? $input : $_POST;

        if (empty($data)) {
            echo json_encode([
                'ok' => false,
                'type' => 'error',
                'message' => 'No se recibieron datos'
            ]);
            return;
        }

        /* =====================================================
         * 2️⃣ Labels personalizados (nombres legibles)
         * ===================================================== */
        $labels = [
            'nombre'             => 'Nombre de la estación',
            'permisocre'         => 'Permiso CRE',
            'razonsocial'        => 'Razón social',
            'rfc'                => 'RFC',
            'direccioncompleta'  => 'Dirección completa',
            'di_estado'          => 'Estado',
            'di_municipio'       => 'Municipio',
            'apoderado_legal'    => 'Apoderado Legal',
            'fecha_autorizacion' => 'Fecha de autorización',
            'distmax'            => 'Distancia máxima',
        ];

        /* =====================================================
         * 3️⃣ Validaciones mínimas
         * ===================================================== */
        $requeridos = array_keys($labels);

        foreach ($requeridos as $campo) {
            if (!isset($data[$campo]) || $data[$campo] === '') {

                $nombre = $labels[$campo];

                echo json_encode([
                    'ok' => false,
                    'type' => 'error',
                    'message' => "El campo {$nombre} es obligatorio"
                ]);
                return;
            }
        }

        /* =====================================================
         * 4️⃣ Preparar datos para Eloquent
         * ===================================================== */
        
        $payload = [
            'nombre'             => trim($data['nombre']),
            'es'                 => $data['es'] ?? '',
            'permisocre'         => trim($data['permisocre']),
            'razonsocial'        => trim($data['razonsocial']),
            'rfc'                => strtoupper(trim($data['rfc'])),
            'direccioncompleta'  => trim($data['direccioncompleta']),
            'di_estado'          => trim($data['di_estado']),
            'di_municipio'       => trim($data['di_municipio']),
            'apoderado_legal'    => $data['apoderado_legal'] ?? '',
            'firma'              => $data['firma'] ?? '',
            'politica'           => $data['politica'] ?? '',
            'mision'             => $data['mision'] ?? '',
            'vision'             => $data['vision'] ?? '',
            'franquicia'         => $data['franquicia'] ?? '',
            'producto_uno'       => $data['producto_uno'] ?? '',
            'producto_dos'       => $data['producto_dos'] ?? '',
            'producto_tres'      => $data['producto_tres'] ?? '',
            'sasisopa'           => $data['sasisopa'] ?? '',
            'fecha_autorizacion' => $data['fecha_autorizacion'] ?? '',
            'organigrama'        => $data['organigrama'] ?? '',
            'volumetrico'        => $data['volumetrico'] ?? '',
            'latitud'            => 0,
            'longitud'           => 0,
            'distmax'            => (float) $data['distmax'],
            'ubicacion'          => 0,
            'estatus'            => 1
        ];

        /* =====================================================
         * 5️⃣ Transacción
         * ===================================================== */
        $estacion = DB::transaction(function () use ($payload) {
            $payload['numlista'] = Estacion::siguienteNumlista();
            return Estacion::guardar($payload);
        });

        /* =====================================================
         * 6️⃣ Respuesta OK
         * ===================================================== */
        echo json_encode([
            'ok' => true,
            'type' => 'success',
            'message' => 'Estación creada correctamente',
            //'id' => $estacion->id
        ]);

    } catch (\Throwable $e) {
        echo json_encode([
            'ok' => false,
            'type' => 'error',
            //'message' => 'Error interno del servidor',
            'message' => $e->getMessage() // quitar en producción
        ]);
    }
}


}