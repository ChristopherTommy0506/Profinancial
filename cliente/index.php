<?php
/* cliente/index.php  —  Listado SSR conectado a MySQL (sin API)
   Requiere: PHP 7.4+ y BD 'profinancial_crm'
   AJUSTA credenciales abajo
*/
session_start();

//  Verificar sesión activa
if (!isset($_SESSION["usuario"])) {
    header("Location: login/login.html");
    exit();
}

$DB_HOST='localhost';
$DB_NAME='profinancial_crm';
$DB_USER='root';
$DB_PASS=''; // <--- AJUSTA

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Configuración de paginación
$rowsPerPage = 10; // Número de filas por página
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $rowsPerPage;

try {
  $pdo = new PDO(
    "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
    $DB_USER, $DB_PASS,
    [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]
  );

  // Consulta para obtener el total de registros
  /*
  LEFT JOIN presentaciones p ON p.periodo_id=per.id
    LEFT JOIN tipos_formulario tf ON tf.id=p.tipo_formulario_id
  */
  $countSql = "
    SELECT COUNT(DISTINCT c.id, per.anio, per.mes) as total
    FROM clientes c
    LEFT JOIN periodos per ON per.cliente_id=c.id
    WHERE per.anio IS NOT NULL AND per.mes IS NOT NULL
  ";
  $totalResult = $pdo->query($countSql)->fetch(PDO::FETCH_ASSOC);
  $totalRows = $totalResult['total'];
  $totalPages = ceil($totalRows / $rowsPerPage);

  /*
   * Estados:
   *  - IVA:      pagado -> 'pagada'; presentado -> 'presentada'; si no -> 'pendiente'
   *  - PA/RENTA: (presentado OR pagado) -> 'realizado'; si no -> 'pendiente'
   *  - PLANILLA: pagado -> 'pagada'; si no -> 'pendiente'
   *  - CONTAB:   (presentado OR pagado) -> 'realizado'; si no -> 'pendiente'
   * Mapea columna "PA" del front a código 'RENTA' de la BD.
   */
  
  // Consulta principal con paginación - AGREGADAS LAS NUEVAS COLUMNAS
  $sql = "
    SELECT
      c.id   AS cliente_id,
      c.nombre AS cliente_nombre,
      c.nit, c.contacto, c.telefono, c.email,
      c.clave_hacienda,
      c.clave_planilla,
      c.contador,
      c.direccion,
      c.nrc,
      per.anio, per.mes,

      -- Determina si es cliente nuevo (sin periodos)
      CASE WHEN per.id IS NULL THEN 1 ELSE 0 END AS cliente_nuevo,

      MAX(CASE WHEN tf.codigo='IVA'
           THEN CASE WHEN p.pagado=1 THEN 'pagada'
                     WHEN p.presentado=1 THEN 'presentada'
                     ELSE 'pendiente' END END) AS iva,

      MAX(CASE WHEN tf.codigo IN ('RENTA','PA')
           THEN CASE WHEN (p.presentado=1 OR p.pagado=1) THEN 'realizado'
                     ELSE 'pendiente' END END) AS pa,

      MAX(CASE WHEN tf.codigo='PLANILLA'
           THEN CASE WHEN p.pagado=1 THEN 'pagada'
                     ELSE 'pendiente' END END) AS planilla,

      MAX(CASE WHEN tf.codigo IN ('CONTAB','CONTABILIDAD')
           THEN CASE WHEN (p.presentado=1 OR p.pagado=1) THEN 'realizado'
                     ELSE 'pendiente' END END) AS conta,

      -- NUEVAS COLUMNAS AGREGADAS
      c.declaracion_iva,
      c.declaracion_pa, 
      c.declaracion_planilla,
      c.declaracion_contabilidad

    FROM clientes c
    LEFT JOIN periodos per        ON per.cliente_id=c.id
    LEFT JOIN presentaciones p    ON p.periodo_id=per.id
    LEFT JOIN tipos_formulario tf ON tf.id=p.tipo_formulario_id

    WHERE per.anio IS NOT NULL AND per.mes IS NOT NULL
    GROUP BY
      c.id, c.nombre, c.nit, c.contacto, c.telefono, c.email,
      c.clave_hacienda, c.clave_planilla, c.contador, c.direccion, c.nrc,
      per.anio, per.mes
    ORDER BY per.anio DESC, per.mes DESC, c.nombre ASC
    LIMIT $offset, $rowsPerPage
  ";
  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
  http_response_code(500);
  echo '<!doctype html><meta charset="utf-8"><pre style="padding:16px;color:#b91c1c">';
  echo "Error conectando a la BD: ".h($e->getMessage());
  echo '</pre>'; exit;
}

function fechaPeriodo($anio,$mes){
  if (!$anio || !$mes) return '';
  return sprintf('%04d-%02d-01',(int)$anio,(int)$mes);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profinancial | Sistema de Gestión</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-item.active { background-color: #eef2ff; color: #4f46e5; border-left: 3px solid #4f46e5; }
        .search-box:focus { box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); }
        select:disabled { opacity: 0.6; cursor: not-allowed; }
        .pagination-link { 
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #d1d5db;
            padding: 0.5rem 0.75rem;
            margin: 0 -1px;
            text-decoration: none;
            color: #6b7280;
        }
        .pagination-link:hover { 
            background-color: #f9fafb;
        }
        .pagination-link.active { 
            background-color: #eef2ff; 
            border-color: #4f46e5; 
            color: #4f46e5; 
            z-index: 10;
        }
        .pagination-link.disabled { 
            opacity: 0.5; 
            cursor: not-allowed; 
            background-color: #f3f4f6;
        }
        .estado-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
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

        <!-- Contenido principal  -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Barra superior -->
            <header class="bg-white shadow-sm">
                <div class="px-6 py-4 flex items-center justify-between">
                    <button class="md:hidden text-gray-500">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-xl font-semibold text-gray-800" id="page-title">Clientes</h1>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <input id="filtroTexto" type="text" placeholder="Filtrar por nombre" class="search-box pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:border-indigo-500">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                        <div class="flex items-center space-x-4">
                          <div class="flex items-center">
                              <img src="https://placehold.co/40x40" alt="Foto perfil" class="h-8 w-8 rounded-full mr-2">
                              <span class="text-sm font-medium">
                                  <?php echo isset($_SESSION["usuario"]["nombre"]) ? h($_SESSION["usuario"]["nombre"]) : "Usuario"; ?>
                              </span>
                          </div>
                          <a href="PHP/logout.php" 
                            class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 transition text-sm">
                            <i class="fas fa-sign-out-alt mr-1"></i> Cerrar sesión
                          </a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6">
                <!-- Toolbar -->
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Listado de Clientes</h2>

                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Apartado -->
                        <label class="text-sm font-medium text-gray-700">Apartado</label>
                        <select id="selectApartado" class="border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="iva">Declaración IVA</option>
                            <option value="pa">Declaración PA</option>
                            <option value="planilla">Planilla</option>
                            <option value="conta">Contabilidad</option>
                        </select>

                        <!-- Estado (según apartado) -->
                        <label class="text-sm font-medium text-gray-700">Estado</label>
                        <select id="selectEstado" class="border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></select>

                        <!-- Periodo -->
                        <span class="h-6 w-px bg-gray-200 mx-1"></span>
                        <label class="text-sm font-medium text-gray-700">Año</label>
                        <select id="selectAnio" class="border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">Todos</option>
                        </select>

                        <label class="text-sm font-medium text-gray-700">Mes</label>
                        <select id="selectMes" class="border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" disabled>
                            <option value="">Todos</option>
                        </select>

                        <label class="text-sm font-medium text-gray-700">Día</label>
                        <select id="selectDia" class="border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" disabled>
                            <option value="">Todos</option>
                        </select>

                        <!-- Modo: filtrar - CORREGIDO: desactivado por defecto -->
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input id="chkFiltrar" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            Filtrar solo coincidentes
                        </label>
                    </div>

                    <a href="../cliente/nuevo_cliente.html">
                        <button class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
                            <i class="fas fa-plus mr-2"></i> Nuevo Cliente
                        </button>
                    </a>
                </div>
                
                <!-- Tabla -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table id="tablaClientes" class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">NIT</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Periodo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Clave Hacienda</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Clave Planilla</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contacto</th>

                                    <!-- NUEVAS COLUMNAS -->
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Declaración IVA</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Declaración PA</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Planilla</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contabilidad</th>
                                    
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contador</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($rows)): ?>
                                  <?php foreach ($rows as $r): 
                                    if (!$r['anio'] || !$r['mes']) continue;
                                    $fecha = fechaPeriodo($r['anio'],$r['mes']);
                                    // Usamos las nuevas columnas en lugar de las calculadas
                                    $iva = strtolower($r['declaracion_iva'] ?? $r['iva'] ?? 'pendiente');
                                    $pa  = strtolower($r['declaracion_pa'] ?? $r['pa'] ?? 'pendiente');
                                    $pla = strtolower($r['declaracion_planilla'] ?? $r['planilla'] ?? 'pendiente');
                                    $con = strtolower($r['declaracion_contabilidad'] ?? $r['conta'] ?? 'pendiente');
                                    
                                    // Formatear periodo para mostrar
                                    $meses = [
                                        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                                        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                                        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                                    ];
                                    $periodo_texto = $r['anio'] && $r['mes'] ? 
                                        $meses[$r['mes']] . ' ' . $r['anio'] : 'Sin periodo';
                                  ?>
                                  <tr
                                    data-fecha="<?=h($fecha)?>"
                                    data-iva="<?=h($iva)?>"
                                    data-pa="<?=h($pa)?>"
                                    data-planilla="<?=h($pla)?>"
                                    data-conta="<?=h($con)?>"
                                    data-cliente-id="<?=h($r['cliente_id'])?>"
                                    data-nombre="<?=h($r['cliente_nombre'])?>"
                                    data-nit="<?=h($r['nit'])?>"
                                    data-nrc="<?=h($r['nrc'])?>"
                                    data-contacto="<?=h($r['contacto'])?>"
                                    data-telefono="<?=h($r['telefono'])?>"
                                    data-email="<?=h($r['email'])?>"
                                    data-clave-hacienda="<?=h($r['clave_hacienda'])?>"
                                    data-clave-planilla="<?=h($r['clave_planilla'])?>"
                                    data-contador="<?=h($r['contador'])?>"
                                    data-direccion="<?=h($r['direccion'])?>"
                                    data-anio="<?=h($r['anio'])?>"
                                    data-mes="<?=h($r['mes'])?>"
                                  >
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?=h($r['cliente_nombre'])?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?=h($r['nit'])?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?=h($periodo_texto)?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?=h($r['clave_hacienda'] ?? '-')?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?=h($r['clave_planilla'] ?? '-')?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?=h($r['contacto'])?></td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm"><span class="estado-badge" data-col="iva"></span></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm"><span class="estado-badge" data-col="pa"></span></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm"><span class="estado-badge" data-col="planilla"></span></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm"><span class="estado-badge" data-col="conta"></span></td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?=h($r['contador'] ?? '-')?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button onclick="openModal(this)" class="text-green-500 hover:text-green-700 mr-3" title="Ver detalles"><i class="fas fa-eye"></i></button>
                                        <button class="text-indigo-600 hover:text-indigo-900 mr-3" title="Editar"><i class="fas fa-edit"></i></button>
                                        <button onclick="document.getElementById('deleteModal').classList.remove('hidden')" class="text-red-600 hover:text-red-900" title="Eliminar"><i class="fas fa-trash"></i></button>
                                    </td>
                                  </tr>
                                  <?php endforeach; ?>
                                <?php else: ?>
                                  <tr><td colspan="12" class="px-6 py-4 text-sm text-gray-500">No hay datos para mostrar.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- PAGINACIÓN SIMPLIFICADA Y FUNCIONAL -->
                    <?php if ($totalPages > 1): ?>
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200">
                        <div class="flex-1 flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Mostrando <span class="font-medium"><?=($offset + 1)?></span> a <span class="font-medium"><?=min($offset + $rowsPerPage, $totalRows)?></span> de <span class="font-medium"><?=$totalRows?></span> resultados
                                </p>
                            </div>
                            <div>
                                <nav class="inline-flex rounded-md shadow-sm">
                                    <!-- Botón Anterior -->
                                    <?php if ($currentPage > 1): ?>
                                        <a href="?page=<?=$currentPage - 1?>" class="pagination-link relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <span class="sr-only">Anterior</span>
                                            <i class="fas fa-chevron-left w-4 h-4"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="pagination-link disabled relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500">
                                            <span class="sr-only">Anterior</span>
                                            <i class="fas fa-chevron-left w-4 h-4"></i>
                                        </span>
                                    <?php endif; ?>

                                    <!-- Números de página -->
                                    <?php
                                    // Mostrar máximo 5 páginas alrededor de la actual
                                    $startPage = max(1, $currentPage - 2);
                                    $endPage = min($totalPages, $startPage + 4);
                                    
                                    // Ajustar si estamos cerca del final
                                    if ($endPage - $startPage < 4) {
                                        $startPage = max(1, $endPage - 4);
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                                        <a href="?page=<?=$i?>" class="pagination-link relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?=($i == $currentPage) ? 'active text-indigo-600' : 'text-gray-500 hover:bg-gray-50'?>">
                                            <?=$i?>
                                        </a>
                                    <?php endfor; ?>

                                    <!-- Botón Siguiente -->
                                    <?php if ($currentPage < $totalPages): ?>
                                        <a href="?page=<?=$currentPage + 1?>" class="pagination-link relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <span class="sr-only">Siguiente</span>
                                            <i class="fas fa-chevron-right w-4 h-4"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="pagination-link disabled relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500">
                                            <span class="sr-only">Siguiente</span>
                                            <i class="fas fa-chevron-right w-4 h-4"></i>
                                        </span>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Detalle (versión en columnas) -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-lg w-full max-w-3xl p-6 relative">
        <button onclick="closeModal()" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600" aria-label="Cerrar">
          <i class="fas fa-times"></i>
        </button>

        <h2 class="text-xl font-bold mb-5 text-gray-800">Detalles del Cliente</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-10 gap-y-3 text-sm">
          <div><span class="block text-gray-500 font-semibold">Nombre:</span><span id="modal-nombre" class="block text-gray-900">—</span></div>
          <div><span class="block text-gray-500 font-semibold">NIT:</span><span id="modal-nit" class="block text-gray-900">—</span></div>
          <div><span class="block text-gray-500 font-semibold">NRC:</span><span id="modal-nrc" class="block text-gray-900">—</span></div>
          <div><span class="block text-gray-500 font-semibold">Periodo:</span><span id="modal-periodo" class="block text-gray-900">—</span></div>
          <div><span class="block text-gray-500 font-semibold">Clave Hacienda:</span><span id="modal-clave-hacienda" class="block text-gray-900">—</span></div>
          <div><span class="block text-gray-500 font-semibold">Clave Planilla:</span><span id="modal-clave-planilla" class="block text-gray-900">—</span></div>
          <div><span class="block text-gray-500 font-semibold">Contacto:</span><span id="modal-contacto" class="block text-gray-900">—</span></div>
          <div><span class="block text-gray-500 font-semibold">Teléfono:</span><span id="modal-telefono" class="block text-gray-900">—</span></div>
          <div><span class="block text-gray-500 font-semibold">Correo:</span><span id="modal-email" class="block text-gray-900">—</span></div>
          <div><span class="block text-gray-500 font-semibold">Contador:</span><span id="modal-contador" class="block text-gray-900">—</span></div>
          <div><span class="block text-gray-500 font-semibold">Dirección:</span><span id="modal-direccion" class="block text-gray-900">—</span></div>
        </div>

        <div class="mt-5">
          <a id="modal-perfil-link" href="#">
            <button class="text-white bg-indigo-600 hover:bg-indigo-700 px-4 py-2 rounded-lg">Perfil</button>
          </a>
        </div>
      </div>
    </div>

    <!-- Modal Eliminar -->
    <div id="deleteModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-lg w-80">
            <h2 class="text-lg font-semibold mb-4">Confirmar eliminación</h2>
            <p class="text-gray-600 mb-6">¿Está seguro de que desea eliminar este cliente?</p>
            <div class="flex justify-end space-x-3">
                <button onclick="document.getElementById('deleteModal').classList.add('hidden')" 
                        class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
                    Cancelar
                </button>
                <button onclick="document.getElementById('deleteModal').classList.add('hidden')" 
                        class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                    Eliminar
                </button>
            </div>
        </div>
    </div>

    <script>
    // Helpers modal
    function openModal(button) {
        const row = button.closest('tr');
        
        // Obtener datos del cliente desde los data attributes
        document.getElementById('modal-nombre').textContent = row.getAttribute('data-nombre') || '—';
        document.getElementById('modal-nit').textContent = row.getAttribute('data-nit') || '—';
        document.getElementById('modal-nrc').textContent = row.getAttribute('data-nrc') || '—';
        document.getElementById('modal-contacto').textContent = row.getAttribute('data-contacto') || '—';
        document.getElementById('modal-telefono').textContent = row.getAttribute('data-telefono') || '—';
        document.getElementById('modal-email').textContent = row.getAttribute('data-email') || '—';
        document.getElementById('modal-clave-hacienda').textContent = row.getAttribute('data-clave-hacienda') || '—';
        document.getElementById('modal-clave-planilla').textContent = row.getAttribute('data-clave-planilla') || '—';
        document.getElementById('modal-contador').textContent = row.getAttribute('data-contador') || '—';
        document.getElementById('modal-direccion').textContent = row.getAttribute('data-direccion') || '—';
        
        // Formatear periodo
        const anio = row.getAttribute('data-anio');
        const mes = row.getAttribute('data-mes');
        const meses = [
            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
        ];
        const periodoTexto = anio && mes ? `${meses[parseInt(mes) - 1]} ${anio}` : 'Sin periodo';
        document.getElementById('modal-periodo').textContent = periodoTexto;
        
        // Actualizar enlace al perfil
        const clienteId = row.getAttribute('data-cliente-id');
        document.getElementById('modal-perfil-link').href = `perfil_cliente.php?id=${clienteId}`;
        
        // Mostrar modal
        document.getElementById('modal').classList.remove('hidden');
        document.getElementById('modal').classList.add('flex');
    }
    
    function closeModal() { 
        document.getElementById('modal').classList.add('hidden'); 
        document.getElementById('modal').classList.remove('flex'); 
    }

    // Cerrar modal al hacer clic fuera del contenido
    document.getElementById('modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Cerrar modal con tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
// ------ Filtro por Apartado/Estado + Periodo (Año/Mes/Día) ------
(function(){
  const tbody        = document.querySelector("#tablaClientes tbody");
  const $apartado    = document.getElementById("selectApartado");
  const $estado      = document.getElementById("selectEstado");
  const $chkFiltrar  = document.getElementById("chkFiltrar");
  const $filtroTexto = document.getElementById("filtroTexto");
  const $anio        = document.getElementById("selectAnio");
  const $mes         = document.getElementById("selectMes");
  const $dia         = document.getElementById("selectDia");

  // ESTADOS ACTUALIZADOS - Todos los estados para IVA, PA, PLANILLA, pero CONTABILIDAD sin "pagada"
  const ESTADOS = {
    iva:      ["documento pendiente", "pendiente de procesar", "en proceso", "presentada", "pagada"],
    pa:       ["documento pendiente", "pendiente de procesar", "en proceso", "presentada", "pagada"],
    planilla: ["documento pendiente", "pendiente de procesar", "en proceso", "presentada", "pagada"],
    conta:    ["pendiente de procesar", "en proceso", "presentada"] // Sin "pagada"
  };

  const MESES = [
    {v:1, n:"Enero"}, {v:2, n:"Febrero"}, {v:3, n:"Marzo"}, {v:4, n:"Abril"},
    {v:5, n:"Mayo"}, {v:6, n:"Junio"}, {v:7, n:"Julio"}, {v:8, n:"Agosto"},
    {v:9, n:"Septiembre"}, {v:10, n:"Octubre"}, {v:11, n:"Noviembre"}, {v:12, n:"Diciembre"}
  ];

  const badgeClasses = (val) => {
    const v = (val || "").toLowerCase();
    if (v === "documento pendiente" || v === "pendiente de procesar") return "bg-yellow-100 text-yellow-800";
    if (v === "en proceso") return "bg-indigo-100 text-indigo-800";
    if (v === "presentada") return "bg-blue-100 text-blue-800";
    if (v === "pagada") return "bg-green-100 text-green-800";
    return "bg-slate-100 text-slate-800";
  };

  const norm = (s='') => s.toString().trim().toLowerCase();
  
  // Función para capitalizar correctamente los estados
  const cap = (s='') => {
    if (!s) return s;
    s = s.toLowerCase();
    if (s === "documento pendiente") return "Documento pendiente";
    if (s === "pendiente de procesar") return "Pendiente de procesar";
    if (s === "en proceso") return "En proceso";
    if (s === "presentada") return "Presentada";
    if (s === "pagada") return "Pagada";
    return s.charAt(0).toUpperCase() + s.slice(1);
  };

  function renderBadges() {
    tbody.querySelectorAll("tr").forEach(row => {
      ["iva","pa","planilla","conta"].forEach(col => {
        const val = row.getAttribute(`data-${col}`) || "";
        const target = row.querySelector(`.estado-badge[data-col="${col}"]`);
        if (target) {
          target.className = `estado-badge inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${badgeClasses(val)}`;
          target.textContent = val ? cap(val) : "-";
          target.title = target.textContent;
        }
      });
    });
  }

  function fillEstados(apartado) {
    const opts = ESTADOS[apartado] || [];
    $estado.innerHTML = opts.map(e => `<option value=\"${e}\">${cap(e)}</option>`).join("");
  }

  function uniqueYears() {
    const years = new Set();
    tbody.querySelectorAll("tr").forEach(tr => {
      const f = tr.getAttribute("data-fecha");
      if (f && /^\d{4}-\d{2}-\d{2}$/.test(f)) years.add(parseInt(f.slice(0,4), 10));
    });
    return Array.from(years).sort((a,b)=>b-a);
  }

  function fillAnios() {
    const años = uniqueYears();
    $anio.innerHTML = `<option value=\"\">Todos</option>` + años.map(y=>`<option value=\"${y}\">${y}</option>`).join("");
  }

  function fillMeses(habilitar) {
    $mes.innerHTML = `<option value=\"\">Todos</option>` + MESES.map(m=>`<option value=\"${m.v}\">${m.n}</option>`).join("");
    $mes.disabled = !habilitar;
  }

  function daysInMonth(year, month) { return new Date(year, month, 0).getDate(); }

  function fillDias(habilitar, year, month) {
    $dia.innerHTML = `<option value=\"\">Todos</option>`;
    if (habilitar && year && month) {
      const max = daysInMonth(year, month);
      let opts = "";
      for (let d=1; d<=max; d++) opts += `<option value=\"${d}\">${d}</option>`;
      $dia.innerHTML = `<option value=\"\">Todos</option>` + opts;
    }
    $dia.disabled = !habilitar;
  }

  function applyFilter() {
    const apartado = $apartado.value;
    const estadoSel = norm($estado.value || "");
    const q = norm($filtroTexto.value || "");

    const ySel = $anio.value ? parseInt($anio.value, 10) : null;
    const mSel = $mes.value ? parseInt($mes.value, 10) : null;
    const dSel = $dia.value ? parseInt($dia.value, 10) : null;

    Array.from(tbody.querySelectorAll("tr")).forEach(row => {
      const matchesText = q ? row.innerText.toLowerCase().includes(q) : true;

      let matchesState = true;
      if ($chkFiltrar.checked && estadoSel) {
        const val = norm(row.getAttribute(`data-${apartado}`) || "");
        matchesState = (val === estadoSel);
      }

      let matchesPeriod = true;
      const f = row.getAttribute("data-fecha");
      if (f && /^\d{4}-\d{2}-\d{2}$/.test(f)) {
        const yr = parseInt(f.slice(0,4), 10);
        const mo = parseInt(f.slice(5,7), 10);
        const dy = parseInt(f.slice(8,10), 10);
        if (ySel !== null && yr !== ySel) matchesPeriod = false;
        if (matchesPeriod && mSel !== null && mo !== mSel) matchesPeriod = false;
        if (matchesPeriod && dSel !== null && dy !== dSel) matchesPeriod = false;
      } else {
        if (ySel !== null || mSel !== null || dSel !== null) matchesPeriod = false;
      }

      row.style.display = (matchesText && matchesState && matchesPeriod) ? "" : "none";
    });
  }

  $apartado.addEventListener("change", () => { fillEstados($apartado.value); applyFilter(); });
  $estado.addEventListener("change", applyFilter);
  $chkFiltrar.addEventListener("change", applyFilter);
  $filtroTexto.addEventListener("input", applyFilter);

  $anio.addEventListener("change", () => {
    const ySel = $anio.value ? parseInt($anio.value, 10) : null;
    fillMeses(!!ySel);
    $mes.value = "";
    fillDias(false);
    applyFilter();
  });

  $mes.addEventListener("change", () => {
    const ySel = $anio.value ? parseInt($anio.value, 10) : null;
    const mSel = $mes.value ? parseInt($mes.value, 10) : null;
    fillDias(!!(ySel && mSel), ySel, mSel);
    $dia.value = "";
    applyFilter();
  });

  $dia.addEventListener("change", applyFilter);

  // Init
  fillEstados($apartado.value);
  renderBadges();
  fillAnios();
  fillMeses(false);
  fillDias(false);
})();
    </script>
    
    <!-- Script para resaltar la página activa en el menú -->
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