<?php
// admin/sections.php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

$message = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO sections (ProgramID, DepartmentID, SemesterID, Name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['program_id'], $_POST['dept_id'], $_POST['semester_id'], $_POST['name']]);
        $message = "Section added successfully.";
    } elseif ($_POST['action'] === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM sections WHERE SectionID = ?");
        $stmt->execute([$_POST['id']]);
        $message = "Section deleted successfully.";
    }
}

$programs = $pdo->query("SELECT * FROM programs")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments")->fetchAll();
$semesters = $pdo->query("SELECT * FROM semesters")->fetchAll();

$query = "
    SELECT s.*, p.Name as ProgramName, d.Name as DeptName, sem.Label as SemesterName 
    FROM sections s
    JOIN programs p ON s.ProgramID = p.ProgramID
    JOIN departments d ON s.DepartmentID = d.DepartmentID
    JOIN semesters sem ON s.SemesterID = sem.SemesterID
    ORDER BY p.ProgramID, d.DepartmentID, sem.SemesterID, s.Name
";
$sections = $pdo->query($query)->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-4">Manage Sections</h2>
        <?php if($message): ?><div class="bg-gray-100 text-maroon font-bold p-3 rounded mb-4"><?php echo $message; ?></div><?php endif; ?>
        
        <div class="bg-white p-6 shadow-md rounded mb-6">
            <h3 class="font-bold mb-4 border-b pb-2">Add New Section</h3>
            <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 items-end">
                <input type="hidden" name="action" value="add">
                
                <div class="md:col-span-1">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Program</label>
                    <select name="program_id" id="prog_select" required class="w-full border p-2 rounded">
                        <option value="">Select Program</option>
                        <?php foreach($programs as $p): ?>
                            <option value="<?php echo $p['ProgramID']; ?>"><?php echo htmlspecialchars($p['Name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="md:col-span-1">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Group / Dept</label>
                    <select name="dept_id" id="dept_select" required class="w-full border p-2 rounded">
                        <option value="">Select Group</option>
                        <!-- To make this rigorous, we should ideally filter by program via JS. For now showing all -->
                        <?php foreach($departments as $d): ?>
                            <option value="<?php echo $d['DepartmentID']; ?>" data-prog="<?php echo $d['ProgramID']; ?>"><?php echo htmlspecialchars($d['Name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="md:col-span-1">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Year / Semester</label>
                    <select name="semester_id" id="sem_select" required class="w-full border p-2 rounded">
                        <option value="">Select Year</option>
                        <?php foreach($semesters as $s): ?>
                            <option value="<?php echo $s['SemesterID']; ?>" data-prog="<?php echo $s['ProgramID']; ?>"><?php echo htmlspecialchars($s['Label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="md:col-span-1">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Section Name</label>
                    <input type="text" name="name" placeholder="e.g. A, B, M1" required class="w-full border p-2 rounded">
                </div>
                
                <div class="md:col-span-1">
                    <button type="submit" class="w-full btn-maroon p-2 rounded font-bold h-10">Add Section</button>
                </div>
            </form>
        </div>
        
        <div class="bg-white p-6 shadow-md rounded">
            <h3 class="font-bold mb-4 border-b pb-2">Existing Sections</h3>
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-3 border-b">Program</th>
                        <th class="p-3 border-b">Group/Dept</th>
                        <th class="p-3 border-b">Year/Sem</th>
                        <th class="p-3 border-b border-r border-gray-300">Sections</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Group sections by Program+Dept+Semester for cleaner UI
                    $grouped = [];
                    foreach($sections as $s) {
                        $key = $s['ProgramName'] . '|' . $s['DeptName'] . '|' . $s['SemesterName'];
                        if(!isset($grouped[$key])) $grouped[$key] = [];
                        $grouped[$key][] = $s;
                    }
                    
                    if(empty($grouped)): ?>
                        <tr><td colspan="4" class="p-4 text-center text-gray-500">No sections defined yet.</td></tr>
                    <?php else:
                    foreach($grouped as $key => $secs): 
                        list($prog, $dept, $sem) = explode('|', $key);
                    ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-3 font-semibold text-gray-800"><?php echo htmlspecialchars($prog); ?></td>
                        <td class="p-3 text-gray-700"><?php echo htmlspecialchars($dept); ?></td>
                        <td class="p-3 text-gray-700"><?php echo htmlspecialchars($sem); ?></td>
                        <td class="p-3">
                            <div class="flex flex-wrap gap-2">
                                <?php foreach($secs as $s): ?>
                                <div class="inline-flex items-center gap-2 bg-[#a60b26]/10 text-[#a60b26] px-3 py-1 rounded-full text-sm font-bold border border-[#a60b26]/20">
                                    Section <?php echo htmlspecialchars($s['Name']); ?>
                                    <form method="POST" class="inline m-0" onsubmit="return confirm('Are you sure you want to delete Section <?php echo htmlspecialchars($s['Name']); ?>? This will cascade delete any courses mapped to this section!');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $s['SectionID']; ?>">
                                        <button type="submit" class="text-[#a60b26] hover:text-red-700 hover:bg-[#a60b26]/20 rounded-full w-5 h-5 flex items-center justify-center transition-colors">&times;</button>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Simple filter to only show depts and sems belonging to selected program
document.getElementById('prog_select').addEventListener('change', function() {
    let pid = this.value;
    
    // Filter Depts
    let deptOpts = document.getElementById('dept_select').options;
    for(let i=1; i<deptOpts.length; i++) {
        if(pid === "" || deptOpts[i].getAttribute('data-prog') === pid) {
            deptOpts[i].style.display = 'block';
        } else {
            deptOpts[i].style.display = 'none';
        }
    }
    document.getElementById('dept_select').value = "";
    
    // Filter Sems
    let semOpts = document.getElementById('sem_select').options;
    for(let i=1; i<semOpts.length; i++) {
        if(pid === "" || semOpts[i].getAttribute('data-prog') === pid) {
            semOpts[i].style.display = 'block';
        } else {
            semOpts[i].style.display = 'none';
        }
    }
    document.getElementById('sem_select').value = "";
});
</script>

<?php include '../includes/footer.php'; ?>
