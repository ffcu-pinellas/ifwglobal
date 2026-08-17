<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_admin_login();
require_permission('view_cases');

ensure_case_status_varchar($pdo);
$case_status_options = get_case_status_options();

$case_id = (int)($_GET['id'] ?? 0);
if (!$case_id) die("Invalid case ID.");

// Fetch Case Details
$stmt = $pdo->prepare("
    SELECT c.*, cl.first_name, cl.last_name, cl.email 
    FROM IFW_cases c 
    JOIN IFW_clients cl ON c.client_id = cl.id 
    WHERE c.id = ?
");
$stmt->execute([$case_id]);
$case = $stmt->fetch();

if (!$case) die("Case not found.");

// Check assignment if not admin/superadmin/manage_cases
if (!in_array($_SESSION['admin_role'], ['super_admin', 'superadmin', 'admin']) && !has_permission('manage_cases')) {
    // Fetch assigned agent of client
    $stmt_c = $pdo->prepare("SELECT assigned_agent_id FROM IFW_clients WHERE id = ?");
    $stmt_c->execute([$case['client_id']]);
    $assigned_agent = $stmt_c->fetchColumn();
    
    if ((int)$case['attorney_id'] !== (int)$_SESSION['admin_id'] && (int)$assigned_agent !== (int)$_SESSION['admin_id']) {
        die("Unauthorized to view this case.");
    }
}

// Ensure columns in IFW_cases & multi-agent case assignment table
try {
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS lifecycle_stage INT DEFAULT 1");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS progress_percent INT DEFAULT 20");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS amount_lost DECIMAL(15,2) DEFAULT 0.00");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS amount_recovered DECIMAL(15,2) DEFAULT 0.00");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS forensic_analyst_id INT NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS legal_counsel_id INT NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_1_title VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_2_title VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_3_title VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_4_title VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_5_title VARCHAR(255) NULL");
    
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_1_desc TEXT NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_2_desc TEXT NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_3_desc TEXT NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_4_desc TEXT NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_5_desc TEXT NULL");

    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_1_protocol VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_2_protocol VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_3_protocol VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_4_protocol VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_5_protocol VARCHAR(255) NULL");

    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_1_jurisdiction VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_2_jurisdiction VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_3_jurisdiction VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_4_jurisdiction VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS stage_5_jurisdiction VARCHAR(255) NULL");

    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS flow_node_1 VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS flow_node_2 VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS flow_node_3 VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS flow_node_4 VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS show_lifecycle_bar INT DEFAULT 1");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS show_flow_visualizer INT DEFAULT 1");

    // Multi-Staff Case Assignment & Capability Matrix
    $pdo->exec("CREATE TABLE IF NOT EXISTS IFW_case_agents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        case_id INT NOT NULL,
        user_id INT NOT NULL,
        case_role VARCHAR(100) DEFAULT 'Senior Investigator',
        can_view_financials TINYINT(1) DEFAULT 1,
        can_edit_timeline TINYINT(1) DEFAULT 1,
        can_chat_client TINYINT(1) DEFAULT 1,
        can_manage_wallets TINYINT(1) DEFAULT 1,
        can_upload_docs TINYINT(1) DEFAULT 1,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_case_user (case_id, user_id),
        KEY idx_case (case_id),
        KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Auto-seed attorney_id if not yet in IFW_case_agents
    if (!empty($case['attorney_id'])) {
        $chk_ca = $pdo->prepare("SELECT id FROM IFW_case_agents WHERE case_id = ? AND user_id = ?");
        $chk_ca->execute([$case_id, $case['attorney_id']]);
        if (!$chk_ca->fetch()) {
            $pdo->prepare("INSERT INTO IFW_case_agents (case_id, user_id, case_role, can_view_financials, can_edit_timeline, can_chat_client, can_manage_wallets, can_upload_docs) VALUES (?, ?, 'Lead Investigator', 1, 1, 1, 1, 1)")
                ->execute([$case_id, $case['attorney_id']]);
        }
    }
} catch(Exception $e) {}

// Handle Case Lifecycle & Details Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_case_stage') {
    $new_status = normalize_case_status($_POST['status'] ?? 'Investigating');
    $stage = max(1, min(5, (int)($_POST['lifecycle_stage'] ?? 1)));
    $custom_percent = isset($_POST['progress_percent']) && $_POST['progress_percent'] !== '' ? (int)$_POST['progress_percent'] : null;
    $default_percent = [1 => 20, 2 => 40, 3 => 60, 4 => 85, 5 => 100][$stage] ?? ($stage * 20);
    $stage_percent = $custom_percent !== null ? max(0, min(100, $custom_percent)) : $default_percent;
    $amount_lost = floatval($_POST['amount_lost'] ?? 0);
    $amount_recovered = floatval($_POST['amount_recovered'] ?? 0);
    
    $stage_1_title = trim($_POST['stage_1_title'] ?? '');
    $stage_2_title = trim($_POST['stage_2_title'] ?? '');
    $stage_3_title = trim($_POST['stage_3_title'] ?? '');
    $stage_4_title = trim($_POST['stage_4_title'] ?? '');
    $stage_5_title = trim($_POST['stage_5_title'] ?? '');

    $stage_1_desc = trim($_POST['stage_1_desc'] ?? '');
    $stage_2_desc = trim($_POST['stage_2_desc'] ?? '');
    $stage_3_desc = trim($_POST['stage_3_desc'] ?? '');
    $stage_4_desc = trim($_POST['stage_4_desc'] ?? '');
    $stage_5_desc = trim($_POST['stage_5_desc'] ?? '');

    $stage_1_protocol = trim($_POST['stage_1_protocol'] ?? '');
    $stage_2_protocol = trim($_POST['stage_2_protocol'] ?? '');
    $stage_3_protocol = trim($_POST['stage_3_protocol'] ?? '');
    $stage_4_protocol = trim($_POST['stage_4_protocol'] ?? '');
    $stage_5_protocol = trim($_POST['stage_5_protocol'] ?? '');

    $stage_1_jurisdiction = trim($_POST['stage_1_jurisdiction'] ?? '');
    $stage_2_jurisdiction = trim($_POST['stage_2_jurisdiction'] ?? '');
    $stage_3_jurisdiction = trim($_POST['stage_3_jurisdiction'] ?? '');
    $stage_4_jurisdiction = trim($_POST['stage_4_jurisdiction'] ?? '');
    $stage_5_jurisdiction = trim($_POST['stage_5_jurisdiction'] ?? '');

    $flow_node_1 = trim($_POST['flow_node_1'] ?? '');
    $flow_node_2 = trim($_POST['flow_node_2'] ?? '');
    $flow_node_3 = trim($_POST['flow_node_3'] ?? '');
    $flow_node_4 = trim($_POST['flow_node_4'] ?? '');

    $show_lifecycle_bar = isset($_POST['show_lifecycle_bar']) ? (int)$_POST['show_lifecycle_bar'] : 1;
    $show_flow_visualizer = isset($_POST['show_flow_visualizer']) ? (int)$_POST['show_flow_visualizer'] : 1;
    $show_blockchain_watcher = isset($_POST['show_blockchain_watcher']) ? (int)$_POST['show_blockchain_watcher'] : 0;
    $show_settlement_escrow = isset($_POST['show_settlement_escrow']) ? (int)$_POST['show_settlement_escrow'] : 0;
    $show_recovery_map = isset($_POST['show_recovery_map']) ? (int)$_POST['show_recovery_map'] : 0;

    $stmt = $pdo->prepare("UPDATE IFW_cases SET status = ?, lifecycle_stage = ?, progress_percent = ?, amount_lost = ?, amount_recovered = ?, stage_1_title = ?, stage_2_title = ?, stage_3_title = ?, stage_4_title = ?, stage_5_title = ?, stage_1_desc = ?, stage_2_desc = ?, stage_3_desc = ?, stage_4_desc = ?, stage_5_desc = ?, stage_1_protocol = ?, stage_2_protocol = ?, stage_3_protocol = ?, stage_4_protocol = ?, stage_5_protocol = ?, stage_1_jurisdiction = ?, stage_2_jurisdiction = ?, stage_3_jurisdiction = ?, stage_4_jurisdiction = ?, stage_5_jurisdiction = ?, flow_node_1 = ?, flow_node_2 = ?, flow_node_3 = ?, flow_node_4 = ?, show_lifecycle_bar = ?, show_flow_visualizer = ?, show_blockchain_watcher = ?, show_settlement_escrow = ?, show_recovery_map = ? WHERE id = ?");
    $stmt->execute([$new_status, $stage, $stage_percent, $amount_lost, $amount_recovered, $stage_1_title, $stage_2_title, $stage_3_title, $stage_4_title, $stage_5_title, $stage_1_desc, $stage_2_desc, $stage_3_desc, $stage_4_desc, $stage_5_desc, $stage_1_protocol, $stage_2_protocol, $stage_3_protocol, $stage_4_protocol, $stage_5_protocol, $stage_1_jurisdiction, $stage_2_jurisdiction, $stage_3_jurisdiction, $stage_4_jurisdiction, $stage_5_jurisdiction, $flow_node_1, $flow_node_2, $flow_node_3, $flow_node_4, $show_lifecycle_bar, $show_flow_visualizer, $show_blockchain_watcher, $show_settlement_escrow, $show_recovery_map, $case_id]);
    
    // Also sync settlement table is_enabled
    try {
        $pdo->prepare("UPDATE IFW_case_settlements SET is_enabled = ? WHERE case_id = ?")->execute([$show_settlement_escrow, $case_id]);
    } catch (Exception $e) {}

    if (function_exists('log_audit_action')) {
        log_audit_action($pdo, $_SESSION['admin_id'], 'Case Progress Update', "Updated Case #{$case['case_number']} to Stage {$stage} ({$new_status}) [Features: Watcher={$show_blockchain_watcher}, Escrow={$show_settlement_escrow}, Radar={$show_recovery_map}]", 'admin');
    }
    
    // Refresh case record
    $stmt = $pdo->prepare("SELECT c.*, cl.first_name, cl.last_name, cl.email as client_email, cl.phone as client_phone FROM IFW_cases c JOIN IFW_clients cl ON c.client_id = cl.id WHERE c.id = ?");
    $stmt->execute([$case_id]);
    $case = $stmt->fetch();
    $success = "Investigation lifecycle stage, custom milestones, and feature visibility saved successfully.";
}

// Handle Notes Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_note') {
    $note = trim($_POST['note']);
    if (!empty($note)) {
        $stmt = $pdo->prepare("INSERT INTO IFW_case_notes (client_id, case_id, agent_id, note) VALUES (?, ?, ?, ?)");
        $stmt->execute([$case['client_id'], $case_id, $_SESSION['admin_id'], $note]);
        $success = "Note added successfully.";
    }
}

// Handle Timeline Event Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_timeline') {
    $milestone_title = trim($_POST['milestone_title']);
    $milestone_body = trim($_POST['milestone_body']);
    $milestone_date = !empty($_POST['milestone_date']) ? $_POST['milestone_date'] : date('Y-m-d');
    $status_color = $_POST['status_color'] ?? 'primary';
    $is_client_visible = isset($_POST['is_client_visible']) ? 1 : 0;
    
    if (!empty($milestone_title)) {
        $stmt = $pdo->prepare("INSERT INTO IFW_case_timeline (case_id, created_by, milestone_title, milestone_body, milestone_date, status_color, is_client_visible) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$case_id, $_SESSION['admin_id'], $milestone_title, $milestone_body, $milestone_date, $status_color, $is_client_visible]);
        
        // Add a notification for client
        try {
            $notif_title = "Case Update: " . $milestone_title;
            $notif_body = substr($milestone_body, 0, 100);
            $stmt_notif = $pdo->prepare("INSERT INTO IFW_notifications (client_id, type, title, body, icon, link) VALUES (?, 'case_update', ?, ?, 'briefcase', '/client/my_cases.php?case_id=')");
            $stmt_notif->execute([$case['client_id'], $notif_title, $notif_body]);
            $last_notif_id = $pdo->lastInsertId();
            // Update the link to point to this case
            $pdo->prepare("UPDATE IFW_notifications SET link = ? WHERE id = ?")->execute(['/client/my_cases.php?case_id=' . $case_id, $last_notif_id]);
        } catch(Exception $e) {}

        // Dispatch branded milestone notification email to client
        if ($is_client_visible && !empty($case['client_email'])) {
            try {
                $client_full_name = trim(($case['first_name'] ?? '') . ' ' . ($case['last_name'] ?? ''));
                send_case_milestone_email($pdo, $case['client_email'], $client_full_name, $case['case_number'], $case['title'], $milestone_title, $milestone_body, $milestone_date, $case_id);
            } catch(Exception $e) {}
        }
        
        $success = "Milestone added successfully and dispatched to client email.";
    }
}

// Handle Timeline Event Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_timeline') {
    $timeline_id = (int)$_POST['timeline_id'];
    $stmt = $pdo->prepare("DELETE FROM IFW_case_timeline WHERE id = ? AND case_id = ?");
    $stmt->execute([$timeline_id, $case_id]);
    $success = "Timeline milestone removed.";
}

// Handle Document Upload to Vault
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_document') {
    if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['doc_file']['tmp_name'];
        $file_name = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['doc_file']['name']);
        $doc_type = $_POST['document_type_select'] ?? 'Standard';
        if ($doc_type === 'Other' && !empty($_POST['document_type_custom'])) {
            $doc_type = trim($_POST['document_type_custom']);
        }
        $requires_sig = isset($_POST['requires_signature']) ? 1 : 0;
        
        $base_dir = dirname(__DIR__);
        $target_dir = $base_dir . '/uploads/vault/';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $new_filename = time() . '_' . $file_name;
        $target_file = $target_dir . $new_filename;
        $db_path = 'uploads/vault/' . $new_filename;
        
        if (move_uploaded_file($file_tmp, $target_file)) {
            $stmt = $pdo->prepare("INSERT INTO IFW_documents (client_id, file_name, file_path, document_type, requires_signature) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$case['client_id'], $file_name, $db_path, $doc_type, $requires_sig]);
            
            // Dispatch notification email to client
            if (!empty($case['client_email'])) {
                try {
                    $client_full_name = trim(($case['first_name'] ?? '') . ' ' . ($case['last_name'] ?? ''));
                    send_case_milestone_email($pdo, $case['client_email'], $client_full_name, $case['case_number'], $case['title'], "New Document Deposited: " . $file_name, "Your case investigator has deposited a new official document ({$doc_type}) into your secure Document Vault.", date('Y-m-d'), $case_id);
                } catch(Exception $e) {}
            }

            $success = "Document uploaded successfully to client vault and notification dispatched.";
        } else {
            $error = "Failed to save uploaded file.";
        }
    } else {
        $error = "Please choose a valid file to upload.";
    }
}

// Handle Custom Dynamic Document Vault Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_custom_document') {
    $doc_name = trim($_POST['document_name'] ?? '');
    $doc_type = trim($_POST['document_type'] ?? 'Standard');
    $doc_body = trim($_POST['document_body'] ?? '');
    $requires_sig = isset($_POST['requires_signature']) ? 1 : 0;
    
    if (empty($doc_name) || empty($doc_body)) {
        $error = "Document Title and Body Content are required.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO IFW_documents (client_id, file_name, file_path, document_type, document_body, requires_signature) VALUES (?, ?, NULL, ?, ?, ?)");
        $stmt->execute([$case['client_id'], $doc_name, $doc_type, $doc_body, $requires_sig]);
        $success = "Custom dynamic document has been created and sent to client vault.";
    }
}

// Handle Document Deletion from Vault
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_document') {
    $doc_id = (int)$_POST['document_id'];
    $base_dir = dirname(__DIR__);
    
    // Fetch file path
    $fStmt = $pdo->prepare("SELECT file_path FROM IFW_documents WHERE id = ? AND client_id = ?");
    $fStmt->execute([$doc_id, $case['client_id']]);
    $doc_file = $fStmt->fetch();
    
    if ($doc_file) {
        @unlink($base_dir . '/' . $doc_file['file_path']);
        $pdo->prepare("DELETE FROM IFW_documents WHERE id = ?")->execute([$doc_id]);
        $success = "Document deleted from vault.";
    }
}

// Handle Add Blockchain Wallet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_blockchain_wallet') {
    $crypto_type = trim($_POST['crypto_type'] ?? 'USDT (TRC-20)');
    $wallet_address = trim($_POST['wallet_address'] ?? '');
    $wallet_label = trim($_POST['wallet_label'] ?? '');
    $balance = floatval($_POST['balance'] ?? 0);
    $usd_value = floatval($_POST['usd_value'] ?? $balance);
    $risk_score = max(0, min(100, intval($_POST['risk_score'] ?? 90)));
    $threat_level = trim($_POST['threat_level'] ?? 'HIGH RISK');
    $exchange_tags = trim($_POST['exchange_tags'] ?? '');
    $status = trim($_POST['status'] ?? 'Active Monitoring');
    $notes = trim($_POST['notes'] ?? '');

    if (!empty($wallet_address)) {
        $stmt = $pdo->prepare("INSERT INTO IFW_blockchain_wallets (case_id, crypto_type, wallet_address, wallet_label, balance, usd_value, risk_score, threat_level, exchange_tags, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$case_id, $crypto_type, $wallet_address, $wallet_label, $balance, $usd_value, $risk_score, $threat_level, $exchange_tags, $status, $notes]);
        $success = "Tracked target blockchain wallet added successfully.";
    }
}

// Handle Delete Blockchain Wallet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_blockchain_wallet') {
    $w_id = (int)$_POST['wallet_id'];
    $pdo->prepare("DELETE FROM IFW_blockchain_wallets WHERE id = ? AND case_id = ?")->execute([$w_id, $case_id]);
    $success = "Tracked target wallet removed from monitoring.";
}

// Handle Add Blockchain Transaction Hop
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_blockchain_tx') {
    $tx_hash = trim($_POST['tx_hash'] ?? '');
    $from_addr = trim($_POST['from_address'] ?? '');
    $to_addr = trim($_POST['to_address'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $crypto_type = trim($_POST['crypto_type'] ?? 'USDT');
    $direction = trim($_POST['direction'] ?? 'OUT');
    $flag_tag = trim($_POST['flag_tag'] ?? 'Exchange Deposit Hop');
    $tx_time = !empty($_POST['tx_time']) ? $_POST['tx_time'] : date('Y-m-d H:i:s');

    if (!empty($tx_hash)) {
        $stmt = $pdo->prepare("INSERT INTO IFW_blockchain_txs (case_id, tx_hash, from_address, to_address, amount, crypto_type, direction, flag_tag, tx_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$case_id, $tx_hash, $from_addr, $to_addr, $amount, $crypto_type, $direction, $flag_tag, $tx_time]);
        $success = "On-chain transaction hop logged successfully.";
    }
}

// Handle Delete Blockchain Transaction Hop
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_blockchain_tx') {
    $tx_id = (int)$_POST['tx_id'];
    $pdo->prepare("DELETE FROM IFW_blockchain_txs WHERE id = ? AND case_id = ?")->execute([$tx_id, $case_id]);
    $success = "Transaction hop removed.";
}

// Handle Update Case Settlement & Escrow Hub
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_case_settlement') {
    $gross_recovered = floatval($_POST['gross_recovered'] ?? 0);
    $fee_percent = floatval($_POST['fee_percent'] ?? 10);
    $fee_amount = $gross_recovered * ($fee_percent / 100);
    $net_payout = $gross_recovered - $fee_amount;
    $escrow_ref = trim($_POST['escrow_ref'] ?? ('IFW-ESCROW-' . date('Y') . '-' . $case_id));
    $custody_entity = trim($_POST['custody_entity'] ?? 'Swiss Multi-Sig Escrow Vault (FINMA Compliant)');
    $clearance_stage = max(1, min(5, intval($_POST['clearance_stage'] ?? 1)));
    $status = trim($_POST['status'] ?? 'Secured in Escrow');
    $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');

    // Check if settlement record exists
    $chk = $pdo->prepare("SELECT id FROM IFW_case_settlements WHERE case_id = ?");
    $chk->execute([$case_id]);
    if ($s_row = $chk->fetch()) {
        $stmt = $pdo->prepare("UPDATE IFW_case_settlements SET gross_recovered = ?, fee_percent = ?, fee_amount = ?, net_payout = ?, escrow_ref = ?, custody_entity = ?, clearance_stage = ?, status = ?, is_enabled = ?, notes = ? WHERE id = ?");
        $stmt->execute([$gross_recovered, $fee_percent, $fee_amount, $net_payout, $escrow_ref, $custody_entity, $clearance_stage, $status, $is_enabled, $notes, $s_row['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO IFW_case_settlements (case_id, client_id, gross_recovered, fee_percent, fee_amount, net_payout, escrow_ref, custody_entity, clearance_stage, status, is_enabled, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$case_id, $case['client_id'], $gross_recovered, $fee_percent, $fee_amount, $net_payout, $escrow_ref, $custody_entity, $clearance_stage, $status, $is_enabled, $notes]);
    }
    
    // Sync with IFW_cases table
    $pdo->prepare("UPDATE IFW_cases SET show_settlement_escrow = ? WHERE id = ?")->execute([$is_enabled, $case_id]);

    $success = "Recovery Escrow & Settlement Hub configuration saved.";
}

// Handle Quick Feature Toggles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_feature_watcher') {
    $val = isset($_POST['show_blockchain_watcher']) ? (int)$_POST['show_blockchain_watcher'] : 0;
    $pdo->prepare("UPDATE IFW_cases SET show_blockchain_watcher = ? WHERE id = ?")->execute([$val, $case_id]);
    $success = "Blockchain Forensic Watcher " . ($val ? 'enabled (visible in client nav)' : 'disabled (hidden from client nav)') . ".";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_feature_radar') {
    $val = isset($_POST['show_recovery_map']) ? (int)$_POST['show_recovery_map'] : 0;
    $pdo->prepare("UPDATE IFW_cases SET show_recovery_map = ? WHERE id = ?")->execute([$val, $case_id]);
    $success = "Global Recovery Radar Map " . ($val ? 'enabled (visible in client nav)' : 'disabled (hidden from client nav)') . ".";
}

// Handle Add Case Jurisdiction Pin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_case_jurisdiction') {
    $country_code = strtoupper(trim($_POST['country_code'] ?? 'US'));
    $country_name = trim($_POST['country_name'] ?? 'United States');
    $city_court = trim($_POST['city_court'] ?? '');
    $action_type = trim($_POST['action_type'] ?? 'Worldwide Freezing Order (Mareva Injunction)');
    $case_ref = trim($_POST['case_ref'] ?? '');
    $status = trim($_POST['status'] ?? 'Active Freeze Order');
    $date_filed = !empty($_POST['date_filed']) ? $_POST['date_filed'] : date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');

    $stmt = $pdo->prepare("INSERT INTO IFW_case_jurisdictions (case_id, country_code, country_name, city_court, action_type, case_ref, status, date_filed, notes, is_enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([$case_id, $country_code, $country_name, $city_court, $action_type, $case_ref, $status, $date_filed, $notes]);
    $success = "Global jurisdiction action pin added to radar map.";
}

// Handle Delete Case Jurisdiction Pin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_case_jurisdiction') {
    $j_id = (int)$_POST['jurisdiction_id'];
    $pdo->prepare("DELETE FROM IFW_case_jurisdictions WHERE id = ? AND case_id = ?")->execute([$j_id, $case_id]);
    $success = "Jurisdiction action pin removed.";
}

// Handle Assign Staff Member to Case
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_case_agent') {
    $staff_user_id = (int)($_POST['staff_user_id'] ?? 0);
    $case_role = trim($_POST['case_role'] ?? 'Senior Investigator');
    $can_fin = isset($_POST['can_view_financials']) ? 1 : 0;
    $can_time = isset($_POST['can_edit_timeline']) ? 1 : 0;
    $can_chat = isset($_POST['can_chat_client']) ? 1 : 0;
    $can_wal = isset($_POST['can_manage_wallets']) ? 1 : 0;
    $can_doc = isset($_POST['can_upload_docs']) ? 1 : 0;

    if ($staff_user_id > 0) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO IFW_case_agents (case_id, user_id, case_role, can_view_financials, can_edit_timeline, can_chat_client, can_manage_wallets, can_upload_docs)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    case_role = VALUES(case_role),
                    can_view_financials = VALUES(can_view_financials),
                    can_edit_timeline = VALUES(can_edit_timeline),
                    can_chat_client = VALUES(can_chat_client),
                    can_manage_wallets = VALUES(can_manage_wallets),
                    can_upload_docs = VALUES(can_upload_docs)
            ");
            $stmt->execute([$case_id, $staff_user_id, $case_role, $can_fin, $can_time, $can_chat, $can_wal, $can_doc]);
            
            // If designated as Lead Investigator, also update attorney_id on the case
            if (stripos($case_role, 'Lead') !== false) {
                $pdo->prepare("UPDATE IFW_cases SET attorney_id = ? WHERE id = ?")->execute([$staff_user_id, $case_id]);
            }
            
            if (function_exists('log_audit_action')) {
                log_audit_action($pdo, $_SESSION['admin_id'], 'Case Team Update', "Assigned staff member ID #{$staff_user_id} ({$case_role}) to Case #{$case['case_number']}", 'admin');
            }
            $success = "Staff member successfully assigned to case with designated permissions.";
        } catch (Exception $e) {
            $error = "Failed to assign staff member: " . $e->getMessage();
        }
    } else {
        $error = "Please select a valid staff member.";
    }
}

// Handle Update Case Staff Permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_case_agent_perms') {
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);
    $case_role = trim($_POST['case_role'] ?? 'Senior Investigator');
    $can_fin = isset($_POST['can_view_financials']) ? 1 : 0;
    $can_time = isset($_POST['can_edit_timeline']) ? 1 : 0;
    $can_chat = isset($_POST['can_chat_client']) ? 1 : 0;
    $can_wal = isset($_POST['can_manage_wallets']) ? 1 : 0;
    $can_doc = isset($_POST['can_upload_docs']) ? 1 : 0;

    try {
        $stmt = $pdo->prepare("UPDATE IFW_case_agents SET case_role = ?, can_view_financials = ?, can_edit_timeline = ?, can_chat_client = ?, can_manage_wallets = ?, can_upload_docs = ? WHERE id = ? AND case_id = ?");
        $stmt->execute([$case_role, $can_fin, $can_time, $can_chat, $can_wal, $can_doc, $assignment_id, $case_id]);
        
        // If lead, sync attorney_id
        if (stripos($case_role, 'Lead') !== false) {
            $u_stmt = $pdo->prepare("SELECT user_id FROM IFW_case_agents WHERE id = ?");
            $u_stmt->execute([$assignment_id]);
            $uid = $u_stmt->fetchColumn();
            if ($uid) {
                $pdo->prepare("UPDATE IFW_cases SET attorney_id = ? WHERE id = ?")->execute([$uid, $case_id]);
            }
        }
        
        $success = "Assigned staff role and case permissions updated successfully.";
    } catch (Exception $e) {
        $error = "Error updating staff case permissions.";
    }
}

// Handle Remove Staff from Case
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_case_agent') {
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("DELETE FROM IFW_case_agents WHERE id = ? AND case_id = ?");
        $stmt->execute([$assignment_id, $case_id]);
        $success = "Staff member removed from this case assignment.";
    } catch (Exception $e) {
        $error = "Error removing staff member.";
    }
}

// Fetch Assigned Investigation Team for Case
$assigned_team = [];
try {
    $teamStmt = $pdo->prepare("
        SELECT ca.*, u.username, u.full_name, u.email, u.phone, u.role as user_role, u.custom_role_title, u.avatar_url 
        FROM IFW_case_agents ca 
        JOIN IFW_users u ON ca.user_id = u.id 
        WHERE ca.case_id = ? 
        ORDER BY (ca.case_role LIKE '%Lead%') DESC, ca.assigned_at ASC
    ");
    $teamStmt->execute([$case_id]);
    $assigned_team = $teamStmt->fetchAll();
} catch(Exception $e) {}

// Fetch All Available Staff Members for Assignment Dropdown
$all_staff = [];
try {
    $all_staff = $pdo->query("SELECT id, username, full_name, email, role, custom_role_title FROM IFW_users ORDER BY username ASC")->fetchAll();
} catch(Exception $e) {}

// Fetch Notes
$notesStmt = $pdo->prepare("
    SELECT n.*, u.username 
    FROM IFW_case_notes n 
    JOIN IFW_users u ON n.agent_id = u.id 
    WHERE n.case_id = ? 
    ORDER BY n.created_at DESC
");
$notesStmt->execute([$case_id]);
$notes = $notesStmt->fetchAll();

// Fetch Timeline Events
$timelineStmt = $pdo->prepare("
    SELECT t.*, u.username 
    FROM IFW_case_timeline t 
    LEFT JOIN IFW_users u ON t.created_by = u.id 
    WHERE t.case_id = ? 
    ORDER BY t.milestone_date DESC, t.created_at DESC
");
$timelineStmt->execute([$case_id]);
$timeline_events = $timelineStmt->fetchAll();

// Fetch Client Documents
$docsStmt = $pdo->prepare("SELECT * FROM IFW_documents WHERE client_id = ? ORDER BY uploaded_at DESC");
$docsStmt->execute([$case['client_id']]);
$documents = $docsStmt->fetchAll();

// Fetch Blockchain Wallets & TXs for Case
$wStmt = $pdo->prepare("SELECT * FROM IFW_blockchain_wallets WHERE case_id = ? ORDER BY id ASC");
$wStmt->execute([$case_id]);
$case_wallets = $wStmt->fetchAll();

$txStmt = $pdo->prepare("SELECT * FROM IFW_blockchain_txs WHERE case_id = ? ORDER BY tx_time DESC LIMIT 20");
$txStmt->execute([$case_id]);
$case_txs = $txStmt->fetchAll();

// Fetch Settlement for Case
$sStmt = $pdo->prepare("SELECT * FROM IFW_case_settlements WHERE case_id = ? LIMIT 1");
$sStmt->execute([$case_id]);
$case_settlement = $sStmt->fetch();

// Fetch Jurisdictions for Case
$jStmt = $pdo->prepare("SELECT * FROM IFW_case_jurisdictions WHERE case_id = ? ORDER BY date_filed DESC");
$jStmt->execute([$case_id]);
$case_jurisdictions = $jStmt->fetchAll();

$_SESSION['role'] = $_SESSION['admin_role'] ?? 'admin';
$_SESSION['user_name'] = $_SESSION['admin_username'] ?? 'Admin';
?>

<?php require_once '../includes/admin_header.php'; ?>
<?php require_once '../includes/admin_sidebar.php'; ?>

<!-- PAGE CONTENT -->
<div class="row">
    <div class="col-12 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="text-white font-weight-bold mb-1"><?php echo htmlspecialchars($case['title']); ?></h4>
            <p class="text-muted mb-0">Case #<?php echo htmlspecialchars($case['case_number']); ?> &middot; Client: <strong class="text-warning"><?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?></strong></p>
        </div>
        <div>
            <?php
            $badge = 'secondary';
            if ($case['status'] === 'active') $badge = 'success';
            if ($case['status'] === 'pending') $badge = 'warning';
            ?>
            <span class="badge badge-<?php echo $badge; ?> p-2 px-3 font-weight-bold" style="font-size: 13px;">
                <?php echo ucfirst($case['status']); ?>
            </span>
        </div>
    </div>

    <div class="col-12">
        <?php if(isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="material-icons mr-2" style="vertical-align: middle;">check_circle</i> <?php echo $success; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <!-- INVESTIGATION LIFECYCLE & STAGE CONTROLLER (PILLAR 3 ENTERPRISE FEATURE) -->
        <div class="card border-0 shadow-sm mb-4 bg-dark text-white border-warning">
            <div class="card-header bg-dark border-secondary text-warning font-weight-bold d-flex justify-content-between align-items-center py-3">
                <span><i class="fas fa-stream mr-2"></i>Investigation & Asset Recovery Lifecycle Manager</span>
                <span class="badge badge-warning text-dark font-weight-bold px-3 py-1">Active Stage <?= (int)($case['lifecycle_stage'] ?? 1) ?> (<?= (int)($case['progress_percent'] ?? 20) ?>%)</span>
            </div>
            <div class="card-body bg-dark text-white p-4">
                <form method="POST">
                    <input type="hidden" name="action" value="update_case_stage">
                    
                    <div class="row mb-3">
                        <div class="col-md-6 form-group">
                            <label class="small text-warning font-weight-bold text-uppercase">Case Recovery Lifecycle Stage</label>
                            <?php $curr_stage = (int)($case['lifecycle_stage'] ?? 1); ?>
                            <select name="lifecycle_stage" class="form-control bg-black text-warning border-secondary font-weight-bold" onchange="document.getElementById('progress_percent_input').value = [0,20,40,60,85,100][this.value] || 20;">
                                <option value="1" <?= $curr_stage === 1 ? 'selected' : '' ?>>Stage 1: <?= htmlspecialchars($case['stage_1_title'] ?: 'Intake & KYC Verification') ?> (20%)</option>
                                <option value="2" <?= $curr_stage === 2 ? 'selected' : '' ?>>Stage 2: <?= htmlspecialchars($case['stage_2_title'] ?: 'Crypto & Asset Tracing') ?> (40%)</option>
                                <option value="3" <?= $curr_stage === 3 ? 'selected' : '' ?>>Stage 3: <?= htmlspecialchars($case['stage_3_title'] ?: 'Evidence Dossier Formulation') ?> (60%)</option>
                                <option value="4" <?= $curr_stage === 4 ? 'selected' : '' ?>>Stage 4: <?= htmlspecialchars($case['stage_4_title'] ?: 'Legal Injunction & Regulatory Filing') ?> (85%)</option>
                                <option value="5" <?= $curr_stage === 5 ? 'selected' : '' ?>>Stage 5: <?= htmlspecialchars($case['stage_5_title'] ?: 'Asset Recovery & Settlement') ?> (100%)</option>
                            </select>
                            <small class="text-muted">Select active milestone or override exact percent below.</small>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="small text-light font-weight-bold text-uppercase">Progress %</label>
                            <input type="number" min="0" max="100" name="progress_percent" id="progress_percent_input" class="form-control bg-dark text-warning font-weight-bold border-secondary" value="<?= (int)($case['progress_percent'] ?? 20) ?>">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="small text-light font-weight-bold text-uppercase">Case Status</label>
                            <?php $c_stat = normalize_case_status($case['status'] ?? 'Received'); ?>
                            <select name="status" class="form-control bg-dark text-white border-secondary">
                                <?php foreach ($case_status_options as $val => $label): ?>
                                    <option value="<?= htmlspecialchars($val) ?>" <?= $c_stat === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6 form-group">
                            <label class="small text-light font-weight-bold text-uppercase">Claim Loss Amount (USD)</label>
                            <input type="number" step="0.01" name="amount_lost" class="form-control bg-dark text-danger font-weight-bold border-secondary" value="<?= htmlspecialchars($case['amount_lost'] ?? '0.00') ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="small text-light font-weight-bold text-uppercase">Recovered / Frozen Amount (USD)</label>
                            <input type="number" step="0.01" name="amount_recovered" class="form-control bg-dark text-success font-weight-bold border-secondary" value="<?= htmlspecialchars($case['amount_recovered'] ?? '0.00') ?>">
                        </div>
                    </div>

                    <!-- MODULE TOGGLES FOR THIS CLIENT -->
                    <div class="row mb-3">
                        <div class="col-md-6 form-group">
                            <label class="small text-light font-weight-bold text-uppercase">Lifecycle Progress Tracker</label>
                            <select name="show_lifecycle_bar" class="form-control bg-dark text-white border-secondary">
                                <option value="1" <?= ($case['show_lifecycle_bar'] ?? 1) == 1 ? 'selected' : '' ?>>Visible to Client (Enabled)</option>
                                <option value="0" <?= ($case['show_lifecycle_bar'] ?? 1) == 0 ? 'selected' : '' ?>>Hidden from Client (Disabled)</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="small text-light font-weight-bold text-uppercase">Fund Flow Diagram</label>
                            <select name="show_flow_visualizer" class="form-control bg-dark text-white border-secondary">
                                <option value="1" <?= ($case['show_flow_visualizer'] ?? 1) == 1 ? 'selected' : '' ?>>Visible to Client (Enabled)</option>
                                <option value="0" <?= ($case['show_flow_visualizer'] ?? 1) == 0 ? 'selected' : '' ?>>Hidden from Client (Disabled)</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="small text-warning font-weight-bold text-uppercase"><i class="fas fa-cubes mr-1"></i>1. Blockchain Watcher</label>
                            <select name="show_blockchain_watcher" class="form-control bg-dark text-white border-secondary">
                                <option value="0" <?= ($case['show_blockchain_watcher'] ?? 0) == 0 ? 'selected' : '' ?>>🔴 Disabled (Default - Hidden in Nav)</option>
                                <option value="1" <?= ($case['show_blockchain_watcher'] ?? 0) == 1 ? 'selected' : '' ?>>🟢 Enabled (Visible in Client Nav)</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="small text-warning font-weight-bold text-uppercase"><i class="fas fa-vault mr-1"></i>2. Escrow & Settlement</label>
                            <select name="show_settlement_escrow" class="form-control bg-dark text-white border-secondary">
                                <option value="0" <?= ($case['show_settlement_escrow'] ?? 0) == 0 ? 'selected' : '' ?>>🔴 Disabled (Default - Hidden in Nav)</option>
                                <option value="1" <?= ($case['show_settlement_escrow'] ?? 0) == 1 ? 'selected' : '' ?>>🟢 Enabled (Visible in Client Nav)</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="small text-warning font-weight-bold text-uppercase"><i class="fas fa-globe-americas mr-1"></i>3. Recovery Radar Map</label>
                            <select name="show_recovery_map" class="form-control bg-dark text-white border-secondary">
                                <option value="0" <?= ($case['show_recovery_map'] ?? 0) == 0 ? 'selected' : '' ?>>🔴 Disabled (Default - Hidden in Nav)</option>
                                <option value="1" <?= ($case['show_recovery_map'] ?? 0) == 1 ? 'selected' : '' ?>>🟢 Enabled (Visible in Client Nav)</option>
                            </select>
                        </div>
                    </div>

                    <!-- CUSTOMIZABLE STEP & FLOW LABELS ACCORDION -->
                    <div class="mb-3">
                        <button class="btn btn-sm btn-outline-secondary text-warning font-weight-bold w-100 text-left py-2" type="button" data-toggle="collapse" data-target="#customLabelsCollapse">
                            <i class="fas fa-sliders-h mr-2"></i>Customize 5 Stage Labels & 4 Flow Nodes for this Client (Click to Expand)
                        </button>
                        <div class="collapse mt-3" id="customLabelsCollapse">
                            <div class="p-3 border border-secondary rounded" style="background:#11151e;">
                                <h6 class="text-warning font-weight-bold small text-uppercase mb-2"><i class="fas fa-list-ol mr-1"></i>5 Lifecycle Stage Names</h6>
                                <div class="row mb-3">
                                    <div class="col-md-4 mb-2">
                                        <label class="small text-muted">Stage 1</label>
                                        <input type="text" name="stage_1_title" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($case['stage_1_title'] ?? '1. Intake & KYC') ?>" placeholder="1. Intake & KYC">
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label class="small text-muted">Stage 2</label>
                                        <input type="text" name="stage_2_title" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($case['stage_2_title'] ?? '2. Crypto & Asset Tracing') ?>" placeholder="2. Crypto & Asset Tracing">
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label class="small text-muted">Stage 3</label>
                                        <input type="text" name="stage_3_title" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($case['stage_3_title'] ?? '3. Evidence Dossier') ?>" placeholder="3. Evidence Dossier">
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="small text-muted">Stage 4</label>
                                        <input type="text" name="stage_4_title" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($case['stage_4_title'] ?? '4. Legal & Regulatory Filing') ?>" placeholder="4. Legal & Regulatory Filing">
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="small text-muted">Stage 5</label>
                                        <input type="text" name="stage_5_title" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($case['stage_5_title'] ?? '5. Asset Recovery') ?>" placeholder="5. Asset Recovery">
                                    </div>
                                </div>

                                <h6 class="text-warning font-weight-bold small text-uppercase mb-2 mt-3"><i class="fas fa-satellite mr-1"></i>Stage 1-5 Custom Forensic Telemetry, Descriptions &amp; Authorities (Client View)</h6>
                                <?php for ($s_idx = 1; $s_idx <= 5; $s_idx++): ?>
                                    <div class="p-2 mb-3 rounded border border-secondary" style="background:#0b0e14;">
                                        <span class="badge badge-warning text-dark font-weight-bold mb-2">Stage <?= $s_idx ?> Telemetry Details</span>
                                        <div class="row">
                                            <div class="col-md-12 mb-2">
                                                <label class="small text-muted">Operational Briefing / Description (Client View)</label>
                                                <textarea name="stage_<?= $s_idx ?>_desc" class="form-control form-control-sm bg-dark text-white border-secondary" rows="2" placeholder="Briefing text shown to client when clicking Stage <?= $s_idx ?>"><?= htmlspecialchars($case['stage_'.$s_idx.'_desc'] ?? '') ?></textarea>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label class="small text-muted">Cryptographic Protocol / Ledger Focus</label>
                                                <input type="text" name="stage_<?= $s_idx ?>_protocol" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($case['stage_'.$s_idx.'_protocol'] ?? '') ?>" placeholder="e.g. On-Chain Heuristic Node Tracking (ETH/BTC/TRC20)">
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label class="small text-muted">Jurisdiction Authority / Legal Filing</label>
                                                <input type="text" name="stage_<?= $s_idx ?>_jurisdiction" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($case['stage_'.$s_idx.'_jurisdiction'] ?? '') ?>" placeholder="e.g. US Federal Court / INTERPOL ICPO Taskforce">
                                            </div>
                                        </div>
                                    </div>
                                <?php endfor; ?>

                                <h6 class="text-warning font-weight-bold small text-uppercase mb-2 mt-3"><i class="fas fa-network-wired mr-1"></i>4 Fund Flow Diagram Nodes</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="small text-muted">Node 1</label>
                                        <input type="text" name="flow_node_1" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($case['flow_node_1'] ?? '1. Rogue Infiltration') ?>" placeholder="1. Rogue Infiltration">
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="small text-muted">Node 2</label>
                                        <input type="text" name="flow_node_2" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($case['flow_node_2'] ?? '2. On-Chain Tracing') ?>" placeholder="2. On-Chain Tracing">
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="small text-muted">Node 3</label>
                                        <input type="text" name="flow_node_3" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($case['flow_node_3'] ?? '3. Asset Freezing') ?>" placeholder="3. Asset Freezing">
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="small text-muted">Node 4</label>
                                        <input type="text" name="flow_node_4" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($case['flow_node_4'] ?? '4. Client Repatriation') ?>" placeholder="4. Client Repatriation">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Changes are logged in audit trail and immediately visible to client.</small>
                        <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4 shadow">
                            <i class="fas fa-save mr-1"></i> Update Case Lifecycle & Progress
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ASSIGNED CASE INVESTIGATION TEAM & PERMISSIONS MATRIX -->
        <div class="card border-0 shadow-sm mb-4 bg-dark text-white border-secondary">
            <div class="card-header bg-dark border-secondary text-warning font-weight-bold d-flex justify-content-between align-items-center py-3">
                <span><i class="fas fa-users-cog mr-2"></i>Assigned Case Investigation Team (<?= count($assigned_team) ?>)</span>
                <button type="button" class="btn btn-warning btn-sm font-weight-bold text-dark" data-toggle="modal" data-target="#assignStaffModal">
                    <i class="fas fa-user-plus mr-1"></i> Assign Staff Member
                </button>
            </div>
            <div class="card-body bg-dark text-white p-3">
                <?php if (empty($assigned_team)): ?>
                    <p class="text-muted text-center py-3 mb-0">No staff members currently assigned to this case.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0" style="font-size:13px;">
                            <thead style="background:#161a23; color:#fecc56;">
                                <tr>
                                    <th>Staff Member</th>
                                    <th>Assigned Role</th>
                                    <th>Granular Case Capabilities</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assigned_team as $tm): ?>
                                <tr>
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center">
                                            <img src="<?= htmlspecialchars(get_portal_avatar_url($pdo, 'admin', $tm['user_id'])) ?>" class="rounded-circle border border-warning mr-2" width="36" height="36" style="object-fit:cover;" onerror="this.onerror=null;this.src='/admin_assets/img/profile/blank.png';">
                                            <div>
                                                <strong class="text-white"><?= htmlspecialchars($tm['full_name'] ?: $tm['username']) ?></strong>
                                                <div class="small text-muted"><?= htmlspecialchars($tm['email'] ?? '') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="align-middle">
                                        <span class="badge badge-warning text-dark font-weight-bold"><?= htmlspecialchars($tm['case_role']) ?></span>
                                    </td>
                                    <td class="align-middle">
                                        <div class="d-flex flex-wrap gap-1">
                                            <span class="badge badge-<?= $tm['can_view_financials'] ? 'success' : 'secondary' ?> mr-1 mb-1" title="Financials & Settlements">
                                                <i class="fas fa-<?= $tm['can_view_financials'] ? 'check' : 'times' ?> mr-1"></i> Financials
                                            </span>
                                            <span class="badge badge-<?= $tm['can_edit_timeline'] ? 'success' : 'secondary' ?> mr-1 mb-1" title="Timeline Milestones">
                                                <i class="fas fa-<?= $tm['can_edit_timeline'] ? 'check' : 'times' ?> mr-1"></i> Timeline
                                            </span>
                                            <span class="badge badge-<?= $tm['can_chat_client'] ? 'success' : 'secondary' ?> mr-1 mb-1" title="Direct Client Chat">
                                                <i class="fas fa-<?= $tm['can_chat_client'] ? 'check' : 'times' ?> mr-1"></i> Live Chat
                                            </span>
                                            <span class="badge badge-<?= $tm['can_manage_wallets'] ? 'success' : 'secondary' ?> mr-1 mb-1" title="Blockchain Tracker">
                                                <i class="fas fa-<?= $tm['can_manage_wallets'] ? 'check' : 'times' ?> mr-1"></i> Blockchain
                                            </span>
                                            <span class="badge badge-<?= $tm['can_upload_docs'] ? 'success' : 'secondary' ?> mb-1" title="Vault Documents">
                                                <i class="fas fa-<?= $tm['can_upload_docs'] ? 'check' : 'times' ?> mr-1"></i> Vault Docs
                                            </span>
                                        </div>
                                    </td>
                                    <td class="align-middle text-right text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-warning mr-1" data-toggle="modal" data-target="#editStaffPermsModal_<?= $tm['id'] ?>" title="Edit Permissions">
                                            <i class="fas fa-shield-alt mr-1"></i> Permissions
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Remove this staff member from this case?');">
                                            <input type="hidden" name="action" value="remove_case_agent">
                                            <input type="hidden" name="assignment_id" value="<?= $tm['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove from Case">
                                                <i class="fas fa-user-minus"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Case Details -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0 fw-bold">Case Description</h5>
            </div>
            <div class="card-body">
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($case['description'])); ?></p>
            </div>
        </div>

        <!-- Case Timeline / Milestones -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold"><i class="material-icons text-warning mr-1" style="vertical-align: text-bottom;">timeline</i> Case Timeline & Milestones</h5>
                <button type="button" class="btn btn-sm btn-warning font-weight-bold text-dark" data-toggle="modal" data-target="#addTimelineModal">
                    <i class="material-icons mr-1" style="font-size:16px; vertical-align:text-bottom;">add</i> Add Milestone
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($timeline_events)): ?>
                    <p class="text-muted text-center py-3">No milestones posted for this case yet.</p>
                <?php else: ?>
                    <div class="timeline-wrapper" style="position: relative; padding-left: 20px; border-left: 2px solid #ddd; margin-left: 10px;">
                        <?php foreach($timeline_events as $event): ?>
                            <div class="timeline-event mb-4" style="position: relative;">
                                <div class="timeline-dot" style="position: absolute; left: -27px; top: 5px; width: 12px; height: 12px; border-radius: 50%; background-color: <?php 
                                    echo ['primary' => '#0b2e59', 'success' => '#28a745', 'warning' => '#ffc107', 'danger' => '#dc3545', 'info' => '#17a2b8'][$event['status_color']] ?? '#0b2e59'; 
                                ?>;"></div>
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong class="text-dark d-block" style="font-size: 1.1rem;"><?php echo htmlspecialchars($event['milestone_title']); ?></strong>
                                        <span class="text-muted small">
                                            <i class="material-icons" style="font-size: 14px; vertical-align: text-bottom;">event</i> <?php echo date('M j, Y', strtotime($event['milestone_date'])); ?>
                                            &middot; Visibility: <?php echo $event['is_client_visible'] ? '<span class="text-success font-weight-bold">Client Visible</span>' : '<span class="text-warning font-weight-bold">Internal Only</span>'; ?>
                                        </span>
                                        <?php if (!empty($event['milestone_body'])): ?>
                                            <p class="text-muted mt-2 mb-0 small" style="white-space: pre-wrap;"><?php echo htmlspecialchars($event['milestone_body']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Remove this milestone?');">
                                        <input type="hidden" name="action" value="delete_timeline">
                                        <input type="hidden" name="timeline_id" value="<?php echo $event['id']; ?>">
                                        <button type="submit" class="btn btn-link text-danger p-0"><i class="material-icons" style="font-size: 18px;">delete</i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Internal Notes -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0 fw-bold">Internal Case Notes</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="mb-4">
                    <input type="hidden" name="action" value="add_note">
                    <div class="input-group">
                        <textarea name="note" class="form-control" rows="2" placeholder="Add an internal note..." required></textarea>
                        <div class="input-group-append">
                            <button class="btn btn-warning fw-bold text-dark px-4" type="submit">Post Note</button>
                        </div>
                    </div>
                </form>

                <?php if (empty($notes)): ?>
                    <p class="text-muted text-center py-3">No internal notes for this case.</p>
                <?php else: ?>
                    <?php foreach($notes as $note): ?>
                    <div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong class="text-primary"><i class="material-icons" style="font-size: 16px; vertical-align: text-bottom;">person</i> <?php echo htmlspecialchars($note['username']); ?></strong>
                            <small class="text-muted"><i class="material-icons" style="font-size: 14px; vertical-align: text-bottom;">schedule</i> <?php echo date('M j, Y h:i A', strtotime($note['created_at'])); ?></small>
                        </div>
                        <div class="text-dark bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($note['note'] ?: $note['note_text'] ?? '')); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Quick Actions -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0 fw-bold">Case Actions</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="#" class="list-group-item list-group-item-action" data-toggle="modal" data-target="#vaultDocumentsModal" data-bs-toggle="modal" data-bs-target="#vaultDocumentsModal"><i class="material-icons text-danger mr-2" style="vertical-align: middle;">picture_as_pdf</i> Vault Documents</a>
                <a href="invoices.php?client_id=<?php echo $case['client_id']; ?>" class="list-group-item list-group-item-action"><i class="material-icons text-info mr-2" style="vertical-align: middle;">receipt</i> Case Invoices</a>
                <a href="chat.php?client_id=<?php echo $case['client_id']; ?>" class="list-group-item list-group-item-action"><i class="material-icons text-warning mr-2" style="vertical-align: middle;">chat</i> Message Client</a>
            </div>
        </div>

        <!-- World-Class Forensic & Settlement Tools -->
        <div class="card border-0 shadow-sm mb-4" style="border-top:3px solid #fecc56 !important;">
            <div class="card-header bg-dark text-warning border-bottom border-secondary d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0 fw-bold"><i class="fas fa-satellite-dish mr-2"></i>Forensic & Settlement Desks</h6>
                <span class="badge badge-warning text-dark font-weight-bold">Enterprise</span>
            </div>
            <div class="list-group list-group-flush">
                <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3" data-toggle="modal" data-target="#blockchainWatcherModal">
                    <div>
                        <i class="fas fa-cubes text-warning mr-2"></i>
                        <strong>Blockchain Watcher</strong>
                        <small class="text-muted d-block mt-1">Configure target fraudster wallets & hops</small>
                    </div>
                    <span class="badge badge-dark border border-secondary text-warning"><?= count($case_wallets) ?> Wallets</span>
                </a>
                <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3" data-toggle="modal" data-target="#settlementEscrowModal">
                    <div>
                        <i class="fas fa-vault text-success mr-2"></i>
                        <strong>Escrow & Settlement Hub</strong>
                        <small class="text-muted d-block mt-1">5-stage recovery disbursement pipeline</small>
                    </div>
                    <span class="badge badge-success">Stage <?= (int)($case_settlement['clearance_stage'] ?? 1) ?>/5</span>
                </a>
                <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3" data-toggle="modal" data-target="#jurisdictionsRadarModal">
                    <div>
                        <i class="fas fa-globe-americas text-info mr-2"></i>
                        <strong>Global Recovery Radar</strong>
                        <small class="text-muted d-block mt-1">Cross-border freeze orders & MLAT map pins</small>
                    </div>
                    <span class="badge badge-info"><?= count($case_jurisdictions) ?> Pins</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Add Timeline Modal -->
<div class="modal fade" id="addTimelineModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="material-icons mr-2" style="vertical-align: text-bottom;">timeline</i> Add Case Milestone</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST">
        <div class="modal-body">
            <input type="hidden" name="action" value="add_timeline">
            
            <div class="form-group mb-3">
                <label class="text-white font-weight-bold">Milestone Title <span class="text-warning">*</span></label>
                <input type="text" name="milestone_title" class="form-control bg-dark text-white border-secondary" required placeholder="e.g. Asset Tracing Report Completed">
            </div>
            
            <div class="form-group mb-3">
                <label class="text-white font-weight-bold">Event Date</label>
                <input type="date" name="milestone_date" class="form-control bg-dark text-white border-secondary" value="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="form-group mb-3">
                <label class="text-white font-weight-bold">Status Color</label>
                <select name="status_color" class="form-control bg-dark text-white border-secondary">
                    <option value="primary">Dark Blue (General)</option>
                    <option value="info">Light Blue (In Progress)</option>
                    <option value="warning">Yellow (Pending)</option>
                    <option value="success">Green (Resolved/Successful)</option>
                    <option value="danger">Red (Attention Needed/Failed)</option>
                </select>
            </div>
            
            <div class="form-group mb-3">
                <label class="text-white font-weight-bold">Details / Description</label>
                <textarea name="milestone_body" rows="4" class="form-control bg-dark text-white border-secondary" placeholder="Enter milestone details..."></textarea>
            </div>
            
            <div class="mt-3 d-flex align-items-center" style="gap: 8px;">
                <input type="checkbox" id="clientVisibleSwitch" name="is_client_visible" value="1" checked style="width: 18px; height: 18px; cursor: pointer; accent-color: #fecc56;">
                <label class="text-light font-weight-bold mb-0" for="clientVisibleSwitch" style="cursor: pointer;">Visible to Client in Dashboard</label>
            </div>
        </div>
        <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4">Add Event</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Vault Documents Modal -->
<div class="modal fade" id="vaultDocumentsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="material-icons mr-2" style="vertical-align: text-bottom;">folder_special</i> Case Document Vault</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
          <!-- Tabbed Layout for Document Creation -->
          <ul class="nav nav-tabs border-secondary mb-3" id="docTabs" role="tablist">
              <li class="nav-item">
                  <a class="nav-link active text-warning bg-transparent border-secondary" id="upload-tab" data-toggle="tab" href="#uploadSection" role="tab" aria-selected="true" style="border-bottom: 2px solid #fecc56 !important;"><i class="fas fa-upload mr-1"></i> Upload File</a>
              </li>
              <li class="nav-item ml-2">
                  <a class="nav-link text-warning bg-transparent border-secondary" id="create-tab" data-toggle="tab" href="#createSection" role="tab" aria-selected="false" style="border-bottom: 2px solid #fecc56 !important;"><i class="fas fa-edit mr-1"></i> Create Custom Document</a>
              </li>
          </ul>

          <div class="tab-content" id="docTabsContent">
              <!-- Upload Section -->
              <div class="tab-pane fade show active" id="uploadSection" role="tabpanel">
                  <form method="POST" enctype="multipart/form-data" class="bg-black p-3 rounded mb-4 border border-secondary">
                      <input type="hidden" name="action" value="upload_document">
                      <h6 class="text-warning font-weight-bold mb-3"><i class="fas fa-upload mr-1"></i> Upload Document to Vault</h6>
                      <div class="row">
                          <div class="col-md-5 mb-2">
                              <label class="small text-muted font-weight-bold d-block">Select File</label>
                              <input type="file" name="doc_file" class="form-control-file text-light" required>
                          </div>
                          <div class="col-md-4 mb-2">
                              <label class="small text-muted font-weight-bold">Document Type</label>
                              <select name="document_type_select" id="docTypeSelect" class="form-control form-control-sm bg-dark text-white border-secondary mb-2" onchange="toggleCustomDocType()">
                                  <option value="Standard">Standard / General Document</option>
                                  <option value="Service Agreement">Service Agreement</option>
                                  <option value="Power of Attorney">Power of Attorney</option>
                                  <option value="NDA">NDA (Non-Disclosure Agreement)</option>
                                  <option value="Invoice">Invoice Attachment</option>
                                  <option value="Other">Other (Custom)</option>
                              </select>
                              <input type="text" name="document_type_custom" id="docTypeCustom" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="Specify custom type" style="display:none;">
                          </div>
                          <div class="col-md-3 mb-2 d-flex flex-column justify-content-end pb-1">
                              <div class="form-check mb-2 d-flex align-items-center">
                                  <input type="checkbox" class="form-check-input" id="sigRequired" name="requires_signature" value="1" style="width:16px; height:16px; cursor:pointer;">
                                  <label class="form-check-label text-light small ml-2" for="sigRequired" style="cursor:pointer;">Requires Signature</label>
                              </div>
                              <button type="submit" class="btn btn-warning btn-sm font-weight-bold text-dark w-100">Upload</button>
                          </div>
                      </div>
                  </form>
              </div>
              
              <!-- Create Custom Section -->
              <div class="tab-pane fade" id="createSection" role="tabpanel">
                  <form method="POST" class="bg-black p-3 rounded mb-4 border border-secondary">
                      <input type="hidden" name="action" value="send_custom_document">
                      <h6 class="text-warning font-weight-bold mb-3"><i class="fas fa-file-signature mr-1"></i> Compose Custom Document</h6>
                       <div class="form-group mb-2">
                           <label class="small text-warning font-weight-bold">Select Document Template (Optional)</label>
                           <select class="form-control form-control-sm bg-dark text-white border-secondary" id="docTemplateSelector" onchange="loadDocTemplate(this)">
                               <option value="">-- Choose a standard template --</option>
                               <option value="service_agreement">Service Agreement & Fee Contract [Global]</option>
                               <option value="nda">Mutual Non-Disclosure Agreement (NDA) [Global]</option>
                               <option value="power_of_attorney">Power of Attorney & Letter of Mandate [Global]</option>
                               <option value="cease_and_desist">Cease & Desist Demand Letter [Global]</option>
                               <option value="letter_of_demand">Formal Letter of Demand [UK / Australia / Commonwealth]</option>
                               <option value="writ_of_mandamus">Writ of Mandamus Court Petition [US / Common Law]</option>
                               <option value="authority_to_act">Third-Party Release & Authority to Act [Global]</option>
                               <option value="settlement_release">Settlement & Mutual Release Agreement [Global]</option>
                               <option value="blockchain_forensic">Crypto Ledger Forensic Freeze Request [Global]</option>
                           </select>
                       </div>
                      <div class="form-group mb-2">
                          <label class="small text-muted font-weight-bold">Document Title / Name</label>
                          <input type="text" name="document_name" class="form-control form-control-sm bg-dark text-white border-secondary" required placeholder="e.g. Asset Recovery Agreement - Jane Doe">
                      </div>
                      <div class="row">
                          <div class="col-md-6 form-group mb-2">
                              <label class="small text-muted font-weight-bold">Document Type (Dynamic)</label>
                              <input type="text" name="document_type" class="form-control form-control-sm bg-dark text-white border-secondary" required placeholder="e.g. Service Agreement, Custom NDA, Recovery Mandate">
                          </div>
                          <div class="col-md-6 form-group mb-2 d-flex align-items-center pt-3">
                              <div class="form-check d-flex align-items-center">
                                  <input type="checkbox" class="form-check-input" id="customSigRequired" name="requires_signature" value="1" style="width:16px; height:16px; cursor:pointer;">
                                  <label class="form-check-label text-light small ml-2" for="customSigRequired" style="cursor:pointer;">Requires Client Signature</label>
                              </div>
                          </div>
                      </div>
                      <div class="form-group mb-3">
                          <label class="small text-muted font-weight-bold">Document Content (HTML / Text Allowed)</label>
                          <textarea name="document_body" id="customDocBody" class="form-control bg-dark text-white border-secondary" rows="10" required placeholder="Write your document content here. You can use standard HTML formatting tags like &lt;b&gt;, &lt;i&gt;, &lt;ul&gt;, &lt;p&gt;, etc."></textarea>
                      </div>
                      <div class="d-flex justify-content-between">
                          <button type="button" class="btn btn-sm btn-outline-warning font-weight-bold px-3" onclick="previewCustomDoc()"><i class="fas fa-eye mr-1"></i> Live Preview Document</button>
                          <button type="submit" class="btn btn-warning btn-sm font-weight-bold text-dark px-4">Create & Send to Client</button>
                      </div>
                  </form>
              </div>
          </div>

          <!-- Documents List -->
          <h6 class="text-light font-weight-bold mb-3"><i class="fas fa-file-pdf mr-1"></i> Vaulted Files & Agreements</h6>
          <div class="table-responsive">
              <table class="table table-dark table-hover table-striped mb-0" style="background:#111;">
                  <thead>
                      <tr>
                          <th>File Name</th>
                          <th>Type</th>
                          <th>Status</th>
                          <th>Uploaded</th>
                          <th>Actions</th>
                      </tr>
                  </thead>
                  <tbody>
                      <?php if (empty($documents)): ?>
                          <tr>
                              <td colspan="5" class="text-center text-muted py-4">No documents vaulted for this client.</td>
                          </tr>
                      <?php else: ?>
                          <?php foreach($documents as $doc): ?>
                              <tr>
                                  <td>
                                      <?php if (!empty($doc['document_body'])): ?>
                                          <a href="view_document.php?id=<?= $doc['id'] ?>" target="_blank" class="text-warning font-weight-bold">
                                              <i class="fas fa-file-alt mr-1"></i> <?= htmlspecialchars($doc['file_name']) ?>
                                          </a>
                                      <?php else: ?>
                                          <a href="<?= BASE_URL . '/' . htmlspecialchars($doc['file_path']) ?>" target="_blank" class="text-warning font-weight-bold">
                                              <i class="fas fa-file-download mr-1"></i> <?= htmlspecialchars($doc['file_name']) ?>
                                          </a>
                                      <?php endif; ?>
                                  </td>
                                  <td><span class="badge badge-secondary"><?= htmlspecialchars($doc['document_type']) ?></span></td>
                                  <td>
                                      <?php if ($doc['requires_signature']): ?>
                                          <?php if ($doc['is_signed']): ?>
                                              <span class="badge badge-success" title="Signed at: <?= $doc['signed_at'] ?> IP: <?= $doc['signature_ip'] ?>"><i class="fas fa-check-circle mr-1"></i> Signed</span>
                                          <?php else: ?>
                                              <span class="badge badge-warning text-dark"><i class="fas fa-signature mr-1"></i> Pending Signature</span>
                                          <?php endif; ?>
                                      <?php else: ?>
                                          <span class="badge badge-info">Standard View</span>
                                      <?php endif; ?>
                                  </td>
                                  <td class="small text-muted"><?= date('M j, Y H:i', strtotime($doc['uploaded_at'])) ?></td>
                                  <td>
                                      <form method="POST" class="d-inline" onsubmit="return confirm('Delete this vaulted document?');">
                                          <input type="hidden" name="action" value="delete_document">
                                          <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                          <button type="submit" class="btn btn-sm btn-link text-danger p-0"><i class="fas fa-trash-alt mr-1"></i>Delete</button>
                                      </form>
                                  </td>
                              </tr>
                          <?php endforeach; ?>
                      <?php endif; ?>
                  </tbody>
              </table>
          </div>
      </div>
      <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Custom Doc Preview Modal -->
<div class="modal fade" id="customDocPreviewModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-eye mr-2"></i>Document Preview</h5>
        <button type="button" class="close text-white" onclick="$('#customDocPreviewModal').modal('hide')">&times;</button>
      </div>
      <div class="modal-body bg-light text-dark p-5" style="font-family: 'Times New Roman', Times, serif; min-height: 400px; max-height: 500px; overflow-y: auto;">
          <div id="previewTitle" class="text-center font-weight-bold mb-4" style="font-size: 1.5rem; border-bottom: 2px solid #333; padding-bottom: 10px; font-family: 'Montserrat', sans-serif;"></div>
          <div id="previewContent" style="font-size: 1.1rem; line-height: 1.6; white-space: pre-wrap;"></div>
      </div>
      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-secondary font-weight-bold" onclick="$('#customDocPreviewModal').modal('hide')">Close Preview</button>
      </div>
    </div>
  </div>
</div>

<script>
function loadDocTemplate(select) {
    var val = select.value;
    var clientName = "<?= htmlspecialchars($case['first_name'] . ' ' . $case['last_name']) ?>";
    
    var docHeader = `<div style="text-align: justify; font-family: 'Times New Roman', Times, serif; line-height: 1.8;">`;
    var docFooter = `<br><br><p style="text-align: center; font-size: 0.9em; border-top: 1px solid #ccc; padding-top: 10px;"><i>This document is confidential and privileged. Executed on the IFW Global Secure Platform.</i></p></div>`;

    if (val === 'service_agreement') {
        document.getElementsByName('document_name')[0].value = 'Service Agreement - ' + clientName;
        document.getElementsByName('document_type')[0].value = 'Service Agreement';
        document.getElementById('customSigRequired').checked = true;
        document.getElementById('customDocBody').value = docHeader + `<h3 style="text-align:center; font-weight:bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom:20px;">LETTER OF ENGAGEMENT & SERVICE AGREEMENT</h3>
<p style="text-align:right;"><b>Date:</b> ` + new Date().toLocaleDateString() + `</p>
<p><b>Jurisdiction:</b> Global / Universal Applicability</p>
<p>This Service Agreement ("Agreement") is made between <b>IFW Global</b> ("Agency") and <b>` + clientName + `</b> ("Client").</p>
<hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
<h4 style="margin-bottom: 10px;">1. SCOPE OF INVESTIGATION & RECOVERY SERVICES</h4>
<p>Agency agrees to perform comprehensive asset tracing, forensic blockchain tracking, and intelligence recovery services concerning the Client's reported financial loss. The Agency will utilize proprietary investigative methodologies to locate, secure, and recover misappropriated funds.</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">2. RETAINER & FEES</h4>
<p>Client shall pay Agency the agreed professional services retainer prior to commencement. Upon successful restitution, a recovery success fee shall be calculated at the rate of 10% of the total recovered value. No hidden fees shall be applied.</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">3. CONFIDENTIALITY</h4>
<p>Both parties agree to hold all information related to this investigation in the absolute strictest confidence. Disclosure to third parties is strictly prohibited without prior written consent.</p>
<p style="margin-top: 30px;">IN WITNESS WHEREOF, the parties hereto have executed this Agreement securely via the IFW Global cryptographic signing portal.</p>` + docFooter;
    } else if (val === 'nda') {
        document.getElementsByName('document_name')[0].value = 'Mutual NDA - ' + clientName;
        document.getElementsByName('document_type')[0].value = 'Non-Disclosure Agreement';
        document.getElementById('customSigRequired').checked = true;
        document.getElementById('customDocBody').value = docHeader + `<h3 style="text-align:center; font-weight:bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom:20px;">MUTUAL NON-DISCLOSURE AGREEMENT (NDA)</h3>
<p style="text-align:right;"><b>Date:</b> ` + new Date().toLocaleDateString() + `</p>
<p><b>Jurisdiction:</b> Global / Universal Applicability</p>
<p>This Mutual Non-Disclosure Agreement is entered into by and between <b>IFW Global</b> and <b>` + clientName + `</b> ("The Parties").</p>
<hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
<p>The Parties wish to explore a potential investigation and asset recovery case, in connection with which they may disclose confidential proprietary information.</p>
<h4 style="margin-bottom: 10px;">1. CONFIDENTIAL INFORMATION</h4>
<p>"Confidential Information" includes all written, oral, electronic, or visual information disclosed between the parties, including but not limited to forensic reports, identity dossiers, financial logs, and investigative tactics.</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">2. RESTRICTION ON USE</h4>
<p>Neither party shall use or disclose any Confidential Information of the other party for any purpose outside the strict scope of this investigation. The receiving party shall employ the highest degree of care to protect such information.</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">3. SURVIVAL</h4>
<p>The obligations under this Agreement shall survive the termination of the investigation case indefinitely.</p>` + docFooter;
    } else if (val === 'power_of_attorney') {
        document.getElementsByName('document_name')[0].value = 'Power of Attorney & Letter of Mandate - ' + clientName;
        document.getElementsByName('document_type')[0].value = 'Power of Attorney';
        document.getElementById('customSigRequired').checked = true;
        document.getElementById('customDocBody').value = docHeader + `<h3 style="text-align:center; font-weight:bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom:20px;">LETTER OF MANDATE & LIMITED POWER OF ATTORNEY</h3>
<p style="text-align:right;"><b>Date:</b> ` + new Date().toLocaleDateString() + `</p>
<p><b>Jurisdiction:</b> Global / Universal Applicability</p>
<p>I, <b>` + clientName + `</b>, hereby appoint <b>IFW Global</b> and its authorized investigative agents as my lawful attorney-in-fact and authorized representative.</p>
<hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
<h4 style="margin-bottom: 10px;">1. GRANT OF AUTHORITY</h4>
<p>IFW Global shall have full power and authority to act on my behalf to trace, freeze, negotiate, and recover funds lost to illicit financial operations. This includes the explicit authority to request confidential records from financial institutions, cryptocurrency exchanges, and law enforcement agencies globally.</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">2. LEGAL REPRESENTATION</h4>
<p>My attorney-in-fact may sign, seal, execute, deliver, and acknowledge any and all documents necessary to facilitate the recovery of my assets, acting as fully as I could do if personally present.</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">3. DURATION & REVOCATION</h4>
<p>This Limited Power of Attorney is effective immediately upon cryptographic execution and shall remain in full force and effect until the conclusion of the recovery mandate, unless revoked by me in writing.</p>` + docFooter;
    } else if (val === 'cease_and_desist') {
        document.getElementsByName('document_name')[0].value = 'Cease & Desist Demand - ' + clientName;
        document.getElementsByName('document_type')[0].value = 'Cease & Desist';
        document.getElementById('customSigRequired').checked = false;
        document.getElementById('customDocBody').value = docHeader + `<h3 style="text-align:center; font-weight:bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom:20px;">FORMAL CEASE AND DESIST DEMAND</h3>
<p style="text-align:right;"><b>Date:</b> ` + new Date().toLocaleDateString() + `</p>
<p><b>Jurisdiction:</b> Global / Universal Applicability</p>
<p><b>VIA SECURE ELECTRONIC DELIVERY</b></p>
<p><b>To Whom It May Concern,</b></p>
<p>We act as retained investigators and authorized representatives on behalf of <b>` + clientName + `</b> in relation to funds fraudulently obtained from our client. Forensic tracing confirms that stolen assets have transited through or are currently held within your platform's infrastructure.</p>
<hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
<p><b>DEMAND IS HEREBY MADE</b> that you immediately:</p>
<ul style="margin-bottom: 20px;">
    <li>Cease and desist all unauthorized operations concerning our client's accounts.</li>
    <li>Preserve all server logs, KYC data, IP addresses, and internal communication records.</li>
    <li>Place an administrative hold on all disputed assets pending the arrival of a formal legal freeze order or subpoena.</li>
</ul>
<p>Failure to comply immediately will result in IFW Global escalating this matter. We will initiate formal criminal and civil complaints with international regulatory bodies and law enforcement agencies, naming your organization as an uncooperative accessory to financial fraud.</p>
<p>Govern yourselves accordingly.</p>
<p style="margin-top: 30px;"><b>IFW Global Legal & Compliance Department</b></p>` + docFooter;
    } else if (val === 'letter_of_demand') {
        document.getElementsByName('document_name')[0].value = 'Formal Letter of Demand - ' + clientName;
        document.getElementsByName('document_type')[0].value = 'Letter of Demand';
        document.getElementById('customSigRequired').checked = false;
        document.getElementById('customDocBody').value = docHeader + `<h3 style="text-align:center; font-weight:bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom:20px;">FORMAL LETTER OF DEMAND (RESTITUTION)</h3>
<p style="text-align:right;"><b>Date:</b> ` + new Date().toLocaleDateString() + `</p>
<p><b>Jurisdiction:</b> UK / Australia / Commonwealth Common Law</p>
<p><b>Dear Sir/Madam,</b></p>
<p>This is a formal Letter of Demand issued pursuant to standard pre-action protocols. We are the authorized representatives of <b>` + clientName + `</b>.</p>
<hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
<p>Our comprehensive forensic investigations confirm that you are currently holding or have received assets belonging to our client. These funds were transferred under fraudulent representations, constituting unjust enrichment and fraud.</p>
<h4 style="margin-bottom: 10px;">DEMAND FOR RESTITUTION</h4>
<p>We hereby demand the immediate repayment and full restitution of the aforementioned sum to our client's designated recovery account within <b>fourteen (14) days</b> from the date of this letter.</p>
<p>Failing restitution within the specified timeframe, we have standing instructions to commence civil and criminal proceedings without further notice, which will result in substantial legal costs being claimed against you.</p>
<p>We await your immediate compliance.</p>` + docFooter;
    } else if (val === 'writ_of_mandamus') {
        document.getElementsByName('document_name')[0].value = 'Petition for Writ of Mandamus - ' + clientName;
        document.getElementsByName('document_type')[0].value = 'Writ of Mandamus';
        document.getElementById('customSigRequired').checked = true;
        document.getElementById('customDocBody').value = docHeader + `<h3 style="text-align:center; font-weight:bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom:20px;">PETITION FOR WRIT OF MANDAMUS</h3>
<p style="text-align:right;"><b>Date:</b> ` + new Date().toLocaleDateString() + `</p>
<p><b>Jurisdiction:</b> United States Judicial System / US Common Law</p>
<p><b>In the Matter of the Case of:</b> ` + clientName + ` (Petitioner)</p>
<p><b>TO THE HONORABLE COURT / REGULATORY BODY:</b></p>
<hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
<p>Petitioner <b>` + clientName + `</b>, by and through their authorized investigative counsel IFW Global, hereby petitions for a Writ of Mandamus directing the Respondent to immediately execute their non-discretionary duty regarding the release of frozen illicit assets back to the rightful owner.</p>
<h4 style="margin-bottom: 10px;">1. BASIS FOR PETITION</h4>
<p>Petitioner has a clear, established legal right to the performance of this duty. Extensive forensic evidence provided by IFW Global confirms the Petitioner's undisputed ownership of the targeted assets. Respondent has a clear legal obligation to perform the release, and the duty is ministerial in nature.</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">2. ABSENCE OF ALTERNATIVE REMEDY</h4>
<p>Petitioner has exhausted all administrative remedies and has no other adequate legal remedy available to compel the return of the misappropriated funds.</p>
<p style="margin-top: 30px;"><b>WHEREFORE</b>, Petitioner respectfully requests that a Writ of Mandamus be issued compelling the immediate release and transfer of the recovered assets.</p>` + docFooter;
    } else if (val === 'authority_to_act') {
        document.getElementsByName('document_name')[0].value = 'Authority to Act & Info Release - ' + clientName;
        document.getElementsByName('document_type')[0].value = 'Authority to Act';
        document.getElementById('customSigRequired').checked = true;
        document.getElementById('customDocBody').value = docHeader + `<h3 style="text-align:center; font-weight:bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom:20px;">AUTHORITY TO ACT & RELEASE OF INFORMATION</h3>
<p style="text-align:right;"><b>Date:</b> ` + new Date().toLocaleDateString() + `</p>
<p><b>Jurisdiction:</b> Global / Universal Applicability</p>
<p>I, <b>` + clientName + `</b>, hereby authorize <b>IFW Global</b> and its designated agents to act as my exclusive representatives and investigators in the matter of my financial loss recovery.</p>
<hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
<h4 style="margin-bottom: 10px;">DIRECTIVE TO THIRD PARTIES</h4>
<p>I hereby instruct, mandate, and authorize all banks, cryptocurrency exchanges, internet service providers, financial institutions, and law enforcement agencies to release any and all records related to my accounts and transactions to IFW Global immediately upon presentation of this document.</p>
<p>This includes, but is not limited to:</p>
<ul style="margin-bottom: 20px;">
    <li>Transaction logs, IP addresses, and routing data.</li>
    <li>KYC/AML documentation and identity records of opposing accounts.</li>
    <li>Internal investigations or freeze status reports.</li>
</ul>
<p>A copy or digital reproduction of this executed document shall have the same legally binding effect as the original.</p>` + docFooter;
    } else if (val === 'settlement_release') {
        document.getElementsByName('document_name')[0].value = 'Settlement & Mutual Release - ' + clientName;
        document.getElementsByName('document_type')[0].value = 'Settlement Agreement';
        document.getElementById('customSigRequired').checked = true;
        document.getElementById('customDocBody').value = docHeader + `<h3 style="text-align:center; font-weight:bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom:20px;">SETTLEMENT AND MUTUAL RELEASE AGREEMENT</h3>
<p style="text-align:right;"><b>Date:</b> ` + new Date().toLocaleDateString() + `</p>
<p><b>Jurisdiction:</b> Global / Universal Applicability</p>
<p>This Settlement and Mutual Release Agreement ("Agreement") is made between <b>` + clientName + `</b> ("Client") and the responding party/entity ("Respondent").</p>
<hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
<h4 style="margin-bottom: 10px;">1. SETTLEMENT PAYMENT</h4>
<p>Respondent agrees to pay Client the agreed sum in full and final settlement of all claims, controversies, and disputes arising out of the investigated financial loss.</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">2. MUTUAL RELEASE</h4>
<p>Upon confirmed receipt of the cleared settlement payment in the Client's designated account, both parties hereby fully and forever release, acquit, and discharge each other from any and all claims, liabilities, demands, damages, or actions of any kind.</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">3. NON-ADMISSION OF LIABILITY</h4>
<p>This Agreement is a compromise of a disputed claim and shall not be construed as an admission of liability by either party, which is expressly denied.</p>` + docFooter;
    } else if (val === 'blockchain_forensic') {
        document.getElementsByName('document_name')[0].value = 'Crypto Forensic Freeze Request - ' + clientName;
        document.getElementsByName('document_type')[0].value = 'Forensic Block Request';
        document.getElementById('customSigRequired').checked = false;
        document.getElementById('customDocBody').value = docHeader + `<h3 style="text-align:center; font-weight:bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom:20px;">CRYPTOGRAPHIC FORENSIC FREEZE REQUEST</h3>
<p style="text-align:right;"><b>Date:</b> ` + new Date().toLocaleDateString() + `</p>
<p><b>Jurisdiction:</b> Global / Universal Crypto Asset Regulations</p>
<p><b>To: Legal, Compliance & Security Department, [Exchange/Custodian Name]</b></p>
<p>We formally represent <b>` + clientName + `</b>, who was the victim of a coordinated cryptocurrency fraud operation. Advanced forensic ledger tracking confirms that the stolen assets were transferred directly into your exchange's custody.</p>
<hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
<h4 style="margin-bottom: 10px;">INCIDENT DETAILS</h4>
<p><b>Target Wallet Address:</b> [Insert Address]<br>
<b>Transaction Hash (TXID):</b> [Insert TXID]<br>
<b>Network:</b> [Insert Blockchain Network]</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">URGENT ADMINISTRATIVE FREEZE REQUEST</h4>
<p>Pursuant to international AML (Anti-Money Laundering) directives and your institution's Terms of Service regarding illicit activities, we request that you immediately place an <b>administrative temporary hold/freeze</b> on the target account.</p>
<p>This freeze is necessary to prevent the dissipation or laundering of stolen assets while we obtain a formal court freeze order or law enforcement subpoena. Failure to act may render your exchange liable for facilitating the laundering of proceeds of crime.</p>
<p style="margin-top: 30px;"><b>IFW Global Blockchain Forensics Team</b></p>` + docFooter;
    }
}

function toggleCustomDocType() {
    var select = document.getElementById('docTypeSelect');
    var customInput = document.getElementById('docTypeCustom');
    if (select.value === 'Other') {
        customInput.style.display = 'block';
        customInput.required = true;
    } else {
        customInput.style.display = 'none';
        customInput.required = false;
        customInput.value = '';
    }
}

function previewCustomDoc() {
    var title = document.getElementsByName('document_name')[0].value || 'Untitled Document';
    var body = document.getElementById('customDocBody').value || '<i>No content provided.</i>';
    
    document.getElementById('previewTitle').innerHTML = title;
    document.getElementById('previewContent').innerHTML = body;
    
    $('#customDocPreviewModal').modal('show');
}
</script>

<!-- ==========================================
     MODAL 1: BLOCKCHAIN FORENSIC WATCHER MANAGER
     ========================================== -->
<div class="modal fade" id="blockchainWatcherModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-cubes mr-2"></i>Blockchain Forensic Watcher Management</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body p-4">
        <!-- Feature Visibility Toggle -->
        <div class="p-3 rounded mb-4 border border-secondary d-flex justify-content-between align-items-center flex-wrap gap-2" style="background:#1f2533;">
            <div>
                <h6 class="font-weight-bold text-white mb-0"><i class="fas fa-eye mr-2 text-warning"></i>Client Portal Feature Status</h6>
                <small class="text-muted">When enabled, the Blockchain Watcher item appears in the client's side nav.</small>
            </div>
            <form method="POST" class="d-flex align-items-center gap-2">
                <input type="hidden" name="action" value="toggle_feature_watcher">
                <select name="show_blockchain_watcher" class="form-control form-control-sm bg-black text-white border-secondary font-weight-bold mr-2" style="min-width:180px;">
                    <option value="0" <?= ($case['show_blockchain_watcher'] ?? 0) == 0 ? 'selected' : '' ?>>🔴 Disabled (Hidden from Nav)</option>
                    <option value="1" <?= ($case['show_blockchain_watcher'] ?? 0) == 1 ? 'selected' : '' ?>>🟢 Enabled (Visible in Nav)</option>
                </select>
                <button type="submit" class="btn btn-warning btn-sm font-weight-bold text-dark">Save Status</button>
            </form>
        </div>

        <!-- Add Wallet Form -->
        <div class="p-3 rounded mb-4 border border-secondary" style="background:#161a23;">
            <h6 class="font-weight-bold text-warning mb-3"><i class="fas fa-plus-circle mr-2"></i>Add Tracked Fraudster / Destination Wallet</h6>
            <form method="POST">
                <input type="hidden" name="action" value="add_blockchain_wallet">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <label class="small text-muted font-weight-bold">Crypto Asset / Network</label>
                        <select name="crypto_type" class="form-control form-control-sm bg-dark text-white border-secondary">
                            <option value="USDT (TRC-20)">USDT (TRC-20)</option>
                            <option value="USDT (ERC-20)">USDT (ERC-20)</option>
                            <option value="Bitcoin (BTC)">Bitcoin (BTC)</option>
                            <option value="Ethereum (ETH)">Ethereum (ETH)</option>
                            <option value="Solana (SOL)">Solana (SOL)</option>
                            <option value="BNB Chain (BEP-20)">BNB Chain (BEP-20)</option>
                        </select>
                    </div>
                    <div class="col-md-5 mb-2">
                        <label class="small text-muted font-weight-bold">Wallet Address <span class="text-danger">*</span></label>
                        <input type="text" name="wallet_address" class="form-control form-control-sm bg-dark text-white border-secondary font-monospace" required placeholder="e.g. TXy7n3K19oP4mQ9wLv8B2xZ5cR6vN1aM4t">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="small text-muted font-weight-bold">Label / Identification</label>
                        <input type="text" name="wallet_label" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="e.g. Laundering Aggregator Node #1">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="small text-muted font-weight-bold">Monitored Balance</label>
                        <input type="number" step="0.0001" name="balance" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="0.00">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="small text-muted font-weight-bold">USD Value Equiv ($)</label>
                        <input type="number" step="0.01" name="usd_value" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="0.00">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="small text-muted font-weight-bold">Threat Score (0-100)</label>
                        <input type="number" name="risk_score" class="form-control form-control-sm bg-dark text-white border-secondary" value="95" min="0" max="100">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="small text-muted font-weight-bold">Preservation Status</label>
                        <select name="status" class="form-control form-control-sm bg-dark text-white border-secondary">
                            <option value="Active Monitoring">Active Monitoring</option>
                            <option value="Subpoena Served">Subpoena Served</option>
                            <option value="Preservation In Force">Preservation In Force</option>
                            <option value="Exchange Frozen">Exchange Frozen</option>
                        </select>
                    </div>
                    <div class="col-md-8 mb-2">
                        <label class="small text-muted font-weight-bold">Clustering / Exchange Tags</label>
                        <input type="text" name="exchange_tags" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="e.g. Binance Deposit Flagged &bull; Tornado Cash Hop">
                    </div>
                    <div class="col-md-4 mb-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-warning btn-sm btn-block font-weight-bold text-dark">
                            <i class="fas fa-plus mr-1"></i> Add Monitored Wallet
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Existing Wallets Table -->
        <h6 class="font-weight-bold text-light mb-2">Current Monitored Target Wallets (<?= count($case_wallets) ?>)</h6>
        <div class="table-responsive mb-4">
            <table class="table table-dark table-bordered mb-0" style="font-size:12.5px;">
                <thead style="background:#1f2533; color:#fecc56;">
                    <tr>
                        <th>Asset</th>
                        <th>Address</th>
                        <th>Label</th>
                        <th class="text-right">Balance / USD</th>
                        <th>Threat Score</th>
                        <th>Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($case_wallets)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">No wallets added yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($case_wallets as $cw): ?>
                        <tr>
                            <td><span class="badge badge-warning text-dark"><?= htmlspecialchars($cw['crypto_type']) ?></span></td>
                            <td class="font-monospace text-warning"><?= htmlspecialchars($cw['wallet_address']) ?></td>
                            <td><?= htmlspecialchars($cw['wallet_label'] ?: '—') ?></td>
                            <td class="text-right font-weight-bold">$<?= number_format((float)($cw['usd_value'] ?: $cw['balance']), 2) ?></td>
                            <td><span class="badge badge-danger"><?= (int)$cw['risk_score'] ?>/100</span></td>
                            <td><span class="badge badge-success"><?= htmlspecialchars($cw['status']) ?></span></td>
                            <td class="text-center">
                                <form method="POST" onsubmit="return confirm('Remove this monitored wallet?');">
                                    <input type="hidden" name="action" value="delete_blockchain_wallet">
                                    <input type="hidden" name="wallet_id" value="<?= $cw['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Add Transaction Hop Form -->
        <div class="p-3 rounded mb-3 border border-secondary" style="background:#161a23;">
            <h6 class="font-weight-bold text-warning mb-3"><i class="fas fa-route mr-2"></i>Log On-Chain Transaction Hop</h6>
            <form method="POST">
                <input type="hidden" name="action" value="add_blockchain_tx">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label class="small text-muted font-weight-bold">TXID Hash <span class="text-danger">*</span></label>
                        <input type="text" name="tx_hash" class="form-control form-control-sm bg-dark text-white border-secondary font-monospace" required placeholder="64-char transaction hash">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="small text-muted font-weight-bold">Direction</label>
                        <select name="direction" class="form-control form-control-sm bg-dark text-white border-secondary">
                            <option value="OUT">OUTFLOW (To Exchange/Mule)</option>
                            <option value="IN">INFLOW (From Victim/Source)</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="small text-muted font-weight-bold">Amount</label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-sm bg-dark text-white border-secondary" required placeholder="0.00">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="small text-muted font-weight-bold">Asset</label>
                        <input type="text" name="crypto_type" class="form-control form-control-sm bg-dark text-white border-secondary" value="USDT">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="small text-muted font-weight-bold">Flag Tag</label>
                        <input type="text" name="flag_tag" class="form-control form-control-sm bg-dark text-white border-secondary" value="Exchange Deposit Hop">
                    </div>
                    <div class="col-md-5 mb-2">
                        <label class="small text-muted font-weight-bold">From Node</label>
                        <input type="text" name="from_address" class="form-control form-control-sm bg-dark text-white border-secondary font-monospace" placeholder="Origin address">
                    </div>
                    <div class="col-md-5 mb-2">
                        <label class="small text-muted font-weight-bold">To Node</label>
                        <input type="text" name="to_address" class="form-control form-control-sm bg-dark text-white border-secondary font-monospace" placeholder="Destination address">
                    </div>
                    <div class="col-md-2 mb-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-warning btn-sm btn-block font-weight-bold text-dark">
                            <i class="fas fa-plus mr-1"></i> Log Hop
                        </button>
                    </div>
                </div>
            </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ==========================================
     MODAL 2: ESCROW & RECOVERY SETTLEMENT MANAGER
     ========================================== -->
<div class="modal fade" id="settlementEscrowModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-vault mr-2"></i>Escrow & Recovery Settlement Hub Manager</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST">
        <div class="modal-body p-4">
            <input type="hidden" name="action" value="update_case_settlement">
            
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="enableSettlement" name="is_enabled" value="1" <?= (!empty($case_settlement['is_enabled']) || !isset($case_settlement['is_enabled'])) ? 'checked' : '' ?> style="width:18px; height:18px; cursor:pointer;">
                <label class="form-check-label text-warning font-weight-bold ml-2" for="enableSettlement" style="cursor:pointer;">
                    Activate Escrow & Settlement Hub for Client
                </label>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="font-weight-bold text-light small">Gross Recovered Amount ($) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="gross_recovered" class="form-control bg-black text-white border-secondary font-weight-bold text-warning" value="<?= htmlspecialchars($case_settlement['gross_recovered'] ?? $case['amount_recovered'] ?? '0.00') ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="font-weight-bold text-light small">Agreed Recovery Fee (%) <span class="text-danger">*</span></label>
                    <input type="number" step="0.1" name="fee_percent" class="form-control bg-black text-white border-secondary font-weight-bold" value="<?= htmlspecialchars($case_settlement['fee_percent'] ?? '10.0') ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="font-weight-bold text-light small">Clearance Pipeline Stage <span class="text-danger">*</span></label>
                    <select name="clearance_stage" class="form-control bg-black text-white border-secondary font-weight-bold text-warning">
                        <option value="1" <?= ((int)($case_settlement['clearance_stage'] ?? 1) === 1) ? 'selected' : '' ?>>1. Custodial Securitization & Audit</option>
                        <option value="2" <?= ((int)($case_settlement['clearance_stage'] ?? 1) === 2) ? 'selected' : '' ?>>2. AML & Sanctions Clearance</option>
                        <option value="3" <?= ((int)($case_settlement['clearance_stage'] ?? 1) === 3) ? 'selected' : '' ?>>3. Judicial Release Authorization</option>
                        <option value="4" <?= ((int)($case_settlement['clearance_stage'] ?? 1) === 4) ? 'selected' : '' ?>>4. Disbursement Execution</option>
                        <option value="5" <?= ((int)($case_settlement['clearance_stage'] ?? 1) === 5) ? 'selected' : '' ?>>5. Settlement Complete</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold text-light small">Escrow Reference ID</label>
                    <input type="text" name="escrow_ref" class="form-control bg-black text-white border-secondary font-monospace" value="<?= htmlspecialchars($case_settlement['escrow_ref'] ?? ('IFW-ESCROW-' . date('Y') . '-' . $case_id)) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold text-light small">Custodial Holding Entity</label>
                    <input type="text" name="custody_entity" class="form-control bg-black text-white border-secondary" value="<?= htmlspecialchars($case_settlement['custody_entity'] ?? 'Swiss Multi-Sig Escrow Vault (FINMA Compliant)') ?>">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="font-weight-bold text-light small">Internal Settlement Notes</label>
                    <textarea name="notes" class="form-control bg-black text-white border-secondary" rows="2" placeholder="Compliance notes or disbursement tracking comments..."><?= htmlspecialchars($case_settlement['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <?php if (!empty($case_settlement['payout_destination_details'])): ?>
            <div class="p-3 rounded border border-success mb-3" style="background:#0b1912;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i> Client Verified Payout Destination</span>
                    <small class="text-muted">Signed: <?= date('M j, Y, g:i a', strtotime($case_settlement['client_confirmed_at'])) ?></small>
                </div>
                <div class="text-warning font-weight-bold small mb-1"><?= htmlspecialchars($case_settlement['payout_method']) ?></div>
                <pre class="text-white font-monospace mb-0 p-2 rounded bg-black border border-secondary" style="font-size:12px; white-space:pre-wrap;"><?= htmlspecialchars($case_settlement['payout_destination_details']) ?></pre>
            </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4">Save Settlement Settings</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ==========================================
     MODAL 3: GLOBAL RECOVERY RADAR MANAGER
     ========================================== -->
<div class="modal fade" id="jurisdictionsRadarModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-globe-americas mr-2"></i>Global Recovery Radar & Jurisdiction Manager</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body p-4">
        <!-- Feature Visibility Toggle -->
        <div class="p-3 rounded mb-4 border border-secondary d-flex justify-content-between align-items-center flex-wrap gap-2" style="background:#1f2533;">
            <div>
                <h6 class="font-weight-bold text-white mb-0"><i class="fas fa-eye mr-2 text-warning"></i>Client Portal Feature Status</h6>
                <small class="text-muted">When enabled, the Recovery Radar item appears in the client's side nav.</small>
            </div>
            <form method="POST" class="d-flex align-items-center gap-2">
                <input type="hidden" name="action" value="toggle_feature_radar">
                <select name="show_recovery_map" class="form-control form-control-sm bg-black text-white border-secondary font-weight-bold mr-2" style="min-width:180px;">
                    <option value="0" <?= ($case['show_recovery_map'] ?? 0) == 0 ? 'selected' : '' ?>>🔴 Disabled (Hidden from Nav)</option>
                    <option value="1" <?= ($case['show_recovery_map'] ?? 0) == 1 ? 'selected' : '' ?>>🟢 Enabled (Visible in Nav)</option>
                </select>
                <button type="submit" class="btn btn-warning btn-sm font-weight-bold text-dark">Save Status</button>
            </form>
        </div>

        <!-- Add Pin Form -->
        <div class="p-3 rounded mb-4 border border-secondary" style="background:#161a23;">
            <h6 class="font-weight-bold text-warning mb-3"><i class="fas fa-map-marker-alt mr-2"></i>Add Cross-Border Jurisdiction Action Pin</h6>
            <form method="POST">
                <input type="hidden" name="action" value="add_case_jurisdiction">
                <div class="row">
                    <div class="col-md-2 mb-2">
                        <label class="small text-muted font-weight-bold">Country Code</label>
                        <select name="country_code" class="form-control form-control-sm bg-dark text-white border-secondary">
                            <option value="US">US (United States)</option>
                            <option value="GB">GB (United Kingdom)</option>
                            <option value="CH">CH (Switzerland)</option>
                            <option value="SG">SG (Singapore)</option>
                            <option value="AE">AE (United Arab Emirates)</option>
                            <option value="CY">CY (Cyprus)</option>
                            <option value="SC">SC (Seychelles)</option>
                            <option value="KY">KY (Cayman Islands)</option>
                            <option value="VG">VG (British Virgin Islands)</option>
                            <option value="HK">HK (Hong Kong)</option>
                            <option value="AU">AU (Australia)</option>
                            <option value="CA">CA (Canada)</option>
                            <option value="DE">DE (Germany)</option>
                            <option value="MT">MT (Malta)</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="small text-muted font-weight-bold">Country Name <span class="text-danger">*</span></label>
                        <input type="text" name="country_name" class="form-control form-control-sm bg-dark text-white border-secondary" required placeholder="e.g. Switzerland">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="small text-muted font-weight-bold">Court / Enforcement Agency <span class="text-danger">*</span></label>
                        <input type="text" name="city_court" class="form-control form-control-sm bg-dark text-white border-secondary" required placeholder="e.g. High Court of Justice, London">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="small text-muted font-weight-bold">Action Type <span class="text-danger">*</span></label>
                        <input type="text" name="action_type" class="form-control form-control-sm bg-dark text-white border-secondary" required placeholder="e.g. Worldwide Freezing Order">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="small text-muted font-weight-bold">Docket / File Ref</label>
                        <input type="text" name="case_ref" class="form-control form-control-sm bg-dark text-white border-secondary font-monospace" placeholder="e.g. EWHC-2026-CV-104">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="small text-muted font-weight-bold">Preservation Status</label>
                        <select name="status" class="form-control form-control-sm bg-dark text-white border-secondary">
                            <option value="Active Freeze Order">Active Freeze Order</option>
                            <option value="Subpoena Served">Subpoena Served</option>
                            <option value="MLAT Notice Pending">MLAT Notice Pending</option>
                            <option value="Hearing Scheduled">Hearing Scheduled</option>
                            <option value="Asset Seizure Executed">Asset Seizure Executed</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="small text-muted font-weight-bold">Date Filed</label>
                        <input type="date" name="date_filed" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-2 mb-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-warning btn-sm btn-block font-weight-bold text-dark">
                            <i class="fas fa-plus mr-1"></i> Add Pin
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Existing Pins Table -->
        <h6 class="font-weight-bold text-light mb-2">Current Active Jurisdiction Pins (<?= count($case_jurisdictions) ?>)</h6>
        <div class="table-responsive">
            <table class="table table-dark table-bordered mb-0" style="font-size:12.5px;">
                <thead style="background:#1f2533; color:#fecc56;">
                    <tr>
                        <th>Country</th>
                        <th>Action Type</th>
                        <th>Court / Agency</th>
                        <th>Docket Ref</th>
                        <th>Status</th>
                        <th>Filed Date</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($case_jurisdictions)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">No jurisdiction pins added yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($case_jurisdictions as $cj): ?>
                        <tr>
                            <td><strong class="text-warning"><?= htmlspecialchars($cj['country_code']) ?></strong> — <?= htmlspecialchars($cj['country_name']) ?></td>
                            <td class="text-white font-weight-bold"><?= htmlspecialchars($cj['action_type']) ?></td>
                            <td><?= htmlspecialchars($cj['city_court'] ?: '—') ?></td>
                            <td class="font-monospace text-muted"><?= htmlspecialchars($cj['case_ref'] ?: '—') ?></td>
                            <td><span class="badge badge-success"><?= htmlspecialchars($cj['status']) ?></span></td>
                            <td><?= date('M j, Y', strtotime($cj['date_filed'] ?: $cj['created_at'])) ?></td>
                            <td class="text-center">
                                <form method="POST" onsubmit="return confirm('Remove this jurisdiction pin?');">
                                    <input type="hidden" name="action" value="delete_case_jurisdiction">
                                    <input type="hidden" name="jurisdiction_id" value="<?= $cj['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ==========================================
     MODAL 4: ASSIGN STAFF MEMBER TO CASE
     ========================================== -->
<div class="modal fade" id="assignStaffModal" tabindex="-1">
  <div class="modal-dialog modal-md">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-user-plus mr-2"></i>Assign Staff Member to Case</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="assign_case_agent">
        <div class="modal-body p-4">
            <div class="form-group mb-3">
                <label class="small text-warning font-weight-bold text-uppercase">Select Staff Member <span class="text-danger">*</span></label>
                <select name="staff_user_id" class="form-control bg-black text-white border-secondary" required>
                    <option value="">-- Choose Staff User --</option>
                    <?php foreach ($all_staff as $st): ?>
                        <option value="<?= $st['id'] ?>">
                            <?= htmlspecialchars($st['full_name'] ?: $st['username']) ?> (<?= htmlspecialchars($st['role']) ?><?= !empty($st['custom_role_title']) ? ' - ' . htmlspecialchars($st['custom_role_title']) : '' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group mb-3">
                <label class="small text-warning font-weight-bold text-uppercase">Case Role / Title <span class="text-danger">*</span></label>
                <input type="text" name="case_role" class="form-control bg-black text-white border-secondary font-weight-bold" list="caseRolesList" required value="Senior Investigator" placeholder="e.g. Lead Investigator, Forensic Analyst">
                <datalist id="caseRolesList">
                    <option value="Lead Investigator">
                    <option value="Senior Forensic Analyst">
                    <option value="Legal Counsel & Barrister">
                    <option value="Blockchain Intelligence Officer">
                    <option value="Case Manager">
                    <option value="Asset Recovery Specialist">
                    <option value="Junior Auditor">
                </datalist>
            </div>

            <label class="small text-warning font-weight-bold text-uppercase mb-2">Granular Capabilities for This Case</label>
            <div class="p-3 rounded border border-secondary" style="background:#161a23;">
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input" id="new_can_fin" name="can_view_financials" value="1" checked>
                    <label class="custom-control-label text-light" for="new_can_fin"><strong>Financials & Settlement Desk</strong> (Invoices & Payouts)</label>
                </div>
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input" id="new_can_time" name="can_edit_timeline" value="1" checked>
                    <label class="custom-control-label text-light" for="new_can_time"><strong>Timeline Milestones</strong> (Create/Edit updates)</label>
                </div>
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input" id="new_can_chat" name="can_chat_client" value="1" checked>
                    <label class="custom-control-label text-light" for="new_can_chat"><strong>Live Client Chat</strong> (Send/Receive direct messages)</label>
                </div>
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input" id="new_can_wal" name="can_manage_wallets" value="1" checked>
                    <label class="custom-control-label text-light" for="new_can_wal"><strong>Blockchain Radar</strong> (Tracked wallets & TX hops)</label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="new_can_doc" name="can_upload_docs" value="1" checked>
                    <label class="custom-control-label text-light" for="new_can_doc"><strong>Vault Document Access</strong> (Upload & review evidence)</label>
                </div>
            </div>
        </div>
        <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4">Assign Staff Member</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ==========================================
     MODALS: EDIT STAFF CASE PERMISSIONS
     ========================================== -->
<?php foreach ($assigned_team as $tm): ?>
<div class="modal fade" id="editStaffPermsModal_<?= $tm['id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-md">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-shield-alt mr-2"></i>Edit Case Permissions</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="update_case_agent_perms">
        <input type="hidden" name="assignment_id" value="<?= $tm['id'] ?>">
        <div class="modal-body p-4">
            <div class="d-flex align-items-center mb-3 p-2 rounded bg-black border border-secondary">
                <img src="<?= htmlspecialchars(get_portal_avatar_url($pdo, 'admin', $tm['user_id'])) ?>" class="rounded-circle border border-warning mr-2" width="40" height="40" style="object-fit:cover;" onerror="this.onerror=null;this.src='/admin_assets/img/profile/blank.png';">
                <div>
                    <h6 class="text-white font-weight-bold mb-0"><?= htmlspecialchars($tm['full_name'] ?: $tm['username']) ?></h6>
                    <small class="text-muted"><?= htmlspecialchars($tm['email'] ?? '') ?></small>
                </div>
            </div>

            <div class="form-group mb-3">
                <label class="small text-warning font-weight-bold text-uppercase">Case Role / Title <span class="text-danger">*</span></label>
                <input type="text" name="case_role" class="form-control bg-black text-white border-secondary font-weight-bold" required value="<?= htmlspecialchars($tm['case_role']) ?>">
            </div>

            <label class="small text-warning font-weight-bold text-uppercase mb-2">Granular Capabilities for This Case</label>
            <div class="p-3 rounded border border-secondary" style="background:#161a23;">
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input" id="edit_can_fin_<?= $tm['id'] ?>" name="can_view_financials" value="1" <?= $tm['can_view_financials'] ? 'checked' : '' ?>>
                    <label class="custom-control-label text-light" for="edit_can_fin_<?= $tm['id'] ?>"><strong>Financials & Settlement Desk</strong></label>
                </div>
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input" id="edit_can_time_<?= $tm['id'] ?>" name="can_edit_timeline" value="1" <?= $tm['can_edit_timeline'] ? 'checked' : '' ?>>
                    <label class="custom-control-label text-light" for="edit_can_time_<?= $tm['id'] ?>"><strong>Timeline Milestones</strong></label>
                </div>
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input" id="edit_can_chat_<?= $tm['id'] ?>" name="can_chat_client" value="1" <?= $tm['can_chat_client'] ? 'checked' : '' ?>>
                    <label class="custom-control-label text-light" for="edit_can_chat_<?= $tm['id'] ?>"><strong>Live Client Chat</strong></label>
                </div>
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input" id="edit_can_wal_<?= $tm['id'] ?>" name="can_manage_wallets" value="1" <?= $tm['can_manage_wallets'] ? 'checked' : '' ?>>
                    <label class="custom-control-label text-light" for="edit_can_wal_<?= $tm['id'] ?>"><strong>Blockchain Radar</strong></label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="edit_can_doc_<?= $tm['id'] ?>" name="can_upload_docs" value="1" <?= $tm['can_upload_docs'] ? 'checked' : '' ?>>
                    <label class="custom-control-label text-light" for="edit_can_doc_<?= $tm['id'] ?>"><strong>Vault Document Access</strong></label>
                </div>
            </div>
        </div>
        <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4">Update Permissions</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php require_once '../includes/admin_footer.php'; ?>




