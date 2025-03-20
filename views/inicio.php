<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario'])) {
    header('Location: ../index.php');
    exit();
}

// Obtener nombre de usuario desde la sesión
$usuario = isset($_SESSION['usuario']['nombre_usuario']) ? $_SESSION['usuario']['nombre_usuario'] : 'Invitado';

// Obtener permisos del usuario desde la sesión
$permisos = isset($_SESSION['permisos']) ? $_SESSION['permisos'] : [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio</title>
    <link rel="stylesheet" href="../css/inicio.css">
</head>
<body>
    <div class="header">
        <span>Usuario: <?php echo $usuario; ?></span>
        <div>
            <a href="cambiar_contrasena.php">Cambiar Contraseña</a>
            <a href="logout.php">Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <h2>Panel de Control</h2>
        <div class="links-container">
            <?php if (in_array('datos_usuario', $permisos)) : ?>
                <a href="datos_usuario.php" class="dashboard-link">
                    <div class="dashboard-item">
                        <h3>Sección de Usuarios</h3>
                        <p>Gestión de usuarios y permisos</p>
                    </div>
                </a>
            <?php endif; ?>
            <?php if (in_array('datos_pasajeros', $permisos)) : ?>
                <a href="datos_pasajeros.php" class="dashboard-link">
                    <div class="dashboard-item">
                        <h3>Pasajeros</h3>
                        <p>Gestión de pasajeros y CG</p>
                    </div>
                </a>
            <?php endif; ?>
            <?php if (in_array('pagos', $permisos)) : ?>
                <a href="pagos.php" class="dashboard-link">
                    <div class="dashboard-item">
                        <h3>Pagos</h3>
                        <p>Registro y gestión de pagos</p>
                    </div>
                </a>
            <?php endif; ?>
            <?php if (in_array('contratos', $permisos)) : ?>
                <a href="contratos.php" class="dashboard-link">
                    <div class="dashboard-item">
                        <h3>Contratos</h3>
                        <p>Gestión de contratos y autorizaciones</p>
                    </div>
                </a>
            <?php endif; ?>
            <?php if (in_array('caja', $permisos)) : ?>
                <a href="caja.php" class="dashboard-link">
                    <div class="dashboard-item">
                        <h3>Caja</h3>
                        <p>Gestión de ingresos y egresos</p>
                    </div>
                </a>
            <?php endif; ?>
            <?php if (in_array('caja', $permisos)) : ?>
                <a href="reportes.php" class="dashboard-link">
                    <div class="dashboard-item">
                        <h3>Salidas</h3>
                        <p>Informe Completo de Salidas</p>
                    </div>
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
