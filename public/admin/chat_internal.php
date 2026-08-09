<?php
// admin/chat_internal.php
// Cleanly redirect to the fully-featured chat desk admin/chat.php
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: /admin/chat.php" . $query);
exit;
?>
