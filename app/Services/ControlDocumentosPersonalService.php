<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPersonalComentarios;
use App\Models\Operativo\RhPersonalBaja;
use App\Models\Operativo\RhPersonalBajaArchivos;
use App\Models\Operativo\RhPersonalBajaComentarios;
use App\Models\Operativo\RhPersonalAcceso;
use App\Models\Operativo\RhPersonalAsistencia;
use App\Models\Operativo\RhPersonalAsistenciaIncidencia;
use App\Models\Operativo\RhPuestos;
use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhListaIncidencias;
use App\Models\Usuario;
use App\Models\Estacion;

class ControlDocumentosPersonalService
{
const DOCUMENT_TYPES = [
'requisicion'      => ['label' => 'Requisición del Personal',       'folder' => 'requisicion',              'prefix' => 'Requisicion'],
'curriculum'       => ['label' => 'Curriculum Vitae',               'folder' => 'curriculum',               'prefix' => 'Curriculum'],
'ine'              => ['label' => 'Identificación Oficial',          'folder' => 'ine',                      'prefix' => 'Identificacion'],
'acta_nacimiento'  => ['label' => 'Acta de Nacimiento',             'folder' => 'acta_nacimiento',          'prefix' => 'Acta de Nacimiento'],
'c_domicilio'      => ['label' => 'Comprobante de Domicilio',       'folder' => 'comprobante_domicilio',    'prefix' => 'Comprobante de Domicilio'],
'nss'              => ['label' => 'Comprobante de Afiliación IMSS',  'folder' => 'nss',                      'prefix' => 'Comprobante IMSS'],
'c_estudios'       => ['label' => 'Comprobante de Estudios',        'folder' => 'comprobante_estudios',     'prefix' => 'Comprobante de Estudios'],
'c_recomendacion'  => ['label' => 'Cartas de Recomendación',        'folder' => 'cartas_recomendacion',     'prefix' => 'Carta de Recomendacion'],
'curp'             => ['label' => 'CURP',                           'folder' => 'curp',                     'prefix' => 'CURP'],
'a_infonavit'      => ['label' => 'Aviso Retención Infonavit',      'folder' => 'acta_infonavit',           'prefix' => 'Aviso Infonavit'],
'rfc'              => ['label' => 'Constancia Situación Fiscal',    'folder' => 'rfc',                      'prefix' => 'RFC'],
'c_antecedentes'   => ['label' => 'Antecedentes No Penales',        'folder' => 'carta_antecedentes',       'prefix' => 'Antecedentes Penales'],
'contrato'         => ['label' => 'Contrato Laboral',               'folder' => 'contrato',                 'prefix' => 'Contrato'],
];

public static function getPermisos(): array
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');
$idUsuario = (int)($sessionUsuario['id'] ?? 0);
$idPuesto = (int)($usuario->id_puesto ?? 0);
$idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
$multiestacion = !empty($sessionUsuario['multiestacion']);
$nombrePuesto = $usuario->puesto->tipo_puesto ?? '';

$permisosDb = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
$tienePermisoCrear = !empty($permisosDb['crear']);
$tienePermisoEditar = !empty($permisosDb['editar']);
$tienePermisoEliminar = !empty($permisosDb['eliminar']);
$tienePermisoDescargar = !empty($permisosDb['descargar']);

return [
'id_usuario'    => $idUsuario,
'id_estacion'   => $idEstacion,
'id_puesto'     => $idPuesto,
'nombre_puesto' => $nombrePuesto,
'multiestacion' => $multiestacion,
'puedeCrear'    => $multiestacion && $tienePermisoCrear,
'puedeEditar'   => $tienePermisoEditar,
'puedeEliminar' => $multiestacion && $tienePermisoEliminar,
'puedeDescargar'=> $tienePermisoDescargar,
];
}

public static function getAllowedEstacionIds(): array
{
$stations = ModuleStationService::getAvailableStations('control-documentos-personal');
$depts = ModuleStationService::getAvailableDepartments('control-documentos-personal');
$ids = [];
foreach ($stations as $s) {
$ids[] = (int)$s['id'];
}
foreach ($depts as $d) {
$ids[] = (int)$d['id'];
}
return array_values(array_unique($ids));
}

public static function resolveNombreEstacion(int $idEstacion): string
{
$loc = RhLocalidad::find($idEstacion);
if ($loc) return $loc->localidad;

$est = Estacion::find($idEstacion);
if ($est) return $est->nombre;

return '';
}

public static function getPersonalList(): array
{
$ctx = ModuleStationService::getContext('control-documentos-personal');
$idEstacion = $ctx['id_estacion'];
$idDepto = $ctx['id_depto'];

$q = RhPersonal::where('estado', 1);

if ($idDepto && !$idEstacion) {
$q->where('id_estacion', $idDepto);
} elseif ($idEstacion) {
$q->where('id_estacion', $idEstacion);
} else {
$allowedIds = self::getAllowedEstacionIds();
if (!empty($allowedIds)) {
$q->whereIn('id_estacion', $allowedIds);
}
}

$personal = $q->orderBy('id', 'asc')->get();
$data = [];

foreach ($personal as $p) {
$puesto = RhPuestos::find($p->puesto);
$nombrePuesto = $puesto ? $puesto->puesto : '';
$nombreEstacion = self::resolveNombreEstacion((int)$p->id_estacion);

$docs = [];
$docsPresentes = 0;
$docsRequeridos = 0;
foreach (self::DOCUMENT_TYPES as $campo => $info) {
$valor = $p->{$campo} ?? '';
$docs[$campo] = $valor;
if (!empty($valor)) $docsPresentes++;
}
$docsRequeridos = count(self::DOCUMENT_TYPES);

$requiereC_antecedentes = ($nombrePuesto === 'Despachador');
if (!$requiereC_antecedentes) {
$docsRequeridos--;
}

if ($docsPresentes === $docsRequeridos) {
$estatus = 'Finalizado';
} elseif ($docsPresentes === 0) {
$estatus = 'Pendiente';
} else {
$estatus = 'En proceso';
}

$numComentarios = RhPersonalComentarios::where('id_personal', $p->id)->count();

$data[] = [
'id'               => $p->id,
'id_estacion'      => (int)$p->id_estacion,
'nombre_estacion'  => $nombreEstacion,
'fecha_ingreso'    => normalizarFecha($p->fecha_ingreso?->format('Y-m-d')) ?? '',
'fecha_ingreso_format' => formatearFecha($p->fecha_ingreso?->format('Y-m-d')) ?: 'S/I',
'no_colaborador'   => $p->no_colaborador ?? '',
'nombre_completo'  => $p->nombre_completo ?? '',
'puesto'           => $nombrePuesto,
'sd'               => $p->sd ?? 0,
'documentos'       => $docs,
'documentos_archivo' => $p->documentos ?? '',
'estatus'          => $estatus,
'num_comentarios'  => $numComentarios,
];
}

return $data;
}

public static function getPersonalInactivosList(): array
{
$ctx = ModuleStationService::getContext('control-documentos-personal');
$idEstacion = $ctx['id_estacion'];
$idDepto = $ctx['id_depto'];

$q = RhPersonal::where('estado', 0);

if ($idDepto && !$idEstacion) {
$q->where('id_estacion', $idDepto);
} elseif ($idEstacion) {
$q->where('id_estacion', $idEstacion);
} else {
$allowedIds = self::getAllowedEstacionIds();
if (!empty($allowedIds)) {
$q->whereIn('id_estacion', $allowedIds);
}
}

$personal = $q->orderBy('id', 'asc')->get();
$data = [];

foreach ($personal as $p) {
$puesto = RhPuestos::find($p->puesto);
$nombrePuesto = $puesto ? $puesto->puesto : '';
$nombreEstacion = self::resolveNombreEstacion((int)$p->id_estacion);

$docs = [];
$docsPresentes = 0;
$docsRequeridos = count(self::DOCUMENT_TYPES);
foreach (self::DOCUMENT_TYPES as $campo => $info) {
$valor = $p->{$campo} ?? '';
$docs[$campo] = $valor;
if (!empty($valor)) $docsPresentes++;
}

$requiereC_antecedentes = ($nombrePuesto === 'Despachador');
if (!$requiereC_antecedentes) {
$docsRequeridos--;
}

if ($docsPresentes === $docsRequeridos) {
$estatus = 'Finalizado';
} elseif ($docsPresentes === 0) {
$estatus = 'Pendiente';
} else {
$estatus = 'En proceso';
}

$numComentarios = RhPersonalComentarios::where('id_personal', $p->id)->count();

$baja = RhPersonalBaja::where('id_personal', $p->id)
->orderBy('id', 'desc')
->first();

$bajaInfo = null;
if ($baja) {
$bajaInfo = [
'id_baja'        => $baja->id,
'fecha_baja'     => formatearFecha($baja->fecha_baja?->format('Y-m-d')) ?: 'S/I',
'estado_proceso' => (int)($baja->estado_proceso ?? 0),
];
}

$data[] = [
'id'               => $p->id,
'id_estacion'      => (int)$p->id_estacion,
'nombre_estacion'  => $nombreEstacion,
'fecha_ingreso'    => normalizarFecha($p->fecha_ingreso?->format('Y-m-d')) ?? '',
'fecha_ingreso_format' => formatearFecha($p->fecha_ingreso?->format('Y-m-d')) ?: 'S/I',
'no_colaborador'   => $p->no_colaborador ?? '',
'nombre_completo'  => $p->nombre_completo ?? '',
'puesto'           => $nombrePuesto,
'sd'               => $p->sd ?? 0,
'documentos'       => $docs,
'documentos_archivo' => $p->documentos ?? '',
'estatus'          => $estatus,
'num_comentarios'  => $numComentarios,
'baja'             => $bajaInfo,
];
}

return $data;
}

public static function addPersonal(array $input, array $files): ?int
{
$ctx = ModuleStationService::getContext('control-documentos-personal');
$idEstacion = (int)($input['id_estacion'] ?? 0);
if (!$idEstacion) $idEstacion = $ctx['id_estacion'] ?? 0;
if (!$idEstacion) return null;

$uploadDir = self::getUploadDir();
$data = [
'id_estacion'      => $idEstacion,
'fecha_ingreso'    => normalizarFecha($input['fecha_ingreso'] ?? ''),
'no_colaborador'   => (int)($input['no_colaborador'] ?? 0),
'nombre_completo'  => trim($input['nombre_completo'] ?? ''),
'puesto'           => (int)($input['puesto'] ?? 0),
'sd'               => (float)($input['sd'] ?? 0),
'estado'           => 1,
];

foreach (self::DOCUMENT_TYPES as $campo => $info) {
$data[$campo] = '';
}

$nombrePersonal = $data['nombre_completo'];

$record = RhPersonal::create($data);
if (!$record) return null;

foreach (self::DOCUMENT_TYPES as $campo => $info) {
$fileKey = 'doc_' . $campo;
if (!empty($files[$fileKey]) && $files[$fileKey]['error'] === 0) {
self::uploadFile($record->id, $campo, $files[$fileKey], $nombrePersonal);
}
}

self::notify($idEstacion, 'agregó personal', [
'nombre' => $nombrePersonal,
'puesto' => RhPuestos::find($data['puesto'])->puesto ?? '',
]);

return $record->id;
}

public static function editPersonal(int $id, array $input, array $files): bool
{
$record = RhPersonal::find($id);
if (!$record) return false;

$record->update([
'id_estacion'     => (int)($input['id_estacion'] ?? $record->id_estacion),
'fecha_ingreso'   => normalizarFecha($input['fecha_ingreso'] ?? ''),
'no_colaborador'  => (int)($input['no_colaborador'] ?? 0),
'nombre_completo' => trim($input['nombre_completo'] ?? ''),
'puesto'          => (int)($input['puesto'] ?? 0),
'sd'              => (float)($input['sd'] ?? 0),
]);

$nombrePersonal = $record->nombre_completo;

foreach (self::DOCUMENT_TYPES as $campo => $info) {
$fileKey = 'doc_' . $campo;
if (!empty($files[$fileKey]) && $files[$fileKey]['error'] === 0) {
self::uploadFile($record->id, $campo, $files[$fileKey], $nombrePersonal);
}
}

self::notify($record->id_estacion, 'modificó personal', [
'nombre' => $nombrePersonal,
'puesto' => RhPuestos::find($record->puesto)->puesto ?? '',
]);

return true;
}

public static function deletePersonal(int $id): bool
{
$record = RhPersonal::find($id);
if (!$record) return false;

$idEstacion = $record->id_estacion;
$nombrePersonal = $record->nombre_completo;
$puesto = RhPuestos::find($record->puesto);
$nombrePuesto = $puesto ? $puesto->puesto : '';

$record->update(['estado' => 0]);

self::notify($idEstacion, 'eliminó personal', [
'nombre' => $nombrePersonal,
'puesto' => $nombrePuesto,
]);

return true;
}

public static function getComentarios(int $idPersonal): array
{
$sessionUsuario = Session::get('usuario');
$idUsuarioActual = (int)($sessionUsuario['id'] ?? 0);

$comentarios = RhPersonalComentarios::where('id_personal', $idPersonal)
->orderBy('id', 'asc')
->get();

$data = [];
foreach ($comentarios as $c) {
$usuario = Usuario::find($c->id_usuario);
$data[] = [
'id'             => $c->id,
'usuario_nombre' => $usuario ? $usuario->nombre : 'Sistema',
'comentario'     => $c->comentario ?? '',
'fecha_hora'     => ($c->fecha_hora && ($fechaFmt = formatearFecha($c->fecha_hora->format('Y-m-d H:i:s')))) ? $fechaFmt . ', ' . $c->fecha_hora->format('g:i a') : '-',
'esPropio'       => (int)$c->id_usuario === $idUsuarioActual,
];
}
return $data;
}

public static function addComentario(int $idPersonal, string $comentario): bool
{
$sessionUsuario = Session::get('usuario');
$idUsuario = (int)($sessionUsuario['id'] ?? 0);

$record = RhPersonal::find($idPersonal);
if (!$record) return false;

RhPersonalComentarios::create([
'id_personal' => $idPersonal,
'id_usuario'  => $idUsuario,
'comentario'  => $comentario,
'fecha_hora'  => date('Y-m-d H:i:s'),
]);

self::notify($record->id_estacion, 'comentó personal', [
'nombre'     => $record->nombre_completo,
'comentario' => mb_strimwidth($comentario, 0, 100, '...'),
]);

return true;
}

public static function uploadDocumento(int $idPersonal, string $tipo, array $file): bool
{
$record = RhPersonal::find($idPersonal);
if (!$record) return false;

if (!isset(self::DOCUMENT_TYPES[$tipo])) return false;

$nombrePersonal = $record->nombre_completo;
self::uploadFile($idPersonal, $tipo, $file, $nombrePersonal);
return true;
}

public static function deleteDocumento(int $idPersonal, string $tipo): bool
{
$record = RhPersonal::find($idPersonal);
if (!$record) return false;

if (!isset(self::DOCUMENT_TYPES[$tipo])) return false;

$campo = $tipo;
$filename = $record->{$campo};
if (!empty($filename)) {
$info = self::DOCUMENT_TYPES[$tipo];
$dir = self::getUploadDir() . $info['folder'] . '/';
if (file_exists($dir . $filename)) {
unlink($dir . $filename);
}
$record->update([$campo => '']);
}

return true;
}

private static function uploadFile(int $idPersonal, string $campo, array $file, string $nombrePersonal): void
{
$info = self::DOCUMENT_TYPES[$campo];
$uploadDir = self::getUploadDir() . $info['folder'] . '/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

$aleatorio1 = rand(1, 1000000);
$aleatorio2 = rand(1000, 9999);
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = $aleatorio1 . '-' . $info['prefix'] . '-' . $nombrePersonal . '-' . $aleatorio2 . '.' . $ext;

move_uploaded_file($file['tmp_name'], $uploadDir . $filename);

$record = RhPersonal::find($idPersonal);
if ($record) {
$oldFile = $record->{$campo};
if (!empty($oldFile) && file_exists($uploadDir . $oldFile)) {
unlink($uploadDir . $oldFile);
}
$record->update([$campo => $filename]);
}
}

public static function getUploadDir(): string
{
$dir = __DIR__ . '/../../public/uploads/archivos/documentos-personal/';
if (!is_dir($dir)) mkdir($dir, 0775, true);
return $dir;
}

public static function getEstaciones(): array
{
$stations = ModuleStationService::getAvailableStations('control-documentos-personal');
$depts = ModuleStationService::getAvailableDepartments('control-documentos-personal');
$result = [];
foreach ($stations as $s) {
$result[] = ['id' => $s['id'], 'nombre' => $s['nombre'], 'tipo' => 'estacion'];
}
foreach ($depts as $d) {
$result[] = ['id' => $d['id'], 'nombre' => $d['nombre'], 'tipo' => 'departamento'];
}
return $result;
}

public static function getPersonalById(int $id): ?array
{
$p = RhPersonal::find($id);
if (!$p) return null;

$puesto = RhPuestos::find($p->puesto);
$nombreEstacion = self::resolveNombreEstacion((int)$p->id_estacion);

$docs = [];
foreach (self::DOCUMENT_TYPES as $campo => $info) {
$docs[$campo] = $p->{$campo} ?? '';
}

return [
'id'               => $p->id,
'id_estacion'      => (int)$p->id_estacion,
'nombre_estacion'  => $nombreEstacion,
'fecha_ingreso'    => normalizarFecha($p->fecha_ingreso?->format('Y-m-d')) ?? '',
'no_colaborador'   => $p->no_colaborador ?? '',
'nombre_completo'  => $p->nombre_completo ?? '',
'puesto'           => (int)$p->puesto,
'puesto_nombre'    => $puesto ? $puesto->puesto : '',
'sd'               => $p->sd ?? 0,
'documentos'       => $docs,
];
}

public static function getFileUrl(string $tipo, string $archivo): string
{
if (!isset(self::DOCUMENT_TYPES[$tipo])) return '';
$downloadTipo = 'docs-personal-' . str_replace('_', '-', $tipo);
return "/download?tipo={$downloadTipo}&file=" . urlencode($archivo);
}

public static function addBajaPersonal(int $idPersonal, array $input, array $files): bool
{
$record = RhPersonal::find($idPersonal);
if (!$record) return false;

$sessionUsuario = Session::get('usuario');
$idUsuario = (int)($sessionUsuario['id'] ?? 0);

$baja = RhPersonalBaja::create([
'id_personal'     => $idPersonal,
'fecha_baja'      => normalizarFecha($input['fecha_baja'] ?? ''),
'motivo'          => $input['motivo'] ?? '',
'detalle'         => $input['detalle'] ?? '',
'solucion'        => '',
'proceso'         => '',
'estado_proceso'  => 0,
]);

$uploadDir = self::getUploadDir() . 'solicitud-baja/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

$bajaDocs = ['acta_hechos' => 'Acta de hechos', 'carta_renuncia' => 'Carta de Renuncia', 'finiquito' => 'Finiquito'];
foreach ($bajaDocs as $key => $label) {
if (!empty($files[$key]) && $files[$key]['error'] === 0) {
$aleatorio1 = rand(1, 1000000);
$aleatorio2 = rand(1000, 9999);
$ext = pathinfo($files[$key]['name'], PATHINFO_EXTENSION);
$filename = $aleatorio1 . '-' . $label . '-' . $aleatorio2 . '.' . $ext;
move_uploaded_file($files[$key]['tmp_name'], $uploadDir . $filename);

RhPersonalBajaArchivos::create([
'id_baja'     => $baja->id,
'descripcion' => $label,
'archivo'     => $filename,
]);
}
}

$record->update(['estado' => 0]);

if (($input['motivo'] ?? '') === 'Mala practica') {
$record->lista_negra()->create([
'fecha'   => normalizarFecha($input['fecha_baja'] ?? ''),
'motivo'  => $input['motivo'] ?? '',
'detalle' => $input['detalle'] ?? '',
]);
}

$puesto = RhPuestos::find($record->puesto);
self::notify($record->id_estacion, 'dió de baja', [
'nombre' => $record->nombre_completo,
'puesto' => $puesto ? $puesto->puesto : '',
'fecha'  => $input['fecha_baja'] ?? '',
'motivo' => $input['motivo'] ?? '',
]);

return true;
}

private static function notify(int $idEstacion, string $accion, array $extra = []): void
{
try {
$sessionUsuario = Session::get('usuario');
$idUsuario = (int)($sessionUsuario['id'] ?? 0);

$user = Usuario::find($idUsuario);
$estacion = Estacion::find($idEstacion);
$nombreUsuario = $user ? $user->nombre : 'Desconocido';

$iconos = [
'agregó personal'          => '✅',
'modificó personal'        => '🔄',
'eliminó personal'         => '🗑',
'comentó personal'         => '💭',
'dió de baja'              => '👎',
'subió archivo de baja'    => '📎',
'eliminó archivo de baja'  => '🗑',
'agregó comentario en baja'=> '💬',
'editó PIN'                => '🔐',
];
$icono = $iconos[$accion] ?? '📝';

$loc = RhLocalidad::find($idEstacion);
if ($loc) {
$nombreDestino = $loc->localidad;
$isDept = $loc->numlista > 8;
$prefijo = $isDept ? '🏢 Departamento' : '⛽ Estación';
} else {
$est = Estacion::find($idEstacion);
$nombreDestino = $est ? $est->nombre : 'Estación #' . $idEstacion;
$isDept = false;
$prefijo = '⛽ Estación';
}

$detalle = $icono . ' Se ha <b>' . $accion . '</b> en el apartado de <b>Recursos Humanos</b>, correspondiente al módulo de <b>Control de Documentos del Personal</b>:'
. PHP_EOL . PHP_EOL;

if (isset($extra['nombre'])) {
$detalle .= '👤 <b>Nombre:</b> ' . $extra['nombre'] . PHP_EOL;
}
if (isset($extra['puesto'])) {
$detalle .= '💼 <b>Puesto:</b> ' . $extra['puesto'] . PHP_EOL;
}
if (isset($extra['fecha'])) {
$detalle .= '📅 <b>Fecha de baja:</b> ' . formatearFecha($extra['fecha']) . PHP_EOL;
}
if (isset($extra['motivo'])) {
$detalle .= '📋 <b>Motivo:</b> ' . $extra['motivo'] . PHP_EOL;
}
if (isset($extra['descripcion'])) {
$detalle .= '📝 <b>Descripción:</b> ' . $extra['descripcion'] . PHP_EOL;
}
if (isset($extra['comentario'])) {
$detalle .= '💬 <b>Comentario:</b> ' . $extra['comentario'] . PHP_EOL;
}
if (isset($extra['campo'])) {
$detalle .= '🔑 <b>Campo:</b> ' . $extra['campo'] . PHP_EOL;
}

$detalle .= PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. $prefijo . ': ' . $nombreDestino;

TelegramService::notificar($idEstacion, $idUsuario, $detalle);
} catch (\Throwable $e) {
error_log('Error notificando control documentos personal: ' . $e->getMessage());
}
}

public static function getAcceso(int $idPersonal): array
{
$acceso = RhPersonalAcceso::where('id_personal', $idPersonal)->first();

if (!$acceso) {
$acceso = RhPersonalAcceso::create([
'id_personal' => $idPersonal,
'huella'      => '',
'pin'         => 0,
]);
}

$personal = RhPersonal::find($idPersonal);
$puesto = $personal ? RhPuestos::find($personal->puesto) : null;
$nombrePuesto = $puesto ? $puesto->puesto : '';

return [
'huella'       => $acceso->huella ?? '',
'pin'          => $acceso->pin ?? 0,
'nombre_puesto' => $nombrePuesto,
];
}

public static function getArchivosBaja(int $idBaja): array
{
$archivos = RhPersonalBajaArchivos::where('id_baja', $idBaja)
->orderBy('id', 'asc')
->get();

$data = [];
foreach ($archivos as $a) {
$data[] = [
'id'          => $a->id,
'descripcion' => $a->descripcion ?? '',
'archivo'     => $a->archivo ?? '',
'url'         => ($a->archivo ?? '') ? '/download?tipo=docs-personal-baja&file=' . urlencode($a->archivo) : '',
];
}
return $data;
}

public static function uploadBajaArchivo(int $idBaja, string $descripcion, array $file): bool
{
$baja = RhPersonalBaja::find($idBaja);
if (!$baja) return false;

if (empty($file) || $file['error'] !== 0) return false;

$uploadDir = self::getUploadDir() . 'solicitud-baja/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

$personal = RhPersonal::find($baja->id_personal);
$nombrePersonal = $personal ? $personal->nombre_completo : 'personal';

$aleatorio1 = rand(1, 1000000);
$aleatorio2 = rand(1000, 9999);
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = $aleatorio1 . '-' . ($descripcion ?: 'archivo') . '-' . $aleatorio2 . '.' . $ext;

move_uploaded_file($file['tmp_name'], $uploadDir . $filename);

RhPersonalBajaArchivos::create([
'id_baja'     => $idBaja,
'descripcion' => $descripcion,
'archivo'     => $filename,
]);

self::notify((int)$personal->id_estacion, 'subió archivo de baja', [
'nombre'      => $personal ? $personal->nombre_completo : '',
'descripcion' => $descripcion,
]);

return true;
}

public static function getComentariosBaja(int $idBaja): array
{
$sessionUsuario = Session::get('usuario');
$idUsuarioActual = (int)($sessionUsuario['id'] ?? 0);

$comentarios = RhPersonalBajaComentarios::where('id_baja', $idBaja)
->orderBy('id', 'asc')
->get();

$data = [];
foreach ($comentarios as $c) {
$usuario = Usuario::find($c->id_usuario);
$data[] = [
'id'             => $c->id,
'usuario_nombre' => $usuario ? $usuario->nombre : 'Sistema',
'comentario'     => $c->comentario ?? '',
'fecha_hora'     => ($c->fecha_hora && ($fechaFmt = formatearFecha($c->fecha_hora->format('Y-m-d H:i:s')))) ? $fechaFmt . ', ' . $c->fecha_hora->format('g:i a') : '-',
'esPropio'       => (int)$c->id_usuario === $idUsuarioActual,
];
}
return $data;
}

public static function addComentarioBaja(int $idBaja, string $comentario): bool
{
$sessionUsuario = Session::get('usuario');
$idUsuario = (int)($sessionUsuario['id'] ?? 0);

$baja = RhPersonalBaja::find($idBaja);
if (!$baja) return false;

$personal = RhPersonal::find($baja->id_personal);

RhPersonalBajaComentarios::create([
'id_baja'    => $idBaja,
'id_usuario' => $idUsuario,
'comentario' => $comentario,
'fecha_hora' => date('Y-m-d H:i:s'),
]);

if ($personal) {
self::notify((int)$personal->id_estacion, 'agregó comentario en baja', [
'nombre'      => $personal->nombre_completo ?? '',
'comentario'  => mb_strimwidth($comentario, 0, 100, '...'),
]);
}

return true;
}

public static function deleteBajaArchivo(int $idArchivo): bool
{
$archivo = RhPersonalBajaArchivos::find($idArchivo);
if (!$archivo) return false;

$baja = RhPersonalBaja::find($archivo->id_baja);
$descripcion = $archivo->descripcion ?? '';
$idEstacion = 0;
$nombrePersonal = '';
if ($baja) {
$personal = RhPersonal::find($baja->id_personal);
if ($personal) {
$idEstacion = (int)$personal->id_estacion;
$nombrePersonal = $personal->nombre_completo ?? '';
}
}

if (!empty($archivo->archivo)) {
$dir = self::getUploadDir() . 'solicitud-baja/';
$fullPath = $dir . $archivo->archivo;
if (file_exists($fullPath)) {
unlink($fullPath);
}
}

$archivo->delete();

if ($idEstacion) {
self::notify($idEstacion, 'eliminó archivo de baja', [
'nombre'      => $nombrePersonal,
'descripcion' => $descripcion,
]);
}

return true;
}

public static function getBajaDetalle(int $idBaja): ?array
{
$baja = RhPersonalBaja::find($idBaja);
if (!$baja) return null;

$personal = RhPersonal::find($baja->id_personal);
if (!$personal) return null;

$puesto = RhPuestos::find($personal->puesto);
$nombreEstacion = self::resolveNombreEstacion((int)$personal->id_estacion);

$docs = [];
foreach (self::DOCUMENT_TYPES as $campo => $info) {
$docs[$campo] = [
'label'    => $info['label'],
'archivo'  => $personal->{$campo} ?? '',
'url'      => ($personal->{$campo} ?? '') ? self::getFileUrl($campo, $personal->{$campo}) : '',
];
}

$bajaArchivos = RhPersonalBajaArchivos::where('id_baja', $idBaja)->get();
$archivosBaja = [];
foreach ($bajaArchivos as $a) {
$archivosBaja[] = [
'id'          => $a->id,
'descripcion' => $a->descripcion ?? '',
'archivo'     => $a->archivo ?? '',
'url'         => ($a->archivo ?? '') ? '/download?tipo=docs-personal-baja&file=' . urlencode($a->archivo) : '',
];
}

$sessionUsuario = Session::get('usuario');
$idUsuarioActual = (int)($sessionUsuario['id'] ?? 0);

$comentarios = RhPersonalBajaComentarios::where('id_baja', $idBaja)
->orderBy('id', 'asc')
->get();
$comentariosData = [];
foreach ($comentarios as $c) {
$usuario = Usuario::find($c->id_usuario);
$comentariosData[] = [
'id'             => $c->id,
'usuario_nombre' => $usuario ? $usuario->nombre : 'Sistema',
'comentario'     => $c->comentario ?? '',
'fecha_hora'     => ($c->fecha_hora && ($fechaFmt = formatearFecha($c->fecha_hora->format('Y-m-d H:i:s')))) ? $fechaFmt . ', ' . $c->fecha_hora->format('g:i a') : '-',
'esPropio'       => (int)$c->id_usuario === $idUsuarioActual,
];
}

$estadoProceso = (int)($baja->estado_proceso ?? 0);
if ($estadoProceso === 0) {
$badgeClass = 'bg-danger';
$badgeLabel = 'Pendiente';
} elseif ($estadoProceso === 1) {
$badgeClass = 'bg-warning text-dark';
$badgeLabel = 'En Proceso';
} else {
$badgeClass = 'bg-success';
$badgeLabel = 'Finalizado';
}

return [
'id'                => (int)$baja->id,
'id_personal'       => (int)$personal->id,
'id_estacion'       => (int)$personal->id_estacion,
'nombre_estacion'   => $nombreEstacion,
'nombre_completo'   => $personal->nombre_completo ?? '',
'no_colaborador'    => $personal->no_colaborador ?? '',
'puesto'            => $puesto ? $puesto->puesto : '',
'fecha_ingreso'     => formatearFecha($personal->fecha_ingreso?->format('Y-m-d')) ?: 'S/I',
'fecha_baja'        => formatearFecha($baja->fecha_baja?->format('Y-m-d')),
'motivo'            => $baja->motivo ?? '',
'detalle'           => $baja->detalle ?? '',
'solucion'          => $baja->solucion ?? '',
'has_solucion'      => !empty($baja->solucion),
'proceso'           => $baja->proceso ?? '',
'estado_proceso'    => $estadoProceso,
'badge_class'       => $badgeClass,
'badge_label'       => $badgeLabel,
'documentos'        => $docs,
'documentos_archivo' => $personal->documentos ?? '',
'has_docs_archivo'  => !empty($personal->documentos),
'has_ine'           => !empty($personal->ine),
'has_curp'          => !empty($personal->curp),
'has_rfc'           => !empty($personal->rfc),
'has_nss'           => !empty($personal->nss),
'has_contrato'      => !empty($personal->contrato),
'archivos_baja'     => $archivosBaja,
'comentarios'       => $comentariosData,
'total_comentarios' => count($comentariosData),
];
}

public static function getAsistenciaPersonal(int $idPersonal): ?array
{
$personal = RhPersonal::find($idPersonal);
if (!$personal) return null;

return [
'id'                => $personal->id,
'nombre_completo'   => $personal->nombre_completo ?? '',
];
}

public static function getAsistenciaData(int $idPersonal): array
{
$asistencias = RhPersonalAsistencia::where('id_personal', $idPersonal)
->orderBy('fecha', 'desc')
->get();

$data = [];
foreach ($asistencias as $a) {
$incidencias = RhPersonalAsistenciaIncidencia::where('id_asistencia', $a->id)
->where('estado', 1)
->get();

$incidenciasDetalle = [];
foreach ($incidencias as $inc) {
$incidenciasDetalle[] = [
'fecha'       => ($inc->fecha && ($fechaFmt = formatearFecha($inc->fecha))) ? $fechaFmt . ', ' . date('g:i a', strtotime($inc->fecha)) : '-',
'incidencia'  => $inc->incidencia ?? '',
'comentario'  => $inc->comentario ?? '',
];
}

$detalle = self::getDetalleAsistencia(
$a->fecha ?: '',
$a->hora_entrada ?: '00:00:00',
$a->hora_salida ?: '00:00:00',
$a->hora_entrada_sensor ?: '00:00:00',
$a->hora_salida_sensor ?: '00:00:00',
(int)($a->retardo_minutos ?? 0)
);

$data[] = [
'id'                  => $a->id,
'fecha'               => formatearFecha($a->fecha) ?: 'S/I',
'fecha_raw'           => $a->fecha ?: '',
'hora_entrada'        => $a->hora_entrada ?: '',
'hora_salida'         => $a->hora_salida ?: '',
'hora_entrada_sensor' => $a->hora_entrada_sensor ?: '',
'hora_salida_sensor'  => $a->hora_salida_sensor ?: '',
'retardo_minutos'     => (int)($a->retardo_minutos ?? 0),
'incidencia_dias'     => (int)($a->incidencia_dias ?? 0),
'sd'                  => (float)($a->sd ?? 0),
'detalle'             => $detalle,
'detalle_badge'       => self::getDetalleBadgeClass($detalle),
'incidencias'         => $incidenciasDetalle,
'total_incidencias'   => $incidencias->count(),
];
}

return $data;
}

public static function getDetalleAsistencia(string $fecha, string $horaEntrada, string $horaSalida, string $horaEntradaSensor, string $horaSalidaSensor, int $retardoMinutos): string
{
if ($horaEntrada === '00:00:00' && $horaSalida === '00:00:00') {
if ($horaEntradaSensor !== '00:00:00') {
return 'Día trabajado';
}
return 'Descanso';
}

if ($horaEntradaSensor !== '00:00:00' || $horaSalidaSensor !== '00:00:00') {
$tsFin = strtotime($horaEntradaSensor);
$tsIni = strtotime($horaEntrada);

if (is_numeric($tsFin - $tsIni) && ($tsFin - $tsIni) < 0) {
return 'OK';
}

$retardo = $retardoMinutos * 60;
$horaInicio = $tsIni + $retardo;

if ($horaInicio < $tsFin) {
return 'Retardo';
}

return 'OK';
}

$diaSemana = date('w', strtotime($fecha));
if ($diaSemana == 0 || $diaSemana == 6) {
return 'Falta fin de semana';
}

return 'Falta';
}

public static function getDetalleBadgeClass(string $detalle): string
{
switch ($detalle) {
case 'Día trabajado':  return 'bg-success';
case 'Descanso':       return 'bg-info';
case 'Retardo':        return 'bg-warning text-dark';
case 'OK':             return 'bg-light text-dark border';
case 'Falta':          return 'bg-danger';
case 'Falta fin de semana': return 'bg-danger';
default:               return 'bg-secondary';
}
}

public static function getIncidenciasCatalogo(): array
{
$catalogo = RhListaIncidencias::orderBy('id')->get();
$resultado = [];
foreach ($catalogo as $item) {
$resultado[] = [
'id'        => (int)$item->id,
'detalle'   => $item->detalle ?? '',
'puntos'    => $item->puntos ?? 0,
'documento' => (int)$item->documento,
];
}
return $resultado;
}

public static function getIncidenciaPorAsistencia(int $idAsistencia): array
{
$asistencia = RhPersonalAsistencia::find($idAsistencia);
$puntos = $asistencia ? (float)($asistencia->incidencia ?? 0) : 0;

$fechaAsistencia = '';
if ($asistencia && $asistencia->fecha) {
$raw = $asistencia->fecha instanceof \DateTime ? $asistencia->fecha->format('Y-m-d H:i:s') : $asistencia->fecha;
$fechaAsistencia = formatearFecha($raw);
}

$incidencia = RhPersonalAsistenciaIncidencia::where('id_asistencia', $idAsistencia)
->where('estado', 1)
->orderBy('id', 'desc')
->first();

if (!$incidencia) {
return [
'existe'    => false,
'fecha'     => $fechaAsistencia,
'incidencia' => '',
'comentario' => '',
'documento'  => '',
];
}

$requiereDocumento = false;
$incidenciaNombre = trim($incidencia->incidencia ?? '');
$catalogoItem = RhListaIncidencias::where('detalle', $incidenciaNombre)->first();
if ($catalogoItem) {
$requiereDocumento = (int)$catalogoItem->documento === 1;
} else {
$requiereDocumento = in_array($incidenciaNombre, ['Incapacidad', 'Incapacidad IMSS', 'Maternidad']);
}

return [
'existe'      => true,
'fecha'       => $fechaAsistencia,
'incidencia'  => $incidencia->incidencia ?? '',
'comentario'  => $incidencia->comentario ?? '',
'documento'   => $incidencia->documento ?? '',
'requiere_documento' => $requiereDocumento,
'puntos'      => $puntos,
'fecha_inicio'     => $incidencia->fecha_inicio ? formatearFecha($incidencia->fecha_inicio) : '',
'fecha_fin'        => $incidencia->fecha_fin ? formatearFecha($incidencia->fecha_fin) : '',
'fecha_inicio_raw' => $incidencia->fecha_inicio ?: '',
'fecha_fin_raw'    => $incidencia->fecha_fin ?: '',
'sueldo_dia'       => $incidencia->sueldo_dia !== null ? (float)$incidencia->sueldo_dia : '',
];
}

public static function agregarIncidencia(int $idAsistencia, int $idIncidenciaCatalogo, string $comentario, ?string $fechaInicio = null, ?string $fechaFin = null, ?float $sueldoDia = null, ?string $documentoRuta = null): array
{
$asistencia = RhPersonalAsistencia::find($idAsistencia);
if (!$asistencia) {
return ['success' => false, 'message' => 'No se encontró el registro de asistencia.'];
}

$ahora = new \DateTime('now');
$fechaAsistencia = $asistencia->fecha ? new \DateTime($asistencia->fecha) : null;

if (!$fechaAsistencia) {
return ['success' => false, 'message' => 'El registro de asistencia no tiene fecha válida.'];
}

$catalogo = RhListaIncidencias::find($idIncidenciaCatalogo);
if (!$catalogo) {
return ['success' => false, 'message' => 'Tipo de incidencia no válido.'];
}

$detalle = $catalogo->detalle;

$yaExiste = RhPersonalAsistenciaIncidencia::where('id_asistencia', $idAsistencia)
->where('estado', 1)
->count();

if ($yaExiste > 0) {
return ['success' => false, 'message' => 'Ya existe una incidencia registrada para este día.'];
}

try {
RhPersonalAsistenciaIncidencia::create([
'id_asistencia' => $idAsistencia,
'fecha'         => $ahora->format('Y-m-d H:i:s'),
'incidencia'    => $detalle,
'comentario'    => trim($comentario),
'documento'     => $documentoRuta ?? '',
'fecha_inicio'  => $fechaInicio,
'fecha_fin'     => $fechaFin,
'sueldo_dia'    => $sueldoDia,
'estado'        => 1,
]);

$asistencia->incidencia = $idIncidenciaCatalogo;
$asistencia->save();
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al guardar la incidencia: ' . $e->getMessage()];
}

$personal = RhPersonal::find($asistencia->id_personal);
$nombrePersonal = $personal ? $personal->nombre_completo : 'Desconocido';
$usuario = Session::get('nombre') ?? (Auth::user()?->nombre ?? 'Sistema');
$fechaFmt = $asistencia->fecha ? formatearFecha($asistencia->fecha) : 'S/I';

$mensaje = "📋 *Nueva incidencia registrada*\n";
$mensaje .= "━━━━━━━━━━━━━━━━━━━━━━\n";
$mensaje .= "👤 *Personal:* {$nombrePersonal}\n";
$mensaje .= "📅 *Fecha:* {$fechaFmt}\n";
$mensaje .= "⚠️ *Incidencia:* {$detalle}\n";
if (trim($comentario)) {
$mensaje .= "💬 *Comentario:* " . trim($comentario) . "\n";
}
$mensaje .= "📝 *Registró:* {$usuario}";

try {
TelegramService::notificar((int)$asistencia->id_estacion, (int)(Session::get('id_usuario') ?? 0), $mensaje);
} catch (\Throwable $e) {
error_log("Error Telegram incidencia: " . $e->getMessage());
}

return ['success' => true, 'message' => 'Incidencia registrada correctamente.'];
}

public static function subirDocumentoIncidencia(int $idAsistencia, string $documentoRuta, ?string $fechaInicio = null, ?string $fechaFin = null, ?float $sueldoDia = null): array
{
$incidencia = RhPersonalAsistenciaIncidencia::where('id_asistencia', $idAsistencia)
->where('estado', 1)
->orderBy('id', 'desc')
->first();

if (!$incidencia) {
return ['success' => false, 'message' => 'No se encontró una incidencia registrada para este día.'];
}

try {
$incidencia->documento = $documentoRuta;
if ($fechaInicio !== null) {
$incidencia->fecha_inicio = $fechaInicio;
}
if ($fechaFin !== null) {
$incidencia->fecha_fin = $fechaFin;
}
if ($sueldoDia !== null) {
$incidencia->sueldo_dia = $sueldoDia;
}
$incidencia->save();
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al guardar el documento: ' . $e->getMessage()];
}

$asistencia = RhPersonalAsistencia::find($idAsistencia);
if ($asistencia && $sueldoDia !== null) {
try {
$asistencia->incidencia = $sueldoDia;
$asistencia->save();
} catch (\Exception $e) {
error_log("Error actualizando sueldo día: " . $e->getMessage());
}
}

try {
$personal = $asistencia ? RhPersonal::find($asistencia->id_personal) : null;
$nombrePersonal = $personal ? $personal->nombre_completo : 'Desconocido';
$usuario = Session::get('nombre') ?? (Auth::user()?->nombre ?? 'Sistema');
$fechaFmt = $asistencia && $asistencia->fecha ? formatearFecha($asistencia->fecha) : 'S/I';

$mensaje = "📄 *Documento de incapacidad adjuntado*\n";
$mensaje .= "━━━━━━━━━━━━━━━━━━━━━━\n";
$mensaje .= "👤 *Personal:* {$nombrePersonal}\n";
$mensaje .= "📅 *Fecha:* {$fechaFmt}\n";
if ($fechaInicio && $fechaFin) {
$mensaje .= "📆 *Periodo:* " . formatearFecha($fechaInicio) . " al " . formatearFecha($fechaFin) . "\n";
}
$mensaje .= "📝 *Registró:* {$usuario}";

TelegramService::notificar((int)($asistencia->id_estacion ?? 0), (int)(Session::get('id_usuario') ?? 0), $mensaje);
} catch (\Throwable $e) {
error_log("Error Telegram documento incapacidad: " . $e->getMessage());
}

return ['success' => true, 'message' => 'Documento guardado correctamente.'];
}

public static function crearIncidencia(int $idAsistencia, string $incidencia, string $comentario, ?string $documento): array
{
$asistencia = RhPersonalAsistencia::find($idAsistencia);
if (!$asistencia) {
return ['success' => false, 'message' => 'No se encontró el registro de asistencia.'];
}

$ahora = new \DateTime('now');
$fechaAsistencia = $asistencia->fecha ? new \DateTime($asistencia->fecha) : null;

if (!$fechaAsistencia) {
return ['success' => false, 'message' => 'El registro de asistencia no tiene fecha válida.'];
}

$mes = (int)$fechaAsistencia->format('m');
$anio = (int)$fechaAsistencia->format('Y');
$mesActual = (int)$ahora->format('m');
$anioActual = (int)$ahora->format('Y');

if ($anio < $anioActual || ($anio === $anioActual && $mes < $mesActual)) {
return ['success' => false, 'message' => 'No se pueden registrar incidencias de meses anteriores.'];
}

try {
RhPersonalAsistenciaIncidencia::create([
'id_asistencia' => $idAsistencia,
'fecha'         => $ahora->format('Y-m-d H:i:s'),
'incidencia'    => trim($incidencia),
'comentario'    => trim($comentario),
'documento'     => $documento ? trim($documento) : '',
'estado'        => 1,
]);

$asistencia->save();
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al guardar la incidencia: ' . $e->getMessage()];
}

$personal = RhPersonal::find($asistencia->id_personal);
$nombrePersonal = $personal ? $personal->nombre_completo : 'Desconocido';
$usuario = Session::get('nombre') ?? (Auth::user()?->nombre ?? 'Sistema');
$fechaFmt = $asistencia->fecha ? formatearFecha($asistencia->fecha) : 'S/I';

$mensaje = "📋 *Nueva incidencia registrada*\n";
$mensaje .= "━━━━━━━━━━━━━━━━━━━━━━\n";
$mensaje .= "👤 *Personal:* {$nombrePersonal}\n";
$mensaje .= "📅 *Fecha:* {$fechaFmt}\n";
$mensaje .= "⚠️ *Incidencia:* " . trim($incidencia) . "\n";
if (trim($comentario)) {
$mensaje .= "💬 *Comentario:* " . trim($comentario) . "\n";
}
$mensaje .= "📝 *Registró:* {$usuario}";

try {
TelegramService::notificar((int)$asistencia->id_estacion, (int)(Session::get('id_usuario') ?? 0), $mensaje);
} catch (\Throwable $e) {
error_log("Error Telegram incidencia: " . $e->getMessage());
}

return ['success' => true, 'message' => 'Incidencia registrada correctamente.'];
}

public static function editarPin(int $idPersonal, string $pin): array
{
$pinLimpio = trim($pin);

if (strlen($pinLimpio) < 5) {
return ['success' => false, 'message' => 'El PIN debe contener al menos 5 caracteres.', 'code' => 0];
}

$existe = RhPersonalAcceso::where('pin', $pinLimpio)
->where('id_personal', '!=', $idPersonal)
->first();

if ($existe) {
return ['success' => false, 'message' => 'Este PIN ya se encuentra registrado para otro personal.', 'code' => 2];
}

$acceso = RhPersonalAcceso::where('id_personal', $idPersonal)->first();
if (!$acceso) {
RhPersonalAcceso::create([
'id_personal' => $idPersonal,
'huella'      => '',
'pin'         => (int)$pinLimpio,
]);
} else {
$acceso->update(['pin' => (int)$pinLimpio]);
}

$personal = RhPersonal::find($idPersonal);
if ($personal) {
self::notify((int)$personal->id_estacion, 'editó PIN', [
'nombre' => $personal->nombre_completo ?? '',
'campo'  => 'PIN de acceso',
]);
}

return ['success' => true, 'message' => 'PIN actualizado correctamente.', 'code' => 1];
}
}
