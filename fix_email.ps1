$files = Get-ChildItem -Path . -Filter *.php -Recurse -Exclude "admin\*", "includes\*", "config.php", "global_*.php", "test_*.php", "wp-content\*"

foreach ($file in $files) {
    if ($file.FullName -match "wp-admin" -or $file.FullName -match "wp-includes") { continue }
    
    $content = Get-Content $file.FullName -Raw

    # Look for info@ifwglobal.com that is NOT preceded by '
    if ($content -match '(?<!'')info@ifwglobal\.com') {
        $content = $content -replace '(?<!'')info@ifwglobal\.com', '<?= htmlspecialchars(get_setting($pdo, ''contact_email'', ''info@ifwglobal.com'')) ?>'
        Set-Content $file.FullName $content -NoNewline
    }
}
Write-Output "Done"
