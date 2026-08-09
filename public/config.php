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

// Base URL configuration (dynamic for Hostinger)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
define('BASE_URL', $protocol . $host);
// Secure session settings (recommendation for production)
// ini_set('session.cookie_httponly', 1);
// ini_set('session.cookie_secure', 1); // Only over HTTPS
// ini_set('session.use_only_cookies', 1);
?>




