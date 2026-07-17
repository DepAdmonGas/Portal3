<?php
namespace App\Services;

use App\Models\Operativo\ComparativoExcel;
use App\Models\Operativo\ComparativoExcelRegistro;
use App\Models\Operativo\ComparativoExcelSat;
use App\Models\Operativo\ComparativoExcelSatRegistro;
use App\Models\Operativo\ComparativoExcelSatComentario;
use App\Models\Operativo\ComparativoXmlDocumento;
use App\Models\Operativo\ComparativoXmlEntrada;
use App\Models\Usuario;
use App\Models\Estacion;
use App\Services\TelegramService;
use App\Core\Session;

class ComparativoXmlService
{
public static function getMapaCampos(): array
{
return [
'mensual' => 1,
'monederos_1' => 2,
'monederos_con_iva' => 3,
'monederos_sin_iva' => 4,
'clientes_1' => 5,
'octanos_87' => 6,
'octanos_91' => 7,
'diesel' => 8,
'aceites_lubricantes' => 9,
'ieps' => 10,
'aceites' => 12,
'renta_espacios' => 13,
'renta' => 14,
'ingresos' => 15,
'total_global' => 16,
'iva' => 17,
'total' => 18,
'monederos_2' => 19,
'clientes_2' => 20,
'iva_cv' => 21,
'total_cv' => 22,
'monederos_3' => 23,
'ingresos_2' => 24,
'clientes_3' => 25,
'iva_sat' => 26,
'total_sat' => 27,
'monederos_4' => 28,
'clientes_4' => 29,
'diferencia' => 30,
'total_2' => 31,
'diferencia_total_monederos' => 32,
'sin_iva' => 34,
];
}

public static function getMapaSecciones(): array
{
return [
'mensual' => 'Despachos',
'monederos_1' => 'Despachos',
'monederos_con_iva' => 'Despachos',
'monederos_sin_iva' => 'Despachos',
'clientes_1' => 'Despachos',
'octanos_87' => 'Ventas',
'octanos_91' => 'Ventas',
'diesel' => 'Ventas',
'aceites_lubricantes' => 'Ventas',
'ieps' => 'Ventas',
'aceites' => 'Ventas',
'renta_espacios' => 'Ventas',
'renta' => 'Ventas',
'ingresos' => 'Ventas',
'total_global' => 'Ventas',
'iva' => 'Ventas',
'total' => 'Ventas',
'monederos_2' => 'Ventas',
'clientes_2' => 'Ventas',
'iva_cv' => 'Facturación',
'total_cv' => 'Facturación',
'monederos_3' => 'Facturación',
'ingresos_2' => 'Facturación',
'clientes_3' => 'Facturación',
'iva_sat' => 'Facturación',
'total_sat' => 'Facturación',
'monederos_4' => 'Facturación',
'clientes_4' => 'Facturación',
'diferencia' => 'Diferencia',
'total_2' => 'Diferencia',
'diferencia_total_monederos' => 'Diferencia',
'sin_iva' => 'Diferencia',
];
}

public static function getMapaNombres(): array
{
return [
'mensual' => 'Mensual',
'monederos_1' => 'Monederos',
'monederos_con_iva' => 'Monederos c/IVA',
'monederos_sin_iva' => 'Monederos s/IVA',
'clientes_1' => 'Clientes',
'octanos_87' => 'Octanos 87',
'octanos_91' => 'Octanos 91',
'diesel' => 'Diésel',
'aceites_lubricantes' => 'Aceites y Lubricantes',
'ieps' => 'IEPS',
'aceites' => 'Aceites',
'renta_espacios' => 'Renta de Espacios',
'renta' => 'Renta',
'ingresos' => 'Ingresos',
'total_global' => 'Total Global',
'iva' => 'IVA',
'total' => 'Total',
'monederos_2' => 'Monederos',
'clientes_2' => 'Clientes',
'iva_cv' => 'IVA CV',
'total_cv' => 'Total CV',
'monederos_3' => 'Monederos',
'ingresos_2' => 'Ingresos',
'clientes_3' => 'Clientes',
'iva_sat' => 'IVA SAT',
'total_sat' => 'Total SAT',
'monederos_4' => 'Monederos',
'clientes_4' => 'Clientes',
'diferencia' => 'Diferencias',
'total_2' => 'Total',
'diferencia_total_monederos' => 'Diferencia total con monederos',
'sin_iva' => 'Soportes',
];
}

public static function getActiveFields(int $idEstacion): array
{
$config = [
'monederos_1' => 0,
'monederos_con_iva' => 1,
'monederos_sin_iva' => 1,
'clientes_1' => 1,
'diesel' => 0,
'aceites_lubricantes' => 1,
'autolavado' => 0,
'aceites' => 0,
'renta_espacios' => 1,
'renta' => 0,
'total_global' => 1,
'monederos_2' => 1,
'clientes_2' => 1,
'ingresos' => 0,
'iva' => 0,
'total' => 0,
'ingresos_2' => 0,
'clientes_3' => 1,
'clientes_4' => 1,
'total_2' => 0,
'diferencia_total_monederos' => 1,
'sin_iva' => 1,
];

if ($idEstacion == 2) {
// autolavado siempre oculto (misma columna comentada en el original)
} elseif (in_array($idEstacion, [1, 3, 4, 5, 14])) {
$config['diesel'] = 1;
} elseif ($idEstacion == 6) {
$config = [
'monederos_1' => 1,
'monederos_con_iva' => 0,
'monederos_sin_iva' => 0,
'clientes_1' => 0,
'diesel' => 1,
'aceites_lubricantes' => 0,
'autolavado' => 0,
'aceites' => 1,
'renta_espacios' => 0,
'renta' => 1,
'total_global' => 0,
'monederos_2' => 0,
'clientes_2' => 0,
'ingresos' => 1,
'iva' => 1,
'total' => 1,
'ingresos_2' => 1,
'clientes_3' => 0,
'clientes_4' => 0,
'total_2' => 1,
'diferencia_total_monederos' => 0,
'sin_iva' => 0,
];
} elseif ($idEstacion == 7) {
$config = [
'monederos_1' => 1,
'monederos_con_iva' => 0,
'monederos_sin_iva' => 0,
'clientes_1' => 0,
'diesel' => 0,
'aceites_lubricantes' => 0,
'autolavado' => 0,
'aceites' => 1,
'renta_espacios' => 0,
'renta' => 1,
'total_global' => 0,
'monederos_2' => 0,
'clientes_2' => 0,
'ingresos' => 1,
'iva' => 1,
'total' => 1,
'ingresos_2' => 1,
'clientes_3' => 0,
'clientes_4' => 0,
'total_2' => 1,
'diferencia_total_monederos' => 0,
'sin_iva' => 0,
];
}

$alwaysActive = ['mensual', 'octanos_87', 'octanos_91', 'ieps', 'iva_cv', 'total_cv', 'monederos_3', 'iva_sat', 'total_sat', 'monederos_4', 'diferencia'];

$allFields = [
'mensual', 'monederos_1', 'monederos_con_iva', 'monederos_sin_iva', 'clientes_1',
'octanos_87', 'octanos_91', 'diesel', 'aceites_lubricantes', 'ieps',
'autolavado', 'aceites', 'renta_espacios', 'renta', 'ingresos',
'total_global', 'iva', 'total', 'monederos_2', 'clientes_2',
'iva_cv', 'total_cv', 'monederos_3', 'ingresos_2', 'clientes_3',
'iva_sat', 'total_sat', 'monederos_4', 'clientes_4',
'diferencia', 'total_2', 'diferencia_total_monederos', 'iva_2', 'sin_iva',
];

$active = [];
foreach ($allFields as $campo) {
if (in_array($campo, $alwaysActive)) {
$active[] = $campo;
} elseif (isset($config[$campo]) && $config[$campo] == 1) {
$active[] = $campo;
}
}

return $active;
}

public static function getActiveHeaders(int $idEstacion): array
{
$campos = self::getActiveFields($idEstacion);
$nombres = self::getMapaNombres();
$headers = [];
foreach ($campos as $campo) {
$headers[$campo] = $nombres[$campo] ?? $campo;
}
return $headers;
}

public static function getStationConfig(int $idEstacion): array
{
$config = [
'colspanDespachos' => 4,
'colspanVentas' => 9,
'colspanFacturacion' => 8,
'colspanDiferencia' => 3,
];
$tb = [
'monederos' => 0,
'monederos_c_iva' => 1,
'monederos_s_iva' => 1,
'clientes_despachos' => 1,
'diesel' => 0,
'aceites_lubricantes' => 1,
'autolavado' => 0,
'aceites' => 0,
'renta_espacios' => 1,
'renta' => 0,
'total_global' => 1,
'monederos_ventas' => 1,
'clientes_ventas' => 1,
'ingresos' => 0,
'iva' => 0,
'total' => 0,
'ingresos_2' => 0,
'clientes_factuacion' => 1,
'clientes_factuacion_2' => 1,
'total_2' => 0,
'diferencias_monederos' => 1,
'sin_iva_2' => 1,
];

if ($idEstacion == 1) {
$config['colspanVentas'] = 9;
$tb['diesel'] = 1;
} elseif ($idEstacion == 2) {
$config['colspanVentas'] = 8;
} elseif (in_array($idEstacion, [3, 4, 5, 14])) {
$tb['diesel'] = 1;
} elseif ($idEstacion == 6) {
$config['colspanDespachos'] = 2;
$config['colspanFacturacion'] = 7;
$config['colspanDiferencia'] = 2;
$tb = array_merge($tb, [
'monederos' => 1,
'monederos_c_iva' => 0,
'monederos_s_iva' => 0,
'clientes_despachos' => 0,
'diesel' => 1,
'aceites_lubricantes' => 0,
'aceites' => 1,
'renta_espacios' => 0,
'renta' => 1,
'total_global' => 0,
'monederos_ventas' => 0,
'clientes_ventas' => 0,
'ingresos' => 1,
'iva' => 1,
'total' => 1,
'ingresos_2' => 1,
'clientes_factuacion' => 0,
'clientes_factuacion_2' => 0,
'total_2' => 1,
'diferencias_monederos' => 0,
'sin_iva_2' => 0,
]);
} elseif ($idEstacion == 7) {
$config['colspanVentas'] = 8;
$config['colspanDespachos'] = 2;
$config['colspanFacturacion'] = 7;
$config['colspanDiferencia'] = 2;
$tb = array_merge($tb, [
'monederos' => 1,
'monederos_c_iva' => 0,
'monederos_s_iva' => 0,
'clientes_despachos' => 0,
'aceites_lubricantes' => 0,
'aceites' => 1,
'renta_espacios' => 0,
'renta' => 1,
'total_global' => 0,
'monederos_ventas' => 0,
'clientes_ventas' => 0,
'ingresos' => 1,
'iva' => 1,
'total' => 1,
'ingresos_2' => 1,
'clientes_factuacion' => 0,
'clientes_factuacion_2' => 0,
'total_2' => 1,
'diferencias_monederos' => 0,
'sin_iva_2' => 0,
]);
}

return ['config' => $config, 'tb' => $tb];
}

public static function getSatCategorias(int $idEstacion): array
{
$map = [
1 => ['CC', 'CD', 'EDI', 'FA', 'FGVM', 'PG', 'VM', 'FEVM'],
2 => ['AL', 'CC', 'CD', 'EDI', 'FA', 'FEVM', 'FGVM', 'PG', 'RL', 'T', 'W', 'VM'],
3 => ['CC', 'CD', 'EDI', 'FA', 'FGVM', 'PG', 'RL', 'VM', 'FEVM'],
4 => ['CC', 'CD', 'EDI', 'FA', 'FGVM', 'PG', 'VM'],
5 => ['CC', 'CD', 'EDI', 'FA', 'FEVM', 'FGVM', 'PG', 'VM'],
6 => ['CA', 'D', 'D-CRE', 'EDI', 'ERP', 'FA', 'FGD', 'PG', 'RL'],
7 => ['EDI', 'FA', 'FGVM', 'PG', 'VM', 'PX', 'FEVM'],
14 => ['CC', 'CD', 'EDI', 'FA', 'FGVM', 'PG', 'RL', 'VM', 'FEVM'],
];
return $map[$idEstacion] ?? [];
}

public static function validarMeses(int $idEstacion, int $idYear): void
{
for ($mes = 1; $mes <= 12; $mes++) {
$exists = ComparativoExcel::where('id_estacion', $idEstacion)
->where('year', $idYear)->where('mes', $mes)->exists();
if (!$exists) {
$record = new ComparativoExcel();
$record->id_estacion = $idEstacion;
$record->year = $idYear;
$record->mes = $mes;
$record->save();
}
}
}

public static function validarSatMeses(int $idEstacion, int $idYear): void
{
$categorias = self::getSatCategorias($idEstacion);
for ($mes = 1; $mes <= 12; $mes++) {
foreach ($categorias as $cat) {
$exists = ComparativoExcelSat::where('id_estacion', $idEstacion)
->where('mes', $mes)->where('year', $idYear)
->where('categoria', $cat)->exists();
if (!$exists) {
$record = new ComparativoExcelSat();
$record->id_estacion = $idEstacion;
$record->mes = $mes;
$record->year = $idYear;
$record->categoria = $cat;
$record->sat_monto = 0;
$record->despacho_monto = 0;
$record->save();
}
}
}
}

public static function getDataRows(int $idEstacion, int $idYear): array
{
$campos = self::getActiveFields($idEstacion);
$mapaCampos = self::getMapaCampos();
$mapaSecciones = self::getMapaSecciones();
$mapaNombres = self::getMapaNombres();

$rows = [];
for ($mes = 1; $mes <= 12; $mes++) {
$record = ComparativoExcel::where('id_estacion', $idEstacion)
->where('year', $idYear)->where('mes', $mes)->first();
if (!$record) continue;

$cells = [];
foreach ($campos as $campo) {
$valor = $record->$campo ?? 0;
$cells[] = [
'campo' => $campo,
'valor' => (float)$valor,
'valor_formateado' => number_format((float)$valor, 2, '.', ','),
'idTipo' => $mapaCampos[$campo] ?? 0,
'seccion' => $mapaSecciones[$campo] ?? 'Sin categoría',
'nombre' => $mapaNombres[$campo] ?? $campo,
];
}

$rows[] = [
'mes' => $mes,
'nombre_mes' => self::monthName($mes),
'id' => $record->id,
'cells' => $cells,
];
}

return $rows;
}

public static function getTotales(int $idEstacion, int $idYear): array
{
$record = ComparativoExcel::selectRaw("
SUM(mensual) AS mensual,
SUM(monederos_1) AS monederos_1,
SUM(monederos_con_iva) AS monederos_con_iva,
SUM(monederos_sin_iva) AS monederos_sin_iva,
SUM(clientes_1) AS clientes_1,
SUM(octanos_87) AS octanos_87,
SUM(octanos_91) AS octanos_91,
SUM(diesel) AS diesel,
SUM(aceites_lubricantes) AS aceites_lubricantes,
SUM(ieps) AS ieps,
SUM(aceites) AS aceites,
SUM(renta_espacios) AS renta_espacios,
SUM(renta) AS renta,
SUM(ingresos) AS ingresos,
SUM(total_global) AS total_global,
SUM(iva) AS iva,
SUM(total) AS total,
SUM(monederos_2) AS monederos_2,
SUM(clientes_2) AS clientes_2,
SUM(iva_cv) AS iva_cv,
SUM(total_cv) AS total_cv,
SUM(monederos_3) AS monederos_3,
SUM(ingresos_2) AS ingresos_2,
SUM(clientes_3) AS clientes_3,
SUM(iva_sat) AS iva_sat,
SUM(total_sat) AS total_sat,
SUM(monederos_4) AS monederos_4,
SUM(clientes_4) AS clientes_4,
SUM(diferencia) AS diferencia,
SUM(total_2) AS total_2,
SUM(diferencia_total_monederos) AS diferencia_total_monederos,
SUM(sin_iva) AS sin_iva
")
->where('id_estacion', $idEstacion)
->where('year', $idYear)
->first();

if (!$record) return [];

$result = [];
foreach ($record->getAttributes() as $key => $value) {
$result[$key] = (float)($value ?? 0);
}
return $result;
}

public static function updateCell(int $idCampo, string $descripcion, int $idTipo, int $idEstacion, int $idYear, int $idMes, string $idSeccion, string $idDescripcion, int $idUsuario): bool
{
$mapa = self::getMapaCampos();
$concepto = null;
foreach ($mapa as $campo => $tipo) {
if ($tipo == $idTipo) {
$concepto = $campo;
break;
}
}
if (!$concepto) return false;

$record = ComparativoExcel::find($idCampo);
if (!$record) return false;

$record->$concepto = $descripcion;
$saved = $record->save();

if ($saved) {
$registro = new ComparativoExcelRegistro();
$registro->id_responsable = $idUsuario;
$registro->id_estacion = $idEstacion;
$registro->year = $idYear;
$registro->mes = $idMes;
$registro->seccion = $idSeccion;
$registro->descripcion = $idDescripcion;
$registro->monto = $descripcion;
$registro->fecha_hora = date('Y-m-d H:i:s');
$registro->save();
}

return $saved;
}

public static function updateSatCell(int $idCampo, string $descripcion, string $categoria, int $idTipo, int $idYear, int $idMes, int $idEstacion, int $idUsuario): bool
{
$concepto = ($idTipo == 1) ? 'sat_monto' : 'despacho_monto';
$nombreTipo = ($idTipo == 1) ? 'SAT' : 'Despacho';

$record = ComparativoExcelSat::find($idCampo);
if (!$record) return false;

$record->$concepto = $descripcion;
$saved = $record->save();

if ($saved) {
$registro = new ComparativoExcelSatRegistro();
$registro->id_responsable = $idUsuario;
$registro->id_estacion = $idEstacion;
$registro->year = $idYear;
$registro->mes = $idMes;
$registro->categoria = $categoria;
$registro->descripcion = $nombreTipo;
$registro->monto = $descripcion;
$registro->fecha_hora = date('Y-m-d H:i:s');
$registro->save();
}

return $saved;
}

public static function getSatData(int $idEstacion, int $idYear): array
{
$months = [];
for ($mes = 1; $mes <= 12; $mes++) {
$items = ComparativoExcelSat::where('id_estacion', $idEstacion)
->where('year', $idYear)->where('mes', $mes)->get();

$totalSat = 0;
$totalDespacho = 0;
$cards = [];

foreach ($items as $item) {
$sat = (float)$item->sat_monto;
$desp = (float)$item->despacho_monto;
$totalSat += $sat;
$totalDespacho += $desp;
$cards[] = [
'id' => $item->id,
'categoria' => $item->categoria,
'sat_monto' => $sat,
'sat_formateado' => number_format($sat, 2, '.', ','),
'despacho_monto' => $desp,
'despacho_formateado' => number_format($desp, 2, '.', ','),
'diferencia' => $sat - $desp,
];
}

$commentCount = ComparativoExcelSatComentario::where('id_estacion', $idEstacion)
->where('year', $idYear)->where('mes', $mes)->count();

$months[] = [
'mes' => $mes,
'nombre' => self::monthName($mes),
'items' => $cards,
'total_sat' => $totalSat,
'total_despacho' => $totalDespacho,
'total_diferencia' => $totalSat - $totalDespacho,
'comment_count' => $commentCount,
];
}

$totalAnualSat = array_sum(array_column($months, 'total_sat'));
$totalAnualDespacho = array_sum(array_column($months, 'total_despacho'));

return [
'months' => $months,
'total_anual_sat' => $totalAnualSat,
'total_anual_despacho' => $totalAnualDespacho,
'total_anual_diferencia' => $totalAnualSat - $totalAnualDespacho,
];
}

public static function addComment(int $idEstacion, int $idYear, int $idMes, int $idUsuario, string $comentario): bool
{
$record = new ComparativoExcelSatComentario();
$record->id_estacion = $idEstacion;
$record->year = $idYear;
$record->mes = $idMes;
$record->id_usuario = $idUsuario;
$record->comentario = $comentario;
$record->fecha_hora = date('Y-m-d H:i:s');
return $record->save();
}

public static function getComments(int $idEstacion, int $idYear, int $idMes): array
{
$records = ComparativoExcelSatComentario::where('id_estacion', $idEstacion)
->where('year', $idYear)->where('mes', $idMes)
->orderBy('id', 'desc')->get();

$comments = [];
foreach ($records as $r) {
$user = Usuario::find($r->id_usuario);
$comments[] = [
'id' => $r->id,
'id_usuario' => $r->id_usuario,
'usuario' => $user ? $user->nombre : 'Desconocido',
'comentario' => $r->comentario,
'fecha' => formatearFecha($r->fecha_hora),
'hora' => date('g:i a', strtotime($r->fecha_hora)),
];
}
return $comments;
}

public static function getDocuments(int $idEstacion, int $idYear, int $idMes): array
{
$records = ComparativoXmlDocumento::where('id_estacion', $idEstacion)
->where('year', $idYear)->where('mes', $idMes)->get();

$docs = [];
foreach ($records as $r) {
$docs[] = [
'id' => $r->id,
'anexo' => $r->anexo,
'archivo' => $r->archivo,
'fecha' => $r->fecha,
'fecha_formato' => formatearFecha($r->fecha),
];
}
return $docs;
}

public static function addDocument(int $idEstacion, int $idYear, int $idMes, string $anexo, string $archivo): int
{
$record = new ComparativoXmlDocumento();
$record->id_estacion = $idEstacion;
$record->anexo = $anexo;
$record->archivo = $archivo;
$record->mes = $idMes;
$record->year = $idYear;
$record->fecha = date('Y-m-d H:i:s');
$record->save();
return $record->id;
}

public static function deleteDocument(int $id): ?array
{
$record = ComparativoXmlDocumento::find($id);
if (!$record) return null;

$data = [
'id_estacion' => $record->id_estacion,
'anexo' => $record->anexo,
'archivo' => $record->archivo,
'mes' => $record->mes,
'year' => $record->year,
];

$record->delete();
return $data;
}

public static function saveObservations(int $idYear, int $idEstacion, string $content): bool
{
$dir = __DIR__ . '/../../public/uploads/archivos/comparativo-xml';
if (!is_dir($dir)) mkdir($dir, 0777, true);
$filename = "$dir/comparativo-ES{$idEstacion}-{$idYear}.html";
return file_put_contents($filename, $content) !== false;
}

public static function loadObservations(int $idYear, int $idEstacion): string
{
$filename = __DIR__ . "/../../public/uploads/archivos/comparativo-xml/comparativo-ES{$idEstacion}-{$idYear}.html";
if (file_exists($filename)) {
return file_get_contents($filename);
}
return '';
}

public static function logAccess(int $idEstacion, int $idYear, int $idUsuario): void
{
$record = new ComparativoXmlEntrada();
$record->id_usuario = $idUsuario;
$record->id_estacion = $idEstacion;
$record->year = $idYear;
$record->fecha_hora = date('Y-m-d H:i:s');
$record->save();
}

public static function getAccessLog(int $idEstacion, int $idYear): array
{
$records = ComparativoXmlEntrada::where('id_estacion', $idEstacion)
->where('year', $idYear)->orderBy('id', 'desc')->get();

$log = [];
foreach ($records as $r) {
$user = Usuario::with('puesto')->find($r->id_usuario);
$explode = explode(' ', $r->fecha_hora);
$hora = isset($explode[1]) ? date('g:i a', strtotime($explode[1])) : '';
$log[] = [
'usuario' => $user ? $user->nombre : 'Desconocido',
'puesto' => ($user && $user->puesto) ? $user->puesto->tipo_puesto : '',
'fecha_hora' => formatearFecha($r->fecha_hora) . ($hora ? ', ' . $hora : ''),
];
}
return $log;
}

public static function getEditLog(int $idEstacion, int $idYear): array
{
$records = ComparativoExcelRegistro::where('id_estacion', $idEstacion)
->where('year', $idYear)->orderBy('id', 'desc')->get();

$log = [];
foreach ($records as $r) {
$user = Usuario::find($r->id_responsable);
$explode = explode(' ', $r->fecha_hora);
$hora = isset($explode[1]) ? date('g:i a', strtotime($explode[1])) : '';
$log[] = [
'responsable' => $user ? $user->nombre : 'Desconocido',
'fecha_hora' => formatearFecha($r->fecha_hora) . ($hora ? ', ' . $hora : ''),
'mes' => $r->mes,
'seccion' => $r->seccion,
'descripcion' => $r->descripcion,
'monto' => (float)$r->monto,
];
}
return $log;
}

public static function getSatEditLog(int $idEstacion, int $idYear): array
{
$records = ComparativoExcelSatRegistro::where('id_estacion', $idEstacion)
->where('year', $idYear)->orderBy('id', 'desc')->get();

$log = [];
foreach ($records as $r) {
$user = Usuario::find($r->id_responsable);
$explode = explode(' ', $r->fecha_hora);
$hora = isset($explode[1]) ? date('g:i a', strtotime($explode[1])) : '';
$log[] = [
'responsable' => $user ? $user->nombre : 'Desconocido',
'fecha_hora' => formatearFecha($r->fecha_hora) . ($hora ? ', ' . $hora : ''),
'mes' => $r->mes,
'categoria' => $r->categoria,
'descripcion' => $r->descripcion,
'monto' => (float)$r->monto,
];
}
return $log;
}

public static function notificarTelegram(string $accion, array $params): void
{
$idEstacion = $params['id_estacion'] ?? 0;
$idUsuario = $params['id_usuario'] ?? 0;

$user = Usuario::find($idUsuario);
$estacion = Estacion::find($idEstacion);
$nombreUsuario = $user ? $user->nombre : 'Desconocido';
$nombreES = $estacion ? $estacion->nombre : "ES{$idEstacion}";

$detalle = ''; 
switch ($accion) {
case 'agregar_documento':
$anexo = $params['anexo'] ?? '';
$year  = $params['year'] ?? '';
$mes   = $params['mes'] ?? 0;

$detalle = '✅ Se ha agregado un nuevo anexo en el apartado de <b>Comparativo XML</b>, correspondiente al periodo de <b>' . self::monthName($mes) . ' ' . $year . '</b>:' . PHP_EOL . PHP_EOL
. '📄 <b>Anexo:</b> ' . $anexo . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;
break;

case 'eliminar_documento':
$anexo = $params['anexo'] ?? '';
$year  = $params['year'] ?? '';
$mes   = $params['mes'] ?? 0;

$detalle = '🗑 Se ha eliminado un anexo del apartado de <b>Comparativo XML</b>, correspondiente al periodo de <b>' . self::monthName($mes) . ' ' . $year . '</b>:' . PHP_EOL . PHP_EOL
. '📄 <b>Anexo:</b> ' . $anexo . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;
break;

case 'agregar_observacion':
$year = $params['year'] ?? '';

$detalle = '📝 Se ha agregado una nueva observación en el apartado de <b>Comparativo XML</b>, correspondiente al año <b>' . $year . '</b>:' . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;
break;

case 'agregar_comentario':
$year = $params['year'] ?? '';
$mes  = $params['mes'] ?? 0;

$detalle = '💭 Se ha agregado un nuevo comentario en el apartado de <b>Comparativo XML</b>, correspondiente al periodo de <b>' . self::monthName($mes) . ' ' . $year . '</b>:' . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;
break;
}

if (!empty($detalle)) {
TelegramService::notificar($idEstacion, $idUsuario, $detalle);
}
}

public static function monthName(int $mes): string
{
$nombres = [
1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];
return $nombres[$mes] ?? '';
}

private static function formatDate(string $date): string
{
if (!$date) return '';
$parts = explode('-', $date);
if (count($parts) == 3) {
return $parts[2] . '/' . $parts[1] . '/' . $parts[0];
}
return $date;
}

public static function getPermisos(): array
{
$usuario = Session::get('usuario');
$idPuesto = (int)($usuario['id_puesto'] ?? 0);
$nombrePuesto = $usuario['nompuesto'] ?? '';
$esDirOp = in_array($idPuesto, [13, 14])
|| in_array($nombrePuesto, ['Dirección de operaciones', 'Dirección de operaciones servicio social'])
|| !empty($usuario['multiestacion']);
return [
'id_usuario' => (int)($usuario['id'] ?? 0),
'id_puesto' => $idPuesto,
'nombre_puesto' => $nombrePuesto,
'multiestacion' => !empty($usuario['multiestacion']),
'canEdit' => self::canEdit($idPuesto),
'esDireccionOperaciones' => $esDirOp,
];
}

public static function canEdit(int $idPuesto): bool
{
return !in_array($idPuesto, [4, 12]);
}
}
