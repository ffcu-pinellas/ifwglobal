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
function log_audit_action($pdo, $user_id, $action, $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $stmt = $pdo->prepare("INSERT INTO IFW_audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $details, $ip]);
}
?>




