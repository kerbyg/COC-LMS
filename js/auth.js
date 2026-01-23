/**
 * ============================================================
 * CIT-LMS Authentication JavaScript
 * ============================================================
 * Handles: Login form submission, validation, errors
 * ============================================================
 */

document.addEventListener('DOMContentLoaded', function() {
    // Get form elements
    const loginForm = document.getElementById('login-form');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const submitBtn = document.getElementById('submit-btn');
    const errorContainer = document.getElementById('error-container');
    const passwordToggle = document.getElementById('password-toggle');
    
    // Password visibility toggle
    if (passwordToggle && passwordInput) {
        passwordToggle.addEventListener('click', function() {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            
            // Update icon
            this.textContent = type === 'password' ? '👁️' : '🙈';
        });
    }
    
    // Form submission
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Clear previous errors
            hideError();
            clearValidation();
            
            // Get values
            const email = emailInput.value.trim();
            const password = passwordInput.value;
            
            // Client-side validation
            let hasError = false;
            
            if (!email) {
                showFieldError(emailInput, 'Email is required');
                hasError = true;
            } else if (!isValidEmail(email)) {
                showFieldError(emailInput, 'Please enter a valid email');
                hasError = true;
            }
            
            if (!password) {
                showFieldError(passwordInput, 'Password is required');
                hasError = true;
            } else if (password.length < 6) {
                showFieldError(passwordInput, 'Password must be at least 6 characters');
                hasError = true;
            }
            
            if (hasError) return;
            
            // Show loading state
            setLoading(true);
            
            try {
                // Make API request
                const response = await fetch(APP_CONFIG.apiUrl + '/AuthAPI.php?action=login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email,
                        password: password
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success message
                    showSuccess('Login successful! Redirecting...');
                    
                    // Redirect after short delay
                    setTimeout(() => {
                        window.location.href = result.data.redirect;
                    }, 500);
                } else {
                    // Show error message
                    showError(result.message || 'Login failed. Please try again.');
                    setLoading(false);
                }
                
            } catch (error) {
                console.error('Login error:', error);
                showError('An error occurred. Please check your connection and try again.');
                setLoading(false);
            }
        });
    }
    
    // Email validation helper
    function isValidEmail(email) {
        const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return pattern.test(email);
    }
    
    // Show error message
    function showError(message) {
        if (errorContainer) {
            errorContainer.innerHTML = `
                <span class="auth-error-icon">⚠️</span>
                <span>${escapeHtml(message)}</span>
            `;
            errorContainer.style.display = 'flex';
            errorContainer.className = 'auth-error';
            
            // Scroll to error
            errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    
    // Show success message
    function showSuccess(message) {
        if (errorContainer) {
            errorContainer.innerHTML = `
                <span class="auth-error-icon">✅</span>
                <span>${escapeHtml(message)}</span>
            `;
            errorContainer.style.display = 'flex';
            errorContainer.className = 'auth-error';
            errorContainer.style.backgroundColor = 'var(--success-light)';
            errorContainer.style.borderColor = 'var(--success)';
            errorContainer.style.color = 'var(--success)';
        }
    }
    
    // Hide error message
    function hideError() {
        if (errorContainer) {
            errorContainer.style.display = 'none';
            errorContainer.innerHTML = '';
        }
    }
    
    // Show field-specific error
    function showFieldError(input, message) {
        input.classList.add('is-invalid');
        
        // Create error message element
        const existingError = input.parentNode.querySelector('.form-error');
        if (existingError) {
            existingError.remove();
        }
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'form-error';
        errorDiv.textContent = message;
        input.parentNode.appendChild(errorDiv);
    }
    
    // Clear all validation errors
    function clearValidation() {
        document.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
        document.querySelectorAll('.form-error').forEach(el => {
            el.remove();
        });
    }
    
    // Set loading state
    function setLoading(isLoading) {
        if (submitBtn) {
            if (isLoading) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<div class="spinner"></div> Logging in...';
            } else {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Login';
            }
        }
        
        // Disable inputs
        if (emailInput) emailInput.disabled = isLoading;
        if (passwordInput) passwordInput.disabled = isLoading;
    }
    
    // Escape HTML helper
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    // Auto-fill demo credentials on click
    const demoLinks = document.querySelectorAll('[data-demo-email]');
    demoLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const email = this.dataset.demoEmail;
            const password = this.dataset.demoPassword || 'password123';
            
            if (emailInput) emailInput.value = email;
            if (passwordInput) passwordInput.value = password;
            
            // Visual feedback
            emailInput.style.backgroundColor = '#d1fae5';
            passwordInput.style.backgroundColor = '#d1fae5';
            
            setTimeout(() => {
                emailInput.style.backgroundColor = '';
                passwordInput.style.backgroundColor = '';
            }, 500);
        });
    });
    
    // Clear error on input
    [emailInput, passwordInput].forEach(input => {
        if (input) {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                const error = this.parentNode.querySelector('.form-error');
                if (error) error.remove();
            });
        }
    });
});