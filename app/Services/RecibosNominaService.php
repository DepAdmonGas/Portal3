<?php

namespace App\Services;

use App\Models\Operativo\OpReciboNominaV2;
use App\Models\Operativo\OpReciboNominaV2Comentario;
use App\Models\Operativo\OpReciboNominaV2Puntaje;
use App\Models\Operativo\OpReciboNominaV2Acuse;
use App\Models\Operativo\OpReciboNominaPeriodoAcuse;
use App\Models\Operativo\OpReciboNominaAguinaldo;
use App\Models\Operativo\ReciboNominaV2PrimaVacacional;
use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPuestos;
use App\Models\Operativo\RhLocalidad;
use App\Core\Auth;
use App\Core\Session;
use App\Models\ModuloConfig;
use App\Services\ModuleStationService;
use DateTime;

class RecibosNominaService
{
    public const MODULE_KEY = 'recibos-nomina';

    private const KEY_PERMISOS = 'recursos-humanos';

    /** Clave del modulo en tb_modulos_config, usada por ModuleStationService. */
    public const KEY_MODULO = 'recibos-nomina';

    /**
     * Los usuarios que la empresa informo como "personal de departamento" NO son
     * una estacion mas del selector: solo tienen acceso a su propio
     * departamento.
     *
     * La llave es tb_usuarios.id_puesto y el valor es el id de
     * op_rh_localidades del departamento que les corresponde:
     *
     *   id_puesto  5 (Gestoria)  -> 13 Departamento Gestion
     *   id_puesto 15 (Juridico)  -> 18 Departamento Juridico
     *   id_puesto  8 (Mantenim.) -> 15 Departamento Mantenimiento
     *   id_puesto  2 (Sistemas)  -> 16 Departamento Sistemas
     *
     * Los cuatro departamentos estan en departamentos_soportados de
     * tb_modulos_config ([9, 11, 13, 15, 16, 18]), asi que el id se puede usar
     * tal cual en el selector y en las consultas por id_estacion.
     *
     * Cualquier otro puesto, o cualquier otra combinacion de id_gas + id_puesto,
     * sigue viendo el selector completo tal como funcionaba antes.
     */
    private const DEPARTAMENTOS_POR_PUESTO = [
        5  => 13,
        15 => 18,
        8  => 15,
        2  => 16,
    ];

    /**
     * Puestos de corporativo que pueden cerrar la actividad de CUALQUIER
     * estacion o departamento que tengan asignado en el selector, no solo de la
     * suya.
     *
     * Direccion de Operaciones (puesto 13) centraliza el cierre de todas las
     * estaciones y de todos los departamentos del modulo, por eso el boton de
     * "Finalizar actividad" tambien le aparece al seleccionar cualquier otra
     * localidad.
     */
    private const PUESTOS_FINALIZAN_TODAS = [13];

    /**
     * Las dos reglas anteriores (departamento propio y cierre de cualquier
     * localidad) solo aplican a usuarios de corporativo, cuyo id_gas es
     * Comodines (8).
     */
    private const ID_GAS_CORPORATIVO = 8;

    /**
     * Textos y clases de los badges de la pantalla de Revisión.
     *
     * Viven aquí para que revision.php no tenga que decidir colores ni
     * textos: la vista solo imprime lo que el service le devuelve.
     */
    private const BADGES_PRIMA = [
        0 => ['clase' => 'badge rounded-pill bg-warning text-dark', 'texto' => 'Pendiente'],
        1 => ['clase' => 'badge rounded-pill bg-danger', 'texto' => 'No se realizó el pago'],
        2 => ['clase' => 'badge rounded-pill bg-success', 'texto' => 'Se realizó el pago'],
    ];

    private const BADGES_ESTATUS = [
        'Finalizado' => ['clase' => 'badge rounded-pill bg-success', 'texto' => 'Finalizado'],
        'En proceso' => ['clase' => 'badge rounded-pill bg-warning', 'texto' => 'En proceso'],
        'Pendiente'  => ['clase' => 'badge rounded-pill bg-danger', 'texto' => 'Pendiente'],
    ];

    private const BADGES_FINALIZACION = [
        'finalizado' => [
            'clase' => 'badge rounded-pill bg-success py-2 px-3',
            'texto' => 'La actividad fue finalizada',
        ],
        'pendiente' => [
            'clase' => 'badge rounded-pill bg-warning text-dark py-2 px-3',
            'texto' => 'La estación no ha finalizado su actividad',
        ],
    ];

    /**
     * id_gas del usuario con el que se armo la sesion.
     *
     * tb_usuarios.id_gas apunta a tb_estaciones.id y NO viene en la sesion, asi
     * que se resuelve desde el modelo del usuario.
     */
    public static function getUsuarioIdGas(): int
    {
        $sessionUsuario = Session::get('usuario') ?? [];
        if (isset($sessionUsuario['id_gas'])) {
            return (int)$sessionUsuario['id_gas'];
        }

        $usuario = Auth::user();

        return $usuario ? (int)$usuario->id_gas : 0;
    }

    /** tb_usuarios.id_puesto, tampoco presente en la sesion. */
    public static function getUsuarioIdPuesto(): int
    {
        $sessionUsuario = Session::get('usuario') ?? [];
        if (isset($sessionUsuario['id_puesto'])) {
            return (int)$sessionUsuario['id_puesto'];
        }

        $usuario = Auth::user();

        return $usuario ? (int)$usuario->id_puesto : 0;
    }

    /**
     * Id de op_rh_localidades del departamento al que el usuario esta atado, o
     * null cuando el usuario conserva el comportamiento anterior (selector
     * completo con estaciones y departamentos).
     */
    public static function getDepartamentoRestringido(): ?int
    {
        if (self::getUsuarioIdGas() !== self::ID_GAS_CORPORATIVO) {
            return null;
        }

        $idPuesto = self::getUsuarioIdPuesto();

        return self::DEPARTAMENTOS_POR_PUESTO[$idPuesto] ?? null;
    }

    /**
     * true cuando el puesto del usuario centraliza el cierre de actividades, en
     * cuyo caso el boton de "Finalizar actividad" le aparece en cualquier
     * estacion o departamento que tenga asignado.
     */
    public static function finalizaTodasLasLocalidades(): bool
    {
        return self::getUsuarioIdGas() === self::ID_GAS_CORPORATIVO
            && in_array(self::getUsuarioIdPuesto(), self::PUESTOS_FINALIZAN_TODAS, true);
    }

    /** true cuando el usuario solo puede ver y finalizar su propio departamento. */
    public static function esUsuarioDepartamento(): bool
    {
        return self::getDepartamentoRestringido() !== null;
    }

    /**
     * Ids de op_rh_localidades que el usuario puede consultar y finalizar en
     * este modulo.
     *
     * Para el personal de departamento la lista es exactamente una: la suya.
     * Para el resto son las estaciones y los departamentos que el usuario
     * tiene asignados, que es lo que ModuleStationService le muestra en el
     * selector y por lo tanto lo unico que el legacy dejaba finalizar.
     */
    public static function getIdsLocalidadPermitidos(): array
    {
        $restringido = self::getDepartamentoRestringido();
        if ($restringido !== null) {
            return [$restringido];
        }

        $ids = array_merge(
            array_column(ModuleStationService::getAvailableStations(self::KEY_MODULO), 'id'),
            array_column(ModuleStationService::getAvailableDepartments(self::KEY_MODULO), 'id')
        );

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Indica si la localidad (estacion o departamento) esta dentro de lo que el
     * usuario puede ver y finalizar en este modulo.
     */
    public static function esLocalidadPermitida(int $idLocalidad): bool
    {
        if ($idLocalidad <= 0) {
            return false;
        }

        return in_array($idLocalidad, self::getIdsLocalidadPermitidos(), true);
    }

    /**
     * Estaciones que el modulo soporta, ya expresadas en op_rh_localidades.
     *
     * tb_modulos_config guarda estaciones_soportadas en el espacio de
     * tb_estaciones, asi que se traducen con el mismo numlista que usa el resto
     * del modulo. Se cachea porque no cambia durante el request.
     */
    private static function getIdsEstacionSoportadas(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $mc = ModuloConfig::where('modulo_key', self::KEY_MODULO)->where('activo', true)->first();
        $soportadas = $mc?->estaciones_soportadas ?? [];

        if (empty($soportadas)) {
            return $cache = [];
        }

        return $cache = array_values(array_unique(array_map('intval', MultiestacionService::convertIds(
            $soportadas,
            MultiestacionService::TABLA_ESTACIONES,
            MultiestacionService::TABLA_RH_LOCALIDADES
        ))));
    }

    /**
     * Ids de op_rh_localidades de las localidades PROPIAS del usuario, es decir
     * aquellas sobre las que le corresponde finalizar la actividad.
     *
     * No es lo mismo que getIdsLocalidadPermitidos(): un usuario multiestacion
     * puede consultar y descargar el detalle de varias estaciones, pero el
     * boton de "Finalizar actividad" solo le pertenece a la suya. Resuelve asi:
     *
     *   - Direccion de Operaciones (puesto 13): todas las localidades que tiene
     *     asignadas, porque centraliza el cierre de estaciones y departamentos.
     *   - Personal de departamento: unicamente su departamento.
     *   - Resto: unicamente la estacion derivada de tb_usuarios.id_gas.
     *
     * Devuelve vacio cuando la estación del usuario no es una de las que el
     * modulo soporta, en cuyo caso no tiene actividad propia que finalizar.
     */
    public static function getIdsLocalidadPropias(): array
    {
        $deptoRestringido = self::getDepartamentoRestringido();
        if ($deptoRestringido !== null) {
            return [$deptoRestringido];
        }

        if (self::finalizaTodasLasLocalidades()) {
            return self::getIdsLocalidadPermitidos();
        }

        $idGas = self::getUsuarioIdGas();
        if ($idGas <= 0) {
            return [];
        }

        $propias = array_values(array_unique(array_map('intval', MultiestacionService::convertIds(
            [$idGas],
            MultiestacionService::TABLA_ESTACIONES,
            MultiestacionService::TABLA_RH_LOCALIDADES
        ))));

        return array_values(array_intersect($propias, self::getIdsEstacionSoportadas()));
    }

    /** true cuando el usuario pertenece de verdad a la localidad indicada. */
    public static function esLocalidadPropia(int $idLocalidad): bool
    {
        if ($idLocalidad <= 0) {
            return false;
        }

        return in_array($idLocalidad, self::getIdsLocalidadPropias(), true);
    }

    public static function getPermisos(): array
    {
        $sessionUsuario = Session::get('usuario') ?? [];
        $multiestacion = !empty($sessionUsuario['multiestacion']);
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);
        $idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);

        $permisosDb = ModuloDptoOperativoService::permisosSesion(self::KEY_PERMISOS);
        if (empty($permisosDb) && $idUsuario) {
            ModuloDptoOperativoService::guardarEnSesion($idUsuario);
            $permisosDb = ModuloDptoOperativoService::permisosSesion(self::KEY_PERMISOS);
        }

        $deptoRestringido = self::getDepartamentoRestringido();

        return [
            'multiestacion'  => $multiestacion,
            'id_usuario'     => $idUsuario,
            'id_estacion'    => $idEstacion,
            'id_gas'         => self::getUsuarioIdGas(),
            'id_puesto'      => self::getUsuarioIdPuesto(),
            'esMexdesa'   => $idUsuario === 354,
            'es_director'    => in_array($idUsuario, [19, 318], true),
            // Departamento unico al que tiene acceso, o null si no aplica.
            'depto_restringido' => $deptoRestringido,
            'es_departamento'   => $deptoRestringido !== null,
            'puedeLeer'      => !empty($permisosDb['leer']),
            'puedeCrear'     => !empty($permisosDb['crear']),
            'puedeEditar'    => !empty($permisosDb['editar']),
            'puedeEliminar'  => !empty($permisosDb['eliminar']),
            'puedeDescargar' => !empty($permisosDb['descargar']),
        ];
    }

    /**
     * Conteos de pendientes que ModuleStationService usa para pintar los
     * contadores junto a cada opcion del selector.
     *
     * Se cuenta un periodo como PENDIENTE cuando ya tiene registros de nomina
     * cargados pero la estacion todavia no lo finalized, es decir, cuando no
     * existe el puntaje "Recibos Estacion" para esa combinacion de
     * (anio, periodo, descripcion). Asi el numero que aparece junto a cada
     * estacion o departamento indica cuantos periodos faltan por finalizar y
     * no cuantos registros hay.
     *
     * Solo se toma en cuenta el anio en curso, que es el rango con el que
     * trabaja el modulo.
     */
    public static function getPendientesConteo(): array
    {
        $pendientes = ['total' => 0];

        $estacionIds = [];
        foreach (ModuleStationService::getAvailableStations(self::KEY_MODULO) as $s) {
            $id = (int)$s['id'];
            $estacionIds[] = $id;
            $pendientes['estacion_' . $id] = 0;
        }

        $deptoIds = [];
        foreach (ModuleStationService::getAvailableDepartments(self::KEY_MODULO) as $d) {
            $id = (int)$d['id'];
            $deptoIds[] = $id;
            $pendientes['depto_' . $id] = 0;
        }

        $permitidos = array_values(array_unique(array_merge($estacionIds, $deptoIds)));
        if (empty($permitidos)) {
            return $pendientes;
        }

        $year = (int)date('Y');

        // Periodos que ya tienen registros cargados.
        $grupos = OpReciboNominaV2::query()
            ->where('year', $year)
            ->whereIn('id_estacion', $permitidos)
            ->groupBy('id_estacion', 'no_semana_quincena', 'descripcion')
            ->selectRaw('id_estacion, no_semana_quincena, descripcion, COUNT(*) as total')
            ->get();

        if ($grupos->isEmpty()) {
            return $pendientes;
        }

        // Periodos que la estacion ya finalizo.
        $finalizados = OpReciboNominaV2Puntaje::query()
            ->where('year', $year)
            ->where('actividad', 'Recibos Estacion')
            ->whereIn('id_estacion', array_values(array_unique(array_column($grupos->toArray(), 'id_estacion'))))
            ->get(['id_estacion', 'no_semana_quincena', 'descripcion'])
            ->map(fn($r) => self::clavePeriodo((int)$r->id_estacion, (int)$r->no_semana_quincena, (string)$r->descripcion))
            ->flip();

        foreach ($grupos as $g) {
            if ($finalizados->has(self::clavePeriodo((int)$g->id_estacion, (int)$g->no_semana_quincena, (string)$g->descripcion))) {
                continue;
            }

            $id = (int)$g->id_estacion;
            $key = in_array($id, $deptoIds, true) ? 'depto_' . $id : 'estacion_' . $id;

            if (!array_key_exists($key, $pendientes)) {
                continue;
            }

            $pendientes[$key]++;
            $pendientes['total']++;
        }

        return $pendientes;
    }

    /** Identificador unico de un periodo (localidad + periodo + tipo). */
    private static function clavePeriodo(int $idLocalidad, int $periodo, string $descripcion): string
    {
        return $idLocalidad . '|' . $periodo . '|' . $descripcion;
    }

    /**
     * Legacy recursos-humanos-recibo-nomina-year.php:924
     *
     *   if($id == 1 || $id == 2 || $id == 3 || $id == 4 || $id == 5 || $id == 9 || $id == 14)
     *
     * Es una lista blanca de ids de op_rh_localidades: las localidades de esa
     * lista liquidan por SEMANA y todas las demas por QUINCENA. El legacy no
     * decide por nombre ni por puesto, y la misma regla cubre estaciones y
     * departamentos porque ambos son filas de op_rh_localidades.
     *
     * Quedan fuera por quincena: Esmegas (6), Xochimilco (7), Direccion de
     * Operaciones (11), Gestion (13), Mantenimiento (15), Sistemas (16) y
     * Juridico (18). Autolavado (9) liquida por semana.
     *
     * Coincide con recibo-nomina-estaciones-year.php:127, donde los puestos
     * Sistemas, Gestoria, Mantenimiento y Departamento Juridico van a quincena.
     */
    public const LOCALIDADES_SEMANALES = [1, 2, 3, 4, 5, 9, 14];

    /**
     * Determina si una localidad liquida por semana o por quincena.
     *
     * @param int $idLocalidad Id de op_rh_localidades. Funciona igual para
     *                        estaciones y para departamentos soportados.
     */
    public static function esSemanal(int $idLocalidad): bool
    {
        return in_array($idLocalidad, self::LOCALIDADES_SEMANALES, true);
    }

/**
     * Ultima semana ISO del anio.
     *
     * Replica UltimaSemanaYear() del legacy (lista-nomina-semanas.php:575):
     * toma el 31 de diciembre y, si cae en la semana 01 del anio siguiente,
     * retrocede una semana.
     *
     * OJO: esta funcion NO es el total de semanas del dropdown. El legacy usa
     * dos calculos distintos y solo coinciden de 2026 en adelante. Para 2025
     * el dropdown ofrece 53 semanas (la 53 arranca el 25 dic 2025) pero aqui
     * el "ultimo periodo" sigue siendo la 52. Ese valor es el que decide si se
     * habilitan los extras de la ultima semana, entre ellos los aguinaldos,
     * asi que se conserva tal cual para no cambiar cuando aparece el aguinaldo.
 *
     * @see self::getTotalSemanasPeriodo() para el total del selector.
 */
public static function getUltimaSemanaYear(int $year): int
    {
        $ultimoDia = new DateTime("$year-12-31");
        if ($ultimoDia->format('W') === '01') {
            $ultimoDia->modify('-1 week');
        }
        return (int)$ultimoDia->format('W');
    }

/**
     *primer jueves del anio con el que arranca la semana 1.
     *
     * Replica el armado del dropdown del legacy
     * (lista-nomina-semanas.php:736-747): si el 1 de enero no cae en jueves
     * se salta al jueves mas cercano. Para 2025 el legacy usa "last thursday",
     * que cae el 26 de diciembre de 2024 y hace que ese anio tenga 53
     * semanas; para el resto usa "next thursday".
     */
private static function inicioSemana1(int $year): DateTime
    {
        $inicio = new DateTime("$year-01-01");

        if ($inicio->format('N') !== '4') {
            if ($year === 2025) {
                $inicio->modify('last thursday');
            } else {
                $inicio->modify('next thursday');
            }
        }

        return $inicio;
    }

/**
     * Total de semanas que ofrece el selector de un anio.
     *
     * Cuenta semanas completas de jueves a miercoles hasta el 31 de diciembre,
     * igual que el bucle del legacy. Resultado: 2024 = 52, 2025 = 53,
     * 2026 = 53, 2027 = 52.
     */
public static function getTotalSemanasPeriodo(int $year): int
    {
        $inicio = self::inicioSemana1($year);
        $fin    = new DateTime("$year-12-31");
        $total  = 0;

        while ($inicio <= $fin) {
            $inicio->modify('+7 days');
            $total++;
    }

        return $total;
    }

    /**
     * Rango de fechas de una semana.
     *
     * La semana N arranca exactamente 7*(N-1) dias despues del primer jueves
     * del anio, que es como el legacy encadena sus semanas.
     */
    public static function getRangoFechasSemana(int $year, int $semana): array
    {
        $inicio = self::inicioSemana1($year);
   $inicio->modify('+' . (($semana - 1) * 7) . ' days');

        $fin = (clone $inicio)->modify('+6 days');

        return [
    'inicio' => $inicio->format('Y-m-d'),
       'fin'    => $fin->format('Y-m-d'),
  // El mes lo define el LUNES de la semana ISO (obtenerMesPorSemana),
            // no el jueves con el que arranca el rango visible. Por eso la
            // semana 1 se guarda con mes = 12 del ano anterior, tal como esta
            // en la base.
            'mes'    => self::obtenerMesPorSemana($year, $semana)
        ];
    }

    /**
     * Valida si un periodo ya puede recibir personal en la nomina.
     *
     * Paridad con el guard que envuelve todo el bloque del legacy:
     *   -Semana:   lista-nomina-semanas.php:385  -> $finSemanaDay  <= hoy + 2 dias
     *   - Quincena: lista-nomina-quincenas.php:363 -> $finQuincenaDay <= hoy + 5 dias
     * y en ambos: 2024 <= $year <= anio actual.
     *
     * Sin este guard el modulo insertaba personal aunque se eligiera una
     * semana o quincena que todavia no habia comenzado.
     */
    public static function periodoAceptaInsercion(int $year, int $periodo, bool $esSemanal): bool
    {
        $anioActual = (int)date('Y');
        if ($year < 2024 || $year > $anioActual) {
            return false;
        }

        $rango = $esSemanal
            ? self::getRangoFechasSemana($year, $periodo)
            : self::getRangoFechasQuincena($year, $periodo);

        // Dias de cortesia que el legacy suma a la fecha actual.
        $diasCortesia = $esSemanal ? 2 : 5;
        $limite = (new DateTime('today'))->modify("+{$diasCortesia} days");

        return new DateTime($rango['fin']) <= $limite;
    }

    public static function getRangoFechasQuincena(int $year, int $quincena): array
    {
        $mes = (int)ceil($quincena / 2);
        $primerDia = mktime(0, 0, 0, $mes, 1, $year);

        if ($quincena % 2 === 1) {
            $inicio = date('Y-m-01', $primerDia);
            $fin = date('Y-m-15', $primerDia);
        } else {
            $inicio = date('Y-m-16', $primerDia);
            $fin = date('Y-m-t', $primerDia);
        }

        return [
            'inicio' => $inicio,
            'fin'    => $fin,
            'mes'    => $mes
        ];
    }

    public static function sincronizarPersonal(int $idEstacion, int $year, int $periodo, string $descripcion, int $mes): void
    {
        $esSemanal = $descripcion === 'Semana';

        // Guard del legacy: no se captura personal si el periodo todavia no
        // ha vencido (ver periodoAceptaInsercion).
        if (!self::periodoAceptaInsercion($year, $periodo, $esSemanal)) {
            return;
        }

        $existe = OpReciboNominaV2::where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->where('no_semana_quincena', $periodo)
            ->where('descripcion', $descripcion)
            ->exists();

        if (!$existe) {
            $personalActivo = RhPersonal::where('id_estacion', $idEstacion)
                ->where('estado', 1)
                ->get();

            foreach ($personalActivo as $p) {
                // Paridad legacy: la semana excluye al usuario 326
                // (lista-nomina-semanas.php:435) y la quincena al 385
                // (lista-nomina-quincenas.php:407).
                if ($esSemanal && $p->id == 326) continue;
                if (!$esSemanal && $p->id == 385) continue;

                OpReciboNominaV2::create([
                    'year'                 => $year,
                    'mes'                  => $mes,
                    'no_semana_quincena'   => $periodo,
                    'descripcion'          => $descripcion,
                    'id_estacion'          => $idEstacion,
                    'id_usuario'           => $p->id,
                    'id_puesto'            => $p->puesto ?? 0,
                    'importe_total'        => 0,
                    'doc_nomina'           => '',
                    'doc_nomina_firma'     => '',
                    'doc_nomina_aguinaldo' => '',
                    'nomina_original'      => 0,
                    'prima_vacacional'     => 0
                ]);
            }
        }
    }

    public static function getPeriodosAnio(int $idEstacion, int $year): array
    {
        $esSemanal = self::esSemanal($idEstacion);

$periodos = [];
      if ($esSemanal) {
// El total sale del mismo conteo que hace el dropdown del legacy, que para
      // 2025 da 53 semanas. No debe usarse getUltimaSemanaYear() aqui porque
      // esa da 52 para 2025 y dejaria fuera la ultima semana.
    $total = self::getTotalSemanasPeriodo($year);
     for ($s = 1; $s <= $total; $s++) {
    $periodos[] = $s;
        }
    } else {
            for ($q = 1; $q <= 24; $q++) {
                $periodos[] = $q;
            }
        }

        $list = [];
        $actual = 1;

        foreach ($periodos as $numero) {
            $rango = $esSemanal
                ? self::getRangoFechasSemana($year, $numero)
                : self::getRangoFechasQuincena($year, $numero);

            // El mismo criterio que valida el backend al insertar, para que el
            // selector no habilite periodos que el servidor va a rechazar.
            $habilitado = self::periodoAceptaInsercion($year, $numero, $esSemanal);

            if (!$esSemanal && $numero === ((int)date('n') - 1) * 2 + ((int)date('d') <= 15 ? 1 : 2)) {
                $actual = $numero;
            }
            if ($esSemanal) {
                $now = new DateTime();
                $iso = (int)$now->format('W');
                if ($iso === $numero) $actual = $numero;
            }

            $list[] = [
                'numero'   => $numero,
                'label'    => ($esSemanal ? 'Semana ' : 'Quincena ') . $numero . ': del ' . self::formatoFecha($rango['inicio']) . ' al ' . self::formatoFecha($rango['fin']),
                'inicio'   => $rango['inicio'],
                'fin'      => $rango['fin'],
                // Encabezado del periodo en texto: "Semana N: 1 de Octubre del 2026
                // al 7 de Octubre del 2026".
                'inicio_fmt' => self::periodoEnTexto($rango['inicio']),
                'fin_fmt'    => self::periodoEnTexto($rango['fin']),
                'habilitado' => $habilitado,
            ];
        }

        // Si el periodo actual es el primero, se deja como está.
        return [
            'es_semanal'    => $esSemanal,
            'periodos'      => $list,
            'periodo_actual'=> $actual,
        ];
    }

    public static function getNominaData(int $idEstacion, int $year, int $periodo, bool $esSemanal): array
    {
        $descripcion = $esSemanal ? 'Semana' : 'Quincena';
        $rango = $esSemanal ? self::getRangoFechasSemana($year, $periodo) : self::getRangoFechasQuincena($year, $periodo);
        $mes = $rango['mes'];

        self::sincronizarPersonal($idEstacion, $year, $periodo, $descripcion, $mes);

        $rows = OpReciboNominaV2::with('personal')
            ->withCount('comentarios')
            ->where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->where('no_semana_quincena', $periodo)
            ->where('descripcion', $descripcion)
            ->orderBy('id_usuario', 'asc')
            ->get();

        $esUltimoPeriodo = $esSemanal ? ($periodo === self::getUltimaSemanaYear($year)) : ($periodo === 24);

        $data = [];
        $totalGeneral = 0;
        $docCompletos = 0;

        // Legacy ToAlertaBd(): 0 = existe aviso de prima pendiente (status 0),
        // 1 = ya no hay aviso. Determina si el bloque de prima es editable.
        $usuariosConAlerta = ReciboNominaV2PrimaVacacional::where('status', 0)
            ->pluck('id_usuario')
            ->flip();

        foreach ($rows as $r) {
            $personal = $r->personal;
            $importe = (float)($r->importe_total ?? 0);
            $totalGeneral += $importe;

            $comentariosCount = (int)$r->comentarios_count;

            $docNom = !empty($r->doc_nomina);
            $docFir = !empty($r->doc_nomina_firma);
            $docAgui = !empty($r->doc_nomina_aguinaldo);

            $estatus = 'Pendiente';
            if ($esUltimoPeriodo) {
                if ($docNom && $docFir && $docAgui && $importe > 0) {
                    $estatus = 'Finalizado';
                } elseif ($docNom || $docFir || $docAgui || $importe > 0) {
                    $estatus = 'En proceso';
                }
            } else {
                if ($docNom && $docFir && $importe > 0) {
                    $estatus = 'Finalizado';
                } elseif ($docNom || $docFir || $importe > 0) {
                    $estatus = 'En proceso';
                }
            }

            if ($estatus === 'Finalizado') {
                $docCompletos++;
            }

            $bg = '#fcfcda';
            if ($estatus === 'Finalizado') {
                $bg = '#b0f2c2';
            } elseif ($estatus === 'Pendiente') {
                $bg = '#ffb6af';
            }

            $puestoNombre = '';
            if ($personal) {
                $puestoObj = RhPuestos::find((int)$personal->puesto);
                $puestoNombre = $puestoObj?->puesto ?? '';
            }

            $data[] = [
                'id'                   => $r->id,
                'id_usuario'           => $r->id_usuario,
                'no_colaborador'       => $personal?->no_colaborador ?: 'S/I',
                'nombre_completo'      => $personal?->nombre_completo ?? 'Sin nombre',
                'puesto'               => $puestoNombre,
                'importe_total'        => $importe,
                'doc_nomina'           => $r->doc_nomina ?? '',
                'doc_nomina_firma'     => $r->doc_nomina_firma ?? '',
                'doc_nomina_aguinaldo' => $r->doc_nomina_aguinaldo ?? '',
                'nomina_original'      => (int)($r->nomina_original ?? 0),
                'prima_vacacional'     => (int)($r->prima_vacacional ?? 0),
                'alerta_bd'            => $usuariosConAlerta->has((int)$r->id_usuario) ? 0 : 1,
                'comentarios_count'    => $comentariosCount,
                'estatus'              => $estatus,
                'bg_color'             => $bg
            ];
        }

        $acuseMexdesa = OpReciboNominaV2Acuse::where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->where('mes', $mes)
            ->where('no_semana_quincena', $periodo)
            ->where('descripcion', $descripcion)
            ->first();

        $finalizadoMexdesa = OpReciboNominaV2Puntaje::where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->where('mes', $mes)
            ->where('no_semana_quincena', $periodo)
            ->where('descripcion', $descripcion)
            ->where('actividad', 'Recibos Mexdesa')
            ->exists();

        $finalizadoEstacion = OpReciboNominaV2Puntaje::where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->where('mes', $mes)
            ->where('no_semana_quincena', $periodo)
            ->where('descripcion', $descripcion)
            ->where('actividad', 'Recibos Estacion')
            ->exists();

        $finalizadoOperativo = OpReciboNominaV2Puntaje::where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->where('mes', $mes)
            ->where('no_semana_quincena', $periodo)
            ->where('descripcion', $descripcion)
            ->where('actividad', 'Recibos Operativo')
            ->exists();

        $aguinaldo = OpReciboNominaAguinaldo::where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->where('mes', $mes)
            ->where('no_semana_quincena', $periodo)
            ->where('descripcion', $descripcion)
            ->first();

        $totalRegistros = count($data);
        $permisos = self::getPermisos();
        $esMultiestacion = $permisos['multiestacion'];

        // Legacy (lista-nomina-semanas.php::botonFinalizar): la estacion solo
        // puede finalizar despues de que MEXDESA subio y finalizo sus recibos.
        // Ese paso solo existe cuando el usuario opera con multiestacion.
        //
        // Para los usuarios que NO son multiestacion no hay una etapa Mexdesa
        // que esperar: el boton se habilita en cuanto todos los registros del
        // periodo estan completos (importe, recibo y recibo firmado, mas
        // aguinaldo en el ultimo periodo). Si falta algo se muestra el mensaje
        // de "no es posible finalizar".
        $puedeFinalizarEstacion = !$finalizadoEstacion
            && $totalRegistros > 0
            && $docCompletos === $totalRegistros
            && ($esMultiestacion ? $finalizadoMexdesa : true);

        return [
            'rows'                     => $data,
            'total_general'            => $totalGeneral,
            'rango_fechas'             => formatearFecha($rango['inicio']) . ' al ' . formatearFecha($rango['fin']),
            'es_ultimo_periodo'        => $esUltimoPeriodo,
            'doc_nomina_acuse'         => $acuseMexdesa?->doc_nomina_acuse ?? '',
            'id_acuse_mexdesa'         => (int)($acuseMexdesa?->id ?? 0),
            'finalizado_mexdesa'       => $finalizadoMexdesa,
            'finalizado_estacion'      => $finalizadoEstacion,
            'finalizado_operativo'     => $finalizadoOperativo,
            'es_multiestacion'         => $esMultiestacion,
            // true solo cuando el usuario pertenece de verdad a esta estación o
            // departamento. Es lo que habilita el boton de "Finalizar
            // actividad": un usuario multiestacion puede ver el detalle de
            // varias estaciones, pero solo finaliza la que le corresponde.
            'es_localidad_propia'       => self::esLocalidadPropia($idEstacion),
            'puede_finalizar_estacion' => $puedeFinalizarEstacion,
            'total_registros'          => $totalRegistros,
            'aguinaldo'                => [
                'id'     => $aguinaldo?->id ?? 0,
                'doc'    => $aguinaldo?->doc_nomina_aguinaldo ?? '',
                'status' => (int)($aguinaldo?->status ?? 0),
            ],
        ];
    }

    /**
     * Finaliza la actividad replicando el puntaje por fechas del módulo legacy.
     */
    public static function finalizarActividad(int $idEstacion, int $year, int $periodo, string $descripcion, int $idResponsable): array
    {
        $esSemanal = $descripcion === 'Semana';
        $rango = $esSemanal ? self::getRangoFechasSemana($year, $periodo) : self::getRangoFechasQuincena($year, $periodo);
        $mes = $rango['mes'];

        $actividad = match ($idResponsable) {
            1       => 'Recibos Mexdesa',
            2       => 'Recibos Estacion',
            default => 'Recibos Operativo'
        };

        $yaFinalizado = OpReciboNominaV2Puntaje::where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->where('mes', $mes)
            ->where('no_semana_quincena', $periodo)
            ->where('descripcion', $descripcion)
            ->where('actividad', $actividad)
            ->exists();

        if ($yaFinalizado) {
            return ['success' => false, 'message' => 'La actividad ya fue finalizada para este periodo.'];
        }

        $puntaje = self::calcularPuntaje($idResponsable, $year, $mes, $periodo, $descripcion);

        OpReciboNominaV2Puntaje::create([
            'year'               => $year,
            'mes'                => $mes,
            'no_semana_quincena' => $periodo,
            'descripcion'        => $descripcion,
            'id_estacion'        => $idEstacion,
            'actividad'          => $actividad,
            'puntaje'            => $puntaje
        ]);

        self::notificarTelegram($idEstacion, $year, $mes, $periodo, $descripcion, $actividad, $puntaje);

        return ['success' => true, 'message' => 'Actividad finalizada correctamente'];
    }

    /**
     * Escala de puntaje 3/2/1/0 por fecha (misma lógica que finalizar-recibos-nomina.php).
     */
    public static function calcularPuntaje(int $idResponsable, int $year, int $mes, int $periodo, string $descripcion): int
    {
        $hoy = date('Y-m-d');

        $tiers = $descripcion === 'Semana'
            ? match ($idResponsable) {
                1       => [[1, 1], [1, 2], [1, 3]],   // Mexdesa
                2       => [[1, 5], [2, 0], [2, 2]],   // Estaciones
                default => [[2, 1], [2, 2], [2, 4]],   // Operativo
            }
            : match ($idResponsable) {
                1       => [[0, 0], [0, 1], [0, 2]],
                2       => [[0, 1], [0, 2], [0, 3]],
                default => [[1, 1], [1, 2], [1, 3]],
            };

        foreach ($tiers as $i => [$semanas, $dias]) {
            $limite = $descripcion === 'Semana'
                ? self::fechaBaseSemana($year, $periodo, $semanas, $dias)
                : self::fechaBaseQuincena($year, $mes, $periodo, $semanas, $dias);

            if ($hoy <= $limite) {
                return 3 - $i;
            }
        }

        return 0;
    }

    private static function fechaBaseSemana(int $year, int $semana, int $sumSemanas, int $sumDias): string
    {
        $d = new DateTime();
        $d->setISODate($year, $semana, 1);
        $d->modify('last thursday');
        $d->modify("+$sumSemanas weeks +$sumDias days");
        return $d->format('Y-m-d');
    }

    private static function fechaBaseQuincena(int $year, int $mes, int $quincena, int $sumSemanas, int $sumDias): string
    {
        $primerDia = mktime(0, 0, 0, $mes, 1, $year);
        $inicio = ($quincena % 2 === 1)
            ? date('Y-m-16', $primerDia)
            : date('Y-m-01', strtotime('+1 month', $primerDia));

        $fecha = strtotime($inicio);
        $fecha = strtotime("+$sumSemanas weeks", $fecha);
        $fecha = strtotime("+$sumDias days", $fecha);

        return date('Y-m-d', $fecha);
    }

    private static function notificarTelegram(int $idEstacion, int $year, int $mes, int $periodo, string $descripcion, string $actividad, int $puntaje): void
    {
        try {
            $sessionUsuario = Session::get('usuario') ?? [];
            $idUsuario = (int)($sessionUsuario['id'] ?? 0);
            $nombreUsuario = $sessionUsuario['nombre'] ?? 'Usuario';

            $localidad = RhLocalidad::find($idEstacion);
            $nombreLugar = $localidad?->localidad ?? "localidad #$idEstacion";
            $esEstacion = $localidad && (int)($localidad->numlista ?? 100) <= 8;
            $tipoLugar = $esEstacion ? 'Estación' : 'Departamento';

            $titulo = match ($actividad) {
                'Recibos Mexdesa'   => 'subió los recibos de nómina del personal',
                'Recibos Estacion'  => 'finalizó la carga de los acuses de nómina del personal',
                default             => 'finalizó la revisión y validación del VOBO de los acuses de nómina del personal',
            };

            $detalle = '✅ ' . $nombreUsuario . ' ' . $titulo
                . ' en el módulo <b>Recibos de Nómina</b>, correspondiente al apartado de Recursos Humanos.' . PHP_EOL
                . '📄 ' . $descripcion . ': ' . $periodo . PHP_EOL
                . '🗓 Mes: ' . nombremes($mes) . ', ' . $year . PHP_EOL . PHP_EOL
                . (($tipoLugar === 'Estación') ? '⛽ ' : '🏢 ') . $tipoLugar . ': ' . $nombreLugar . '.' . PHP_EOL
                . 'Puntaje: ' . $puntaje;

            $telegram = new TelegramService();
            $ids = $telegram->getUserIdsByStation($idEstacion, $idUsuario);

            if ($idEstacion == 6 || $idEstacion == 7) {
                $ids = array_merge($ids, $telegram->getUserIdsComercializadora($idUsuario));
            } elseif (in_array($idEstacion, [1, 2, 3, 4, 5, 14], true)) {
                $ids = array_merge($ids, $telegram->getUserIdsContabilidad($idUsuario));
            }

            $ids = array_values(array_unique($ids));

            if (!empty($ids)) {
                $telegram->sendMessageToMultiple($ids, $detalle);
            }
        } catch (\Throwable $e) {
            // Nunca debe impedir la finalización de la actividad.
        }
    }

    private static function notificarAccion(int $idEstacion, int $year, int $mes, int $periodo, string $descripcion, string $titulo): void
    {
        try {
            $sessionUsuario = Session::get('usuario') ?? [];
            $idUsuario = (int)($sessionUsuario['id'] ?? 0);
            $nombreUsuario = $sessionUsuario['nombre'] ?? 'Usuario';

            $localidad = RhLocalidad::find($idEstacion);
            $nombreLugar = $localidad?->localidad ?? "localidad #$idEstacion";

            $detalle = '✅ ' . $nombreUsuario . ' ' . $titulo
                . ' en el módulo <b>Recibos de Nómina</b>, correspondiente al apartado de Recursos Humanos.' . PHP_EOL
                . '📄 ' . $descripcion . ': ' . $periodo . PHP_EOL
                . '🗓 Mes: ' . nombremes($mes) . ', ' . $year . PHP_EOL . PHP_EOL
                . '🏢 Lugar: ' . $nombreLugar . '.';

            $telegram = new TelegramService();
            $ids = $telegram->getUserIdsByStation($idEstacion, $idUsuario);
            $ids = array_values(array_unique($ids));

            if (!empty($ids)) {
                $telegram->sendMessageToMultiple($ids, $detalle);
            }
        } catch (\Throwable $e) {
            // Nunca debe impedir la operación.
        }
    }

    public static function formatoFecha(string $fecha): string
    {
        $d = new DateTime($fecha);
        return $d->format('d/m/Y');
    }

    /**
     * Periodo en texto para el encabezado del selector.
     *
     * Reutiliza formatearFecha() de helpers.php, que ya produce
     * "1 de Octubre del 2026", y unicamente le quita el cero inicial del dia
     * para igualarlo al formato largo que usan el resto de los modulos.
     * No se toca el helper global porque se usa en todo el portal.
     */
    public static function periodoEnTexto(string $fecha): string
    {
        return preg_replace('/^0(\d) de /', '$1 de ', formatearFecha($fecha)) ?? '';
    }

    public static function semanasDelMes(int $mes, int $year): array
    {
        $semanas = [];
        $primerDia = strtotime("$year-$mes-01");
        $primerDia = strtotime('this Wednesday', $primerDia);

        for ($currentDate = $primerDia; date('m', $currentDate) == $mes; $currentDate = strtotime('+1 week', $currentDate)) {
            $semana = date('W', $currentDate);
            if (!in_array($semana, $semanas, true)) {
                $semanas[] = (int)$semana;
            }
        }

        return $semanas;
    }

    public static function quincenasDelMes(int $mes, int $year): array
    {
        return [((int)$mes - 1) * 2 + 1, ((int)$mes - 1) * 2 + 2];
    }

    public static function obtenerMesPorSemana(int $year, int $semana): int
    {
        $d = new DateTime();
        $d->setISODate($year, $semana);
        return (int)$d->format('n');
    }

    public static function listarPersonalFaltante(int $idEstacion, int $year, int $periodo, bool $esSemanal): array
    {
        // Paridad con modal-agregar-usuario.php del legacy: se resta el
        // personal ya capturado filtrando solo por estación, año y
        // no_semana_quincena (sin mes ni descripcion), y sin excluir ids.
        $yaRegistrados = OpReciboNominaV2::where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->where('no_semana_quincena', $periodo)
            ->pluck('id_usuario')
            ->all();

        $personal = RhPersonal::where('id_estacion', $idEstacion)
            ->where('estado', 1)
            ->orderBy('id', 'desc')
            ->get();

        $list = [];
        foreach ($personal as $p) {
            if (in_array($p->id, $yaRegistrados, true)) continue;

            $puestoObj = RhPuestos::find((int)$p->puesto);
            $list[] = [
                'id'               => $p->id,
                'nombre_completo'  => $p->nombre_completo,
                'no_colaborador'   => $p->no_colaborador ?: 'S/I',
                'puesto'           => $puestoObj?->puesto ?? ''
            ];
        }

        return $list;
    }

    public static function agregarPersonal(int $idEstacion, int $year, int $periodo, string $descripcion, array $ids): void
    {
        $esSemanal = $descripcion === 'Semana';
        $rango = $esSemanal
            ? self::getRangoFechasSemana($year, $periodo)
            : self::getRangoFechasQuincena($year, $periodo);
        $mes = $rango['mes'];

        $idEstacion = (int)$idEstacion;
        $year = (int)$year;
        $periodo = (int)$periodo;

        foreach (array_unique(array_map('intval', $ids)) as $idUsuario) {
            if (!$idUsuario) continue;

            $yaExiste = OpReciboNominaV2::where('id_estacion', $idEstacion)
                ->where('year', $year)
                ->where('mes', $mes)
                ->where('no_semana_quincena', $periodo)
                ->where('descripcion', $descripcion)
                ->where('id_usuario', $idUsuario)
                ->exists();

            if ($yaExiste) continue;

            $personal = RhPersonal::find($idUsuario);

            OpReciboNominaV2::create([
                'year'               => $year,
                'mes'                => $mes,
                'no_semana_quincena' => $periodo,
                'descripcion'        => $descripcion,
                'id_estacion'        => $idEstacion,
                'id_usuario'         => $idUsuario,
                'id_puesto'          => $personal?->puesto ?? 0,
                'importe_total'      => 0,
                'doc_nomina'         => '',
                'doc_nomina_firma'   => '',
                'doc_nomina_aguinaldo' => '',
                'nomina_original'    => 0,
                'prima_vacacional'   => 0
            ]);

            self::notificarAccion($idEstacion, $year, $mes, $periodo, $descripcion, 'agregó personal a la nómina');
        }
    }

    public static function getAcusesPeriodo(int $idEstacion, int $year, int $periodo, string $descripcion): array
    {
        $rows = OpReciboNominaPeriodoAcuse::where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->where('no_semana_quincena', $periodo)
            ->where('descripcion', $descripcion)
            ->orderBy('id', 'desc')
            ->get();

        return $rows->map(function ($r) {
            return [
                'id'       => $r->id,
                'archivo'  => $r->archivo,
                'fecha'    => $r->fecha ? formatearFecha($r->fecha)  : '',
            ];
        })->values()->toArray();
    }

       



    public static function guardarAcusePeriodo(int $idEstacion, int $year, int $periodo, string $descripcion, string $archivo): void
    {
        $esSemanal = $descripcion === 'Semana';
        $rango = $esSemanal
            ? self::getRangoFechasSemana($year, $periodo)
            : self::getRangoFechasQuincena($year, $periodo);
        $mes = $rango['mes'];

        OpReciboNominaPeriodoAcuse::create([
            'year'               => $year,
            'mes'                => $mes,
            'no_semana_quincena' => $periodo,
            'descripcion'        => $descripcion,
            'id_estacion'        => $idEstacion,
            'archivo'            => $archivo
        ]);

        self::notificarAccion($idEstacion, $year, $mes, $periodo, $descripcion, 'subió un acuse de nómina del periodo');
    }

    public static function eliminarAcusePeriodo(int $id): void
    {
        OpReciboNominaPeriodoAcuse::where('id', $id)->delete();
    }

    public static function guardarAcuseMexdesa(int $idEstacion, int $year, int $periodo, string $descripcion, string $archivo, int $idAcuse): void
    {
        $esSemanal = $descripcion === 'Semana';
        $rango = $esSemanal
            ? self::getRangoFechasSemana($year, $periodo)
            : self::getRangoFechasQuincena($year, $periodo);
        $mes = $rango['mes'];

        if ($idAcuse > 0) {
            OpReciboNominaV2Acuse::where('id', $idAcuse)->update(['doc_nomina_acuse' => $archivo]);
        } else {
            OpReciboNominaV2Acuse::create([
                'year'               => $year,
                'mes'                => $mes,
                'no_semana_quincena' => $periodo,
                'descripcion'        => $descripcion,
                'id_estacion'        => $idEstacion,
                'doc_nomina_acuse'   => $archivo
            ]);
        }

        self::notificarAccion($idEstacion, $year, $mes, $periodo, $descripcion, 'subió los recibos de nómina del personal');
    }

    public static function subirAguinaldo(int $idEstacion, int $year, int $periodo, string $descripcion, string $archivo, int $idAguinaldo): void
    {
        $esSemanal = $descripcion === 'Semana';
        $rango = $esSemanal
            ? self::getRangoFechasSemana($year, $periodo)
            : self::getRangoFechasQuincena($year, $periodo);
        $mes = $rango['mes'];

        if ($idAguinaldo > 0) {
            OpReciboNominaAguinaldo::where('id', $idAguinaldo)->update(['doc_nomina_aguinaldo' => $archivo]);
        } else {
            OpReciboNominaAguinaldo::create([
                'id_estacion'        => $idEstacion,
                'year'               => $year,
                'mes'                => $mes,
                'no_semana_quincena' => $periodo,
                'descripcion'        => $descripcion,
                'doc_nomina_aguinaldo' => $archivo,
                'status'             => 0
            ]);
        }

        self::notificarAccion($idEstacion, $year, $mes, $periodo, $descripcion, 'subió los recibos de aguinaldo del personal');
    }

    public static function finalizarAguinaldo(int $idAguinaldo): void
    {
        $rec = OpReciboNominaAguinaldo::find($idAguinaldo);
        if (!$rec) return;

        OpReciboNominaAguinaldo::where('id', $idAguinaldo)->update(['status' => 1]);

        self::notificarAccion(
            (int)$rec->id_estacion,
            (int)$rec->year,
            (int)$rec->mes,
            (int)$rec->no_semana_quincena,
            (string)$rec->descripcion,
            'finalizó la carga de los recibos de aguinaldo del personal'
        );
    }

    /**
     * View model de la pantalla de Revisión.
     *
     * Aquí se resuelve TODO lo que la vista necesita pintar: qué locality
     * mostrar, qué botón habilita cada periodo, el texto y la clase de cada
     * badge y las URLs de los documentos. La vista (revision.php) solo itera y
     * escribe HTML, sin lógica de presentación.
     *
     * Cuando $idEstacion es 0 (no hay estación/departamento asignado en el
     * selector) devuelve el esqueleto con `hay_estacion` en false.
     */
    public static function dataRevision(int $idEstacion, int $year, int $mes): array
    {
        if ($idEstacion <= 0) {
            return [
                'hay_estacion'  => false,
                'mostrar_excel' => false,
                'excel_url'     => '',
                'es_semanal'    => false,
                'mes'           => $mes,
                'bloques'       => [],
            ];
        }

        $esSemanal = self::esSemanal($idEstacion);
        $descripcion = $esSemanal ? 'Semana' : 'Quincena';
        $periodos = $esSemanal ? self::semanasDelMes($mes, $year) : self::quincenasDelMes($mes, $year);
        $puedeEditar = (bool)self::getPermisos()['puedeEditar'];

        // El Excel de despachadores solo existe para estaciones semanales y no
        // para la localidad 9 (Autolavado), igual que en la vista anterior.
        $mostrarExcel = $esSemanal && $idEstacion !== 9;

        $bloques = [];
        foreach ($periodos as $periodo) {
            $nomina = self::getNominaData($idEstacion, $year, $periodo, $esSemanal);

            $finalizadoOperativo = !empty($nomina['finalizado_operativo']);

            // Solo quien puede editar y siempre que la estación ya haya cerrado
            // su parte del periodo puede cerrarla desde la Revisión.
            $puedeFinalizar = !$finalizadoOperativo
                && !empty($nomina['finalizado_estacion'])
                && (int)$nomina['total_registros'] > 0
                && $puedeEditar;

            $estado = $finalizadoOperativo ? 'finalizado' : 'pendiente';

            $rows = [];
            foreach ($nomina['rows'] as $indice => $fila) {
                $rows[] = self::presentarFilaRevision($fila, $indice + 1);
            }

            $bloques[] = [
                'periodo'        => $periodo,
                'descripcion'    => $descripcion,
                'titulo'         => $descripcion . ' ' . $periodo,
                'rango_fechas'   => (string)$nomina['rango_fechas'],
                'total_general'  => number_format((float)$nomina['total_general'], 2),
                'estado'         => $estado,
                'estado_badge'   => self::BADGES_FINALIZACION[$estado],
                'puede_finalizar'=> $puedeFinalizar,
                'hay_rows'       => $rows !== [],
                'rows'           => $rows,
            ];
        }

        return [
            'hay_estacion'  => true,
            'mostrar_excel' => $mostrarExcel,
            'excel_url'     => $mostrarExcel
                ? '/departamento-operativo/recursos-humanos/recibos-nomina-revision/excel'
                    . '?idEstacion=' . $idEstacion . '&year=' . $year . '&mes=' . $mes
                : '',
            'es_semanal'    => $esSemanal,
            'mes'           => $mes,
            'bloques'       => $bloques,
        ];
    }

    /**
     * Convierte una fila de getNominaData() en la estructura que la vista de
     * Revisión imprime: importe formateado, badge de prima vacacional, iconos
     * de documento y badge de estatus ya resueltos.
     */
    private static function presentarFilaRevision(array $fila, int $num): array
    {
        $primaVacacional = (int)$fila['prima_vacacional'];

        return [
            'num'            => $num,
            'no_colaborador' => (string)$fila['no_colaborador'],
            'nombre'         => (string)$fila['nombre_completo'],
            'importe'        => number_format((float)$fila['importe_total'], 2),
            'bg_color'       => (string)$fila['bg_color'],
            'prima'          => self::BADGES_PRIMA[$primaVacacional] ?? self::BADGES_PRIMA[0],
            'doc_nomina'     => self::presentarDocumento('recibos-nomina', (string)$fila['doc_nomina'], 'ti ti-file-text text-primary fs-7'),
            'doc_firma'      => self::presentarDocumento('recibos-nomina-firma', (string)$fila['doc_nomina_firma'], 'ti ti-signature text-success fs-8'),
            'doc_aguinaldo'  => self::presentarDocumento('recibos-aguinaldo', (string)$fila['doc_nomina_aguinaldo'], 'ti ti-gift text-warning fs-7'),
            'estatus'        => self::BADGES_ESTATUS[$fila['estatus']] ?? self::BADGES_ESTATUS['Pendiente'],
        ];
    }

    /**
     * Estado del icono de un documento: si existe se arma el link de descarga,
     * si no se deja el icono de "no disponible".
     */
    private static function presentarDocumento(string $tipo, string $archivo, string $claseIcono): array
    {
        $tieneDocumento = $archivo !== '';

        return [
            'tiene_documento' => $tieneDocumento,
            'url'             => $tieneDocumento
                ? '/download?tipo=' . $tipo . '&file=' . urlencode($archivo)
                : '',
            'icono'           => $tieneDocumento ? $claseIcono : 'ti ti-file-off text-muted fs-6',
        ];
    }

    public static function getExcelDespachadores(int $idEstacion, int $year, int $mes): string
    {
        $semanas = self::semanasDelMes($mes, $year);

        $encabezado = ['No.', 'No. de Colaborador', 'Nombre del personal'];
        foreach ($semanas as $semana) {
            $rango = self::getRangoFechasSemana($year, $semana);
            $encabezado[] = 'Semana ' . $semana . ' del ' . $rango['inicio'] . ' al ' . $rango['fin'];
        }
        $encabezado[] = 'Total a percibir';

        $filas[] = $encabezado;

        $idsUsuarios = OpReciboNominaV2::where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->where('mes', $mes)
            ->where('descripcion', 'Semana')
            ->where('id_puesto', 4)
            ->distinct()
            ->orderBy('id_usuario', 'asc')
            ->pluck('id_usuario');

        $num = 1;
        $sumaTotal = 0;

        foreach ($idsUsuarios as $idUsuario) {
            $personal = RhPersonal::find($idUsuario);
            $nombre = $personal?->nombre_completo ?? '';
            $noColaborador = $personal && (int)$personal->no_colaborador !== 0 ? (string)$personal->no_colaborador : 'S/I';

            $fila = [$num, $noColaborador, $nombre];
            $totalAPercibir = 0;

            foreach ($semanas as $semana) {
                $importe = (float)OpReciboNominaV2::where('id_usuario', $idUsuario)
                    ->where('mes', $mes)
                    ->where('no_semana_quincena', $semana)
                    ->where('descripcion', 'Semana')
                    ->value('importe_total');

                $importe = $importe ?? 0;
                $fila[] = $importe;
                $totalAPercibir += $importe;
            }

            $totalAPercibir = round($totalAPercibir, 2);
            $sumaTotal += $totalAPercibir;
            $fila[] = number_format($totalAPercibir, 2, '.', '');

            $filas[] = $fila;
            $num++;
        }

        $filaTotal = ['', '', '   Total'];
        foreach ($semanas as $semana) {
            $filaTotal[] = '';
        }
        $filaTotal[] = number_format($sumaTotal, 2, '.', '');
        $filas[] = $filaTotal;

        $fp = fopen('php://temp', 'r+');
        foreach ($filas as $fila) {
            $fila = array_map(function ($v) {
                if (is_string($v)) {
                    $original = $v;
                    $v = @iconv('UTF-8', 'ISO-8859-1', $original);
                    if ($v === false) $v = mb_convert_encoding($original, 'ISO-8859-1', 'UTF-8');
                }
                return $v;
            }, $fila);
            fputcsv($fp, $fila);
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv;
    }
}