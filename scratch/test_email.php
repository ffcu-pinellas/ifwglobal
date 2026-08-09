<?php
// scratch/test_email.php
require_once __DIR__ . '/../includes/mailer.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $env = parse_ini_file($envPath);
} else {
    die("No .env file found.\n");
}

$test_email = 'notifications@frontfieldcu.pro';
echo "Sending test email to: $test_email...\n";

$subject = "Test Invoice Email";
$body = "<h2>Test Invoice</h2><p>This is a test of the email invoice feature.</p>";

$res = send_html_email($test_email, $subject, $body);

if ($res) {
    echo "SUCCESS: Email sent!\n";
} else {
    echo "FAILURE: Email failed to send.\n";
}
?>
