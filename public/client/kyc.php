<?php
// public/client/kyc.php
$dir = __DIR__;
while (!file_exists($dir . '/config.php') && $dir !== dirname($dir)) {
    $dir = dirname($dir);
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['client_logged_in'])) { header("Location: /client/login.php"); exit; }

$client_id = $_SESSION['client_portal_id'] ?? 0;
$_SESSION['role'] = 'client';
$error = null;

// Check existing submission
$submission = null;
try {
    $s = $pdo->prepare("SELECT * FROM IFW_kyc_submissions WHERE client_id=? ORDER BY submitted_at DESC LIMIT 1");
    $s->execute([$client_id]);
    $submission = $s->fetch();
} catch(Exception $e) {}

// Fetch dynamic KYC fields
$fields = [];
try {
    $fields = $pdo->query("SELECT * FROM IFW_kyc_fields ORDER BY sort_order ASC, id ASC")->fetchAll();
} catch(Exception $e) {}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kyc_data = [];
    $upload_dir = is_dir($dir . '/public') ? $dir . '/public/uploads/kyc/' : $dir . '/uploads/kyc/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    foreach ($fields as $field) {
        $name = $field['field_name'];
        if ($field['field_type'] === 'file') {
            if (isset($_FILES[$name])) {
                $paths = [];
                $files_arr = isset($_FILES[$name]['name'][0]) ? $_FILES[$name] : [
                    'name'     => [$_FILES[$name]['name']],
                    'tmp_name' => [$_FILES[$name]['tmp_name']],
                    'error'    => [$_FILES[$name]['error']],
                    'size'     => [$_FILES[$name]['size']],
                ];
                foreach ($files_arr['name'] as $k => $fname) {
                    if ($files_arr['error'][$k] == 0 && $files_arr['size'][$k] > 0) {
                        $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg','jpeg','png','pdf','doc','docx'])) {
                            $newname = 'kyc_'.$client_id.'_'.$name.'_'.time().'_'.$k.'.'.$ext;
                            if (move_uploaded_file($files_arr['tmp_name'][$k], $upload_dir.$newname)) {
                                $paths[] = 'uploads/kyc/'.$newname;
                            }
                        }
                    }
                }
                if (!empty($paths)) $kyc_data[$name] = implode(', ', $paths);
            }
        } elseif ($field['field_type'] === 'select' || $field['field_type'] === 'country') {
            $kyc_data[$name] = htmlspecialchars($_POST[$name] ?? '');
        } else {
            $kyc_data[$name] = trim($_POST[$name] ?? '');
        }
    }

    try {
        if ($submission && in_array($submission['status'], ['Pending','Rejected'])) {
            $new_status = 'Pending';
            $pdo->prepare("UPDATE IFW_kyc_submissions SET submission_data=?, status=?, rejection_reason=NULL, updated_at=CURRENT_TIMESTAMP WHERE id=?")
                ->execute([json_encode($kyc_data), $new_status, $submission['id']]);
        } else {
            $pdo->prepare("INSERT INTO IFW_kyc_submissions (client_id, submission_data, status) VALUES (?,?,'Pending')")
                ->execute([$client_id, json_encode($kyc_data)]);
        }
        // Notify admin
        try {
            $admins = $pdo->query("SELECT id FROM IFW_users WHERE role IN ('admin','superadmin')")->fetchAll();
            foreach($admins as $a) {
                $pdo->prepare("INSERT INTO IFW_notifications (client_id, type, title, body, icon, link) VALUES (?,?,?,?,?,?)")
                    ->execute([-$a['id'], 'kyc', 'KYC Submission Received', "Client #{$client_id} submitted identity verification documents.", 'id-card', '/admin/kyc_review.php']);
            }
        } catch(Exception $e) {}
        header("Location: /client/kyc.php?success=1"); exit;
    } catch(Exception $e) {
        $error = "Submission failed. Please try again.";
    }
}

// Countries list
$countries = ["Afghanistan","Albania","Algeria","Andorra","Angola","Antigua and Barbuda","Argentina","Armenia","Australia","Austria","Azerbaijan","Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bhutan","Bolivia","Bosnia and Herzegovina","Botswana","Brazil","Brunei","Bulgaria","Burkina Faso","Burundi","Cabo Verde","Cambodia","Cameroon","Canada","Central African Republic","Chad","Chile","China","Colombia","Comoros","Congo (DRC)","Congo (Republic)","Costa Rica","Croatia","Cuba","Cyprus","Czech Republic","Denmark","Djibouti","Dominica","Dominican Republic","Ecuador","Egypt","El Salvador","Equatorial Guinea","Eritrea","Estonia","Eswatini","Ethiopia","Fiji","Finland","France","Gabon","Gambia","Georgia","Germany","Ghana","Greece","Grenada","Guatemala","Guinea","Guinea-Bissau","Guyana","Haiti","Honduras","Hungary","Iceland","India","Indonesia","Iran","Iraq","Ireland","Israel","Italy","Jamaica","Japan","Jordan","Kazakhstan","Kenya","Kiribati","Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon","Lesotho","Liberia","Libya","Liechtenstein","Lithuania","Luxembourg","Madagascar","Malawi","Malaysia","Maldives","Mali","Malta","Marshall Islands","Mauritania","Mauritius","Mexico","Micronesia","Moldova","Monaco","Mongolia","Montenegro","Morocco","Mozambique","Myanmar","Namibia","Nauru","Nepal","Netherlands","New Zealand","Nicaragua","Niger","Nigeria","North Korea","North Macedonia","Norway","Oman","Pakistan","Palau","Palestine","Panama","Papua New Guinea","Paraguay","Peru","Philippines","Poland","Portugal","Qatar","Romania","Russia","Rwanda","Saint Kitts and Nevis","Saint Lucia","Saint Vincent and the Grenadines","Samoa","San Marino","Sao Tome and Principe","Saudi Arabia","Senegal","Serbia","Seychelles","Sierra Leone","Singapore","Slovakia","Slovenia","Solomon Islands","Somalia","South Africa","South Korea","South Sudan","Spain","Sri Lanka","Sudan","Suriname","Sweden","Switzerland","Syria","Taiwan","Tajikistan","Tanzania","Thailand","Timor-Leste","Togo","Tonga","Trinidad and Tobago","Tunisia","Turkey","Turkmenistan","Tuvalu","Uganda","Ukraine","United Arab Emirates","United Kingdom","United States","Uruguay","Uzbekistan","Vanuatu","Vatican City","Venezuela","Vietnam","Yemen","Zambia","Zimbabwe"];

require_once $dir . '/includes/admin_header.php';
require_once $dir . '/includes/admin_sidebar.php';
?>

<style>
.kyc-step-card { border-left: 4px solid #fecc56; }
.file-drop-zone { border: 2px dashed #555; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; transition: all .2s; }
.file-drop-zone:hover { border-color: #fecc56; background: rgba(254,204,86,.05); }
.file-drop-zone.has-files { border-color: #28a745; background: rgba(40,167,69,.05); }
</style>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="font-weight-bold mb-0"><i class="fas fa-shield-alt text-warning mr-2"></i>Identity Verification (KYC)</h4>
            <p class="text-muted small mb-0">Secure verification in compliance with global AML/CFT standards</p>
        </div>
        <a href="/client/dashboard.php" class="btn btn-outline-dark btn-sm font-weight-bold">
            <i class="fas fa-arrow-left mr-1"></i> Dashboard
        </a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success border-0 shadow text-center py-4 mb-4">
    <i class="fas fa-check-circle fa-3x mb-3 text-success d-block"></i>
    <h5 class="font-weight-bold">Documents Submitted Successfully</h5>
    <p class="mb-0 text-muted">Our compliance team will review your documents within 1–2 business days. You will be notified of the outcome.</p>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger border-0 shadow"><i class="fas fa-exclamation-triangle mr-2"></i><?= $error ?></div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">

        <!-- STATUS PANEL -->
        <?php if ($submission && $submission['status'] === 'Approved'): ?>
        <div class="card bg-dark border-success shadow-lg text-center py-5 mb-4">
            <div class="card-body">
                <i class="fas fa-user-check text-success fa-4x mb-4 d-block"></i>
                <h3 class="text-success font-weight-bold">Identity Verified</h3>
                <p class="text-muted mb-2">Your identity has been fully verified and approved.</p>
                <span class="badge badge-success px-4 py-2" style="font-size:14px;">
                    <i class="fas fa-check mr-1"></i> Verified on <?= date('M j, Y', strtotime($submission['reviewed_at'] ?? $submission['submitted_at'])) ?>
                </span>
            </div>
        </div>
        <?php return; endif; ?>

        <?php if ($submission && $submission['status'] === 'Pending' && !isset($_GET['success'])): ?>
        <div class="alert border-warning bg-dark text-center py-4 shadow mb-4">
            <i class="fas fa-hourglass-half text-warning fa-2x mb-2 d-block"></i>
            <h5 class="text-warning font-weight-bold">Verification Under Review</h5>
            <p class="text-light mb-2">Your submission is being reviewed by our compliance team. You may update your submission below before it's approved.</p>
            <span class="badge badge-warning text-dark px-3 py-1">Submitted <?= date('M j, Y', strtotime($submission['submitted_at'])) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($submission && $submission['status'] === 'Rejected' && !isset($_GET['success'])): ?>
        <div class="card border-danger bg-dark shadow mb-4">
            <div class="card-body py-4">
                <h5 class="text-danger font-weight-bold"><i class="fas fa-times-circle mr-2"></i>Verification Rejected — Resubmission Required</h5>
                <p class="text-light mb-0"><strong>Reason:</strong> <?= htmlspecialchars($submission['rejection_reason'] ?: 'Please resubmit with clearer, legible documents.') ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- FORM -->
        <?php if (!$submission || in_array($submission['status'] ?? '', ['Pending','Rejected']) || !isset($_GET['success'])): ?>
        <?php if (!($submission && $submission['status'] === 'Approved')): ?>
        <div class="card bg-dark border-secondary shadow-lg">
            <div class="card-header bg-dark border-secondary py-3">
                <h5 class="text-warning font-weight-bold mb-0"><i class="fas fa-upload mr-2"></i>
                    <?= $submission ? 'Update Your Submission' : 'Submit Verification Documents' ?>
                </h5>
                <small class="text-muted">Fields marked <span class="text-danger">*</span> are required</small>
            </div>
            <div class="card-body py-4">
                <?php if (empty($fields)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-cog fa-3x mb-3 d-block"></i>
                        <p>Verification form is being configured. Please check back shortly.</p>
                    </div>
                <?php else: ?>
                <form method="POST" enctype="multipart/form-data" id="kycForm">
                    <div class="row">
                        <?php foreach ($fields as $field): ?>
                        <?php
                            $f_type = $field['field_type'];
                            $f_name = htmlspecialchars($field['field_name']);
                            $f_label = htmlspecialchars($field['field_label']);
                            $f_req = $field['is_required'];
                            $f_opts = $field['field_options'] ?? '';
                            $col = in_array($f_type, ['file','textarea']) ? '12' : '6';
                        ?>
                        <div class="col-md-<?= $col ?> mb-4">
                            <label class="font-weight-bold text-light d-block mb-2">
                                <?= $f_label ?><?= $f_req ? ' <span class="text-danger">*</span>' : ' <span class="text-muted small">(Optional)</span>' ?>
                            </label>

                            <?php if ($f_type === 'file'): ?>
                                <div class="file-drop-zone" id="drop_<?= $f_name ?>" onclick="document.getElementById('file_<?= $f_name ?>').click()">
                                    <i class="fas fa-cloud-upload-alt fa-2x text-warning mb-2 d-block"></i>
                                    <p class="text-muted mb-1 small">Click to upload or drag & drop files here</p>
                                    <small class="text-muted">JPG, PNG, PDF, DOC accepted · Multiple files allowed</small>
                                    <div id="preview_<?= $f_name ?>" class="mt-2"></div>
                                </div>
                                <input type="file" name="<?= $f_name ?>[]" id="file_<?= $f_name ?>" <?= $f_req ? 'required' : '' ?> accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" multiple style="display:none;"
                                    onchange="updateDropZone('<?= $f_name ?>', this)">

                            <?php elseif ($f_type === 'textarea'): ?>
                                <textarea name="<?= $f_name ?>" class="form-control bg-dark text-white border-secondary" rows="3" <?= $f_req ? 'required' : '' ?>></textarea>

                            <?php elseif ($f_type === 'select' && !empty($f_opts)): ?>
                                <select name="<?= $f_name ?>" class="form-control bg-dark text-white border-secondary" <?= $f_req ? 'required' : '' ?>>
                                    <option value="">Select...</option>
                                    <?php foreach (explode(',', $f_opts) as $opt): ?>
                                        <option value="<?= htmlspecialchars(trim($opt)) ?>"><?= htmlspecialchars(trim($opt)) ?></option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif ($f_type === 'country'): ?>
                                <select name="<?= $f_name ?>" class="form-control bg-dark text-white border-secondary" <?= $f_req ? 'required' : '' ?>>
                                    <option value="">Select country...</option>
                                    <?php foreach ($countries as $c): ?>
                                        <option><?= htmlspecialchars($c) ?></option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif ($f_type === 'date'): ?>
                                <input type="date" name="<?= $f_name ?>" class="form-control bg-dark text-white border-secondary" <?= $f_req ? 'required' : '' ?>>

                            <?php elseif ($f_type === 'tel'): ?>
                                <input type="tel" name="<?= $f_name ?>" class="form-control bg-dark text-white border-secondary" placeholder="+1 234 567 8900" <?= $f_req ? 'required' : '' ?>>

                            <?php elseif ($f_type === 'email'): ?>
                                <input type="email" name="<?= $f_name ?>" class="form-control bg-dark text-white border-secondary" <?= $f_req ? 'required' : '' ?>>

                            <?php elseif ($f_type === 'number'): ?>
                                <input type="number" name="<?= $f_name ?>" class="form-control bg-dark text-white border-secondary" <?= $f_req ? 'required' : '' ?>>

                            <?php else: ?>
                                <input type="text" name="<?= $f_name ?>" class="form-control bg-dark text-white border-secondary" <?= $f_req ? 'required' : '' ?>>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-4 pt-3 border-top border-secondary d-flex justify-content-between align-items-center">
                        <small class="text-muted"><i class="fas fa-lock mr-1"></i>All submissions are encrypted and handled with strict confidentiality.</small>
                        <button type="submit" class="btn btn-warning font-weight-bold text-dark px-5 py-2 shadow-lg">
                            <i class="fas fa-shield-alt mr-2"></i>
                            <?= $submission ? 'Update Submission' : 'Submit for Verification' ?>
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<script>
function updateDropZone(name, input) {
    var zone = document.getElementById('drop_' + name);
    var preview = document.getElementById('preview_' + name);
    var count = input.files.length;
    if (count > 0) {
        zone.classList.add('has-files');
        var html = '<div class="mt-2">';
        for (var i = 0; i < count; i++) {
            html += '<span class="badge badge-success mr-1 mb-1"><i class="fas fa-file mr-1"></i>' + input.files[i].name + '</span>';
        }
        html += '</div>';
        preview.innerHTML = html;
    }
}
// Drag and drop
document.querySelectorAll('.file-drop-zone').forEach(function(zone) {
    zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.style.borderColor = '#fecc56'; });
    zone.addEventListener('dragleave', function() { zone.style.borderColor = '#555'; });
    zone.addEventListener('drop', function(e) {
        e.preventDefault();
        var id = zone.id.replace('drop_', '');
        var input = document.getElementById('file_' + id);
        input.files = e.dataTransfer.files;
        updateDropZone(id, input);
    });
});
</script>

<?php require_once $dir . '/includes/admin_footer.php'; ?>
