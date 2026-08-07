<?php
$dirs = array_filter(glob('*'), 'is_dir');
foreach ($dirs as $dir) {
    if (in_array($dir, ['admin', 'client', 'includes', 'uploads', 'vendor', 'wp-content', 'wp-includes', 'public', 'api', 'media', 'intelligence', 'investigation', 'asset-recovery'])) {
        continue;
    }
    if (file_exists($dir . '.php')) {
        echo "Deleting directory: $dir\n";
        // Delete directory and contents
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        rmdir($dir);
    }
}
echo "Done.\n";




