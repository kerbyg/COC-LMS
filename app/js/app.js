/**
 * App.js - Main entry point & Router
 * Handles authentication check, routing, and page loading
 */

import { Auth } from './auth.js';
import { BASE_URL } from './api.js';
import { renderSidebar } from './components/sidebar.js';
import { renderTopbar } from './components/topbar.js';

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

    // Load the page
    const pageKey = `${route.role}/${route.page}`;
    const content = document.getElementById('page-content');

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
