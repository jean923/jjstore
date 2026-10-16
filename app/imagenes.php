<?php
/**
 * Subida segura de imágenes de productos.
 *  - Se verifica el tipo REAL del archivo (no la extensión que envía el navegador).
 *  - Se genera un nombre aleatorio: el usuario no controla la ruta ni la extensión.
 */

declare(strict_types=1);

const IMAGEN_MAX_BYTES = 2 * 1024 * 1024; // 2 MB
const CARPETA_IMAGENES = 'uploads/productos';

function hay_imagen_subida(string $campo): bool
{
    return isset($_FILES[$campo]) && $_FILES[$campo]['error'] !== UPLOAD_ERR_NO_FILE;
}

/** Valida la imagen subida. Devuelve un mensaje de error o null si está bien. */
function validar_imagen_subida(array $archivo): ?string
{
    if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE) {
        return 'La imagen no puede superar 2 MB.';
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        return 'No se pudo subir la imagen. Intenta de nuevo.';
    }

    if ($archivo['size'] > IMAGEN_MAX_BYTES) {
        return 'La imagen no puede superar 2 MB.';
    }

    if (extension_imagen($archivo['tmp_name']) === null || @getimagesize($archivo['tmp_name']) === false) {
        return 'Formato no permitido. Usa una imagen JPG, PNG o WEBP.';
    }

    return null;
}

/** Mueve la imagen (ya validada) a public/uploads/productos y devuelve su ruta relativa. */
function guardar_imagen_subida(array $archivo): string
{
    $nombre = bin2hex(random_bytes(16)) . '.' . extension_imagen($archivo['tmp_name']);
    $carpeta = RAIZ . '/public/' . CARPETA_IMAGENES;

    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0755, true);
    }

    if (!move_uploaded_file($archivo['tmp_name'], $carpeta . '/' . $nombre)) {
        throw new RuntimeException('No se pudo mover la imagen subida a ' . $carpeta);
    }

    return CARPETA_IMAGENES . '/' . $nombre;
}

/** Borra una imagen local (las URLs externas se ignoran). */
function eliminar_imagen_local(?string $ruta): void
{
    if ($ruta === null || !preg_match('#^uploads/productos/[a-f0-9]{32}\.(jpg|png|webp)$#', $ruta)) {
        return;
    }

    $archivo = RAIZ . '/public/' . $ruta;

    if (is_file($archivo)) {
        unlink($archivo);
    }
}

function extension_imagen(string $rutaTemporal): ?string
{
    $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($rutaTemporal);

    return [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ][$tipo] ?? null;
}
