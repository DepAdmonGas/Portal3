<?php

namespace App\Repositories;

use App\Models\Usuario;

class UsuarioRepository
{

    public function findActiveByUsername(
        string $usuario
    ): ?Usuario {

        return Usuario::activo()
            ->where('usuario', $usuario)
            ->first();
    }



    public function findById(
        int $id
    ): ?Usuario {

        return Usuario::find($id);
    }



    public function findActiveById(
        int $id
    ): ?Usuario {

        return Usuario::activo()
            ->where('id', $id)
            ->first();
    }
}
