<?php
require_once 'config.php';

try {
    // Add assigned_agent_id column if it doesn't exist
    $pdo->exec("ALTER TABLE IFW_contact_submissions ADD COLUMN assigned_agent_id INT DEFAULT NULL");
    echo "Column assigned_agent_id added successfully.\n";
} catch(PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column assigned_agent_id already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
