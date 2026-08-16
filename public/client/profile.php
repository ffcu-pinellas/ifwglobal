<?php
// client/profile.php
$dir = __DIR__;
while (!file_exists($dir . '/config.php') && $dir !== dirname($dir)) {
    $dir = dirname($dir);
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
require_once $dir . '/includes/currency_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['client_logged_in']) || empty($_SESSION['client_portal_id'])) {
    header("Location: login.php");
    exit;
}

$client_id = (int)$_SESSION['client_portal_id'];

// Handle Profile & Avatar Updates
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_currency') {
        $new_curr = strtoupper(trim($_POST['preferred_currency'] ?? 'USD'));
        $avail = get_available_currencies();
        if (isset($avail[$new_curr])) {
            try {
                $stmt = $pdo->prepare("UPDATE IFW_clients SET preferred_currency = ? WHERE id = ?");
                $stmt->execute([$new_curr, $client_id]);
                $_SESSION['client_currency'] = $new_curr;
                $success_msg = "Display currency updated to {$new_curr} successfully.";
            } catch (Exception $e) {
                $error_msg = "Failed to update currency preference.";
            }
        }
    } elseif ($_POST['action'] === 'update_profile') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        if (empty($first_name)) {
            $error_msg = "First name is required and cannot be left blank.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE IFW_clients SET first_name = ?, last_name = ?, phone = ?, country = ?, address = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $phone, $country, $address, $client_id]);
                
                $_SESSION['client_name'] = trim($first_name . ' ' . $last_name);
                $_SESSION['user_name'] = $first_name;
                $success_msg = "Profile and personal details updated successfully.";
            } catch (Exception $e) {
                $error_msg = "Failed to save profile details: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'update_avatar') {
        // Handle direct file upload
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['avatar_file']['tmp_name'];
            $fileName = $_FILES['avatar_file']['name'];
            $fileSize = $_FILES['avatar_file']['size'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($ext, $allowed)) {
                $error_msg = "Invalid image format. Allowed formats: JPG, PNG, WEBP, GIF.";
            } elseif ($fileSize > 5 * 1024 * 1024) {
                $error_msg = "Image file is too large. Maximum size allowed is 5MB.";
            } else {
                $uploadDir = $dir . '/uploads/avatars/';
                $publicUploadDir = $dir . '/public/uploads/avatars/';
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                if (!is_dir($publicUploadDir)) @mkdir($publicUploadDir, 0755, true);
                
                $newFileName = 'client_' . $client_id . '_' . time() . '.' . $ext;
                $targetPath = $uploadDir . $newFileName;
                $publicTargetPath = $publicUploadDir . $newFileName;
                
                if (move_uploaded_file($fileTmp, $targetPath)) {
                    @copy($targetPath, $publicTargetPath);
                    $avatar_rel_url = '/uploads/avatars/' . $newFileName;
                    
                    try {
                        $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(255) NULL");
                        $st_av = $pdo->prepare("UPDATE IFW_clients SET avatar_url = ? WHERE id = ?");
                        $st_av->execute([$avatar_rel_url, $client_id]);
                        $success_msg = "Profile photo uploaded and updated successfully.";
                    } catch (Exception $e) {
                        $error_msg = "Image uploaded but database update failed.";
                    }
                } else {
                    $error_msg = "Failed to upload image. Please check directory permissions.";
                }
            }
        } elseif (isset($_POST['preset_avatar']) && !empty($_POST['preset_avatar'])) {
            $preset = trim($_POST['preset_avatar']);
            $valid_presets = [
                '/admin_assets/img/profile/male.jpg',
                '/admin_assets/img/profile/female.jpg',
                '/admin_assets/img/profile/other.png',
                '/admin_assets/img/profile/blank.png'
            ];
            if (in_array($preset, $valid_presets) || $preset === 'default') {
                $avatar_val = ($preset === 'default' || $preset === '/admin_assets/img/profile/blank.png') ? NULL : $preset;
                try {
                    $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(255) NULL");
                    $st_av = $pdo->prepare("UPDATE IFW_clients SET avatar_url = ? WHERE id = ?");
                    $st_av->execute([$avatar_val, $client_id]);
                    $success_msg = "Avatar preset updated successfully.";
                } catch (Exception $e) {
                    $error_msg = "Failed to update avatar preset.";
                }
            }
        } elseif (isset($_POST['remove_avatar'])) {
            try {
                $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(255) NULL");
                $st_av = $pdo->prepare("UPDATE IFW_clients SET avatar_url = NULL WHERE id = ?");
                $st_av->execute([$client_id]);
                $success_msg = "Profile photo removed. Using default account avatar.";
            } catch (Exception $e) {
                $error_msg = "Failed to reset avatar.";
            }
        }
    }
}

// Fetch Fresh Client Info
$stmt = $pdo->prepare("SELECT * FROM IFW_clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();
if (!$client) {
    header("Location: logout.php");
    exit;
}

$active_currency = get_client_currency($pdo, $client_id);
$avail_currencies = get_available_currencies();
$curr_meta = $avail_currencies[$active_currency] ?? $avail_currencies['USD'];

require_once $dir . '/includes/admin_header.php';
require_once $dir . '/includes/admin_sidebar.php';
$portal_avatar_url = get_portal_avatar_url($pdo, 'client', $client_id);
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="text-warning font-weight-bold mb-1">
            <i class="fas fa-user-cog mr-2"></i>Profile &amp; Account Settings
        </h3>
        <p class="text-muted mb-0">Manage your verified name, profile picture, display currency, privacy shield, and contact details.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="security.php" class="btn btn-outline-warning btn-sm font-weight-bold">
            <i class="fas fa-user-shield mr-1"></i> Security Desk
        </a>
        <a href="dashboard.php" class="btn btn-warning text-dark btn-sm font-weight-bold">
            <i class="fas fa-th-large mr-1"></i> Back to Dashboard
        </a>
    </div>
</div>

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success border-0 shadow-sm font-weight-bold mb-4">
        <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success_msg) ?>
    </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger border-0 shadow-sm font-weight-bold mb-4">
        <i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error_msg) ?>
    </div>
<?php endif; ?>

<div class="row">
    <!-- LEFT COLUMN: Profile & Avatar Management -->
    <div class="col-lg-4 mb-4">
        <div class="card bg-dark border-secondary shadow-lg h-100" style="border-radius: 12px; border-top: 3px solid #fecc56 !important;">
            <div class="card-body text-center p-4">
                <div class="position-relative d-inline-block mb-3">
                    <img src="<?= htmlspecialchars($portal_avatar_url) ?>" id="profileAvatarPreview" class="rounded-circle border border-warning shadow" width="105" height="105" style="object-fit:cover;" onerror="this.onerror=null;this.src='/admin_assets/img/profile/blank.png';">
                    <span class="badge badge-success position-absolute" style="bottom: 4px; right: 4px; border-radius: 50%; padding: 7px;" title="Online"><i class="fas fa-check" style="font-size: 11px;"></i></span>
                </div>

                <h5 class="text-white font-weight-bold mb-1"><?= htmlspecialchars(trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? ''))) ?></h5>
                <p class="text-warning small font-weight-bold mb-2">Verified Client Account</p>
                <div class="badge badge-dark border border-secondary text-muted px-3 py-1 mb-3">
                    Client ID: <span class="text-warning font-monospace">#<?= str_pad($client['id'], 5, '0', STR_PAD_LEFT) ?></span>
                </div>

                <!-- Avatar Actions -->
                <div class="mt-2 mb-4 p-3 bg-secondary rounded border border-dark text-left">
                    <label class="text-warning font-weight-bold small mb-2 d-block">
                        <i class="fas fa-camera mr-1"></i> Update Profile Picture
                    </label>
                    <form method="POST" action="profile.php" enctype="multipart/form-data" class="mb-2">
                        <input type="hidden" name="action" value="update_avatar">
                        <div class="custom-file mb-2">
                            <input type="file" name="avatar_file" class="custom-file-input" id="avatarFileInput" accept="image/png, image/jpeg, image/jpg, image/webp, image/gif" onchange="previewSelectedAvatar(this)" required>
                            <label class="custom-file-label text-truncate" for="avatarFileInput" id="avatarFileLabel" style="font-size: 12px;">Choose photo...</label>
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm btn-block text-dark font-weight-bold">
                            <i class="fas fa-upload mr-1"></i> Upload Photo
                        </button>
                    </form>

                    <!-- Preset Avatars / Reset -->
                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top border-dark">
                        <span class="text-muted small">Presets:</span>
                        <div class="d-flex gap-1">
                            <form method="POST" action="profile.php" class="d-inline">
                                <input type="hidden" name="action" value="update_avatar">
                                <input type="hidden" name="preset_avatar" value="/admin_assets/img/profile/male.jpg">
                                <button type="submit" class="btn btn-outline-secondary btn-sm p-1" title="Male Avatar" style="width:28px;height:28px;border-radius:50%;overflow:hidden;padding:0!important;">
                                    <img src="/admin_assets/img/profile/male.jpg" width="100%" height="100%" style="object-fit:cover;">
                                </button>
                            </form>
                            <form method="POST" action="profile.php" class="d-inline">
                                <input type="hidden" name="action" value="update_avatar">
                                <input type="hidden" name="preset_avatar" value="/admin_assets/img/profile/female.jpg">
                                <button type="submit" class="btn btn-outline-secondary btn-sm p-1" title="Female Avatar" style="width:28px;height:28px;border-radius:50%;overflow:hidden;padding:0!important;">
                                    <img src="/admin_assets/img/profile/female.jpg" width="100%" height="100%" style="object-fit:cover;">
                                </button>
                            </form>
                            <form method="POST" action="profile.php" class="d-inline">
                                <input type="hidden" name="action" value="update_avatar">
                                <input type="hidden" name="preset_avatar" value="/admin_assets/img/profile/other.png">
                                <button type="submit" class="btn btn-outline-secondary btn-sm p-1" title="Abstract Avatar" style="width:28px;height:28px;border-radius:50%;overflow:hidden;padding:0!important;">
                                    <img src="/admin_assets/img/profile/other.png" width="100%" height="100%" style="object-fit:cover;">
                                </button>
                            </form>
                            <form method="POST" action="profile.php" class="d-inline">
                                <input type="hidden" name="action" value="update_avatar">
                                <input type="hidden" name="remove_avatar" value="1">
                                <button type="submit" class="btn btn-outline-danger btn-sm p-0" title="Remove custom avatar" style="width:28px;height:28px;border-radius:50%;font-size:10px;">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="border-top border-secondary pt-3 text-left small text-muted">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Email Address:</span>
                        <span class="text-white font-weight-bold text-truncate ml-2"><?= htmlspecialchars($client['email'] ?? 'N/A') ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Phone Contact:</span>
                        <span class="text-white font-weight-bold"><?= htmlspecialchars($client['phone'] ?? 'Not set') ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Country / Region:</span>
                        <span class="text-white font-weight-bold"><?= htmlspecialchars($client['country'] ?? 'Global') ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Account Status:</span>
                        <span class="badge badge-success"><?= htmlspecialchars($client['status'] ?? 'Active') ?></span>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top border-secondary">
                    <a href="kyc.php" class="btn btn-outline-secondary btn-sm btn-block font-weight-bold text-light mb-2">
                        <i class="fas fa-id-card text-warning mr-1"></i> Identity Documents (KYC)
                    </a>
                    <a href="security.php" class="btn btn-outline-secondary btn-sm btn-block font-weight-bold text-light">
                        <i class="fas fa-shield-alt text-warning mr-1"></i> Security Logs &amp; Sessions
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: Identity, Currency & Privacy -->
    <div class="col-lg-8 mb-4">
        <!-- 1. PERSONAL IDENTITY & ACCOUNT PROFILE -->
        <div class="card bg-dark border-secondary shadow-lg mb-4" style="border-radius: 12px;">
            <div class="card-header bg-dark border-secondary text-warning font-weight-bold py-3">
                <i class="fas fa-user-edit mr-2"></i>Personal Identity &amp; Account Details
            </div>
            <div class="card-body p-4">
                <form method="POST" action="profile.php">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="text-light font-weight-bold small">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control bg-secondary text-white border-0 font-weight-bold" value="<?= htmlspecialchars($client['first_name'] ?? '') ?>" placeholder="First name" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="text-light font-weight-bold small">Last Name</label>
                            <input type="text" name="last_name" class="form-control bg-secondary text-white border-0 font-weight-bold" value="<?= htmlspecialchars($client['last_name'] ?? '') ?>" placeholder="Last name">
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="text-light font-weight-bold small">Registered Email Address</label>
                            <input type="email" class="form-control bg-secondary text-muted border-0" value="<?= htmlspecialchars($client['email'] ?? '') ?>" disabled>
                            <small class="text-muted">Primary account email used for two-factor authentication and official reports.</small>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="text-light font-weight-bold small">Telephone / Mobile Number</label>
                            <input type="text" name="phone" class="form-control bg-secondary text-white border-0" value="<?= htmlspecialchars($client['phone'] ?? '') ?>" placeholder="+1 (555) 000-0000">
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="text-light font-weight-bold small">Country / Jurisdiction of Residence</label>
                            <input type="text" name="country" class="form-control bg-secondary text-white border-0" value="<?= htmlspecialchars($client['country'] ?? '') ?>" placeholder="e.g. United States, United Kingdom, Australia">
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="text-light font-weight-bold small">Residential Address</label>
                            <input type="text" name="address" class="form-control bg-secondary text-white border-0" value="<?= htmlspecialchars($client['address'] ?? '') ?>" placeholder="Street, City, State/Province, Postal Code">
                        </div>
                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-warning text-dark font-weight-bold px-4 py-2 shadow-sm">
                                <i class="fas fa-save mr-1"></i> Save Profile Details
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- 2. DISPLAY CURRENCY PREFERENCE -->
        <div class="card bg-dark border-secondary shadow-lg mb-4" style="border-radius: 12px;">
            <div class="card-header bg-dark border-secondary text-warning font-weight-bold d-flex justify-content-between align-items-center py-3">
                <span><i class="fas fa-coins mr-2"></i>Preferred Display Currency</span>
                <span class="badge badge-warning text-dark font-weight-bold"><?= $curr_meta['flag'] ?> <?= $curr_meta['code'] ?> (<?= $curr_meta['symbol'] ?>)</span>
            </div>
            <div class="card-body p-4">
                <p class="text-light small mb-3">
                    Select your preferred fiat currency. All cases, invoices, recovery calculations, and financial estimates will automatically convert using real-time global exchange rates.
                </p>
                
                <form method="POST" action="profile.php" class="row">
                    <input type="hidden" name="action" value="update_currency">
                    <div class="col-md-8 mb-3 mb-md-0">
                        <select name="preferred_currency" class="form-control bg-secondary text-white border-0 font-weight-bold form-control-lg" onchange="this.form.submit()">
                            <?php foreach ($avail_currencies as $cCode => $cMeta): ?>
                                <option value="<?= $cCode ?>" <?= ($cCode === $active_currency) ? 'selected' : '' ?>>
                                    <?= $cMeta['flag'] ?> <?= $cMeta['code'] ?> (<?= $cMeta['symbol'] ?>) - <?= $cMeta['name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-warning text-dark font-weight-bold btn-block h-100">
                            <i class="fas fa-check mr-1"></i> Update Currency
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 3. FINANCIAL PRIVACY SHIELD -->
        <div class="card bg-dark border-secondary shadow-lg mb-4" style="border-radius: 12px;">
            <div class="card-header bg-dark border-secondary text-warning font-weight-bold d-flex justify-content-between align-items-center py-3">
                <span><i class="fas fa-user-secret mr-2"></i>Financial Privacy Shield Mode</span>
                <span class="badge badge-secondary" id="privacyShieldBadge">Discreet Browsing</span>
            </div>
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div style="max-width: 500px;">
                        <h6 class="text-white font-weight-bold mb-1">Mask Sensitive Financial Balances</h6>
                        <p class="text-muted small mb-0">
                            When enabled, all claim values, escrow balances, and monetary figures across your portal will be obscured (<code>••••••</code>) so you can browse discreetly in public spaces or around colleagues.
                        </p>
                    </div>
                    <div>
                        <button type="button" id="profilePrivacyToggle" class="btn btn-outline-warning font-weight-bold px-4 py-2" onclick="togglePrivacyShield(); syncProfilePrivacyUI();">
                            <i class="fas fa-eye-slash mr-2" id="profilePrivacyIcon"></i>
                            <span id="profilePrivacyBtnText">Enable Privacy Shield</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function previewSelectedAvatar(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var prev = document.getElementById('profileAvatarPreview');
            if (prev) prev.src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
        var label = document.getElementById('avatarFileLabel');
        if (label) label.textContent = input.files[0].name;
    }
}

function syncProfilePrivacyUI() {
    var saved = localStorage.getItem('ifw_privacy_shield');
    var isAct = (saved === 'active');
    var btn = document.getElementById('profilePrivacyToggle');
    var icon = document.getElementById('profilePrivacyIcon');
    var txt = document.getElementById('profilePrivacyBtnText');
    var badge = document.getElementById('privacyShieldBadge');
    
    if (btn && icon && txt) {
        if (isAct) {
            btn.className = 'btn btn-warning font-weight-bold text-dark px-4 py-2';
            icon.className = 'fas fa-eye mr-2';
            txt.textContent = 'Privacy Shield Active (Click to Disable)';
            if (badge) {
                badge.className = 'badge badge-success';
                badge.textContent = 'Active (Protected)';
            }
        } else {
            btn.className = 'btn btn-outline-warning font-weight-bold px-4 py-2';
            icon.className = 'fas fa-eye-slash mr-2';
            txt.textContent = 'Enable Privacy Shield';
            if (badge) {
                badge.className = 'badge badge-secondary';
                badge.textContent = 'Disabled';
            }
        }
    }
}
document.addEventListener('DOMContentLoaded', syncProfilePrivacyUI);
</script>

<?php require_once $dir . '/includes/admin_footer.php'; ?>
