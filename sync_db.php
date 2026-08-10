<?php
// sync_db.php
require_once __DIR__ . '/config.php';

echo "=== STARTING DATABASE SYNC ===\n";

try {
    // 1. Sync IFW_invoices amount and total_amount
    echo "Syncing IFW_invoices amounts...\n";
    $pdo->exec("UPDATE IFW_invoices SET amount = total_amount WHERE (amount IS NULL OR amount = 0) AND total_amount > 0");
    $pdo->exec("UPDATE IFW_invoices SET total_amount = amount WHERE (total_amount IS NULL OR total_amount = 0) AND amount > 0");
    $pdo->exec("UPDATE IFW_invoices SET currency = 'USD' WHERE currency IS NULL OR currency = ''");
    
    // 2. Sync client assigned_agent_id with case attorney_id
    echo "Syncing client assigned agents from cases...\n";
    $pdo->exec("
        UPDATE IFW_clients c 
        JOIN (
            SELECT client_id, attorney_id 
            FROM IFW_cases 
            WHERE attorney_id IS NOT NULL AND attorney_id > 0 
            GROUP BY client_id
        ) ca ON c.id = ca.client_id 
        SET c.assigned_agent_id = ca.attorney_id 
        WHERE c.assigned_agent_id IS NULL OR c.assigned_agent_id = 0
    ");

    // 3. Update full_name and roles in IFW_users where missing
    echo "Updating user display names and roles...\n";
    $pdo->exec("UPDATE IFW_users SET full_name = 'Gary Livingston' WHERE username = 'Gary009' AND (full_name IS NULL OR full_name = '' OR full_name = 'Gary009')");
    $pdo->exec("UPDATE IFW_users SET role = 'Senior Investigator' WHERE (username = 'Gary009' OR email LIKE '%gary%') AND (role = 'agent' OR role = 'staff' OR role IS NULL)");
    $pdo->exec("UPDATE IFW_users SET full_name = 'IFW Senior Case Director' WHERE username = 'admin' AND (full_name IS NULL OR full_name = '' OR full_name = 'admin')");

    // 4. Ensure late_fee_is_percentage and late_fee_accumulated columns exist
    try { $pdo->exec("ALTER TABLE IFW_invoices ADD COLUMN late_fee_is_percentage TINYINT(1) DEFAULT 0 AFTER late_fee_accumulated"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE IFW_invoices ADD COLUMN late_fee_accumulated DECIMAL(15,2) DEFAULT 0.00 AFTER late_fee_start_date"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE IFW_invoices ADD COLUMN last_reminder_sent DATETIME NULL AFTER late_fee_accumulated"); } catch(Exception $e) {}

    echo "=== DATABASE SYNC COMPLETED SUCCESSFULLY ===\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
