<?php
// cron_chat_unread_reminders.php
// Sends email reminders for unread chat messages older than 15 minutes (without exposing message content)
// Schedule: */15 * * * * php /path/to/cron_chat_unread_reminders.php

$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
if (file_exists($dir . '/includes/mailer.php')) {
    require_once $dir . '/includes/mailer.php';
}

$is_cli = (php_sapi_name() === 'cli' || defined('STDIN'));
$app_name = get_setting($pdo, 'app_name', 'IFW Global');
$reminder_minutes = 15;
$reminders_sent = 0;

try {
    $pdo->exec("ALTER TABLE IFW_chat_messages ADD COLUMN IF NOT EXISTS email_reminder_sent TINYINT(1) DEFAULT 0");
} catch (Exception $e) {}

if ($is_cli) {
    echo "====================================================\n";
    echo "  [IFW Global] Unread Chat Email Reminder Monitor\n";
    echo "  Started at: " . date('Y-m-d H:i:s') . "\n";
    echo "====================================================\n";
}

try {
    $sql = "
        SELECT m.id, m.client_id, m.admin_id, m.sender_type, m.created_at,
               c.first_name AS client_first, c.last_name AS client_last, c.email AS client_email,
               u.full_name AS admin_name, u.username AS admin_username, u.email AS admin_email
        FROM IFW_chat_messages m
        JOIN IFW_clients c ON m.client_id = c.id
        LEFT JOIN IFW_users u ON m.admin_id = u.id
        WHERE m.is_read = 0
          AND COALESCE(m.email_reminder_sent, 0) = 0
          AND m.created_at <= DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ORDER BY m.created_at ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$reminder_minutes]);
    $messages = $stmt->fetchAll();

    $processed_recipients = [];

    foreach ($messages as $msg) {
        $msg_id = (int)$msg['id'];
        $sender_label = '';
        $recipient_email = '';
        $recipient_name = '';
        $portal_link = rtrim(BASE_URL, '/') . '/';

        if ($msg['sender_type'] === 'client') {
            // Admin/staff should be reminded
            $sender_label = trim(($msg['client_first'] ?? '') . ' ' . ($msg['client_last'] ?? ''));
            $recipient_email = $msg['admin_email'] ?? '';
            $recipient_name = $msg['admin_name'] ?: ($msg['admin_username'] ?? 'Investigator');
            $portal_link .= 'admin/chat.php?client_id=' . (int)$msg['client_id'];

            // If no admin email on message, notify assigned agent
            if (empty($recipient_email)) {
                $agent_stmt = $pdo->prepare("SELECT u.email, u.full_name, u.username FROM IFW_clients c JOIN IFW_users u ON c.assigned_agent_id = u.id WHERE c.id = ?");
                $agent_stmt->execute([(int)$msg['client_id']]);
                if ($agent = $agent_stmt->fetch()) {
                    $recipient_email = $agent['email'] ?? '';
                    $recipient_name = $agent['full_name'] ?: ($agent['username'] ?? 'Investigator');
                }
            }
        } else {
            // Client should be reminded
            $sender_label = $msg['admin_name'] ?: ($msg['admin_username'] ?? 'Your IFW Investigator');
            $recipient_email = $msg['client_email'] ?? '';
            $recipient_name = trim(($msg['client_first'] ?? '') . ' ' . ($msg['client_last'] ?? ''));
            $portal_link .= 'client/chat.php';
        }

        if (empty($recipient_email)) {
            $pdo->prepare("UPDATE IFW_chat_messages SET email_reminder_sent = 1 WHERE id = ?")->execute([$msg_id]);
            continue;
        }

        $dedupe_key = $recipient_email . '|' . (int)$msg['client_id'] . '|' . $msg['sender_type'];
        if (isset($processed_recipients[$dedupe_key])) {
            $pdo->prepare("UPDATE IFW_chat_messages SET email_reminder_sent = 1 WHERE id = ?")->execute([$msg_id]);
            continue;
        }

        $subject = "Reminder: Unread secure message on your {$app_name} portal";
        $html = "
        <div style='background:#0d1117;color:#f0f6fc;font-family:Montserrat,sans-serif;max-width:600px;margin:0 auto;border-radius:12px;border:1px solid #30363d;overflow:hidden;'>
            <div style='background:#161b22;padding:24px;text-align:center;border-bottom:2px solid #fecc56;'>
                <h2 style='margin:0;color:#fecc56;font-size:18px;'>Unread Secure Message</h2>
            </div>
            <div style='padding:28px 24px;'>
                <p style='font-size:14px;color:#f0f6fc;'>Hello <strong>" . htmlspecialchars($recipient_name) . "</strong>,</p>
                <p style='color:#8b949e;font-size:13.5px;line-height:1.6;'>
                    You have an unread secure message from <strong>" . htmlspecialchars($sender_label) . "</strong> waiting in your encrypted portal workspace.
                    For confidentiality, the message content is not included in this email.
                </p>
                <p style='text-align:center;margin:28px 0;'>
                    <a href='" . htmlspecialchars($portal_link) . "' style='background:#fecc56;color:#000;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;display:inline-block;'>Open Secure Messages</a>
                </p>
                <p style='color:#8b949e;font-size:12px;'>Message received: " . htmlspecialchars(date('M j, Y g:i A', strtotime($msg['created_at']))) . " UTC</p>
            </div>
            <div style='background:#161b22;padding:16px 24px;text-align:center;border-top:1px solid #30363d;font-size:11px;color:#8b949e;'>
                &copy; " . date('Y') . " " . htmlspecialchars($app_name) . " &bull; Encrypted Communications Desk
            </div>
        </div>";

        $sent = false;
        if (function_exists('send_html_email')) {
            $sent = (bool)@send_html_email($recipient_email, $subject, $html);
        } elseif (function_exists('send_notification_email')) {
            $sent = (bool)@send_notification_email($pdo, $recipient_email, $subject, $html);
        }

        if ($sent) {
            $reminders_sent++;
            $processed_recipients[$dedupe_key] = true;
        }

        $pdo->prepare("UPDATE IFW_chat_messages SET email_reminder_sent = 1 WHERE id = ?")->execute([$msg_id]);
    }
} catch (Exception $e) {
    if ($is_cli) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

if ($is_cli) {
    echo "Reminders sent: {$reminders_sent}\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
}

echo json_encode(['status' => 'ok', 'reminders_sent' => $reminders_sent]);
