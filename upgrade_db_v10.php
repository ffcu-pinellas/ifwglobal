<?php
// upgrade_db_v10.php - Full database upgrade: notifications, case timeline, invoice payments, late fees, KYC tables, and fallback structures.
require_once __DIR__ . '/config.php';
$results = [];

$tables = [
    // KYC Fields Table
    "CREATE TABLE IF NOT EXISTS IFW_kyc_fields (
        id INT AUTO_INCREMENT PRIMARY KEY,
        field_name VARCHAR(100) NOT NULL UNIQUE,
        field_label VARCHAR(100) NOT NULL,
        field_type VARCHAR(50) NOT NULL,
        field_options TEXT NULL,
        is_required TINYINT(1) DEFAULT 1,
        sort_order INT DEFAULT 0
    )" => "IFW_kyc_fields",

    // KYC Submissions Table
    "CREATE TABLE IF NOT EXISTS IFW_kyc_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        submission_data JSON NOT NULL,
        status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
        rejection_reason TEXT NULL,
        reviewed_by INT NULL,
        reviewed_at TIMESTAMP NULL,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_client (client_id)
    )" => "IFW_kyc_submissions",

    // Notifications Table
    "CREATE TABLE IF NOT EXISTS IFW_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        type VARCHAR(50) NOT NULL COMMENT 'message|invoice|kyc|case_update|payment',
        title VARCHAR(255) NOT NULL,
        body TEXT,
        icon VARCHAR(50) DEFAULT 'bell',
        link VARCHAR(500),
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_client (client_id),
        INDEX idx_read (is_read)
    )" => "IFW_notifications",

    // Case Timeline Table
    "CREATE TABLE IF NOT EXISTS IFW_case_timeline (
        id INT AUTO_INCREMENT PRIMARY KEY,
        case_id INT NOT NULL,
        created_by INT COMMENT 'admin user id',
        milestone_title VARCHAR(255) NOT NULL,
        milestone_body TEXT,
        milestone_date DATE,
        status_color VARCHAR(20) DEFAULT 'primary' COMMENT 'primary|success|warning|danger|info',
        icon VARCHAR(50) DEFAULT 'circle',
        is_client_visible TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_case (case_id)
    )" => "IFW_case_timeline",

    // Invoice Payments (proof upload + instalments)
    "CREATE TABLE IF NOT EXISTS IFW_invoice_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        client_id INT NOT NULL,
        amount DECIMAL(15,2),
        currency VARCHAR(10) DEFAULT 'USD',
        payment_method VARCHAR(100),
        reference_number VARCHAR(200),
        proof_file VARCHAR(500),
        notes TEXT,
        status ENUM('Pending','Confirmed','Rejected') DEFAULT 'Pending',
        reviewed_by INT,
        reviewed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_invoice (invoice_id),
        INDEX idx_client (client_id)
    )" => "IFW_invoice_payments",

    // Invoice Items
    "CREATE TABLE IF NOT EXISTS IFW_invoice_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        description VARCHAR(255) NOT NULL,
        qty DECIMAL(10,2) DEFAULT 1.00,
        rate DECIMAL(15,2) DEFAULT 0.00,
        amount DECIMAL(15,2) DEFAULT 0.00,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_invoice (invoice_id)
    )" => "IFW_invoice_items",

    // Invoice Instalments
    "CREATE TABLE IF NOT EXISTS IFW_invoice_instalments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        instalment_number INT DEFAULT 1,
        amount DECIMAL(15,2),
        due_date DATE,
        status ENUM('Pending','Paid','Overdue') DEFAULT 'Pending',
        paid_at TIMESTAMP NULL,
        notes VARCHAR(500),
        INDEX idx_invoice (invoice_id)
    )" => "IFW_invoice_instalments",

    // Chat File Attachments
    "CREATE TABLE IF NOT EXISTS IFW_chat_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT,
        client_id INT,
        sender VARCHAR(20),
        file_path VARCHAR(500),
        file_name VARCHAR(255),
        file_type VARCHAR(100),
        file_size INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )" => "IFW_chat_attachments",

    // Client Satisfaction Ratings
    "CREATE TABLE IF NOT EXISTS IFW_case_ratings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        case_id INT NOT NULL,
        client_id INT NOT NULL,
        rating TINYINT CHECK(rating BETWEEN 1 AND 5),
        feedback TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_case_rating (case_id, client_id)
    )" => "IFW_case_ratings",
];

// 1. Create Tables
foreach ($tables as $sql => $tableName) {
    try {
        $pdo->exec($sql);
        $results[] = "✅ Table `$tableName` created/verified";
    } catch (PDOException $e) {
        $results[] = "❌ Error with `$tableName`: " . $e->getMessage();
    }
}

// 2. Insert Default KYC Fields if none exist
try {
    $count = $pdo->query("SELECT COUNT(*) FROM IFW_kyc_fields")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO IFW_kyc_fields (field_name, field_label, field_type, is_required, sort_order) VALUES
            ('government_id', 'Government Issued ID (Passport / Driver\'s License)', 'file', 1, 1),
            ('proof_of_address', 'Proof of Address (Utility Bill / Bank Statement)', 'file', 1, 2),
            ('customs_declaration', 'Customs Declaration / Ownership Proof', 'file', 0, 3)
        ");
        $results[] = "✅ Populated default KYC fields in IFW_kyc_fields";
    }
} catch (PDOException $e) {
    $results[] = "❌ Error populating default KYC fields: " . $e->getMessage();
}

// 3. Alter Existing Tables to Add Missing Columns
$alterations = [
    // IFW_invoices alterations
    ["ALTER TABLE IFW_invoices ADD COLUMN invoice_number VARCHAR(100) NULL AFTER id", "IFW_invoices.invoice_number"],
    ["ALTER TABLE IFW_invoices ADD COLUMN case_id INT NULL AFTER client_id", "IFW_invoices.case_id"],
    ["ALTER TABLE IFW_invoices ADD COLUMN subtotal DECIMAL(15,2) DEFAULT 0.00 AFTER amount", "IFW_invoices.subtotal"],
    ["ALTER TABLE IFW_invoices ADD COLUMN tax_rate DECIMAL(5,2) DEFAULT 0.00 AFTER subtotal", "IFW_invoices.tax_rate"],
    ["ALTER TABLE IFW_invoices ADD COLUMN tax_amount DECIMAL(15,2) DEFAULT 0.00 AFTER tax_rate", "IFW_invoices.tax_amount"],
    ["ALTER TABLE IFW_invoices ADD COLUMN discount_amount DECIMAL(15,2) DEFAULT 0.00 AFTER tax_amount", "IFW_invoices.discount_amount"],
    ["ALTER TABLE IFW_invoices ADD COLUMN total_amount DECIMAL(15,2) DEFAULT 0.00 AFTER discount_amount", "IFW_invoices.total_amount"],
    ["ALTER TABLE IFW_invoices ADD COLUMN currency VARCHAR(10) DEFAULT 'USD' AFTER total_amount", "IFW_invoices.currency"],
    ["ALTER TABLE IFW_invoices ADD COLUMN notes TEXT NULL AFTER currency", "IFW_invoices.notes"],
    ["ALTER TABLE IFW_invoices ADD COLUMN payment_info TEXT NULL AFTER notes", "IFW_invoices.payment_info"],
    ["ALTER TABLE IFW_invoices ADD COLUMN issue_date DATE NULL AFTER payment_info", "IFW_invoices.issue_date"],
    ["ALTER TABLE IFW_invoices ADD COLUMN due_date DATE NULL AFTER issue_date", "IFW_invoices.due_date"],
    ["ALTER TABLE IFW_invoices ADD COLUMN late_fee_enabled TINYINT(1) DEFAULT 0 AFTER due_date", "IFW_invoices.late_fee_enabled"],
    ["ALTER TABLE IFW_invoices ADD COLUMN late_fee_type ENUM('daily','weekly','monthly','hourly') DEFAULT 'daily' AFTER late_fee_enabled", "IFW_invoices.late_fee_type"],
    ["ALTER TABLE IFW_invoices ADD COLUMN late_fee_amount DECIMAL(10,2) DEFAULT 0.00 AFTER late_fee_type", "IFW_invoices.late_fee_amount"],
    ["ALTER TABLE IFW_invoices ADD COLUMN late_fee_start_date DATE NULL AFTER late_fee_amount", "IFW_invoices.late_fee_start_date"],
    ["ALTER TABLE IFW_invoices ADD COLUMN late_fee_accumulated DECIMAL(15,2) DEFAULT 0.00 AFTER late_fee_start_date", "IFW_invoices.late_fee_accumulated"],
    ["ALTER TABLE IFW_invoices ADD COLUMN has_instalments TINYINT(1) DEFAULT 0 AFTER late_fee_accumulated", "IFW_invoices.has_instalments"],

    // IFW_cases alterations
    ["ALTER TABLE IFW_cases ADD COLUMN case_number VARCHAR(100) NULL AFTER id", "IFW_cases.case_number"],
    ["ALTER TABLE IFW_cases ADD COLUMN case_type VARCHAR(50) DEFAULT 'Recovery' AFTER title", "IFW_cases.case_type"],
    ["ALTER TABLE IFW_cases ADD COLUMN priority ENUM('Low','Medium','High','Critical') DEFAULT 'Medium' AFTER case_type", "IFW_cases.priority"],
    ["ALTER TABLE IFW_cases ADD COLUMN amount_lost DECIMAL(15,2) DEFAULT 0.00 AFTER description", "IFW_cases.amount_lost"],
    ["ALTER TABLE IFW_cases ADD COLUMN amount_recovered DECIMAL(15,2) DEFAULT 0.00 AFTER amount_lost", "IFW_cases.amount_recovered"],
    ["ALTER TABLE IFW_cases ADD COLUMN currency VARCHAR(10) DEFAULT 'USD' AFTER amount_recovered", "IFW_cases.currency"],
    ["ALTER TABLE IFW_cases ADD COLUMN closing_notes TEXT NULL AFTER currency", "IFW_cases.closing_notes"],
    ["ALTER TABLE IFW_cases ADD COLUMN satisfaction_requested TINYINT(1) DEFAULT 0 AFTER closing_notes", "IFW_cases.satisfaction_requested"],
    
    // IFW_messages alterations
    ["ALTER TABLE IFW_messages ADD COLUMN attachment_path VARCHAR(500) NULL AFTER message_text", "IFW_messages.attachment_path"],

    // IFW_kyc_fields alterations (just in case they existed without these)
    ["ALTER TABLE IFW_kyc_fields ADD COLUMN field_options TEXT NULL AFTER field_type", "IFW_kyc_fields.field_options"],
    ["ALTER TABLE IFW_kyc_fields ADD COLUMN sort_order INT DEFAULT 0 AFTER is_required", "IFW_kyc_fields.sort_order"],

    // IFW_case_notes alterations
    ["ALTER TABLE IFW_case_notes ADD COLUMN case_id INT NULL AFTER client_id", "IFW_case_notes.case_id"],
    ["ALTER TABLE IFW_case_notes ADD COLUMN note TEXT NULL AFTER note_text", "IFW_case_notes.note"],
];

foreach ($alterations as [$sql, $label]) {
    try {
        $pdo->exec($sql);
        $results[] = "✅ Added/Verified column: $label";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'Duplicate column') !== false || stripos($msg, 'already exists') !== false) {
            $results[] = "ℹ️ Column already exists: $label";
        } else {
            $results[] = "❌ Error adding $label: $msg";
        }
    }
}

// 4. Populate any default settings if missing
try {
    $defaultSettings = [
        'chat_provider' => 'internal',
        'bank_name' => 'IFW Secure Escrow',
        'bank_account_name' => 'IFW Global Limited Recovery Group',
        'bank_account_number' => '3829-0091-2290',
        'bank_swift_iban' => 'IFWGAUS33XXX',
        'payment_instructions' => "Payment instructions:\nPlease specify your Invoice Reference in the transfer memo.\nAll payments are processed securely.",
        'display_phone_numbers' => 'show',
    ];
    
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM IFW_site_settings WHERE setting_key = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO IFW_site_settings (setting_key, setting_value) VALUES (?, ?)");
    
    foreach ($defaultSettings as $key => $val) {
        $stmtCheck->execute([$key]);
        if ($stmtCheck->fetchColumn() == 0) {
            $stmtInsert->execute([$key, $val]);
            $results[] = "✅ Added default site setting: $key";
        }
    }
} catch (PDOException $e) {
    $results[] = "❌ Error setting default site settings: " . $e->getMessage();
}

echo "<h2>🚀 Database Upgrade V10 — IFW Global Portal</h2>";
echo "<ul style='font-family:monospace;line-height:2;'>";
foreach ($results as $r) echo "<li>$r</li>";
echo "</ul>";
$errors = array_filter($results, fn($r) => str_starts_with($r, '❌'));
echo $errors ? "<p style='color:red'><strong>⚠️ Some errors occurred — check above.</strong></p>" : "<p style='color:green'><strong>✅ All upgrades applied successfully! Delete this file now.</strong></p>";
?>
