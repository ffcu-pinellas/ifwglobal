<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
?>
require_once __DIR__ . '/config.php';
try {
    $pdo->exec("ALTER TABLE IFW_users ADD COLUMN full_name VARCHAR(255) NULL AFTER username");
    echo "Added full_name column to IFW_users";
} catch (Exception $e) {
    echo "Error or already exists: " . $e->getMessage();
}