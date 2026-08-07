$files = Get-ChildItem -Path . -Filter *.php -Recurse -Exclude "admin\*", "includes\*", "config.php", "global_*.php", "test_*.php", "wp-content\*"

$styleBlock = @"
<?php if (get_setting(`$pdo, 'display_phone_numbers', '1') == '0'): ?>
<style>
.alert__numbers, .phones__link, .phone-number, a[href^="tel:"] { display: none !important; visibility: hidden !important; }
</style>
<?php endif; ?>
"@

foreach ($file in $files) {
    if ($file.FullName -match "wp-admin" -or $file.FullName -match "wp-includes") { continue }
    
    $content = Get-Content $file.FullName -Raw

    # Only process files that have a head tag
    if ($content -match '<head>') {
        $changed = $false

        # 1. Inject CSS if not already there
        if ($content -notmatch 'display_phone_numbers') {
            $content = $content -replace '<head>', "<head>`n$styleBlock"
            $changed = $true
        }

        # 2. Replace hardcoded secondary tel links
        if ($content -match 'tel:\+6183280402' -or $content -match 'tel:\+61 2 8328 0402') {
            $content = $content -replace 'href="tel:\+6183280402"', 'href="tel:<?= htmlspecialchars(preg_replace(''/[^0-9+]/'', '''', get_setting($pdo, ''phone_australia_secondary'', ''+61 (02) 8328 0402''))) ?>"'
            $content = $content -replace 'href="tel:\+61 2 8328 0402"', 'href="tel:<?= htmlspecialchars(preg_replace(''/[^0-9+]/'', '''', get_setting($pdo, ''phone_australia_secondary'', ''+61 (02) 8328 0402''))) ?>"'
            $changed = $true
        }

        # 3. Replace Level 26, 44 Market Street
        $addrRegex = 'Level 26, 44 Market Street,<br>\s*Sydney NSW 2000'
        if ($content -match $addrRegex) {
            $replacement = '<?= nl2br(htmlspecialchars(get_setting($pdo, ''office_address'', "Level 26, 44 Market Street\nSydney NSW 2000"))) ?>'
            $content = $content -replace $addrRegex, $replacement
            $changed = $true
        }
        
        # 3b. Replace Level 5, 20 Bond Street
        $addrRegex2 = 'Level 5, 20 Bond Street,<br>\s*Sydney NSW 2000'
        if ($content -match $addrRegex2) {
            $replacement = '<?= nl2br(htmlspecialchars(get_setting($pdo, ''office_address'', "Level 5, 20 Bond Street\nSydney NSW 2000"))) ?>'
            $content = $content -replace $addrRegex2, $replacement
            $changed = $true
        }

        # 4. Replace info@ifwglobal.com
        if ($content -match 'info@ifwglobal\.com') {
            # Be careful not to replace it if it's already inside a get_setting call
            if ($content -notmatch 'get_setting\(\$pdo, ''contact_email'', ''info@ifwglobal\.com''\)') {
                $content = $content -replace 'info@ifwglobal\.com', '<?= htmlspecialchars(get_setting($pdo, ''contact_email'', ''info@ifwglobal.com'')) ?>'
                $changed = $true
            }
        }

        if ($changed) {
            Set-Content $file.FullName $content -NoNewline
        }
    }
}
Write-Output "Done"
