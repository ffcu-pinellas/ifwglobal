<?php
// includes/chat_widget.php
// Tawk.to Integration — only renders when a valid, non-placeholder property ID is configured
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

$tawkto_raw = trim(get_setting($pdo, 'tawkto_property_id', ''));

// Do not inject widget when chat provider is not tawkto or setting is empty/placeholder
$chat_provider = get_setting($pdo, 'chat_provider', 'none');
if ($chat_provider !== 'tawkto' && $chat_provider !== 'none') {
    return;
}

$clean_id = $tawkto_raw;
$clean_id = strip_tags($clean_id);
$clean_id = preg_replace('/<!--.*?-->/s', '', $clean_id);
$clean_id = preg_replace('/var\s+Tawk_API[\s\S]*?embed\.tawk\.to\//i', '', $clean_id);
$clean_id = preg_replace('/[\'"];.*$/s', '', $clean_id);
$clean_id = trim($clean_id, " \t\n\r;'\"/");

if (strpos($clean_id, 'embed.tawk.to/') !== false) {
    $clean_id = preg_replace('/.*embed\.tawk\.to\//', '', $clean_id);
    $clean_id = trim($clean_id, " \t\n\r;'\"");
}

// Known placeholder / demo IDs — skip injection to prevent console 404s
$placeholder_ids = [
    '6a742dd38875351d455643d1/default',
    'YOUR_TAWKTO_PROPERTY_ID',
    'your_property_id/default',
    'placeholder/default',
];

$is_valid = !empty($clean_id)
    && preg_match('/^[a-zA-Z0-9_\/\-]{10,}$/', $clean_id)
    && !in_array(strtolower($clean_id), array_map('strtolower', $placeholder_ids), true);

if (!$is_valid) {
    return;
}

$tawkto_src = 'https://embed.tawk.to/' . $clean_id;
?>
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){
    var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
    s1.async=true;
    s1.src='<?php echo htmlspecialchars($tawkto_src, ENT_QUOTES); ?>';
    s1.charset='UTF-8';
    s1.setAttribute('crossorigin','*');
    s0.parentNode.insertBefore(s1,s0);
})();
</script>
