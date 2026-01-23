<?php
require_once 'config/database.php';

echo "<h2>Subject Offerings Check</h2>";

// Check subject offerings
$offerings = db()->fetchAll(
    "SELECT so.*, s.subject_code, s.subject_name, s.department_id, d.department_name
     FROM subject_offered so
     JOIN subject s ON so.subject_id = s.subject_id
     LEFT JOIN department d ON s.department_id = d.department_id
     ORDER BY so.academic_year DESC, s.subject_code"
);

if (empty($offerings)) {
    echo "<p style='color:orange; font-size:18px;'><strong>No subject offerings found!</strong></p>";
    echo "<p>The dean needs to create subject offerings first before creating sections.</p>";
    echo "<p>Go to: <a href='pages/dean/subject-offerings.php'>Dean > Subject Offerings</a></p>";
} else {
    echo "<h3>Existing Subject Offerings:</h3>";
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
    echo "<tr style='background:#f0f0f0'><th>ID</th><th>Subject Code</th><th>Subject Name</th><th>Academic Year</th><th>Semester</th><th>Department</th><th>Status</th></tr>";
    foreach ($offerings as $off) {
        echo "<tr>";
        echo "<td>{$off['subject_offered_id']}</td>";
        echo "<td>{$off['subject_code']}</td>";
        echo "<td>{$off['subject_name']}</td>";
        echo "<td>{$off['academic_year']}</td>";
        echo "<td>{$off['semester']}</td>";
        echo "<td>{$off['department_name']}</td>";
        echo "<td>{$off['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check subjects available for the dean's department (ID 1)
echo "<h3>Subjects in College of Information Technology (Dept ID 1):</h3>";
$subjects = db()->fetchAll("SELECT * FROM subject WHERE department_id = 1 ORDER BY subject_code");

if (empty($subjects)) {
    echo "<p style='color:red'>No subjects found for this department!</p>";
} else {
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
    echo "<tr style='background:#f0f0f0'><th>ID</th><th>Code</th><th>Name</th><th>Units</th><th>Status</th></tr>";
    foreach ($subjects as $sub) {
        echo "<tr>";
        echo "<td>{$sub['subject_id']}</td>";
        echo "<td>{$sub['subject_code']}</td>";
        echo "<td>{$sub['subject_name']}</td>";
        echo "<td>{$sub['units']}</td>";
        echo "<td>{$sub['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}
