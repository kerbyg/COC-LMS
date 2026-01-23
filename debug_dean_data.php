<?php
require_once 'config/database.php';

echo "<h2>Debug Dean Data</h2>";

// 1. Check subjects
echo "<h3>1. Subjects in Database:</h3>";
$subjects = db()->fetchAll("SELECT * FROM subject ORDER BY subject_code");
if (empty($subjects)) {
    echo "<p style='color:red'>NO SUBJECTS FOUND!</p>";
} else {
    echo "<table border='1' cellpadding='6' style='border-collapse:collapse'>";
    echo "<tr style='background:#f0f0f0'><th>ID</th><th>Code</th><th>Name</th><th>Dept ID</th><th>Status</th></tr>";
    foreach ($subjects as $s) {
        echo "<tr><td>{$s['subject_id']}</td><td>{$s['subject_code']}</td><td>{$s['subject_name']}</td><td>{$s['department_id']}</td><td>{$s['status']}</td></tr>";
    }
    echo "</table>";
}

// 2. Check subject_offered
echo "<h3>2. Subject Offerings in Database:</h3>";
$offerings = db()->fetchAll("SELECT * FROM subject_offered ORDER BY subject_offered_id DESC");
if (empty($offerings)) {
    echo "<p style='color:red'>NO SUBJECT OFFERINGS FOUND!</p>";
} else {
    echo "<table border='1' cellpadding='6' style='border-collapse:collapse'>";
    echo "<tr style='background:#f0f0f0'><th>ID</th><th>Subject ID</th><th>Academic Year</th><th>Semester</th><th>Status</th></tr>";
    foreach ($offerings as $o) {
        echo "<tr><td>{$o['subject_offered_id']}</td><td>{$o['subject_id']}</td><td>{$o['academic_year']}</td><td>{$o['semester']}</td><td>{$o['status']}</td></tr>";
    }
    echo "</table>";
}

// 3. Check what the dean sections page query would return
echo "<h3>3. Dean's Department (ID 1) - Offerings Query:</h3>";
$deanOfferings = db()->fetchAll(
    "SELECT so.subject_offered_id, so.academic_year, so.semester, so.status as offering_status,
            s.subject_code, s.subject_name, s.department_id
     FROM subject_offered so
     JOIN subject s ON so.subject_id = s.subject_id
     WHERE s.department_id = 1
     ORDER BY so.academic_year DESC, s.subject_code"
);

if (empty($deanOfferings)) {
    echo "<p style='color:red'>NO OFFERINGS FOR DEAN'S DEPARTMENT!</p>";
    echo "<p>Possible causes:</p>";
    echo "<ul>";
    echo "<li>Subject offerings exist but subjects are not assigned to department_id = 1</li>";
    echo "<li>The subject_id in subject_offered doesn't match any subject with department_id = 1</li>";
    echo "</ul>";
} else {
    echo "<table border='1' cellpadding='6' style='border-collapse:collapse'>";
    echo "<tr style='background:#d4edda'><th>Offering ID</th><th>Subject Code</th><th>Subject Name</th><th>Dept ID</th><th>Year</th><th>Semester</th><th>Status</th></tr>";
    foreach ($deanOfferings as $o) {
        echo "<tr><td>{$o['subject_offered_id']}</td><td>{$o['subject_code']}</td><td>{$o['subject_name']}</td><td>{$o['department_id']}</td><td>{$o['academic_year']}</td><td>{$o['semester']}</td><td>{$o['offering_status']}</td></tr>";
    }
    echo "</table>";
}

// 4. Check what the sections dropdown should show
echo "<h3>4. Offerings for Sections Dropdown (status IN 'open','active'):</h3>";
$dropdownOfferings = db()->fetchAll(
    "SELECT so.subject_offered_id, so.academic_year, so.semester, so.status,
            s.subject_code, s.subject_name, s.department_id
     FROM subject_offered so
     JOIN subject s ON so.subject_id = s.subject_id
     WHERE so.status IN ('open', 'active') AND s.department_id = 1
     ORDER BY so.academic_year DESC, s.subject_code"
);

if (empty($dropdownOfferings)) {
    echo "<p style='color:orange'>NO OFFERINGS WITH STATUS 'open' or 'active' FOR DEAN!</p>";
    echo "<p><strong>Solution:</strong> Make sure subject offerings have status = 'open' or 'active'</p>";
} else {
    echo "<table border='1' cellpadding='6' style='border-collapse:collapse'>";
    echo "<tr style='background:#d4edda'><th>Offering ID</th><th>Subject Code</th><th>Year</th><th>Semester</th><th>Status</th></tr>";
    foreach ($dropdownOfferings as $o) {
        echo "<tr><td>{$o['subject_offered_id']}</td><td>{$o['subject_code']}</td><td>{$o['academic_year']}</td><td>{$o['semester']}</td><td>{$o['status']}</td></tr>";
    }
    echo "</table>";
}

// 5. Show subject_offered table structure
echo "<h3>5. subject_offered Table Structure:</h3>";
$cols = db()->fetchAll("SHOW COLUMNS FROM subject_offered");
echo "<table border='1' cellpadding='6' style='border-collapse:collapse'>";
echo "<tr style='background:#f0f0f0'><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
foreach ($cols as $c) {
    echo "<tr><td>{$c['Field']}</td><td>{$c['Type']}</td><td>{$c['Null']}</td><td>{$c['Default']}</td></tr>";
}
echo "</table>";
