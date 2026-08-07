<?php
require_once 'config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // Alter IFW_invoices table
    $pdo->exec("ALTER TABLE IFW_invoices 
        ADD COLUMN invoice_number VARCHAR(50) NULL AFTER id,
        ADD COLUMN case_id INT NULL AFTER client_id,
        ADD COLUMN issue_date DATE NULL AFTER status,
        ADD COLUMN due_date DATE NULL AFTER issue_date,
        ADD COLUMN subtotal DECIMAL(10,2) DEFAULT 0.00 AFTER due_date,
        ADD COLUMN tax_rate DECIMAL(5,2) DEFAULT 0.00 AFTER subtotal,
        ADD COLUMN tax_amount DECIMAL(10,2) DEFAULT 0.00 AFTER tax_rate,
        ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0.00 AFTER tax_amount,
        ADD COLUMN total_amount DECIMAL(10,2) DEFAULT 0.00 AFTER discount_amount,
        ADD COLUMN currency VARCHAR(10) DEFAULT 'USD' AFTER total_amount,
        ADD COLUMN notes TEXT NULL AFTER currency;");
    echo "Altered IFW_invoices table.<br>";
} catch (Exception $e) {
    echo "Warning altering IFW_invoices: " . $e->getMessage() . "<br>";
}

try {
    // Create IFW_invoice_items table
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

echo "Done.";
?>
