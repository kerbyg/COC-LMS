<?php
/**
 * ============================================================
 * CIT-LMS Authentication & Session Management
 * ============================================================
 * Handles user authentication, sessions, and access control.
 * 
 * Usage:
 *   require_once 'config/auth.php';
 *   
 *   // Check if logged in
 *   if (Auth::check()) { ... }
 *   
 *   // Require login (redirects if not logged in)
 *   Auth::requireLogin();
 *   
 *   // Require specific role
 *   Auth::requireRole('admin');
 *   Auth::requireRole(['admin', 'instructor']);
 * ============================================================
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Session configuration
    ini_set('session.cookie_httponly', 1);      // Prevent JavaScript access to session cookie
    ini_set('session.use_only_cookies', 1);     // Only use cookies for sessions
    ini_set('session.cookie_secure', 0);        // Set to 1 in production with HTTPS
    ini_set('session.gc_maxlifetime', 7200);    // Session lifetime: 2 hours
    
    session_start();
}

// Include constants for BASE_URL
require_once __DIR__ . '/constants.php';

/**
 * Auth Class
 * Handles all authentication-related functions
 */
class Auth {
    
    /**
     * Check if user is logged in
     * 
     * @return bool
     */
    public static function check() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Alias for check()
     * 
     * @return bool
     */
    public static function isLoggedIn() {
        return self::check();
    }
    
    /**
     * Get current user's ID
     * 
     * @return int|null
     */
    public static function id() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Alias for id()
     * 
     * @return int|null
     */
    public static function userId() {
        return self::id();
    }
    
    /**
     * Get current user's role
     * 
     * @return string|null
     */
    public static function role() {
        return $_SESSION['user_role'] ?? null;
    }
    
    /**
     * Alias for role()
     * 
     * @return string|null
     */
    public static function userRole() {
        return self::role();
    }
    
    /**
     * Get current user's full name
     * 
     * @return string
     */
    public static function name() {
        return $_SESSION['user_name'] ?? 'Guest';
    }
    
    /**
     * Alias for name()
     * 
     * @return string
     */
    public static function userName() {
        return self::name();
    }
    
    /**
     * Get current user's email
     * 
     * @return string|null
     */
    public static function email() {
        return $_SESSION['user_email'] ?? null;
    }
    
    /**
     * Get current user's profile image
     * 
     * @return string
     */
    public static function avatar() {
        return $_SESSION['user_avatar'] ?? BASE_URL . '/assets/images/default-avatar.svg';
    }
    
    /**
     * Get all user session data
     *
     * @return array
     */
    public static function user() {
        if (!self::check()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'first_name' => $_SESSION['first_name'] ?? '',
            'last_name' => $_SESSION['last_name'] ?? '',
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role'],
            'avatar' => self::avatar()
        ];
    }
    
    /**
     * Log in a user (store user data in session)
     * 
     * @param array $user - User data from database
     * @return bool
     */
    public static function login($user) {
        if (empty($user) || !isset($user['users_id'])) {
            return false;
        }
        
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        // Store user data in session
        $_SESSION['user_id'] = $user['users_id'];
        $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_avatar'] = $user['profile_image'] ?? null;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Store additional useful data
        $_SESSION['employee_id'] = $user['employee_id'] ?? null;
        $_SESSION['student_id'] = $user['student_id'] ?? null;
        $_SESSION['department_id'] = $user['department_id'] ?? null;
        $_SESSION['program_id'] = $user['program_id'] ?? null;
        
        return true;
    }
    
    /**
     * Log out the current user
     */
    public static function logout() {
        // Clear all session data
        $_SESSION = [];
        
        // Delete the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // Destroy the session
        session_destroy();
    }
    
    /**
     * Require user to be logged in
     * Redirects to login page if not authenticated
     * 
     * @param string|null $redirectUrl - URL to redirect after login
     */
    public static function requireLogin($redirectUrl = null) {
        if (!self::check()) {
            // Store intended URL for redirect after login
            if ($redirectUrl) {
                $_SESSION['intended_url'] = $redirectUrl;
            } else {
                $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '';
            }
            
            header('Location: ' . BASE_URL . '/pages/auth/login.php');
            exit;
        }
        
        // Check session timeout (2 hours of inactivity)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
            self::logout();
            header('Location: ' . BASE_URL . '/pages/auth/login.php?timeout=1');
            exit;
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Require user to have a specific role
     * Redirects to unauthorized page if role doesn't match
     * 
     * @param string|array $roles - Required role(s)
     */
    public static function requireRole($roles) {
        // First, make sure user is logged in
        self::requireLogin();
        
        // Convert string to array for consistency
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        // Check if user's role matches any required role
        if (!in_array(self::role(), $roles)) {
            header('Location: ' . BASE_URL . '/pages/shared/unauthorized.php');
            exit;
        }
    }
    
    /**
     * Check if current user has a specific role
     * 
     * @param string|array $roles - Role(s) to check
     * @return bool
     */
    public static function hasRole($roles) {
        if (!self::check()) {
            return false;
        }
        
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        return in_array(self::role(), $roles);
    }
    
    /**
     * Check if user is admin
     * 
     * @return bool
     */
    public static function isAdmin() {
        return self::hasRole('admin');
    }
    
    /**
     * Check if user is instructor
     * 
     * @return bool
     */
    public static function isInstructor() {
        return self::hasRole('instructor');
    }
    
    /**
     * Check if user is student
     * 
     * @return bool
     */
    public static function isStudent() {
        return self::hasRole('student');
    }
    
    /**
     * Check if user is dean
     * 
     * @return bool
     */
    public static function isDean() {
        return self::hasRole('dean');
    }
    
    /**
     * Get the intended URL (where user wanted to go before login)
     * 
     * @return string|null
     */
    public static function intendedUrl() {
        $url = $_SESSION['intended_url'] ?? null;
        unset($_SESSION['intended_url']);
        return $url;
    }
    
    /**
     * Get redirect URL based on user role
     * 
     * @return string
     */
    public static function dashboardUrl() {
        switch (self::role()) {
            case 'admin':
                return BASE_URL . '/pages/admin/dashboard.php';
            case 'dean':
                return BASE_URL . '/pages/dean/dashboard.php';
            case 'instructor':
                return BASE_URL . '/pages/instructor/dashboard.php';
            case 'student':
                return BASE_URL . '/pages/student/dashboard.php';
            default:
                return BASE_URL . '/pages/auth/login.php';
        }
    }
    
    /**
     * Hash a password
     * 
     * @param string $password - Plain text password
     * @return string - Hashed password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify a password against hash
     * 
     * @param string $password - Plain text password
     * @param string $hash - Hashed password from database
     * @return bool
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate a random token (for password reset, etc.)
     * 
     * @param int $length - Token length
     * @return string
     */
    public static function generateToken($length = 64) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Get CSRF token (creates one if doesn't exist)
     * 
     * @return string
     */
    public static function csrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateToken(32);
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     * 
     * @param string $token - Token to verify
     * @return bool
     */
    public static function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get CSRF hidden input field
     * 
     * @return string - HTML input field
     */
    public static function csrfField() {
        return '<input type="hidden" name="csrf_token" value="' . self::csrfToken() . '">';
    }
}