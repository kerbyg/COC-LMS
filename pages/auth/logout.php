<?php
/**
 * ============================================================
 * CIT-LMS Logout Page
 * ============================================================
 * Logs out the user and redirects to login page
 * ============================================================
 */

// Load config
require_once __DIR__ . '/../../config/auth.php';

// Log activity before logout
if (Auth::check()) {
    require_once __DIR__ . '/../../config/database.php';
    
    try {
        db()->execute(
            "INSERT INTO activity_logs (users_id, activity_type, activity_description, ip_address, created_at) 
             VALUES (?, ?, ?, ?, NOW())",
            [
                Auth::id(),
                'logout',
                'User logged out',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]
        );
    } catch (Exception $e) {
        // Continue even if logging fails
    }
}

// Logout
Auth::logout();

// Redirect to login page
header('Location: /COC-LMS/pages/auth/login.php');
exit;