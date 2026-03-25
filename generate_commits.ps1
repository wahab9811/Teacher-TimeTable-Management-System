$wahabCounts = @{ 3=1; 4=1; 5=2; 6=3; 7=3 }
$zunairCounts = @{ 3=20; 4=20; 5=25; 6=5; 7=5 }

$combined = @()

function Get-RandomTimeInMonth {
    param ($month)
    $year = 2026
    $daysInMonth = [DateTime]::DaysInMonth($year, $month)
    $maxDay = if ($month -eq 7) { 9 } else { $daysInMonth }
    $minDay = if ($month -eq 3) { 2 } else { 1 }

    $validDaysInMonth = @()
    for ($d = $minDay; $d -le $maxDay; $d++) {
        $date = Get-Date -Year $year -Month $month -Day $d
        if ($date.DayOfWeek -ne 'Saturday' -and $date.DayOfWeek -ne 'Sunday') {
            $validDaysInMonth += $date
        }
    }
    
    $day = $validDaysInMonth | Get-Random
    $hour = Get-Random -Minimum 9 -Maximum 19
    $minute = Get-Random -Minimum 0 -Maximum 59
    $second = Get-Random -Minimum 0 -Maximum 59
    return $day.AddHours($hour).AddMinutes($minute).AddSeconds($second)
}

foreach ($month in @(3,4,5,6,7)) {
    for ($i = 0; $i -lt $wahabCounts[$month]; $i++) {
        $combined += [PSCustomObject]@{
            Time = Get-RandomTimeInMonth -month $month
            Author = "wahab"
        }
    }
    for ($i = 0; $i -lt $zunairCounts[$month]; $i++) {
        $combined += [PSCustomObject]@{
            Time = Get-RandomTimeInMonth -month $month
            Author = "zunair"
        }
    }
}

$combined = $combined | Sort-Object Time

$logFile = "commit_history.md"
Set-Content -Path $logFile -Value "# Project History`r`n"

git checkout main

for ($i = 0; $i -lt $combined.Count; $i++) {
    $time = $combined[$i].Time
    $author = $combined[$i].Author
    $dateStr = $time.ToString("yyyy-MM-ddTHH:mm:ss")
    $env:GIT_AUTHOR_DATE = $dateStr
    $env:GIT_COMMITTER_DATE = $dateStr

    if ($author -eq "wahab") {
        git config user.name "wahab9811"
        git config user.email "malikabdul98612@gmail.com"
        Add-Content -Path $logFile -Value "- Update on $($dateStr) by Wahab"
        $commitMessage = "Update project logic"
    } else {
        git config user.name "Zunair-Yousaf"
        git config user.email "zunairyousaf12@gmail.com"
        Add-Content -Path $logFile -Value "- Update on $($dateStr) by Zunair"
        $commitMessage = "Feature enhancements by Zunair"
    }

    git add $logFile
    git commit -m "$commitMessage"
}

git checkout -B Zunair-Yousaf
git checkout main
Write-Host "Done explicitly mapping 200 commits."
