/**
 * ============================================================
 * CIT-LMS Global JavaScript Utilities
 * ============================================================
 * This file is loaded on EVERY page and contains:
 * - API Helper (fetch wrapper for GET, POST, PUT, DELETE)
 * - Toast Notifications
 * - Modal Helper
 * - Form Validation
 * - Loading Spinner
 * - DOM Helpers
 * - Date/Number Formatting
 * - Sidebar Toggle
 * - Dropdown Handler
 * 
 * Usage:
 *   <script src="/cit-lms/js/app.js"></script>
 * ============================================================
 */

// ============================================================
// CONFIGURATION
// ============================================================
const APP_CONFIG = {
    baseUrl: '/COC-LMS',
    apiUrl: '/COC-LMS/api',
    debug: true
};


// ============================================================
// API HELPER
// ============================================================
const API = {
    /**
     * GET request
     * @param {string} endpoint - API endpoint (e.g., '/UsersAPI.php?action=getAll')
     * @returns {Promise<object>} - JSON response
     */
    async get(endpoint) {
        try {
            const response = await fetch(APP_CONFIG.apiUrl + endpoint, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });
            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    },

    /**
     * POST request
     * @param {string} endpoint - API endpoint
     * @param {object} data - Data to send
     * @returns {Promise<object>} - JSON response
     */
    async post(endpoint, data = {}) {
        try {
            const response = await fetch(APP_CONFIG.apiUrl + endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });
            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    },

    /**
     * PUT request (update)
     * @param {string} endpoint - API endpoint
     * @param {object} data - Data to send
     * @returns {Promise<object>} - JSON response
     */
    async put(endpoint, data = {}) {
        try {
            const response = await fetch(APP_CONFIG.apiUrl + endpoint, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });
            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    },

    /**
     * DELETE request
     * @param {string} endpoint - API endpoint
     * @returns {Promise<object>} - JSON response
     */
    async delete(endpoint) {
        try {
            const response = await fetch(APP_CONFIG.apiUrl + endpoint, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json'
                }
            });
            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    },

    /**
     * Upload file with FormData
     * @param {string} endpoint - API endpoint
     * @param {FormData} formData - Form data with file
     * @returns {Promise<object>} - JSON response
     */
    async upload(endpoint, formData) {
        try {
            const response = await fetch(APP_CONFIG.apiUrl + endpoint, {
                method: 'POST',
                body: formData
                // Don't set Content-Type header - browser will set it with boundary
            });
            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    },

    /**
     * Handle API response
     */
    async handleResponse(response) {
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Request failed');
        }
        
        return data;
    },

    /**
     * Handle API error
     */
    handleError(error) {
        if (APP_CONFIG.debug) {
            console.error('API Error:', error);
        }
        
        return {
            success: false,
            message: error.message || 'An error occurred. Please try again.',
            data: null
        };
    }
};


// ============================================================
// TOAST NOTIFICATIONS
// ============================================================
const Toast = {
    container: null,

    /**
     * Initialize toast container
     */
    init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    },

    /**
     * Show a toast notification
     * @param {string} message - Message to display
     * @param {string} type - 'success', 'error', 'warning', 'info'
     * @param {number} duration - Duration in milliseconds
     */
    show(message, type = 'info', duration = 3000) {
        this.init();

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        // Icon based on type
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };

        toast.innerHTML = `
            <span class="toast-icon">${icons[type] || icons.info}</span>
            <span class="toast-message">${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        `;

        this.container.appendChild(toast);

        // Auto remove after duration
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    /**
     * Success toast
     */
    success(message, duration = 3000) {
        this.show(message, 'success', duration);
    },

    /**
     * Error toast
     */
    error(message, duration = 4000) {
        this.show(message, 'error', duration);
    },

    /**
     * Warning toast
     */
    warning(message, duration = 3500) {
        this.show(message, 'warning', duration);
    },

    /**
     * Info toast
     */
    info(message, duration = 3000) {
        this.show(message, 'info', duration);
    }
};


// ============================================================
// MODAL HELPER
// ============================================================
const Modal = {
    /**
     * Open a modal
     * @param {string} modalId - Modal element ID
     */
    open(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    },

    /**
     * Close a modal
     * @param {string} modalId - Modal element ID
     */
    close(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    },

    /**
     * Close all open modals
     */
    closeAll() {
        document.querySelectorAll('.modal-overlay.active').forEach(modal => {
            modal.classList.remove('active');
        });
        document.body.style.overflow = '';
    },

    /**
     * Show confirmation dialog
     * @param {string} message - Confirmation message
     * @param {string} title - Dialog title
     * @returns {Promise<boolean>} - User's choice
     */
    async confirm(message, title = 'Confirm') {
        return new Promise((resolve) => {
            // Create modal HTML
            const modalId = 'confirm-modal-' + Date.now();
            const modalHtml = `
                <div class="modal-overlay" id="${modalId}">
                    <div class="modal" style="max-width: 400px;">
                        <div class="modal-header">
                            <h3>${title}</h3>
                            <button class="modal-close" onclick="Modal.close('${modalId}')">&times;</button>
                        </div>
                        <div class="modal-body">
                            <p>${message}</p>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline" id="${modalId}-cancel">Cancel</button>
                            <button class="btn btn-danger" id="${modalId}-confirm">Confirm</button>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHtml);

            const modal = document.getElementById(modalId);
            const cancelBtn = document.getElementById(`${modalId}-cancel`);
            const confirmBtn = document.getElementById(`${modalId}-confirm`);

            // Show modal
            setTimeout(() => modal.classList.add('active'), 10);

            // Handle buttons
            cancelBtn.onclick = () => {
                modal.remove();
                resolve(false);
            };

            confirmBtn.onclick = () => {
                modal.remove();
                resolve(true);
            };

            // Handle click outside
            modal.onclick = (e) => {
                if (e.target === modal) {
                    modal.remove();
                    resolve(false);
                }
            };
        });
    },

    /**
     * Show alert dialog
     * @param {string} message - Alert message
     * @param {string} title - Dialog title
     */
    alert(message, title = 'Alert') {
        return this.confirm(message, title);
    }
};


// ============================================================
// FORM VALIDATION
// ============================================================
const Validator = {
    /**
     * Check if value is not empty
     */
    required(value) {
        return value !== null && value !== undefined && value.toString().trim() !== '';
    },

    /**
     * Check if value is valid email
     */
    email(value) {
        const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return pattern.test(value);
    },

    /**
     * Check minimum length
     */
    minLength(value, min) {
        return value.length >= min;
    },

    /**
     * Check maximum length
     */
    maxLength(value, max) {
        return value.length <= max;
    },

    /**
     * Check if value is numeric
     */
    numeric(value) {
        return !isNaN(parseFloat(value)) && isFinite(value);
    },

    /**
     * Check if value matches pattern
     */
    pattern(value, regex) {
        return regex.test(value);
    },

    /**
     * Check if two values match
     */
    matches(value, otherValue) {
        return value === otherValue;
    },

    /**
     * Validate a form
     * @param {HTMLFormElement|string} form - Form element or selector
     * @param {object} rules - Validation rules
     * @returns {object} - { valid: boolean, errors: object }
     */
    validateForm(form, rules) {
        if (typeof form === 'string') {
            form = document.querySelector(form);
        }

        const errors = {};
        const formData = new FormData(form);

        for (const [field, fieldRules] of Object.entries(rules)) {
            const value = formData.get(field) || '';

            for (const rule of fieldRules) {
                let ruleName = rule;
                let ruleParam = null;

                // Handle rules with parameters like 'minLength:6'
                if (typeof rule === 'string' && rule.includes(':')) {
                    [ruleName, ruleParam] = rule.split(':');
                }

                let isValid = true;
                let message = '';

                switch (ruleName) {
                    case 'required':
                        isValid = this.required(value);
                        message = `${field} is required`;
                        break;
                    case 'email':
                        isValid = !value || this.email(value);
                        message = 'Invalid email format';
                        break;
                    case 'minLength':
                        isValid = !value || this.minLength(value, parseInt(ruleParam));
                        message = `Must be at least ${ruleParam} characters`;
                        break;
                    case 'maxLength':
                        isValid = !value || this.maxLength(value, parseInt(ruleParam));
                        message = `Must be no more than ${ruleParam} characters`;
                        break;
                    case 'numeric':
                        isValid = !value || this.numeric(value);
                        message = 'Must be a number';
                        break;
                }

                if (!isValid) {
                    errors[field] = message;
                    break; // Stop at first error for this field
                }
            }
        }

        return {
            valid: Object.keys(errors).length === 0,
            errors
        };
    },

    /**
     * Show validation errors on form
     * @param {HTMLFormElement|string} form - Form element or selector
     * @param {object} errors - Error messages
     */
    showErrors(form, errors) {
        if (typeof form === 'string') {
            form = document.querySelector(form);
        }

        // Clear existing errors
        form.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('.form-error').forEach(el => el.remove());

        // Show new errors
        for (const [field, message] of Object.entries(errors)) {
            const input = form.querySelector(`[name="${field}"]`);
            if (input) {
                input.classList.add('is-invalid');
                
                const errorDiv = document.createElement('div');
                errorDiv.className = 'form-error';
                errorDiv.textContent = message;
                input.parentNode.appendChild(errorDiv);
            }
        }
    },

    /**
     * Clear all validation errors
     */
    clearErrors(form) {
        if (typeof form === 'string') {
            form = document.querySelector(form);
        }

        form.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('.form-error').forEach(el => el.remove());
    }
};


// ============================================================
// LOADING SPINNER
// ============================================================
const Loading = {
    overlay: null,

    /**
     * Show loading overlay
     * @param {string} message - Optional loading message
     */
    show(message = 'Loading...') {
        if (!this.overlay) {
            this.overlay = document.createElement('div');
            this.overlay.className = 'loading-overlay';
            this.overlay.innerHTML = `
                <div style="text-align: center;">
                    <div class="spinner spinner-lg"></div>
                    <p class="loading-message mt-3" style="color: var(--gray-600);">${message}</p>
                </div>
            `;
            document.body.appendChild(this.overlay);
        }

        setTimeout(() => this.overlay.classList.add('active'), 10);
    },

    /**
     * Hide loading overlay
     */
    hide() {
        if (this.overlay) {
            this.overlay.classList.remove('active');
        }
    },

    /**
     * Show loading on specific element
     * @param {HTMLElement|string} element - Element or selector
     */
    showOn(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.classList.add('loading');
            element.dataset.originalContent = element.innerHTML;
            element.innerHTML = '<div class="spinner"></div>';
        }
    },

    /**
     * Hide loading on specific element
     * @param {HTMLElement|string} element - Element or selector
     */
    hideOn(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.classList.remove('loading');
            if (element.dataset.originalContent) {
                element.innerHTML = element.dataset.originalContent;
                delete element.dataset.originalContent;
            }
        }
    }
};


// ============================================================
// DOM HELPERS
// ============================================================

/**
 * Query selector shorthand
 * @param {string} selector - CSS selector
 * @returns {Element|null}
 */
function $(selector) {
    return document.querySelector(selector);
}

/**
 * Query selector all shorthand
 * @param {string} selector - CSS selector
 * @returns {NodeList}
 */
function $$(selector) {
    return document.querySelectorAll(selector);
}

/**
 * Show element
 * @param {HTMLElement|string} element - Element or selector
 */
function show(element) {
    if (typeof element === 'string') element = $(element);
    if (element) element.style.display = '';
}

/**
 * Hide element
 * @param {HTMLElement|string} element - Element or selector
 */
function hide(element) {
    if (typeof element === 'string') element = $(element);
    if (element) element.style.display = 'none';
}

/**
 * Toggle element visibility
 * @param {HTMLElement|string} element - Element or selector
 */
function toggle(element) {
    if (typeof element === 'string') element = $(element);
    if (element) {
        element.style.display = element.style.display === 'none' ? '' : 'none';
    }
}

/**
 * Add event listener shorthand
 * @param {HTMLElement|string} element - Element or selector
 * @param {string} event - Event type
 * @param {Function} handler - Event handler
 */
function on(element, event, handler) {
    if (typeof element === 'string') element = $(element);
    if (element) element.addEventListener(event, handler);
}

/**
 * Create element with attributes
 * @param {string} tag - HTML tag
 * @param {object} attrs - Attributes
 * @param {string} content - Inner HTML
 * @returns {HTMLElement}
 */
function createElement(tag, attrs = {}, content = '') {
    const el = document.createElement(tag);
    for (const [key, value] of Object.entries(attrs)) {
        if (key === 'className') {
            el.className = value;
        } else if (key === 'dataset') {
            for (const [dataKey, dataValue] of Object.entries(value)) {
                el.dataset[dataKey] = dataValue;
            }
        } else {
            el.setAttribute(key, value);
        }
    }
    if (content) el.innerHTML = content;
    return el;
}


// ============================================================
// DATE & NUMBER FORMATTING
// ============================================================
const Format = {
    /**
     * Format date
     * @param {string|Date} date - Date to format
     * @param {string} format - Format string (default: 'long')
     * @returns {string}
     */
    date(date, format = 'long') {
        if (!date) return '';
        
        const d = new Date(date);
        
        const options = {
            short: { month: 'short', day: 'numeric', year: 'numeric' },
            long: { month: 'long', day: 'numeric', year: 'numeric' },
            full: { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }
        };

        return d.toLocaleDateString('en-PH', options[format] || options.long);
    },

    /**
     * Format date and time
     * @param {string|Date} date - Date to format
     * @returns {string}
     */
    dateTime(date) {
        if (!date) return '';
        
        const d = new Date(date);
        return d.toLocaleDateString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    },

    /**
     * Format time
     * @param {string|Date} date - Date to format
     * @returns {string}
     */
    time(date) {
        if (!date) return '';
        
        const d = new Date(date);
        return d.toLocaleTimeString('en-PH', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    },

    /**
     * Format relative time (e.g., "2 hours ago")
     * @param {string|Date} date - Date to format
     * @returns {string}
     */
    relative(date) {
        if (!date) return '';
        
        const d = new Date(date);
        const now = new Date();
        const diff = now - d;
        
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);

        if (seconds < 60) return 'Just now';
        if (minutes < 60) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        if (days < 7) return `${days} day${days > 1 ? 's' : ''} ago`;
        
        return this.date(date, 'short');
    },

    /**
     * Format number with commas
     * @param {number} number - Number to format
     * @returns {string}
     */
    number(number) {
        return new Intl.NumberFormat('en-PH').format(number);
    },

    /**
     * Format as percentage
     * @param {number} value - Value to format
     * @param {number} decimals - Decimal places
     * @returns {string}
     */
    percent(value, decimals = 0) {
        return `${parseFloat(value).toFixed(decimals)}%`;
    },

    /**
     * Format file size
     * @param {number} bytes - Size in bytes
     * @returns {string}
     */
    fileSize(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        while (bytes >= 1024 && i < units.length - 1) {
            bytes /= 1024;
            i++;
        }
        return `${bytes.toFixed(i > 0 ? 1 : 0)} ${units[i]}`;
    }
};


// ============================================================
// UTILITY FUNCTIONS
// ============================================================

/**
 * Debounce function
 * @param {Function} func - Function to debounce
 * @param {number} wait - Wait time in ms
 * @returns {Function}
 */
function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle function
 * @param {Function} func - Function to throttle
 * @param {number} limit - Time limit in ms
 * @returns {Function}
 */
function throttle(func, limit = 300) {
    let inThrottle;
    return function executedFunction(...args) {
        if (!inThrottle) {
            func(...args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * Copy text to clipboard
 * @param {string} text - Text to copy
 * @returns {Promise<boolean>}
 */
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        Toast.success('Copied to clipboard');
        return true;
    } catch (err) {
        Toast.error('Failed to copy');
        return false;
    }
}

/**
 * Generate random string
 * @param {number} length - String length
 * @returns {string}
 */
function randomString(length = 8) {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let result = '';
    for (let i = 0; i < length; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
}

/**
 * Escape HTML to prevent XSS
 * @param {string} str - String to escape
 * @returns {string}
 */
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}


// ============================================================
// SIDEBAR & DROPDOWN HANDLERS
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            // On mobile, toggle 'open' class
            if (window.innerWidth <= 1024) {
                sidebar.classList.toggle('open');
            } else {
                // On desktop, toggle 'collapsed' class
                sidebar.classList.toggle('collapsed');
            }
        });
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 1024 && sidebar && sidebar.classList.contains('open')) {
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        }
    });

    // Dropdown toggle
    document.querySelectorAll('.dropdown').forEach(dropdown => {
        const toggle = dropdown.querySelector('[data-dropdown-toggle]');
        
        if (toggle) {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                
                // Close other dropdowns
                document.querySelectorAll('.dropdown.active').forEach(d => {
                    if (d !== dropdown) d.classList.remove('active');
                });
                
                dropdown.classList.toggle('active');
            });
        }
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', () => {
        document.querySelectorAll('.dropdown.active').forEach(d => {
            d.classList.remove('active');
        });
    });

    // Modal close on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });

    // ESC key to close modals
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            Modal.closeAll();
        }
    });
});


// ============================================================
// LOGOUT FUNCTION
// ============================================================
async function logout() {
    const confirmed = await Modal.confirm('Are you sure you want to logout?', 'Logout');

    if (confirmed) {
        // Redirect to logout page which handles session cleanup
        window.location.href = APP_CONFIG.baseUrl + '/pages/auth/logout.php';
    }
}


// ============================================================
// ADD CSS FOR TOAST SLIDE OUT ANIMATION
// ============================================================
const toastStyles = document.createElement('style');
toastStyles.textContent = `
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(toastStyles);


// ============================================================
// EXPORT FOR MODULE USE (if needed)
// ============================================================
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        API,
        Toast,
        Modal,
        Validator,
        Loading,
        Format,
        $,
        $$,
        debounce,
        throttle
    };
}