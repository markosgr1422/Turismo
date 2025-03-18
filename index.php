<?php
session_start();
require_once 'db/db_config.php'; // Incluir el archivo de configuración

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = md5($_POST['password']); // Utiliza password_hash en producción

    $sql = "SELECT * FROM usuarios WHERE nombre_usuario = ? AND contraseña = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['usuario'] = [
            'id' => $user['id'],
            'nombre_usuario' => $user['nombre_usuario']
        ];
        $_SESSION['permisos'] = json_decode($user['permisos'], true);
        header("Location: views/inicio.php");
        exit();
    } else {
        $message = "Nombre de usuario o contraseña incorrectos.";
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
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="container">
        <h2>Iniciar Sesión</h2>
        <?php if ($message) { echo "<p class='message'>$message</p>"; } ?>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="text" name="username" placeholder="Nombre de usuario" required>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">Iniciar Sesión</button>
        </form>
    </div>
</body>
</html>
