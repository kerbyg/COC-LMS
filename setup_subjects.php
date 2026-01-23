<?php
require_once 'config/database.php';

echo "<h2>Setup Subjects for College of Information Technology</h2>";

$deptId = 1; // College of Information Technology

// Define subjects
$subjects = [
    ['CC101', 'Introduction to Computing', 3],
    ['CC102', 'Computer Programming 1', 3],
    ['CC103', 'Computer Programming 2', 3],
    ['CC104', 'Data Structures and Algorithms', 3],
    ['CC105', 'Database Management Systems', 3],
    ['CC106', 'Web Development', 3],
    ['CC107', 'Object-Oriented Programming', 3],
    ['CC108', 'Software Engineering', 3],
    ['CC109', 'Computer Networks', 3],
    ['CC110', 'Operating Systems', 3],
];

if (isset($_POST['create'])) {
    $created = 0;
    $skipped = 0;

    foreach ($subjects as $sub) {
        // Check if subject already exists
        $existing = db()->fetchOne("SELECT subject_id FROM subject WHERE subject_code = ?", [$sub[0]]);

        if ($existing) {
            // Update department if exists
            db()->execute("UPDATE subject SET department_id = ? WHERE subject_code = ?", [$deptId, $sub[0]]);
            $skipped++;
        } else {
            // Create new subject
            db()->execute(
                "INSERT INTO subject (subject_code, subject_name, units, department_id, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 'active', NOW(), NOW())",
                [$sub[0], $sub[1], $sub[2], $deptId]
            );
            $created++;
        }
    }

    echo "<div style='background:#d4edda;padding:20px;border-radius:8px;margin:20px 0;'>";
    echo "<h3 style='margin:0 0 10px;color:#155724;'>Success!</h3>";
    echo "<p style='margin:0;'>Created: <strong>$created</strong> subjects</p>";
    echo "<p style='margin:0;'>Updated: <strong>$skipped</strong> existing subjects</p>";
    echo "</div>";

    echo "<h3>Next Steps:</h3>";
    echo "<ol style='font-size:16px;line-height:2;'>";
    echo "<li>Login as <strong>Dean</strong></li>";
    echo "<li>Go to <strong>Subjects</strong> - you should now see the subjects</li>";
    echo "<li>Go to <strong>Subject Offerings</strong> - create offerings for the current semester</li>";
    echo "<li>Go to <strong>Sections</strong> - create sections for each offering</li>";
    echo "</ol>";

    echo "<p><a href='pages/dean/subjects.php' style='background:#007bff;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;display:inline-block;margin-top:10px;'>Go to Dean Subjects →</a></p>";

} else {
    echo "<h3>Subjects to be created:</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
    echo "<tr style='background:#f0f0f0'><th>Code</th><th>Name</th><th>Units</th><th>Department</th></tr>";
    foreach ($subjects as $sub) {
        echo "<tr>";
        echo "<td><strong>{$sub[0]}</strong></td>";
        echo "<td>{$sub[1]}</td>";
        echo "<td>{$sub[2]}</td>";
        echo "<td>College of Information Technology</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<form method='POST' style='margin-top:20px;'>";
    echo "<button type='submit' name='create' style='background:#28a745;color:#fff;padding:15px 30px;border:none;border-radius:8px;font-size:18px;cursor:pointer;'>Create These Subjects</button>";
    echo "</form>";
}
