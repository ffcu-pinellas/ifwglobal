Write-Host "Resetting previous large commit..."
git reset HEAD~1

Write-Host "Staging and pushing chunk 1: wp-content..."
git add wp-content
git commit -m "Chunk 1: wp-content"
git push -u origin main

Write-Host "Staging and pushing chunk 2: wp-includes..."
git add wp-includes
git commit -m "Chunk 2: wp-includes"
git push origin main

Write-Host "Staging and pushing chunk 3: Root and remaining files..."
git add .
git commit -m "Chunk 3: Remaining files"
git push origin main

Write-Host "Chunked push complete!"
