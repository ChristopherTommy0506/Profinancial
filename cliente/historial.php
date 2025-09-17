<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: ../login.php");
    exit();
}

// Obtener datos del usuario
$usuario = $_SESSION['usuario'];

// Conexión a la base de datos
$conexion = new mysqli("localhost", "root", "", "profinancial_crm");
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

// Consulta para obtener historial de cambios con nombre de usuario
$sql = "SELECT a.accion, a.modulo, a.detalle, a.ip, a.fecha, u.nombre AS usuario
        FROM auditoria a
        INNER JOIN usuarios u ON a.usuario_id = u.id
        ORDER BY a.fecha DESC";
$resultado = $conexion->query($sql);

// Helper para escapar HTML
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Historial de Cambios | ProFinancial</title>
  <link rel="icon" href="../multimedia/Logo PROFINANCIAL.png" type="image/png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; }
    .sidebar-item.active { background-color: #eef2ff; color: #4f46e5; border-left: 3px solid #4f46e5; }
  </style>
</head>
<body class="bg-gray-50 min-h-screen flex relative">

  <!-- Sidebar / Dashboard -->
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
  <main class="flex-1 p-8">
    <div class="bg-white rounded-lg shadow-md p-6">
      <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
        <i class="fas fa-history text-blue-600 mr-3"></i>
        Historial de Cambios
      </h2>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left text-gray-700 border-collapse rounded-lg overflow-hidden shadow-sm">
          <thead class="bg-gray-200">
            <tr>
              <th class="px-3 py-2">Fecha</th>
              <th class="px-3 py-2">Usuario</th>
              <th class="px-3 py-2">Módulo</th>
              <th class="px-3 py-2">Acción</th>
              <th class="px-3 py-2">Detalles</th>
              <th class="px-3 py-2">IP</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php if ($resultado && $resultado->num_rows > 0): ?>
              <?php while ($fila = $resultado->fetch_assoc()): ?>
                <?php 
                  $detalle = !empty($fila['detalle']) ? json_decode($fila['detalle'], true) : [];
                  $campo = $detalle['campo'] ?? $detalle['campo_afectado'] ?? '';
                  $valorAnterior = $detalle['valor_anterior'] ?? '';
                  $valorNuevo = $detalle['valor_nuevo'] ?? '';
                ?>
                <tr class="hover:bg-gray-50">
                  <td class="px-3 py-2"><?= date('d/m/Y H:i', strtotime($fila['fecha'])) ?></td>
                  <td class="px-3 py-2"><?= h($fila['usuario']) ?></td>
                  <td class="px-3 py-2"><?= h($fila['modulo']) ?></td>
                  <td class="px-3 py-2"><?= h($fila['accion']) ?></td>
                  <td class="px-3 py-2">
                    <?php if (!empty($campo)): ?>
                      <div><strong><?= h($campo) ?>:</strong></div>
                      <div>
                        <span class="text-red-600 line-through"><?= h($valorAnterior) ?></span>
                        →
                        <span class="text-green-600 font-semibold"><?= h($valorNuevo) ?></span>
                      </div>
                    <?php elseif (!empty($fila['detalle'])): ?>
                      <span class="text-gray-500"><?= h(substr($fila['detalle'], 0, 100)) ?>...</span>
                    <?php else: ?>
                      <span class="text-gray-400 italic">Sin detalles específicos</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-3 py-2"><?= h($fila['ip']) ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="px-4 py-6 text-center text-gray-500 italic">
                  No hay registros en el historial
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <!-- Script para marcar item activo -->
  <script>
    const currentPage = window.location.pathname.split("/").pop();
    document.querySelectorAll(".sidebar-item").forEach(item => {
      const linkPage = item.getAttribute("href").split("/").pop();
      if (linkPage === currentPage) {
        item.classList.add("active");
      } else {
        item.classList.remove("active");
      }
    });
  </script>

</body>
</html>
