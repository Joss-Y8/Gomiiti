/* detalles.html: asigna complementos y notas a los artículos del pedido en curso.
   Requiere auth.js (API, api()). El pedido vive en sessionStorage (lo crea pedido.js). */
const CLAVE_PEDIDO = 'gomitti_pedido';
const TIPOS = [                                   // orden y título de cada grupo
  {tipo:'salsa',   titulo:'Salsas',          multiple:true},
  {tipo:'polvo',   titulo:'Polvos',          multiple:true},
  {tipo:'topping', titulo:'Toppings',        multiple:true},
  {tipo:'picante', titulo:'Nivel de picante', multiple:false}
];

const $ = id => document.getElementById(id);
const dinero = n => '$' + Number(n).toFixed(2);

let pedido = [];          // copia de trabajo; solo se guarda al pulsar "Guardar detalles"
let extras = [];          // catálogo desde la BD
let actual = 0;           // índice del artículo mostrado

function cargarPedido(){
  try { return JSON.parse(sessionStorage.getItem(CLAVE_PEDIDO)) || []; }
  catch(e){ return []; }
}


async function cargarExtras(){
  const cont = $('extras-contenedor');
  try {
    const d = await api(API + 'extras.php');
    if(!d.ok){ cont.textContent = d.mensaje || 'No se pudieron cargar los complementos.'; return false; }
    extras = d.extras;
    pintarExtras();
    return true;
  } catch(e){
    cont.textContent = 'No se pudieron cargar los complementos. Intenta de nuevo.';
    return false;
  }
}

function pintarExtras(){
  const cont = $('extras-contenedor');
  cont.replaceChildren();
  if(!extras.length){ cont.textContent = 'No hay complementos disponibles.'; return; }
  for(const g of TIPOS){
    const lista = extras.filter(e => e.tipo === g.tipo);
    if(!lista.length) continue;
    const fs = document.createElement('fieldset');
    const lg = document.createElement('legend'); lg.textContent = g.titulo;
    fs.append(lg);
    for(const e of lista){
      const lb = document.createElement('label');
      const inp = document.createElement('input');
      inp.type  = g.multiple ? 'checkbox' : 'radio';
      inp.name  = g.multiple ? 'extra' : 'picante';
      inp.value = e.id;
      lb.append(inp, ' ' + e.nombre + (e.costo > 0 ? ' (+' + dinero(e.costo) + ')' : ''));
      fs.append(lb);
    }
    cont.append(fs);
  }
}


function cargarEnFormulario(item){
  const ids = (item.extras || []).map(e => e.id);
  document.querySelectorAll('#extras-contenedor input').forEach(inp => {
    inp.checked = ids.includes(Number(inp.value));
  });
  $('notas').value = item.nota || '';
}

function guardarDesdeFormulario(item){
  const marcados = [...document.querySelectorAll('#extras-contenedor input:checked')].map(i => Number(i.value));
  item.extras = extras.filter(e => marcados.includes(e.id))
                      .map(e => ({id:e.id, nombre:e.nombre, costo:e.costo}));
  item.nota = $('notas').value.trim().slice(0, 255);
}

function pintarSelectorArticulos(){
  const sel = $('articulo');
  sel.replaceChildren();
  pedido.forEach((i, idx) => {
    const o = document.createElement('option');
    o.value = idx;
    o.textContent = i.cantidad + ' × ' + i.nombre;
    sel.append(o);
  });
}


function alCambiarArticulo(){
  guardarDesdeFormulario(pedido[actual]);          // conserva lo capturado del artículo anterior
  actual = Number($('articulo').value);
  cargarEnFormulario(pedido[actual]);
}

function alGuardar(ev){
  ev.preventDefault();
  guardarDesdeFormulario(pedido[actual]);
  sessionStorage.setItem(CLAVE_PEDIDO, JSON.stringify(pedido));
  location.href = 'pedido.html';                   // pedido.js recalcula el total con los extras
}


async function iniciar(){
  $('notas').maxLength = 255;
  pedido = cargarPedido();
  if(!pedido.length){
    $('extras-contenedor').textContent = 'No hay artículos en el pedido. Regresa a Pedidos y agrega productos primero.';
    $('form-detalles').querySelectorAll('select, textarea, button').forEach(el => el.disabled = true);
    return;
  }
  pintarSelectorArticulos();
  if(await cargarExtras()) cargarEnFormulario(pedido[actual]);
  $('articulo').addEventListener('change', alCambiarArticulo);
  $('form-detalles').addEventListener('submit', alGuardar);
}
iniciar();