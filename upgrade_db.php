<?php
// upgrade_db.php
require_once 'config.php';

try {
    // 1. Add 'role' to IFW_users
    $pdo->exec("ALTER TABLE IFW_users ADD COLUMN role ENUM('admin', 'agent') DEFAULT 'admin' AFTER email");
    echo "Added role to IFW_users.\n";
} catch (PDOException $e) {
    echo "IFW_users.role might already exist: " . $e->getMessage() . "\n";
}

try {
    // 2. Add 'password_hash' and 'assigned_agent_id' to IFW_clients
    $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN password_hash VARCHAR(255) NULL AFTER email");
    $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN assigned_agent_id INT NULL AFTER password_hash");
    $pdo->exec("ALTER TABLE IFW_clients ADD FOREIGN KEY (assigned_agent_id) REFERENCES IFW_users(id) ON DELETE SET NULL");
    echo "Added password_hash and assigned_agent_id to IFW_clients.\n";
} catch (PDOException $e) {
    echo "IFW_clients changes might already exist: " . $e->getMessage() . "\n";
}

try {
    // 3. Add 'attachment_path' to IFW_messages
    $pdo->exec("ALTER TABLE IFW_messages ADD COLUMN attachment_path VARCHAR(255) NULL AFTER message_text");
    echo "Added attachment_path to IFW_messages.\n";
} catch (PDOException $e) {
    echo "IFW_messages.attachment_path might already exist: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN pin_hash VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN status ENUM('Received', 'Investigating', 'Evidence Gathered', 'Legal Action', 'Recovery') DEFAULT 'Received'");
    $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN private_notes TEXT NULL");
    $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN last_seen TIMESTAMP NULL");
    echo "Added Phase 2 fields to IFW_clients.<br>\n";
} catch (PDOException $e) { echo "IFW_clients phase 2 changes error: " . $e->getMessage() . "<br>\n"; }

try {
    $pdo->exec("ALTER TABLE IFW_messages ADD COLUMN is_read BOOLEAN DEFAULT FALSE");
    echo "Added is_read to IFW_messages.<br>\n";
} catch (PDOException $e) { echo "IFW_messages.is_read error: " . $e->getMessage() . "<br>\n"; }

try {
    $pdo->exec("CREATE TABLE IFW_audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(255) NOT NULL,
        details TEXT,
        ip_address VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES IFW_users(id) ON DELETE SET NULL
    )");
    echo "Created IFW_audit_logs table.<br>\n";
} catch (PDOException $e) { echo "IFW_audit_logs error: " . $e->getMessage() . "<br>\n"; }

try {
    $pdo->exec("CREATE TABLE IFW_chat_status (
        user_type ENUM('client', 'admin') NOT NULL,
        user_id INT NOT NULL,
        is_typing BOOLEAN DEFAULT FALSE,
        is_online BOOLEAN DEFAULT FALSE,
        last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_type, user_id)
    )");
    echo "Created IFW_chat_status table.<br>\n";
} catch (PDOException $e) { echo "IFW_chat_status error: " . $e->getMessage() . "<br>\n"; }

echo "Database upgrade completed.<br>\n";
?>




