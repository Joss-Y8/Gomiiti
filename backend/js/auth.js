/* Sesión y restricciones por rol (front). La seguridad real la valida PHP en cada petición. */

/* Rutas calculadas desde la ubicación de ESTE archivo (backend/js/auth.js),
   así funcionan igual desde la raíz o desde frontend/html/ */
const BASE  = document.currentScript.src;
const API   = new URL('../php/', BASE).href;
const INDEX = new URL('../../index.html', BASE).href;   // login + panel de inicio

const PERMISOS = {                       // página -> roles permitidos
  'index.html':['cajero','admin'],   'pedido.html':['cajero','admin'],
  'detalles.html':['cajero','admin'],'historial.html':['cajero','admin'],
  'inventario.html':['admin'],       'catalogo.html':['admin']
};

function sesion(){
  try { return JSON.parse(sessionStorage.getItem('gomitti_sesion')); }
  catch(e){ return null; }
}

/* Cierra el turno en el servidor, limpia el navegador y vuelve al login */
async function cerrarSesion(){
  try { await fetch(API + 'logout.php', {method:'POST'}); } catch(e){ /* sin red: igual salimos */ }
  sessionStorage.clear();
  location.href = INDEX;
  if(location.href === INDEX) location.reload();   // si ya estamos en index, recarga para mostrar el login
}

/* Llamar al final de CADA página (index.html incluida) */
function protegerPagina(){
  const s = sesion();
  const pag = location.pathname.split('/').pop() || 'index.html';
  const enIndex = pag === 'index.html';

  if(!s){
    if(enIndex){                                  // index sin sesión: mostrar el login
      document.getElementById('vista-login').hidden = false;
      document.getElementById('vista-panel').hidden = true;
      document.getElementById('codigo')?.focus();
    } else {
      location.href = INDEX;                      // otra página sin sesión: al login
    }
    return null;
  }
  if(!PERMISOS[pag]?.includes(s.rol)){
    alert('No tienes permiso para esta sección');
    location.href = INDEX;
    return null;
  }
  if(enIndex){                                    // index con sesión: mostrar el panel
    document.getElementById('vista-login').hidden = true;
    document.getElementById('vista-panel').hidden = false;
  }
  document.body.dataset.rol = s.rol;
  const etiqueta = s.rol === 'admin' ? 'Administrador' : 'Cajero ' + s.turno;
  document.querySelectorAll('[data-usuario]').forEach(e => e.textContent = s.nombre);
  document.querySelectorAll('[data-rol-texto]').forEach(e => e.textContent = etiqueta);
  return s;
}

/* Helper para pedir datos a PHP. Si la sesión del servidor expiró (401), regresa al login. */
async function api(url, opciones){
  const r = await fetch(url, opciones);
  if(r.status === 401 && !url.includes('login.php')){
    sessionStorage.clear();
    location.href = INDEX;
    throw new Error('Sesión expirada');
  }
  return r.json().catch(() => ({ok:false, mensaje:'Respuesta inválida del servidor.'}));
}

/* Login por código */
async function iniciarSesion(ev){
  ev.preventDefault();
  const f = ev.target, err = document.getElementById('error'), btn = document.getElementById('btn-entrar');
  err.textContent = '';
  if(!f.codigo.value.trim()){ err.textContent = 'Escribe tu código.'; return; }
  btn.disabled = true;
  try {
    const d = await api(API + 'login.php', {method:'POST', body:new FormData(f)});
    if(!d.ok){ err.textContent = d.mensaje || 'No se pudo iniciar sesión.'; f.codigo.value = ''; f.codigo.focus(); return; }
    sessionStorage.setItem('gomitti_sesion', JSON.stringify({nombre:d.nombre, rol:d.rol, turno:d.turno}));
    f.codigo.value = '';
    protegerPagina();                             // cambia de login a panel sin recargar
  } catch(e){
    err.textContent = 'No hay conexión con el servidor.';
  } finally {
    btn.disabled = false;
  }
}