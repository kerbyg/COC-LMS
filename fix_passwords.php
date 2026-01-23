<?php
/**
 * ============================================================
 * Password Fix Script
 * ============================================================
 * This script updates all user passwords to "password123"
 * Run this once if you're having login issues
 * ============================================================
 */

// Load database config
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix'])) {
    try {
        // Generate new password hash for "password123"
        $newPassword = 'password123';
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update all users
        $result = db()->execute(
            "UPDATE users SET password = ?",
            [$passwordHash]
        );

        if ($result) {
            $message = "✓ Success! All user passwords have been updated to: <strong>password123</strong><br><br>";
            $message .= "You can now login with:<br>";
            $message .= "• <strong>Student:</strong> maria.santos@student.cit-lms.edu.ph<br>";
            $message .= "• <strong>Instructor:</strong> juan.delacruz@cit-lms.edu.ph<br>";
            $message .= "• <strong>Admin:</strong> admin@cit-lms.edu.ph<br>";
            $message .= "• <strong>Password:</strong> password123<br><br>";
            $message .= "<a href='/COC-LMS/pages/auth/login.php' style='display: inline-block; margin-top: 10px; padding: 10px 20px; background: #4299e1; color: white; text-decoration: none; border-radius: 6px;'>Go to Login →</a>";
        } else {
            $error = "Failed to update passwords. Please check database connection.";
        }

    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Test current password
$testHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
$passwordWorks = password_verify('password', $testHash);
$password123Works = password_verify('password123', $testHash);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Passwords - CIT-LMS</title>
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
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .info-box strong {
            color: #2d3748;
            display: block;
            margin-bottom: 8px;
        }
        .info-box code {
            background: #edf2f7;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            color: #d97706;
        }
        .message {
            background: #c6f6d5;
            border: 1px solid #9ae6b4;
            color: #22543d;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.8;
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
        .debug-box {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Fix Password Issue</h1>
        <p class="subtitle">Reset all user passwords to "password123"</p>

        <?php if ($message): ?>
            <div class="message"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <div class="debug-box">
            <strong>Password Hash Debug:</strong><br>
            Current hash: <code><?= substr($testHash, 0, 30) ?>...</code><br>
            Works with "password": <?= $passwordWorks ? '✓ YES' : '✗ NO' ?><br>
            Works with "password123": <?= $password123Works ? '✓ YES' : '✗ NO' ?>
        </div>

        <div class="info-box">
            <strong>What this does:</strong><br>
            This will update ALL user passwords in the database to <code>password123</code> so you can login.
        </div>

        <form method="POST">
            <button type="submit" name="fix" class="btn">
                Update All Passwords to "password123"
            </button>
        </form>

        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center; color: #718096; font-size: 13px;">
            Delete this file after fixing passwords
        </div>
    </div>
</body>
</html>
