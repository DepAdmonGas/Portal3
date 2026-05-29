<?php

if (!function_exists('base_url')) {
function base_url(): string
{
return rtrim($_ENV['APP_URL'] ?? '', '/');
}
}

if (!function_exists('asset')) {
function asset(string $path): string
{
return base_url() . '/assets/' . ltrim($path, '/');
}
}

function formatearFecha($fecha)
{
if (empty($fecha)) return '';

$date = \Carbon\Carbon::parse($fecha);

$meses = [
1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

return $date->format('d') . ' de ' . $meses[(int)$date->format('m')] . ' del ' . $date->format('Y');
}

if (!function_exists('formatearFechaCorta')) {

function formatearFechaCorta($fecha)
{
if (empty($fecha)) return '';

// Si es objeto (Carbon o DateTime)
if ($fecha instanceof \DateTimeInterface) {
return $fecha->format('d-m-Y');
}

$fecha = (string) $fecha;

// Fechas inválidas comunes
if (
$fecha === '0000-00-00' ||
str_contains($fecha, '-0001')
) {
return '';
}

try {
return \Carbon\Carbon::parse($fecha)->format('d-m-Y');
} catch (\Exception $e) {
return '';
}
}
}

function formatDate($fecha)
{
if (empty($fecha)) return '';

return \Carbon\Carbon::parse($fecha)->format('Y-m-d');
}

function nombremes($mes){
if ($mes=="01") $mes="Enero";
if ($mes=="02") $mes="Febrero";
if ($mes=="03") $mes="Marzo";
if ($mes=="04") $mes="Abril";
if ($mes=="05") $mes="Mayo";
if ($mes=="06") $mes="Junio";
if ($mes=="07") $mes="Julio";
if ($mes=="08") $mes="Agosto";
if ($mes=="09") $mes="Septiembre";
if ($mes=="10") $mes="Octubre";
if ($mes=="11") $mes="Noviembre";
if ($mes=="12") $mes="Diciembre";
return $mes;
}

function normalizarFecha($fecha): ?string
{
if (empty($fecha)) {
return null;
}

$fecha = trim((string)$fecha);

$invalidas = [
'0000-00-00',
'0000-00-00 00:00:00',
'-0001-11-30',
'-0001-11-30 00:00:00'
];

if (in_array($fecha, $invalidas)) {
return null;
}

try {

return \Carbon\Carbon::parse($fecha)
->format('Y-m-d');

} catch (\Throwable $e) {

return null;
}
}

// ============================================================
// SECURITY: BAJO #35 - Función segura para crear directorios
// Uso: mkdir_safe('/path/to/directory')
// Permiso 0755: propietario rw, otros r-x
// ============================================================
if (!function_exists('mkdir_safe')) {
/**
* Crea un directorio con permisos seguros (0755)
* 
* SECURITY: BAJO #35 - Reemplaza mkdir con 0777 inseguro
* 
* @param string $path Ruta del directorio a crear
* @param bool $recursive Crear directorios padres si no existen
* @return bool True si se creó o ya existe
* @throws \Exception Si no se puede crear el directorio
*/
function mkdir_safe(string $path, bool $recursive = true): bool
{
// Si ya existe, retornar true
if (is_dir($path)) {
return true;
}

// Crear directorio con permisos seguros (0755)
// propietario: leer, escribir, ejecutar
// grupo: leer, ejecutar
// otros: leer, ejecutar
$result = mkdir($path, 0755, $recursive);

if (!$result) {
// Loggear el error
$error = error_get_last();
\App\Core\Logger::getLogger()->error('Error al crear directorio', [
'path' => $path,
'error' => $error['message'] ?? 'Error al crear'
]);

throw new \Exception("No se pudo crear el directorio: {$path}");
}

// Loggear creación exitosa (solo en desarrollo)
if ($_ENV['APP_ENV'] ?? 'dev' === 'dev') {
\App\Core\Logger::getLogger()->debug('Directorio creado con permisos seguros', [
'path' => $path,
'permissions' => '0755'
]);
}

return true;
}
}

// ============================================================
// TELEGRAM: Obtiene el token del bot según el entorno
// APP_ENV=dev|demo   → usa TELEGRAM_BOT_TOKEN_DEMO (pruebas locales)
// APP_ENV=prod       → usa TELEGRAM_BOT_TOKEN    (servidor productivo)
// ============================================================
if (!function_exists('telegramBotToken')) {
function telegramBotToken(): string
{
$env = $_ENV['APP_ENV'] ?? 'prod';

if (in_array($env, ['dev', 'demo', 'local'], true)) {
return $_ENV['TELEGRAM_BOT_TOKEN_DEMO'] ?? $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
}

return $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
}
}


/**
* Sanitización de entrada de usuario
* Previene XSS e inyección de datos maliciosos
* 
* @param mixed $value Valor a sanitizar
* @param string $type Tipo de sanitización: string, int, email, url, alphanumeric, uuid
* @return mixed Valor sanitizado
*/
if (!function_exists('sanitize_input')) {
function sanitize_input(mixed $value, string $type = 'string'): mixed
{
if ($value === null || $value === '') {
return null;
}

switch ($type) {
case 'string':
// Eliminar espacios extra y escapar HTML
$value = trim($value ?? '');
return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

case 'int':
case 'integer':
return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);

case 'float':
case 'decimal':
return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT);

case 'email':
return filter_var($value, FILTER_SANITIZE_EMAIL);

case 'url':
return filter_var($value, FILTER_SANITIZE_URL);

case 'alphanumeric':
return preg_replace('/[^a-zA-Z0-9]/', '', $value);

case 'uuid':
// Validar formato UUID
if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
return $value;
}
return null;

default:
return $value;
}
}
}

/**
* Validador de entrada con reglas
* 
* @param array $data Datos a validar
* @param array $rules Reglas de validación: 'campo' => 'required|email|numeric|min:1|max:255'
* @return array Array de errores (vacío si todo ok)
*/
if (!function_exists('validate_input')) {
function validate_input(array $data, array $rules): array
{
$errors = [];

foreach ($rules as $field => $ruleSet) {
$value = $data[$field] ?? null;
$rulesArray = explode('|', $ruleSet);

foreach ($rulesArray as $rule) {
// Extraer parámetro si existe (ej: min:1)
$param = null;
if (strpos($rule, ':') !== false) {
[$rule, $param] = explode(':', $rule, 2);
}

switch ($rule) {
case 'required':
if (empty($value) && $value !== '0') {
$errors[$field] = "El campo {$field} es requerido";
}
break;

case 'email':
if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
$errors[$field] = "El campo {$field} debe ser un email válido";
}
break;

case 'numeric':
if ($value && !is_numeric($value)) {
$errors[$field] = "El campo {$field} debe ser numérico";
}
break;

case 'min':
if ($value && (int)$value < (int)$param) {
$errors[$field] = "El campo {$field} debe ser mayor a {$param}";
}
break;

case 'max':
if ($value && strlen($value) > (int)$param) {
$errors[$field] = "El campo {$field} excede el límite de {$param} caracteres";
}
break;
}
}
}

return $errors;
}
}


