<?php
/* cliente/consolidado.php — Consolidado con modo edición tipo Excel (SSR + fetch)
   Ajusta credenciales:
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
function monthName($m){
  $n=[1=>"Enero",2=>"Febrero",3=>"Marzo",4=>"Abril",5=>"Mayo",6=>"Junio",7=>"Julio",8=>"Agosto",9=>"Septiembre",10=>"Octubre",11=>"Noviembre",12=>"Diciembre"];
  return $n[(int)$m]??"";
}

/* ---------- ENDPOINTS AJAX (fetch POST) ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  header('Content-Type: application/json; charset=utf-8');
  $action = $_POST['action'] ?? '';
  try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",$DB_USER,$DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

    if ($action==='update_cliente') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) throw new Exception("ID inválido.");

      // Campos permitidos
      $map = [
        'nombre'         => $_POST['nombre'] ?? null,
        'nit'            => $_POST['nit'] ?? null,
        'contacto'       => $_POST['contacto'] ?? null,
        'telefono'       => $_POST['telefono'] ?? null,
        'email'          => $_POST['email'] ?? null,
        'contador'       => $_POST['contador'] ?? null,
        'clave_hacienda' => $_POST['clave_hacienda'] ?? null,
        'clave_planilla' => $_POST['clave_planilla'] ?? null,
      ];
      $sets=[]; $vals=[];
      foreach ($map as $col=>$val){ if ($val!==null){ $sets[]="{$col}=?"; $vals[]=$val; } }
      if (!$sets) throw new Exception("Nada que actualizar.");
      $vals[]=$id;
      $sql="UPDATE clientes SET ".implode(",",$sets)." WHERE id=?";
      $pdo->prepare($sql)->execute($vals);
      echo json_encode(['ok'=>true,'msg'=>'Cliente actualizado']); exit;
    }

    if ($action==='update_estados') {
      $cliente_id = (int)($_POST['id'] ?? 0);
      if ($cliente_id<=0) throw new Exception("ID de cliente requerido.");

      // Estados recibidos (opcionales) - mapeados a las columnas correctas de la tabla clientes
      $estado = [
        'declaracion_iva' => $_POST['iva'] ?? null,
        'declaracion_pa' => $_POST['pa'] ?? null,
        'declaracion_planilla' => $_POST['planilla'] ?? null,
        'declaracion_contabilidad' => $_POST['conta'] ?? null,
      ];

      // Mapear los valores simples a los valores permitidos en la base de datos
      $mapEstado = function($tipo, $val) {
        if ($val === null) return null;
        
        $v = strtolower(trim($val));
        switch ($tipo) {
          case 'declaracion_iva':
            // Para IVA: documentos pendiente, pendiente de procesar, en proceso, presentada, pagada
            if ($v==='documentos pendiente') return 'documento pendiente';
            if ($v==='pendiente de procesar') return 'pendiente de procesar';
            if ($v==='en proceso') return 'en proceso';
            if ($v==='presentada') return 'presentada';
            if ($v==='pagada') return 'pagada';
            return 'documento pendiente';
          case 'declaracion_pa':
            // Para PA: documentos pendiente, pendiente de procesar, en proceso, presentada, pagada
            if ($v==='documentos pendiente') return 'documento pendiente';
            if ($v==='pendiente de procesar') return 'pendiente de procesar';
            if ($v==='en proceso') return 'en proceso';
            if ($v==='presentada') return 'presentada';
            if ($v==='pagada') return 'pagada';
            return 'documento pendiente';
          case 'declaracion_planilla':
            // Para Planilla: documentos pendiente, pendiente de procesar, en proceso, presentada, pagada
            if ($v==='documentos pendiente') return 'documento pendiente';
            if ($v==='pendiente de procesar') return 'pendiente de procesar';
            if ($v==='en proceso') return 'en proceso';
            if ($v==='presentada') return 'presentada';
            if ($v==='pagada') return 'pagada';
            return 'documento pendiente';
          case 'declaracion_contabilidad':
            // Para Contabilidad: documentos pendiente, pendiente de procesar, en proceso, presentada (sin pagada)
            if ($v==='documentos pendiente') return 'documento pendiente';
            if ($v==='pendiente de procesar') return 'pendiente de procesar';
            if ($v==='en proceso') return 'en proceso';
            if ($v==='presentada') return 'presentada';
            return 'documento pendiente';
        }
        return null;
      };

      $sets=[]; $vals=[];
      foreach ($estado as $col=>$val){
        if ($val!==null){
          $mappedVal = $mapEstado($col, $val);
          if ($mappedVal !== null) {
            $sets[]="{$col}=?";
            $vals[]=$mappedVal;
          }
        }
      }
      
      if (!$sets) throw new Exception("Nada que actualizar.");
      $vals[]=$cliente_id;
      $sql="UPDATE clientes SET ".implode(",",$sets)." WHERE id=?";
      $pdo->prepare($sql)->execute($vals);
      
      echo json_encode(['ok'=>true,'msg'=>'Estados actualizados']); exit;
    }

    // Nuevo endpoint para autoguardado
    if ($action==='autosave') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) throw new Exception("ID inválido.");

      $field = $_POST['field'] ?? '';
      $value = $_POST['value'] ?? '';
      
      if (!in_array($field, ['nombre', 'nit', 'contacto', 'telefono', 'email', 'contador', 'clave_hacienda', 'clave_planilla'])) {
        throw new Exception("Campo no permitido.");
      }

      $sql = "UPDATE clientes SET {$field} = ? WHERE id = ?";
      $pdo->prepare($sql)->execute([$value, $id]);
      
      echo json_encode(['ok'=>true,'msg'=>'Campo autoguardado']); exit;
    }

    // Nuevo endpoint para autoguardado de estados
    if ($action==='autosave_estado') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) throw new Exception("ID inválido.");

      $tipo = $_POST['tipo'] ?? '';
      $valor = $_POST['valor'] ?? '';
      
      $mapEstado = function($tipo, $val) {
        $v = strtolower(trim($val));
        switch ($tipo) {
          case 'iva': case 'declaracion_iva':
            if ($v==='documentos pendiente') return 'documento pendiente';
            if ($v==='pendiente de procesar') return 'pendiente de procesar';
            if ($v==='en proceso') return 'en proceso';
            if ($v==='presentada') return 'presentada';
            if ($v==='pagada') return 'pagada';
            return 'documento pendiente';
          case 'pa': case 'declaracion_pa':
            if ($v==='documentos pendiente') return 'documento pendiente';
            if ($v==='pendiente de procesar') return 'pendiente de procesar';
            if ($v==='en proceso') return 'en proceso';
            if ($v==='presentada') return 'presentada';
            if ($v==='pagada') return 'pagada';
            return 'documento pendiente';
          case 'planilla': case 'declaracion_planilla':
            if ($v==='documentos pendiente') return 'documento pendiente';
            if ($v==='pendiente de procesar') return 'pendiente de procesar';
            if ($v==='en proceso') return 'en proceso';
            if ($v==='presentada') return 'presentada';
            if ($v==='pagada') return 'pagada';
            return 'documento pendiente';
          case 'conta': case 'declaracion_contabilidad':
            if ($v==='documentos pendiente') return 'documento pendiente';
            if ($v==='pendiente de procesar') return 'pendiente de procesar';
            if ($v==='en proceso') return 'en proceso';
            if ($v==='presentada') return 'presentada';
            return 'documento pendiente';
        }
        return null;
      };

      $columna = '';
      switch ($tipo) {
        case 'iva': $columna = 'declaracion_iva'; break;
        case 'pa': $columna = 'declaracion_pa'; break;
        case 'planilla': $columna = 'declaracion_planilla'; break;
        case 'conta': $columna = 'declaracion_contabilidad'; break;
        default: throw new Exception("Tipo de estado no válido.");
      }

      $valorBD = $mapEstado($tipo, $valor);
      if ($valorBD === null) throw new Exception("Valor de estado no válido.");

      $sql = "UPDATE clientes SET {$columna} = ? WHERE id = ?";
      $pdo->prepare($sql)->execute([$valorBD, $id]);
      
      echo json_encode(['ok'=>true,'msg'=>'Estado autoguardado']); exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Acción no soportada.']); exit;

  } catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); exit;
  }
}

/* ---------- VISTA (GET) ---------- */
$anio = isset($_GET['anio']) && $_GET['anio']!=='' ? (int)$_GET['anio'] : null;
$mes  = isset($_GET['mes'])  && $_GET['mes']  !=='' ? (int)$_GET['mes']  : null;

try {
  $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",$DB_USER,$DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

  // Años y meses para combos (de la tabla periodos)
  $years = $pdo->query("SELECT DISTINCT anio FROM periodos ORDER BY anio DESC")->fetchAll(PDO::FETCH_COLUMN);
  $months = [];
  if ($anio){
    $stm = $pdo->prepare("SELECT DISTINCT mes FROM periodos WHERE anio=? ORDER BY mes ASC");
    $stm->execute([$anio]);
    $months = $stm->fetchAll(PDO::FETCH_COLUMN);
  }

  // SQL consolidado - ahora obtenemos los datos directamente de la tabla clientes
  $sql = "
    SELECT 
      id, 
      nombre AS cliente_nombre, 
      nit, 
      contacto, 
      telefono, 
      email,
      contador, 
      clave_hacienda, 
      clave_planilla,
      declaracion_iva AS iva,
      declaracion_pa AS pa,
      declaracion_planilla AS planilla,
      declaracion_contabilidad AS conta
    FROM clientes 
    WHERE activo = 1
    ORDER BY nombre ASC
  ";

  $st = $pdo->prepare($sql); $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

} catch(Throwable $e){
  http_response_code(500);
  echo '<!doctype html><meta charset="utf-8"><pre style="padding:16px;color:#b91c1c">Error: '.h($e->getMessage()).'</pre>'; exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ProFinancial - Consolidado de Clientes</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <style>
    body{font-family:'Inter',sans-serif}
    .sidebar-item.active{background:#eef2ff;color:#4f46e5;border-left:3px solid #4f46e5}
    .pill{display:inline-flex;align-items:center;padding:.25rem .5rem;border-radius:999px;font-size:.75rem;font-weight:600}
    .pill.yellow{background:#fef3c7;color:#92400e}
    .pill.blue{background:#dbeafe;color:#1e40af}
    .pill.indigo{background:#e0e7ff;color:#3730a3}
    .pill.green{background:#d1fae5;color:#065f46}
    .hint{font-size:.75rem;color:#6b7280}
    .edit-cell { border: 2px solid #3b82f6 !important; background-color: #f0f9ff !important; }
    .saving { opacity: 0.7; background-color: #fef3c7 !important; }
    .saved { background-color: #d1fae5 !important; transition: background-color 0.5s ease; }
    .autosave-status { 
      position: fixed; bottom: 20px; right: 20px; padding: 10px 15px; 
      border-radius: 8px; font-size: 14px; z-index: 1000;
    }
  </style>
</head>
<body class="flex bg-gray-100">
  <div class="flex h-screen w-full">
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
          <a href="../cliente/perfil_contador.php" class="sidebar-item flex items-center p-3 rounded-lg">
            <i class="fas fa-user-circle mr-3 text-gray-500"></i> Mi Perfil
          </a>
        </div>
      </nav>
    </div>

    <!-- Contenido principal -->
    <main class="flex-1 p-8 overflow-y-auto">
      <div class="flex md:flex-row md:items-center md:justify-between gap-4 mb-4">
        <div>
          <h2 class="text-2xl font-bold">Consolidado General de Clientes</h2>
          <p class="hint">Para editar <b>estados</b>, selecciona Año y Mes. Sin período, solo se guardan datos del cliente.</p>
        </div>

        <form id="filtros" class="flex flex-wrap items-center gap-3" method="get">
          <select name="anio" id="filtroAno" class="border rounded px-3 py-2">
            <option value="">Año (último disponible)</option>
            <?php foreach ($years as $y): ?>
              <option value="<?=h($y)?>" <?= ($anio===$y)?'selected':''?>><?=h($y)?></option>
            <?php endforeach; ?>
          </select>

          <select name="mes" id="filtroMes" class="border rounded px-3 py-2" <?= $anio?'':'disabled'?>>
            <option value="">Mes (último del año)</option>
            <?php foreach ($months as $m): ?>
              <option value="<?=h($m)?>" <?= ($mes===$m)?'selected':''?>><?=h(monthName($m))?></option>
            <?php endforeach; ?>
          </select>

          <button class="hidden" type="submit">Aplicar</button>

          <button id="editarTabla" type="button" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded flex items-center">
            <i class="fas fa-edit mr-2"></i><span>Editar Tabla</span>
          </button>

          <div id="autoSaveStatus" class="autosave-status bg-gray-100 text-gray-600 hidden">
            <i class="fas fa-save mr-1"></i> <span>Auto-guardado activo</span>
          </div>
        </form>
      </div>

      <div class="overflow-x-auto bg-white rounded-lg shadow-md">
        <table class="min-w-max text-sm text-left text-gray-700">
          <thead class="bg-blue-600 text-white">
            <tr>
              <th class="px-4 py-2 min-w-[180px]">Cliente</th>
              <th class="px-4 py-2 min-w-[120px]">NIT</th>
              <th class="px-4 py-2 min-w-[140px]">Contacto</th>
              <th class="px-4 py-2 min-w-[120px]">Número</th>
              <th class="px-4 py-2 min-w-[200px]">Correo</th>
              <th class="px-4 py-2 min-w-[150px]">Contador</th>
              <th class="px-4 py-2 min-w-[150px]">Clave Hacienda</th>
              <th class="px-4 py-2 min-w-[150px]">Clave Planilla</th>
              <th class="px-4 py-2 min-w-[130px]">IVA</th>
              <th class="px-4 py-2 min-w-[130px]">PA</th>
              <th class="px-4 py-2 min-w-[120px]">Planilla</th>
              <th class="px-4 py-2 min-w-[140px]">Contabilidad</th>
              <th class="px-4 py-2 min-w-[150px]">Acciones</th>
            </tr>
          </thead>
          <tbody id="tablaClientes">
          <?php if ($rows): foreach ($rows as $r):
              // Mapear los valores de la BD a los valores mostrados en la interfaz
              $vIva = strtolower($r['iva'] ?? 'documento pendiente');
              $vPa  = strtolower($r['pa']  ?? 'documento pendiente');
              $vPla = strtolower($r['planilla'] ?? 'documento pendiente');
              $vCon = strtolower($r['conta'] ?? 'pendiente de procesar');
              
              // Convertir a los valores mostrados en la interfaz
              $displayIva = $vIva;
              $displayPa = $vPa;
              $displayPla = $vPla;
              $displayCon = $vCon;
          ?>
            <tr class="border-b" data-id="<?=h($r['id'])?>">
              <!-- celdas editables (se activan por toggle) -->
              <td class="px-4 py-2 whitespace-nowrap editable-cell" contenteditable="false" data-field="nombre"><?=h($r['cliente_nombre'])?></td>
              <td class="px-4 py-2 whitespace-nowrap editable-cell" contenteditable="false" data-field="nit"><?=h($r['nit'])?></td>
              <td class="px-4 py-2 whitespace-nowrap editable-cell" contenteditable="false" data-field="contacto"><?=h($r['contacto'])?></td>
              <td class="px-4 py-2 whitespace-nowrap editable-cell" contenteditable="false" data-field="telefono"><?=h($r['telefono'])?></td>
              <td class="px-4 py-2 whitespace-nowrap editable-cell" contenteditable="false" data-field="email"><?=h($r['email'])?></td>
              <td class="px-4 py-2 whitespace-nowrap editable-cell" contenteditable="false" data-field="contador"><?=h($r['contador'])?></td>
              <td class="px-4 py-2 whitespace-nowrap editable-cell" contenteditable="false" data-field="clave_hacienda"><?=h($r['clave_hacienda'])?></td>
              <td class="px-4 py-2 whitespace-nowrap editable-cell" contenteditable="false" data-field="clave_planilla"><?=h($r['clave_planilla'])?></td>

              <!-- selects de estado -->
              <td class="px-4 py-2 whitespace-nowrap">
                <select class="estado iva border rounded px-2 py-1" disabled data-tipo="iva">
                  <?php
                    $opts=['documentos pendiente','pendiente de procesar','en proceso','presentada','pagada'];
                    foreach($opts as $o){ $sel=$o===$displayIva?'selected':''; echo '<option '.$sel.' value="'.h($o).'">'.h(ucfirst($o)).'</option>'; }
                  ?>
                </select>
              </td>
              <td class="px-4 py-2 whitespace-nowrap">
                <select class="estado pa border rounded px-2 py-1" disabled data-tipo="pa">
                  <?php
                    $opts=['documentos pendiente','pendiente de procesar','en proceso','presentada','pagada'];
                    foreach($opts as $o){ $sel=$o===$displayPa?'selected':''; echo '<option '.$sel.' value="'.h($o).'">'.h(ucfirst($o)).'</option>'; }
                  ?>
                </select>
              </td>
              <td class="px-4 py-2 whitespace-nowrap">
                <select class="estado planilla border rounded px-2 py-1" disabled data-tipo="planilla">
                  <?php
                    $opts=['documentos pendiente','pendiente de procesar','en proceso','presentada','pagada'];
                    foreach($opts as $o){ $sel=$o===$displayPla?'selected':''; echo '<option '.$sel.' value="'.h($o).'">'.h(ucfirst($o)).'</option>'; }
                  ?>
                </select>
              </td>
              <td class="px-4 py-2 whitespace-nowrap">
                <select class="estado conta border rounded px-2 py-1" disabled data-tipo="conta">
                  <?php
                    $opts=['documentos pendiente','pendiente de procesar','en proceso','presentada'];
                    foreach($opts as $o){ $sel=$o===$displayCon?'selected':''; echo '<option '.$sel.' value="'.h($o).'">'.h(ucfirst($o)).'</option>'; }
                  ?>
                </select>
              </td>

              <td class="px-4 py-2 whitespace-nowrap">
                <button class="btn-guardar bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded mr-2 disabled:opacity-50" disabled>Guardar</button>
                <button class="bg-red-500 text-white px-3 py-1 rounded opacity-50 cursor-not-allowed" disabled>Eliminar</button>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="13" class="px-4 py-6 text-center text-gray-500">Sin datos para el filtro seleccionado.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>

  <script>
    const fAnio = document.getElementById('filtroAno');
    const fMes  = document.getElementById('filtroMes');
    const formFiltros = document.getElementById('filtros');
    const btnEditar = document.getElementById('editarTabla');
    const tabla = document.getElementById('tablaClientes');
    const statusElement = document.getElementById('autoSaveStatus');

    // Variables para autoguardado
    let autoSaveTimeout;
    let pendingChanges = new Map();
    const AUTO_SAVE_DELAY = 2000; // 2 segundos
    let isSaving = false;

    // Auto-submit filtros / habilitar Mes
    fAnio.addEventListener('change', ()=>{
      if(!fAnio.value){ fMes.disabled = true; fMes.value=''; }
      else { fMes.disabled = false; }
      formFiltros.submit();
    });
    fMes.addEventListener('change', ()=> formFiltros.submit());

    // Toggle modo edición
    let editMode = false;
    btnEditar.addEventListener('click', ()=>{
      editMode = !editMode;
      btnEditar.classList.toggle('bg-blue-600', !editMode);
      btnEditar.classList.toggle('bg-red-600', editMode);
      btnEditar.querySelector('span').textContent = editMode ? 'Desactivar Edición' : 'Editar Tabla';
      statusElement.classList.toggle('hidden', !editMode);

      // celdas contenteditable
      tabla.querySelectorAll('tr').forEach(tr=>{
        tr.querySelectorAll('.editable-cell').forEach(td=>{
          td.setAttribute('contenteditable', String(editMode));
          if (editMode) {
            td.classList.add('ring-1','ring-indigo-200');
            // Agregar event listeners para autoguardado
            td.addEventListener('input', debounce(() => handleCellEdit(td, tr), 300));
          } else {
            td.classList.remove('ring-1','ring-indigo-200');
            // Remover event listeners
            const newTd = td.cloneNode(true);
            td.parentNode.replaceChild(newTd, td);
          }
        });
        
        // selects de estado
        tr.querySelectorAll('select.estado').forEach(sel=>{
          sel.disabled = !editMode;
          if (editMode) {
            sel.classList.remove('opacity-60');
            // Agregar event listeners para autoguardado
            sel.addEventListener('change', () => handleSelectChange(sel, tr));
          } else {
            sel.classList.add('opacity-60');
            // Remover event listeners
            const newSel = sel.cloneNode(true);
            sel.parentNode.replaceChild(newSel, sel);
          }
        });
        
        // botón guardar
        const btn = tr.querySelector('.btn-guardar');
        btn.disabled = !editMode;
        btn.classList.toggle('opacity-50', !editMode);
      });
    });

    // Función debounce para optimizar
    function debounce(func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    }

    // Manejar edición de celdas
    function handleCellEdit(cell, row) {
      const clientId = row.getAttribute('data-id');
      const field = cell.getAttribute('data-field');
      const value = cell.textContent.trim();

      cell.classList.add('edit-cell', 'saving');
      scheduleAutoSave(clientId, field, value, cell, 'autosave');
    }

    // Manejar cambio de selects
    function handleSelectChange(select, row) {
      const clientId = row.getAttribute('data-id');
      const tipo = select.getAttribute('data-tipo');
      const value = select.value;

      select.classList.add('edit-cell', 'saving');
      scheduleAutoSave(clientId, tipo, value, select, 'autosave_estado');
    }

    // Programar autoguardado
    function scheduleAutoSave(clientId, field, value, element, action) {
      // Cancelar timeout anterior
      if (autoSaveTimeout) {
        clearTimeout(autoSaveTimeout);
      }

      // Guardar cambio pendiente
      if (!pendingChanges.has(clientId)) {
        pendingChanges.set(clientId, new Map());
      }
      pendingChanges.get(clientId).set(field, {value, element, action});

      // Actualizar estado
      updateStatus('guardando');

      // Programar guardado
      autoSaveTimeout = setTimeout(() => {
        savePendingChanges();
      }, AUTO_SAVE_DELAY);
    }

    // Guardar cambios pendientes
    async function savePendingChanges() {
      if (pendingChanges.size === 0 || isSaving) return;
      
      isSaving = true;

      try {
        updateStatus('guardando');

        for (const [clientId, changes] of pendingChanges) {
          for (const [field, data] of changes) {
            const formData = new FormData();
            formData.append('action', data.action);
            formData.append('id', clientId);

            if (data.action === 'autosave') {
              formData.append('field', field);
              formData.append('value', data.value);
            } else if (data.action === 'autosave_estado') {
              formData.append('tipo', field);
              formData.append('valor', data.value);
            }

            const response = await fetch(location.href, {
              method: 'POST',
              body: formData
            });

            const result = await response.json();

            if (result.ok) {
              // Marcar como guardado
              data.element.classList.remove('saving');
              data.element.classList.add('saved');
              setTimeout(() => data.element.classList.remove('saved'), 2000);
            } else {
              console.error('Error autoguardando:', result.msg);
              data.element.classList.remove('saving');
            }
          }
        }

        updateStatus('guardado');
        setTimeout(() => updateStatus('activo'), 2000);

        pendingChanges.clear();

      } catch (error) {
        console.error('Error en autoguardado:', error);
        updateStatus('error');
        // Hacer backup de los cambios en localStorage
        backupChangesToLocalStorage();
      } finally {
        isSaving = false;
      }
    }

    // Guardar cambios con sendBeacon (para cuando se cierra la pestaña)
    function savePendingChangesWithBeacon() {
      if (pendingChanges.size === 0) return true;

      let allSent = true;

      for (const [clientId, changes] of pendingChanges) {
        for (const [field, data] of changes) {
          const formData = new FormData();
          formData.append('action', data.action);
          formData.append('id', clientId);

          if (data.action === 'autosave') {
            formData.append('field', field);
            formData.append('value', data.value);
          } else if (data.action === 'autosave_estado') {
            formData.append('tipo', field);
            formData.append('valor', data.value);
          }

          // Convertir FormData a URLSearchParams
          const params = new URLSearchParams();
          for (const [key, value] of formData.entries()) {
            params.append(key, value);
          }

          // Usar sendBeacon para enviar los datos
          const success = navigator.sendBeacon(location.href, params);
          
          if (!success) {
            allSent = false;
            console.warn('No se pudo enviar el cambio:', field);
          } else {
            console.log('Cambio enviado con sendBeacon:', field);
          }
        }
      }

      // Hacer backup en localStorage por si acaso
      if (!allSent) {
        backupChangesToLocalStorage();
      } else {
        // Limpiar cambios pendientes si se enviaron todos
        pendingChanges.clear();
      }

      return allSent;
    }

    // Backup de cambios en localStorage
    function backupChangesToLocalStorage() {
      try {
        const changesArray = Array.from(pendingChanges.entries());
        if (changesArray.length > 0) {
          localStorage.setItem('pending_changes_backup', JSON.stringify({
            timestamp: new Date().toISOString(),
            changes: changesArray,
            url: window.location.href
          }));
          console.log('Cambios guardados en backup localStorage');
        }
      } catch (error) {
        console.error('Error haciendo backup en localStorage:', error);
      }
    }

    // Recuperar cambios del backup
    function recoverChangesFromBackup() {
      try {
        const backup = localStorage.getItem('pending_changes_backup');
        if (backup) {
          const backupData = JSON.parse(backup);
          
          // Verificar que el backup sea de esta misma URL
          if (backupData.url === window.location.href) {
            console.log('Cambios pendientes encontrados en backup:', backupData);
            
            // Preguntar al usuario si quiere recuperar los cambios
            if (confirm('Se encontraron cambios no guardados de la última sesión. ¿Deseas recuperarlos?')) {
              // Aquí podrías implementar la lógica para aplicar los cambios recuperados
              // Por ahora simplemente limpiamos el backup
              localStorage.removeItem('pending_changes_backup');
              return true;
            }
          }
          
          // Limpiar backup antiguo o de otra página
          localStorage.removeItem('pending_changes_backup');
        }
      } catch (error) {
        console.error('Error recuperando backup:', error);
        localStorage.removeItem('pending_changes_backup');
      }
      return false;
    }

    // Actualizar estado del autoguardado
    function updateStatus(status) {
      switch(status) {
        case 'activo':
          statusElement.innerHTML = '<i class="fas fa-save mr-1"></i> Auto-guardado activo';
          statusElement.className = 'autosave-status bg-gray-100 text-gray-600';
          break;
        case 'guardando':
          statusElement.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...';
          statusElement.className = 'autosave-status bg-yellow-100 text-yellow-700';
          break;
        case 'guardado':
          statusElement.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Guardado';
          statusElement.className = 'autosave-status bg-green-100 text-green-700';
          break;
        case 'error':
          statusElement.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Error al guardar';
          statusElement.className = 'autosave-status bg-red-100 text-red-700';
          break;
      }
    }

    // Event listeners para guardar al cerrar la pestaña
    window.addEventListener('beforeunload', (event) => {
      if (pendingChanges.size > 0 && editMode) {
        // Mostrar advertencia al usuario
        event.preventDefault();
        event.returnValue = 'Tienes cambios sin guardar. ¿Estás seguro de que quieres salir?';
        
        // Intentar guardar con sendBeacon
        const success = savePendingChangesWithBeacon();
        
        if (success) {
          // Si se enviaron todos los cambios, permitir cerrar sin advertencia
          delete event.returnValue;
        }
      }
    });

    // Para móviles y tablets
    window.addEventListener('pagehide', (event) => {
      if (pendingChanges.size > 0 && editMode) {
        savePendingChangesWithBeacon();
      }
    });

    // Guardado manual por fila (mantener compatibilidad)
    tabla.addEventListener('click', async (ev)=>{
      const btn = ev.target.closest('.btn-guardar');
      if(!btn) return;
      
      // Guardar cambios pendientes primero
      if (pendingChanges.size > 0) {
        await savePendingChanges();
      }

      const tr = ev.target.closest('tr');
      const id = tr.getAttribute('data-id');

      // Extraer valores de celdas
      const payloadCliente = {
        action:'update_cliente',
        id,
        nombre: tr.querySelector('[data-field="nombre"]').textContent.trim(),
        nit: tr.querySelector('[data-field="nit"]').textContent.trim(),
        contacto: tr.querySelector('[data-field="contacto"]').textContent.trim(),
        telefono: tr.querySelector('[data-field="telefono"]').textContent.trim(),
        email: tr.querySelector('[data-field="email"]').textContent.trim(),
        contador: tr.querySelector('[data-field="contador"]').textContent.trim(),
        clave_hacienda: tr.querySelector('[data-field="clave_hacienda"]').textContent.trim(),
        clave_planilla: tr.querySelector('[data-field="clave_planilla"]').textContent.trim()
      };

      const fd1 = new FormData();
      Object.entries(payloadCliente).forEach(([k,v])=>fd1.append(k,v));

      btn.disabled = true; 
      btn.textContent = 'Guardando...';

      try{
        let r = await fetch(location.href, {method:'POST', body: fd1});
        let j = await r.json();
        if(!j.ok) throw new Error(j.msg || 'Error al actualizar cliente');

        // Actualizar estados
        const payloadEstados = {
          action:'update_estados',
          id,
          iva: tr.querySelector('select.iva').value,
          pa: tr.querySelector('select.pa').value,
          planilla: tr.querySelector('select.planilla').value,
          conta: tr.querySelector('select.conta').value
        };
        
        const fd2 = new FormData();
        Object.entries(payloadEstados).forEach(([k,v])=>fd2.append(k,v));
        r = await fetch(location.href, {method:'POST', body: fd2});
        j = await r.json();
        
        if(!j.ok) throw new Error(j.msg || 'Error al actualizar estados');

        btn.textContent = 'Guardado ✔';
        setTimeout(()=>{ btn.textContent='Guardar'; btn.disabled=false; }, 1200);

      }catch(e){
        alert(e.message || 'Error guardando.');
        btn.textContent = 'Guardar';
        btn.disabled = false;
      }
    });

    // Sidebar activo
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll(".sidebar-item").forEach(item=>{
      const linkPage = item.getAttribute("href").split("/").pop();
      if(linkPage===currentPage) item.classList.add("active"); else item.classList.remove("active");
    });

    // Recuperar cambios al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
      recoverChangesFromBackup();
    });
  </script>
</body>
</html>