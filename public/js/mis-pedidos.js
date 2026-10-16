/* ==========================================
   JJ STORE - HISTORIAL DE COMPRAS
========================================== */

const lista = document.getElementById("listaPedidos");
const { esc, precio } = JJ;

async function iniciar() {
    await JJ.requerirSesion();
    JJ.pintarSesion(document.getElementById("zonaSesion"));

    try {
        const pedidos = await JJ.api("pedidos.php?accion=mis-pedidos");
        pintarPedidos(pedidos);
    } catch (error) {
        lista.innerHTML = `<p class="alerta alerta-error">${esc(error.message)}</p>`;
    }

    document.getElementById("cargando").hidden = true;
}

function pintarPedidos(pedidos) {
    if (!pedidos.length) {
        lista.innerHTML = `
            <div class="vacio">
                <h2>Todavía no tienes pedidos</h2>
                <p>Cuando compres, aquí podrás ver el estado de cada envío.</p>
                <a href="index.html#catalogo" class="btn">EXPLORAR COLECCIÓN ↗</a>
            </div>`;
        return;
    }

    const gastado = pedidos.reduce((suma, pedido) => suma + pedido.total, 0);
    document.getElementById("resumenPedidos").textContent =
        `${pedidos.length} ${pedidos.length === 1 ? "pedido" : "pedidos"} · ${precio(gastado)} en compras`;

    lista.innerHTML = pedidos.map(pedido => {
        const unidades = pedido.items.reduce((suma, item) => suma + item.cantidad, 0);

        // Un estado por tienda dentro del pedido
        const estados = new Map();
        pedido.items.forEach(item => estados.set(item.tienda, item.estado));

        return `
            <article class="tarjeta pedido-tarjeta">
                <div class="pedido-tarjeta-cabecera">
                    <div>
                        <h2>${esc(pedido.codigo)}</h2>
                        <p>${esc(JJ.fecha(pedido.creado_en, true))} · ${unidades} ${unidades === 1 ? "producto" : "productos"} · ${esc(pedido.detalle_pago)}</p>
                    </div>
                    <div class="pedido-total">
                        <small class="texto-suave">Total</small>
                        <strong>${precio(pedido.total)}</strong>
                    </div>
                </div>

                <div class="miniaturas">
                    ${pedido.items.map(item => `<img src="${esc(item.imagen)}" alt="${esc(item.nombre)}" title="${esc(item.nombre)}">`).join("")}
                </div>

                <div class="pedido-tarjeta-pie">
                    <div class="estados-resumen">
                        ${[...estados.entries()].map(([tienda, estado]) => `
                            <span class="chip-estado estado-${esc(estado)}" title="${esc(tienda)}">
                                ${esc(tienda)}: ${esc(JJ.ESTADOS[estado])}
                            </span>`).join("")}
                    </div>
                    <a href="pedido.html?codigo=${encodeURIComponent(pedido.codigo)}" class="btn-secundario btn-pequeno">VER DETALLE →</a>
                </div>
            </article>`;
    }).join("");
}

iniciar().catch(error => {
    document.getElementById("cargando").textContent = error.message;
});
