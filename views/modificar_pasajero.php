<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

// Verificar si el usuario tiene permiso para acceder a datos_usuarios.php
if (!isset($_SESSION['permisos']['datos_usuario']) || $_SESSION['permisos']['datos_pasajeros'] !== true) {
    header('Location: index.php'); // Redireccionar si no tiene permisos
    exit();
}
// Incluir el archivo de configuración de la base de datos
require_once '../db/db_config.php';

// Mensaje para mostrar el resultado de las operaciones
$message = '';

// Obtener los datos del pasajero a modificar
if (isset($_GET['id'])) {
    $pasajero_id = $_GET['id'];
    $sql = "SELECT * FROM pasajeros WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $pasajero_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pasajero = $result->fetch_assoc();
}

// Lógica para modificar los datos del pasajero
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nombre'])) {
    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $dni = $_POST['dni'];
    $pasajero_id = $_POST['pasajero_id'];

    $sql = "UPDATE pasajeros SET nombre = ?, apellido = ?, dni = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $nombre, $apellido, $dni, $pasajero_id);

    if ($stmt->execute()) {
        $message = "Pasajero modificado exitosamente.";
    } else {
        $message = "Error al modificar pasajero: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar Pasajero</title>
    <link rel="stylesheet" href="../css/datos_pasajeros.css">
</head>

<body>
    <div class="header">
        <span>Usuario: <?php echo $_SESSION['usuario']; ?></span>
        <a href="logout.php">Cerrar Sesión</a>
    </div>
    <div class="container">
        <h2>Modificar Pasajero</h2>
        <?php if (!empty($message)): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>

        <form method="POST" action="modificar_pasajero.php?id=<?php echo $pasajero['id']; ?>">
            <input type="hidden" name="pasajero_id" value="<?php echo $pasajero['id']; ?>">
            
            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo $pasajero['nombre']; ?>" required>

            <label for="apellido">Apellido:</label>
            <input type="text" id="apellido" name="apellido" value="<?php echo $pasajero['apellido']; ?>" required>

            <label for="dni">DNI:</label>
            <input type="text" id="dni" name="dni" value="<?php echo $pasajero['dni']; ?>" required>

            <button type="submit">Modificar Pasajero</button>
        </form>

        <button onclick="window.location.href='datos_pasajeros.php'">Volver</button>
    </div>
</body>

</html>
