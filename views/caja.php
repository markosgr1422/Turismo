<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario'])) {
    header('Location: ../index.php');
    exit();
}

// Verificar si el usuario tiene permiso para acceder a caja.php
if (!isset($_SESSION['permisos']['caja']) || $_SESSION['permisos']['caja'] !== true) {
    header('Location: inicio.php'); // Redireccionar si no tiene permisos
    exit();
}

// Incluir la configuración de la base de datos
require '../db/db_config.php';

// Conexión a la base de datos
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Mensajes de éxito y error
$message = '';

// Lógica para agregar un gasto o ingreso extra
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_movimiento'])) {
    $tipo = $_POST['tipo'];
    $descripcion = $_POST['descripcion'];
    $monto = $_POST['monto'];
    $fecha = $_POST['fecha'];
    $comprobante = $_POST['comprobante'];

    // Insertar el movimiento en la tabla caja
    $sql = "INSERT INTO caja (tipo, descripcion, monto, fecha, comprobante) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdss", $tipo, $descripcion, $monto, $fecha, $comprobante);

    if ($stmt->execute()) {
        $message = "Movimiento agregado correctamente.";
    } else {
        $message = "Error al agregar el movimiento: " . $stmt->error;
    }

    $stmt->close();
}

// Lógica para filtrar movimientos por fecha
$filtro_fecha = isset($_GET['filtro_fecha']) ? $_GET['filtro_fecha'] : '';
$movimientos = [];

// Consulta para obtener los movimientos de caja
$sql = "SELECT * FROM caja WHERE 1=1";
if (!empty($filtro_fecha)) {
    $sql .= " AND fecha = ?";
}

$sql .= " ORDER BY fecha DESC";
$stmt = $conn->prepare($sql);

if (!empty($filtro_fecha)) {
    $stmt->bind_param("s", $filtro_fecha);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $movimientos[] = $row;
    }
}

$stmt->close();

// Consulta para obtener los pagos (efectivo, tarjeta, transferencia)
$pagos = [];
$sql_pagos = "SELECT p.metodo_pago, p.monto, p.fecha, p.numero_transaccion, p.numero_transferencia, pa.nombre, pa.apellido 
              FROM pagos p 
              JOIN pasajeros pa ON p.pasajero_id = pa.id 
              WHERE 1=1";

if (!empty($filtro_fecha)) {
    $sql_pagos .= " AND p.fecha = ?";
}

$sql_pagos .= " ORDER BY p.fecha DESC";
$stmt_pagos = $conn->prepare($sql_pagos);

if (!empty($filtro_fecha)) {
    $stmt_pagos->bind_param("s", $filtro_fecha);
}

$stmt_pagos->execute();
$result_pagos = $stmt_pagos->get_result();

if ($result_pagos->num_rows > 0) {
    while ($row = $result_pagos->fetch_assoc()) {
        $pagos[] = $row;
    }
}

$stmt_pagos->close();

// Cerrar la conexión a la base de datos
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja</title>
    <link rel="stylesheet" href="../css/caja.css">
</head>
<body>
    <div class="header">
        <span>Usuario: <?php echo isset($_SESSION['usuario']['nombre_usuario']) ? $_SESSION['usuario']['nombre_usuario'] : ''; ?></span>
        <a href="inicio.php">Inicio</a>
        <a href="logout.php">Cerrar Sesión</a>
    </div>

    <div class="container">
        <h2>Reporte de Caja</h2>

        <!-- Mensajes de éxito y error -->
        <?php if (!empty($message)): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>

        <!-- Formulario para filtrar por fecha -->
        <form method="GET" action="caja.php">
            <label for="filtro_fecha">Filtrar por fecha:</label>
            <input type="date" id="filtro_fecha" name="filtro_fecha" value="<?php echo htmlspecialchars($filtro_fecha); ?>">
            <button type="submit">Filtrar</button>
        </form>

        <!-- Tabla de movimientos de caja -->
        <h3>Movimientos de Caja</h3>
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Descripción</th>
                    <th>Monto</th>
                    <th>Fecha</th>
                    <th>Comprobante</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movimientos as $movimiento): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($movimiento['tipo']); ?></td>
                        <td><?php echo htmlspecialchars($movimiento['descripcion']); ?></td>
                        <td><?php echo number_format($movimiento['monto'], 2); ?></td>
                        <td><?php echo htmlspecialchars($movimiento['fecha']); ?></td>
                        <td><?php echo htmlspecialchars($movimiento['comprobante']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Tabla de pagos -->
        <h3>Pagos Registrados</h3>
        <table>
            <thead>
                <tr>
                    <th>Método de Pago</th>
                    <th>Monto</th>
                    <th>Fecha</th>
                    <th>Número de Transacción/Transferencia</th>
                    <th>Pasajero</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pagos as $pago): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($pago['metodo_pago']); ?></td>
                        <td><?php echo number_format($pago['monto'], 2); ?></td>
                        <td><?php echo htmlspecialchars($pago['fecha']); ?></td>
                        <td>
                            <?php
                            if ($pago['metodo_pago'] === 'tarjeta') {
                                echo htmlspecialchars($pago['numero_transaccion']);
                            } elseif ($pago['metodo_pago'] === 'transferencia') {
                                echo htmlspecialchars($pago['numero_transferencia']);
                            } else {
                                echo "N/A";
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($pago['nombre'] . ' ' . $pago['apellido']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Formulario para agregar gastos o ingresos extras -->
        <h3>Agregar Movimiento Extra</h3>
        <form method="POST" action="caja.php">
            <label for="tipo">Tipo:</label>
            <select id="tipo" name="tipo" required>
                <option value="Ingreso">Ingreso</option>
                <option value="Gasto">Gasto</option>
            </select>

            <label for="descripcion">Descripción:</label>
            <input type="text" id="descripcion" name="descripcion" required>

            <label for="monto">Monto:</label>
            <input type="number" id="monto" name="monto" step="0.01" required>

            <label for="fecha">Fecha:</label>
            <input type="date" id="fecha" name="fecha" required>

            <label for="comprobante">Comprobante:</label>
            <input type="text" id="comprobante" name="comprobante" required>

            <button type="submit" name="agregar_movimiento">Agregar Movimiento</button>
        </form>
    </div>
</body>
</html>