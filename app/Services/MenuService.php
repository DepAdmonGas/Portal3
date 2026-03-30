<?php
namespace App\Services;

use App\Models\Usuario;
use App\Models\Menu;

class MenuService
{
    public static function getMenuByUsuario(int $usuario_id, ?string $modulo = null)
    {
        $usuario = Usuario::find($usuario_id);
        if (!$usuario) return [];

        // 🔹 1. MENÚS POR PERMISOS (ROL O USUARIO)
        $menus = Menu::with('categoria')
            ->where('activo', 1)
            ->where(function($q) use ($usuario, $usuario_id) {
                // Menús por rol
                $q->whereHas('roles', fn($r) => $r->where('puesto_id', $usuario->id_puesto))
                  // Menús permitidos por usuario
                  ->orWhereHas('usuarios', fn($u) => $u->where('usuario_id', $usuario_id)->where('tipo','permitir'));
            })
            ->get();

        // 🔹 2. MENÚS DEL MÓDULO (solo si se especifica módulo)
        if ($modulo) {
            $menusModulo = Menu::with('categoria')
                ->where('activo', 1)
                ->whereHas('modulos', fn($m) => $m->where('clave', $modulo))
                ->get();

            // Unir con los permisos, evitando duplicados
            $menus = $menus->merge($menusModulo)->unique('id');
        }

        // 🔹 3. FILTRAR MENÚS DENEGADOS
        $denegados = Menu::whereHas('usuarios', fn($q) => 
            $q->where('usuario_id', $usuario_id)->where('tipo','denegar')
        )->pluck('id');

        $menus = $menus->whereNotIn('id', $denegados)->sortBy('orden')->values();

        // 🔹 4. FORMATEAR PARA FRONT
        return self::formatearMenu($menus);
    }

    private static function formatearMenu($menus)
{
    $grupos = [];

    foreach ($menus as $menu) {
        $categoriaNombre = $menu->categoria->nombre ?? 'General';
        $categoriaIcono  = $menu->categoria->icono ?? 'ti ti-dots';
        $categoriaOrden  = $menu->categoria->orden ?? 0; // 🔹 tomar orden de categoría

        if (!isset($grupos[$categoriaNombre])) {
            $grupos[$categoriaNombre] = [
                'nombre' => $categoriaNombre,
                'icono'  => $categoriaIcono,
                'orden'  => $categoriaOrden, // 🔹 guardamos orden
                'items'  => []
            ];
        }

        $grupos[$categoriaNombre]['items'][] = [
            'id' => $menu->id,
            'nombre' => $menu->nombre,
            'ruta' => $menu->ruta,
            'icono' => $menu->icono,
            'padre_id' => $menu->padre_id ?? null,
            'orden' => $menu->orden ?? 0
        ];
    }

    // 🔹 Ordenar items dentro de cada categoría
    foreach ($grupos as &$grupo) {
        usort($grupo['items'], fn($a, $b) => $a['orden'] <=> $b['orden']);
    }

    // 🔹 Ordenar categorías por su orden
    usort($grupos, fn($a, $b) => $a['orden'] <=> $b['orden']);

    // 🔹 Eliminar clave temporal 'orden' antes de devolver
    foreach ($grupos as &$grupo) {
        unset($grupo['orden']);
    }

    return array_values($grupos);
}
}