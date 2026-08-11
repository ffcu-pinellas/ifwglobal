<?php
// cron_invoice_reminders.php
// Automated background processor for invoice payment reminders and overdue late fee notices.
// Can be run via Server Crontab (e.g. 0 */4 * * * php /path/to/cron_invoice_reminders.php)
// or triggered via background web hooks.

$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
require_once $dir . '/includes/currency_helper.php';
if (file_exists($dir . '/includes/mailer.php')) {
    require_once $dir . '/includes/mailer.php';
}

$is_cli = (php_sapi_name() === 'cli' || defined('STDIN'));
$app_name = get_setting($pdo, 'app_name', 'IFW Global');
$app_url = rtrim(BASE_URL, '/');

if ($is_cli) {
    echo "====================================================\n";
    echo "  [IFW Global] Invoice & Penalty Reminder Monitor\n";
    echo "  Started at: " . date('Y-m-d H:i:s') . "\n";
    echo "====================================================\n";
}

$reminders_sent = 0;
$now_ts = time();
$cooldown_seconds = 86400; // 24 hours between automated email reminders per invoice

try {
    // 1. Fetch all unpaid or partially paid invoices with client info
    $sql = "
        SELECT i.*, 
               c.first_name, c.last_name, c.email AS client_email, c.preferred_currency,
               ca.case_number, ca.title AS case_title,
               u.email AS agent_email, u.username AS agent_username, u.full_name AS agent_name
        FROM IFW_invoices i
        JOIN IFW_clients c ON i.client_id = c.id
        LEFT JOIN IFW_cases ca ON i.case_id = ca.id
        LEFT JOIN IFW_users u ON c.assigned_agent_id = u.id
        WHERE i.status NOT IN ('Paid', 'paid', 'Cancelled', 'cancelled', 'Draft', 'draft')
        ORDER BY i.id ASC
    ";
    $invoices = $pdo->query($sql)->fetchAll();

    foreach ($invoices as $inv) {
        if (empty($inv['client_email'])) continue;

        $inv_id = (int)$inv['id'];
        $inv_ref = !empty($inv['invoice_number']) ? $inv['invoice_number'] : '#INV-' . str_pad($inv_id, 5, '0', STR_PAD_LEFT);
        $currency = !empty($inv['currency']) ? strtoupper($inv['currency']) : 'USD';
        
        $base_amount = ($inv['total_amount'] > 0) ? (float)$inv['total_amount'] : (float)($inv['amount'] ?? 0);
        
        // Calculate payments received
        $stmt_pay = $pdo->prepare("SELECT SUM(amount) FROM IFW_invoice_payments WHERE invoice_id = ? AND status = 'Confirmed'");
        $stmt_pay->execute([$inv_id]);
        $total_paid = (float)($stmt_pay->fetchColumn() ?: 0);

        // Check late fee calculations
        $late_fee = 0.00;
        $is_penalty_active = false;
        if (!empty($inv['late_fee_enabled']) && (float)($inv['late_fee_amount'] ?? 0) > 0) {
            $raw_start = !empty($inv['late_fee_start_date']) ? $inv['late_fee_start_date'] : (!empty($inv['due_date']) ? $inv['due_date'] : null);
            $start_ts = $raw_start ? strtotime($raw_start) : 0;
            $rate = (float)$inv['late_fee_amount'];
            if (!empty($inv['late_fee_is_percentage'])) {
                $rate = ($rate / 100) * $base_amount;
            }

            if ($start_ts > 0 && $now_ts >= $start_ts) {
                $diff_sec = $now_ts - $start_ts;
                $type = strtolower($inv['late_fee_type'] ?? 'daily');
                
                if ($type === 'hourly') {
                    $intervals = floor($diff_sec / 3600);
                } elseif ($type === 'daily') {
                    $intervals = floor($diff_sec / 86400);
                } elseif ($type === 'weekly') {
                    $intervals = floor($diff_sec / (86400 * 7));
                } else { // monthly
                    $intervals = floor($diff_sec / (86400 * 30));
                }
                $late_fee = max(0, $intervals * $rate);
                if ($late_fee > 0) {
                    $is_penalty_active = true;
                }
            }
        }

        $total_due = ($base_amount + $late_fee) - $total_paid;
        if ($total_due <= 0) {
            // Already settled, update status if not marked
            $pdo->prepare("UPDATE IFW_invoices SET status = 'Paid' WHERE id = ?")->execute([$inv_id]);
            continue;
        }

        // Update late_fee_accumulated in DB
        if ($late_fee > 0) {
            $pdo->prepare("UPDATE IFW_invoices SET late_fee_accumulated = ? WHERE id = ?")->execute([$late_fee, $inv_id]);
        }

        // Check if invoice is overdue
        $due_ts = !empty($inv['due_date']) ? strtotime($inv['due_date']) : 0;
        $is_overdue = ($due_ts > 0 && $now_ts > $due_ts);
        $days_until_due = ($due_ts > 0) ? ceil(($due_ts - $now_ts) / 86400) : 999;

        // Check last reminder timestamp
        $last_sent_ts = !empty($inv['last_reminder_sent']) ? strtotime($inv['last_reminder_sent']) : 0;
        if ($last_sent_ts > 0 && ($now_ts - $last_sent_ts) < $cooldown_seconds) {
            // Cooldown active, skip this turn
            continue;
        }

        // Determine email type
        $should_send = false;
        $subject = "";
        $email_type = "";

        if ($is_penalty_active) {
            $should_send = true;
            $email_type = "penalty_notice";
            $subject = "URGENT: Outstanding Overdue Penalty Notice — Invoice {$inv_ref}";
        } elseif ($is_overdue) {
            $should_send = true;
            $email_type = "overdue_reminder";
            $days_over = floor(($now_ts - $due_ts) / 86400);
            $subject = "Action Required: Overdue Invoice Reminder — {$inv_ref} ({$days_over} days overdue)";
        } elseif ($days_until_due <= 3 && $days_until_due >= 0) {
            $should_send = true;
            $email_type = "upcoming_due";
            $subject = "Friendly Reminder: Invoice {$inv_ref} Due Soon";
        }

        if (!$should_send) continue;

        // Build Email Body
        $client_name = htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']);
        $login_url = $app_url . '/client/login.php';
        $invoice_url = $app_url . '/client/invoice_view.php?id=' . $inv_id;

        $body = "
            <div style='font-family: Arial, sans-serif; color: #222;'>
                <p style='font-size: 16px;'>Dear <strong>{$client_name}</strong>,</p>
        ";

        if ($email_type === 'penalty_notice') {
            $body .= "
                <p style='color: #c92a2a; font-weight: bold; font-size: 15px;'>
                    ⚠️ Automated Formal Notice: Your invoice <u>{$inv_ref}</u> is overdue and is actively accruing late fee penalties.
                </p>
            ";
        } elseif ($email_type === 'overdue_reminder') {
            $body .= "
                <p style='color: #d9480f; font-weight: bold; font-size: 15px;'>
                    ⚠️ This is a reminder that payment for invoice <u>{$inv_ref}</u> is currently past its due date.
                </p>
            ";
        } else {
            $body .= "
                <p style='font-size: 15px;'>
                    This is a courtesy reminder regarding your upcoming invoice <u>{$inv_ref}</u> due on <strong>" . date('F j, Y', $due_ts) . "</strong>.
                </p>
            ";
        }

        $body .= "
            <div style='background-color: #f8f9fa; border: 1px solid #e9ecef; border-left: 5px solid " . ($email_type === 'penalty_notice' ? '#e03131' : '#fecc56') . "; padding: 18px 20px; border-radius: 6px; margin: 20px 0;'>
                <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
                    <tr>
                        <td style='padding: 6px 0; color: #666;'>Invoice Number:</td>
                        <td style='padding: 6px 0; font-weight: bold; text-align: right;'>{$inv_ref}</td>
                    </tr>
        ";

        if (!empty($inv['case_number'])) {
            $body .= "
                    <tr>
                        <td style='padding: 6px 0; color: #666;'>Case Reference:</td>
                        <td style='padding: 6px 0; font-weight: bold; text-align: right;'>" . htmlspecialchars($inv['case_number']) . "</td>
                    </tr>
            ";
        }

        $body .= "
                    <tr>
                        <td style='padding: 6px 0; color: #666;'>Original Amount:</td>
                        <td style='padding: 6px 0; font-weight: bold; text-align: right;'>{$currency} " . number_format($base_amount, 2) . "</td>
                    </tr>
        ";

        if ($late_fee > 0) {
            $body .= "
                    <tr style='color: #c92a2a;'>
                        <td style='padding: 6px 0;'>Accumulated Late Fee / Penalty:</td>
                        <td style='padding: 6px 0; font-weight: bold; text-align: right;'>+ {$currency} " . number_format($late_fee, 2) . "</td>
                    </tr>
            ";
        }

        if ($total_paid > 0) {
            $body .= "
                    <tr style='color: #2b8a3e;'>
                        <td style='padding: 6px 0;'>Payments Received:</td>
                        <td style='padding: 6px 0; font-weight: bold; text-align: right;'>- {$currency} " . number_format($total_paid, 2) . "</td>
                    </tr>
            ";
        }

        $body .= "
                    <tr style='border-top: 2px solid #dee2e6; font-size: 16px;'>
                        <td style='padding: 10px 0; font-weight: bold; color: #111;'>Total Balance Due:</td>
                        <td style='padding: 10px 0; font-weight: bold; color: #c92a2a; text-align: right;'>{$currency} " . number_format($total_due, 2) . "</td>
                    </tr>
                </table>
            </div>
        ";

        if (!empty($inv['payment_info'])) {
            $body .= "
                <div style='background: #f1f3f5; padding: 15px; border-radius: 4px; margin-bottom: 20px;'>
                    <strong style='color: #333; display: block; margin-bottom: 5px;'>Payment Instructions:</strong>
                    <pre style='margin: 0; font-family: monospace; font-size: 13px; color: #495057; white-space: pre-wrap;'>" . htmlspecialchars($inv['payment_info']) . "</pre>
                </div>
            ";
        }

        $body .= "
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$invoice_url}' style='background: linear-gradient(135deg,#fecc56,#f0a500); color: #000; text-decoration: none; padding: 14px 28px; border-radius: 5px; font-weight: bold; font-size: 15px; display: inline-block; box-shadow: 0 4px 12px rgba(254,204,86,0.4);'>
                        View & Pay Invoice Online &rarr;
                    </a>
                </div>
                <p style='font-size: 13px; color: #666;'>
                    You can submit your wire receipt or transaction hash directly through your client portal once payment is initiated.
                </p>
                <p style='font-size: 13px; color: #666;'>
                    If you have already settled this invoice, please disregard this automated notification.
                </p>
            </div>
        ";

        // Send HTML email
        $sent = false;
        if (function_exists('send_html_email')) {
            $sent = send_html_email($inv['client_email'], $subject, $body);
        } elseif (function_exists('send_notification_email')) {
            $sent = send_notification_email($inv['client_email'], $subject, $body);
        }

        if ($sent) {
            // Update last_reminder_sent timestamp
            $pdo->prepare("UPDATE IFW_invoices SET last_reminder_sent = NOW() WHERE id = ?")->execute([$inv_id]);
            
            // Create in-app portal notification for the client
            try {
                $pdo->prepare("INSERT INTO IFW_notifications (client_id, type, title, body, icon, link) VALUES (?, 'invoice', ?, ?, 'exclamation-circle', ?)")
                    ->execute([
                        $inv['client_id'],
                        $email_type === 'penalty_notice' ? 'Overdue Penalty Notice' : 'Invoice Payment Reminder',
                        "Invoice {$inv_ref} has a balance of {$currency} " . number_format($total_due, 2) . " outstanding.",
                        "/client/invoice_view.php?id={$inv_id}"
                    ]);
            } catch(Exception $nex) {}

            $reminders_sent++;
            if ($is_cli) {
                echo "[SENT] Reminder for {$inv_ref} to {$inv['client_email']} (Type: {$email_type}, Due: {$currency} " . number_format($total_due, 2) . ")\n";
            }
        } else {
            if ($is_cli) {
                echo "[FAILED] Failed to send reminder for {$inv_ref} to {$inv['client_email']}\n";
            }
        }
    }

} catch (Exception $e) {
    if ($is_cli) {
        echo "[ERROR] " . $e->getMessage() . "\n";
    }
}

if ($is_cli) {
    echo "====================================================\n";
    echo "  Processed completed. Total sent: {$reminders_sent}\n";
    echo "====================================================\n";
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'reminders_sent' => $reminders_sent, 'timestamp' => date('Y-m-d H:i:s')]);
}
