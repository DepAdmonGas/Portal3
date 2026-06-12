<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ClienteMesExcelService
{
    public static function generarYDescargar(int $idEstacion, int $idYear, int $idMes): void
    {
        $idReporte = ClienteMesService::getIdReporte($idEstacion, $idYear, $idMes);
        $nombreEstacion = ClienteMesService::getNombreEstacion($idEstacion);
        $datos = ClienteMesService::getDatos($idReporte);
        $credito = $datos['credito'];
        $debito = $datos['debito'];

        $mesFormateado = nombremes(sprintf('%02d', $idMes));
        $spreadsheet = new Spreadsheet();

        // Sheet 1: Crédito
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Credito');

        $sheet1->setCellValue('A1', '#');
        $sheet1->setCellValue('B1', 'CUENTA');
        $sheet1->setCellValue('C1', 'CLIENTE');
        $sheet1->setCellValue('D1', 'SALDO INICIO');
        $sheet1->setCellValue('E1', 'CONSUMOS');
        $sheet1->setCellValue('F1', 'PAGOS');
        $sheet1->setCellValue('G1', 'SALDO FINAL');

        $sheet1->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID);
        $sheet1->getStyle('A1:G1')->getFill()->getStartColor()->setRGB('749ABF');
        $sheet1->getStyle('A1:G1')->getFont()->getColor()->setRGB('FFFFFF');

        $rowIdx = 2;
        $TSIC = 0; $TCC = 0; $TPC = 0; $TSFC = 0;
        foreach ($credito as $r) {
            $saldofinal = (float) $r['saldo_inicial'] + (float) $r['consumos'] - (float) $r['pagos'];
            $TSIC += (float) $r['saldo_inicial'];
            $TCC += (float) $r['consumos'];
            $TPC += (float) $r['pagos'];
            $TSFC += (float) $r['saldo_final'];

            $sheet1->setCellValue("A$rowIdx", $r['id']);
            $sheet1->setCellValue("B$rowIdx", $r['cuenta']);
            $sheet1->setCellValue("C$rowIdx", $r['nombre']);
            $sheet1->setCellValue("D$rowIdx", $r['saldo_inicial']);
            $sheet1->setCellValue("E$rowIdx", $r['consumos']);
            $sheet1->setCellValue("F$rowIdx", $r['pagos']);
            $sheet1->setCellValue("G$rowIdx", $saldofinal);
            $rowIdx++;
        }

        if (empty($credito)) {
            $sheet1->setCellValue("A$rowIdx", 'No se encontro informacion.');
            $sheet1->mergeCells("A$rowIdx:G$rowIdx");
            $rowIdx++;
        }

        $rowTotal = $rowIdx + 1;
        $sheet1->setCellValue("A$rowTotal", 'TOTAL CREDITO');
        $sheet1->mergeCells("A$rowTotal:C$rowTotal");
        $sheet1->setCellValue("D$rowTotal", $TSIC);
        $sheet1->setCellValue("E$rowTotal", $TCC);
        $sheet1->setCellValue("F$rowTotal", $TPC);
        $sheet1->setCellValue("G$rowTotal", $TSFC);

        foreach (range('A', 'G') as $col) {
            $sheet1->getColumnDimension($col)->setAutoSize(true);
            $sheet1->getStyle($col)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet1->getStyle($col)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        // Sheet 2: Débito
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Debito');

        $sheet2->setCellValue('A1', '#');
        $sheet2->setCellValue('B1', 'CUENTA');
        $sheet2->setCellValue('C1', 'CLIENTE');
        $sheet2->setCellValue('D1', 'SALDO INICIO');
        $sheet2->setCellValue('E1', 'CONSUMOS');
        $sheet2->setCellValue('F1', 'PAGOS');
        $sheet2->setCellValue('G1', 'SALDO FINAL');

        $sheet2->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID);
        $sheet2->getStyle('A1:G1')->getFill()->getStartColor()->setRGB('749ABF');
        $sheet2->getStyle('A1:G1')->getFont()->getColor()->setRGB('FFFFFF');

        $rowIdx = 2;
        $TSID = 0; $TCD = 0; $TPD = 0; $TSFD = 0;
        foreach ($debito as $r) {
            $saldofinal = (float) $r['saldo_inicial'] + (float) $r['consumos'] - (float) $r['pagos'];
            $TSID += (float) $r['saldo_inicial'];
            $TCD += (float) $r['consumos'];
            $TPD += (float) $r['pagos'];
            $TSFD += (float) $r['saldo_final'];

            $sheet2->setCellValue("A$rowIdx", $r['id']);
            $sheet2->setCellValue("B$rowIdx", $r['cuenta']);
            $sheet2->setCellValue("C$rowIdx", $r['nombre']);
            $sheet2->setCellValue("D$rowIdx", $r['saldo_inicial']);
            $sheet2->setCellValue("E$rowIdx", $r['consumos']);
            $sheet2->setCellValue("F$rowIdx", $r['pagos']);
            $sheet2->setCellValue("G$rowIdx", $saldofinal);
            $rowIdx++;
        }

        if (empty($debito)) {
            $sheet2->setCellValue("A$rowIdx", 'No se encontro informacion.');
            $sheet2->mergeCells("A$rowIdx:G$rowIdx");
            $rowIdx++;
        }

        $rowTotal = $rowIdx + 1;
        $sheet2->setCellValue("A$rowTotal", 'TOTAL DEBITO');
        $sheet2->mergeCells("A$rowTotal:C$rowTotal");
        $sheet2->setCellValue("D$rowTotal", $TSID);
        $sheet2->setCellValue("E$rowTotal", $TCD);
        $sheet2->setCellValue("F$rowTotal", $TPD);
        $sheet2->setCellValue("G$rowTotal", $TSFD);

        foreach (range('A', 'G') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
            $sheet2->getStyle($col)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle($col)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        // Sheet 3: Gran total
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Gran total');

        $sheet3->setCellValue('A1', '');
        $sheet3->setCellValue('B1', 'SALDO INICIO');
        $sheet3->setCellValue('C1', 'CONSUMOS');
        $sheet3->setCellValue('D1', 'PAGOS');
        $sheet3->setCellValue('E1', 'SALDO FINAL');

        $sheet3->getStyle('A1:E1')->getFill()->setFillType(Fill::FILL_SOLID);
        $sheet3->getStyle('A1:E1')->getFill()->getStartColor()->setRGB('749ABF');
        $sheet3->getStyle('A1:E1')->getFont()->getColor()->setRGB('FFFFFF');

        $sheet3->setCellValue('A2', 'CREDITO');
        $sheet3->setCellValue('B2', $TSIC);
        $sheet3->setCellValue('C2', $TCC);
        $sheet3->setCellValue('D2', $TPC);
        $sheet3->setCellValue('E2', $TSFC);

        $sheet3->setCellValue('A3', 'DEBITO');
        $sheet3->setCellValue('B3', $TSID);
        $sheet3->setCellValue('C3', $TCD);
        $sheet3->setCellValue('D3', $TPD);
        $sheet3->setCellValue('E3', $TSFD);

        $sheet3->setCellValue('A4', 'TOTAL');
        $sheet3->setCellValue('B4', $TSIC + $TSID);
        $sheet3->setCellValue('C4', $TCC + $TCD);
        $sheet3->setCellValue('D4', $TPC + $TPD);
        $sheet3->setCellValue('E4', $TSFC + $TSFD);

        foreach (range('A', 'E') as $col) {
            $sheet3->getColumnDimension($col)->setAutoSize(true);
            $sheet3->getStyle($col)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet3->getStyle($col)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="Resumen_Clientes_' . $nombreEstacion . '_' . $mesFormateado . '_' . $idYear . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        exit;
    }
}
