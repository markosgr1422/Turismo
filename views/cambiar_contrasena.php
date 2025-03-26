<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

// Configuración de conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "launionsgo2024";

// Mensaje para mostrar resultados de operaciones
$message = '';

// Procesar el formulario para cambiar la contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Verificar la conexión
    if ($conn->connect_error) {
        die("Error de conexión a la base de datos: " . $conn->connect_error);
    }

    $usuario = $_SESSION['usuario'];
    $contrasena_actual = md5($_POST['contrasena_actual']); // Utilizar password_hash en producción
    $contrasena_nueva = md5($_POST['contrasena_nueva']); // Utilizar password_hash en producción
    $confirmar_contrasena = md5($_POST['confirmar_contrasena']); // Utilizar password_hash en producción

    // Verificar que la contraseña nueva y la confirmación coincidan
    if ($contrasena_nueva !== $confirmar_contrasena) {
        $message = "La nueva contraseña y la confirmación no coinciden.";
    } else {
        // Verificar que la contraseña actual sea correcta
        $sql = "SELECT * FROM usuarios WHERE nombre_usuario = ? AND contraseña = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $usuario, $contrasena_actual);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            // Actualizar la contraseña
            $sql = "UPDATE usuarios SET contraseña = ? WHERE nombre_usuario = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $contrasena_nueva, $usuario);

            if ($stmt->execute()) {
                $message = "Contraseña cambiada correctamente.";
            } else {
                $message = "Error al cambiar la contraseña: " . $stmt->error;
            }
        } else {
            $message = "La contraseña actual es incorrecta.";
        }

        $stmt->close();
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña</title>
    <link rel="stylesheet" href="../css/cambiar_contraseña.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            margin: 0;
            padding: 0;
        }
        .header {
            background-color: #333;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header a {
            color: #fff;
            text-decoration: none;
            padding: 5px 10px;
            background-color: #555;
            border-radius: 3px;
            margin-left: 10px;
        }
        .header a:hover {
            background-color: #777;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .form-group {
            margin-bottom: 10px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input[type="password"] {
            width: calc(100% - 10px);
            padding: 8px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 3px;
            margin-top: 5px;
        }
        .form-group button {
            padding: 10px;
            background-color: #333;
            color: #fff;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 16px;
        }
        .form-group button:hover {
            background-color: #555;
        }
        .message {
            background-color: #f2dede;
            color: #a94442;
            padding: 10px;
            border: 1px solid #ebccd1;
            border-radius: 3px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <span>Usuario: <?php echo isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Invitado'; ?></span>
        <div>
            <a href="cambiar_contrasena.php">Cambiar Contraseña</a>
            <a href="logout.php">Cerrar Sesión</a>
        </div>
    </div>
    <div class="container">
        <h2>Cambiar Contraseña</h2>
        <?php if (!empty($message)) : ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label for="contrasena_actual">Contraseña Actual:</label>
                <input type="password" id="contrasena_actual" name="contrasena_actual" required>
            </div>
            <div class="form-group">
                <label for="contrasena_nueva">Nueva Contraseña:</label>
                <input type="password" id="contrasena_nueva" name="contrasena_nueva" required>
            </div>
            <div class="form-group">
                <label for="confirmar_contrasena">Confirmar Nueva Contraseña:</label>
                <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" required>
            </div>
            <div class="form-group">
                <button type="submit">Cambiar Contraseña</button>
            </div>
        </form>
    </div>
</body>
</html>
