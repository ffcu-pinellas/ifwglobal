<?php
require_once 'config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

$columns_to_add = [
    "invoice_number VARCHAR(50) NULL AFTER id",
    "case_id INT NULL AFTER client_id",
    "issue_date DATE NULL AFTER status",
    "due_date DATE NULL AFTER issue_date",
    "subtotal DECIMAL(10,2) DEFAULT 0.00 AFTER due_date",
    "tax_rate DECIMAL(5,2) DEFAULT 0.00 AFTER subtotal",
    "tax_amount DECIMAL(10,2) DEFAULT 0.00 AFTER tax_rate",
    "discount_amount DECIMAL(10,2) DEFAULT 0.00 AFTER tax_amount",
    "total_amount DECIMAL(10,2) DEFAULT 0.00 AFTER discount_amount",
    "currency VARCHAR(10) DEFAULT 'USD' AFTER total_amount",
    "notes TEXT NULL AFTER currency"
];

foreach ($columns_to_add as $colDef) {
    try {
        $pdo->exec("ALTER TABLE IFW_invoices ADD COLUMN $colDef;");
        echo "Added column: $colDef <br>";
    } catch (Exception $e) {
        // Ignore duplicate column errors
        if (strpos($e->getMessage(), 'Duplicate column') === false && strpos($e->getMessage(), 'already exists') === false) {
            echo "Error adding $colDef: " . $e->getMessage() . "<br>";
        }
    }
}

try {
    $pdo->exec("CREATE TABLE IFW_invoice_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        description VARCHAR(255) NOT NULL,
        qty DECIMAL(10,2) DEFAULT 1.00,
        rate DECIMAL(10,2) DEFAULT 0.00,
        amount DECIMAL(10,2) DEFAULT 0.00,
        FOREIGN KEY (invoice_id) REFERENCES IFW_invoices(id) ON DELETE CASCADE
    );");
    echo "Created IFW_invoice_items table.<br>";
} catch (Exception $e) {
    echo "Warning creating IFW_invoice_items: " . $e->getMessage() . "<br>";
}

echo "DB Upgrade Complete.";
?>
