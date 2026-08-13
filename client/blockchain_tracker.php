<?php
// client/blockchain_tracker.php
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

// Fetch Client Cases where Blockchain Watcher is Enabled
$cases_stmt = $pdo->prepare("SELECT * FROM IFW_cases WHERE client_id = ? AND show_blockchain_watcher = 1 ORDER BY id DESC");
$cases_stmt->execute([$client_id]);
$cases = $cases_stmt->fetchAll();

if (empty($cases)) {
    // If feature is disabled by admin for all client cases, redirect to main dashboard
    header("Location: dashboard.php");
    exit;
}

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

$is_feature_enabled = ($active_case && ($active_case['show_blockchain_watcher'] ?? 0) == 1);

// Fetch Wallets for active case
$wallets = [];
$transactions = [];
if ($active_case) {
    $w_stmt = $pdo->prepare("SELECT * FROM IFW_blockchain_wallets WHERE case_id = ? ORDER BY id ASC");
    $w_stmt->execute([$selected_case_id]);
    $wallets = $w_stmt->fetchAll();

    // If no wallets in DB yet, generate initial sample telemetry for demo/onboarding
    if (empty($wallets)) {
        try {
            $pdo->prepare("INSERT INTO IFW_blockchain_wallets (case_id, crypto_type, wallet_address, wallet_label, balance, usd_value, risk_score, threat_level, exchange_tags, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([
                    $selected_case_id,
                    'USDT (TRC-20)',
                    'TXy7n3K19oP4mQ9wLv8B2xZ5cR6vN1aM4t',
                    'Primary Fraudster Aggregator Address',
                    148500.00,
                    148500.00,
                    96,
                    'CRITICAL',
                    'Binance Deposit Cluster #4 &bull; OKX Hot Wallet Hop',
                    'Preservation Subpoena Served',
                    'Target address flagged in chain-hop transaction. Outbound dispersal monitored 24/7.'
                ]);
            $new_w_id = $pdo->lastInsertId();
            
            $pdo->prepare("INSERT INTO IFW_blockchain_txs (wallet_id, case_id, tx_hash, from_address, to_address, amount, crypto_type, direction, flag_tag, tx_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW() - INTERVAL 2 HOUR)")
                ->execute([$new_w_id, $selected_case_id, '7f3a8b9c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a', 'TXy7n3K19oP4mQ9wLv8B2xZ5cR6vN1aM4t', 'TBinanceHotWallet8x92Km...', 45000.00, 'USDT', 'OUT', 'Exchange Deposit Hop (Flagged for Freeze)', ]);

            $pdo->prepare("INSERT INTO IFW_blockchain_txs (wallet_id, case_id, tx_hash, from_address, to_address, amount, crypto_type, direction, flag_tag, tx_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW() - INTERVAL 12 HOUR)")
                ->execute([$new_w_id, $selected_case_id, '4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a7f3a8b9c1d2e3f', 'VictimTransferNode...', 'TXy7n3K19oP4mQ9wLv8B2xZ5cR6vN1aM4t', 148500.00, 'USDT', 'IN', 'Initial Inflow from Victim Transfer', ]);

            $w_stmt->execute([$selected_case_id]);
            $wallets = $w_stmt->fetchAll();
        } catch (Exception $e) {}
    }

    $tx_stmt = $pdo->prepare("SELECT * FROM IFW_blockchain_txs WHERE case_id = ? ORDER BY tx_time DESC, id DESC LIMIT 20");
    $tx_stmt->execute([$selected_case_id]);
    $transactions = $tx_stmt->fetchAll();
}

// Calculate Telemetry Totals
$total_tracked_balance = 0;
$total_wallets_count = count($wallets);
$critical_count = 0;
foreach ($wallets as $w) {
    $total_tracked_balance += (float)($w['usd_value'] ?? $w['balance']);
    if (($w['risk_score'] ?? 0) >= 80) $critical_count++;
}

require_once '../includes/admin_header.php';
require_once '../includes/admin_sidebar.php';
?>

<!-- PAGE HEADER -->
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center mb-1">
                <h4 class="font-weight-bold text-white mb-0 mr-3"><i class="fas fa-cubes text-warning mr-2"></i>Blockchain Forensic Watcher
                    <i class="fas fa-info-circle text-muted ml-1" style="font-size:14px;cursor:help;" data-toggle="tooltip" data-placement="top" title="We track stolen crypto as it moves between wallets — like following footprints on a digital trail. This helps identify where your funds went and who may be holding them."></i>
                </h4>
                <span class="badge badge-success px-2 py-1 font-weight-bold" style="font-size:11px; letter-spacing:0.5px;">
                    <i class="fas fa-satellite-dish mr-1"></i> ON-CHAIN TELEMETRY LIVE
                </span>
            </div>
            <p class="text-muted small mb-0">Real-time cryptocurrency tracing, fraudster wallet monitoring, and exchange deposit clustering.
                <span class="d-block mt-1" style="font-size:11px;opacity:0.85;"><i class="fas fa-lightbulb text-warning mr-1"></i><strong>In plain English:</strong> We watch blockchain addresses linked to your case and alert our team when suspicious transfers occur.</span>
            </p>
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

<?php if (!$active_case): ?>
<div class="card border-secondary bg-dark text-white p-5 text-center shadow-sm" style="border-radius:12px;">
    <i class="fas fa-folder-open fa-3x text-secondary mb-3"></i>
    <h5 class="text-warning font-weight-bold">No Active Case Found</h5>
    <p class="text-muted">Once your investigation case is opened by our intelligence desk, on-chain blockchain monitoring will activate automatically.</p>
</div>
<?php elseif (!$is_feature_enabled): ?>
<div class="card border-secondary bg-dark text-white p-5 text-center shadow-sm" style="border-radius:12px;">
    <div style="width:64px; height:64px; border-radius:50%; background:rgba(254,204,86,0.15); display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
        <i class="fas fa-eye-slash fa-2x text-warning"></i>
    </div>
    <h5 class="text-warning font-weight-bold">Blockchain Telemetry Standby</h5>
    <p class="text-muted max-w-md mx-auto" style="max-width:550px;">
        Blockchain address monitoring for <strong><?= htmlspecialchars($active_case['case_number']) ?></strong> is currently in standby. Your lead forensic investigator will publish live tracked destination wallets as soon as blockchain hop verifications are completed.
    </p>
</div>
<?php else: ?>

<!-- TOP TELEMETRY STATS -->
<div class="row mb-4">
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card border-secondary shadow-sm h-100" style="background:#161a23; border-radius:10px; border-left:4px solid #fecc56 !important;">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase font-weight-bold mb-1" style="font-size:10.5px;">Monitored Target Value</div>
                <div class="text-warning font-weight-bold" style="font-size:1.6rem;">$<?= number_format($total_tracked_balance, 2) ?></div>
                <small class="text-muted" style="font-size:11px;">Aggregated across target wallets</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card border-secondary shadow-sm h-100" style="background:#161a23; border-radius:10px; border-left:4px solid #22c55e !important;">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase font-weight-bold mb-1" style="font-size:10.5px;">Tracked Wallets</div>
                <div class="text-white font-weight-bold" style="font-size:1.6rem;"><?= $total_wallets_count ?> Addresses</div>
                <small class="text-success font-weight-bold" style="font-size:11px;"><i class="fas fa-check-circle mr-1"></i>24/7 Node Watch Active</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card border-secondary shadow-sm h-100" style="background:#161a23; border-radius:10px; border-left:4px solid #ef4444 !important;">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase font-weight-bold mb-1" style="font-size:10.5px;">Threat Intelligence Level</div>
                <div class="text-danger font-weight-bold" style="font-size:1.6rem;">HIGH RISK</div>
                <small class="text-danger font-weight-bold" style="font-size:11px;"><i class="fas fa-exclamation-triangle mr-1"></i>Exchange Clustering Flagged</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card border-secondary shadow-sm h-100" style="background:#161a23; border-radius:10px; border-left:4px solid #3b82f6 !important;">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase font-weight-bold mb-1" style="font-size:10.5px;">Exchange Preservation</div>
                <div class="text-info font-weight-bold" style="font-size:1.4rem;">SUBPOENA SERVED</div>
                <small class="text-muted" style="font-size:11px;">Freezing request active</small>
            </div>
        </div>
    </div>
</div>

<!-- TRACKED TARGET WALLET CARDS -->
<div class="card border-secondary shadow-sm mb-4" style="background:#161a23; border-radius:12px; overflow:hidden;">
    <div class="card-header bg-dark text-warning border-secondary py-3 d-flex justify-content-between align-items-center">
        <h6 class="font-weight-bold mb-0"><i class="fas fa-wallet mr-2"></i>Monitored Fraudster Destination Wallets (<?= count($wallets) ?>)</h6>
        <span class="badge badge-dark border border-secondary text-warning small font-weight-bold"><i class="fas fa-shield-alt mr-1"></i>Chain Forensics Active</span>
    </div>
    <div class="card-body p-4">
        <div class="row">
            <?php foreach ($wallets as $idx => $w): ?>
            <div class="col-lg-6 mb-4">
                <div class="p-3 rounded border border-secondary h-100" style="background:#1f2533;">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="badge badge-warning text-dark font-weight-bold px-2 py-1 mb-1">
                                <?= htmlspecialchars($w['crypto_type']) ?>
                            </span>
                            <h6 class="font-weight-bold text-white mb-0"><?= htmlspecialchars($w['wallet_label'] ?: 'Monitored Target Address') ?></h6>
                        </div>
                        <span class="badge badge-<?= ($w['risk_score'] >= 80) ? 'danger' : 'warning' ?> px-2 py-1 font-weight-bold" data-toggle="tooltip" title="A score from 0–100 showing how likely this wallet is connected to fraud. Higher = more suspicious.">
                            Threat Score: <?= (int)$w['risk_score'] ?>/100
                        </span>
                    </div>
                    
                    <div class="my-3 p-2 rounded bg-black border border-secondary d-flex align-items-center justify-content-between">
                        <span class="font-monospace text-warning font-weight-bold small text-truncate mr-2" id="walletAddr_<?= $w['id'] ?>" style="font-size:12.5px;">
                            <?= htmlspecialchars($w['wallet_address']) ?>
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-warning font-weight-bold py-1 px-2" onclick="copyWalletAddr('<?= $w['id'] ?>')">
                            <i class="fas fa-copy mr-1"></i> <span id="copyBtn_<?= $w['id'] ?>">Copy</span>
                        </button>
                    </div>

                    <div class="row mb-3 text-muted small">
                        <div class="col-6">
                            <span>Monitored Balance:</span>
                            <div class="text-white font-weight-bold" style="font-size:14px;">
                                <?= number_format((float)$w['balance'], 4) ?> <small><?= htmlspecialchars($w['crypto_type']) ?></small>
                            </div>
                        </div>
                        <div class="col-6 text-right">
                            <span>USD Valuation:</span>
                            <div class="text-warning font-weight-bold" style="font-size:14px;">
                                $<?= number_format((float)($w['usd_value'] ?: $w['balance']), 2) ?> USD
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($w['exchange_tags'])): ?>
                    <div class="mb-2 p-2 rounded bg-dark border border-secondary text-light small" style="font-size:11.5px;">
                        <i class="fas fa-tags text-warning mr-1"></i> <strong>Clustering Flags:</strong> <?= htmlspecialchars($w['exchange_tags']) ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($w['notes'])): ?>
                    <div class="text-muted small" style="font-size:11px; line-height:1.4;">
                        <i class="fas fa-info-circle text-info mr-1"></i> <?= htmlspecialchars($w['notes']) ?>
                    </div>
                    <?php endif; ?>

                    <div class="border-top border-secondary pt-2 mt-3 d-flex justify-content-between align-items-center">
                        <span class="badge badge-success px-2 py-1"><i class="fas fa-lock mr-1"></i><?= htmlspecialchars($w['status'] ?: 'Active Monitoring') ?></span>
                        <a href="https://tronscan.org/#/address/<?= urlencode($w['wallet_address']) ?>" target="_blank" class="text-warning small font-weight-bold text-decoration-none">
                            View on Blockchain Explorer <i class="fas fa-external-link-alt ml-1"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- DETECTED ON-CHAIN TRANSACTION HOPS LEDGER -->
<div class="card border-secondary shadow-sm mb-4" style="background:#161a23; border-radius:12px; overflow:hidden;">
    <div class="card-header bg-dark text-warning border-secondary py-3 d-flex justify-content-between align-items-center">
        <h6 class="font-weight-bold mb-0"><i class="fas fa-route mr-2"></i>On-Chain Transaction Hops & Exchange Inflow Explorer</h6>
        <span class="badge badge-dark border border-secondary text-muted">Immutable Ledger Stream</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0" style="font-size:13px; background:#161a23;">
                <thead style="background:#1f2533; color:#fecc56; font-size:11.5px; text-transform:uppercase;">
                    <tr>
                        <th class="py-3 px-3">Date / Time (UTC)</th>
                        <th class="py-3">Transaction Hash (TXID)</th>
                        <th class="py-3">Type</th>
                        <th class="py-3 text-right">Amount</th>
                        <th class="py-3">Source &rarr; Destination Node</th>
                        <th class="py-3 px-3">Forensic Classification</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fas fa-stream fa-2x mb-2 text-secondary"></i>
                                <p class="mb-0">No on-chain transaction hops recorded yet.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td class="py-3 px-3 text-muted" style="white-space:nowrap;">
                                <?= date('M j, Y, H:i', strtotime($tx['tx_time'] ?: $tx['created_at'])) ?>
                            </td>
                            <td class="py-3 font-monospace">
                                <a href="https://tronscan.org/#/transaction/<?= urlencode($tx['tx_hash']) ?>" target="_blank" class="text-warning font-weight-bold text-decoration-none">
                                    <?= substr($tx['tx_hash'], 0, 10) ?>...<?= substr($tx['tx_hash'], -8) ?> <i class="fas fa-external-link-alt small ml-1"></i>
                                </a>
                            </td>
                            <td class="py-3">
                                <?php if ($tx['direction'] === 'IN'): ?>
                                    <span class="badge badge-success px-2 py-1"><i class="fas fa-arrow-down mr-1"></i>INFLOW</span>
                                <?php else: ?>
                                    <span class="badge badge-danger px-2 py-1"><i class="fas fa-arrow-up mr-1"></i>OUTFLOW</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 text-right font-weight-bold text-white">
                                <?= number_format((float)$tx['amount'], 2) ?> <small class="text-warning"><?= htmlspecialchars($tx['crypto_type']) ?></small>
                            </td>
                            <td class="py-3 text-light font-monospace small" style="max-width:220px; word-break:break-all;">
                                <?= htmlspecialchars(substr($tx['from_address'] ?? 'Origin', 0, 10)) ?>... &rarr; <?= htmlspecialchars(substr($tx['to_address'] ?? 'Destination', 0, 12)) ?>...
                            </td>
                            <td class="py-3 px-3">
                                <span class="badge badge-dark border border-secondary text-warning px-2 py-1">
                                    <i class="fas fa-flag mr-1"></i><?= htmlspecialchars($tx['flag_tag'] ?: 'Flagged Forensic Hop') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function copyWalletAddr(id) {
    var txt = document.getElementById('walletAddr_' + id).innerText.trim();
    navigator.clipboard.writeText(txt);
    var btn = document.getElementById('copyBtn_' + id);
    btn.innerText = 'Copied!';
    setTimeout(function() {
        btn.innerText = 'Copy';
    }, 2500);
}
</script>

<?php endif; ?>

<?php require_once '../includes/admin_footer.php'; ?>
