<?php
ob_start();
// admin/settings.php
require_once '../config.php';
require_once '../includes/functions.php';
require_admin_login();

$user_role = $_SESSION['admin_role'] ?? 'viewer';
if (!in_array($user_role, ['super_admin', 'superadmin', 'admin'])) {
    die("Unauthorized access to settings.");
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $keys = [
        'phone_australia', 'phone_australia_secondary', 'phone_uk', 'phone_usa', 'display_phone_numbers',
        'contact_email', 'contact_phone', 'office_address',
        'bank_name', 'bank_account_name', 'bank_account_number', 'bank_swift_iban',
        'crypto_wallet_address', 'crypto_wallet_type', 'payment_instructions',
        'hero_headline', 'hero_subheadline', 'hero_cta', 
        'announcement_bar_text', 'announcement_bar_active',
        'meta_title', 'meta_description', 'meta_keywords', 'maintenance_mode',
        'chat_provider', 'manychat_script_code', 'tawkto_property_id', 'custom_chat_code', 'logo_url',
        'show_lifecycle_tracker', 'show_fund_flow_visualizer',
        'telegram_bot_token', 'telegram_chat_id'
    ];
    
    foreach ($keys as $key) {
        $val = $_POST[$key] ?? '';
        if (($key == 'announcement_bar_active' || $key == 'maintenance_mode') && !isset($_POST[$key])) {
            $val = '0';
        }
        set_setting($pdo, $key, $val);
    }
    
    header("Location: settings.php?success=1");
    exit;
}

// Fetch Current Settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM IFW_site_settings");
$s = [];
while ($row = $stmt->fetch()) {
    $s[$row['setting_key']] = $row['setting_value'];
}
?>

<?php require_once '../includes/admin_header.php'; ?>
<?php require_once '../includes/admin_sidebar.php'; ?>

<div class="row">
    <div class="col-12 mb-4 d-flex align-items-center justify-content-between">
        <div>
            <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-cogs mr-2"></i>Global Site & Integration Settings</h3>
            <p class="text-muted mb-0">Manage phone numbers, banking details, ManyChat/Tawk.to live chat, announcement banner, and SEO metadata.</p>
        </div>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success bg-success text-white border-0 shadow-sm mb-4">
        <i class="fas fa-check-circle mr-2"></i>Global settings updated successfully!
    </div>
<?php endif; ?>

<form method="POST">
    <div class="row">
        <!-- 1. LIVE CHAT & COMMUNICATIONS INTEGRATION -->
        <div class="col-lg-12 mb-4">
            <div class="card shadow-sm border-warning">
                <div class="card-header bg-dark text-warning border-warning d-flex align-items-center justify-content-between">
                    <span><i class="fas fa-comments mr-2"></i>Live Chat System & 3rd-Party Integration (ManyChat / Tawk.to)</span>
                    <span class="badge badge-warning text-dark font-weight-bold">Live System Switcher</span>
                </div>
                <div class="card-body bg-dark text-white">
                    <div class="form-group mb-4">
                        <label class="font-weight-bold text-warning fs-5">Active Chat Provider</label>
                        <select name="chat_provider" class="form-control bg-secondary text-white border-0 form-control-lg font-weight-bold">
                            <option value="manychat" <?php echo ($s['chat_provider'] ?? 'manychat') === 'manychat' ? 'selected' : ''; ?>>ManyChat (Recommended - Auto Messenger / AI Agent)</option>
                            <option value="tawkto" <?php echo ($s['chat_provider'] ?? '') === 'tawkto' ? 'selected' : ''; ?>>Tawk.to Live Chat Widget</option>
                            <option value="internal" <?php echo ($s['chat_provider'] ?? '') === 'internal' ? 'selected' : ''; ?>>Internal Secure Database Chat (Client / Agent / Investigator)</option>
                            <option value="custom" <?php echo ($s['chat_provider'] ?? '') === 'custom' ? 'selected' : ''; ?>>Custom Third-Party Embed Snippet</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-bold text-light">ManyChat Embed Code / Page ID</label>
                            <textarea name="manychat_script_code" class="form-control bg-secondary text-white border-0" rows="4" placeholder="Paste your ManyChat Javascript Snippet or Page Embed Code here..."><?php echo htmlspecialchars($s['manychat_script_code'] ?? ''); ?></textarea>
                            <small class="text-muted">Paste your ManyChat embed snippet or widget code to activate ManyChat across all client & admin chat interfaces.</small>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-bold text-light">Tawk.to Direct Chat URL or Property ID</label>
                            <textarea name="tawkto_property_id" class="form-control bg-secondary text-white border-0" rows="4" placeholder="e.g. https://tawk.to/chat/YOUR_PROPERTY_ID/default or Tawk.to Script Snippet"><?php echo htmlspecialchars($s['tawkto_property_id'] ?? ''); ?></textarea>
                            <small class="text-muted">Paste your Tawk.to Direct Chat Link or Javascript embed snippet.</small>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-light">Custom Chat Snippet Code (Fallback)</label>
                        <textarea name="custom_chat_code" class="form-control bg-secondary text-white border-0" rows="3" placeholder="Paste custom chat widget script tag here..."><?php echo htmlspecialchars($s['custom_chat_code'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. PHONE NUMBERS & CONTACT DETAILS -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100 shadow-sm border-secondary">
                <div class="card-header bg-dark text-warning border-secondary d-flex align-items-center">
                    <i class="fas fa-phone-alt mr-2"></i>Phone Numbers & Contact Settings
                </div>
                <div class="card-body bg-dark text-white">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Phone Numbers Display Mode</label>
                        <select name="display_phone_numbers" class="form-control bg-secondary text-white border-0">
                            <option value="show" <?php echo ($s['display_phone_numbers'] ?? 'show') === 'show' ? 'selected' : ''; ?>>Show Dynamic Phone Numbers</option>
                            <option value="hide" <?php echo ($s['display_phone_numbers'] ?? '') === 'hide' ? 'selected' : ''; ?>>Hide Phone Numbers & Show "Submit an Enquiry" Button</option>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Australia Phone Number</label>
                        <input type="text" name="phone_australia" class="form-control bg-secondary text-white border-0" value="<?php echo htmlspecialchars($s['phone_australia'] ?? '+61 (02) 8328 0402'); ?>" placeholder="HQ Number">
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Australia Phone Number (Secondary)</label>
                        <input type="text" name="phone_australia_secondary" class="form-control bg-secondary text-white border-0" value="<?php echo htmlspecialchars($s['phone_australia_secondary'] ?? '+61 (02) 8328 0402'); ?>" placeholder="Secondary Number">
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">UK Phone Number</label>
                        <input type="text" name="phone_uk" class="form-control bg-secondary text-white border-0" value="<?php echo htmlspecialchars($s['phone_uk'] ?? ''); ?>" placeholder="UK Number">
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">USA Phone Number</label>
                        <input type="text" name="phone_usa" class="form-control bg-secondary text-white border-0" value="<?php echo htmlspecialchars($s['phone_usa'] ?? '+1 (239) 247 5287'); ?>" placeholder="+1 (239) 247 5287">
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Contact Email Address</label>
                        <input type="email" name="contact_email" class="form-control bg-secondary text-white border-0" value="<?php echo htmlspecialchars($s['contact_email'] ?? 'investigations@ifwglobal.com'); ?>">
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-light">Head Office Address</label>
                        <textarea name="office_address" class="form-control bg-secondary text-white border-0" rows="2"><?php echo htmlspecialchars($s['office_address'] ?? 'Sydney HQ: Level 25, 88 Phillip Street, Sydney NSW 2000'); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. BANK & ACCOUNT INFORMATION -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100 shadow-sm border-secondary">
                <div class="card-header bg-dark text-warning border-secondary d-flex align-items-center">
                    <i class="fas fa-university mr-2"></i>Account & Payment Information
                </div>
                <div class="card-body bg-dark text-white">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Bank Name</label>
                        <input type="text" name="bank_name" class="form-control bg-secondary text-white border-0" value="<?php echo htmlspecialchars($s['bank_name'] ?? 'Commonwealth Bank of Australia'); ?>">
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Account Holder Name</label>
                        <input type="text" name="bank_account_name" class="form-control bg-secondary text-white border-0" value="<?php echo htmlspecialchars($s['bank_account_name'] ?? 'IFW Global Recovery Pty Ltd'); ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-bold text-light">Account / BSB Number</label>
                            <input type="text" name="bank_account_number" class="form-control bg-secondary text-white border-0" value="<?php echo htmlspecialchars($s['bank_account_number'] ?? '062-000 19283746'); ?>">
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-bold text-light">SWIFT / BIC Code</label>
                            <input type="text" name="bank_swift_iban" class="form-control bg-secondary text-white border-0" value="<?php echo htmlspecialchars($s['bank_swift_iban'] ?? 'CTBAAU2S'); ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8 form-group mb-3">
                            <label class="font-weight-bold text-light">Crypto Wallet Address (Optional)</label>
                            <input type="text" name="crypto_wallet_address" class="form-control bg-secondary text-white border-0" value="<?php echo htmlspecialchars($s['crypto_wallet_address'] ?? ''); ?>" placeholder="0x... or bc1q...">
                        </div>
                        <div class="col-md-4 form-group mb-3">
                            <label class="font-weight-bold text-light">Wallet Network</label>
                            <input type="text" name="crypto_wallet_type" class="form-control bg-secondary text-white border-0" value="<?php echo htmlspecialchars($s['crypto_wallet_type'] ?? 'USDT (TRC20)'); ?>" placeholder="USDT TRC20 / BTC">
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-light">Payment Instructions for Clients</label>
                        <textarea name="payment_instructions" class="form-control bg-secondary text-white border-0" rows="2"><?php echo htmlspecialchars($s['payment_instructions'] ?? 'Please include your Case Reference Number in the transaction memo.'); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. ANNOUNCEMENT & HOMEPAGE CONTENT -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100 shadow-sm border-secondary">
                <div class="card-header bg-dark text-warning border-secondary d-flex align-items-center">
                    <i class="fas fa-bullhorn mr-2"></i>Announcement & Header Warning Banner
                </div>
                <div class="card-body bg-dark text-white">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Top Announcement Banner Active</label>
                        <select name="announcement_bar_active" class="form-control bg-secondary text-white border-0">
                            <option value="1" <?php echo ($s['announcement_bar_active'] ?? '1') == '1' ? 'selected' : ''; ?>>Enabled (Show Header Notice Banner)</option>
                            <option value="0" <?php echo ($s['announcement_bar_active'] ?? '1') == '0' ? 'selected' : ''; ?>>Disabled (Hide Header Notice Banner)</option>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Announcement Banner Text (IFW GLOBAL NOTICE)</label>
                        <textarea name="announcement_bar_text" class="form-control bg-secondary text-white border-0" rows="2"><?php echo htmlspecialchars($s['announcement_bar_text'] ?? 'IFW GLOBAL NOTICE: Protect yourself from scam impersonators. Only interact with our official team.'); ?></textarea>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Hero Title Headline</label>
                        <input type="text" name="hero_headline" class="form-control bg-secondary text-white border-0" value="<?php echo htmlspecialchars($s['hero_headline'] ?? 'EXPERTS IN CROSS BORDER ASSET RECOVERY & ASSET TRACING'); ?>">
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Hero Subheadline</label>
                        <textarea name="hero_subheadline" class="form-control bg-secondary text-white border-0" rows="2"><?php echo htmlspecialchars($s['hero_subheadline'] ?? 'Intelligence-led private investigations, cybercrime enforcement, and recovery of stolen funds worldwide.'); ?></textarea>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-light">Site & Invoice Brand Logo URL</label>
                        <input type="text" name="logo_url" class="form-control bg-secondary text-white border-0" value="<?php echo htmlspecialchars($s['logo_url'] ?? '/admin_assets/img/logo/logo.svg'); ?>" placeholder="e.g. /admin_assets/img/logo/logo.svg">
                        <small class="text-muted" style="font-size:10px;">Enter a custom image URL for the logo displayed on invoices, PDFs, and client portals.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. SEO & METADATA -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100 shadow-sm border-secondary">
                <div class="card-header bg-dark text-warning border-secondary d-flex align-items-center">
                    <i class="fas fa-search mr-2"></i>SEO & Metadata Settings
                </div>
                <div class="card-body bg-dark text-white">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Default Meta Title</label>
                        <input type="text" name="meta_title" class="form-control bg-secondary text-white border-0" value="<?php echo htmlspecialchars($s['meta_title'] ?? 'IFW Global | Asset Recovery & Financial Crime Investigations'); ?>">
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Default Meta Description</label>
                        <textarea name="meta_description" class="form-control bg-secondary text-white border-0" rows="2"><?php echo htmlspecialchars($s['meta_description'] ?? 'IFW Global is a leading private intelligence and asset recovery firm specializing in complex cybercrime, fraud, and global asset tracing.'); ?></textarea>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-light">Meta Keywords</label>
                        <input type="text" name="meta_keywords" class="form-control bg-secondary text-white border-0" value="<?php echo htmlspecialchars($s['meta_keywords'] ?? 'asset recovery, fraud investigation, crypto tracing, private intelligence'); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. CLIENT PORTAL VISUAL MODULES & FEATURES -->
        <div class="col-lg-12 mb-4">
            <div class="card shadow-sm border-secondary">
                <div class="card-header bg-dark text-warning border-secondary d-flex align-items-center">
                    <i class="fas fa-cubes mr-2"></i>Client Portal Visual Modules & Interactive Trackers
                </div>
                <div class="card-body bg-dark text-white">
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-bold text-light">1. Investigation & Asset Recovery Lifecycle Tracker</label>
                            <select name="show_lifecycle_tracker" class="form-control bg-secondary text-white border-0">
                                <option value="1" <?php echo ($s['show_lifecycle_tracker'] ?? '1') == '1' ? 'selected' : ''; ?>>Enabled (Show 5-Stage Recovery Lifecycle on Client Portal)</option>
                                <option value="0" <?php echo ($s['show_lifecycle_tracker'] ?? '1') == '0' ? 'selected' : ''; ?>>Disabled (Hide Lifecycle Bar)</option>
                            </select>
                            <small class="text-muted">Managed and updated per case in the Admin Recovery Cases section.</small>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-bold text-light">2. Forensic Fund Tracing & Asset Recovery Flow</label>
                            <select name="show_fund_flow_visualizer" class="form-control bg-secondary text-white border-0">
                                <option value="1" <?php echo ($s['show_fund_flow_visualizer'] ?? '1') == '1' ? 'selected' : ''; ?>>Enabled (Show Interactive Flow Diagram on Client Dashboard)</option>
                                <option value="0" <?php echo ($s['show_fund_flow_visualizer'] ?? '1') == '0' ? 'selected' : ''; ?>>Disabled (Hide Flow Diagram)</option>
                            </select>
                            <small class="text-muted">Displays the 4-step fund interception and repatriation flow to clients.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. TELEGRAM NOTIFICATIONS -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card shadow-sm border-warning">
                <div class="card-header bg-dark text-warning border-warning d-flex align-items-center">
                    <i class="fab fa-telegram mr-2"></i>Telegram Recording & Cataloging Notifications
                </div>
                <div class="card-body bg-dark text-white">
                    <p class="small text-muted mb-3">Configure your Telegram Bot token and target chat/channel ID. The system will send real-time alerts when clients change their temporary passwords and setup their security PINs for adequate cataloging.</p>
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-bold text-light">Telegram Bot Token</label>
                            <input type="text" name="telegram_bot_token" class="form-control bg-secondary text-white border-0" value="<?php echo htmlspecialchars($s['telegram_bot_token'] ?? ''); ?>" placeholder="e.g. 1234567890:ABCdefGhIJKlmNoPQRsTUVwxyZ">
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-bold text-light">Telegram Chat ID</label>
                            <input type="text" name="telegram_chat_id" class="form-control bg-secondary text-white border-0" value="<?php echo htmlspecialchars($s['telegram_chat_id'] ?? ''); ?>" placeholder="e.g. -100123456789 or USER_ID">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SAVE BUTTON -->
    <div class="row mb-5">
        <div class="col-12 text-right">
            <button type="submit" class="btn btn-warning btn-lg font-weight-bold px-5 text-dark shadow-sm">
                <i class="fas fa-save mr-2"></i>Save All Settings & Integration Controls
            </button>
        </div>
    </div>
</form>

<?php require_once '../includes/admin_footer.php'; ?>

