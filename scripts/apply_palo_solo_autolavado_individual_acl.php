<?php

declare(strict_types=1);

/**
 * Script de autorización ACL individual en desarrollo para Encargados de Palo Solo.
 * Asigna exclusivamente la localidad departamental Autolavado (op_rh_localidades.id = 9)
 * para los Encargados de Palo Solo identificados en el diagnóstico técnico.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use App\Core\Database;

$environment = strtolower((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: ''));
if ($environment === '') {
    Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
    $environment = strtolower((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: ''));
}

if (!in_array($environment, ['dev', 'development', 'local'], true)) {
    echo "ERROR: Este script sólo puede ejecutarse en ambiente de desarrollo local.\n";
    exit(1);
}

Database::initialize();
$db = Capsule::connection();

// Encargados activos de Palo Solo identificados:
// 1. Gustavo Ramírez Antonio (id: 52, usuario: gustavora, id_gas: 2, id_puesto: 6)
// 2. Marco Antonio Aparicio Estrada (id: 117, usuario: marcoae16, id_gas: 2, id_puesto: 6)
$targetUserIds = [52, 117];

echo "=== APLICACIÓN DE ACL INDIVIDUAL: PALO SOLO -> AUTOLAVADO ===\n";

foreach ($targetUserIds as $userId) {
    $user = $db->table('tb_usuarios')
        ->where('id', $userId)
        ->where('estatus', 0)
        ->where('id_puesto', 6)
        ->where('id_gas', 2)
        ->first(['id', 'nombre', 'usuario', 'id_puesto', 'id_gas', 'estatus']);

    if (!$user) {
        echo "[ERROR] Usuario ID {$userId} no cumple con puesto Encargado (6) y estación Palo Solo (2).\n";
        continue;
    }

    echo sprintf(
        "Usuario verificado: ID %d | %s (%s) | Puesto: %d | Estación: %d\n",
        $user->id,
        $user->nombre,
        $user->usuario,
        $user->id_puesto,
        $user->id_gas
    );

    // Alcance estricto: estación 2 (Palo Solo), departamento localidad 9 (Autolavado)
    $scopeData = [
        'id_usuario' => (int) $user->id,
        'estaciones' => json_encode([2]),
        'departamentos_puestos' => json_encode([]),
        'departamentos_localidades' => json_encode([9]),
        'activo' => 1,
    ];

    $existing = $db->table('tb_multiestacion_usuario')
        ->where('id_usuario', (int) $user->id)
        ->first();

    if ($existing) {
        $db->table('tb_multiestacion_usuario')
            ->where('id', $existing->id)
            ->update($scopeData);
        echo "  -> Registro existente ID {$existing->id} actualizado con alcance [estaciones: [2], departamentos_localidades: [9]].\n";
    } else {
        $insertId = $db->table('tb_multiestacion_usuario')->insertGetId($scopeData);
        echo "  -> Nuevo registro individual creado con ID {$insertId}.\n";
    }

    // Readback de verificación
    $readback = $db->table('tb_multiestacion_usuario')
        ->where('id_usuario', (int) $user->id)
        ->where('activo', 1)
        ->first();

    echo sprintf(
        "  -> Readback: ID %d | id_usuario: %d | estaciones: %s | deptos: %s | activo: %d\n",
        $readback->id,
        $readback->id_usuario,
        $readback->estaciones,
        $readback->departamentos_localidades,
        $readback->activo
    );
}

echo "=== ACL INDIVIDUAL COMPLETADO SATISFACTORIAMENTE ===\n";
