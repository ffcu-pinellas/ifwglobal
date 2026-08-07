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

// Base URL configuration (dynamic for Hostinger)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
define('BASE_URL', $protocol . $host);
// Secure session settings (recommendation for production)
// ini_set('session.cookie_httponly', 1);
// ini_set('session.cookie_secure', 1); // Only over HTTPS
// ini_set('session.use_only_cookies', 1);
?>




