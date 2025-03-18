<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

// Verificar si el usuario tiene permiso para acceder a editar_contrato.php
if (!isset($_SESSION['permisos']['contratos']) || $_SESSION['permisos']['contratos'] !== true) {
    header('Location: index.php'); // Redireccionar si no tiene permisos
    exit();
}

// Incluir la configuración de la base de datos
require '../db/db_config.php';

// Mensaje para mostrar resultados de operaciones
$message = '';

// Verificar si se ha enviado el formulario de modificación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['modificar_contrato'])) {
    // Obtener los datos del formulario
    $id = $_POST['id'];
    $numero_referencia = $_POST['numero_referencia'];
    $nombre_contrato = $_POST['nombre_contrato'];
    $nombre_hotel = $_POST['nombre_hotel'];
    $cantidad_dias = $_POST['cantidad_dias'];
    $cantidad_noches = $_POST['cantidad_noches'];
    $monto = $_POST['monto'];
    $fecha_salida = $_POST['fecha_salida'];
    $fecha_retorno = $_POST['fecha_retorno'];

    // Actualizar los datos del contrato en la base de datos
    $sql = "UPDATE contratos SET numero_referencia = ?, nombre_contrato = ?, nombre_hotel = ?, cantidad_dias = ?, cantidad_noches = ?, monto = ?, fecha_salida = ?, fecha_retorno = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssiiissi", $numero_referencia, $nombre_contrato, $nombre_hotel, $cantidad_dias, $cantidad_noches, $monto, $fecha_salida, $fecha_retorno, $id);

    if ($stmt->execute()) {
        $message = "Contrato modificado correctamente.";
    } else {
        $message = "Error al modificar el contrato: " . $stmt->error;
    }

    $stmt->close();

    // Obtener los datos actualizados del contrato después de la modificación
    $sql = "SELECT id, numero_referencia, nombre_contrato, nombre_hotel, cantidad_dias, cantidad_noches, monto, fecha_salida, fecha_retorno FROM contratos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $contrato = $result->fetch_assoc();
    } else {
        $message = "Contrato no encontrado.";
    }

    $stmt->close();
}

// Obtener el contrato a modificar según el ID proporcionado en la URL
if (isset($_GET['id']) && !isset($contrato)) {
    $id = $_GET['id'];

    // Consulta para obtener los datos del contrato
    $sql = "SELECT id, numero_referencia, nombre_contrato, nombre_hotel, cantidad_dias, cantidad_noches, monto, fecha_salida, fecha_retorno FROM contratos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $contrato = $result->fetch_assoc();
    } else {
        $message = "Contrato no encontrado.";
    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Editar Contrato</title>
    <link rel="stylesheet" href="../css/contratos.css">
</head>

<body>
    <div class="header">
        <span>Usuario: <?php echo isset($_SESSION['usuario']['nombre_usuario']) ? $_SESSION['usuario']['nombre_usuario'] : ''; ?></span>
        <a href="logout.php">Cerrar Sesión</a>
    </div>

    <div class="container">
        <a href="contratos.php" class="back">Volver</a>
        <h2>Editar Contrato</h2>
        <p><?php echo $message; ?></p>

        <!-- Formulario para editar el contrato -->
        <form method="POST" action="editar_contrato.php">
            <input type="hidden" name="id" value="<?php echo isset($contrato['id']) ? $contrato['id'] : ''; ?>">
            <label for="numero_referencia">Número de Referencia:</label>
            <input type="text" id="numero_referencia" name="numero_referencia" value="<?php echo isset($contrato['numero_referencia']) ? $contrato['numero_referencia'] : ''; ?>" required><br><br>
            <label for="nombre_contrato">Nombre del Contrato:</label>
            <input type="text" id="nombre_contrato" name="nombre_contrato" value="<?php echo isset($contrato['nombre_contrato']) ? $contrato['nombre_contrato'] : ''; ?>" required><br><br>
            <label for="nombre_hotel">Nombre del Hotel:</label>
            <input type="text" id="nombre_hotel" name="nombre_hotel" value="<?php echo isset($contrato['nombre_hotel']) ? $contrato['nombre_hotel'] : ''; ?>" required><br><br>
            <label for="cantidad_dias">Cantidad de Días:</label>
            <input type="number" id="cantidad_dias" name="cantidad_dias" value="<?php echo isset($contrato['cantidad_dias']) ? $contrato['cantidad_dias'] : ''; ?>" required><br><br>
            <label for="cantidad_noches">Cantidad de Noches:</label>
            <input type="number" id="cantidad_noches" name="cantidad_noches" value="<?php echo isset($contrato['cantidad_noches']) ? $contrato['cantidad_noches'] : ''; ?>" required><br><br>
            <label for="monto">Monto:</label>
            <input type="text" id="monto" name="monto" value="<?php echo isset($contrato['monto']) ? $contrato['monto'] : ''; ?>" required><br><br>
            <label for="fecha_salida">Fecha de Salida:</label>
            <input type="date" id="fecha_salida" name="fecha_salida" value="<?php echo isset($contrato['fecha_salida']) ? $contrato['fecha_salida'] : ''; ?>" required><br><br>
            <label for="fecha_retorno">Fecha de Retorno:</label>
            <input type="date" id="fecha_retorno" name="fecha_retorno" value="<?php echo isset($contrato['fecha_retorno']) ? $contrato['fecha_retorno'] : ''; ?>" required><br><br>
            <button type="submit" name="modificar_contrato">Modificar Contrato</button>
        </form>
    </div>
</body>

</html>
