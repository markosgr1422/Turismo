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
$pasajeros = [];
$pagos = [];
$total_viaje = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['buscar_pasajero_submit'])) {
    $search_term = $_POST['buscar_pasajero'];

    $sql = "SELECT p.id, p.nombre, p.apellido, p.dni, cg.credencial_pago, c.numero_referencia, c.nombre_contrato, c.monto AS monto_total_viaje
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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ver_pagos'])) {
    $pasajero_id = $_POST['pasajero_id'];
    $credencial_pago = $_POST['credencial_pago'];

    $sql = "SELECT monto, fecha FROM pagos WHERE pasajero_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $pasajero_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $pagos[] = $row;
        }
    }
    $stmt->close();

    // Obtener el monto total del viaje desde la tabla contratos
    $sql_total_viaje = "SELECT c.monto FROM contratos c 
                        JOIN cg_pasajeros cg ON c.id = cg.contrato_id 
                        WHERE cg.pasajero_id = ?";
    $stmt_total_viaje = $conn->prepare($sql_total_viaje);
    $stmt_total_viaje->bind_param("i", $pasajero_id);
    $stmt_total_viaje->execute();
    $result_total_viaje = $stmt_total_viaje->get_result();

    if ($result_total_viaje->num_rows > 0) {
        $row_total_viaje = $result_total_viaje->fetch_assoc();
        $total_viaje = $row_total_viaje['monto'];
    }
    $stmt_total_viaje->close();
}

$conn->close();

function format_currency($amount) {
    return '$' . number_format($amount, 2, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Pagos por Pasajero</title>
    <link rel="stylesheet" href="../css/ver_pagos_pasajeros.css">
    <style>
        .total-container {
            background-color: lightgrey;
            padding: 10px;
            margin-top: 10px;
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
        <h2>Ver Pagos por Pasajero</h2>
        <!-- Formulario para buscar pasajero por nombre, apellido, número de credencial o DNI -->
        <form method="POST" action="ver_pagos_por_pasajero.php">
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
                            <th>Número de Credencial</th>
                            <th>Número de Contrato</th>
                            <th>Nombre de Contrato</th>
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
                                <td><?php echo $pasajero['numero_referencia']; ?></td>
                                <td><?php echo $pasajero['nombre_contrato']; ?></td>
                                <td>
                                    <form method="POST" action="ver_pagos_por_pasajero.php">
                                        <input type="hidden" name="pasajero_id" value="<?php echo $pasajero['id']; ?>">
                                        <input type="hidden" name="credencial_pago" value="<?php echo $pasajero['credencial_pago']; ?>">
                                        <button type="submit" name="ver_pagos">Ver Pagos</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <!-- Historial de pagos del pasajero -->
            <?php if (!empty($pagos)): ?>
                <h3>Historial de Pagos:</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_pagado = 0;
                        foreach ($pagos as $pago): 
                            $total_pagado += $pago['monto'];
                        ?>
                            <tr>
                                <td><?php echo $pago['fecha']; ?></td>
                                <td><?php echo format_currency($pago['monto']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="total-container">
                    <h4>Total Pagado: <?php echo format_currency($total_pagado); ?></h4>
                    <h4>Total del Viaje: <?php echo format_currency($total_viaje); ?></h4>
                    <h4>Monto para Cancelar Viaje: <?php echo format_currency($total_viaje - $total_pagado); ?></h4>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
