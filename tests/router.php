<?php
// Local integration-test router: emulate a WordPress web server's front controller.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file('/tmp/vw-wordpress' . $path)) return false;
require '/tmp/vw-wordpress/index.php';
