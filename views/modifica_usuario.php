<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

// Verificar si el usuario tiene permiso para acceder a datos_usuarios.php
if (!isset($_SESSION['permisos']['datos_usuario']) || $_SESSION['permisos']['datos_usuario'] !== true) {
    header('Location: inicio.php'); // Redirigir si no tiene permisos
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

// Obtener el ID del usuario a modificar desde la URL
if (!isset($_GET['id'])) {
    header('Location: datos_usuarios.php'); // Redireccionar si no se proporciona un ID válido
    exit();
}
$id = $_GET['id'];

// Procesar el formulario para guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Verificar la conexión
    if ($conn->connect_error) {
        die("Error de conexión a la base de datos: " . $conn->connect_error);
    }

    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $nombre_usuario = $_POST['nombre_usuario'];
    $contrasena = !empty($_POST['contrasena']) ? password_hash($_POST['contrasena'], PASSWORD_DEFAULT) : null; // Utilizar password_hash

    // Convertir permisos a JSON
    $permisos = [
        'datos_usuario' => isset($_POST['permisos']['datos_usuario']),
        'datos_pasajeros' => isset($_POST['permisos']['datos_pasajeros']),
        'pagos' => isset($_POST['permisos']['pagos']),
        'contratos' => isset($_POST['permisos']['contratos']),
        'reportes' => isset($_POST['permisos']['reportes']),
        'caja' => isset($_POST['permisos']['caja'])
    ];

    $permisos_json = json_encode($permisos);

    // Si la contraseña está vacía, no se actualiza en la base de datos
    if ($contrasena === null) {
        $sql = "UPDATE usuarios SET nombre = ?, apellido = ?, nombre_usuario = ?, permisos = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $nombre, $apellido, $nombre_usuario, $permisos_json, $id);
    } else {
        $sql = "UPDATE usuarios SET nombre = ?, apellido = ?, nombre_usuario = ?, contraseña = ?, permisos = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $nombre, $apellido, $nombre_usuario, $contrasena, $permisos_json, $id);
    }

    if ($stmt->execute()) {
        $message = "Usuario modificado correctamente.";
    } else {
        $message = "Error al modificar el usuario: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}

// Obtener datos del usuario seleccionado para modificar
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar la conexión
if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}

$sql = "SELECT * FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $usuario = $result->fetch_assoc();

    // Convertir permisos JSON a array asociativo
    $permisos_usuario = json_decode($usuario['permisos'], true);
} else {
    // Si no se encuentra el usuario, redireccionar de vuelta a la lista de usuarios
    header('Location: datos_usuarios.php');
    exit();
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar Usuario</title>
    <link rel="stylesheet" href="../css/modificar_usuarios.css">
</head>
<body>
    <div class="header">
        <span>Usuario: <?php echo isset($_SESSION['usuario']['nombre']) ? $_SESSION['usuario']['nombre'] : 'Invitado'; ?></span>
        <a href="inicio.php">Inicio</a>
        <a href="logout.php">Cerrar Sesión</a>
    </div>
    <div class="container">
        <h2>Modificar Usuario</h2>
        <?php if (!empty($message)) : ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $id; ?>">
            <div class="form-group">
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo $usuario['nombre']; ?>" required>
            </div>
            <div class="form-group">
                <label for="apellido">Apellido:</label>
                <input type="text" id="apellido" name="apellido" value="<?php echo $usuario['apellido']; ?>" required>
            </div>
            <div class="form-group">
                <label for="nombre_usuario">Nombre de Usuario:</label>
                <input type="text" id="nombre_usuario" name="nombre_usuario" value="<?php echo $usuario['nombre_usuario']; ?>" required>
            </div>
            <div class="form-group">
                <label for="contrasena">Contraseña:</label>
                <input type="password" id="contrasena" name="contrasena">
                <small>Dejar en blanco para no modificar la contraseña.</small>
            </div>
            <div class="form-group">
                <label>Permisos:</label><br>
                <input type="checkbox" name="permisos[datos_usuario]" <?php echo $permisos_usuario['datos_usuario'] ? 'checked' : ''; ?>> Datos de Usuario<br>
                <input type="checkbox" name="permisos[datos_pasajeros]" <?php echo $permisos_usuario['datos_pasajeros'] ? 'checked' : ''; ?>> Datos de Pasajeros<br>
                <input type="checkbox" name="permisos[pagos]" <?php echo $permisos_usuario['pagos'] ? 'checked' : ''; ?>> Pagos<br>
                <input type="checkbox" name="permisos[contratos]" <?php echo $permisos_usuario['contratos'] ? 'checked' : ''; ?>> Contratos<br>
                <input type="checkbox" name="permisos[reportes]" <?php echo $permisos_usuario['reportes'] ? 'checked' : ''; ?>> Reportes<br>
                <input type="checkbox" name="permisos[caja]" <?php echo $permisos_usuario['caja'] ? 'checked' : ''; ?>> Caja<br>
            </div>
            <div class="form-group">
                <button type="submit">Guardar Cambios</button>
            </div>
        </form>
    </div>
</body>
</html>
