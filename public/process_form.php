<?php
// process_form.php
require_once 'config.php';
require_once 'includes/functions.php';

// Include mailer
require_once 'includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Fetch form fields schema from database to validate input
    $stmt = $pdo->query("SELECT field_name, field_label, field_type, is_required FROM IFW_form_fields");
    $fields = $stmt->fetchAll();
    
    $submission_data = [];
    $errors = [];
    
    foreach ($fields as $field) {
        $name = $field['field_name'];
        $val = isset($_POST[$name]) ? trim($_POST[$name]) : '';
        
        // Validation
        if ($field['is_required'] && empty($val)) {
            $errors[] = "The field '{$field['field_label']}' is required.";
        }
        
        if ($field['field_type'] == 'email' && !empty($val) && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email for '{$field['field_label']}'.";
        }
        
        $submission_data[$name] = $val;
    }
    
    // If no errors, process submission
    if (empty($errors)) {
        // Fetch IP and Location
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $location_str = 'Unknown';
        
        // Simple cURL to ip-api.com
        if ($ip_address !== 'Unknown' && $ip_address !== '::1' && $ip_address !== '127.0.0.1') {
            $ch = curl_init("http://ip-api.com/json/{$ip_address}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            $ip_data = json_decode(curl_exec($ch), true);
            curl_close($ch);
            
            if (isset($ip_data['status']) && $ip_data['status'] === 'success') {
                $location_str = $ip_data['city'] . ', ' . ($ip_data['regionName'] ?? '') . ', ' . $ip_data['country'];
            }
        }
        
        // Append location info to submission data
        $submission_data['ip_address'] = $ip_address;
        $submission_data['location'] = trim($location_str, ', ');

        // 3. Save to IFW_contact_submissions
        try {
            $stmt = $pdo->prepare("INSERT INTO IFW_contact_submissions (submission_data) VALUES (?)");
            $stmt->execute([json_encode($submission_data)]);
        } catch (Exception $e) { /* table may not exist yet */ }
        
        // 4. Also auto-create / update IFW_clients record so submission appears in Client Manager
        try {
            $email = $submission_data['email'] ?? '';
            if (!empty($email)) {
                // Check if client already exists by email
                $check = $pdo->prepare("SELECT id FROM IFW_clients WHERE email = ?");
                $check->execute([$email]);
                $existing_client = $check->fetch();
                
                if (!$existing_client) {
                    // Extract name parts - support both combined name and first/last fields
                    $full_name = $submission_data['name'] ?? (($submission_data['first_name'] ?? '') . ' ' . ($submission_data['last_name'] ?? ''));
                    $name_parts = explode(' ', trim($full_name), 2);
                    $first_name = $name_parts[0] ?? 'Unknown';
                    $last_name = $name_parts[1] ?? '';
                    $phone = $submission_data['phone'] ?? $submission_data['phone_number'] ?? '';
                    
                    $ins = $pdo->prepare("INSERT INTO IFW_clients (first_name, last_name, email, phone, status, notes) VALUES (?, ?, ?, ?, 'Received', ?)");
                    $notes = "Auto-created from consultation form submission. Enquiry type: " . ($submission_data['enquiry_type'] ?? $submission_data['service_type'] ?? 'General');
                    $ins->execute([$first_name, $last_name, $email, $phone, $notes]);
                }
            }
        } catch (Exception $e) { /* column may vary */ }

        // Fetch recipient email
        $recipient = get_setting($pdo, 'recipient_email', 'admin@ifwglobal.com');
        $success_msg = get_setting($pdo, 'success_message', 'Thank you!');
        
        // Build HTML Email Content
        $email_subject = "New Contact Form Submission: " . (isset($submission_data['name']) ? $submission_data['name'] : ($submission_data['first_name'] ?? 'Website'));
        $html_body = "<h2>New Form Submission</h2>
                      <p>You have received a new submission from your website:</p>
                      <table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>";
                      
        foreach ($fields as $field) {
            $html_body .= "<tr>
                              <td style='padding: 10px; border-bottom: 1px solid #ddd; font-weight: bold; width: 30%;'>{$field['field_label']}</td>
                              <td style='padding: 10px; border-bottom: 1px solid #ddd;'>" . nl2br(htmlspecialchars($submission_data[$field['field_name']])) . "</td>
                           </tr>";
        }
        
        $html_body .= "<tr>
                          <td style='padding: 10px; border-bottom: 1px solid #ddd; font-weight: bold;'>IP Address</td>
                          <td style='padding: 10px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($submission_data['ip_address']) . "</td>
                       </tr>
                       <tr>
                          <td style='padding: 10px; border-bottom: 1px solid #ddd; font-weight: bold;'>Location</td>
                          <td style='padding: 10px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($submission_data['location']) . "</td>
                       </tr>
                       </table>";
        
        // Send Email via new Mailer
        send_html_email($recipient, $email_subject, $html_body);

        // Send Telegram Notification
        $tgToken = $env['TELEGRAM_BOT_TOKEN'] ?? '';
        $tgChatId = $env['TELEGRAM_CHAT_ID'] ?? '';
        if (!empty($tgToken) && !empty($tgChatId) && $tgToken !== 'your_telegram_bot_token_here') {
            $tgMessage = "🚨 *New Form Submission*\n\n";
            foreach ($fields as $field) {
                $tgMessage .= "*" . $field['field_label'] . ":* " . $submission_data[$field['field_name']] . "\n";
            }
            $tgMessage .= "\n📍 *Location:* " . $submission_data['location'];
            $tgMessage .= "\n🌐 *IP Address:* " . $submission_data['ip_address'];
            
            $tgUrl = "https://api.telegram.org/bot{$tgToken}/sendMessage";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $tgUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'chat_id' => $tgChatId,
                'text' => $tgMessage,
                'parse_mode' => 'Markdown'
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            curl_close($ch);
        }
        
        $ref_id = 'IFW-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        
        // Return JSON response for AJAX submission
        echo json_encode([
            'status' => 'success', 
            'message' => $success_msg,
            'ref_id' => $ref_id,
            'client_email' => $submission_data['email'] ?? '',
            'contact_email_primary' => 'notifications@ifwglobalrecovery.site',
            'contact_email_secondary' => 'investigations@ifwglobalrecovery.site'
        ]);
        exit;
    } else {
        // Return errors
        echo json_encode(['status' => 'error', 'errors' => $errors]);
        exit;
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
}
?>




