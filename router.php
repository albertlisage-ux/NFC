<?php
/**
 * Router for the PHP built-in server, so /t/{publicId} works locally the same
 * way it does behind Apache or Nginx.
 *
 *   php -S localhost:8080 router.php
 *
 * On a real server the rewrite rules in .htaccess (Apache) or
 * docker/nginx/portal.conf (Nginx) do the same job.
 */

$root = __DIR__;
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

// Serve existing files (assets, PHP pages) through the built-in server.
if ($path !== '/' && is_file($root . $path)) {
    return false;
}

// Directory with an index file.
if (substr($path, -1) === '/') {
    $index = $root . rtrim($path, '/') . '/index.php';
    if (is_file($index)) {
        $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '/index.php';
        require $index;
        return true;
    }
}

// Public tag page: /t/{publicId}
if (preg_match('#^/t/([A-Za-z0-9]{4,12})/?$#', $path, $matches)) {
    $_GET['id'] = $matches[1];
    $_SERVER['SCRIPT_NAME'] = '/t/index.php';
    require $root . '/t/index.php';
    return true;
}

// Clean URLs for the content pages.
$cleanRoutes = [
    '/how-it-works' => 'how-it-works.php',
    '/use-cases' => 'use-cases.php',
    '/privacy' => 'privacy.php',
];
$normalised = rtrim($path, '/');
if (isset($cleanRoutes[$normalised])) {
    $_SERVER['SCRIPT_NAME'] = '/' . $cleanRoutes[$normalised];
    require $root . '/' . $cleanRoutes[$normalised];
    return true;
}

if ($path === '/') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require $root . '/index.php';
    return true;
}

// Unknown path: let the server produce its own 404.
return false;
