<?php
namespace App\Services;

use App\Models\Usuario;
use App\Models\Menu;
use App\Models\MenuCategoria;
use App\Models\Modulo;
use App\Services\MenuBadgeService;

class MenuService
{
    public static function getMenuByUsuario(int $usuario_id, ?string $modulo = null): array
    {
        $usuario = Usuario::find($usuario_id);
        if (!$usuario) return [];

        $menus = self::menusAsignados($usuario_id, $usuario, $modulo);
        $grupos = self::formatearMenu($menus, $usuario_id);
        $grupos = array_merge($grupos, self::gruposPorPermisos($usuario_id));

        return self::ordenarGrupos($grupos);
    }

    /** @return Menu[] */
    private static function menusAsignados(int $usuario_id, Usuario $usuario, ?string $modulo): array
    {
        $menus = self::menusPorRolOUsuario($usuario_id, $usuario);

        if ($modulo !== null && $modulo !== '') {
            $menus = self::agregarMenusDelModulo($menus, $modulo);
        }

        $menus = self::filtrarMenusDenegados($menus, $usuario_id);

        return self::filtrarCategoriasModulares($menus);
    }

    /** @return Menu[] */
    private static function menusPorRolOUsuario(int $usuario_id, Usuario $usuario): array
    {
        $menus = Menu::query()->with('categoria')
            ->where('activo', 1)
            ->where(function($q) use ($usuario, $usuario_id) {
                // Menús por rol
                $q->whereHas('roles', fn($r) => $r->where('puesto_id', $usuario->id_puesto))
                  // Menús permitidos por usuario
                  ->orWhereHas('usuarios', fn($u) => $u->where('usuario_id', $usuario_id)->where('tipo','permitir'));
            })
            ->get();

        /** @var Menu[] $items */
        $items = $menus->all();
        return $items;
    }

    /** @param Menu[] $menus
     *  @return Menu[]
     */
    private static function agregarMenusDelModulo(array $menus, string $modulo): array
    {
        $menusModulo = Menu::query()->with('categoria')
            ->where('activo', 1)
            ->whereHas('modulos', fn($query) => $query->where('clave', $modulo))
            ->get();

        /** @var Menu[] $itemsModulo */
        $itemsModulo = $menusModulo->all();
        $unicos = [];
        foreach (array_merge($menus, $itemsModulo) as $menu) {
            $unicos[(int)$menu->id] = $menu;
        }

        return array_values($unicos);
    }

    /** @param Menu[] $menus
     *  @return Menu[]
     */
    private static function filtrarMenusDenegados(array $menus, int $usuario_id): array
    {
        $denegados = Menu::whereHas('usuarios', fn($q) => 
            $q->where('usuario_id', $usuario_id)->where('tipo','denegar')
        )->pluck('id')->map(static fn($id): int => (int)$id)->all();

        $permitidos = [];
        foreach ($menus as $menu) {
            if (!in_array((int)$menu->id, $denegados, true)) {
                $permitidos[] = $menu;
            }
        }

        usort($permitidos, static fn($a, $b): int => (int)$a->orden <=> (int)$b->orden);
        return $permitidos;
    }

    /** @param Menu[] $menus
     *  @return Menu[]
     */
    private static function filtrarCategoriasModulares(array $menus): array
    {
        // SASISOPA, Dirección de Operaciones y SGM se construyen desde sus
        // permisos efectivos. No dejamos que los grants antiguos de
        // roles_menus/usuarios_menus muestren enlaces que contradigan esos ACL.
        $categoriasModulares = MenuCategoria::query()
            ->whereIn('nombre', ['SASISOPA', 'Dirección de Operaciones', 'SGM', 'Cursos'])
            ->get(['id', 'nombre']);
        $idsCategoriasModulares = $categoriasModulares
            ->pluck('id')
            ->map(static fn ($id): int => (int)$id)
            ->all();

        $filtrados = [];
        foreach ($menus as $menu) {
            if (in_array((int)$menu->categoria_id, $idsCategoriasModulares, true)) {
                continue;
            }
            $filtrados[] = $menu;
        }

        return $filtrados;
    }

    /** @param array<int, array<string, mixed>> $grupos
     *  @return array<int, array<string, mixed>>
     */
    private static function ordenarGrupos(array $grupos): array
    {
        $ordenes = MenuCategoria::query()->pluck('orden', 'nombre')->all();
        foreach ($grupos as &$grupo) {
            $grupo['_orden'] = (int)($ordenes[$grupo['nombre']] ?? 0);
        }
        unset($grupo);

        usort($grupos, static fn (array $a, array $b): int => $a['_orden'] <=> $b['_orden']);

        foreach ($grupos as &$grupo) {
            unset($grupo['_orden']);
        }
        unset($grupo);

        return $grupos;
    }

    /** Categorías de módulos cuyas ACL no se administran mediante roles_menus. */
    private static function gruposPorPermisos(int $usuarioId): array
    {
        $permisos = ModuloService::getPermisos($usuarioId);
        $claves = [
            'sasisopa',
            'sasisopa-cursos',
            'sgm',
            'sgm-cursos',
        ];
        $activos = Modulo::query()
            ->whereIn('clave', $claves)
            ->where('activo', 1)
            ->pluck('clave')
            ->all();

        $puedeLeer = static fn (string $clave): bool => in_array($clave, $activos, true)
            && (int)($permisos[$clave]['leer'] ?? 0) === 1;

        $grupos = [];
        $sasisopa = [];
        if ($puedeLeer('sasisopa')) {
            $sasisopa[] = self::item('sasisopa', 'Elementos SASISOPA', '/sasisopa', 'ti ti-shield-check', 1);
            $sasisopa[] = self::item('sasisopa-reporte-cre', 'Reporte Estadístico CRE', '/sasisopa/reporte-diario', 'ti ti-chart-bar', 2);
            $sasisopa[] = self::item('sasisopa-programa-mantenimiento', 'Programa Mantenimiento', '/sasisopa/control-actividades-procesos/programa-anual-mantenimiento', 'ti ti-tool', 3);
            $sasisopa[] = self::item('sasisopa-mis-cursos', 'Mis cursos', '/sasisopa/cursos', 'ti ti-school', 4);
        }

        self::agregarGrupo($grupos, 'SASISOPA', $sasisopa);

        $sgm = [];
        if ($puedeLeer('sgm')) {
            $sgm[] = self::item('sgm', 'Elementos SGM', '/sgm', 'ti ti-ruler-measure', 1);
            $sgm[] = self::item('sgm-mis-cursos', 'Mis cursos', '/sgm/cursos', 'ti ti-school', 2);
        }
        self::agregarGrupo($grupos, 'SGM', $sgm);

        $cursos = [];
        if ($puedeLeer('sasisopa-cursos')) {
            $cursos[] = self::item('sasisopa-cursos', 'Cursos (SASISOPA)', '/sasisopa/cursos', 'ti ti-school', 1);
        }
        if ($puedeLeer('sgm-cursos')) {
            $cursos[] = self::item('sgm-cursos', 'Cursos (SGM)', '/sgm/cursos', 'ti ti-school', 2);
        }
        self::agregarGrupo($grupos, 'Cursos', $cursos);

        $do = MenuDptoOperativoService::paraUsuario($usuarioId, true);
        foreach ($do as $grupoDo) {
            $items = [];
            foreach ($grupoDo['items'] ?? [] as $index => $item) {
                $item['id'] = 'do-' . (string)($item['id'] ?? $index);
                $item['padre_id'] = null;
                $item['orden'] = $index + 1;
                $item['badge'] = MenuBadgeService::contar(
                    (string)($item['clave'] ?? ''),
                    $usuarioId
                );
                $items[] = $item;
            }
            self::agregarGrupo($grupos, 'Dirección de Operaciones', $items);
        }

        return $grupos;
    }

    private static function item(string $id, string $nombre, string $ruta, string $icono, int $orden): array
    {
        return [
            'id' => $id,
            'nombre' => $nombre,
            'ruta' => $ruta,
            'icono' => $icono,
            'padre_id' => null,
            'orden' => $orden,
            'badge' => null,
        ];
    }

    private static function agregarGrupo(array &$grupos, string $nombre, array $items): void
    {
        if ($items === []) {
            return;
        }

        $categoria = MenuCategoria::query()->where('nombre', $nombre)->first();
        $grupos[] = [
            'nombre' => $categoria->nombre ?? $nombre,
            'icono' => $categoria->icono ?: 'ti ti-dots',
            'items' => $items,
        ];
    }

    /** @param Menu[] $menus
     *  @return array<int, array<string, mixed>>
     */
    private static function formatearMenu(array $menus, ?int $usuario_id = null): array
    {
        $grupos = [];

        foreach ($menus as $menu) {
            $categoriaNombre = $menu->categoria->nombre ?? 'General';
            $categoriaIcono  = $menu->categoria->icono ?? 'ti ti-dots';
            $categoriaOrden  = $menu->categoria->orden ?? 0;

            if (!isset($grupos[$categoriaNombre])) {
                $grupos[$categoriaNombre] = [
                    'nombre' => $categoriaNombre,
                    'icono'  => $categoriaIcono,
                    'orden'  => $categoriaOrden,
                    'items'  => [],
                ];
            }

            $grupos[$categoriaNombre]['items'][] = [
                'id' => $menu->id,
                'nombre' => $menu->nombre,
                'ruta' => $menu->ruta,
                'icono' => $menu->icono,
                'padre_id' => $menu->padre_id ?? null,
                'orden' => $menu->orden ?? 0,
                'badge' => ($usuario_id !== null && $usuario_id > 0)
                    ? MenuBadgeService::contar((int)$menu->id, $usuario_id)
                    : null,
            ];
        }

        foreach ($grupos as &$grupo) {
            usort($grupo['items'], static fn(array $a, array $b): int => $a['orden'] <=> $b['orden']);
        }
        unset($grupo);

        usort($grupos, static fn(array $a, array $b): int => $a['orden'] <=> $b['orden']);

        foreach ($grupos as &$grupo) {
            unset($grupo['orden']);
        }
        unset($grupo);

        return array_values($grupos);
    }
}
