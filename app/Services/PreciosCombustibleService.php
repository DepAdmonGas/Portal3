<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Operativo\FormatoPrecios;
use App\Models\Operativo\FormatoPreciosDetalleC;
use App\Models\Operativo\FormatoPreciosTransporte;
use App\Models\Usuario;
use App\Services\ModuloDptoOperativoService;
use App\Services\TelegramService;
use Illuminate\Database\Capsule\Manager as Capsule;

class PreciosCombustibleService
{
    public const MODULE_KEY = 'precios-diarios-combustible';

    public const PRODUCTOS = ['Super', 'Premium', 'Diesel'];

    public const TRANSPORTE_DEFAULTS = [
        ['detalle' => 'Tuxpan', 'precio' => 0.73],
        ['detalle' => 'Tizayuca', 'precio' => 0.4142],
        ['detalle' => 'Puebla', 'precio' => 0.53],
    ];

    public const ESTACIONES_TELEGRAM = [1, 2, 3, 4, 5, 6, 7, 14];

    private const COLOR_PRODUCTO = [
        'Super'   => 'background-color:#74bc1f;color:white;',
        'Premium' => 'background-color:#e01883;color:white;',
        'Diesel'  => 'background-color:#5c108c;color:white;',
    ];

    public static function getPermisos(): array
    {
        $usuario = Auth::user();
        $sessionUsuario = Session::get('usuario');
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);
        $idPuesto = (int)($usuario->id_puesto ?? 0);

        $allPerms = ModuloDptoOperativoService::getPermisos($idUsuario, 'importacion');
        $permisosDb = $allPerms['importacion'] ?? [];

        $tieneSubmenu = false;
        foreach ($permisosDb['submenus'] ?? [] as $sm) {
            if (($sm['clave'] ?? '') === self::MODULE_KEY) {
                $tieneSubmenu = true;
                break;
            }
        }

        $leer = !empty($permisosDb['leer']);
        $crear = !empty($permisosDb['crear']);
        $editar = !empty($permisosDb['editar']);

        return [
            'id_usuario'   => $idUsuario,
            'id_puesto'    => $idPuesto,
            'puedeVer'     => $tieneSubmenu && $leer,
            'puedeCrear'   => $tieneSubmenu && $crear,
            'puedeEditar'  => $tieneSubmenu && $editar,
            'puedeDetalle' => $tieneSubmenu && $leer && $editar,
            'esPuesto13'   => $idPuesto === 13,
        ];
    }

    public static function getData(int $year, int $mes): array
    {
        $daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $mes, $year);

        $registros = FormatoPrecios::where('year', $year)
            ->where('mes', $mes)
            ->get()
            ->keyBy(function ($r) {
                return (string)$r->fecha;
            });

        $rows = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $fecha = sprintf('%04d-%02d-%02d', $year, $mes, $day);
            $registro = $registros[$fecha] ?? null;

            $estatus = $registro ? (int)$registro->estatus : -1;
            $id = $registro ? (int)$registro->id : 0;

            $rowClass = 'bg-danger-subtle';
            if ($estatus === 1) {
                $rowClass = 'bg-success-subtle';
            } elseif ($estatus === 0) {
                $rowClass = 'bg-warning-subtle';
            }

            $rows[] = [
                'day'       => $day,
                'fecha'     => formatearFecha($fecha),
                'fechaRaw'  => $fecha,
                'id'        => $id,
                'estatus'   => $estatus,
                'rowClass'  => $rowClass,
            ];
        }

        return $rows;
    }

    public static function crear(int $year, int $mes, string $fecha): int
    {
        return Capsule::transaction(function () use ($year, $mes, $fecha) {
            $formato = FormatoPrecios::create([
                'fecha'   => $fecha,
                'year'    => $year,
                'mes'     => $mes,
                'estatus' => 0,
            ]);

            $idFormato = $formato->id;

            foreach (self::PRODUCTOS as $producto) {
                FormatoPreciosDetalleC::create([
                    'id_precio'          => $idFormato,
                    'producto'           => $producto,
                    'pemex'              => 0,
                    'delivery_montera'   => 0,
                    'delivery_tuxpan'    => 0,
                    'delivery_vopak'     => 0,
                    'pickup_montera'     => 0,
                    'pickup_tuxpan'      => 0,
                    'pickup_vopak'       => 0,
                    'pickup_tizayuca'    => 0,
                    'pickup_puebla'      => 0,
                    'p1' => 0, 'p2' => 0, 'p3' => 0, 'p4' => 0, 'p5' => 0,
                    'p6' => 0, 'p7' => 0, 'p8' => 0, 'p9' => 0, 'p10' => 0,
                ]);
            }

            foreach (self::TRANSPORTE_DEFAULTS as $t) {
                FormatoPreciosTransporte::create([
                    'id_formato' => $idFormato,
                    'detalle'    => $t['detalle'],
                    'precio'     => $t['precio'],
                ]);
            }

            return $idFormato;
        });
    }

    public static function updateField(int $id, string $valor, int $num): bool
    {
        $fieldMap = [
            1  => ['table' => 'detalle_c', 'column' => 'pemex'],
            2  => ['table' => 'detalle_c', 'columns' => ['delivery_montera', 'delivery_tuxpan']],
            4  => ['table' => 'detalle_c', 'column' => 'delivery_vopak'],
            9  => ['table' => 'detalle_c', 'columns' => ['pickup_tuxpan', 'pickup_montera']],
            10 => ['table' => 'detalle_c', 'column' => 'pickup_vopak'],
            12 => ['table' => 'detalle_c', 'column' => 'pickup_tizayuca'],
            13 => ['table' => 'detalle_c', 'column' => 'pickup_puebla'],
            14 => ['table' => 'transporte', 'column' => 'precio'],
            15 => ['table' => 'formato', 'column' => 'fecha'],
        ];

        if (!isset($fieldMap[$num])) {
            return false;
        }

        $def = $fieldMap[$num];

        if ($def['table'] === 'formato') {
            return FormatoPrecios::where('id', $id)->update([$def['column'] => $valor]) > 0;
        }

        if ($def['table'] === 'transporte') {
            return FormatoPreciosTransporte::where('id', $id)->update([$def['column'] => $valor]) > 0;
        }

        $model = FormatoPreciosDetalleC::where('id', $id);
        if (isset($def['columns'])) {
            $update = [];
            foreach ($def['columns'] as $col) {
                $update[$col] = $valor;
            }
            return $model->update($update) > 0;
        }

        return $model->update([$def['column'] => $valor]) > 0;
    }

    public static function finalizar(int $id): bool
    {
        $ok = FormatoPrecios::where('id', $id)->update(['estatus' => 1]) > 0;
        if (!$ok) {
            return false;
        }

        $formato = FormatoPrecios::find($id);
        if ($formato) {
            self::notificarFinalizacion($formato->fecha);
        }

        return true;
    }

    public static function togglePrecioBajo(int $idPrecio, int $valCheck, int $num, string $producto): bool
    {
        $columnMap = [
            1 => 'p1', 2 => 'p2', 3 => 'p3', 4 => 'p4', 5 => 'p5',
            6 => 'p6', 7 => 'p7', 8 => 'p8', 9 => 'p9', 10 => 'p10',
        ];

        if (!isset($columnMap[$num])) {
            return false;
        }

        $valor = $valCheck === 0 ? 1 : 0;

        return FormatoPreciosDetalleC::where('id_precio', $idPrecio)
            ->where('producto', $producto)
            ->update([$columnMap[$num] => $valor]) > 0;
    }

    public static function getFormato(int $id): ?array
    {
        $formato = FormatoPrecios::find($id);
        if (!$formato) {
            return null;
        }

        $transporte = FormatoPreciosTransporte::where('id_formato', $id)->get();
        $detalle = FormatoPreciosDetalleC::where('id_precio', $id)->get();

        return [
            'formato'    => $formato,
            'transporte' => $transporte,
            'detalle'    => $detalle,
        ];
    }

    public static function getTransporteTarifa(int $idFormato, string $terminal): float
    {
        $row = FormatoPreciosTransporte::where('id_formato', $idFormato)
            ->where('detalle', $terminal)
            ->first();

        if (!$row || $row->precio == 0) {
            return 0;
        }

        $precio = (float)$row->precio;
        $iva = number_format($precio * 0.16, 4);
        $retencion = number_format($precio * 0.04, 4);
        return number_format($precio + $iva - $retencion, 4);
    }

    public static function getReporte(int $id): array
    {
        $formato = FormatoPrecios::find($id);
        if (!$formato) {
            return [];
        }

        $ocultarVopak = self::isVopakHidden($formato->fecha);

        $tarifas = [];
        foreach (['Tuxpan', 'Vopak', 'Tizayuca', 'Puebla'] as $terminal) {
            $tarifas[$terminal] = self::getTransporteTarifa($id, $terminal);
        }

        $detalle = FormatoPreciosDetalleC::where('id_precio', $id)->get()
            ->keyBy('producto');

        $rowDefs = [
            1  => ['modalidad' => 'Network Pemex',   'terminal' => 'Azcapozalco',    'field' => 'pemex',            'tarifa' => null,   'distribuye' => 'Pemex'],
            2  => ['modalidad' => 'Delivery G500',   'terminal' => 'Vopack',         'field' => 'delivery_vopak',   'tarifa' => null,   'distribuye' => 'Network'],
            3  => ['modalidad' => 'Delivery G500',   'terminal' => 'Tuxpan',         'field' => 'delivery_tuxpan',  'tarifa' => null,   'distribuye' => 'Network'],
            4  => ['modalidad' => 'Delivery G500',   'terminal' => 'Monterra',       'field' => 'delivery_montera', 'tarifa' => null,   'distribuye' => 'Network'],
            5  => ['modalidad' => 'Pick up',         'terminal' => 'Vopack',         'field' => 'pickup_vopak',     'tarifa' => 'Vopak', 'distribuye' => 'Vientos del norte'],
            6  => ['modalidad' => 'Pick up',         'terminal' => 'Tuxpan',         'field' => 'pickup_tuxpan',    'tarifa' => 'Tuxpan', 'distribuye' => 'Vientos del norte'],
            7  => ['modalidad' => 'Pick up',         'terminal' => 'Monterra',       'field' => 'pickup_montera',   'tarifa' => 'Tuxpan', 'distribuye' => 'Vientos del norte'],
            8  => ['modalidad' => 'Pick up/Simsa',   'terminal' => 'Monterra',       'field' => null,               'tarifa' => null,   'distribuye' => 'Vientos del norte'],
            9  => ['modalidad' => 'Pick up/Valero',  'terminal' => 'Tizayuca',       'field' => 'pickup_tizayuca',  'tarifa' => 'Tizayuca', 'distribuye' => 'Vientos del norte'],
            10 => ['modalidad' => 'Pick up/Valero',  'terminal' => 'Puebla',         'field' => 'pickup_puebla',    'tarifa' => 'Puebla', 'distribuye' => 'Vientos del norte'],
        ];

        $colorBg = [
            'Super'   => '#74bc1f',
            'Premium' => '#e01883',
            'Diesel'  => '#5c108c',
        ];

        $productos = [];
        foreach (self::PRODUCTOS as $producto) {
            $row = $detalle[$producto] ?? null;
            if (!$row) {
                continue;
            }

            $precios = [];
            foreach ($rowDefs as $num => $def) {
                if ($def['field'] === null) {
                    $precios[$num] = 0;
                    continue;
                }
                $valor = (float)$row->{$def['field']};
                if ($def['tarifa'] !== null) {
                    $valor = $valor + (float)$tarifas[$def['tarifa']];
                }
                $precios[$num] = $valor;
            }

            $minArray = [];
            foreach ($rowDefs as $num => $def) {
                if ($num === 8) {
                    continue;
                }
                if ($ocultarVopak && in_array($num, [2, 5])) {
                    continue;
                }
                $minArray[] = $precios[$num];
            }
            $minPrecio = $minArray ? min($minArray) : 0;

            $checks = [];
            for ($n = 1; $n <= 10; $n++) {
                $checks[$n] = (int)($row->{'p' . $n} ?? 0);
            }

            $rows = [];
            $noIdx = 1;
            foreach ($rowDefs as $num => $def) {
                $ocultarRow = $ocultarVopak && in_array($num, [2, 5]);
                $isMin = !$ocultarRow && $num !== 8 && ((float)$precios[$num] === (float)$minPrecio);

                $rowStyle = '';
                $trClass = '';
                $no = '';
                if (!$ocultarRow) {
                    if ($isMin) {
                        $rowStyle = 'background-color: ' . $colorBg[$producto] . '; color: white;';
                    } elseif ($checks[$num] === 1) {
                        $rowStyle = 'background-color: #fcfcda;';
                    } elseif ($num === 8) {
                        $trClass = '';
                    }
                    $no = (string)$noIdx;
                    $noIdx++;
                }

                $rows[] = [
                    'num'        => $num,
                    'no'         => $no,
                    'modalidad'  => $def['modalidad'],
                    'terminal'   => $def['terminal'],
                    'producto'   => $producto,
                    'precio'     => $num === 8 ? '0.00' : number_format($precios[$num], 2),
                    'diferencia' => in_array($num, [1, 8]) ? '0.00' : number_format($precios[$num] - $precios[1], 2),
                    'distribuye' => $def['distribuye'],
                    'check'      => $checks[$num],
                    'isMin'      => $isMin,
                    'rowStyle'   => $rowStyle,
                    'trClass'    => $trClass,
                    'ocultar'    => $ocultarRow,
                ];
            }

            $productos[$producto] = [
                'color' => $colorBg[$producto],
                'rows'  => $rows,
            ];
        }

        return [
            'ocultarVopak' => $ocultarVopak,
            'productos'    => $productos,
        ];
    }

    public static function isVopakHidden(string $fecha): bool
    {
        return $fecha > '2024-02-20';
    }

    public static function buildReporteHtml(int $id, bool $puedeToggle): string
    {
        $reporte = self::getReporte($id);
        if (empty($reporte)) {
            return '';
        }

        $ocultarTH = $puedeToggle ? '' : 'd-none';

        $html = '<div class="table-responsive">';
        $html .= '<table class="table table-bordered mb-3 text-nowrap align-middle">';
        $html .= '<thead>';
        $html .= '<tr>'
            . '<th class="align-middle text-center" colspan="9">REPORTE DE PRECIOS</th>'
            . '</tr>';
        $html .= '<tr>'
            . '<th class="align-middle text-center" width="48px">#</th>'
            . '<th class="align-middle text-center">Modalidad</th>'
            . '<th class="align-middle text-center">Terminal</th>'
            . '<th class="align-middle text-center">Producto</th>'
            . '<th class="align-middle text-center" width="100px">Precio</th>'
            . '<th class="align-middle text-center" width="100px">Diferencia vs Pemex</th>'
            . '<th class="align-middle text-center">Comercializa</th>'
            . '<th class="align-middle text-center">Distribuye</th>'
            . '<th class="align-middle text-center ' . $ocultarTH . '" width="15px"><i class="ti ti-tag fs-6 text-danger"></i></th>'
            . '</tr>';
        $html .= '</thead>';
        $html .= '<tbody class="bg-white">';

        foreach (self::PRODUCTOS as $producto) {
            if (empty($reporte['productos'][$producto])) {
                continue;
            }
            $bloque = $reporte['productos'][$producto];
            foreach ($bloque['rows'] as $r) {
                $celdaStyle = $r['rowStyle'] ? ' style="' . $r['rowStyle'] . '"' : '';
                $html .= '<tr class="text-center align-middle ' . ($r['ocultar'] ? 'd-none' : ' ') . ' ' . $r['trClass'] . '"'
                    . ($r['ocultar'] ? '' : ' style="' . $r['rowStyle'] . '"') . '>';
                $html .= '<td class="text-white" style="background-color: ' . $bloque['color'] . ';">' . htmlspecialchars((string)$r['no']) . '</td>';
                $html .= '<td' . $celdaStyle . '>' . htmlspecialchars((string)$r['modalidad']) . '</td>';
                $html .= '<td' . $celdaStyle . '>' . htmlspecialchars((string)$r['terminal']) . '</td>';
                $html .= '<td class="super text-center font-weight-bold"' . $celdaStyle . '>' . htmlspecialchars((string)$r['producto']) . '</td>';
                $html .= '<td class="text-center"' . $celdaStyle . '>$ ' . $r['precio'] . '</td>';
                $html .= '<td class="text-center"' . $celdaStyle . '>$ ' . $r['diferencia'] . '</td>';
                $html .= '<td' . $celdaStyle . '>Network</td>';
                $html .= '<td' . $celdaStyle . '>' . htmlspecialchars((string)$r['distribuye']) . '</td>';
                $html .= '<td class="align-middle text-center p-2 ' . $ocultarTH . '" width="15px">';
                if ($puedeToggle && !$r['isMin']) {
                    $html .= '<i class="ti ti-tag fs-6 text-danger pointer" onclick="SelPrecioBajo(' . $id . ',' . (int)$r['check'] . ',' . (int)$r['num'] . ',\'' . $r['producto'] . '\')"></i>';
                }
                $html .= '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    public static function buildTablasHtml(
        array $transporte,
        array $detalle,
        string $modo,
        bool $editable,
        bool $ocultarVopak
    ): array {
        $vopakClass = $ocultarVopak ? 'd-none' : '';
        $colspanTB = $ocultarVopak ? 9 : 13;

        return [
            'transporte' => self::buildTablaTransporte($transporte, $modo, $editable),
            'detalle'    => self::buildTablaDetalle($detalle, $modo, $editable, $vopakClass, $colspanTB),
        ];
    }

    private static function buildTablaTransporte(array $transporte, string $modo, bool $editable): string
    {
        $html = '<div class="table-responsive">';
        $html .= '<table class="table table-striped table-bordered mb-3 text-nowrap align-middle">';
        $html .= '<thead>';
        $html .= '<tr>'
            . '<th class="align-middle text-center" colspan="6">Precio del transporte</th>'
            . '</tr>';
        $html .= '<tr>'
            . '<th class="align-middle text-center">Terminal</th>'
            . '<th class="align-middle text-center">Pickup</th>'
            . '<th class="align-middle text-center">IVA 16%</th>'
            . '<th class="align-middle text-center">Retencion 4%</th>'
            . '<th class="align-middle text-center">Tarifa final transporte <br> Pickup</th>'
            . '</tr>';
        $html .= '</thead>';
        $html .= '<tbody class="bg-white">';

        foreach ($transporte as $t) {
            $html .= '<tr class="text-center align-middle">';
            $html .= '<td class="align-middle"><b>' . htmlspecialchars((string)$t['detalle']) . '</b></td>';

            if ($modo === 'editar') {
                if ($editable) {
                    $html .= '<td class="p-0"><input type="number" class="form-control rounded-0 border-0 p-3 text-center" value="' . htmlspecialchars((string)$t['precio']) . '" onkeyup="EditPrecio(this,' . (int)$t['id'] . ',14)"/></td>';
                } else {
                    $html .= '<td class="p-0"><span>$ ' . number_format((float)$t['precio'], 2) . '</span></td>';
                }
                $html .= '<td class="p-0"><input type="number" class="form-control rounded-0 border-0 p-3 text-center bg-transparent fw-semibold" id="inputIVA' . (int)$t['id'] . '" value="' . htmlspecialchars((string)$t['iva']) . '" disabled/></td>';
                $html .= '<td class="p-0"><input type="number" class="form-control rounded-0 border-0 p-3 text-center bg-transparent fw-semibold" id="inputRetencion' . (int)$t['id'] . '" value="' . htmlspecialchars((string)$t['retencion']) . '" disabled/></td>';
                $html .= '<td class="p-0"><input type="number" class="form-control rounded-0 border-0 p-3 text-center bg-transparent fw-semibold" id="input' . htmlspecialchars((string)$t['detalle']) . '" name="inputTotalPU' . (int)$t['id'] . '" value="' . htmlspecialchars((string)$t['tarifa']) . '" disabled/></td>';
            } else {
                $html .= '<td class="">$ ' . number_format((float)$t['precio'], 2) . '</td>';
                $html .= '<td class="">$ ' . number_format((float)$t['iva'], 4) . '</td>';
                $html .= '<td class="">$ ' . number_format((float)$t['retencion'], 4) . '</td>';
                $html .= '<td class="">$ ' . number_format((float)$t['tarifa'], 4) . '</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    private static function buildTablaDetalle(
        array $detalle,
        string $modo,
        bool $editable,
        string $vopakClass,
        int $colspanTB
    ): string {
        $html = '<div class="table-responsive"> <table class="table table-bordered mb-3 text-nowrap align-middle">';
        $html .= '<thead>';
        $html .= '<tr>'
            . '<th class="text-center align-middle"></th>'
            . '<th class="text-center align-middle" colspan="' . $colspanTB . '">Delivery</th>'
            . '<th class="text-center align-middle" colspan="13">Pick Up</th>'
            . '</tr>';
        $html .= '<tr>'
            . '<th class="text-center align-middle">Producto</th>'
            . '<th class="text-center align-middle text-white" style="background-color:#535252;">Pemex</th>'
            . '<th class="text-center align-middle" style="background-color:#d6dce4;">Delivery<br>G500 Network<br>Monterra</th>'
            . '<th class="text-center align-middle" style="background-color:#d6dce4;">Diferencia<br>vs<br>Pemex</th>'
            . '<th class="text-center align-middle ' . $vopakClass . '" style="background-color:#cfcfcf;">Delivery<br>G500 Network<br>Vopak</th>'
            . '<th class="text-center align-middle ' . $vopakClass . '" style="background-color:#cfcfcf;">Diferencia<br>vs<br>Pemex</th>'
            . '<th class="text-center align-middle" style="background-color:#e2efda;">Delivery<br>G500 Network<br>Tuxpan</th>'
            . '<th class="text-center align-middle" style="background-color:#e2efda;">Diferencia<br>vs<br>Pemex</th>'
            . '<th class="text-center align-middle ' . $vopakClass . '" style="background-color:#cfcfcf;">Pick up<br>G500 Network<br>Vopak</th>'
            . '<th class="text-center align-middle ' . $vopakClass . '" style="background-color:#cfcfcf;">Diferencia<br>vs<br>Pemex</th>'
            . '<th class="text-center align-middle" style="background-color:#e2efda;">Pick up<br>G500 Network<br>Tuxpan</th>'
            . '<th class="text-center align-middle" style="background-color:#e2efda;">Diferencia<br>vs<br>Pemex</th>'
            . '<th class="text-center align-middle" style="background-color:#d6dce4;">Pick up<br>G500 Network<br>Monterra</th>'
            . '<th class="text-center align-middle" style="background-color:#d6dce4;">Diferencia<br>vs<br>Pemex</th>'
            . '<th class="text-center align-middle" style="background-color:#94b8da;">Pick up<br>G500 Network<br>Tizayuca</th>'
            . '<th class="text-center align-middle" style="background-color:#94b8da;">Diferencia<br>vs<br>Pemex</th>'
            . '<th class="text-center align-middle text-white" style="background-color:#922d9a;">Pick up<br>G500 Network<br>Puebla</th>'
            . '<th class="text-center align-middle text-white" style="background-color:#922d9a;">Diferencia<br>vs<br>Pemex</th>'
            . '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach ($detalle as $d) {
            $color = self::COLOR_PRODUCTO[$d['producto'] ?? ''] ?? '';
            $html .= '<tr>';
            $html .= $modo === 'editar'
                ? self::filaDetalleEditar($d, $color, $vopakClass, $editable)
                : self::filaDetalleVer($d, $color, $vopakClass);
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    private static function filaDetalleEditar(array $d, string $color, string $vopakClass, bool $editable): string
    {
        $id = (int)($d['id'] ?? 0);

        $h = '<th class="align-middle text-white" style="' . $color . '">' . htmlspecialchars((string)$d['producto']) . '</th>';

        $h .= '<td>' . self::celdaEditable($id, 'PemexV', $d['pemex'], 1, $editable, true, false, 'align-middle') . '</td>';
        $h .= '<td>' . self::celdaEditable($id, 'MonterraVD', $d['delivery_montera'], 2, $editable) . '</td>';
        $h .= '<td class="table-light" ><input type="number" class="form-control rounded-0 border-0 p-3 text-center align-middle bg-transparent fw-semibold" id="MonterraD' . $id . '" value="' . number_format((float)$d['dif_delivery_montera'], 4) . '" disabled/></td>';
        $h .= '<td class=" ' . $vopakClass . '">' . self::celdaEditable($id, 'VopakVD', $d['delivery_vopak'], 4, $editable, false, false, 'align-middle') . '</td>';
        $h .= '<td class=" ' . $vopakClass . '"><input type="number" class="form-control rounded-0 border-0 p-3 text-center align-middle bg-transparent fw-semibold" id="VopakD' . $id . '" value="' . number_format((float)$d['dif_delivery_vopak'], 4) . '" disabled/></td>';
        $h .= '<td>' . self::celdaEditable($id, 'TuxpanVD', $d['delivery_tuxpan'], 3, $editable, false, true, 'bg-white align-middle', 'onchange') . '</td>';
        $h .= '<td class="table-light"><input type="number" class="form-control rounded-0 border-0 p-3 text-center align-middle bg-transparent fw-semibold" id="TuxpanD' . $id . '" value="' . number_format((float)$d['dif_delivery_tuxpan'], 4) . '" disabled/></td>';
        $h .= '<td class=" ' . $vopakClass . '">' . self::celdaEditable($id, 'VopakVP', $d['pickup_vopak'], 10, $editable, false, false, 'align-middle') . '</td>';
        $h .= '<td class=" ' . $vopakClass . '"><input type="number" class="form-control rounded-0 border-0 p-3 text-center align-middle bg-transparent fw-semibold" id="VopakP' . $id . '" data-tarifa="' . htmlspecialchars((string)$d['tarifa_vopak']) . '" value="' . number_format((float)$d['dif_pickup_vopak'], 4) . '" disabled/></td>';
        $h .= '<td >' . self::celdaEditable($id, 'TuxpanVP', $d['pickup_tuxpan'], 9, $editable, false, false, 'align-middle') . '</td>';
        $h .= '<td class="table-light"><input type="number" class="form-control rounded-0 border-0 p-3 text-center align-middle bg-transparent fw-semibold" id="TuxpanP' . $id . '" data-tarifa="' . htmlspecialchars((string)$d['tarifa_tuxpan']) . '" value="' . number_format((float)$d['dif_pickup_tuxpan'], 4) . '" disabled/></td>';
        $h .= '<td>' . self::celdaEditable($id, 'MonterraVP', $d['pickup_montera'], 8, $editable, false, true, 'bg-transparent') . '</td>';
        $h .= '<td class="table-light"><input type="number" class="form-control rounded-0 border-0 p-3 text-center align-middle bg-transparent fw-semibold" id="MonterraP' . $id . '" data-tarifa="' . htmlspecialchars((string)$d['tarifa_tuxpan']) . '" value="' . number_format((float)$d['dif_pickup_montera'], 4) . '" disabled/></td>';
        $h .= '<td>' . self::celdaEditable($id, 'TizayuVP', $d['pickup_tizayuca'], 12, $editable, false, false, 'align-middle') . '</td>';
        $h .= '<td class="table-light"><input type="number" class="form-control rounded-0 border-0 p-3 text-center align-middle bg-transparent fw-semibold" id="TizayuP' . $id . '" data-tarifa="' . htmlspecialchars((string)$d['tarifa_tizayuca']) . '" value="' . number_format((float)$d['dif_pickup_tizayuca'], 4) . '" disabled/></td>';
        $h .= '<td>' . self::celdaEditable($id, 'PueblaVP', $d['pickup_puebla'], 13, $editable, false, false, 'align-middle') . '</td>';
        $h .= '<td class="table-light"><input type="number" class="form-control rounded-0 border-0 p-3 text-center align-middle bg-transparent fw-semibold" id="PueblaP' . $id . '" data-tarifa="' . htmlspecialchars((string)$d['tarifa_puebla']) . '" value="' . number_format((float)$d['dif_pickup_puebla'], 4) . '" disabled/></td>';

        return $h;
    }

    private static function celdaEditable(
        int $id,
        string $prefix,
        $valor,
        int $num,
        bool $editable,
        bool $bold = false,
        bool $disabled = false,
        string $extraClass = '',
        string $evento = 'oninput'
    ): string {
        if (!$editable) {
            return '<span' . ($bold ? ' class="fw-semibold"' : '') . '>$ ' . number_format((float)$valor, 4) . '</span>';
        }

        $clase = 'form-control rounded-0 border-0 p-3 text-center' . ($extraClass ? ' ' . $extraClass : '');
        return '<input type="number" class="' . $clase . '" id="' . $prefix . $id . '" value="' . htmlspecialchars((string)$valor) . '" ' . $evento . '="EditPrecio(this,' . $id . ',' . $num . ')"' . ($disabled ? ' disabled fw-semibold' : '') . '/>';
    }

    private static function filaDetalleVer(array $d, string $color, string $vopakClass): string
    {
        $min = (float)($d['min_precio'] ?? 0);
        $estiloMin = function ($valor) use ($color, $min) {
            return ((float)$valor === $min) ? 'style="' . $color . '"' : '';
        };
        $m4 = function ($valor) {
            return number_format((float)$valor, 4);
        };

        $h = '<th class="p-3 text-white" style="' . $color . '">' . htmlspecialchars((string)$d['producto']) . '</th>';

        $h .= '<td class="text-center" ' . $estiloMin($d['pemex']) . '><b>$ ' . $m4($d['pemex']) . '</b></td>';

        $h .= '<td class="text-center" ' . $estiloMin($d['delivery_montera']) . '>$ ' . $m4($d['delivery_montera']) . '</td>';
        $h .= '<td class="text-center"><b>$ ' . $m4($d['dif_delivery_montera']) . '</b></td>';

        $h .= '<td class="text-center ' . $vopakClass . '" ' . $estiloMin($d['delivery_vopak']) . '>$ ' . $m4($d['delivery_vopak']) . '</td>';
        $h .= '<td class="text-center ' . $vopakClass . ' "><b>$ ' . $m4($d['dif_delivery_vopak']) . '</b></td>';

        $h .= '<td class="text-center" ' . $estiloMin($d['delivery_tuxpan']) . '>$ ' . $m4($d['delivery_tuxpan']) . '</td>';
        $h .= '<td class="text-center"><b>$ ' . $m4($d['dif_delivery_tuxpan']) . '</b></td>';

        $h .= '<td class="text-center ' . $vopakClass . '" ' . $estiloMin($d['pickup_vopak']) . '>$ ' . $m4($d['pickup_vopak']) . '</td>';
        $h .= '<td class="text-center ' . $vopakClass . ' "><b>$ ' . $m4($d['dif_pickup_vopak']) . '</b></td>';

        $h .= '<td class="text-center" ' . $estiloMin($d['pickup_tuxpan']) . '>$ ' . $m4($d['pickup_tuxpan']) . '</td>';
        $h .= '<td class="text-center"><b>$ ' . $m4($d['dif_pickup_tuxpan']) . '</b></td>';

        $h .= '<td class="text-center" ' . $estiloMin($d['pickup_montera']) . '>$ ' . $m4($d['pickup_montera']) . '</td>';
        $h .= '<td class="text-center"><b>$ ' . $m4($d['dif_pickup_montera']) . '</b></td>';

        $h .= '<td class="text-center" ' . $estiloMin($d['pickup_tizayuca']) . '>$ ' . $m4($d['pickup_tizayuca']) . '</td>';
        $h .= '<td class="text-center"><b>$ ' . $m4($d['dif_pickup_tizayuca']) . '</b></td>';

        $h .= '<td class="text-center" ' . $estiloMin($d['pickup_puebla']) . '>$ ' . $m4($d['pickup_puebla']) . '</td>';
        $h .= '<td class="text-center"><b>$ ' . $m4($d['dif_pickup_puebla']) . '</b></td>';

        return $h;
    }

    private static function notificarFinalizacion(string $fecha): void
    {
        try {
            $usuario = Auth::user();
            $nombre = $usuario ? $usuario->nombre : 'Usuario';
            $fechaFormat = formatearFecha($fecha);

$mensaje = '✅ Se ha finalizado el <b>Precio de Combustible</b> correspondiente al día <b>' . $fechaFormat . '</b> del apartado de Precios Diarios del Módulo de Importación:' . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombre;

            $telegram = new TelegramService();
            $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

            foreach (self::ESTACIONES_TELEGRAM as $idEstacion) {
                $userIds = $telegram->getUserIdsByStation($idEstacion, $idUsuario);
                foreach ($userIds as $uid) {
                    $telegram->sendTokenAsync($uid, $mensaje);
                }
            }
        } catch (\Throwable $e) {
            error_log('Error Telegram precios combustible: ' . $e->getMessage());
        }
    }
}
