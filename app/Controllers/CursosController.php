<?php
namespace App\Controllers;
use App\Models\Sasisopa\CursoCalendario;
use FPDF;


class CursosController extends BaseController {

    public function descargar(int $id){

    $pdf = new FPDF();

    // ======================
    // OBTENER DATOS
    // ======================
    $cal = CursoCalendario::with([
        'tema.modulo',
        'usuario'
    ])->findOrFail($id);

    $titulo = $cal->tema->titulo;
    $modulo = $cal->tema->modulo->titulo ?? '';
    $fecha = $cal->fecha_programada;
    $nombre = $cal->usuario->nombre;

    $observacion = $cal->observaciones 
        ? ' (' . $cal->observaciones . ')' 
        : '';

    // ======================
    // PDF
    // ======================
    $pdf = new FPDF('L','mm','A4');
    $pdf->AddPage();

    // Fondo
    $pdf->Image(
        asset('images/cursos/fondo-2024.jpg'),
        0, 0, 300, 210
    );

    // Helper encoding
    $txt = fn($t) => mb_convert_encoding($t, 'ISO-8859-1', 'UTF-8');

    // ======================
    // NOMBRE
    // ======================
    $pdf->SetFont('Arial','',30);
    $pdf->SetY(100);
    $pdf->Cell(0,10, $txt($nombre),0,0,'C');

    // ======================
    // TEMA
    // ======================
    $pdf->Ln(33);

    $pdf->SetFont('Arial','',17);
    $pdf->SetX(70);
    $pdf->SetMargins(68, 0);

    $pdf->MultiCell(
        0,
        6,
        $txt($titulo . $observacion),
        0,
        'C'
    );

    // ======================
    // FECHA
    // ======================
    $pdf->Ln(20);

    $pdf->SetFont('Arial','',12);
    $pdf->SetX(77);

    $pdf->Cell(
        0,
        10,
        $txt(formatearFecha($fecha)),
        0,
        0
    );

    // ======================
    // OUTPUT
    // ======================
    return $pdf->Output('I', 'reconocimiento.pdf');

    }

public function descargarAll(int $year, int $idModulo)
{
    $idEstacion = $this->estacionId();

    // ======================
    // HELPER TEXTO
    // ======================
    $safeText = function ($text) {
        if (!$text) return '';

        $text = trim($text);
        $text = strip_tags($text);
        $text = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text);

        return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    };

    // ======================
    // CONSULTA (IGUAL QUE TU SQL)
    // ======================
    $calendarios = CursoCalendario::with([
        'tema.modulo',
        'usuario'
    ])
    ->whereYear('fecha_programada', $year)
    ->where('id_estacion', $idEstacion)
    ->where('resultado', '>=', 60)
    ->whereHas('tema', function ($q) use ($idModulo) {
        $q->where('id_modulo', $idModulo);
    })
    ->orderBy('fecha_programada', 'desc')
    ->get();

    // ======================
    // PDF
    // ======================
    $pdf = new FPDF('L', 'mm', 'A4');

    foreach ($calendarios as $cal) {

        if (!$cal->usuario || !$cal->tema) continue;

        $nombre = $safeText($cal->usuario->nombre);
        $titulo = $safeText($cal->tema->titulo);

        // ⚠️ IMPORTANTE: primero formateas, luego conviertes
        $fechaFormateada = formatearFecha($cal->fecha_programada);
        $fecha = $safeText($fechaFormateada);

        $observacion = $cal->observaciones
            ? ' (' . $safeText($cal->observaciones) . ')'
            : '';

        // ======================
        // HOJA
        // ======================
        $pdf->AddPage();

        $pdf->SetTitle(substr($titulo, 0, 100));

        $pdf->Image(
            asset('images/cursos/fondo-2024.jpg'),
            0, 0, 300, 210
        );

        // ======================
        // NOMBRE
        // ======================
        $pdf->SetFont('Arial', '', 30);
        $pdf->SetY(100);
        $pdf->Cell(0, 10, $nombre, 0, 0, 'C');

        // ======================
        // TEMA
        // ======================
        $pdf->Ln(33);

        $pdf->SetFont('Arial', '', 17);
        $pdf->SetX(70);
        $pdf->SetMargins(68, 0);

        $pdf->MultiCell(
            0,
            6,
            $titulo . $observacion,
            0,
            'C'
        );

        // ======================
        // FECHA
        // ======================
        $pdf->Ln(20);

        $pdf->SetFont('Arial', '', 12);
        $pdf->SetX(77);

        $pdf->Cell(
            0,
            10,
            $fecha,
            0,
            0
        );
    }

    return $pdf->Output(
        'I',
        "reconocimientos_modulo_{$idModulo}_{$year}.pdf"
    );
}

}