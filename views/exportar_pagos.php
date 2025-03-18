<?php
require_once '../vendor/autoload.php'; // Incluye la librería TCPDF para generar PDFs (o la que uses)
require_once '../db/db_config.php'; // Ajusta la ruta según tu estructura de archivos

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

// Obtener datos del GET
$usuario_id = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : null;
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : null;
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : null;
$formato = isset($_GET['formato']) ? $_GET['formato'] : 'pdf';

// Obtener los pagos según los parámetros
$pagos = [];
$usuario_nombre = '';

if ($usuario_id && $fecha_desde && $fecha_hasta) {
    $sql = "SELECT p.id AS pago_id, p.monto, p.fecha, p.metodo_pago, p.numero_transaccion, pa.nombre, pa.apellido, c.nombre_contrato
            FROM pagos p
            JOIN pasajeros pa ON p.pasajero_id = pa.id
            JOIN contratos c ON pa.contrato_id = c.id
            WHERE p.usuario_id = ? AND p.fecha >= ? AND p.fecha <= ?
            ORDER BY p.fecha ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $usuario_id, $fecha_desde, $fecha_hasta);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $pagos[] = $row;
        }
    }

    // Obtener nombre del usuario (cobrador)
    $sql_usuario = "SELECT nombre FROM usuarios WHERE id = ?";
    $stmt_usuario = $conn->prepare($sql_usuario);
    $stmt_usuario->bind_param("i", $usuario_id);
    $stmt_usuario->execute();
    $result_usuario = $stmt_usuario->get_result();

    if ($result_usuario->num_rows > 0) {
        $usuario_nombre = $result_usuario->fetch_assoc()['nombre'];
    }

    $stmt->close();
    $stmt_usuario->close();
}

$conn->close();

// Exportar a PDF o Excel según el formato seleccionado
if ($formato == 'pdf') {
    // Generar PDF (implementación con TCPDF o MPDF)
    // Ejemplo con TCPDF:
    $pdf = new TCPDF();
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Cell(0, 10, 'Reporte de Pagos', 0, 1, 'C');
    $pdf->Cell(0, 10, 'Cobrador: ' . $usuario_nombre, 0, 1);
    $pdf->Cell(30, 10, 'Fecha', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Pasajero', 1, 0, 'C');
    $pdf->Cell(50, 10, 'Contrato', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Monto', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Método de Pago', 1, 1, 'C');

    foreach ($pagos as $pago) {
        $pdf->Cell(30, 10, $pago['fecha'], 1, 0, 'C');
        $pdf->Cell(40, 10, $pago['nombre'] . ' ' . $pago['apellido'], 1, 0, 'C');
        $pdf->Cell(50, 10, $pago['nombre_contrato'], 1, 0, 'C');
        $pdf->Cell(30, 10, '$' . number_format($pago['monto'], 2, ',', '.'), 1, 0, 'C');
        $pdf->Cell(40, 10, ucfirst($pago['metodo_pago']), 1, 1, 'C');
    }

    $pdf->Output('reporte_pagos.pdf', 'D');
    exit;
} elseif ($formato == 'excel') {
    // Generar Excel (implementación con PhpSpreadsheet)
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'Reporte de Pagos');
    $sheet->setCellValue('A2', 'Cobrador: ' . $usuario_nombre);
    $sheet->setCellValue('A3', 'Fecha');
    $sheet->setCellValue('B3', 'Pasajero');
    $sheet->setCellValue('C3', 'Contrato');
    $sheet->setCellValue('D3', 'Monto');
    $sheet->setCellValue('E3', 'Método de Pago');

    $row = 4;
    foreach ($pagos as $pago) {
        $sheet->setCellValue('A' . $row, $pago['fecha']);
        $sheet->setCellValue('B' . $row, $pago['nombre'] . ' ' . $pago['apellido']);
        $sheet->setCellValue('C' . $row, $pago['nombre_contrato']);
        $sheet->setCellValue('D' . $row, $pago['monto']);
        $sheet->setCellValue('E' . $row, ucfirst($pago['metodo_pago']));
        $row++;
    }

    $writer = new Xlsx($spreadsheet);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="reporte_pagos.xlsx"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');
    exit;
}
?>
