/* ==========================================
   JJ STORE - PANEL DE VENDEDOR
   Resumen, productos, ventas y datos de la tienda.
========================================== */

const SECCIONES = ["resumen", "productos", "ventas", "tienda"];

const ACCIONES_ESTADO = {
    preparando: { texto: "MARCAR COMO PREPARANDO", clase: "btn-secundario" },
    enviado: { texto: "MARCAR COMO ENVIADO", clase: "btn" },
    entregado: { texto: "MARCAR COMO ENTREGADO", clase: "btn" },
    cancelado: { texto: "CANCELAR VENTA", clase: "btn-secundario btn-peligro" }
};

const { esc, precio } = JJ;

let usuario = null;
let categorias = [];
let opciones = { tallas: [], colores: [] };
let productos = [];
let estadoVentas = "";
let productoEditando = null;
let stockActual = new Map();
let urlVistaPrevia = null;

const panel = document.getElementById("panel");
const dialogoProducto = document.getElementById("dialogoProducto");
const formProducto = document.getElementById("formProducto");

/* ==========================================
   INICIO
========================================== */

async function iniciar() {
    usuario = await JJ.requerirSesion();
    JJ.pintarSesion(document.getElementById("zonaSesion"));
    document.getElementById("cargando").hidden = true;

    if (!usuario.tienda) {
        document.getElementById("abrirTienda").hidden = false;
        return;
    }

    await mostrarPanel();
}

async function mostrarPanel() {
    document.getElementById("abrirTienda").hidden = true;
    panel.hidden = false;
    document.getElementById("navTienda").textContent = usuario.tienda.nombre;
    document.getElementById("saludo").textContent = `Hola, ${usuario.nombre.split(" ")[0]}`;

    [categorias, opciones] = await Promise.all([
        JJ.api("catalogo.php?accion=categorias"),
        JJ.api("catalogo.php?accion=opciones")
    ]);

    document.getElementById("prodCategoria").innerHTML =
        `<option value="">Selecciona...</option>` +
        categorias.map(c => `<option value="${c.id}">${esc(c.nombre)}</option>`).join("");

    irASeccion(location.hash.slice(1));
}

window.addEventListener("hashchange", () => {
    if (!panel.hidden) irASeccion(location.hash.slice(1));
});

function irASeccion(nombre) {
    if (!SECCIONES.includes(nombre)) nombre = "resumen";

    SECCIONES.forEach(seccion => {
        document.getElementById("seccion-" + seccion).hidden = seccion !== nombre;
    });

    document.querySelectorAll(".panel-nav a").forEach(enlace => {
        const activo = enlace.dataset.seccion === nombre;
        enlace.classList.toggle("activo", activo);
        enlace.toggleAttribute("aria-current", activo);
    });

    const cargadores = { resumen: cargarResumen, productos: cargarProductos, ventas: cargarVentas, tienda: cargarTienda };
    cargadores[nombre]().catch(error => JJ.toast(error.message, "error"));
}

/* ==========================================
   ABRIR TIENDA (comprador que quiere vender)
========================================== */

document.getElementById("formAbrirTienda").addEventListener("submit", async evento => {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    JJ.limpiarErrores(formulario);

    try {
        const respuesta = await JJ.conBoton(formulario.querySelector("button[type=submit]"), "CREANDO TIENDA...", () =>
            JJ.api("auth.php?accion=ser-vendedor", { metodo: "POST", datos: Object.fromEntries(new FormData(formulario)) })
        );

        usuario = respuesta.usuario;
        JJ.pintarSesion(document.getElementById("zonaSesion"));
        JJ.toast(`¡Tu tienda ${usuario.tienda.nombre} está abierta! Publica tu primer producto.`, "exito");
        await mostrarPanel();
    } catch (error) {
        JJ.mostrarErrores(formulario, error.errores);
        JJ.toast(error.message, "error");
    }
});

/* ==========================================
   RESUMEN
========================================== */

async function cargarResumen() {
    const datos = await JJ.api("vendedor.php?accion=resumen");

    actualizarContadorVentas(datos.por_despachar);

    document.getElementById("kpis").innerHTML = `
        <div class="kpi destacado">
            <small>VENTAS TOTALES</small>
            <strong>${precio(datos.ventas_total)}</strong>
            <span>${datos.unidades} ${datos.unidades === 1 ? "unidad vendida" : "unidades vendidas"}</span>
        </div>
        <div class="kpi">
            <small>PEDIDOS</small>
            <strong>${datos.pedidos}</strong>
            <span>recibidos en total</span>
        </div>
        <div class="kpi">
            <small>POR DESPACHAR</small>
            <strong>${datos.por_despachar}</strong>
            <span>nuevos o en preparación</span>
        </div>
        <div class="kpi">
            <small>PRODUCTOS ACTIVOS</small>
            <strong>${datos.productos_activos}<span style="font-size:14px"> / ${datos.productos}</span></strong>
            <span>visibles en la tienda</span>
        </div>`;

    pintarGrafico(datos.ultimos_7_dias);

    document.getElementById("masVendidos").innerHTML = datos.mas_vendidos.length
        ? datos.mas_vendidos.map((producto, indice) => `
            <li>
                <span>${indice + 1}. ${esc(producto.nombre)}
                    <small>${producto.unidades} ${producto.unidades === 1 ? "unidad" : "unidades"}</small></span>
                <strong>${precio(producto.total)}</strong>
            </li>`).join("")
        : `<li class="texto-suave">Aún no tienes ventas. ¡Comparte tu tienda!</li>`;

    document.getElementById("stockBajo").innerHTML = datos.stock_bajo.length
        ? datos.stock_bajo.map(variante => `
            <li>
                <span>${esc(variante.nombre)}
                    <small>Talla ${esc(variante.talla)} · ${esc(variante.color)}</small></span>
                <span class="grupo-botones" style="align-items:center">
                    <span class="stock-num ${variante.stock === 0 ? "cero" : "bajo"}">${variante.stock}</span>
                    <button type="button" class="btn-secundario btn-pequeno" data-editar="${variante.producto_id}">REPONER</button>
                </span>
            </li>`).join("")
        : `<li class="texto-suave">Todo tu inventario está bien surtido.</li>`;
}

function pintarGrafico(dias) {
    const maximo = Math.max(...dias.map(dia => dia.total), 1);
    const compacto = new Intl.NumberFormat("es-CO", { notation: "compact", maximumFractionDigits: 1 });

    document.getElementById("grafico").innerHTML = dias.map(dia => {
        const altura = Math.max((dia.total / maximo) * 100, 2);
        const nombreDia = new Date(dia.dia + "T00:00:00").toLocaleDateString("es-CO", { weekday: "short" });

        return `
            <div class="barra" title="${esc(JJ.fecha(dia.dia))}: ${precio(dia.total)}">
                <span class="barra-valor">${dia.total ? "$" + compacto.format(dia.total) : ""}</span>
                <span class="barra-relleno ${dia.total ? "" : "cero"}" style="height:${altura}%"></span>
                <span class="barra-dia">${esc(nombreDia.replace(".", ""))}</span>
            </div>`;
    }).join("");
}

document.getElementById("stockBajo").addEventListener("click", async evento => {
    const boton = evento.target.closest("[data-editar]");
    if (!boton) return;

    if (!productos.length) productos = await JJ.api("vendedor.php?accion=productos");
    abrirEditor(productos.find(producto => producto.id === Number(boton.dataset.editar)));
});

function actualizarContadorVentas(cantidad) {
    const contador = document.getElementById("contVentas");
    contador.textContent = cantidad;
    contador.hidden = !cantidad;
}

/* ==========================================
   PRODUCTOS
========================================== */

async function cargarProductos() {
    productos = await JJ.api("vendedor.php?accion=productos");
    pintarProductos();
}

function pintarProductos() {
    const contenedor = document.getElementById("tablaProductos");
    const texto = document.getElementById("filtroProductos").value.trim().toLowerCase();
    const filtrados = productos.filter(producto =>
        producto.nombre.toLowerCase().includes(texto) || producto.categoria.toLowerCase().includes(texto)
    );

    if (!productos.length) {
        contenedor.innerHTML = `
            <div class="vacio">
                <h2>Aún no tienes productos</h2>
                <p>Publica tu primera prenda con sus tallas, colores y stock.</p>
                <button type="button" class="btn" data-nuevo-producto>+ PUBLICAR PRODUCTO</button>
            </div>`;
        return;
    }

    if (!filtrados.length) {
        contenedor.innerHTML = `<p class="alerta alerta-info">Ningún producto coincide con "${esc(texto)}".</p>`;
        return;
    }

    contenedor.innerHTML = `
        <div class="tabla-contenedor">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Precio</th>
                        <th>Stock</th>
                        <th>Vendidos</th>
                        <th>Visible</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    ${filtrados.map(producto => `
                        <tr class="${producto.activo ? "" : "fila-inactiva"}">
                            <td>
                                <div class="producto-celda">
                                    <img src="${esc(producto.imagen)}" alt="">
                                    <div>
                                        <strong>${esc(producto.nombre)}</strong>
                                        <small>${esc(producto.categoria)}</small>
                                    </div>
                                </div>
                            </td>
                            <td><strong>${precio(producto.precio)}</strong></td>
                            <td>
                                <span class="stock-num ${producto.stock_total === 0 ? "cero" : producto.stock_total <= 5 ? "bajo" : ""}">
                                    ${producto.stock_total}
                                </span>
                                <small class="stock-variantes">${producto.variantes.length} variantes</small>
                            </td>
                            <td>${producto.vendidos}</td>
                            <td>
                                <label class="interruptor">
                                    <input type="checkbox" data-visible="${producto.id}" ${producto.activo ? "checked" : ""}
                                           aria-label="Mostrar ${esc(producto.nombre)} en la tienda">
                                    <span class="pista"></span>
                                    <span>${producto.activo ? "Sí" : "No"}</span>
                                </label>
                            </td>
                            <td>
                                <div class="grupo-botones" style="flex-wrap:nowrap">
                                    <button type="button" class="btn-secundario btn-pequeno" data-editar="${producto.id}">EDITAR</button>
                                    <button type="button" class="btn-secundario btn-pequeno btn-peligro" data-eliminar="${producto.id}">ELIMINAR</button>
                                </div>
                            </td>
                        </tr>`).join("")}
                </tbody>
            </table>
        </div>`;
}

document.getElementById("filtroProductos").addEventListener("input", pintarProductos);

const tablaProductos = document.getElementById("tablaProductos");

tablaProductos.addEventListener("click", async evento => {
    const editar = evento.target.closest("[data-editar]");
    const eliminar = evento.target.closest("[data-eliminar]");

    if (editar) {
        abrirEditor(productos.find(producto => producto.id === Number(editar.dataset.editar)));
    }

    if (eliminar) {
        const producto = productos.find(item => item.id === Number(eliminar.dataset.eliminar));
        const aceptado = await confirmar(
            "Eliminar producto",
            `¿Seguro que quieres eliminar "${producto.nombre}"? Las ventas que ya tiene se conservan en tu historial.`,
            "ELIMINAR"
        );

        if (!aceptado) return;

        try {
            await JJ.api("vendedor.php?accion=eliminar-producto", { metodo: "POST", datos: { id: producto.id } });
            JJ.toast(`"${producto.nombre}" fue eliminado.`, "exito");
            await cargarProductos();
        } catch (error) {
            JJ.toast(error.message, "error");
        }
    }
});

tablaProductos.addEventListener("change", async evento => {
    const interruptor = evento.target.closest("[data-visible]");
    if (!interruptor) return;

    const id = Number(interruptor.dataset.visible);

    try {
        await JJ.api("vendedor.php?accion=estado-producto", {
            metodo: "POST",
            datos: { id, activo: interruptor.checked }
        });

        productos.find(producto => producto.id === id).activo = interruptor.checked;
        pintarProductos();
        JJ.toast(interruptor.checked ? "El producto ya se ve en la tienda." : "El producto quedó oculto.", "exito");
    } catch (error) {
        interruptor.checked = !interruptor.checked;
        JJ.toast(error.message, "error");
    }
});

// Botones "+ NUEVO PRODUCTO" (hay varios en la página)
document.addEventListener("click", evento => {
    if (evento.target.closest("[data-nuevo-producto]")) abrirEditor(null);
});

/* ==========================================
   FORMULARIO DE PRODUCTO (crear / editar)
========================================== */

function abrirEditor(producto) {
    productoEditando = producto || null;
    formProducto.reset();
    JJ.limpiarErrores(formProducto);

    document.getElementById("tituloProducto").textContent = producto ? "Editar producto" : "Nuevo producto";
    document.getElementById("prodNombre").value = producto?.nombre ?? "";
    document.getElementById("prodCategoria").value = producto?.categoria_id ?? "";
    document.getElementById("prodPrecio").value = producto ? Math.round(producto.precio) : "";
    document.getElementById("prodDescripcion").value = producto?.descripcion ?? "";
    document.getElementById("prodActivo").checked = producto ? producto.activo : true;

    mostrarVistaPrevia(producto?.imagen ?? null);

    // Variantes actuales (o una sugerencia para productos nuevos)
    stockActual = new Map();
    const tallas = new Set(producto ? producto.variantes.map(v => v.talla) : ["S", "M", "L", "XL"]);
    const colores = new Set(producto ? producto.variantes.map(v => v.color) : ["Negro"]);

    producto?.variantes.forEach(variante => {
        stockActual.set(`${variante.talla}|${variante.color}`, variante.stock);
    });

    document.getElementById("chipsTallas").innerHTML = opciones.tallas.map(talla => `
        <label class="chip">
            <input type="checkbox" data-talla="${esc(talla)}" ${tallas.has(talla) ? "checked" : ""}>
            <span>${esc(talla)}</span>
        </label>`).join("");

    document.getElementById("chipsColores").innerHTML = opciones.colores.map(color => `
        <label class="chip">
            <input type="checkbox" data-color="${esc(color)}" ${colores.has(color) ? "checked" : ""}>
            <span><i class="muestra" style="background:${JJ.colorMuestra(color)}"></i>${esc(color)}</span>
        </label>`).join("");

    pintarMatriz();
    dialogoProducto.showModal();
    document.getElementById("prodNombre").focus();
}

function seleccionados(atributo) {
    return [...formProducto.querySelectorAll(`[data-${atributo}]:checked`)].map(input => input.dataset[atributo]);
}

/** Tabla de stock: una fila por color y una columna por talla. */
function pintarMatriz() {
    const tallas = seleccionados("talla");
    const colores = seleccionados("color");
    const contenedor = document.getElementById("matrizStock");

    if (!tallas.length || !colores.length) {
        contenedor.innerHTML = `<p class="texto-suave">Selecciona al menos una talla y un color.</p>`;
        return;
    }

    contenedor.innerHTML = `
        <table class="matriz-stock">
            <thead>
                <tr><th>Color \\ Talla</th>${tallas.map(talla => `<th>${esc(talla)}</th>`).join("")}</tr>
            </thead>
            <tbody>
                ${colores.map(color => `
                    <tr>
                        <th><i class="muestra" style="display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:6px;background:${JJ.colorMuestra(color)}"></i>${esc(color)}</th>
                        ${tallas.map(talla => {
                            const clave = `${talla}|${color}`;
                            return `<td><input type="number" min="0" max="9999" inputmode="numeric"
                                        data-clave="${esc(clave)}" value="${stockActual.get(clave) ?? 0}"
                                        aria-label="Stock ${esc(color)} talla ${esc(talla)}"></td>`;
                        }).join("")}
                    </tr>`).join("")}
            </tbody>
        </table>`;
}

formProducto.addEventListener("change", evento => {
    if (evento.target.matches("[data-talla], [data-color]")) pintarMatriz();
});

// Al entrar a una celda de stock se selecciona el número para reemplazarlo de una vez
formProducto.addEventListener("focusin", evento => {
    if (evento.target.matches("[data-clave]")) evento.target.select();
});

formProducto.addEventListener("input", evento => {
    if (evento.target.matches("[data-clave]")) {
        stockActual.set(evento.target.dataset.clave, Number(evento.target.value) || 0);
    }
});

// Vista previa de la imagen
function mostrarVistaPrevia(src) {
    const vista = document.getElementById("vistaImagen");
    vista.innerHTML = src ? `<img src="${esc(src)}" alt="Vista previa">` : "Sin imagen";
}

document.getElementById("prodImagen").addEventListener("change", evento => {
    const archivo = evento.target.files[0];

    if (urlVistaPrevia) URL.revokeObjectURL(urlVistaPrevia);

    if (!archivo) {
        mostrarVistaPrevia(productoEditando?.imagen ?? null);
        return;
    }

    if (archivo.size > 2 * 1024 * 1024) {
        JJ.toast("La imagen no puede superar 2 MB.", "error");
        evento.target.value = "";
        return;
    }

    urlVistaPrevia = URL.createObjectURL(archivo);
    mostrarVistaPrevia(urlVistaPrevia);
});

document.getElementById("prodImagenUrl").addEventListener("change", evento => {
    const url = evento.target.value.trim();
    if (/^https?:\/\//i.test(url)) mostrarVistaPrevia(url);
});

dialogoProducto.querySelectorAll("[data-cerrar]").forEach(boton => {
    boton.addEventListener("click", () => dialogoProducto.close());
});

formProducto.addEventListener("submit", async evento => {
    evento.preventDefault();
    JJ.limpiarErrores(formProducto);

    const datos = new FormData(formProducto);
    const tallas = seleccionados("talla");
    const colores = seleccionados("color");

    const variantes = colores.flatMap(color => tallas.map(talla => ({
        talla,
        color,
        stock: stockActual.get(`${talla}|${color}`) ?? 0
    })));

    datos.set("variantes", JSON.stringify(variantes));
    datos.set("activo", document.getElementById("prodActivo").checked ? "1" : "0");

    if (!document.getElementById("prodImagen").files.length) datos.delete("imagen");
    if (productoEditando) datos.set("id", productoEditando.id);

    try {
        await JJ.conBoton(document.getElementById("guardarProducto"), "GUARDANDO...", () =>
            JJ.api("vendedor.php?accion=guardar-producto", { metodo: "POST", formulario: datos })
        );

        dialogoProducto.close();
        JJ.toast(productoEditando ? "Producto actualizado." : "¡Producto publicado!", "exito");

        if (location.hash === "#productos") {
            await cargarProductos();
        } else {
            location.hash = "#productos";
        }
    } catch (error) {
        JJ.mostrarErrores(formProducto, error.errores);
        JJ.toast(error.message, "error");
    }
});

/* ==========================================
   VENTAS
========================================== */

async function cargarVentas() {
    const lista = document.getElementById("listaVentas");
    lista.innerHTML = `<p class="cargando-pagina">Cargando ventas...</p>`;

    const ventas = await JJ.api("vendedor.php?accion=ventas&estado=" + encodeURIComponent(estadoVentas));

    if (!ventas.length) {
        lista.innerHTML = `
            <div class="vacio">
                <h2>${estadoVentas ? "No hay ventas en este estado" : "Aún no tienes ventas"}</h2>
                <p>Cuando alguien compre tus productos, aparecerán aquí y te llegará un correo.</p>
            </div>`;
        return;
    }

    lista.innerHTML = ventas.map(venta => {
        const c = venta.comprador;
        const botones = venta.transiciones.map(estado => `
            <button type="button" class="${ACCIONES_ESTADO[estado].clase} btn-pequeno"
                    data-pedido="${venta.id}" data-codigo="${esc(venta.codigo)}" data-estado="${estado}">
                ${ACCIONES_ESTADO[estado].texto}
            </button>`).join("");

        return `
            <article class="tarjeta venta-tarjeta">
                <div class="pedido-tarjeta-cabecera">
                    <div>
                        <h2>${esc(venta.codigo)}</h2>
                        <p>${esc(JJ.fecha(venta.creado_en, true))} · ${esc(c.nombre)}</p>
                    </div>
                    <div class="pedido-total">
                        <span class="chip-estado estado-${esc(venta.estado)}">${esc(JJ.ESTADOS[venta.estado])}</span>
                        <strong style="margin-top:8px">${precio(venta.total_tienda)}</strong>
                    </div>
                </div>

                <div class="venta-grid">
                    <div class="venta-items">
                        ${venta.items.map(item => `
                            <div class="linea-producto">
                                <img src="${esc(item.imagen)}" alt="">
                                <div>
                                    <h3>${esc(item.nombre)}</h3>
                                    <p>Talla ${esc(item.talla)} · ${esc(item.color)} · ${item.cantidad} × ${precio(item.precio_unitario)}</p>
                                </div>
                                <span class="valor">${precio(item.subtotal)}</span>
                            </div>`).join("")}
                    </div>

                    <div class="venta-envio">
                        <span class="etiqueta-bloque">ENVIAR A</span>
                        <strong>${esc(c.nombre)}</strong><br>
                        ${esc(c.telefono)} · ${esc(c.email)}<br>
                        ${esc(c.direccion)}<br>
                        ${esc(c.ciudad)}, ${esc(c.departamento)}
                        ${c.notas ? `<br><em>“${esc(c.notas)}”</em>` : ""}
                        <span class="etiqueta-bloque" style="margin-top:12px">PAGO</span>
                        ${esc(venta.pago.detalle)} ·
                        ${venta.pago.estado === "aprobado" ? "Aprobado" : "<strong>Pendiente: se cobra al entregar</strong>"}
                    </div>
                </div>

                ${botones ? `<div class="pedido-tarjeta-pie" style="margin-top:18px">
                    <span class="texto-suave">Siguiente paso:</span>
                    <div class="grupo-botones">${botones}</div>
                </div>` : ""}
            </article>`;
    }).join("");
}

document.getElementById("filtrosVentas").addEventListener("click", evento => {
    const boton = evento.target.closest(".filtro");
    if (!boton) return;

    estadoVentas = boton.dataset.estado;
    document.querySelectorAll("#filtrosVentas .filtro").forEach(filtro => {
        filtro.classList.toggle("activo", filtro === boton);
    });

    cargarVentas().catch(error => JJ.toast(error.message, "error"));
});

document.getElementById("listaVentas").addEventListener("click", async evento => {
    const boton = evento.target.closest("[data-estado]");
    if (!boton) return;

    const { pedido, codigo, estado } = boton.dataset;

    if (estado === "cancelado") {
        const aceptado = await confirmar(
            "Cancelar venta",
            `¿Cancelar tus productos del pedido ${codigo}? Las unidades vuelven a tu inventario y le avisaremos al comprador.`,
            "SÍ, CANCELAR"
        );
        if (!aceptado) return;
    }

    try {
        await JJ.conBoton(boton, "GUARDANDO...", () =>
            JJ.api("vendedor.php?accion=estado-venta", { metodo: "POST", datos: { pedido_id: Number(pedido), estado } })
        );

        JJ.toast(`Pedido ${codigo}: ${JJ.ESTADOS[estado].toLowerCase()}. Le avisamos al comprador por correo.`, "exito");
        await cargarVentas();

        const resumen = await JJ.api("vendedor.php?accion=resumen");
        actualizarContadorVentas(resumen.por_despachar);
    } catch (error) {
        JJ.toast(error.message, "error");
    }
});

/* ==========================================
   MI TIENDA
========================================== */

async function cargarTienda() {
    const tienda = await JJ.api("vendedor.php?accion=tienda");
    const formulario = document.getElementById("formTienda");

    formulario.elements.nombre.value = tienda.nombre;
    formulario.elements.ciudad.value = tienda.ciudad ?? "";
    formulario.elements.descripcion.value = tienda.descripcion ?? "";
}

document.getElementById("formTienda").addEventListener("submit", async evento => {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    JJ.limpiarErrores(formulario);

    try {
        const tienda = await JJ.conBoton(formulario.querySelector("button[type=submit]"), "GUARDANDO...", () =>
            JJ.api("vendedor.php?accion=guardar-tienda", { metodo: "POST", datos: Object.fromEntries(new FormData(formulario)) })
        );

        document.getElementById("navTienda").textContent = tienda.nombre;
        await JJ.obtenerSesion(true);
        JJ.pintarSesion(document.getElementById("zonaSesion"));
        JJ.toast("Datos de la tienda guardados.", "exito");
    } catch (error) {
        JJ.mostrarErrores(formulario, error.errores);
        JJ.toast(error.message, "error");
    }
});

/* ==========================================
   CONFIRMACIÓN (reemplaza al confirm() del navegador)
========================================== */

function confirmar(titulo, texto, textoBoton = "CONFIRMAR") {
    const dialogo = document.getElementById("dialogoConfirmar");

    document.getElementById("confirmarTitulo").textContent = titulo;
    document.getElementById("confirmarTexto").textContent = texto;
    document.getElementById("confirmarSi").textContent = textoBoton;

    dialogo.returnValue = "";
    dialogo.showModal();

    return new Promise(resolver => {
        dialogo.addEventListener("close", () => resolver(dialogo.returnValue === "si"), { once: true });
    });
}

iniciar().catch(error => {
    document.getElementById("cargando").textContent = error.message;
});
