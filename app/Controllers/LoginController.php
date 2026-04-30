<?php
namespace App\Controllers;
use App\Models\Usuario;
use App\Models\Estacion;
use App\Core\JWTService;
use App\Core\View;
use App\Services\ModuloService;
use App\Core\Session;

class LoginController{

    
    public function index(){
        
        $data = [
            'title' => 'Login Portal3',
            'scripts' => []
        ];
        
        View::render('login/index', $data,'auth');
       
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

        $multiestacion = ($user->id_gas == 8);

         $token = JWTService::create([
            'id' => $user->id,
            'nombre' => $user->nombre
        ]);

        $estacion = Estacion::find($user->id_gas);
        $nombreEstacion = $estacion->nombre ?? '';

        // Guardar sesión
        Session::set('usuario', [
            'id' => $user->id,
            'nombre' => $user->nombre,
            'nombre_estacion' => $nombreEstacion,   
            'id_estacion' => $user->id_gas,
            'razonsocial' => 'Todas las estaciones',
            'multiestacion' => $multiestacion
        ]);

        // Control de tiempo
        Session::set('LAST_ACTIVITY', time());

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

        ModuloService::guardarEnSesion($user->id);

        // Login correcto
        echo json_encode([
            'type' => 'success',
            'message' => 'Login exitoso',
            'token' => $token
        ]);
    }

}