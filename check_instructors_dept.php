<?php
require_once 'config/database.php';

echo "<h2>Debug: Instructors and Departments</h2>";

// Get dean info
$deanDept = db()->fetchOne("SELECT u.users_id, u.first_name, u.last_name, u.department_id, d.department_name 
    FROM users u 
    LEFT JOIN department d ON u.department_id = d.department_id 
    WHERE u.role = 'dean' LIMIT 1");

echo "<h3>Dean Info:</h3>";
echo "<p>Dean: {$deanDept['first_name']} {$deanDept['last_name']}, Department ID: <strong>{$deanDept['department_id']}</strong> ({$deanDept['department_name']})</p>";

echo "<h3>All Instructors:</h3>";
$instructors = db()->fetchAll("SELECT u.users_id, u.first_name, u.last_name, u.department_id, u.status, d.department_name 
    FROM users u 
    LEFT JOIN department d ON u.department_id = d.department_id 
    WHERE u.role = 'instructor' 
    ORDER BY u.last_name");

echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
echo "<tr style='background:#f0f0f0'><th>ID</th><th>Name</th><th>Dept ID</th><th>Department</th><th>Status</th><th>Same as Dean?</th></tr>";
foreach ($instructors as $i) {
    $sameAsDean = ($i['department_id'] == $deanDept['department_id']) ? '✅ Yes' : '❌ No';
    $style = ($i['department_id'] != $deanDept['department_id']) ? "background:#fff3cd" : "";
    echo "<tr style='$style'>";
    echo "<td>{$i['users_id']}</td>";
    echo "<td>{$i['first_name']} {$i['last_name']}</td>";
    echo "<td>{$i['department_id']}</td>";
    echo "<td>{$i['department_name']}</td>";
    echo "<td>{$i['status']}</td>";
    echo "<td>$sameAsDean</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Solution:</h3>";
echo "<p>If Cooper Flagg should be in the dean's department, run:</p>";
echo "<code>UPDATE users SET department_id = {$deanDept['department_id']} WHERE first_name = 'Cooper' AND last_name = 'Flagg';</code>";
