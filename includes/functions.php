<?php
// includes/functions.php
if (file_exists(__DIR__ . '/mailer.php')) {
    require_once __DIR__ . '/mailer.php';
}

/**
 * Log System & User Audit Actions
 */
function log_audit_action($pdo, $user_id, $action, $details = '', $user_type = 'client', $ip = null) {
    if (!$pdo) return false;
    if (!$ip) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
    }
    try {
        // Ensure table exists & has user_type column
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS IFW_audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                user_type VARCHAR(50) DEFAULT 'client',
                action VARCHAR(100) NOT NULL,
                details TEXT,
                ip_address VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("ALTER TABLE IFW_audit_logs ADD COLUMN IF NOT EXISTS user_type VARCHAR(50) DEFAULT 'client' AFTER user_id");
        
        $stmt = $pdo->prepare("INSERT INTO IFW_audit_logs (user_id, user_type, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        return $stmt->execute([$user_id, $user_type, $action, $details, $ip]);
    } catch (Exception $e) {
        try {
            // Fallback for older schemas without user_type
            $stmt_fb = $pdo->prepare("INSERT INTO IFW_audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
            return $stmt_fb->execute([$user_id, $action, $details, $ip]);
        } catch (Exception $ex) {
            return false;
        }
    }
}

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
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #0b0e14; margin: 0; padding: 20px 0; color: #333333; }
            .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 8px 25px rgba(0,0,0,0.35); border-top: 4px solid #fecc56; }
            .header { background-color: #111827; padding: 28px 20px; text-align: center; border-bottom: 2px solid #fecc56; }
            .content { padding: 35px 30px; line-height: 1.65; font-size: 14.5px; color: #1e293b; }
            .footer { background-color: #f8fafc; padding: 20px 30px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; line-height: 1.5; }
        </style>
    </head>
    <body style='background-color: #0b0e14; margin: 0; padding: 25px 10px;'>
        <div class='container' style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 10px; overflow: hidden; border-top: 4px solid #fecc56;'>
            <div class='header' style='background-color: #111827; padding: 26px 20px; text-align: center; border-bottom: 2px solid #fecc56;'>
                <table align='center' border='0' cellpadding='0' cellspacing='0' style='margin: 0 auto;'>
                    <tr>
                        <td style='vertical-align: middle; padding-right: 12px;'>
                            <div style='width: 36px; height: 36px; background-color: #fecc56; border-radius: 6px; text-align: center; line-height: 36px; font-size: 20px; font-weight: bold; color: #111827;'>🛡️</div>
                        </td>
                        <td style='vertical-align: middle; text-align: left;'>
                            <div style='color: #fecc56; font-size: 22px; font-weight: 900; letter-spacing: 2px; font-family: Arial, sans-serif; text-transform: uppercase; line-height: 1.1;'>IFW GLOBAL</div>
                            <div style='color: #cbd5e1; font-size: 10px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; margin-top: 2px;'>Private Intelligence & Asset Recovery</div>
                        </td>
                    </tr>
                </table>
            </div>
            <div class='content' style='padding: 35px 30px; line-height: 1.65; font-size: 14.5px; color: #1e293b;'>
                $body
            </div>
            <div class='footer' style='background-color: #f8fafc; padding: 20px 30px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; line-height: 1.5;'>
                <strong>IFW Global Cyber & Financial Crime Investigation Division</strong><br>
                This is an automated encrypted dispatch from the IFW Global Client Recovery Portal.
            </div>
        </div>
    </body>
    </html>
    ";
    
    return @mail($to, $subject, $html_body, $headers);
}

/**
 * Send an official case milestone update email to client
 */
function send_case_milestone_email($pdo, $client_email, $client_name, $case_number, $case_title, $milestone_title, $milestone_body, $milestone_date, $case_id) {
    if (empty($client_email)) return false;
    
    $app_name = get_setting($pdo, 'app_name', 'IFW Global');
    $subject = "Case Update: {$milestone_title} — Case #{$case_number}";
    $date_formatted = date('F j, Y', strtotime($milestone_date ?: date('Y-m-d')));
    $case_url = (defined('BASE_URL') ? BASE_URL : '') . "/client/my_cases.php?case_id=" . $case_id;

    $body_content = "
        <p>Dear <strong>" . htmlspecialchars($client_name) . "</strong>,</p>
        <p>A new official investigation milestone has been logged for your active recovery case.</p>
        
        <div style='background: #191e2b; border: 1px solid #fecc56; border-left: 5px solid #fecc56; border-radius: 6px; padding: 20px; margin: 20px 0; color: #ffffff;'>
            <div style='font-size: 11px; text-transform: uppercase; color: #fecc56; font-weight: bold; margin-bottom: 5px;'>CASE REFERENCE</div>
            <div style='font-size: 16px; font-weight: bold; margin-bottom: 12px; color: #ffffff;'>#" . htmlspecialchars($case_number) . " — " . htmlspecialchars($case_title) . "</div>
            
            <div style='font-size: 11px; text-transform: uppercase; color: #fecc56; font-weight: bold; margin-bottom: 5px;'>MILESTONE POSTED (" . $date_formatted . ")</div>
            <div style='font-size: 15px; font-weight: bold; color: #ffffff; margin-bottom: 10px;'>" . htmlspecialchars($milestone_title) . "</div>
            " . (!empty($milestone_body) ? "<div style='font-size: 13.5px; color: #cbd5e1; line-height: 1.6; white-space: pre-wrap; background: #0f131c; padding: 12px; border-radius: 4px;'>" . nl2br(htmlspecialchars($milestone_body)) . "</div>" : "") . "
        </div>

        <p style='color: #475569; font-size: 13px;'>You can review the full dossier, cryptographic evidence vault, and case timeline anytime directly in your portal.</p>
        
        <div style='text-align: center; margin-top: 25px;'>
            <a href='" . htmlspecialchars($case_url) . "' style='display: inline-block; background-color: #fecc56; color: #000000; padding: 14px 28px; font-size: 14px; font-weight: bold; text-decoration: none; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;'>View Case Update & Dossier &rarr;</a>
        </div>
    ";

    return send_notification_email($client_email, $subject, $body_content);
}

/**
 * Automatically trigger background tasks (reminders, SLA monitors) throttled to run every 4 hours max
 */
function trigger_background_cron_tasks($pdo) {
    if (!$pdo) return;
    try {
        $last_run = (int)get_setting($pdo, 'last_cron_reminders_ts', '0');
        if ((time() - $last_run) >= 14400) { // 4 hours
            set_setting($pdo, 'last_cron_reminders_ts', (string)time());
            $cron_file = __DIR__ . '/../cron_invoice_reminders.php';
            if (file_exists($cron_file)) {
                // If possible run in background, otherwise include
                if (function_exists('exec') && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                    @exec("php " . escapeshellarg($cron_file) . " > /dev/null 2>&1 &");
                } else {
                    // Safe silent inclusion
                    include_once $cron_file;
                }
            }
        }
    } catch (Exception $e) {}
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

/**
 * Get accurate client IP address
 */
function get_client_ip_address() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    }
    return trim($ip);
}

/**
 * Parse User Agent into Device Type, Browser, and OS
 */
function parse_device_user_agent($ua = null) {
    if (!$ua) {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device';
    }
    
    // Device Type
    $device = 'Desktop PC';
    if (preg_match('/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', $ua)) {
        $device = 'Tablet';
    } elseif (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile|iphone|ipod)/i', $ua)) {
        $device = 'Mobile Device';
    }
    
    // OS
    $os = 'Unknown OS';
    if (preg_match('/windows nt 10/i', $ua))     $os = 'Windows 10 / 11';
    elseif (preg_match('/windows nt 6.3/i', $ua)) $os = 'Windows 8.1';
    elseif (preg_match('/windows nt 6.2/i', $ua)) $os = 'Windows 8';
    elseif (preg_match('/windows nt 6.1/i', $ua)) $os = 'Windows 7';
    elseif (preg_match('/macintosh|mac os x/i', $ua)) $os = 'macOS';
    elseif (preg_match('/iphone/i', $ua))         $os = 'iOS (iPhone)';
    elseif (preg_match('/ipad/i', $ua))           $os = 'iPadOS';
    elseif (preg_match('/android/i', $ua))        $os = 'Android OS';
    elseif (preg_match('/linux/i', $ua))          $os = 'Linux';
    
    // Browser
    $browser = 'Unknown Browser';
    if (preg_match('/edg/i', $ua))               $browser = 'Microsoft Edge';
    elseif (preg_match('/chrome/i', $ua))        $browser = 'Google Chrome';
    elseif (preg_match('/firefox/i', $ua))       $browser = 'Mozilla Firefox';
    elseif (preg_match('/safari/i', $ua))        $browser = 'Apple Safari';
    elseif (preg_match('/opera|opr/i', $ua))     $browser = 'Opera';
    elseif (preg_match('/msie|trident/i', $ua))  $browser = 'Internet Explorer';
    
    return [
        'device' => $device,
        'os' => $os,
        'browser' => $browser,
        'raw_ua' => $ua
    ];
}

/**
 * Log user login and trigger instant security email if signing in from a new/unrecognized IP or device
 */
function log_user_login($pdo, $user_id, $role, $email, $status = 'success') {
    if (!$pdo || empty($user_id) || empty($email)) return false;
    
    $ip = get_client_ip_address();
    $ua_info = parse_device_user_agent();
    $device_type = $ua_info['device'];
    $browser = $ua_info['browser'];
    $os = $ua_info['os'];
    $raw_ua = $ua_info['raw_ua'];
    
    // Check if this IP/Device has signed in within past 30 days
    $is_new_device = 0;
    try {
        $stmt_chk = $pdo->prepare("SELECT id FROM IFW_login_history WHERE user_id = ? AND role = ? AND (ip_address = ? OR (browser = ? AND os = ?)) AND login_status = 'success' LIMIT 1");
        $stmt_chk->execute([$user_id, $role, $ip, $browser, $os]);
        $existing_login = $stmt_chk->fetch();
        
        if (!$existing_login && $status === 'success') {
            $is_new_device = 1;
        }
        
        // Approximate location lookup or fallback
        $location = 'Encrypted TLS Endpoint';
        if ($ip !== '127.0.0.1' && $ip !== '::1') {
            // Optional GeoIP via non-blocking lightweight lookup
            $location = 'Authorized Network (' . $ip . ')';
        }
        
        // Insert record
        $stmt_ins = $pdo->prepare("INSERT INTO IFW_login_history (user_id, role, email, ip_address, user_agent, device_type, browser, os, city_country, is_new_device, login_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt_ins->execute([$user_id, $role, $email, $ip, $raw_ua, $device_type, $browser, $os, $location, $is_new_device, $status]);
        
        // Also mirror to IFW_audit_logs
        $audit_action = ($status === 'success') ? 'PORTAL_LOGIN' : 'LOGIN_FAILED';
        $audit_desc = "Authentication from {$device_type} ({$browser} on {$os})" . ($is_new_device ? ' [New Device]' : '');
        log_audit_action($pdo, $user_id, $audit_action, $audit_desc, $role, $ip);

        // If it's a new device/IP and successful login, dispatch instant security alert email!
        if ($is_new_device && $status === 'success') {
            send_security_login_alert($pdo, $email, $user_id, $role, [
                'ip' => $ip,
                'device' => $device_type,
                'browser' => $browser,
                'os' => $os,
                'location' => $location,
                'time' => date('F j, Y, g:i a') . ' UTC'
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Dispatch an Instant Branded Security Alert Email for New Device / IP Sign-In
 */
function send_security_login_alert($pdo, $email, $user_id, $role, $info) {
    $app_name = get_setting($pdo, 'company_name', 'IFW Global Intelligence');
    $user_name = 'Valued Client';
    
    if ($role === 'client') {
        $st = $pdo->prepare("SELECT first_name, last_name FROM IFW_clients WHERE id = ?");
        $st->execute([$user_id]);
        if ($c = $st->fetch()) {
            $user_name = $c['first_name'] . ' ' . $c['last_name'];
        }
    } else {
        $st = $pdo->prepare("SELECT username FROM IFW_users WHERE id = ?");
        $st->execute([$user_id]);
        if ($u = $st->fetch()) {
            $user_name = $u['username'];
        }
    }
    
    $subject = "🛡️ Security Alert: New Sign-In Detected on Your {$app_name} Workspace";
    
    $html = "
    <div style='background:#0d1117; color:#f0f6fc; font-family:Montserrat,-apple-system,BlinkMacSystemFont,sans-serif; max-width:600px; margin:0 auto; border-radius:12px; border:1px solid #30363d; overflow:hidden;'>
        <div style='background:#161b22; padding:24px; text-align:center; border-bottom:2px solid #fecc56;'>
            <h2 style='margin:0; color:#fecc56; font-size:20px; font-weight:700; letter-spacing:1px;'>IFW GLOBAL SECURITY DESK</h2>
            <div style='color:#8b949e; font-size:12px; margin-top:4px;'>Automated Device & Session Authentication Monitor</div>
        </div>
        <div style='padding:28px 24px;'>
            <p style='font-size:15px; color:#f0f6fc; margin-top:0;'>Hello <strong>" . htmlspecialchars($user_name) . "</strong>,</p>
            <p style='color:#8b949e; font-size:13.5px; line-height:1.6;'>A new sign-in was just verified on your <strong>" . htmlspecialchars($app_name) . "</strong> workspace from an unrecognized device or IP address.</p>
            
            <div style='background:#161b22; border:1px solid #30363d; border-radius:8px; padding:16px 20px; margin:20px 0;'>
                <table style='width:100%; font-size:13px; color:#f0f6fc;'>
                    <tr>
                        <td style='color:#8b949e; padding:6px 0; width:35%;'>Device:</td>
                        <td style='font-weight:600; padding:6px 0;'>" . htmlspecialchars($info['device']) . "</td>
                    </tr>
                    <tr>
                        <td style='color:#8b949e; padding:6px 0;'>Browser & OS:</td>
                        <td style='font-weight:600; padding:6px 0;'>" . htmlspecialchars($info['browser'] . ' (' . $info['os'] . ')') . "</td>
                    </tr>
                    <tr>
                        <td style='color:#8b949e; padding:6px 0;'>IP Address:</td>
                        <td style='font-weight:600; padding:6px 0; color:#fecc56; font-family:monospace;'>" . htmlspecialchars($info['ip']) . "</td>
                    </tr>
                    <tr>
                        <td style='color:#8b949e; padding:6px 0;'>Timestamp:</td>
                        <td style='font-weight:600; padding:6px 0;'>" . htmlspecialchars($info['time']) . "</td>
                    </tr>
                </table>
            </div>
            
            <div style='background:rgba(254,204,86,0.1); border-left:4px solid #fecc56; padding:12px 16px; border-radius:4px; font-size:12.5px; color:#e6edf3; margin-bottom:24px;'>
                <strong>Was this you?</strong> If you recently logged in from this device or network, you can safely disregard this alert.
            </div>
            
            <p style='color:#f85149; font-size:13px; font-weight:600; line-height:1.5; margin-bottom:16px;'>
                ⚠️ Did not recognize this activity? Please immediately log into your workspace and change your password & 4-digit Security PIN to lock unauthorized sessions.
            </p>
        </div>
        <div style='background:#161b22; padding:16px 24px; text-align:center; border-top:1px solid #30363d; font-size:11.5px; color:#8b949e;'>
            &copy; " . date('Y') . " " . htmlspecialchars($app_name) . " &bull; Cyber & Financial Forensics Division
        </div>
    </div>";
    
    // Send email using system mailer
    if (function_exists('send_html_email')) {
        @send_html_email($email, $subject, $html);
    } elseif (function_exists('send_notification_email')) {
        @send_notification_email($pdo, $email, $subject, $html);
    }
}
?>




