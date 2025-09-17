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

// Extraer el ID del usuario desde el array
$user_id = isset($usuario['id']) ? $usuario['id'] : 0;

// Convertir a entero para seguridad
$user_id = intval($user_id);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil del Contador | ProFinancial</title>
    <link rel="icon" href="../multimedia/Logo PROFINANCIAL.png" type="Logo PF">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .sidebar-item.active {
            background-color: #eef2ff;
            color: #4f46e5;
            border-left: 3px solid #4f46e5;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex relative">
    <!-- Sidebar -->
    <div class="hidden md:flex flex-col w-64 bg-white border-r">
        <div class="flex items-center h-16 px-4 bg-indigo-600">
            <div class="flex items-center">
                <img src="../multimedia/Logo PROFINANCIAL.png" alt="Logo Profinancial" class="h-8 w-8 rounded-full bg-white p-1">
                <span class="ml-2 text-white font-bold">Profinancial</span>
            </div>
        </div>
    <nav class="flex-1 p-4">
      <div class="space-y-2">
        <a href="../cliente/index.php" class="sidebar-item flex items-center p-3 rounded-lg">
          <i class="fas fa-users mr-3 text-gray-500"></i> Clientes
        </a>
        <a href="../cliente/consolidado.php" class="sidebar-item flex items-center p-3 rounded-lg">
          <i class="fas fa-file-alt mr-3 text-gray-500"></i> Consolidado
        </a>
        <a href="../cliente/historial.php" class="sidebar-item flex items-center p-3 rounded-lg">
          <i class="fas fa-history mr-3 text-gray-500"></i> Historial
        </a>
        <a href="../cliente/perfil_contador.php" class="sidebar-item flex items-center p-3 rounded-lg">
          <i class="fas fa-user-circle mr-3 text-gray-500"></i> Mi Perfil
        </a>
      </div>
    </nav>
    </div>

    <!-- Contenido Principal -->
    <div class="flex-1 flex justify-center items-start p-8 relative z-10">
        <div class="w-full max-w-4xl">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="bg-indigo-600 p-6 text-white">
                    <h2 class="text-2xl font-bold">Mi Perfil</h2>
                    <p class="text-indigo-200">Información personal del contador</p>
                </div>
                
                <div class="p-6">
                    <div class="flex flex-col md:flex-row gap-6">
                        <div class="md:w-1/3 flex flex-col items-center">
                            <img src="../multimedia/Logo PROFINANCIAL.png" alt="Foto de perfil" class="h-32 w-32 rounded-full border-4 border-white shadow mb-4">
                            <button class="text-indigo-600 text-sm font-medium hover:text-indigo-800">
                                Cambiar foto
                            </button>
                        </div>
                        
                        <div class="md:w-2/3">
                            <?php
                            // Mostrar mensajes de éxito o error si existen
                            if (isset($_SESSION['mensaje'])) {
                                echo '<div class="bg-green-100 p-4 rounded-lg text-green-700 mb-4">' . $_SESSION['mensaje'] . '</div>';
                                unset($_SESSION['mensaje']);
                            }
                            
                            if (isset($_SESSION['error'])) {
                                echo '<div class="bg-red-100 p-4 rounded-lg text-red-700 mb-4">' . $_SESSION['error'] . '</div>';
                                unset($_SESSION['error']);
                            }
                            
                            // Mostrar información directamente desde la sesión
                            echo '
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-1">Nombre Completo</label>
                                    <div class="bg-gray-50 p-3 rounded-lg">' . htmlspecialchars($usuario['nombre']) . '</div>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-1">ID de Usuario</label>
                                    <div class="bg-gray-50 p-3 rounded-lg">' . $usuario['id'] . '</div>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-1">Correo Electrónico</label>
                                    <div class="bg-gray-50 p-3 rounded-lg">' . htmlspecialchars($usuario['email']) . '</div>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-1">Estado</label>
                                    <div class="bg-gray-50 p-3 rounded-lg">Activo</div>
                                </div>
                            </div>';
                            ?>
                            
                            <hr class="my-5">
                            
                            <h3 class="text-lg font-semibold mb-4">Actualizar Información</h3>
                            <form action="PHP/actualizar_perfil.php" method="POST">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="nombre">Nombre</label>
                                        <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" class="w-full p-2 border rounded-lg">
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="email">Email</label>
                                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" class="w-full p-2 border rounded-lg">
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-1" for="password">Nueva Contraseña (dejar en blanco para no cambiar)</label>
                                    <input type="password" id="password" name="password" class="w-full p-2 border rounded-lg">
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">Guardar Cambios</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Script para manejar el onclick -->
    <script>
        // Obtener el nombre del archivo actual
        const currentPage = window.location.pathname.split("/").pop(); // ejemplo: perfil_contador.php

        // Recorrer todos los items del sidebar
        document.querySelectorAll(".sidebar-item").forEach(item => {
            const linkPage = item.getAttribute("href").split("/").pop(); // solo el nombre del archivo
            if (linkPage === currentPage) {
                item.classList.add("active"); // marcar el que coincide
            } else {
                item.classList.remove("active"); // quitar a los demás
            }
        });
    </script>
</body>
</html>