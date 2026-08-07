<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
?>
<html><head>
<?php if (get_setting($pdo, 'display_phone_numbers', 'show') === 'hide'): ?>
<style>
.alert__numbers, .phones__link, .phone-number, a[href^="tel:"] { display: none !important; visibility: hidden !important; }
.footer__address, .footer__details, address, .contact-details { display: none !important; visibility: hidden !important; }
</style>
<?php endif; ?>
<style id='gdpr-global-suppress'>#gdpr-cookie-consent-bar, #gdpr-cookie-consent-show-again, #cookie_action_settings, .gdpr_action_button, .gdpr-modal, .cli-modal, #cliModal, [id*='gdpr'], [class*='gdpr-cookie'], [class*='cli-'] { display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; height: 0 !important; width: 0 !important; margin: 0 !important; padding: 0 !important; }</style>
<title>429, Rate Limited</title>
</head>
<body>
<?php if(get_setting($pdo, 'announcement_bar_active') == '1'): ?>
<div style="background-color: #fecc56; color: #000; text-align: center; padding: 12px; font-weight: bold; z-index: 9999; position: relative; border-bottom: 2px solid #e5b340;">
    <?= htmlspecialchars(get_setting($pdo, 'announcement_bar_text')) ?>
</div>
<?php endif; ?>

<?php require_once $dir . '/includes/announcement.php'; ?>
<h1>429, Rate Limited</h1>
<p>Wait a minute and try again</p>



<?php require_once $dir . '/includes/chat_widget.php'; ?>
</body></html>





