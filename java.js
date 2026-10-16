
/* ==========================================
   JJ STORE - CATÁLOGO Y VISTA RÁPIDA
========================================== */

const prendas = [
    {
        id: 1,
        nombre: "Camiseta Oversize Black",
        categoria: "camisetas",
        precio: 65000,
        imagen: "https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=600&q=80"
    },
    {
        id: 2,
        nombre: "Camiseta Urban White",
        categoria: "camisetas",
        precio: 55000,
        imagen: "https://images.unsplash.com/photo-1503341504253-dff4815485f1?auto=format&fit=crop&w=600&q=80"
    },
    {
        id: 3,
        nombre: "Camiseta Streetwear",
        categoria: "camisetas",
        precio: 70000,
        imagen: "https://images.unsplash.com/photo-1523398002811-999ca8dec234?auto=format&fit=crop&w=600&q=80"
    },
    {
        id: 4,
        nombre: "Camiseta Minimal",
        categoria: "camisetas",
        precio: 60000,
        imagen: "https://images.unsplash.com/photo-1576566588028-4147f3842f27?auto=format&fit=crop&w=600&q=80"
    },
    {
        id: 5,
        nombre: "Pantalón Cargo",
        categoria: "pantalones",
        precio: 120000,
        imagen: "https://images.unsplash.com/photo-1517438476312-10d79c077509?auto=format&fit=crop&w=600&q=80"
    },
    {
        id: 6,
        nombre: "Jeans Urban Blue",
        categoria: "pantalones",
        precio: 110000,
        imagen: "https://images.unsplash.com/photo-1542272604-787c3835535d?auto=format&fit=crop&w=600&q=80"
    },
    {
        id: 7,
        nombre: "Chaqueta Street",
        categoria: "chaquetas",
        precio: 180000,
        imagen: "https://images.unsplash.com/photo-1551028719-00167b16eac5?auto=format&fit=crop&w=600&q=80"
    },
    {
        id: 8,
        nombre: "Chaqueta Denim",
        categoria: "chaquetas",
        precio: 160000,
        imagen: "https://images.unsplash.com/photo-1543076447-215ad9ba6923?auto=format&fit=crop&w=600&q=80"
    }
];

let carrito = [];
let categoriaActual = "todos";
let prendaSeleccionada = null;
let ultimoElementoModal = null;

const contenedor = document.getElementById("productos");
const buscador = document.getElementById("buscador");
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
const modalAgregar = document.getElementById("modalAgregar");
const cerrarModalBtn = document.getElementById("cerrarModal");

// Formatear precios en pesos colombianos
function formatoPrecio(precio) {
    return precio.toLocaleString("es-CO", {
        style: "currency",
        currency: "COP",
        maximumFractionDigits: 0
    });
}

/* ==========================================
   MOSTRAR PRODUCTOS
========================================== */

function mostrarProductos() {
    const texto = buscador.value.toLowerCase().trim();

    const filtradas = prendas.filter(prenda => {
        const coincideTexto =
            prenda.nombre.toLowerCase().includes(texto) ||
            prenda.categoria.toLowerCase().includes(texto);

        const coincideCategoria =
            categoriaActual === "todos" ||
            prenda.categoria === categoriaActual;

        return coincideTexto && coincideCategoria;
    });

    contenedor.innerHTML = "";

    document.getElementById("sinResultados").hidden =
        filtradas.length > 0;

    filtradas.forEach(prenda => {
        const tarjeta = document.createElement("article");
        tarjeta.className = "producto";

        const imagen = document.createElement("img");
        imagen.src = prenda.imagen;
        imagen.alt = prenda.nombre;
        imagen.loading = "lazy";

        const informacion = document.createElement("div");
        informacion.className = "producto-info";

        const categoria = document.createElement("p");
        categoria.className = "categoria";
        categoria.textContent = prenda.categoria;

        const nombre = document.createElement("h3");
        nombre.textContent = prenda.nombre;

        const precio = document.createElement("p");
        precio.className = "producto-precio";
        precio.textContent = formatoPrecio(prenda.precio);

        const botonDetalles = document.createElement("button");
        botonDetalles.className = "ver-detalles";
        botonDetalles.type = "button";
        botonDetalles.dataset.id = prenda.id;
        botonDetalles.textContent = "VER PRENDA +";

        informacion.append(categoria, nombre, precio, botonDetalles);
        tarjeta.append(imagen, informacion);
        contenedor.appendChild(tarjeta);
    });
}

// Buscar mientras escribes
buscador.addEventListener("input", mostrarProductos);

document.getElementById("buscarBtn").addEventListener("click", () => {
    mostrarProductos();
});

// Filtrar categorías
document.querySelectorAll(".filtro").forEach(boton => {
    boton.addEventListener("click", () => {
        categoriaActual = boton.dataset.categoria;

        document.querySelectorAll(".filtro").forEach(filtro => {
            filtro.classList.remove("activo");
        });

        boton.classList.add("activo");
        mostrarProductos();
    });
});

/* ==========================================
   VISTA RÁPIDA
========================================== */

function abrirVistaRapida(id, elementoOrigen) {
    const prenda = prendas.find(item => item.id === Number(id));

    if (!prenda || !modal) return;

    prendaSeleccionada = prenda;
    ultimoElementoModal = elementoOrigen;

    modalImagen.src = prenda.imagen;
    modalImagen.alt = prenda.nombre;
    modalNombre.textContent = prenda.nombre;
    modalPrecio.textContent = formatoPrecio(prenda.precio);

    // Reiniciar selección de talla
    document.querySelectorAll("[data-talla]").forEach(boton => {
        boton.classList.remove("seleccionado");
        boton.setAttribute("aria-pressed", "false");
    });

    // Reiniciar color al negro
    document.querySelectorAll(".color-opcion").forEach(boton => {
        const seleccionado = boton.dataset.color === "Negro";

        boton.classList.toggle("seleccionado", seleccionado);
        boton.setAttribute("aria-pressed", String(seleccionado));
    });

    modal.classList.add("abierto");
    modal.setAttribute("aria-hidden", "false");
    modal.removeAttribute("inert");
    document.body.style.overflow = "hidden";

    cerrarModalBtn.focus();
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

// Seleccionar talla
document.querySelectorAll("[data-talla]").forEach(boton => {
    boton.setAttribute("aria-pressed", "false");

    boton.addEventListener("click", () => {
        document.querySelectorAll("[data-talla]").forEach(opcion => {
            opcion.classList.remove("seleccionado");
            opcion.setAttribute("aria-pressed", "false");
        });

        boton.classList.add("seleccionado");
        boton.setAttribute("aria-pressed", "true");
    });
});

// Seleccionar color
document.querySelectorAll(".color-opcion").forEach(boton => {
    boton.addEventListener("click", () => {
        document.querySelectorAll(".color-opcion").forEach(opcion => {
            opcion.classList.remove("seleccionado");
            opcion.setAttribute("aria-pressed", "false");
        });

        boton.classList.add("seleccionado");
        boton.setAttribute("aria-pressed", "true");
    });
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

function agregarAlCarrito(prenda, talla, color) {
    // Cada combinación de prenda, talla y color es independiente
    const clave = `${prenda.id}-${talla}-${color}`;

    const existente = carrito.find(item => item.clave === clave);

    if (existente) {
        existente.cantidad++;
    } else {
        carrito.push({
            ...prenda,
            clave,
            talla,
            color,
            cantidad: 1
        });
    }

    actualizarCarrito();
}

// Agregar desde la vista rápida
modalAgregar.addEventListener("click", () => {
    if (!prendaSeleccionada) return;

    const tallaElegida = document.querySelector(
        "[data-talla].seleccionado"
    );

    const colorElegido = document.querySelector(
        ".color-opcion.seleccionado"
    );

    if (!tallaElegida) {
        alert("Por favor, selecciona una talla.");
        return;
    }

    if (!colorElegido) {
        alert("Por favor, selecciona un color.");
        return;
    }

    const nombrePrenda = prendaSeleccionada.nombre;

    agregarAlCarrito(
        prendaSeleccionada,
        tallaElegida.dataset.talla,
        colorElegido.dataset.color
    );

    modalAgregar.textContent = "¡AGREGADO AL CARRITO! ✓";

    setTimeout(() => {
        modalAgregar.textContent = "AGREGAR AL CARRITO +";
    }, 1200);

    cerrarVistaRapida();
});

/* ==========================================
   ACTUALIZAR CARRITO
========================================== */

function actualizarCarrito() {
    const unidades = carrito.reduce(
        (suma, item) => suma + item.cantidad,
        0
    );

    const sumaTotal = carrito.reduce(
        (suma, item) => suma + item.precio * item.cantidad,
        0
    );

    contador.textContent = unidades;
    total.textContent = formatoPrecio(sumaTotal);

    carritoItems.innerHTML = "";

    if (carrito.length === 0) {
        carritoItems.innerHTML =
            "<p style='color:#aaa;padding:20px 0'>Tu carrito está vacío.</p>";
        return;
    }

    carrito.forEach(item => {
        const fila = document.createElement("div");
        fila.className = "item-carrito";

        const informacion = document.createElement("div");

        const nombre = document.createElement("h4");
        nombre.textContent = item.nombre;

        const precio = document.createElement("p");
        precio.textContent = formatoPrecio(item.precio);

        const detalles = document.createElement("p");
        detalles.textContent = `Talla: ${item.talla} · Color: ${item.color}`;

        const cantidad = document.createElement("div");
        cantidad.className = "cantidad";

        const restar = document.createElement("button");
        restar.type = "button";
        restar.dataset.accion = "restar";
        restar.dataset.clave = item.clave;
        restar.textContent = "−";
        restar.setAttribute("aria-label", "Restar una unidad");

        const numero = document.createElement("span");
        numero.textContent = item.cantidad;

        const sumar = document.createElement("button");
        sumar.type = "button";
        sumar.dataset.accion = "sumar";
        sumar.dataset.clave = item.clave;
        sumar.textContent = "+";
        sumar.setAttribute("aria-label", "Agregar una unidad");

        cantidad.append(restar, numero, sumar);
        informacion.append(nombre, precio, detalles, cantidad);

        const eliminar = document.createElement("button");
        eliminar.className = "eliminar";
        eliminar.type = "button";
        eliminar.dataset.accion = "eliminar";
        eliminar.dataset.clave = item.clave;
        eliminar.textContent = "✕";
        eliminar.setAttribute("aria-label", `Eliminar ${item.nombre}`);

        fila.append(informacion, eliminar);
        carritoItems.appendChild(fila);
    });
}

// Cambiar cantidades o eliminar artículos
carritoItems.addEventListener("click", evento => {
    const boton = evento.target.closest("button");
    if (!boton) return;

    const clave = boton.dataset.clave;
    const item = carrito.find(prenda => prenda.clave === clave);

    if (!item) return;

    switch (boton.dataset.accion) {
        case "sumar":
            item.cantidad++;
            break;

        case "restar":
            item.cantidad--;

            if (item.cantidad <= 0) {
                carrito = carrito.filter(
                    prenda => prenda.clave !== clave
                );
            }
            break;

        case "eliminar":
            carrito = carrito.filter(
                prenda => prenda.clave !== clave
            );
            break;

        default:
            return;
    }

    actualizarCarrito();
});

/* ==========================================
   ABRIR Y CERRAR CARRITO
========================================== */

function abrirCarrito() {
    panel.classList.add("abierto");
    overlay.classList.add("visible");
}

function cerrarCarrito() {
    panel.classList.remove("abierto");
    overlay.classList.remove("visible");
}

document.getElementById("abrirCarrito")
    .addEventListener("click", abrirCarrito);

document.getElementById("cerrarCarrito")
    .addEventListener("click", cerrarCarrito);

overlay.addEventListener("click", cerrarCarrito);

// Cerrar ventanas con Escape
document.addEventListener("keydown", evento => {
    if (evento.key !== "Escape") return;

    if (modal.classList.contains("abierto")) {
        cerrarVistaRapida();
    } else {
        cerrarCarrito();
    }
});

/* ==========================================
   PREPARAR PEDIDO DE DEMOSTRACIÓN
========================================== */

document.getElementById("comprarBtn").addEventListener("click", () => {
    if (carrito.length === 0) {
        alert("Agrega alguna prenda antes de comprar.");
        return;
    }

    const detalle = carrito.map(item =>
        `${item.nombre} | Talla ${item.talla} | Color ${item.color} | Cantidad: ${item.cantidad} = ${
            formatoPrecio(item.precio * item.cantidad)
        }`
    ).join("\n");

    const sumaTotal = carrito.reduce(
        (suma, item) => suma + item.precio * item.cantidad, 0
    );

    alert(
        "RESUMEN DE TU PEDIDO\n\n" +
        detalle +
        "\n\nTOTAL: " + formatoPrecio(sumaTotal) +
        "\n\nEsta es una demostración. Todavía no se procesa ningún pago."
    );
});

/* ==========================================
   INICIAR PÁGINA
========================================== */

mostrarProductos();
actualizarCarrito();


const anioFooter = document.getElementById("anioFooter");

if (anioFooter) {
    anioFooter.textContent = new Date().getFullYear();
}


/* VENTANA DE PROMOCIÓN JJ STORE */
const promoOverlay = document.getElementById("promoOverlay");
const cerrarPromo = document.getElementById("cerrarPromo");
const promoExplorar = document.getElementById("promoExplorar");

let promoAbierta = false;

function abrirPromo() {
    if (!promoOverlay || promoAbierta) return;

    promoAbierta = true;
    promoOverlay.classList.add("visible");
    promoOverlay.setAttribute("aria-hidden", "false");
    promoOverlay.removeAttribute("inert");
    document.body.style.overflow = "hidden";
    cerrarPromo.focus();
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
