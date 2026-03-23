<?php

namespace App\Services;

use App\Models\Usuario;
use App\Models\Menu;

class MenuService
{
    public static function getMenuByUsuario($usuario_id)
    {
        $usuario = Usuario::find($usuario_id);

        if (!$usuario) {
            return [];
        }

        // 🔹 Menús por puesto
        $menusRol = Menu::with('categoria')
            ->whereHas('roles', function ($q) use ($usuario) {
                $q->where('puesto_id', $usuario->id_puesto);
            })->get();

        // 🔹 Menús por usuario (permitir)
        $menusUsuario = Menu::with('categoria')
            ->whereHas('usuarios', function ($q) use ($usuario_id) {
                $q->where('usuario_id', $usuario_id)
                  ->where('tipo', 'permitir');
            })->get();

        // 🔹 Combinar y quitar duplicados
        $menus = $menusRol->merge($menusUsuario)->unique('id');

        // 🔹 IDs denegados
        $denegados = Menu::whereHas('usuarios', function ($q) use ($usuario_id) {
            $q->where('usuario_id', $usuario_id)
              ->where('tipo', 'denegar');
        })->pluck('id')->toArray();

        // 🔹 Filtrar denegados
        $menus = $menus->reject(function ($menu) use ($denegados) {
            return in_array($menu->id, $denegados);
        });

        // 🔥 FORMATEAR (CLAVE)
        return self::formatearMenu($menus);
    }

    private static function formatearMenu($menus)
    {
        $grupos = [];

        foreach ($menus as $menu) {

            // 🔹 Validar categoría
            $categoriaNombre = $menu->categoria ? $menu->categoria->nombre : 'General';
            $categoriaIcono  = $menu->categoria ? $menu->categoria->icono : 'ti ti-dots';

            if (!isset($grupos[$categoriaNombre])) {
                $grupos[$categoriaNombre] = [
                    'nombre' => $categoriaNombre,
                    'icono'  => $categoriaIcono,
                    'items'  => []
                ];
            }

            $grupos[$categoriaNombre]['items'][] = [
                'id' => $menu->id,
                'nombre' => $menu->nombre,
                'ruta' => $menu->ruta,
                'icono' => $menu->icono,
                'padre_id' => $menu->padre_id ?? null
            ];
        }

        return array_values($grupos);
    }
}