<?php
/**
 * ============================================================
 * Database Setup Script
 * ============================================================
 * This script helps you set up the database automatically.
 * Run this once to create the database and import the schema.
 *
 * HOW TO USE:
 * 1. Make sure XAMPP MySQL is running
 * 2. Open in browser: http://localhost/COC-LMS/setup_database.php
 * 3. Click "Setup Database"
 * ============================================================
 */

// Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cit_lms');
define('SQL_FILE', __DIR__ . '/database/cit_lms.sql');

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])) {
    try {
        // Connect to MySQL (without selecting database)
        $pdo = new PDO(
            "mysql:host=" . DB_HOST,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // Check if database exists
        $stmt = $pdo->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
        $dbExists = $stmt->rowCount() > 0;

        if ($dbExists) {
            // Drop existing database
            $pdo->exec("DROP DATABASE `" . DB_NAME . "`");
            $message .= "Dropped existing database '" . DB_NAME . "'<br>";
        }

        // Create database
        $pdo->exec("CREATE DATABASE `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $message .= "Created database '" . DB_NAME . "'<br>";

        // Select the database
        $pdo->exec("USE `" . DB_NAME . "`");

        // Read SQL file
        if (!file_exists(SQL_FILE)) {
            throw new Exception("SQL file not found: " . SQL_FILE);
        }

        $sql = file_get_contents(SQL_FILE);

        // Remove comments and split into statements
        $sql = preg_replace('/--.*\n/', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        // Execute SQL statements
        $pdo->exec($sql);

        $message .= "Imported database schema successfully!<br>";
        $message .= "<br><strong style='color: green;'>✓ Setup Complete!</strong><br><br>";
        $message .= "You can now login with:<br>";
        $message .= "<strong>Student:</strong> maria.santos@student.cit-lms.edu.ph<br>";
        $message .= "<strong>Instructor:</strong> juan.delacruz@cit-lms.edu.ph<br>";
        $message .= "<strong>Admin:</strong> admin@cit-lms.edu.ph<br>";
        $message .= "<strong>Password:</strong> password123<br><br>";
        $message .= "<a href='/COC-LMS/pages/auth/login.php' style='color: blue; text-decoration: underline;'>Go to Login Page →</a>";

    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - CIT-LMS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #1a202c;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #718096;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .info-box {
            background: #f7fafc;
            border-left: 4px solid #4299e1;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .info-box strong {
            color: #2d3748;
            display: block;
            margin-bottom: 8px;
        }
        .info-box ul {
            margin-left: 20px;
            color: #4a5568;
            font-size: 14px;
        }
        .info-box li {
            margin: 5px 0;
        }
        .message {
            background: #c6f6d5;
            border: 1px solid #9ae6b4;
            color: #22543d;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .error {
            background: #fed7d7;
            border: 1px solid #fc8181;
            color: #742a2a;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .warning {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            color: #78350f;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Database Setup</h1>
        <p class="subtitle">CIT-LMS Database Installation</p>

        <?php if ($message): ?>
            <div class="message"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <div class="info-box">
            <strong>Before you proceed:</strong>
            <ul>
                <li>Make sure XAMPP MySQL is running</li>
                <li>This will create a new database called 'cit_lms'</li>
                <li>If database exists, it will be dropped and recreated</li>
            </ul>
        </div>

        <div class="warning">
            ⚠️ <strong>Warning:</strong> If the database 'cit_lms' already exists, ALL DATA will be lost!
        </div>

        <form method="POST">
            <button type="submit" name="setup" class="btn">
                Setup Database Now
            </button>
        </form>

        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center; color: #718096; font-size: 13px;">
            After setup, delete this file for security
        </div>
    </div>
</body>
</html>
