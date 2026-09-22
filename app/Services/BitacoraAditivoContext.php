<?php

namespace App\Services;

use App\Core\Session;
use App\Models\Estacion;
use App\Services\ModuloService;
use App\Services\ModuloDptoOperativoService;
use App\Services\ModuleStationService;

class BitacoraAditivoContext
{
    public const MODULE_KEY = 'bitacora-aditivo';

    public const CONTEXTO_HOME = 'home';
    public const CONTEXTO_IMPORTACION = 'importacion';

    private array $data;

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function detectarContextoDeUrl(string $url = ''): string
    {
        $url = $url ?: (string)($_SERVER['REQUEST_URI'] ?? '');
        return str_starts_with($url, '/departamento-operativo')
            ? self::CONTEXTO_IMPORTACION
            : self::CONTEXTO_HOME;
    }

    public static function resolver(?string $contexto = null): self
    {
        $contexto = $contexto ?? self::detectarContextoDeUrl();

        $esHome = ($contexto === self::CONTEXTO_HOME);

        $ctx = ModuleStationService::getContext(self::MODULE_KEY);
        $estacionId = $ctx['id_estacion'];
        $estacionNombre = (string)($ctx['nombre'] ?? '');

        return new self([
            'contexto'        => $contexto,
            'layout'          => $esHome ? 'main' : 'departamento-operativo',
            'baseUrl'         => $esHome
                ? '/bitacora-aditivo'
                : '/departamento-operativo/importacion/bitacora-aditivo',
            'moduleKey'       => self::MODULE_KEY,
            'estacionId'      => $estacionId,
            'estacionNombre'  => $estacionNombre,
            'estaciones'      => ModuleStationService::getAvailableStations(self::MODULE_KEY),
            'capacidades'     => self::resolverCapacidades($contexto),
            'productos'       => self::resolverProductos($estacionId),
        ]);
    }

    public static function resolverCapacidades(string $contexto): array
    {
        if ($contexto === self::CONTEXTO_IMPORTACION) {
            return self::capacidadesImportacion();
        }
        return self::capacidadesHome();
    }

    private static function capacidadesHome(): array
    {
        $perm = ModuloService::permisosSesion(self::MODULE_KEY);

        return [
            'puedeVer'      => (bool)($perm['leer'] ?? false),
            'puedeCrear'    => (bool)($perm['crear'] ?? false),
            'puedeEditar'   => (bool)($perm['editar'] ?? false),
            'puedeEliminar' => (bool)($perm['eliminar'] ?? false),
            'puedeDescargar'=> (bool)($perm['descargar'] ?? false),
            'puedeVerResumen'  => (bool)($perm['leer'] ?? false),
            'puedeSeleccionarEstacion' => self::hayVariasEstaciones(),
        ];
    }

    private static function capacidadesImportacion(): array
    {
        $sessionUsuario = Session::get('usuario');
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);

        $allPerms = ModuloDptoOperativoService::getPermisos($idUsuario, 'importacion');
        $modulo = $allPerms['importacion'] ?? [];

        $submodulo = null;
        foreach ($modulo['submenus'] ?? [] as $sm) {
            if (($sm['clave'] ?? '') === self::MODULE_KEY) {
                $submodulo = $sm;
                break;
            }
        }

        $accede = $submodulo !== null;

        $leer      = (bool)($modulo['leer'] ?? false);
        $crear     = (bool)($modulo['crear'] ?? false);
        $editar    = (bool)($modulo['editar'] ?? false);
        $eliminar  = (bool)($modulo['eliminar'] ?? false);
        $descargar = (bool)($modulo['descargar'] ?? false);

        return [
            'puedeVer'      => $accede && $leer,
            'puedeCrear'    => $accede && $crear,
            'puedeEditar'   => $accede && $editar,
            'puedeEliminar' => $accede && $eliminar,
            'puedeDescargar'=> $accede && $descargar,
            'puedeVerResumen'  => $accede && $leer,
            'puedeSeleccionarEstacion' => self::hayVariasEstaciones(),
        ];
    }

    private static function hayVariasEstaciones(): bool
    {
        $estaciones = ModuleStationService::getAvailableStations(self::MODULE_KEY);
        return count($estaciones) > 1;
    }

    public static function resolverProductos(?int $estacionId): array
    {
        if (!$estacionId) {
            return [
                'producto_uno'  => '',
                'producto_dos'  => '',
                'producto_tres' => '',
                'tieneDiesel'   => false,
            ];
        }

        $estacion = Estacion::find($estacionId);

        if (!$estacion) {
            return [
                'producto_uno'  => '',
                'producto_dos'  => '',
                'producto_tres' => '',
                'tieneDiesel'   => false,
            ];
        }

        $productoTres = (string)($estacion->producto_tres ?? '');

        return [
            'producto_uno'  => (string)($estacion->producto_uno ?? ''),
            'producto_dos'  => (string)($estacion->producto_dos ?? ''),
            'producto_tres' => $productoTres,
            'tieneDiesel'   => $productoTres !== '',
        ];
    }

    public function breadcrumbs(string $titulo, bool $esHijo = false): array
    {
        if ($this->getContexto() === self::CONTEXTO_IMPORTACION) {
            $trail = [
                ['Home', '/home'],
                ['Dirección de Operaciones', '/departamento-operativo'],
                ['Importación', '/departamento-operativo/importacion'],
                ['Bitácora de aditivo', $esHijo ? $this->getBaseUrl() : ''],
            ];
            if ($esHijo) {
                array_push($trail, [$titulo, '']);
            }
            return $trail;
        }

        $trail = [
            ['Home', '/home'],
            ['Bitácora de aditivo', $esHijo ? $this->getBaseUrl() : ''],
        ];
        if ($esHijo) {
            array_push($trail, [$titulo, '']);
        }
        return $trail;
    }

    public function getContexto(): string
    {
        return $this->data['contexto'];
    }

    public function getLayout(): string
    {
        return $this->data['layout'];
    }

    public function getBaseUrl(): string
    {
        return $this->data['baseUrl'];
    }

    public function getModuleKey(): string
    {
        return $this->data['moduleKey'];
    }

    public function getEstacionId(): ?int
    {
        return $this->data['estacionId'];
    }

    public function getEstacionNombre(): string
    {
        return $this->data['estacionNombre'];
    }

    public function getEstaciones(): array
    {
        return $this->data['estaciones'];
    }

    public function getCapacidades(): array
    {
        return $this->data['capacidades'];
    }

    public function getProductos(): array
    {
        return $this->data['productos'];
    }
}