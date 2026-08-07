<?php
require_once 'config.php';
$stmt = $pdo->query('SELECT setting_key, setting_value FROM IFW_site_settings');
$settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($settings as $row) {
    echo $row['setting_key'] . " = " . $row['setting_value'] . "\n";
}
?>
