/* historial.html: lista de ventas. Cajero = su turno; Admin = todo con filtros y acumulados.*/

const $ = id => document.getElementById(id);
const dinero = n => '$' + Number(n).toFixed(2);
const ES_ADMIN = sesion()?.rol === 'admin';
const COLUMNAS = ES_ADMIN ? 6 : 5;

function textoFila(tbody, texto){
  const tr = document.createElement('tr'), td = document.createElement('td');
  td.colSpan = COLUMNAS; td.textContent = texto;
  tr.append(td); tbody.replaceChildren(tr);
}

function celda(contenido){
  const td = document.createElement('td');
  if(contenido instanceof Node) td.append(contenido); else td.textContent = contenido;
  return td;
}

function formatoFecha(s){
  const f = new Date(s.replace(' ', 'T'));
  if(isNaN(f)) return s;
  return ES_ADMIN
    ? f.toLocaleString('es-MX', {dateStyle:'short', timeStyle:'short'})
    : f.toLocaleTimeString('es-MX', {hour:'2-digit', minute:'2-digit'});
}

function listaProductos(lineas){
  const ul = document.createElement('ul');
  ul.style.cssText = 'margin:0;padding-left:1rem';
  for(const l of lineas){
    const li = document.createElement('li'); li.textContent = l.cantidad + ' × ' + l.nombre;
    ul.append(li);
  }
  return ul;
}

function listaExtras(lineas){                       // solo admin
  const ul = document.createElement('ul');
  ul.style.cssText = 'margin:0;padding-left:1rem';
  for(const l of lineas){
    const partes = [];
    if(l.extras?.length) partes.push(l.extras.join(', '));
    if(l.nota) partes.push('Nota: ' + l.nota);
    if(!partes.length) continue;
    const li = document.createElement('li'); li.textContent = l.nombre + ' — ' + partes.join(' · ');
    ul.append(li);
  }
  if(!ul.children.length) return '—';
  return ul;
}

async function cargar(){
  const tbody = $('ventas');
  const q = new URLSearchParams();
  if(ES_ADMIN){
    if($('f-fecha').value) q.set('fecha', $('f-fecha').value);
    if($('f-pago').value)  q.set('pago', $('f-pago').value.toLowerCase());
  }
  try {
    const d = await api(API + 'historial.php?' + q);
    if(!d.ok){ textoFila(tbody, d.mensaje || 'No se pudo cargar el historial.'); return; }

    if(ES_ADMIN && d.resumen){
      const r = d.resumen;
      $('acumulado').textContent = dinero(r.total) + ' · ' + r.ventas + ' venta' + (r.ventas === 1 ? '' : 's') +
        ' (Efectivo ' + dinero(r.efectivo) + ' · Tarjeta ' + dinero(r.tarjeta) + ')' +
        (r.ventas > d.ventas.length ? ' — se muestran las últimas ' + d.ventas.length : '');
    }

    if(!d.ventas.length){
      textoFila(tbody, ES_ADMIN ? 'No hay ventas con esos filtros.' : 'Aún no hay ventas en tu turno.');
      return;
    }
    tbody.replaceChildren();
    for(const v of d.ventas){
      const tr = document.createElement('tr');
      tr.append(celda('#' + v.folio), celda(formatoFecha(v.fecha_hora)), celda(listaProductos(v.lineas)),
                celda(v.pago === 'efectivo' ? 'Efectivo' : 'Tarjeta'), celda(dinero(v.total)));
      if(ES_ADMIN) tr.append(celda(listaExtras(v.lineas)));
      tbody.append(tr);
    }
  } catch(e){
    textoFila(tbody, 'No se pudo cargar el historial. Intenta de nuevo.');
  }
}

if(ES_ADMIN){
  $('f-fecha').addEventListener('change', cargar);
  $('f-pago').addEventListener('change', cargar);
}
cargar();