<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario'])) {
    header('Location: ../index.php');
    exit();
}

// Verificar si el usuario tiene permiso para acceder a datos_pasajeros.php
if (!isset($_SESSION['permisos']['datos_pasajeros']) || $_SESSION['permisos']['datos_pasajeros'] !== true) {
    header('Location: inicio.php'); // Redireccionar si no tiene permisos
    exit();
}

// Incluir el archivo de configuración de la base de datos
require_once '../db/db_config.php';

// Mensaje para mostrar el resultado de las operaciones
$message = '';

// Lógica para agregar un pasajero a un contrato
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'])) {
    // Obtener los datos del pasajero
    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $dni = $_POST['dni'];
    $contrato_id = $_POST['contrato_id']; // ID del contrato seleccionado
    $credencial_pago = $_POST['credencial_pago']; // Credencial de pago proporcionada

    // Insertar el pasajero en la tabla pasajeros
    $sql = "INSERT INTO pasajeros (nombre, apellido, dni) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $nombre, $apellido, $dni);

    if ($stmt->execute()) {
        $pasajero_id = $stmt->insert_id; // Obtener el ID del pasajero recién insertado

        // Generar credencial de pago si no se proporcionó
        if (empty($credencial_pago)) {
            $credencial_pago = generateCredencialPago();
        }

        // Asignar el pasajero al contrato en la tabla cg_pasajeros
        $sql = "INSERT INTO cg_pasajeros (contrato_id, pasajero_id, credencial_pago) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $contrato_id, $pasajero_id, $credencial_pago);

        if ($stmt->execute()) {
            $message = "Pasajero agregado y asignado al contrato exitosamente.";
        } else {
            $message = "Error al asignar el pasajero al contrato: " . $stmt->error;
        }
    } else {
        $message = "Error al agregar pasajero: " . $stmt->error;
    }

    $stmt->close();
}

// Lógica para eliminar un pasajero de un contrato específico
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pasajero_id']) && isset($_POST['delete_contrato_id'])) {
    $pasajero_id = $_POST['delete_pasajero_id'];
    $contrato_id = $_POST['delete_contrato_id'];

    // Eliminar el pasajero del contrato en la tabla cg_pasajeros
    $sql_delete_cg_pasajeros = "DELETE FROM cg_pasajeros WHERE pasajero_id = ? AND contrato_id = ?";
    $stmt_delete_cg_pasajeros = $conn->prepare($sql_delete_cg_pasajeros);
    $stmt_delete_cg_pasajeros->bind_param("ii", $pasajero_id, $contrato_id);

    if ($stmt_delete_cg_pasajeros->execute()) {
        $message = "Pasajero eliminado del contrato exitosamente.";

        // Eliminar los pagos asociados al pasajero y contrato en la tabla pagos
        $sql_delete_pagos = "DELETE FROM pagos WHERE pasajero_id = ? AND contrato_id = ?";
        $stmt_delete_pagos = $conn->prepare($sql_delete_pagos);
        $stmt_delete_pagos->bind_param("ii", $pasajero_id, $contrato_id);
        $stmt_delete_pagos->execute();

        $stmt_delete_pagos->close();
    } else {
        $message = "Error al eliminar el pasajero del contrato: " . $stmt_delete_cg_pasajeros->error;
    }

    $stmt_delete_cg_pasajeros->close();
}

// Función para generar una credencial de pago única
function generateCredencialPago() {
    return uniqid(); // Puedes ajustar la generación según tus requisitos
}

// Consulta para obtener todos los contratos disponibles
$sql_contratos = "SELECT id, numero_referencia, nombre_contrato FROM contratos";
$result_contratos = $conn->query($sql_contratos);
$contratos = [];
if ($result_contratos->num_rows > 0) {
    while ($row = $result_contratos->fetch_assoc()) {
        $contratos[] = $row;
    }
}

// Lógica para buscar pasajeros por DNI, nombre o apellido
$pasajeros = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search_pasajero'])) {
    $search_pasajero = $_GET['search_pasajero'];
    $sql = "SELECT p.id, p.nombre, p.apellido, p.dni, c.numero_referencia, c.nombre_contrato, cp.contrato_id
            FROM pasajeros p
            INNER JOIN cg_pasajeros cp ON p.id = cp.pasajero_id
            INNER JOIN contratos c ON cp.contrato_id = c.id
            WHERE p.dni LIKE ? OR p.nombre LIKE ? OR p.apellido LIKE ?";
    $stmt = $conn->prepare($sql);
    $search_term = "%" . $search_pasajero . "%";
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $pasajeros[] = $row;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datos de Pasajeros</title>
    <link rel="stylesheet" href="../css/datos_pasajeros.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#search_contrato').on('keyup', function () {
                var searchText = $(this).val().toLowerCase();
                $('#contrato_id option').each(function () {
                    var contratoText = $(this).text().toLowerCase();
                    var match = contratoText.indexOf(searchText) > -1;
                    $(this).toggle(match);
                });
            });
        });
    </script>
</head>
<body>
    <div class="header">
        <span>Usuario: <?php echo isset($_SESSION['usuario']['nombre_usuario']) ? htmlspecialchars($_SESSION['usuario']['nombre_usuario']) : 'Invitado'; ?></span>
        <a href="inicio.php">Inicio</a>
        <a href="logout.php">Cerrar Sesión</a>
    </div>

    <div class="container">
        <h2>Agregar Pasajero y Asignarlo a un Contrato</h2>
        <?php if (!empty($message)): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>

        <form method="POST" action="datos_pasajeros.php">
            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" required>

            <label for="apellido">Apellido:</label>
            <input type="text" id="apellido" name="apellido" required>

            <label for="dni">DNI:</label>
            <input type="text" id="dni" name="dni" required>

            <label for="credencial_pago">Credencial de Pago (opcional):</label>
            <input type="text" id="credencial_pago" name="credencial_pago">

            <label for="search_contrato">Buscar Contrato:</label>
            <input type="text" id="search_contrato" name="search_contrato" placeholder="Buscar contrato...">

            <select id="contrato_id" name="contrato_id" required>
                <option value="">Seleccione un contrato</option>
                <?php foreach ($contratos as $contrato): ?>
                    <option value="<?php echo $contrato['id']; ?>"><?php echo $contrato['numero_referencia'] . ' - ' . $contrato['nombre_contrato']; ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Agregar Pasajero</button>
        </form>

        <h2>Buscar y Eliminar Pasajero de Contrato</h2>
        <form method="GET" action="datos_pasajeros.php">
            <label for="search_pasajero">Buscar Pasajero (DNI, Nombre o Apellido):</label>
            <input type="text" id="search_pasajero" name="search_pasajero" placeholder="Buscar pasajero...">
            <button type="submit">Buscar</button>
        </form>

        <?php if (!empty($pasajeros)): ?>
            <h3>Resultados de Búsqueda:</h3>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>DNI</th>
                    <th>Contrato</th>
                    <th>Acciones</th>
                </tr>
                <?php foreach ($pasajeros as $pasajero): ?>
                    <tr>
                        <td><?php echo $pasajero['id']; ?></td>
                        <td><?php echo htmlspecialchars($pasajero['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($pasajero['apellido']); ?></td>
                        <td><?php echo htmlspecialchars($pasajero['dni']); ?></td>
                        <td><?php echo $pasajero['numero_referencia'] . ' - ' . $pasajero['nombre_contrato']; ?></td>
                        <td>
                            <form method="POST" action="datos_pasajeros.php">
                                <input type="hidden" name="delete_pasajero_id" value="<?php echo $pasajero['id']; ?>">
                                <input type="hidden" name="delete_contrato_id" value="<?php echo $pasajero['contrato_id']; ?>">
                                <button type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search_pasajero'])): ?>
            <p>No se encontraron resultados.</p>
        <?php endif; ?>
    </div>
</body>
</html>
