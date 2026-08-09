<?php
// admin/backup.php
require_once '../config.php';
require_once '../includes/functions.php';
require_admin_login();

$user_role = $_SESSION['admin_role'] ?? 'viewer';
if (!in_array($user_role, ['super_admin', 'superadmin', 'admin'])) {
    die("Unauthorized access to backups.");
}

if (isset($_GET['action']) && $_GET['action'] == 'download') {
    // Basic PHP script to dump MySQL database. 
    // In production on Hostinger, using `mysqldump` via exec() might be better if permitted,
    // but a PHP fallback ensures it works everywhere.
    
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $sqlScript = "";
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW CREATE TABLE $table");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $sqlScript .= "\n\n" . $row[1] . ";\n\n";
        
        $stmt = $pdo->query("SELECT * FROM $table");
        $rowCount = $stmt->rowCount();
        
        if ($rowCount > 0) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $sqlScript .= "INSERT INTO $table VALUES(";
                $values = [];
                foreach ($row as $val) {
                    if (!isset($val)) {
                        $values[] = "NULL";
                    } else {
                        $values[] = $pdo->quote($val);
                    }
                }
                $sqlScript .= implode(', ', $values) . ");\n";
            }
        }
        $sqlScript .= "\n";
    }
    
    $backup_name = 'backup_' . DB_NAME . '_' . date("Y-m-d-H-i-s") . '.sql';
    
    // Log the backup
    $stmt = $pdo->prepare("INSERT INTO IFW_backups (filename) VALUES (?)");
    // Assuming IFW_backups table exists (added in our schema logic conceptually, though wasn't fully detailed in database.sql)
    try {
        $pdo->exec("CREATE TABLE IFW_backups (id INT AUTO_INCREMENT PRIMARY KEY, filename VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $stmt->execute([$backup_name]);
    } catch (Exception $e) {
        // Table exists or error, ignore for this simple implementation
    }
    
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary"); 
    header("Content-disposition: attachment; filename=\"".$backup_name."\"");
    echo $sqlScript;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php if (get_setting($pdo, 'display_phone_numbers', '1') == '0'): ?>
<style>
.alert__numbers, .phones__link, .phone-number, a[href^="tel:"] { display: none !important; visibility: hidden !important; }
</style>
<?php endif; ?>
<style id='gdpr-global-suppress'>#gdpr-cookie-consent-bar, #gdpr-cookie-consent-show-again, #cookie_action_settings, .gdpr_action_button, .gdpr-modal, .cli-modal, #cliModal, [id*='gdpr'], [class*='gdpr-cookie'], [class*='cli-'] { display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; height: 0 !important; width: 0 !important; margin: 0 !important; padding: 0 !important; }</style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup - IFW Global Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: #343a40; color: white; padding-top: 20px; }
        .sidebar a { color: #adb5bd; text-decoration: none; display: block; padding: 10px 20px; }
        .sidebar a:hover, .sidebar a.active { color: white; background: #495057; }
        .content-area { padding: 30px; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar px-0">
            <h5 class="text-center mb-4 text-white">IFW Global</h5>
            <a href="index.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
            <a href="settings.php"><i class="bi bi-gear me-2"></i> Global Settings</a>
            <a href="form_builder.php"><i class="bi bi-ui-radios me-2"></i> Form Builder</a>
            <a href="content_managers.php"><i class="bi bi-file-text me-2"></i> Content & Pages</a>
            <a href="client_manager.php"><i class="bi bi-people me-2"></i> Client Manager</a>
            <a href="chat.php"><i class="bi bi-chat-dots me-2"></i> Live Chat</a>
            <a href="backup.php" class="active"><i class="bi bi-database me-2"></i> Database Backup</a>
            <a href="logout.php" class="mt-5 text-danger"><i class="bi bi-box-arrow-left me-2"></i> Logout</a>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-10 content-area bg-light">
            <h2>Database Backup</h2>
            <p class="text-muted">Generate and download a full SQL dump of your database.</p>
            
            <div class="card mt-4">
                <div class="card-body text-center p-5">
                    <i class="bi bi-cloud-arrow-down" style="font-size: 4rem; color: #0d6efd;"></i>
                    <h4 class="mt-3">Download Full Backup</h4>
                    <p class="text-muted">This will generate an `.sql` file containing all tables, settings, clients, and chat messages.</p>
                    <a href="backup.php?action=download" class="btn btn-primary btn-lg mt-3 px-5">Generate & Download Backup</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
