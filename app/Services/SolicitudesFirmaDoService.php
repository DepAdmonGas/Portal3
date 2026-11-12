<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Solicitudes pendientes de firma del Departamento Operativo.
 *
 * Migra el contador y el listado del botón flotante del sistema anterior
 * (public/admin/vistas/index.php + modal-firmas.php) sin reimplementar el
 * flujo de firma: cada solicitud enlaza a la pantalla de firma que ya existe
 * en Portal3.
 *
 * Reglas heredadas que se conservan:
 *   - Sólo los usuarios 2 y 19 pueden consultar.
 *   - Cheque: sólo el mes en curso (las firmas de periodos anteriores ya no
 *     aplican).
 *   - Resto de solicitudes: mes actual y anterior (en enero: enero y
 *     diciembre), como en el sistema anterior.
 *   - Cheque se filtra por id_year/id_mes; el resto por fecha.
 *   - Los estatus dependen del usuario firmante:
 *       Cheque        usuario 2 => 1, usuario 19 => 0
 *       Formatos      usuario 2 => 2, usuario 19 => 1
 *       Pinturas, Papelería, Limpieza, Aditivo
 *                     usuario 19 => 1, usuario 2 => 4
 *
 * Orden de Mantenimiento se omite: su módulo todavía no está migrado a
 * Portal3 y no debe aparecer como una opción huérfana.
 */
class SolicitudesFirmaDoService
{
    /** Usuarios autorizados (id real de tb_usuarios). */
    public const USUARIOS = [2, 19];

    public const TIPO_GENERAL = 'General';

    /** Nombres de formato de Recursos Humanos (op_rh_formatos.formato). */
    private const FORMATOS = [
        1 => 'Alta de personal',
        2 => 'Baja de personal',
        3 => 'Falta de personal',
        4 => 'Reestructuración de personal',
        5 => 'Ajuste salarial',
        6 => 'Formato de Vacaciones',
        7 => 'Solicitud Prima Vacacional',
    ];

    /** Etiquetas del selector, en el orden del sistema anterior. */
    private const ETIQUETAS = [
        'Cheque' => 'Cheques',
        'Pinturas' => 'Pedido de Pinturas',
        'Papeleria' => 'Pedido de Papelería',
        'Limpieza' => 'Pedido de Artículos de Limpieza',
        'Aditivo' => 'Pedido de Aditivo',
        'Formatos' => 'Formatos',
    ];

    /** Rutas de firma ya existentes en Portal3 (grupo Departamento Operativo). */
    private const RUTAS_FIRMA = [
        'Cheque' => '/departamento-operativo/solicitud-cheque-firmar/',
        'Formatos' => '/departamento-operativo/recursos-humanos/formatos/firmar/',
        'Pinturas' => '/departamento-operativo/comercializadora/pedido-pinturas-firma/',
        'Papeleria' => '/departamento-operativo/comercializadora/pedido-papeleria-firma/',
        'Limpieza' => '/departamento-operativo/comercializadora/pedido-articulos-limpieza-firma/',
        'Aditivo' => '/departamento-operativo/comercializadora/pedido-aditivo-firma/',
    ];

    /** @var array<int, array<int, array<string, mixed>>> */
    private static array $cache = [];

    /* ------------------------------------------------------------------ */
    /* Permisos                                                            */
    /* ------------------------------------------------------------------ */

    public static function autorizado(?int $idUsuario = null): bool
    {
        if ($idUsuario === null) {
            $usuario = Auth::user();
            $idUsuario = $usuario !== null ? (int) ($usuario->id ?? 0) : 0;
        }

        return in_array((int) $idUsuario, self::USUARIOS, true);
    }

    public static function contarParaUsuarioActual(): int
    {
        $usuario = Auth::user();

        if ($usuario === null) {
            return 0;
        }

        $idUsuario = (int) ($usuario->id ?? 0);

        if (!self::autorizado($idUsuario)) {
            return 0;
        }

        return self::contar($idUsuario);
    }

    /* ------------------------------------------------------------------ */
    /* Consulta                                                            */
    /* ------------------------------------------------------------------ */

    public static function contar(int $idUsuario): int
    {
        return count(self::pendientes($idUsuario));
    }

    /**
     * Pendientes del usuario, opcionalmente filtrados por tipo.
     *
     * @return array{total: int, items: array<int, array<string, mixed>>}
     */
    public static function listar(int $idUsuario, string $tipo = self::TIPO_GENERAL): array
    {
        $todos = self::pendientes($idUsuario);

        $items = $tipo === self::TIPO_GENERAL
            ? $todos
            : array_values(array_filter($todos, fn ($item) => $item['tipo'] === $tipo));

        return [
            'total' => count($todos),
            'items' => $items,
        ];
    }

    /**
     * Opciones del selector de tipo, según el usuario (igual que el legacy).
     *
     * @return array<int, array{valor: string, etiqueta: string}>
     */
    public static function tipos(int $idUsuario): array
    {
        if (!self::autorizado($idUsuario)) {
            return [];
        }

        $opciones = [
            ['valor' => self::TIPO_GENERAL, 'etiqueta' => 'Todas las solicitudes y pedidos...'],
            ['valor' => 'Cheque', 'etiqueta' => self::ETIQUETAS['Cheque']],
        ];

        if ((int) $idUsuario === 19) {
            foreach (['Pinturas', 'Papeleria', 'Limpieza', 'Aditivo'] as $tipo) {
                $opciones[] = ['valor' => $tipo, 'etiqueta' => self::ETIQUETAS[$tipo]];
            }
        }

        $opciones[] = ['valor' => 'Formatos', 'etiqueta' => self::ETIQUETAS['Formatos']];

        return $opciones;
    }

    public static function tipoValido(int $idUsuario, string $tipo): bool
    {
        foreach (self::tipos($idUsuario) as $opcion) {
            if ($opcion['valor'] === $tipo) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filas pendientes ya normalizadas para el listado.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function pendientes(int $idUsuario): array
    {
        if (!self::autorizado($idUsuario)) {
            return [];
        }

        if (isset(self::$cache[$idUsuario])) {
            return self::$cache[$idUsuario];
        }

        $filas = self::consultar($idUsuario);

        self::$cache[$idUsuario] = array_map(
            fn ($fila) => self::normalizar($fila),
            $filas
        );

        return self::$cache[$idUsuario];
    }

    /**
     * UNION de las tablas con las condiciones heredadas.
     *
     * @return array<int, object>
     */
    private static function consultar(int $idUsuario): array
    {
        $anio = (int) date('Y');
        $mes = (int) date('n');

        $esUsuario2 = (int) $idUsuario === 2;

        $condCheque = $esUsuario2 ? 1 : 0;
        $condFormatos = $esUsuario2 ? 2 : 1;
        $condOtros = (int) $idUsuario === 19 ? 1 : 4;

        $desde = date('Y-m-01', strtotime('first day of previous month'));
        $hasta = date('Y-m-01', strtotime('first day of next month'));

        $rangoFecha = 'fecha >= ? AND fecha < ?';

        $sql = "
            SELECT 'Cheque' AS tipo, id, fecha, beneficiario, monto, concepto, NULL AS formato, NULL AS comentarios
            FROM op_solicitud_cheque
            WHERE status = ? AND id_year = ? AND id_mes = ?

            UNION ALL

            SELECT 'Formatos', id, fecha, NULL, 0, NULL, formato, NULL
            FROM op_rh_formatos
            WHERE status = ? AND {$rangoFecha}

            UNION ALL

            SELECT 'Pinturas', id, fecha, NULL, 0, NULL, NULL, observaciones
            FROM op_pedido_pinturas_complementos
            WHERE status = ? AND {$rangoFecha}

            UNION ALL

            SELECT 'Papeleria', id, fecha, NULL, 0, NULL, NULL, NULL
            FROM op_pedido_papeleria
            WHERE status = ? AND {$rangoFecha}

            UNION ALL

            SELECT 'Limpieza', id, fecha, NULL, 0, NULL, NULL, NULL
            FROM op_pedido_limpieza
            WHERE status = ? AND {$rangoFecha}

            UNION ALL

            SELECT 'Aditivo', id, fecha, NULL, 0, NULL, NULL, comentarios
            FROM op_solicitud_aditivo
            WHERE status = ? AND {$rangoFecha}

            ORDER BY fecha ASC, tipo ASC, id ASC
        ";

        $bindings = [
            $condCheque, $anio, $mes,
            $condFormatos, $desde, $hasta,
            $condOtros, $desde, $hasta,
            $condOtros, $desde, $hasta,
            $condOtros, $desde, $hasta,
            $condOtros, $desde, $hasta,
        ];

        return Capsule::select($sql, $bindings);
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizar(object $fila): array
    {
        $tipo = (string) $fila->tipo;
        $id = (int) $fila->id;

        $url = self::urlFirma($tipo, $id);
        $disponible = $url !== null && self::puedeAbrir($tipo, $id);

        return [
            'tipo' => $tipo,
            'id' => $id,
            'fecha' => formatearFecha($fila->fecha ?? null),
            'concepto' => self::concepto($tipo, $fila),
            'beneficiario' => $tipo === 'Cheque' ? (string) ($fila->beneficiario ?? '') : null,
            'monto' => $tipo === 'Cheque' ? number_format((float) ($fila->monto ?? 0), 2) : null,
            'url' => $url,
            'disponible' => $disponible,
            'aviso' => $disponible ? null : self::aviso($tipo),
        ];
    }

    private static function concepto(string $tipo, object $fila): string
    {
        if ($tipo === 'Cheque') {
            return (string) ($fila->concepto ?? '');
        }

        if ($tipo === 'Pinturas' || $tipo === 'Aditivo') {
            $comentario = trim((string) ($fila->comentarios ?? ''));

            return $comentario !== '' ? $comentario : 'Solicitud ' . $tipo;
        }

        if ($tipo === 'Formatos') {
            return self::FORMATOS[(int) ($fila->formato ?? 0)] ?? 'Solicitud Formatos';
        }

        return 'Solicitud ' . $tipo;
    }

    /**
     * Verifica que el destino de firma sea accesible para la sesión actual.
     * Algunas solicitudes de cheque quedan fuera del alcance de
     * estación/departamento configurado en el módulo de Portal3.
     */
    private static function puedeAbrir(string $tipo, int $id): bool
    {
        if (!isset(self::RUTAS_FIRMA[$tipo])) {
            return false;
        }

        if ($tipo === 'Cheque') {
            try {
                return SolicitudChequeService::findAuthorizedSolicitudCheque($id) !== null
                    || SolicitudChequeService::puedeFirmarPendiente($id);
            } catch (\Throwable $e) {
                // Sin contexto de sesión no se puede verificar el alcance:
                // se evita enviar al usuario a una pantalla 404.
                return false;
            }
        }

        return true;
    }

    private static function aviso(string $tipo): string
    {
        if ($tipo === 'Cheque') {
            return 'No tienes acceso a esta solicitud desde Portal3.';
        }

        return 'Esta solicitud no está disponible en Portal3.';
    }

    private static function urlFirma(string $tipo, int $id): ?string
    {
        if (!isset(self::RUTAS_FIRMA[$tipo])) {
            return null;
        }

        return self::RUTAS_FIRMA[$tipo] . $id;
    }
}
