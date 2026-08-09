<?php
// includes/functions.php

/**
 * Fetch a site setting by key
 *
 * @param PDO $pdo
 * @param string $key
 * @param string $default
 * @return string
 */
function get_setting($pdo, $key, $default = '') {
    $stmt = $pdo->prepare("SELECT setting_value FROM IFW_site_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    if ($row = $stmt->fetch()) {
        return $row['setting_value'];
    }
    return $default;
}

/**
 * Update or insert a site setting
 *
 * @param PDO $pdo
 * @param string $key
 * @param string $value
 * @return bool
 */
function set_setting($pdo, $key, $value) {
    $stmt = $pdo->prepare("INSERT INTO IFW_site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    return $stmt->execute([$key, $value]);
}

/**
 * Render dynamic form fields based on the database schema
 *
 * @param PDO $pdo
 * @return string HTML for the form fields
 */
function render_dynamic_form($pdo) {
    $stmt = $pdo->query("SELECT * FROM IFW_form_fields ORDER BY display_order ASC");
    $fields = $stmt->fetchAll();
    
    $html = '<div class="gform_wrapper gravity-theme"><div class="gform_body"><ul class="gform_fields top_label form_sublabel_below description_below">';
    foreach ($fields as $field) {
        $required = $field['is_required'] ? 'required' : '';
        $asterisk = $field['is_required'] ? '<span class="gfield_required">*</span>' : '';
        $name = htmlspecialchars($field['field_name']);
        $label = htmlspecialchars($field['field_label']);
        
        $html .= '<li class="gfield field_sublabel_below field_description_below gfield_visibility_visible">';
        $html .= '<label class="gfield_label" for="' . $name . '">' . $label . ' ' . $asterisk . '</label>';
        $html .= '<div class="ginput_container ginput_container_' . htmlspecialchars($field['field_type']) . '">';
        
        switch ($field['field_type']) {
            case 'text':
            case 'email':
            case 'tel':
                $html .= '<input type="' . htmlspecialchars($field['field_type']) . '" class="large" id="' . $name . '" name="' . $name . '" placeholder="' . $label . '" ' . $required . '>';
                break;
            case 'textarea':
                $html .= '<textarea class="textarea large" id="' . $name . '" name="' . $name . '" rows="4" placeholder="' . $label . '" ' . $required . '></textarea>';
                break;
            case 'select':
                $html .= '<select class="large gfield_select" id="' . $name . '" name="' . $name . '" ' . $required . '>';
                $article = in_array(strtolower($label[0] ?? ''), ['a', 'e', 'i', 'o', 'u']) ? 'an' : 'a';
                $html .= '<option value="">Select ' . $article . ' ' . $label . '</option>';
                $options = json_decode($field['field_options'], true);
                if (!is_array($options) && !empty($field['field_options'])) {
                    $options = explode(',', $field['field_options']);
                }
                if (is_array($options)) {
                    foreach ($options as $opt) {
                        $opt = trim($opt);
                        $html .= '<option value="' . htmlspecialchars($opt) . '">' . htmlspecialchars($opt) . '</option>';
                    }
                }
                $html .= '</select>';
                break;
            case 'checkbox':
                $html .= '<ul class="gfield_checkbox" id="' . $name . '">';
                $html .= '<li class="gchoice"><input type="checkbox" id="choice_' . $name . '" name="' . $name . '" value="1" ' . $required . '>';
                $html .= '<label for="choice_' . $name . '">Yes</label></li>';
                $html .= '</ul>';
                break;
        }
        $html .= '</div></li>';
    }
    $html .= '</ul></div></div>';
    
    return $html;
}

/**
 * Check if the user is logged in as admin
 *
 * @return void
 */
function require_admin_login() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: " . BASE_URL . "/admin/login.php");
        exit;
    }
}

/**
 * Check if the user is a full admin (not just an agent)
 */
function require_superadmin() {
    require_admin_login();
    $role = $_SESSION['admin_role'] ?? 'admin';
    // Instead of hardcoding 'admin', we can check if they have the 'manage_settings' permission
    // For backward compatibility, we also allow the 'admin' role directly.
    if ($role !== 'admin' && !has_permission('manage_settings')) {
        die("Unauthorized access. You do not have permission to view this page.");
    }
}

/**
 * Check if the currently logged in admin has a specific permission
 */
function has_permission($permission_name) {
    global $pdo;
    if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) return false;
    
    $role = $_SESSION['admin_role'] ?? '';
    if ($role === 'admin') return true; // Admins have all permissions
    
    try {
        // 1. Check user-specific overrides
        $stmt_override = $pdo->prepare("
            SELECT up.is_granted 
            FROM IFW_permissions p
            JOIN IFW_user_permissions up ON p.id = up.permission_id
            WHERE up.user_id = ? AND p.name = ?
        ");
        $stmt_override->execute([$_SESSION['admin_id'], $permission_name]);
        $override = $stmt_override->fetchColumn();
        
        if ($override !== false) {
            return (bool)$override; // Explicitly granted or denied
        }

        // 2. Fallback to role permissions
        $stmt = $pdo->prepare("
            SELECT p.id 
            FROM IFW_permissions p
            JOIN IFW_role_permissions rp ON p.id = rp.permission_id
            JOIN IFW_roles r ON rp.role_id = r.id
            WHERE r.name = ? AND p.name = ?
        ");
        $stmt->execute([$role, $permission_name]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Require a specific permission, otherwise die.
 */
function require_permission($permission_name) {
    if (!has_permission($permission_name)) {
        die("Unauthorized access. You need the '$permission_name' permission to view this page.");
    }
}

/**
 * Log an action to the audit logs
 *
 * @param PDO $pdo
 * @param int|null $user_id (Admin or Agent ID)
 * @param string $action Short action name
 * @param string $details Detailed description
 */
}

/**
 * Send an email notification wrapped in a premium HTML template
 *
 * @param string $to Email address
 * @param string $subject Subject line
 * @param string $body Body text (or HTML)
 * @return bool
 */
function send_notification_email($to, $subject, $body) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: IFW Global Support <noreply@ifwglobal.com>\r\n";
    $headers .= "Reply-To: noreply@ifwglobal.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    $html_body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; color: #333333; }
            .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border-top: 5px solid #fecc56; }
            .header { background-color: #1f1b1c; padding: 20px; text-align: center; }
            .header h1 { color: #fecc56; margin: 0; font-size: 24px; }
            .content { padding: 30px; line-height: 1.6; }
            .button { display: inline-block; padding: 12px 24px; margin-top: 20px; background-color: #fecc56; color: #000000; text-decoration: none; border-radius: 4px; font-weight: bold; }
            .footer { background-color: #f4f4f4; padding: 15px; text-align: center; font-size: 12px; color: #777777; border-top: 1px solid #e0e0e0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>IFW Global</h1>
            </div>
            <div class='content'>
                $body
            </div>
            <div class='footer'>
                This is an automated notification from the IFW Global Secure Recovery Portal.
            </div>
        </div>
    </body>
    </html>
    ";
    
    return @mail($to, $subject, $html_body, $headers);
}

/**
 * Send a notification message to Telegram using Bot API configured in settings
 */
function send_telegram_notification($pdo, $message) {
    $token = get_setting($pdo, 'telegram_bot_token', '');
    $chat_id = get_setting($pdo, 'telegram_chat_id', '');
    if (empty($token) || empty($chat_id)) {
        return false;
    }
    
    $url = "https://api.telegram.org/bot" . $token . "/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];
    
    $context  = stream_context_create($options);
    try {
        $result = @file_get_contents($url, false, $context);
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}
?>




