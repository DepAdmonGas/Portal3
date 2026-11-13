<?php

namespace App\Services;

use App\Core\Auth;
use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPersonalAsistencia;
use App\Models\Operativo\RhPersonalAsistenciaIncidencia;
use App\Models\Operativo\RhPersonalBajaArchivos;

/** Resolves and authorizes sensitive downloads as domain resources, not paths. */
class SensitiveDownloadAuthorizationService
{
    private const PERSONAL_DOCUMENTS = [
        'docs-personal-requisicion' => ['requisicion', 'requisicion'],
        'docs-personal-curriculum' => ['curriculum', 'curriculum'],
        'docs-personal-ine' => ['ine', 'ine'],
        'docs-personal-acta-nacimiento' => ['acta_nacimiento', 'acta_nacimiento'],
        'docs-personal-c-domicilio' => ['c_domicilio', 'comprobante_domicilio'],
        'docs-personal-nss' => ['nss', 'nss'],
        'docs-personal-c-estudios' => ['c_estudios', 'comprobante_estudios'],
        'docs-personal-c-recomendacion' => ['c_recomendacion', 'cartas_recomendacion'],
        'docs-personal-curp' => ['curp', 'curp'],
        'docs-personal-a-infonavit' => ['a_infonavit', 'acta_infonavit'],
        'docs-personal-rfc' => ['rfc', 'rfc'],
        'docs-personal-c-antecedentes' => ['c_antecedentes', 'carta_antecedentes'],
        'docs-personal-contrato' => ['contrato', 'contrato'],
        'docs-personal-documentos' => ['documentos', ''],
    ];

    /** @return array{path:string,base:string}|null */
    public static function resolve(string $tipo, string $file): ?array
    {
        if ($tipo === 'docs-personal-incidencias') {
            if (!Auth::check() || basename($file) !== $file
                || !ModuloDptoOperativoService::validaPermiso('recursos-humanos', 'descargar')) return null;
            $permissions = ControlDocumentosPersonalService::getPermisos();
            if (empty($permissions['puedeLeer']) || empty($permissions['puedeDescargar'])) return null;

            $incidencia = RhPersonalAsistenciaIncidencia::where('documento', $file)
                ->where('estado', 1)
                ->first();
            if (!$incidencia) return null;
            $asistencia = RhPersonalAsistencia::find((int)$incidencia->id_asistencia);
            $personal = $asistencia ? RhPersonal::find((int)$asistencia->id_personal) : null;
            if (!$personal || !self::isAuthorizedForPersonal((int)$personal->id_estacion)) return null;

            $base = dirname(__DIR__, 2) . '/public/uploads/archivos/incidencias/';
            $previousPortal3Base = dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/incidencias/';
            if (!is_file($base . $file) && is_file($previousPortal3Base . $file)) $base = $previousPortal3Base;
            return ['path' => $base . $file, 'base' => $base];
        }

        if ($tipo === 'docs-personal-baja') {
            if (!Auth::check() || basename($file) !== $file
                || !ModuloDptoOperativoService::validaPermiso('recursos-humanos', 'descargar')) return null;
            $permissions = ControlDocumentosPersonalService::getPermisos();
            if (empty($permissions['puedeLeer']) || empty($permissions['puedeDescargar'])) return null;

            $archivo = RhPersonalBajaArchivos::where('archivo', $file)->first();
            if (!$archivo) return null;
            $detalle = ControlDocumentosPersonalService::getBajaDetalle((int)$archivo->id_baja);
            if (!$detalle || !self::isAuthorizedForPersonal((int)$detalle['id_estacion'])) return null;

            $base = dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/solicitud-baja/';
            return ['path' => $base . basename($file), 'base' => $base];
        }
        if (!Auth::check() || !isset(self::PERSONAL_DOCUMENTS[$tipo])) return null;

        [$column, $folder] = self::PERSONAL_DOCUMENTS[$tipo];
        $resource = RhPersonal::where($column, $file)->first();
        if (!$resource || !self::isAuthorizedForPersonal((int) $resource->id_estacion)) return null;

        $relativeFolder = '/documentos-personal/' . ($folder === '' ? '' : $folder . '/');
        $base = self::uploadRoot() . $relativeFolder;
        $filename = basename($file);
        if (!is_file($base . $filename)) {
            $privateBase = self::privateUploadRoot() . $relativeFolder;
            if (is_file($privateBase . $filename)) $base = $privateBase;
        }
        return ['path' => $base . $filename, 'base' => $base];
    }

    private static function isAuthorizedForPersonal(int $idEstacion): bool
    {
        if (!ModuloDptoOperativoService::validaPermiso('recursos-humanos', 'descargar')) return false;

        $allowedStations = ControlDocumentosPersonalService::getAllowedEstacionIds();
        return !empty($allowedStations) && in_array($idEstacion, $allowedStations, true);
    }

    private static function uploadRoot(): string
    {
        $testRoot = getenv('P0_TEST_DOWNLOAD_ROOT');
        if (is_string($testRoot) && str_starts_with($testRoot, '/tmp/portal3-p0-download-')) return $testRoot;

        return dirname(__DIR__, 2) . '/public/uploads/archivos';
    }

    private static function privateUploadRoot(): string
    {
        return dirname(__DIR__, 2) . '/storage/private';
    }
}
