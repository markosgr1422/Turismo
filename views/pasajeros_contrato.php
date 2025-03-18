<?php
// Iniciar la sesión PHP
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario'])) {
    // Si el usuario no está autenticado, redirigir al inicio de sesión
    header('Location: index.php');
    exit();
}

// Incluir el archivo de conexión a la base de datos
require_once '../db/db_config.php';

// Mensaje de estado para mostrar al usuario
$message = '';

// Verificar si se recibió un ID de contrato válido por GET
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $contrato_id = $_GET['id'];

    // Consulta para obtener los pasajeros asociados a este contrato
    $sql = "SELECT p.id, p.nombre, p.apellido, p.dni, cg.credencial_pago
            FROM pasajeros p
            INNER JOIN cg_pasajeros cg ON p.id = cg.pasajero_id
            WHERE cg.contrato_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $contrato_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $pasajeros = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $pasajeros[] = $row;
        }
    } else {
        $message = "No se encontraron pasajeros para este contrato.";
    }

    $stmt->close();
} else {
    // Si no se proporciona un ID válido, redirigir a la página anterior
    header("Location: contratos.php");
    exit();
}

// Lógica para actualizar o agregar la credencial de pago
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['credencial_pago']) && isset($_POST['pasajero_id'])) {
    $credencial_pago_nueva = $_POST['credencial_pago']; // Credencial de pago nueva
    $pasajero_id = $_POST['pasajero_id']; // ID del pasajero

    // Obtener la credencial de pago actual del pasajero
    $sql_credencial_actual = "SELECT credencial_pago FROM cg_pasajeros WHERE contrato_id=? AND pasajero_id=?";
    $stmt_credencial_actual = $conn->prepare($sql_credencial_actual);
    $stmt_credencial_actual->bind_param("ii", $contrato_id, $pasajero_id);
    $stmt_credencial_actual->execute();
    $stmt_credencial_actual->bind_result($credencial_pago_anterior);
    $stmt_credencial_actual->fetch();
    $stmt_credencial_actual->close();

    // Actualizar la credencial de pago en la tabla cg_pasajeros
    $sql_update_cg_pasajeros = "UPDATE cg_pasajeros SET credencial_pago=? WHERE contrato_id=? AND pasajero_id=?";
    $stmt_update_cg_pasajeros = $conn->prepare($sql_update_cg_pasajeros);
    $stmt_update_cg_pasajeros->bind_param("sii", $credencial_pago_nueva, $contrato_id, $pasajero_id);

    if ($stmt_update_cg_pasajeros->execute()) {
        $message = '<span style="color: green;">Credencial de pago actualizada exitosamente.</span>';
    } else {
        $message = "Error al actualizar la credencial de pago en cg_pasajeros: " . $stmt_update_cg_pasajeros->error;
    }

    $stmt_update_cg_pasajeros->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pasajeros del Contrato</title>
    <link rel="stylesheet" href="../css/contratos.css">
</head>
<body>
    <div class="header">
        <span>Usuario: <?php echo isset($_SESSION['usuario']['nombre_usuario']) ? $_SESSION['usuario']['nombre_usuario'] : ''; ?></span>
        <a href="logout.php">Cerrar Sesión</a>
    </div>

    <div class="container">
        <a href="contratos.php" class="back">Volver</a>
        <h2>Pasajeros del Contrato</h2>
        <p><?php echo $message; ?></p>

        <?php if (!empty($pasajeros)): ?>
            <div class="usuarios-table">
                <h3>Listado de Pasajeros:</h3>
                <table>
                    <thead>
                        <tr>
                            <th>#</th> <!-- Añadido para la enumeración -->
                            <th>DNI</th>
                            <th>Nombre</th>
                            <th>Apellido</th>
                            <th>Credencial de Pago</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $contador = 1; // Variable para contar los pasajeros
                        foreach ($pasajeros as $pasajero): ?>
                            <tr>
                                <td><?php echo $contador++; ?></td> <!-- Muestra el número de fila -->
                                <td><?php echo $pasajero['dni']; ?></td>
                                <td><?php echo $pasajero['nombre']; ?></td>
                                <td><?php echo $pasajero['apellido']; ?></td>
                                <td><?php echo $pasajero['credencial_pago']; ?></td>
                                <td>
                                    <form method="POST" action="">
                                        <input type="hidden" name="pasajero_id" value="<?php echo $pasajero['id']; ?>">
                                        <input type="text" name="credencial_pago" placeholder="Nueva credencial" required>
                                        <button type="submit">Actualizar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
