<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

// Verificar si el usuario tiene permiso para acceder a crear_contratos.php
if (!isset($_SESSION['permisos']['contratos']) || $_SESSION['permisos']['contratos'] !== true) {
    header('Location: index.php'); // Redireccionar si no tiene permisos
    exit();
}

// Incluir la configuración de la base de datos
require '../db/db_config.php';

// Mensaje para mostrar resultados de operaciones
$message = '';

// Procesar el formulario para agregar contratos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion']) && $_POST['accion'] === 'crear') {
        // Obtener los datos del formulario
        $numero_referencia = $_POST['numero_referencia'];
        $nombre_contrato = $_POST['nombre_contrato'];
        $año = $_POST['año'];
        $nombre_hotel = $_POST["nombre_hotel"];
        $cantidad_dias = $_POST['cantidad_dias'];
        $cantidad_noches = $_POST['cantidad_noches'];
        $monto = $_POST['monto'];
        $fecha_salida = $_POST['fecha_salida'];
        $fecha_retorno = $_POST['fecha_retorno'];

        // Verificar que no exista un contrato con el mismo número de referencia
        $sql = "SELECT id FROM contratos WHERE numero_referencia = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $numero_referencia);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message = "Ya existe un contrato con el mismo número de referencia.";
        } else {
            // Insertar el nuevo contrato en la base de datos
            $sql = "INSERT INTO contratos (numero_referencia, nombre_contrato, año, nombre_hotel, cantidad_dias, cantidad_noches, monto, fecha_salida, fecha_retorno) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            // Corregir la cadena de tipo para bind_param
            $stmt->bind_param("ssissdsss", $numero_referencia, $nombre_contrato, $año, $nombre_hotel, $cantidad_dias, $cantidad_noches, $monto, $fecha_salida, $fecha_retorno);

            if ($stmt->execute()) {
                $message = "Contrato creado correctamente.";
            } else {
                $message = "Error al crear el contrato: " . $stmt->error;
            }
        }

        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Contrato</title>
    <link rel="stylesheet" href="../css/crear_contratos.css">
</head>
<body>
    <div class="header">
        <span>Usuario: <?php echo isset($_SESSION['usuario']['nombre_usuario']) ? $_SESSION['usuario']['nombre_usuario'] : ''; ?></span>
        <a href="contratos.php" class="back">Volver</a>
        <a href="logout.php">Cerrar Sesión</a>
    </div>

    <div class="container">
        <h2>Crear Nuevo Contrato</h2>
        <?php if (!empty($message)) : ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label for="numero_referencia">Número de Referencia:</label>
                <input type="text" id="numero_referencia" name="numero_referencia" required>
            </div>
            <div class="form-group">
                <label for="nombre_contrato">Nombre del Contrato:</label>
                <input type="text" id="nombre_contrato" name="nombre_contrato" required>
            </div>
            <div class="form-group">
                <label for="nombre_hotel">Nombre del Hotel:</label>
                <input type="text" id="nombre_hotel" name="nombre_hotel" required>
            </div>
            <div class="form-group">
                <label for="año">Año:</label>
                <input type="number" id="año" name="año" required>
            </div>
            <div class="form-group">
                <label for="cantidad_dias">Cantidad de Días:</label>
                <input type="number" id="cantidad_dias" name="cantidad_dias" required>
            </div>
            <div class="form-group">
                <label for="cantidad_noches">Cantidad de Noches:</label>
                <input type="number" id="cantidad_noches" name="cantidad_noches" required>
            </div>
            <div class="form-group">
                <label for="monto">Monto:</label>
                <input type="number" id="monto" name="monto" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="fecha_salida">Fecha de Salida:</label>
                <input type="date" id="fecha_salida" name="fecha_salida" required>
            </div>
            <div class="form-group">
                <label for="fecha_retorno">Fecha de Retorno:</label>
                <input type="date" id="fecha_retorno" name="fecha_retorno" required>
            </div>
            <div class="form-group">
                <button type="submit" name="accion" value="crear">Crear Contrato</button>
            </div>
        </form>
    </div>
</body>
</html>
