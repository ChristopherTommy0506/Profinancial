<?php
// procesar_login.php
session_start();

// Configuración de la base de datos
$host = "localhost";
$dbname = "profinancial_crm";
$user = "root";   // cámbialo según tu configuración
$pass = "";       // cámbialo si tienes contraseña

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST["email"]);
    $password = $_POST["password"];

    // Validar campos vacíos
    if (empty($email) || empty($password)) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <script>
                Swal.fire({
                  icon: 'warning',
                  title: 'Campos vacíos',
                  text: 'Por favor ingresa tu correo y contraseña.',
                  confirmButtonColor: '#3085d6'
                }).then(() => {
                  window.history.back();
                });
              </script>";
        exit();
    }

    // Buscar usuario en la BD
    $stmt = $pdo->prepare("SELECT id, nombre, email, password_hash FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($password, $usuario["password_hash"])) {
        // Iniciar sesión
        $_SESSION["usuario"] = [
            "id"     => $usuario["id"],
            "nombre" => $usuario["nombre"],
            "email"  => $usuario["email"]
        ];
        echo "<!DOCTYPE html>
        <html lang='es'>
        <head>
        <meta charset='UTF-8'>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
        <script>
        Swal.fire({
                  icon: 'success',
                  title: 'Bienvenido',
                  text: 'Acceso correcto. Redirigiendo...',
                  confirmButtonColor: '#28a745',
                  timer: 1500,
                  showConfirmButton: false
                }).then(() => {
                  window.location.href = '../index.php';
                });
        </script>
        </body>
        </html>";
        exit();
    } else {
        echo "<!DOCTYPE html>
        <html lang='es'>
        <head>
        <meta charset='UTF-8'>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
        <script>
                Swal.fire({
                  icon: 'error',
                  title: 'Acceso denegado',
                  text: 'Correo o contraseña incorrectos.',
                  confirmButtonColor: '#d33'
                }).then(() => {
                  window.history.back();
                });
        </script>
        </body>
        </html>";
        exit();
    }
} else {
    header("Location: login.html");
    exit();
}
