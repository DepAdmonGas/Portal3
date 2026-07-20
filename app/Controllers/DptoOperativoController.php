<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloDptoOperativoService;
use App\Services\ModuloService;
use App\Core\Auth;
use App\Services\ModuleStationService;

class DptoOperativoController extends BaseController{
protected string $modulo = 'departamento-operativo';

public function index(){
// Resetear contextos de estaciones al salir de módulos
ModuleStationService::resetAllContexts();

$title = 'Dirección de Operaciones';

Breadcrumb::add('Home', '/home');
Breadcrumb::add($title, '');

$usuario = Auth::user();

// Buscar permisos de los modulos
$permisos = ModuloService::getPermisos($usuario->id);

// Buscar menu / modulo del departamento
$elementos = ModuloDptoOperativoService::getPermisos($usuario->id);

$data = [
'title' => $title,
'permisos' => $permisos,
'elementos' => $elementos,
'links' =>[],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time()
],
'help' => false,
'ocultarSelectorEstacion' => true,
];  

View::render('departamento-operativo/index', $data,'departamento-operativo');
}

private function renderModulo($slug, $title)
{
Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add($title, '');

$usuario = Auth::user();

$permisos = ModuloService::permisosSesion($this->modulo);
$modulo = ModuloDptoOperativoService::getPermiso($usuario->id,$slug);
$submenus = $modulo['submenus'] ?? [];

$idYear = date('Y');
$idMes  = date('n');

$routeSuffix = [
'corte-diario'      => "/{$idYear}/{$idMes}",
'embarques'         => "/{$idYear}/{$idMes}",
'solicitud-cheque'  => "/{$idYear}/{$idMes}",
'ingresos-facturacion' => "/{$idYear}",
'despacho-ventas'   => "/{$idYear}/{$idMes}",
'comparativo-xml'   => "/{$idYear}",
'aclaracion-voucher'   => "/{$idYear}/{$idMes}",
'solicitud-vales'   => "/{$idYear}/{$idMes}",
'factura-monedero'   => "/{$idYear}/{$idMes}",
];

foreach ($submenus as &$submenu) {
$suffix = $routeSuffix[$submenu['clave']] ?? '';
$submenu['ruta'] .= $suffix;
}
unset($submenu);

$data = [
'title' => $title,
'permisos' => $permisos,
'submenus' => $submenus,
'modulo' => $this->modulo,
'idYear' => $idYear,
'idMes' => $idMes,
'links' => [],
'scripts' => [],
'help' => false,
'ocultarSelectorEstacion' => true,
];

View::render("departamento-operativo/submodulos-index", $data, 'departamento-operativo');
}

public function corporativoIndex()
{
$this->renderModulo('corporativo', 'Corporativo');
}

public function recursosHumanosIndex()
{
$this->renderModulo('recursos-humanos', 'Recursos Humanos');
}

public function importacionIndex()
{
$this->renderModulo('importacion', 'Importación');
}

public function almacenIndex()
{
$this->renderModulo('almacen', 'Almacén');
}

public function comercializadoraIndex()
{
$this->renderModulo('comercializadora', 'Comercializadora');
}

}

