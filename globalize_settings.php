<?php
// globalize_settings.php
// Run this script from the terminal to bulk-replace static strings with dynamic PHP calls.

$directory = __DIR__;
$files = [];

// Recursively find all .php and .html files (ignoring admin and includes)
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
foreach ($iterator as $file) {
    if ($file->isFile() && in_array($file->getExtension(), ['php', 'html'])) {
        $path = $file->getPathname();
        // Skip admin, includes, and this script itself
        if (strpos($path, DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR) !== false) continue;
        if (strpos($path, DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR) !== false) continue;
        if (strpos($path, 'globalize_settings.php') !== false) continue;
        if (strpos($path, 'config.php') !== false) continue;
        if (strpos($path, 'process_form.php') !== false) continue;
        
        $files[] = $path;
    }
}

$processed = 0;
$updated = 0;

$config_snippet = "<?php
\$dir = __DIR__;
while (!file_exists(\$dir . '/config.php')) {
    \$dir = dirname(\$dir);
    if (\$dir === '/' || \$dir === '\\\\' || preg_match('/^[A-Z]:\\\\\\\\$/i', \$dir)) break;
}
require_once \$dir . '/config.php';
require_once \$dir . '/includes/functions.php';
?>\n";

foreach ($files as $file) {
    $content = file_get_contents($file);
    $original = $content;
    
    // 1. Prepend config if not present
    if (strpos($content, 'require_once $dir . \'/config.php\';') === false && strpos($content, 'require_once \'config.php\';') === false) {
        // Find the start, bypassing any BOM or leading spaces
        if (substr(trim($content), 0, 5) === '<?php') {
            // It already has a PHP block, we can just insert our logic after it
            $content = preg_replace('/^<\?php\s*/', $config_snippet, trim($content));
        } else {
            $content = $config_snippet . $content;
        }
    }
    
    // 2. Replace hardcoded values with dynamic PHP calls
    
    // Phone numbers (Australia)
    $content = str_replace('1300 439 456', "<?= htmlspecialchars(get_setting(\$pdo, 'phone_australia', '1300 439 456')) ?>", $content);
    $content = str_replace('1300439456', "<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', get_setting(\$pdo, 'phone_australia', '1300439456'))) ?>", $content);
    
    // Email
    $content = str_replace('<?= htmlspecialchars(get_setting($pdo, 'contact_email', 'info@ifwglobal.com')) ?>', "<?= htmlspecialchars(get_setting(\$pdo, 'contact_email', '<?= htmlspecialchars(get_setting($pdo, 'contact_email', 'info@ifwglobal.com')) ?>')) ?>", $content);
    
    // Office Address
    $content = str_replace('Level 13, 201 Elizabeth Street, Sydney', "<?= htmlspecialchars(get_setting(\$pdo, 'office_address', 'Level 13, 201 Elizabeth Street, Sydney')) ?>", $content);
    
    // 3. Inject Announcement Banner after <body> tag
    if (strpos($content, 'announcement_bar_active') === false) {
        $banner_snippet = "
<?php if(get_setting(\$pdo, 'announcement_bar_active') == '1'): ?>
<div style=\"background-color: #fecc56; color: #000; text-align: center; padding: 12px; font-weight: bold; z-index: 9999; position: relative; border-bottom: 2px solid #e5b340;\">
    <?= htmlspecialchars(get_setting(\$pdo, 'announcement_bar_text')) ?>
</div>
<?php endif; ?>
";
        $content = preg_replace('/(<body[^>]*>)/i', '$1' . $banner_snippet, $content, 1);
    }
    
    if ($content !== $original) {
        file_put_contents($file, $content);
        $updated++;
    }
    $processed++;
}

echo "Processed $processed files. Updated $updated files.\n";
?>

