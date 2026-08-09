<?php
// includes/chat_widget.php
// Tawk.to Integration — Always renders from clean property ID, never dumps raw script as text
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

// ALWAYS extract clean property ID — never echo raw stored code directly
$tawkto_raw = get_setting($pdo, 'tawkto_property_id', '6a742dd38875351d455643d1/default');

// Strip any HTML tags, script wrappers, comments from stored value to get clean property ID
$clean_id = $tawkto_raw;
$clean_id = strip_tags($clean_id);                             // Remove any <script> tags
$clean_id = preg_replace('/<!--.*?-->/s', '', $clean_id);      // Remove HTML comments
$clean_id = preg_replace('/var\s+Tawk_API[\s\S]*?embed\.tawk\.to\//i', '', $clean_id); // Strip JS preamble
$clean_id = preg_replace('/[\'"];.*$/s', '', $clean_id);       // Remove trailing JS
$clean_id = trim($clean_id, " \t\n\r;'\"/");

// If the stored value looks like a full URL, extract just the path part after embed.tawk.to/
if (strpos($clean_id, 'embed.tawk.to/') !== false) {
    $clean_id = preg_replace('/.*embed\.tawk\.to\//', '', $clean_id);
    $clean_id = trim($clean_id, " \t\n\r;'\"");
}

// Validate: should look like "XXXXXXXX/YYYYY" or "hash/default"  
if (empty($clean_id) || !preg_match('/^[a-zA-Z0-9_\/\-]{10,}$/', $clean_id)) {
    $clean_id = '6a742dd38875351d455643d1/default'; // fallback
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
