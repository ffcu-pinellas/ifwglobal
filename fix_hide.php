<?php
$directory = __DIR__;
$files = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
foreach ($iterator as $file) {
    if ($file->isFile() && in_array($file->getExtension(), ['php', 'html'])) {
        $path = $file->getPathname();
        if (strpos($path, DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR) !== false) continue;
        if (strpos($path, DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR) !== false) continue;
        if (strpos($path, 'globalize_settings.php') !== false) continue;
        if (strpos($path, 'config.php') !== false) continue;
        if (strpos($path, 'fix_hide.php') !== false) continue;
        
        $files[] = $path;
    }
}

$updated = 0;
foreach ($files as $file) {
    $content = file_get_contents($file);
    $original = $content;
    
    // Fix the display_phone_numbers check
    $content = str_replace(
        "<?php if (get_setting(\$pdo, 'display_phone_numbers', '1') == '0'): ?>",
        "<?php if (get_setting(\$pdo, 'display_phone_numbers', 'show') === 'hide'): ?>",
        $content
    );
    
    // Add additional css to hide address in the footer if display_phone_numbers is hide
    // We will find the existing style block and append to it
    $existing_style = '.alert__numbers, .phones__link, .phone-number, a[href^="tel:"] { display: none !important; visibility: hidden !important; }';
    $new_style = $existing_style . "\n.footer__address, .footer__details, address, .contact-details { display: none !important; visibility: hidden !important; }";
    $content = str_replace($existing_style, $new_style, $content);
    
    if ($content !== $original) {
        file_put_contents($file, $content);
        $updated++;
    }
}
echo "Updated $updated files with correct hide logic.\n";
?>
