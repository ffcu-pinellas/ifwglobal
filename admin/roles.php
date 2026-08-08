<?php
// admin/roles.php
require_once '../config.php';
require_once '../includes/functions.php';

require_superadmin();

$success = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create_staff') {
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name'] ?? $username);
        $email = trim($_POST['email']);
        $role = $_POST['role'] ?? 'agent';
        $password = trim($_POST['password']);
        $pin = trim($_POST['pin'] ?? '0000');

        if (!empty($username) && !empty($email) && !empty($password)) {
            try {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $pin_hashed = password_hash($pin, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO IFW_users (username, full_name, email, role, password_hash, pin_hash) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $full_name, $email, $role, $hashed, $pin_hashed]);
                $success = "Staff account '$username' (" . ucfirst($role) . ") created successfully!";
            } catch (PDOException $e) {
                $error = "Error creating staff account. Username or email may already exist.";
            }
        }
    } elseif ($action === 'delete_staff') {
        $user_id = (int)$_POST['user_id'];
        if ($user_id !== $_SESSION['admin_id']) {
            try {
                $stmt = $pdo->prepare("DELETE FROM IFW_users WHERE id = ?");
                $stmt->execute([$user_id]);
                $success = "Staff account removed.";
            } catch (Exception $e) {}
        } else {
            $error = "You cannot delete your own active superadmin account.";
        }
    } elseif ($action === 'create_role') {
        $role_name = trim($_POST['role_name']);
        $perms = $_POST['permissions'] ?? [];

        if (!empty($role_name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO IFW_roles (name) VALUES (?)");
                $stmt->execute([$role_name]);
                $role_id = $pdo->lastInsertId();

                if (!empty($perms)) {
                    $permStmt = $pdo->prepare("INSERT INTO IFW_role_permissions (role_id, permission_id) VALUES (?, ?)");
                    foreach ($perms as $perm_id) {
                        $permStmt->execute([$role_id, $perm_id]);
                    }
                }
                $success = "Role '$role_name' created successfully.";
            } catch (PDOException $e) {
                $error = "Error creating role. It may already exist.";
            }
        }
    } elseif ($action === 'update_staff_role') {
        $user_id = (int)$_POST['user_id'];
        $role = $_POST['role'] ?? 'agent';
        if ($user_id !== $_SESSION['admin_id'] || $role === 'superadmin') {
            try {
                $stmt = $pdo->prepare("UPDATE IFW_users SET role = ? WHERE id = ?");
                $stmt->execute([$role, $user_id]);
                $success = "User role updated successfully.";
            } catch (Exception $e) {
                $error = "Error updating role.";
            }
        } else {
            $error = "You cannot demote yourself from superadmin.";
        }
    } elseif ($action === 'update_user_permissions') {
        $user_id = (int)$_POST['user_id'];
        $overrides = $_POST['permissions'] ?? []; // format: ['perm_id' => '1', 'perm_id2' => '0']
        
        try {
            // Delete existing overrides for this user
            $stmt = $pdo->prepare("DELETE FROM IFW_user_permissions WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Insert new overrides
            if (!empty($overrides)) {
                $insertStmt = $pdo->prepare("INSERT INTO IFW_user_permissions (user_id, permission_id, is_granted) VALUES (?, ?, ?)");
                foreach ($overrides as $perm_id => $is_granted) {
                    if ($is_granted === '1' || $is_granted === '0') {
                        $insertStmt->execute([$user_id, $perm_id, (int)$is_granted]);
                    }
                }
            }
            $success = "User-specific permissions updated successfully.";
        } catch (PDOException $e) {
            $error = "Error updating user permissions.";
        }
    }
}

// Fetch all staff members
$staff_members = [];
try {
    $staff_members = $pdo->query("SELECT id, username, email, role, created_at FROM IFW_users ORDER BY username ASC")->fetchAll();
} catch (Exception $e) {}

// Fetch roles and permissions safely
$roles = [];
$permissions = [];
$user_permissions = [];
try {
    // Auto-create user_permissions table if it doesn't exist yet
    $pdo->exec("CREATE TABLE IF NOT EXISTS IFW_user_permissions (
        user_id INT NOT NULL,
        permission_id INT NOT NULL,
        is_granted TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (user_id, permission_id),
        FOREIGN KEY (user_id) REFERENCES IFW_users(id) ON DELETE CASCADE,
        FOREIGN KEY (permission_id) REFERENCES IFW_permissions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $roles = $pdo->query("SELECT * FROM IFW_roles ORDER BY name")->fetchAll();
    $permissions = $pdo->query("SELECT * FROM IFW_permissions ORDER BY name")->fetchAll();
    
    $up_stmt = $pdo->query("SELECT user_id, permission_id, is_granted FROM IFW_user_permissions");
    while ($row = $up_stmt->fetch()) {
        $user_permissions[$row['user_id']][$row['permission_id']] = (int)$row['is_granted'];
    }
} catch (Exception $e) {}

require_once '../includes/admin_header.php';
require_once '../includes/admin_sidebar.php';
?>

<div class="row">
    <div class="col-12 mb-4 d-flex align-items-center justify-content-between">
        <div>
            <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-user-shield mr-2"></i>Staff & Permission Management</h3>
            <p class="text-muted mb-0">Create staff, agent, and investigator accounts, configure roles, and assign security clearance permissions.</p>
        </div>
        <div>
            <button class="btn btn-warning font-weight-bold text-dark px-4 shadow mr-2" data-toggle="modal" data-target="#createStaffModal">
                <i class="fas fa-user-plus mr-1"></i> Add Staff / Investigator
            </button>
            <button class="btn btn-outline-warning font-weight-bold" data-toggle="modal" data-target="#createRoleModal">
                <i class="fas fa-plus mr-1"></i> Create Custom Role
            </button>
        </div>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success font-weight-bold mb-3"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger font-weight-bold mb-3"><i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- STAFF DIRECTORY SECTION -->
<div class="card shadow-lg bg-dark border-secondary mb-4">
    <div class="card-header bg-dark border-secondary text-warning font-weight-bold">
        <i class="fas fa-users-cog mr-2"></i>Staff, Agent & Attorney Accounts (<?= count($staff_members) ?> Active Accounts)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr class="text-warning" style="border-bottom: 2px solid rgba(254,204,86,0.3);">
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email Address</th>
                        <th>Assigned Role</th>
                        <th>Created Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff_members as $staff): ?>
                        <tr>
                            <td>#<?= $staff['id'] ?></td>
                            <td><strong class="text-white"><?= htmlspecialchars($staff['username']) ?></strong></td>
                            <td><a href="mailto:<?= htmlspecialchars($staff['email']) ?>" class="text-warning"><?= htmlspecialchars($staff['email']) ?></a></td>
                            <td>
                                <span class="badge <?= ($staff['role'] === 'superadmin') ? 'badge-warning text-dark' : (($staff['role'] === 'admin') ? 'badge-info' : 'badge-secondary') ?> px-3 py-1 font-weight-bold" style="font-size: 11px;">
                                    <?= strtoupper($staff['role']) ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($staff['created_at'])) ?></td>
                            <td>
                                <?php if ($staff['id'] !== $_SESSION['admin_id']): ?>
                                    <button class="btn btn-sm btn-outline-warning mr-1" data-toggle="modal" data-target="#editRoleModal<?= $staff['id'] ?>" title="Edit Role"><i class="fas fa-edit"></i> Edit Role</button>
                                    <button class="btn btn-sm btn-outline-info mr-1" data-toggle="modal" data-target="#customPermsModal<?= $staff['id'] ?>" title="Custom Permissions"><i class="fas fa-key"></i></button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to remove staff member <?= htmlspecialchars($staff['username']) ?>?');">
                                        <input type="hidden" name="action" value="delete_staff">
                                        <input type="hidden" name="user_id" value="<?= $staff['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete Account"><i class="fas fa-trash"></i></button>
                                    </form>
                                    
                                    <!-- Custom Permissions Modal -->
                                    <div class="modal fade" id="customPermsModal<?= $staff['id'] ?>" tabindex="-1">
                                      <div class="modal-dialog modal-lg">
                                        <div class="modal-content bg-dark text-white border-info">
                                          <div class="modal-header border-secondary">
                                            <h5 class="modal-title text-info font-weight-bold"><i class="fas fa-key mr-2"></i>Custom Permissions for <?= htmlspecialchars($staff['username']) ?></h5>
                                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                          </div>
                                          <form method="POST">
                                              <div class="modal-body">
                                                <input type="hidden" name="action" value="update_user_permissions">
                                                <input type="hidden" name="user_id" value="<?= $staff['id'] ?>">
                                                <p class="text-muted small">Here you can override the default role permissions for this specific user. Selecting "Grant" gives them the permission regardless of their role. Selecting "Deny" revokes it. Selecting "Default" leaves it to their role.</p>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-dark table-striped mt-3">
                                                        <thead>
                                                            <tr class="text-info">
                                                                <th>Permission</th>
                                                                <th>Description</th>
                                                                <th>Override</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach($permissions as $perm): 
                                                                $current_status = $user_permissions[$staff['id']][$perm['id']] ?? '';
                                                            ?>
                                                            <tr>
                                                                <td class="font-weight-bold text-white"><?= htmlspecialchars($perm['name']) ?></td>
                                                                <td class="text-muted" style="font-size: 12px;"><?= htmlspecialchars($perm['description']) ?></td>
                                                                <td>
                                                                    <select name="permissions[<?= $perm['id'] ?>]" class="form-control form-control-sm bg-dark text-white border-secondary">
                                                                        <option value="" <?= $current_status === '' ? 'selected' : '' ?>>Inherit (Default)</option>
                                                                        <option value="1" <?= $current_status === 1 ? 'selected' : '' ?>>Force Grant</option>
                                                                        <option value="0" <?= $current_status === 0 ? 'selected' : '' ?>>Force Deny</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                              </div>
                                              <div class="modal-footer border-secondary">
                                                <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-info font-weight-bold text-dark px-4">Save Permissions</button>
                                              </div>
                                          </form>
                                        </div>
                                      </div>
                                    </div>
                                    
                                    <!-- Edit Role Modal -->
                                    <div class="modal fade" id="editRoleModal<?= $staff['id'] ?>" tabindex="-1">
                                      <div class="modal-dialog">
                                        <div class="modal-content bg-dark text-white border-warning">
                                          <div class="modal-header border-secondary">
                                            <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-user-edit mr-2"></i>Edit Role for <?= htmlspecialchars($staff['username']) ?></h5>
                                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                          </div>
                                          <form method="POST">
                                              <div class="modal-body">
                                                <input type="hidden" name="action" value="update_staff_role">
                                                <input type="hidden" name="user_id" value="<?= $staff['id'] ?>">
                                                <div class="mb-3">
                                                    <label class="font-weight-bold text-white">Account Security Role <span class="text-warning">*</span></label>
                                                    <select name="role" class="form-control bg-dark text-white border-secondary" required>
                                                        <option value="agent" <?= $staff['role'] == 'agent' ? 'selected' : '' ?>>Agent / Case Investigator</option>
                                                        <option value="admin" <?= $staff['role'] == 'admin' ? 'selected' : '' ?>>Administrator</option>
                                                        <option value="superadmin" <?= $staff['role'] == 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
                                                        <optgroup label="Custom Roles">
                                                        <?php foreach($roles as $r): ?>
                                                            <option value="<?= htmlspecialchars($r['name']) ?>" <?= $staff['role'] == $r['name'] ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($r['name'])) ?></option>
                                                        <?php endforeach; ?>
                                                        </optgroup>
                                                    </select>
                                                </div>
                                              </div>
                                              <div class="modal-footer border-secondary">
                                                <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4">Save Changes</button>
                                              </div>
                                          </form>
                                        </div>
                                      </div>
                                    </div>
                                <?php else: ?>
                                    <span class="badge badge-success px-2 py-1"><i class="fas fa-user-check mr-1"></i> Current Session</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Create Staff Account -->
<div class="modal fade" id="createStaffModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-user-plus mr-2"></i>Create New Staff / Investigator Account</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="create_staff">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold text-white">Full Name <span class="text-warning">*</span></label>
                    <input type="text" name="full_name" class="form-control bg-dark text-white border-secondary" required placeholder="John Doe">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold text-white">Login Username <span class="text-warning">*</span></label>
                    <input type="text" name="username" class="form-control bg-dark text-white border-secondary" required placeholder="johndoe_agent">
                </div>
            </div>
            <div class="mb-3">
                <label class="font-weight-bold text-white">Email Address <span class="text-warning">*</span></label>
                <input type="email" name="email" class="form-control bg-dark text-white border-secondary" required placeholder="agent@ifwglobal.com">
            </div>
            <div class="mb-3">
                <label class="font-weight-bold text-white">Account Security Role <span class="text-warning">*</span></label>
                <select name="role" class="form-control bg-dark text-white border-secondary" required>
                    <option value="agent">Agent / Case Investigator</option>
                    <option value="admin">Administrator</option>
                    <option value="superadmin">Superadmin</option>
                    <optgroup label="Custom Roles">
                    <?php foreach($roles as $r): ?>
                        <option value="<?= htmlspecialchars($r['name']) ?>"><?= htmlspecialchars(ucfirst($r['name'])) ?></option>
                    <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold text-white">Initial Password <span class="text-warning">*</span></label>
                    <input type="password" name="password" class="form-control bg-dark text-white border-secondary" required placeholder="Enter strong password">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold text-white">Initial Security PIN <span class="text-warning">*</span></label>
                    <input type="password" name="pin" class="form-control bg-dark text-white border-secondary" maxlength="6" required placeholder="4-6 digit PIN">
                </div>
            </div>
          </div>
          <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4">Create Staff Account</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Create Role -->
<div class="modal fade" id="createRoleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-shield-alt mr-2"></i>Create Custom Role</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="create_role">
            <div class="mb-3">
                <label class="font-weight-bold text-white">Role Name</label>
                <input type="text" name="role_name" class="form-control bg-dark text-white border-secondary" required placeholder="Senior Investigator">
            </div>
            <div class="mb-3">
                <label class="font-weight-bold text-white">Assign Permissions</label>
                <div class="p-3 border border-secondary rounded" style="max-height: 200px; overflow-y: auto;">
                    <?php if (empty($permissions)): ?>
                        <div class="text-muted small">No permissions defined in system yet.</div>
                    <?php else: ?>
                        <?php foreach($permissions as $perm): ?>
                            <div class="form-check mb-2 d-flex align-items-center">
                                <input type="checkbox" class="form-check-input" id="perm_<?= $perm['id'] ?>" name="permissions[]" value="<?= $perm['id'] ?>" style="width: 18px; height: 18px; cursor: pointer;">
                                <label class="form-check-label text-light ml-2" style="cursor:pointer;" for="perm_<?= $perm['id'] ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $perm['name']))) ?></label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
          </div>
          <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4">Save Role</button>
          </div>
      </form>
    </div>
  </div>
</div>

<?php require_once '../includes/admin_footer.php'; ?>
