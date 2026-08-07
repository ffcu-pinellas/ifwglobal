<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("."));
$search = "    if (\$dir === '/' || \$dir === '\' || preg_match('/^[A-Z]:\\\\$/i', \$dir)) break;";
$replace = "    if (\$dir === '/' || \$dir === '\\\\' || preg_match('/^[A-Z]:\\\\\\\\$/i', \$dir)) break;";
foreach ($files as $file) {
    if ($file->getExtension() === "php") {
        $path = $file->getPathname();
        if (strpos($path, "wp-admin") !== false || strpos($path, "wp-includes") !== false) continue;
        $content = file_get_contents($path);
        if (strpos($content, $search) !== false) {
            $content = str_replace($search, $replace, $content);
            file_put_contents($path, $content);
        }
    }
}
echo "Done\n";

