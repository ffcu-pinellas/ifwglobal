$snippetPattern = '<\?php\s*\$dir = __DIR__;\s*while \(!file_exists\(\$dir \. ''/config\.php''\)\) \{\s*\$dir = dirname\(\$dir\);\s*if \(\$dir === ''/'' \|\| \$dir === ''\\'' \|\| preg_match\(''/^\[A-Z\]:\\\$/i'', \$dir\)\) break;\s*\}\s*require_once \$dir \. ''/config\.php'';\s*require_once \$dir \. ''/includes/functions\.php'';\s*\?>'

$files = Get-ChildItem -Path . -Filter *.php -Recurse -Exclude "wp-admin\*", "wp-includes\*", "vendor\*"

$fixedCount = 0

foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    
    if ($content -match "^$snippetPattern") {
        # Check if the next line looks like PHP code that is outside of a tag
        # e.g. starts with // or require_once or if (
        $afterSnippet = $content -replace "^$snippetPattern\s*", ""
        
        # If it doesn't start with '<' (like HTML), then it's probably PHP code that was exposed
        if ($afterSnippet -match '^(//|require_once|if\s*\(|session_start|error_reporting|ini_set|\$)') {
            $content = $content -replace "^($snippetPattern)", "`$1`n<?php`n"
            # Actually, instead of adding <?php, let's just replace the ?> at the end of the snippet
            $content = $content -replace 'require_once \$dir \. ''/includes/functions\.php'';\s*\?>', "require_once `$dir . '/includes/functions.php';"
            Set-Content $file.FullName $content -NoNewline
            $fixedCount++
            Write-Output "Fixed $($file.FullName)"
        }
    }
}
Write-Output "Fixed $fixedCount files"
