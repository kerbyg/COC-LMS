<?php
/**
 * ============================================================
 * CIT-LMS Authentication API
 * ============================================================
 * Handles: Login, Logout, Session Check
 * 
 * Endpoints:
 *   GET  ?action=check     - Check if user is logged in
 *   GET  ?action=logout    - Logout current user
 *   GET  ?action=me        - Get current user data
 *   POST ?action=login     - Login with email/password
 * ============================================================
 */

// Headers for JSON API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load config files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/jwt.php';

// Allow JWT via Authorization header for API actions
header('Access-Control-Allow-Headers', 'Content-Type, Authorization');

// Get the action from query string
$action = $_GET['action'] ?? '';

// Route to appropriate handler
switch ($action) {
    case 'login':
        handleLogin();
        break;
    
    case 'logout':
        handleLogout();
        break;
    
    case 'check':
        handleCheck();
        break;

    case 'verify-token':
        handleVerifyToken();
        break;
    
    case 'me':
        handleGetCurrentUser();
        break;

    case 'update-profile':
        handleUpdateProfile();
        break;

    case 'change-password':
        handleChangePassword();
        break;

    default:
        jsonResponse(false, 'Invalid action', null, 400);
}

/**
 * ─────────────────────────────────────────────────────────────
 * HANDLE LOGIN
 * ─────────────────────────────────────────────────────────────
 */
function handleLogin() {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Method not allowed', null, 405);
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $userId = trim($input['user_id'] ?? $input['email'] ?? '');
    $password = $input['password'] ?? '';

    // Validation
    if (empty($userId)) {
        jsonResponse(false, 'User ID is required');
    }

    if (empty($password)) {
        jsonResponse(false, 'Password is required');
    }

    try {
        // Find user by student_id or employee_id
        $user = db()->fetchOne(
            "SELECT * FROM users WHERE student_id = ? OR employee_id = ? LIMIT 1",
            [$userId, $userId]
        );

        // Check if user exists
        if (!$user) {
            logActivity(null, 'login_failed', "Failed login attempt for user ID: $userId");
            jsonResponse(false, 'Invalid ID or password');
        }
        
        // Check if user is active
        if ($user['status'] !== 'active') {
            logActivity($user['users_id'], 'login_blocked', 'Login blocked - account not active');
            jsonResponse(false, 'Your account is not active. Please contact administrator.');
        }

        // Verify password
        if (!Auth::verifyPassword($password, $user['password'])) {
            logActivity($user['users_id'], 'login_failed', 'Failed login - incorrect password');
            jsonResponse(false, 'Invalid ID or password');
        }
        
        // Login successful - create session
        Auth::login($user);
        
        // Update last login timestamp
        db()->execute(
            "UPDATE users SET updated_at = NOW() WHERE users_id = ?",
            [$user['users_id']]
        );
        
        // Log successful login
        logActivity($user['users_id'], 'login_success', 'User logged in successfully');

        // Generate JWT token
        $token = JWT::generate($user);

        // Get redirect URL based on role
        $redirectUrl = Auth::dashboardUrl();

        // Return success response with JWT token
        jsonResponse(true, 'Login successful', [
            'user' => [
                'id'    => $user['users_id'],
                'name'  => trim($user['first_name'] . ' ' . $user['last_name']),
                'email' => $user['email'],
                'role'  => $user['role']
            ],
            'token'    => $token,
            'redirect' => $redirectUrl
        ]);
        
    } catch (Exception $e) {
        error_log('Login error: ' . $e->getMessage());
        jsonResponse(false, 'An error occurred. Please try again.', null, 500);
    }
}

/**
 * ─────────────────────────────────────────────────────────────
 * HANDLE LOGOUT
 * ─────────────────────────────────────────────────────────────
 */
function handleLogout() {
    // Log the logout
    if (Auth::check()) {
        logActivity(Auth::id(), 'logout', 'User logged out');
    }
    
    // Destroy session
    Auth::logout();
    
    jsonResponse(true, 'Logged out successfully', [
        'redirect' => BASE_URL . '/pages/auth/login.php'
    ]);
}

/**
 * ─────────────────────────────────────────────────────────────
 * HANDLE CHECK (Check if logged in via Session or JWT)
 * ─────────────────────────────────────────────────────────────
 */
function handleCheck() {
    // Check JWT first
    $jwtUser = JWT::authenticate();
    if ($jwtUser) {
        jsonResponse(true, 'User is authenticated', [
            'authenticated' => true,
            'auth_method'   => 'jwt',
            'user'          => $jwtUser
        ]);
    }

    // Fall back to session
    if (Auth::check()) {
        jsonResponse(true, 'User is authenticated', [
            'authenticated' => true,
            'auth_method'   => 'session',
            'user'          => Auth::user()
        ]);
    }

    jsonResponse(true, 'User is not authenticated', [
        'authenticated' => false,
        'user'          => null
    ]);
}

/**
 * ─────────────────────────────────────────────────────────────
 * HANDLE VERIFY TOKEN (Validate JWT)
 * ─────────────────────────────────────────────────────────────
 */
function handleVerifyToken() {
    $payload = JWT::authenticate();
    if (!$payload) {
        jsonResponse(false, 'Invalid or expired token', null, 401);
    }
    jsonResponse(true, 'Token is valid', [
        'user'       => $payload,
        'expires_at' => date('Y-m-d H:i:s', $payload['exp'])
    ]);
}

/**
 * ─────────────────────────────────────────────────────────────
 * HANDLE GET CURRENT USER
 * ─────────────────────────────────────────────────────────────
 */
function handleGetCurrentUser() {
    if (!Auth::check()) {
        jsonResponse(false, 'Not authenticated', null, 401);
    }
    
    try {
        $user = db()->fetchOne(
            "SELECT 
                u.users_id,
                u.employee_id,
                u.student_id,
                u.email,
                u.first_name,
                u.last_name,
                u.middle_name,
                u.role,
                u.status,
                u.department_id,
                u.program_id,
                u.year_level,
                u.created_at,
                d.department_name,
                p.program_name,
                p.program_code
            FROM users u
            LEFT JOIN department d ON u.department_id = d.department_id
            LEFT JOIN program p ON u.program_id = p.program_id
            WHERE u.users_id = ?",
            [Auth::id()]
        );
        
        if (!$user) {
            jsonResponse(false, 'User not found', null, 404);
        }
        
        jsonResponse(true, 'User data retrieved', ['user' => $user]);
        
    } catch (Exception $e) {
        error_log('Get user error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to get user data', null, 500);
    }
}

/**
 * ─────────────────────────────────────────────────────────────
 * HANDLE UPDATE PROFILE
 * ─────────────────────────────────────────────────────────────
 */
function handleUpdateProfile() {
    if (!Auth::check()) {
        jsonResponse(false, 'Not authenticated', null, 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $firstName = trim($input['first_name'] ?? '');
    $lastName = trim($input['last_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $userId = Auth::id();

    if (!$firstName || !$lastName || !$email) {
        jsonResponse(false, 'All fields are required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Invalid email format');
    }

    try {
        // Check email uniqueness
        $existing = db()->fetchOne(
            "SELECT users_id FROM users WHERE email = ? AND users_id != ?",
            [$email, $userId]
        );
        if ($existing) {
            jsonResponse(false, 'Email is already in use');
        }

        db()->execute(
            "UPDATE users SET first_name = ?, last_name = ?, email = ?, updated_at = NOW() WHERE users_id = ?",
            [$firstName, $lastName, $email, $userId]
        );

        jsonResponse(true, 'Profile updated successfully');
    } catch (Exception $e) {
        error_log('Profile update error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to update profile', null, 500);
    }
}

/**
 * ─────────────────────────────────────────────────────────────
 * HANDLE CHANGE PASSWORD
 * ─────────────────────────────────────────────────────────────
 */
function handleChangePassword() {
    if (!Auth::check()) {
        jsonResponse(false, 'Not authenticated', null, 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $userId = Auth::id();

    if (!$currentPassword || !$newPassword) {
        jsonResponse(false, 'Current and new password are required');
    }

    if (strlen($newPassword) < 6) {
        jsonResponse(false, 'New password must be at least 6 characters');
    }

    try {
        $user = db()->fetchOne("SELECT password FROM users WHERE users_id = ?", [$userId]);
        if (!$user || !Auth::verifyPassword($currentPassword, $user['password'])) {
            jsonResponse(false, 'Current password is incorrect');
        }

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        db()->execute(
            "UPDATE users SET password = ?, updated_at = NOW() WHERE users_id = ?",
            [$hashed, $userId]
        );

        jsonResponse(true, 'Password changed successfully');
    } catch (Exception $e) {
        error_log('Password change error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to change password', null, 500);
    }
}

/**
 * ─────────────────────────────────────────────────────────────
 * HELPER: JSON Response
 * ─────────────────────────────────────────────────────────────
 */
function jsonResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}

/**
 * ─────────────────────────────────────────────────────────────
 * HELPER: Log Activity
 * ─────────────────────────────────────────────────────────────
 */
function logActivity($userId, $activityType, $description) {
    try {
        db()->execute(
            "INSERT INTO activity_logs (users_id, activity_type, activity_description, ip_address, created_at) 
             VALUES (?, ?, ?, ?, NOW())",
            [
                $userId,
                $activityType,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]
        );
    } catch (Exception $e) {
        error_log('Activity log error: ' . $e->getMessage());
    }
}