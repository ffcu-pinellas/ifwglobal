<?php
// client/chat_internal.php
// Cleanly redirect to the fully-featured chat desk client/chat.php
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: /client/chat.php" . $query);
exit;
?>
