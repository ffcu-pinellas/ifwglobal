<?php
// config.php
session_start();

// Parse .env file
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $env = parse_ini_file($envPath);
    define('DB_HOST', $env['DB_HOST'] ?? '127.0.0.1');
    define('DB_PORT', $env['DB_PORT'] ?? '3306');
    define('DB_USER', $env['DB_USER'] ?? ''); 
    define('DB_PASS', $env['DB_PASS'] ?? '');     
    define('DB_NAME', $env['DB_NAME'] ?? '');
} else {
    // Fallback or production environment variables
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
    define('DB_USER', getenv('DB_USER') ?: 'u664663598_ifwglobal'); 
    define('DB_PASS', getenv('DB_PASS') ?: 'Messenger@0090');     
    define('DB_NAME', getenv('DB_NAME') ?: 'u664663598_ifwglobal');
}

// Establish connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}

// Self-healing database check
try {
    $pdo->query("SELECT country FROM IFW_clients LIMIT 1");
} catch (PDOException $e) {
    try { $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN country VARCHAR(100) NULL AFTER phone"); } catch (Exception $ex) {}
}
try {
    $pdo->query("SELECT dob FROM IFW_clients LIMIT 1");
} catch (PDOException $e) {
    try { $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN dob DATE NULL AFTER email"); } catch (Exception $ex) {}
}
try {
    $pdo->query("SELECT address FROM IFW_clients LIMIT 1");
} catch (PDOException $e) {
    try { $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN address TEXT NULL AFTER dob"); } catch (Exception $ex) {}
}
try {
    $pdo->query("SELECT preferred_currency FROM IFW_clients LIMIT 1");
} catch (PDOException $e) {
    try { $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN preferred_currency VARCHAR(10) DEFAULT 'USD'"); } catch (Exception $ex) {}
}
try {
    $pdo->query("SELECT attachment_path FROM IFW_chat_messages LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE IFW_chat_messages ADD COLUMN attachment_path VARCHAR(500) NULL AFTER message");
        $pdo->exec("ALTER TABLE IFW_chat_messages ADD COLUMN attachment_name VARCHAR(255) NULL AFTER attachment_path");
        $pdo->exec("ALTER TABLE IFW_chat_messages ADD COLUMN attachment_size INT DEFAULT 0 AFTER attachment_name");
        $pdo->exec("ALTER TABLE IFW_chat_messages ADD COLUMN email_notified TINYINT(1) DEFAULT 0");
    } catch (Exception $ex) {}
}
try {
    $pdo->query("SELECT admin_id FROM IFW_messages LIMIT 1");
} catch (PDOException $e) {
    try { $pdo->exec("ALTER TABLE IFW_messages ADD COLUMN admin_id INT NULL AFTER sender"); } catch (Exception $ex) {}
}
try {
    $pdo->query("SELECT late_fee_is_percentage FROM IFW_invoices LIMIT 1");
} catch (PDOException $e) {
    try { $pdo->exec("ALTER TABLE IFW_invoices ADD COLUMN late_fee_is_percentage TINYINT(1) DEFAULT 0 AFTER late_fee_amount"); } catch (Exception $ex) {}
}

try {
    $pdo->query("SELECT id FROM IFW_invoice_payments LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("CREATE TABLE IFW_invoice_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            client_id INT NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            payment_method VARCHAR(255) NULL,
            reference_number VARCHAR(255) NULL,
            proof_file VARCHAR(500) NULL,
            notes TEXT NULL,
            status ENUM('Pending', 'Confirmed', 'Rejected') DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $ex) {}
}

// Self-healing: Blockchain Watcher Wallets
try {
    $pdo->query("SELECT id FROM IFW_blockchain_wallets LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("CREATE TABLE IFW_blockchain_wallets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            case_id INT NOT NULL,
            crypto_type VARCHAR(50) NOT NULL DEFAULT 'USDT (TRC-20)',
            wallet_address VARCHAR(255) NOT NULL,
            wallet_label VARCHAR(255) NULL,
            balance DECIMAL(24,8) DEFAULT 0.00000000,
            usd_value DECIMAL(15,2) DEFAULT 0.00,
            risk_score INT DEFAULT 92,
            threat_level VARCHAR(50) DEFAULT 'CRITICAL',
            exchange_tags VARCHAR(500) NULL,
            status VARCHAR(100) DEFAULT 'Active Monitoring',
            is_live_api TINYINT(1) DEFAULT 0,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $ex) {}
}

// Self-healing: Blockchain Watcher Transactions / Hops
try {
    $pdo->query("SELECT id FROM IFW_blockchain_txs LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("CREATE TABLE IFW_blockchain_txs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            wallet_id INT NULL,
            case_id INT NOT NULL,
            tx_hash VARCHAR(255) NOT NULL,
            from_address VARCHAR(255) NULL,
            to_address VARCHAR(255) NULL,
            amount DECIMAL(24,8) NOT NULL DEFAULT 0.00000000,
            crypto_type VARCHAR(50) DEFAULT 'USDT',
            direction ENUM('IN', 'OUT') DEFAULT 'OUT',
            flag_tag VARCHAR(255) NULL,
            tx_time DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $ex) {}
}

// Self-healing: Case Settlements & Escrow Hub
try {
    $pdo->query("SELECT id FROM IFW_case_settlements LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("CREATE TABLE IFW_case_settlements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            case_id INT NOT NULL,
            client_id INT NOT NULL,
            gross_recovered DECIMAL(15,2) DEFAULT 0.00,
            fee_percent DECIMAL(5,2) DEFAULT 10.00,
            fee_amount DECIMAL(15,2) DEFAULT 0.00,
            net_payout DECIMAL(15,2) DEFAULT 0.00,
            escrow_ref VARCHAR(100) NULL,
            custody_entity VARCHAR(255) DEFAULT 'Swiss Multi-Sig Escrow Vault (FINMA Compliant)',
            clearance_stage INT DEFAULT 1,
            status VARCHAR(100) DEFAULT 'Secured in Escrow',
            payout_method VARCHAR(100) NULL,
            payout_destination_details TEXT NULL,
            client_confirmed_at DATETIME NULL,
            client_signature_hash VARCHAR(255) NULL,
            is_enabled TINYINT(1) DEFAULT 1,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    } catch (Exception $ex) {}
}

// Self-healing: Jurisdictional Freeze Radar Pins
try {
    $pdo->query("SELECT id FROM IFW_case_jurisdictions LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("CREATE TABLE IFW_case_jurisdictions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            case_id INT NOT NULL,
            country_code VARCHAR(10) NOT NULL,
            country_name VARCHAR(100) NOT NULL,
            city_court VARCHAR(255) NULL,
            action_type VARCHAR(255) NOT NULL,
            case_ref VARCHAR(100) NULL,
            status VARCHAR(100) DEFAULT 'Active Freeze Order',
            date_filed DATE NULL,
            notes TEXT NULL,
            is_enabled TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $ex) {}
}

// Self-healing: Login History & Device Security Audit
try {
    $pdo->query("SELECT id FROM IFW_login_history LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("CREATE TABLE IFW_login_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            role ENUM('client', 'admin', 'staff') NOT NULL DEFAULT 'client',
            email VARCHAR(255) NOT NULL,
            ip_address VARCHAR(100) NOT NULL,
            user_agent VARCHAR(500) NULL,
            device_type VARCHAR(50) DEFAULT 'Desktop',
            browser VARCHAR(100) NULL,
            os VARCHAR(100) NULL,
            city_country VARCHAR(255) NULL,
            is_new_device TINYINT(1) DEFAULT 0,
            login_status ENUM('success', 'failed_credentials', 'failed_otp') DEFAULT 'success',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $ex) {}
}

// Self-healing: Feature Flags on IFW_cases (Disabled by default: 0)
try {
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS show_blockchain_watcher TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS show_settlement_escrow TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS show_recovery_map TINYINT(1) DEFAULT 0");
} catch (Exception $ex) {}

// Base URL configuration (dynamic for Hostinger and localhost subdirectories)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$script_dirname = dirname($_SERVER['SCRIPT_NAME']);
$script_dirname = str_replace('\\', '/', $script_dirname);
// Remove /admin or /client from the end of the script_dirname if present
$script_dirname = preg_replace('#/(admin|client)$#', '', $script_dirname);
if ($script_dirname === '/') $script_dirname = '';

define('BASE_URL', $protocol . $host . $script_dirname);
// Secure session settings (recommendation for production)
// ini_set('session.cookie_httponly', 1);
// ini_set('session.cookie_secure', 1); // Only over HTTPS
// ini_set('session.use_only_cookies', 1);
?>




