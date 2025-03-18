<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

// Verificar si el usuario tiene permiso para acceder a contratos.php
if (!isset($_SESSION['permisos']['contratos']) || $_SESSION['permisos']['pagos'] !== true) {
    header('Location: ../index.php'); // Redireccionar si no tiene permisos
    exit();
}
// Incluir archivo de configuración de base de datos
require_once '../db/db_config.php';

// Conexión a la base de datos
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$mensaje_exito = $mensaje_error = "";

// Lógica para importar pagos desde un archivo TXT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['importar'])) {
    if ($_FILES['file_pago']['error'] === UPLOAD_ERR_OK) {
        $file_path = $_FILES['file_pago']['tmp_name'];

        // Procesamiento del archivo TXT
        $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $pagos_cargados = 0;
        $pagos_repetidos = 0;
        $credenciales_no_encontradas = [];
        $pagos_omitidos = [];

        foreach ($lines as $line) {
            $parts = explode('|', $line);

            if (count($parts) >= 10) {
                $credencial_pago = trim($parts[2]);
                $fecha_pago = substr($parts[3], 0, 4) . '-' . substr($parts[3], 4, 2) . '-' . substr($parts[3], 6, 2);
                $monto_pago = intval($parts[5]) / 100;
                $dni_pasajero = trim($parts[6]);
                $num_trans = trim($parts[9]);

                // Consulta para verificar si la credencial_pago existe en cg_pasajeros
                $sql_credencial = "SELECT p.id, c.id AS contrato_id
                                   FROM pasajeros p
                                   JOIN cg_pasajeros cg ON p.id = cg.pasajero_id
                                   JOIN contratos c ON cg.contrato_id = c.id
                                   WHERE cg.credencial_pago = ?";
                $stmt_credencial = $conn->prepare($sql_credencial);
                $stmt_credencial->bind_param("s", $credencial_pago);
                $stmt_credencial->execute();
                $stmt_credencial->store_result();

                if ($stmt_credencial->num_rows > 0) {
                    $stmt_credencial->bind_result($pasajero_id, $contrato_id);
                    $stmt_credencial->fetch();

                    // Consulta para verificar si el pago ya existe en la base de datos por credencial_pago y numero_transaccion
                    $sql_verificar_pago = "SELECT id FROM pagos WHERE credencial_pago = ? AND numero_transaccion = ?";
                    $stmt_verificar_pago = $conn->prepare($sql_verificar_pago);
                    $stmt_verificar_pago->bind_param("ss", $credencial_pago, $num_trans);
                    $stmt_verificar_pago->execute();
                    $stmt_verificar_pago->store_result();

                    if ($stmt_verificar_pago->num_rows == 0) {
                        // Insertar el pago en la tabla pagos
                        $sql_insert_pago = "INSERT INTO pagos (pasajero_id, contrato_id, monto, fecha, numero_transaccion, credencial_pago) VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt_insert_pago = $conn->prepare($sql_insert_pago);
                        $stmt_insert_pago->bind_param("iidsss", $pasajero_id, $contrato_id, $monto_pago, $fecha_pago, $num_trans, $credencial_pago);

                        if ($stmt_insert_pago->execute()) {
                            $pagos_cargados++;
                        } else {
                            $mensaje_error .= "Error al cargar el pago para el número de transacción $num_trans: " . $stmt_insert_pago->error . "<br>";
                        }
                        $stmt_insert_pago->close();
                    } else {
                        $pagos_omitidos[] = "Credencial $credencial_pago con número de transacción $num_trans ya existe.";
                        $pagos_repetidos++;
                    }

                    $stmt_verificar_pago->close();
                } else {
                    $credenciales_no_encontradas[] = $credencial_pago;
                }

                $stmt_credencial->close();
            } else {
                $mensaje_error .= "Error: Formato de archivo incorrecto.<br>";
            }
        }

        // Construcción de mensajes de éxito y error
        $mensaje_exito .= "Se cargaron $pagos_cargados pagos nuevos.";
        if ($pagos_repetidos > 0) {
            $mensaje_exito .= " $pagos_repetidos pagos ya estaban cargados previamente.";
        }
        if (!empty($credenciales_no_encontradas)) {
            $mensaje_error .= "Los siguientes números de credencial no fueron encontrados en la base de datos: " . implode(', ', $credenciales_no_encontradas) . "<br>";
        }
        if (!empty($pagos_omitidos)) {
            $mensaje_error .= "Pagos omitidos: " . implode(', ', $pagos_omitidos) . "<br>";
        }
    } else {
        $mensaje_error = "Error al subir el archivo.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pagos</title>
    <link rel="stylesheet" href="../css/pagos.css?v=<?php echo time(); ?>">
    <!-- Agregar ?v=<?php echo time(); ?> para forzar la recarga del archivo CSS -->
</head>
<body>
    <div class="header">
        <span>Usuario: <?php echo isset($_SESSION['usuario']['nombre_usuario']) ? $_SESSION['usuario']['nombre_usuario'] : ''; ?></span>
        <a href="logout.php">Cerrar Sesión</a>
    </div>
    
    <div class="container">
        <a href="inicio.php" class="back">Volver</a>


    <div class="container">
        <h2>Gestión de Pagos</h2>

        <!-- Mensajes de éxito y error -->
        <?php if (!empty($mensaje_exito)): ?>
            <div class="mensaje mensaje-exito"><?php echo $mensaje_exito; ?></div>
        <?php endif; ?>
        <?php if (!empty($mensaje_error)): ?>
            <div class="mensaje mensaje-error"><?php echo $mensaje_error; ?></div>
        <?php endif; ?>

        <!-- Formulario para importar pagos desde archivo TXT -->
        <form method="POST" action="pagos.php" enctype="multipart/form-data">
            <input type="file" name="file_pago" accept=".txt">
            <button type="submit" name="importar">Importar Pagos desde TXT</button>
        </form>

        <!-- Botones adicionales -->
        <form method="GET" action="ver_pagos_por_pasajero.php">
            <button type="submit">Ver Pagos Cargados por Pasajero</button>
        </form>
        <form method="GET" action="pagos_por_viaje.php">
            <button type="submit">Ver Pagos por Viajes</button>
        </form>

        <!-- Botón para realizar un pago -->
        <form method="GET" action="realizar_pagos.php">
            <button type="submit">Realizar Pago</button>
        </form>

        <!-- Botón para ver cobradores -->
        <!-- <form method="GET" action="ver_pagos_cobrador.php">
            <button type="submit">Ver Pagos por Cobradores</button>
        </form> -->
        
        <!-- Botón para volver a inicio.php -->
        <!-- <form method="GET" action="inicio.php">
            <button type="submit">Volver</button>
        </form> -->
    </div>
</body>
</html>
