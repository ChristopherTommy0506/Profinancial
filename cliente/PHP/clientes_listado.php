<?php
/* clientes_listado.php  (sin API, SSR)
   - Requiere PHP 7.4+ y tu BD 'profinancial_crm'
   - Ajusta credenciales abajo
*/
$DB_HOST='localhost';
$DB_NAME='profinancial_crm';
$DB_USER='root';
$DB_PASS=''; // <-- AJUSTA

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

try {
  $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
                 $DB_USER,$DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

  /* Trae una fila por cliente+periodo con estados pivot:
     - IVA: pagado -> pagada; presentado -> presentada; si no -> pendiente
     - PA:  presentado o pagado -> realizado; si no -> pendiente
     - PLANILLA: pagado -> pagada; si no -> pendiente
     - CONTAB: presentado o pagado -> realizado; si no -> pendiente
  */
  $sql = "
    SELECT
      c.id AS cliente_id, c.nombre AS cliente_nombre, c.nit, c.contacto, c.telefono, c.email,
      u.nombre AS contador,
      per.anio, per.mes,
      MAX(CASE WHEN tf.codigo='IVA'
        THEN CASE WHEN p.pagado=1 THEN 'pagada'
                  WHEN p.presentado=1 THEN 'presentada'
                  ELSE 'pendiente' END END) AS iva,
      MAX(CASE WHEN tf.codigo='PA'
        THEN CASE WHEN (p.presentado=1 OR p.pagado=1) THEN 'realizado'
                  ELSE 'pendiente' END END) AS pa,
      MAX(CASE WHEN tf.codigo='PLANILLA'
        THEN CASE WHEN p.pagado=1 THEN 'pagada'
                  ELSE 'pendiente' END END) AS planilla,
      MAX(CASE WHEN tf.codigo='CONTAB'
        THEN CASE WHEN (p.presentado=1 OR p.pagado=1) THEN 'realizado'
                  ELSE 'pendiente' END END) AS conta,
      MAX(CASE WHEN ch.servicio='HACIENDA' THEN ch.usuario_servicio END) AS clave_hacienda,
      MAX(CASE WHEN cp.servicio='PLANILLA' THEN cp.usuario_servicio END) AS clave_planilla
    FROM clientes c
    LEFT JOIN asignaciones_cliente ac
           ON ac.cliente_id=c.id AND ac.hasta IS NULL AND ac.rol_en_cliente='RESPONSABLE'
    LEFT JOIN usuarios u ON u.id=ac.usuario_id
    LEFT JOIN periodos per ON per.cliente_id=c.id
    LEFT JOIN presentaciones p ON p.periodo_id=per.id
    LEFT JOIN tipos_formulario tf ON tf.id=p.tipo_formulario_id
    LEFT JOIN credenciales_cliente ch ON ch.cliente_id=c.id AND ch.servicio='HACIENDA'
    LEFT JOIN credenciales_cliente cp ON cp.cliente_id=c.id AND cp.servicio='PLANILLA'
    /* Si quieres limitar a los últimos 12 meses, cambia el WHERE */
    WHERE 1=1
    GROUP BY c.id, c.nombre, c.nit, c.contacto, c.telefono, c.email, u.nombre, per.anio, per.mes
    ORDER BY per.anio DESC, per.mes DESC, c.nombre ASC
  ";
  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

} catch(Throwable $e) {
  http_response_code(500);
  echo "<h1>Error de conexión</h1><pre>".h($e->getMessage())."</pre>";
  exit;
}

// Helper para fecha data-fecha
function primeraDelMes($anio,$mes){
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
    select:disabled { opacity: .6; cursor: not-allowed; }
  </style>
</head>
<body class="bg-gray-50">
<div class="flex h-screen">
  <!-- Sidebar -->
  <div class="hidden md:flex flex-col w-64 bg-white border-r">
    <div class="flex items-center h-16 px-4 bg-indigo-600">
      <div class="flex items-center">
        <img src="https://placehold.co/30x30" alt="Logo Profinancial" class="h-8 w-8 rounded-full bg-white p-1">
        <span class="ml-2 text-white font-bold">Profinancial</span>
      </div>
    </div>
    <nav class="flex-1 p-4">
      <div class="space-y-2">
        <a href="#" class="sidebar-item active flex items-center p-3 rounded-lg">
          <i class="fas fa-users mr-3 text-gray-500"></i> Clientes
        </a>
        <a href="../cliente/perfil_contador.html" class="sidebar-item flex items-center p-3 rounded-lg">
          <i class="fas fa-user-circle mr-3 text-gray-500"></i> Mi Perfil
        </a>
      </div>
    </nav>
  </div>

  <!-- Contenido principal -->
  <div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white shadow-sm">
      <div class="px-6 py-4 flex items-center justify-between">
        <button class="md:hidden text-gray-500"><i class="fas fa-bars text-xl"></i></button>
        <h1 class="text-xl font-semibold text-gray-800" id="page-title">Clientes</h1>
        <div class="flex items-center space-x-4">
          <div class="relative">
            <input id="filtroTexto" type="text" placeholder="Filtrar" class="search-box pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:border-indigo-500">
            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
          </div>
          <div class="flex items-center">
            <img src="https://placehold.co/40x40" alt="Foto perfil" class="h-8 w-8 rounded-full mr-2">
            <span class="text-sm font-medium">Contador Ejemplo</span>
          </div>
        </div>
      </div>
    </header>

    <main class="flex-1 overflow-y-auto p-6">
      <!-- Toolbar -->
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Listado de Clientes</h2>

        <div class="flex flex-wrap items-center gap-3">
          <label class="text-sm font-medium text-gray-700">Apartado</label>
          <select id="selectApartado" class="border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="iva">Declaración IVA</option>
            <option value="pa">Declaración PA</option>
            <option value="planilla">Planilla</option>
            <option value="conta">Contabilidad</option>
          </select>

          <label class="text-sm font-medium text-gray-700">Estado</label>
          <select id="selectEstado" class="border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></select>

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

          <label class="inline-flex items-center gap-2 text-sm">
            <input id="chkFiltrar" type="checkbox" class="rounded border-gray-300" checked>
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
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Clave Hacienda</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Clave Planilla</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contacto</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Declaración IVA</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Declaración PA</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Planilla</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contabilidad</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contador</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php foreach ($rows as $r):
                if (!$r['anio'] || !$r['mes']) continue;
                $fecha = primeraDelMes($r['anio'],$r['mes']);
                $iva = strtolower($r['iva'] ?? 'pendiente');
                $pa  = strtolower($r['pa']  ?? 'pendiente');
                $pla = strtolower($r['planilla'] ?? 'pendiente');
                $con = strtolower($r['conta'] ?? 'pendiente');
              ?>
              <tr
                data-fecha="<?=h($fecha)?>"
                data-iva="<?=h($iva)?>"
                data-pa="<?=h($pa)?>"
                data-planilla="<?=h($pla)?>"
                data-conta="<?=h($con)?>"
              >
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?=h($r['cliente_nombre'])?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?=h($r['nit'])?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?=h($r['clave_hacienda'] ?? '-')?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?=h($r['clave_planilla'] ?? '-')?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?=h($r['contacto'])?></td>

                <td class="px-6 py-4 whitespace-nowrap text-sm"><span class="estado-badge" data-col="iva"></span></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm"><span class="estado-badge" data-col="pa"></span></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm"><span class="estado-badge" data-col="planilla"></span></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm"><span class="estado-badge" data-col="conta"></span></td>

                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?=h($r['contador'] ?? '-')?></td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <button onclick="openModal()" class="text-green-500 hover:text-green-700 mr-3" title="Ver detalles"><i class="fas fa-eye"></i></button>
                  <button class="text-indigo-600 hover:text-indigo-900 mr-3" title="Editar"><i class="fas fa-edit"></i></button>
                  <button onclick="document.getElementById('deleteModal').classList.remove('hidden')" class="text-red-600 hover:text-red-900" title="Eliminar"><i class="fas fa-trash"></i></button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($rows)): ?>
              <tr><td colspan="11" class="px-6 py-4 text-sm text-gray-500">No hay datos para mostrar.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-gray-700">
                Mostrando <span class="font-medium"><?=count($rows)?></span> resultado(s)
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                <a class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500">
                  <i class="fas fa-chevron-left"></i>
                </a>
                <a aria-current="page" class="z-10 bg-indigo-50 border-indigo-500 text-indigo-600 relative inline-flex items-center px-4 py-2 border text-sm font-medium">1</a>
                <a class="bg-white border-gray-300 text-gray-500 relative inline-flex items-center px-4 py-2 border text-sm font-medium">2</a>
                <a class="bg-white border-gray-300 text-gray-500 relative inline-flex items-center px-4 py-2 border text sm font-medium">3</a>
                <a class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500">
                  <i class="fas fa-chevron-right"></i>
                </a>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Modal Detalle -->
<div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-3xl p-6 relative">
    <button onclick="closeModal()" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600" aria-label="Cerrar">
      <i class="fas fa-times"></i>
    </button>
    <h2 class="text-xl font-bold mb-5 text-gray-800">Detalles del Cliente</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-10 gap-y-3 text-sm">
      <div><span class="block text-gray-500 font-semibold">Nombre:</span><span class="block text-gray-900">—</span></div>
      <div><span class="block text-gray-500 font-semibold">NIT:</span><span class="block text-gray-900">—</span></div>
      <div><span class="block text-gray-500 font-semibold">Registro:</span><span class="block text-gray-900">—</span></div>
      <div><span class="block text-gray-500 font-semibold">Clave Hacienda:</span><span class="block text-gray-900">—</span></div>
      <div><span class="block text-gray-500 font-semibold">Clave Planilla:</span><span class="block text-gray-900">—</span></div>
      <div><span class="block text-gray-500 font-semibold">Contacto:</span><span class="block text-gray-900">—</span></div>
      <div><span class="block text-gray-500 font-semibold">Teléfono:</span><span class="block text-gray-900">—</span></div>
      <div><span class="block text-gray-500 font-semibold">Correo:</span><span class="block text-gray-900">—</span></div>
      <div><span class="block text-gray-500 font-semibold">Contador:</span><span class="block text-gray-900">—</span></div>
    </div>
    <div class="mt-5">
      <a href="../cliente/perfil_cliente.html">
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
      <button onclick="document.getElementById('deleteModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancelar</button>
      <button onclick="document.getElementById('deleteModal').classList.add('hidden')" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Eliminar</button>
    </div>
  </div>
</div>

<script>
// Helpers modal
function openModal(){ const m=document.getElementById('modal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeModal(){ const m=document.getElementById('modal'); m.classList.add('hidden'); m.classList.remove('flex'); }

/* === Filtro front (igual al tuyo, sin API) === */
(function(){
  const tbody        = document.querySelector("#tablaClientes tbody");
  const $apartado    = document.getElementById("selectApartado");
  const $estado      = document.getElementById("selectEstado");
  const $chkFiltrar  = document.getElementById("chkFiltrar");
  const $filtroTexto = document.getElementById("filtroTexto");
  const $anio        = document.getElementById("selectAnio");
  const $mes         = document.getElementById("selectMes");
  const $dia         = document.getElementById("selectDia");

  const ESTADOS = {
    iva:      ["pendiente","presentada","pagada","almacenada"],
    pa:       ["pendiente","realizado"],
    planilla: ["pagada","pendiente"],
    conta:    ["pendiente","realizado"]
  };
  const MESES = [
    {v:1,n:"Enero"},{v:2,n:"Febrero"},{v:3,n:"Marzo"},{v:4,n:"Abril"},
    {v:5,n:"Mayo"},{v:6,n:"Junio"},{v:7,n:"Julio"},{v:8,n:"Agosto"},
    {v:9,n:"Septiembre"},{v:10,n:"Octubre"},{v:11,n:"Noviembre"},{v:12,n:"Diciembre"}
  ];

  const badgeClasses = (val)=>{
    const v=(val||"").toLowerCase();
    if (v==="pendiente") return "bg-yellow-100 text-yellow-800";
    if (v==="presentada") return "bg-blue-100 text-blue-800";
    if (v==="realizado") return "bg-indigo-100 text-indigo-800";
    if (v==="pagada"||v==="pagado") return "bg-green-100 text-green-800";
    if (v==="almacenada"||v==="almacenado") return "bg-gray-200 text-gray-800";
    return "bg-slate-100 text-slate-800";
  };
  const norm=(s='')=>s.toString().trim().toLowerCase();
  const cap =(s='')=>s ? s.charAt(0).toUpperCase()+s.slice(1) : s;

  function renderBadges(){
    tbody.querySelectorAll("tr").forEach(row=>{
      ["iva","pa","planilla","conta"].forEach(col=>{
        const val=row.getAttribute(`data-${col}`)||"";
        const el=row.querySelector(`.estado-badge[data-col="${col}"]`);
        if (el){
          el.className=`estado-badge inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${badgeClasses(val)}`;
          el.textContent=val?cap(val):"-";
          el.title=el.textContent;
        }
      });
    });
  }

  function fillEstados(apartado){
    const opts=ESTADOS[apartado]||[];
    $estado.innerHTML=opts.map(e=>`<option value="${e}">${cap(e)}</option>`).join("");
  }

  function uniqueYears(){
    const years=new Set();
    tbody.querySelectorAll("tr").forEach(tr=>{
      const f=tr.getAttribute("data-fecha");
      if(f&&/^\d{4}-\d{2}-\d{2}$/.test(f)) years.add(parseInt(f.slice(0,4),10));
    });
    return Array.from(years).sort((a,b)=>b-a);
  }
  function fillAnios(){
    const años=uniqueYears();
    $anio.innerHTML=`<option value="">Todos</option>`+años.map(y=>`<option value="${y}">${y}</option>`).join("");
  }
  function fillMeses(habilitar){
    const MESES_OPTS = `<option value="">Todos</option>`+MESES.map(m=>`<option value="${m.v}">${m.n}</option>`).join("");
    $mes.innerHTML=MESES_OPTS;
    $mes.disabled=!habilitar;
  }
  function daysInMonth(y,m){ return new Date(y,m,0).getDate(); }
  function fillDias(habilitar,y,m){
    $dia.innerHTML=`<option value="">Todos</option>`;
    if(habilitar&&y&&m){
      let opts=""; for(let d=1; d<=daysInMonth(y,m); d++) opts+=`<option value="${d}">${d}</option>`;
      $dia.innerHTML+=opts;
    }
    $dia.disabled=!habilitar;
  }

  function applyFilter(){
    const apartado=$apartado.value;
    const estadoSel=norm($estado.value||"");
    const q=norm($filtroTexto.value||"");
    const ySel=$anio.value?parseInt($anio.value,10):null;
    const mSel=$mes.value?parseInt($mes.value,10):null;
    const dSel=$dia.value?parseInt($dia.value,10):null;

    tbody.querySelectorAll("tr").forEach(row=>{
      const matchesText = q ? row.innerText.toLowerCase().includes(q) : true;

      let matchesState = true;
      if($chkFiltrar.checked && estadoSel){
        const val=norm(row.getAttribute(`data-${apartado}`)||"");
        matchesState=(val===estadoSel);
      }

      let matchesPeriod=true;
      const f=row.getAttribute("data-fecha");
      if(f&&/^\d{4}-\d{2}-\d{2}$/.test(f)){
        const yr=parseInt(f.slice(0,4),10);
        const mo=parseInt(f.slice(5,7),10);
        const dy=parseInt(f.slice(8,10),10);
        if(ySel!==null && yr!==ySel) matchesPeriod=false;
        if(matchesPeriod && mSel!==null && mo!==mSel) matchesPeriod=false;
        if(matchesPeriod && dSel!==null && dy!==dSel) matchesPeriod=false;
      } else if (ySel!==null || mSel!==null || dSel!==null){
        matchesPeriod=false;
      }

      row.style.display = (matchesText && matchesState && matchesPeriod) ? "" : "none";
    });
  }

  $apartado.addEventListener("change", ()=>{ fillEstados($apartado.value); applyFilter(); });
  $estado.addEventListener("change", applyFilter);
  $chkFiltrar.addEventListener("change", applyFilter);
  $filtroTexto.addEventListener("input", applyFilter);
  $anio.addEventListener("change", ()=>{
    const ySel=$anio.value?parseInt($anio.value,10):null;
    fillMeses(!!ySel); $mes.value=""; fillDias(false); applyFilter();
  });
  $mes.addEventListener("change", ()=>{
    const ySel=$anio.value?parseInt($anio.value,10):null;
    const mSel=$mes.value?parseInt($mes.value,10):null;
    fillDias(!!(ySel&&mSel), ySel, mSel); $dia.value=""; applyFilter();
  });
  $dia.addEventListener("change", applyFilter);

  // Init
  fillEstados($apartado.value);
  renderBadges();
  fillAnios();
  fillMeses(false);
  fillDias(false);
  applyFilter();
})();
</script>
</body>
</html>
