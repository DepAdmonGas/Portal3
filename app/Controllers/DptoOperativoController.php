<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloDptoOperativoService;
use App\Services\DptoOperativoPresentacionService;
use App\Services\ModuloService;
use App\Core\Auth;
use App\Services\ModuleStationService;
use App\Services\SolicitudChequeService;
use App\Services\AclaracionVoucherService;
use App\Services\FormatosService;
use App\Services\BitacoraRrhhService;
use App\Services\PedidoPinturasService;
use App\Services\PedidoPapeleriaService;
use App\Services\PedidoArticulosLimpiezaService;
use App\Services\PedidoAditivoService;
use App\Services\OrdenMantenimientoService;

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

/*
 * Accesos a pintar en esta pantalla. La decisión de mostrar la tarjeta del
 * módulo o sus submódulos como accesos directos la toma el servicio de
 * presentación; aquí solo se pasa el resultado a la vista.
 */
$accesos = DptoOperativoPresentacionService::paraModulos($elementos);

$data = [
'title' => $title,
'permisos' => $permisos,
'elementos' => $elementos,
'accesos' => $accesos,
'pendientesFirmaSolicitudCheque' => SolicitudChequeService::getPendientesFirmaCard(),
'pendientesAclaracionVoucher' => AclaracionVoucherService::getPendientesMesActual(),
'pendientesFirmaFormatos' => FormatosService::getPendientesFirmaCard(),
'pendientesBitacoraRrhh' => BitacoraRrhhService::getPendientesCardMesActual(),
'pendientesFirmaPedidoPinturas' => PedidoPinturasService::getPendientesFirmaVoBoCard(),
'pendientesFirmaPedidoPapeleria' => PedidoPapeleriaService::getPendientesFirmaVoBoCard(),
'pendientesFirmaPedidoArticulosLimpieza' => PedidoArticulosLimpiezaService::getPendientesFirmaVoBoCard(),
'pendientesFirmaPedidoAditivo' => PedidoAditivoService::getPendientesFirmaVoBoCard(),
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

/*
 * Las rutas con año y mes se resuelven con el mismo mapa que usan los
 * accesos directos de /departamento-operativo, para que ambos caminos
 * generen exactamente la misma URL.
 */
foreach ($submenus as &$submenu) {
$submenu['ruta'] = ModuloDptoOperativoService::resolverRutaSubmodulo($submenu);
}
unset($submenu);

$data = [
'title' => $title,
'permisos' => $permisos,
'submenus' => $submenus,
'pendientesFirmaSolicitudCheque' => $slug === 'corporativo' ? SolicitudChequeService::getPendientesFirmaCard() : 0,
'pendientesAclaracionVoucher' => $slug === 'corporativo' ? AclaracionVoucherService::getPendientesMesActual() : 0,
'pendientesFirmaFormatos' => $slug === 'recursos-humanos' ? FormatosService::getPendientesFirmaCard() : 0,
'pendientesBitacoraRrhh' => $slug === 'recursos-humanos' ? BitacoraRrhhService::getPendientesCardMesActual() : 0,
'pendientesFirmaPedidoPinturas' => $slug === 'comercializadora' ? PedidoPinturasService::getPendientesFirmaVoBoCard() : 0,
'pendientesFirmaPedidoPapeleria' => $slug === 'comercializadora' ? PedidoPapeleriaService::getPendientesFirmaVoBoCard() : 0,
'pendientesFirmaPedidoArticulosLimpieza' => $slug === 'comercializadora' ? PedidoArticulosLimpiezaService::getPendientesFirmaVoBoCard() : 0,
'pendientesFirmaPedidoAditivo' => $slug === 'comercializadora' ? PedidoAditivoService::getPendientesFirmaVoBoCard() : 0,
'pendientesFirmaOrdenMantenimiento' => $slug === 'almacen'
    ? count(OrdenMantenimientoService::pendingSignatureItems((int)($usuario->id ?? 0)))
    : 0,
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

/**
 * Hub de Mantenimiento (Almacén).
 *
 * El legacy llegaba a esta pantalla desde el apartado Mantenimiento y no desde
 * Almacén: por eso el breadcrumb de Calibración de Dispensarios cambia según el puesto.
 * Reutiliza la vista de cards submodulos-index.
 */
public function almacenMantenimientoIndex()
{
$title = 'Mantenimiento';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Almacén', '/departamento-operativo/almacen');
Breadcrumb::add($title, '');

if (!ModuloDptoOperativoService::validaPermiso('almacen', 'leer')) {
View::render('errors/404', [], 'departamento-operativo');
return;
}

$usuario = Auth::user();

$modulo = ModuloDptoOperativoService::getPermiso($usuario->id, 'almacen');

/*
* La card se lee de modulos_sub_dpto_operativo por clave para que nombre e icono
* se mantengan sincronizados con Configuración, sin duplicarlos en el código.
*/
$cards = \App\Models\ModuloSubDptoOperativo::whereIn('clave', [
    'calibracion-dispensarios',
    'medicion-nivel-explosividad',
    'maquinaria-equipos',
    'mantenimiento-preventivo',
    'terminales-punto-venta',
    'orden-mantenimiento'
])
->where('id_modulo', 4)
->where('activo', 1)
->get();

$submenus = [];
foreach ($cards as $c) {
    if ($c->ruta) {
        $submenus[] = [
            'id_sub_modulo' => $c->id,
            'nombre'        => $c->nombre,
            'clave'         => $c->clave,
            'ruta'          => $c->ruta,
            'icono'         => $c->icono ?: 'ti ti ti-layout-grid',
        ];
    }
}

View::render('departamento-operativo/submodulos-index', [
'title' => $title,
'permisos' => $modulo,
'submenus' => $submenus,
'pendientesFirmaOrdenMantenimiento' => count(OrdenMantenimientoService::pendingSignatureItems((int)($usuario->id ?? 0))),
'modulo' => 'almacen',
'links' => [],
'scripts' => [],
'help' => false,
'ocultarSelectorEstacion' => true,
], 'departamento-operativo');
}

public function comercializadoraIndex()
{
$this->renderModulo('comercializadora', 'Comercializadora');
}

}

