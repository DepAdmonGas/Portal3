<?php

namespace App\Controllers;

use App\Models\Sgm\ControlDocumental;

class DocumentoController
{

    public static function validaDocumento(string $nombre, int $idEstacion): array
    {
        $documento = ControlDocumental::query()
            ->with('documento')
            ->where('id_estacion', $idEstacion)
            ->whereHas('documento', function ($query) use ($nombre) {
                $query->where('nombre', $nombre);
            })
            ->latest('fecha')
            ->first();

        return [
            'archivo' => '/uploads/archivos/FormatosSGM/' . $documento?->archivo ?? '',
        ];
    }
}
