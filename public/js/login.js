/* ==========================================
   JJ STORE - INICIO DE SESIÓN
========================================== */

const formLogin = document.getElementById("formLogin");
const mensajeLogin = document.getElementById("mensajeLogin");

/** Página a la que se vuelve después de entrar (solo rutas internas). */
function destinoSeguro(usuario) {
    const volver = JJ.parametro("volver");

    if (volver && /^[a-z0-9-]+\.html([?#].*)?$/i.test(volver)) {
        return volver;
    }

    return usuario.tienda ? "vendedor.html" : "index.html";
}

// Mensaje si viene desde el checkout
if (JJ.parametro("volver")?.startsWith("checkout")) {
    document.getElementById("subtituloFormulario").textContent =
        "Inicia sesión para terminar tu compra. Tu carrito sigue guardado.";
}

// Conservar el destino también al ir al registro
const irRegistro = document.getElementById("irRegistro");
if (JJ.parametro("volver")) {
    irRegistro.href = "register.html?volver=" + encodeURIComponent(JJ.parametro("volver"));
}

// Si ya hay sesión, no tiene sentido mostrar el login
JJ.obtenerSesion().then(({ usuario }) => {
    if (usuario) location.replace(destinoSeguro(usuario));
}).catch(() => {});

formLogin.addEventListener("submit", async evento => {
    evento.preventDefault();
    mensajeLogin.textContent = "";
    JJ.limpiarErrores(formLogin);

    const datos = Object.fromEntries(new FormData(formLogin));
    const boton = formLogin.querySelector(".boton-principal");

    try {
        const { usuario } = await JJ.conBoton(boton, "ENTRANDO...", () =>
            JJ.api("auth.php?accion=login", { metodo: "POST", datos })
        );

        location.href = destinoSeguro(usuario);
    } catch (error) {
        mensajeLogin.textContent = error.message;
        mensajeLogin.classList.add("error");
    }
});

// Cuentas de prueba para la presentación
document.querySelectorAll(".cuentas-demo [data-correo]").forEach(boton => {
    boton.addEventListener("click", () => {
        document.getElementById("correoLogin").value = boton.dataset.correo;
        document.getElementById("passwordLogin").value = "Demo1234";
        formLogin.requestSubmit();
    });
});

document.getElementById("olvidoPassword").addEventListener("click", evento => {
    evento.preventDefault();
    mensajeLogin.classList.remove("error");
    mensajeLogin.textContent =
        "La recuperación de contraseña estará disponible en una próxima versión.";
});

// Mostrar y ocultar contraseña
document.querySelectorAll(".ver-password").forEach(boton => {
    boton.addEventListener("click", () => {
        const campo = document.getElementById(boton.dataset.objetivo);
        const mostrar = campo.type === "password";

        campo.type = mostrar ? "text" : "password";
        boton.querySelector(".icono-ojo").textContent = mostrar ? "🙈" : "👁️";
        boton.querySelector(".texto-ojo").textContent = mostrar ? "Ocultar" : "Mostrar";

        boton.classList.remove("cambiado");
        void boton.offsetWidth;
        boton.classList.add("cambiado");
    });
});

document.getElementById("anio").textContent = new Date().getFullYear();
