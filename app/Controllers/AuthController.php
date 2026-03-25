<?php
namespace App\Controllers;

class AuthController
{
    public function logout()
    {
        session_unset();
        session_destroy();
        
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
