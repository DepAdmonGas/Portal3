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

    
