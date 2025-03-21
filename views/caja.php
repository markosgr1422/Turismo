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
    $usuario_id = $_SESSION['usuario']['id']; // Obtener el ID del usuario actual

    // Insertar el movimiento en la tabla caja
    $sql = "INSERT INTO caja (tipo, descripcion, monto, fecha, comprobante, usuario_id) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdssi", $tipo, $descripcion, $monto, $fecha, $comprobante, $usuario_id);

    if ($stmt->execute()) {
        $message = "Movimiento agregado correctamente.";
    } else {
        $message = "Error al agregar el movimiento: " . $stmt->error;
    }

    $stmt->close();
}

// Lógica para filtrar movimientos por fecha y usuario
$filtro_fecha = isset($_GET['filtro_fecha']) ? $_GET['filtro_fecha'] : '';
$filtro_usuario = isset($_GET['filtro_usuario']) ? $_GET['filtro_usuario'] : '';
$movimientos = [];
$pagos = [];
$total_efectivo = 0;
$total_tarjeta = 0;
$total_transferencia = 0;

if (!empty($filtro_fecha)) {
    // Consulta para obtener los movimientos de caja
    $sql = "SELECT c.*, u.nombre_usuario 
            FROM caja c 
            JOIN usuarios u ON c.usuario_id = u.id 
            WHERE c.fecha = ?";
    if (!empty($filtro_usuario)) {
        $sql .= " AND c.usuario_id = ?";
    }
    $sql .= " ORDER BY c.fecha DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($filtro_usuario)) {
        $stmt->bind_param("si", $filtro_fecha, $filtro_usuario);
    } else {
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
    $sql_pagos = "SELECT p.metodo_pago, p.monto, p.fecha, p.numero_transaccion, p.numero_transferencia, pa.nombre, pa.apellido, u.nombre_usuario, c.nombre_contrato 
                  FROM pagos p 
                  JOIN pasajeros pa ON p.pasajero_id = pa.id 
                  JOIN usuarios u ON p.id_cobrador = u.id 
                  JOIN contratos c ON p.contrato_id = c.id 
                  WHERE p.fecha = ?";
    if (!empty($filtro_usuario)) {
        $sql_pagos .= " AND p.id_cobrador = ?";
    }
    $sql_pagos .= " ORDER BY p.fecha DESC";

    $stmt_pagos = $conn->prepare($sql_pagos);
    if (!empty($filtro_usuario)) {
        $stmt_pagos->bind_param("si", $filtro_fecha, $filtro_usuario);
    } else {
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

    // Consulta para obtener las sumatorias de pagos
    $sql_sumatorias = "SELECT 
                            SUM(CASE WHEN metodo_pago = 'efectivo' THEN monto ELSE 0 END) AS total_efectivo,
                            SUM(CASE WHEN metodo_pago = 'tarjeta' THEN monto ELSE 0 END) AS total_tarjeta,
                            SUM(CASE WHEN metodo_pago = 'transferencia' THEN monto ELSE 0 END) AS total_transferencia
                        FROM pagos 
                        WHERE fecha = ?";
    if (!empty($filtro_usuario)) {
        $sql_sumatorias .= " AND id_cobrador = ?";
    }

    $stmt_sumatorias = $conn->prepare($sql_sumatorias);
    if (!empty($filtro_usuario)) {
        $stmt_sumatorias->bind_param("si", $filtro_fecha, $filtro_usuario);
    } else {
        $stmt_sumatorias->bind_param("s", $filtro_fecha);
    }
    $stmt_sumatorias->execute();
    $result_sumatorias = $stmt_sumatorias->get_result();

    if ($result_sumatorias->num_rows > 0) {
        $row_sumatorias = $result_sumatorias->fetch_assoc();
        $total_efectivo = $row_sumatorias['total_efectivo'];
        $total_tarjeta = $row_sumatorias['total_tarjeta'];
        $total_transferencia = $row_sumatorias['total_transferencia'];
    }

    $stmt_sumatorias->close();
}

// Consulta para obtener la lista de usuarios
$sql_usuarios = "SELECT id, nombre_usuario FROM usuarios";
$result_usuarios = $conn->query($sql_usuarios);
$usuarios = [];
if ($result_usuarios->num_rows > 0) {
    while ($row = $result_usuarios->fetch_assoc()) {
        $usuarios[] = $row;
    }
}

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
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            .reporte, .reporte * {
                visibility: visible;
            }
            .reporte {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none;
            }
        }
    </style>
    <script>
        function imprimirReporte() {
            window.print();
        }
    </script>
</head>
<body>
    <div class="header no-print">
        <span>Usuario: <?php echo isset($_SESSION['usuario']['nombre_usuario']) ? $_SESSION['usuario']['nombre_usuario'] : ''; ?></span>
        <a href="inicio.php">Inicio</a>
        <a href="logout.php">Cerrar Sesión</a>
    </div>

    <div class="container">
        <h2 class="no-print">Reporte de Caja</h2>

        <!-- Mensajes de éxito y error -->
        <?php if (!empty($message)): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>

        <!-- Formulario para filtrar por fecha y usuario -->
        <form method="GET" action="caja.php" class="no-print">
            <label for="filtro_fecha">Filtrar por fecha:</label>
            <input type="date" id="filtro_fecha" name="filtro_fecha" value="<?php echo htmlspecialchars($filtro_fecha); ?>">

            <label for="filtro_usuario">Filtrar por usuario:</label>
            <select id="filtro_usuario" name="filtro_usuario">
                <option value="">Todos los usuarios</option>
                <?php foreach ($usuarios as $usuario): ?>
                    <option value="<?php echo $usuario['id']; ?>" <?php echo ($filtro_usuario == $usuario['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($usuario['nombre_usuario']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Filtrar</button>
        </form>

        <?php if (!empty($filtro_fecha)): ?>
            <div class="reporte">
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
                            <th>Usuario</th>
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
                                <td><?php echo htmlspecialchars($movimiento['nombre_usuario']); ?></td>
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
                            <th>Contrato</th>
                            <th>Cobrador</th>
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
                                <td><?php echo htmlspecialchars($pago['nombre_contrato']); ?></td>
                                <td><?php echo htmlspecialchars($pago['nombre_usuario']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Sumatorias -->
                <h3>Sumatorias</h3>
                <p><strong>Total Efectivo:</strong> <?php echo number_format($total_efectivo, 2); ?></p>
                <p><strong>Total Tarjeta:</strong> <?php echo number_format($total_tarjeta, 2); ?></p>
                <p><strong>Total Transferencia:</strong> <?php echo number_format($total_transferencia, 2); ?></p>
                <p><strong>Total General:</strong> <?php echo number_format($total_efectivo + $total_tarjeta + $total_transferencia, 2); ?></p>
            </div>

            <!-- Botón para imprimir -->
            <button onclick="imprimirReporte()" class="no-print">Imprimir Reporte</button>
        <?php endif; ?>

        <!-- Formulario para agregar gastos o ingresos extras -->
        <h3 class="no-print">Agregar Movimiento Extra</h3>
        <form method="POST" action="caja.php" class="no-print">
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