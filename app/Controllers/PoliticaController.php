<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\View;
use App\Models\Estacion;
use App\Models\Sasisopa\PoliticaListaComprobacion;
use App\Models\Sasisopa\PoliticaListaComprobacionDetalle;
use App\Services\ModuloService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Capsule\Manager as Capsule;

class PoliticaController extends BaseController
{
    protected string $modulo = 'sasisopa';

    public function politica(){

        $title = '1. POLÍTICA';
        // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
             'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',
                '/js/sasisopa/politica.datatable.init.js?v=' . time(),
                '/js/sasisopa/politica.actions.init.js?v=' . time(),
                '/js/sasisopa/listacomprobacion.actions.init.js?v=' . time(),
                '/js/asistencia/listaasistencia.datatable.init.js?v=' . time(),
                '/js/asistencia/listaasistencia.crear.init.js?v=' . time(),
            ],
            'help' => true
        ];
        
        View::render('sasisopa/politica', $data,'sasisopa');

    }

    public function updatePolitica(){

        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $politica = $data['politica'] ?? null;
        $mision = $data['mision'] ?? null;
        $vision = $data['vision'] ?? null;


        if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para editar'
            ]);
            return;
        }

        $registro = Estacion::find($this->estacionId());

        if (!$registro) {
            echo json_encode([
                'success' => false,
                'message' => 'Registro no encontrado'
            ]);
            return;
        }

        $registro->politica = $politica;
        $registro->mision = $mision;
        $registro->vision = $vision;
        $registro->save();

        echo json_encode([
            'success' => true,
            'message' => 'Politica actualizada correctamente'
        ]);


    }

    public function descargarPolitica()
    {
        $registro = Estacion::find($this->estacionId());

        if (!$registro) {
            echo "No se encontró la información";
            return;
        }

    
        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $permisocre   = $registro->permisocre;
        $razonsocial   = $registro->razonsocial;
        $direccioncompleta   = $registro->direccioncompleta;
        
        $politica = $registro->politica;
        $mision   = $registro->mision;
        $vision   = $registro->vision;

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <title>POLÍTICA</title>
        <link rel="stylesheet" href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">
        </head>

        <body>

        <div class="text-center">
            <img src="'.$logo.'" width="150">
        </div>

        <div class="text-center mt-4">'.$permisocre.'</div>
        <div class="text-center">'.$razonsocial.'</div>
        <div class="text-center">'.$direccioncompleta.'</div>

        <h2 class="mt-2 text-primary">Política</h2>
        <p>'.htmlspecialchars($politica).'</p>

        <h2 class="text-primary">Misión</h2>
        <p>'.htmlspecialchars($mision).'</p>

        <h2 class="text-primary">Visión</h2>
        <p>'.htmlspecialchars($vision).'</p>
        
        </body>
        </html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dompdf->stream("politica.pdf", ["Attachment" => true]);
    }

    public function datatableListaComprobacion(){

        // permisos
        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
        $permisoDescargar   = ModuloService::validaPermiso($this->modulo, 'descargar');

        $data = PoliticaListaComprobacion::where('id_estacion', $this->estacionId())
        ->orderBy('fecha','desc')
        ->get();

         echo json_encode([
            "data" => $data,
            "permisos" => [
                "eliminar" => $permisoEliminar,
                "editar"   => $permisoEditar,
                "descargar" => $permisoDescargar
            ]
        ]);
        
        exit;
    }

    public function createListaComprobacion(){

        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $fecha = $data['fecha'];

         if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

         if ($fecha == "") {
        echo json_encode([
            'success' => false,
            'message' => 'Campos obligatorios faltantes'
        ]);
        return;
        }

        Capsule::beginTransaction();

        try {

            // CREAR LISTA
            $lista = PoliticaListaComprobacion::create([
                'id_estacion' => $this->estacionId(),
                'id_usuario'  => $this->userId(),
                'fecha'       => $fecha,
                'asistentes'  => $data['asistentes'] ?? '',
                'comentarios' => $data['comentarios'] ?? ''
            ]);

            // CRITERIOS
            $criterios = [
                'R1' => 'La política es adecuada a la naturaleza magnitud y actividades del proyecto',
                'R2' => 'La política incluye la seguridad operativa',
                'R3' => 'La política incluye la protección al medio ambiente',
                'R4' => 'Los trabajadores, la alta dirección, los clientes y los subcontratistas tienen conocimiento de la política',
                'R5' => 'La política se revisa periódicamente',
                'R6' => 'La política se compromete al control de los peligros e impactos ambientales',
                'R7' => 'La política considera la participación del personal'
            ];

            // INSERT DETALLE
            foreach ($criterios as $key => $criterio) {

                PoliticaListaComprobacionDetalle::create([
                    'id_lista_comprobacion' => $lista->id,
                    'criterio' => $criterio,
                    'resultado' => $data[$key] ?? ''
                ]);
            }

            Capsule::commit();

            echo json_encode([
            'success' => true,
            'message' => 'Lista de comprobación guardada correctamente'
            ]);

        } catch (\Throwable $e) {

        Capsule::rollBack();

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);

        }        

    }

    public function updateListaComprobacion(){

         header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $fecha = $data['fecha'];

         if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para editar'
            ]);
            return;
        }

         if ($fecha == "") {
        echo json_encode([
            'success' => false,
            'message' => 'Campos obligatorios faltantes'
        ]);
        return;
        }

        try {

        Capsule::beginTransaction();

        $lista = PoliticaListaComprobacion::find($data['id']);

        if (!$lista) {
            throw new \Exception('Registro no encontrado');
        }

        // 🔹 UPDATE LISTA
        $lista->update([
            'fecha'       => $data['fecha'],
            'asistentes'  => $data['asistentes'] ?? '',
            'comentarios' => $data['comentarios'] ?? ''
        ]);

        // ELIMINAR DETALLES
        PoliticaListaComprobacionDetalle::where('id_lista_comprobacion', $lista->id)->delete();

        // REINSERTAR
        $criterios = [
            'R1' => 'La política es adecuada a la naturaleza magnitud y actividades del proyecto',
                'R2' => 'La política incluye la seguridad operativa',
                'R3' => 'La política incluye la protección al medio ambiente',
                'R4' => 'Los trabajadores, la alta dirección, los clientes y los subcontratistas tienen conocimiento de la política',
                'R5' => 'La política se revisa periódicamente',
                'R6' => 'La política se compromete al control de los peligros e impactos ambientales',
                'R7' => 'La política considera la participación del personal'
        ];

        foreach ($criterios as $key => $criterio) {

            PoliticaListaComprobacionDetalle::create([
                'id_lista_comprobacion' => $lista->id,
                'criterio' => $criterio,
                'resultado' => $data[$key] ?? ''
            ]);
        }

        Capsule::commit();

        echo json_encode([
            'success' => true,
            'message' => 'Actualizado correctamente'
        ]);

    } catch (\Throwable $e) {

        Capsule::rollBack();

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    }

    public function getListaComprobacion($id)
    {
        header('Content-Type: application/json; charset=utf-8');

        $lista = PoliticaListaComprobacion::with('detalles')
            ->where('id_estacion', $this->estacionId())
            ->find($id);

        if (!$lista) {
            echo json_encode([
                'success' => false,
                'message' => 'Registro no encontrado'
            ]);
            return;
        }

        // transformar detalle → R1, R2...
        $map = [
            'La política es adecuada a la naturaleza magnitud y actividades del proyecto' => 'R1',
            'La política incluye la seguridad operativa' => 'R2',
            'La política incluye la protección al medio ambiente' => 'R3',
            'Los trabajadores, la alta dirección, los clientes y los subcontratistas tienen conocimiento de la política' => 'R4',
            'La política se revisa periódicamente' => 'R5',
            'La política se compromete al control de los peligros e impactos ambientales' => 'R6',
            'La política considera la participación del personal' => 'R7',
        ];

        $respuestas = [];

        foreach ($lista->detalles as $det) {
            if (isset($map[$det->criterio])) {
                $respuestas[$map[$det->criterio]] = $det->resultado;
            }
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $lista->id,
                'fecha' => $lista->fecha,
                'asistentes' => $lista->asistentes,
                'comentarios' => $lista->comentarios,
                ...$respuestas
            ]
        ]);
    }

    public function deleteListaComprobacion(){

         header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;

         if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para eliminar'
            ]);
            return;
        }

         if (!$id) {
            echo json_encode(['success' => false,'message' => 'ID requerido']);
            return;
        }

         Capsule::beginTransaction();
         try {

            // Buscar registro
            $reporte = PoliticaListaComprobacion::find($id);

            if (!$reporte) {
                throw new \Exception('Registro no encontrado');
            }

            $detalle = PoliticaListaComprobacionDetalle::where('id_lista_comprobacion',$id);

            if (!$detalle) {
                throw new \Exception('Registro no encontrado');
            }

            // Eliminar registro
            $reporte->delete();
            $detalle->delete();

            Capsule::commit();

            echo json_encode([
                'success' => true,
                'message' => 'Reporte eliminado correctamente'
            ]);

        } catch (\Throwable $e) {

            Capsule::rollBack();

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

    }

    public function descargarListaComprobacion($id)
    {
        header('Content-Type: application/pdf');

        $estacion = Estacion::find($this->estacionId());
        $reporte  = PoliticaListaComprobacion::find($id);

        if (!$reporte) {
            echo "No se encontró la información";
            return;
        }

        $detalle = PoliticaListaComprobacionDetalle::where('id_lista_comprobacion', $id)->get();

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        // Sanitizar
        $asistentes = htmlspecialchars($reporte->asistentes ?? '');
        $comentarios = htmlspecialchars($reporte->comentarios ?? '');
        $apoderado = htmlspecialchars($estacion->apoderado_legal ?? '');

        // ================= HTML =================

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Lista de comprobación</title>
            <link rel="stylesheet" href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">
        </head>
        <body>

        <table class="table table-bordered">
            <tr>
                <td class="text-center">
                    <img src="'.$logo.'" style="width:150px;">
                </td>
                <td colspan="2" class="text-center"><b>Lista de comprobación</b></td>
                <td class="text-center"><b>Fo.ADMONGAS.001</b></td>
            </tr>
            <tr>
                <td class="text-center">Realizado por:<br> Nelly Estrada Garcia</td>
                <td class="text-center">Revisado por:<br> Eduardo Galicia Flores</td>
                <td class="text-center">Autorizado por:<br> '.$apoderado.'</td>
                <td class="text-center">Fecha de aprobación:<br> 01-oct-18</td>
            </tr>
        </table>

        <div class="text-center mt-3 mb-3"><b>Política del SASISOPA</b></div>
        <div class="mb-3"><b>Fecha:</b> '.formatearFecha($reporte->fecha).'</div>

        <table class="table table-bordered">
            <tr>
                <td class="text-center"><b>Política del SASISOPA</b></td>
                <td class="text-center"><b>Si</b></td>
                <td class="text-center"><b>En Parte</b></td>
                <td class="text-center"><b>No</b></td>
            </tr>
        ';

        foreach ($detalle as $row) {

            $criterio  = htmlspecialchars($row->criterio);
            $resultado = $row->resultado;

            $si      = $resultado === "Si" ? "X" : "";
            $enparte = $resultado === "En Parte" ? "X" : "";
            $no      = $resultado === "No" ? "X" : "";

            $html .= "
            <tr>
                <td class='align-middle'>{$criterio}</td>
                <td class='text-center'>{$si}</td>
                <td class='text-center'>{$enparte}</td>
                <td class='text-center'>{$no}</td>
            </tr>";
        }

        $html .= '
        </table>

        <div class="mt-3 border p-3">
            <b>Asistentes:</b><br>
            '.$asistentes.'
        </div>

        <div class="mt-3 border p-3">
            <b>Comentarios:</b><br>
            '.$comentarios.'
        </div>

        </body>
        </html>
        ';


        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dompdf->stream("Lista-comprobacion.pdf", ["Attachment" => true]);
    }

    }
