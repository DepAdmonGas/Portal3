<?php

namespace App\Services;

use App\Models\Operativo\CorteMes;
use App\Models\Operativo\CorteDia;
use App\Models\Operativo\TarjetasCB;
use App\Models\Operativo\ClientesControlgas;
use App\Models\Operativo\Prosegur;
use App\Models\Operativo\MonederoDocumento;
use App\Models\Operativo\MonederoEdi;
use App\Models\Operativo\MonederoListaDocumento;
use App\Core\Auth;
use App\Core\Session;



class ResumenMonederoService
{
public static function getMesId(int $idEstacion, int $idYear, int $idMes): ?int
{
return CorteMes::whereHas('year', function ($q) use ($idEstacion, $idYear) {
$q->where('id_estacion', $idEstacion)->where('year', $idYear);
})->where('mes', $idMes)->value('id');
}

public static function getPermisos(): array
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');

$multiEstacion = $sessionUsuario['multiestacion'] ?? false;
$esDireccionOperaciones = false;
$esContabilidad = false;
$esServicioSocial = false;

if ($usuario && $usuario->puesto) {
$tipo = $usuario->puesto->tipo_puesto ?? '';
$esDireccionOperaciones = $tipo === 'Dirección de operaciones';
$esContabilidad = $tipo === 'Contabilidad';
$esServicioSocial = $tipo === 'Dirección de operaciones servicio social';
}

$esCorporativo = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
$puedeLeer = $esCorporativo || ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
$puedeCrear = ModuloDptoOperativoService::validaPermiso('corporativo', 'crear');
$puedeEditar = ModuloDptoOperativoService::validaPermiso('corporativo', 'editar');
$puedeDescargar = ModuloDptoOperativoService::validaPermiso('corporativo', 'descargar');

$puedeEliminar = $esDireccionOperaciones || ModuloDptoOperativoService::validaPermiso('corporativo', 'eliminar');

$verProsegur = in_array($sessionUsuario['id'] ?? 0, [19, 318]) || $esDireccionOperaciones;
$puedeEliminarDoc = !$esContabilidad && !$esServicioSocial;

return [
'multiestacion' => $multiEstacion,
'es_direccion_operaciones' => $esDireccionOperaciones,
'es_contabilidad' => $esContabilidad,
'es_servicio_social' => $esServicioSocial,
'es_corporativo' => $esCorporativo,
'id_usuario' => $sessionUsuario['id'] ?? 0,
'id_puesto' => $usuario->id_puesto ?? 0,
'tipo_puesto' => $usuario->puesto->tipo_puesto ?? '',
'ver_prosegur' => $verProsegur,
'puede_eliminar_doc' => $puedeEliminarDoc,
'puede_leer' => $puedeLeer,
'puede_crear' => $puedeCrear,
'puede_editar' => $puedeEditar,
'puede_eliminar' => $puedeEliminar,
'puede_descargar' => $puedeDescargar,
];
}

private static function getBaucher(int $idReporte, string $concepto): float
{
$row = TarjetasCB::where('idreporte_dia', $idReporte)
->where('concepto', $concepto)
->first();
return $row ? (float) $row->baucher : 0;
}

private static function getControlgasPago(int $idReporte, string $concepto): float
{
$row = ClientesControlgas::where('idreporte_dia', $idReporte)
->where('concepto', $concepto)
->first();
return $row ? (float) $row->pago : 0;
}

private static function getControlgasConsumo(int $idReporte, string $concepto): float
{
$row = ClientesControlgas::where('idreporte_dia', $idReporte)
->where('concepto', $concepto)
->first();
return $row ? (float) $row->consumo : 0;
}

private static function getProsegurImporte(int $idReporte, string $denominacion): float
{
$row = Prosegur::where('idreporte_dia', $idReporte)
->where('denominacion', $denominacion)
->first();
return $row ? (float) $row->importe : 0;
}

private static function calcularRow(array $d): array
{
$bancomer = self::getBaucher($d['id'], 'BBVA BANCOMER SA');
$amex = self::getBaucher($d['id'], 'AMERICAN EXPRESS');
$inburgas = self::getBaucher($d['id'], 'INBURGAS');
$inbursa = self::getBaucher($d['id'], 'INBURSA');
$totalTB = $bancomer + $amex + $inbursa;

$ticketcard = self::getBaucher($d['id'], 'TICKETCARD');
$g500fleet = self::getBaucher($d['id'], 'G500 FLETT');
$efecticard = self::getBaucher($d['id'], 'EFECTICARD');
$sodexo = self::getBaucher($d['id'], 'SODEXO');
$ultragas = self::getBaucher($d['id'], 'ULTRAGAS');
$energex = self::getBaucher($d['id'], 'ENERGEX');
$shell = self::getBaucher($d['id'], 'SHELL FLEET NAVIGATOR');
$totalTarjetas = $ticketcard + $g500fleet + $efecticard + $inburgas + $sodexo + $ultragas + $energex + $shell;

$valAccord = self::getBaucher($d['id'], 'VALE ACCORD');
$valEfectivale = self::getBaucher($d['id'], 'VALE EFECTIVALE');
$valSodexo = self::getBaucher($d['id'], 'VALE SODEXO');
$siVale = self::getBaucher($d['id'], 'SI VALE');
$totalVales = $valAccord + $valEfectivale + $valSodexo + $siVale;

$pagoC = self::getControlgasPago($d['id'], 'CRÉDITO (ANEXO)');
$consumoC = self::getControlgasConsumo($d['id'], 'CRÉDITO (ANEXO)');
$pagoD = self::getControlgasPago($d['id'], 'DEBITO (ANEXO)');
$consumoD = self::getControlgasConsumo($d['id'], 'DEBITO (ANEXO)');
$totalPago = $pagoC + $pagoD;
$totalConsumo = $consumoC + $consumoD;

$billeteM = self::getProsegurImporte($d['id'], 'BILLETE MATUTINO');
$billeteV = self::getProsegurImporte($d['id'], 'BILLETE VESPERTINO');
$billeteN = self::getProsegurImporte($d['id'], 'BILLETE NOCTURNO');
$morralla = self::getProsegurImporte($d['id'], 'MORRALLA');
$deposito = self::getProsegurImporte($d['id'], 'DEPOSITO BANCARIO');
$cheque1 = self::getProsegurImporte($d['id'], 'CHEQUE 1');
$transf1 = self::getProsegurImporte($d['id'], 'TRANSFERENCIA 1');
$cheque2 = self::getProsegurImporte($d['id'], 'CHEQUE 2');
$transf2 = self::getProsegurImporte($d['id'], 'TRANSFERENCIA 2');
$totalProsegur = $billeteM + $billeteV + $billeteN + $morralla + $deposito + $cheque1 + $transf1 + $cheque2 + $transf2;

return [
'fecha' => $d['fecha'],
'bancomer' => $bancomer,
'amex' => $amex,
'inbursa' => $inbursa,
'total_tb' => $totalTB,
'inburgas' => $inburgas,
'ticketcard' => $ticketcard,
'efecticard' => $efecticard,
'sodexo' => $sodexo,
'ultragas' => $ultragas,
'energex' => $energex,
'shell' => $shell,
'total_tarjetas' => $totalTarjetas,
'vale_accord' => $valAccord,
'vale_efectivale' => $valEfectivale,
'vale_sodexo' => $valSodexo,
'si_vale' => $siVale,
'total_vales' => $totalVales,
'credito_pago' => $pagoC,
'credito_consumo' => $consumoC,
'debito_pago' => $pagoD,
'debito_consumo' => $consumoD,
'total_pago' => $totalPago,
'total_consumo' => $totalConsumo,
'billete_matutino' => $billeteM,
'billete_vespertino' => $billeteV,
'billete_nocturno' => $billeteN,
'morralla' => $morralla,
'deposito_bancario' => $deposito,
'cheque1' => $cheque1,
'transferencia1' => $transf1,
'cheque2' => $cheque2,
'transferencia2' => $transf2,
'total_prosegur' => $totalProsegur,
];
}

public static function getData(int $idMesDb): array
{
$dias = CorteDia::where('id_mes', $idMesDb)
->orderBy('fecha')
->get(['id', 'fecha']);

$rows = [];
$totales = [];

foreach ($dias as $dia) {
            $r = self::calcularRow(['id' => $dia->id, 'fecha' => formatearFecha($dia->fecha->format('Y-m-d'))]);
$rows[] = $r;

foreach ($r as $k => $v) {
if ($k === 'fecha') continue;
$totales[$k] = ($totales[$k] ?? 0) + $v;
}
}

return [
'rows' => $rows,
'totales' => $totales,
];
}

public static function getDocumentos(int $idMesDb): array
{
$docs = MonederoDocumento::where('id_mes', $idMesDb)
->orderBy('id', 'desc')
->get();

$result = [];
foreach ($docs as $doc) {
$arr = $doc->toArray();
$arr['fecha'] = formatearFecha($arr['fecha']);
$arr['fecha_input'] = $doc->fecha instanceof \Carbon\Carbon ? $doc->fecha->format('Y-m-d') : date('Y-m-d', strtotime($doc->fecha));
$arr['fecha_evaluacion'] = $arr['fecha_evaluacion'] ? formatearFecha($arr['fecha_evaluacion']) : '';
$result[] = $arr;
}

return $result;
}

public static function getDocumentoById(int $id): ?array
{
$doc = MonederoDocumento::find($id);
return $doc ? $doc->toArray() : null;
}

public static function getEdiByDocumento(int $idDocumento): array
{
return MonederoEdi::where('id_documento', $idDocumento)
->orderBy('id', 'desc')
->get()
->toArray();
}

public static function getListaDocumentos(int $idMonedero): array
{
$rows = MonederoListaDocumento::where('id_monedero', $idMonedero)
->orderBy('id', 'desc')
->get()
->toArray();

foreach ($rows as &$row) {
$row['fecha_formateada'] = $row['fecha_hora'] ? formatearFecha($row['fecha_hora']) : '';
}
unset($row);

return $rows;
}

public static function getResumenPeriodo(int $idEstacion, int $year, int $mes): array
{
    $periodRanges = [
        ['label' => '1er periodo', 'hasta' => '8',  'startDay' => 1,  'endDay' => 8],
        ['label' => '2do periodo', 'hasta' => '15', 'startDay' => 9,  'endDay' => 15],
        ['label' => '3er periodo', 'hasta' => '22', 'startDay' => 16, 'endDay' => 22],
        ['label' => '4to periodo', 'hasta' => '29', 'startDay' => 23, 'endDay' => 29],
        ['label' => '5to periodo', 'hasta' => '30/31', 'startDay' => 30, 'endDay' => 31],
    ];

    $conceptKeys = [
        'Toinburgas' => 'INBURGAS',
        'Toticketcard' => 'TICKETCARD',
        'Tog500fleet' => 'G500 FLETT',
        'Toefecticard' => 'EFECTICARD',
        'Tosodexo' => 'SODEXO',
        'Toultragas' => 'ULTRAGAS',
        'Toenergex' => 'ENERGEX',
        'Tovalaccord' => 'VALE ACCORD',
        'Tovalefectivale' => 'VALE EFECTIVALE',
        'Tovalsodexo' => 'VALE SODEXO',
        'Tovalvale' => 'SI VALE',
    ];

    $mesDb = self::getMesId($idEstacion, $year, $mes);
    if (!$mesDb) {
        return ['periodos' => [], 'totales' => []];
    }

    $resultados = [];
    $acumulados = array_fill_keys(array_keys($conceptKeys), 0);

    foreach ($periodRanges as $r) {
        $fechaInicio = sprintf('%04d-%02d-%02d', $year, $mes, $r['startDay']);
        $fechaTermino = sprintf('%04d-%02d-%02d', $year, $mes, $r['endDay']);

        $dias = CorteDia::where('id_mes', $mesDb)
            ->whereBetween('fecha', [$fechaInicio, $fechaTermino])
            ->orderBy('fecha')
            ->get(['id', 'fecha']);

        $data = array_fill_keys(array_keys($conceptKeys), 0);

        foreach ($dias as $dia) {
            foreach ($conceptKeys as $key => $concepto) {
                $val = (float) (TarjetasCB::where('idreporte_dia', $dia->id)
                    ->where('concepto', $concepto)
                    ->value('baucher') ?? 0);
                $data[$key] += $val;
                $acumulados[$key] += $val;
            }
        }

        $primerTotal = $data['Toinburgas'] + $data['Toticketcard'] + $data['Tog500fleet']
            + $data['Toefecticard'] + $data['Tosodexo'] + $data['Toultragas'] + $data['Toenergex'];
        $segundoTotal = $data['Tovalaccord'] + $data['Tovalefectivale'] + $data['Tovalsodexo'] + $data['Tovalvale'];

        $resultados[] = [
            'label' => $r['label'],
            'hasta' => $r['hasta'],
            'data' => $data,
            'primer_total' => $primerTotal,
            'segundo_total' => $segundoTotal,
        ];
    }

    $totalPrimer = $acumulados['Toinburgas'] + $acumulados['Toticketcard'] + $acumulados['Tog500fleet']
        + $acumulados['Toefecticard'] + $acumulados['Tosodexo'] + $acumulados['Toultragas'] + $acumulados['Toenergex'];
    $totalSegundo = $acumulados['Tovalaccord'] + $acumulados['Tovalefectivale'] + $acumulados['Tovalsodexo'] + $acumulados['Tovalvale'];

    return [
        'periodos' => $resultados,
        'totales' => array_merge($acumulados, ['primer_total' => $totalPrimer, 'segundo_total' => $totalSegundo]),
    ];
}

public static function getUploadDir(): string
{
$dir = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'archivos');
if (!$dir) {
$dir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'archivos';
if (!is_dir($dir)) {
mkdir($dir, 0755, true);
}
}
return $dir;
}

public static function getListaUploadDir(): string
{
$base = self::getUploadDir();
$dir = $base . DIRECTORY_SEPARATOR . 'resumen-monederos-documentos';
if (!is_dir($dir)) {
mkdir($dir, 0755, true);
}
return $dir;
}

private static function calcularPuntaje(string $year, string $mes): int
{
$fechaActual = date('Y-m-d');
$diaActual = (int) date('j');
$mesFormateado = sprintf('%02d', $mes);

$limite20 = "{$year}-{$mesFormateado}-20";
$limite25 = "{$year}-{$mesFormateado}-25";
$limite28 = "{$year}-{$mesFormateado}-28";

if ($fechaActual <= $limite20) return 3;
if ($fechaActual > $limite20 && $fechaActual <= $limite25) return 2;
if ($fechaActual > $limite25 && $fechaActual <= $limite28) return 1;
return 0;
}

public static function createDocumento(array $data, array $files, string $uploadDir): bool
{
$aleatorio = uniqid();
$idMes = (int) ($data['id_mes'] ?? 0);
$fecha = $data['fecha'] ?? '';
$monedero = $data['monedero'] ?? '';
$diferencia = (float) ($data['diferencia'] ?? 0);
$year = $data['year'] ?? '';
$mes = $data['mes'] ?? '';

$documentoPDF = '';
$documentoXML = '';

if (!empty($files['PDF_file']['name'])) {
$pdfName = $aleatorio . '-' . $files['PDF_file']['name'];
if (move_uploaded_file($files['PDF_file']['tmp_name'], $uploadDir . '/' . $pdfName)) {
$documentoPDF = $pdfName;
}
}

if (!empty($files['XML_file']['name'])) {
$xmlName = $aleatorio . '-' . $files['XML_file']['name'];
if (move_uploaded_file($files['XML_file']['tmp_name'], $uploadDir . '/' . $xmlName)) {
$documentoXML = $xmlName;
}
}

$puntaje = self::calcularPuntaje($year, $mes);
$fechaActual = date('Y-m-d');

$doc = MonederoDocumento::create([
'id_mes' => $idMes,
'fecha' => $fecha,
'monedero' => $monedero,
'diferencia' => $diferencia,
'pdf' => $documentoPDF,
'xml' => $documentoXML,
'excel' => '',
'sodi' => '',
'fecha_evaluacion' => $fechaActual,
'puntaje' => $puntaje,
]);

return $doc->exists;
}

public static function updateDocumento(int $id, array $data, array $files, string $uploadDir): bool
{
$doc = MonederoDocumento::find($id);
if (!$doc) return false;

$aleatorio = uniqid();

$updateData = [
'fecha' => $data['fecha'] ?? $doc->fecha,
'monedero' => $data['monedero'] ?? $doc->monedero,
'diferencia' => (float) ($data['diferencia'] ?? $doc->diferencia),
];

if (!empty($files['PDF_file']['name'])) {
$pdfName = $aleatorio . '-' . $files['PDF_file']['name'];
if (move_uploaded_file($files['PDF_file']['tmp_name'], $uploadDir . '/' . $pdfName)) {
$updateData['pdf'] = $pdfName;
}
}

if (!empty($files['XML_file']['name'])) {
$xmlName = $aleatorio . '-' . $files['XML_file']['name'];
if (move_uploaded_file($files['XML_file']['tmp_name'], $uploadDir . '/' . $xmlName)) {
$updateData['xml'] = $xmlName;
}
}

if (!empty($files['EXCEL_file']['name'])) {
$excelName = $aleatorio . '-' . $files['EXCEL_file']['name'];
if (move_uploaded_file($files['EXCEL_file']['tmp_name'], $uploadDir . '/' . $excelName)) {
$updateData['excel'] = $excelName;
}
}

if (!empty($files['SoporteD_file']['name'])) {
$sodiName = $aleatorio . '-' . $files['SoporteD_file']['name'];
if (move_uploaded_file($files['SoporteD_file']['tmp_name'], $uploadDir . '/' . $sodiName)) {
$updateData['sodi'] = $sodiName;
}
}

return $doc->update($updateData);
}

public static function deleteDocumento(int $id): bool
{
$doc = MonederoDocumento::find($id);
if (!$doc) return false;

MonederoEdi::where('id_documento', $id)->delete();
MonederoListaDocumento::where('id_monedero', $id)->delete();

return (bool) $doc->delete();
}

public static function createEdi(int $idDocumento, string $complemento, array $files, string $uploadDir): bool
{
$aleatorio = uniqid();
$documentoPDF = '';
$documentoXML = '';

if (!empty($files['PDF_file']['name'])) {
$pdfName = $aleatorio . '-' . $files['PDF_file']['name'];
if (move_uploaded_file($files['PDF_file']['tmp_name'], $uploadDir . '/' . $pdfName)) {
$documentoPDF = $pdfName;
}
}

if (!empty($files['XML_file']['name'])) {
$xmlName = $aleatorio . '-' . $files['XML_file']['name'];
if (move_uploaded_file($files['XML_file']['tmp_name'], $uploadDir . '/' . $xmlName)) {
$documentoXML = $xmlName;
}
}

$edi = MonederoEdi::create([
'id_documento' => $idDocumento,
'complemento' => $complemento,
'pdf' => $documentoPDF,
'xml' => $documentoXML,
]);

return $edi->exists;
}

public static function deleteEdi(int $id): bool
{
return (bool) MonederoEdi::where('id', $id)->delete();
}

public static function createListaDocumento(int $idMonedero, string $descripcion, array $files, string $uploadDir): bool
{
$documento = '';

if (!empty($files['ArchivoPDF_file']['name'])) {
$file = $files['ArchivoPDF_file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, ['pdf', 'xlsx'])) {
return false;
}

$numero = rand(1, 1000000);
$nombre = $numero . '-Documentacion-' . rand(1, 1000000) . '.' . $ext;

if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $nombre)) {
$documento = $nombre;
}
}

if (empty($documento)) return false;

$record = MonederoListaDocumento::create([
'id_monedero' => $idMonedero,
'descripcion' => $descripcion,
'archivo' => $documento,
]);

return $record->exists;
}

public static function deleteListaDocumento(int $id): bool
{
$record = MonederoListaDocumento::find($id);
if (!$record) return false;

$uploadDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'archivos' . DIRECTORY_SEPARATOR . 'resumen-monederos-documentos');
$ruta = ($uploadDir ? $uploadDir : (__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'archivos' . DIRECTORY_SEPARATOR . 'resumen-monederos-documentos')) . DIRECTORY_SEPARATOR . $record->archivo;
if (!empty($record->archivo) && file_exists($ruta)) {
@unlink($ruta);
}

return (bool) $record->delete();
}
}
