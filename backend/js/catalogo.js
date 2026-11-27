//creamos promociones, registra nuevos productos, cambia su disponibilidad y administra las promos 

document.addEventListener('DOMContentLoaded', () => {
  const endpoint = API + 'catalogo.php';
  const $ = id => document.getElementById(id);

  const formProducto = $('form-producto');
  const formPromo = $('form-promo');
  const selectCategoria = $('categoria');
  const selectProductosPromo = $('promo-prods');
  const tablaProductos = $('productos');
  const listaPromos = $('lista-promos');

  const dinero = valor => '$' + Number(valor || 0).toFixed(2);

  const mostrarMensaje = (elemento, texto, error = false) => {
    elemento.textContent = texto || '';
    elemento.classList.toggle('msg-error', error && Boolean(texto));
  };

  async function enviar(datos) {
    const respuesta = await api(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(datos)
    });

    if (!respuesta.ok) {
      throw new Error(
        respuesta.mensaje || 'No se pudo completar la operación.'
      );
    }

    return respuesta;
  }

  function option(valor, texto) {
    const op = document.createElement('option');
    op.value = valor;
    op.textContent = texto;
    return op;
  }

  async function cargarCatalogo() {
    const data = await api(endpoint);

    if (!data.ok) {
      throw new Error(
        data.mensaje || 'No se pudo cargar el catálogo.'
      );
    }

    // Cargar categorías
    selectCategoria.replaceChildren(
      option('', 'Selecciona una categoría')
    );

    data.categorias.forEach(c => {
      selectCategoria.append(
        option(c.id_categoria, c.nombre)
      );
    });

    // Cargar productos para las promociones
    selectProductosPromo.replaceChildren();

    data.productos.forEach(p => {
      selectProductosPromo.append(
        option(p.id_producto, p.nombre)
      );
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

      // Botón para activar o desactivar
      const btnEstado = document.createElement('button');

      btnEstado.type = 'button';
      btnEstado.className = 'sec';
      btnEstado.textContent = p.disponible
        ? 'Desactivar'
        : 'Activar';

      btnEstado.addEventListener('click', async () => {
        try {
          await enviar({
            accion: 'cambiar_disponibilidad',
            id_producto: p.id_producto
          });

          await cargarCatalogo();
        } catch (e) {
          alert(e.message);
        }
      });

      // Botón para eliminar
      const btnEliminar = document.createElement('button');

      btnEliminar.type = 'button';
      btnEliminar.className = 'sec';
      btnEliminar.textContent = 'Eliminar';

      btnEliminar.addEventListener('click', async () => {
        if (!confirm(`¿Eliminar "${p.nombre}"?`)) {
          return;
        }

        try {
          await enviar({
            accion: 'eliminar_producto',
            id_producto: p.id_producto
          });

          await cargarCatalogo();
        } catch (e) {
          alert(e.message);
        }
      });

      td.append(
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

      const tipoPromo = p.precio_promo !== null
        ? `Precio promocional: ${dinero(p.precio_promo)}`
        : `Descuento: ${Number(p.descuento_pct).toFixed(2)}%`;

      detalle.textContent =
        `${tipoPromo} · Productos: ${
          p.productos.length
            ? p.productos.join(', ')
            : 'Sin productos'
        }`;

      const estado = document.createElement('p');
      estado.textContent = p.activa ? 'Activa' : 'Inactiva';

      info.append(titulo, detalle, estado);

      // Activar o desactivar promoción
      const btnToggle = document.createElement('button');

      btnToggle.type = 'button';
      btnToggle.className = 'sec';
      btnToggle.textContent = p.activa
        ? 'Desactivar'
        : 'Activar';

      btnToggle.addEventListener('click', async () => {
        try {
          await enviar({
            accion: 'cambiar_promocion',
            id_promo: p.id_promo
          });

          await cargarCatalogo();
        } catch (e) {
          alert(e.message);
        }
      });

      // Eliminar promoción
      const btnDelete = document.createElement('button');

      btnDelete.type = 'button';
      btnDelete.className = 'sec';
      btnDelete.textContent = 'Eliminar';

      btnDelete.addEventListener('click', async () => {
        if (!confirm(
          `¿Eliminar la promoción "${p.nombre}"?`
        )) {
          return;
        }

        try {
          await enviar({
            accion: 'eliminar_promocion',
            id_promo: p.id_promo
          });

          await cargarCatalogo();
        } catch (e) {
          alert(e.message);
        }
      });

      li.append(info, btnToggle, btnDelete);
      listaPromos.append(li);
    });
  }

  // Registrar producto
  formProducto.addEventListener('submit', async ev => {
    ev.preventDefault();

    const msg = $('msg-producto');
    mostrarMensaje(msg, '');

    const nombre = $('nombre').value.trim();
    const id_categoria = Number(selectCategoria.value);
    const precio = Number($('precio').value);

    if (
      !nombre ||
      !id_categoria ||
      !Number.isFinite(precio) ||
      precio < 0
    ) {
      mostrarMensaje(
        msg,
        'Completa nombre, categoría y un precio válido.',
        true
      );
      return;
    }

    const btn = formProducto.querySelector('[type="submit"]');
    btn.disabled = true;

    try {
      const result = await enviar({
        accion: 'crear_producto',
        nombre,
        id_categoria,
        precio,
        disponible: $('disponible').checked,
        es_temporada: $('temporada').checked
      });

      formProducto.reset();
      $('disponible').checked = true;

      mostrarMensaje(msg, result.mensaje);

      await cargarCatalogo();
    } catch (e) {
      mostrarMensaje(msg, e.message, true);
    } finally {
      btn.disabled = false;
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

    const productos = Array.from(
      selectProductosPromo.selectedOptions
    ).map(op => Number(op.value));

    if (
      !nombre ||
      !productos.length ||
      (precio === '' && descuento === '') ||
      (precio !== '' && descuento !== '')
    ) {
      mostrarMensaje(
        msg,
        'Escribe el nombre, selecciona productos e indica solo un precio o un descuento.',
        true
      );
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
        productos
      });

      formPromo.reset();

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
