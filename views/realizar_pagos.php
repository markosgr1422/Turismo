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

$search_term = "";
$pasajeros = [];
$mensaje = "";

// Obtener usuario_id de la sesión
$usuario_id = isset($_SESSION['usuario']['id']) ? $_SESSION['usuario']['id'] : null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['buscar_pasajero_submit'])) {
        $search_term = $_POST['buscar_pasajero'];

        $sql = "SELECT p.id, p.nombre, p.apellido, p.dni, cg.credencial_pago, c.nombre_contrato 
                FROM pasajeros p 
                JOIN cg_pasajeros cg ON p.id = cg.pasajero_id 
                JOIN contratos c ON cg.contrato_id = c.id
                WHERE p.nombre LIKE ? OR p.apellido LIKE ? OR cg.credencial_pago LIKE ? OR p.dni LIKE ?";
        $stmt = $conn->prepare($sql);
        $search_term_with_wildcards = "%$search_term%";
        $stmt->bind_param("ssss", $search_term_with_wildcards, $search_term_with_wildcards, $search_term_with_wildcards, $search_term_with_wildcards);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $pasajeros[] = $row;
            }
        }
        $stmt->close();
    }

    // Realizar pago
    if (isset($_POST['realizar_pago'])) {
        $pasajero_id = $_POST['pasajero_id'];
        $monto = $_POST['monto'];
        $metodo_pago = $_POST['metodo_pago'];
        $fecha = date("Y-m-d");
        $numero_transaccion = '';
        $numero_transferencia = null;
        $monto_transferencia = null;

        if ($metodo_pago == 'efectivo') {
            $numero_transaccion = uniqid();
        } elseif ($metodo_pago == 'tarjeta') {
            $numero_transaccion = $_POST['numero_transaccion'];
        } elseif ($metodo_pago == 'transferencia') {
            $numero_transferencia = $_POST['numero_transferencia'];
            $monto_transferencia = $_POST['monto_transferencia'];
        }

        // Verificar que usuario_id no sea null
        if ($usuario_id === null) {
            $mensaje = "Error: No se pudo obtener el ID del cobrador.";
        } else {
            // Insertar pago con id_cobrador
            $sql = "INSERT INTO pagos (pasajero_id, monto, fecha, metodo_pago, numero_transaccion, id_cobrador, numero_transferencia, monto_transferencia) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("idsssiis", $pasajero_id, $monto, $fecha, $metodo_pago, $numero_transaccion, $usuario_id, $numero_transferencia, $monto_transferencia);

            if ($stmt->execute()) {
                $mensaje = "Pago realizado con éxito.";
            } else {
                $mensaje = "Error al realizar el pago: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Realizar Pagos</title>
    <link rel="stylesheet" href="../css/ver_pagos_pasajeros.css">
</head>
<body>
    <div class="header">
        <span>Usuario: <?php echo isset($_SESSION['usuario']['nombre_usuario']) ? $_SESSION['usuario']['nombre_usuario'] : ''; ?></span>
        <a href="logout.php">Cerrar Sesión</a>
    </div>
    <div class="container">
        <a href="pagos.php" class="back">Volver</a>
        <h2>Realizar Pagos</h2>
        <!-- Formulario para buscar pasajero -->
        <form method="POST" action="realizar_pagos.php">
            <label for="buscar_pasajero">Buscar por Pasajero (DNI, Nombre y Apellido, o Número de Credencial):</label>
            <input type="text" id="buscar_pasajero" name="buscar_pasajero" value="<?php echo htmlspecialchars($search_term); ?>" required>
            <button type="submit" name="buscar_pasajero_submit">Buscar</button>
        </form>
        <!-- Resultados de búsqueda de pasajeros -->
        <div class="resultados">
            <?php if (!empty($pasajeros)): ?>
                <h3>Resultados de Búsqueda:</h3>
                <table>
                    <thead>
                        <tr>
                            <th>DNI</th>
                            <th>Nombre</th>
                            <th>Apellido</th>
                            <th>Credencial</th>
                            <th>Nombre del Contrato</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pasajeros as $pasajero): ?>
                            <tr>
                                <td><?php echo $pasajero['dni']; ?></td>
                                <td><?php echo $pasajero['nombre']; ?></td>
                                <td><?php echo $pasajero['apellido']; ?></td>
                                <td><?php echo $pasajero['credencial_pago']; ?></td>
                                <td><?php echo $pasajero['nombre_contrato']; ?></td>
                                <td>
                                    <form method="POST" action="realizar_pagos.php">
                                        <input type="hidden" name="pasajero_id" value="<?php echo $pasajero['id']; ?>">
                                        <label for="monto">Monto:</label>
                                        <input type="number" name="monto" required>
                                        <label for="metodo_pago">Método de Pago:</label>
                                        <select name="metodo_pago" required>
                                            <option value="efectivo">Efectivo</option>
                                            <option value="tarjeta">Tarjeta</option>
                                            <option value="transferencia">Transferencia</option>
                                        </select>
                                        <div id="numero_transaccion_div">
                                            <label for="numero_transaccion">Número de Transacción (solo para tarjeta):</label>
                                            <input type="text" name="numero_transaccion">
                                        </div>
                                        <div id="numero_transferencia_div" style="display:none;">
                                            <label for="numero_transferencia">Número de Transferencia (solo para transferencia):</label>
                                            <input type="text" name="numero_transferencia">
                                            <label for="monto_transferencia">Monto de Transferencia:</label>
                                            <input type="number" name="monto_transferencia">
                                        </div>
                                        <button type="submit" name="realizar_pago">Realizar Pago</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No se encontraron resultados para la búsqueda.</p>
            <?php endif; ?>
        </div>
        <div class="mensaje">
            <?php if ($mensaje) { echo "<p>$mensaje</p>"; } ?>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const metodoPagoSelect = document.querySelector('select[name="metodo_pago"]');
            const numeroTransaccionDiv = document.getElementById('numero_transaccion_div');
            const numeroTransferenciaDiv = document.getElementById('numero_transferencia_div');
            metodoPagoSelect.addEventListener('change', function() {
                if (metodoPagoSelect.value === 'tarjeta') {
                    numeroTransaccionDiv.style.display = 'block';
                    numeroTransferenciaDiv.style.display = 'none';
                } else if (metodoPagoSelect.value === 'transferencia') {
                    numeroTransferenciaDiv.style.display = 'block';
                    numeroTransaccionDiv.style.display = 'none';
                } else {
                    numeroTransaccionDiv.style.display = 'none';
                    numeroTransferenciaDiv.style.display = 'none';
                }
            });
            metodoPagoSelect.dispatchEvent(new Event('change'));
        });
    </script>
</body>
</html>
