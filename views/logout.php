<?php
// Iniciar sesión
session_start();

// Desvincular todas las variables de sesión
$_SESSION = array();

// Destruir la sesión
session_destroy();

// Redirigir al usuario a la página de inicio
header("Location: ../index.php");
exit();
?>
