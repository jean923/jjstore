/* ==========================================
   JJ STORE - REGISTRO DE COMPRADORES Y VENDEDORES
========================================== */

const formRegistro = document.getElementById("formRegistro");
const passwordRegistro = document.getElementById("passwordRegistro");
const confirmarPassword = document.getElementById("confirmarPassword");
const barraPassword = document.getElementById("barraPassword");
const textoPassword = document.getElementById("textoPassword");
const estadoCoincidencia = document.getElementById("estadoCoincidencia");
const mensajeRegistro = document.getElementById("mensajeRegistro");
const datosTienda = document.getElementById("datosTienda");

document.getElementById("anio").textContent = new Date().getFullYear();

// Se llega con ?rol=vendedor desde "Abrir mi tienda"
if (JJ.parametro("rol") === "vendedor") {
    formRegistro.querySelector('[name="rol"][value="vendedor"]').checked = true;
}

const volver = JJ.parametro("volver");
if (volver) {
    document.getElementById("irLogin").href = "login.html?volver=" + encodeURIComponent(volver);
}

// Mostrar los datos de la tienda solo si quiere vender
function actualizarRol() {
    const esVendedor = formRegistro.querySelector('[name="rol"]:checked').value === "vendedor";
    datosTienda.hidden = !esVendedor;
    datosTienda.querySelectorAll("input").forEach(campo => (campo.required = esVendedor));
}

formRegistro.querySelectorAll('[name="rol"]').forEach(opcion => {
    opcion.addEventListener("change", actualizarRol);
});

actualizarRol();

// Mostrar y ocultar contraseñas
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

// Indicador visual de seguridad
passwordRegistro.addEventListener("input", () => {
    const valor = passwordRegistro.value;
    let nivel = 0;

    if (valor.length >= 8) nivel++;
    if (/[a-z]/.test(valor) && /[A-Z]/.test(valor)) nivel++;
    if (/[0-9]/.test(valor)) nivel++;
    if (/[^A-Za-z0-9]/.test(valor)) nivel++;

    barraPassword.style.width = ["0%", "25%", "50%", "75%", "100%"][nivel];

    textoPassword.textContent = valor.length === 0
        ? "Usa al menos 8 caracteres."
        : ["Usa al menos 8 caracteres.", "Contraseña débil.", "Puedes mejorarla con variedad de caracteres.",
           "Contraseña aceptable.", "Buena variedad de caracteres."][nivel];

    comprobarCoincidencia();
});

function comprobarCoincidencia() {
    const primera = passwordRegistro.value;
    const segunda = confirmarPassword.value;

    estadoCoincidencia.classList.remove("correcto", "incorrecto");

    if (!segunda) {
        estadoCoincidencia.textContent = "";
    } else if (primera === segunda) {
        estadoCoincidencia.textContent = "Las contraseñas coinciden.";
        estadoCoincidencia.classList.add("correcto");
    } else {
        estadoCoincidencia.textContent = "Las contraseñas no coinciden.";
        estadoCoincidencia.classList.add("incorrecto");
    }
}

confirmarPassword.addEventListener("input", comprobarCoincidencia);

// Enviar el registro al servidor
formRegistro.addEventListener("submit", async evento => {
    evento.preventDefault();
    mensajeRegistro.textContent = "";
    mensajeRegistro.classList.remove("error");
    JJ.limpiarErrores(formRegistro);

    if (passwordRegistro.value !== confirmarPassword.value) {
        mensajeRegistro.textContent = "Las contraseñas no coinciden.";
        mensajeRegistro.classList.add("error");
        confirmarPassword.focus();
        return;
    }

    if (!document.getElementById("aceptarTerminos").checked) {
        mensajeRegistro.textContent = "Debes aceptar los términos y condiciones.";
        mensajeRegistro.classList.add("error");
        return;
    }

    const datos = Object.fromEntries(new FormData(formRegistro));
    const boton = formRegistro.querySelector(".boton-principal");

    try {
        const { usuario } = await JJ.conBoton(boton, "CREANDO CUENTA...", () =>
            JJ.api("auth.php?accion=registro", { metodo: "POST", datos })
        );

        mensajeRegistro.textContent = `¡Bienvenido, ${usuario.nombre.split(" ")[0]}! Tu cuenta fue creada.`;

        const destino = volver && /^[a-z0-9-]+\.html([?#].*)?$/i.test(volver)
            ? volver
            : (usuario.tienda ? "vendedor.html" : "index.html");

        setTimeout(() => (location.href = destino), 700);
    } catch (error) {
        mensajeRegistro.textContent = error.message;
        mensajeRegistro.classList.add("error");
        JJ.mostrarErrores(formRegistro, error.errores);
    }
});
