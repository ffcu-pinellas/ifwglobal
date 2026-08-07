<?php
// cron_backup.php
// This script should be run via a cron job on the server (e.g., weekly)
// 0 0 * * 0 php /path/to/public_html/cron_backup.php

require_once __DIR__ . '/config.php';

// Only allow CLI execution or secure token access to prevent unauthorized backups
if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== 'super_secret_cron_token_123')) {
    http_response_code(403);
    die("Access denied");
}

$tables = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

$sqlScript = "";
foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW CREATE TABLE $table");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $sqlScript .= "\n\n" . $row[1] . ";\n\n";
    
    $stmt = $pdo->query("SELECT * FROM $table");
    $rowCount = $stmt->rowCount();
    
    if ($rowCount > 0) {
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $sqlScript .= "INSERT INTO $table VALUES(";
            $values = [];
            foreach ($row as $val) {
                if (!isset($val)) {
                    $values[] = "NULL";
                } else {
                    $values[] = $pdo->quote($val);
                }
            }
            $sqlScript .= implode(', ', $values) . ");\n";
        }
    }
    $sqlScript .= "\n";
}

$backup_dir = __DIR__ . '/admin/backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
    file_put_contents($backup_dir . '.htaccess', "Deny from all");
}

$backup_name = 'backup_' . DB_NAME . '_' . date("Y-m-d-H-i-s") . '.sql';
$backup_file = $backup_dir . $backup_name;

file_put_contents($backup_file, $sqlScript);

// Cleanup old backups (keep last 4)
$files = glob($backup_dir . '*.sql');
if (count($files) > 4) {
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    // Delete oldest until only 4 remain
    while (count($files) > 4) {
        $oldest = array_shift($files);
        unlink($oldest);
    }
}

echo "Backup generated successfully: $backup_name\n";
?>




