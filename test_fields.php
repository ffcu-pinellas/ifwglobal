<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
?>
require 'config.php';
$stmt = $pdo->query('SELECT * FROM IFW_form_fields');
$res = $stmt->fetchAll();
print_r($res);
?>