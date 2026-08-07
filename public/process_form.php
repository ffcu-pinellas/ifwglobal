<?php
// process_form.php
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Fetch form fields schema from database
    $fields = [];
    try {
        $stmt = $pdo->query("SELECT field_name, field_label, field_type, is_required FROM IFW_form_fields");
        $fields = $stmt->fetchAll();
    } catch (Exception $e) {}
    
    $submission_data = [];
    $errors = [];
    
    if (!empty($fields)) {
        foreach ($fields as $field) {
            $name = $field['field_name'];
            $val = isset($_POST[$name]) ? trim($_POST[$name]) : '';
            
            if ($field['is_required'] && empty($val)) {
                $errors[] = "The field '{$field['field_label']}' is required.";
            }
            if ($field['field_type'] == 'email' && !empty($val) && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Please enter a valid email address.";
            }
            $submission_data[$name] = $val;
        }
    } else {
        // Fallback for standard fields
        $submission_data['first_name'] = trim($_POST['first_name'] ?? $_POST['name'] ?? '');
        $submission_data['last_name'] = trim($_POST['last_name'] ?? '');
        $submission_data['email'] = trim($_POST['email'] ?? '');
        $submission_data['phone'] = trim($_POST['phone'] ?? '');
        $submission_data['message'] = trim($_POST['message'] ?? '');
        
        if (empty($submission_data['first_name'])) $errors[] = "First name is required.";
        if (empty($submission_data['email'])) $errors[] = "Email address is required.";
    }
    
    if (empty($errors)) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $location_str = 'Unknown';
        
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
        
        $submission_data['ip_address'] = $ip_address;
        $submission_data['location'] = trim($location_str, ', ');

        // Save to IFW_contact_submissions / IFW_form_submissions
        try {
            $stmt = $pdo->prepare("INSERT INTO IFW_contact_submissions (submission_data) VALUES (?)");
            $stmt->execute([json_encode($submission_data)]);
        } catch (Exception $e) {}

        // AUTO-POPULATE INTO IFW_clients FOR CLIENT DIRECTORY
        $first_name = trim($submission_data['first_name'] ?? $submission_data['name'] ?? 'Enquiry');
        $last_name = trim($submission_data['last_name'] ?? 'Lead');
        $email = trim($submission_data['email'] ?? '');
        $phone = trim($submission_data['phone'] ?? '');

        if (!empty($email)) {
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM IFW_clients WHERE email = ?");
                $checkStmt->execute([$email]);
                if (!$checkStmt->fetch()) {
                    $insertClient = $pdo->prepare("INSERT INTO IFW_clients (first_name, last_name, email, phone, status) VALUES (?, ?, ?, ?, 'Received')");
                    $insertClient->execute([$first_name, $last_name, $email, $phone]);
                }
            } catch (Exception $e) {}
        }
        
        // Fetch recipient email & send notification
        $recipient = get_setting($pdo, 'recipient_email', 'admin@ifwglobal.com');
        $email_subject = "New Recovery Enquiry: " . $first_name . " " . $last_name;
        
        $html_body = "<h2>New Case Recovery Enquiry</h2>
                      <p>You have received a new consultation submission from the IFW Global website:</p>
                      <table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
                          <tr><td style='padding: 10px; border-bottom: 1px solid #ddd; font-weight: bold;'>Name</td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($first_name . ' ' . $last_name) . "</td></tr>
                          <tr><td style='padding: 10px; border-bottom: 1px solid #ddd; font-weight: bold;'>Email</td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($email) . "</td></tr>
                          <tr><td style='padding: 10px; border-bottom: 1px solid #ddd; font-weight: bold;'>Phone</td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($phone) . "</td></tr>
                          <tr><td style='padding: 10px; border-bottom: 1px solid #ddd; font-weight: bold;'>Message</td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>" . nl2br(htmlspecialchars($submission_data['message'] ?? '')) . "</td></tr>
                          <tr><td style='padding: 10px; border-bottom: 1px solid #ddd; font-weight: bold;'>IP / Location</td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($ip_address . ' (' . $location_str . ')') . "</td></tr>
                      </table>";
        
        send_html_email($recipient, $email_subject, $html_body);

        echo json_encode(['status' => 'success', 'message' => 'Thank you for your enquiry. An assigned recovery specialist will contact you shortly.']);
        exit;
    } else {
        echo json_encode(['status' => 'error', 'errors' => $errors]);
        exit;
    }
}
