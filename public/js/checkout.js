/* ==========================================
   JJ STORE - CHECKOUT (ENVÍO Y PAGO SIMULADO)
========================================== */

const DEPARTAMENTOS = [
    "Amazonas", "Antioquia", "Arauca", "Atlántico", "Bogotá D.C.", "Bolívar", "Boyacá", "Caldas",
    "Caquetá", "Casanare", "Cauca", "Cesar", "Chocó", "Córdoba", "Cundinamarca", "Guainía",
    "Guaviare", "Huila", "La Guajira", "Magdalena", "Meta", "Nariño", "Norte de Santander",
    "Putumayo", "Quindío", "Risaralda", "San Andrés y Providencia", "Santander", "Sucre",
    "Tolima", "Valle del Cauca", "Vaupés", "Vichada"
];

const CLAVE_ENVIO = "jj_ultimo_envio";
const CAMPOS_ENVIO = ["nombre", "email", "telefono", "direccion", "ciudad", "departamento", "notas"];

const formCheckout = document.getElementById("formCheckout");
const pagarBtn = document.getElementById("pagarBtn");
const errorPago = document.getElementById("errorPago");
const procesando = document.getElementById("procesando");

let cotizacion = null;

/* ==========================================
   INICIO
========================================== */

async function iniciar() {
    const usuario = await JJ.requerirSesion();
    JJ.pintarSesion(document.getElementById("zonaSesion"));

    const departamento = document.getElementById("envDepartamento");
    DEPARTAMENTOS.forEach(nombre => departamento.add(new Option(nombre, nombre)));

    precargarEnvio(usuario);

    if (JJ.leerCarrito().length === 0) {
        mostrarVacio();
        return;
    }

    await actualizarCotizacion();

    document.getElementById("cargando").hidden = true;
    document.getElementById("checkout").hidden = false;
}

function mostrarVacio() {
    document.getElementById("cargando").hidden = true;
    document.getElementById("checkout").hidden = true;
    document.getElementById("carritoVacio").hidden = false;
}

/** Usa los datos del último envío o, si no hay, los de la cuenta. */
function precargarEnvio(usuario) {
    let guardado = {};

    try {
        guardado = JSON.parse(localStorage.getItem(CLAVE_ENVIO) || "{}");
    } catch {
        guardado = {};
    }

    const valores = { nombre: usuario.nombre, email: usuario.email, telefono: usuario.telefono || "", ...guardado };

    CAMPOS_ENVIO.forEach(campo => {
        const entrada = formCheckout.elements[campo];
        if (entrada && valores[campo]) entrada.value = valores[campo];
    });
}

/* ==========================================
   RESUMEN DEL PEDIDO (precios reales del servidor)
========================================== */

async function actualizarCotizacion() {
    const carrito = JJ.leerCarrito();

    if (!carrito.length) {
        mostrarVacio();
        return;
    }

    try {
        cotizacion = await JJ.api("pedidos.php?accion=cotizar", {
            metodo: "POST",
            datos: { items: carrito.map(({ variante_id, cantidad }) => ({ variante_id, cantidad })) }
        });
    } catch (error) {
        document.getElementById("problemas").innerHTML =
            `<p class="alerta alerta-error">${JJ.esc(error.message)}</p>`;
        pagarBtn.disabled = true;
        return;
    }

    pintarResumen();
}

function pintarResumen() {
    const conProblema = new Set(cotizacion.problemas.map(problema => problema.variante_id));

    document.getElementById("resumenItems").innerHTML = cotizacion.lineas.map(linea => `
        <div class="linea-producto ${conProblema.has(linea.variante_id) ? "con-problema" : ""}">
            <img src="${JJ.esc(linea.imagen)}" alt="">
            <div>
                <h3>${JJ.esc(linea.nombre)}</h3>
                <p>${JJ.esc(linea.talla)} · ${JJ.esc(linea.color)} · Cant. ${linea.cantidad}</p>
                <p>Vendido por ${JJ.esc(linea.tienda)}</p>
            </div>
            <span class="valor">${JJ.precio(linea.subtotal)}</span>
        </div>`).join("");

    document.getElementById("resSubtotal").textContent = JJ.precio(cotizacion.subtotal);
    document.getElementById("resEnvio").textContent = cotizacion.envio === 0 ? "Gratis" : JJ.precio(cotizacion.envio);
    document.getElementById("resTotal").textContent = JJ.precio(cotizacion.total);

    pintarProblemas(cotizacion.problemas.map(problema => problema.mensaje));
    actualizarBotonPagar();
}

function pintarProblemas(mensajes) {
    const zona = document.getElementById("problemas");

    if (!mensajes.length) {
        zona.innerHTML = "";
        return;
    }

    zona.innerHTML = `
        <div class="alerta alerta-error" style="margin-bottom:16px">
            <strong>Hay cambios en tu carrito:</strong>
            <ul>${mensajes.map(mensaje => `<li>${JJ.esc(mensaje)}</li>`).join("")}</ul>
            <button type="button" class="btn-secundario btn-pequeno" id="ajustarCarrito" style="margin-top:12px">
                AJUSTAR CARRITO AUTOMÁTICAMENTE
            </button>
        </div>`;

    document.getElementById("ajustarCarrito").addEventListener("click", ajustarCarrito);
}

/** Deja en el carrito solo lo que hay en inventario. */
async function ajustarCarrito() {
    const vigentes = new Map(cotizacion.lineas.map(linea => [linea.variante_id, linea]));

    const ajustado = JJ.leerCarrito()
        .filter(item => vigentes.get(item.variante_id)?.stock > 0)
        .map(item => {
            const linea = vigentes.get(item.variante_id);
            return { ...item, precio: linea.precio, stock: linea.stock, cantidad: Math.min(item.cantidad, linea.stock) };
        });

    JJ.guardarCarrito(ajustado);
    JJ.toast("Actualizamos tu carrito con las unidades disponibles.", "info");
    await actualizarCotizacion();
}

function actualizarBotonPagar() {
    const metodo = formCheckout.elements.metodo.value;
    const hayProblemas = cotizacion.problemas.length > 0;

    pagarBtn.disabled = hayProblemas || cotizacion.lineas.length === 0;
    pagarBtn.innerHTML = metodo === "contraentrega"
        ? `CONFIRMAR PEDIDO · ${JJ.precio(cotizacion.total)} <span>→</span>`
        : `PAGAR ${JJ.precio(cotizacion.total)} <span>→</span>`;
}

/* ==========================================
   MÉTODO DE PAGO
========================================== */

formCheckout.querySelectorAll('[name="metodo"]').forEach(opcion => {
    opcion.addEventListener("change", () => {
        document.querySelectorAll("[data-metodo]").forEach(panelMetodo => {
            panelMetodo.hidden = panelMetodo.dataset.metodo !== opcion.value;
        });

        errorPago.hidden = true;
        if (cotizacion) actualizarBotonPagar();
    });
});

// Formato del número: grupos de 4 dígitos
const pagoNumero = document.getElementById("pagoNumero");
const pagoTitular = document.getElementById("pagoTitular");
const pagoVence = document.getElementById("pagoVence");
const pagoCvv = document.getElementById("pagoCvv");

function marcaTarjeta(numero) {
    if (/^4/.test(numero)) return "VISA";
    if (/^(5[1-5]|2[2-7])/.test(numero)) return "MASTERCARD";
    if (/^3[47]/.test(numero)) return "AMEX";
    return "TARJETA";
}

function actualizarTarjetaVisual() {
    const digitos = pagoNumero.value.replace(/\D/g, "");

    document.getElementById("visualNumero").textContent =
        (digitos.padEnd(16, "•").match(/.{1,4}/g) || []).join(" ");
    document.getElementById("visualMarca").textContent = marcaTarjeta(digitos);
    document.getElementById("visualTitular").textContent = pagoTitular.value.trim().toUpperCase() || "NOMBRE DEL TITULAR";
    document.getElementById("visualVence").textContent = pagoVence.value || "MM/AA";
}

pagoNumero.addEventListener("input", () => {
    const digitos = pagoNumero.value.replace(/\D/g, "").slice(0, 19);
    pagoNumero.value = digitos.replace(/(.{4})(?=.)/g, "$1 ");
    actualizarTarjetaVisual();
});

pagoVence.addEventListener("input", evento => {
    let digitos = pagoVence.value.replace(/\D/g, "").slice(0, 4);

    if (digitos.length >= 3 || (digitos.length === 2 && evento.inputType !== "deleteContentBackward")) {
        digitos = digitos.slice(0, 2) + "/" + digitos.slice(2);
    }

    pagoVence.value = digitos;
    actualizarTarjetaVisual();
});

pagoCvv.addEventListener("input", () => {
    pagoCvv.value = pagoCvv.value.replace(/\D/g, "").slice(0, 4);
});

pagoTitular.addEventListener("input", actualizarTarjetaVisual);

// Botones de tarjetas de prueba
document.querySelectorAll("[data-tarjeta]").forEach(boton => {
    boton.addEventListener("click", () => {
        pagoNumero.value = boton.dataset.tarjeta;

        if (!pagoTitular.value) pagoTitular.value = formCheckout.elements.nombre.value.toUpperCase();

        if (!pagoVence.value) {
            const vence = new Date();
            vence.setFullYear(vence.getFullYear() + 3);
            pagoVence.value = String(vence.getMonth() + 1).padStart(2, "0") + "/" + String(vence.getFullYear()).slice(2);
        }

        if (!pagoCvv.value) pagoCvv.value = "123";

        actualizarTarjetaVisual();
    });
});

/* ==========================================
   PAGAR
========================================== */

formCheckout.addEventListener("submit", async evento => {
    evento.preventDefault();

    if (!cotizacion || pagarBtn.disabled) return;

    JJ.limpiarErrores(formCheckout);
    errorPago.hidden = true;

    const campos = formCheckout.elements;
    const envio = Object.fromEntries(CAMPOS_ENVIO.map(campo => [campo, campos[campo].value.trim()]));
    const metodo = campos.metodo.value;
    const pago = { metodo };

    if (metodo === "tarjeta") {
        Object.assign(pago, {
            numero: pagoNumero.value,
            titular: pagoTitular.value.trim(),
            vencimiento: pagoVence.value,
            cvv: pagoCvv.value
        });
    } else if (metodo === "pse") {
        pago.banco = campos.banco.value;
    }

    const items = JJ.leerCarrito().map(({ variante_id, cantidad }) => ({ variante_id, cantidad }));

    procesando.hidden = false;
    document.getElementById("procesandoTitulo").textContent =
        metodo === "contraentrega" ? "Registrando tu pedido..." : "Procesando tu pago...";

    try {
        // Se espera al menos 1,5 s para que se sienta como una pasarela real
        const [resultado] = await Promise.all([
            JJ.api("pedidos.php?accion=crear", { metodo: "POST", datos: { items, envio, pago } }),
            new Promise(resolver => setTimeout(resolver, 1500))
        ]);

        try {
            localStorage.setItem(CLAVE_ENVIO, JSON.stringify(envio));
        } catch {
            // sin almacenamiento disponible
        }

        JJ.vaciarCarrito();
        location.href = "pedido.html?codigo=" + encodeURIComponent(resultado.codigo) + "&nuevo=1";
    } catch (error) {
        procesando.hidden = true;

        if (error.estado === 422) {
            JJ.mostrarErrores(formCheckout, error.errores);
            JJ.toast(error.message, "error");
        } else if (error.estado === 409) {
            await actualizarCotizacion();
            if (error.errores.carrito) pintarProblemas(error.errores.carrito);
            JJ.toast(error.message, "error");
        } else {
            errorPago.textContent = error.message;
            errorPago.hidden = false;
            errorPago.scrollIntoView({ behavior: "smooth", block: "center" });
        }
    }
});

iniciar().catch(error => {
    document.getElementById("cargando").textContent = error.message;
});
