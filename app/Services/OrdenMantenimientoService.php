<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Operativo\PedidoMateriales;
use App\Models\Operativo\PedidoMaterialesArea;
use App\Models\Operativo\PedidoMaterialesAreaOtros;
use App\Models\Operativo\PedidoMaterialesComentarios;
use App\Models\Operativo\PedidoMaterialesDetalle;
use App\Models\Operativo\PedidoMaterialesEvidenciaArchivo;
use App\Models\Operativo\PedidoMaterialesFirma;
use App\Models\Operativo\PedidoMaterialesToken;
use App\Models\Operativo\PedidoMaterialesInstalacion;
use App\Models\Operativo\PedidoMaterialesCausa;
use App\Models\Estacion;
use App\Models\Usuario;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Portal3 adapter for the legacy Pedido de Materiales workflow shown at
 * /departamento-operativo/almacen/orden-mantenimiento.
 * It deliberately does not use the separate op_orden_mantenimiento tables.
 */
final class OrdenMantenimientoService
{
    public const MODULE_KEY = 'orden-mantenimiento';
    public const PARENT_MODULE = 'almacen';
    public const UPLOAD_FOLDER = 'public/uploads/archivos/';
    public const CAUSE_UPLOAD_FOLDER = 'public/uploads/archivos/orden-mantenimiento-causa/';

    private const DEFAULT_AREAS = [
        'Zona de despacho', 'Zona de tanques', 'Cuarto eléctrico',
        'Cuarto de residuos', 'Bodega aceites', 'Baños clientes',
    ];

    public static function permissions(): array
    {
        $user = Auth::user();
        return [
            'leer' => ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'leer'),
            'crear' => ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'crear'),
            'editar' => ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'editar'),
            'eliminar' => ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'eliminar'),
            'descargar' => ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'descargar'),
            'id_usuario' => (int)(Session::get('usuario')['id'] ?? 0),
            'id_puesto' => (int)($user->id_puesto ?? 0),
        ];
    }

    /** Available station and locality ids are intersected with ModuleStationService. */
    private static function contexts(): array
    {
        $contexts = [];
        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $row) {
            $contexts[(int)$row['id']] = (string)$row['nombre'];
        }
        foreach (ModuleStationService::getAvailableDepartments(self::MODULE_KEY) as $row) {
            $contexts[(int)$row['id']] = (string)$row['nombre'];
        }
        return $contexts;
    }

    private static function contextId(): ?int
    {
        $context = ModuleStationService::getContext(self::MODULE_KEY);
        $id = $context['id_estacion'] ?? $context['id_depto'] ?? null;
        return $id !== null && (int)$id > 0 ? (int)$id : null;
    }

    private static function authorizedRecord(int $id): ?PedidoMateriales
    {
        $contexts = self::contexts();
        if ($id <= 0 || $contexts === []) return null;
        $record = PedidoMateriales::find($id);
        return $record && isset($contexts[(int)$record->id_estacion]) ? $record : null;
    }

    public static function getData(): array
    {
        $contexts = self::contexts();
        if ($contexts === []) return [];
        $contextId = self::contextId();
        if ($contextId !== null && !isset($contexts[$contextId])) return [];

        $query = PedidoMateriales::query()->whereIn('id_estacion', array_keys($contexts));
        if ($contextId !== null) $query->where('id_estacion', $contextId);
        $records = $query->orderByDesc('folio')->get();
        if ($records->isEmpty()) return [];

        $ids = $records->pluck('id')->all();
        $concepts = PedidoMaterialesDetalle::whereIn('id_pedido', $ids)
            ->orderBy('id')->get()->groupBy('id_pedido');
        $commentCounts = PedidoMaterialesComentarios::whereIn('id_pedido', $ids)
            ->selectRaw('id_pedido, COUNT(*) as total')->groupBy('id_pedido')->pluck('total', 'id_pedido');
        $signatureTypes = PedidoMaterialesFirma::whereIn('id_pedido', $ids)
            ->get(['id_pedido', 'tipo_firma'])->groupBy('id_pedido');
        $installations = PedidoMaterialesInstalacion::whereIn('id_pedido', $ids)->pluck('id_pedido')->all();
        $causes = PedidoMaterialesCausa::whereIn('id_reporte', $ids)->pluck('id_reporte')->all();
        $permissions = self::permissions();
        $allContexts = self::contextId() === null;
        $result = [];

        foreach ($records as $record) {
            $status = (int)$record->estatus;
            // Read the stored date directly so the display is not affected by
            // Eloquent's `date` cast when the record is serialized for JSON.
            $recordDate = (string)$record->getRawOriginal('fecha');
            $hasInstall = in_array((int)$record->id, array_map('intval', $installations), true);
            $hasCause = in_array((int)$record->id, array_map('intval', $causes), true);
            $subject = $concepts->get($record->id, collect())
                ->pluck('concepto')
                ->map(static fn($concepto) => trim((string)$concepto))
                ->filter()
                ->implode(', ');
            $affectation = trim((string)$record->afectacion);
            $result[] = [
                'id' => (int)$record->id,
                'folio' => (int)$record->folio,
                'folio_label' => str_pad((string)$record->folio, 3, '0', STR_PAD_LEFT),
                'id_estacion' => (int)$record->id_estacion,
                'estacion' => $contexts[(int)$record->id_estacion] ?? 'S/I',
                'fecha' => self::formatDate($recordDate),
                // Keep every concept visible, separated for readability.
                'asunto' => $subject !== '' ? $subject : 'S/I',
                'afectacion' => $affectation !== '' ? $affectation : 'S/I',
                'estatus' => $status,
                'estatus_texto' => self::statusText($status, $hasInstall, $hasCause),
                'comentarios' => (int)($commentCounts[$record->id] ?? 0),
                'firma_b' => $signatureTypes->get($record->id, collect())->contains('tipo_firma', 'B'),
                'firma_c' => $signatureTypes->get($record->id, collect())->contains('tipo_firma', 'C'),
                'puede_editar' => $permissions['editar'] && in_array($status, [0, 1], true),
                'puede_eliminar' => $permissions['eliminar'] && ($status === 0 || $permissions['id_usuario'] === 19),
                'puede_descargar' => $permissions['descargar'] && in_array($status, [2, 3], true),
                // Keep the legacy menu's mutually exclusive states: once an
                // installation is registered, the Cause action is disabled;
                // once a cause is registered, Evidence is disabled.
                'puede_ver_evidencia' => in_array($status, [2, 3], true) && !$hasCause,
                'puede_ver_causa' => in_array($status, [2, 3], true) && !$hasInstall,
                'puede_firmar' => ($permissions['id_usuario'] === 21 && $status === 1 && !$signatureTypes->get($record->id, collect())->contains('tipo_firma', 'B'))
                    || ($permissions['id_usuario'] === 19 && $status === 1 && $signatureTypes->get($record->id, collect())->contains('tipo_firma', 'B') && !$signatureTypes->get($record->id, collect())->contains('tipo_firma', 'C')),
                'tipo_firma_pendiente' => $permissions['id_usuario'] === 21 ? 'B' : ($permissions['id_usuario'] === 19 ? 'C' : ''),
                'puede_ver_estacion' => $allContexts,
            ];
        }
        return $result;
    }

    public static function create(): array
    {
        $contexts = self::contexts();
        $idContext = self::contextId();
        if ($idContext === null || !isset($contexts[$idContext])) {
            return self::fail('Selecciona una estación o departamento en el selector superior.', 422);
        }
        try {
            $id = DB::transaction(static function () use ($idContext): int {
                $folio = ((int)PedidoMateriales::where('id_estacion', $idContext)->max('folio')) + 1;
                $record = PedidoMateriales::create([
                    'folio' => $folio, 'id_estacion' => $idContext,
                    'fecha' => date('Y-m-d'), 'afectacion' => '',
                    'tipo_servicio' => 0, 'orden_trabajo' => 0,
                    'orden_riesgo' => 0, 'comentarios' => '', 'estatus' => 0,
                ]);
                foreach (self::DEFAULT_AREAS as $area) {
                    $areaRecord = PedidoMaterialesArea::create(['id_pedido' => $record->id, 'area' => $area, 'estatus' => 0]);
                    self::seedSubareas($areaRecord, $idContext);
                }
                return (int)$record->id;
            });
            self::notify($id, 'creado una nueva solicitud');
            return ['success' => true, 'message' => 'Solicitud creada.', 'id' => $id, 'code' => 201];
        } catch (\Throwable $e) {
            error_log('[OrdenMantenimiento] create: '.$e->getMessage());
            return self::fail('No se pudo crear la solicitud.', 500);
        }
    }

    public static function detail(int $id): ?array
    {
        $record = self::authorizedRecord($id);
        if (!$record) return null;
        $recordDate = (string)$record->getRawOriginal('fecha');
        $contexts = self::contexts();
        $areas = PedidoMaterialesArea::where('id_pedido', $id)->orderBy('id')->get();
        $subareas = PedidoMaterialesAreaOtros::whereIn('id_area', $areas->pluck('id'))
            ->orderBy('id')->get()->groupBy('id_area');
        $details = PedidoMaterialesDetalle::where('id_pedido', $id)->orderBy('id')->get();
        $status = (int)$record->estatus;
        $hasInstall = PedidoMaterialesInstalacion::where('id_pedido', $id)->exists();
        $hasCause = PedidoMaterialesCausa::where('id_reporte', $id)->exists();
        $stationId = (int)$record->id_estacion;
        $razonSocial = $stationId === 9
            ? 'Autolavado'
            : trim((string)(Estacion::where('id', $stationId)->value('razonsocial') ?? ''));
        $files = self::files($id) ?? [];
        $signatures = PedidoMaterialesFirma::where('id_pedido', $id)->orderBy('id')->get()->map(static function ($signature): array {
            $file = basename((string)$signature->firma);
            $type = (string)$signature->tipo_firma;
            $signatureDate = (string)$signature->getRawOriginal('fecha');
            $imagePath = dirname(__DIR__, 2).'/public/uploads/archivos/'.$file;
            $imageAvailable = $type === 'A' && $file !== '' && is_file($imagePath);
            return [
                'tipo' => $type,
                'titulo' => match ($type) {
                    'A' => 'NOMBRE Y FIRMA DEL ENCARGADO',
                    'B' => 'NOMBRE Y FIRMA DE VO.BO',
                    'C' => 'NOMBRE Y FIRMA DE AUTORIZACIÓN',
                    default => 'Firma',
                },
                'usuario' => (string)(Usuario::where('id', (int)$signature->id_usuario)->value('nombre') ?: 'Usuario'),
                'fecha' => self::formatDateTimeLabel($signatureDate),
                'fecha_raw' => $signatureDate,
                'firma_url' => $imageAvailable
                    ? '/download?tipo=orden-mantenimiento&file='.rawurlencode($file).'&view=1'
                    : null,
                'firma_archivo_disponible' => $imageAvailable,
                'electronica' => $type !== 'A',
            ];
        })->all();
        return [
            'id' => (int)$record->id,
            'folio' => (int)$record->folio,
            'folio_label' => str_pad((string)$record->folio, 3, '0', STR_PAD_LEFT),
            'id_estacion' => (int)$record->id_estacion,
            'estacion' => $contexts[(int)$record->id_estacion] ?? 'S/I',
            'razon_social' => $razonSocial !== '' ? $razonSocial : 'S/I',
            'fecha' => $recordDate,
            'fecha_label' => self::formatDate($recordDate),
            'asunto' => ($subject = $details
                ->pluck('concepto')
                ->map(static fn($concepto) => trim((string)$concepto))
                ->filter()
                ->implode(', ')) !== '' ? $subject : 'S/I',
            'afectacion' => trim((string)$record->afectacion) !== '' ? trim((string)$record->afectacion) : 'S/I',
            'tipo_servicio' => (int)$record->tipo_servicio,
            'orden_trabajo' => (int)$record->orden_trabajo,
            'orden_riesgo' => (int)$record->orden_riesgo,
            'comentarios_generales' => (string)$record->comentarios,
            'estatus' => $status,
            'estatus_texto' => self::statusText($status, $hasInstall, $hasCause),
            'areas' => $areas->map(static fn($row) => [
                'id' => (int)$row->id,
                'area' => (string)$row->area,
                'estatus' => (int)$row->estatus,
                'subareas' => $subareas->get($row->id, collect())
                    ->filter(static fn($subarea) => (int)$row->estatus === 1 && (int)$subarea->estatus === 1)
                    ->map(static fn($subarea) => [
                    'id' => (int)$subarea->id,
                    'nombre' => (string)$subarea->sub_area,
                    'estatus' => (int)$subarea->estatus,
                ])->all(),
                'subareas_disponibles' => $subareas->get($row->id, collect())
                    ->map(static fn($subarea) => [
                        'id' => (int)$subarea->id,
                        'nombre' => (string)$subarea->sub_area,
                        'estatus' => (int)$subarea->estatus,
                    ])->all(),
            ])->all(),
            'materiales' => $details->map(static fn($row) => ['id' => (int)$row->id, 'concepto' => (string)$row->concepto, 'cantidad' => (string)$row->cantidad, 'nota' => (string)$row->nota])->all(),
            'evidencias' => $files['evidencias'] ?? [],
            'firmas' => $signatures,
        ];
    }

    public static function exportData(int $id): ?array
    {
        $detail = self::detail($id);
        $record = self::authorizedRecord($id);
        if ($detail === null || !$record || !in_array((int)$detail['estatus'], [2, 3], true)) return null;
        $station = Estacion::find((int)$record->id_estacion);
        $detail['direccion_completa'] = (int)$record->id_estacion === 9
            ? 'Av. Palo Solo #3515, Huixquilucan, Estado de México, C.P. 52787'
            : trim((string)($station->direccioncompleta ?? ''));
        $detail['causas'] = self::files($id)['causas'] ?? [];
        return $detail;
    }

    public static function update(int $id, array $data, bool $sendNotification = true): array
    {
        $record = self::authorizedRecord($id);
        if (!$record) return self::fail('Registro no encontrado.', 404);
        if (!in_array((int)$record->estatus, [0, 1], true)) return self::fail('La solicitud ya no permite edición.', 409);

        $allowed = ['afectacion' => 2000, 'comentarios' => 4000];
        $values = [];
        foreach ($allowed as $field => $limit) {
            if (array_key_exists($field, $data)) {
                $value = trim((string)$data[$field]);
                if (mb_strlen($value) > $limit) return self::fail('El campo '.$field.' excede la longitud permitida.', 422);
                $values[$field] = $value;
            }
        }
        foreach (['tipo_servicio' => [0,1,2,3], 'orden_trabajo' => [0,1,2,3], 'orden_riesgo' => [0,1,2]] as $field => $choices) {
            if (array_key_exists($field, $data)) {
                $v = filter_var($data[$field], FILTER_VALIDATE_INT);
                if ($v === false || !in_array($v, $choices, true)) return self::fail('Valor inválido para '.$field.'.', 422);
                $values[$field] = $v;
            }
        }
        try {
            $record->fill($values)->save();
            if ($sendNotification) self::notify($id, 'actualizado la solicitud');
            return ['success' => true, 'message' => 'Solicitud actualizada.', 'code' => 200];
        } catch (\Throwable $e) {
            error_log('[OrdenMantenimiento] update: '.$e->getMessage());
            return self::fail('No se pudo actualizar la solicitud.', 500);
        }
    }

    public static function addComment(int $id, string $text): array
    {
        $record = self::authorizedRecord($id);
        $text = trim($text);
        if (!$record) return self::fail('Registro no encontrado.', 404);
        if ($text === '' || mb_strlen($text) > 4000) return self::fail('Escribe un comentario válido.', 422);
        $userId = (int)(Session::get('usuario')['id'] ?? 0);
        if ($userId <= 0) return self::fail('Sesión no válida.', 401);
        try {
            PedidoMaterialesComentarios::create(['id_pedido' => $id, 'id_usuario' => $userId, 'comentario' => $text]);
            self::notifyComment($id, $text);
            return ['success' => true, 'message' => 'Comentario guardado.', 'code' => 200];
        } catch (\Throwable $e) {
            error_log('[OrdenMantenimiento] addComment: '.$e->getMessage());
            return self::fail('No se pudo guardar el comentario.', 500);
        }
    }

    public static function toggleArea(int $areaId, int $status): array
    {
        $area = PedidoMaterialesArea::find($areaId);
        if (!$area || !self::authorizedRecord((int)$area->id_pedido)) return self::fail('Área no encontrada.', 404);
        if (!in_array($status, [0, 1], true)) return self::fail('Estado de área inválido.', 422);
        $record = self::authorizedRecord((int)$area->id_pedido);
        if (!$record || !in_array((int)$record->estatus, [0, 1], true)) return self::fail('La orden ya no permite cambios.', 409);
        try {
            $area->estatus = $status;
            $area->save();
            return ['success' => true, 'message' => 'Área actualizada.', 'code' => 200];
        } catch (\Throwable $e) {
            error_log('[OrdenMantenimiento] toggleArea: '.$e->getMessage());
            return self::fail('No se pudo actualizar el área.', 500);
        }
    }

    public static function addArea(int $id, string $name): array
    {
        $record = self::authorizedRecord($id);
        $name = trim($name);
        if (!$record) return self::fail('Registro no encontrado.', 404);
        if (!in_array((int)$record->estatus, [0, 1], true)) return self::fail('La orden ya no permite agregar áreas.', 409);
        if ($name === '' || mb_strlen($name) > 180) return self::fail('Escribe un nombre de área válido.', 422);
        if (PedidoMaterialesArea::where('id_pedido', $id)->whereRaw('LOWER(area) = ?', [mb_strtolower($name)])->exists()) return self::fail('El área ya existe en esta orden.', 409);
        try {
            PedidoMaterialesArea::create(['id_pedido' => $id, 'area' => $name, 'estatus' => 1]);
            return ['success' => true, 'message' => 'Área agregada.', 'code' => 201];
        } catch (\Throwable $e) {
            error_log('[OrdenMantenimiento] addArea: '.$e->getMessage());
            return self::fail('No se pudo agregar el área.', 500);
        }
    }

    public static function toggleSubarea(int $subareaId, int $status): array
    {
        $subarea = PedidoMaterialesAreaOtros::find($subareaId);
        if (!$subarea) return self::fail('Subárea no encontrada.', 404);
        $area = PedidoMaterialesArea::find((int)$subarea->id_area);
        $record = $area ? self::authorizedRecord((int)$area->id_pedido) : null;
        if (!$record) return self::fail('Subárea fuera de tu alcance.', 404);
        if (!in_array((int)$record->estatus, [0, 1], true)) return self::fail('La orden ya no permite cambios.', 409);
        if (!in_array($status, [0, 1], true)) return self::fail('Estado de subárea inválido.', 422);
        try {
            $subarea->estatus = $status;
            $subarea->save();
            return ['success' => true, 'message' => 'Subárea actualizada.', 'code' => 200];
        } catch (\Throwable $e) {
            error_log('[OrdenMantenimiento] toggleSubarea: '.$e->getMessage());
            return self::fail('No se pudo actualizar la subárea.', 500);
        }
    }

    public static function addMaterial(int $id, array $data): array
    {
        $record = self::authorizedRecord($id);
        if (!$record) return self::fail('Registro no encontrado.', 404);
        if (!in_array((int)$record->estatus, [0, 1], true)) return self::fail('La orden ya no permite cambios.', 409);
        $quantity = filter_var($data['cantidad'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($quantity === false || $quantity < 1) return self::fail('La cantidad debe ser de al menos 1.', 422);
        $refaccionId = (int)($data['refaccion'] ?? 0);
        $manualName = trim((string)($data['otro'] ?? ''));
        $refaccion = null;
        if ($refaccionId > 0) {
            $refaccion = DB::table('op_refacciones')->where('id', $refaccionId)
                ->where('id_estacion', (int)$record->id_estacion)->where('status', 1)->first();
            if (!$refaccion) return self::fail('La refacción no está disponible para esta ubicación.', 422);
            $name = (string)$refaccion->nombre;
            $stock = (float)$refaccion->unidad;
            $stockLabel = trim((string)$refaccion->unidad);
            $note = $stock <= 0
                ? 'No se cuenta con piezas en almacén, se creará una orden de compra.'
                : ($stock < $quantity
                    ? 'Solo se cuenta con '.$stockLabel.' refacciones, se creará una orden de compra para las refacciones faltantes.'
                    : 'La refacción estará en la estación en 5 horas mínimo.');
        } else {
            if ($manualName === '' || mb_strlen($manualName) > 250) return self::fail('Selecciona una refacción o escribe otra.', 422);
            $name = $manualName;
            $refaccionId = 0;
            $note = 'No se cuenta con piezas en almacén, se creará una orden de compra.';
        }
        try {
            PedidoMaterialesDetalle::create([
                'id_pedido' => $id, 'refaccion' => $refaccionId, 'concepto' => $name,
                'cantidad' => rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.'), 'nota' => $note,
            ]);
            return ['success' => true, 'message' => 'Refacción agregada.', 'code' => 201];
        } catch (\Throwable $e) {
            error_log('[OrdenMantenimiento] addMaterial: '.$e->getMessage());
            return self::fail('No se pudo agregar la refacción.', 500);
        }
    }

    public static function deleteMaterial(int $detailId): array
    {
        $detail = PedidoMaterialesDetalle::find($detailId);
        if (!$detail || !self::authorizedRecord((int)$detail->id_pedido)) return self::fail('Refacción no encontrada.', 404);
        $record = self::authorizedRecord((int)$detail->id_pedido);
        if (!$record || !in_array((int)$record->estatus, [0, 1], true)) return self::fail('La orden ya no permite cambios.', 409);
        try {
            $detail->delete();
            return ['success' => true, 'message' => 'Refacción eliminada.', 'code' => 200];
        } catch (\Throwable $e) {
            error_log('[OrdenMantenimiento] deleteMaterial: '.$e->getMessage());
            return self::fail('No se pudo eliminar la refacción.', 500);
        }
    }

    public static function refacciones(int $id): ?array
    {
        $record = self::authorizedRecord($id);
        if (!$record) return null;
        return DB::table('op_refacciones')->where('id_estacion', (int)$record->id_estacion)
            ->where('status', 1)->orderBy('id')->get(['id', 'nombre', 'estado_r', 'unidad'])
            ->map(static fn($row) => [
                'id' => (int)$row->id, 'nombre' => (string)$row->nombre,
                'estado' => (string)$row->estado_r, 'existencia' => (float)$row->unidad,
            ])->all();
    }

    public static function files(int $id): ?array
    {
        if (!self::authorizedRecord($id)) return null;
        $links = static fn(string $name): string => '/download?tipo=orden-mantenimiento&file='.rawurlencode(basename($name));
        $causeLinks = static function (string $name): string {
            $name = basename($name);
            $specificPath = dirname(__DIR__, 2).'/'.self::CAUSE_UPLOAD_FOLDER.$name;
            $type = is_file($specificPath) ? 'orden-mantenimiento-causa' : 'orden-mantenimiento';
            return '/download?tipo='.$type.'&file='.rawurlencode($name);
        };
        return [
            'instalaciones' => PedidoMaterialesInstalacion::where('id_pedido', $id)->orderBy('id')->get()->map(static fn($file) => [
                'id' => (int)$file->id, 'archivo' => basename((string)$file->archivo),
                'url' => $links((string)$file->archivo),
                'preview_url' => $links((string)$file->archivo).'&view=1',
            ])->all(),
            'evidencias' => PedidoMaterialesEvidenciaArchivo::where('id_pedido', $id)->orderBy('id')->get()->map(static fn($file) => [
                'id' => (int)$file->id, 'area' => (string)$file->area, 'motivo' => (string)$file->motivo,
                'archivo' => basename((string)$file->archivo), 'url' => $links((string)$file->archivo),
            ])->all(),
            'causas' => PedidoMaterialesCausa::where('id_reporte', $id)->orderBy('id')->get()->map(static fn($cause) => [
                'id' => (int)$cause->id,
                'fecha_hora' => self::formatDateTimeLabel((string)$cause->getRawOriginal('fecha').' '.(string)$cause->getRawOriginal('hora')),
                'descripcion' => (string)$cause->descripcion, 'refaccion' => (string)$cause->localidad_refaccion,
                'precio' => number_format((float)$cause->precio, 2, '.', ','),
                'factura_pdf' => basename((string)$cause->factura_pdf), 'factura_xml' => basename((string)$cause->factura_xml),
                'pdf_url' => (string)$cause->factura_pdf !== '' ? $causeLinks((string)$cause->factura_pdf) : null,
                'xml_url' => (string)$cause->factura_xml !== '' ? $causeLinks((string)$cause->factura_xml) : null,
            ])->all(),
        ];
    }

    public static function storeInstallation(int $id, array $file): array
    {
        $record = self::authorizedRecord($id);
        if (!$record) return self::fail('Registro no encontrado.', 404);
        if (!in_array((int)$record->estatus, [2, 3], true)) return self::fail('La orden aún no está disponible para registrar instalación.', 409);
        $upload = self::saveUpload($file, ['pdf', 'jpg', 'jpeg', 'png', 'webp']);
        if (!$upload['success']) return $upload;
        try {
            DB::transaction(static function () use ($id, $upload): void {
                PedidoMaterialesInstalacion::create(['id_pedido' => $id, 'archivo' => $upload['file']]);
                PedidoMateriales::where('id', $id)->update(['estatus' => 3]);
            });
            self::notify($id, 'agregado evidencia de instalación');
            return ['success' => true, 'message' => 'Evidencia de instalación guardada.', 'code' => 201];
        } catch (\Throwable $e) {
            self::removeUpload($upload['file']);
            error_log('[OrdenMantenimiento] storeInstallation: '.$e->getMessage());
            return self::fail('No se pudo guardar la evidencia.', 500);
        }
    }

    public static function deleteInstallation(int $installationId): array
    {
        $installation = PedidoMaterialesInstalacion::find($installationId);
        $record = $installation ? self::authorizedRecord((int)$installation->id_pedido) : null;
        if (!$installation || !$record) return self::fail('Evidencia no encontrada.', 404);
        if (!in_array((int)$record->estatus, [2, 3], true)) return self::fail('La evidencia ya no se puede eliminar.', 409);

        $file = (string)$installation->archivo;
        $recordId = (int)$record->id;
        try {
            DB::transaction(static fn() => $installation->delete());
            self::removeUpload($file);
            self::notify($recordId, 'eliminado una evidencia de instalación');
            return ['success' => true, 'message' => 'Evidencia eliminada.', 'code' => 200];
        } catch (\Throwable $e) {
            error_log('[OrdenMantenimiento] deleteInstallation: '.$e->getMessage());
            return self::fail('No se pudo eliminar la evidencia.', 500);
        }
    }

    public static function storeEvidence(int $id, array $data, array $file): array
    {
        $record = self::authorizedRecord($id);
        if (!$record) return self::fail('Registro no encontrado.', 404);
        if (!in_array((int)$record->estatus, [0, 1], true)) return self::fail('La orden ya no permite agregar evidencias.', 409);
        $area = trim((string)($data['area'] ?? ''));
        $reason = trim((string)($data['motivo'] ?? ''));
        if ($area === '' || mb_strlen($area) > 255 || $reason === '' || mb_strlen($reason) > 1000) return self::fail('Completa área y motivo de la evidencia.', 422);
        $upload = self::saveUpload($file, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx']);
        if (!$upload['success']) return $upload;
        try {
            PedidoMaterialesEvidenciaArchivo::create(['id_pedido' => $id, 'area' => $area, 'motivo' => $reason, 'archivo' => $upload['file']]);
            return ['success' => true, 'message' => 'Evidencia agregada.', 'code' => 201];
        } catch (\Throwable $e) {
            self::removeUpload($upload['file']);
            error_log('[OrdenMantenimiento] storeEvidence: '.$e->getMessage());
            return self::fail('No se pudo guardar la evidencia.', 500);
        }
    }

    public static function deleteEvidence(int $evidenceId): array
    {
        $evidence = PedidoMaterialesEvidenciaArchivo::find($evidenceId);
        $record = $evidence ? self::authorizedRecord((int)$evidence->id_pedido) : null;
        if (!$evidence || !$record) return self::fail('Evidencia no encontrada.', 404);
        if (!in_array((int)$record->estatus, [0, 1], true)) return self::fail('La orden ya no permite eliminar evidencias.', 409);
        try {
            DB::table('op_pedido_materiales_evidencia_foto')->where('id_evidencia', $evidenceId)->delete();
            $evidence->delete();
            return ['success' => true, 'message' => 'Evidencia eliminada.', 'code' => 200];
        } catch (\Throwable $e) {
            error_log('[OrdenMantenimiento] deleteEvidence: '.$e->getMessage());
            return self::fail('No se pudo eliminar la evidencia.', 500);
        }
    }

    public static function addCause(int $id, array $data, array $pdf, array $xml): array
    {
        $record = self::authorizedRecord($id);
        if (!$record) return self::fail('Registro no encontrado.', 404);
        if (!in_array((int)$record->estatus, [2, 3], true)) return self::fail('La causa sólo puede registrarse después de la autorización.', 409);
        $description = trim((string)($data['descripcion'] ?? ''));
        $locality = trim((string)($data['refaccion'] ?? ''));
        $rawPrice = trim((string)($data['precio'] ?? ''));
        $price = $rawPrice === '' ? 0.0 : filter_var($rawPrice, FILTER_VALIDATE_FLOAT);
        if ($description === '' || $locality === '' || $price === false || $price < 0) return self::fail('Completa descripción, lugar de la refacción y un precio válido.', 422);
        $pdfResult = self::saveUpload($pdf, ['pdf'], true, self::CAUSE_UPLOAD_FOLDER);
        if (!$pdfResult['success']) return $pdfResult;
        $xmlResult = self::saveUpload($xml, ['xml'], true, self::CAUSE_UPLOAD_FOLDER);
        if (!$xmlResult['success']) { self::removeUpload($pdfResult['file'], self::CAUSE_UPLOAD_FOLDER); return $xmlResult; }
        try {
            PedidoMaterialesCausa::create([
                'id_reporte' => $id, 'fecha' => date('Y-m-d'), 'hora' => date('H:i:s'),
                'descripcion' => $description, 'localidad_refaccion' => $locality,
                'factura_pdf' => $pdfResult['file'], 'factura_xml' => $xmlResult['file'], 'precio' => $price,
            ]);
            return ['success' => true, 'message' => 'Causa registrada.', 'code' => 201];
        } catch (\Throwable $e) {
            self::removeUpload($pdfResult['file'], self::CAUSE_UPLOAD_FOLDER); self::removeUpload($xmlResult['file'], self::CAUSE_UPLOAD_FOLDER);
            error_log('[OrdenMantenimiento] addCause: '.$e->getMessage());
            return self::fail('No se pudo registrar la causa.', 500);
        }
    }

    public static function createSignatureToken(int $id, string $channel): array
    {
        $record = self::authorizedRecord($id);
        $userId = (int)(Session::get('usuario')['id'] ?? 0);
        $signType = self::expectedSignType($record, $userId);
        if (!$record || $signType === null) return self::fail('No te corresponde firmar esta orden.', 403);
        if (!in_array($channel, ['telegram', 'email'], true)) return self::fail('Canal de envío inválido.', 422);
        $token = (string)random_int(100000, 999999);
        try {
            DB::transaction(static function () use ($id, $userId, $token): void {
                PedidoMaterialesToken::where('id_pedido', $id)->where('id_usuario', $userId)->delete();
                PedidoMaterialesToken::create([
                    'id_pedido' => $id,
                    'id_usuario' => $userId,
                    'fecha_creacion' => date('Y-m-d H:i:s'),
                    'token' => (int)$token,
                ]);
            });
            $stage = $signType === 'B' ? 'firma de VO.BO.' : 'firma de autorización';
            $message = '📲 Usa el token <b>'.$token.'</b> para completar la '.$stage.' de la <b>Orden de Mantenimiento</b>:' . PHP_EOL . PHP_EOL .
                self::telegramOrderDetails(
                    $id,
                    (int)$record->id_estacion,
                    (int)$record->folio,
                    $record,
                    null,
                    (string)(Session::get('usuario')['nombre'] ?? 'Usuario')
                );
            if ($channel === 'telegram') {
                if (!(new TelegramService())->sendToken($userId, $message)) {
                    PedidoMaterialesToken::where('id_pedido', $id)->where('id_usuario', $userId)->delete();
                    return self::fail('No se pudo enviar el token por Telegram. Verifica que tu cuenta esté vinculada e inténtalo de nuevo.', 502);
                }
            } else {
                $email = (string)(Usuario::where('id', $userId)->value('email') ?? '');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !(new EmailService())->sendToken($email, $token, 'Orden de Mantenimiento (#'.$folio.')')) {
                    PedidoMaterialesToken::where('id_pedido', $id)->where('id_usuario', $userId)->delete();
                    return self::fail('No se pudo enviar el token al correo asociado a tu cuenta.', 502);
                }
            }
            return ['success' => true, 'message' => $channel === 'telegram' ? 'Token enviado por Telegram.' : 'Token enviado por correo electrónico.', 'code' => 200, 'tipo_firma' => $signType];
        } catch (\Throwable $e) {
            error_log('[OrdenMantenimiento] createSignatureToken: '.$e->getMessage());
            return self::fail('No se pudo generar el token.', 500);
        }
    }

    public static function signingType(int $id): ?string
    {
        $userId = (int)(Session::get('usuario')['id'] ?? 0);
        return self::expectedSignType(self::authorizedRecord($id), $userId);
    }

    /**
     * Firma pendientes visibles en el panel lateral para sus firmantes.
     * El alcance queda limitado a los contextos configurados para el módulo.
     * @return array<int, array<string, mixed>>
     */
    public static function pendingSignatureItems(int $userId): array
    {
        if (!in_array($userId, [19, 21], true) || !self::permissions()['leer']) {
            return [];
        }

        $contexts = self::contexts();
        if ($contexts === []) {
            return [];
        }

        $records = PedidoMateriales::query()
            ->where('estatus', 1)
            ->whereIn('id_estacion', array_keys($contexts))
            ->orderBy('fecha')
            ->get();
        if ($records->isEmpty()) {
            return [];
        }

        $ids = $records->pluck('id')->map(static fn($id) => (int)$id)->all();
        $firmas = PedidoMaterialesFirma::whereIn('id_pedido', $ids)
            ->get(['id_pedido', 'tipo_firma'])
            ->groupBy('id_pedido');
        $conceptos = PedidoMaterialesDetalle::whereIn('id_pedido', $ids)
            ->orderBy('id')
            ->get(['id_pedido', 'concepto'])
            ->groupBy('id_pedido');

        $items = [];
        foreach ($records as $record) {
            $tiposFirma = $firmas->get((int)$record->id, collect())->pluck('tipo_firma')->all();
            $pendiente = ($userId === 21 && !in_array('B', $tiposFirma, true))
                || ($userId === 19 && in_array('B', $tiposFirma, true) && !in_array('C', $tiposFirma, true));
            if (!$pendiente) {
                continue;
            }

            $detalle = $conceptos->get((int)$record->id, collect())
                ->pluck('concepto')
                ->map(static fn($concepto) => trim((string)$concepto))
                ->filter()
                ->implode(', ');
            $url = '/departamento-operativo/almacen/orden-mantenimiento-firma/'.(int)$record->id;

            $items[] = [
                'tipo' => 'OrdenMantenimiento',
                'tipo_label' => 'Orden de Mantenimiento',
                'id' => (int)$record->id,
                'fecha' => formatearFecha($record->getRawOriginal('fecha')),
                'concepto' => $detalle !== '' ? $detalle : 'Orden de Mantenimiento',
                'beneficiario' => null,
                'monto' => null,
                'url' => $url,
                'disponible' => true,
                'aviso' => null,
            ];
        }

        return $items;
    }

    public static function finalize(int $id, array $data): array
    {
        $record = self::authorizedRecord($id);
        if (!$record) return self::fail('Registro no encontrado.', 404);
        if ((int)$record->estatus !== 0) return self::fail('La orden ya se finalizó.', 409);
        $affectation = trim((string)($data['afectacion'] ?? ''));
        if ($affectation === '') return self::fail('Completa el campo de afectación antes de finalizar.', 422);
        $choiceOptions = [
            'tipo_servicio' => [1, 2, 3],
            'orden_trabajo' => [1, 2, 3],
            'orden_riesgo' => [1, 2],
        ];
        $choices = [];
        foreach ($choiceOptions as $field => $allowedValues) {
            $value = filter_var($data[$field] ?? null, FILTER_VALIDATE_INT);
            if ($value === false || !in_array($value, $allowedValues, true)) {
                return self::fail('Selecciona una opción válida en cada apartado antes de finalizar.', 422);
            }
            $choices[$field] = $value;
        }
        $png = self::decodeSignature((string)($data['firma'] ?? ''));
        if ($png === null) return self::fail('Agrega tu firma para finalizar.', 422);
        $fileName = bin2hex(random_bytes(16)).'.png';
        $directory = dirname(__DIR__, 2).'/'.self::UPLOAD_FOLDER;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) return self::fail('No está disponible la carpeta de firmas.', 500);
        if (file_put_contents($directory.$fileName, $png, LOCK_EX) === false) return self::fail('No se pudo guardar la firma.', 500);
        try {
            DB::transaction(static function () use ($id, $record, $fileName, $data, $affectation, $choices): void {
                $locked = PedidoMateriales::where('id', $id)->lockForUpdate()->first();
                if (!$locked || (int)$locked->estatus !== 0) throw new \RuntimeException('La orden cambió de estado.');
                $locked->afectacion = $affectation;
                $locked->comentarios = trim((string)($data['comentarios'] ?? $locked->comentarios));
                $locked->tipo_servicio = $choices['tipo_servicio'];
                $locked->orden_trabajo = $choices['orden_trabajo'];
                $locked->orden_riesgo = $choices['orden_riesgo'];
                $locked->estatus = 1;
                $locked->save();
                PedidoMaterialesFirma::create(['id_pedido' => $id, 'id_usuario' => (int)(Session::get('usuario')['id'] ?? 0), 'tipo_firma' => 'A', 'firma' => $fileName]);
            });
            self::notify($id, 'finalizado el registro; está pendiente el VO.BO', null, [21, 19]);
            self::notifySigner($record, 21, 'B', 'La Orden de Mantenimiento está lista para tu firma de VO.BO.');
            self::notifySigner($record, 19, 'C', 'El proceso de firmas comenzó. La autorización quedará disponible después del VO.BO.');
            return ['success' => true, 'message' => 'Orden finalizada y enviada a firma.', 'code' => 200];
        } catch (\Throwable $e) {
            self::removeUpload($fileName);
            error_log('[OrdenMantenimiento] finalize: '.$e->getMessage());
            return self::fail($e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo finalizar la orden.', $e instanceof \RuntimeException ? 409 : 500);
        }
    }

    public static function sign(int $id, string $token): array
    {
        $userId = (int)(Session::get('usuario')['id'] ?? 0);
        try {
            DB::transaction(static function () use ($id, $token, $userId): void {
                $record = PedidoMateriales::where('id', $id)->lockForUpdate()->first();
                $authorized = $record ? self::authorizedRecord($id) : null;
                $type = self::expectedSignType($authorized, $userId);
                if (!$record || !$authorized || $type === null) throw new \RuntimeException('No te corresponde firmar esta orden.');
                $validToken = PedidoMaterialesToken::where('id_pedido', $id)->where('id_usuario', $userId)->where('token', (int)$token)->lockForUpdate()->first();
                if (!$validToken) throw new \RuntimeException('El token no es válido. Solicita uno nuevo.');
                $firma = PedidoMaterialesFirma::create([
                    'id_pedido' => $id, 'id_usuario' => $userId,
                    'tipo_firma' => $type, 'firma' => 'Firma: '.bin2hex(random_bytes(64)).'.'.uniqid('', true),
                ]);
                if ($type === 'C') {
                    foreach (PedidoMaterialesDetalle::where('id_pedido', $id)->where('refaccion', '>', 0)->get() as $item) {
                        $stock = DB::table('op_refacciones')->where('id', (int)$item->refaccion)->lockForUpdate()->first();
                        if ($stock) {
                            $remaining = max(0, (float)$stock->unidad - (float)$item->cantidad);
                            DB::table('op_refacciones')->where('id', (int)$item->refaccion)->update(['unidad' => $remaining]);
                        }
                    }
                    $record->estatus = 2;
                    $record->save();
                }
                PedidoMaterialesToken::where('id', (int)$validToken->id)->delete();
            });
            self::notify($id, $userId === 21 ? 'firmado el VO.BO' : 'firmado la autorización', null, [21, 19]);
            if ($userId === 21) {
                $signedRecord = PedidoMateriales::find($id);
                if ($signedRecord) self::notifySigner($signedRecord, 19, 'C', 'El VO.BO fue registrado. Ya puedes realizar la firma de autorización.');
            }
            return ['success' => true, 'message' => 'Firma registrada.', 'code' => 200];
        } catch (\Throwable $e) {
            error_log('[OrdenMantenimiento] sign: '.$e->getMessage());
            return self::fail($e->getMessage() === 'El token no es válido. Solicita uno nuevo.' ? $e->getMessage() : 'No se pudo registrar la firma.', $e instanceof \RuntimeException ? 422 : 500);
        }
    }

    private static function expectedSignType(?PedidoMateriales $record, int $userId): ?string
    {
        if (!$record || (int)$record->estatus !== 1) return null;
        $signatures = PedidoMaterialesFirma::where('id_pedido', (int)$record->id)->pluck('tipo_firma')->all();
        if ($userId === 21 && !in_array('B', $signatures, true)) return 'B';
        if ($userId === 19 && in_array('B', $signatures, true) && !in_array('C', $signatures, true)) return 'C';
        return null;
    }

    private static function notifySigner(PedidoMateriales $record, int $userId, string $signType, string $notice): void
    {
        try {
            $stage = $signType === 'B' ? 'VO.BO.' : 'autorización';
            $message = '🔔 <b>Firma pendiente: '.$stage.'</b> en el apartado de <b>Orden de Mantenimiento</b>:' . PHP_EOL . PHP_EOL .
                self::escapeTelegram($notice) . PHP_EOL . PHP_EOL .
                self::telegramOrderDetails(
                    (int)$record->id,
                    (int)$record->id_estacion,
                    (int)$record->folio,
                    $record,
                    null,
                    (string)(Session::get('usuario')['nombre'] ?? 'Usuario')
                );
            (new TelegramService())->sendTokenAsync($userId, $message);
        } catch (\Throwable $e) {
            error_log('[OrdenMantenimiento] Firma Telegram: '.$e->getMessage());
        }
    }

    /** Return the correct Telegram label/icon for the record's authorized context. */
    private static function contextDescriptor(int $contextId): array
    {
        $departmentIds = array_map(
            'intval',
            array_column(ModuleStationService::getAvailableDepartments(self::MODULE_KEY), 'id')
        );

        return in_array($contextId, $departmentIds, true)
            ? ['label' => 'Departamento', 'icon' => '🏢']
            : ['label' => 'Estación', 'icon' => '⛽'];
    }

    private static function orderSubject(int $id): string
    {
        return PedidoMaterialesDetalle::where('id_pedido', $id)
            ->orderBy('id')
            ->pluck('concepto')
            ->map(static fn($concept): string => trim((string)($concept ?? '')))
            ->filter()
            ->implode(', ') ?: 'S/I';
    }

    private static function telegramOrderDetails(
        int $id,
        int $contextId,
        int $folio,
        ?PedidoMateriales $record = null,
        ?array $payload = null,
        string $responsible = 'Usuario'
    ): string {
        $date = (string)($payload['date'] ?? ($record?->getRawOriginal('fecha') ?? ''));
        $subject = trim((string)($payload['subject'] ?? ''));
        if ($subject === '') $subject = self::orderSubject($id);
        $context = self::contextDescriptor($contextId);
        $contextName = self::contexts()[$contextId] ?? 'S/I';

        return '📋 <b>Folio:</b> #'.str_pad((string)$folio, 3, '0', STR_PAD_LEFT).PHP_EOL .
            '📅 <b>Fecha:</b> '.self::escapeTelegram(self::formatDate($date)).PHP_EOL .
            '🧰 <b>Asunto:</b> '.self::escapeTelegram($subject !== '' ? $subject : 'S/I').PHP_EOL . PHP_EOL .
            '👤 <b>Responsable:</b> '.self::escapeTelegram($responsible !== '' ? $responsible : 'Usuario').PHP_EOL .
            $context['icon'].' <b>'.$context['label'].':</b> '.self::escapeTelegram($contextName);
    }

    private static function escapeTelegram(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function decodeSignature(string $encoded): ?string
    {
        if (!preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/', $encoded, $match)) return null;
        $decoded = base64_decode($match[1], true);
        return $decoded !== false && strlen($decoded) >= 100 && str_starts_with($decoded, "\x89PNG\r\n\x1a\n") ? $decoded : null;
    }

    public static function deleteCause(int $causeId): array
    {
        $cause = PedidoMaterialesCausa::find($causeId);
        $record = $cause ? self::authorizedRecord((int)$cause->id_reporte) : null;
        if (!$cause || !$record) return self::fail('Causa no encontrada.', 404);
        if (!in_array((int)$record->estatus, [2, 3], true)) return self::fail('La causa sólo puede eliminarse después de la autorización.', 409);
        $pdf = basename((string)$cause->factura_pdf);
        $xml = basename((string)$cause->factura_xml);
        try {
            $cause->delete();
            self::removeCauseUpload($pdf);
            self::removeCauseUpload($xml);
            return ['success' => true, 'message' => 'Causa eliminada.', 'code' => 200];
        } catch (\Throwable $e) {
            error_log('[OrdenMantenimiento] deleteCause: '.$e->getMessage());
            return self::fail('No se pudo eliminar la causa.', 500);
        }
    }

    private static function saveUpload(array $file, array $allowedExtensions, bool $optional = false, string $directoryRelative = self::UPLOAD_FOLDER): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE && $optional) return ['success' => true, 'file' => ''];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) return self::fail('Selecciona un archivo válido.', 422);
        $original = basename((string)($file['name'] ?? ''));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true) || (int)($file['size'] ?? 0) > 15 * 1024 * 1024) return self::fail('Tipo de archivo no permitido o el archivo supera 15 MB.', 422);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
        if (in_array($mime, ['text/html', 'application/x-php', 'text/x-php', 'application/x-executable', 'application/x-dosexec'], true)) return self::fail('El tipo de contenido del archivo no está permitido.', 422);
        $directory = dirname(__DIR__, 2).'/'.$directoryRelative;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) return self::fail('No está disponible la carpeta de archivos.', 500);
        $name = bin2hex(random_bytes(12)).'.'.$extension;
        if (!move_uploaded_file((string)$file['tmp_name'], $directory.$name)) return self::fail('No se pudo mover el archivo al almacenamiento.', 500);
        return ['success' => true, 'file' => $name, 'code' => 200];
    }

    private static function removeUpload(string $name, string $directoryRelative = self::UPLOAD_FOLDER): void
    {
        if ($name === '') return;
        $directory = realpath(dirname(__DIR__, 2).'/'.$directoryRelative);
        $path = $directory ? realpath($directory.DIRECTORY_SEPARATOR.basename($name)) : false;
        if ($directory && $path && str_starts_with($path, $directory.DIRECTORY_SEPARATOR) && is_file($path)) @unlink($path);
    }

    private static function removeCauseUpload(string $name): void
    {
        if ($name === '') return;
        $name = basename($name);
        $causePath = dirname(__DIR__, 2).'/'.self::CAUSE_UPLOAD_FOLDER.$name;
        $folder = is_file($causePath) ? self::CAUSE_UPLOAD_FOLDER : self::UPLOAD_FOLDER;
        self::removeUpload($name, $folder);
    }

    public static function comments(int $id): ?array
    {
        if (!self::authorizedRecord($id)) return null;
        $rows = PedidoMaterialesComentarios::where('id_pedido', $id)->orderBy('fecha_hora')->get();
        $names = Usuario::whereIn('id', $rows->pluck('id_usuario')->unique())->pluck('nombre', 'id');
        return $rows->map(static fn($row) => [
            'id' => (int)$row->id,
            'usuario' => (string)($names[$row->id_usuario] ?? 'Usuario'),
            'comentario' => (string)$row->comentario,
            'fecha' => self::formatDateTime((string)$row->fecha_hora),
            'propio' => (int)$row->id_usuario === (int)(Session::get('usuario')['id'] ?? 0),
        ])->all();
    }

    public static function destroy(int $id): array
    {
        $record = self::authorizedRecord($id);
        $permissions = self::permissions();
        if (!$record) return self::fail('Registro no encontrado.', 404);
        if (!$permissions['eliminar'] || ((int)$record->estatus !== 0 && $permissions['id_usuario'] !== 19)) {
            return self::fail('No tienes permiso para eliminar esta solicitud.', 403);
        }
        $notificationContext = [
            'station' => (int)$record->id_estacion,
            'folio' => (int)$record->folio,
            'date' => (string)$record->getRawOriginal('fecha'),
            'subject' => self::orderSubject($id),
        ];
        try {
            $result = DB::transaction(static function () use ($id): bool {
                PedidoMaterialesComentarios::where('id_pedido', $id)->delete();
                PedidoMaterialesFirma::where('id_pedido', $id)->delete();
                PedidoMaterialesInstalacion::where('id_pedido', $id)->delete();
                PedidoMaterialesDetalle::where('id_pedido', $id)->delete();
                $areaIds = PedidoMaterialesArea::where('id_pedido', $id)->pluck('id');
                PedidoMaterialesAreaOtros::whereIn('id_area', $areaIds)->delete();
                $evidenciaIds = PedidoMaterialesEvidenciaArchivo::where('id_pedido', $id)->pluck('id');
                DB::table('op_pedido_materiales_evidencia_foto')->whereIn('id_evidencia', $evidenciaIds)->delete();
                PedidoMaterialesArea::where('id_pedido', $id)->delete();
                PedidoMaterialesEvidenciaArchivo::where('id_pedido', $id)->delete();
                PedidoMaterialesCausa::where('id_reporte', $id)->delete();
                return (bool)PedidoMateriales::where('id', $id)->delete();
            });
            if (!$result) return self::fail('No se pudo eliminar la solicitud.', 409);
            self::notify($id, 'eliminado el registro', $notificationContext);
            return ['success' => true, 'message' => 'Solicitud eliminada.', 'code' => 200];
        } catch (\Throwable $e) {
            error_log('[OrdenMantenimiento] destroy: '.$e->getMessage());
            return self::fail('No se pudo eliminar la solicitud.', 500);
        }
    }

    private static function statusText(int $status, bool $installed, bool $cause): string
    {
        if ($status === 0) return 'Pendiente';
        if ($status === 1) return 'En proceso';
        if ($installed) return 'Refacción Instalada';
        if ($cause) return 'Orden cerrada';
        if (in_array($status, [2,3], true)) return 'Tienes 5 días hábiles para instalar la refacción';
        return '—';
    }

    private static function seedSubareas(PedidoMaterialesArea $area, int $contextId): void
    {
        $areaName = (string)$area->area;
        $items = [];
        $category = match ($areaName) {
            'Zona de despacho' => 1,
            'Zona de tanques' => 2,
            'Baños clientes' => 3,
            default => 0,
        };
        if ($category === 1) {
            $dispensarios = DB::table('tb_dispensarios')->where('id_estacion', $contextId)->orderBy('no_dispensario')->get(['no_dispensario']);
            foreach ($dispensarios as $dispensario) {
                foreach (['A', 'B'] as $side) $items[] = 'Dispensario: '.$dispensario->no_dispensario.', Lado: '.$side;
            }
        } elseif ($category === 2) {
            $tanques = DB::table('tb_tanque_almacenamiento')->where('id_estacion', $contextId)->orderBy('no_tanque')->get(['no_tanque', 'capacidad', 'producto']);
            foreach ($tanques as $tanque) $items[] = 'Tanque: '.$tanque->no_tanque.', '.$tanque->capacidad.', '.$tanque->producto;
        } elseif ($category === 3) {
            $items = ['Hombre', 'Mujer'];
        }
        foreach (array_unique($items) as $item) {
            PedidoMaterialesAreaOtros::firstOrCreate([
                'id_area' => (int)$area->id,
                'categoria' => $category,
                'sub_area' => $item,
            ], ['estatus' => 0]);
        }
    }

    private static function formatDate(string $date): string
    {
        $date = trim($date);
        if ($date === '' || str_starts_with($date, '0000-00-00')) return 'S/I';
        $date = self::normalizeDateInput($date);
        $formatted = function_exists('formatearFecha') ? formatearFecha($date) : date('d/m/Y', strtotime($date));
        return $formatted !== '' ? $formatted : 'S/I';
    }

    /** Normalize both MySQL ISO dates and legacy day-first strings before Carbon parses them. */
    private static function normalizeDateInput(string $date): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $match)) {
            return sprintf('%04d-%02d-%02d', (int)$match[1], (int)$match[2], (int)$match[3]);
        }
        if (preg_match('/^(\d{2})[-\/](\d{2})[-\/](\d{4})/', $date, $match)) {
            return sprintf('%04d-%02d-%02d', (int)$match[3], (int)$match[2], (int)$match[1]);
        }
        return $date;
    }

    private static function formatDateTime(string $date): string
    {
        if ($date === '') return 'S/I';
        return function_exists('formatearFecha') ? formatearFecha($date) : date('d/m/Y H:i', strtotime($date));
    }

    private static function formatDateTimeLabel(string $date): string
    {
        $timestamp = strtotime($date);
        if ($date === '' || $timestamp === false) return 'S/I';
        return self::formatDate($date).', '.date('g:i a', $timestamp);
    }

    private static function fail(string $message, int $code): array
    {
        return ['success' => false, 'message' => $message, 'code' => $code];
    }

    private static function notifyComment(int $id, string $comment): void
    {
        try {
            $record = PedidoMateriales::find($id);
            if (!$record) return;

            $stationId = (int)$record->id_estacion;
            $actor = (int)(Session::get('usuario')['id'] ?? 0);
            $actorName = (string)(Session::get('usuario')['nombre'] ?? 'Usuario');
            $context = self::contextDescriptor($stationId);
            $contextName = self::contexts()[$stationId] ?? 'S/I';
            $targets = (new TelegramService())->getUserIdsByStationWithComodines($stationId, $actor);
            if (in_array($stationId, [6, 7], true)) {
                $targets = array_merge($targets, (new TelegramService())->getUserIdsComercializadora($actor));
            }
            $targets = array_merge($targets, (new TelegramService())->getUserIdsMantenimiento($actor));

            $message = '💬 Se ha agregado un comentario al apartado de <b>Orden de Mantenimiento</b>:' . PHP_EOL . PHP_EOL
                . '📝 <b>Comentario:</b> ' . self::escapeTelegram($comment) . PHP_EOL
                . '👤 <b>Responsable:</b> ' . self::escapeTelegram($actorName) . PHP_EOL
                . $context['icon'] . ' <b>' . $context['label'] . ':</b> ' . self::escapeTelegram($contextName);

            foreach (array_unique($targets) as $target) {
                (new TelegramService())->sendTokenAsync((int)$target, $message);
            }
        } catch (\Throwable $e) {
            error_log('[OrdenMantenimiento] Telegram comment: ' . $e->getMessage());
        }
    }

    private static function notify(int $id, string $action, ?array $payload = null, array $excludeUsers = []): void
    {
        try {
            // Re-read after persistence/deletion so Telegram failures cannot undo the action.
            $record = $payload === null ? PedidoMateriales::find($id) : null;
            if ($payload === null && !$record) return;
            $stationId = (int)($payload['station'] ?? $record->id_estacion);
            $folio = (int)($payload['folio'] ?? $record->folio);
            $actor = (int)(Session::get('usuario')['id'] ?? 0);
            $actorName = (string)(Session::get('usuario')['nombre'] ?? 'Usuario');
            $targets = (new TelegramService())->getUserIdsByStationWithComodines($stationId, $actor);
            if (in_array($stationId, [6,7], true)) $targets = array_merge($targets, (new TelegramService())->getUserIdsComercializadora($actor));
            $targets = array_merge($targets, (new TelegramService())->getUserIdsMantenimiento($actor));
            $message = '🛠 Se ha ' . self::escapeTelegram($action) . ' en el apartado de <b>Orden de Mantenimiento</b>:' . PHP_EOL . PHP_EOL .
                self::telegramOrderDetails($id, $stationId, $folio, $record, $payload, $actorName);
            $targets = array_diff(array_unique($targets), array_map('intval', $excludeUsers));
            foreach ($targets as $target) (new TelegramService())->sendTokenAsync((int)$target, $message);
        } catch (\Throwable $e) {
            error_log('[OrdenMantenimiento] Telegram: '.$e->getMessage());
        }
    }
}
