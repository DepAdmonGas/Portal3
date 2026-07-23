<?php

namespace App\Repositories;

use App\Models\Estacion;

class EstacionRepository
{

    public function findById(?int $id): ?Estacion
    {
        if (!$id) {
            return null;
        }

        return Estacion::find($id);
    }
}
