/* ==========================================
   JJ STORE - UTILIDADES COMPARTIDAS
   Conexión con la API, sesión, carrito y ayudas
   que usan todas las páginas.
========================================== */

class ErrorApi extends Error {
    constructor(mensaje, estado = 0, errores = {}) {
        super(mensaje);
        this.estado = estado;
        this.errores = errores;
    }
}

const JJ = (() => {

    // Color de muestra para cada color de prenda
    const COLORES = {
        "Negro": "#111111",
        "Blanco": "#f2f1ed",
        "Gris": "#8a8a8a",
        "Azul": "#2f4a7a",
        "Beige": "#d6c7a8",
        "Verde": "#4d5e3c",
        "Rojo": "#9b2c2c",
        "Café": "#6b4a32",
        "Rosa": "#e3b5b8",
        "Naranja": "#d9822b",
        "Terracota": "#b0573a"
    };

    const ESTADOS = {
        nuevo: "Nuevo",
        preparando: "Preparando",
        enviado: "Enviado",
        entregado: "Entregado",
        cancelado: "Cancelado"
    };

    const CLAVE_CARRITO = "jj_carrito";
    const MAX_POR_ITEM = 20;

    let sesion = null;
    let sesionPendiente = null;

    /* ---------- API ---------- */

    async function api(ruta, { metodo = "GET", datos = null, formulario = null, reintento = false } = {}) {
        const opciones = {
            method: metodo,
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        };

        if (metodo !== "GET") {
            const actual = await obtenerSesion();
            opciones.headers["X-CSRF-Token"] = actual.csrf;

            if (formulario) {
                opciones.body = formulario;
            } else {
                opciones.headers["Content-Type"] = "application/json";
                opciones.body = JSON.stringify(datos ?? {});
            }
        }

        let respuesta;

        try {
            respuesta = await fetch("api/" + ruta, opciones);
        } catch {
            throw new ErrorApi(location.protocol === "file:"
                ? "Abriste el archivo directamente. El sitio debe abrirse desde XAMPP o WAMP, por ejemplo http://localhost/JJSTORE/"
                : "No hay conexión con el servidor. Revisa que Apache esté encendido.");
        }

        let texto = "";
        let cuerpo = null;

        try {
            texto = await respuesta.text();
            cuerpo = JSON.parse(texto);
        } catch {
            cuerpo = null;
        }

        // Token CSRF vencido: se pide uno nuevo y se reintenta una vez
        if (respuesta.status === 403 && cuerpo?.tipo === "csrf" && !reintento) {
            await obtenerSesion(true);
            return api(ruta, { metodo, datos, formulario, reintento: true });
        }

        if (!respuesta.ok || !cuerpo || !cuerpo.ok) {
            throw new ErrorApi(
                cuerpo?.error || explicarRespuesta(respuesta.status, texto),
                respuesta.status,
                cuerpo?.errores || {}
            );
        }

        const resultado = cuerpo.datos;

        // Las respuestas de login/registro/logout traen la sesión nueva
        if (resultado && typeof resultado === "object" && "csrf" in resultado) {
            sesion = { usuario: resultado.usuario, csrf: resultado.csrf };
        }

        return resultado;
    }

    /**
     * La API no respondió con JSON: casi siempre es un problema de instalación.
     * Se explica la causa probable y cómo revisarla.
     */
    function explicarRespuesta(estado, texto) {
        const diagnostico = new URL("diagnostico.php", location.href).href;

        if (texto.includes("<?php") || estado === 405) {
            return "El servidor no está ejecutando PHP (por ejemplo, se abrió con Live Server). " +
                "Abre el sitio desde XAMPP o WAMP, por ejemplo http://localhost/JJSTORE/";
        }

        if (/parse error|syntax error/i.test(texto)) {
            return "La versión de PHP es muy antigua para este proyecto: necesita PHP 8.0 o superior. " +
                `Instala una versión reciente de XAMPP. Detalle: ${diagnostico}`;
        }

        if (estado === 404) {
            return "No se encontró la API (error 404). Revisa que el proyecto esté dentro de htdocs y " +
                `que entres por http://localhost/NOMBRE-DE-LA-CARPETA/. Detalle: ${diagnostico}`;
        }

        return `El servidor respondió con un error (código ${estado}). Para ver qué falta abre ${diagnostico}`;
    }

    /* ---------- SESIÓN ---------- */

    function obtenerSesion(forzar = false) {
        if (sesion && !forzar) {
            return Promise.resolve(sesion);
        }

        if (!sesionPendiente || forzar) {
            sesionPendiente = api("auth.php?accion=sesion")
                .then(datos => (sesion = datos))
                .finally(() => (sesionPendiente = null));
        }

        return sesionPendiente;
    }

    function usuario() {
        return sesion?.usuario ?? null;
    }

    /**
     * Exige una sesión iniciada. Si no la hay, lleva al login y
     * vuelve a esta página después de entrar.
     */
    async function requerirSesion() {
        const { usuario } = await obtenerSesion();

        if (!usuario) {
            const volver = location.pathname.split("/").pop() + location.search + location.hash;
            location.replace("login.html?volver=" + encodeURIComponent(volver));
            return new Promise(() => {}); // la página se está redirigiendo
        }

        return usuario;
    }

    async function cerrarSesion() {
        try {
            await api("auth.php?accion=logout", { metodo: "POST" });
        } finally {
            location.href = "index.html";
        }
    }

    /* ---------- CARRITO (guardado en el navegador) ---------- */

    function leerCarrito() {
        try {
            const items = JSON.parse(localStorage.getItem(CLAVE_CARRITO) || "[]");
            return Array.isArray(items) ? items : [];
        } catch {
            return [];
        }
    }

    function guardarCarrito(items) {
        try {
            localStorage.setItem(CLAVE_CARRITO, JSON.stringify(items));
        } catch {
            // Navegación privada sin almacenamiento: el carrito vive solo en esta página
        }

        document.dispatchEvent(new CustomEvent("carrito:cambio", { detail: items }));
    }

    /** Agrega una variante. Devuelve false si ya no hay más stock. */
    function agregarAlCarrito(item) {
        const items = leerCarrito();
        const existente = items.find(i => i.variante_id === item.variante_id);
        const limite = Math.min(item.stock, MAX_POR_ITEM);

        if (existente) {
            if (existente.cantidad >= limite) return false;
            existente.cantidad++;
            existente.stock = item.stock;
            existente.precio = item.precio;
        } else {
            if (limite < 1) return false;
            items.push({ ...item, cantidad: 1 });
        }

        guardarCarrito(items);
        return true;
    }

    function cambiarCantidad(varianteId, cantidad) {
        let items = leerCarrito();
        const item = items.find(i => i.variante_id === varianteId);

        if (!item) return;

        if (cantidad <= 0) {
            items = items.filter(i => i.variante_id !== varianteId);
        } else {
            item.cantidad = Math.min(cantidad, item.stock || MAX_POR_ITEM, MAX_POR_ITEM);
        }

        guardarCarrito(items);
    }

    function vaciarCarrito() {
        guardarCarrito([]);
    }

    function unidadesCarrito() {
        return leerCarrito().reduce((suma, item) => suma + item.cantidad, 0);
    }

    /* ---------- FORMATO ---------- */

    function precio(valor) {
        return Number(valor).toLocaleString("es-CO", {
            style: "currency",
            currency: "COP",
            maximumFractionDigits: 0
        });
    }

    function fecha(texto, conHora = false) {
        const valor = new Date(String(texto).replace(" ", "T"));

        return valor.toLocaleDateString("es-CO", {
            day: "numeric",
            month: "short",
            year: "numeric",
            ...(conHora ? { hour: "numeric", minute: "2-digit" } : {})
        });
    }

    /** Escapa texto antes de insertarlo con innerHTML (evita XSS). */
    function esc(texto) {
        return String(texto ?? "").replace(/[&<>"']/g, caracter => ({
            "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
        })[caracter]);
    }

    function colorMuestra(nombre) {
        return COLORES[nombre] || "#777777";
    }

    function parametro(nombre) {
        return new URLSearchParams(location.search).get(nombre);
    }

    /* ---------- MENSAJES ---------- */

    function toast(mensaje, tipo = "info") {
        let zona = document.getElementById("zonaToast");

        if (!zona) {
            zona = document.createElement("div");
            zona.id = "zonaToast";
            zona.className = "zona-toast";
            zona.setAttribute("aria-live", "polite");
            document.body.appendChild(zona);
        }

        const aviso = document.createElement("div");
        aviso.className = `toast toast-${tipo}`;
        aviso.textContent = mensaje;
        zona.appendChild(aviso);

        setTimeout(() => aviso.classList.add("saliendo"), 3200);
        setTimeout(() => aviso.remove(), 3600);
    }

    /** Pinta los errores de validación del servidor junto a cada campo. */
    function mostrarErrores(formulario, errores = {}) {
        limpiarErrores(formulario);

        let primero = null;

        Object.entries(errores).forEach(([campo, mensaje]) => {
            const entrada = formulario.querySelector(`[name="${campo}"]`);
            const contenedor = entrada?.closest("[data-campo]") || entrada?.parentElement;

            if (!contenedor) return;

            const texto = document.createElement("small");
            texto.className = "error-campo";
            texto.textContent = Array.isArray(mensaje) ? mensaje.join(" ") : mensaje;
            contenedor.classList.add("con-error");
            contenedor.appendChild(texto);

            if (entrada) entrada.setAttribute("aria-invalid", "true");
            primero ??= entrada;
        });

        primero?.focus();
    }

    // Al corregir un campo se quita su mensaje de error
    ["input", "change"].forEach(tipo => {
        document.addEventListener(tipo, evento => {
            const contenedor = evento.target.closest?.(".con-error");
            if (!contenedor) return;

            contenedor.classList.remove("con-error");
            contenedor.querySelectorAll(".error-campo").forEach(el => el.remove());
            contenedor.querySelectorAll("[aria-invalid]").forEach(el => el.removeAttribute("aria-invalid"));
        });
    });

    function limpiarErrores(formulario) {
        formulario.querySelectorAll(".error-campo").forEach(el => el.remove());
        formulario.querySelectorAll(".con-error").forEach(el => el.classList.remove("con-error"));
        formulario.querySelectorAll("[aria-invalid]").forEach(el => el.removeAttribute("aria-invalid"));
    }

    /** Desactiva un botón mientras se espera la respuesta. */
    async function conBoton(boton, textoCargando, accion) {
        const original = boton.innerHTML;
        boton.disabled = true;
        boton.textContent = textoCargando;

        try {
            return await accion();
        } finally {
            boton.disabled = false;
            boton.innerHTML = original;
        }
    }

    /* ---------- MENÚ DE USUARIO EN EL ENCABEZADO ---------- */

    function pintarSesion(contenedor) {
        if (!contenedor) return;

        const actual = usuario();

        if (!actual) {
            contenedor.innerHTML = `
                <a href="login.html" class="btn-login">Iniciar sesión</a>
                <a href="register.html" class="btn-registro">Registrarme</a>`;
            return;
        }

        const primerNombre = actual.nombre.split(" ")[0];
        const enlaceVender = actual.tienda
            ? `<a href="vendedor.html">Panel de vendedor</a>`
            : `<a href="vendedor.html">Vender en JJ STORE</a>`;

        contenedor.innerHTML = `
            <div class="menu-usuario">
                <button type="button" class="menu-usuario-boton" aria-expanded="false" aria-haspopup="true">
                    <span class="avatar" aria-hidden="true">${esc(primerNombre.charAt(0).toUpperCase())}</span>
                    <span class="menu-usuario-nombre">${esc(primerNombre)}</span>
                    <span aria-hidden="true">▾</span>
                </button>
                <div class="menu-usuario-lista" hidden>
                    <p class="menu-usuario-info">
                        <strong>${esc(actual.nombre)}</strong>
                        <small>${esc(actual.email)}</small>
                        ${actual.tienda ? `<small class="menu-usuario-tienda">Tienda: ${esc(actual.tienda.nombre)}</small>` : ""}
                    </p>
                    <a href="index.html">Ir a la tienda</a>
                    <a href="mis-pedidos.html">Mis pedidos</a>
                    <a href="cuenta.html">Mi cuenta</a>
                    ${enlaceVender}
                    <button type="button" data-accion="salir">Cerrar sesión</button>
                </div>
            </div>`;

        const boton = contenedor.querySelector(".menu-usuario-boton");
        const lista = contenedor.querySelector(".menu-usuario-lista");

        const cerrar = () => {
            lista.hidden = true;
            boton.setAttribute("aria-expanded", "false");
        };

        boton.addEventListener("click", evento => {
            evento.stopPropagation();
            lista.hidden = !lista.hidden;
            boton.setAttribute("aria-expanded", String(!lista.hidden));
        });

        document.addEventListener("click", evento => {
            if (!contenedor.contains(evento.target)) cerrar();
        });

        document.addEventListener("keydown", evento => {
            if (evento.key === "Escape") cerrar();
        });

        lista.querySelector("[data-accion='salir']").addEventListener("click", cerrarSesion);
    }

    return {
        COLORES,
        ESTADOS,
        api,
        obtenerSesion,
        usuario,
        requerirSesion,
        cerrarSesion,
        leerCarrito,
        guardarCarrito,
        agregarAlCarrito,
        cambiarCantidad,
        vaciarCarrito,
        unidadesCarrito,
        precio,
        fecha,
        esc,
        colorMuestra,
        parametro,
        toast,
        mostrarErrores,
        limpiarErrores,
        conBoton,
        pintarSesion
    };
})();
