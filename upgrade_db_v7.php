<?php
// upgrade_db_v7.php
// Run this script by visiting it in the browser or via curl
require_once 'config.php';
require_once 'includes/functions.php';

try {
    $pdo->beginTransaction();

    // 1. Create IFW_chat_messages table
    $sql_chat = "CREATE TABLE IFW_chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        sender_type ENUM('client', 'admin') NOT NULL,
        sender_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_client_id (client_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'IFW_chat_messages'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec($sql_chat);
        echo "Created IFW_chat_messages table.<br>\n";
    } else {
        echo "Table IFW_chat_messages already exists.<br>\n";
    }

    // 2. Add 'chat_provider' to settings
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM IFW_site_settings WHERE setting_key = 'chat_provider'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO IFW_site_settings (setting_key, setting_value) VALUES ('chat_provider', 'tawkto')");
        $stmt->execute();
        echo "Added 'chat_provider' to IFW_site_settings.<br>\n";
    } else {
        echo "'chat_provider' already exists.<br>\n";
    }

    $pdo->commit();
    echo "Database upgrade V7 completed successfully.<br>\n";

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error during upgrade: " . $e->getMessage());
}
?>
