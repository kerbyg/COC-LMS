<?php
/**
 * ============================================================
 * CIT-LMS Login Page
 * ============================================================
 * Public page - no authentication required
 * Redirects to dashboard if already logged in
 * ============================================================
 */

// Load config (but don't require auth)
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/auth.php';

// If already logged in, redirect to dashboard
if (Auth::check()) {
    header('Location: ' . Auth::dashboardUrl());
    exit;
}

// Check for session timeout message
$timeout = isset($_GET['timeout']) && $_GET['timeout'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <title>Login | <?= APP_NAME ?></title>
    
    <meta name="description" content="Login to <?= APP_NAME ?> - <?= APP_FULL_NAME ?>">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/auth.css">
</head>
<body>
    
    <div class="auth-page">
        <div class="auth-container">
            
            <!-- Login Card -->
            <div class="auth-card">
                
                <!-- Header -->
                <div class="auth-header">
                    <div class="auth-logo">
                        <span class="auth-logo-icon">📚</span>
                        <span class="auth-logo-text"><?= APP_NAME ?></span>
                    </div>
                    <h1>Welcome Back!</h1>
                    <p>Sign in to continue to your dashboard</p>
                </div>
                
                <!-- Body -->
                <div class="auth-body">
                    
                    <!-- Error/Success Container -->
                    <div id="error-container" style="display: none;"></div>
                    
                    <!-- Session Timeout Message -->
                    <?php if ($timeout): ?>
                    <div class="auth-error">
                        <span class="auth-error-icon">⏰</span>
                        <span>Your session has expired. Please login again.</span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Demo Credentials (Remove in Production!) -->
                    <div class="demo-credentials">
                        <div class="demo-credentials-title">
                            <span>🔑</span>
                            <span>Demo Credentials</span>
                        </div>
                        <div class="demo-credentials-list">
                            <p>
                                <strong>Admin:</strong> 
                                <a href="#" data-demo-email="admin@cit-lms.edu.ph" data-demo-password="password123">
                                    <code>admin@cit-lms.edu.ph</code>
                                </a>
                            </p>
                            <p>
                                <strong>Instructor:</strong> 
                                <a href="#" data-demo-email="juan.delacruz@cit-lms.edu.ph" data-demo-password="password123">
                                    <code>juan.delacruz@cit-lms.edu.ph</code>
                                </a>
                            </p>
                            <p>
                                <strong>Student:</strong> 
                                <a href="#" data-demo-email="maria.santos@student.cit-lms.edu.ph" data-demo-password="password123">
                                    <code>maria.santos@student.cit-lms.edu.ph</code>
                                </a>
                            </p>
                            <p class="text-muted" style="margin-top: 8px; font-size: 0.75rem;">
                                Password for all: <code>password123</code>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Login Form -->
                    <form id="login-form" class="auth-form" method="POST" autocomplete="on">
                        
                        <!-- Email -->
                        <div class="form-group">
                            <label for="email" class="form-label required">Email Address</label>
                            <div class="form-input-icon">
                                <span class="input-icon">📧</span>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    class="form-input" 
                                    placeholder="Enter your email"
                                    autocomplete="email"
                                    required
                                >
                            </div>
                        </div>
                        
                        <!-- Password -->
                        <div class="form-group">
                            <label for="password" class="form-label required">Password</label>
                            <div class="form-input-icon">
                                <span class="input-icon">🔒</span>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    class="form-input" 
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    required
                                    minlength="6"
                                    style="padding-right: 48px;"
                                >
                                <button type="button" id="password-toggle" class="password-toggle" title="Show/Hide Password">
                                    👁️
                                </button>
                            </div>
                        </div>
                        
                        <!-- Remember Me & Forgot Password -->
                        <div class="auth-options">
                            <label class="form-check">
                                <input type="checkbox" id="remember" name="remember" class="form-check-input">
                                <span class="form-check-label">Remember me</span>
                            </label>
                            <!--
                            <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
                            -->
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" id="submit-btn" class="auth-submit">
                            Login
                        </button>
                        
                    </form>
                    
                </div>
                
                <!-- Footer -->
                <div class="auth-footer">
                    <p><?= APP_FULL_NAME ?></p>
                </div>
                
            </div>

        </div>
    </div>
    
    <!-- Scripts -->
    <script>
        // App config for JS
        const APP_CONFIG = {
            baseUrl: '<?= BASE_URL ?>',
            apiUrl: '<?= BASE_URL ?>/api'
        };
    </script>
    <script src="<?= BASE_URL ?>/js/auth.js"></script>
    
</body>
</html>