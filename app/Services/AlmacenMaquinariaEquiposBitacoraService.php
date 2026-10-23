<?php

namespace App\Services;

use App\Core\Session;
use App\Models\Operativo\MaquinariaEquipo;
use App\Models\Operativo\MaquinariaMantenimiento;
use App\Models\Operativo\MaquinariaMantenimientoActividad;
use App\Models\Operativo\MaquinariaMantenimientoComentario;
use App\Models\Operativo\MaquinariaMantenimientoEvidencia;
use App\Models\Operativo\MaquinariaMantenimientoFirma;
use App\Models\Operativo\MaquinariaMantenimientoOcurrencia;
use App\Models\Operativo\MaquinariaMantenimientoToken;
use App\Models\Usuario;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Bitácora de Maquinaria y Equipos (Almacén)
 *
 * Port del modelo legacy `departamento-operativo/app/modelo/4-almacen/MaquinariaMantenimiento.php`
 * y de su controlador (`controladorMaquinariaMantenimiento.php`) sobre las tablas
 * op_maquinaria_mantenimiento / _ocurrencia / _actividad / _firma / _token /
 * _evidencia / _comentario.
 *
 * Decisiones de paridad con el legacy:
 *  - Mismas constantes, checklists fijos y textos EXACTOS (incluidos typos).
 *  - Ocurrencias generadas: Diario=7, Semanal=4, Horas=1 por bloque (50/100/300/500
 *    con fecha = inicio + floor(horas/8) días), Mensual(Planta)=1. Correctivo u
 *    otra maquinaria: 1 sola ocurrencia.
 *  - `frecuencia` solo se guarda cuando el equipo es Hidrolavadora o Planta de
 *    emergencia + Preventivo; en otro caso queda NULL y se muestra "N/A".
 *  - Firmas: A (elaborador, dibujo) → estatus 0; B (puesto 13 dibuja | usuario 19
 *    token) → estatus 1; C (usuario 21 token) → estatus 2. Secuencia obligatoria.
 *  - Eliminar mantenimiento: el legacy borraba ocurrencias ANTES de leer sus ids
 *    (firmas/token huérfanas); aquí se corrige borrando por id_mantenimiento
 *    dentro de una transacción (misma intención, sin residuos).
 *  - `completarOcurrencia` pone estatus=1 (En proceso), igual que el legacy.
 *  - Id de usuario/puesto SIEMPRE viene de la sesión: nunca del request.
 */
class AlmacenMaquinariaEquiposBitacoraService
{
    /* ------------------------------------------------------------------ */
    /* Constantes (paridad exacta con el legacy)                           */
    /* ------------------------------------------------------------------ */

    public const EST_PENDIENTE   = 0;
    public const EST_EN_PROCESO  = 1;
    public const EST_FINALIZADO  = 2;

    public const TIPO_PREVENTIVO = 1;
    public const TIPO_CORRECTIVO = 2;

    public const FREC_DIARIO     = 1;
    public const FREC_SEMANAL    = 2;
    public const FREC_HORAS      = 3;
    public const FREC_MENSUAL    = 4;

    public const MAQUINARIA_HIDROLAVADORA     = 'Hidrolavadora';
    public const MAQUINARIA_PLANTA_EMERGENCIA = 'Planta de emergencia';

    /** Horas de jornada laboral para convertir "por horas" a fechas. */
    public const HORAS_POR_DIA = 8;

    public const DOWNLOAD_TIPO   = 'maquinaria-mantenimiento';
    public const UPLOAD_FOLDER   = 'public/uploads/archivos/maquinaria-mantenimiento/';
    public const FIRMAS_FOLDER   = 'public/uploads/firmas/maquinaria-mantenimiento/';

    public const ESTADO_ACTUAL_OPCIONES = ['En operación', 'Fuera de servicio', 'En Reparación'];

    public const TIPO_LABELS   = [self::TIPO_PREVENTIVO => 'Preventivo', self::TIPO_CORRECTIVO => 'Correctivo'];
    public const FREC_LABELS   = [self::FREC_DIARIO => 'Diario', self::FREC_SEMANAL => 'Semanal', self::FREC_HORAS => 'Por horas', self::FREC_MENSUAL => 'Mensual'];
    public const ESTATUS_LABELS = [self::EST_PENDIENTE => 'Pendiente', self::EST_EN_PROCESO => 'En proceso', self::EST_FINALIZADO => 'Finalizado'];
    public const ESTATUS_CLASES  = [self::EST_PENDIENTE => 'estado-pendiente', self::EST_EN_PROCESO => 'estado-atrasado', self::EST_FINALIZADO => 'estado-finalizado'];

    /** Color de fila por etapa del flujo de firmas (igual que solicitud de cheques). */
    public const FILA_COLORES = [self::EST_PENDIENTE => '#ffb6af', self::EST_EN_PROCESO => '#fcfcda', self::EST_FINALIZADO => '#b0f2c2'];

    /** Usuarios fijos del flujo de firmas. */
    public const USUARIO_VOBO          = 19; // firma el VoBo con token
    public const USUARIO_AUTORIZACION  = 21; // firma la autorización con token
    public const PUESTO_VOBO           = 13; // firma el VoBo dibujando

    public const EXTENSIONES_EVIDENCIA = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

    public const MIME_PELIGROSOS = [
        'application/x-php',
        'text/x-php',
        'application/x-executable',
        'application/x-dosexec',
        'application/x-msdownload',
        'text/html',
        'image/svg+xml',
        'application/x-sh',
        'application/javascript',
        'text/x-javascript',
        'text/javascript',
    ];

    /* ------------------------------------------------------------------ */
    /* Checklists fijos (textos EXACTOS del legacy)                        */
    /* ------------------------------------------------------------------ */

    /**
     * Reconoce "Planta de emergencia" sin importar la variante del catálogo
     * ('Planta de Emergencia', 'planta de mergencia' …): normaliza a minúsculas,
     * colapsa espacios y corrige el typo "mergencia" como palabra completa.
     */
    public static function esPlantaEmergencia(string $maquinaria): bool
    {
        $normalizada = strtolower(preg_replace('/\s+/', ' ', trim($maquinaria)));
        $normalizada = preg_replace('/\bmergencia\b/', 'emergencia', $normalizada);

        return ($normalizada === 'planta de emergencia');
    }

    /**
     * ¿Aplica la generación de ocurrencias por frecuencia?
     * (Preventivo + Hidrolavadora/Planta de emergencia).
     */
    public static function usaFrecuencia(string $maquinaria, int $tipoMantenimiento): bool
    {
        if ($tipoMantenimiento !== self::TIPO_PREVENTIVO) {
            return false;
        }

        $maquinaria = trim($maquinaria);

        return ($maquinaria === self::MAQUINARIA_HIDROLAVADORA || self::esPlantaEmergencia($maquinaria));
    }

    /**
     * FrecuenciasOfrecidas en el combo "Nuevo mantenimiento", con la misma regla
     * que valida crearMantenimiento() (y que el legacy):
     *   - Hidrolavadora        → Diario, Semanal, Por horas (sin Mensual)
     *   - Planta de emergencia → Diario, Semanal, Mensual   (sin Por horas)
     *   - Resto de maquinaria  → ninguna (no genera checklist)
     * Devuelve [{valor,label}] listo para pintar el <select>.
     */
    public static function frecuenciasDisponibles(string $maquinaria, int $tipoMantenimiento = self::TIPO_PREVENTIVO): array
    {
        if (!self::usaFrecuencia($maquinaria, $tipoMantenimiento)) {
            return [];
        }

        $valores = [self::FREC_DIARIO, self::FREC_SEMANAL];

        $valores[] = self::esPlantaEmergencia(trim($maquinaria))
            ? self::FREC_MENSUAL
            : self::FREC_HORAS;

        $opciones = [];

        foreach ($valores as $v) {
            $opciones[] = [
                'valor' => $v,
                'label' => self::FREC_LABELS[$v],
            ];
        }

        return $opciones;
    }

    public static function checklistDiario(): array
    {
        return [
            'Inspeccionar el filtro de ingreso de agua y limpiar si es necesario.',
            'Checar el nivel apropiado de aceite y su consistencia, cambias si esta contaminado',
            'Checar que no haya fugas de aceite en el carter o flecha.',
            'Checar que no haya fugas de agua en el manifold o conexiones',
        ];
    }

    public static function checklistSemanal(): array
    {
        return [
            'Checar el acoplamiento del motor a la bomba, si son poleas y bandas, debera tener la tension y alineamiento adecuados, si es cople no debera estar desgastado o con juego; si es acoplamiento directo a flecha hueca debera estar bien apretado',
            'Checar la valvula reguladora y presostato para una apropiada operacion en modo presion y en modo bypass',
            'Checar todos los conectores, tornillos y tuerca, todos estos elementos deberan estar firmemente apretados',
        ];
    }

    public static function checklistHoras(): array
    {
        return [
            ['bloque' => '50',  'descripcion' => 'Para todas las bombas, cambiar el aceite a las primeras 50 horas de trabajo la primera vez', 'tipo_campo' => 'select'],
            ['bloque' => '100', 'descripcion' => 'Cambiar los empaques y retenes cada 100 horas en bombas domesticas', 'tipo_campo' => 'select'],
            ['bloque' => '300', 'descripcion' => 'Cambiar el aceite cada 300 horas o cada mes, lo que suceda primero', 'tipo_campo' => 'select'],
            ['bloque' => '300', 'descripcion' => 'Cambiar los empaques y retenes cada 300 horas en bombas comerciales', 'tipo_campo' => 'select'],
            ['bloque' => '500', 'descripcion' => 'Cambiar los empaques y retenes cada 500 horas en bombas industriales', 'tipo_campo' => 'texto'],
        ];
    }

    public static function checklistPlantaDiario(): array
    {
        return [
            'Nivel de aceite del motor',
            'Nivel de anticongelante',
            'Nivel de Diesel en el tanque de combustible',
            'Revisión visual de fugas (aceite, combustible, agua)',
            'Estado de batería (voltaje / carga / conexiones)',
            'Sello del tapón del radiado',
            'Falso contacto en todas las conexiones eléctricas (motor, generador, tablero de transferencia)',
            'Indicadores del tablero sin alarmas activas',
            'Área limpia y sin obstrucciones',
        ];
    }

    public static function checklistPlantaSemanal(): array
    {
        return [
            'Prueba de arranque en vacío o con carga',
            'Revisión de tensión de bandas',
            'Limpieza de filtro de aire (revisión / soplado si aplica)',
            'Revisión de conexiones eléctricas',
            'Verificación de sistema de enfriamiento (radiador limpio)',
            'Estado de las mangueras de Diesel del motor y tanque de combustible',
            'Limpieza general del equipo',
        ];
    }

    /**
     * Mensual de Planta: 28 actividades (checkbox) + 7 filas de sección
     * ('titulo'/'subtitulo'/'nota') que NO son actividades ni checkboxes.
     * Los textos se conservan EXACTOS (incluidos los errores tipográficos).
     */
    public static function checklistPlantaMensual(): array
    {
        return [
            ['tipo' => 'titulo', 'texto' => 'Verificar parámetros de operación del equipo'],
            ['tipo' => 'actividad', 'texto' => 'Voltaje de generación entre fases (AB, BC, CA)'],
            ['tipo' => 'actividad', 'texto' => 'Voltaje de generación entre fase y neutro (AN, BN, CN)'],
            ['tipo' => 'actividad', 'texto' => 'Voltaje de excitación del regulador (F+, F-)'],
            ['tipo' => 'actividad', 'texto' => 'Voltaje de excitación del alternador'],
            ['tipo' => 'actividad', 'texto' => 'Frecuencia'],
            ['tipo' => 'actividad', 'texto' => 'Voltaje de salida del alternador'],

            ['tipo' => 'titulo', 'texto' => 'Revisar'],
            ['tipo' => 'actividad', 'texto' => 'Fugas de agua en el motor y radiador'],
            ['tipo' => 'actividad', 'texto' => 'Fugas de Diesel en el motor, tuberías de alimentación, retorno y tanque de combustible'],
            ['tipo' => 'actividad', 'texto' => 'Fugas de aceite en el motor'],
            ['tipo' => 'actividad', 'texto' => 'Fugas de gases en el múltiple de escape, tuberías y silenciador'],
            ['tipo' => 'nota', 'texto' => 'Nota: de ser necesario se deben ajustar y corregir los parámetros anteriores'],

            ['tipo' => 'titulo', 'texto' => 'Simulación de fallas'],
            ['tipo' => 'subtitulo', 'texto' => 'Ajuste de arranque, paro y protección de la planta de emergencia'],
            ['tipo' => 'actividad', 'texto' => 'Arranque en automático'],
            ['tipo' => 'actividad', 'texto' => 'Falla de largo tiempo de arranqué'],
            ['tipo' => 'actividad', 'texto' => 'Falla de la presión de aceite'],
            ['tipo' => 'actividad', 'texto' => 'Falla de la sobretemperatura'],
            ['tipo' => 'actividad', 'texto' => 'Falla de bajo voltaje'],
            ['tipo' => 'actividad', 'texto' => 'Falla de sobrvelocidad'],
            ['tipo' => 'actividad', 'texto' => 'Falla de sobrecorriente'],

            ['tipo' => 'titulo', 'texto' => 'Pruebas de cargo, simulando una ausencia de alimentación (CFE)'],
            ['tipo' => 'actividad', 'texto' => 'El tablero de transferencia hace su cambio de normal a emergencia...'],
            ['tipo' => 'actividad', 'texto' => 'Revisar el tiempo que tarda en tomar la carga y que el equipo arranque...'],
            ['tipo' => 'actividad', 'texto' => 'Voltaje de salida entre fases (AB, BC, CA)'],
            ['tipo' => 'actividad', 'texto' => 'Voltaje de salida entre fases y neutro (AN, BN, CA)'],
            ['tipo' => 'actividad', 'texto' => 'Frecuencia'],
            ['tipo' => 'actividad', 'texto' => 'Corriente Neutro'],
            ['tipo' => 'actividad', 'texto' => 'Corriente por fase (A, B, C)'],
            ['tipo' => 'actividad', 'texto' => 'Corriente Tierra'],
            ['tipo' => 'actividad', 'texto' => 'Porcentaje de carga (KW) al que está operando el equipo'],

            ['tipo' => 'titulo', 'texto' => 'Pruebas de transferencia y retransferencia'],
            ['tipo' => 'actividad', 'texto' => 'Tiempo de transferencia'],
            ['tipo' => 'actividad', 'texto' => 'Tiempo de desfogue'],
        ];
    }

    /**
     * Vista previa del checklist que verá el usuario en el formulario "Nuevo"
     * según maquinaria + tipo + frecuencia. Devuelve filas normalizadas:
     * ['tipo' => actividad|titulo|subtitulo|nota, 'texto' => …, 'bloque' => ?, 'campo' => checkbox|select|texto]
     */
    public static function checklistPreview(string $maquinaria, int $tipoMantenimiento, ?int $frecuencia): array
    {
        if (!self::usaFrecuencia($maquinaria, $tipoMantenimiento) || $frecuencia === null) {
            return [];
        }

        $filas = [];
        $esPlanta = self::esPlantaEmergencia(trim($maquinaria));

        if ($esPlanta) {
            if ($frecuencia === self::FREC_DIARIO) {
                foreach (self::checklistPlantaDiario() as $t) {
                    $filas[] = ['tipo' => 'actividad', 'texto' => $t, 'bloque' => '', 'campo' => 'checkbox'];
                }
            } elseif ($frecuencia === self::FREC_SEMANAL) {
                foreach (self::checklistPlantaSemanal() as $t) {
                    $filas[] = ['tipo' => 'actividad', 'texto' => $t, 'bloque' => '', 'campo' => 'checkbox'];
                }
            } elseif ($frecuencia === self::FREC_MENSUAL) {
                foreach (self::checklistPlantaMensual() as $item) {
                    $filas[] = [
                        'tipo'   => $item['tipo'],
                        'texto'  => $item['texto'],
                        'bloque' => '',
                        'campo'  => $item['tipo'] === 'actividad' ? 'checkbox' : '',
                    ];
                }
            }

            return $filas;
        }

        if ($frecuencia === self::FREC_DIARIO) {
            foreach (self::checklistDiario() as $t) {
                $filas[] = ['tipo' => 'actividad', 'texto' => $t, 'bloque' => '', 'campo' => 'checkbox'];
            }
        } elseif ($frecuencia === self::FREC_SEMANAL) {
            foreach (self::checklistSemanal() as $t) {
                $filas[] = ['tipo' => 'actividad', 'texto' => $t, 'bloque' => '', 'campo' => 'revisado'];
            }
        } elseif ($frecuencia === self::FREC_HORAS) {
            foreach (self::checklistHoras() as $a) {
                $filas[] = ['tipo' => 'actividad', 'texto' => $a['descripcion'], 'bloque' => $a['bloque'], 'campo' => $a['tipo_campo']];
            }
        }

        return $filas;
    }

    /* ------------------------------------------------------------------ */
    /* Contexto, sesión y permisos                                         */
    /* ------------------------------------------------------------------ */

    public static function usuarioId(): int
    {
        return (int)(Session::get('usuario')['id'] ?? 0);
    }

    public static function getPermisos(): array
    {
        $permisos = AlmacenMaquinariaEquiposService::getPermisos();

        $permisos['puedeOperar'] = $permisos['esUsuarioEstacion']; // Encargado / Asistente Administrativo

        return $permisos;
    }

    /** Archivos de evidencia (firma del módulo en DownloadController). */
    public static function getUploadDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/' . self::UPLOAD_FOLDER;

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** Archivos de firma dibujada (PNG del signature pad). */
    public static function getFirmasDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/' . self::FIRMAS_FOLDER;

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** Equipo sólo si pertenece a una localidad autorizada para el usuario. */
    public static function equipo(int $idEquipo): ?MaquinariaEquipo
    {
        if ($idEquipo <= 0) {
            return null;
        }

        $disponibles = AlmacenMaquinariaEquiposService::getLocalidadesDisponibles();
        $equipo      = MaquinariaEquipo::find($idEquipo);

        if (!$equipo) {
            return null;
        }

        return isset($disponibles[(int)$equipo->id_estacion]) ? $equipo : null;
    }

    /** Encabezado del equipo para la bitácora (título + badges). */
    public static function equipoInfo(int $idEquipo): ?array
    {
        $equipo = self::equipo($idEquipo);

        if (!$equipo) {
            return null;
        }

        $disponibles = AlmacenMaquinariaEquiposService::getLocalidadesDisponibles();
        $idEstacion  = (int)$equipo->id_estacion;

        return [
            'id'               => (int)$equipo->id,
            'id_estacion'      => $idEstacion,
            'estacion'         => $disponibles[$idEstacion] ?? ('Estación #' . $idEstacion),
            'descripcion'      => trim((string)$equipo->descripcion) !== '' ? (string)$equipo->descripcion : 'S/I',
            'marca'            => trim((string)$equipo->marca) !== '' ? (string)$equipo->marca : 'S/I',
            'modelo'           => trim((string)$equipo->modelo) !== '' ? (string)$equipo->modelo : 'S/I',
            'maquinaria'       => trim((string)$equipo->maquinaria) !== '' ? (string)$equipo->maquinaria : 'S/I',
            'no_serie'         => trim((string)$equipo->no_serie) !== '' ? (string)$equipo->no_serie : 'S/I',
        ];
    }

    /** Usuarios activos de la estación (SELECT "Revisado por" / "Cambiado por"). */
    public static function usuariosEstacion(int $idEstacion): array
    {
        if ($idEstacion <= 0) {
            return [];
        }

        return Usuario::where('id_gas', $idEstacion)
            ->where('estatus', 0)
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn ($u) => ['id' => (int)$u->id, 'nombre' => (string)$u->nombre])
            ->toArray();
    }

    /* ------------------------------------------------------------------ */
    /* Calendario + día                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Datos del mes para armar la rejilla (igual que contenido-calendario.php):
     * conteo de registros por día con el color del badge.
     */
    public static function getCalendario(int $idEquipo, int $mes, int $year): array
    {
        $mes  = max(1, min(12, $mes));
        $year = max(2000, min(2100, $year));

        $inicio = sprintf('%04d-%02d-01', $year, $mes);
        $fin    = date('Y-m-t', strtotime($inicio));
        $hoy    = date('Y-m-d');

        $conteos = [];

        $rows = MaquinariaMantenimientoOcurrencia::query()
            ->join('op_maquinaria_mantenimiento as m', 'm.id', '=', 'op_maquinaria_mantenimiento_ocurrencia.id_mantenimiento')
            ->where('m.id_equipo', $idEquipo)
            ->whereBetween('op_maquinaria_mantenimiento_ocurrencia.fecha', [$inicio, $fin])
            ->groupBy('op_maquinaria_mantenimiento_ocurrencia.fecha', 'op_maquinaria_mantenimiento_ocurrencia.estatus')
            ->selectRaw('op_maquinaria_mantenimiento_ocurrencia.fecha as fecha, op_maquinaria_mantenimiento_ocurrencia.estatus as estatus, COUNT(*) as total')
            ->get();

        foreach ($rows as $r) {
            $fecha = substr((string)$r->fecha, 0, 10);

            if (!isset($conteos[$fecha])) {
                $conteos[$fecha] = ['total' => 0, 'pendientes' => 0, 'finalizadas' => 0];
            }

            $conteos[$fecha]['total'] += (int)$r->total;

            if ((int)$r->estatus === self::EST_FINALIZADO) {
                $conteos[$fecha]['finalizadas'] += (int)$r->total;
            } else {
                $conteos[$fecha]['pendientes'] += (int)$r->total;
            }
        }

        $diasEnMes = (int)date('t', strtotime($inicio));
        $diaInicial = (int)date('w', strtotime($inicio));
        $dias = [];

        for ($d = 1; $d <= $diasEnMes; $d++) {
            $fecha      = sprintf('%04d-%02d-%02d', $year, $mes, $d);
            $total      = $conteos[$fecha]['total'] ?? 0;
            $pendientes = $conteos[$fecha]['pendientes'] ?? 0;
            $finalizadas= $conteos[$fecha]['finalizadas'] ?? 0;

            if ($total === 0) {
                $badge = '';
            } elseif ($total === $finalizadas) {
                $badge = 'bg-success';
            } elseif ($total === $pendientes) {
                $badge = 'bg-danger';
            } else {
                $badge = 'bg-warning';
            }

            $dias[] = [
                'fecha'       => $fecha,
                'dia'         => $d,
                'total'       => $total,
                'pendientes'  => $pendientes,
                'finalizadas' => $finalizadas,
                'badge'       => $badge,
                'hoy'         => $fecha === $hoy,
            ];
        }

        $mesAnterior = ($mes === 1) ? 12 : $mes - 1;
        $yearAnterior= ($mes === 1) ? $year - 1 : $year;
        $mesSiguiente= ($mes === 12) ? 1 : $mes + 1;
        $yearSiguiente=($mes === 12) ? $year + 1 : $year;

        return [
            'mes'            => $mes,
            'year'           => $year,
            'mes_nombre'     => self::nombreMes($mes),
            'dia_inicial'    => $diaInicial,
            'dias'           => $dias,
            'mes_anterior'   => $mesAnterior,
            'year_anterior'  => $yearAnterior,
            'mes_siguiente'  => $mesSiguiente,
            'year_siguiente' => $yearSiguiente,
        ];
    }

    /**
     * Calendario por rango de fechas (start/end de FullCalendar).
     *
     * A diferencia de getCalendario() (que recibe mes/year), aquí el rango lo
     * manda el componente: el dayGridMonth pide también los días de los meses
     * vecinos que se ven en la rejilla, así que consultar por mes dejaba meses
     * anteriores/siguientes sin marcar. Se devuelven sólo los días con datos
     * más los totales del rango (mismo shape que sasisopa/calendario).
     */
    public static function getCalendarioRango(int $idEquipo, string $inicio, string $fin): array
    {
        $inicio = substr(trim($inicio), 0, 10);
        $fin    = substr(trim($fin), 0, 10);

        $valida = function (string $f): bool {
            $d = \DateTime::createFromFormat('Y-m-d', $f);

            return $d !== false && $d->format('Y-m-d') === $f;
        };

        if (!$valida($inicio) || !$valida($fin) || $inicio > $fin) {
            $inicio = date('Y-m-01');
            $fin    = date('Y-m-t');
        }

        $conteos = [];

        $rows = MaquinariaMantenimientoOcurrencia::query()
            ->join('op_maquinaria_mantenimiento as m', 'm.id', '=', 'op_maquinaria_mantenimiento_ocurrencia.id_mantenimiento')
            ->where('m.id_equipo', $idEquipo)
            ->whereBetween('op_maquinaria_mantenimiento_ocurrencia.fecha', [$inicio, $fin])
            ->groupBy('op_maquinaria_mantenimiento_ocurrencia.fecha', 'op_maquinaria_mantenimiento_ocurrencia.estatus')
            ->selectRaw('op_maquinaria_mantenimiento_ocurrencia.fecha as fecha, op_maquinaria_mantenimiento_ocurrencia.estatus as estatus, COUNT(*) as total')
            ->get();

        foreach ($rows as $r) {
            $fecha = substr((string)$r->fecha, 0, 10);

            if (!isset($conteos[$fecha])) {
                $conteos[$fecha] = ['total' => 0, 'pendientes' => 0, 'finalizadas' => 0];
            }

            $conteos[$fecha]['total'] += (int)$r->total;

            if ((int)$r->estatus === self::EST_FINALIZADO) {
                $conteos[$fecha]['finalizadas'] += (int)$r->total;
            } else {
                $conteos[$fecha]['pendientes'] += (int)$r->total;
            }
        }

        ksort($conteos);

        $dias = [];

        foreach ($conteos as $fecha => $c) {
            if ($c['total'] === 0) {
                continue;
            }

            $dias[] = [
                'fecha'        => $fecha,
                'total'        => $c['total'],
                'pendientes'   => $c['pendientes'],
                'finalizadas'  => $c['finalizadas'],
                'badge'        => $c['total'] === $c['finalizadas']
                    ? 'bg-success'
                    : ($c['total'] === $c['pendientes'] ? 'bg-danger' : 'bg-warning'),
            ];
        }

        return [
            'dias'    => $dias,
            'totales' => [
                'pendientes'  => array_sum(array_column($dias, 'pendientes')),
                'finalizados'=> array_sum(array_column($dias, 'finalizadas')),
                'total'      => array_sum(array_column($dias, 'total')),
            ],
        ];
    }

    /** Registros (ocurrencias) de un día del equipo con todos sus flags de UI. */
    public static function getDia(int $idEquipo, string $fecha): array
    {
        $fecha = substr($fecha, 0, 10);

        $ocurrencias = MaquinariaMantenimientoOcurrencia::query()
            ->from('op_maquinaria_mantenimiento_ocurrencia as oc')
            ->join('op_maquinaria_mantenimiento as m', 'm.id', '=', 'oc.id_mantenimiento')
            ->leftJoin('op_maquinaria_equipos as e', 'e.id', '=', 'm.id_equipo')
            ->where('m.id_equipo', $idEquipo)
            ->where('oc.fecha', $fecha)
            ->selectRaw('oc.*, m.tipo_mantenimiento, m.frecuencia, m.orden, m.falla_descripcion, m.observaciones, m.costo_mantenimiento, m.creado_por, m.estatus AS estatus_mantenimiento, e.descripcion AS descripcion_equipo, e.maquinaria')
            ->orderBy('m.orden')
            ->orderBy('oc.id')
            ->get();

        if ($ocurrencias->isEmpty()) {
            return [
                'fecha'       => $fecha,
                'fecha_label' => formatearFechaLarga($fecha),
                'registros'   => [],
            ];
        }

        $ids          = $ocurrencias->pluck('id')->map(fn ($v) => (int)$v)->all();
        $idsManten    = $ocurrencias->pluck('id_mantenimiento')->map(fn ($v) => (int)$v)->unique()->values()->all();

        $firmas      = self::firmasPorOcurrencia($ids);
        $comentarios = self::contarPor('op_maquinaria_mantenimiento_comentario', $ids, 'id_ocurrencia');
        $evidencias  = self::contarPor('op_maquinaria_mantenimiento_evidencia', $ids, 'id_ocurrencia');
        $secuencia   = self::mapaSecuencia($idsManten);

        $idUsuario   = self::usuarioId();
        $registros   = [];

        foreach ($ocurrencias as $oc) {
            $registros[] = self::armarRegistroDia($oc, $firmas, $comentarios, $evidencias, $secuencia, $idUsuario);
        }

        return [
            'fecha'       => $fecha,
            'fecha_label' => formatearFechaLarga($fecha),
            'registros'   => $registros,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Registro (detalle + checklist + firmas + evidencias)                */
    /* ------------------------------------------------------------------ */

    /**
     * Fuente única para Detalle / Mantenimiento (checklist) / Firmas / PDF:
     * registro completo + actividades + firmas + evidencias + flags de permisos.
     */
    public static function getRegistro(int $idOcurrencia): ?array
    {
        $oc = MaquinariaMantenimientoOcurrencia::query()
            ->from('op_maquinaria_mantenimiento_ocurrencia as oc')
            ->join('op_maquinaria_mantenimiento as m', 'm.id', '=', 'oc.id_mantenimiento')
            ->leftJoin('op_maquinaria_equipos as e', 'e.id', '=', 'm.id_equipo')
            ->where('oc.id', $idOcurrencia)
            ->selectRaw('oc.*, m.tipo_mantenimiento, m.frecuencia, m.orden, m.falla_descripcion, m.observaciones, m.fecha AS fecha_inicio, m.costo_mantenimiento, m.creado_por, m.estatus AS estatus_mantenimiento, e.id AS id_equipo, e.descripcion AS descripcion_equipo, e.marca, e.modelo, e.no_serie, e.maquinaria, e.id_estacion')
            ->first();

        if (!$oc) {
            return null;
        }

        $disponibles = AlmacenMaquinariaEquiposService::getLocalidadesDisponibles();
        $idEquipo    = (int)$oc->id_equipo;

        if (!isset($disponibles[(int)$oc->id_estacion])) {
            return null;
        }

        $idUsuario   = self::usuarioId();
        $idManten    = (int)$oc->id_mantenimiento;
        $secuencia   = self::mapaSecuencia([$idManten]);
        $secOk       = $secuencia[(int)$oc->id] ?? false;
        $esElaborador= ((int)$oc->creado_por === $idUsuario);

        $firmasRows  = MaquinariaMantenimientoFirma::where('id_ocurrencia', $idOcurrencia)->orderBy('id')->get();
        $firmaA      = $firmasRows->contains(fn ($f) => $f->tipo_firma === 'A');
        $firmaB      = $firmasRows->contains(fn ($f) => $f->tipo_firma === 'B');
        $firmaC      = $firmasRows->contains(fn ($f) => $f->tipo_firma === 'C');

        $estatus     = (int)$oc->estatus;
        $frecuencia  = $oc->frecuencia !== null ? (int)$oc->frecuencia : null;

        $actividades = MaquinariaMantenimientoActividad::where('id_ocurrencia', $idOcurrencia)
            ->orderBy('id')
            ->get()
            ->map(fn ($a) => [
                'id'                 => (int)$a->id,
                'descripcion'        => (string)$a->descripcion,
                'revision_tipo'      => (string)$a->revision_tipo,
                'bloque_horas'       => (string)$a->bloque_horas,
                'resultado'          => (int)$a->resultado,
                'observacion'        => (string)$a->observacion,
                'revisado_por'       => $a->revisado_por !== null ? (int)$a->revisado_por : null,
                'cambiado_por_texto' => $a->cambiado_por_texto !== null ? (string)$a->cambiado_por_texto : null,
                'fecha'              => $a->fecha ? (string)$a->fecha : '',
                'es_fila_seccion'    => !in_array((string)$a->revision_tipo, ['checkbox', 'select', 'texto', 'revisado'], true),
                'es_checkbox'        => (string)$a->revision_tipo === 'checkbox',
                'es_select'          => (string)$a->revision_tipo === 'select',
                'es_texto'           => (string)$a->revision_tipo === 'texto',
                'es_revisado'        => (string)$a->revision_tipo === 'revisado',
            ])
            ->toArray();

        $puedeChecklist = ($esElaborador && !$firmaA && $estatus === self::EST_PENDIENTE && $secOk);

        $usuario = Usuario::find($idUsuario);
        $idPuesto = (int)($usuario->id_puesto ?? 0);

        return [
            // Registro
            'id'                     => (int)$oc->id,
            'id_mantenimiento'        => $idManten,
            'id_equipo'               => $idEquipo,
            'id_estacion'             => (int)$oc->id_estacion,
            'estacion'                => $disponibles[(int)$oc->id_estacion] ?? ('Estación #' . (int)$oc->id_estacion),
            'orden'                   => (int)$oc->orden,
            'orden_label'             => str_pad((string)(int)$oc->orden, 3, '0', STR_PAD_LEFT),
            'fecha'                   => substr((string)$oc->fecha, 0, 10),
            'fecha_label'             => formatearFecha($oc->fecha),
            'fecha_inicio'            => substr((string)$oc->fecha_inicio, 0, 10),
            'fecha_inicio_label'      => formatearFecha($oc->fecha_inicio),
            'estado_actual'           => (string)($oc->estado_actual ?? ''),
            'estado_actual_label'     => trim((string)($oc->estado_actual ?? '')) !== '' ? (string)$oc->estado_actual : '—',
            'costo'                   => (float)$oc->costo,
            'costo_label'             => '$' . number_format((float)$oc->costo, 2),
            'costo_mantenimiento'     => (float)$oc->costo_mantenimiento,
            'tipo_mantenimiento'      => (int)$oc->tipo_mantenimiento,
            'tipo_label'              => self::TIPO_LABELS[(int)$oc->tipo_mantenimiento] ?? '—',
            'frecuencia'              => $frecuencia,
            'frecuencia_label'        => ($frecuencia !== null && isset(self::FREC_LABELS[$frecuencia])) ? self::FREC_LABELS[$frecuencia] : 'N/A',
            'estatus'                 => $estatus,
            'estatus_label'           => self::ESTATUS_LABELS[$estatus] ?? '—',
            'estatus_clase'           => self::ESTATUS_CLASES[$estatus] ?? 'estado-pendiente',
            'color_fila'              => self::FILA_COLORES[$estatus] ?? '#ffffff',
            'falla_descripcion'       => (string)($oc->falla_descripcion ?? ''),
            'observaciones'           => (string)($oc->observaciones ?? ''),
            'creado_por'              => (int)$oc->creado_por,

            // Equipo
            'descripcion_equipo'      => trim((string)($oc->descripcion_equipo ?? '')) !== '' ? (string)$oc->descripcion_equipo : 'S/I',
            'marca'                   => trim((string)($oc->marca ?? '')) !== '' ? (string)$oc->marca : 'S/I',
            'modelo'                  => trim((string)($oc->modelo ?? '')) !== '' ? (string)$oc->modelo : 'S/I',
            'no_serie'                => trim((string)($oc->no_serie ?? '')) !== '' ? (string)$oc->no_serie : 'S/I',
            'maquinaria'              => (string)($oc->maquinaria ?? ''),

            // Flags de flujo
            'es_elaborador'           => $esElaborador,
            'secuencia_ok'            => $secOk,
            'firma_a'                 => $firmaA,
            'firma_b'                 => $firmaB,
            'firma_c'                 => $firmaC,
            'icono_firma'             => self::iconoFirma($estatus, $firmaA, $firmaB, $firmaC),
            'bloqueado'               => $firmaA, // con firma A el registro queda en solo lectura
            'puede_checklist'         => $puedeChecklist,
            'puede_editar_costo'      => ($esElaborador && !$firmaA && $estatus !== self::EST_FINALIZADO && $secOk),
            'puede_eliminar'          => ($esElaborador && $estatus !== self::EST_FINALIZADO),
            'puede_completar'         => ($esElaborador && $estatus === self::EST_PENDIENTE && $secOk),
            'puede_pdf'               => ($estatus === self::EST_FINALIZADO),
            'puede_pdf_general'       => ($frecuencia === self::FREC_DIARIO && self::cicloTieneFinalizado($idManten)),

            // Firmas (estado por tipo)
            'firmas'                  => self::estadoFirmas($firmasRows, $esElaborador, $secOk, $estatus, $idUsuario, $idPuesto),
            'firmas_rows'             => $firmasRows->map(fn ($f) => [
                'id'          => (int)$f->id,
                'tipo_firma'  => (string)$f->tipo_firma,
                'id_usuario'  => (int)$f->id_usuario,
                'nombre'      => self::nombreUsuario((int)$f->id_usuario),
                'fecha'       => (string)($f->fecha ?? ''),
                'fecha_label' => self::fechaHoraTexto((string)($f->fecha ?? '')),
                'firma'       => (string)$f->firma,
                'es_imagen'   => self::firmaEsImagen((string)$f->firma),
                'url'         => self::firmaUrl((string)$f->firma),
            ])->toArray(),

            // Actividades / evidencias / comentarios
            'actividades'             => $actividades,
            'actividades_total'       => count($actividades),
            'evidencias'              => self::getEvidencias($idOcurrencia),
            'evidencias_total'        => MaquinariaMantenimientoEvidencia::where('id_ocurrencia', $idOcurrencia)->count(),
            'comentarios_total'       => MaquinariaMantenimientoComentario::where('id_ocurrencia', $idOcurrencia)->count(),
            'usuarios_estacion'       => self::usuariosEstacion((int)$oc->id_estacion),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Comentarios                                                         */
    /* ------------------------------------------------------------------ */

    public static function getComentarios(int $idOcurrencia): array
    {
        $idUsuario = self::usuarioId();

        $rows = MaquinariaMantenimientoComentario::where('id_ocurrencia', $idOcurrencia)
            ->orderByDesc('id')
            ->get();

        $nombres = self::mapaNombres($rows->pluck('id_usuario'));

        return $rows->map(fn ($c) => [
            'id'             => (int)$c->id,
            'id_usuario'     => (int)$c->id_usuario,
            'nombre_usuario' => $nombres[(int)$c->id_usuario] ?? ('Usuario #' . (int)$c->id_usuario),
            'es_propio'      => ((int)$c->id_usuario === $idUsuario),
            'comentario'     => (string)$c->comentario,
            'fecha_label'    => self::fechaHoraTexto((string)($c->fecha_hora ?? '')),
        ])->toArray();
    }

    public static function agregarComentario(int $idOcurrencia, array $data): array
    {
        $oc = MaquinariaMantenimientoOcurrencia::find($idOcurrencia);

        if (!$oc) {
            return self::fail('El registro no existe.', 404);
        }

        $comentario = trim((string)($data['comentario'] ?? ''));

        if ($comentario === '') {
            return self::fail('Escribe un comentario.', 422);
        }

        $idUsuario = self::usuarioId();

        if ($idUsuario <= 0) {
            return self::fail('Sesión no válida.', 401);
        }

        try {
            MaquinariaMantenimientoComentario::create([
                'id_mantenimiento' => (int)$oc->id_mantenimiento,
                'id_ocurrencia'    => $idOcurrencia,
                'id_usuario'       => $idUsuario,
                'comentario'       => $comentario,
                'fecha_hora'       => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('[MaquinariaBitacora] agregarComentario: ' . $e->getMessage());

            return self::fail('Error al guardar el comentario.', 500);
        }

        return self::ok('Comentario agregado.');
    }

    /* ------------------------------------------------------------------ */
    /* Evidencias                                                          */
    /* ------------------------------------------------------------------ */

    public static function getEvidencias(int $idOcurrencia): array
    {
        $rows = MaquinariaMantenimientoEvidencia::where('id_ocurrencia', $idOcurrencia)
            ->orderByDesc('id')
            ->get();

        $nombres = self::mapaNombres($rows->pluck('subido_por'));

        return $rows->map(fn ($e) => [
            'id'              => (int)$e->id,
            'archivo'         => (string)$e->archivo,
            'nombre_original' => (string)$e->nombre_original,
            'extension'       => (string)$e->extension,
            'tipo'            => (int)$e->tipo,
            'subido_por'      => (int)$e->subido_por,
            'subido_por_nombre' => $nombres[(int)$e->subido_por] ?? ('Usuario #' . (int)$e->subido_por),
            'fecha_label'     => self::fechaHoraTexto((string)($e->fecha_subida ?? '')),
            'es_pdf'          => strtolower((string)$e->extension) === 'pdf',
            'url'             => self::descargaUrl((string)$e->archivo),
            'existe'          => is_file(self::getUploadDir() . (string)$e->archivo),
        ])->toArray();
    }

    public static function subirEvidencia(int $idOcurrencia, array $file): array
    {
        $oc = MaquinariaMantenimientoOcurrencia::find($idOcurrencia);

        if (!$oc) {
            return self::fail('El registro no existe.', 404);
        }

        if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return self::fail('Debes seleccionar un archivo.', 422);
        }

        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));

        if (!in_array($extension, self::EXTENSIONES_EVIDENCIA, true)) {
            return self::fail('Formato no permitido (solo imágenes o PDF).', 422);
        }

        if ((int)($file['size'] ?? 0) <= 0) {
            return self::fail('El archivo está vacío.', 422);
        }

        if (!self::mimeSeguro((string)$file['tmp_name'])) {
            return self::fail('El archivo no es válido.', 422);
        }

        $idMantenimiento = (int)$oc->id_mantenimiento;
        $nombreArchivo   = "MANT-{$idMantenimiento}-" . uniqid() . '.' . $extension;

        if (!move_uploaded_file((string)$file['tmp_name'], self::getUploadDir() . $nombreArchivo)) {
            return self::fail('No se pudo guardar la evidencia.', 500);
        }

        try {
            MaquinariaMantenimientoEvidencia::create([
                'id_mantenimiento' => $idMantenimiento,
                'id_ocurrencia'    => $idOcurrencia,
                'archivo'          => $nombreArchivo,
                'nombre_original'  => (string)$file['name'],
                'extension'        => $extension,
                'tipo'             => 3,
                'subido_por'       => self::usuarioId(),
                'fecha_subida'     => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            @unlink(self::getUploadDir() . $nombreArchivo);
            error_log('[MaquinariaBitacora] subirEvidencia: ' . $e->getMessage());

            return self::fail('Error al registrar la evidencia.', 500);
        }

        return self::ok('Evidencia agregada exitosamente.');
    }

    public static function eliminarEvidencia(int $idEvidencia): array
    {
        $evidencia = MaquinariaMantenimientoEvidencia::find($idEvidencia);

        if (!$evidencia) {
            return self::fail('La evidencia no existe.', 404);
        }

        $archivo = (string)$evidencia->archivo;

        try {
            $evidencia->delete();
        } catch (\Throwable $e) {
            error_log('[MaquinariaBitacora] eliminarEvidencia: ' . $e->getMessage());

            return self::fail('Error al eliminar la evidencia.', 500);
        }

        if ($archivo !== '' && is_file(self::getUploadDir() . $archivo)) {
            @unlink(self::getUploadDir() . $archivo);
        }

        return self::ok('Evidencia eliminada.');
    }

    /* ------------------------------------------------------------------ */
    /* Crear mantenimiento (+ ocurrencias)                                 */
    /* ------------------------------------------------------------------ */

    public static function crearMantenimiento(array $data): array
    {
        $permisos = self::getPermisos();

        if (!$permisos['puedeOperar']) {
            return self::fail('Tu puesto no está habilitado para crear mantenimientos.', 403);
        }

        $idEquipo = (int)($data['idEquipo'] ?? 0);
        $equipo   = self::equipo($idEquipo);

        if (!$equipo) {
            return self::fail('El equipo no existe o no está disponible.', 404);
        }

        $tipoMantenimiento = (int)($data['tipoMantenimiento'] ?? 0);

        if (!in_array($tipoMantenimiento, [self::TIPO_PREVENTIVO, self::TIPO_CORRECTIVO], true)) {
            return self::fail('El tipo de mantenimiento es obligatorio.', 422);
        }

        $frecuencia = (isset($data['frecuencia']) && $data['frecuencia'] !== '' && $data['frecuencia'] !== null)
            ? (int)$data['frecuencia']
            : null;

        $maquinaria = trim((string)$equipo->maquinaria);

        if (self::usaFrecuencia($maquinaria, $tipoMantenimiento)) {
            if ($frecuencia === null || !isset(self::FREC_LABELS[$frecuencia])) {
                return self::fail('La frecuencia es obligatoria para este equipo.', 422);
            }

            // Planta de emergencia no tiene frecuencia "Por horas" (ni Hidrolavadora "Mensual").
            if (self::esPlantaEmergencia($maquinaria) && $frecuencia === self::FREC_HORAS) {
                return self::fail('Frecuencia no válida para la Planta de emergencia.', 422);
            }

            if ($maquinaria === self::MAQUINARIA_HIDROLAVADORA && $frecuencia === self::FREC_MENSUAL) {
                return self::fail('Frecuencia no válida para la Hidrolavadora.', 422);
            }
        }

        $fechaInicio = (isset($data['fechaInicio']) && trim((string)$data['fechaInicio']) !== '')
            ? substr(trim((string)$data['fechaInicio']), 0, 10)
            : date('Y-m-d');

        if (!self::esFechaValida($fechaInicio)) {
            return self::fail('La fecha de inicio no es válida.', 422);
        }

        $fallaDescripcion = trim((string)($data['fallaDescripcion'] ?? ''));
        $observaciones    = trim((string)($data['observaciones'] ?? ''));
        $costo            = (isset($data['costoMantenimiento']) && trim((string)$data['costoMantenimiento']) !== '')
            ? (float)$data['costoMantenimiento']
            : 0.0;
        $estadoActual     = trim((string)($data['estadoActual'] ?? ''));
        $estadoActual     = in_array($estadoActual, self::ESTADO_ACTUAL_OPCIONES, true) ? $estadoActual : 'En operación';

        $idUsuario = self::usuarioId();

        if ($idUsuario <= 0) {
            return self::fail('Sesión no válida.', 401);
        }

        $usaFrecuencia = self::usaFrecuencia($maquinaria, $tipoMantenimiento);

        try {
            $idMantenimiento = Capsule::transaction(function () use (
                $idEquipo, $tipoMantenimiento, $frecuencia, $fechaInicio, $fallaDescripcion,
                $observaciones, $costo, $idUsuario, $estadoActual, $usaFrecuencia, $maquinaria
            ): int {
                $orden = (int)Capsule::table('op_maquinaria_mantenimiento')
                    ->where('id_equipo', $idEquipo)
                    ->max('orden');

                $idMantenimiento = (int)Capsule::table('op_maquinaria_mantenimiento')->insertGetId([
                    'id_equipo'           => $idEquipo,
                    'tipo_mantenimiento'  => $tipoMantenimiento,
                    // La frecuencia solo aplica a Hidrolavadora/Planta + Preventivo; si no, NULL.
                    'frecuencia'          => $usaFrecuencia ? $frecuencia : null,
                    'fecha'               => $fechaInicio,
                    'orden'               => $orden + 1,
                    'estatus'             => self::EST_PENDIENTE,
                    'falla_descripcion'   => $fallaDescripcion,
                    'observaciones'       => $observaciones,
                    'costo_mantenimiento' => $costo,
                    'creado_por'          => $idUsuario,
                    'fecha_creacion'      => date('Y-m-d H:i:s'),
                ]);

                if ($usaFrecuencia && $frecuencia !== null) {
                    if (self::esPlantaEmergencia($maquinaria)) {
                        self::generarOcurrenciasPlanta($idMantenimiento, $frecuencia, $fechaInicio, $estadoActual, $costo);
                    } else {
                        self::generarOcurrenciasHidrolavadora($idMantenimiento, $frecuencia, $fechaInicio, $estadoActual, $costo);
                    }
                } else {
                    // Mantenimiento normal: 1 sola ocurrencia (correctivo u otra maquinaria).
                    self::insertarOcurrencia($idMantenimiento, $fechaInicio, $estadoActual, $costo);
                }

                return $idMantenimiento;
            });
        } catch (\Throwable $e) {
            error_log('[MaquinariaBitacora] crearMantenimiento: ' . $e->getMessage());

            return self::fail('No se pudo crear el mantenimiento.', 500);
        }

        return self::ok('Mantenimiento creado exitosamente.', ['id' => $idMantenimiento]);
    }

    /* ------------------------------------------------------------------ */
    /* Editar / eliminar                                                   */
    /* ------------------------------------------------------------------ */

    public static function editarMantenimiento(array $data): array
    {
        $idMantenimiento = (int)($data['idMantenimiento'] ?? 0);
        $mant            = MaquinariaMantenimiento::find($idMantenimiento);

        if (!$mant) {
            return self::fail('El mantenimiento no existe.', 404);
        }

        $equipo = self::equipo((int)$mant->id_equipo);

        if (!$equipo) {
            return self::fail('Registro no encontrado.', 404);
        }

        if ((int)$mant->estatus >= self::EST_EN_PROCESO) {
            return self::fail('No se puede editar un mantenimiento en proceso o finalizado.', 422);
        }

        $costo = (isset($data['costoMantenimiento']) && trim((string)$data['costoMantenimiento']) !== '')
            ? (float)$data['costoMantenimiento']
            : 0.0;

        try {
            $mant->falla_descripcion   = trim((string)($data['fallaDescripcion'] ?? ''));
            $mant->observaciones       = trim((string)($data['observaciones'] ?? ''));
            $mant->costo_mantenimiento = $costo;
            $mant->save();
        } catch (\Throwable $e) {
            error_log('[MaquinariaBitacora] editarMantenimiento: ' . $e->getMessage());

            return self::fail('No se pudo actualizar el mantenimiento.', 500);
        }

        return self::ok('Mantenimiento actualizado exitosamente.');
    }

    public static function eliminarMantenimiento(int $idMantenimiento): array
    {
        $mant = MaquinariaMantenimiento::find($idMantenimiento);

        if (!$mant) {
            return self::fail('El mantenimiento no existe.', 404);
        }

        if (!self::equipo((int)$mant->id_equipo)) {
            return self::fail('Registro no encontrado.', 404);
        }

        $enProceso = MaquinariaMantenimientoOcurrencia::where('id_mantenimiento', $idMantenimiento)
            ->where('estatus', '>=', self::EST_EN_PROCESO)
            ->count();

        if ($enProceso > 0) {
            return self::fail('No se puede eliminar: hay registros en proceso o finalizados.', 422);
        }

        try {
            Capsule::transaction(function () use ($idMantenimiento): void {
                $idsOcurrencias = MaquinariaMantenimientoOcurrencia::where('id_mantenimiento', $idMantenimiento)
                    ->pluck('id')
                    ->map(fn ($v) => (int)$v)
                    ->all();

                if (!empty($idsOcurrencias)) {
                    self::borrarArchivosEvidencia($idsOcurrencias);
                    self::borrarArchivosFirma($idsOcurrencias);

                    MaquinariaMantenimientoActividad::whereIn('id_ocurrencia', $idsOcurrencias)->delete();
                    MaquinariaMantenimientoFirma::whereIn('id_ocurrencia', $idsOcurrencias)->delete();
                    MaquinariaMantenimientoToken::whereIn('id_ocurrencia', $idsOcurrencias)->delete();
                    MaquinariaMantenimientoEvidencia::whereIn('id_ocurrencia', $idsOcurrencias)->delete();
                    MaquinariaMantenimientoComentario::whereIn('id_ocurrencia', $idsOcurrencias)->delete();
                }

                // Residuos por maestro (por si hubiera filas huérfanas de versiones previas).
                MaquinariaMantenimientoActividad::where('id_mantenimiento', $idMantenimiento)->delete();
                MaquinariaMantenimientoFirma::where('id_mantenimiento', $idMantenimiento)->delete();
                MaquinariaMantenimientoToken::where('id_mantenimiento', $idMantenimiento)->delete();
                MaquinariaMantenimientoEvidencia::where('id_mantenimiento', $idMantenimiento)->delete();
                MaquinariaMantenimientoComentario::where('id_mantenimiento', $idMantenimiento)->delete();

                MaquinariaMantenimientoOcurrencia::where('id_mantenimiento', $idMantenimiento)->delete();
                MaquinariaMantenimiento::where('id', $idMantenimiento)->delete();
            });
        } catch (\Throwable $e) {
            error_log('[MaquinariaBitacora] eliminarMantenimiento: ' . $e->getMessage());

            return self::fail('No se pudo eliminar el mantenimiento.', 500);
        }

        return self::ok('Mantenimiento eliminado exitosamente.');
    }

    /**
     * Eliminación EN CASCADA de un registro y sus consecuentes de LA MISMA
     * solicitud, en una sola transacción. Si el maestro queda sin registros,
     * se elimina también.
     */
    public static function eliminarRegistro(int $idOcurrencia): array
    {
        $idUsuario = self::usuarioId();
        $oc        = MaquinariaMantenimientoOcurrencia::find($idOcurrencia);

        if (!$oc) {
            return self::fail('El registro no existe.', 404);
        }

        $mant = MaquinariaMantenimiento::find((int)$oc->id_mantenimiento);

        if (!$mant || !self::equipo((int)$mant->id_equipo)) {
            return self::fail('Registro no encontrado.', 404);
        }

        if ((int)$mant->creado_por !== $idUsuario) {
            return self::fail('Solo el usuario que elaboró el registro puede eliminarlo.', 403);
        }

        if ((int)$oc->estatus === self::EST_FINALIZADO) {
            return self::fail('No se puede eliminar un registro Finalizado.', 422);
        }

        $secuencia = self::secuenciaRegistros((int)$mant->id);
        $index     = -1;

        foreach ($secuencia as $i => $reg) {
            if ((int)$reg['id'] === $idOcurrencia) {
                $index = $i;
                break;
            }
        }

        if ($index < 0) {
            return self::fail('No se pudo eliminar el registro.', 500);
        }

        $idsEliminar = [];

        for ($i = $index; $i < count($secuencia); $i++) {
            $idsEliminar[] = (int)$secuencia[$i]['id'];
        }

        $idMantenimiento = (int)$mant->id;

        try {
            Capsule::transaction(function () use ($idsEliminar, $idMantenimiento): void {
                self::borrarArchivosEvidencia($idsEliminar);
                self::borrarArchivosFirma($idsEliminar);

                MaquinariaMantenimientoActividad::whereIn('id_ocurrencia', $idsEliminar)->delete();
                MaquinariaMantenimientoFirma::whereIn('id_ocurrencia', $idsEliminar)->delete();
                MaquinariaMantenimientoToken::whereIn('id_ocurrencia', $idsEliminar)->delete();
                MaquinariaMantenimientoEvidencia::whereIn('id_ocurrencia', $idsEliminar)->delete();
                MaquinariaMantenimientoComentario::whereIn('id_ocurrencia', $idsEliminar)->delete();
                MaquinariaMantenimientoOcurrencia::whereIn('id', $idsEliminar)->delete();

                $restantes = MaquinariaMantenimientoOcurrencia::where('id_mantenimiento', $idMantenimiento)->count();

                if ($restantes === 0) {
                    self::borrarArchivosFirma(
                        MaquinariaMantenimientoFirma::where('id_mantenimiento', $idMantenimiento)
                            ->pluck('id_ocurrencia')
                            ->map(fn ($v) => (int)$v)
                            ->unique()
                            ->all()
                    );

                    MaquinariaMantenimientoFirma::where('id_mantenimiento', $idMantenimiento)->delete();
                    MaquinariaMantenimientoToken::where('id_mantenimiento', $idMantenimiento)->delete();
                    MaquinariaMantenimientoEvidencia::where('id_mantenimiento', $idMantenimiento)->delete();
                    MaquinariaMantenimientoComentario::where('id_mantenimiento', $idMantenimiento)->delete();
                    MaquinariaMantenimientoActividad::where('id_mantenimiento', $idMantenimiento)->delete();
                    MaquinariaMantenimiento::where('id', $idMantenimiento)->delete();
                }
            });
        } catch (\Throwable $e) {
            error_log('[MaquinariaBitacora] eliminarRegistro: ' . $e->getMessage());

            return self::fail('No se pudo eliminar el registro.', 500);
        }

        return self::ok('Registro y sus consecuentes eliminados.', ['eliminados' => count($idsEliminar)]);
    }

    public static function editarCostoRegistro(int $idOcurrencia, float $costo): array
    {
        $idUsuario = self::usuarioId();
        $oc        = MaquinariaMantenimientoOcurrencia::find($idOcurrencia);

        if (!$oc) {
            return self::fail('El registro no existe.', 404);
        }

        if (!self::esElaborador($oc, $idUsuario)) {
            return self::fail('Solo el usuario que elaboró la solicitud puede editar el costo.', 403);
        }

        if ((int)$oc->estatus === self::EST_FINALIZADO) {
            return self::fail('No se puede editar el costo de un registro Finalizado.', 422);
        }

        try {
            $oc->costo = $costo;
            $oc->save();
        } catch (\Throwable $e) {
            error_log('[MaquinariaBitacora] editarCostoRegistro: ' . $e->getMessage());

            return self::fail('No se pudo actualizar el costo.', 500);
        }

        return self::ok('Costo actualizado exitosamente.');
    }

    /* ------------------------------------------------------------------ */
    /* Checklist de actividades                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Regla backend del checklist: solo quien ELABORÓ la solicitud, con el
     * registro en Pendiente y con la secuencia interna cumplida.
     */
    public static function actividadPermitida(int $idActividad, int $idUsuario): bool
    {
        $actividad = MaquinariaMantenimientoActividad::find($idActividad);

        if (!$actividad || $actividad->id_ocurrencia === null) {
            return false;
        }

        $oc = MaquinariaMantenimientoOcurrencia::find((int)$actividad->id_ocurrencia);

        if (!$oc || (int)$oc->estatus !== self::EST_PENDIENTE) {
            return false;
        }

        $mant = MaquinariaMantenimiento::find((int)$oc->id_mantenimiento);

        if (!$mant || (int)$mant->creado_por !== $idUsuario) {
            return false;
        }

        return self::secuenciaOk((int)$oc->id);
    }

    public static function guardarActividad(array $data): array
    {
        $idActividad = (int)($data['idActividad'] ?? 0);
        $idUsuario   = self::usuarioId();

        $resultado = (int)(($data['completada'] ?? ($data['resultado'] ?? 0)) ?: 0);
        $observacion = trim((string)($data['observacion'] ?? ''));

        $revisadoPor = (isset($data['revisadoPor']) && $data['revisadoPor'] !== '' && $data['revisadoPor'] !== null)
            ? (int)$data['revisadoPor']
            : null;

        $cambiadoPorTexto = (isset($data['cambiadoPorTexto']) && $data['cambiadoPorTexto'] !== null)
            ? (string)$data['cambiadoPorTexto']
            : null;

        $actividad = MaquinariaMantenimientoActividad::find($idActividad);

        if (!$actividad) {
            return self::fail('La actividad no existe.', 404);
        }

        if (!self::actividadPermitida($idActividad, $idUsuario)) {
            return self::fail('No puedes modificar este checklist (solo el elaborador, con el registro en Pendiente y la secuencia cumplida).', 403);
        }

        try {
            $actividad->resultado    = $resultado;
            $actividad->observacion  = $observacion;
            $actividad->fecha        = date('Y-m-d H:i:s');

            if ($revisadoPor !== null) {
                $actividad->revisado_por = $revisadoPor;
            }

            if ($cambiadoPorTexto !== null) {
                $actividad->cambiado_por_texto = $cambiadoPorTexto;
            } elseif (in_array((string)$actividad->revision_tipo, ['select', 'texto'], true) && $observacion !== '') {
                // Paridad legacy: el valor capturado en select/texto también alimenta
                // `cambiado_por_texto` (lo usa la matriz del PDF general).
                $actividad->cambiado_por_texto = $observacion;
            }

            // Paridad legacy: si no llega "revisadoPor" y el resultado es 0, se limpia.
            if ($revisadoPor === null && $resultado === 0) {
                $actividad->revisado_por = null;
            }

            $actividad->save();
        } catch (\Throwable $e) {
            error_log('[MaquinariaBitacora] guardarActividad: ' . $e->getMessage());

            return self::fail('No se pudo guardar la actividad.', 500);
        }

        return self::ok('Actividad guardada.');
    }

    /**
     * Pasa el registro a "En proceso" (estatus 1). Solo el elaborador y con la
     * secuencia cumplida (igual que el legacy: estatus 0 → 1).
     */
    public static function completarOcurrencia(int $idOcurrencia): array
    {
        $idUsuario = self::usuarioId();
        $oc        = MaquinariaMantenimientoOcurrencia::find($idOcurrencia);

        if (!$oc) {
            return self::fail('El registro no existe.', 404);
        }

        $mant = MaquinariaMantenimiento::find((int)$oc->id_mantenimiento);

        if (!$mant) {
            return self::fail('El mantenimiento no existe.', 404);
        }

        if ((int)$mant->creado_por !== $idUsuario) {
            return self::fail('Solo el usuario que elaboró el registro puede completar el mantenimiento.', 403);
        }

        if (!self::secuenciaOk($idOcurrencia)) {
            return self::fail('Debes finalizar el registro anterior antes de procesar este.', 409, ['code' => 2]);
        }

        if ((int)$oc->estatus !== self::EST_PENDIENTE) {
            return self::fail('El registro ya no está Pendiente.', 422);
        }

        try {
            $oc->estatus        = self::EST_EN_PROCESO;
            $oc->realizado_por  = $idUsuario;
            $oc->fecha_realizado = date('Y-m-d H:i:s');
            $oc->save();
        } catch (\Throwable $e) {
            error_log('[MaquinariaBitacora] completarOcurrencia: ' . $e->getMessage());

            return self::fail('No se pudo completar el registro.', 500);
        }

        return self::ok('Registro completado.');
    }

    /* ------------------------------------------------------------------ */
    /* Firmas A / B / C                                                    */
    /* ------------------------------------------------------------------ */

    /** Firma A: solo quien elaboró, con la secuencia cumplida. No cambia estatus. */
    public static function firmaElaboro(int $idOcurrencia, string $imagen): array
    {
        $idUsuario = self::usuarioId();
        $oc        = MaquinariaMantenimientoOcurrencia::find($idOcurrencia);

        if (!$oc) {
            return self::fail('El registro no existe.', 404);
        }

        if (!self::secuenciaOk($idOcurrencia)) {
            return self::fail('Debes finalizar el registro anterior antes de procesar este.', 409);
        }

        if (!self::esElaborador($oc, $idUsuario)) {
            return self::fail('Solo el usuario que elaboró el registro puede firmar la Firma A.', 403);
        }

        if (trim($imagen) === '') {
            return self::fail('Dibuja tu firma antes de guardar.', 422);
        }

        $yaFirmo = MaquinariaMantenimientoFirma::where('id_ocurrencia', $idOcurrencia)
            ->where('tipo_firma', 'A')
            ->exists();

        if ($yaFirmo) {
            return self::fail('Ya existe una firma Elaboró para este registro.', 422);
        }

        $archivo = self::guardarFirmaPng($imagen);

        if ($archivo === null) {
            return self::fail('La firma no es una imagen válida.', 422);
        }

        try {
            MaquinariaMantenimientoFirma::create([
                'id_mantenimiento' => (int)$oc->id_mantenimiento,
                'id_ocurrencia'    => $idOcurrencia,
                'id_usuario'       => $idUsuario,
                'tipo_firma'       => 'A',
                'firma'            => $archivo,
                'fecha'            => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            @unlink(self::getFirmasDir() . $archivo);
            error_log('[MaquinariaBitacora] firmaElaboro: ' . $e->getMessage());

            return self::fail('No se pudo registrar la firma.', 500);
        }

        return self::ok('Firma Elaboró registrada.');
    }

    /** Firma B dibujada: solo el puesto 13 (el usuario 19 debe usar token). */
    public static function firmaVobo(int $idOcurrencia, string $imagen): array
    {
        $idUsuario = self::usuarioId();
        $oc        = MaquinariaMantenimientoOcurrencia::find($idOcurrencia);

        if (!$oc) {
            return self::fail('El registro no existe.', 404);
        }

        self::validarSecuenciaFirmas($idOcurrencia, 'B');

        if ($idUsuario === self::USUARIO_VOBO) {
            return self::fail('El usuario autorizado debe firmar el VoBo mediante token.', 403);
        }

        $puesto = self::puestoUsuario($idUsuario);

        if ($puesto !== self::PUESTO_VOBO) {
            return self::fail('Este canal de firma (VoBo) solo está disponible para el puesto autorizado.', 403);
        }

        if (trim($imagen) === '') {
            return self::fail('Dibuja tu firma antes de guardar.', 422);
        }

        $archivo = self::guardarFirmaPng($imagen);

        if ($archivo === null) {
            return self::fail('La firma no es una imagen válida.', 422);
        }

        try {
            Capsule::transaction(function () use ($oc, $idUsuario, $archivo, $idOcurrencia): void {
                MaquinariaMantenimientoFirma::create([
                    'id_mantenimiento' => (int)$oc->id_mantenimiento,
                    'id_ocurrencia'    => $idOcurrencia,
                    'id_usuario'       => $idUsuario,
                    'tipo_firma'       => 'B',
                    'firma'            => $archivo,
                    'fecha'            => date('Y-m-d H:i:s'),
                ]);

                MaquinariaMantenimientoOcurrencia::where('id', $idOcurrencia)->update(['estatus' => self::EST_EN_PROCESO]);
            });
        } catch (\Throwable $e) {
            @unlink(self::getFirmasDir() . $archivo);
            error_log('[MaquinariaBitacora] firmaVobo: ' . $e->getMessage());

            return self::fail('No se pudo registrar el VoBo.', 500);
        }

        return self::ok('VoBo registrado.');
    }

    /** Genera y envía el token por Telegram (canal 3) para B (user 19) o C (user 21). */
    public static function solicitarToken(int $idOcurrencia, string $via, string $tipoFirma): array
    {
        $idUsuario = self::usuarioId();
        $oc        = MaquinariaMantenimientoOcurrencia::find($idOcurrencia);

        if (!$oc) {
            return self::fail('El registro no existe.', 404);
        }

        $tipoFirma = strtoupper($tipoFirma);

        if ($tipoFirma === 'B') {
            if ($idUsuario !== self::USUARIO_VOBO) {
                return self::fail('El VoBo con token solo puede firmarlo el usuario autorizado.', 403);
            }

            try {
                self::validarSecuenciaFirmas($idOcurrencia, 'B');
            } catch (\Throwable $e) {
                return self::fail($e->getMessage(), 422);
            }
        } elseif ($tipoFirma === 'C') {
            if ($idUsuario !== self::USUARIO_AUTORIZACION) {
                return self::fail('La autorización solo puede firmarla el usuario autorizado.', 403);
            }

            if ((int)$oc->estatus !== self::EST_EN_PROCESO) {
                return self::fail('El registro debe estar En proceso para autorizarlo.', 422);
            }

            try {
                self::validarSecuenciaFirmas($idOcurrencia, 'C');
            } catch (\Throwable $e) {
                return self::fail($e->getMessage(), 422);
            }
        } else {
            return self::fail('Tipo de firma inválido.', 422);
        }

        // Mismo esquema de canales que Solicitud de Cheques: telegram (3) o email (4).
        $via   = strtolower(trim($via)) === 'email' ? 'email' : 'telegram';
        $canal = $via === 'email' ? 4 : 3;

        $token = rand(100000, 999999);

        try {
            Capsule::transaction(function () use ($oc, $idUsuario, $token, $canal): void {
                MaquinariaMantenimientoToken::where('id_mantenimiento', (int)$oc->id_mantenimiento)
                    ->where('id_ocurrencia', (int)$oc->id)
                    ->where('id_usuario', $idUsuario)
                    ->delete();

                MaquinariaMantenimientoToken::create([
                    'id_mantenimiento' => (int)$oc->id_mantenimiento,
                    'id_ocurrencia'    => (int)$oc->id,
                    'id_usuario'       => $idUsuario,
                    'token'            => $token,
                    'fecha_creacion'   => date('Y-m-d H:i:s'),
                    'expirado'         => 0,
                    'canal'            => $canal,
                ]);
            });
        } catch (\Throwable $e) {
            error_log('[MaquinariaBitacora] solicitarToken: ' . $e->getMessage());

            return self::fail('No se pudo generar el token.', 500);
        }

        $tipoLabel      = ($tipoFirma === 'B') ? 'el VoBo' : 'la autorización';
        $nombreEstacion = self::nombreEstacionEquipo((int)$oc->id_mantenimiento);
        $mensaje        = "📲 Usa el token <b>{$token}</b> para firmar {$tipoLabel} del mantenimiento de maquinaria y equipos.\n\n"
            . "⛽ Estación: {$nombreEstacion}.\n"
            . "📋 Registro: #" . (int)$oc->orden . " (" . formatearFecha($oc->fecha) . ").";

        if ($via === 'email') {
            $usuario = Usuario::find($idUsuario);
            $email   = $usuario?->email ?? '';

            if (!$email) {
                return self::fail('El usuario no tiene correo electrónico registrado.', 422);
            }

            try {
                $emailService = new EmailService();
                $emailService->sendToken($email, $token);
            } catch (\Throwable $e) {
                error_log('[MaquinariaBitacora] solicitarToken email: ' . $e->getMessage());

                return self::fail('No se pudo enviar el token por correo electrónico.', 500);
            }

            return self::ok('Token enviado por correo electrónico.');
        }

        try {
            $telegram = new TelegramService();
            $telegram->sendToken($idUsuario, $mensaje);
        } catch (\Throwable $e) {
            error_log('[MaquinariaBitacora] solicitarToken telegram: ' . $e->getMessage());
        }

        return self::ok('Token enviado por Telegram.');
    }

    /** Valida el token y registra la firma (B → estatus 1, C → estatus 2). */
    public static function validarToken(int $idOcurrencia, string $tipoFirma, int $token): array
    {
        $idUsuario = self::usuarioId();
        $oc        = MaquinariaMantenimientoOcurrencia::find($idOcurrencia);

        if (!$oc) {
            return self::fail('El registro no existe.', 404);
        }

        $tipoFirma = strtoupper($tipoFirma);

        if (!in_array($tipoFirma, ['B', 'C'], true)) {
            return self::fail('Tipo de firma inválido.', 422);
        }

        if ($tipoFirma === 'B') {
            if ($idUsuario !== self::USUARIO_VOBO) {
                return self::fail('El VoBo con token solo puede firmarlo el usuario autorizado.', 403);
            }

            try {
                self::validarSecuenciaFirmas($idOcurrencia, 'B');
            } catch (\Throwable $e) {
                return self::fail($e->getMessage(), 422);
            }
        } else {
            if ($idUsuario !== self::USUARIO_AUTORIZACION) {
                return self::fail('La autorización solo puede firmarla el usuario autorizado.', 403);
            }

            if ((int)$oc->estatus !== self::EST_EN_PROCESO) {
                return self::fail('El registro debe estar En proceso.', 422);
            }

            try {
                self::validarSecuenciaFirmas($idOcurrencia, 'C');
            } catch (\Throwable $e) {
                return self::fail($e->getMessage(), 422);
            }
        }

        $registro = MaquinariaMantenimientoToken::where('id_mantenimiento', (int)$oc->id_mantenimiento)
            ->where('id_ocurrencia', $idOcurrencia)
            ->where('id_usuario', $idUsuario)
            ->where('token', $token)
            ->where('expirado', 0)
            ->orderBy('id')
            ->first();

        if (!$registro) {
            return self::fail('El token no es válido.', 422);
        }

        $firma = bin2hex(random_bytes(16)) . '.' . uniqid();

        try {
            Capsule::transaction(function () use ($registro, $oc, $idUsuario, $tipoFirma, $firma, $token, $idOcurrencia): void {
                $registro->expirado = 1;
                $registro->save();

                MaquinariaMantenimientoFirma::create([
                    'id_mantenimiento' => (int)$oc->id_mantenimiento,
                    'id_ocurrencia'    => $idOcurrencia,
                    'id_usuario'       => $idUsuario,
                    'tipo_firma'       => $tipoFirma,
                    'firma'            => $firma,
                    'fecha'            => date('Y-m-d H:i:s'),
                    'token_usado'      => (string)$token,
                ]);

                $estatus = ($tipoFirma === 'B') ? self::EST_EN_PROCESO : self::EST_FINALIZADO;
                MaquinariaMantenimientoOcurrencia::where('id', $idOcurrencia)->update(['estatus' => $estatus]);
            });
        } catch (\Throwable $e) {
            error_log('[MaquinariaBitacora] validarToken: ' . $e->getMessage());

            return self::fail('No se pudo registrar la firma.', 500);
        }

        return self::ok(($tipoFirma === 'B') ? 'VoBo registrado.' : 'Autorización registrada.');
    }

    /* ------------------------------------------------------------------ */
    /* Datos para PDF                                                      */
    /* ------------------------------------------------------------------ */

    public static function getDatosPdfRegistro(int $idOcurrencia): ?array
    {
        $registro = self::getRegistro($idOcurrencia);

        if (!$registro) {
            return null;
        }

        if ((int)$registro['estatus'] !== self::EST_FINALIZADO) {
            return null; // paridad: solo registros Finalizados
        }

        $registro['numero_registro'] = self::numeroDeRegistro($idOcurrencia);
        $registro['equipo']          = self::equipoInfo((int)$registro['id_equipo']);
        $registro['comentarios']     = self::getComentarios($idOcurrencia);

        return $registro;
    }

    /**
     * Datos del PDF general (ciclo completo): matriz legacy
     * `descargar-bitacora-pdf-general.php` → filas de actividades × una columna
     * por día, con SI/NO solo cuando el día ya se elaboró (estatus >= 1 o con
     * algún dato capturado); los días no habilitados quedan en blanco.
     * Última firma presente por tipo A/B/C en todo el ciclo.
     *
     * Nota de paridad: las filas de sección (título/subtítulo/nota) se dejan sin
     * celda en el legacy se pintaban "NO" por un efecto secundario de la fórmula.
     */
    public static function getDatosPdfGeneral(int $idMantenimiento): ?array
    {
        $mant = MaquinariaMantenimiento::find($idMantenimiento);

        if (!$mant) {
            return null;
        }

        $equipo = self::equipoInfo((int)$mant->id_equipo);

        if (!$equipo) {
            return null;
        }

        if ((int)$mant->frecuencia !== self::FREC_DIARIO) {
            return null; // paridad: PDF general solo para ciclo Diario
        }

        $ocurrencias = MaquinariaMantenimientoOcurrencia::where('id_mantenimiento', $idMantenimiento)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        if ($ocurrencias->isEmpty()) {
            return null;
        }

        $filas = [];
        $dias  = [];

        foreach ($ocurrencias as $i => $oc) {
            $acts = MaquinariaMantenimientoActividad::where('id_ocurrencia', (int)$oc->id)
                ->orderBy('id')
                ->get();

            if ($i === 0) {
                $filas = $acts->map(fn ($a) => [
                    'descripcion'   => (string)$a->descripcion,
                    'revision_tipo' => (string)$a->revision_tipo,
                    'es_seccion'    => !in_array((string)$a->revision_tipo, ['checkbox', 'select', 'texto', 'revisado'], true),
                ])->toArray();
            }

            $elab = ((int)$oc->estatus >= self::EST_EN_PROCESO);

            if (!$elab) {
                foreach ($acts as $a) {
                    if ((int)$a->resultado !== 0 || !empty($a->revisado_por) || !empty($a->cambiado_por_texto)) {
                        $elab = true;
                        break;
                    }
                }
            }

            $celdas = [];

            foreach ($acts as $j => $a) {
                $celdas[$j] = '';

                if (!$elab || $a->revision_tipo === 'titulo' || $a->revision_tipo === 'subtitulo' || $a->revision_tipo === 'nota') {
                    continue;
                }

                if ($a->revision_tipo === 'checkbox') {
                    $celdas[$j] = ((int)$a->resultado === 1) ? 'SI' : 'NO';
                } else {
                    $celdas[$j] = (!empty($a->revisado_por) || !empty($a->cambiado_por_texto)) ? 'SI' : 'NO';
                }
            }

            $dias[] = [
                'fecha'       => substr((string)$oc->fecha, 0, 10),
                'fecha_label' => formatearFecha($oc->fecha),
                'elaborado'   => $elab,
                'celdas'      => $celdas,
            ];
        }

        // Última firma presente por tipo A/B/C en todo el ciclo (orden por fecha).
        $firmasRows = MaquinariaMantenimientoFirma::where('id_mantenimiento', $idMantenimiento)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $ultimas = [];

        foreach ($firmasRows as $f) {
            $ultimas[(string)$f->tipo_firma] = $f;
        }

        $firmas = [];

        foreach (['A', 'B', 'C'] as $tipo) {
            $f = $ultimas[$tipo] ?? null;

            $firmas[$tipo] = $f ? [
                'nombre'      => self::nombreUsuario((int)$f->id_usuario),
                'fecha'       => (string)($f->fecha ?? ''),
                'fecha_label' => self::fechaHoraTexto((string)($f->fecha ?? '')),
                'firma'       => (string)$f->firma,
                'es_imagen'   => self::firmaEsImagen((string)$f->firma),
                'ruta'        => self::firmaEsImagen((string)$f->firma) ? (self::getFirmasDir() . (string)$f->firma) : '',
            ] : null;
        }

        return [
            'mantenimiento' => [
                'id'                 => (int)$mant->id,
                'orden'              => (int)$mant->orden,
                'orden_label'        => str_pad((string)(int)$mant->orden, 3, '0', STR_PAD_LEFT),
                'tipo_label'         => self::TIPO_LABELS[(int)$mant->tipo_mantenimiento] ?? '—',
                'frecuencia_label'   => ((int)$mant->frecuencia === self::FREC_DIARIO) ? self::FREC_LABELS[self::FREC_DIARIO] : 'N/A',
                'fecha_inicio'       => substr((string)$mant->fecha, 0, 10),
                'fecha_inicio_label' => formatearFecha($mant->fecha),
                'fecha_fin'          => (!empty($mant->fecha_fin))
                    ? substr((string)$mant->fecha_fin, 0, 10)
                    : ($dias[count($dias) - 1]['fecha'] ?? $dias[0]['fecha']),
                'fecha_fin_label'    => (!empty($mant->fecha_fin))
                    ? formatearFecha($mant->fecha_fin)
                    : ($dias[count($dias) - 1]['fecha_label'] ?? $dias[0]['fecha_label']),
                'falla_descripcion'  => (string)($mant->falla_descripcion ?? ''),
                'observaciones'      => (string)($mant->observaciones ?? ''),
                'creado_por'         => (int)$mant->creado_por,
                'creado_por_nombre'  => self::nombreUsuario((int)$mant->creado_por),
            ],
            'equipo' => $equipo,
            'filas'  => $filas,
            'dias'   => $dias,
            'firmas' => $firmas,
        ];
    }

    /** Posición del registro dentro de la secuencia global del equipo (1-based). */
    public static function numeroDeRegistro(int $idOcurrencia): int
    {
        $oc = MaquinariaMantenimientoOcurrencia::find($idOcurrencia);

        if (!$oc) {
            return 0;
        }

        $secuencia = self::secuenciaRegistros((int)$oc->id_mantenimiento);

        foreach ($secuencia as $i => $reg) {
            if ((int)$reg['id'] === $idOcurrencia) {
                return $i + 1;
            }
        }

        return 0;
    }

    /* ------------------------------------------------------------------ */
    /* Generación de ocurrencias (transaccional)                           */
    /* ------------------------------------------------------------------ */

    /** Hidrolavadora + Preventivo: Diario 7, Semanal 4, Horas 1 por bloque. */
    private static function generarOcurrenciasHidrolavadora(int $idMantenimiento, int $frecuencia, string $fechaInicio, string $estadoActual, float $costo): void
    {
        if ($frecuencia === self::FREC_DIARIO) {
            for ($i = 0; $i < 7; $i++) {
                $fecha = date('Y-m-d', strtotime("{$fechaInicio} +{$i} days"));
                $idOc  = self::insertarOcurrencia($idMantenimiento, $fecha, $estadoActual, $costo);

                foreach (self::checklistDiario() as $desc) {
                    self::insertarActividad($idMantenimiento, $idOc, $desc, 'checkbox');
                }
            }
        } elseif ($frecuencia === self::FREC_SEMANAL) {
            for ($i = 0; $i < 4; $i++) {
                $fecha = date('Y-m-d', strtotime("{$fechaInicio} +{$i} weeks"));
                $idOc  = self::insertarOcurrencia($idMantenimiento, $fecha, $estadoActual, $costo);

                foreach (self::checklistSemanal() as $desc) {
                    self::insertarActividad($idMantenimiento, $idOc, $desc, 'revisado');
                }
            }
        } elseif ($frecuencia === self::FREC_HORAS) {
            $bloques = [];

            foreach (self::checklistHoras() as $act) {
                $bloques[$act['bloque']][] = $act;
            }

            foreach ($bloques as $horas => $actividades) {
                $dias  = (int)floor((int)$horas / self::HORAS_POR_DIA);
                $fecha = date('Y-m-d', strtotime("{$fechaInicio} +{$dias} days"));
                $idOc  = self::insertarOcurrencia($idMantenimiento, $fecha, $estadoActual, $costo);

                foreach ($actividades as $act) {
                    self::insertarActividad($idMantenimiento, $idOc, $act['descripcion'], $act['tipo_campo'], $act['bloque']);
                }
            }
        }
    }

    /** Planta de emergencia + Preventivo: Diario 7, Semanal 4, Mensual 1. */
    public static function generarOcurrenciasPlanta(int $idMantenimiento, int $frecuencia, string $fechaInicio, string $estadoActual, float $costo): void
    {
        if ($frecuencia === self::FREC_DIARIO) {
            for ($i = 0; $i < 7; $i++) {
                $fecha = date('Y-m-d', strtotime("{$fechaInicio} +{$i} days"));
                $idOc  = self::insertarOcurrencia($idMantenimiento, $fecha, $estadoActual, $costo);

                foreach (self::checklistPlantaDiario() as $texto) {
                    self::insertarActividad($idMantenimiento, $idOc, $texto, 'checkbox');
                }
            }
        } elseif ($frecuencia === self::FREC_SEMANAL) {
            for ($i = 0; $i < 4; $i++) {
                $fecha = date('Y-m-d', strtotime("{$fechaInicio} +{$i} weeks"));
                $idOc  = self::insertarOcurrencia($idMantenimiento, $fecha, $estadoActual, $costo);

                foreach (self::checklistPlantaSemanal() as $texto) {
                    self::insertarActividad($idMantenimiento, $idOc, $texto, 'checkbox');
                }
            }
        } elseif ($frecuencia === self::FREC_MENSUAL) {
            $idOc = self::insertarOcurrencia($idMantenimiento, $fechaInicio, $estadoActual, $costo);

            foreach (self::checklistPlantaMensual() as $item) {
                $tipo = ($item['tipo'] === 'actividad') ? 'checkbox' : $item['tipo'];
                self::insertarActividad($idMantenimiento, $idOc, $item['texto'], $tipo);
            }
        }
    }

    public static function insertarOcurrencia(int $idMantenimiento, string $fecha, string $estadoActual, float $costo): int
    {
        return (int)Capsule::table('op_maquinaria_mantenimiento_ocurrencia')->insertGetId([
            'id_mantenimiento' => $idMantenimiento,
            'fecha'            => $fecha,
            'estado_actual'    => $estadoActual,
            'costo'            => $costo,
            'estatus'          => self::EST_PENDIENTE,
        ]);
    }

    public static function insertarActividad(int $idMantenimiento, int $idOcurrencia, string $descripcion, string $tipoCampo = 'checkbox', string $bloqueHoras = ''): void
    {
        Capsule::table('op_maquinaria_mantenimiento_actividad')->insert([
            'id_mantenimiento' => $idMantenimiento,
            'id_ocurrencia'    => $idOcurrencia,
            'descripcion'      => $descripcion,
            'resultado'        => 0,
            'observacion'      => '',
            'revision_tipo'    => $tipoCampo,
            'bloque_horas'     => $bloqueHoras,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Secuencia y firmas (helpers)                                        */
    /* ------------------------------------------------------------------ */

    /** Secuencia de registros de UNA solicitud: [['id','fecha','estatus'], …] ASC. */
    private static function secuenciaRegistros(int $idMantenimiento): array
    {
        return MaquinariaMantenimientoOcurrencia::where('id_mantenimiento', $idMantenimiento)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get(['id', 'fecha', 'estatus'])
            ->map(fn ($r) => ['id' => (int)$r->id, 'fecha' => (string)$r->fecha, 'estatus' => (int)$r->estatus])
            ->toArray();
    }

    /**
     * Mapa id_ocurrencia → ¿el registro anterior inmediato de la MISMA solicitud
     * está Finalizado? (los primeros siempre true).
     *
     * @param array<int> $idsMantenimientos
     * @return array<int, bool>
     */
    private static function mapaSecuencia(array $idsMantenimientos): array
    {
        $mapa = [];

        if (empty($idsMantenimientos)) {
            return $mapa;
        }

        $ocurrencias = MaquinariaMantenimientoOcurrencia::whereIn('id_mantenimiento', $idsMantenimientos)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get(['id', 'id_mantenimiento', 'estatus']);

        $prevFin  = [];
        $primero  = [];

        foreach ($ocurrencias as $oc) {
            $idM = (int)$oc->id_mantenimiento;
            $idOc= (int)$oc->id;

            if (!isset($primero[$idM])) {
                $mapa[$idOc] = true;
                $primero[$idM]= true;
            } else {
                $mapa[$idOc] = (bool)($prevFin[$idM] ?? false);
            }

            $prevFin[$idM] = ((int)$oc->estatus === self::EST_FINALIZADO);
        }

        return $mapa;
    }

    public static function secuenciaOk(int $idOcurrencia): bool
    {
        $oc = MaquinariaMantenimientoOcurrencia::find($idOcurrencia);

        if (!$oc) {
            return false;
        }

        $mapa = self::mapaSecuencia([(int)$oc->id_mantenimiento]);

        return (bool)($mapa[$idOcurrencia] ?? false);
    }

    /**
     * Valida la secuencia OBLIGATORIA del registro (anterior Finalizado) y la
     * secuencia interna de firmas: B exige A, C exige A y B.
     *
     * @throws \Exception
     */
    public static function validarSecuenciaFirmas(int $idOcurrencia, string $tipo): void
    {
        if (!self::secuenciaOk($idOcurrencia)) {
            throw new \Exception('Debes finalizar el registro anterior antes de procesar este.');
        }

        $firmas = MaquinariaMantenimientoFirma::where('id_ocurrencia', $idOcurrencia)
            ->pluck('tipo_firma')
            ->all();

        if ($tipo === 'B' && !in_array('A', $firmas, true)) {
            throw new \Exception('Primero debe firmar Elaboró.');
        }

        if ($tipo === 'C' && !in_array('B', $firmas, true)) {
            throw new \Exception('Primero deben completarse las firmas Elaboró y VoBo.');
        }
    }

    /** Estado de cada firma A/B/C para la pantalla de firmas. */
    private static function estadoFirmas($firmasRows, bool $esElaborador, bool $secOk, int $estatus, int $idUsuario, int $idPuesto): array
    {
        $tipos = $firmasRows->groupBy('tipo_firma');

        $tiene = fn (string $t): bool => $tipos->has($t) && $tipos->get($t)->isNotEmpty();

        $a = $tiene('A');
        $b = $tiene('B');
        $c = $tiene('C');

        return [
            'A' => [
                'label'       => 'Elaboró',
                'existe'      => $a,
                'disponible'  => (!$a && $esElaborador && $secOk),
                'via'         => 'dibujo',
                'via_dibujo'  => (!$a && $esElaborador && $secOk),
            ],
            'B' => [
                'label'       => 'Vo. Bueno',
                'existe'      => $b,
                'disponible'  => (!$b && $a && $secOk && ($idUsuario === self::USUARIO_VOBO || $idPuesto === self::PUESTO_VOBO)),
                'via_dibujo'  => (!$b && $a && $secOk && $idPuesto === self::PUESTO_VOBO && $idUsuario !== self::USUARIO_VOBO),
                'via_token'   => (!$b && $a && $secOk && $idUsuario === self::USUARIO_VOBO),
            ],
            'C' => [
                'label'      => 'Autorización',
                'existe'     => $c,
                'disponible' => (!$c && $b && $secOk && $idUsuario === self::USUARIO_AUTORIZACION),
                'via_token'  => (!$c && $b && $secOk && $idUsuario === self::USUARIO_AUTORIZACION),
            ],
            'listo'     => ($a && $b && $c),
            'firma_a_dibujada' => $a,
        ];
    }

    private static function iconoFirma(int $estatus, bool $a, bool $b, bool $c): string
    {
        if ($estatus === self::EST_FINALIZADO) {
            return 'verde';
        }

        if ($a && $b && !$c) {
            return 'azul';
        }

        return 'negro';
    }

    /* ------------------------------------------------------------------ */
    /* Armar filas / flags                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * PDF general del ciclo: alcanza con que el primer registro esté
     * finalizado (con la secuencia estricta, si hay uno finalizado el primero
     * lo está), como en el legacy.
     */
    private static function cicloTieneFinalizado(int $idMantenimiento): bool
    {
        return MaquinariaMantenimientoOcurrencia::where('id_mantenimiento', $idMantenimiento)
            ->where('estatus', self::EST_FINALIZADO)
            ->exists();
    }

    private static function armarRegistroDia($oc, array $firmas, array $comentarios, array $evidencias, array $secuencia, int $idUsuario): array
    {
        $idOc      = (int)$oc->id;
        $idManten  = (int)$oc->id_mantenimiento;
        $estatus   = (int)$oc->estatus;
        $frecuencia= $oc->frecuencia !== null ? (int)$oc->frecuencia : null;
        $firmaA    = (bool)($firmas[$idOc]['A'] ?? false);
        $firmaB    = (bool)($firmas[$idOc]['B'] ?? false);
        $firmaC    = (bool)($firmas[$idOc]['C'] ?? false);
        $secOk     = (bool)($secuencia[$idOc] ?? false);
        $esCreador = ((int)$oc->creado_por === $idUsuario);
        $costo     = (float)$oc->costo;

        return [
            'id'                   => $idOc,
            'id_mantenimiento'     => $idManten,
            'orden'                => (int)$oc->orden,
            'orden_label'          => str_pad((string)(int)$oc->orden, 3, '0', STR_PAD_LEFT),
            'fecha'                => substr((string)$oc->fecha, 0, 10),
            'fecha_label'          => formatearFecha($oc->fecha),
            'estado_actual'        => trim((string)($oc->estado_actual ?? '')) !== '' ? (string)$oc->estado_actual : '—',
            'costo'                => $costo,
            'costo_label'          => '$' . number_format($costo, 2),
            'tipo_mantenimiento'   => (int)$oc->tipo_mantenimiento,
            'tipo_label'           => self::TIPO_LABELS[(int)$oc->tipo_mantenimiento] ?? '—',
            'frecuencia'           => $frecuencia,
            'frecuencia_label'     => ($frecuencia !== null && isset(self::FREC_LABELS[$frecuencia])) ? self::FREC_LABELS[$frecuencia] : 'N/A',
            'estatus'              => $estatus,
            'estatus_label'        => self::ESTATUS_LABELS[$estatus] ?? '—',
            'estatus_clase'        => self::ESTATUS_CLASES[$estatus] ?? 'estado-pendiente',
            'color_fila'           => self::FILA_COLORES[$estatus] ?? '#ffffff',
            'falla_descripcion'    => (string)($oc->falla_descripcion ?? ''),
            'descripcion_equipo'   => (string)($oc->descripcion_equipo ?? ''),
            'maquinaria'           => (string)($oc->maquinaria ?? ''),
            'creado_por'           => (int)$oc->creado_por,
            'creador_nombre'       => self::nombreUsuario((int)$oc->creado_por),
            'es_elaborador'        => $esCreador,
            'secuencia_ok'         => $secOk,
            'firma_a'              => $firmaA,
            'firma_b'              => $firmaB,
            'firma_c'              => $firmaC,
            'icono_firma'          => self::iconoFirma($estatus, $firmaA, $firmaB, $firmaC),
            'bloqueado'            => $firmaA,
            'comentarios'          => (int)($comentarios[$idOc] ?? 0),
            'evidencias'           => (int)($evidencias[$idOc] ?? 0),
            'acciones'             => [
                'mantenimiento' => ($esCreador && !$firmaA && $estatus === self::EST_PENDIENTE && $secOk),
                'editar'        => ($esCreador && !$firmaA && $estatus !== self::EST_FINALIZADO && $secOk),
                'eliminar'      => ($esCreador && $estatus !== self::EST_FINALIZADO),
                'firmar'        => true,
                'pdf'           => ($estatus === self::EST_FINALIZADO),
                'pdf_general'   => ($frecuencia === self::FREC_DIARIO && self::cicloTieneFinalizado($idManten)),
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Helpers internos                                                    */
    /* ------------------------------------------------------------------ */

    /** @return array<int, array{A?:bool,B?:bool,C?:bool}> */
    private static function firmasPorOcurrencia(array $idsOcurrencias): array
    {
        $mapa = [];

        if (empty($idsOcurrencias)) {
            return $mapa;
        }

        $rows = MaquinariaMantenimientoFirma::whereIn('id_ocurrencia', $idsOcurrencias)
            ->get(['id_ocurrencia', 'tipo_firma']);

        foreach ($rows as $r) {
            $idOc = (int)$r->id_ocurrencia;
            $mapa[$idOc][(string)$r->tipo_firma] = true;
        }

        return $mapa;
    }

    /** @return array<int, int> */
    private static function contarPor(string $tabla, array $idsOcurrencias, string $columna): array
    {
        if (empty($idsOcurrencias)) {
            return [];
        }

        return Capsule::table($tabla)
            ->whereIn($columna, $idsOcurrencias)
            ->groupBy($columna)
            ->selectRaw($columna . ' as id_ocurrencia, COUNT(*) as total')
            ->get()
            ->reduce(function ($acc, $row) {
                $acc[(int)$row->id_ocurrencia] = (int)$row->total;

                return $acc;
            }, []);
    }

    private static function esElaborador($oc, int $idUsuario): bool
    {
        $mant = MaquinariaMantenimiento::find((int)$oc->id_mantenimiento);

        return $mant && ((int)$mant->creado_por === $idUsuario);
    }

    private static function puestoUsuario(int $idUsuario): ?int
    {
        $usuario = Usuario::find($idUsuario);

        return $usuario ? (int)$usuario->id_puesto : null;
    }

    private static function nombreUsuario(int $idUsuario): string
    {
        if ($idUsuario <= 0) {
            return 'Desconocido';
        }

        $usuario = Usuario::find($idUsuario);

        return $usuario ? (string)$usuario->nombre : ('Usuario #' . $idUsuario);
    }

    /** @return array<int, string> */
    private static function mapaNombres($idsUsuarios): array
    {
        $ids = collect($idsUsuarios)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Usuario::whereIn('id', $ids)->get(['id', 'nombre'])
            ->reduce(function ($acc, $u) {
                $acc[(int)$u->id] = (string)$u->nombre;

                return $acc;
            }, []);
    }

    private static function nombreEstacionEquipo(int $idMantenimiento): string
    {
        $mant = MaquinariaMantenimiento::find($idMantenimiento);

        if (!$mant) {
            return '';
        }

        return self::nombreEstacion((int)$mant->id_equipo);
    }

    private static function nombreEstacion(int $idEquipo): string
    {
        $info = self::equipoInfo($idEquipo);

        return $info['estacion'] ?? '';
    }

    /** Guarda el PNG dibujado en el signature pad. Devuelve el nombre o null. */
    private static function guardarFirmaPng(string $imagen): ?string
    {
        $base64 = $imagen;

        if (str_contains($base64, ',')) {
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }

        $datos = base64_decode($base64, true);

        if ($datos === false || $datos === '') {
            return null;
        }

        // Debe ser PNG o JPEG real (no HTML/PHP disfrazado).
        $esPng   = str_starts_with($datos, "\x89PNG\r\n\x1a\n");
        $esJpeg  = str_starts_with($datos, "\xFF\xD8\xFF");

        if (!$esPng && !$esJpeg) {
            return null;
        }

        $nombre = uniqid() . '.png';

        if (file_put_contents(self::getFirmasDir() . $nombre, $datos) === false) {
            return null;
        }

        return $nombre;
    }

    private static function firmaEsImagen(string $firma): bool
    {
        if ($firma === '') {
            return false;
        }

        $ext = strtolower(pathinfo($firma, PATHINFO_EXTENSION));

        return in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)
            && is_file(self::getFirmasDir() . $firma);
    }

    private static function firmaUrl(string $firma): ?string
    {
        return self::firmaEsImagen($firma) ? ('/uploads/firmas/maquinaria-mantenimiento/' . rawurlencode($firma)) : null;
    }

    private static function descargaUrl(string $nombre): string
    {
        return '/download?tipo=' . self::DOWNLOAD_TIPO . '&file=' . rawurlencode($nombre);
    }

    private static function borrarArchivosEvidencia(array $idsOcurrencias): void
    {
        if (empty($idsOcurrencias)) {
            return;
        }

        $archivos = MaquinariaMantenimientoEvidencia::whereIn('id_ocurrencia', $idsOcurrencias)
            ->pluck('archivo')
            ->all();

        $dir = self::getUploadDir();

        foreach ($archivos as $archivo) {
            if (!empty($archivo) && is_file($dir . $archivo)) {
                @unlink($dir . $archivo);
            }
        }
    }

    /** Borra los PNG de firma en disco de las ocurrencias indicadas (sólo nombres de archivo). */
    private static function borrarArchivosFirma(array $idsOcurrencias): void
    {
        if (empty($idsOcurrencias)) {
            return;
        }

        $archivos = MaquinariaMantenimientoFirma::whereIn('id_ocurrencia', $idsOcurrencias)
            ->pluck('firma')
            ->all();

        $dir = self::getFirmasDir();

        foreach ($archivos as $archivo) {
            $archivo = (string)$archivo;

            if ($archivo === '' || !preg_match('/^[\w.-]+\.(png|jpe?g|pdf)$/i', $archivo)) {
                continue;
            }

            if (is_file($dir . $archivo)) {
                @unlink($dir . $archivo);
            }
        }
    }

    private static function mimeSeguro(string $tmpPath): bool
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return false;
        }

        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        return $mime !== false && $mime !== '' && !in_array($mime, self::MIME_PELIGROSOS, true);
    }

    private static function esFechaValida(string $fecha): bool
    {
        if ($fecha === '' || $fecha === '0000-00-00') {
            return false;
        }

        $d = \DateTime::createFromFormat('Y-m-d', $fecha);

        return $d !== false && $d->format('Y-m-d') === $fecha;
    }

    private static function fechaHoraTexto(string $fechaHora): string
    {
        $partes = explode(' ', trim($fechaHora));
        $fecha  = $partes[0] ?? '';
        $hora   = $partes[1] ?? '';

        $fechaTxt = formatearFecha($fecha);
        $horaTxt  = ($hora !== '' && strtotime($fechaHora) !== false) ? date('g:i a', strtotime($fechaHora)) : '';

        return trim($fechaTxt . ($horaTxt !== '' ? ', ' . $horaTxt : ''));
    }

    private static function nombreMes(int $mes): string
    {
        $mapa = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return $mapa[$mes] ?? '';
    }

    private static function ok(string $message, array $extra = []): array
    {
        return array_merge(['success' => true, 'message' => $message, 'code' => 200], $extra);
    }

    private static function fail(string $message, int $code = 422, array $extra = []): array
    {
        return array_merge(['success' => false, 'message' => $message, 'code' => $code], $extra);
    }

    /* ------------------------------------------------------------------ */
    /* API nominal (nombres del contrato FASE B3) — delegación delgada     */
    /* ------------------------------------------------------------------ */

    public static function obtenerEquipo(int $idEquipo): ?array
    {
        return self::equipoInfo($idEquipo);
    }

    /** Maestro de mantenimiento con los datos del equipo para el encabezado. */
    public static function obtenerMantenimiento(int $idMantenimiento): ?array
    {
        $mant = MaquinariaMantenimiento::find($idMantenimiento);

        if (!$mant || !self::equipo((int)$mant->id_equipo)) {
            return null;
        }

        return [
            'id'                 => (int)$mant->id,
            'id_equipo'          => (int)$mant->id_equipo,
            'tipo_mantenimiento' => (int)$mant->tipo_mantenimiento,
            'tipo_label'         => self::TIPO_LABELS[(int)$mant->tipo_mantenimiento] ?? '—',
            'frecuencia'         => $mant->frecuencia !== null ? (int)$mant->frecuencia : null,
            'frecuencia_label'   => ($mant->frecuencia !== null && isset(self::FREC_LABELS[(int)$mant->frecuencia]))
                ? self::FREC_LABELS[(int)$mant->frecuencia]
                : 'N/A',
            'orden'              => (int)$mant->orden,
            'fecha'              => substr((string)$mant->fecha, 0, 10),
            'estatus'            => (int)$mant->estatus,
            'costo'              => (float)$mant->costo_mantenimiento,
            'falla_descripcion'  => (string)($mant->falla_descripcion ?? ''),
            'observaciones'      => (string)($mant->observaciones ?? ''),
            'creado_por'         => (int)$mant->creado_por,
            'equipo'             => self::equipoInfo((int)$mant->id_equipo),
        ];
    }

    public static function obtenerOcurrencia(int $idOcurrencia): ?array
    {
        return self::getRegistro($idOcurrencia);
    }

    public static function obtenerRegistroCompleto(int $idOcurrencia): ?array
    {
        return self::getRegistro($idOcurrencia);
    }

    public static function usuarioEsElaborador(int $idOcurrencia): bool
    {
        $oc = MaquinariaMantenimientoOcurrencia::find($idOcurrencia);

        return $oc !== null && self::esElaborador($oc, self::usuarioId());
    }

    /** Siguiente número de orden dentro del equipo (max + 1). */
    public static function calcularSiguienteOrden(int $idEquipo): int
    {
        return ((int)Capsule::table('op_maquinaria_mantenimiento')
            ->where('id_equipo', $idEquipo)
            ->max('orden')) + 1;
    }

    /**
     * Genera las ocurrencias de un mantenimiento según su frecuencia.
     * Fuera de Hidrolavadora/Planta + Preventivo sólo se genera 1 ocurrencia.
     */
    public static function generarOcurrencias(
        int $idMantenimiento,
        string $maquinaria,
        int $frecuencia,
        string $fechaInicio,
        string $estadoActual,
        float $costo
    ): void {
        if (!self::usaFrecuencia($maquinaria, self::TIPO_PREVENTIVO)) {
            self::insertarOcurrencia($idMantenimiento, $fechaInicio, $estadoActual, $costo);

            return;
        }

        if (self::esPlantaEmergencia($maquinaria)) {
            self::generarOcurrenciasPlanta($idMantenimiento, $frecuencia, $fechaInicio, $estadoActual, $costo);
        } else {
            self::generarOcurrenciasHidrolavadora($idMantenimiento, $frecuencia, $fechaInicio, $estadoActual, $costo);
        }
    }

    /** Bloques por horas de la Hidrolavadora: 50/100/300/500 → 1 ocurrencia por bloque. */
    public static function generarOcurrenciasHoras(int $idMantenimiento, string $fechaInicio, string $estadoActual, float $costo): void
    {
        self::generarOcurrenciasHidrolavadora($idMantenimiento, self::FREC_HORAS, $fechaInicio, $estadoActual, $costo);
    }

    /** Checklist que se precarga en el formulario "Nuevo" según equipo/tipo/frecuencia. */
    public static function precargarChecklist(string $maquinaria, int $tipoMantenimiento, ?int $frecuencia): array
    {
        return self::checklistPreview($maquinaria, $tipoMantenimiento, $frecuencia);
    }

    public static function obtenerOcurrenciasDelDia(int $idEquipo, string $fecha): array
    {
        return self::getDia($idEquipo, $fecha);
    }

    public static function contarOcurrenciasDelDia(int $idEquipo, string $fecha): int
    {
        $fecha = substr($fecha, 0, 10);

        return (int)MaquinariaMantenimientoOcurrencia::query()
            ->join('op_maquinaria_mantenimiento as m', 'm.id', '=', 'op_maquinaria_mantenimiento_ocurrencia.id_mantenimiento')
            ->where('m.id_equipo', $idEquipo)
            ->where('op_maquinaria_mantenimiento_ocurrencia.fecha', $fecha)
            ->count();
    }

    public static function listaSecuenciaRegistros(int $idMantenimiento): array
    {
        return self::secuenciaRegistros($idMantenimiento);
    }

    public static function validarSecuenciaOcurrencia(int $idOcurrencia): bool
    {
        return self::secuenciaOk($idOcurrencia);
    }

    public static function obtenerUsuariosEstacion(int $idEstacion): array
    {
        return self::usuariosEstacion($idEstacion);
    }

    public static function editarCostoOcurrencia(int $idOcurrencia, float $costo): array
    {
        return self::editarCostoRegistro($idOcurrencia, $costo);
    }

    public static function actualizarActividad(array $data): array
    {
        return self::guardarActividad($data);
    }

    public static function firmaElaboroOcurrencia(int $idOcurrencia, string $imagen): array
    {
        return self::firmaElaboro($idOcurrencia, $imagen);
    }

    public static function solicitarTokenOcurrencia(int $idOcurrencia, string $via, string $tipoFirma): array
    {
        return self::solicitarToken($idOcurrencia, $via, $tipoFirma);
    }

    public static function validarTokenFirmaOcurrencia(int $idOcurrencia, string $tipoFirma, int $token): array
    {
        return self::validarToken($idOcurrencia, $tipoFirma, $token);
    }

    public static function subirEvidenciaOcurrencia(int $idOcurrencia, array $file): array
    {
        return self::subirEvidencia($idOcurrencia, $file);
    }

    public static function agregarComentarioOcurrencia(int $idOcurrencia, array $data): array
    {
        return self::agregarComentario($idOcurrencia, $data);
    }

    public static function obtenerComentariosOcurrencia(int $idOcurrencia): array
    {
        return self::getComentarios($idOcurrencia);
    }

    /**
     * Cambio directo de estatus (0/1/2) con las mismas reglas del flujo:
     * sólo el elaborador, nunca retroceder desde Finalizado ni saltar la secuencia.
     */
    public static function actualizarEstatusOcurrencia(int $idOcurrencia, int $estatus): array
    {
        if (!isset(self::ESTATUS_LABELS[$estatus])) {
            return self::fail('Estatus no válido.', 422);
        }

        $idUsuario = self::usuarioId();
        $oc        = MaquinariaMantenimientoOcurrencia::find($idOcurrencia);

        if (!$oc) {
            return self::fail('El registro no existe.', 404);
        }

        if (!self::esElaborador($oc, $idUsuario)) {
            return self::fail('Solo el usuario que elaboró el registro puede cambiar el estatus.', 403);
        }

        if ((int)$oc->estatus === self::EST_FINALIZADO) {
            return self::fail('No se puede cambiar el estatus de un registro Finalizado.', 422);
        }

        if ($estatus > self::EST_PENDIENTE && !self::secuenciaOk($idOcurrencia)) {
            return self::fail('Debes finalizar el registro anterior antes de procesar este.', 409);
        }

        if ($estatus === self::EST_FINALIZADO) {
            $faltan = MaquinariaMantenimientoActividad::where('id_ocurrencia', $idOcurrencia)
                ->where('resultado', '!=', 1)
                ->count();

            if ($faltan > 0) {
                return self::fail('Debes completar todo el checklist antes de finalizar.', 422, ['faltan' => $faltan]);
            }

            $firmaA = MaquinariaMantenimientoFirma::where('id_ocurrencia', $idOcurrencia)
                ->where('tipo_firma', 'A')
                ->exists();

            if (!$firmaA) {
                return self::fail('Debes registrar la firma Elaboró antes de finalizar.', 422);
            }
        }

        try {
            $oc->estatus = $estatus;
            $oc->save();
        } catch (\Throwable $e) {
            error_log('[MaquinariaBitacora] actualizarEstatusOcurrencia: ' . $e->getMessage());

            return self::fail('No se pudo actualizar el estatus.', 500);
        }

        return self::ok('Estatus actualizado.');
    }

    /** ¿Puede eliminarse el registro (quién lo elaboró y no está Finalizado)? */
    public static function registroEliminable(int $idOcurrencia): bool
    {
        $idUsuario = self::usuarioId();
        $oc        = MaquinariaMantenimientoOcurrencia::find($idOcurrencia);

        if (!$oc) {
            return false;
        }

        if ((int)$oc->estatus === self::EST_FINALIZADO) {
            return false;
        }

        $mant = MaquinariaMantenimiento::find((int)$oc->id_mantenimiento);

        return $mant && ((int)$mant->creado_por === $idUsuario);
    }
}
