param (
    [string]$CommitMessage = "Update IFW Global files"
)

# Initialize git if it hasn't been initialized
if (!(Test-Path -Path ".git")) {
    Write-Host "Initializing git repository..." -ForegroundColor Cyan
    git init
}

# Ensure the correct remote exists
$remoteUrl = "https://github.com/ffcu-pinellas/ifwglobal.git"
$remotes = git remote -v
if ($remotes -notmatch "origin") {
    Write-Host "Adding GitHub remote origin..." -ForegroundColor Cyan
    git remote add origin $remoteUrl
} elseif ($remotes -notmatch $remoteUrl) {
    Write-Host "Updating remote URL to match..." -ForegroundColor Cyan
    git remote set-url origin $remoteUrl
}

# Ensure main branch is used
$currentBranch = git branch --show-current
if ($currentBranch -ne "main") {
    git branch -M main
}

Write-Host "Staging files..." -ForegroundColor Cyan
git add .

Write-Host "Committing changes with message: '$CommitMessage'" -ForegroundColor Cyan
git commit -m $CommitMessage

Write-Host "Pushing to GitHub (and implicitly Hostinger if webhooks are set up)..." -ForegroundColor Cyan
git push -u origin main

Write-Host "Done!" -ForegroundColor Green
