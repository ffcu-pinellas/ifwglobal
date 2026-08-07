<?php
require_once 'config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    print_r($tables);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
