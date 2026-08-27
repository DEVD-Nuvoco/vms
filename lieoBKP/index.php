<?php
/**
 * Legacy /lieo URL — send browsers and old email links to /lieo.
 */
$uri = $_SERVER['REQUEST_URI'] ?? '/lieo/';
$target = preg_replace('#/lieo(/|$)#', '/lieo$1', $uri, 1);
if ($target === $uri) {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = dirname($script);
    $target = rtrim(str_replace('/lieo', '/lieo', $dir), '/') . '/login.php';
}
header('Location: ' . $target, true, 302);
exit;
