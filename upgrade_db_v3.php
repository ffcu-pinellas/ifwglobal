<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\' || preg_match('/^[A-Z]:\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
?>
require_once __DIR__ . '/config.php';

try {
    // 1. KYC Tables
    $pdo->exec("CREATE TABLE IFW_kyc_fields (
        id INT AUTO_INCREMENT PRIMARY KEY,
        field_name VARCHAR(100) NOT NULL,
        field_label VARCHAR(100) NOT NULL,
        field_type VARCHAR(50) NOT NULL DEFAULT 'text',
        is_required TINYINT(1) DEFAULT 0,
        sort_order INT DEFAULT 0
    )");
    
    // Insert default KYC fields
    $pdo->exec("INSERT INTO IFW_kyc_fields (field_name, field_label, field_type, is_required, sort_order) VALUES 
        ('full_name', 'Full Legal Name', 'text', 1, 1),
        ('dob', 'Date of Birth', 'date', 1, 2),
        ('id_type', 'ID Type (Passport/License)', 'text', 1, 3),
        ('id_number', 'ID Number', 'text', 1, 4),
        ('id_front', 'ID Front Scan (Upload)', 'file', 1, 5),
        ('id_back', 'ID Back Scan (Upload)', 'file', 1, 6)
    ");
} catch (Exception $e) { }

try {
    $pdo->exec("CREATE TABLE IFW_kyc_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        submission_data JSON,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reviewed_by INT NULL,
        reviewed_at TIMESTAMP NULL,
        rejection_reason TEXT
    )");
} catch (Exception $e) { }

try {
    // 2. Cases Tables
    $pdo->exec("CREATE TABLE IFW_cases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        case_number VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        client_id INT NOT NULL,
        attorney_id INT NULL,
        court_date DATETIME NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) { }

try {
    // 3. Invoices Tables
    $pdo->exec("CREATE TABLE IFW_invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_number VARCHAR(50) NOT NULL,
        client_id INT NOT NULL,
        case_id INT NULL,
        status VARCHAR(50) DEFAULT 'Unpaid',
        issue_date DATE NOT NULL,
        due_date DATE NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) { }

try {
    $pdo->exec("CREATE TABLE IFW_invoice_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        description VARCHAR(255) NOT NULL,
        qty DECIMAL(10,2) NOT NULL DEFAULT 1.00,
        rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00
    )");
} catch (Exception $e) { }

echo "Database updated successfully with KYC, Cases, and Invoices tables.";
?>