<?php
require 'config.php';
$pdo->exec("UPDATE IFW_users SET full_name = 'Admin Support' WHERE username = 'admin' AND (full_name IS NULL OR full_name = '')");
$pdo->exec("UPDATE IFW_users SET full_name = username WHERE full_name IS NULL OR full_name = ''");
echo "Updated full_names in IFW_users.";
