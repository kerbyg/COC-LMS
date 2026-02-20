<?php
/**
 * ============================================================
 * CIT-LMS Topbar Include
 * ============================================================
 * Modern Milwaukee Bucks Theme (#00461B + #EFEBD2)
 * Top navigation bar with search, notifications, user dropdown
 * ============================================================
 */

$userName = Auth::name();
$userRole = Auth::role();
$user = Auth::user();
$initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));

// Helper function for role display name
if (!function_exists('getRoleName')) {
    function getRoleName($role) {
        $roles = [
            'admin' => 'Administrator',
            'dean' => 'Dean',
            'instructor' => 'Instructor',
            'student' => 'Student'
        ];
        return $roles[$role] ?? ucfirst($role);
    }
}
?>

<!-- Topbar -->
<header class="topbar">
    
    <!-- Left Side -->
    <div class="topbar-left">
        <!-- Mobile Sidebar Toggle -->
        <button class="topbar-btn mobile-menu-btn" id="sidebar-toggle" title="Toggle Menu">
            ☰
        </button>
        
        <!-- Page Title -->
        <h1 class="page-title"><?= e($pageTitle ?? 'Dashboard') ?></h1>
    </div>
    
    <!-- Right Side -->
    <div class="topbar-right">
        
        <!-- Search Button -->
        <button class="topbar-btn" title="Search" onclick="toggleSearch()">
            🔍
        </button>
        
        <!-- Notifications -->
        <div class="dropdown">
            <button class="topbar-btn" title="Notifications" data-dropdown-toggle>
                🔔
                <span class="badge">3</span>
            </button>
            <div class="dropdown-menu notification-dropdown">
                <div class="dropdown-header">
                    <strong>Notifications</strong>
                    <a href="#">Mark all read</a>
                </div>
                <div class="dropdown-body">
                    <a href="#" class="notification-item unread">
                        <span class="notification-icon">📝</span>
                        <div class="notification-content">
                            <span class="notification-title">New quiz available</span>
                            <span class="notification-time">2 minutes ago</span>
                        </div>
                    </a>
                    <a href="#" class="notification-item unread">
                        <span class="notification-icon">📢</span>
                        <div class="notification-content">
                            <span class="notification-title">New announcement posted</span>
                            <span class="notification-time">1 hour ago</span>
                        </div>
                    </a>
                    <a href="#" class="notification-item">
                        <span class="notification-icon">✅</span>
                        <div class="notification-content">
                            <span class="notification-title">Quiz graded: 85%</span>
                            <span class="notification-time">Yesterday</span>
                        </div>
                    </a>
                </div>
                <div class="dropdown-footer">
                    <a href="#">View all notifications</a>
                </div>
            </div>
        </div>
        
        <!-- User Dropdown -->
        <div class="dropdown">
            <div class="topbar-user" data-dropdown-toggle>
                <div class="topbar-user-avatar"><?= $initials ?></div>
                <div class="topbar-user-info">
                    <span class="topbar-user-name"><?= e($userName) ?></span>
                    <span class="topbar-user-role"><?= getRoleName($userRole) ?></span>
                </div>
                <span class="dropdown-arrow">▼</span>
            </div>
            
            <!-- User Dropdown Menu -->
            <div class="dropdown-menu user-dropdown">
                <a href="<?= BASE_URL ?>/pages/<?= $userRole ?>/profile.php" class="dropdown-item">
                    <span>👤</span>
                    <span>My Profile</span>
                </a>
                
                <?php if ($userRole === 'admin'): ?>
                <a href="<?= BASE_URL ?>/pages/admin/settings.php" class="dropdown-item">
                    <span>⚙️</span>
                    <span>Settings</span>
                </a>
                <?php endif; ?>
                
                <div class="dropdown-divider"></div>
                
                <a href="javascript:void(0)" onclick="logout()" class="dropdown-item danger">
                    <span>🚪</span>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
    </div>
    
</header>

<style>
/* Topbar Additional Styles */
.mobile-menu-btn {
    display: none;
}

@media (max-width: 1024px) {
    .mobile-menu-btn {
        display: flex !important;
    }
}

/* Dropdown Styles */
.dropdown {
    position: relative;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    min-width: 200px;
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--gray-200);
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transform: translateY(10px);
    transition: var(--transition);
    z-index: 1000;
    margin-top: 8px;
}

.dropdown.active .dropdown-menu {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    transform: translateY(0);
}

.dropdown-header {
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-100);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.dropdown-header a {
    font-size: 12px;
    color: var(--primary);
}

.dropdown-body {
    max-height: 300px;
    overflow-y: auto;
}

.dropdown-footer {
    padding: 12px 16px;
    border-top: 1px solid var(--gray-100);
    text-align: center;
}

.dropdown-footer a {
    font-size: 13px;
    color: var(--primary);
    font-weight: 500;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    color: var(--gray-700);
    font-size: 14px;
    transition: var(--transition-fast);
}

.dropdown-item:hover {
    background: var(--gray-50);
    color: var(--primary);
}

.dropdown-item.danger:hover {
    background: var(--danger-bg);
    color: var(--danger);
}

.dropdown-divider {
    height: 1px;
    background: var(--gray-100);
    margin: 4px 0;
}

/* Notification Dropdown */
.notification-dropdown {
    width: 320px;
}

.notification-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-50);
    transition: var(--transition-fast);
}

.notification-item:hover {
    background: var(--gray-50);
}

.notification-item.unread {
    background: var(--cream-light);
}

.notification-icon {
    font-size: 20px;
    flex-shrink: 0;
}

.notification-content {
    flex: 1;
}

.notification-title {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: var(--gray-800);
    margin-bottom: 2px;
}

.notification-time {
    font-size: 11px;
    color: var(--gray-500);
}

/* User Section in Topbar */
.topbar-user {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 12px;
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: var(--transition);
}

.topbar-user:hover {
    background: var(--gray-100);
}

.topbar-user-avatar {
    width: 38px;
    height: 38px;
    background: var(--primary);
    color: var(--white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
}

.topbar-user-info {
    display: flex;
    flex-direction: column;
}

.topbar-user-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--gray-800);
}

.topbar-user-role {
    font-size: 12px;
    color: var(--gray-500);
}

.dropdown-arrow {
    font-size: 10px;
    color: var(--gray-400);
    margin-left: 4px;
}

/* User Dropdown */
.user-dropdown {
    width: 200px;
}

@media (max-width: 768px) {
    .topbar-user-info {
        display: none;
    }
    
    .dropdown-arrow {
        display: none;
    }
}
</style>

<script>
// Toggle Sidebar
document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
    document.querySelector('.sidebar').classList.toggle('active');
});

// Dropdown Toggle
document.querySelectorAll('[data-dropdown-toggle]').forEach(function(toggle) {
    toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        const dropdown = this.closest('.dropdown');
        
        // Close other dropdowns
        document.querySelectorAll('.dropdown.active').forEach(function(d) {
            if (d !== dropdown) d.classList.remove('active');
        });
        
        dropdown.classList.toggle('active');
    });
});

// Close dropdowns when clicking outside
document.addEventListener('click', function() {
    document.querySelectorAll('.dropdown.active').forEach(function(d) {
        d.classList.remove('active');
    });
});

// Search Toggle
function toggleSearch() {
    alert('Search feature coming soon!');
}

// Logout function
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = '<?= BASE_URL ?>/pages/auth/logout.php';
    }
}
</script>