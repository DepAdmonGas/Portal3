<?php

namespace App\Services;

use App\Models\Operativo\OpAlmacenProveedor;
use App\Models\Operativo\OpAlmacenProveedorDocumento;
use Carbon\Carbon;

class ProveedoresService
{
    public const UPLOAD_FOLDER = 'public/uploads/archivos/proveedores/';

    public static function getUploadDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/' . self::UPLOAD_FOLDER;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public static function getNextFolio(): int
    {
        $max = OpAlmacenProveedor::max('folio');
        return ($max ?: 0) + 1;
    }

    public static function getData(): array
    {
        $rows = OpAlmacenProveedor::where('status', '!=', 1)
            ->orWhereNull('status')
            ->orderByDesc('folio')
            ->get();

        $hoy = Carbon::now()->startOfDay();
        $data = [];

        foreach ($rows as $r) {
            $documentos = OpAlmacenProveedorDocumento::where('id_proveedor', $r->id)->get();
            $alertas = 0;

            foreach ($documentos as $doc) {
                if (in_array(trim($doc->nombre), ['Constancia de Situacion Fiscal', 'Caratula Bancaria']) && !empty($doc->fecha)) {
                    try {
                        $fechaDoc = Carbon::parse($doc->fecha);
                        $fechaLimite = $fechaDoc->copy()->addMonths(3)->startOfDay();
                        if ($hoy->gte($fechaLimite)) {
                            $alertas++;
                        }
                    } catch (\Throwable $th) {}
                }
            }

            $fechaFmt = !empty($r->fecha) && function_exists('formatearFecha') 
                ? formatearFecha($r->fecha) 
                : ($r->fecha ?? 'S/I');

            $data[] = [
                'id'                  => (int)$r->id,
                'folio'               => '00' . $r->folio,
                'fecha'               => $fechaFmt,
                'razon_social'        => (string)($r->razon_social ?? ''),
                'actividad_economica' => (string)($r->actividad_economica ?? ''),
                'alertas_count'       => $alertas,
            ];
        }

        return $data;
    }

    public static function getDetalle(int $id): ?array
    {
        if ($id <= 0) return null;

        $r = OpAlmacenProveedor::find($id);
        if (!$r) return null;

        $hoy = Carbon::now()->startOfDay();
        $docsRaw = OpAlmacenProveedorDocumento::where('id_proveedor', $r->id)->orderBy('nombre', 'asc')->get();
        $docs = [];

        foreach ($docsRaw as $d) {
            $vencido = false;
            if (!empty($d->fecha)) {
                try {
                    $limite = Carbon::parse($d->fecha)->addMonths(3)->startOfDay();
                    $vencido = $hoy->gte($limite);
                } catch (\Throwable $th) {}
            }

            $fechaFmt = !empty($d->fecha) && function_exists('formatearFecha') 
                ? formatearFecha($d->fecha) 
                : ($d->fecha ?? 'Sin fecha');

            $docs[] = [
                'id'           => (int)$d->id,
                'nombre'       => (string)$d->nombre,
                'fecha'        => $fechaFmt,
                'fecha_raw'    => $d->fecha ?? '',
                'archivo'      => (string)($d->archivo ?? ''),
                'esta_vencido' => $vencido
            ];
        }

        $fechaProv = !empty($r->fecha) && function_exists('formatearFecha') 
            ? formatearFecha($r->fecha) 
            : ($r->fecha ?? '');

        return [
            'id'                  => (int)$r->id,
            'folio'               => '00' . $r->folio,
            'fecha'               => $fechaProv,
            'fecha_raw'           => $r->fecha ?? '',
            'razon_social'        => (string)($r->razon_social ?? ''),
            'actividad_economica' => (string)($r->actividad_economica ?? ''),
            'email'               => (string)($r->email ?? ''),
            'rfc'                 => (string)($r->rfc ?? ''),
            'ciudad'              => (string)($r->ciudad ?? ''),
            'telefono_1'          => (string)($r->telefono_1 ?? ''),
            'telefono_2'          => (string)($r->telefono_2 ?? ''),
            'direccion'           => (string)($r->direccion ?? ''),
            'beneficiario'        => (string)($r->beneficiario ?? ''),
            'banco'               => (string)($r->banco ?? ''),
            'metodo_pago'         => (string)($r->metodo_pago ?? ''),
            'cfdi'                => (string)($r->cfdi ?? ''),
            'moneda'              => (string)($r->moneda ?? 'MXN'),
            'forma_pago'          => (string)($r->forma_pago ?? ''),
            'descripcion'         => (string)($r->descripcion ?? ''),
            'documentos'          => $docs
        ];
    }

    public static function store(array $data, array $files): array
    {
        $dir = self::getUploadDir();
        $folio = self::getNextFolio();

        $proveedor = OpAlmacenProveedor::create([
            'folio'               => $folio,
            'fecha'               => $data['Fecha'] ?? date('Y-m-d'),
            'razon_social'        => $data['RazonSocial'] ?? '',
            'actividad_economica' => $data['ActividadEco'] ?? '',
            'email'               => $data['Email'] ?? '',
            'rfc'                 => strtoupper(trim($data['RFC'] ?? $data['rfc'] ?? '')),
            'ciudad'              => $data['Ciudad'] ?? '',
            'telefono_1'          => $data['Telefono1'] ?? '',
            'telefono_2'          => $data['Telefono2'] ?? '',
            'direccion'           => $data['Direccion'] ?? '',
            'beneficiario'        => $data['Beneficiario'] ?? '',
            'banco'               => $data['Banco'] ?? '',
            'metodo_pago'         => $data['Metodopago'] ?? $data['MetodoPago'] ?? '',
            'cfdi'                => $data['CFDI'] ?? '',
            'moneda'              => $data['Moneda'] ?? 'MXN',
            'forma_pago'          => $data['FormaPago'] ?? '',
            'descripcion'         => $data['Descripcion'] ?? '',
            'status'              => 0
        ]);

        if (!$proveedor) {
            return ['success' => false, 'message' => 'No se pudo crear el proveedor.', 'code' => 500];
        }

        if (!empty($files['ConstanciaS_file']['tmp_name'])) {
            $nomConstancia = uniqid() . '-' . $files['ConstanciaS_file']['name'];
            if (move_uploaded_file($files['ConstanciaS_file']['tmp_name'], $dir . $nomConstancia)) {
                OpAlmacenProveedorDocumento::create([
                    'id_proveedor' => $proveedor->id,
                    'nombre'       => 'Constancia de Situacion Fiscal',
                    'fecha'        => $data['FechaConstancia'] ?? date('Y-m-d'),
                    'archivo'      => $nomConstancia
                ]);
            }
        }

        if (!empty($files['CaratulaB_file']['tmp_name'])) {
            $nomCaratula = uniqid() . '-' . $files['CaratulaB_file']['name'];
            if (move_uploaded_file($files['CaratulaB_file']['tmp_name'], $dir . $nomCaratula)) {
                OpAlmacenProveedorDocumento::create([
                    'id_proveedor' => $proveedor->id,
                    'nombre'       => 'Caratula Bancaria',
                    'fecha'        => $data['FechaCaratula'] ?? date('Y-m-d'),
                    'archivo'      => $nomCaratula
                ]);
            }
        }

        return ['success' => true, 'message' => 'Proveedor registrado exitosamente.', 'code' => 200];
    }

    public static function update(int $id, array $data): array
    {
        $proveedor = OpAlmacenProveedor::find($id);
        if (!$proveedor) {
            return ['success' => false, 'message' => 'Proveedor no encontrado.', 'code' => 404];
        }

        $proveedor->update([
            'fecha'               => $data['Fecha'] ?? $proveedor->fecha,
            'razon_social'        => $data['RazonSocial'] ?? '',
            'actividad_economica' => $data['ActividadEco'] ?? '',
            'email'               => $data['Email'] ?? '',
            'rfc'                 => strtoupper(trim($data['RFC'] ?? $data['rfc'] ?? '')),
            'ciudad'              => $data['Ciudad'] ?? '',
            'telefono_1'          => $data['Telefono1'] ?? '',
            'telefono_2'          => $data['Telefono2'] ?? '',
            'direccion'           => $data['Direccion'] ?? '',
            'beneficiario'        => $data['Beneficiario'] ?? '',
            'banco'               => $data['Banco'] ?? '',
            'metodo_pago'         => $data['Metodopago'] ?? $data['MetodoPago'] ?? '',
            'cfdi'                => $data['CFDI'] ?? '',
            'moneda'              => $data['Moneda'] ?? 'MXN',
            'forma_pago'          => $data['FormaPago'] ?? '',
            'descripcion'         => $data['Descripcion'] ?? ''
        ]);

        return ['success' => true, 'message' => 'Proveedor actualizado exitosamente.', 'code' => 200];
    }

    public static function actualizarArchivo(int $idProveedor, string $tipoArchivo, string $fechaDoc, array $file): array
    {
        if ($idProveedor <= 0 || empty($tipoArchivo) || empty($fechaDoc) || empty($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Todos los campos y el archivo son obligatorios.', 'code' => 422];
        }

        $dir = self::getUploadDir();
        $nombreArchivo = uniqid() . '-' . $file['name'];

        if (!move_uploaded_file($file['tmp_name'], $dir . $nombreArchivo)) {
            return ['success' => false, 'message' => 'Error al subir el archivo físico.', 'code' => 500];
        }

        $doc = OpAlmacenProveedorDocumento::where('id_proveedor', $idProveedor)
            ->where('nombre', $tipoArchivo)
            ->first();

        if ($doc) {
            if (!empty($doc->archivo) && file_exists($dir . $doc->archivo)) {
                @unlink($dir . $doc->archivo);
            }
            $doc->fecha = $fechaDoc;
            $doc->archivo = $nombreArchivo;
            $doc->save();
        } else {
            OpAlmacenProveedorDocumento::create([
                'id_proveedor' => $idProveedor,
                'nombre'       => $tipoArchivo,
                'fecha'        => $fechaDoc,
                'archivo'      => $nombreArchivo
            ]);
        }

        return ['success' => true, 'message' => 'Documento actualizado exitosamente.', 'code' => 200];
    }

    public static function eliminar(int $id): array
    {
        $prov = OpAlmacenProveedor::find($id);
        if (!$prov) {
            return ['success' => false, 'message' => 'Proveedor no encontrado.', 'code' => 404];
        }

        $dir = self::getUploadDir();
        $documentos = OpAlmacenProveedorDocumento::where('id_proveedor', $id)->get();
        foreach ($documentos as $d) {
            if (!empty($d->archivo) && file_exists($dir . $d->archivo)) {
                @unlink($dir . $d->archivo);
            }
            $d->delete();
        }

        $prov->delete();
        return ['success' => true, 'message' => 'Registro eliminado exitosamente.', 'code' => 200];
    }
}