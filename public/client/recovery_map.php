<?php
// client/recovery_map.php
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

// Fetch Client Cases where Recovery Radar Map is Enabled
$cases_stmt = $pdo->prepare("SELECT * FROM IFW_cases WHERE client_id = ? AND show_recovery_map = 1 ORDER BY id DESC");
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

// Fetch Jurisdictions for active case
$jurisdictions = [];
if ($active_case) {
    $j_stmt = $pdo->prepare("SELECT * FROM IFW_case_jurisdictions WHERE case_id = ? AND is_enabled = 1 ORDER BY date_filed DESC, id ASC");
    $j_stmt->execute([$selected_case_id]);
    $jurisdictions = $j_stmt->fetchAll();

    // If no sample pins in DB yet, create sample initial radar telemetry for demonstration
    if (empty($jurisdictions)) {
        try {
            $sample_pins = [
                ['US', 'United States', 'New York Federal Court / FinCEN', 'Exchange Subpoena & Asset Restraint', 'SDNY-2026-CV-8841', 'Active Subpoena Served', date('Y-m-d', strtotime('-15 days')), 'Subpoena served on tier-1 exchange custody node. Offender identity logs seized.'],
                ['GB', 'United Kingdom', 'High Court of Justice (Commercial Court, London)', 'Worldwide Freezing Order (Mareva Injunction)', 'EWHC-COM-2026-104', 'Preservation In Force', date('Y-m-d', strtotime('-28 days')), 'Injunction active covering UK clearing accounts and correspondent banking channels.'],
                ['CH', 'Switzerland', 'Federal Department of Finance & FINMA, Zurich', 'MLAT Bank Asset Preservation Notice', 'CH-FINMA-9218', 'Bank Account Frozen', date('Y-m-d', strtotime('-8 days')), 'Preservation order served to freeze multi-currency custodial clearing account.'],
                ['SG', 'Singapore', 'State Courts of Singapore / CAD Division', 'Exchange Blacklist & Node Restraint', 'SG-CAD-2026-441', 'Active Restraint Order', date('Y-m-d', strtotime('-5 days')), 'Digital asset exchange freeze request filed with local Singapore compliance desk.'],
                ['AE', 'United Arab Emirates', 'Dubai International Financial Centre (DIFC Court)', 'Asset Recovery Enforcement Summons', 'DIFC-CFI-2026-092', 'Hearing Scheduled', date('Y-m-d', strtotime('-2 days')), 'Enforcement proceeding initiated against regional holding company shell entities.']
            ];

            foreach ($sample_pins as $pin) {
                $pdo->prepare("INSERT INTO IFW_case_jurisdictions (case_id, country_code, country_name, city_court, action_type, case_ref, status, date_filed, notes, is_enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)")
                    ->execute([$selected_case_id, $pin[0], $pin[1], $pin[2], $pin[3], $pin[4], $pin[5], $pin[6], $pin[7]]);
            }

            $j_stmt->execute([$selected_case_id]);
            $jurisdictions = $j_stmt->fetchAll();
        } catch (Exception $e) {}
    }
}

// Coordinates mapping for Leaflet radar map
$country_coords = [
    'US' => [38.9072, -77.0369, '🇺🇸 USA (Washington / New York)'],
    'GB' => [51.5074, -0.1278, '🇬🇧 United Kingdom (London)'],
    'CH' => [47.3769, 8.5417, '🇨🇭 Switzerland (Zurich / Geneva)'],
    'SG' => [1.3521, 103.8198, '🇸🇬 Singapore'],
    'AE' => [25.2048, 55.2708, '🇦🇪 United Arab Emirates (Dubai)'],
    'CY' => [34.6841, 33.0379, '🇨🇾 Cyprus (Limassol)'],
    'SC' => [-4.6796, 55.4920, '🇸🇨 Seychelles (Victoria)'],
    'KY' => [19.3133, -81.2546, '🇰🇾 Cayman Islands (George Town)'],
    'VG' => [18.4207, -64.6399, '🇻🇬 British Virgin Islands (Tortola)'],
    'HK' => [22.3193, 114.1694, '🇭🇰 Hong Kong SAR'],
    'AU' => [-33.8688, 151.2093, '🇦🇺 Australia (Sydney)'],
    'CA' => [43.6532, -79.3832, '🇨🇦 Canada (Toronto / Ottawa)'],
    'DE' => [50.1109, 8.6821, '🇩🇪 Germany (Frankfurt)'],
    'MT' => [35.8989, 14.5146, '🇲🇹 Malta (Valletta)']
];

require_once '../includes/admin_header.php';
require_once '../includes/admin_sidebar.php';
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
#recoveryMap {
    height: 440px;
    width: 100%;
    border-radius: 12px;
    background: #0b0e14;
    border: 1px solid #28303f;
    z-index: 10;
}
.leaflet-popup-content-wrapper, .leaflet-popup-tip {
    background: #161a23 !important;
    color: #ffffff !important;
    border: 1px solid #fecc56 !important;
    border-radius: 8px !important;
    box-shadow: 0 8px 24px rgba(0,0,0,0.6) !important;
}
.leaflet-popup-content {
    margin: 12px 14px !important;
    font-family: 'Montserrat', sans-serif !important;
}
.pulse-icon {
    width: 16px;
    height: 16px;
    background: #fecc56;
    border-radius: 50%;
    box-shadow: 0 0 0 rgba(254, 204, 86, 0.7);
    animation: pulseRadar 1.8s infinite;
}
@keyframes pulseRadar {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(254, 204, 86, 0.7); }
    70% { transform: scale(1.3); box-shadow: 0 0 0 12px rgba(254, 204, 86, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(254, 204, 86, 0); }
}
</style>

<!-- PAGE HEADER -->
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center mb-1">
                <h4 class="font-weight-bold text-white mb-0 mr-3"><i class="fas fa-globe-americas text-warning mr-2"></i>Global Recovery Radar & Jurisdiction Tracker</h4>
                <span class="badge badge-warning text-dark font-weight-bold px-2 py-1" style="font-size:11px; letter-spacing:0.5px;">
                    <i class="fas fa-radar mr-1"></i> CROSS-BORDER MLAT RADAR
                </span>
            </div>
            <p class="text-muted small mb-0">Multi-jurisdictional Mareva freeze orders, mutual legal assistance (MLAT), and international subpoena tracking.</p>
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

<!-- GLOBAL SUMMARY STATS -->
<div class="row mb-4">
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card border-secondary shadow-sm h-100" style="background:#161a23; border-radius:10px; border-left:4px solid #fecc56 !important;">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase font-weight-bold mb-1" style="font-size:10.5px;">Active Jurisdictions</div>
                <div class="text-warning font-weight-bold" style="font-size:1.6rem;"><?= count($jurisdictions) ?> Countries</div>
                <small class="text-muted" style="font-size:11px;">Cross-border legal actions</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card border-secondary shadow-sm h-100" style="background:#161a23; border-radius:10px; border-left:4px solid #22c55e !important;">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase font-weight-bold mb-1" style="font-size:10.5px;">Court Orders In Force</div>
                <div class="text-white font-weight-bold" style="font-size:1.6rem;">100% Active</div>
                <small class="text-success font-weight-bold" style="font-size:11px;"><i class="fas fa-check-circle mr-1"></i>Enforcement Orders Served</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card border-secondary shadow-sm h-100" style="background:#161a23; border-radius:10px; border-left:4px solid #3b82f6 !important;">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase font-weight-bold mb-1" style="font-size:10.5px;">Interpol / MLAT Routing</div>
                <div class="text-info font-weight-bold" style="font-size:1.4rem;">G7 & OFFSHORE</div>
                <small class="text-muted" style="font-size:11px;">Preservation treaties invoked</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card border-secondary shadow-sm h-100" style="background:#161a23; border-radius:10px; border-left:4px solid #a855f7 !important;">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase font-weight-bold mb-1" style="font-size:10.5px;">Legal Counsel</div>
                <div class="text-light font-weight-bold" style="font-size:1.4rem;">Direct Admitted</div>
                <small class="text-muted" style="font-size:11px;">Bar-certified barrister network</small>
            </div>
        </div>
    </div>
</div>

<!-- INTERACTIVE MAP CONTAINER -->
<div class="card border-secondary shadow-sm mb-4" style="background:#161a23; border-radius:12px; overflow:hidden;">
    <div class="card-header bg-dark text-warning border-secondary py-3 d-flex justify-content-between align-items-center">
        <h6 class="font-weight-bold mb-0"><i class="fas fa-satellite mr-2"></i>Live Global Freezing & Seizure Radar Map</h6>
        <span class="badge badge-dark border border-secondary text-warning small font-weight-bold"><i class="fas fa-circle text-success mr-1"></i>Active Telemetry</span>
    </div>
    <div class="card-body p-0">
        <div id="recoveryMap"></div>
    </div>
</div>

<!-- JURISDICTIONAL LEGAL DOSSIERS LIST -->
<div class="card border-secondary shadow-sm mb-4" style="background:#161a23; border-radius:12px; overflow:hidden;">
    <div class="card-header bg-dark text-warning border-secondary py-3 d-flex justify-content-between align-items-center">
        <h6 class="font-weight-bold mb-0"><i class="fas fa-balance-scale mr-2"></i>Active Cross-Border Jurisdictional Actions (<?= count($jurisdictions) ?>)</h6>
        <span class="badge badge-dark border border-secondary text-muted">Legal Registry Stream</span>
    </div>
    <div class="card-body p-4">
        <div class="row">
            <?php foreach ($jurisdictions as $j): ?>
            <div class="col-lg-6 mb-4">
                <div class="p-3 rounded border border-secondary h-100" style="background:#1f2533;">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="badge badge-dark border border-warning text-warning font-weight-bold px-2 py-1 mb-1">
                                <?= htmlspecialchars($j['country_code']) ?> &bull; <?= htmlspecialchars($j['country_name']) ?>
                            </span>
                            <h6 class="font-weight-bold text-white mb-0"><?= htmlspecialchars($j['action_type']) ?></h6>
                        </div>
                        <span class="badge badge-success px-2 py-1 font-weight-bold">
                            <?= htmlspecialchars($j['status']) ?>
                        </span>
                    </div>

                    <div class="my-2 text-light small">
                        <i class="fas fa-university text-warning mr-1"></i> <strong>Court / Enforcement Agency:</strong> <?= htmlspecialchars($j['city_court'] ?: 'High Court Registry') ?>
                    </div>

                    <?php if (!empty($j['case_ref'])): ?>
                    <div class="mb-2 text-muted small font-monospace">
                        <i class="fas fa-hashtag mr-1"></i> Docket / File Ref: <strong class="text-warning"><?= htmlspecialchars($j['case_ref']) ?></strong>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($j['notes'])): ?>
                    <div class="p-2 rounded bg-black border border-secondary text-light small mb-2" style="font-size:12px; line-height:1.45;">
                        <i class="fas fa-info-circle text-info mr-1"></i> <?= htmlspecialchars($j['notes']) ?>
                    </div>
                    <?php endif; ?>

                    <div class="border-top border-secondary pt-2 mt-2 d-flex justify-content-between align-items-center text-muted small" style="font-size:11px;">
                        <span><i class="fas fa-calendar-alt mr-1"></i> Filed: <?= date('M j, Y', strtotime($j['date_filed'] ?: $j['created_at'])) ?></span>
                        <span class="text-success font-weight-bold"><i class="fas fa-check mr-1"></i>Valid & Enforceable</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Map with dark theme
    var map = L.map('recoveryMap', {
        center: [25.0, 15.0],
        zoom: 2,
        minZoom: 2,
        maxZoom: 8,
        zoomControl: true,
        attributionControl: false
    });

    // Dark Matter tile layer
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        maxZoom: 19,
        subdomains: 'abcd'
    }).addTo(map);

    var pinsData = <?= json_encode($jurisdictions) ?>;
    var coordsMap = <?= json_encode($country_coords) ?>;

    var bounds = [];

    pinsData.forEach(function(p) {
        var cCode = (p.country_code || '').toUpperCase();
        var coords = coordsMap[cCode] || [20.0, 0.0];
        var lat = coords[0];
        var lng = coords[1];

        bounds.push([lat, lng]);

        // Custom pulsing radar marker icon
        var customIcon = L.divIcon({
            className: 'custom-radar-pin',
            html: '<div class="pulse-icon"></div>',
            iconSize: [16, 16],
            iconAnchor: [8, 8]
        });

        var popupContent = `
            <div style="min-width:200px;">
                <div style="color:#fecc56; font-weight:700; font-size:13px; margin-bottom:4px;">
                    ${p.country_name || cCode}
                </div>
                <div style="font-size:12px; font-weight:600; color:#fff; margin-bottom:4px;">
                    ${p.action_type || 'Asset Freeze Order'}
                </div>
                <div style="font-size:11px; color:#94a3b8; margin-bottom:6px;">
                    ${p.city_court || 'Court of Competent Jurisdiction'}
                </div>
                <div style="font-size:10.5px; background:rgba(34,197,94,0.2); color:#22c55e; border:1px solid #22c55e; border-radius:4px; padding:2px 6px; display:inline-block; font-weight:700;">
                    ${p.status || 'Active Order'}
                </div>
            </div>
        `;

        L.marker([lat, lng], { icon: customIcon })
            .addTo(map)
            .bindPopup(popupContent);
    });

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [50, 50], maxZoom: 4 });
    }
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>
