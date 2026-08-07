<?php
// includes/mailer.php

// Include Composer autoloader for PHPMailer if not already included
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send a professionally styled HTML email
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body_html The inner HTML content of the email
 * @return bool True on success, false on failure
 */
function send_html_email($to, $subject, $body_html) {
    global $env; // Usually parsed in config.php
    
    if (!isset($env) || !is_array($env)) {
        // Fallback to parse .env if global is missing
        $envPath = __DIR__ . '/../.env';
        if (file_exists($envPath)) {
            $env = parse_ini_file($envPath);
        } else {
            $env = [];
        }
    }
    
    $app_name = $env['APP_NAME'] ?? 'IFW Global';
    $app_url = $env['APP_URL'] ?? '/';
    $logo_url = rtrim($app_url, '/') . '/media/gallery/IFW-Podcast-Screen.jpg';
    $current_year = date('Y');
    
    // HTML Template Wrapper
    $full_html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<style id='gdpr-global-suppress'>#gdpr-cookie-consent-bar, #gdpr-cookie-consent-show-again, #cookie_action_settings, .gdpr_action_button, .gdpr-modal, .cli-modal, #cliModal, [id*='gdpr'], [class*='gdpr-cookie'], [class*='cli-'] { display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; height: 0 !important; width: 0 !important; margin: 0 !important; padding: 0 !important; }</style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; color: #333333; }
        .email-container { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border: 1px solid #e0e0e0; }
        .email-header { background-color: #0b2e59; padding: 30px; text-align: center; }
        .email-header img { max-width: 200px; height: auto; }
        .email-body { padding: 40px 30px; line-height: 1.6; font-size: 16px; }
        .email-footer { background-color: #f9f9f9; padding: 20px 30px; text-align: center; font-size: 12px; color: #777777; border-top: 1px solid #eeeeee; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #0b2e59; color: #ffffff !important; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 20px; }
        h1, h2, h3 { color: #0b2e59; margin-top: 0; }
        a { color: #0056b3; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <img src="{$logo_url}" alt="{$app_name} Logo">
        </div>
        <div class="email-body">
            {$body_html}
        </div>
        <div class="email-footer">
            <p>&copy; {$current_year} {$app_name}. All rights reserved.</p>
            <p>This email is confidential and intended solely for the use of the individual to whom it is addressed. If you have received this email in error, please notify the sender immediately.</p>
        </div>
    </div>
</body>
</html>
HTML;

    $mail = new PHPMailer(true);
    
    try {
        if (!empty($env['MAIL_HOST'])) {
            $mail->isSMTP();
            $mail->Host       = $env['MAIL_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $env['MAIL_USERNAME'];
            $mail->Password   = $env['MAIL_PASSWORD'];
            $mail->SMTPSecure = $env['MAIL_ENCRYPTION'] == 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $env['MAIL_PORT'];
        }
        
        $fromAddress = !empty($env['MAIL_FROM_ADDRESS']) ? $env['MAIL_FROM_ADDRESS'] : 'no-reply@' . parse_url($app_url, PHP_URL_HOST);
        $fromName = !empty($env['MAIL_FROM_NAME']) ? $env['MAIL_FROM_NAME'] : $app_name;
        
        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($to);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $full_html;
        
        // Plain text fallback
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $body_html));
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("HTML Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        
        // Fallback to basic PHP mail if SMTP fails
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: {$fromName} <{$fromAddress}>\r\n";
        
        return @mail($to, $subject, $full_html, $headers);
    }
}
?>





