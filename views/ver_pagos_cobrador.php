<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: ../index.php');
    exit();
}

if (!isset($_SESSION['permisos']['pagos']) || $_SESSION['permisos']['pagos'] !== true) {
    header('Location: ../index.php');
    exit();
}

require_once '../db/db_config.php';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$cobradores = [];
$pagos = [];
$mensaje = "";
$total_efectivo = 0;
$total_tarjeta = 0;
$total_transferencia = 0;

// Obtener usuario_id de la sesión
$usuario_id = isset($_SESSION['usuario']['id']) ? $_SESSION['usuario']['id'] : null;

// Obtener cobradores para el formulario de filtro
$sql_cobradores = "SELECT id, nombre_usuario FROM usuarios WHERE permisos LIKE '%pagos%'";
$result_cobradores = $conn->query($sql_cobradores);
if (!$result_cobradores) {
    die("Error en la consulta SQL: " . $conn->error);
}
if ($result_cobradores->num_rows > 0) {
    while ($row = $result_cobradores->fetch_assoc()) {
        $cobradores[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_cobrador = $_POST['cobrador'] ?? null;
    $fecha_inicio = $_POST['fecha_inicio'] ?? null;
    $fecha_fin = $_POST['fecha_fin'] ?? null;

    // Validar fechas
    if ($fecha_inicio && $fecha_fin) {
        $sql = "SELECT p.fecha, p.monto, p.metodo_pago, pas.nombre, pas.apellido, pas.dni, c.nombre_contrato, u.nombre_usuario
                FROM pagos p
                JOIN pasajeros pas ON p.pasajero_id = pas.id
                JOIN contratos c ON p.contrato_id = c.id
                JOIN usuarios u ON p.id_cobrador = u.id
                WHERE p.id_cobrador = ? AND p.fecha BETWEEN ? AND ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $id_cobrador, $fecha_inicio, $fecha_fin);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $pagos[] = $row;

                // Sumar montos por método de pago
                if ($row['metodo_pago'] == 'efectivo') {
                    $total_efectivo += $row['monto'];
                } elseif ($row['metodo_pago'] == 'tarjeta') {
                    $total_tarjeta += $row['monto'];
                } elseif ($row['metodo_pago'] == 'transferencia') {
                    $total_transferencia += $row['monto'];
                }
            }
        } else {
            $mensaje = "No se encontraron pagos para este cobrador en el rango de fechas proporcionado.";
        }
        $stmt->close();
    } else {
        $mensaje = "Por favor, ingrese un rango de fechas válido.";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Pagos por Cobrador</title>
    <link rel="stylesheet" href="../css/ver_pagos_pasajeros.css">
</head>
<body>
    <div class="header">
        <span>Usuario: <?php echo isset($_SESSION['usuario']['nombre_usuario']) ? $_SESSION['usuario']['nombre_usuario'] : ''; ?></span>
        <a href="logout.php">Cerrar Sesión</a>
    </div>
    <div class="container">
        <a href="pagos.php" class="back">Volver</a>
        <h2>Reporte de Pagos por Cobrador</h2>
        
        <!-- Formulario de filtro -->
        <form method="POST">
            <label for="cobrador">Seleccionar Cobrador:</label>
            <select name="cobrador" required>
                <option value="">Seleccione un cobrador</option>
                <?php foreach ($cobradores as $cobrador): ?>
                    <option value="<?php echo $cobrador['id']; ?>"><?php echo $cobrador['nombre_usuario']; ?></option>
                <?php endforeach; ?>
            </select>

            <label for="fecha_inicio">Fecha de Inicio:</label>
            <input type="date" name="fecha_inicio" required>

            <label for="fecha_fin">Fecha de Fin:</label>
            <input type="date" name="fecha_fin" required>

            <button type="submit">Generar Reporte</button>
        </form>

        <!-- Mensaje -->
        <div class="mensaje">
            <?php if ($mensaje) { echo "<p>$mensaje</p>"; } ?>
        </div>

        <!-- Tabla de resultados -->
        <?php if (!empty($pagos)): ?>
            <h3>Pagos Realizados</h3>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Nombre del Pasajero</th>
                        <th>DNI</th>
                        <th>Contrato</th>
                        <th>Monto</th>
                        <th>Método de Pago</th>
                        <th>Cobrador</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagos as $pago): ?>
                        <tr>
                            <td><?php echo $pago['fecha']; ?></td>
                            <td><?php echo $pago['nombre'] . ' ' . $pago['apellido']; ?></td>
                            <td><?php echo $pago['dni']; ?></td>
                            <td><?php echo $pago['nombre_contrato']; ?></td>
                            <td><?php echo '$' . number_format($pago['monto'], 2); ?></td>
                            <td><?php echo ucfirst($pago['metodo_pago']); ?></td>
                            <td><?php echo $pago['nombre_usuario']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Sumatoria de pagos -->
            <h3>Sumatoria por Método de Pago</h3>
            <p><strong>Efectivo:</strong> $<?php echo number_format($total_efectivo, 2); ?></p>
            <p><strong>Tarjeta:</strong> $<?php echo number_format($total_tarjeta, 2); ?></p>
            <p><strong>Transferencia:</strong> $<?php echo number_format($total_transferencia, 2); ?></p>
            <p><strong>Total:</strong> $<?php echo number_format($total_efectivo + $total_tarjeta + $total_transferencia, 2); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
