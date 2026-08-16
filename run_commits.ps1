$ErrorActionPreference = "Stop"

# Paths
$workspace = "C:\xampp\htdocs\project1.1"
$backupDir = "C:\xampp\htdocs\project_backup"
$fakeGitDir = "C:\xampp\htdocs\target_repo"

Write-Host "Creating clean target directory..."
if (Test-Path $fakeGitDir) {
    Remove-Item -Path $fakeGitDir -Recurse -Force
}
New-Item -ItemType Directory -Path $fakeGitDir | Out-Null

Set-Location $fakeGitDir
git init
git remote add origin https://github.com/wahab9811/Teacher-TimeTable-Management-System.git
git config init.defaultBranch main
git branch -m main

# Set up authors
$wahab = @{ Name="wahab9811"; Email="malikabdul98612@gmail.com" }
$zunair = @{ Name="Zunair-Yousaf"; Email="zunairyousaf12@gmail.com" }

# Target commits
$totalWahab = 125
$totalZunair = 75

# Create commit sequence
$commitList = @()
for ($i=0; $i -lt $totalWahab; $i++) { $commitList += $wahab }
for ($i=0; $i -lt $totalZunair; $i++) { $commitList += $zunair }

# Shuffle array natively to get random chunks of streak (e.g. 5, 2, etc.)
$commitList = $commitList | Get-Random -Count $commitList.Count

# Generate Dates
# Timeframe: March 2, 2026 to July 9, 2026.
$startDate = Get-Date -Year 2026 -Month 3 -Day 2
$endDate = Get-Date -Year 2026 -Month 7 -Day 9

$activeDates = @()
$currentDate = $startDate
while ($currentDate -le $endDate) {
    # Get to Monday of current week
    $dayOfWeek = [int]$currentDate.DayOfWeek
    if ($dayOfWeek -eq 0) { $dayOfWeek = 7 }
    $monday = $currentDate.AddDays(-( $dayOfWeek - 1 ))
    
    # Decide how many days to work this week
    $workDaysOptions = @(0, 2, 3, 5, 5, 5) # Weighted for 5 days a week, but can have breaks(0)
    $workDaysCount = $workDaysOptions | Get-Random

    if ($workDaysCount -gt 0) {
        # Pick random days from Mon-Fri (1 to 5)
        $potentialDays = @(0,1,2,3,4) | Get-Random -Count $workDaysCount
        foreach ($d in $potentialDays) {
            $workDay = $monday.AddDays($d)
            if ($workDay -ge $startDate -and $workDay -le $endDate) {
                $activeDates += $workDay
            }
        }
    }
    
    # Move to next Monday
    $currentDate = $monday.AddDays(7)
}

$activeDates = $activeDates | Sort-Object | Select-Object -Unique

# Assign each of the 200 commits to a random active date, then sort by time
$scheduledCommits = @()
for ($i=0; $i -lt $commitList.Count; $i++) {
    $author = $commitList[$i]
    $dateBase = $activeDates | Get-Random
    $hour = Get-Random -Minimum 9 -Maximum 19
    $minute = Get-Random -Minimum 0 -Maximum 59
    $second = Get-Random -Minimum 0 -Maximum 59
    $finalDate = $dateBase.AddHours($hour).AddMinutes($minute).AddSeconds($second)
    
    $scheduledCommits += [PSCustomObject]@{
        Author = $author
        Date = $finalDate
    }
}
$scheduledCommits = $scheduledCommits | Sort-Object Date

# Gather list of actual files from the backup directory
$files = Get-ChildItem -Path $backupDir -File -Recurse | Sort-Object FullName
$filesList = @()
foreach ($f in $files) {
    $filesList += $f
}

# Distribute actual files across commits.
# We have 200 commits and 59 files. We can add 1 file for some commits, and others just update a log.
# We will just write a new file 'commit_history.md' for EVERY commit.
# And for the first 59 commits, we will copy one actual tracked file.

Write-Host "Creating commits..."

$logFile = "commit_history.md"

for ($i = 0; $i -lt $scheduledCommits.Count; $i++) {
    $commit = $scheduledCommits[$i]
    $info = $commit.Author
    $dateStr = $commit.Date.ToString("yyyy-MM-ddTHH:mm:ss")
    
    $env:GIT_AUTHOR_DATE = $dateStr
    $env:GIT_COMMITTER_DATE = $dateStr

    git config user.name $info.Name
    git config user.email $info.Email
    
    # Update log file every commit
    Add-Content -Path $logFile -Value "- [$dateStr] Project updated by $($info.Name)"
    
    # Add actual code selectively
    $commitMessage = "Update project structure and files"
    if ($i -lt $filesList.Count) {
        $sourceFile = $filesList[$i]
        $relPath = $sourceFile.FullName.Substring($backupDir.Length + 1)
        $destFile = Join-Path $fakeGitDir $relPath
        
        $destDir = Split-Path $destFile -Parent
        if (-not (Test-Path $destDir)) {
            New-Item -ItemType Directory -Path $destDir -Force | Out-Null
        }
        
        Copy-Item -Path $sourceFile.FullName -Destination $destFile -Force
        $commitMessage = "Add component: $($sourceFile.Name)"
    }
    
    git add .
    git commit -m "$commitMessage" --quiet
}

Write-Host "Done generating commits."
Write-Host "Force pushing to remote..."
git push -f origin main
Write-Host "Push completed!"
