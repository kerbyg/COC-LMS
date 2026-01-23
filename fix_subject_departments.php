<?php
require_once 'config/database.php';

echo "<h2>Fix Subject Department Assignments</h2>";

// Check all subjects and their current department
echo "<h3>Current Subject Assignments:</h3>";
$subjects = db()->fetchAll(
    "SELECT s.*, d.department_name
     FROM subject s
     LEFT JOIN department d ON s.department_id = d.department_id
     ORDER BY s.subject_code"
);

echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
echo "<tr style='background:#f0f0f0'><th>ID</th><th>Code</th><th>Name</th><th>Current Dept ID</th><th>Current Dept Name</th></tr>";
foreach ($subjects as $sub) {
    $deptStyle = empty($sub['department_id']) ? "background:#ffcccc" : "";
    echo "<tr style='$deptStyle'>";
    echo "<td>{$sub['subject_id']}</td>";
    echo "<td>{$sub['subject_code']}</td>";
    echo "<td>{$sub['subject_name']}</td>";
    echo "<td>" . ($sub['department_id'] ?: '<span style="color:red">NULL</span>') . "</td>";
    echo "<td>" . ($sub['department_name'] ?: '<span style="color:red">NOT ASSIGNED</span>') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Show all departments
echo "<h3>Available Departments:</h3>";
$departments = db()->fetchAll("SELECT * FROM department ORDER BY department_id");
echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
echo "<tr style='background:#f0f0f0'><th>ID</th><th>Code</th><th>Name</th></tr>";
foreach ($departments as $dept) {
    echo "<tr>";
    echo "<td>{$dept['department_id']}</td>";
    echo "<td>{$dept['department_code']}</td>";
    echo "<td>{$dept['department_name']}</td>";
    echo "</tr>";
}
echo "</table>";

// Fix button
if (isset($_POST['fix'])) {
    $deptId = (int)$_POST['department_id'];

    // Update all subjects with NULL or 0 department_id
    $result = db()->execute(
        "UPDATE subject SET department_id = ? WHERE department_id IS NULL OR department_id = 0",
        [$deptId]
    );

    // Also update CC subjects (Computer Science subjects) to the selected department
    $result2 = db()->execute(
        "UPDATE subject SET department_id = ? WHERE subject_code LIKE 'CC%'",
        [$deptId]
    );

    echo "<div style='background:#d4edda;padding:15px;border-radius:8px;margin:20px 0;'>";
    echo "<strong>Done!</strong> Subjects have been assigned to department ID: $deptId";
    echo "</div>";
    echo "<p><a href='fix_subject_departments.php'>Refresh to see changes</a></p>";
} else {
    echo "<h3>Assign Subjects to Department:</h3>";
    echo "<form method='POST'>";
    echo "<p>Select department to assign all CC subjects to:</p>";
    echo "<select name='department_id' style='padding:10px;font-size:16px;'>";
    foreach ($departments as $dept) {
        $selected = $dept['department_id'] == 1 ? 'selected' : '';
        echo "<option value='{$dept['department_id']}' $selected>{$dept['department_name']}</option>";
    }
    echo "</select><br><br>";
    echo "<button type='submit' name='fix' style='background:#28a745;color:#fff;padding:12px 24px;border:none;border-radius:6px;font-size:16px;cursor:pointer;'>Assign Subjects to Department</button>";
    echo "</form>";
}
