/* ==========================================
   JJ STORE - MI CUENTA
   Editar nombre y celular, y cambiar la contraseña.
========================================== */

const formPerfil = document.getElementById("formPerfil");
const formPassword = document.getElementById("formPassword");

async function iniciar() {
    const usuario = await JJ.requerirSesion();
    JJ.pintarSesion(document.getElementById("zonaSesion"));

    pintarDatos(usuario);

    document.getElementById("cargando").hidden = true;
    document.getElementById("cuenta").hidden = false;
}

function pintarDatos(usuario) {
    formPerfil.elements.nombre.value = usuario.nombre;
    formPerfil.elements.telefono.value = usuario.telefono ?? "";
    document.getElementById("perfilEmail").value = usuario.email;

    document.getElementById("tipoCuenta").textContent = usuario.tienda
        ? `Cuenta de vendedor · ${usuario.tienda.nombre}`
        : "Cuenta de comprador";
}

/* ---------- Datos personales ---------- */

formPerfil.addEventListener("submit", async evento => {
    evento.preventDefault();
    JJ.limpiarErrores(formPerfil);

    try {
        const { usuario } = await JJ.conBoton(formPerfil.querySelector("button[type=submit]"), "GUARDANDO...", () =>
            JJ.api("auth.php?accion=actualizar-perfil", {
                metodo: "POST",
                datos: Object.fromEntries(new FormData(formPerfil))
            })
        );

        pintarDatos(usuario);
        JJ.pintarSesion(document.getElementById("zonaSesion"));
        JJ.toast("Tus datos quedaron guardados.", "exito");
    } catch (error) {
        JJ.mostrarErrores(formPerfil, error.errores);
        JJ.toast(error.message, "error");
    }
});

/* ---------- Contraseña ---------- */

formPassword.addEventListener("submit", async evento => {
    evento.preventDefault();
    JJ.limpiarErrores(formPassword);

    const datos = Object.fromEntries(new FormData(formPassword));

    // Revisión rápida en el navegador; el servidor vuelve a validar todo
    if (datos.nueva && datos.confirmar && datos.nueva !== datos.confirmar) {
        JJ.mostrarErrores(formPassword, { confirmar: "Las contraseñas no coinciden." });
        return;
    }

    try {
        await JJ.conBoton(formPassword.querySelector("button[type=submit]"), "CAMBIANDO...", () =>
            JJ.api("auth.php?accion=cambiar-password", { metodo: "POST", datos })
        );

        formPassword.reset();
        mostrarPasswords(false);
        JJ.toast("Contraseña actualizada. Úsala la próxima vez que inicies sesión.", "exito");
    } catch (error) {
        JJ.mostrarErrores(formPassword, error.errores);
        JJ.toast(error.message, "error");
    }
});

function mostrarPasswords(mostrar) {
    document.getElementById("verPasswords").checked = mostrar;
    formPassword.querySelectorAll("input[name]").forEach(entrada => {
        entrada.type = mostrar ? "text" : "password";
    });
}

document.getElementById("verPasswords").addEventListener("change", evento => {
    mostrarPasswords(evento.target.checked);
});

iniciar().catch(error => {
    document.getElementById("cargando").textContent = error.message;
});
