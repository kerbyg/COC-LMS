<?php
require_once 'config/database.php';

echo "<h2>Checking Sections Data</h2>";

// Get all sections with their department info
$sections = db()->fetchAll(
    "SELECT sec.section_id, sec.section_name, sec.enrollment_code,
            so.subject_offered_id, so.academic_year, so.semester,
            s.subject_id, s.subject_code, s.subject_name, s.department_id,
            d.department_name
     FROM section sec
     JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     LEFT JOIN department d ON s.department_id = d.department_id
     ORDER BY d.department_name, s.subject_code, sec.section_name"
);

echo "<h3>All Sections by Department:</h3>";
echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
echo "<tr style='background:#f0f0f0'><th>Section ID</th><th>Section Name</th><th>Subject Code</th><th>Subject Name</th><th>Department</th><th>Enrollment Code</th></tr>";

$currentDept = null;
foreach ($sections as $sec) {
    if ($currentDept !== $sec['department_name']) {
        $currentDept = $sec['department_name'];
        echo "<tr style='background:#fffacd'><td colspan='6'><strong>Department: " . ($currentDept ?: 'NO DEPARTMENT') . "</strong></td></tr>";
    }
    echo "<tr>";
    echo "<td>{$sec['section_id']}</td>";
    echo "<td>{$sec['section_name']}</td>";
    echo "<td>{$sec['subject_code']}</td>";
    echo "<td>{$sec['subject_name']}</td>";
    echo "<td>" . ($sec['department_name'] ?: '<span style="color:red">NULL</span>') . "</td>";
    echo "<td>{$sec['enrollment_code']}</td>";
    echo "</tr>";
}
echo "</table>";

// Check dean users and their departments
echo "<h3>Dean Users and Their Departments:</h3>";
$deans = db()->fetchAll(
    "SELECT u.users_id, u.first_name, u.last_name, u.department_id, d.department_name
     FROM users u
     LEFT JOIN department d ON u.department_id = d.department_id
     WHERE u.role = 'dean'"
);

echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
echo "<tr style='background:#f0f0f0'><th>User ID</th><th>Name</th><th>Department ID</th><th>Department Name</th></tr>";
foreach ($deans as $dean) {
    echo "<tr>";
    echo "<td>{$dean['users_id']}</td>";
    echo "<td>{$dean['first_name']} {$dean['last_name']}</td>";
    echo "<td>" . ($dean['department_id'] ?: '<span style="color:red">NULL</span>') . "</td>";
    echo "<td>" . ($dean['department_name'] ?: '<span style="color:red">NOT ASSIGNED</span>') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check subjects without department
echo "<h3>Subjects Without Department (Problem!):</h3>";
$noDepSubjects = db()->fetchAll("SELECT * FROM subject WHERE department_id IS NULL OR department_id = 0");
if (empty($noDepSubjects)) {
    echo "<p style='color:green'>All subjects have a department assigned.</p>";
} else {
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
    echo "<tr style='background:#f0f0f0'><th>Subject ID</th><th>Subject Code</th><th>Subject Name</th></tr>";
    foreach ($noDepSubjects as $sub) {
        echo "<tr style='background:#ffcccc'>";
        echo "<td>{$sub['subject_id']}</td>";
        echo "<td>{$sub['subject_code']}</td>";
        echo "<td>{$sub['subject_name']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}
