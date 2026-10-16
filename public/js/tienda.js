/* ==========================================
   JJ STORE - CATÁLOGO, VISTA RÁPIDA Y CARRITO
   Los productos vienen de la base de datos (api/catalogo.php)
========================================== */

const ENVIO_GRATIS_DESDE = 200000;

let productos = [];
let categoriaActual = "todos";
let prendaSeleccionada = null;
let colorSeleccionado = null;
let varianteSeleccionada = null;
let ultimoElementoModal = null;
let temporizadorBusqueda = null;
let ultimaPeticion = 0;

const contenedor = document.getElementById("productos");
const buscador = document.getElementById("buscador");
const filtros = document.getElementById("filtros");
const sinResultados = document.getElementById("sinResultados");
const resumenCatalogo = document.getElementById("resumenCatalogo");
const contador = document.getElementById("contador");
const carritoItems = document.getElementById("carritoItems");
const total = document.getElementById("total");
const panel = document.getElementById("cartPanel");
const overlay = document.getElementById("cartOverlay");

// Elementos de la vista rápida
const modal = document.getElementById("modalProducto");
const modalImagen = document.getElementById("modalImagen");
const modalNombre = document.getElementById("modalNombre");
const modalPrecio = document.getElementById("modalPrecio");
const modalEtiqueta = document.getElementById("modalEtiqueta");
const modalDescripcion = document.getElementById("modalDescripcion");
const modalVendedor = document.getElementById("modalVendedor");
const modalStock = document.getElementById("modalStock");
const opcionesColores = document.getElementById("opcionesColores");
const opcionesTallas = document.getElementById("opcionesTallas");
const modalAgregar = document.getElementById("modalAgregar");
const cerrarModalBtn = document.getElementById("cerrarModal");

/* ==========================================
   CARGAR CATEGORÍAS Y PRODUCTOS
========================================== */

async function cargarCategorias() {
    try {
        const categorias = await JJ.api("catalogo.php?accion=categorias");

        categorias
            .filter(categoria => categoria.productos > 0)
            .forEach(categoria => {
                const boton = document.createElement("button");
                boton.type = "button";
                boton.className = "filtro";
                boton.dataset.categoria = categoria.slug;
                boton.textContent = categoria.nombre;
                filtros.appendChild(boton);
            });
    } catch {
        // Si falla, queda solo el filtro "Todos"
    }
}

async function cargarProductos() {
    const peticion = ++ultimaPeticion;
    const parametros = new URLSearchParams({ accion: "productos" });
    const texto = buscador.value.trim();

    if (categoriaActual !== "todos") parametros.set("categoria", categoriaActual);
    if (texto) parametros.set("q", texto);

    if (!productos.length) mostrarEsqueleto();
    contenedor.setAttribute("aria-busy", "true");

    try {
        const resultado = await JJ.api("catalogo.php?" + parametros);

        // Si el usuario siguió escribiendo, se ignora la respuesta vieja
        if (peticion !== ultimaPeticion) return;

        productos = resultado;
        mostrarProductos();
    } catch (error) {
        if (peticion !== ultimaPeticion) return;

        contenedor.innerHTML = "";
        sinResultados.hidden = false;
        sinResultados.textContent = error.message;
    } finally {
        contenedor.setAttribute("aria-busy", "false");
    }
}

function mostrarEsqueleto() {
    contenedor.innerHTML = Array.from({ length: 8 }, () =>
        `<div class="producto producto-cargando" aria-hidden="true">
            <div class="cargando-imagen"></div>
            <div class="producto-info">
                <div class="cargando-linea corta"></div>
                <div class="cargando-linea"></div>
                <div class="cargando-linea media"></div>
            </div>
        </div>`
    ).join("");
}

/* ==========================================
   MOSTRAR PRODUCTOS
========================================== */

function mostrarProductos() {
    contenedor.innerHTML = "";

    sinResultados.hidden = productos.length > 0;
    sinResultados.textContent = "No encontramos prendas con esa búsqueda.";

    const tiendas = new Set(productos.map(prenda => prenda.tienda.id)).size;
    resumenCatalogo.textContent = productos.length
        ? `${productos.length} ${productos.length === 1 ? "prenda" : "prendas"} de ${tiendas} ${tiendas === 1 ? "tienda" : "tiendas"}.`
        : "Prendas seleccionadas para ti.";

    productos.forEach(prenda => {
        const tarjeta = document.createElement("article");
        tarjeta.className = "producto";

        const marco = document.createElement("div");
        marco.className = "producto-imagen";

        const imagen = document.createElement("img");
        imagen.src = prenda.imagen;
        imagen.alt = prenda.nombre;
        imagen.loading = "lazy";
        marco.appendChild(imagen);

        if (prenda.stock_total === 0) {
            marco.appendChild(crearEtiqueta("AGOTADO", "agotado"));
        } else if (prenda.stock_total <= 5) {
            marco.appendChild(crearEtiqueta("ÚLTIMAS UNIDADES", "pocas"));
        }

        const informacion = document.createElement("div");
        informacion.className = "producto-info";

        const categoria = document.createElement("p");
        categoria.className = "categoria";
        categoria.textContent = prenda.categoria_nombre;

        const nombre = document.createElement("h3");
        nombre.textContent = prenda.nombre;

        const tienda = document.createElement("p");
        tienda.className = "producto-tienda";
        tienda.textContent = "por " + prenda.tienda.nombre;

        const precio = document.createElement("p");
        precio.className = "producto-precio";
        precio.textContent = JJ.precio(prenda.precio);

        const botonDetalles = document.createElement("button");
        botonDetalles.className = "ver-detalles";
        botonDetalles.type = "button";
        botonDetalles.dataset.id = prenda.id;
        botonDetalles.textContent = "VER PRENDA +";

        informacion.append(categoria, nombre, tienda, precio, botonDetalles);
        tarjeta.append(marco, informacion);
        contenedor.appendChild(tarjeta);
    });
}

function crearEtiqueta(texto, tipo) {
    const etiqueta = document.createElement("span");
    etiqueta.className = `etiqueta-producto ${tipo}`;
    etiqueta.textContent = texto;
    return etiqueta;
}

// Buscar mientras escribes (espera a que el usuario pare de escribir)
buscador.addEventListener("input", () => {
    clearTimeout(temporizadorBusqueda);
    temporizadorBusqueda = setTimeout(cargarProductos, 300);
});

document.getElementById("formBuscar").addEventListener("submit", evento => {
    evento.preventDefault();
    clearTimeout(temporizadorBusqueda);
    cargarProductos();
});

// Filtrar categorías
filtros.addEventListener("click", evento => {
    const boton = evento.target.closest(".filtro");
    if (!boton) return;

    categoriaActual = boton.dataset.categoria;

    filtros.querySelectorAll(".filtro").forEach(filtro => {
        filtro.classList.toggle("activo", filtro === boton);
    });

    cargarProductos();
});

/* ==========================================
   VISTA RÁPIDA
========================================== */

function abrirVistaRapida(id, elementoOrigen) {
    const prenda = productos.find(item => item.id === Number(id));

    if (!prenda || !modal) return;

    prendaSeleccionada = prenda;
    ultimoElementoModal = elementoOrigen;
    varianteSeleccionada = null;

    modalImagen.src = prenda.imagen;
    modalImagen.alt = prenda.nombre;
    modalNombre.textContent = prenda.nombre;
    modalPrecio.textContent = JJ.precio(prenda.precio);
    modalEtiqueta.textContent = `${prenda.categoria_nombre.toUpperCase()} / STREETWEAR`;
    modalDescripcion.textContent = prenda.descripcion || "Estilo urbano, diseño moderno y comodidad para completar tu outfit.";
    modalVendedor.textContent = `Vendido por ${prenda.tienda.nombre}` +
        (prenda.tienda.ciudad ? ` · ${prenda.tienda.ciudad}` : "");

    // Primer color que tenga unidades disponibles
    const conStock = prenda.variantes.find(variante => variante.stock > 0);
    colorSeleccionado = (conStock || prenda.variantes[0])?.color ?? null;

    pintarColores();
    pintarTallas();
    actualizarEstadoAgregar();

    modal.classList.add("abierto");
    modal.setAttribute("aria-hidden", "false");
    modal.removeAttribute("inert");
    document.body.style.overflow = "hidden";

    cerrarModalBtn.focus();
}

function pintarColores() {
    const colores = [...new Set(prendaSeleccionada.variantes.map(variante => variante.color))];
    opcionesColores.innerHTML = "";

    colores.forEach(color => {
        const disponible = prendaSeleccionada.variantes.some(v => v.color === color && v.stock > 0);

        const boton = document.createElement("button");
        boton.type = "button";
        boton.className = "color-opcion";
        boton.dataset.color = color;
        boton.classList.toggle("seleccionado", color === colorSeleccionado);
        boton.classList.toggle("sin-stock", !disponible);
        boton.setAttribute("aria-pressed", String(color === colorSeleccionado));

        const muestra = document.createElement("span");
        muestra.className = "muestra-color";
        muestra.style.background = JJ.colorMuestra(color);

        boton.append(muestra, document.createTextNode(" " + color));
        opcionesColores.appendChild(boton);
    });
}

function pintarTallas() {
    opcionesTallas.innerHTML = "";

    prendaSeleccionada.variantes
        .filter(variante => variante.color === colorSeleccionado)
        .forEach(variante => {
            const boton = document.createElement("button");
            boton.type = "button";
            boton.dataset.variante = variante.id;
            boton.textContent = variante.talla;
            boton.disabled = variante.stock === 0;
            boton.title = variante.stock === 0 ? "Agotada" : `${variante.stock} disponibles`;
            boton.classList.toggle("seleccionado", varianteSeleccionada?.id === variante.id);
            boton.setAttribute("aria-pressed", String(varianteSeleccionada?.id === variante.id));
            opcionesTallas.appendChild(boton);
        });
}

function actualizarEstadoAgregar() {
    const agotado = prendaSeleccionada.stock_total === 0;

    modalAgregar.disabled = agotado;
    modalAgregar.textContent = agotado ? "PRODUCTO AGOTADO" : "AGREGAR AL CARRITO +";
    modalStock.className = "modal-stock";

    if (agotado) {
        modalStock.textContent = "Este producto no tiene unidades disponibles por ahora.";
    } else if (varianteSeleccionada && varianteSeleccionada.stock <= 3) {
        modalStock.textContent = `¡Solo quedan ${varianteSeleccionada.stock}!`;
        modalStock.classList.add("aviso");
    } else {
        modalStock.textContent = "";
    }
}

function cerrarVistaRapida() {
    if (!modal) return;

    modal.classList.remove("abierto");
    modal.setAttribute("aria-hidden", "true");
    modal.setAttribute("inert", "");

    prendaSeleccionada = null;
    document.body.style.overflow = "";

    if (ultimoElementoModal && ultimoElementoModal.isConnected) {
        ultimoElementoModal.focus();
    }
}

// Abrir la vista rápida desde el catálogo
contenedor.addEventListener("click", evento => {
    const boton = evento.target.closest(".ver-detalles");

    if (!boton) return;

    abrirVistaRapida(boton.dataset.id, boton);
});

// Seleccionar color
opcionesColores.addEventListener("click", evento => {
    const boton = evento.target.closest(".color-opcion");
    if (!boton) return;

    colorSeleccionado = boton.dataset.color;
    varianteSeleccionada = null;

    pintarColores();
    pintarTallas();
    actualizarEstadoAgregar();
});

// Seleccionar talla
opcionesTallas.addEventListener("click", evento => {
    const boton = evento.target.closest("button[data-variante]");
    if (!boton || boton.disabled) return;

    varianteSeleccionada = prendaSeleccionada.variantes.find(
        variante => variante.id === Number(boton.dataset.variante)
    );

    pintarTallas();
    actualizarEstadoAgregar();
});

// Cerrar ventana
cerrarModalBtn.addEventListener("click", cerrarVistaRapida);

modal.addEventListener("click", evento => {
    if (evento.target === modal) {
        cerrarVistaRapida();
    }
});

/* ==========================================
   AGREGAR PRENDA AL CARRITO
========================================== */

modalAgregar.addEventListener("click", () => {
    if (!prendaSeleccionada) return;

    if (!varianteSeleccionada) {
        modalStock.textContent = "Por favor, selecciona una talla.";
        modalStock.className = "modal-stock error";
        opcionesTallas.querySelector("button:not(:disabled)")?.focus();
        return;
    }

    const agregado = JJ.agregarAlCarrito({
        variante_id: varianteSeleccionada.id,
        producto_id: prendaSeleccionada.id,
        nombre: prendaSeleccionada.nombre,
        imagen: prendaSeleccionada.imagen,
        precio: prendaSeleccionada.precio,
        talla: varianteSeleccionada.talla,
        color: varianteSeleccionada.color,
        tienda: prendaSeleccionada.tienda.nombre,
        stock: varianteSeleccionada.stock
    });

    if (!agregado) {
        modalStock.textContent = "Ya tienes en el carrito todas las unidades disponibles de esta talla.";
        modalStock.className = "modal-stock error";
        return;
    }

    JJ.toast(`${prendaSeleccionada.nombre} (${varianteSeleccionada.talla} / ${varianteSeleccionada.color}) agregado al carrito.`, "exito");
    contador.classList.remove("pulso");
    void contador.offsetWidth;
    contador.classList.add("pulso");

    cerrarVistaRapida();
});

/* ==========================================
   ACTUALIZAR CARRITO
========================================== */

function actualizarCarrito() {
    const carrito = JJ.leerCarrito();
    const unidades = carrito.reduce((suma, item) => suma + item.cantidad, 0);
    const subtotal = carrito.reduce((suma, item) => suma + item.precio * item.cantidad, 0);

    contador.textContent = unidades;
    total.textContent = JJ.precio(subtotal);
    actualizarEnvioGratis(subtotal);

    carritoItems.innerHTML = "";

    if (carrito.length === 0) {
        carritoItems.innerHTML = `
            <div class="carrito-vacio">
                <p>Tu carrito está vacío.</p>
                <p>Explora la colección y encuentra tu próxima prenda favorita.</p>
            </div>`;
        return;
    }

    carrito.forEach(item => {
        const fila = document.createElement("div");
        fila.className = "item-carrito";

        const imagen = document.createElement("img");
        imagen.src = item.imagen;
        imagen.alt = "";
        imagen.className = "item-carrito-imagen";

        const informacion = document.createElement("div");
        informacion.className = "item-carrito-info";

        const nombre = document.createElement("h4");
        nombre.textContent = item.nombre;

        const detalles = document.createElement("p");
        detalles.textContent = `Talla: ${item.talla} · Color: ${item.color}`;

        const precio = document.createElement("p");
        precio.className = "item-carrito-precio";
        precio.textContent = JJ.precio(item.precio * item.cantidad);

        const cantidad = document.createElement("div");
        cantidad.className = "cantidad";

        const restar = document.createElement("button");
        restar.type = "button";
        restar.dataset.accion = "restar";
        restar.dataset.variante = item.variante_id;
        restar.textContent = "−";
        restar.setAttribute("aria-label", "Restar una unidad");

        const numero = document.createElement("span");
        numero.textContent = item.cantidad;

        const sumar = document.createElement("button");
        sumar.type = "button";
        sumar.dataset.accion = "sumar";
        sumar.dataset.variante = item.variante_id;
        sumar.textContent = "+";
        sumar.disabled = item.cantidad >= Math.min(item.stock, 20);
        sumar.setAttribute("aria-label", "Agregar una unidad");

        cantidad.append(restar, numero, sumar);
        informacion.append(nombre, detalles, precio, cantidad);

        const eliminar = document.createElement("button");
        eliminar.className = "eliminar";
        eliminar.type = "button";
        eliminar.dataset.accion = "eliminar";
        eliminar.dataset.variante = item.variante_id;
        eliminar.textContent = "✕";
        eliminar.setAttribute("aria-label", `Eliminar ${item.nombre}`);

        fila.append(imagen, informacion, eliminar);
        carritoItems.appendChild(fila);
    });
}

function actualizarEnvioGratis(subtotal) {
    const texto = document.getElementById("envioTexto");
    const barra = document.getElementById("envioBarra");
    const progreso = document.getElementById("envioProgreso");

    progreso.hidden = subtotal === 0;

    if (subtotal >= ENVIO_GRATIS_DESDE) {
        texto.textContent = "¡Tienes envío gratis!";
        barra.style.width = "100%";
    } else {
        texto.textContent = `Te faltan ${JJ.precio(ENVIO_GRATIS_DESDE - subtotal)} para el envío gratis.`;
        barra.style.width = `${Math.round((subtotal / ENVIO_GRATIS_DESDE) * 100)}%`;
    }
}

// Cambiar cantidades o eliminar artículos
carritoItems.addEventListener("click", evento => {
    const boton = evento.target.closest("button[data-accion]");
    if (!boton) return;

    const varianteId = Number(boton.dataset.variante);
    const item = JJ.leerCarrito().find(producto => producto.variante_id === varianteId);

    if (!item) return;

    switch (boton.dataset.accion) {
        case "sumar":
            JJ.cambiarCantidad(varianteId, item.cantidad + 1);
            break;

        case "restar":
            JJ.cambiarCantidad(varianteId, item.cantidad - 1);
            break;

        case "eliminar":
            JJ.cambiarCantidad(varianteId, 0);
            break;
    }
});

document.addEventListener("carrito:cambio", actualizarCarrito);

// Si el carrito cambia en otra pestaña, se refleja aquí
window.addEventListener("storage", evento => {
    if (evento.key === "jj_carrito") actualizarCarrito();
});

/**
 * Al cargar la página se actualizan precios y stock del carrito guardado,
 * por si el vendedor los cambió desde la última visita.
 */
async function sincronizarCarrito() {
    const carrito = JJ.leerCarrito();
    if (!carrito.length) return;

    try {
        const cotizacion = await JJ.api("pedidos.php?accion=cotizar", {
            metodo: "POST",
            datos: { items: carrito.map(({ variante_id, cantidad }) => ({ variante_id, cantidad })) }
        });

        const vigentes = new Map(cotizacion.lineas.map(linea => [linea.variante_id, linea]));
        const actualizado = carrito
            .filter(item => vigentes.has(item.variante_id) && vigentes.get(item.variante_id).stock > 0)
            .map(item => {
                const linea = vigentes.get(item.variante_id);
                return { ...item, precio: linea.precio, stock: linea.stock, cantidad: Math.min(item.cantidad, linea.stock) };
            });

        if (actualizado.length < carrito.length) {
            JJ.toast("Quitamos de tu carrito productos que ya no están disponibles.", "info");
        }

        JJ.guardarCarrito(actualizado);
    } catch {
        // Sin conexión: se mantiene el carrito como estaba
    }
}

/* ==========================================
   ABRIR Y CERRAR CARRITO
========================================== */

function abrirCarrito() {
    panel.classList.add("abierto");
    panel.removeAttribute("inert");
    panel.setAttribute("aria-hidden", "false");
    overlay.classList.add("visible");
    document.body.classList.add("carrito-abierto");
    document.getElementById("cerrarCarrito").focus();
}

function cerrarCarrito() {
    panel.classList.remove("abierto");
    panel.setAttribute("inert", "");
    panel.setAttribute("aria-hidden", "true");
    overlay.classList.remove("visible");
    document.body.classList.remove("carrito-abierto");
}

document.getElementById("abrirCarrito")
    .addEventListener("click", abrirCarrito);

document.getElementById("cerrarCarrito")
    .addEventListener("click", cerrarCarrito);

overlay.addEventListener("click", cerrarCarrito);

// Cerrar ventanas con Escape
document.addEventListener("keydown", evento => {
    if (evento.key !== "Escape") return;

    if (promoOverlay?.classList.contains("visible")) {
        ocultarPromo();
    } else if (modal.classList.contains("abierto")) {
        cerrarVistaRapida();
    } else if (panel.classList.contains("abierto")) {
        cerrarCarrito();
    }
});

/* ==========================================
   IR A PAGAR
========================================== */

document.getElementById("comprarBtn").addEventListener("click", () => {
    if (JJ.leerCarrito().length === 0) {
        JJ.toast("Agrega alguna prenda antes de comprar.", "error");
        return;
    }

    location.href = "checkout.html";
});

/* ==========================================
   MENÚ MÓVIL Y ENCABEZADO
========================================== */

const menuToggle = document.getElementById("menuToggle");
const menuPrincipal = document.getElementById("menuPrincipal");

menuToggle.addEventListener("click", () => {
    const abierto = menuPrincipal.classList.toggle("abierto");
    menuToggle.setAttribute("aria-expanded", String(abierto));
});

menuPrincipal.addEventListener("click", evento => {
    if (!evento.target.closest(".nav-link")) return;

    menuPrincipal.classList.remove("abierto");
    menuToggle.setAttribute("aria-expanded", "false");

    menuPrincipal.querySelectorAll(".nav-link").forEach(enlace => {
        enlace.classList.toggle("activo", enlace === evento.target);
    });
});

window.addEventListener("scroll", () => {
    document.getElementById("navbar").classList.toggle("scrolled", window.scrollY > 10);
}, { passive: true });

/* ==========================================
   INICIAR PÁGINA
========================================== */

actualizarCarrito();
cargarCategorias();
cargarProductos();

JJ.obtenerSesion()
    .then(() => JJ.pintarSesion(document.getElementById("zonaSesion")))
    .then(sincronizarCarrito)
    .catch(() => {});

const anioFooter = document.getElementById("anioFooter");

if (anioFooter) {
    anioFooter.textContent = new Date().getFullYear();
}


/* VENTANA DE PROMOCIÓN JJ STORE (una vez por visita) */
const promoOverlay = document.getElementById("promoOverlay");
const cerrarPromo = document.getElementById("cerrarPromo");
const promoExplorar = document.getElementById("promoExplorar");

let promoAbierta = false;

function promoYaVista() {
    try {
        return sessionStorage.getItem("jj_promo") === "1";
    } catch {
        return false;
    }
}

function abrirPromo() {
    if (!promoOverlay || promoAbierta || promoYaVista()) return;

    promoAbierta = true;
    promoOverlay.classList.add("visible");
    promoOverlay.setAttribute("aria-hidden", "false");
    promoOverlay.removeAttribute("inert");
    document.body.style.overflow = "hidden";
    cerrarPromo.focus();

    try {
        sessionStorage.setItem("jj_promo", "1");
    } catch {
        // sin almacenamiento disponible
    }
}

function ocultarPromo() {
    if (!promoOverlay) return;

    promoOverlay.classList.remove("visible");
    promoOverlay.setAttribute("aria-hidden", "true");
    promoOverlay.setAttribute("inert", "");
    document.body.style.overflow = "";
}

if (promoOverlay && cerrarPromo) {
    // Mostrar después de un pequeño retraso
    window.setTimeout(abrirPromo, 1200);

    cerrarPromo.addEventListener("click", ocultarPromo);

    promoOverlay.addEventListener("click", evento => {
        if (evento.target === promoOverlay) {
            ocultarPromo();
        }
    });

    if (promoExplorar) {
        promoExplorar.addEventListener("click", ocultarPromo);
    }
}
