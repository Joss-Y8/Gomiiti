const $ = id => document.getElementById(id);
const ESTADOS = {
  disponible: {texto:'Disponible', clase:'estado-ok'},
  bajo:       {texto:'Bajo stock', clase:'estado-bajo'},
  agotado:    {texto:'Agotado',    clase:'estado-agotado'}
};
let items = [];

function textoFila(texto){
  const tr = document.createElement('tr'), td = document.createElement('td');
  td.colSpan = 4; td.textContent = texto;
  tr.append(td); $('inventario').replaceChildren(tr);
}

function mensaje(texto, ok){
  let p = $('msg-mov');
  if(!p){
    p = document.createElement('p'); p.id = 'msg-mov'; p.setAttribute('role', 'status');
    $('form-movimiento').querySelector('button[type="submit"]').before(p);
  }
  p.textContent = texto || '';
  p.style.color = ok ? 'var(--ok)' : 'var(--bad)';
}

/* ---------- Tabla ---------- */
async function cargar(){
  try {
    const d = await api(API + 'inventario.php');
    if(!d.ok){ textoFila(d.mensaje || 'No se pudo cargar el inventario.'); return; }
    items = d.items;
    pintarTabla();
    pintarSelector();
  } catch(e){
    textoFila('No se pudo cargar el inventario. Intenta de nuevo.');
  }
}

function pintarTabla(){
  $('n-bajo').textContent    = items.filter(i => i.estado === 'bajo').length;
  $('n-agotado').textContent = items.filter(i => i.estado === 'agotado').length;

  const filtro = $('f-estado').value;                          // "", "Disponible", "Bajo stock", "Agotado"
  const lista = filtro ? items.filter(i => ESTADOS[i.estado].texto === filtro) : items;
  if(!lista.length){ textoFila(items.length ? 'Ningún producto coincide con el filtro.' : 'Aún no hay productos.'); return; }

  const tbody = $('inventario'); tbody.replaceChildren();
  for(const i of lista){
    const tr = document.createElement('tr');
    const tdNombre = document.createElement('td');
    tdNombre.append(i.nombre);
    const sm = document.createElement('small');
    sm.textContent = ' · ' + i.categoria + (i.disponible ? '' : ' · oculto en el menú');
    tdNombre.append(sm);
    const tdEx = document.createElement('td'); tdEx.textContent = i.existencia;
    const tdMin = document.createElement('td'); tdMin.textContent = i.minimo;
    const tdEst = document.createElement('td');
    tdEst.textContent = ESTADOS[i.estado].texto; tdEst.className = ESTADOS[i.estado].clase;
    tr.append(tdNombre, tdEx, tdMin, tdEst);
    tbody.append(tr);
  }
}

function pintarSelector(){
  const sel = $('item'), previo = sel.value;
  sel.replaceChildren();
  for(const i of items){
    const o = document.createElement('option'); o.value = i.id_producto; o.textContent = i.nombre;
    sel.append(o);
  }
  if(previo) sel.value = previo;                               // conserva la selección tras recargar
}

/* ---------- Formulario ---------- */
function ajustarEtiqueta(){
  const esEntrada = $('tipo').value === 'entrada';
  document.querySelector('label[for="cant"]').textContent = esEntrada ? 'Unidades que entran' : 'Existencia real contada';
  $('cant').min = esEntrada ? 1 : 0;
}

async function registrar(ev){
  ev.preventDefault();
  mensaje('');
  const cantidad = Number($('cant').value);
  if($('cant').value === '' || !Number.isInteger(cantidad)){ mensaje('Escribe una cantidad entera.'); return; }
  const btn = ev.target.querySelector('button[type="submit"]'); btn.disabled = true;
  try {
    const d = await api(API + 'inventario.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({id_producto:Number($('item').value), tipo:$('tipo').value, cantidad})
    });
    if(!d.ok){ mensaje(d.mensaje || 'No se pudo registrar el movimiento.'); return; }
    mensaje('Movimiento registrado. Existencia actual: ' + d.existencia + '.', true);
    $('cant').value = '';
    await cargar();
  } catch(e){
    mensaje('No hay conexión con el servidor.');
  } finally {
    btn.disabled = false;
  }
}

$('f-estado').addEventListener('change', pintarTabla);
$('tipo').addEventListener('change', ajustarEtiqueta);
$('form-movimiento').addEventListener('submit', registrar);
ajustarEtiqueta();
cargar();