<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\UsuariosFamiliares;
use App\Models\UsuariosFormacionAcademica;
use App\Models\UsuariosExperienciaLaboral;
use App\Models\UsuariosExperienciaEmpresaGrupo;
use Dompdf\Dompdf;
use Dompdf\Options;

class PerfilPersonalController extends BaseController
{
 protected string $modulo = 'sasisopa';

public function perfilesPersonal(){
        $title = 'Perfil del personal';
         // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('6. COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO', '/sasisopa/competencia-personal-capacitacion-entrenamiento');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
             'links' =>[
                 '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
                
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/competenciapersonalcapacitacionentrenamiento/perfilpersonal.datatable.init.js?v=1.0'
            ],
            'help' => false
        ];
        
        View::render('competenciapersonalcapacitacionentrenamiento/perfiles-personal', $data,'sasisopa');

    }

    public function datatablePerfilesPersonal(){

      $permisoDescargar   = ModuloService::validaPermiso($this->modulo, 'descargar');

        $usuarios = Usuario::with('puesto')
            ->activo()
            ->where('id_gas', $this->estacionId())
            ->where('id_puesto', '!=', 1)
            ->orderBy('id','desc')
            ->get();

        $data = $usuarios->map(function ($u) {

            $porcentaje = $u->porcentaje_cumplimiento;

            // color dinámico
            $color = 'text-danger';
            if ($porcentaje >= 80) {
                $color = 'text-success';
            } elseif ($porcentaje >= 60) {
                $color = 'text-warning';
            }

            return [
                'id'        => $u->id,
                'nombre'    => $u->nombre,
                'telefono'  => $u->telefono,
                'email'     => $u->email,
                'puesto'    => $u->puesto->tipo_puesto ?? '',
                'porcentaje'=> $porcentaje,
                'color'     => $color
            ];
        });

        echo json_encode([
            'data' => $data,
            "permisos" => [
                "descargar" => $permisoDescargar
            ]
        ]);


    exit;

    }

    public function fichaPersonal(int $id){

        $title = 'Ficha personal';
         // Buscar permisos de los modulos
         $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('6. COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO', '/sasisopa/competencia-personal-capacitacion-entrenamiento');
        Breadcrumb::add('Perfil del personal', '/sasisopa/competencia-personal-capacitacion-entrenamiento/perfiles-personal');
        Breadcrumb::add($title, '');

        $usuario = Usuario::find($id);

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'usuario' => $usuario,
             'links' =>[
                
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/signature_pad/docs/js/signature_pad.umd.min.js',
                '/js/competenciapersonalcapacitacionentrenamiento/fichapersonal.actions.init.js?v=1.8'
            ],
            'help' => false
        ];
        
        View::render('competenciapersonalcapacitacionentrenamiento/ficha-personal', $data,'sasisopa');

    }


    public function updateFichaPersonal(){

        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);

         if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

        // SECURITY: Sanitización de inputs (Vulnerabilidad #5)
        $id = sanitize_input($data['id'] ?? null, 'int');
        $nombre = sanitize_input($data['nombre'] ?? null, 'string');
        $domicilio = sanitize_input($data['domicilio'] ?? null, 'string');
        $telefono = sanitize_input($data['telefono'] ?? null, 'string');
        $email = sanitize_input($data['email'] ?? null, 'email');

        if (!$nombre || !$domicilio) {
        echo json_encode([
            'success' => false,
            'message' => 'Campos obligatorios'
        ]);
        exit;
        }

        try {

            $usuario = Usuario::find($id);

            if (!$usuario) {
                echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
                return;
            }

            $usuario->update([
                'nombre'            => $nombre,
                'domicilio'         => $domicilio,
                'fecha_nacimiento'  => $data['fecha_nacimiento'],
                'estado_civil'      => $data['estado_civil'],
                'seguro_social'     => $data['seguro_social'],
                'telefono'          => $telefono,
                'email'             => $email
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Ficha personal actualizada correctamente',
            ]);
            exit;

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar la ficha personal'
            ]);
        }

    }

    public function getFamiliares(int $id){
         header('Content-Type: application/json');
         $data = UsuariosFamiliares::where('id_usuario', $id)->get();

        echo json_encode($data);
        exit;
    }

    public function deleteDatoFamiliar(){
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

         if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para eliminar'
            ]);
            return;
        }

        try {
            $familiar = UsuariosFamiliares::find($data['id']);

            if (!$familiar) {
                echo json_encode(['success' => false, 'message' => 'Familiar no encontrado']);
                return;
            }

            $familiar->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Familiar eliminado correctamente',
            ]);
            exit;

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar el familiar'
            ]);
        }
    }

    public function createDatoFamiliar(){

        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

         if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

        // SECURITY: Sanitización de inputs (Vulnerabilidad #5)
        $nombrecompleto = sanitize_input($data['nombrecompleto'] ?? null, 'string');
        $parentesco = sanitize_input($data['parentesco'] ?? null, 'string');
        $domicilio = sanitize_input($data['domicilio'] ?? null, 'string');
        $telefono = sanitize_input($data['telefono'] ?? null, 'string');

        if (!$nombrecompleto || !$parentesco) {
        echo json_encode([
            'success' => false,
            'message' => 'Campos obligatorios'
        ]);
        exit;
        }

        try {

            $familiar = UsuariosFamiliares::create([
                'id_usuario' => sanitize_input($data['id_usuario'] ?? null, 'int'),
                'nombrecompleto' => $nombrecompleto,
                'parentesco' => $parentesco,
                'domicilio' => $domicilio,
                'telefono' => $telefono
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Familiar creado correctamente',
                'familiar' => $familiar
            ]);
            exit;

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error al crear el familiar'
            ]);
        }

    }

    public function getFormacionAcademica(int $id){
     $data = UsuariosFormacionAcademica::where('id_usuario', $id)->get();
     echo json_encode($data);
    }

    public function createFormacion()
        {
            $data = json_decode(file_get_contents("php://input"), true);

            try {

                UsuariosFormacionAcademica::create([
                    'id_usuario' => $data['id_usuario'],
                    'nivel'      => $data['nivel'],
                    'detalle'    => $data['detalle']
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Formación académica creada correctamente'
                    ]);

            } catch (\Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message'=> 'Error al crear la formación académica'
                    ]);
            }
    }

    public function deleteFormacion()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        try {

            UsuariosFormacionAcademica::where('id', $data['id'])->delete();

            echo json_encode([
                'success' => true,
                'message'=> 'Formación académica eliminada correctamente'
                ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message'=> 'Error al eliminar la formación académica'
                ]);
        }
    }

    public function getExperiencia(int $id)
    {
        echo json_encode(
            UsuariosExperienciaLaboral::where('id_usuario', $id)->get()
        );
    }

    public function createExperiencia()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            echo json_encode([
                'success' => false,
                'message'=> 'Faltan datos requeridos'
                ]);
            return;
        }

        try {

            UsuariosExperienciaLaboral::create([
                'id_usuario' => $data['id_usuario'],
                'detalle' => $data['detalle']
            ]);

            echo json_encode([
                'success' => true,
                'message'=> 'Experiencia laboral creada correctamente'
                ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message'=> 'Error al crear la experiencia laboral'
                ]);
        }
    }

    public function deleteExperiencia()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        try {

            UsuariosExperienciaLaboral::find($data['id'])->delete();

            echo json_encode([
                'success' => true,
                'message'=> 'Experiencia laboral eliminada correctamente'
                ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message'=> 'Error al eliminar la experiencia laboral'
                ]);
        }
    }

    public function getExperienciaEmpresa(int $id)
    {
        echo json_encode(
            UsuariosExperienciaEmpresaGrupo::where('id_usuario', $id)->get()
        );
    }

    public function createExperienciaEmpresa()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        try {

            UsuariosExperienciaEmpresaGrupo::create([
                'id_usuario'     => $data['id_usuario'],
                'razon_social'   => $data['razon_social'],
                'puesto'         => $data['puesto'],
                'periodo_inicio' => $data['periodo_inicio'],
                'periodo_fin'    => $data['periodo_fin'] ?? null
            ]);

            echo json_encode([
                'success' => true,
                'message'=> 'Experiencia laboral creada correctamente'
                ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message'=> 'Error al crear la experiencia laboral'
                ]);
        }
    }

    public function deleteExperienciaEmpresa()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        try {

            UsuariosExperienciaEmpresaGrupo::where('id', $data['id'])->delete();

            echo json_encode([
                'success' => true,
                'message'=> 'Experiencia laboral eliminada correctamente'
                ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message'=> 'Error al eliminar la experiencia laboral'
                ]);
        }
    }

    public function updateExperienciaEmpresa() {

        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || empty($data['id'])) {
            echo json_encode([
                'success' => false,
                'message'=> 'Faltan datos requeridos'
                ]);
            return;
        }

        try {

            $registro = UsuariosExperienciaEmpresaGrupo::find($data['id']);

            if (!$registro) {
                echo json_encode([
                    'success' => false,
                    'message'=> 'Registro no encontrado'
                ]);
                return;
            }

            $registro->update([
                'razon_social'   => $data['razon_social'],
                'puesto'         => $data['puesto'],
                'periodo_inicio' => $data['periodo_inicio'],
                'periodo_fin'    => $data['periodo_fin']
            ]);

            echo json_encode([
                'success' => true,
                'message'=> 'Experiencia laboral actualizada correctamente'
                ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message'=> 'Error al actualizar la experiencia laboral'
                ]);
        }
    }

    public function updateFirma()
        {

        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || empty($data['id']) || empty($data['firma'])) {
            echo json_encode(['success' => false]);
            return;
        }

        try {

           $usuario = Usuario::find($data['id']);

            if (!$usuario) {
                echo json_encode([
                    'success' => false,
                    'message'=> 'Usuario no encontrado'
                    ]);
                return;
            }

              $firma = $data['firma'];
            $firma = str_replace('data:image/png;base64,', '', $firma);
            $firma = str_replace(' ', '+', $firma);

            $imageData = base64_decode($firma);

            $nombreArchivo = 'firma_' . $usuario->id . '_' . time() . '.png';
            $ruta = __DIR__ . '../../../public/uploads/firma-personal/' . $nombreArchivo;

            file_put_contents($ruta, $imageData);

            $usuario->update([
                'firma' => $nombreArchivo
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Firma actualizada correctamente',
                'ruta' => $_ENV['APP_URL'] . '/uploads/firma-personal/' . $ruta
                ]); 
        
          

        } catch (\Exception $e) {

            echo json_encode([
                'success' => false,
                'error' => 'Error al actualizar la firma'
            ]);
        }
            
    }

    //------------------------------------------------------------------

    public function fichaPersonalIndividualPdf(int $id)
    {
        $usuario = Usuario::with([
            'puesto',
            'familiares',
            'formaciones',
            'experiencias',
            'experienciaEmpresa'
        ])->find($id);

        if (!$usuario) {
            echo "Usuario no encontrado";
            return;
        }

        $this->generarPdf([$usuario]);
    }

    public function fichaPersonalPdf()
    {
        $usuarios = Usuario::with([
            'puesto',
            'familiares',
            'formaciones',
            'experiencias',
            'experienciaEmpresa'
        ])
        ->where('id_gas', $this->estacionId())
        ->activo()
        ->get();

        if ($usuarios->isEmpty()) {
            echo "No hay usuarios";
            return;
        }

        $this->generarPdf($usuarios);
    }

    public function generarPdf(iterable $usuarios)
    {
        $registro = Estacion::find($this->estacionId());

        if (!$registro) {
            echo "No se encontró la información";
            return;
        }

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';
        $apoderadolegal = $registro->apoderado_legal;

        $usuarios = collect($usuarios);

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <title>Fichas de personal</title>
        <link rel="stylesheet" href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">
        </head>
        <body class="fs-6">

        <!-- HEADER -->
        <table class="table">
        <tr>
            <td class="text-center">
                <img src="'.$logo.'" style="width:130px">
            </td>
            <td colspan="2" class="text-center"><b>Fichas de personal</b></td>
            <td class="text-center"><b>Fo.ADMONGAS.008</b></td>
        </tr>

        <tr>
            <td class="text-center">Realizado por:<br>Nelly Estrada Garcia</td>
            <td class="text-center">Revisado por:<br>Eduardo Galicia Flores</td>
            <td class="text-center">Autorizado por:<br>'.$apoderadolegal.'</td>
            <td class="text-center">Fecha de aprobación:<br>01/10/2018</td>
        </tr>
        </table>';

        foreach ($usuarios as $index => $usuario) {

            $empresa = $usuario->experienciaEmpresa;

        $html .= '

        <!-- 1 DATOS -->
        <div class="mt-3 mb-3"><b>1. Datos personales:</b></div>

        <div class="">'.($usuario->nombre ?? '').'</div>
        <div class="text-muted bt-2 mt-2">Nombre completo:</div>

        <div class="mt-2">'.($usuario->domicilio ?? '').'</div>
        <div class="text-muted bt-2 mt-2">Domicilio( Calle, Numero, Colonia, Municipio, Estado, C.P.):</div>

        <table class="mt-2" style="width:100%;">
        <tr>
            <td><div class="mt-2">'.formatearFecha($usuario->fecha_nacimiento).'</div></td>
            <td><div class="mt-2">'.($usuario->estado_civil ?? '').'</div></td>
        </tr>
        <tr>
            <td><div class="text-muted bt-2 mt-2">Fecha nacimiento</div></td>
            <td><div class="text-muted bt-2 mt-2">Estado civil</div></td>
        </tr>

        <tr>
            <td><div class="mt-2">'.($usuario->seguro_social ?? '').'</div></td>
            <td><div class="mt-2">'.($usuario->telefono ?? '').'</div></td>
        </tr>
        <tr>
            <td><div class="text-muted bt-2 mt-2">Seguro social</div></td>
            <td><div class="text-muted bt-2 mt-2">Teléfono</div></td>
        </tr>
        </table>

        <!-- 2 FAMILIARES -->
        <div class="mt-3 mb-3"><b>2. Datos de familiares</b></div>

        <table class="table mt-2">
        <tr>
            <th>Nombre</th>
            <th>Parentesco</th>
            <th>Domicilio</th>
            <th>Teléfono</th>
        </tr>';

        if ($usuario->familiares->count()) {
            foreach ($usuario->familiares as $familiar) {
                $html .= '
                <tr>
                    <td>'.$familiar->nombrecompleto.'</td>
                    <td>'.$familiar->parentesco.'</td>
                    <td>'.$familiar->domicilio.'</td>
                    <td>'.$familiar->telefono.'</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="4" class="text-center">No hay datos</td></tr>';
        }

        $html .= '</table>

        <!-- 3 FORMACION -->
        <div class="mt-3 mb-3"><b>3. Formación académica</b></div>

        <table class="table">
        <tr>
            <th>Nivel</th>
            <th>Detalle</th>
        </tr>';

        if ($usuario->formaciones->count()) {
            foreach ($usuario->formaciones as $fa) {
                $html .= '
                <tr>
                    <td>'.$fa->nivel.'</td>
                    <td>'.$fa->detalle.'</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="2">Sin información</td></tr>';
        }

        $html .= '</table>

        <!-- 4 EXPERIENCIA -->
        <div class="mt-3 mb-3"><b>4. Experiencia laboral</b></div>
        <div><b>4.1 En otras empresas</b></div>

        <table class="table">';

        if ($usuario->experiencias->count()) {
            $i = 1;
            foreach ($usuario->experiencias as $exp) {
                $html .= '
                <tr>
                    <td>'.$i++.'</td>
                    <td>'.$exp->detalle.'</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td>Sin información</td></tr>';
        }

        $html .= '</table>

        <!-- 4.2 EMPRESA -->
        <div class="mt-3 mb-3"><b>4.2 En la empresa</b></div>

        <table class="table">
        <tr>
        <th rowspan="2">Razón social</th>
        <th rowspan="2">Puesto</th>
        <th colspan="2">Periodo</th>
        </tr>
        <tr>
            <th>Inicio</th>
            <th>Fin</th>
        </tr>';

        if ($empresa && $empresa->count()) {
            foreach ($empresa as $e) {
                $html .= '
                <tr>
                    <td>'.$e->razon_social.'</td>
                    <td>'.$e->puesto.'</td>
                    <td>'.formatearFecha($e->periodo_inicio).'</td>
                    <td>'.formatearFecha($e->periodo_fin).'</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="4">Sin información</td></tr>';
        }

        $html .= '</table>';

        if ($index < $usuarios->count() - 1) {
        $html .= '<div style="page-break-after: always;"></div>';
        }

        }

        $html .= '</body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dompdf->stream("Fichas-de-personal.pdf", ["Attachment" => true]);
    }
   
}