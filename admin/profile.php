<?php
// admin/profile.php
require_once '../config.php';
require_once '../includes/functions.php';
require_admin_login();

$admin_id = (int)$_SESSION['admin_id'];
$msg = $err = '';

// Ensure custom columns exist in IFW_users
try {
    $pdo->exec("ALTER TABLE IFW_users ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL AFTER email");
    $pdo->exec("ALTER TABLE IFW_users ADD COLUMN IF NOT EXISTS custom_role_title VARCHAR(100) NULL AFTER role");
    $pdo->exec("ALTER TABLE IFW_users ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(255) NULL AFTER custom_role_title");
} catch(Exception $e) {}

// Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $custom_title = trim($_POST['custom_role_title'] ?? '');
        
        if (!empty($full_name) && !empty($email)) {
            try {
                $stmt = $pdo->prepare("UPDATE IFW_users SET full_name = ?, email = ?, phone = ?, custom_role_title = ? WHERE id = ?");
                $stmt->execute([$full_name, $email, $phone, $custom_title, $admin_id]);
                
                $_SESSION['admin_username'] = $full_name;
                
                if (function_exists('log_audit_action')) {
                    log_audit_action($pdo, $admin_id, 'Admin Profile Update', "Updated profile details (Name: {$full_name}, Title: {$custom_title})", 'admin');
                }
                $msg = "Profile details updated successfully.";
            } catch (Exception $e) {
                $err = "Could not update profile. Email may already be in use.";
            }
        } else {
            $err = "Full name and email are required.";
        }
    } elseif ($_POST['action'] === 'update_password') {
        $old_pwd = $_POST['old_password'] ?? '';
        $new_pwd = $_POST['new_password'] ?? '';
        $con_pwd = $_POST['confirm_password'] ?? '';
        
        if (strlen($new_pwd) < 6) {
            $err = "New password must be at least 6 characters.";
        } elseif ($new_pwd !== $con_pwd) {
            $err = "Passwords do not match.";
        } else {
            $stmt = $pdo->prepare("SELECT password_hash FROM IFW_users WHERE id = ?");
            $stmt->execute([$admin_id]);
            $current_hash = $stmt->fetchColumn();
            
            if ($current_hash && !password_verify($old_pwd, $current_hash)) {
                $err = "Current password is incorrect.";
            } else {
                $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE IFW_users SET password_hash = ? WHERE id = ?")->execute([$new_hash, $admin_id]);
                if (function_exists('log_audit_action')) {
                    log_audit_action($pdo, $admin_id, 'Password Change', "Admin changed their login password", 'admin');
                }
                $msg = "Password updated successfully.";
            }
        }
    } elseif ($_POST['action'] === 'update_pin') {
        $new_pin = $_POST['new_pin'] ?? '';
        if (strlen($new_pin) !== 4 || !is_numeric($new_pin)) {
            $err = "Security PIN must be exactly 4 numeric digits.";
        } else {
            $pin_hash = password_hash($new_pin, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE IFW_users SET pin_hash = ? WHERE id = ?")->execute([$pin_hash, $admin_id]);
            if (function_exists('log_audit_action')) {
                log_audit_action($pdo, $admin_id, 'Security PIN Change', "Admin updated their 4-digit security PIN", 'admin');
            }
            $msg = "4-digit Security PIN updated successfully.";
        }
    }
}

// Fetch current user details
$stmt = $pdo->prepare("SELECT * FROM IFW_users WHERE id = ?");
$stmt->execute([$admin_id]);
$user = $stmt->fetch();

require_once '../includes/admin_header.php';
require_once '../includes/admin_sidebar.php';
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-user-circle mr-2"></i>My Investigator & Admin Profile</h3>
            <p class="text-muted mb-0">Manage your staff credentials, public display role shown to clients, and security settings.</p>
        </div>
        <a href="index.php" class="btn btn-outline-warning btn-sm font-weight-bold"><i class="fas fa-arrow-left mr-1"></i> Dashboard</a>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-success border-0 shadow-sm"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger border-0 shadow-sm"><i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="row">
    <!-- LEFT: Profile & Public Investigator Info -->
    <div class="col-lg-7 mb-4">
        <div class="card shadow-lg bg-dark border-secondary">
            <div class="card-header bg-dark border-secondary text-warning font-weight-bold py-3">
                <i class="fas fa-id-badge mr-2"></i>Personal Information & Client Display Role
            </div>
            <div class="card-body bg-dark text-white p-4">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row mb-3">
                        <div class="col-md-6 form-group">
                            <label class="small text-muted font-weight-bold text-uppercase">Username (System)</label>
                            <input type="text" class="form-control bg-secondary text-white border-0" value="<?= htmlspecialchars($user['username'] ?? '') ?>" disabled>
                            <small class="text-muted">Unique login identifier (read-only)</small>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="small text-warning font-weight-bold text-uppercase">System Access Role</label>
                            <input type="text" class="form-control bg-secondary text-white border-0" value="<?= htmlspecialchars(ucwords(str_replace('_', ' ', $user['role'] ?? 'Agent'))) ?>" disabled>
                            <small class="text-muted">Permission authority level</small>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="small text-light font-weight-bold text-uppercase">Full Display Name <span class="text-warning">*</span></label>
                        <input type="text" name="full_name" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($user['full_name'] ?? $user['username']) ?>" required placeholder="e.g. Gary Livingston">
                        <small class="text-muted">This name appears in case reports, client communications, and live chat.</small>
                    </div>

                    <div class="form-group mb-3">
                        <label class="small text-warning font-weight-bold text-uppercase">Custom Display Role / Forensic Title</label>
                        <input type="text" name="custom_role_title" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($user['custom_role_title'] ?? '') ?>" placeholder="e.g. Senior Blockchain Forensic Investigator & Recovery Lead">
                        <small class="text-muted">Custom title displayed on client dashboard, messages, and case timeline (e.g. Senior Intelligence Officer, Asset Tracing Director).</small>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6 form-group">
                            <label class="small text-light font-weight-bold text-uppercase">Official Email Address <span class="text-warning">*</span></label>
                            <input type="email" name="email" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="small text-light font-weight-bold text-uppercase">Direct Phone / Signal</label>
                            <input type="text" name="phone" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+1 (800) 555-0199">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4 shadow">
                        <i class="fas fa-save mr-1"></i> Save Profile Details
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT: Security, Password & PIN -->
    <div class="col-lg-5 mb-4">
        <!-- Change Password Card -->
        <div class="card shadow-lg bg-dark border-secondary mb-4">
            <div class="card-header bg-dark border-secondary text-warning font-weight-bold py-3">
                <i class="fas fa-key mr-2"></i>Change Admin Password
            </div>
            <div class="card-body bg-dark text-white p-4">
                <form method="POST">
                    <input type="hidden" name="action" value="update_password">
                    
                    <div class="form-group mb-3">
                        <label class="small text-muted font-weight-bold">Current Password</label>
                        <input type="password" name="old_password" class="form-control bg-dark text-white border-secondary" required placeholder="Enter current password">
                    </div>
                    <div class="form-group mb-3">
                        <label class="small text-muted font-weight-bold">New Password (Min 6 chars)</label>
                        <input type="password" name="new_password" class="form-control bg-dark text-white border-secondary" required minlength="6" placeholder="Enter new strong password">
                    </div>
                    <div class="form-group mb-3">
                        <label class="small text-muted font-weight-bold">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control bg-dark text-white border-secondary" required placeholder="Re-type new password">
                    </div>

                    <button type="submit" class="btn btn-outline-warning btn-block font-weight-bold">
                        <i class="fas fa-lock mr-1"></i> Update Password
                    </button>
                </form>
            </div>
        </div>

        <!-- 4-Digit Security PIN Card -->
        <div class="card shadow-lg bg-dark border-secondary">
            <div class="card-header bg-dark border-secondary text-warning font-weight-bold py-3">
                <i class="fas fa-shield-alt mr-2"></i>Investigator Security PIN
            </div>
            <div class="card-body bg-dark text-white p-4">
                <p class="small text-muted mb-3">Used for administrative overrides, unlocking high-security case files, and authorizing client refunds.</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_pin">
                    
                    <div class="form-group mb-3">
                        <label class="small text-muted font-weight-bold">New 4-Digit PIN</label>
                        <input type="password" name="new_pin" class="form-control bg-dark text-white border-secondary text-center font-weight-bold font-large" maxlength="4" placeholder="••••" required pattern="\d{4}">
                    </div>

                    <button type="submit" class="btn btn-outline-warning btn-block font-weight-bold">
                        <i class="fas fa-key mr-1"></i> Save Security PIN
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/admin_footer.php'; ?>
