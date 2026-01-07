<?php
namespace App\Controllers;
use App\Models\Usuario;
use App\Core\JWTService;

class LoginController extends BaseController{

    
    public function index(){
        
        $data = ['title' => 'Login Portal3'];
        $this->view('login/index.php', $data);
       
    }

    public function login(){
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        $usuario  = $data['usuario'] ?? '';
        $password = $data['password'] ?? '';
        $ttl = 60 * 60 * 24;

        // Validación backend (obligatoria)
        if (!$usuario || !$password) {
            echo json_encode([
                'type' => 'error',
                'message' => 'Usuario y contraseña son obligatorios'
            ]);
            return;
        }

        // Buscar usuario con Eloquent
        $user = Usuario::activo()->where('usuario', $usuario)->first();

        if (!$user || !($password == $user->password)) {
            echo json_encode([
                'type' => 'error',
                'message' => 'Credenciales inválidas'
            ]);
            return;
        }

         $token = JWTService::create([
            'id' => $user->id,
            'nombre' => $user->nombre
        ]);

        setcookie(
    'token',
    $token,
    [
        'expires'  => time() + $ttl,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax'
        ]
        );

        // Login correcto
        echo json_encode([
            'type' => 'success',
            'message' => 'Login exitoso',
            'token' => $token
        ]);
    }

}