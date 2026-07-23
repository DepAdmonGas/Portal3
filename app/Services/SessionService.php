<?php

namespace App\Services;

use App\Core\Session;

class SessionService
{
    public static function start(array $data): void
    {
        Session::regenerate();

        Session::set('usuario', $data);

        Session::set(
            'LAST_ACTIVITY',
            time()
        );
    }

    public static function touch(): void
    {
        Session::set(
            'LAST_ACTIVITY',
            time()
        );
    }

    public static function logout(): void
    {
        Session::destroy();
    }
}
