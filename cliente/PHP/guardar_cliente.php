<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "profinancial_crm"; // cámbialo según corresponda

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

    // Verificar duplicado por NIT
    $check = $conn->query("SELECT id FROM clientes WHERE nit = '$nit'");
    if ($check->num_rows > 0) {
        echo "<script>
            alert('Error: Este cliente ya existe con ese NIT.');
            window.location.href = '../index.php';
        </script>";
        exit;
    }

    $sql = "INSERT INTO clientes (nombre, nit, nrc, contacto, telefono, email, clave_hacienda, clave_planilla, contador, direccion)
            VALUES ('$nombre', '$nit', '$nrc', '$contacto', '$telefono', '$email', '$clave_hacienda', '$clave_planilla', '$contador', '$direccion')";

    if ($conn->query($sql) === TRUE) {
        echo "<script>
            alert('Cliente guardado exitosamente');
            window.location.href = '../index.php';
        </script>";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
} else {
    echo "Acceso no permitido.";
}

$conn->close();
?>
