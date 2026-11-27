/* pedido.html: catálogo desde la BD + sidebar con resumen, pago y cambio. */

const CLAVE_PEDIDO = 'gomitti_pedido';
let productos = [];
let pedido = cargarPedido();          // [{id, nombre, precio, cantidad, max, extras:[{id,nombre,costo}], nota}]

const $ = id => document.getElementById(id);
const dinero = n => '$' + Number(n).toFixed(2);

function cargarPedido(){
  try { return JSON.parse(sessionStorage.getItem(CLAVE_PEDIDO)) || []; }
  catch(e){ return []; }
}
function guardarPedido(){ sessionStorage.setItem(CLAVE_PEDIDO, JSON.stringify(pedido)); }

function precioUnitario(i){ return i.precio + (i.extras || []).reduce((s, e) => s + Number(e.costo), 0); }
function totalPedido(){
  return Math.round(pedido.reduce((s, i) => s + precioUnitario(i) * i.cantidad, 0) * 100) / 100;
}

function mensaje(texto, ok){
  const p = $('error');
  p.textContent = texto || '';
  p.style.color = ok ? 'var(--ok)' : 'var(--bad)';
}

/* ---------- Productos (desde la BD) ---------- */
async function cargarProductos(){
  const cont = $('lista-productos');
  try {
    const d = await api(API + 'productos.php');
    if(!d.ok){ cont.textContent = d.mensaje || 'No se pudieron cargar los productos.'; return; }
    productos = d.productos;
    sincronizarPedido();
    pintarProductos(productos);
    pintarPedido();
  } catch(e){
    cont.textContent = 'No se pudieron cargar los productos. Intenta de nuevo.';
  }
}

/* Mantiene el pedido consistente con el catálogo actual (precio, stock, productos retirados) */
function sincronizarPedido(){
  pedido = pedido.filter(i => productos.some(p => p.id_producto === i.id));
  for(const i of pedido){
    const p = productos.find(x => x.id_producto === i.id);
    i.precio = p.precio; i.max = p.existencia; i.nombre = p.nombre;
    if(i.cantidad > i.max) i.cantidad = i.max;
  }
  guardarPedido();
}

function pintarProductos(lista){
  const cont = $('lista-productos');
  cont.replaceChildren();
  if(!lista.length){ cont.textContent = 'No hay productos disponibles.'; return; }
  for(const p of lista){
    const card = document.createElement('article');
    card.className = 'card';
    const h = document.createElement('h3');  h.textContent = p.nombre;
    const cat = document.createElement('small'); cat.textContent = p.categoria;
    const pr = document.createElement('p'); pr.textContent = dinero(p.precio);
    const b = document.createElement('button'); b.type = 'button'; b.textContent = 'Agregar';
    b.addEventListener('click', () => agregar(p));
    card.append(h, cat, pr, b);
    cont.append(card);
  }
}

/* ---------- Pedido en curso ---------- */
function agregar(p){
  mensaje('');
  const item = pedido.find(i => i.id === p.id_producto);
  if(item){
    if(item.cantidad >= p.existencia){ mensaje('Solo quedan ' + p.existencia + ' de ' + p.nombre + '.'); return; }
    item.cantidad++;
  } else {
    pedido.push({id:p.id_producto, nombre:p.nombre, precio:p.precio, cantidad:1, max:p.existencia, extras:[], nota:''});
  }
  guardarPedido(); pintarPedido();
}

function cambiarCantidad(id, delta){
  const i = pedido.find(x => x.id === id);
  if(!i) return;
  const nueva = i.cantidad + delta;
  if(nueva < 1) return;
  if(nueva > i.max){ mensaje('Solo quedan ' + i.max + ' de ' + i.nombre + '.'); return; }
  mensaje('');
  i.cantidad = nueva;
  guardarPedido(); pintarPedido();
}

function quitar(id){
  pedido = pedido.filter(i => i.id !== id);
  guardarPedido(); pintarPedido();
}

function boton(texto, etiqueta, fn, clase){
  const b = document.createElement('button');
  b.type = 'button'; b.textContent = texto; b.setAttribute('aria-label', etiqueta);
  if(clase) b.className = clase;
  b.addEventListener('click', fn);
  return b;
}

function pintarPedido(){
  const ul = $('items');
  ul.replaceChildren();
  if(!pedido.length){
    const li = document.createElement('li'); li.textContent = 'Aún no hay productos en el pedido.';
    ul.append(li);
  }
  for(const i of pedido){
    const li = document.createElement('li');
    const nombre = document.createElement('strong'); nombre.textContent = i.nombre;
    li.append(nombre);
    if(i.extras?.length){
      const ex = document.createElement('small');
      ex.textContent = ' · ' + i.extras.map(e => e.nombre).join(', ');
      li.append(ex);
    }
    const linea = document.createElement('div');
    const cant = document.createElement('span'); cant.textContent = ' ' + i.cantidad + ' ';
    const sub = document.createElement('span'); sub.textContent = ' ' + dinero(precioUnitario(i) * i.cantidad) + ' ';
    const menos = boton('−', 'Quitar una unidad de ' + i.nombre, () => cambiarCantidad(i.id, -1), 'sec');
    menos.disabled = i.cantidad <= 1;
    linea.append(menos, cant,
      boton('+', 'Agregar una unidad de ' + i.nombre, () => cambiarCantidad(i.id, 1), 'sec'),
      sub,
      boton('Quitar', 'Quitar ' + i.nombre + ' del pedido', () => quitar(i.id), 'danger'));
    li.append(linea);
    ul.append(li);
  }
  $('total').textContent = dinero(totalPedido());
  actualizarPago();
}

/* ---------- Pago y cambio ---------- */
function metodoPago(){ return document.querySelector('input[name="pago"]:checked').value; }

function actualizarPago(){
  const efectivo = metodoPago() === 'efectivo';
  $('bloque-efectivo').hidden = !efectivo;
  const total = totalPedido();
  const recibido = parseFloat($('recibido').value);
  if(!efectivo || isNaN(recibido)){ $('cambio').textContent = dinero(0); return; }
  $('cambio').textContent = recibido >= total ? dinero(recibido - total) : 'Faltan ' + dinero(total - recibido);
}

/* ---------- Confirmar venta ---------- */
async function confirmarVenta(){
  mensaje('');
  if(!pedido.length){ mensaje('El pedido está vacío.'); return; }
  if(pedido.some(i => !Number.isInteger(i.cantidad) || i.cantidad < 1)){ mensaje('Hay cantidades inválidas en el pedido.'); return; }

  const metodo = metodoPago(), total = totalPedido();
  let recibido = null;
  if(metodo === 'efectivo'){
    recibido = parseFloat($('recibido').value);
    if(isNaN(recibido) || recibido < total){ mensaje('El efectivo recibido no cubre el total.'); return; }
  }

  const btn = $('confirmar'); btn.disabled = true;
  try {
    const d = await api(API + 'registrar_venta.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        metodo_pago: metodo,
        recibido: recibido,
        items: pedido.map(i => ({id_producto:i.id, cantidad:i.cantidad, extras:(i.extras || []).map(e => e.id), nota:i.nota || ''}))
      })
    });
    if(!d.ok){ mensaje(d.mensaje || 'No se pudo registrar la venta.'); return; }
    pedido = []; guardarPedido();
    $('recibido').value = '';
    pintarPedido();
    mensaje('Venta registrada. Folio ' + d.folio + '.', true);
    cargarProductos();                       // refresca existencias
  } catch(e){
    mensaje('No hay conexión con el servidor.');
  } finally {
    btn.disabled = false;
  }
}

/* ---------- Arranque ---------- */
$('buscar').addEventListener('input', e => {
  const q = e.target.value.trim().toLowerCase();
  pintarProductos(productos.filter(p => p.nombre.toLowerCase().includes(q) || p.categoria.toLowerCase().includes(q)));
});
document.querySelectorAll('input[name="pago"]').forEach(r => r.addEventListener('change', actualizarPago));
$('recibido').addEventListener('input', actualizarPago);
$('confirmar').addEventListener('click', confirmarVenta);

pintarPedido();
cargarProductos();