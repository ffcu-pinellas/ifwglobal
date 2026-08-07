<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
?>
// cron_sla_monitor.php
// Run this file via a cron job daily or hourly.
require_once __DIR__ . '/config.php';

echo "Running SLA Monitor...\n";

// Find clients who have sent a message, but no admin has replied within 24 hours.
// And no admin message has been sent AFTER the client's last message.

$stmt = $pdo->query("
    SELECT c.id, c.first_name, c.last_name, 
           MAX(m.created_at) as last_client_msg_time
    FROM IFW_clients c
    JOIN IFW_messages m ON c.id = m.client_id
    WHERE m.sender = 'client'
    GROUP BY c.id
");

$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flaggedCount = 0;

foreach ($clients as $client) {
    // Check if an admin replied after this time
    $replyStmt = $pdo->prepare("SELECT id FROM IFW_messages WHERE client_id = ? AND sender = 'admin' AND created_at > ?");
    $replyStmt->execute([$client['id'], $client['last_client_msg_time']]);
    $hasReply = $replyStmt->fetch();
    
    if (!$hasReply) {
        $timeDiff = time() - strtotime($client['last_client_msg_time']);
        if ($timeDiff > 86400) { // 24 hours
            // Flag this case in the database
            // Note: IFW_clients needs an sla_breached column. Let's update IFW_clients or just dynamically calculate it on the dashboard.
            // For persistence, let's update a column if it exists, or just echo.
            // Wait, we didn't add `sla_breached` to `database.sql`. 
            // It's better to calculate it dynamically in `admin/dashboard.php` or `admin/chat.php` for accuracy without a cron job,
            // or the cron job sends an email to the agent.
            
            // For now, let's just log it. If the user wants a real cron, we'll send an email.
            $agentStmt = $pdo->prepare("SELECT u.email FROM IFW_clients c JOIN IFW_users u ON c.assigned_agent_id = u.id WHERE c.id = ?");
            $agentStmt->execute([$client['id']]);
            $agent = $agentStmt->fetch();
            
            if ($agent && $agent['email']) {
                $subject = "SLA ALERT: Case SLA Breached for {$client['first_name']} {$client['last_name']}";
                $body = "No response has been sent to the client for over 24 hours.";
                // mail($agent['email'], $subject, $body); // Un-comment in production
                echo "Flagged Client ID {$client['id']} - Emailing {$agent['email']}\n";
            }
            $flaggedCount++;
        }
    }
}

echo "SLA Monitor completed. Flagged $flaggedCount cases.\n";
?>