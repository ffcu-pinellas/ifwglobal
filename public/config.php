<?php
// config.php

if (!function_exists('env_val')) {
    /**
     * Unified environment variable lookup supporting both .env (Hostinger/cPanel)
     * and OS environment variables (Render/Docker).
     */
    function env_val($key, $default = null) {
        static $iniCache = null;
        if ($iniCache === null) {
            $iniCache = [];
            $candidates = [
                __DIR__ . '/.env',
                dirname(__DIR__) . '/.env',
            ];
            foreach ($candidates as $envPath) {
                if (is_file($envPath) && is_readable($envPath)) {
                    $parsed = @parse_ini_file($envPath, false, INI_SCANNER_RAW);
                    if (is_array($parsed)) {
                        foreach ($parsed as $k => $v) {
                            $v = trim((string)$v);
                            if (strlen($v) >= 2 && (($v[0] === '"' && $v[strlen($v) - 1] === '"') || ($v[0] === "'" && $v[strlen($v) - 1] === "'"))) {
                                $v = substr($v, 1, -1);
                            }
                            $iniCache[$k] = $v;
                        }
                    }
                    break;
                }
            }
        }

        if (array_key_exists($key, $iniCache)) {
            return $iniCache[$key];
        }

        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return $val;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        return $default;
    }
}

if (!function_exists('ifw_load_env_array')) {
    function ifw_load_env_array() {
        $result = [];
        $candidates = [__DIR__ . '/.env', dirname(__DIR__) . '/.env'];
        foreach ($candidates as $envPath) {
            if (is_file($envPath) && is_readable($envPath)) {
                $parsed = @parse_ini_file($envPath, false, INI_SCANNER_RAW);
                if (is_array($parsed)) {
                    foreach ($parsed as $k => $v) {
                        $v = trim((string)$v);
                        if (strlen($v) >= 2 && (($v[0] === '"' && $v[strlen($v) - 1] === '"') || ($v[0] === "'" && $v[strlen($v) - 1] === "'"))) {
                            $v = substr($v, 1, -1);
                        }
                        $result[$k] = $v;
                    }
                }
                break;
            }
        }
        $keys = [
            'APP_NAME', 'APP_URL',
            'DB_HOST', 'DB_PORT', 'DB_USER', 'DB_USERNAME', 'DB_PASS', 'DB_PASSWORD', 'DB_NAME', 'DB_DATABASE',
            'DB_SSL', 'DB_SSL_CA', 'MYSQL_ATTR_SSL_CA', 'DB_SSL_VERIFY',
            'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
            'RESEND_API_KEY', 'BREVO_API_KEY',
            'TELEGRAM_BOT_TOKEN', 'TELEGRAM_CHAT_ID',
            'CRON_SECRET',
        ];
        foreach ($keys as $k) {
            if (!array_key_exists($k, $result)) {
                $v = env_val($k, null);
                if ($v !== null) {
                    $result[$k] = $v;
                }
            }
        }
        return $result;
    }
}

if (!function_exists('ifw_resolve_ssl_ca_path')) {
    function ifw_resolve_ssl_ca_path() {
        $configured = env_val('DB_SSL_CA', env_val('MYSQL_ATTR_SSL_CA', ''));
        if (!empty($configured)) {
            return $configured;
        }
        $candidates = [
            '/etc/secrets/isrgrootx1.pem',
            __DIR__ . '/isrgrootx1.pem',
            dirname(__DIR__) . '/isrgrootx1.pem',
            '/etc/ssl/certs/ca-certificates.crt',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }
}

if (!function_exists('ifw_run_schema_once')) {
    /**
     * Gate self-healing DDL checks behind a versioned setting + session cache
     * so normal HTTP requests do not execute DDL storms against remote TiDB.
     */
    function ifw_run_schema_once($pdo, $scope, $version, callable $fn) {
        if (!$pdo) {
            return;
        }
        static $localChecked = [];
        $cacheKey = $scope . ':' . $version;
        if (!empty($localChecked[$cacheKey])) {
            return;
        }
        if (php_sapi_name() !== 'cli' && isset($_SESSION['ifw_schema_cache'][$scope]) && $_SESSION['ifw_schema_cache'][$scope] === $version) {
            $localChecked[$cacheKey] = true;
            return;
        }
        $settingKey = ($scope === 'core') ? 'schema_version' : ('schema_version_' . $scope);
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM IFW_site_settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$settingKey]);
            $current = $stmt->fetchColumn();
            if ($current === $version) {
                $localChecked[$cacheKey] = true;
                if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION['ifw_schema_cache'][$scope] = $version;
                }
                return;
            }
        } catch (Exception $e) {
            // IFW_site_settings might not exist yet on a fresh install; run $fn anyway
        }

        $fn($pdo);

        try {
            $pdo->prepare("INSERT INTO IFW_site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
                ->execute([$settingKey, $version]);
        } catch (Exception $e) {}

        $localChecked[$cacheKey] = true;
        if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['ifw_schema_cache'][$scope] = $version;
        }
    }
}

// Guard session initialization for HTTP requests only and apply secure cookie flags
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $isHttpsReq = (
        (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ||
        (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
        ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
    );
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.use_only_cookies', '1');
    if ($isHttpsReq) {
        @ini_set('session.cookie_secure', '1');
    }
    @session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttpsReq,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Populate $env consistently for both .env (cPanel/Hostinger) and OS env (Render)
$env = ifw_load_env_array();

if (!defined('DB_HOST')) define('DB_HOST', (string)env_val('DB_HOST', '127.0.0.1'));
if (!defined('DB_PORT')) define('DB_PORT', (string)env_val('DB_PORT', '3306'));
if (!defined('DB_USER')) define('DB_USER', (string)env_val('DB_USER', env_val('DB_USERNAME', '')));
if (!defined('DB_PASS')) define('DB_PASS', (string)env_val('DB_PASS', env_val('DB_PASSWORD', '')));
if (!defined('DB_NAME')) define('DB_NAME', (string)env_val('DB_NAME', env_val('DB_DATABASE', '')));

// Establish PDO connection (with automatic TLS for TiDB Cloud or explicit DB_SSL/DB_SSL_CA)
try {
    $pdoOptions = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $dbSslRaw = strtolower(trim((string)env_val('DB_SSL', '')));
    $sslExplicitFalse = in_array($dbSslRaw, ['false', '0', 'off', 'no', 'disable', 'disabled'], true);
    $sslExplicitTrue  = in_array($dbSslRaw, ['true', '1', 'on', 'yes', 'require', 'required'], true);
    $configuredSslCa  = env_val('DB_SSL_CA', env_val('MYSQL_ATTR_SSL_CA', ''));
    $isTiDbTarget     = ((int)DB_PORT === 4000) || (stripos(DB_HOST, 'tidbcloud.com') !== false);

    if (!$sslExplicitFalse && ($sslExplicitTrue || !empty($configuredSslCa) || $isTiDbTarget)) {
        $sslCaPath = ifw_resolve_ssl_ca_path();
        if (!empty($sslCaPath) && defined('PDO::MYSQL_ATTR_SSL_CA')) {
            $pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = $sslCaPath;
        }
        $verifyRaw = strtolower(trim((string)env_val('DB_SSL_VERIFY', 'true')));
        $verifyCert = !in_array($verifyRaw, ['false', '0', 'off', 'no'], true);
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $verifyCert;
        }
    }

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdoOptions);
    $pdo->exec("SET time_zone = '+00:00'");
} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}

// Self-healing database check (gated by schema_version in IFW_site_settings + session cache)
ifw_run_schema_once($pdo, 'core', '2026_11_24_v1', function ($pdo) {
    $alterStatements = [
        "ALTER TABLE IFW_clients ADD COLUMN IF NOT EXISTS country VARCHAR(100) NULL AFTER phone",
        "ALTER TABLE IFW_clients ADD COLUMN IF NOT EXISTS dob DATE NULL AFTER email",
        "ALTER TABLE IFW_clients ADD COLUMN IF NOT EXISTS address TEXT NULL AFTER dob",
        "ALTER TABLE IFW_clients ADD COLUMN IF NOT EXISTS preferred_currency VARCHAR(10) DEFAULT 'USD'",
        "ALTER TABLE IFW_chat_messages ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(500) NULL AFTER message",
        "ALTER TABLE IFW_chat_messages ADD COLUMN IF NOT EXISTS attachment_name VARCHAR(255) NULL AFTER attachment_path",
        "ALTER TABLE IFW_chat_messages ADD COLUMN IF NOT EXISTS attachment_size INT DEFAULT 0 AFTER attachment_name",
        "ALTER TABLE IFW_chat_messages ADD COLUMN IF NOT EXISTS email_notified TINYINT(1) DEFAULT 0",
        "ALTER TABLE IFW_messages ADD COLUMN IF NOT EXISTS admin_id INT NULL AFTER sender",
        "ALTER TABLE IFW_invoices ADD COLUMN IF NOT EXISTS late_fee_is_percentage TINYINT(1) DEFAULT 0 AFTER late_fee_amount",
        "ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS show_blockchain_watcher TINYINT(1) DEFAULT 0",
        "ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS show_settlement_escrow TINYINT(1) DEFAULT 0",
        "ALTER TABLE IFW_cases ADD COLUMN IF NOT EXISTS show_recovery_map TINYINT(1) DEFAULT 0",
    ];
    foreach ($alterStatements as $sql) {
        try { $pdo->exec($sql); } catch (Exception $ex) {}
    }

    $createStatements = [
        "CREATE TABLE IF NOT EXISTS IFW_invoice_payments (
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
        )",
        "CREATE TABLE IF NOT EXISTS IFW_blockchain_wallets (
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
        )",
        "CREATE TABLE IF NOT EXISTS IFW_blockchain_txs (
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
        )",
        "CREATE TABLE IF NOT EXISTS IFW_case_settlements (
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
        )",
        "CREATE TABLE IF NOT EXISTS IFW_case_jurisdictions (
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
        )",
        "CREATE TABLE IF NOT EXISTS IFW_login_history (
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
        )",
    ];
    foreach ($createStatements as $sql) {
        try { $pdo->exec($sql); } catch (Exception $ex) {}
    }

    // Default Chatwoot Settings Auto-Seeder
    try {
        $cw_check = $pdo->query("SELECT setting_value FROM IFW_site_settings WHERE setting_key = 'chatwoot_website_token' LIMIT 1");
        $cw_val = $cw_check ? $cw_check->fetchColumn() : null;
        if (empty($cw_val)) {
            $pdo->prepare("INSERT INTO IFW_site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                ->execute(['chatwoot_website_token', 'uHR3DJPM8AZ2Lpo8tDdJ5tei', 'uHR3DJPM8AZ2Lpo8tDdJ5tei']);
            $pdo->prepare("INSERT INTO IFW_site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                ->execute(['chatwoot_account_id', '180927', '180927']);
            $pdo->prepare("INSERT INTO IFW_site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                ->execute(['chatwoot_base_url', 'https://app.chatwoot.com', 'https://app.chatwoot.com']);
            $pdo->prepare("INSERT INTO IFW_site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                ->execute(['chatwoot_hmac_key', '6q99KLZgjCtHCd1fvQpQTp2F', '6q99KLZgjCtHCd1fvQpQTp2F']);
            $pdo->prepare("INSERT INTO IFW_site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                ->execute(['chat_provider', 'chatwoot', 'chatwoot']);
        }
    } catch (Exception $ex) {}
});

// Base URL configuration: prefer APP_URL (essential for CLI crons and Render),
// otherwise dynamically detect reverse-proxy HTTPS + Host + subdirectory.
if (!defined('BASE_URL')) {
    $appUrlEnv = trim((string)env_val('APP_URL', ''));
    if ($appUrlEnv !== '') {
        define('BASE_URL', rtrim($appUrlEnv, '/'));
    } else {
        $isHttps = (
            (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ||
            (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
            ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
        );
        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
        $script_dirname = $script_name !== '' ? str_replace('\\', '/', dirname($script_name)) : '';
        // Remove /admin, /client, or /api from the end of the script_dirname if present
        $script_dirname = preg_replace('#/(admin|client|api)$#', '', $script_dirname);
        if ($script_dirname === '/' || $script_dirname === '.') {
            $script_dirname = '';
        }
        define('BASE_URL', $protocol . $host . $script_dirname);
    }
}

