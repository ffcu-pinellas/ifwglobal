<?php
// api/upload_avatar.php — Secure avatar upload for admin/staff and clients
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_admin = !empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id']);
$is_client = !empty($_SESSION['client_logged_in']) && !empty($_SESSION['client_portal_id']);

if (!$is_admin && !$is_client) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['avatar'])) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['avatar'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'Upload failed']);
    exit;
}

$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!isset($allowed[$mime])) {
    echo json_encode(['status' => 'error', 'message' => 'Only JPG, PNG, and WEBP images are allowed']);
    exit;
}

if ($file['size'] > 3 * 1024 * 1024) {
    echo json_encode(['status' => 'error', 'message' => 'Image must be under 3MB']);
    exit;
}

$upload_dir = $dir . '/uploads/avatars';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$prefix = $is_client ? 'client_' . (int)$_SESSION['client_portal_id'] : 'user_' . (int)$_SESSION['admin_id'];
$filename = $prefix . '_' . time() . '.' . $allowed[$mime];
$dest = $upload_dir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['status' => 'error', 'message' => 'Could not save file']);
    exit;
}

// Resize to max 256x256 if GD available
if (function_exists('imagecreatefromstring')) {
    $img_data = file_get_contents($dest);
    $src = @imagecreatefromstring($img_data);
    if ($src) {
        $w = imagesx($src);
        $h = imagesy($src);
        $max = 256;
        if ($w > $max || $h > $max) {
            $ratio = min($max / $w, $max / $h);
            $nw = (int)($w * $ratio);
            $nh = (int)($h * $ratio);
            $dst = imagecreatetruecolor($nw, $nh);
            if ($mime === 'image/png' || $mime === 'image/webp') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            if ($mime === 'image/jpeg') {
                imagejpeg($dst, $dest, 88);
            } elseif ($mime === 'image/png') {
                imagepng($dst, $dest, 8);
            } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
                imagewebp($dst, $dest, 85);
            }
            imagedestroy($dst);
        }
        imagedestroy($src);
    }
}

$public_url = '/uploads/avatars/' . $filename;

try {
    if ($is_client) {
        $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(255) NULL");
        $pdo->prepare("UPDATE IFW_clients SET avatar_url = ? WHERE id = ?")->execute([$public_url, (int)$_SESSION['client_portal_id']]);
    } else {
        $pdo->exec("ALTER TABLE IFW_users ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(255) NULL");
        $pdo->prepare("UPDATE IFW_users SET avatar_url = ? WHERE id = ?")->execute([$public_url, (int)$_SESSION['admin_id']]);
    }
} catch (Exception $e) {
    @unlink($dest);
    echo json_encode(['status' => 'error', 'message' => 'Database update failed']);
    exit;
}

echo json_encode(['status' => 'success', 'avatar_url' => $public_url]);
