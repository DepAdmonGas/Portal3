<?php

namespace App\Services;

use App\Models\Operativo\ConsumosPago;
use App\Models\Operativo\Cliente;
use App\Models\Operativo\CorteDia;
use App\Models\Operativo\ClientesControlgas;
use App\Models\Estacion;
use App\Services\TelegramService;
use App\Core\Auth;
use App\Core\Session;

class ClienteService
{
public static function getEstado(int $idReporte): int
{
return 0;
}

public static function getFecha(int $idReporte): string
{
$corte = CorteDia::find($idReporte);
return $corte ? $corte->fecha->format('Y-m-d') : '';
}

public static function getPermisos(): array
{
$sessionUsuario = Session::get('usuario');
$multiEstacion = $sessionUsuario['multiestacion'] ?? false;
if (ModuleStationService::isPuesto6Estacion8()) {
$multiEstacion = false;
}

$usuario = Auth::user();
$esDireccionOperaciones = false;
if ($usuario && $usuario->puesto) {
$esDireccionOperaciones = ($usuario->puesto->tipo_puesto ?? '') === 'Dirección de operaciones';
}

$permisos = ModuloDptoOperativoService::permisosSesion('corporativo');

return [
'multiestacion' => $multiEstacion,
'es_direccion_operaciones' => $esDireccionOperaciones,
'id_usuario' => $sessionUsuario['id'] ?? 0,
'puedeLeer' => !empty($permisos['leer']),
'puedeCrear' => !empty($permisos['crear']),
'puedeEditar' => !empty($permisos['editar']),
'puedeEliminar' => !empty($permisos['eliminar']),
];
}

public static function getData(int $idReporte): array
{
$rows = ConsumosPago::where('id_reportedia', $idReporte)
->join('op_cliente', 'op_consumos_pagos.id_cliente', '=', 'op_cliente.id')
->select(
'op_consumos_pagos.id',
'op_consumos_pagos.id_reportedia',
'op_consumos_pagos.id_cliente',
'op_consumos_pagos.total',
'op_consumos_pagos.tipo as consumo_tipo',
'op_consumos_pagos.pago',
'op_consumos_pagos.comprobante',
'op_cliente.cuenta',
'op_cliente.cliente',
'op_cliente.tipo'
)
->get()
->toArray();

$dc = 0; $dp = 0; $cc = 0; $cp = 0;
$totalConsumo = 0; $totalPago = 0;

foreach ($rows as &$row) {
$row['total'] = (float) $row['total'];
if ($row['consumo_tipo'] === 'Consumo') {
$totalConsumo += $row['total'];
if ($row['tipo'] === 'Débito') $dc += $row['total'];
if ($row['tipo'] === 'Crédito') $cc += $row['total'];
} else {
$totalPago += $row['total'];
if ($row['tipo'] === 'Débito') $dp += $row['total'];
if ($row['tipo'] === 'Crédito') $cp += $row['total'];
}
}
unset($row);

$resumen = [
'dc' => $dc,
'dp' => $dp,
'cc' => $cc,
'cp' => $cp,
'total_consumo' => $totalConsumo,
'total_pago' => $totalPago,
];

return [
'rows' => $rows,
'resumen' => $resumen,
];
}

public static function getClientes(int $idEstacion): array
{
return Cliente::where('id_estacion', $idEstacion)
->where('estado', 1)
->select('id', 'cliente', 'cuenta', 'tipo', 'rfc')
->orderBy('cliente')
->get()
->toArray();
}

public static function getClientesLista(int $idEstacion): array
{
$credito = Cliente::where('id_estacion', $idEstacion)
->where('tipo', 'Crédito')
->orderBy('cliente')
->get()
->toArray();

$debito = Cliente::where('id_estacion', $idEstacion)
->where('tipo', 'Débito')
->orderBy('cliente')
->get()
->toArray();

return [
'credito' => $credito,
'debito' => $debito,
];
}

public static function toggleEstado(int $idCliente, int $estado): bool
{
$cliente = Cliente::find($idCliente);
if (!$cliente) return false;
$cliente->estado = $estado;
return $cliente->save();
}

public static function crearCliente(int $idEstacion, string $cuenta, string $cliente, string $tipo, string $rfc, array $files): array
{
$nuevo = new Cliente();
$nuevo->id_estacion = $idEstacion;
$nuevo->cuenta = $cuenta;
$nuevo->cliente = $cliente;
$nuevo->tipo = $tipo;
$nuevo->rfc = $rfc;
$nuevo->estado = 1;

try {
$saved = $nuevo->save();
} catch (\Throwable $e) {
return ['success' => false, 'message' => 'Error al guardar el cliente: ' . $e->getMessage()];
}

if ($saved) {
try {
$fieldMap = [];
if ($tipo === 'Crédito') {
$fieldMap = [
0 => 'doc_cc', 1 => 'doc_ac', 2 => 'doc_cd',
3 => 'doc_io', 4 => 'doc_rfc', 5 => 'doc_oc', 6 => 'doc_np',
];
} elseif ($tipo === 'Débito') {
$fieldMap = [2 => 'doc_cd', 3 => 'doc_io', 4 => 'doc_rfc'];
}
foreach ($fieldMap as $idx => $column) {
$file = $files[$idx] ?? null;
if ($file && !empty($file['name']) && $file['error'] === UPLOAD_ERR_OK) {
$aleatorio = uniqid();
$nombre = $aleatorio . '-' . basename($file['name']);
$destino = __DIR__ . '/../../public/archivos/' . $nombre;
if (move_uploaded_file($file['tmp_name'], $destino)) {
Cliente::where('id', $nuevo->id)
->where('id_estacion', $idEstacion)
->update([$column => $nombre]);
}
}
}
} catch (\Throwable $e) {
return ['success' => false, 'message' => 'Error al procesar archivos: ' . $e->getMessage()];
}
return ['success' => true, 'message' => 'Cliente creado exitosamente'];
}

return ['success' => false, 'message' => 'No se pudo crear el cliente'];
}

public static function editarCliente(int $idCliente, string $cuenta, string $cliente, string $tipo, string $rfc, array $files): array
{
$clienteObj = Cliente::find($idCliente);
if (!$clienteObj) return ['success' => false, 'message' => 'Cliente no encontrado'];

$clienteObj->cuenta = $cuenta;
$clienteObj->cliente = $cliente;
$clienteObj->tipo = $tipo;
$clienteObj->rfc = $rfc;

try {
$saved = $clienteObj->save();
} catch (\Throwable $e) {
return ['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()];
}

if ($saved) {
try {
$fieldMap = [];
if ($tipo === 'Crédito') {
$fieldMap = [
0 => 'doc_cc', 1 => 'doc_ac', 2 => 'doc_cd',
3 => 'doc_io', 4 => 'doc_rfc', 5 => 'doc_oc', 6 => 'doc_np',
];
} elseif ($tipo === 'Débito') {
$fieldMap = [2 => 'doc_cd', 3 => 'doc_io', 4 => 'doc_rfc'];
}
foreach ($fieldMap as $idx => $column) {
$file = $files[$idx] ?? null;
if ($file && !empty($file['name']) && $file['error'] === UPLOAD_ERR_OK) {
$aleatorio = uniqid();
$nombre = $aleatorio . '-' . basename($file['name']);
$destino = __DIR__ . '/../../public/archivos/' . $nombre;
if (move_uploaded_file($file['tmp_name'], $destino)) {
Cliente::where('id', $idCliente)->update([$column => $nombre]);
}
}
}
} catch (\Throwable $e) {
return ['success' => false, 'message' => 'Error al procesar archivos: ' . $e->getMessage()];
}
return ['success' => true, 'message' => 'Cliente editado exitosamente'];
}

return ['success' => false, 'message' => 'No se pudo actualizar el cliente'];
}

public static function agregarPago(int $idReporte, int $idCliente, float $total, string $formaPago, ?array $file): bool
{
$pdfNombre = '';

if ($file && !empty($file['name'])) {
$aleatorio = uniqid();
$uploadFolder = __DIR__ . '/../../public/archivos/' . $aleatorio . '-' . $file['name'];
$pdfNombre = $aleatorio . '-' . $file['name'];
move_uploaded_file($file['tmp_name'], $uploadFolder);
}

$consumoPago = new ConsumosPago();
$consumoPago->id_reportedia = $idReporte;
$consumoPago->id_cliente = $idCliente;
$consumoPago->total = $total;
$consumoPago->tipo = 'Pago';
$consumoPago->pago = $formaPago;
$consumoPago->comprobante = $pdfNombre;

return $consumoPago->save();
}

public static function agregarConsumo(int $idReporte, int $idCliente, float $total): bool
{
$consumoPago = new ConsumosPago();
$consumoPago->id_reportedia = $idReporte;
$consumoPago->id_cliente = $idCliente;
$consumoPago->total = $total;
$consumoPago->tipo = 'Consumo';
$consumoPago->pago = '';
$consumoPago->comprobante = '';

return $consumoPago->save();
}

public static function eliminarConsumoPago(int $id): bool
{
return ConsumosPago::where('id', $id)->delete() > 0;
}

public static function sincronizarControlgas(int $idReporte): void
{
$dc = self::resumenTipo($idReporte, 'Débito', 'Consumo');
$dp = self::resumenTipo($idReporte, 'Débito', 'Pago');
$cc = self::resumenTipo($idReporte, 'Crédito', 'Consumo');
$cp = self::resumenTipo($idReporte, 'Crédito', 'Pago');

ClientesControlgas::updateOrCreate(
['idreporte_dia' => $idReporte, 'concepto' => 'DEBITO (ANEXO)'],
['pago' => $dp, 'consumo' => $dc]
);

ClientesControlgas::updateOrCreate(
['idreporte_dia' => $idReporte, 'concepto' => 'CRÉDITO (ANEXO)'],
['pago' => $cp, 'consumo' => $cc]
);
}

private static function resumenTipo(int $idReporte, string $tipoCliente, string $tipoConsumo): float
{
$total = ConsumosPago::where('id_reportedia', $idReporte)
->join('op_cliente', 'op_consumos_pagos.id_cliente', '=', 'op_cliente.id')
->where('op_cliente.tipo', $tipoCliente)
->where('op_consumos_pagos.tipo', $tipoConsumo)
->sum('op_consumos_pagos.total');

return (float) $total;
}

private static function getEstacionIdFromReporte(int $idReporte): int
{
return (int) CorteDia::from('op_corte_dia as d')
->join('op_corte_mes as m', 'd.id_mes', '=', 'm.id')
->join('op_corte_year as y', 'm.id_year', '=', 'y.id')
->where('d.id', $idReporte)
->value('y.id_estacion');
}

private static function enviarTelegram(int $idEstacion, int $excludeUserId, string $mensaje): void
{
$telegram = new TelegramService();
$userIds = $telegram->getUserIdsByStation($idEstacion, $excludeUserId);

if (in_array($idEstacion, [6, 7])) {
$extraIds = $telegram->getUserIdsComercializadora($excludeUserId);
$userIds = array_values(array_unique(array_merge($userIds, $extraIds)));
} elseif (in_array($idEstacion, [1, 2, 3, 4, 5, 14])) {
$extraIds = $telegram->getUserIdsContabilidad($excludeUserId);
$userIds = array_values(array_unique(array_merge($userIds, $extraIds)));
}

$telegram->sendMessageToMultiple($userIds, $mensaje);
}

public static function notificarAgregarCliente(int $idReporte, int $idUsuario, string $nombreUsuario, string $tipo, ?string $nombreCliente = '', float $total = 0, int $idCliente = 0): void
{
try {
$idEstacion = self::getEstacionIdFromReporte($idReporte);
if (!$idEstacion) return;

$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';
$corte = CorteDia::find($idReporte);
$fechaStr = $corte ? formatearFecha($corte->fecha->format('Y-m-d')) : '';

$totalStr = $total > 0 ? '$ ' . number_format($total, 2) : 'N/A';

$clienteData = $idCliente > 0 ? Cliente::find($idCliente) : null;
$idClienteStr = $idCliente > 0 ? 'ID: ' . $idCliente . PHP_EOL : '';
$cuentaStr = $clienteData ? ($clienteData->cuenta ?? '') : '';
$tipoClienteStr = $clienteData ? ($clienteData->tipo ?? '') : '';
$rfcClienteStr = $clienteData ? ($clienteData->rfc ?? '') : '';

$mensaje = '💳 ' . ($tipo === 'pago' ? 'Se ha registrado un pago' : 'Se ha registrado un consumo') . ' en el apartado de <b>Clientes</b> correspondiente al Corte Diario con fecha del día <b>' . $fechaStr . '</b>:' . PHP_EOL . PHP_EOL
. '👤 <b>Nombre del Cliente:</b> ' . ($nombreCliente ?? '') . PHP_EOL
. '💼 <b>Cuenta:</b> ' . ($cuentaStr ?: 'N/A') . PHP_EOL
. ($tipoClienteStr ? '📌 <b>Tipo de cliente:</b> ' . $tipoClienteStr . PHP_EOL : '')
. ($rfcClienteStr ? '🧾 <b>RFC:</b> ' . $rfcClienteStr . PHP_EOL : '')
. '💰 <b>Total:</b> ' . $totalStr . PHP_EOL  . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;

self::enviarTelegram($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarAgregarCliente: ' . $e->getMessage());
}
}

public static function notificarEliminarCliente(int $idReporte, int $idUsuario, string $nombreUsuario, array $deleteInfo = []): void
{
try {
$idEstacion = self::getEstacionIdFromReporte($idReporte);
if (!$idEstacion) return;

$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';
$corte = CorteDia::find($idReporte);
$fechaStr = $corte ? formatearFecha($corte->fecha->format('Y-m-d')) : '';

$detalle = '';

if (!empty($deleteInfo)) {
$detalle .= '👤 <b>Nombre del Cliente:</b> ' . ($deleteInfo['cliente'] ?? 'N/A') . PHP_EOL
. '💼 <b>Cuenta:</b> ' . ($deleteInfo['cuenta'] ?? 'N/A') . PHP_EOL
. '📌 <b>Tipo de cliente:</b> ' . ($deleteInfo['tipo_cliente'] ?? 'N/A') . PHP_EOL
. '💰 <b>Total:</b> ' . (isset($deleteInfo['total']) ? '$ ' . number_format((float)$deleteInfo['total'], 2) : 'N/A');
}

$mensaje = '🗑️ Se ha eliminado un registro del apartado de <b>Clientes</b> correspondiente al Corte Diario con fecha del día <b>' . $fechaStr . '</b>:' . PHP_EOL . PHP_EOL
. $detalle . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario. PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;

self::enviarTelegram($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarEliminarCliente: ' . $e->getMessage());
}
}

public static function notificarCrearClienteLista(int $idEstacion, int $idUsuario, string $nombreUsuario, ?string $nombreCliente = '', string $cuenta = '', string $tipo = '', string $rfc = ''): void
{
try {
$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';

$mensaje = '📝 Se ha agregado un nuevo cliente en el apartado de <b>Lista de Clientes</b>:' . PHP_EOL . PHP_EOL
. '👤 <b>Nombre del cliente:</b> ' . ($nombreCliente ?? '') . PHP_EOL
. '💼 <b>Cuenta:</b> ' . ($cuenta ?: 'N/A') . PHP_EOL
. '📌 <b>Tipo de cliente:</b> ' . ($tipo ?: 'N/A') . PHP_EOL
. '🧾 <b>RFC:</b> ' . ($rfc ?: 'N/A') . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL 
. '⛽ <b>Estación:</b> ' . $nombreES;

self::enviarTelegram($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarCrearClienteLista: ' . $e->getMessage());
}
}

public static function notificarEditarClienteLista(int $idEstacion, int $idUsuario, string $nombreUsuario, ?string $nombreCliente = '', string $cuenta = '', string $tipo = '', string $rfc = '', int $idCliente = 0, array $oldData = []): void
{
try {
$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';

$cambios = '';
if (!empty($oldData)) {
$campos = [
'Cliente' => ['old' => $oldData['cliente'] ?? '', 'new' => $nombreCliente ?? ''],
'Cuenta' => ['old' => $oldData['cuenta'] ?? '', 'new' => $cuenta],
'Tipo' => ['old' => $oldData['tipo'] ?? '', 'new' => $tipo],
'RFC' => ['old' => $oldData['rfc'] ?? '', 'new' => $rfc],
];
foreach ($campos as $label => $vals) {
if ($vals['old'] !== $vals['new']) {
$cambios .= '• ' . $label . ': ' . ($vals['old'] ?: '(vacío)') . ' → ' . ($vals['new'] ?: '(vacío)') . PHP_EOL;
}
}
}

$mensaje = '✏️ Se ha editado un cliente en el apartado de <b>Lista de Clientes</b>:' . PHP_EOL . PHP_EOL
. '👤 <b>Nombre del cliente:</b> ' . ($nombreCliente ?? '') . PHP_EOL
. '💼 <b>Cuenta:</b> ' . ($cuenta ?: 'N/A') . PHP_EOL
. '📌 <b>Tipo de cliente:</b> ' . ($tipo ?: 'N/A') . PHP_EOL
. '🧾 <b>RFC:</b> ' . ($rfc ?: 'N/A') . PHP_EOL
. ($cambios ? PHP_EOL . '📋 <b>Cambios realizados:</b>' . PHP_EOL . $cambios . PHP_EOL : '')
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;

self::enviarTelegram($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarEditarClienteLista: ' . $e->getMessage());
}
}

public static function notificarToggleClienteLista(int $idEstacion, int $idUsuario, string $nombreUsuario, ?string $nombreCliente = '', string $nuevoEstado, string $cuenta = '', string $tipo = '', string $rfc = ''): void
{
try {
$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';

$mensaje = '🔄 Se ha ' . ($nuevoEstado === 'habilitó' ? 'habilitado' : 'deshabilitado') . ' un cliente en el apartado de <b>Lista de Clientes</b>:' . PHP_EOL . PHP_EOL
. '👤 <b>Nombre del cliente:</b> ' . ($nombreCliente ?? '') . PHP_EOL
. '💼 <b>Cuenta:</b> ' . ($cuenta ?: 'N/A') . PHP_EOL
. '📌 <b>Tipo de cliente:</b> ' . ($tipo ?: 'N/A') . PHP_EOL
. '🧾 <b>RFC:</b> ' . ($rfc ?: 'N/A') . PHP_EOL
. '📊 <b>Nuevo estado:</b> ' . ($nuevoEstado === 'habilitó' ? 'Habilitado' : 'Deshabilitado') . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL 
. '⛽ <b>Estación:</b> ' . $nombreES;

self::enviarTelegram($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarToggleClienteLista: ' . $e->getMessage());
}
}
}
