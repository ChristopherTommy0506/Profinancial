<?php
// logout.php
session_start();
session_unset();   // Limpia variables de sesión
session_destroy(); // Destruye la sesión actual

// Redirige al login
header("Location: ../login/login.html");
exit();
