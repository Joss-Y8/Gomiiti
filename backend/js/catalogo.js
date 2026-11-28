//creamos promociones, registra nuevos productos, edita su precio, cambia su disponibilidad y administra las promos

document.addEventListener('DOMContentLoaded', () => {
  const endpoint = API + 'catalogo.php';
  const $ = id => document.getElementById(id);

  const formProducto = $('form-producto');
  const formPromo = $('form-promo');
  const selectCategoria = $('categoria');
  const selectProductosPromo = $('promo-prods');
  const tablaProductos = $('productos');
  const listaPromos = $('lista-promos');

  const tituloForm = $('titulo-form-producto');
  const btnSubmitProducto = formProducto.querySelector('[type="submit"]');
  const btnCancelar = $('cancelar-edicion');
  let editandoId = null;                     // null = alta; número = editando ese producto

  const dinero = valor => '$' + Number(valor || 0).toFixed(2);

  const mostrarMensaje = (elemento, texto, error = false) => {
    elemento.textContent = texto || '';
    elemento.classList.toggle('msg-error', error && Boolean(texto));
  };

  async function enviar(datos) {
    const respuesta = await api(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(datos)
    });

    if (!respuesta.ok) {
      throw new Error(respuesta.mensaje || 'No se pudo completar la operación.');
    }
    return respuesta;
  }

  function option(valor, texto) {
    const op = document.createElement('option');
    op.value = valor;
    op.textContent = texto;
    return op;
  }

  /* ---------- Modo edición del formulario de producto ---------- */
  function entrarEdicion(p) {
    editandoId = p.id_producto;
    tituloForm.textContent = 'Editar producto';
    btnSubmitProducto.textContent = 'Guardar cambios';
    btnCancelar.hidden = false;
    mostrarMensaje($('msg-producto'), '');
    $('nombre').value = p.nombre;
    selectCategoria.value = p.id_categoria;
    $('precio').value = p.precio;
    $('temporada').checked = p.es_temporada;
    $('disponible').disabled = true;         // la disponibilidad se cambia con el botón de la tabla
    formProducto.scrollIntoView({ behavior: 'smooth' });
    $('nombre').focus();
  }

  function salirEdicion() {
    editandoId = null;
    formProducto.reset();
    $('disponible').checked = true;
    $('disponible').disabled = false;
    tituloForm.textContent = 'Nuevo producto';
    btnSubmitProducto.textContent = 'Agregar producto';
    btnCancelar.hidden = true;
  }
  btnCancelar.addEventListener('click', () => {
    salirEdicion();
    mostrarMensaje($('msg-producto'), '');
  });

  async function cargarCatalogo() {
    const data = await api(endpoint);

    if (!data.ok) {
      throw new Error(data.mensaje || 'No se pudo cargar el catálogo.');
    }

    // Conserva lo que el administrador ya había elegido antes de reconstruir los selectores
    const categoriaPrevia = selectCategoria.value;
    const promoPrevios = new Set(Array.from(selectProductosPromo.selectedOptions).map(o => o.value));

    // Cargar categorías
    selectCategoria.replaceChildren(option('', 'Selecciona una categoría'));
    data.categorias.forEach(c => {
      selectCategoria.append(option(c.id_categoria, c.nombre));
    });
    selectCategoria.value = categoriaPrevia;

    // Cargar productos para las promociones
    selectProductosPromo.replaceChildren();
    data.productos.forEach(p => {
      const op = option(p.id_producto, p.nombre);
      op.selected = promoPrevios.has(String(p.id_producto));
      selectProductosPromo.append(op);
    });

    // Mostrar productos en la tabla
    tablaProductos.replaceChildren();

    if (!data.productos.length) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 6;
      td.textContent = 'Todavía no hay productos registrados.';
      tr.append(td);
      tablaProductos.append(tr);
    }

    data.productos.forEach(p => {
      const tr = document.createElement('tr');

      const valores = [
        p.nombre + (p.es_temporada ? ' · Temporada' : ''),
        p.categoria,
        dinero(p.precio),
        String(p.existencia),
        p.disponible ? 'Disponible' : 'No disponible'
      ];

      valores.forEach(texto => {
        const td = document.createElement('td');
        td.textContent = texto;
        tr.append(td);
      });

      const td = document.createElement('td');

      // Botón para editar
      const btnEditar = document.createElement('button');
      btnEditar.type = 'button';
      btnEditar.className = 'sec';
      btnEditar.textContent = 'Editar';
      btnEditar.addEventListener('click', () => entrarEdicion(p));

      // Botón para activar o desactivar
      const btnEstado = document.createElement('button');
      btnEstado.type = 'button';
      btnEstado.className = 'sec';
      btnEstado.textContent = p.disponible ? 'Desactivar' : 'Activar';

      btnEstado.addEventListener('click', async () => {
        try {
          await enviar({ accion: 'cambiar_disponibilidad', id_producto: p.id_producto });
          await cargarCatalogo();
        } catch (e) {
          alert(e.message);
        }
      });

      // Botón para eliminar
      const btnEliminar = document.createElement('button');
      btnEliminar.type = 'button';
      btnEliminar.className = 'sec danger';
      btnEliminar.textContent = 'Eliminar';

      btnEliminar.addEventListener('click', async () => {
        if (!confirm(`¿Eliminar "${p.nombre}"?`)) return;

        try {
          await enviar({ accion: 'eliminar_producto', id_producto: p.id_producto });
          if (editandoId === p.id_producto) salirEdicion();
          await cargarCatalogo();
        } catch (e) {
          alert(e.message);
        }
      });

      td.append(
        btnEditar,
        document.createTextNode(' '),
        btnEstado,
        document.createTextNode(' '),
        btnEliminar
      );

      tr.append(td);
      tablaProductos.append(tr);
    });

    // Mostrar promociones
    listaPromos.replaceChildren();

    if (!data.promociones.length) {
      const li = document.createElement('li');
      li.textContent = 'Todavía no hay promociones registradas.';
      listaPromos.append(li);
    }

    data.promociones.forEach(p => {
      const li = document.createElement('li');
      const info = document.createElement('div');

      const titulo = document.createElement('strong');
      titulo.textContent = p.nombre;

      const detalle = document.createElement('p');

      const tipoPromo = p.precio_promo !== null && p.precio_promo !== ''
        ? `Precio promocional: ${dinero(p.precio_promo)}`
        : `Descuento: ${Number(p.descuento_pct).toFixed(2)}%`;

      const comboTexto = p.requiere_combo ? ' · Combo estricto' : '';

      detalle.textContent =
        `${tipoPromo}${comboTexto} · Productos: ${p.productos.length ? p.productos.join(', ') : 'Sin productos'}`;

      const estado = document.createElement('p');
      estado.textContent = p.activa ? 'Activa' : 'Inactiva';

      info.append(titulo, detalle, estado);

      // Activar o desactivar promoción
      const btnToggle = document.createElement('button');
      btnToggle.type = 'button';
      btnToggle.className = 'sec';
      btnToggle.textContent = p.activa ? 'Desactivar' : 'Activar';

      btnToggle.addEventListener('click', async () => {
        try {
          await enviar({ accion: 'cambiar_promocion', id_promo: p.id_promo });
          await cargarCatalogo();
        } catch (e) {
          alert(e.message);
        }
      });

      // Eliminar promoción
      const btnDelete = document.createElement('button');
      btnDelete.type = 'button';
      btnDelete.className = 'sec danger';
      btnDelete.textContent = 'Eliminar';

      btnDelete.addEventListener('click', async () => {
        if (!confirm(`¿Eliminar la promoción "${p.nombre}"?`)) return;

        try {
          await enviar({ accion: 'eliminar_promocion', id_promo: p.id_promo });
          await cargarCatalogo();
        } catch (e) {
          alert(e.message);
        }
      });

      li.append(info, btnToggle, document.createTextNode(' '), btnDelete);
      listaPromos.append(li);
    });
  }

  // Registrar o editar producto
  formProducto.addEventListener('submit', async ev => {
    ev.preventDefault();

    const msg = $('msg-producto');
    mostrarMensaje(msg, '');

    const nombre = $('nombre').value.trim();
    const id_categoria = Number(selectCategoria.value);
    const precio = Number($('precio').value);

    if (!nombre || !id_categoria || !Number.isFinite(precio) || precio < 0) {
      mostrarMensaje(msg, 'Completa nombre, categoría y un precio válido.', true);
      return;
    }

    btnSubmitProducto.disabled = true;

    try {
      const datos = {
        accion: editandoId ? 'editar_producto' : 'crear_producto',
        nombre,
        id_categoria,
        precio,
        disponible: $('disponible').checked,
        es_temporada: $('temporada').checked
      };
      if (editandoId) datos.id_producto = editandoId;

      const result = await enviar(datos);

      salirEdicion();
      mostrarMensaje(msg, result.mensaje);
      await cargarCatalogo();
    } catch (e) {
      mostrarMensaje(msg, e.message, true);
    } finally {
      btnSubmitProducto.disabled = false;
    }
  });

  // Crear promoción
  formPromo.addEventListener('submit', async ev => {
    ev.preventDefault();

    const msg = $('msg-promo');
    mostrarMensaje(msg, '');

    const nombre = $('promo-nombre').value.trim();
    const precio = $('promo-precio').value;
    const descuento = $('promo-descuento').value;
    const requiereCombo = $('promo-combo') ? $('promo-combo').checked : false;

    const productos = Array.from(selectProductosPromo.selectedOptions).map(op => Number(op.value));

    if (!nombre || !productos.length || (precio === '' && descuento === '') || (precio !== '' && descuento !== '')) {
      mostrarMensaje(msg, 'Escribe el nombre, selecciona productos e indica solo un precio o un descuento.', true);
      return;
    }

    if (requiereCombo && productos.length < 2) {
      mostrarMensaje(msg, 'Una promoción de combo estricto requiere seleccionar al menos 2 productos.', true);
      return;
    }

    const btn = formPromo.querySelector('[type="submit"]');
    btn.disabled = true;

    try {
      const result = await enviar({
        accion: 'crear_promocion',
        nombre,
        precio_promo: precio,
        descuento_pct: descuento,
        requiere_combo: requiereCombo,
        productos
      });

      formPromo.reset();
      if ($('promo-combo')) $('promo-combo').checked = false;
      mostrarMensaje(msg, result.mensaje);
      await cargarCatalogo();
    } catch (e) {
      mostrarMensaje(msg, e.message, true);
    } finally {
      btn.disabled = false;
    }
  });

  // Carga inicial
  cargarCatalogo().catch(e => {
    mostrarMensaje($('msg-catalogo'), e.message, true);
  });
});