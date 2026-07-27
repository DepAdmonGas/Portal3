<?php

namespace App\Core;

use App\Models\Usuario;

class Auth
{
    private static ?Usuario $user = null;

    public static function id(): ?int
    {
        $usuario = Session::get('usuario');

        return $usuario['id'] ?? null;
    }

    public static function user(): ?Usuario
    {
        if (self::$user !== null) {
            return self::$user;
        }

        $id = self::id();

        if ($id === null) {
            return null;
        }

        self::$user = Usuario::find($id);

        return self::$user;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function forget(): void
    {
        self::$user = null;
    }

    public static function session(): array
    {
        return Session::get('usuario') ?? [];
    }

    public static function name(): ?string
    {
        return self::session()['nombre'] ?? null;
    }
}
