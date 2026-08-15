<?php
// public/client/verify_pin.php
// Redirect to unified 2FA Security Gate in PIN mode
header("Location: verify_otp.php?mode=pin");
exit;
