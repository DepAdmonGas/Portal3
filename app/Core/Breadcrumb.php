<?php

namespace App\Core;

class Breadcrumb
{
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

    public static function render()
    {
        $items = self::$items;

        if(empty($items)){
            return;
        }

        echo '<nav aria-label="breadcrumb">';
        echo '<ol class="breadcrumb">';

        foreach ($items as $index => $item) {

            $last = $index === array_key_last($items);

            if($last){
                echo '<li class="breadcrumb-item active">'.$item['label'].'</li>';
            }else{
                echo '<li class="breadcrumb-item"><a class="link-info text-decoration-none" href="'.$item['url'].'">'.$item['label'].'</a></li>';
            }

        }

        echo '</ol>';
        echo '</nav>';
    }
}