<?php
// cron_sla_monitor.php
// Run this file via a cron job (e.g. every 10 or 15 minutes) or call it dynamically.

$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

echo "Starting Chat SLA Monitor...\n";

// Get SLA timeout setting (default: 30 minutes)
$sla_minutes = (int)get_setting($pdo, 'sla_response_time_minutes', '30');
$sla_seconds = $sla_minutes * 60;
$cutoff_time = date('Y-m-d H:i:s', time() - $sla_seconds);

echo "Cutoff time is: $cutoff_time (SLA: $sla_minutes minutes)\n";

$emails_sent = 0;

try {
    // 1. UNREAD CLIENT MESSAGES -> NOTIFY ASSIGNED AGENT OR ADMINS
    $client_msgs_stmt = $pdo->prepare("
        SELECT m.id, m.client_id, m.message, m.created_at, 
               c.first_name, c.last_name, c.assigned_agent_id
        FROM IFW_chat_messages m
        JOIN IFW_clients c ON m.client_id = c.id
        WHERE m.sender_type = 'client' 
          AND m.is_read = 0 
          AND m.email_notified = 0 
          AND m.created_at < ?
    ");
    $client_msgs_stmt->execute([$cutoff_time]);
    $client_msgs = $client_msgs_stmt->fetchAll();

    foreach ($client_msgs as $msg) {
        $client_name = $msg['first_name'] . ' ' . $msg['last_name'];
        $message_snippet = strlen($msg['message']) > 80 ? substr($msg['message'], 0, 80) . '...' : $msg['message'];
        
        $recipients = [];
        
        if ($msg['assigned_agent_id']) {
            // Send to assigned investigator
            $agent_stmt = $pdo->prepare("SELECT email, username FROM IFW_users WHERE id = ?");
            $agent_stmt->execute([$msg['assigned_agent_id']]);
            $agent = $agent_stmt->fetch();
            if ($agent && $agent['email']) {
                $recipients[] = [
                    'email' => $agent['email'],
                    'name' => $agent['username']
                ];
            }
        }
        
        // If no assigned agent found or to ensure admins get it, fallback to superadmins/admins
        if (empty($recipients)) {
            $admins_stmt = $pdo->query("SELECT email, username FROM IFW_users WHERE role IN ('admin', 'superadmin')");
            while ($admin = $admins_stmt->fetch()) {
                if ($admin['email']) {
                    $recipients[] = [
                        'email' => $admin['email'],
                        'name' => $admin['username']
                    ];
                }
            }
        }

        foreach ($recipients as $recip) {
            $subject = "Unresolved Client Message Alert: {$client_name}";
            $body = "
                <p>Hello <strong>{$recip['name']}</strong>,</p>
                <p>This is an automated alert that a client message has remained unanswered for more than <strong>{$sla_minutes} minutes</strong>.</p>
                <div style='background-color:#f9f9f9; padding: 15px; border-left: 4px solid #fecc56; margin: 15px 0;'>
                    <strong>Client:</strong> {$client_name}<br>
                    <strong>Sent At:</strong> {$msg['created_at']}<br><br>
                    <strong>Message:</strong><br>
                    <em>\"{$message_snippet}\"</em>
                </div>
                <p><a href='" . BASE_URL . "/admin/chat.php' class='button' style='color:#000;'>Open Chat Console & Respond</a></p>
            ";
            
            $sent = false;
            if (function_exists('send_html_email')) {
                $sent = send_html_email($recip['email'], $subject, $body);
            } elseif (function_exists('send_notification_email')) {
                $sent = send_notification_email($recip['email'], $subject, $body);
            }
            if ($sent) {
                echo "Sent unreplied client message alert to: {$recip['email']}\n";
                $emails_sent++;
            }
        }
        
        // Mark message as notified so we don't spam
        $pdo->prepare("UPDATE IFW_chat_messages SET email_notified = 1 WHERE id = ?")->execute([$msg['id']]);
    }

    // 2. UNREAD ADMIN MESSAGES -> NOTIFY CLIENT
    $admin_msgs_stmt = $pdo->prepare("
        SELECT m.id, m.client_id, m.message, m.created_at, 
               c.first_name, c.last_name, c.email
        FROM IFW_chat_messages m
        JOIN IFW_clients c ON m.client_id = c.id
        WHERE m.sender_type = 'admin' 
          AND m.is_read = 0 
          AND m.email_notified = 0 
          AND m.created_at < ?
    ");
    $admin_msgs_stmt->execute([$cutoff_time]);
    $admin_msgs = $admin_msgs_stmt->fetchAll();

    foreach ($admin_msgs as $msg) {
        if ($msg['email']) {
            $client_name = $msg['first_name'] . ' ' . $msg['last_name'];
            $message_snippet = strlen($msg['message']) > 80 ? substr($msg['message'], 0, 80) . '...' : $msg['message'];
            
            $subject = "New Secure Message from IFW Global Support";
            $body = "
                <p>Dear <strong>{$client_name}</strong>,</p>
                <p>You have an unread secure message waiting for you in your IFW Global client portal.</p>
                <div style='background-color:#f9f9f9; padding: 15px; border-left: 4px solid #fecc56; margin: 15px 0;'>
                    <strong>From:</strong> Case Recovery Team<br>
                    <strong>Sent At:</strong> {$msg['created_at']}<br><br>
                    <strong>Message Preview:</strong><br>
                    <em>\"{$message_snippet}\"</em>
                </div>
                <p><a href='" . BASE_URL . "/client/chat.php' class='button' style='color:#000;'>Login & View Message</a></p>
            ";
            
            $sent = false;
            if (function_exists('send_html_email')) {
                $sent = send_html_email($msg['email'], $subject, $body);
            } elseif (function_exists('send_notification_email')) {
                $sent = send_notification_email($msg['email'], $subject, $body);
            }
            if ($sent) {
                echo "Sent unread admin message alert to client: {$msg['email']}\n";
                $emails_sent++;
            }
        }
        
        // Mark message as notified so we don't spam
        $pdo->prepare("UPDATE IFW_chat_messages SET email_notified = 1 WHERE id = ?")->execute([$msg['id']]);
    }

} catch (Exception $e) {
    echo "Error running SLA monitor: " . $e->getMessage() . "\n";
}

echo "SLA Monitor run completed. Sent $emails_sent alert emails.\n";
?>