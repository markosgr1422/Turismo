<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

// Verificar si el usuario tiene permiso para acceder a datos_usuarios.php
if (!isset($_SESSION['permisos']['datos_usuario']) || $_SESSION['permisos']['pagos'] !== true) {
    header('Location: index.php'); // Redireccionar si no tiene permisos
    exit();
}
require_once '../db/db_config.php'; // Ajusta la ruta según tu estructura de archivos

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$search_term = "";
$contratos = [];
$total_pagos = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['buscar_contrato_submit'])) {
    $search_term = $_POST['buscar_contrato'];

    $sql = "SELECT id, numero_referencia, nombre_contrato FROM contratos WHERE nombre_contrato LIKE ? OR numero_referencia LIKE ?";
    $stmt = $conn->prepare($sql);
    $search_term_with_wildcards = "%$search_term%";
    $stmt->bind_param("ss", $search_term_with_wildcards, $search_term_with_wildcards);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $contratos[] = $row;
        }
    }
    $stmt->close();
}

$pasajeros = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ver_pagos_por_contrato'])) {
    $contrato_id = $_POST['contrato_id'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];

    $sql_pasajeros = "SELECT p.id, p.nombre, p.apellido, p.dni, cg.credencial_pago
                      FROM pasajeros p 
                      JOIN cg_pasajeros cg ON p.id = cg.pasajero_id 
                      WHERE cg.contrato_id = ?";
    $stmt_pasajeros = $conn->prepare($sql_pasajeros);
    $stmt_pasajeros->bind_param("i", $contrato_id);
    $stmt_pasajeros->execute();
    $result_pasajeros = $stmt_pasajeros->get_result();

    if ($result_pasajeros->num_rows > 0) {
        while ($row_pasajero = $result_pasajeros->fetch_assoc()) {
            $pasajero = [
                'id' => $row_pasajero['id'],
                'nombre' => $row_pasajero['nombre'],
                'apellido' => $row_pasajero['apellido'],
                'dni' => $row_pasajero['dni'],
                'credencial_pago' => $row_pasajero['credencial_pago'],
                'pagos' => [],
                'total_pagado' => 0
            ];

            $sql_pagos = "SELECT monto, fecha 
                          FROM pagos 
                          WHERE pasajero_id = ? AND fecha BETWEEN ? AND ?";
            $stmt_pagos = $conn->prepare($sql_pagos);
            $stmt_pagos->bind_param("iss", $pasajero['id'], $fecha_inicio, $fecha_fin);
            $stmt_pagos->execute();
            $result_pagos = $stmt_pagos->get_result();

            if ($result_pagos->num_rows > 0) {
                while ($row_pago = $result_pagos->fetch_assoc()) {
                    $pasajero['pagos'][] = $row_pago;
                    $pasajero['total_pagado'] += $row_pago['monto'];
                    $total_pagos += $row_pago['monto'];
                }
            }
            $stmt_pagos->close();

            $pasajeros[] = $pasajero;
        }
    }
    $stmt_pasajeros->close();
}

$conn->close();

// Descarga en PDF o Excel
if (isset($_POST['export_pdf'])) {
    // Aquí deberías incluir el código para generar el PDF, por ejemplo con Dompdf
    // Cargar Dompdf y generar el PDF
    require_once('../vendor/autoload.php'); // Ajusta la ruta según tu configuración

    $dompdf = new Dompdf();

    // Contenido HTML para el PDF
    ob_start();
    include('pagos_por_viaje_pdf_template.php'); // Ajusta la ruta según tu estructura
    $html = ob_get_clean();

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream("pagos_por_viaje.pdf", array("Attachment" => false));
    exit();
}

if (isset($_POST['export_excel'])) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="pagos_por_viaje.xls"');

    $output = fopen("php://output", "w");
    fputcsv($output, array('DNI', 'Nombre', 'Apellido', 'Fecha', 'Monto'));

    foreach ($pasajeros as $pasajero) {
        foreach ($pasajero['pagos'] as $pago) {
            fputcsv($output, array($pasajero['dni'], $pasajero['nombre'], $pasajero['apellido'], $pago['fecha'], $pago['monto']));
        }
    }

    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos por Viaje</title>
    <link rel="stylesheet" href="../css/ver_pagos_pasajeros.css">
    <style>
        .total-pagado {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            background-color: #f0f0f0;
            border-radius: 5px;
        }
        .total-pagado .label {
            font-weight: bold;
        }
        .total-pagado .amount {
            font-weight: bold;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <span>Usuario: <?php echo isset($_SESSION['usuario']['nombre_usuario']) ? $_SESSION['usuario']['nombre_usuario'] : ''; ?></span>
        <a href="logout.php">Cerrar Sesión</a>
    </div>
    <div class="container">
        <a href="pagos.php" class="back">Volver</a>
        <h2>Pagos por Viaje</h2>
        <!-- Formulario para buscar contrato -->
        <form method="POST" action="pagos_por_viaje.php">
            <label for="buscar_contrato">Buscar por Contrato (Nombre o Número de Referencia):</label>
            <input type="text" id="buscar_contrato" name="buscar_contrato" value="<?php echo htmlspecialchars($search_term); ?>" required>
            <button type="submit" name="buscar_contrato_submit">Buscar</button>
        </form>
        <!-- Resultados de búsqueda de contratos -->
        <div class="resultados">
            <?php if (!empty($contratos)): ?>
                <h3>Resultados de Búsqueda:</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Número de Referencia</th>
                            <th>Nombre del Contrato</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contratos as $contrato): ?>
                            <tr>
                                <td><?php echo $contrato['numero_referencia']; ?></td>
                                <td><?php echo $contrato['nombre_contrato']; ?></td>
                                <td>
                                    <form method="POST" action="pagos_por_viaje.php">
                                        <input type="hidden" name="contrato_id" value="<?php echo $contrato['id']; ?>">
                                        <label for="fecha_inicio">Fecha Inicio:</label>
                                        <input type="date" name="fecha_inicio" required>
                                        <label for="fecha_fin">Fecha Fin:</label>
                                        <input type="date" name="fecha_fin" required>
                                        <button type="submit" name="ver_pagos_por_contrato">Ver Pagos</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <!-- Historial de pagos del contrato -->
            <?php if (!empty($pasajeros)): ?>
                <h3>Historial de Pagos del Contrato:</h3>
                <table>
                    <thead>
                        <tr>
                            <th>DNI</th>
                            <th>Nombre</th>
                            <th>Apellido</th>
                            <th>Fecha</th>
                            <th>Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pasajeros as $pasajero): ?>
                            <?php foreach ($pasajero['pagos'] as $pago): ?>
                                <tr>
                                    <td><?php echo $pasajero['dni']; ?></td>
                                    <td><?php echo $pasajero['nombre']; ?></td>
                                    <td><?php echo $pasajero['apellido']; ?></td>
                                    <td><?php echo $pago['fecha']; ?></td>
                                    <td><?php echo '$' . number_format($pago['monto'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="5">
                                    <div class="total-pagado">
                                        <span class="label">Total Pagado por <?php echo $pasajero['nombre'] . ' ' . $pasajero['apellido']; ?>:</span>
                                        <span class="amount"><?php echo '$' . number_format($pasajero['total_pagado'], 2, ',', '.'); ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <h4>Total de Todos los Pagos: <?php echo '$' . number_format($total_pagos, 2, ',', '.'); ?></h4>

                <!-- Opciones de exportación -->
                <form method="POST" action="pagos_por_viaje.php">
                    <button type="submit" name="export_pdf">Exportar a PDF</button>
                    <button type="submit" name="export_excel">Exportar a Excel</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
