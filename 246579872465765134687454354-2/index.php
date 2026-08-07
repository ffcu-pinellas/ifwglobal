<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
?>
<html lang="en"><head>
<style id='gdpr-global-suppress'>#gdpr-cookie-consent-bar, #gdpr-cookie-consent-show-again, #cookie_action_settings, .gdpr_action_button, .gdpr-modal, .cli-modal, #cliModal, [id*='gdpr'], [class*='gdpr-cookie'], [class*='cli-'] { display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; height: 0 !important; width: 0 !important; margin: 0 !important; padding: 0 !important; }</style>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gamble Investigates - Coming Soon</title>

  <style>
    body {
      margin: 0;
      min-height: 100vh;
      background-color: #08183a;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: Arial, Helvetica, sans-serif;
    }

    .coming-soon-container {
      text-align: center;
    }

    .logo {
      max-width: 520px;
      width: 90%;
      height: auto;
      display: block;
      margin: 0 auto;
    }

    .coming-soon {
      margin-top: 28px;
      color: #ffffff;
      font-size: 32px;
      font-weight: 700;
      letter-spacing: 2px;
      text-transform: uppercase;
    }
  </style>
</head>

<body>
<?php if(get_setting($pdo, 'announcement_bar_active') == '1'): ?>
<div style="background-color: #fecc56; color: #000; text-align: center; padding: 12px; font-weight: bold; z-index: 9999; position: relative; border-bottom: 2px solid #e5b340;">
    <?= htmlspecialchars(get_setting($pdo, 'announcement_bar_text')) ?>
</div>
<?php endif; ?>

<?php require_once $dir . '/includes/announcement.php'; ?>
  <div class="coming-soon-container">
    <img src="../wp-content/uploads/2026/05/Screenshot-2026-05-13-141848.png/index.html" alt="Gamble Investigates Logo" class="logo">

    <div class="coming-soon">Coming Soon</div>
  </div>





<?php require_once $dir . '/includes/chat_widget.php'; ?>
</body></html>





