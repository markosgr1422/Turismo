<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

// Verificar si el usuario tiene permiso para acceder a contratos.php
if (!isset($_SESSION['permisos']['contratos']) || $_SESSION['permisos']['contratos'] !== true) {
    header('Location: ../index.php'); // Redireccionar si no tiene permisos
    exit();
}

// Incluir la configuración de la base de datos
require '../db/db_config.php';

// Mensaje para mostrar resultados de operaciones
$message = '';

// Lógica para agregar/modificar pasajero
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Lógica para crear un contrato
    if (isset($_POST['crear_contrato'])) {
        $numero_referencia = $_POST['numero_referencia'];
        $nombre_contrato = $_POST['nombre_contrato'];
        $nombre_hotel = $_POST['nombre_hotel'];
        $cantidad_dias = $_POST['cantidad_dias'];
        $cantidad_noches = $_POST['cantidad_noches'];
        $monto = $_POST['monto'];
        $año = $_POST['año'];
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
            $sql = "INSERT INTO contratos (numero_referencia, nombre_contrato, nombre_hotel, cantidad_dias, cantidad_noches, monto, año, fecha_salida, fecha_retorno) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssiidsss", $numero_referencia, $nombre_contrato, $nombre_hotel, $cantidad_dias, $cantidad_noches, $monto, $año, $fecha_salida, $fecha_retorno);

            if ($stmt->execute()) {
                $message = "Contrato creado correctamente.";
            } else {
                $message = "Error al crear el contrato: " . $stmt->error;
            }
        }

        $stmt->close();
    }

    // Lógica para eliminar un contrato
    if (isset($_POST['eliminar_contrato'])) {
        $contrato_id = $_POST['contrato_id'];

        // Eliminar el contrato de la base de datos
        $sql = "DELETE FROM contratos WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $contrato_id);

        if ($stmt->execute()) {
            $message = "Contrato eliminado correctamente.";
        } else {
            $message = "Error al eliminar el contrato: " . $stmt->error;
        }

        $stmt->close();
    }
}

// Obtener los contratos filtrados por número de referencia o nombre de contrato
$contratos = [];
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$filter_sql = "%" . $filter . "%";

$sql = "SELECT c.id, c.nombre_contrato, c.numero_referencia, c.nombre_hotel, c.monto, c.cantidad_dias, c.cantidad_noches, c.año, c.fecha_salida, c.fecha_retorno
        FROM contratos c
        WHERE c.numero_referencia LIKE ? OR c.nombre_contrato LIKE ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $filter_sql, $filter_sql);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $contratos[] = $row;
    }
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contratos</title>
    <link rel="stylesheet" href="../css/contratos.css">
</head>
<body>
    <div class="header">
        <span>Usuario: <?php echo isset($_SESSION['usuario']['nombre_usuario']) ? $_SESSION['usuario']['nombre_usuario'] : ''; ?></span>
        <a href="logout.php">Cerrar Sesión</a>
    </div>
    
    <div class="container">
        <a href="inicio.php" class="back">Volver</a>
        <h2>Contratos</h2>
        <p><?php echo $message; ?></p>

        <div class="resultados">
            <a href="crear_contrato.php">Crear contrato</a>
            <div class="usuarios-table">
                <h3>Contratos existentes:</h3>
                <!-- Formulario de filtro -->
                <form method="GET" action="contratos.php">
                    <label for="filter">Buscar por número de referencia o nombre de contrato:</label>
                    <input type="text" id="filter" name="filter" value="<?php echo htmlspecialchars($filter); ?>" required>
                    <button type="submit">Buscar</button>
                </form>
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Hotel</th>
                            <th>Monto</th>
                            <th>Días</th>
                            <th>Noches</th>
                            <th>Año</th>
                            <th>Fechas</th> <!-- Nueva columna para las fechas -->
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contratos as $contrato): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($contrato['numero_referencia']); ?></td>
                                <td><?php echo htmlspecialchars($contrato['nombre_contrato']); ?></td>
                                <td><?php echo htmlspecialchars($contrato['nombre_hotel']); ?></td>
                                <td><?php echo number_format($contrato['monto'], 2); ?></td>
                                <td><?php echo $contrato['cantidad_dias']; ?></td>
                                <td><?php echo $contrato['cantidad_noches']; ?></td>
                                <td><?php echo $contrato['año']; ?></td>

                                <!-- Mostrar las fechas de salida y retorno -->
                                <td>
                                    <div class="fechas-recuadro">
                                        <?php 
                                        // Mostrar las fechas desde la base de datos
                                        echo "Fecha salida: " . date("Y-m-d", strtotime($contrato['fecha_salida'])) . "<br>";
                                        echo "Fecha retorno: " . date("Y-m-d", strtotime($contrato['fecha_retorno'])); 
                                        ?>
                                    </div>
                                </td>

                                <td>
                                    <a href="editar_contrato.php?id=<?php echo $contrato['id']; ?>">Editar</a>
                                    <a href="pasajeros_contrato.php?id=<?php echo $contrato['id']; ?>">Ver</a> <!-- Enlace Ver -->
                                    <form method="POST" action="contratos.php" style="display:inline;">
                                        <input type="hidden" name="contrato_id" value="<?php echo $contrato['id']; ?>">
                                        <button type="submit" name="eliminar_contrato">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
