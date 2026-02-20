<?php
/**
 * ============================================================
 * CIT-LMS Sidebar Include
 * ============================================================
 * Modern Milwaukee Bucks Theme (#00461B + #EFEBD2)
 * Dynamic navigation menu based on user role
 * ============================================================
 */

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentFolder = basename(dirname($_SERVER['PHP_SELF']));
$userRole = Auth::role();
$userName = Auth::name();
$user = Auth::user();
$initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    
    <!-- Sidebar Header / Logo -->
    <div class="sidebar-header">
        <a href="<?= BASE_URL ?>/pages/<?= $userRole ?>/dashboard.php" class="logo">
            <span class="logo-icon">📖</span>
            <span class="logo-text">CIT-LMS</span>
        </a>
    </div>
    
    <!-- Sidebar Navigation -->
    <nav class="sidebar-nav">
        
        <?php if ($userRole === 'admin'): ?>
        <!-- ═══════════════════════════════════════════
             ADMIN MENU
             ═══════════════════════════════════════════ -->
        
        <div class="nav-section">
            <span class="nav-section-title">Main</span>
            <a href="<?= BASE_URL ?>/pages/admin/dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>
        </div>
        
        <div class="nav-section">
            <span class="nav-section-title">Management</span>
            <a href="<?= BASE_URL ?>/pages/admin/users.php" class="nav-item <?= $currentPage === 'users' ? 'active' : '' ?>">
                <span class="nav-icon">👥</span>
                <span class="nav-text">Users</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/admin/departments.php" class="nav-item <?= $currentPage === 'departments' ? 'active' : '' ?>">
                <span class="nav-icon">🏢</span>
                <span class="nav-text">Departments</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/admin/programs.php" class="nav-item <?= $currentPage === 'programs' ? 'active' : '' ?>">
                <span class="nav-icon">🎓</span>
                <span class="nav-text">Programs</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/admin/subjects.php" class="nav-item <?= $currentPage === 'subjects' ? 'active' : '' ?>">
                <span class="nav-icon">📚</span>
                <span class="nav-text">Subjects</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/admin/curriculum.php" class="nav-item <?= $currentPage === 'curriculum' ? 'active' : '' ?>">
                <span class="nav-icon">📋</span>
                <span class="nav-text">Curriculum</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/admin/subject-offerings.php" class="nav-item <?= $currentPage === 'subject-offerings' ? 'active' : '' ?>">
                <span class="nav-icon">📅</span>
                <span class="nav-text">Subject Offerings</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/admin/sections.php" class="nav-item <?= $currentPage === 'sections' ? 'active' : '' ?>">
                <span class="nav-icon">🏫</span>
                <span class="nav-text">Sections</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/admin/faculty-assignments.php" class="nav-item <?= $currentPage === 'faculty-assignments' ? 'active' : '' ?>">
                <span class="nav-icon">👨‍🏫</span>
                <span class="nav-text">Faculty Assignments</span>
            </a>
        </div>

        <div class="nav-section">
            <span class="nav-section-title">Reports</span>
            <a href="<?= BASE_URL ?>/pages/admin/reports.php" class="nav-item <?= $currentPage === 'reports' ? 'active' : '' ?>">
                <span class="nav-icon">📈</span>
                <span class="nav-text">Reports & Analytics</span>
            </a>
        </div>
        
        <div class="nav-section">
            <span class="nav-section-title">System</span>
            <a href="<?= BASE_URL ?>/pages/admin/settings.php" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
                <span class="nav-icon">⚙️</span>
                <span class="nav-text">Settings</span>
            </a>
        </div>
        
        <?php elseif ($userRole === 'dean'): ?>
        <!-- ═══════════════════════════════════════════
             DEAN MENU
             ═══════════════════════════════════════════ -->
        
        <div class="nav-section">
            <span class="nav-section-title">Main</span>
            <a href="<?= BASE_URL ?>/pages/dean/dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>
        </div>
        
        <div class="nav-section">
            <span class="nav-section-title">Academic</span>
            <a href="<?= BASE_URL ?>/pages/dean/instructors.php" class="nav-item <?= $currentPage === 'instructors' ? 'active' : '' ?>">
                <span class="nav-icon">👨‍🏫</span>
                <span class="nav-text">Instructors</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/dean/subjects.php" class="nav-item <?= $currentPage === 'subjects' ? 'active' : '' ?>">
                <span class="nav-icon">📚</span>
                <span class="nav-text">Subjects</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/dean/subject-offerings.php" class="nav-item <?= $currentPage === 'subject-offerings' ? 'active' : '' ?>">
                <span class="nav-icon">📅</span>
                <span class="nav-text">Subject Offerings</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/dean/sections.php" class="nav-item <?= $currentPage === 'sections' ? 'active' : '' ?>">
                <span class="nav-icon">🏫</span>
                <span class="nav-text">Sections</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/dean/faculty-assignments.php" class="nav-item <?= $currentPage === 'faculty-assignments' ? 'active' : '' ?>">
                <span class="nav-icon">👥</span>
                <span class="nav-text">Faculty Assignments</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/dean/reports.php" class="nav-item <?= $currentPage === 'reports' ? 'active' : '' ?>">
                <span class="nav-icon">📈</span>
                <span class="nav-text">Reports</span>
            </a>
        </div>
        
        <?php elseif ($userRole === 'instructor'): ?>
        <!-- ═══════════════════════════════════════════
             INSTRUCTOR MENU
             ═══════════════════════════════════════════ -->
        
        <div class="nav-section">
            <span class="nav-section-title">Main</span>
            <a href="<?= BASE_URL ?>/pages/instructor/dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>
        </div>
        
        <div class="nav-section">
            <span class="nav-section-title">Teaching</span>
            <a href="<?= BASE_URL ?>/pages/instructor/my-classes.php" class="nav-item <?= $currentPage === 'my-classes' || $currentPage === 'my-subjects' ? 'active' : '' ?>">
                <span class="nav-icon">📚</span>
                <span class="nav-text">My Classes</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/instructor/students.php" class="nav-item <?= $currentPage === 'students' ? 'active' : '' ?>">
                <span class="nav-icon">👥</span>
                <span class="nav-text">Students</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/instructor/lessons.php" class="nav-item <?= $currentPage === 'lessons' || $currentPage === 'lesson-create' || $currentPage === 'lesson-edit' ? 'active' : '' ?>">
                <span class="nav-icon">📖</span>
                <span class="nav-text">Lessons</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/instructor/quizzes.php" class="nav-item <?= $currentPage === 'quizzes' || $currentPage === 'quiz-create' || $currentPage === 'quiz-edit' || $currentPage === 'quiz-questions' ? 'active' : '' ?>">
                <span class="nav-icon">📝</span>
                <span class="nav-text">Quizzes</span>
            </a>
        </div>
        
        <div class="nav-section">
            <span class="nav-section-title">Assessment</span>
            <a href="<?= BASE_URL ?>/pages/instructor/gradebook.php" class="nav-item <?= $currentPage === 'gradebook' ? 'active' : '' ?>">
                <span class="nav-icon">📋</span>
                <span class="nav-text">Gradebook</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/instructor/analytics.php" class="nav-item <?= $currentPage === 'analytics' ? 'active' : '' ?>">
                <span class="nav-icon">📈</span>
                <span class="nav-text">Analytics</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/instructor/remedials.php" class="nav-item <?= $currentPage === 'remedials' ? 'active' : '' ?>">
                <span class="nav-icon">📌</span>
                <span class="nav-text">Remedials</span>
            </a>
        </div>

        <div class="nav-section">
            <span class="nav-section-title">Communication</span>
            <a href="<?= BASE_URL ?>/pages/instructor/announcements.php" class="nav-item <?= $currentPage === 'announcements' ? 'active' : '' ?>">
                <span class="nav-icon">📢</span>
                <span class="nav-text">Announcements</span>
            </a>
        </div>
        
        <?php elseif ($userRole === 'student'): ?>
        <!-- ═══════════════════════════════════════════
             STUDENT MENU
             ═══════════════════════════════════════════ -->
        
        <div class="nav-section">
            <span class="nav-section-title">Main</span>
            <a href="<?= BASE_URL ?>/pages/student/dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>
        </div>
        
        <div class="nav-section">
            <span class="nav-section-title">Learning</span>
            <a href="<?= BASE_URL ?>/pages/student/enroll.php" class="nav-item <?= $currentPage === 'enroll' ? 'active' : '' ?>">
                <span class="nav-icon">🎓</span>
                <span class="nav-text">Enroll in Section</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/student/my-subjects.php" class="nav-item <?= $currentPage === 'my-subjects' ? 'active' : '' ?>">
                <span class="nav-icon">📚</span>
                <span class="nav-text">My Subjects</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/student/lessons.php" class="nav-item <?= $currentPage === 'lessons' || $currentPage === 'lesson-view' ? 'active' : '' ?>">
                <span class="nav-icon">📖</span>
                <span class="nav-text">Lessons</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/student/quizzes.php" class="nav-item <?= $currentPage === 'quizzes' || $currentPage === 'take-quiz' || $currentPage === 'quiz-result' ? 'active' : '' ?>">
                <span class="nav-icon">📝</span>
                <span class="nav-text">Quizzes</span>
            </a>
        </div>
        
        <div class="nav-section">
            <span class="nav-section-title">Progress</span>
            <a href="<?= BASE_URL ?>/pages/student/grades.php" class="nav-item <?= $currentPage === 'grades' ? 'active' : '' ?>">
                <span class="nav-icon">📋</span>
                <span class="nav-text">My Grades</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/student/progress.php" class="nav-item <?= $currentPage === 'progress' ? 'active' : '' ?>">
                <span class="nav-icon">📈</span>
                <span class="nav-text">My Progress</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/student/remedials.php" class="nav-item <?= $currentPage === 'remedials' ? 'active' : '' ?>">
                <span class="nav-icon">📌</span>
                <span class="nav-text">Remedials</span>
            </a>
        </div>
        
        <div class="nav-section">
            <span class="nav-section-title">Updates</span>
            <a href="<?= BASE_URL ?>/pages/student/announcements.php" class="nav-item <?= $currentPage === 'announcements' ? 'active' : '' ?>">
                <span class="nav-icon">📢</span>
                <span class="nav-text">Announcements</span>
            </a>
        </div>
        
        <?php endif; ?>
        
        <!-- ═══════════════════════════════════════════
             COMMON MENU (All Roles)
             ═══════════════════════════════════════════ -->
        
        <div class="nav-section">
            <span class="nav-section-title">Account</span>
            <a href="<?= BASE_URL ?>/pages/<?= $userRole ?>/profile.php" class="nav-item <?= $currentPage === 'profile' ? 'active' : '' ?>">
                <span class="nav-icon">👤</span>
                <span class="nav-text">My Profile</span>
            </a>
            <a href="javascript:void(0)" onclick="logout()" class="nav-item logout">
                <span class="nav-icon">🚪</span>
                <span class="nav-text">Logout</span>
            </a>
        </div>
        
    </nav>
    
    <!-- Sidebar Footer (User Info) -->
    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar"><?= $initials ?></div>
            <div class="user-info">
                <span class="user-name"><?= e($userName) ?></span>
                <span class="user-role"><?= getRoleName($userRole) ?></span>
            </div>
        </div>
    </div>
    
</aside>