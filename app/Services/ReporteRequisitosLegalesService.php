<?php
namespace App\Services;

use App\Models\Sasisopa\RequisitosLegalesMatriz;
use App\Models\Sasisopa\RequisitosLegalesCalendario;
use Carbon\Carbon;

class ReporteRequisitosLegalesService
{

    /**
     * Obtiene la última actualización de un requisito legal.
     */
public function ultimaActualizacion(
    int $idCalendario,
    string $vigencia,
    string $fechaInicio,
    string $fechaTermino
): array {

    $consulta = RequisitosLegalesMatriz::query()
        ->where('idcalendario', $idCalendario);

    switch ($vigencia) {

        case '5 años':

            $consulta->whereBetween(
                'fecha_emision',
                [
                    Carbon::parse($fechaInicio)->subYears(5)->format('Y-m-d'),
                    $fechaTermino
                ]
            );

        break;

        case '3 años':

            $consulta->whereBetween(
                'fecha_emision',
                [
                    Carbon::parse($fechaInicio)->subYears(3)->format('Y-m-d'),
                    $fechaTermino
                ]
            );

        break;

        case 'Bianual':

            $consulta->whereBetween(
                'fecha_emision',
                [
                    Carbon::parse($fechaInicio)->subYears(2)->format('Y-m-d'),
                    $fechaTermino
                ]
            );

        break;

        case 'Permanente':
        case 'Cuando se realice cambio':
            // Sin filtro
        break;

        default:

            $consulta->whereBetween(
                'fecha_emision',
                [
                    $fechaInicio,
                    $fechaTermino
                ]
            );

        break;

    }

    $registro = $consulta
        ->latest('id')
        ->first();

    if (!$registro) {

        return [
            'fecha_emision'     => null,
            'fecha_vencimiento' => null,
            'acusepdf'          => null,
            'requisitolegalpdf' => null,
            'cumplimiento'      => $this->calcularCumplimiento(null, null)
        ];

    }

    $fechaEmision = $registro->getRawOriginal('fecha_emision');
    $fechaVencimiento = $registro->getRawOriginal('fecha_vencimiento');

    return [

        'fecha_emision' => (
            empty($fechaEmision) ||
            $fechaEmision === '0000-00-00'
        )
            ? null
            : $registro->fecha_emision,

        'fecha_vencimiento' => (
            empty($fechaVencimiento) ||
            $fechaVencimiento === '0000-00-00'
        )
            ? null
            : $registro->fecha_vencimiento,

        'acusepdf' => $registro->acusepdf,

        'requisitolegalpdf' => $registro->requisitolegalpdf,

        'cumplimiento' => $this->calcularCumplimiento(
            $registro->acusepdf,
            $registro->requisitolegalpdf
        )

    ];

}

    /**
     * Calcula el porcentaje de cumplimiento.
     */
    public function calcularCumplimiento(
        ?string $acuse,
        ?string $requisito
    ): array {

        $acuse = !empty($acuse);

        $requisito = !empty($requisito);

        if (!$acuse && !$requisito) {

            return [
                'texto' => '0 %',
                'valor' => 0
            ];

        }

        if ($acuse && !$requisito) {

            return [
                'texto' => '50 %',
                'valor' => 50
            ];

        }

        return [

            'texto' => '100 %',

            'valor' => 100

        ];

    }

    /**
 * Obtiene los meses donde aplica renovación.
 */
    public function calcularRenovacion($calendario): string
    {
        $meses = [];

        $mapa = [
            'enero' => 'Enero',
            'febrero' => 'Febrero',
            'marzo' => 'Marzo',
            'abril' => 'Abril',
            'mayo' => 'Mayo',
            'junio' => 'Junio',
            'julio' => 'Julio',
            'agosto' => 'Agosto',
            'septiembre' => 'Septiembre',
            'octubre' => 'Octubre',
            'noviembre' => 'Noviembre',
            'diciembre' => 'Diciembre',
        ];

        foreach ($mapa as $campo => $nombre) {

            if ((int) $calendario->$campo === 1) {
                $meses[] = $nombre;
            }

        }

        return count($meses)
            ? implode(', ', $meses)
            : 'S/I';
    }

    /**
 * Calcula el estatus visual del requisito.
 */
public function calcularEstatus(
    string $vigencia,
    ?string $fechaVencimiento,
    int $cumplimiento
): array {

    $hoy = date('Y-m-d');

    if (
        in_array($vigencia, [
            'Cuando se realice cambio',
            'Permanente'
        ])
    ) {

        if ($cumplimiento === 100) {

            return [
                'titulo' => 'Finalizado',
                'color' => 'success'
            ];

        }

        return [
            'titulo' => 'Pendiente',
            'color' => 'warning'
        ];
    }

    if (
        !empty($fechaVencimiento)
        && $fechaVencimiento !== '0000-00-00'
    ) {

        $notificacion = date(
            'Y-m-d',
            strtotime($fechaVencimiento . ' -30 days')
        );

        if ($fechaVencimiento < $hoy) {

            return [
                'titulo' => 'Vencido',
                'color' => 'danger'
            ];

        }

        if ($notificacion <= $hoy) {

            return [
                'titulo' => 'Próximo a vencer',
                'color' => 'warning'
            ];

        }

    }

    if ($cumplimiento === 100) {

        return [
            'titulo' => 'Finalizado',
            'color' => 'success'
        ];

    }

    return [
        'titulo' => 'Pendiente',
        'color' => 'warning'
    ];
}

/**
 * Calcula el porcentaje de cumplimiento de un nivel de gobierno.
 */
public function porcentajeNivel(array $detalle): array
{
    $total = count($detalle);

    if ($total === 0) {

        return [
            'cumple' => 0,
            'nocumple' => 100
        ];

    }

    $suma = collect($detalle)
        ->sum(function ($item) {

            return $item['cumplimiento']['valor'] ?? 0;

        });


    $cumple = round($suma / $total);


    return [

        'cumple' => $cumple,

        'nocumple' => 100 - $cumple

    ];
}

/**
 * Obtiene toda la información de un nivel de gobierno.
 */
public function obtenerNivel(
    string $nivelGobierno,
    int $idEstacion,
    string $fechaInicio,
    string $fechaTermino
): array {

    $calendarios = RequisitosLegalesCalendario::query()

        ->with('requisito')

        ->where('id_estacion', $idEstacion)

        ->where('nivel_gobierno', $nivelGobierno)

        ->where('estado', 1)

        ->orderBy('id_requisito_legal')

        ->get();

    $items = [];

    foreach ($calendarios as $calendario) {

    $ultima = $this->ultimaActualizacion(
        $calendario->id,
        $calendario->vigencia,
        $fechaInicio,
        $fechaTermino
    );


    $cumplimiento = $this->calcularCumplimiento(
        $ultima['acusepdf'] ?? null,
        $ultima['requisitolegalpdf'] ?? null
    );


    $renovacion = $this->calcularRenovacion($calendario);


    $estatus = $this->calcularEstatus(
        $calendario->vigencia,
        $ultima['fecha_vencimiento'] ?? null,
        $cumplimiento['valor']
    );


    $items[] = [

        'id' => $calendario->id,

        'dependencia' => $calendario->requisito?->dependencia ?? 'S/I',

        'permiso' => $calendario->requisito?->permiso ?? 'S/I',

        'vigencia' => $calendario->vigencia,

        'fecha_emision' =>
            !empty($ultima['fecha_emision']) &&
            $ultima['fecha_emision'] != '0000-00-00'
                ? formatearFecha(
                    $ultima['fecha_emision'] instanceof Carbon
                        ? $ultima['fecha_emision']->format('Y-m-d')
                        : $ultima['fecha_emision']
                )
                : 'S/I',

        'fecha_vencimiento' => 
            !empty($ultima['fecha_vencimiento']) &&
            $ultima['fecha_vencimiento'] != '0000-00-00'
                ? formatearFecha(
                    $ultima['fecha_vencimiento'] instanceof Carbon
                        ? $ultima['fecha_vencimiento']->format('Y-m-d')
                        : $ultima['fecha_vencimiento']
                )
                : 'S/I',

        'acusepdf' => $ultima['acusepdf'] ?? null,

        'requisitolegalpdf' => $ultima['requisitolegalpdf'] ?? null,

        'cumplimiento' => $cumplimiento,

        'renovacion' => $renovacion,

        'estatus' => $estatus
    ];
}

    return [

        'items' => $items,

        'porcentaje' => $this->porcentajeNivel($items)

    ];
}

public function obtenerTodosLosNiveles(
    int $idEstacion,
    string $fechaInicio,
    string $fechaTermino
): array {

    return [
        'Municipal' => $this->obtenerNivel(
            'Municipal',
            $idEstacion,
            $fechaInicio,
            $fechaTermino
        ),

        'Estatal' => $this->obtenerNivel(
            'Estatal',
            $idEstacion,
            $fechaInicio,
            $fechaTermino
        ),

        'Federal' => $this->obtenerNivel(
            'Federal',
            $idEstacion,
            $fechaInicio,
            $fechaTermino
        ),

        'Varios' => $this->obtenerNivel(
            'Varios',
            $idEstacion,
            $fechaInicio,
            $fechaTermino
        ),
    ];
}

}