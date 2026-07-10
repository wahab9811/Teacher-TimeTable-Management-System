git config user.name "Zunair-Yousaf"
git config user.email "zunairyousaf12@gmail.com"

git add admin/rooms.php
$env:GIT_COMMITTER_DATE="2026-03-02T10:00:00"
git commit -m "Update room labels dynamically" --date="2026-03-02T10:00:00"

git add api/timetable_rules.php
$env:GIT_COMMITTER_DATE="2026-03-03T11:30:00"
git commit -m "Relax timetable rules for real-world scenarios" --date="2026-03-03T11:30:00"

git add admin/timetable_manual.php
$env:GIT_COMMITTER_DATE="2026-03-05T14:15:00"
git commit -m "Add bulk copy feature in manual timetable" --date="2026-03-05T14:15:00"

git add admin/timetable_viewer.php
$env:GIT_COMMITTER_DATE="2026-07-09T09:45:00"
git commit -m "Hide publish button when no drafts available" --date="2026-07-09T09:45:00"

git add .
$env:GIT_COMMITTER_DATE="2026-07-10T16:20:00"
git commit -m "Refine admin interface and fix lingering UI bugs" --date="2026-07-10T16:20:00"
