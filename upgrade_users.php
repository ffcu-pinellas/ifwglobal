<?php
require_once __DIR__ . '/config.php';
try {
    $pdo->exec("ALTER TABLE IFW_users ADD COLUMN full_name VARCHAR(255) NULL AFTER username");
    echo "Added full_name column to IFW_users";
} catch (Exception $e) {
    echo "Error or already exists: " . $e->getMessage();
}
