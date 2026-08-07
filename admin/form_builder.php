<?php
// admin/form_builder.php
require_once '../config.php';
require_once '../includes/functions.php';
require_superadmin();

// Handle form field addition/updating/deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_field') {
        $stmt = $pdo->prepare("INSERT INTO IFW_form_fields (field_name, field_label, field_type, field_options, is_required, display_order) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            trim($_POST['field_name']),
            trim($_POST['field_label']),
            trim($_POST['field_type']),
            trim($_POST['field_options']) ?: null,
            isset($_POST['is_required']) ? 1 : 0,
            (int)$_POST['display_order']
        ]);
        header("Location: form_builder.php?success=1");
        exit;
    } elseif ($_POST['action'] == 'delete_field') {
        $stmt = $pdo->prepare("DELETE FROM IFW_form_fields WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        header("Location: form_builder.php?success=1");
        exit;
    } elseif ($_POST['action'] == 'update_settings') {
        $stmt = $pdo->prepare("UPDATE IFW_form_settings SET setting_value = ? WHERE setting_key = 'recipient_email'");
        $stmt->execute([trim($_POST['recipient_email'])]);
        
        $stmt = $pdo->prepare("UPDATE IFW_form_settings SET setting_value = ? WHERE setting_key = 'success_message'");
        $stmt->execute([trim($_POST['success_message'])]);
        header("Location: form_builder.php?success=1");
        exit;
    }
}

$fields = $pdo->query("SELECT * FROM IFW_form_fields ORDER BY display_order ASC")->fetchAll();
$recipient = $pdo->query("SELECT setting_value FROM IFW_form_settings WHERE setting_key = 'recipient_email'")->fetchColumn();
$success_msg = $pdo->query("SELECT setting_value FROM IFW_form_settings WHERE setting_key = 'success_message'")->fetchColumn();
?>

<?php require_once '../includes/admin_header.php'; ?>
<?php require_once '../includes/admin_sidebar.php'; ?>

<div class="row">
    <div class="col-12 mb-4 d-flex align-items-center justify-content-between">
        <div>
            <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-tasks mr-2"></i>Dynamic Form Builder</h3>
            <p class="text-muted mb-0">Customise the fields and notification rules for front-end enquiry forms.</p>
        </div>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success bg-success text-white border-0 shadow-sm mb-4">
        <i class="fas fa-check-circle mr-2"></i>Form settings saved successfully!
    </div>
<?php endif; ?>

<div class="row">
    <!-- Existing Fields List -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm border-secondary h-100">
            <div class="card-header bg-dark text-warning border-secondary d-flex align-items-center justify-content-between">
                <span class="font-weight-bold">Active Form Fields</span>
            </div>
            <div class="card-body bg-dark text-white p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0 align-middle">
                        <thead style="background-color: #2a2526; color: #fecc56;">
                            <tr>
                                <th>Order</th>
                                <th>Label</th>
                                <th>Field Name</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fields as $f): ?>
                                <tr>
                                    <td><?= $f['display_order'] ?></td>
                                    <td><strong class="text-white"><?= htmlspecialchars($f['field_label']) ?></strong></td>
                                    <td><code><?= htmlspecialchars($f['field_name']) ?></code></td>
                                    <td><span class="badge badge-secondary"><?= htmlspecialchars($f['field_type']) ?></span></td>
                                    <td>
                                        <?php if ($f['is_required']): ?>
                                            <span class="badge badge-danger">Required</span>
                                        <?php else: ?>
                                            <span class="badge badge-dark">Optional</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this field?');">
                                            <input type="hidden" name="action" value="delete_field">
                                            <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt"></i> Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add New Field Form -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-secondary mb-4">
            <div class="card-header bg-dark text-warning border-secondary font-weight-bold">
                Add New Form Field
            </div>
            <div class="card-body bg-dark text-white">
                <form method="POST">
                    <input type="hidden" name="action" value="add_field">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Field Label</label>
                        <input type="text" name="field_label" class="form-control bg-secondary text-white border-0" required placeholder="e.g. Phone Number">
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Field Identifier (Name)</label>
                        <input type="text" name="field_name" class="form-control bg-secondary text-white border-0" required placeholder="e.g. phone">
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Field Type</label>
                        <select name="field_type" class="form-control bg-secondary text-white border-0">
                            <option value="text">Text Input</option>
                            <option value="email">Email Address</option>
                            <option value="tel">Phone Input</option>
                            <option value="textarea">Textarea (Multiline)</option>
                            <option value="select">Dropdown Select</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Options (Comma separated for dropdown)</label>
                        <input type="text" name="field_options" class="form-control bg-secondary text-white border-0" placeholder="Option 1, Option 2">
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Display Order</label>
                        <input type="number" name="display_order" class="form-control bg-secondary text-white border-0" value="<?= count($fields) + 1 ?>">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_required" value="1" id="reqCheck" checked>
                        <label class="form-check-label text-light font-weight-bold" for="reqCheck">Mandatory Field</label>
                    </div>
                    <button type="submit" class="btn btn-warning font-weight-bold text-dark w-100">Add Field</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-secondary">
            <div class="card-header bg-dark text-warning border-secondary font-weight-bold">
                Notification Recipient
            </div>
            <div class="card-body bg-dark text-white">
                <form method="POST">
                    <input type="hidden" name="action" value="update_settings">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Alert Recipient Email</label>
                        <input type="email" name="recipient_email" class="form-control bg-secondary text-white border-0" value="<?= htmlspecialchars($recipient ?? 'investigations@ifwglobal.com') ?>" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Success Message</label>
                        <textarea name="success_message" class="form-control bg-secondary text-white border-0" rows="2" required><?= htmlspecialchars($success_msg ?? 'Thank you. Your consultation request has been logged.') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-warning font-weight-bold w-100">Save Notification Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/admin_footer.php'; ?>
