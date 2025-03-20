<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario'])) {
    header('Location: ../index.php');
    exit();
}

// Verificar si el usuario tiene permiso para acceder a caja.php
if (!isset($_SESSION['permisos']['reportes']) || $_SESSION['permisos']['reportes'] !== true) {
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

// Obtener datos de contratos
$sql_contratos = "SELECT id, numero_referencia, nombre_contrato, nombre_hotel, cantidad_dias, cantidad_noches, monto, fecha_salida, fecha_retorno FROM contratos";
$result_contratos = $conn->query($sql_contratos);
$contratos = [];
if ($result_contratos->num_rows > 0) {
    while ($row = $result_contratos->fetch_assoc()) {
        $contratos[] = $row;
    }
}

// Obtener datos de pasajeros
$sql_pasajeros = "SELECT p.id, p.nombre, p.apellido, p.dni, c.nombre_contrato 
                  FROM pasajeros p 
                  JOIN cg_pasajeros cg ON p.id = cg.pasajero_id 
                  JOIN contratos c ON cg.contrato_id = c.id";
$result_pasajeros = $conn->query($sql_pasajeros);
$pasajeros = [];
if ($result_pasajeros->num_rows > 0) {
    while ($row = $result_pasajeros->fetch_assoc()) {
        $pasajeros[] = $row;
    }
}

// Obtener datos de pagos
$sql_pagos = "SELECT p.fecha, p.monto, p.metodo_pago, pa.nombre, pa.apellido, c.nombre_contrato 
              FROM pagos p 
              JOIN pasajeros pa ON p.pasajero_id = pa.id 
              JOIN contratos c ON p.contrato_id = c.id";
$result_pagos = $conn->query($sql_pagos);
$pagos = [];
if ($result_pagos->num_rows > 0) {
    while ($row = $result_pagos->fetch_assoc()) {
        $pagos[] = $row;
    }
}

// Obtener datos de caja
$sql_caja = "SELECT tipo, descripcion, monto, fecha, comprobante FROM caja";
$result_caja = $conn->query($sql_caja);
$caja = [];
if ($result_caja->num_rows > 0) {
    while ($row = $result_caja->fetch_assoc()) {
        $caja[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes</title>
    <link rel="stylesheet" href="../css/reportes.css">
</head>
<body>
    <div class="header">
        <span>Usuario: <?php echo isset($_SESSION['usuario']['nombre_usuario']) ? $_SESSION['usuario']['nombre_usuario'] : ''; ?></span>
        <a href="inicio.php">Inicio</a>
        <a href="logout.php">Cerrar Sesión</a>
    </div>

    <div class="container">
        <h2>Reportes Generales</h2>

        <!-- Sección de Contratos -->
        <h3>Contratos</h3>
        <table>
            <thead>
                <tr>
                    <th>Número de Referencia</th>
                    <th>Nombre del Contrato</th>
                    <th>Hotel</th>
                    <th>Días</th>
                    <th>Noches</th>
                    <th>Monto</th>
                    <th>Fecha de Salida</th>
                    <th>Fecha de Retorno</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contratos as $contrato): ?>
                    <tr>
                        <td><?php echo $contrato['numero_referencia']; ?></td>
                        <td><?php echo $contrato['nombre_contrato']; ?></td>
                        <td><?php echo $contrato['nombre_hotel']; ?></td>
                        <td><?php echo $contrato['cantidad_dias']; ?></td>
                        <td><?php echo $contrato['cantidad_noches']; ?></td>
                        <td><?php echo number_format($contrato['monto'], 2); ?></td>
                        <td><?php echo $contrato['fecha_salida']; ?></td>
                        <td><?php echo $contrato['fecha_retorno']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Sección de Pasajeros -->
        <h3>Pasajeros</h3>
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>DNI</th>
                    <th>Contrato</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pasajeros as $pasajero): ?>
                    <tr>
                        <td><?php echo $pasajero['nombre']; ?></td>
                        <td><?php echo $pasajero['apellido']; ?></td>
                        <td><?php echo $pasajero['dni']; ?></td>
                        <td><?php echo $pasajero['nombre_contrato']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Sección de Pagos -->
        <h3>Pagos</h3>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Monto</th>
                    <th>Método de Pago</th>
                    <th>Pasajero</th>
                    <th>Contrato</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pagos as $pago): ?>
                    <tr>
                        <td><?php echo $pago['fecha']; ?></td>
                        <td><?php echo number_format($pago['monto'], 2); ?></td>
                        <td><?php echo $pago['metodo_pago']; ?></td>
                        <td><?php echo $pago['nombre'] . ' ' . $pago['apellido']; ?></td>
                        <td><?php echo $pago['nombre_contrato']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Sección de Caja -->
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
                <?php foreach ($caja as $movimiento): ?>
                    <tr>
                        <td><?php echo $movimiento['tipo']; ?></td>
                        <td><?php echo $movimiento['descripcion']; ?></td>
                        <td><?php echo number_format($movimiento['monto'], 2); ?></td>
                        <td><?php echo $movimiento['fecha']; ?></td>
                        <td><?php echo $movimiento['comprobante']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>