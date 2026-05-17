<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\Grupo;
use App\Core\Breadcrumb;
class GrupoController extends BaseController{

public function index(){

     $title = 'Grupos';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

    $data = [
    'title' => $title,
    'links' =>[
    '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
    ],
    'scripts' => [
    '/assets/js/vendor.min.js',
    '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
    '/assets/js/grupos/datatable.init.js',
    '/assets/js/grupos/actions.init.js'
    ]
    ];

View::render('grupos/index', $data,'main');

}

public function datatableGrupos(){

$grupo = Grupo::all();

echo json_encode([
"data" => $grupo
]);

exit;

}

public function createGrupo(){

$data = json_decode(file_get_contents('php://input'), true);

// SECURITY: Sanitización de inputs (Vulnerabilidad #5)
$nombre = sanitize_input($data['nombre'] ?? null, 'string');

if (empty($nombre)) {
http_response_code(422);
echo json_encode(['message' => 'Nombre requerido']);
exit;
}

Grupo::create([
'nombre' => $nombre,
'estatus' => 1
]);

header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit;
}

public function updateGrupo()
{
$data = json_decode(file_get_contents('php://input'), true);

// SECURITY: Sanitización de inputs (Vulnerabilidad #5)
$id = sanitize_input($data['id'] ?? null, 'int');
$nombre = sanitize_input($data['nombre'] ?? null, 'string');

if (empty($id) || empty($nombre)) {
echo json_encode(['message' => 'Datos incompletos']);
exit;
}

$grupo = Grupo::find($id);

if (!$grupo) {
echo json_encode(['message' => 'Grupo no encontrado']);
exit;
}

$grupo->nombre = $nombre;
$grupo->save();

header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit;
}


public function deleteGrupo()
{
// Leer JSON enviado por Axios
$data = json_decode(file_get_contents('php://input'), true);
// SECURITY: Sanitización de inputs (Vulnerabilidad #5)
$id = sanitize_input($data['id'] ?? null, 'int');

if (!$id) {
echo json_encode(['message' => 'ID requerido']);
exit;
}

// Buscar el grupo
$grupo = Grupo::find($id);

if (!$grupo) {
echo json_encode(['message' => 'Grupo no encontrado']);
exit;
}

if ($grupo->estatus == 0) {
echo json_encode(['message' => 'No se puede eliminar un grupo ya inactivo']);
exit;
}

// Soft delete: cambiar estatus a 0
$grupo->estatus = 0;
$grupo->save();

// Devolver respuesta JSON
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true]);
exit;
}

}