<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario'])) {
    header('Location: ../index.php');
    exit();
}

// Verificar si el usuario tiene permiso para acceder a datos_usuarios.php
if (!isset($_SESSION['permisos']['datos_usuario']) || $_SESSION['permisos']['datos_usuario'] !== true) {
    header('Location: ../index.php'); // Redireccionar si no tiene permisos
    exit();
}

// Incluir el archivo de configuración de la base de datos
require_once '../db/db_config.php';

// Mensaje para mostrar resultados de operaciones
$message = '';

// Procesar el formulario para agregar usuarios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        // Crear nuevo usuario
        if ($_POST['accion'] === 'crear') {
            $nombre = $_POST['nombre'];
            $apellido = $_POST['apellido'];
            $nombre_usuario = $_POST['nombre_usuario'];
            $contrasena = md5($_POST['contrasena']); // Utilizar password_hash en producción

            // Convertir permisos a JSON
            $permisos = [
                'datos_usuario' => isset($_POST['permisos']['datos_usuario']),
                'datos_pasajeros' => isset($_POST['permisos']['datos_pasajeros']),
                'pagos' => isset($_POST['permisos']['pagos']),
                'contratos' => isset($_POST['permisos']['contratos']),
                'caja' => isset($_POST['permisos']['caja'])
            ];

            $permisos_json = json_encode($permisos);

            $sql = "INSERT INTO usuarios (nombre, apellido, nombre_usuario, contraseña, permisos) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $nombre, $apellido, $nombre_usuario, $contrasena, $permisos_json);

            if ($stmt->execute()) {
                $message = "Usuario creado correctamente.";
            } else {
                $message = "Error al crear el usuario: " . $stmt->error;
            }

            $stmt->close();
        }

        // Eliminar usuario
        if ($_POST['accion'] === 'eliminar' && isset($_POST['id'])) {
            $id = $_POST['id'];
            $sql = "DELETE FROM usuarios WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $message = "Usuario eliminado correctamente.";
            } else {
                $message = "Error al eliminar el usuario: " . $conn->error;
            }

            $stmt->close();
        }

        // Modificar usuario (redireccionar a un nuevo archivo modifica_usuario.php)
        if ($_POST['accion'] === 'modificar' && isset($_POST['id'])) {
            $id = $_POST['id'];
            header("Location: modifica_usuario.php?id=$id");
            exit();
        }
    }
}

// Obtener todos los usuarios de la base de datos
$sql = "SELECT id, nombre, apellido, nombre_usuario FROM usuarios";
$result = $conn->query($sql);

$usuarios = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $usuarios[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios</title>
    <link rel="stylesheet" href="../css/datos_usuarios.css"> <!-- Incluir el archivo CSS separado -->
</head>
<body>
    <div class="header">
        <span>Usuario: <?php echo isset($_SESSION['usuario']['nombre_usuario']) ? $_SESSION['usuario']['nombre_usuario'] : 'Invitado'; ?></span>
        <a href="inicio.php">Inicio</a>
        <a href="logout.php">Cerrar Sesión</a>
    </div>
    <div class="container">
        <h2>Administración de Usuarios</h2>
        <?php if (!empty($message)) : ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" required>
            </div>
            <div class="form-group">
                <label for="apellido">Apellido:</label>
                <input type="text" id="apellido" name="apellido" required>
            </div>
            <div class="form-group">
                <label for="nombre_usuario">Nombre de Usuario:</label>
                <input type="text" id="nombre_usuario" name="nombre_usuario" required>
            </div>
            <div class="form-group">
                <label for="contrasena">Contraseña:</label>
                <input type="password" id="contrasena" name="contrasena" required>
            </div>
            <div class="form-group">
                <label>Permisos:</label><br>
                <input type="checkbox" name="permisos[datos_usuario]"> Sección de Usuario<br>
                <input type="checkbox" name="permisos[datos_pasajeros]"> Datos de Pasajeros<br>
                <input type="checkbox" name="permisos[pagos]"> Pagos<br>
                <input type="checkbox" name="permisos[contratos]"> Contratos<br>
                <input type="checkbox" name="permisos[caja]"> Caja<br>
            </div>
            <div class="form-group">
                <button type="submit" name="accion" value="crear">Crear Usuario</button>
            </div>
        </form>
        
        <h2>Listado de Usuarios</h2>
        <?php if (!empty($usuarios)) : ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>Nombre de Usuario</th>
                    <th>Acciones</th>
                </tr>
                <?php foreach ($usuarios as $usuario) : ?>
                    <tr>
                        <td><?php echo $usuario['id']; ?></td>
                        <td><?php echo $usuario['nombre']; ?></td>
                        <td><?php echo $usuario['apellido']; ?></td>
                        <td><?php echo $usuario['nombre_usuario']; ?></td>
                        <td>
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                                <button type="submit" name="accion" value="eliminar">Eliminar</button>
                                <button type="submit" name="accion" value="modificar">Modificar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else : ?>
            <p>No hay usuarios registrados.</p>
        <?php endif; ?>
    </div>
</body>
</html>
