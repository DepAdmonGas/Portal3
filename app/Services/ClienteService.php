<?php

namespace App\Services;

use App\Models\Operativo\ConsumosPago;
use App\Models\Operativo\Cliente;
use App\Models\Operativo\CorteDia;
use App\Models\Operativo\ClientesControlgas;
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
}
