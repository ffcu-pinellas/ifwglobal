# smart_push.ps1
Write-Host "Resetting local git repository..." -ForegroundColor Cyan
if (Test-Path -Path ".git") {
    Remove-Item -Recurse -Force .git
}
git init
git remote add origin https://github.com/ffcu-pinellas/ifwglobal.git
git branch -M main

# First, configure Git to handle larger files better to prevent connection resets
git config --global http.postBuffer 524288000
git config --global core.compression 0

$batchSize = 200
# Filter out ignored files, .git, and .mp4 to prevent timeouts
$files = Get-ChildItem -File -Recurse | Where-Object { 
    $_.FullName -notmatch '\\.git\\' -and 
    $_.Name -ne '.env' -and 
    $_.Extension -ne '.mp4' 
}
$count = 0
$batchNum = 1

Write-Host "Total files to process: $($files.Count)" -ForegroundColor Yellow

# Ensure .gitignore is added first
git add .gitignore
git commit -m "Add gitignore"
git push -u origin main -f

foreach ($f in $files) {
    # Get relative path for git add
    $relPath = $f.FullName.Replace($PWD.Path + '\', '').Replace('\', '/')
    git add $relPath
    $count++

    if ($count -ge $batchSize) {
        Write-Host "Committing and pushing batch $batchNum..." -ForegroundColor Cyan
        git commit -m "Upload batch $batchNum (approx $batchSize files)"
        
        git push -u origin main

        # Check if push failed
        if ($LASTEXITCODE -ne 0) {
            Write-Host "Push failed on batch $batchNum. Retrying..." -ForegroundColor Red
            Start-Sleep -Seconds 5
            git push -u origin main
        }
        
        $batchNum++
        $count = 0
    }
}

# Push any remaining files
if ($count -gt 0) {
    Write-Host "Committing and pushing final batch $batchNum..." -ForegroundColor Cyan
    git commit -m "Upload final batch $batchNum"
    git push -u origin main
}

Write-Host "All batches successfully pushed!" -ForegroundColor Green
