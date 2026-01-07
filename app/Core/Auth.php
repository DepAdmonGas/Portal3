<?php
namespace App\Core;

use App\Models\Usuario;

class Auth
{
    private static ?Usuario $user = null;

    public static function id(): ?int
    {
        return $GLOBALS['user']['sub'] ?? null;
    }

    public static function user(): ?Usuario
    {
        if (self::$user) {
            return self::$user;
        }

        $id = self::id();

        if (!$id) {
            return null;
        }

        self::$user = Usuario::find($id);

        return self::$user;
    }

}
