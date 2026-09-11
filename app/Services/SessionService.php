<?php

namespace App\Services;

use App\Core\Session;
use App\Core\CsrfToken;

class SessionService
{
    public static function start(array $data): void
    {
        Session::regenerate();

        // A post-authentication session must not retain an anonymous CSRF token.
        CsrfToken::refresh();

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
