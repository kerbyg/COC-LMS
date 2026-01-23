<?php
/**
 * CIT-LMS - Profile Page
 * View and edit student profile
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();
$pageTitle = 'My Profile';
$currentPage = 'profile';
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $contactNumber = trim($_POST['contact_number'] ?? '');

        if (empty($firstName) || empty($lastName)) {
            $error = 'First name and last name are required.';
        } else {
            try {
                db()->execute(
                    "UPDATE users SET first_name = ?, last_name = ?, contact_number = ?, updated_at = NOW() WHERE users_id = ?",
                    [$firstName, $lastName, $contactNumber, $userId]
                );

                // Update session
                $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name'] = $lastName;

                $success = 'Profile updated successfully!';
            } catch (Exception $e) {
                $error = 'Failed to update profile.';
            }
        }
    }
    
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'All password fields are required.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            // Verify current password
            $user = db()->fetchOne("SELECT password FROM users WHERE users_id = ?", [$userId]);
            
            if (!password_verify($currentPassword, $user['password'])) {
                $error = 'Current password is incorrect.';
            } else {
                try {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    db()->execute(
                        "UPDATE users SET password = ?, updated_at = NOW() WHERE users_id = ?",
                        [$hashedPassword, $userId]
                    );
                    $success = 'Password changed successfully!';
                } catch (Exception $e) {
                    $error = 'Failed to change password.';
                }
            }
        }
    }
}

// Get user data
$user = db()->fetchOne(
    "SELECT u.*, p.program_code, p.program_name
     FROM users u
     LEFT JOIN program p ON u.program_id = p.program_id
     WHERE u.users_id = ?",
    [$userId]
);

// Get student stats
$stats = db()->fetchOne(
    "SELECT
        (SELECT COUNT(*) FROM student_subject WHERE user_student_id = ? AND status = 'enrolled') as subjects,
        (SELECT COUNT(*) FROM student_progress WHERE user_student_id = ? AND status = 'completed') as lessons,
        (SELECT COUNT(*) FROM student_quiz_attempts WHERE user_student_id = ? AND status = 'completed') as quizzes
    ",
    [$userId, $userId, $userId]
);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>
    
    <div class="page-content">
        
        <!-- Page Header -->
        <div class="page-head">
            <h1>👤 My Profile</h1>
            <p>View and manage your account information</p>
        </div>
        
        <!-- Messages -->
        <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        
        <div class="profile-grid">
            
            <!-- Profile Card -->
            <div class="profile-card">
                <div class="profile-avatar">
                    <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                </div>
                <h2><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                <p class="profile-role">Student</p>
                
                <div class="profile-stats">
                    <div class="pstat">
                        <span class="pstat-value"><?= $stats['subjects'] ?></span>
                        <span class="pstat-label">Subjects</span>
                    </div>
                    <div class="pstat">
                        <span class="pstat-value"><?= $stats['lessons'] ?></span>
                        <span class="pstat-label">Lessons</span>
                    </div>
                    <div class="pstat">
                        <span class="pstat-value"><?= $stats['quizzes'] ?></span>
                        <span class="pstat-label">Quizzes</span>
                    </div>
                </div>
                
                <div class="profile-info">
                    <div class="info-row">
                        <span class="info-label">📧 Email</span>
                        <span class="info-value"><?= e($user['email']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">🎓 Program</span>
                        <span class="info-value"><?= e($user['program_code'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">🏫 Section</span>
                        <span class="info-value"><?= e($user['section'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">📅 Joined</span>
                        <span class="info-value"><?= date('M d, Y', strtotime($user['created_at'])) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Edit Forms -->
            <div class="profile-forms">
                
                <!-- Edit Profile -->
                <div class="form-card">
                    <h3>✏️ Edit Profile</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" value="<?= e($user['first_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" value="<?= e($user['last_name']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" value="<?= e($user['email']) ?>" disabled>
                            <small>Contact admin to change email</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="contact_number" value="<?= e($user['contact_number'] ?? '') ?>" placeholder="09XX XXX XXXX">
                        </div>
                        
                        <button type="submit" class="btn-primary">Save Changes</button>
                    </form>
                </div>
                
                <!-- Change Password -->
                <div class="form-card">
                    <h3>🔒 Change Password</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn-primary">Change Password</button>
                    </form>
                </div>
                
            </div>
        </div>
        
    </div>
</main>

<style>
/* Profile Page Styles */

.page-head {
    margin-bottom: 24px;
}
.page-head h1 {
    font-size: 22px;
    color: #1c1917;
    margin: 0 0 4px;
}
.page-head p {
    color: #78716c;
    margin: 0;
    font-size: 14px;
}

/* Alerts */
.alert {
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
}
.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}
.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

/* Profile Grid */
.profile-grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 24px;
}

/* Profile Card */
.profile-card {
    background: #fff;
    border: 1px solid #f5f0e8;
    border-radius: 16px;
    padding: 32px;
    text-align: center;
}
.profile-avatar {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    font-weight: 700;
    margin: 0 auto 16px;
}
.profile-card h2 {
    font-size: 20px;
    color: #1c1917;
    margin: 0 0 4px;
}
.profile-role {
    display: inline-block;
    background: #dcfce7;
    color: #16a34a;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 24px;
}

.profile-stats {
    display: flex;
    justify-content: center;
    gap: 24px;
    padding: 20px 0;
    border-top: 1px solid #f5f0e8;
    border-bottom: 1px solid #f5f0e8;
    margin-bottom: 20px;
}
.pstat {
    text-align: center;
}
.pstat-value {
    display: block;
    font-size: 24px;
    font-weight: 700;
    color: #16a34a;
}
.pstat-label {
    font-size: 12px;
    color: #78716c;
}

.profile-info {
    text-align: left;
}
.info-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #f5f0e8;
}
.info-row:last-child {
    border-bottom: none;
}
.info-label {
    font-size: 14px;
    color: #78716c;
}
.info-value {
    font-size: 14px;
    color: #1c1917;
    font-weight: 500;
}

/* Form Cards */
.profile-forms {
    display: flex;
    flex-direction: column;
    gap: 24px;
}
.form-card {
    background: #fff;
    border: 1px solid #f5f0e8;
    border-radius: 12px;
    padding: 24px;
}
.form-card h3 {
    font-size: 16px;
    color: #1c1917;
    margin: 0 0 20px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.form-group {
    margin-bottom: 16px;
}
.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: #57534e;
    margin-bottom: 6px;
}
.form-group input {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #e7e5e4;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
}
.form-group input:focus {
    outline: none;
    border-color: #16a34a;
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
}
.form-group input:disabled {
    background: #f5f5f4;
    color: #78716c;
}
.form-group small {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: #a8a29e;
}

.btn-primary {
    padding: 12px 24px;
    background: #16a34a;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-primary:hover {
    background: #15803d;
}

/* Responsive */
@media (max-width: 900px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
    .profile-card {
        max-width: 400px;
        margin: 0 auto;
    }
}
@media (max-width: 500px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    .profile-stats {
        gap: 16px;
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>