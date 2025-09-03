<?php
// Iniciar sesión - DEBE SER LO PRIMERO EN EL DOCUMENTO
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    // Redirigir al login si no hay sesión activa
    header("Location: ../login.php");
    exit();
}

// Obtener los datos del usuario de la sesión
$usuario = $_SESSION['usuario'];
$user_id = $usuario['id'];

// Conexión a la base de datos
$servername = "127.0.0.1:3306";
$username = "root";
$password = "";
$dbname = "profinancial_crm";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    $_SESSION['error'] = "Error de conexión: " . $conn->connect_error;
    header("Location: ../perfil_contador.php");
    exit();
}

// Recoger datos del formulario
$nombre = trim($_POST['nombre']);
$email = trim($_POST['email']);
$password = trim($_POST['password']);

// Validar datos
if (empty($nombre) || empty($email)) {
    $_SESSION['error'] = "Nombre y email son campos obligatorios.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "El formato del email no es válido.";
} else {
    // Construir la consulta de actualización
    if (!empty($password)) {
        // Si se proporcionó una nueva contraseña, hash y actualizar
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE usuarios SET nombre = '$nombre', email = '$email', password_hash = '$password_hash' WHERE id = $user_id";
    } else {
        // Si no se proporcionó contraseña, mantener la actual
        $sql = "UPDATE usuarios SET nombre = '$nombre', email = '$email' WHERE id = $user_id";
    }
    
    // Ejecutar la consulta
    if ($conn->query($sql)) {
        // Actualizar también los datos en la sesión
        $_SESSION['usuario']['nombre'] = $nombre;
        $_SESSION['usuario']['email'] = $email;
        
        $_SESSION['mensaje'] = "Perfil actualizado correctamente.";
    } else {
        $_SESSION['error'] = "Error al actualizar el perfil: " . $conn->error;
    }
}

$conn->close();

// Redirigir de vuelta al perfil
header("Location: ../perfil_contador.php");
exit();
?>