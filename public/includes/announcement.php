<?php
// includes/announcement.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

$announcement_active = function_exists('get_setting') ? get_setting($pdo, 'announcement_bar_active', '1') : '1';
$announcement_text = function_exists('get_setting') ? get_setting($pdo, 'announcement_bar_text', 'IFW GLOBAL NOTICE: Protect yourself from scam impersonators. Only interact with our official team.') : 'IFW GLOBAL NOTICE: Protect yourself from scam impersonators. Only interact with our official team.';
?>
<style>
    /* Completely hide phone numbers in top header everywhere to prevent linebreaking */
    .phone-headers {
        display: none !important;
    }
    
    @media screen and (max-width: 47.9375em) {
        .site-header__book[href*="/client/login.php"] {
            position: static !important;
            width: auto !important;
            height: auto !important;
            overflow: visible !important;
            clip: auto !important;
            white-space: nowrap !important;
            display: inline-flex !important;
            align-items: center !important;
            padding: 0.45rem 0.75rem !important;
            font-size: 0.85rem !important;
            line-height: 1.2 !important;
            margin-left: 4px !important;
            margin-right: 4px !important;
            border-radius: 3px !important;
            z-index: 10 !important;
        }
    }
    
    .ifw-global-announcement-bar {
        background-color: #fecc56 !important;
        color: #1f1b1c !important;
        font-weight: 700 !important;
        font-size: 14px !important;
        padding: 10px 20px !important;
        text-align: center !important;
        position: relative !important;
        z-index: 9999 !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.15) !important;
        border-bottom: 2px solid #231f20 !important;
    }
    .ifw-global-announcement-bar a {
        color: #1f1b1c !important;
        text-decoration: underline !important;
    }
</style>

<?php if ($announcement_active == '1' && !empty($announcement_text)): ?>
<div class="ifw-global-announcement-bar">
    <span class="mr-2"><i class="fas fa-exclamation-triangle text-dark"></i></span>
    <span><?= htmlspecialchars($announcement_text) ?></span>
</div>
<?php endif; ?>
