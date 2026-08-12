<?php
$dir = dirname(__DIR__);
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en-AU" class="js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy & Compliance Terms | IFW Global</title>
    <meta name="description" content="IFW Global's Privacy Policy, Data Protection standards, and GDPR compliance framework.">
    <link rel="icon" type="image/png" sizes="32x32" href="/media/icons/favicon-32x32.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; background-color: #0e1117; color: #f1f5f9; font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.7; }
        .policy-header { background: #161a23; border-bottom: 1px solid #28303f; padding: 30px 20px; }
        .policy-container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        .policy-card { background: #161a23; border: 1px solid #28303f; border-radius: 12px; padding: 35px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
        h1, h2, h3 { color: #fecc56; font-weight: 700; }
        h1 { font-size: 2.2rem; margin-bottom: 10px; }
        h2 { font-size: 1.4rem; margin-top: 25px; margin-bottom: 12px; border-bottom: 1px solid #2e3849; padding-bottom: 8px; }
        p, li { color: #cbd5e1; font-size: 14.5px; }
        ul { padding-left: 20px; }
        li { margin-bottom: 8px; }
        .badge-compliance { background: rgba(254,204,86,0.15); color: #fecc56; border: 1px solid rgba(254,204,86,0.3); padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .back-btn { display: inline-flex; align-items: center; background: #fecc56; color: #000; font-weight: bold; padding: 10px 20px; border-radius: 6px; text-decoration: none; transition: 0.2s; }
        .back-btn:hover { background: #e5b340; color: #000; text-decoration: none; }
    </style>
</head>
<body>

<?php require_once $dir . '/includes/announcement.php'; ?>

<div class="policy-header">
    <div style="max-width: 900px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <span class="badge-compliance"><i class="fas fa-shield-alt mr-1"></i> Official Legal Policy</span>
            <h1 style="margin-top: 8px;">Privacy Policy & Data Protection</h1>
            <small style="color: #94a3b8;">Effective Date: January 1, 2026 &bull; IFW Global Forensics & Intelligence</small>
        </div>
        <a href="/" class="back-btn"><i class="fas fa-arrow-left mr-2"></i> Return to Homepage</a>
    </div>
</div>

<div class="policy-container">
    <div class="policy-card">
        <h2>1. Commitment to Privacy & Confidentiality</h2>
        <p>IFW Global ("we", "our", or "the Company") is committed to protecting the privacy, confidentiality, and data integrity of all clients, claimants, and corporate entities. In the conduct of international asset recovery, private intelligence, blockchain forensics, and litigation support, all confidential transmissions are handled under strict legal non-disclosure frameworks.</p>

        <h2>2. Collection & Forensic Handling of Information</h2>
        <p>We collect information provided directly by clients during case intake, preliminary consultations, KYC identity verification, and evidence dossier submissions. This may include:</p>
        <ul>
            <li>Government-issued identification credentials and identity verification documents.</li>
            <li>Financial loss records, transaction receipts, bank wire records, and blockchain transaction identifiers (TXIDs).</li>
            <li>Correspondences, rogue entity communications, and contract agreements.</li>
        </ul>

        <h2>3. Cryptographic & Security Safeguards</h2>
        <p>All sensitive client evidence and case data are protected utilizing 256-bit AES cryptographic encryption at rest and in transit via TLS 1.3 protocol. Access to active case files is restricted strictly to authorized investigators and legal counsel assigned to the respective jurisdiction.</p>

        <h2>4. Disclosure to Regulatory & Law Enforcement Agencies</h2>
        <p>Where authorized by the client or instructed under judicial subpoena, IFW Global submits certified evidentiary briefs to international law enforcement agencies, cybercrime task forces, and national financial regulatory bodies to effectuate asset freeze orders and settlement recoveries.</p>

        <h2>5. Client Rights & Data Retention</h2>
        <p>Clients maintain the right to inspect, verify, and request redaction of their personal records within our secure portal, subject to legal compliance and statutory retention requirements for financial crime investigations.</p>

        <h2>6. Contact Legal & Compliance Department</h2>
        <p>For any inquiries regarding data protection policies or regulatory compliance, contact our Compliance Directorate at <strong>compliance@ifwglobal.com</strong> or via the secure live case line.</p>
    </div>
</div>

<?php require_once $dir . '/includes/chat_widget.php'; ?>
</body>
</html>
