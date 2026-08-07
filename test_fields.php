<?php
require 'config.php';
$stmt = $pdo->query('SELECT * FROM IFW_form_fields');
$res = $stmt->fetchAll();
print_r($res);
?>
