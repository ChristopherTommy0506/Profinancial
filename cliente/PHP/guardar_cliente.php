<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "profinancial_crm";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'] ?? '';
    $nit = $_POST['nit'] ?? '';
    $nrc = $_POST['nrc'] ?? '';
    $contacto = $_POST['contacto'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $email = $_POST['email'] ?? '';
    $clave_hacienda = $_POST['clave_hacienda'] ?? '';
    $clave_planilla = $_POST['clave_planilla'] ?? '';
    $contador = $_POST['contador'] ?? '';
    $direccion = $_POST['direccion'] ?? '';
    
    // Nuevos campos de estados
    $declaracion_iva = $_POST['declaracion_iva'] ?? 'documento pendiente';
    $declaracion_pa = $_POST['declaracion_pa'] ?? 'documento pendiente';
    $declaracion_planilla = $_POST['declaracion_planilla'] ?? 'documento pendiente';
    $declaracion_contabilidad = $_POST['declaracion_contabilidad'] ?? 'documento pendiente';

    // Verificar duplicado por NIT
    $check = $conn->query("SELECT id FROM clientes WHERE nit = '$nit'");
    if ($check->num_rows > 0) {
        echo "<script>
            alert('Error: Este cliente ya existe con ese NIT.');
            window.location.href = '../index.php';
        </script>";
        exit;
    }

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // Insertar el cliente con los nuevos campos
        $sql = "INSERT INTO clientes 
                (nombre, nit, nrc, contacto, telefono, email, 
                 clave_hacienda, clave_planilla, contador, direccion,
                 declaracion_iva, declaracion_pa, declaracion_planilla, declaracion_contabilidad)
                VALUES 
                ('$nombre', '$nit', '$nrc', '$contacto', '$telefono', '$email', 
                 '$clave_hacienda', '$clave_planilla', '$contador', '$direccion',
                 '$declaracion_iva', '$declaracion_pa', '$declaracion_planilla', '$declaracion_contabilidad')";

        if ($conn->query($sql) === TRUE) {
            // Obtener el ID del cliente recién insertado
            $cliente_id = $conn->insert_id;
            
            // Obtener mes y año actual
            $anio_actual = date('Y');
            $mes_actual = date('n'); // 1-12
            
            // Insertar el periodo para este cliente
            $sql_periodo = "INSERT INTO periodos (cliente_id, anio, mes) 
                           VALUES ('$cliente_id', '$anio_actual', '$mes_actual')";
            
            if ($conn->query($sql_periodo) === TRUE) {
                // Confirmar la transacción
                $conn->commit();
                
                echo "<script>
                    alert('Cliente guardado exitosamente con el periodo actual');
                    window.location.href = '../index.php';
                </script>";
            } else {
                throw new Exception("Error al crear periodo: " . $conn->error);
            }
        } else {
            throw new Exception("Error al guardar cliente: " . $conn->error);
        }
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conn->rollback();
        echo "<script>
            alert('Error: " . addslashes($e->getMessage()) . "');
            window.location.href = '../agregar_cliente.php';
        </script>";
    }
} else {
    echo "Acceso no permitido.";
}

$conn->close();
?>