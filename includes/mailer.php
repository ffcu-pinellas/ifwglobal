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
    global $env;
    
    if (!isset($env) || !is_array($env)) {
        $envPath = __DIR__ . '/../.env';
        if (file_exists($envPath)) {
            $env = parse_ini_file($envPath);
        } else {
            $env = [];
        }
    }
    
    $app_name = $env['APP_NAME'] ?? 'IFW Global';
    $app_url = $env['APP_URL'] ?? '/';
    $current_year = date('Y');
    
    // HTML Template Wrapper with Guaranteed 100% Reliable Header
    $full_html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #0b0e14; margin: 0; padding: 20px 0; color: #333333; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 8px 25px rgba(0,0,0,0.35); border-top: 4px solid #fecc56; }
        .email-header { background-color: #111827; padding: 26px 20px; text-align: center; border-bottom: 2px solid #fecc56; }
        .email-body { padding: 35px 30px; line-height: 1.65; font-size: 15px; color: #1e293b; }
        .email-footer { background-color: #f8fafc; padding: 20px 30px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; line-height: 1.5; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #fecc56; color: #111827 !important; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
        h1, h2, h3 { color: #111827; margin-top: 0; }
        a { color: #d97706; }
    </style>
</head>
<body style="background-color: #0b0e14; margin: 0; padding: 25px 10px;">
    <div class="email-container" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 10px; overflow: hidden; border-top: 4px solid #fecc56;">
        <div class="email-header" style="background-color: #111827; padding: 26px 20px; text-align: center; border-bottom: 2px solid #fecc56;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                <tr>
                    <td style="vertical-align: middle; padding-right: 12px;">
                        <div style="width: 36px; height: 36px; background-color: #fecc56; border-radius: 6px; text-align: center; line-height: 36px; font-size: 20px; font-weight: bold; color: #111827;">🛡️</div>
                    </td>
                    <td style="vertical-align: middle; text-align: left;">
                        <div style="color: #fecc56; font-size: 22px; font-weight: 900; letter-spacing: 2px; font-family: Arial, sans-serif; text-transform: uppercase; line-height: 1.1;">IFW GLOBAL</div>
                        <div style="color: #cbd5e1; font-size: 10px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; margin-top: 2px;">Private Intelligence & Asset Recovery</div>
                    </td>
                </tr>
            </table>
        </div>
        <div class="email-body" style="padding: 35px 30px; line-height: 1.65; font-size: 15px; color: #1e293b;">
            {$body_html}
        </div>
        <div class="email-footer" style="background-color: #f8fafc; padding: 20px 30px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; line-height: 1.5;">
            <p style="margin: 0 0 5px 0;"><strong>&copy; {$current_year} {$app_name}. All rights reserved.</strong></p>
            <p style="margin: 0;">This email is confidential and intended solely for the use of the individual or entity to whom it is addressed.</p>
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
        $mail->AltBody = strip_tags($body_html);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
