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
           OR ca.client_id IN (SELECT id FROM IFW_clients WHERE email = (SELECT email FROM IFW_clients WHERE id = ? LIMIT 1))
        ORDER BY ca.created_at DESC");
    $s->execute([$client_id, $client_id, $client_id]);
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

html.light-mode .case-sidebar-item,
body.light-mode .case-sidebar-item {
    color: #334155 !important;
    background: #f8fafc;
}
html.light-mode .case-sidebar-item .text-dark,
body.light-mode .case-sidebar-item .text-dark {
    color: #0f172a !important;
}
html.light-mode .case-sidebar-item:hover,
html.light-mode .case-sidebar-item.active,
body.light-mode .case-sidebar-item:hover,
body.light-mode .case-sidebar-item.active {
    background: #fffbeb !important;
    color: #b45309 !important;
    border-left-color: #f59e0b !important;
}
html.light-mode .timeline-item .card,
body.light-mode .timeline-item .card {
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
}
html.light-mode .timeline-item .card h6,
body.light-mode .timeline-item .card h6 {
    color: #b45309 !important;
}
html.light-mode .timeline-item .card p,
body.light-mode .timeline-item .card p {
    color: #334155 !important;
}

.table-portal-wrap {
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: auto;
    border: 1px solid #28303f;
    border-radius: 10px;
    background: #161a23;
}

/* CASE LIFECYCLE PROGRESS TRACKER */
.progress-track-container { background: #161a23; border: 1px solid #28303f; border-radius: 12px; padding: 20px; margin-bottom: 24px; }
.progress-track { display: flex; justify-content: space-between; position: relative; margin-top: 15px; margin-bottom: 5px; }
.progress-track::before { content: ''; position: absolute; top: 18px; left: 20px; right: 20px; height: 4px; background: #262e3d; z-index: 1; border-radius: 2px; }
.progress-bar-fill { position: absolute; top: 18px; left: 20px; height: 4px; background: linear-gradient(90deg, #fecc56, #22c55e); z-index: 1; border-radius: 2px; transition: width 0.6s ease; }
.step-item { position: relative; z-index: 2; text-align: center; flex: 1; min-width: 0; padding: 0 4px; }
.step-icon { width: 34px; height: 34px; border-radius: 50%; background: #1c212c; border: 2px solid #374151; display: flex; align-items: center; justify-content: center; margin: 0 auto 6px; font-size: 12px; font-weight: 700; color: #94a3b8; transition: all 0.3s; }
.step-item.active .step-icon { background: #fecc56; border-color: #fecc56; color: #000; box-shadow: 0 0 14px rgba(254,204,86,0.6); }
.step-item.completed .step-icon { background: #22c55e; border-color: #22c55e; color: #fff; box-shadow: 0 0 10px rgba(34,197,94,0.4); }
.step-title { font-size: 10.5px; font-weight: 600; color: #94a3b8; line-height: 1.2; }
.step-item.active .step-title { color: #fecc56; font-weight: 700; }
.step-item.completed .step-title { color: #22c55e; }

html.light-mode .progress-track-container,
body.light-mode .progress-track-container {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 4px 14px rgba(0,0,0,0.04) !important;
}
html.light-mode .progress-track .step-icon,
body.light-mode .progress-track .step-icon {
    background: #f1f5f9 !important;
    border-color: #cbd5e1 !important;
    color: #64748b !important;
}
html.light-mode .progress-track .step-item.active .step-icon,
body.light-mode .progress-track .step-item.active .step-icon {
    background: #f59e0b !important;
    border-color: #f59e0b !important;
    color: #ffffff !important;
}
html.light-mode #telemetryDetailCard,
body.light-mode #telemetryDetailCard {
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    border-left: 4px solid #f59e0b !important;
    color: #0f172a !important;
}
.table-portal {
    width: 100% !important;
    max-width: 100% !important;
    margin-bottom: 0;
    color: #f1f5f9;
    border-collapse: collapse;
}
.table-portal thead th {
    background: #1f2533 !important;
    color: #fecc56 !important;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-top: none;
    border-bottom: 2px solid #333d4e !important;
    padding: 10px 12px;
}
.table-portal tbody tr {
    background: #161a23;
    border-top: 1px solid #262e3d;
}
.table-portal tbody tr:hover {
    background: #1c2230 !important;
}
.table-portal td {
    padding: 12px;
    vertical-align: middle;
    color: #e2e8f0;
    font-size: 13px;
    word-break: break-word;
}
.table-portal td .btn {
    white-space: nowrap;
    margin: 2px;
}

@media (max-width: 768px) {
    .table-portal thead { display: none !important; }
    .table-portal, .table-portal tbody, .table-portal tr, .table-portal td {
        display: block !important;
        width: 100% !important;
    }
    .table-portal tr {
        margin-bottom: 12px;
        background: #1a202c !important;
        border: 1px solid #2d3748 !important;
        border-radius: 8px !important;
        padding: 8px;
    }
    .table-portal td {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        padding: 8px 10px !important;
        border: none !important;
        border-bottom: 1px solid rgba(255,255,255,0.05) !important;
        text-align: right !important;
    }
    .table-portal td:last-child {
        border-bottom: none !important;
        justify-content: flex-end !important;
    }
    .table-portal td::before {
        content: attr(data-label);
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
        color: #fecc56;
        text-align: left;
        margin-right: 12px;
    }
}
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

        <?php
        // Resolve 5-phase Stage & Percentage for $active_case
        $case_status_norm = strtolower(trim($active_case['status'] ?? 'investigating'));
        $case_stage_step = 2;
        $case_stage_percent = 40;

        if (in_array($case_status_norm, ['received', 'open', 'intake', 'pending'])) {
            $case_stage_step = 1;
            $case_stage_percent = 20;
        } elseif (in_array($case_status_norm, ['investigating', 'in_progress', 'tracing', 'blockchain analysis'])) {
            $case_stage_step = 2;
            $case_stage_percent = 45;
        } elseif (in_array($case_status_norm, ['evidence gathered', 'evidence', 'dossier ready', 'subpoena'])) {
            $case_stage_step = 3;
            $case_stage_percent = 70;
        } elseif (in_array($case_status_norm, ['legal action', 'court filing', 'freezing order', 'injunction'])) {
            $case_stage_step = 4;
            $case_stage_percent = 85;
        } elseif (in_array($case_status_norm, ['recovery', 'settled', 'repatriation', 'closed', 'completed'])) {
            $case_stage_step = 5;
            $case_stage_percent = 100;
        }

        $st1 = !empty($active_case['stage_1_title']) ? $active_case['stage_1_title'] : get_setting($pdo, 'default_stage_1_title', '1. Case Intake & Dossier');
        $st2 = !empty($active_case['stage_2_title']) ? $active_case['stage_2_title'] : get_setting($pdo, 'default_stage_2_title', '2. Blockchain & Asset Tracing');
        $st3 = !empty($active_case['stage_3_title']) ? $active_case['stage_3_title'] : get_setting($pdo, 'default_stage_3_title', '3. Evidence & Subpoena Filing');
        $st4 = !empty($active_case['stage_4_title']) ? $active_case['stage_4_title'] : get_setting($pdo, 'default_stage_4_title', '4. Asset Freezing & Injunction');
        $st5 = !empty($active_case['stage_5_title']) ? $active_case['stage_5_title'] : get_setting($pdo, 'default_stage_5_title', '5. Repatriation & Settlement');

        $sd1 = !empty($active_case['stage_1_desc']) ? $active_case['stage_1_desc'] : get_setting($pdo, 'default_stage_1_desc', "Initial dossier registration, victim statement logging, claim valuation, and KYC regulatory identification under international anti-money laundering (AML) frameworks.");
        $sd2 = !empty($active_case['stage_2_desc']) ? $active_case['stage_2_desc'] : get_setting($pdo, 'default_stage_2_desc', "Advanced heuristics and node cluster mapping tracking stolen assets across multi-chain hops, decentralized bridges, centralized exchanges (CEX), and peer-to-peer liquidity pools.");
        $sd3 = !empty($active_case['stage_3_desc']) ? $active_case['stage_3_desc'] : get_setting($pdo, 'default_stage_3_desc', "Compiling verified chain of custody evidence, forensic audit certificates, and issuing formal legal subpoenas to receiving exchanges and financial custodians.");
        $sd4 = !empty($active_case['stage_4_desc']) ? $active_case['stage_4_desc'] : get_setting($pdo, 'default_stage_4_desc', "Serving Mareva injunctions and judicial asset-freezing orders to lock fraudulent custodial wallets and hold rogue exchange accounts in strict escrow custody.");
        $sd5 = !empty($active_case['stage_5_desc']) ? $active_case['stage_5_desc'] : get_setting($pdo, 'default_stage_5_desc', "Formal liquidation, escrow release verification, and direct digital or bank settlement release into verified client beneficiary accounts.");

        $sp1 = !empty($active_case['stage_1_protocol']) ? $active_case['stage_1_protocol'] : get_setting($pdo, 'default_stage_1_protocol', "KYC-AML / 256-Bit Cryptographic Vault");
        $sp2 = !empty($active_case['stage_2_protocol']) ? $active_case['stage_2_protocol'] : get_setting($pdo, 'default_stage_2_protocol', "On-Chain Heuristic Node Tracking (ETH/BTC/TRC20)");
        $sp3 = !empty($active_case['stage_3_protocol']) ? $active_case['stage_3_protocol'] : get_setting($pdo, 'default_stage_3_protocol', "ISO/IEC 27037 Digital Forensics Admissibility");
        $sp4 = !empty($active_case['stage_4_protocol']) ? $active_case['stage_4_protocol'] : get_setting($pdo, 'default_stage_4_protocol', "Judicial Asset Freezing Order &amp; Custodial Escrow Lock");
        $sp5 = !empty($active_case['stage_5_protocol']) ? $active_case['stage_5_protocol'] : get_setting($pdo, 'default_stage_5_protocol', "Multi-Signature Escrow Disbursement (USDT/EUR/USD)");

        $sj1 = !empty($active_case['stage_1_jurisdiction']) ? $active_case['stage_1_jurisdiction'] : get_setting($pdo, 'default_stage_1_jurisdiction', "International Cross-Border Asset Recovery Desk");
        $sj2 = !empty($active_case['stage_2_jurisdiction']) ? $active_case['stage_2_jurisdiction'] : get_setting($pdo, 'default_stage_2_jurisdiction', "International Cyber Forensics Intelligence Network");
        $sj3 = !empty($active_case['stage_3_jurisdiction']) ? $active_case['stage_3_jurisdiction'] : get_setting($pdo, 'default_stage_3_jurisdiction', "United States Federal Court / High Court of Justice / Inter-State Injunctions");
        $sj4 = !empty($active_case['stage_4_jurisdiction']) ? $active_case['stage_4_jurisdiction'] : get_setting($pdo, 'default_stage_4_jurisdiction', "Financial Conduct Authority / SEC / Interpol Taskforce");
        $sj5 = !empty($active_case['stage_5_jurisdiction']) ? $active_case['stage_5_jurisdiction'] : get_setting($pdo, 'default_stage_5_jurisdiction', "Client Registered Settlement Account");
        ?>

        <!-- CASE RECOVERY PROGRESS LIFECYCLE (INTERACTIVE TIMELINE & LIVE TELEMETRY) -->
        <div class="progress-track-container mb-4 shadow-sm" style="background: linear-gradient(180deg, #161a23 0%, #11141c 100%); border: 1px solid #28303f; border-radius: 12px; padding: 20px;">
            <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                <div>
                    <div class="d-flex align-items-center">
                        <span class="badge badge-success px-2 py-1 mr-2" style="font-size:10px; cursor:help;" data-toggle="tooltip" data-placement="top" title="Continuous forensic node monitoring &amp; legal case updates synchronized with our operational intelligence desk."><i class="fas fa-satellite mr-1"></i>LIVE TELEMETRY</span>
                        <h6 class="font-weight-bold mb-0 text-warning"><i class="fas fa-stream mr-2"></i>Investigation &amp; Asset Recovery Lifecycle</h6>
                    </div>
                    <small class="text-muted">Case Reference: <strong class="text-white"><?= htmlspecialchars($active_case['case_number'] ?? 'IFW-'.$active_case['id']) ?></strong> &bull; <?= htmlspecialchars($active_case['title']) ?></small>
                </div>
                <div class="mt-2 mt-sm-0">
                    <span class="badge badge-warning text-dark font-weight-bold px-3 py-1 shadow-sm" style="font-size:12px;"><i class="fas fa-bolt mr-1"></i><?= $case_stage_percent ?>% Processed</span>
                </div>
            </div>

            <!-- Progress Track with Clickable Interactive Nodes -->
            <div class="progress-track my-3">
                <div class="progress-bar-fill" style="width: <?= max(8, $case_stage_percent - 5) ?>%;"></div>
                
                <div class="step-item <?= $case_stage_step >= 1 ? ($case_stage_step > 1 ? 'completed' : 'active') : '' ?>" onclick="switchMilestoneTelemetry(1)" style="cursor: pointer;" data-toggle="tooltip" data-placement="top" title="Phase 1: Initial dossier registration, KYC identification, and AML regulatory compliance filing.">
                    <div class="step-icon"><i class="fas <?= $case_stage_step > 1 ? 'fa-check' : 'fa-id-card' ?>"></i></div>
                    <div class="step-title"><?= htmlspecialchars($st1) ?></div>
                </div>
                <div class="step-item <?= $case_stage_step >= 2 ? ($case_stage_step > 2 ? 'completed' : 'active') : '' ?>" onclick="switchMilestoneTelemetry(2)" style="cursor: pointer;" data-toggle="tooltip" data-placement="top" title="Phase 2: Deep heuristics and multi-chain cryptographic tracing across blockchain ledgers and exchange clusters.">
                    <div class="step-icon"><i class="fas <?= $case_stage_step > 2 ? 'fa-check' : 'fa-search-dollar' ?>"></i></div>
                    <div class="step-title"><?= htmlspecialchars($st2) ?></div>
                </div>
                <div class="step-item <?= $case_stage_step >= 3 ? ($case_stage_step > 3 ? 'completed' : 'active') : '' ?>" onclick="switchMilestoneTelemetry(3)" style="cursor: pointer;" data-toggle="tooltip" data-placement="top" title="Phase 3: Formal ISO/IEC digital forensics evidence packaging and subpoena filings served to custodians.">
                    <div class="step-icon"><i class="fas <?= $case_stage_step > 3 ? 'fa-check' : 'fa-file-invoice' ?>"></i></div>
                    <div class="step-title"><?= htmlspecialchars($st3) ?></div>
                </div>
                <div class="step-item <?= $case_stage_step >= 4 ? ($case_stage_step > 4 ? 'completed' : 'active') : '' ?>" onclick="switchMilestoneTelemetry(4)" style="cursor: pointer;" data-toggle="tooltip" data-placement="top" title="Phase 4: Judicial asset freezing orders, Mareva injunctions, and custodial escrow locking.">
                    <div class="step-icon"><i class="fas <?= $case_stage_step > 4 ? 'fa-check' : 'fa-gavel' ?>"></i></div>
                    <div class="step-title"><?= htmlspecialchars($st4) ?></div>
                </div>
                <div class="step-item <?= $case_stage_step >= 5 ? 'completed' : '' ?>" onclick="switchMilestoneTelemetry(5)" style="cursor: pointer;" data-toggle="tooltip" data-placement="top" title="Phase 5: Asset liquidation, escrow clearance, and direct repatriation settlement into your verified account.">
                    <div class="step-icon"><i class="fas fa-hand-holding-usd"></i></div>
                    <div class="step-title"><?= htmlspecialchars($st5) ?></div>
                </div>
            </div>

            <!-- Live Telemetry Card (Dynamic & Expandable) -->
            <div id="telemetryDetailCard" class="mt-3 p-3 rounded border border-secondary" style="background: rgba(11, 14, 20, 0.75); border-left: 4px solid #fecc56 !important;">
                <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                    <div class="d-flex align-items-center">
                        <span class="badge badge-warning text-dark font-weight-bold mr-2" id="telemetryPhaseBadge" style="font-size:11px;">PHASE <?= $case_stage_step ?> ACTIVE</span>
                        <strong class="text-white" id="telemetryPhaseTitle"><?= htmlspecialchars(${'st'.$case_stage_step}) ?></strong>
                    </div>
                    <span class="badge badge-secondary small" id="telemetryStatusBadge" data-toggle="tooltip" data-placement="top" title="Phase Verification Status — Real-time progression state verified by cryptographic proof and judicial timestamps."><i class="fas fa-shield-alt mr-1"></i>Operational Status: In Progress</span>
                </div>
                <p class="text-light small mb-3" id="telemetryPhaseDesc" style="line-height: 1.6;">
                    <?= htmlspecialchars(${'sd'.$case_stage_step}) ?>
                </p>

                <div class="row text-muted small g-2 mb-2" id="telemetryMetricsRow">
                    <div class="col-sm-4 mb-2 mb-sm-0">
                        <span class="d-block text-muted" style="font-size: 11px; cursor:help;" data-toggle="tooltip" data-placement="top" title="Cryptographic Ledger Layer — The blockchain protocol, smart contract infrastructure, and address cluster being tracked by our forensics node.">
                            <i class="fas fa-cubes mr-1 text-warning"></i>Cryptographic Protocol <i class="fas fa-info-circle ml-1" style="font-size:10px;"></i>
                        </span>
                        <code class="text-warning" id="telemetryProtocol"><?= htmlspecialchars(${'sp'.$case_stage_step}) ?></code>
                    </div>
                    <div class="col-sm-4 mb-2 mb-sm-0">
                        <span class="d-block text-muted" style="font-size: 11px; cursor:help;" data-toggle="tooltip" data-placement="top" title="Judicial Authority — The international court system, arbitration tribunal, and law enforcement taskforces overseeing legal asset recovery in this jurisdiction.">
                            <i class="fas fa-gavel mr-1 text-warning"></i>Jurisdiction Authority <i class="fas fa-info-circle ml-1" style="font-size:10px;"></i>
                        </span>
                        <strong class="text-white" id="telemetryJurisdiction"><?= htmlspecialchars(${'sj'.$case_stage_step}) ?></strong>
                    </div>
                    <div class="col-sm-4">
                        <span class="d-block text-muted" style="font-size: 11px; cursor:help;" data-toggle="tooltip" data-placement="top" title="Lead Forensic Investigator — Your assigned senior officer actively managing on-chain operations and court filings.">
                            <i class="fas fa-user-shield mr-1 text-warning"></i>Lead Investigator Desk <i class="fas fa-info-circle ml-1" style="font-size:10px;"></i>
                        </span>
                        <strong class="text-warning"><?= htmlspecialchars($active_case['agent_name'] ?? 'Senior Forensic Unit') ?></strong>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top border-secondary">
                    <small class="text-muted"><i class="fas fa-sync-alt fa-spin mr-1"></i> Live Forensic Telemetry synchronized</small>
                    <a href="/client/chat.php" class="btn btn-sm btn-outline-warning font-weight-bold" style="font-size:11.5px;">
                        <i class="fas fa-comments mr-1"></i> Ask Investigator About This Stage
                    </a>
                </div>
            </div>
        </div>

        <script>
        var currentCaseStep = <?= (int)$case_stage_step ?>;
        var milestoneTelemetryData = {
            1: {
                badge: "PHASE 1: INTAKE & IDENTITY VERIFICATION",
                title: "<?= addslashes(htmlspecialchars($st1)) ?>",
                desc: "<?= addslashes(htmlspecialchars($sd1)) ?>",
                status: currentCaseStep > 1 ? '<i class="fas fa-check-circle text-success mr-1"></i>Completed &amp; Sealed' : '<i class="fas fa-spinner fa-spin text-warning mr-1"></i>Active In Processing',
                protocol: "<?= addslashes(htmlspecialchars($sp1)) ?>",
                jurisdiction: "<?= addslashes(htmlspecialchars($sj1)) ?>"
            },
            2: {
                badge: "PHASE 2: CRYPTOGRAPHIC & BLOCKCHAIN TRACING",
                title: "<?= addslashes(htmlspecialchars($st2)) ?>",
                desc: "<?= addslashes(htmlspecialchars($sd2)) ?>",
                status: currentCaseStep > 2 ? '<i class="fas fa-check-circle text-success mr-1"></i>Completed' : (currentCaseStep === 2 ? '<i class="fas fa-spinner fa-spin text-warning mr-1"></i>Active Forensic Telemetry' : '<i class="fas fa-clock text-muted mr-1"></i>Scheduled Deployment'),
                protocol: "<?= addslashes(htmlspecialchars($sp2)) ?>",
                jurisdiction: "<?= addslashes(htmlspecialchars($sj2)) ?>"
            },
            3: {
                badge: "PHASE 3: EVIDENCE DOSSIER & SUBPOENA FILINGS",
                title: "<?= addslashes(htmlspecialchars($st3)) ?>",
                desc: "<?= addslashes(htmlspecialchars($sd3)) ?>",
                status: currentCaseStep > 3 ? '<i class="fas fa-check-circle text-success mr-1"></i>Completed' : (currentCaseStep === 3 ? '<i class="fas fa-spinner fa-spin text-warning mr-1"></i>Active Court Filings' : '<i class="fas fa-clock text-muted mr-1"></i>Pending Phase 2 Seal'),
                protocol: "<?= addslashes(htmlspecialchars($sp3)) ?>",
                jurisdiction: "<?= addslashes(htmlspecialchars($sj3)) ?>"
            },
            4: {
                badge: "PHASE 4: ASSET FREEZING & INJUNCTIONS",
                title: "<?= addslashes(htmlspecialchars($st4)) ?>",
                desc: "<?= addslashes(htmlspecialchars($sd4)) ?>",
                status: currentCaseStep > 4 ? '<i class="fas fa-check-circle text-success mr-1"></i>Completed' : (currentCaseStep === 4 ? '<i class="fas fa-spinner fa-spin text-warning mr-1"></i>Active Injunctions Enforced' : '<i class="fas fa-clock text-muted mr-1"></i>Awaiting Judicial Hearing'),
                protocol: "<?= addslashes(htmlspecialchars($sp4)) ?>",
                jurisdiction: "<?= addslashes(htmlspecialchars($sj4)) ?>"
            },
            5: {
                badge: "PHASE 5: REPATRIATION & SETTLEMENT",
                title: "<?= addslashes(htmlspecialchars($st5)) ?>",
                desc: "<?= addslashes(htmlspecialchars($sd5)) ?>",
                status: currentCaseStep === 5 ? '<i class="fas fa-check-circle text-success mr-1"></i>Repatriation In Progress' : '<i class="fas fa-clock text-muted mr-1"></i>Final Stage of Recovery',
                protocol: "<?= addslashes(htmlspecialchars($sp5)) ?>",
                jurisdiction: "<?= addslashes(htmlspecialchars($sj5)) ?>"
            }
        };

        function switchMilestoneTelemetry(step) {
            var data = milestoneTelemetryData[step];
            if (!data) return;
            
            document.getElementById('telemetryPhaseBadge').textContent = data.badge;
            document.getElementById('telemetryPhaseTitle').textContent = data.title;
            document.getElementById('telemetryPhaseDesc').innerHTML = data.desc;
            document.getElementById('telemetryStatusBadge').innerHTML = data.status;
            document.getElementById('telemetryProtocol').innerHTML = data.protocol;
            document.getElementById('telemetryJurisdiction').innerHTML = data.jurisdiction;
        }
        </script>

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
            <div class="card-body p-3 p-md-4" style="overflow-x: hidden;">
                <?php if (empty($vault_docs)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-file-pdf fa-3x text-muted mb-3 d-block"></i>
                        <p class="text-muted small">No documents uploaded yet. Your investigator will upload agreements, NDAs, and reports here.</p>
                    </div>
                <?php else: ?>
                    <p class="text-light small mb-3">Below are the files assigned to your profile. Documents requiring cryptographic signature can be e-signed immediately with your 4-digit security PIN.</p>
                    <div class="table-portal-wrap" style="overflow-x: auto; max-width: 100%;">
                        <table class="table-portal" style="width: 100%; table-layout: auto;">
                            <thead>
                                <tr class="text-warning">
                                    <th style="min-width: 180px;">File Name</th>
                                    <th style="min-width: 120px;">Type</th>
                                    <th style="min-width: 140px;">Verification Status</th>
                                    <th class="text-right" style="min-width: 130px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($vault_docs as $doc): ?>
                                    <tr>
                                        <td data-label="File Name" style="word-break: break-all; max-width: 250px;">
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
                                        <td data-label="Type">
                                            <span class="badge badge-secondary"><?= htmlspecialchars($doc['document_type']) ?></span>
                                        </td>
                                        <td data-label="Verification Status">
                                            <?php if ($doc['requires_signature']): ?>
                                                <?php if ($doc['is_signed']): ?>
                                                    <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Signed</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-signature mr-1"></i> Pending Signature</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge badge-info px-2 py-1"><i class="fas fa-eye mr-1"></i> Reference Only</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Action" class="text-right">
                                            <div class="d-inline-flex flex-wrap justify-content-end" style="gap: 4px;">
                                                <?php if (!empty($doc['document_body'])): ?>
                                                    <a href="view_document.php?id=<?= $doc['id'] ?>" target="_blank" class="btn btn-sm btn-outline-warning" title="View Document">
                                                        <i class="fas fa-eye mr-1"></i> View
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?= BASE_URL . '/' . htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-warning" title="Download">
                                                        <i class="fas fa-download mr-1"></i> Download
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($doc['requires_signature'] && !$doc['is_signed']): ?>
                                                    <button type="button" class="btn btn-sm btn-warning text-dark font-weight-bold" onclick="openSigningModal(<?= $doc['id'] ?>, '<?= htmlspecialchars(addslashes($doc['file_name'])) ?>')">
                                                        <i class="fas fa-pen mr-1"></i> Sign Now
                                                    </button>
                                                <?php endif; ?>
                                            </div>
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
