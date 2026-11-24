<?php
// includes/mailer.php

// Include Composer autoloader for PHPMailer if not already included
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('ifw_mailer_env')) {
    function ifw_mailer_env($key, $default = null) {
        if (function_exists('env_val')) {
            return env_val($key, $default);
        }
        static $iniCache = null;
        if ($iniCache === null) {
            $iniCache = [];
            foreach ([__DIR__ . '/../.env', __DIR__ . '/../../.env'] as $envPath) {
                if (is_file($envPath) && is_readable($envPath)) {
                    $parsed = @parse_ini_file($envPath, false, INI_SCANNER_RAW);
                    if (is_array($parsed)) {
                        foreach ($parsed as $k => $v) {
                            $v = trim((string)$v);
                            if (strlen($v) >= 2 && (($v[0] === '"' && $v[strlen($v) - 1] === '"') || ($v[0] === "'" && $v[strlen($v) - 1] === "'"))) {
                                $v = substr($v, 1, -1);
                            }
                            $iniCache[$k] = $v;
                        }
                    }
                    break;
                }
            }
        }
        if (array_key_exists($key, $iniCache)) {
            return $iniCache[$key];
        }
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return $val;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        return $default;
    }
}

if (!function_exists('ifw_send_https_mail_fallback')) {
    /**
     * Optional HTTPS email API fallback (Resend or Brevo) for container environments
     * such as Render Free Tier where outbound SMTP ports may be blocked or time out.
     */
    function ifw_send_https_mail_fallback($to, $subject, $htmlBody, $textBody, $fromAddress, $fromName) {
        if (!function_exists('curl_init')) {
            return false;
        }

        $resendKey = trim((string)ifw_mailer_env('RESEND_API_KEY', ''));
        if ($resendKey !== '') {
            $payload = json_encode([
                'from'    => $fromName . ' <' . $fromAddress . '>',
                'to'      => [$to],
                'subject' => $subject,
                'html'    => $htmlBody,
                'text'    => $textBody,
            ]);
            $ch = curl_init('https://api.resend.com/emails');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $resendKey,
                    'Content-Type: application/json',
                ],
            ]);
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code >= 200 && $code < 300 && $resp !== false) {
                return true;
            }
            error_log("Resend API fallback failed (HTTP $code): " . (string)$resp);
        }

        $brevoKey = trim((string)ifw_mailer_env('BREVO_API_KEY', ''));
        if ($brevoKey !== '') {
            $payload = json_encode([
                'sender'      => ['name' => $fromName, 'email' => $fromAddress],
                'to'          => [['email' => $to]],
                'subject'     => $subject,
                'htmlContent' => $htmlBody,
                'textContent' => $textBody,
            ]);
            $ch = curl_init('https://api.brevo.com/v3/smtp/email');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_HTTPHEADER     => [
                    'api-key: ' . $brevoKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
            ]);
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code >= 200 && $code < 300 && $resp !== false) {
                return true;
            }
            error_log("Brevo API fallback failed (HTTP $code): " . (string)$resp);
        }

        return false;
    }
}

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
    if ((!isset($env) || !is_array($env)) && function_exists('ifw_load_env_array')) {
        $env = ifw_load_env_array();
    }

    $app_name = (string)ifw_mailer_env('APP_NAME', 'IFW Global');
    $fallbackBase = defined('BASE_URL') ? BASE_URL : '/';
    $app_url = rtrim((string)ifw_mailer_env('APP_URL', $fallbackBase), '/');
    $current_year = date('Y');
    $logo_url = $app_url . '/media/logos/logo.svg';
    
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
            <img src="{$logo_url}" alt="IFW Global" width="180" style="max-height: 52px; width: auto; height: auto; display: block; margin: 0 auto 8px auto;" />
            <div style="color: #cbd5e1; font-size: 10px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase;">Private Intelligence &amp; Asset Recovery</div>
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

    $mailHost   = trim((string)ifw_mailer_env('MAIL_HOST', ''));
    $mailUser   = (string)ifw_mailer_env('MAIL_USERNAME', '');
    $mailPass   = (string)ifw_mailer_env('MAIL_PASSWORD', '');
    $mailPort   = (int)ifw_mailer_env('MAIL_PORT', 465);
    $mailEnc    = strtolower(trim((string)ifw_mailer_env('MAIL_ENCRYPTION', '')));
    $fromEnv    = trim((string)ifw_mailer_env('MAIL_FROM_ADDRESS', ''));
    $fromNameEnv = trim((string)ifw_mailer_env('MAIL_FROM_NAME', ''));

    $parsed_host = parse_url($app_url, PHP_URL_HOST);
    if ($fromEnv !== '') {
        $fromAddress = $fromEnv;
    } elseif (!empty($parsed_host) && filter_var('no-reply@' . $parsed_host, FILTER_VALIDATE_EMAIL)) {
        $fromAddress = 'no-reply@' . $parsed_host;
    } else {
        $fromAddress = 'no-reply@ifwglobalrecovery.site';
    }
    $fromName = $fromNameEnv !== '' ? $fromNameEnv : $app_name;
    $altBody = strip_tags($body_html);

    $hasHttpsApi = trim((string)ifw_mailer_env('RESEND_API_KEY', '')) !== ''
        || trim((string)ifw_mailer_env('BREVO_API_KEY', '')) !== '';

    if ($mailHost === '' && $hasHttpsApi) {
        return ifw_send_https_mail_fallback($to, $subject, $full_html, $altBody, $fromAddress, $fromName);
    }

    $mail = new PHPMailer(true);

    try {
        if ($mailHost !== '') {
            $mail->isSMTP();
            $mail->Host       = $mailHost;
            $mail->SMTPAuth   = ($mailUser !== '' || $mailPass !== '');
            $mail->Username   = $mailUser;
            $mail->Password   = $mailPass;
            $mail->SMTPSecure = ($mailEnc === 'ssl' || $mailPort === 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $mailPort ?: 465;
            $mail->Timeout    = 5;
            $mail->getSMTPInstance()->Timelimit = 5;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
        }

        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $full_html;
        $mail->AltBody = $altBody;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent via PHPMailer. Mailer Error: {$mail->ErrorInfo}");
        if ($hasHttpsApi) {
            return ifw_send_https_mail_fallback($to, $subject, $full_html, $altBody, $fromAddress, $fromName);
        }
        return false;
    }
}
