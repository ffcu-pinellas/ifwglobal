<?php
// public/client/verify_pin.php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['pending_client_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $entered_pin = trim($_POST['pin'] ?? '');

    if (empty($entered_pin)) {
        $error = "Please enter your PIN.";
    } else {
        $stmt = $pdo->prepare("SELECT pin_hash FROM IFW_clients WHERE id = ?");
        $stmt->execute([$_SESSION['pending_client_id']]);
        $client = $stmt->fetch();
        
        if ($client && password_verify($entered_pin, $client['pin_hash'])) {
            // Fully logged in
            $_SESSION['client_logged_in'] = true;
            $_SESSION['client_portal_id'] = $_SESSION['pending_client_id'];
            $_SESSION['client_name'] = $_SESSION['pending_client_name'];
            
            // Cleanup
            unset($_SESSION['pending_client_id']);
            unset($_SESSION['pending_client_email']);
            unset($_SESSION['pending_client_name']);
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid PIN.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php if (get_setting($pdo, 'display_phone_numbers', 'show') === 'hide'): ?>
<style>
.alert__numbers, .phones__link, .phone-number, a[href^="tel:"] { display: none !important; visibility: hidden !important; }
.footer__address, .footer__details, address, .contact-details { display: none !important; visibility: hidden !important; }
</style>
<?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter PIN - IFW Global</title>
    
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #0d0d0e;
            background-image: radial-gradient(circle at 50% 30%, rgba(254, 204, 86, 0.08) 0%, rgba(13, 13, 14, 0.95) 70%);
            color: #ffffff;
            font-family: 'Montserrat', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-card {
            background-color: #171719;
            border: 1px solid rgba(254, 204, 86, 0.15);
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
        }
        .brand-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .brand-logo h3 {
            color: #fecc56;
            font-weight: 700;
            letter-spacing: 1.5px;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-warning {
            background: linear-gradient(135deg, #fecc56 0%, #f1b834 100%);
            border: none;
            color: #000000;
            font-weight: 600;
            padding: 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-warning:hover {
            background: linear-gradient(135deg, #f1b834 0%, #d89e20 100%);
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(254, 204, 86, 0.25);
            color: #000000;
        }
        .form-control {
            background-color: #212124;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
            padding: 12px;
            border-radius: 8px;
        }
        .form-control:focus {
            background-color: #26262a;
            border-color: #fecc56;
            color: #ffffff;
            box-shadow: 0 0 0 0.25rem rgba(254, 204, 86, 0.15);
        }
        .text-warning-custom {
            color: #fecc56;
            text-decoration: none;
            font-weight: 500;
        }
        .text-warning-custom:hover {
            color: #f1b834;
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="auth-card text-center">
    <div class="brand-logo">
        <h3><i class="fas fa-shield-alt"></i> IFW GLOBAL</h3>
    </div>
    
    <div class="mb-4">
        <h5 class="fw-bold">Security PIN Required</h5>
        <p class="text-muted small">Please enter your 4-digit security PIN to unlock your portal.</p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 text-center py-2 mb-3" style="font-size: 0.9rem; background-color: rgba(220, 53, 69, 0.15); color: #ea868f;">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-4">
            <input type="password" class="form-control form-control-lg text-center fw-bold fs-3 letter-spacing-5" name="pin" placeholder="****" required maxlength="4" autofocus>
        </div>
        <button type="submit" class="btn btn-warning w-100 py-3 font-weight-bold text-dark"><i class="fas fa-lock-open me-2"></i>Unlock Portal</button>
    </form>
    
    <div class="mt-4">
        <a href="login.php" class="text-warning-custom small"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
    </div>
</div>

</body>
</html>
