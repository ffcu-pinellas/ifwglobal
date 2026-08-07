<?php
require_once 'config.php';

try {
    $pdo->exec("ALTER TABLE IFW_invoices ADD COLUMN currency VARCHAR(10) DEFAULT 'USD' AFTER total_amount");
    $pdo->exec("ALTER TABLE IFW_invoices ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0.00 AFTER tax_amount");
    echo "Successfully upgraded IFW_invoices for Bibric styling.\n";
} catch (PDOException $e) {
    echo "Error upgrading DB: " . $e->getMessage();
}
?>
