/**
 * App.js - Main entry point & Router
 * Handles authentication check, routing, and page loading
 */

import { Auth } from './auth.js';
import { BASE_URL } from './api.js';
import { renderSidebar } from './components/sidebar.js';
import { renderTopbar } from './components/topbar.js';

// ── Permission map: page → required permission slug ───────────
// null = no permission required (always accessible)
const PAGE_PERMISSIONS = {
    // Admin
    'admin/users':               'users.view',
    'admin/rbac':                'rbac.view',
    'admin/settings':            'settings.view',
    'admin/departments':         'departments.view',
    'admin/programs':            'programs.view',
    'admin/subjects':            'subjects.view',
    'admin/curriculum':          'curriculum.view',
    'admin/sections':            'sections.view',
    'admin/subject-offerings':   'subject_offerings.view',
    'admin/faculty-assignments': 'faculty_assignments.view',
    // Dean
    'dean/instructors':          'faculty_assignments.view',
    'dean/subjects':             'subjects.view',
    'dean/sections':             'sections.view',
    'dean/subject-offerings':    'subject_offerings.view',
    'dean/faculty-assignments':  'faculty_assignments.view',
    'dean/reports':              'reports.view',
    // Instructor
    'instructor/sections':       'sections.view',
    'instructor/my-classes':     'subjects.view',
    'instructor/lesson-bank':    'lessons.view',
    'instructor/content-bank':   'lessons.view',
    'instructor/quizzes':        'quizzes.view',
    'instructor/gradebook':      'grades.view',
    'instructor/analytics':      'analytics.view',
    // Student
    'student/my-subjects':       'subjects.view',
    'student/lessons':           'lessons.view',
    'student/quizzes':           'quizzes.view',
    'student/grades':            'grades.view',
};

// Page registry - maps route names to page modules
const pages = {};

/**
 * Register a page module
 */
export function registerPage(role, name, loadFn) {
    pages[`${role}/${name}`] = loadFn;
}

/**
 * Navigate to a page
 */
export function navigate(role, page) {
    const hash = `#${role}/${page}`;
    if (window.location.hash !== hash) {
        window.location.hash = hash;
    } else {
        loadCurrentPage();
    }
}

/**
 * Get current route from hash
 */
function getCurrentRoute() {
    const hash = window.location.hash.replace('#', '');
    if (!hash) return null;
    const [pathPart, queryPart] = hash.split('?');
    const [role, ...rest] = pathPart.split('/');
    const params = {};
    if (queryPart) {
        queryPart.split('&').forEach(p => {
            const [k, v] = p.split('=');
            if (k) params[decodeURIComponent(k)] = decodeURIComponent(v || '');
        });
    }
    return { role, page: rest.join('/') || 'dashboard', params };
}

/**
 * Load the current page based on hash
 */
async function loadCurrentPage() {
    const route = getCurrentRoute();
    const user = Auth.user();

    if (!route || !user) {
        // Default: redirect to user's dashboard
        if (user) {
            window.location.hash = `#${user.role}/dashboard`;
        }
        return;
    }

    // Update page title in topbar
    const pageTitle = route.page.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    document.title = `${pageTitle} | CIT-LMS`;

    // Update sidebar active state
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        if (item.dataset.page === route.page) {
            item.classList.add('active');
        }
    });

    // Update topbar title
    const topbarTitle = document.querySelector('.page-title');
    if (topbarTitle) topbarTitle.textContent = pageTitle;

    // Check permission before loading
    const pageKey  = `${route.role}/${route.page}`;
    const required = PAGE_PERMISSIONS[pageKey];
    const content  = document.getElementById('page-content');

    if (required && !Auth.can(required)) {
        content.innerHTML = `
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:80px 24px;text-align:center;">
                <div style="font-size:56px;margin-bottom:16px;">🚫</div>
                <h2 style="font-size:22px;font-weight:700;color:#1B4D3E;margin:0 0 8px;">Access Denied</h2>
                <p style="color:#6b7280;font-size:14px;max-width:360px;margin:0 0 24px;">
                    You don't have permission to view this page.<br>
                    Contact your administrator to request access.
                </p>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 18px;font-size:13px;color:#166534;">
                    Required permission: <strong>${required}</strong>
                </div>
            </div>`;
        return;
    }

    if (pages[pageKey]) {
        content.innerHTML = '<div style="display:flex;justify-content:center;padding:60px"><div class="spinner-lg" style="width:36px;height:36px;border:3px solid var(--gray-200);border-top-color:var(--primary);border-radius:50%;animation:spin 0.8s linear infinite"></div></div>';
        try {
            await pages[pageKey](content, route.params);
        } catch (err) {
            console.error('Page load error:', err);
            content.innerHTML = `<div class="empty-state"><div class="empty-state-icon">!</div><h3>Error Loading Page</h3><p>${err.message}</p></div>`;
        }
    } else {
        // Try to dynamically import the page module
        try {
            content.innerHTML = '<div style="display:flex;justify-content:center;padding:60px"><div class="spinner-lg" style="width:36px;height:36px;border:3px solid var(--gray-200);border-top-color:var(--primary);border-radius:50%;animation:spin 0.8s linear infinite"></div></div>';
            const module = await import(`./pages/${route.role}/${route.page}.js?v=${Date.now()}`);
            if (module.render) {
                pages[pageKey] = module.render;
                await module.render(content, route.params);
            }
        } catch (err) {
            console.error('Module not found:', pageKey, err);
            content.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">🚧</div>
                    <h3>Page Not Available Yet</h3>
                    <p>The "${route.page}" page for ${route.role} hasn't been built yet.</p>
                    <p style="margin-top:12px;font-size:13px;color:var(--gray-400)">Route: ${pageKey}</p>
                </div>`;
        }
    }
}

/**
 * Boot the application
 */
async function boot() {
    // Check authentication
    const user = await Auth.requireLogin();
    if (!user) return; // Redirected to login

    // Get detailed user data
    await Auth.getUser();

    // Load RBAC permissions for the current user
    await Auth.fetchPermissions();

    // Hide loading, show app
    document.getElementById('app-loading').style.display = 'none';
    document.getElementById('app').style.display = 'flex';

    // Render sidebar and topbar
    renderSidebar(document.getElementById('sidebar'));
    renderTopbar(document.getElementById('topbar'));

    // Set default route if none
    if (!window.location.hash) {
        window.location.hash = `#${user.role}/dashboard`;
    }

    // Load current page
    await loadCurrentPage();

    // Listen for hash changes (navigation)
    window.addEventListener('hashchange', loadCurrentPage);
}

// Start the app
boot();
