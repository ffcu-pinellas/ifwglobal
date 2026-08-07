$files = Get-ChildItem -Path . -Filter *.php -Recurse -Exclude "wp-content\*"

foreach ($file in $files) {
    if ($file.FullName -match "wp-admin" -or $file.FullName -match "wp-includes") { continue }
    
    $content = Get-Content $file.FullName -Raw

    $search = "if (`$dir === '/' || `$dir === '\' || preg_match('/^[A-Z]:\\`$/i', `$dir)) break;"
    $replace = "if (`$dir === '/' || `$dir === '\\' || preg_match('/^[A-Z]:\\\\`$/i', `$dir)) break;"

    if ($content -match "\$dir === '\\'") {
        $content = $content.Replace($search, $replace)
        Set-Content $file.FullName $content -NoNewline
    }
}
Write-Output "Done"
