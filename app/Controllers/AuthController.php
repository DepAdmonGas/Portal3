<?php
namespace App\Controllers;

class AuthController
{
    public function logout()
    {
        // Eliminar cookie JWT
        setcookie(
            'token',
            '',
            [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => false,   
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );

        // Redirigir al login
        header('Location: /');
        exit;
    }
}
