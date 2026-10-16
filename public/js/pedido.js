/* ==========================================
   JJ STORE - CONFIRMACIÓN / DETALLE DE UN PEDIDO
========================================== */

const contenido = document.getElementById("contenido");
const { esc, precio } = JJ;

async function iniciar() {
    await JJ.requerirSesion();
    JJ.pintarSesion(document.getElementById("zonaSesion"));

    const codigo = JJ.parametro("codigo") || "";
    const esNuevo = JJ.parametro("nuevo") === "1";

    try {
        const pedido = await JJ.api("pedidos.php?accion=detalle&codigo=" + encodeURIComponent(codigo));
        pintarPedido(pedido, esNuevo);
    } catch (error) {
        contenido.innerHTML = `
            <div class="vacio">
                <h2>No encontramos el pedido</h2>
                <p>${esc(error.message)}</p>
                <a href="mis-pedidos.html" class="btn">VER MIS PEDIDOS</a>
            </div>`;
    }

    document.getElementById("cargando").hidden = true;
    contenido.hidden = false;
}

function avisoCorreo(pedido) {
    const correo = pedido.correo;
    const enlace = `api/pedidos.php?accion=correo&codigo=${encodeURIComponent(pedido.codigo)}`;
    const botonVer = `<a href="${enlace}" target="_blank" rel="noopener" class="btn-secundario btn-pequeno">VER CORREO ↗</a>`;

    if (!correo) return "";

    if (correo.estado === "enviado") {
        return `<div class="alerta alerta-exito aviso-correo">
                    <span>📧 Te enviamos la confirmación a <strong>${esc(correo.destinatario)}</strong>.</span>
                    ${botonVer}
                </div>`;
    }

    if (correo.estado === "simulado") {
        return `<div class="alerta alerta-info aviso-correo">
                    <span>📧 Modo demostración: el correo de confirmación para
                    <strong>${esc(correo.destinatario)}</strong> quedó registrado.</span>
                    ${botonVer}
                </div>`;
    }

    return `<div class="alerta alerta-error aviso-correo">
                <span>No pudimos enviar el correo de confirmación, pero tu pedido quedó registrado correctamente.</span>
                ${botonVer}
            </div>`;
}

function pintarPedido(pedido, esNuevo) {
    document.title = `Pedido ${pedido.codigo} | JJ STORE`;

    // Agrupar ítems por tienda (cada tienda envía lo suyo)
    const porTienda = new Map();
    pedido.items.forEach(item => {
        if (!porTienda.has(item.tienda)) porTienda.set(item.tienda, []);
        porTienda.get(item.tienda).push(item);
    });

    const grupos = [...porTienda.entries()].map(([tienda, items]) => `
        <div class="grupo-tienda">
            <div class="grupo-tienda-cabecera">
                <strong>${esc(tienda)}</strong>
                <span class="chip-estado estado-${esc(items[0].estado)}">${esc(JJ.ESTADOS[items[0].estado])}</span>
            </div>
            ${items.map(item => `
                <div class="linea-producto">
                    <img src="${esc(item.imagen)}" alt="">
                    <div>
                        <h3>${esc(item.nombre)}</h3>
                        <p>${esc(item.talla)} · ${esc(item.color)} · ${item.cantidad} × ${precio(item.precio_unitario)}</p>
                    </div>
                    <span class="valor">${precio(item.subtotal)}</span>
                </div>`).join("")}
        </div>`).join("");

    const encabezado = esNuevo
        ? `<section class="confirmacion">
                <div class="icono-exito" aria-hidden="true">✓</div>
                <p class="eyebrow">${pedido.estado_pago === "aprobado" ? "PAGO APROBADO" : "PEDIDO REGISTRADO"}</p>
                <h1>¡GRACIAS POR TU COMPRA!</h1>
                <p class="texto-suave">Tu número de pedido es</p>
                <span class="codigo-pedido">${esc(pedido.codigo)}</span>
                <p class="texto-suave">Ya avisamos a las tiendas para que preparen tus productos.</p>
                ${avisoCorreo(pedido)}
           </section>`
        : `<div class="pagina-encabezado">
                <div>
                    <p class="eyebrow">DETALLE DEL PEDIDO</p>
                    <h1>${esc(pedido.codigo)}<span>.</span></h1>
                </div>
                <a href="mis-pedidos.html" class="btn-secundario">← MIS PEDIDOS</a>
           </div>
           ${avisoCorreo(pedido)}`;

    contenido.innerHTML = `
        ${encabezado}

        <div class="pedido-grid" style="margin-top:22px">
            <section class="tarjeta">
                <h2 class="tarjeta-titulo">Productos</h2>
                ${grupos}

                <div class="totales" style="margin:22px 0 0">
                    <div><span>Subtotal</span><span>${precio(pedido.subtotal)}</span></div>
                    <div><span>Envío</span><span>${pedido.costo_envio > 0 ? precio(pedido.costo_envio) : "Gratis"}</span></div>
                    <div class="total-final"><span>Total</span><span>${precio(pedido.total)}</span></div>
                </div>
            </section>

            <aside class="tarjeta">
                <h2 class="tarjeta-titulo">Resumen</h2>
                <dl class="dato-lista">
                    <div><dt>Fecha</dt><dd>${esc(JJ.fecha(pedido.creado_en, true))}</dd></div>
                    <div>
                        <dt>Pago</dt>
                        <dd>${esc(pedido.detalle_pago)} · ${pedido.estado_pago === "aprobado" ? "Aprobado" : "Se paga al recibir"}</dd>
                    </div>
                    <div><dt>Referencia</dt><dd>${esc(pedido.referencia_pago)}</dd></div>
                    <div>
                        <dt>Enviar a</dt>
                        <dd>
                            ${esc(pedido.nombre_envio)} · ${esc(pedido.telefono)}<br>
                            ${esc(pedido.direccion)}<br>
                            ${esc(pedido.ciudad)}, ${esc(pedido.departamento)}
                            ${pedido.notas ? `<br><em>“${esc(pedido.notas)}”</em>` : ""}
                        </dd>
                    </div>
                </dl>

                <div class="grupo-botones" style="margin-top:26px">
                    <a href="index.html#catalogo" class="btn">SEGUIR COMPRANDO ↗</a>
                    <a href="mis-pedidos.html" class="btn-secundario">MIS PEDIDOS</a>
                </div>
            </aside>
        </div>`;
}

iniciar().catch(error => {
    document.getElementById("cargando").textContent = error.message;
});
