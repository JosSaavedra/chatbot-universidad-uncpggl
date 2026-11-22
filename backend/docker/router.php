<?php

$public = dirname(__DIR__).'/public';
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
// Servir sólo archivos públicos existentes. Evitar el router de desarrollo,
// que imprime las rutas de aprobación QR con sus credenciales.
$static = realpath($public.$path);
if ($static !== false && str_starts_with($static, $public.DIRECTORY_SEPARATOR)
    && ! preg_match('#/\.#', $path) && is_file($static)) {
    return false;
}

require $public.'/index.php';
