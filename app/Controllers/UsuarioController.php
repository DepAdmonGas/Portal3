<?php
namespace App\Controllers;
use App\Core\View;
Use App\Models\Usuario;
Use App\Models\Estacion;

class UsuarioController extends BaseController{

    public function index(){

        $idestacion = $_GET['idEstacion'] ?? null;
        $razonsocial = null;

        if (!empty($idestacion) && is_numeric($idestacion)) {
            $razonsocial = Estacion::where('id', $idestacion)->value('razonsocial');
        }

        $data = [
            'title' => 'Usuarios',
            'idestacion' => $idestacion,
            'razonsocial' => $razonsocial,
             'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/usuarios/datatable.init.js'
            ]
        ];
        
        View::render('usuario/index', $data,'main');
    }

public function datatableUsuarios()
{

     $idestacion = isset($_GET['idestacion']) ? (int) $_GET['idestacion'] : null;

    $usuarios = Usuario::select([
            'id',
            'nombre',
            'email',
            'telefono',
            'id_gas',
            'id_puesto',
            'estatus'
        ])
        ->with([
            'estacion:id,razonsocial',
            'puesto:id,tipo_puesto'
        ])
        ->when($idestacion, function ($q) use ($idestacion) {
            $q->where('id_gas', $idestacion);
        })
        ->get()
        ->map(fn ($u) => [
            'id' => $u->id,
            'nombre' => $u->nombre,
            'email' => $u->email,
            'telefono' => $u->telefono,
            'razonsocial' => $u->estacion->razonsocial,
            'puesto' => $u->puesto->tipo_puesto,
            'estatus' => $u->estatus
        ]);

     echo json_encode([
        'data' => $usuarios
    ]);
    exit;
    }




}