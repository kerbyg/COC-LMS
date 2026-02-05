<?php
/**
 * CIT-LMS Instructor Sidebar
 * Modern Milwaukee Bucks Theme
 */

$currentPage = $currentPage ?? '';
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="logo">
            <span class="logo-icon">📖</span>
            <span class="logo-text">CIT-LMS</span>
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section">
            <span class="nav-section-title">Main Menu</span>
            <a href="dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>
        </div>
        
        <div class="nav-section">
            <span class="nav-section-title">Teaching</span>
            <a href="my-classes.php" class="nav-item <?= $currentPage === 'my-classes' ? 'active' : '' ?>">
                <span class="nav-icon">📚</span>
                <span class="nav-text">My Classes</span>
            </a>
            <a href="students.php" class="nav-item <?= $currentPage === 'students' ? 'active' : '' ?>">
                <span class="nav-icon">👥</span>
                <span class="nav-text">Students</span>
            </a>
            <a href="lessons.php" class="nav-item <?= $currentPage === 'lessons' ? 'active' : '' ?>">
                <span class="nav-icon">📖</span>
                <span class="nav-text">Lessons</span>
            </a>
            <a href="quizzes.php" class="nav-item <?= $currentPage === 'quizzes' ? 'active' : '' ?>">
                <span class="nav-icon">📝</span>
                <span class="nav-text">Quizzes</span>
            </a>
        </div>
        
        <div class="nav-section">
            <span class="nav-section-title">Assessment</span>
            <a href="gradebook.php" class="nav-item <?= $currentPage === 'gradebook' ? 'active' : '' ?>">
                <span class="nav-icon">📋</span>
                <span class="nav-text">Gradebook</span>
            </a>
            <a href="analytics.php" class="nav-item <?= $currentPage === 'analytics' ? 'active' : '' ?>">
                <span class="nav-icon">📈</span>
                <span class="nav-text">Analytics</span>
            </a>
        </div>
        
        <div class="nav-section">
            <span class="nav-section-title">Communication</span>
            <a href="announcements.php" class="nav-item <?= $currentPage === 'announcements' ? 'active' : '' ?>">
                <span class="nav-icon">📢</span>
                <span class="nav-text">Announcements</span>
            </a>
        </div>
        
        <div class="nav-section">
            <span class="nav-section-title">Account</span>
            <a href="profile.php" class="nav-item <?= $currentPage === 'profile' ? 'active' : '' ?>">
                <span class="nav-icon">👤</span>
                <span class="nav-text">My Profile</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/auth/logout.php" class="nav-item logout">
                <span class="nav-icon">🚪</span>
                <span class="nav-text">Logout</span>
            </a>
        </div>
    </nav>
    
    <div class="sidebar-footer">
        <?php 
        $user = Auth::user();
        $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
        ?>
        <div class="user-card">
            <div class="user-avatar"><?= $initials ?></div>
            <div class="user-info">
                <span class="user-name"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></span>
                <span class="user-role">Instructor</span>
            </div>
        </div>
    </div>
</aside>