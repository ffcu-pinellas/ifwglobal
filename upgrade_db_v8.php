<?php
// upgrade_db_v8.php
require_once __DIR__ . '/config.php';

try {
    $pdo->exec("ALTER TABLE IFW_invoices ADD COLUMN payment_info TEXT NULL AFTER notes");
    echo "Successfully added payment_info to IFW_invoices\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false || strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'duplicate column') !== false || strpos($e->getMessage(), 'already exists') !== false) {
        echo "Column payment_info already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
