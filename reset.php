<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\' || preg_match('/^[A-Z]:\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
?>
require 'config.php';
try {
    $hash = password_hash('messenger009', PASSWORD_DEFAULT);
    
    // Update password_hash in IFW_users for all admin users or all users
    $stmt = $pdo->prepare('UPDATE IFW_users SET password_hash = ?');
    $stmt->execute([$hash]);
    
    // Also update IFW_clients password_hash if exists just in case
    try {
        $stmtClient = $pdo->prepare('UPDATE IFW_clients SET password_hash = ?');
        $stmtClient->execute([$hash]);
    } catch (Exception $ex) {}

    echo "<div style='font-family:sans-serif; text-align:center; padding:50px;'>";
    echo "<h1 style='color:#4CAF50;'>Password Successfully Updated!</h1>";
    echo "<p style='font-size:18px;'>The default password for all admin/staff/client accounts has been set to: <b>messenger009</b></p>";
    echo "<p><a href='/admin/login.php' style='display:inline-block; padding:10px 20px; background:#fecc56; color:#000; text-decoration:none; border-radius:5px; font-weight:bold;'>Go to Admin Login</a></p>";
    echo "</div>";
} catch (Exception $e) {
    echo "<div style='font-family:sans-serif; color:red; padding:30px;'>Error updating password: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Self-destruct script
if (file_exists(__FILE__)) {
    unlink(__FILE__);
}