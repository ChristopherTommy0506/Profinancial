<?php
// procesar_registro.php
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

// Verificar que se haya enviado el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre   = trim($_POST["nombre"]);
    $email    = trim($_POST["email"]);
    $password = $_POST["password"];

    // Validar campos vacíos
    if (empty($nombre) || empty($email) || empty($password)) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <script>
                Swal.fire({
                  icon: 'warning',
                  title: 'Campos incompletos',
                  text: 'Por favor completa todos los campos.',
                  confirmButtonColor: '#3085d6'
                }).then(() => {
                  window.history.back();
                });
              </script>";
        exit();
    }

    // Validar correo existente
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
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
        title: 'Correo en uso',
        text: 'El correo ya está registrado. Usa otro o inicia sesión.',
        confirmButtonColor: '#d33'
         }).then(() => {
            window.location.href = '../login/login.html';
        });
        </script>
        </body>
        </html>";
        exit();
    }

    // Encriptar contraseña
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insertar usuario nuevo
    $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password_hash) VALUES (?, ?, ?)");
    $stmt->execute([$nombre, $email, $passwordHash]);

    $_SESSION["usuario"] = [
        "id"     => $pdo->lastInsertId(),
        "nombre" => $nombre,
        "email"  => $email
    ];

    // Alerta de éxito y redirección
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
            title: 'Registro exitoso',
            text: 'Bienvenido a ProFinancial',
            confirmButtonColor: '#28a745'
        }).then(() => {
            window.location.href = '../index.php';
        });
        </script>
        </body>
        </html>";
    exit();
} else {
    header("Location: singin.html");
    exit();
}
?>
