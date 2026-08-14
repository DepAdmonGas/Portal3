<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPersonalHorario;
use App\Models\Operativo\RhLocalidadesHorario;
use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhPuestos;

class HorarioPersonalService
{
public const MODULE_KEY = 'horario-personal';

public const ESTACIONES_SOPORTADAS = [1, 2, 3, 4, 5, 6, 7, 14];

public const DEPTO_AUTOLAVADO = 9;

/** Personal excluido de la vista (misma lista que la versión anterior). */
public const EXCLUIDOS = [387, 358, 296, 326, 300, 335];

public const DIAS = [
1 => 'Lunes',
2 => 'Martes',
3 => 'Miércoles',
4 => 'Jueves',
5 => 'Viernes',
6 => 'Sábado',
7 => 'Domingo',
];

public static function getPermisos(): array
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');
$idUsuario = (int)($sessionUsuario['id'] ?? 0);
$idPuesto = (int)($usuario->id_puesto ?? 0);
$idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
$multiestacion = !empty($sessionUsuario['multiestacion']);
$nombrePuesto = $usuario->puesto->tipo_puesto ?? '';

$permisosDb = ModuloDptoOperativoService::permisosSesion('recursos-humanos');

return [
'id_usuario'     => $idUsuario,
'id_estacion'    => $idEstacion,
'id_puesto'      => $idPuesto,
'nombre_puesto'  => $nombrePuesto,
'multiestacion'  => $multiestacion,
'puedeEditar'    => !empty($permisosDb['editar']),
'puedeEliminar'  => !empty($permisosDb['eliminar']),
'puedeDescargar' => !empty($permisosDb['descargar']),
];
}

public static function getContexto(): array
{
$ctx = ModuleStationService::getContext(self::MODULE_KEY);
$idEstacion = $ctx['id_estacion'];
$idDepto = $ctx['id_depto'];

if (self::esTodasPaloSolo()) {
$idEstacion = null;
$idDepto = null;
}

$tipo = 'todas';
if ($idEstacion && !$idDepto) {
$tipo = 'estacion';
} elseif ($idDepto) {
$tipo = 'depto';
}

return [
'id_estacion' => $idEstacion,
'id_depto'    => $idDepto,
'tipo'        => $tipo,
'nombre'      => $ctx['nombre'],
];
}

/**
* Solo usuarios con id_gas = 2 (Palo Solo) que NO son multiestación
* y seleccionaron explícitamente "TODAS LAS ESTACIONES Y DEPARTAMENTOS":
* el contexto se guarda como null/null y ModuleStationService::getContext()
* lo reinterpreta como "mi estación" (Palo Solo). Aquí se restaura el
* comportamiento real de "TODAS" únicamente para este caso.
*/
private static function esTodasPaloSolo(): bool
{
$sessionUsuario = Session::get('usuario');
$idGas = $sessionUsuario['id_estacion'] ?? null;
if ($idGas === null) {
$usuario = Auth::user();
$idGas = $usuario ? $usuario->id_gas : null;
}
if ((int)$idGas !== 2) {
return false;
}

if (MultiestacionService::isEnabled()) {
return false;
}

$contexto = Session::get('module_context') ?? [];
if (!array_key_exists(self::MODULE_KEY, $contexto)) {
return false;
}

$raw = $contexto[self::MODULE_KEY];

return empty($raw['id_estacion']) && empty($raw['id_depto']);
}

/**
* Catálogo de turnos de una localidad.
* Autolavado (9) usa el catálogo de Palo Solo (2).
*/
public static function getCatalogoTurnos(int $idEstacion): array
{
$idCatalogo = $idEstacion === self::DEPTO_AUTOLAVADO ? 2 : $idEstacion;

return RhLocalidadesHorario::where('id_estacion', $idCatalogo)
->orderBy('id')
->get(['titulo', 'hora_entrada', 'hora_salida'])
->toArray();
}

private static function getIdsPermitidos(): array
{
$ids = [];

foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
$ids[] = (int)$s['id'];
}
foreach (ModuleStationService::getAvailableDepartments(self::MODULE_KEY) as $d) {
$ids[] = (int)$d['id'];
}

return array_values(array_unique($ids));
}

public static function getDatos(): array
{
$ctx = self::getContexto();

$query = RhPersonal::where('estado', 1)
->whereNotIn('id', self::EXCLUIDOS);

if ($ctx['tipo'] === 'estacion') {
$query->where('id_estacion', $ctx['id_estacion']);
} elseif ($ctx['tipo'] === 'depto') {
$query->where('id_estacion', $ctx['id_depto']);
} else {
$ids = self::getIdsPermitidos();
if (!empty($ids)) {
$query->whereIn('id_estacion', $ids);
}
}

$personal = $query->orderBy('id_estacion', 'asc')
->orderBy('id', 'asc')
->get();

$rows = [];
$idsPersonal = $personal->pluck('id')->toArray();

$horarios = [];
if (!empty($idsPersonal)) {
foreach (RhPersonalHorario::whereIn('id_personal', $idsPersonal)->get() as $h) {
$horarios[$h->id_personal][$h->dia] = $h;
}
}

foreach ($personal as $p) {
$puesto = RhPuestos::find($p->puesto);
$turnos = self::getCatalogoTurnos((int)$p->id_estacion);

$row = [
'id'               => (int)$p->id,
'id_estacion'      => (int)$p->id_estacion,
'nombre_estacion'  => self::resolveNombreEstacion((int)$p->id_estacion),
'nombre_completo'  => $p->nombre_completo,
'puesto'           => $puesto ? $puesto->puesto : '',
'turnos'           => $turnos,
];

foreach (self::DIAS as $num => $nombreDia) {
$row[self::keyDia($num)] = self::datosDia($horarios[$p->id][$nombreDia] ?? null);
}

$rows[] = $row;
}

return [
'rows'     => $rows,
'contexto' => $ctx,
];
}

private static function keyDia(int $num): string
{
return [
1 => 'lunes',
2 => 'martes',
3 => 'miercoles',
4 => 'jueves',
5 => 'viernes',
6 => 'sabado',
7 => 'domingo',
][$num];
}

private static function datosDia(?RhPersonalHorario $h): ?array
{
if (!$h) {
return null;
}

return [
'horario'       => $h->horario,
'hora_entrada'  => (string)$h->getRawOriginal('hora_entrada'),
'hora_salida'   => (string)$h->getRawOriginal('hora_salida'),
'formateado'    => self::formatearHorarioDia((string)$h->getRawOriginal('hora_entrada'), (string)$h->getRawOriginal('hora_salida')),
];
}

public static function formatearHorarioDia(string $horaEntrada, string $horaSalida): string
{
if (self::esHoraCero($horaEntrada) && self::esHoraCero($horaSalida)) {
return 'Descanso';
}

$entrada = date('g:i a', strtotime($horaEntrada));
$salida = date('g:i a', strtotime($horaSalida));

return $entrada . ' a ' . $salida;
}

private static function esHoraCero(string $hora): bool
{
if ($hora === '') {
return true;
}

if ($hora === '00:00' || $hora === '00:00:00') {
return true;
}

$t = strtotime($hora);

return $t !== false && date('H:i', $t) === '00:00';
}

public static function resolveNombreEstacion(int $idEstacion): string
{
$loc = RhLocalidad::find($idEstacion);
if ($loc) {
return $loc->localidad;
}

$est = Estacion::find($idEstacion);
if ($est) {
return $est->nombre;
}

return '';
}

public static function editarHorario(int $idPersonal, int $dia, string $horario): array
{
if (!isset(self::DIAS[$dia])) {
throw new \InvalidArgumentException('Día no válido.');
}

$personal = RhPersonal::find($idPersonal);
if (!$personal) {
throw new \InvalidArgumentException('El personal no existe.');
}

$idEstacion = (int)$personal->id_estacion;
$idEstacionHorario = $idEstacion === self::DEPTO_AUTOLAVADO ? 2 : $idEstacion;

$nomDia = self::DIAS[$dia];
$horaEntrada = '00:00:00';
$horaSalida = '00:00:00';

if ($horario === '') {
throw new \InvalidArgumentException('Selecciona un turno o "Sin asignar".');
}

if ($horario !== 'Descanso') {
$turno = RhLocalidadesHorario::where('id_estacion', $idEstacionHorario)
->where('titulo', $horario)
->first();

if (!$turno) {
throw new \InvalidArgumentException('El turno seleccionado no existe en el catálogo.');
}

$horaEntrada = (string)$turno->hora_entrada;
$horaSalida = (string)$turno->hora_salida;
}

$registro = RhPersonalHorario::where('id_estacion', $idEstacion)
->where('id_personal', $idPersonal)
->where('dia', $nomDia)
->first();

$accion = 'editar';

if ($registro) {
$registro->update([
'horario'       => $horario,
'hora_entrada'  => $horaEntrada,
'hora_salida'   => $horaSalida,
]);
} else {
$accion = 'agregar';
RhPersonalHorario::create([
'id_estacion'   => $idEstacion,
'id_personal'   => $idPersonal,
'horario'       => $horario,
'dia'           => $nomDia,
'hora_entrada'  => $horaEntrada,
'hora_salida'   => $horaSalida,
]);
}

self::notificarTelegram($accion, [
'id_estacion'    => $idEstacion,
'id_personal'    => $idPersonal,
'dia'            => $nomDia,
'horario'        => $horario,
'hora_entrada'   => $horaEntrada,
'hora_salida'    => $horaSalida,
'nombre_personal' => $personal->nombre_completo,
'puesto'          => self::puestoPersonal($idPersonal),
]);

return [
'horario'       => $horario,
'hora_entrada'  => $horaEntrada,
'hora_salida'   => $horaSalida,
'formateado'    => self::formatearHorarioDia($horaEntrada, $horaSalida),
];
}

public static function eliminarHorarioPersonal(int $idPersonal): void
{
$personal = RhPersonal::find($idPersonal);
if (!$personal) {
throw new \InvalidArgumentException('El personal no existe.');
}

$eliminados = RhPersonalHorario::where('id_personal', $idPersonal)->delete();

if ($eliminados > 0) {
self::notificarTelegram('eliminar', [
'id_estacion'     => (int)$personal->id_estacion,
'id_personal'     => $idPersonal,
'nombre_personal' => $personal->nombre_completo,
'puesto'          => self::puestoPersonal($idPersonal),
]);
}
}

public static function eliminarHorarioDia(int $idPersonal, int $dia): void
{
if (!isset(self::DIAS[$dia])) {
throw new \InvalidArgumentException('Día no válido.');
}

$personal = RhPersonal::find($idPersonal);
if (!$personal) {
throw new \InvalidArgumentException('El personal no existe.');
}

$nomDia = self::DIAS[$dia];
$idEstacion = (int)$personal->id_estacion;

$eliminados = RhPersonalHorario::where('id_estacion', $idEstacion)
->where('id_personal', $idPersonal)
->where('dia', $nomDia)
->delete();

if ($eliminados > 0) {
self::notificarTelegram('eliminar', [
'id_estacion'     => $idEstacion,
'id_personal'     => $idPersonal,
'dia'             => $nomDia,
'nombre_personal' => $personal->nombre_completo,
'puesto'          => self::puestoPersonal($idPersonal),
]);
}
}

private static function puestoPersonal(int $idPersonal): string
{
$personal = RhPersonal::find($idPersonal);
if (!$personal) {
return '';
}

$puesto = RhPuestos::find($personal->puesto);
return $puesto ? $puesto->puesto : '';
}

private static function notificarTelegram(string $accion, array $params): void
{
try {
$sessionUsuario = Session::get('usuario');
$idUsuario = (int)($sessionUsuario['id'] ?? 0);

$usuario = Usuario::find($idUsuario);
$nombreUsuario = $usuario ? $usuario->nombre : 'Desconocido';

$nombreEstacion = self::resolveNombreEstacion((int)$params['id_estacion']);
$nombrePersonal = $params['nombre_personal'] ?? '';
$puesto = $params['puesto'] ?? '';

switch ($accion) {
case 'agregar':
$icono = '✅';
$accionTexto = 'asignado el horario del personal';
break;
case 'editar':
$icono = '🔄';
$accionTexto = 'actualizado el horario del personal';
break;
case 'eliminar':
$icono = '🗑';
$accionTexto = 'eliminado el horario del personal';
break;
default:
return;
}

$detalle = $icono . ' Se ha ' . $accionTexto
. ' del apartado de <b>Recursos Humanos</b>, módulo de <b>Horario Personal</b>:'
. PHP_EOL . PHP_EOL
. '👤 <b>Personal:</b> ' . $nombrePersonal . PHP_EOL
. '💼 <b>Puesto:</b> ' . $puesto . PHP_EOL;

if ($accion !== 'eliminar') {
$dia = $params['dia'] ?? '';
$horario = $params['horario'] ?? '';
$horaEntrada = $params['hora_entrada'] ?? '00:00:00';
$horaSalida = $params['hora_salida'] ?? '00:00:00';

$detalle .= '🗓 <b>Día:</b> ' . $dia . PHP_EOL;

$esDescanso = in_array(strtoupper($horario), ['DESCANSO', 'VACACIONES', 'INCAPACIDAD', 'BAJA'], true);

if ($esDescanso) {
$detalle .= '🚨 ' . strtoupper($horario) . PHP_EOL;
} else {
$detalle .= '🕐 <b>Horario asignado:</b>' . PHP_EOL
. '🟢 Entrada: ' . date('g:i A', strtotime($horaEntrada)) . PHP_EOL
. '🔴 Salida: ' . date('g:i A', strtotime($horaSalida)) . PHP_EOL;
}
} elseif (!empty($params['dia'])) {
$detalle .= '🗓 <b>Día:</b> ' . $params['dia'] . PHP_EOL;
}

$detalle .= PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreEstacion . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario;

TelegramService::notificar((int)$params['id_estacion'], $idUsuario, $detalle);
} catch (\Throwable $e) {
error_log('Error en HorarioPersonalService::notificarTelegram: ' . $e->getMessage());
}
}

/** Secciones para el PDF: estación 2 (Palo Solo) agrega también Autolavado. */
public static function getSeccionesPdf(): array
{
$ctx = self::getContexto();

if ($ctx['tipo'] === 'estacion') {
$ids = [$ctx['id_estacion']];
if ((int)$ctx['id_estacion'] === 2) {
$ids[] = self::DEPTO_AUTOLAVADO;
}
} elseif ($ctx['tipo'] === 'depto') {
$ids = [$ctx['id_depto']];
} else {
$ids = self::getIdsPermitidos();
}

$secciones = [];

foreach ($ids as $id) {
$personal = RhPersonal::where('id_estacion', $id)
->where('estado', 1)
->whereNotIn('id', self::EXCLUIDOS)
->orderBy('id', 'asc')
->get();

$filas = [];

foreach ($personal as $p) {
$fila = [
'nombre_completo' => $p->nombre_completo,
'puesto'          => self::puestoPersonal((int)$p->id),
];

foreach (self::DIAS as $num => $nombreDia) {
$h = RhPersonalHorario::where('id_personal', $p->id)
->where('dia', $nombreDia)
->first();

$fila[self::keyDia($num)] = $h
? self::formatearHorarioDia((string)$h->getRawOriginal('hora_entrada'), (string)$h->getRawOriginal('hora_salida'))
: 'Descanso';
}

$filas[] = $fila;
}

$secciones[] = [
'id'       => (int)$id,
'nombre'   => self::resolveNombreEstacion((int)$id),
'filas'    => $filas,
];
}

return $secciones;
}

public static function getHtmlPdf(string $fecha = ''): string
{
$fechaBase = $fecha !== '' ? $fecha : date('Y-m-d');
$lunes = date('Y-m-d', strtotime('monday this week', strtotime($fechaBase)));
$domingo = date('Y-m-d', strtotime('sunday this week', strtotime($fechaBase)));

$secciones = self::getSeccionesPdf();
$nombreContexto = self::getContexto()['nombre'];
$logo = ($_ENV['APP_URL'] ?? '') . '/assets/images/logos/Logo.png';

$tablas = '';
$primera = true;

foreach ($secciones as $seccion) {
if (!$primera) {
$tablas .= '<div style="page-break-before: always;"></div>';
}
$primera = false;

$tablas .= '<h3 style="margin:0;">Horario Personal ' . $seccion['nombre'] . '</h3>'
. '<small style="font-size:12px;color:#555;">(Semana: '
. date('d/m/Y', strtotime($lunes)) . ' al ' . date('d/m/Y', strtotime($domingo)) . ')</small> <br>';

$tablas .= '<table class="custom-table" style="width:100%;border-collapse:collapse;margin-top:10px;">'
. '<thead><tr style="background:#215D98;color:#fff;text-transform:uppercase;font-size:9.5px;">'
. '<th style="padding:8px;border:1px solid #215D98;">Nombre completo</th>'
. '<th style="padding:8px;border:1px solid #215D98;">Puesto</th>';

foreach (self::DIAS as $nombreDia) {
$tablas .= '<th style="padding:8px;border:1px solid #215D98;">' . $nombreDia . '</th>';
}

$tablas .= '</tr></thead><tbody>';

if (empty($seccion['filas'])) {
$tablas .= '<tr><td colspan="9" style="padding:8px;border:1px solid #ccc;">No se encontró información para mostrar</td></tr>';
}

foreach ($seccion['filas'] as $fila) {
$tablas .= '<tr style="background:#f2f2f2;font-size:10.5px;">'
. '<td style="padding:8px;border:1px solid #ccc;">' . $fila['nombre_completo'] . '</td>'
. '<td style="padding:8px;border:1px solid #ccc;">' . $fila['puesto'] . '</td>';

foreach (self::DIAS as $num => $nombreDia) {
$tablas .= '<td style="padding:8px;border:1px solid #ccc;">' . $fila[self::keyDia($num)] . '</td>';
}

$tablas .= '</tr>';
}

$tablas .= '</tbody></table>';
}

return '<html><head><meta charset="UTF-8"><title>Horario Personal</title></head><body>'
. '<div style="text-align:center;margin-bottom:15px;">'
. '<img src="' . $logo . '" style="width:130px;">'
. '</div>'
. $tablas
. '</body></html>';
}
}