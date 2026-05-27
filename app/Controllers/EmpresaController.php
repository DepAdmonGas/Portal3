<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Models\Empresa;
use App\Services\ModuloService;
use App\Core\Auth;

class EmpresaController extends BaseController{
protected string $modulo = 'empresa';

public function index(){

$title = 'Empresa';
Breadcrumb::add('Home', '/home');
Breadcrumb::add($title, '');

// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'filtro_usuario' => $this->filtro_usuario,
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/empresa/empresa.datatable.init.js?v=' . time()
],
'help' => false
];

View::render('empresa/index', $data,'main');
}

public function datatableEmpresa(){

$permisoDescargar = ModuloService::validaPermiso($this->modulo, 'descargar');

$actividades = Empresa::select([
'id',
'descripcion',
'id_tipo',
'archivo',
'estatus'
])
->with([
'tipoPuesto:id,tipo,estatus'
])
->orderBy('id_tipo', 'asc')
->get()
->map(fn ($e) => [
'id' => $e->id,
'descripcion' => $e->descripcion,
'tipo' => $e->tipoPuesto->tipo ?? null,
'archivo' => $e->archivo,
'estatus' => $e->estatus,
'estatus_tipo' => $e->tipoPuesto->estatus ?? null
]);

echo json_encode([
"data" => $actividades,
"permisos" => [
"descargar" => $permisoDescargar
]
]);

exit;
}

}
