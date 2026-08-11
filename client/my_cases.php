<?php
// public/client/my_cases.php
$dir = __DIR__;
while (!file_exists($dir . '/config.php') && $dir !== dirname($dir)) {
    $dir = dirname($dir);
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
require_once $dir . '/includes/currency_helper.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['client_logged_in']) || empty($_SESSION['client_portal_id'])) { 
    unset($_SESSION['client_logged_in'], $_SESSION['client_portal_id']);
    header("Location: /client/login.php"); 
    exit; 
}

$client_id = (int)$_SESSION['client_portal_id'];
$_SESSION['role'] = 'client';
$client_currency = get_client_currency($pdo, $client_id);

// Submit satisfaction rating
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_rating') {
    $case_id = (int)$_POST['case_id'];
    $rating  = max(1, min(5, (int)$_POST['rating']));
    $feedback = trim($_POST['feedback'] ?? '');
    try {
        $pdo->prepare("INSERT INTO IFW_case_ratings (case_id, client_id, rating, feedback) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE rating=VALUES(rating), feedback=VALUES(feedback)")
            ->execute([$case_id, $client_id, $rating, $feedback]);
    } catch(Exception $e) {}
    header("Location: my_cases.php?case_id=$case_id&rated=1"); exit;
}

// Fetch all cases
$cases = [];
try {
    $s = $pdo->prepare("SELECT ca.*, 
        COALESCE(NULLIF(u.full_name, ''), u.username) AS agent_name, 
        u.role AS agent_role,
        u.email AS agent_email,
        u.phone AS agent_phone
        FROM IFW_cases ca 
        LEFT JOIN IFW_users u ON ca.attorney_id = u.id 
        WHERE ca.client_id = ? 
           OR ca.id IN (SELECT case_id FROM IFW_invoices WHERE client_id = ? AND case_id > 0)
        ORDER BY ca.created_at DESC");
    $s->execute([$client_id, $client_id]);
    $cases = $s->fetchAll();
} catch(Exception $e) {}

// Active case view
$active_case = null;
$timeline = [];
$case_rating = null;
$selected_id = isset($_GET['case_id']) ? (int)$_GET['case_id'] : ($cases[0]['id'] ?? 0);

if ($selected_id) {
    foreach ($cases as $c) {
        if ((int)$c['id'] === $selected_id) { $active_case = $c; break; }
    }
    if ($active_case) {
        try {
            $s = $pdo->prepare("SELECT t.*, u.username AS added_by_name FROM IFW_case_timeline t LEFT JOIN IFW_users u ON t.created_by=u.id WHERE t.case_id=? AND t.is_client_visible=1 ORDER BY t.milestone_date ASC, t.created_at ASC");
            $s->execute([$selected_id]);
            $timeline = $s->fetchAll();
        } catch(Exception $e) {}
        try {
            $s = $pdo->prepare("SELECT * FROM IFW_documents WHERE client_id=? ORDER BY uploaded_at DESC");
            $s->execute([$client_id]);
            $vault_docs = $s->fetchAll();
        } catch(Exception $e) {
            $vault_docs = [];
        }
        try {
            $s = $pdo->prepare("SELECT * FROM IFW_case_ratings WHERE case_id=? AND client_id=?");
            $s->execute([$selected_id, $client_id]);
            $case_rating = $s->fetch();
        } catch(Exception $e) {}
    }
}

$app_name = get_setting($pdo, 'app_name', 'IFW Global');
require_once $dir . '/includes/admin_header.php';
require_once $dir . '/includes/admin_sidebar.php';
?>

<style>
.case-sidebar-item { cursor:pointer; border-left:3px solid transparent; transition:all .2s; color: #eee !important; }
.case-sidebar-item:hover, .case-sidebar-item.active { border-left-color:#fecc56; background: rgba(254, 204, 86, 0.12); color: #fff !important; }
.case-sidebar-item .text-dark { color: #fff !important; }
.case-sidebar-item.active .text-dark { color: #fecc56 !important; }
.case-sidebar-item .text-muted { color: #bbb !important; }
.timeline { position:relative; padding-left:30px; }
.timeline::before { content:''; position:absolute; left:10px; top:0; bottom:0; width:2px; background:linear-gradient(to bottom,#fecc56,#e0e0e0); }
.timeline-item { position:relative; margin-bottom:24px; }
.timeline-dot { position:absolute; left:-24px; top:4px; width:16px; height:16px; border-radius:50%; border:2px solid #fff; box-shadow:0 0 0 3px #fecc56; background:#fecc56; z-index:2; }
.timeline-dot.success { box-shadow:0 0 0 3px #28a745; background:#28a745; }
.timeline-dot.danger  { box-shadow:0 0 0 3px #dc3545; background:#dc3545; }
.timeline-dot.info    { box-shadow:0 0 0 3px #17a2b8; background:#17a2b8; }
.timeline-dot.warning { box-shadow:0 0 0 3px #ffc107; background:#ffc107; }
.star-rating .star { font-size:28px; color:#ddd; cursor:pointer; transition:color .15s; }
.star-rating .star:hover, .star-rating .star.active { color:#fecc56; }
.case-meta-row { padding:8px 0; border-bottom:1px solid #f0f0f0; }
.case-meta-row:last-child { border-bottom:none; }
</style>

<div class="row mb-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="font-weight-bold mb-0 text-white"><i class="fas fa-briefcase text-warning mr-2"></i>My Investigation Cases</h4>
                <p class="text-warning small mb-0 font-weight-bold">Track your active and completed recovery cases with forensic milestones</p>
            </div>
            <a href="/client/dashboard.php" class="btn btn-outline-warning text-warning btn-sm font-weight-bold">
                <i class="fas fa-arrow-left mr-1"></i> Return to Dashboard
            </a>
        </div>
    </div>
</div>

<?php if (isset($_GET['rated'])): ?>
    <div class="alert alert-success border-0 shadow-sm"><i class="fas fa-star mr-2"></i>Thank you for your feedback!</div>
<?php endif; ?>

<?php if (empty($cases)): ?>
    <div class="card border-0 shadow-sm text-center py-5">
        <div class="card-body">
            <i class="fas fa-folder-open fa-4x text-muted mb-4 d-block"></i>
            <h5 class="font-weight-bold text-muted">No Cases Opened Yet</h5>
            <p class="text-muted">Once your investigator opens a case for you, it will appear here with full details and progress updates.</p>
        </div>
    </div>
<?php else: ?>
<div class="row">
    <!-- CASE LIST SIDEBAR -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-dark text-warning font-weight-bold py-3">
                <i class="fas fa-list mr-2"></i>All Cases (<?= count($cases) ?>)
            </div>
            <div class="card-body p-0" style="max-height:600px; overflow-y:auto;">
                <?php foreach($cases as $c): ?>
                <?php
                    $s = strtolower($c['status'] ?? 'pending');
                    $badge = ['pending'=>'warning text-dark','in progress'=>'info','active'=>'info','resolved'=>'success','closed'=>'secondary','rejected'=>'danger'][$s] ?? 'secondary';
                ?>
                <a href="my_cases.php?case_id=<?= $c['id'] ?>" class="d-block text-decoration-none">
                    <div class="case-sidebar-item p-3 border-bottom <?= (int)$c['id'] === $selected_id ? 'active' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1 pr-2">
                                <div class="font-weight-bold text-dark small"><?= htmlspecialchars($c['title']) ?></div>
                                <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($c['case_number'] ?? 'Case #'.$c['id']) ?></div>
                                <div class="text-muted" style="font-size:10px;"><?= date('M j, Y', strtotime($c['created_at'])) ?></div>
                            </div>
                            <span class="badge badge-<?= $badge ?>" style="font-size:10px; white-space:nowrap;"><?= htmlspecialchars(ucwords($c['status'] ?? 'Pending')) ?></span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- CASE DETAIL -->
    <div class="col-lg-8">
        <?php if ($active_case): ?>

        <!-- CASE HEADER -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark border-0 py-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small text-uppercase mb-1" style="font-size:10px; color:#aaa !important;">Case Reference Number</div>
                        <h5 class="text-warning font-weight-bold mb-0"><?= htmlspecialchars($active_case['case_number'] ?? 'IFW-'.str_pad($active_case['id'],5,'0',STR_PAD_LEFT)) ?></h5>
                    </div>
                    <?php
                    $s = strtolower($active_case['status'] ?? 'pending');
                    $badge = ['pending'=>'warning text-dark','in progress'=>'info','active'=>'info','resolved'=>'success','closed'=>'secondary','rejected'=>'danger'][$s] ?? 'secondary';
                    ?>
                    <span class="badge badge-<?= $badge ?> px-3 py-2" style="font-size:14px;"><?= htmlspecialchars(ucwords($active_case['status'] ?? 'Pending')) ?></span>
                </div>
            </div>
            <div class="card-body bg-dark text-white">
                <h5 class="font-weight-bold mb-3"><?= htmlspecialchars($active_case['title']) ?></h5>
                <?php if (!empty($active_case['description'])): ?>
                    <p class="text-muted"><?= nl2br(htmlspecialchars($active_case['description'])) ?></p>
                <?php endif; ?>

                <!-- KEY METRICS ROW -->
                <div class="row mt-3">
                    <div class="col-6 col-md-3 mb-3">
                        <div class="text-muted small text-uppercase mb-1" style="font-size:10px;">Opened</div>
                        <div class="font-weight-bold"><?= date('M j, Y', strtotime($active_case['created_at'])) ?></div>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <div class="text-muted small text-uppercase mb-1" style="font-size:10px;">Investigator</div>
                        <div class="font-weight-bold"><?= htmlspecialchars($active_case['agent_name'] ?? 'TBA') ?></div>
                    </div>
                    <?php if (!empty($active_case['case_type'])): ?>
                    <div class="col-6 col-md-3 mb-3">
                        <div class="text-muted small text-uppercase mb-1" style="font-size:10px;">Case Type</div>
                        <div class="font-weight-bold"><?= htmlspecialchars($active_case['case_type']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($active_case['priority'])): ?>
                    <div class="col-6 col-md-3 mb-3">
                        <div class="text-muted small text-uppercase mb-1" style="font-size:10px;">Priority</div>
                        <div class="font-weight-bold text-<?= $active_case['priority']==='Critical'?'danger':($active_case['priority']==='High'?'warning':'light') ?>"><?= $active_case['priority'] ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($active_case['amount_lost']) && $active_case['amount_lost'] > 0): ?>
                    <?php
                    $c_curr = !empty($active_case['currency']) ? strtoupper($active_case['currency']) : 'USD';
                    $loss_pref = convert_currency($active_case['amount_lost'], $c_curr, $client_currency);
                    ?>
                    <div class="col-6 col-md-3 mb-3">
                        <div class="text-muted small text-uppercase mb-1" style="font-size:10px;">Reported Loss</div>
                        <div class="font-weight-bold text-danger">
                            <?= htmlspecialchars($c_curr) ?> <?= number_format($active_case['amount_lost'],2) ?>
                            <?php if ($c_curr !== $client_currency): ?>
                                <br><small class="text-light" style="font-size:11px;">(≈ <?= format_currency($loss_pref, $client_currency) ?>)</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($active_case['amount_recovered']) && $active_case['amount_recovered'] > 0): ?>
                    <?php
                    $c_curr = !empty($active_case['currency']) ? strtoupper($active_case['currency']) : 'USD';
                    $rec_pref = convert_currency($active_case['amount_recovered'], $c_curr, $client_currency);
                    ?>
                    <div class="col-6 col-md-3 mb-3">
                        <div class="text-muted small text-uppercase mb-1" style="font-size:10px;">Recovered</div>
                        <div class="font-weight-bold text-success">
                            <?= htmlspecialchars($c_curr) ?> <?= number_format($active_case['amount_recovered'],2) ?>
                            <?php if ($c_curr !== $client_currency): ?>
                                <br><small class="text-light" style="font-size:11px;">(≈ <?= format_currency($rec_pref, $client_currency) ?>)</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($active_case['court_date'])): ?>
                    <div class="col-6 col-md-3 mb-3">
                        <div class="text-muted small text-uppercase mb-1" style="font-size:10px;">Key Date</div>
                        <div class="font-weight-bold"><?= date('M j, Y', strtotime($active_case['court_date'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PROGRESS TIMELINE -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark border-bottom font-weight-bold py-3 text-warning">
                <i class="fas fa-stream text-warning mr-2"></i>Investigation Timeline & Progress
            </div>
            <div class="card-body py-4">
                <?php if (empty($timeline)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-clock fa-3x text-muted mb-3 d-block"></i>
                        <p class="text-muted">No timeline updates yet. Your investigator will post milestones as the case progresses.</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach($timeline as $t): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot <?= htmlspecialchars($t['status_color'] ?? 'warning') ?>"></div>
                            <div class="card border-0 shadow-sm" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255,255,255,0.1) !important;">
                                <div class="card-body py-3 px-4">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="font-weight-bold mb-0" style="color: #fecc56 !important;"><?= htmlspecialchars($t['milestone_title']) ?></h6>
                                        <small class="text-muted ml-2 text-nowrap" style="color: #bbb !important;"><?= $t['milestone_date'] ? date('M j, Y', strtotime($t['milestone_date'])) : date('M j, Y', strtotime($t['created_at'])) ?></small>
                                    </div>
                                    <?php if (!empty($t['milestone_body'])): ?>
                                        <p class="mb-0 small" style="color: #ddd !important;"><?= nl2br(htmlspecialchars($t['milestone_body'])) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($t['added_by_name'])): ?>
                                        <small class="text-muted d-block mt-2" style="font-size:10px; color: #aaa !important;"><i class="fas fa-user-shield mr-1"></i><?= htmlspecialchars($t['added_by_name']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <!-- Current Status -->
                        <div class="timeline-item">
                            <div class="timeline-dot warning" style="animation: pulse 2s infinite;"></div>
                            <div class="card border-warning shadow-sm" style="background: rgba(254, 204, 86, 0.08); border: 1px solid #fecc56 !important;">
                                <div class="card-body py-2 px-4">
                                    <span class="text-warning font-weight-bold small"><i class="fas fa-circle mr-1"></i>Current Status: <?= htmlspecialchars(ucwords($active_case['status'] ?? 'Pending')) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- DOCUMENT VAULT & SECURE E-SIGNATURES -->
        <div class="card shadow-sm border-0 mb-4 bg-dark text-white border-warning">
            <div class="card-header bg-dark border-bottom font-weight-bold py-3 text-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <i class="fas fa-folder-open mr-2"></i>Document Vault & e-Signatures
                    <span class="badge badge-warning text-dark font-weight-bold ml-2"><?= count($vault_docs) ?> Files</span>
                </div>
                <button type="button" class="btn btn-warning btn-sm font-weight-bold text-dark shadow-sm" data-toggle="modal" data-target="#evidenceUploadModal">
                    <i class="fas fa-cloud-upload-alt mr-1"></i> Upload Case Evidence
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($vault_docs)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-file-pdf fa-3x text-muted mb-3 d-block"></i>
                        <p class="text-muted small">No documents uploaded yet. Your investigator will upload agreements, NDAs, and reports here.</p>
                    </div>
                <?php else: ?>
                    <p class="text-light small mb-3">Below are the files assigned to your profile. Documents requiring cryptographic signature can be e-signed immediately with your 4-digit security PIN.</p>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover table-striped mb-0" style="background:#111; font-size:13px;">
                            <thead>
                                <tr class="text-warning">
                                    <th>File Name</th>
                                    <th>Type</th>
                                    <th>Verification Status</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($vault_docs as $doc): ?>
                                    <tr>
                                        <td class="align-middle">
                                            <?php if (!empty($doc['document_body'])): ?>
                                                <a href="view_document.php?id=<?= $doc['id'] ?>" target="_blank" class="text-warning font-weight-bold text-decoration-none">
                                                    <i class="fas fa-file-alt mr-1"></i> <?= htmlspecialchars($doc['file_name']) ?>
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= BASE_URL . '/' . htmlspecialchars($doc['file_path']) ?>" target="_blank" class="text-warning font-weight-bold text-decoration-none">
                                                    <i class="fas fa-file-download mr-1"></i> <?= htmlspecialchars($doc['file_name']) ?>
                                                </a>
                                            <?php endif; ?>
                                            <br><small class="text-muted"><?= date('M j, Y H:i', strtotime($doc['uploaded_at'])) ?></small>
                                        </td>
                                        <td class="align-middle">
                                            <span class="badge badge-secondary"><?= htmlspecialchars($doc['document_type']) ?></span>
                                        </td>
                                        <td class="align-middle">
                                            <?php if ($doc['requires_signature']): ?>
                                                <?php if ($doc['is_signed']): ?>
                                                    <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Cryptographically Signed</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-signature mr-1"></i> Pending Signature</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge badge-info px-2 py-1"><i class="fas fa-eye mr-1"></i> Reference Only</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle text-right">
                                            <?php if (!empty($doc['document_body'])): ?>
                                                <a href="view_document.php?id=<?= $doc['id'] ?>" target="_blank" class="btn btn-xs btn-outline-warning mr-1" title="View Document">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= BASE_URL . '/' . htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-xs btn-outline-warning mr-1" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($doc['requires_signature'] && !$doc['is_signed']): ?>
                                                <button type="button" class="btn btn-xs btn-warning text-dark font-weight-bold" onclick="openSigningModal(<?= $doc['id'] ?>, '<?= htmlspecialchars(addslashes($doc['file_name'])) ?>')">
                                                    <i class="fas fa-pen mr-1"></i> Sign
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cryptographic Signing Modal -->
        <div class="modal fade" id="signingModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content bg-dark text-white border-warning">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-file-signature mr-2"></i>Secure e-Signature</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form id="signingForm">
                        <div class="modal-body">
                            <input type="hidden" name="document_id" id="signingDocId">
                            <p class="small text-muted">You are signing: <strong class="text-light" id="signingDocName"></strong></p>
                            
                            <div class="form-group mb-3">
                                <label class="font-weight-bold text-light small d-block">Enter 4-Digit Security PIN</label>
                                <input type="password" name="pin" id="signingPin" class="form-control bg-black text-warning border-secondary text-center font-weight-bold font-large" maxlength="4" placeholder="Enter PIN" required pattern="\d{4}">
                                <small class="text-muted mt-1 d-block" style="font-size:10px;">If you have not set a PIN yet, enter any 4-digit code to configure and save it as your security PIN.</small>
                            </div>
                            
                            <div id="signingError" class="alert alert-danger py-2 small" style="display:none;"></div>
                            <div id="signingSuccess" class="alert alert-success py-2 small" style="display:none;"></div>
                        </div>
                        <div class="modal-footer border-secondary">
                            <button type="button" class="btn btn-secondary font-weight-bold btn-sm" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning font-weight-bold text-dark btn-sm"><i class="fas fa-check-double mr-1"></i>Sign Document</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        function openSigningModal(docId, docName) {
            document.getElementById('signingDocId').value = docId;
            document.getElementById('signingDocName').innerText = docName;
            document.getElementById('signingPin').value = '';
            document.getElementById('signingError').style.display = 'none';
            document.getElementById('signingSuccess').style.display = 'none';
            $('#signingModal').modal('show');
        }

        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById('signingForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const docId = document.getElementById('signingDocId').value;
                const pin = document.getElementById('signingPin').value;
                const errDiv = document.getElementById('signingError');
                const succDiv = document.getElementById('signingSuccess');
                
                errDiv.style.display = 'none';
                succDiv.style.display = 'none';
                
                const formData = new FormData();
                formData.append('document_id', docId);
                formData.append('pin', pin);
                
                fetch('/api/sign_document.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        succDiv.innerText = data.message;
                        succDiv.style.display = 'block';
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        errDiv.innerText = data.message;
                        errDiv.style.display = 'block';
                    }
                })
                .catch(error => {
                    errDiv.innerText = 'An error occurred. Please try again.';
                    errDiv.style.display = 'block';
                });
            });
        });
        </script>

        <!-- SATISFACTION RATING (if resolved/closed) -->
        <?php if (in_array(strtolower($active_case['status'] ?? ''), ['resolved','closed','completed'])): ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark border-bottom font-weight-bold py-3 text-warning">
                <i class="fas fa-star text-warning mr-2"></i>Rate Your Experience
            </div>
            <div class="card-body text-center py-4">
                <?php if ($case_rating): ?>
                    <div class="mb-2">
                        <?php for($i=1;$i<=5;$i++): ?>
                            <i class="fas fa-star text-<?= $i <= $case_rating['rating'] ? 'warning' : 'muted' ?>" style="font-size:28px;"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="text-muted">You rated this case <?= $case_rating['rating'] ?>/5 stars. Thank you!</p>
                    <?php if (!empty($case_rating['feedback'])): ?>
                        <div class="alert alert-light border mt-2 text-left small"><?= htmlspecialchars($case_rating['feedback']) ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted mb-3">How satisfied are you with the handling of this case?</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="submit_rating">
                        <input type="hidden" name="case_id" value="<?= $active_case['id'] ?>">
                        <input type="hidden" name="rating" id="ratingValue" value="5">
                        <div class="star-rating mb-3 d-flex justify-content-center">
                            <?php for($i=1;$i<=5;$i++): ?>
                                <i class="fas fa-star star <?= $i<=5?'active':'' ?> mr-1" data-val="<?= $i ?>" onclick="setRating(<?= $i ?>)"></i>
                            <?php endfor; ?>
                        </div>
                        <div class="mb-3">
                            <textarea name="feedback" class="form-control border-secondary" rows="2" placeholder="Share your feedback (optional)..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4">Submit Rating</button>
                    </form>
                    <script>
                    function setRating(val) {
                        document.getElementById('ratingValue').value = val;
                        document.querySelectorAll('.star').forEach(function(s, i) {
                            s.classList.toggle('active', i < val);
                        });
                    }
                    setRating(5);
                    </script>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
            <div class="card border-0 shadow-sm text-center py-5">
                <div class="card-body">
                    <i class="fas fa-mouse-pointer fa-3x text-muted mb-3 d-block"></i>
                    <p class="text-muted">Select a case from the left panel to view details.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Client Case Evidence Upload Modal -->
<div class="modal fade" id="evidenceUploadModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content bg-dark text-white border-warning">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-cloud-upload-alt mr-2"></i>Upload Case Evidence</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="evidenceUploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <p class="small text-muted mb-3">Upload transaction receipts, chat screenshots, wire records, or suspect profile links for your investigator to review.</p>
                    
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light small">Select Document / Evidence File</label>
                        <input type="file" name="vault_file" id="evidenceFileInput" class="form-control-file border border-secondary p-2 rounded w-100 bg-black text-white" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                        <small class="text-muted">Allowed formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB)</small>
                    </div>
                    
                    <div id="evidenceUploadError" class="alert alert-danger py-2 small" style="display:none;"></div>
                    <div id="evidenceUploadSuccess" class="alert alert-success py-2 small" style="display:none;"></div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary font-weight-bold btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" id="evidenceSubmitBtn" class="btn btn-warning font-weight-bold text-dark btn-sm"><i class="fas fa-upload mr-1"></i>Upload to Vault</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var evidenceForm = document.getElementById('evidenceUploadForm');
    if (evidenceForm) {
        evidenceForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var errDiv = document.getElementById('evidenceUploadError');
            var succDiv = document.getElementById('evidenceUploadSuccess');
            var btn = document.getElementById('evidenceSubmitBtn');
            
            errDiv.style.display = 'none';
            succDiv.style.display = 'none';
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Uploading...';
            
            var formData = new FormData(this);
            fetch('/api/vault_upload.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-upload mr-1"></i> Upload to Vault';
                if (data.status === 'success') {
                    succDiv.innerText = 'Evidence uploaded successfully to your vault!';
                    succDiv.style.display = 'block';
                    setTimeout(function() {
                        location.reload();
                    }, 1200);
                } else {
                    errDiv.innerText = data.message || 'Upload failed. Please check file format.';
                    errDiv.style.display = 'block';
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-upload mr-1"></i> Upload to Vault';
                errDiv.innerText = 'Connection error. Please try again.';
                errDiv.style.display = 'block';
            });
        });
    }
});
</script>

<style>
@keyframes pulse { 0%,100%{ box-shadow: 0 0 0 3px #ffc107; } 50%{ box-shadow: 0 0 0 6px rgba(255,193,7,.3); } }
</style>

<?php require_once $dir . '/includes/admin_footer.php'; ?>
