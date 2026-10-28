<?php

namespace App\Core;

class Breadcrumb
{
    /**
     * Pantalla principal de Departamento Operativo.
     * Es el único origen que acorta el breadcrumb.
     */
    public const RUTA_HOME_DO = '/departamento-operativo';

    /**
     * Rótulo que identifica a una página de Departamento Operativo.
     * Si el breadcrumb no lo contiene no se toca: así los demás módulos
     * del portal quedan fuera de esta excepción.
     */
    public const ETIQUETA_DO = 'Dirección de Operaciones';

    protected static array $items = [];

    public static function add($label, $url = null)
    {
        self::$items[] = [
            'label' => $label,
            'url' => $url
        ];
    }

    public static function get()
    {
        return self::$items;
    }

    /**
     * Indica si un elemento del breadcrumb es un control auxiliar
     * (los desplegables de mes y año) y no un rótulo de navegación.
     */
    protected static function esUtil($label): bool
    {
        return str_contains((string)$label, 'dropdown-toggle');
    }

    /**
     * Acorta el breadcrumb cuando el usuario entró desde la pantalla
     * principal de Departamento Operativo.
     *
     * Al mostrar submódulos como accesos directos en
     * /departamento-operativo el usuario ya no pasa por la pantalla del
     * módulo, así que la ruta interna sobra:
     *
     *   Home / Dirección de Operaciones / Almacén / Mantenimiento / TPV
     *
     * se muestra como:
     *
     *   Home / Dirección de Operaciones / TPV
     *
     * Si el usuario navegó desde el menú del módulo el referer no es la
     * pantalla principal y el breadcrumb queda intacto.
     *
     * @return array Mismo formato que self::$items.
     */
    protected static function acortarDesdeHomeDO(array $items): array
    {
        // Nada que recortar.
        if (count($items) < 3) {
            return $items;
        }

        $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');

        if ($referer === '') {
            return $items;
        }

        $ruta = (string)(parse_url($referer, PHP_URL_PATH) ?? '');

        if (rtrim($ruta, '/') !== self::RUTA_HOME_DO) {
            return $items;
        }

        // Solo se aplica a páginas de Departamento Operativo.
        $indiceDo = null;

        foreach ($items as $i => $item) {

            $etiqueta = trim(strip_tags((string)($item['label'] ?? '')));

            if ($etiqueta === self::ETIQUETA_DO) {
                $indiceDo = $i;
                break;
            }
        }

        if ($indiceDo === null) {
            return $items;
        }

        /*
         * El destino es el último rótulo real. Varias páginas cierran su
         * breadcrumb con los desplegables de mes y año, y recortar justo
         * en el último elemento haría desaparecer el título.
         */
        $titulo = null;

        for ($i = count($items) - 1; $i > $indiceDo; $i--) {

            if (!self::esUtil($items[$i]['label'] ?? '')) {
                $titulo = $i;
                break;
            }
        }

        if ($titulo === null) {
            return $items;
        }

        /*
         * Los desplegables de mes y año van DESPUÉS del título y no son
         * niveles de navegación: son controles de la página. Se conservan
         * para que el usuario pueda cambiar el periodo desde el breadcrumb
         * aunque haya entrado por un acceso directo del sidebar.
         */
        $cola = array_slice($items, $titulo);

        // Ya tiene la forma corta: no hay nada que recortar.
        if ($titulo - $indiceDo <= 1) {
            return $items;
        }

        return array_merge(
            array_slice($items, 0, $indiceDo + 1),
            $cola
        );
    }

    public static function render()
    {
        $items = self::acortarDesdeHomeDO(self::$items);

        if(empty($items)){
            return;
        }

        echo '<nav aria-label="breadcrumb">';
        echo '<ol class="breadcrumb">';

        foreach ($items as $index => $item) {

            $last = $index === array_key_last($items);

            if ($last || ($item['url'] ?? null) === null) {
                echo '<li class="breadcrumb-item active" aria-current="page">' . $item['label'] . '</li>';
            } else {
                echo '<li class="breadcrumb-item"><a class="link-info text-decoration-none" href="'.$item['url'].'">'.$item['label'].'</a></li>';
            }

        }

        echo '</ol>';
        echo '</nav>';
    }
}
