<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Operativo\RhOrganigramaEstacion;
use App\Models\OrganigramaPlantilla;
use App\Models\OrganigramaEstaciones;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Operativo\RhPersonal;
use Carbon\Carbon;

class OrganigramaService
{
public static function getPermisos(): array
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');
$idUsuario = (int)($sessionUsuario['id'] ?? 0);
$idPuesto = (int)($usuario->id_puesto ?? 0);
$idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
$multiestacion = !empty($sessionUsuario['multiestacion']);
$nombrePuesto = $usuario->puesto->tipo_puesto ?? '';

if ($idUsuario === 292) $idEstacion = 8;

$esEncargado = in_array($nombrePuesto, ['Encargado', 'Asistente Administrativo']);
$puedeCrear = $esEncargado;
$puedeEditar = $esEncargado;
$puedeEliminar = $esEncargado;

$permisosDb = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
if (!empty($permisosDb)) {
if (isset($permisosDb['crear'])) $puedeCrear = !empty($permisosDb['crear']);
if (isset($permisosDb['editar'])) $puedeEditar = !empty($permisosDb['editar']);
if (isset($permisosDb['eliminar'])) $puedeEliminar = !empty($permisosDb['eliminar']);
}

return [
'id_usuario' => $idUsuario,
'id_estacion' => $idEstacion,
'id_puesto' => $idPuesto,
'nombre_puesto' => $nombrePuesto,
'es_encargado' => $esEncargado,
'multiestacion' => $multiestacion,
'puedeCrear' => $puedeCrear,
'puedeEditar' => $puedeEditar,
'puedeEliminar' => $puedeEliminar,
];
}

public static function getUploadDir(): string
{
$dir = __DIR__ . '/../../public/uploads/archivos/organigrama/';
if (!is_dir($dir)) mkdir($dir, 0775, true);
return $dir;
}

public static function getDocumentUploadDir(): string
{
$dir = __DIR__ . '/../../public/uploads/archivos/organigrama-documentos/';
if (!is_dir($dir)) mkdir($dir, 0775, true);
return $dir;
}

public static function getOrganigramaVersions(int $idEstacion): array
{
$records = RhOrganigramaEstacion::where('id_estacion', $idEstacion)
->orderBy('version', 'desc')
->get();

$data = [];
foreach ($records as $r) {
$data[] = [
'id' => $r->id,
'version' => $r->version,
'archivo' => $r->archivo ?? '',
'observaciones' => $r->observaciones ?? '',
'fechacreacion' => $r->fechacreacion ? $r->fechacreacion->format('Y-m-d H:i:s') : '',
'fechacreacion_format' => $r->fechacreacion
? formatearFecha($r->fechacreacion->format('Y-m-d')) . ', ' . $r->fechacreacion->format('g:i a')
: '-',
];
}
return $data;
}

public static function getNextVersion(int $idEstacion): int
{
$last = RhOrganigramaEstacion::where('id_estacion', $idEstacion)
->orderBy('version', 'desc')
->first();
return $last ? $last->version + 1 : 1;
}

public static function addVersion(array $input): ?int
{
$idEstacion = (int)($input['id_estacion'] ?? 0);
if (!$idEstacion) {
$ctx = ModuleStationService::getContext('organigrama');
$idEstacion = (int)($ctx['id_estacion'] ?? 0);
}
if (!$idEstacion) {
$sessionUsuario = Session::get('usuario');
$idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
}
if (!$idEstacion) return null;

$observaciones = $input['observaciones'] ?? '';
$version = self::getNextVersion($idEstacion);
$uploadDir = self::getUploadDir();
$aleatorio = uniqid();
$archivo = '';

if (!empty($input['archivo']) && isset($input['archivo']['tmp_name']) && $input['archivo']['error'] === 0) {
$ext = pathinfo($input['archivo']['name'], PATHINFO_EXTENSION);
$archivo = $aleatorio . '-organigrama.' . $ext;
move_uploaded_file($input['archivo']['tmp_name'], $uploadDir . $archivo);
}

$record = RhOrganigramaEstacion::create([
'id_estacion' => $idEstacion,
'version' => $version,
'archivo' => $archivo,
'observaciones' => $observaciones,
]);

if ($record) {
$ctx = ModuleStationService::getContext('organigrama');
self::notifyOrganigrama($idEstacion, 'agregó', [
'version' => $version,
'id_depto' => $ctx['id_depto'] ?? null,
]);
}

return $record ? $record->id : null;
}

public static function deleteVersion(int $id): bool
{
$record = RhOrganigramaEstacion::find($id);
if (!$record) return false;

$uploadDir = self::getUploadDir();
if ($record->archivo && file_exists($uploadDir . $record->archivo)) {
unlink($uploadDir . $record->archivo);
}

$idEstacion = $record->id_estacion;
$version = $record->version;
$ctx = ModuleStationService::getContext('organigrama');
$record->delete();

self::notifyOrganigrama($idEstacion, 'eliminó', [
'version' => $version,
'id_depto' => $ctx['id_depto'] ?? null,
]);

return true;
}

private static function notifyOrganigrama(int $idEstacion, string $accion, array $extra = []): void
{
try {
$sessionUsuario = Session::get('usuario');
$idUsuario = (int)($sessionUsuario['id'] ?? 0);
$usuario = Usuario::find($idUsuario);
$nombreUsuario = $usuario ? $usuario->nombre : 'Sistema';

$iconos = [
'agregó' => '📄',
'modificó' => '✏️',
'eliminó' => '🗑️',
];
$icono = $iconos[$accion] ?? '📝';

$accionTexto = [
'agregó' => 'agregado',
'modificó' => 'modificado',
'eliminó' => 'eliminado',
];
$texto = $accionTexto[$accion] ?? 'modificado';

$isDept = !empty($extra['id_depto']);
if ($isDept) {
$depts = [
4 => 'Comercializadora', 5 => 'Gestoría',
11 => 'Dirección de operaciones', 9 => 'Autolavado',
15 => 'Departamento Mantenimiento', 18 => 'Quitarga',
19 => 'Operación servicio y mantenimiento de personal',
];
$nombreDestino = $depts[$extra['id_depto']] ?? 'Depto #' . $extra['id_depto'];
} else {
$est = Estacion::find($idEstacion);
$nombreDestino = $est ? $est->nombre : 'Estación #' . $idEstacion;
}


$detalle = $icono . ' Se ha <b>' . $texto . '</b> un organigrama en el apartado de <b>Recursos Humanos</b>, correspondiente al módulo de <b>Organigrama</b>:'
. PHP_EOL . PHP_EOL;

if (!empty($extra['version'])) {
$detalle .= '🏷️ <b>Versión:</b> ' . $extra['version'] . PHP_EOL;
}

$detalle .= '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL .
'⛽ <b>' . ($isDept ? 'Departamento' : 'Estación') . ':</b> ' . $nombreDestino ;

$telegram = new TelegramService();

if ($isDept) {
$idUsuarioSesion = (int)($sessionUsuario['id'] ?? 0);
$userStation = Usuario::find($idUsuarioSesion);
$idNotif = $userStation ? (int)$userStation->id_gas : $idEstacion;
} else {
$idNotif = $idEstacion;
}

$userIds = $telegram->getUserIdsByStation($idNotif, $idUsuario);

if (in_array($idNotif, [6, 7])) {
$extraIds = $telegram->getUserIdsComercializadora($idUsuario);
$userIds = array_values(array_unique(array_merge($userIds, $extraIds)));
} elseif (in_array($idNotif, [1, 2, 3, 4, 5, 14])) {
$extraIds = $telegram->getUserIdsContabilidad($idUsuario);
$userIds = array_values(array_unique(array_merge($userIds, $extraIds)));
}

foreach ($userIds as $uid) {
$telegram->sendTokenAsync($uid, $detalle);
}
} catch (\Throwable $e) {
error_log('Error notificando organigrama: ' . $e->getMessage());
}
}

public static function getPlantilla(int $idEstacion): array
{
$records = OrganigramaPlantilla::where('id_estacion', $idEstacion)
->where('status', 0)
->orderBy('id', 'asc')
->get();

$data = [];
foreach ($records as $r) {
$nombreCompleto = '';
if ((int)$r->id_usuario > 0) {
$personal = RhPersonal::find((int)$r->id_usuario);
$nombreCompleto = $personal ? $personal->nombre_completo : '';
} else {
$nombreCompleto = $r->nombre ?? '';
}

$data[] = [
'id' => $r->id,
'id_usuario' => (int)($r->id_usuario ?? 0),
'nombre' => $r->nombre ?? '',
'nombre_completo' => $nombreCompleto,
'descripcion' => $r->descripcion ?? '',
'documento_perfil' => $r->documento_perfil ?? '',
'documento_contrato' => $r->documento_contrato ?? '',
'status' => (int)($r->status ?? 0),
];
}
return $data;
}

public static function addPlantillaRow(int $idEstacion): ?int
{
$record = OrganigramaPlantilla::create([
'id_estacion' => $idEstacion,
'id_usuario' => 0,
'descripcion' => '',
'nombre' => '',
'status' => 0,
]);
return $record ? $record->id : null;
}

public static function updatePlantillaRow(int $id, string $campo, string $valor): bool
{
$record = OrganigramaPlantilla::find($id);
if (!$record) return false;

$allowedFields = ['descripcion', 'nombre'];
if (!in_array($campo, $allowedFields)) return false;

$record->update([$campo => $valor]);
return true;
}

public static function updatePlantillaUsuario(int $id, int $idUsuario, string $nombre): bool
{
$record = OrganigramaPlantilla::find($id);
if (!$record) return false;

if ($idUsuario > 0) {
$record->update(['id_usuario' => $idUsuario, 'nombre' => '']);
} else {
$record->update(['id_usuario' => 0, 'nombre' => $nombre]);
}
return true;
}

public static function deletePlantillaRow(int $id): bool
{
return OrganigramaPlantilla::where('id', $id)->update(['status' => 1]);
}

public static function uploadDocumento(int $idPlantilla, string $tipo, array $file): bool
{
$record = OrganigramaPlantilla::find($idPlantilla);
if (!$record) return false;

$uploadDir = self::getDocumentUploadDir();
$aleatorio = uniqid();
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = $aleatorio . '-' . $tipo . '.' . $ext;
move_uploaded_file($file['tmp_name'], $uploadDir . $filename);

if ($tipo === 'perfil') {
if ($record->documento_perfil && file_exists($uploadDir . $record->documento_perfil)) {
unlink($uploadDir . $record->documento_perfil);
}
$record->update(['documento_perfil' => $filename]);
} elseif ($tipo === 'contrato') {
if ($record->documento_contrato && file_exists($uploadDir . $record->documento_contrato)) {
unlink($uploadDir . $record->documento_contrato);
}
$record->update(['documento_contrato' => $filename]);
} else {
return false;
}

return true;
}

public static function deleteDocumento(int $idPlantilla, string $tipo): bool
{
$record = OrganigramaPlantilla::find($idPlantilla);
if (!$record) return false;

$uploadDir = self::getDocumentUploadDir();
if ($tipo === 'perfil') {
if ($record->documento_perfil && file_exists($uploadDir . $record->documento_perfil)) {
unlink($uploadDir . $record->documento_perfil);
}
$record->update(['documento_perfil' => '']);
} elseif ($tipo === 'contrato') {
if ($record->documento_contrato && file_exists($uploadDir . $record->documento_contrato)) {
unlink($uploadDir . $record->documento_contrato);
}
$record->update(['documento_contrato' => '']);
} else {
return false;
}

return true;
}

public static function getStationInfo(int $idEstacion): ?array
{
$record = OrganigramaEstaciones::where('id_estacion', $idEstacion)->first();
if (!$record) return null;

$razonsocial = $record->razonsocial ?? '';
if (empty($razonsocial)) {
$est = Estacion::find($idEstacion);
$razonsocial = $est ? $est->razonsocial : '';
}

return [
'id' => $record->id,
'id_estacion' => (int)$record->id_estacion,
'razonsocial' => $razonsocial,
'registro_patronal' => $record->registro_patronal ?? '',
'calle' => $record->calle ?? '',
'numero_exterior' => $record->numero_exterior ?? '',
'numero_interior' => $record->numero_interior ?? '',
'colonia' => $record->colonia ?? '',
'codigo_postal' => $record->codigo_postal ?? '',
'estado' => $record->estado ?? '',
'municipio' => $record->municipio ?? '',
'numero_telefono' => $record->numero_telefono ?? '',
];
}

public static function updateStationInfo(int $idEstacion, string $campo, string $valor): bool
{
$allowedFields = [
'razonsocial', 'registro_patronal', 'calle', 'numero_exterior',
'numero_interior', 'colonia', 'codigo_postal', 'estado', 'municipio', 'numero_telefono'
];
if (!in_array($campo, $allowedFields)) return false;

$record = OrganigramaEstaciones::where('id_estacion', $idEstacion)->first();
if (!$record) {
OrganigramaEstaciones::create([
'id_estacion' => $idEstacion,
$campo => $valor,
]);
return true;
}

$record->update([$campo => $valor]);
return true;
}

public static function searchPersonal(int $idEstacion, string $query): array
{
$q = RhPersonal::where('estado', 1)
->where('id_estacion', $idEstacion);

if (!empty($query)) {
$q->where('nombre_completo', 'like', '%' . $query . '%');
}

$personal = $q->orderBy('nombre_completo')->get(['id', 'nombre_completo']);

$data = [];
foreach ($personal as $p) {
$data[] = [
'id' => $p->id,
'nombre' => $p->nombre_completo,
];
}
return $data;
}
}
