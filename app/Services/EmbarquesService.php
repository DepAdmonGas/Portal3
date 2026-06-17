<?php

namespace App\Services;

use App\Models\Operativo\CorteMes;
use App\Models\Operativo\Embarque;
use App\Models\Operativo\EmbarquesComentario;
use App\Core\Auth;
use App\Core\Session;

class EmbarquesService
{
public static function getMesId(int $idEstacion, int $idYear, int $idMes): ?int
{
$corteMes = CorteMes::whereHas('year', function ($q) use ($idEstacion, $idYear) {
$q->where('id_estacion', $idEstacion)->where('year', $idYear);
})->where('mes', $idMes)->first();

return $corteMes?->id;
}

public static function getPermisos(): array
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');

$multiEstacion = $sessionUsuario['multiestacion'] ?? false;
$esDireccionOperaciones = false;
$esContabilidad = false;
$esServicioSocial = false;
$esComercializadora = false;
$esEncargado = false;
$esAsistente = false;

if ($usuario && $usuario->puesto) {
$tipo = $usuario->puesto->tipo_puesto ?? '';
$esDireccionOperaciones = $tipo === 'Dirección de operaciones';
$esContabilidad = $tipo === 'Contabilidad';
$esServicioSocial = $tipo === 'Dirección de operaciones servicio social';
$esComercializadora = $tipo === 'Comercializadora';
$esEncargado = $tipo === 'Encargado';
$esAsistente = $tipo === 'Asistente Administrativo';
}

return [
'multiestacion' => $multiEstacion,
'es_direccion_operaciones' => $esDireccionOperaciones,
'es_contabilidad' => $esContabilidad,
'es_servicio_social' => $esServicioSocial,
'es_comercializadora' => $esComercializadora,
'es_encargado' => $esEncargado,
'es_asistente' => $esAsistente,
'id_usuario' => $sessionUsuario['id'] ?? 0,
'id_puesto' => $usuario->id_puesto ?? 0,
'tipo_puesto' => $usuario->puesto->tipo_puesto ?? '',
'puede_leer' => true,
'puede_agregar' => $esEncargado || $esAsistente || $esDireccionOperaciones,
'puede_editar' => $esEncargado || $esAsistente || $esDireccionOperaciones,
'puede_eliminar' => ($esEncargado || $esAsistente || $esDireccionOperaciones) && !$esServicioSocial,
'puede_upload' => $esEncargado || $esAsistente || $esDireccionOperaciones,
'puede_ver_comentarios' => true,
'puede_agregar_comentarios' => $esEncargado || $esAsistente || $esDireccionOperaciones,
'puede_analisis_compras' => $esDireccionOperaciones || $esContabilidad || $esComercializadora || $esServicioSocial,
];
}

public static function getDatos(?int $idMesDb): array
{
if (!$idMesDb) {
return [];
}

$rows = Embarque::where('id_mes', $idMesDb)
->orderBy('fecha', 'desc')
->orderBy('id', 'desc')
->get();

$result = [];

foreach ($rows as $row) {
$numCom = EmbarquesComentario::where('id_embarques', $row->id)->count();

$result[] = [
'id' => $row->id,
'id_mes' => $row->id_mes,
'fecha' => formatearFecha($row->fecha ?? ''),
'fecha_raw' => !empty($row->fecha) ? $row->fecha->format('Y-m-d') : '',
'embarque' => $row->embarque ?? '',
'producto' => $row->producto ?? '',
'documento' => $row->documento ?? '',
'documentocv' => $row->documentocv ?? '',
'importef' => (float)($row->importef ?? 0),
'precio_litro' => (float)($row->precio_litro ?? 0),
'merma' => (float)($row->merma ?? 0),
'tad' => $row->tad ?? '',
'nom_transporte' => $row->nom_transporte ?? '',
'chofer' => $row->chofer ?? '',
'unidad' => $row->unidad ?? '',
'pdf' => $row->pdf ?? '',
'xml' => $row->xml ?? '',
'comprobante_p' => $row->comprobante_p ?? '',
'nc_pdf' => $row->nc_pdf ?? '',
'nc_xml' => $row->nc_xml ?? '',
'comPDF' => $row->comPDF ?? '',
'comXML' => $row->comXML ?? '',
'semaforo' => self::getSemaforoDocs($row),
'num_comentarios' => $numCom,
];
}

return $result;
}

public static function getSemaforoDocs(Embarque $row): int
{
$transportista = strtoupper(trim($row->nom_transporte ?? ''));
$tipoEmbarque = strtoupper(trim($row->embarque ?? ''));

if ($tipoEmbarque === 'PEMEX' || $tipoEmbarque === 'DELIVERY') {
return 2;
}

if (strpos($transportista, 'PETRO ASFALTOS') !== false || strpos($transportista, 'SANTA FE') !== false) {
$docs = ['pdf', 'xml', 'comprobante_p', 'nc_pdf', 'comPDF', 'comXML'];
} elseif ($transportista === 'SIPCI') {
$docs = ['pdf', 'xml', 'comprobante_p'];
} else {
$docs = ['pdf', 'xml', 'comprobante_p', 'nc_pdf', 'nc_xml', 'comPDF', 'comXML'];
}

$tiene = 0;
$total = count($docs);
foreach ($docs as $d) {
if (!empty($row->$d)) $tiene++;
}

if ($tiene === 0) return 0;
if ($tiene >= $total) return 2;
return 1;
}

public static function store(array $data, array $files): array
{
$idMesDb = (int) ($data['id_mes'] ?? 0);
if (!$idMesDb) {
return ['success' => false, 'message' => 'ID de mes no válido'];
}

$usuario = Auth::user();
if (!$usuario) {
return ['success' => false, 'message' => 'Sesión no válida'];
}

$uploadDir = self::getUploadDir();
if (!is_dir($uploadDir)) {
mkdir($uploadDir, 0755, true);
}

$record = [
'id_mes' => $idMesDb,
'fecha' => $data['fecha'] ?? null,
'embarque' => $data['embarque'] ?? '',
'producto' => $data['producto'] ?? '',
'documentocv' => $data['documentocv'] ?? '',
'importef' => (float) ($data['importef'] ?? 0),
'precio_litro' => (float) ($data['precio_litro'] ?? 0),
'merma' => (float) ($data['merma'] ?? 0),
'tad' => $data['tad'] ?? '',
'nom_transporte' => $data['nom_transporte'] ?? '',
'chofer' => $data['chofer'] ?? '',
'unidad' => $data['unidad'] ?? '',
];

$fileFields = ['documento', 'pdf', 'xml', 'comprobante_p', 'nc_pdf', 'nc_xml', 'comPDF', 'comXML'];
foreach ($fileFields as $field) {
if (!empty($files[$field]) && $files[$field]['error'] === UPLOAD_ERR_OK) {
$ext = pathinfo($files[$field]['name'], PATHINFO_EXTENSION);
$filename = $field . '_' . time() . '_' . uniqid() . '.' . $ext;
if (move_uploaded_file($files[$field]['tmp_name'], $uploadDir . '/' . $filename)) {
$record[$field] = $filename;
}
}
}

self::persistCatalogos($data);

try {
$embarque = Embarque::create($record);
return ['success' => true, 'message' => 'Embarque agregado exitosamente.', 'id' => $embarque->id];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()];
}
}

public static function update(int $id, array $data, array $files): array
{
$embarque = Embarque::find($id);
if (!$embarque) {
return ['success' => false, 'message' => 'Embarque no encontrado'];
}

$embarque->fecha = $data['fecha'] ?? $embarque->fecha;
$embarque->embarque = $data['embarque'] ?? $embarque->embarque;
$embarque->producto = $data['producto'] ?? $embarque->producto;
$embarque->documentocv = $data['documentocv'] ?? $embarque->documentocv;
$embarque->importef = (float) ($data['importef'] ?? $embarque->importef);
$embarque->precio_litro = (float) ($data['precio_litro'] ?? $embarque->precio_litro);
$embarque->merma = (float) ($data['merma'] ?? $embarque->merma);
$embarque->tad = $data['tad'] ?? $embarque->tad;
$embarque->nom_transporte = $data['nom_transporte'] ?? $embarque->nom_transporte;
$embarque->chofer = $data['chofer'] ?? $embarque->chofer;
$embarque->unidad = $data['unidad'] ?? $embarque->unidad;

$uploadDir = self::getUploadDir();
if (!is_dir($uploadDir)) {
mkdir($uploadDir, 0755, true);
}

$fileFields = ['documento', 'pdf', 'xml', 'comprobante_p', 'nc_pdf', 'nc_xml', 'comPDF', 'comXML'];
foreach ($fileFields as $field) {
if (!empty($files[$field]) && $files[$field]['error'] === UPLOAD_ERR_OK) {
if ($embarque->$field) {
$oldFile = $uploadDir . '/' . $embarque->$field;
if (file_exists($oldFile)) {
unlink($oldFile);
}
}
$ext = pathinfo($files[$field]['name'], PATHINFO_EXTENSION);
$filename = $field . '_' . time() . '_' . uniqid() . '.' . $ext;
if (move_uploaded_file($files[$field]['tmp_name'], $uploadDir . '/' . $filename)) {
$embarque->$field = $filename;
}
}
}

self::persistCatalogos($data);

try {
$embarque->save();
return ['success' => true, 'message' => 'Embarque actualizado exitosamente.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()];
}
}

public static function destroy(int $id): array
{
$embarque = Embarque::find($id);
if (!$embarque) {
return ['success' => false, 'message' => 'Embarque no encontrado'];
}

$uploadDir = self::getUploadDir();
$fileFields = ['documento', 'pdf', 'xml', 'comprobante_p', 'nc_pdf', 'nc_xml', 'comPDF', 'comXML'];
foreach ($fileFields as $field) {
if ($embarque->$field) {
$filePath = $uploadDir . '/' . $embarque->$field;
if (file_exists($filePath)) {
unlink($filePath);
}
}
}

try {
$embarque->delete();
return ['success' => true, 'message' => 'Embarque eliminado exitosamente.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()];
}
}

public static function getComentarios(int $idEmbarque): array
{
return EmbarquesComentario::where('id_embarques', $idEmbarque)
->orderBy('fecha_hora', 'asc')
->with('usuario:id,nombre')
->get()
->toArray();
}

public static function storeComentario(int $idEmbarque, string $comentario): array
{
$usuario = Auth::user();
if (!$usuario) {
return ['success' => false, 'message' => 'Sesión no válida'];
}

if (!trim($comentario)) {
return ['success' => false, 'message' => 'El comentario no puede estar vacío'];
}

try {
EmbarquesComentario::create([
'id_embarques' => $idEmbarque,
'fecha_hora' => \Carbon\Carbon::now(),
'id_usuario' => $usuario->id,
'comentario' => trim($comentario),
]);
return ['success' => true, 'message' => 'Comentario agregado.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al guardar comentario: ' . $e->getMessage()];
}
}

public static function getCatalogos(): array
{
try {
$capsule = new \Illuminate\Database\Capsule\Manager;
$choferes = $capsule::connection()->select("SELECT nombre_chofer FROM tb_pivoteo_chofer WHERE estado = 0 ORDER BY id ASC");
$unidades = $capsule::connection()->select("SELECT no_unidad FROM tb_unidades_transporte WHERE estado = 0 ORDER BY id ASC");
$transportes = $capsule::connection()->select("SELECT nombre_transporte FROM tb_lista_transportes WHERE estado = 0 ORDER BY nombre_transporte ASC");

return [
'choferes' => array_map(fn($r) => $r->nombre_chofer, $choferes),
'unidades' => array_map(fn($r) => $r->no_unidad, $unidades),
'transportes' => array_map(fn($r) => $r->nombre_transporte, $transportes),
];
} catch (\Exception $e) {
return ['choferes' => [], 'unidades' => [], 'transportes' => []];
}
}

public static function persistCatalogos(array $data): void
{
$capsule = new \Illuminate\Database\Capsule\Manager;

if (!empty($data['chofer'])) {
$exists = $capsule::connection()->selectOne("SELECT id FROM tb_pivoteo_chofer WHERE nombre_chofer = ?", [trim($data['chofer'])]);
if (!$exists) {
$capsule::connection()->insert("INSERT INTO tb_pivoteo_chofer (nombre_chofer, estado) VALUES (?, 0)", [trim($data['chofer'])]);
}
}

if (!empty($data['unidad'])) {
$exists = $capsule::connection()->selectOne("SELECT id FROM tb_unidades_transporte WHERE no_unidad = ?", [trim($data['unidad'])]);
if (!$exists) {
$capsule::connection()->insert("INSERT INTO tb_unidades_transporte (no_unidad, estado) VALUES (?, 0)", [trim($data['unidad'])]);
}
}

if (!empty($data['nom_transporte'])) {
$exists = $capsule::connection()->selectOne("SELECT id FROM tb_lista_transportes WHERE nombre_transporte = ?", [trim($data['nom_transporte'])]);
if (!$exists) {
$capsule::connection()->insert("INSERT INTO tb_lista_transportes (nombre_transporte, estado) VALUES (?, 0)", [trim($data['nom_transporte'])]);
}
}
}

public static function getUploadDir(): string
{
$dir = __DIR__ . '/../../public/uploads/archivos/embarques';
if (!is_dir($dir)) {
mkdir($dir, 0755, true);
}
return realpath($dir);
}
}
