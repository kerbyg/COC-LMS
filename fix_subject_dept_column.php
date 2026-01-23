<?php
require_once 'config/database.php';

echo "<h2>Fix Subject Table - Add Department Column</h2>";

// Check if department_id column exists
$cols = db()->fetchAll("SHOW COLUMNS FROM subject");
$colNames = array_column($cols, 'Field');
$hasDeptId = in_array('department_id', $colNames);

echo "<h3>Current Subject Table Columns:</h3>";
echo "<pre>" . implode(", ", $colNames) . "</pre>";

if ($hasDeptId) {
    echo "<p style='color:green'>department_id column already exists!</p>";
} else {
    echo "<p style='color:orange'>department_id column is MISSING!</p>";

    if (isset($_POST['add_column'])) {
        // Add department_id column
        db()->execute("ALTER TABLE subject ADD COLUMN department_id INT(11) NULL AFTER program_id");

        // Add foreign key index
        try {
            db()->execute("ALTER TABLE subject ADD INDEX idx_subject_department (department_id)");
        } catch (Exception $e) {
            // Index might already exist
        }

        echo "<div style='background:#d4edda;padding:15px;border-radius:8px;margin:20px 0;'>";
        echo "<strong>Column Added!</strong> department_id has been added to the subject table.";
        echo "</div>";

        $hasDeptId = true;
    } else {
        echo "<form method='POST'>";
        echo "<button type='submit' name='add_column' style='background:#007bff;color:#fff;padding:12px 24px;border:none;border-radius:6px;font-size:16px;cursor:pointer;'>Add department_id Column</button>";
        echo "</form>";
    }
}

// If column exists, allow assigning departments
if ($hasDeptId) {
    echo "<h3>Assign Subjects to Departments:</h3>";

    // Get departments
    $departments = db()->fetchAll("SELECT * FROM department ORDER BY department_name");

    // Get subjects
    $subjects = db()->fetchAll("SELECT * FROM subject ORDER BY subject_code");

    if (isset($_POST['assign'])) {
        $deptId = (int)$_POST['dept_id'];

        // Assign all CC subjects to CIT department
        db()->execute("UPDATE subject SET department_id = ? WHERE subject_code LIKE 'CC%'", [$deptId]);

        // Assign GE subjects to the same department (or you can change this)
        db()->execute("UPDATE subject SET department_id = ? WHERE subject_code LIKE 'GE%'", [$deptId]);

        echo "<div style='background:#d4edda;padding:15px;border-radius:8px;margin:20px 0;'>";
        echo "<strong>Done!</strong> Subjects have been assigned to the department.";
        echo "</div>";
        echo "<p><a href='fix_subject_dept_column.php'>Refresh to see changes</a></p>";
    } else {
        echo "<table border='1' cellpadding='8' style='border-collapse:collapse;margin-bottom:20px;'>";
        echo "<tr style='background:#f0f0f0'><th>ID</th><th>Code</th><th>Name</th><th>Current Dept ID</th></tr>";
        foreach ($subjects as $s) {
            $deptVal = $s['department_id'] ?? 'NULL';
            $style = empty($s['department_id']) ? "background:#fff3cd" : "";
            echo "<tr style='$style'><td>{$s['subject_id']}</td><td>{$s['subject_code']}</td><td>{$s['subject_name']}</td><td>$deptVal</td></tr>";
        }
        echo "</table>";

        echo "<form method='POST'>";
        echo "<p>Select department to assign subjects to:</p>";
        echo "<select name='dept_id' style='padding:10px;font-size:16px;margin-bottom:15px;'>";
        foreach ($departments as $d) {
            $sel = $d['department_id'] == 1 ? 'selected' : '';
            echo "<option value='{$d['department_id']}' $sel>{$d['department_name']}</option>";
        }
        echo "</select><br>";
        echo "<button type='submit' name='assign' style='background:#28a745;color:#fff;padding:12px 24px;border:none;border-radius:6px;font-size:16px;cursor:pointer;'>Assign All Subjects to Department</button>";
        echo "</form>";
    }
}

echo "<hr style='margin:30px 0;'>";
echo "<h3>After Fixing:</h3>";
echo "<ol style='font-size:16px;line-height:2;'>";
echo "<li>Run this fix to add column and assign departments</li>";
echo "<li>Login as <strong>Dean</strong></li>";
echo "<li>Go to <strong>Sections</strong> - offerings should now appear in dropdown</li>";
echo "</ol>";
