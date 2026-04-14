/**
 * Sidebar Component
 * Renders navigation based on user role + live RBAC permissions.
 * Each nav item declares a `permission` slug — items are hidden
 * if the current user does not have that permission.
 */

import { Auth } from '../auth.js';
import { Api }  from '../api.js';

// ── Menu definitions ─────────────────────────────────────────
// permission: null  → always visible (dashboard, profile)
// permission: 'x.view' → hidden if user lacks that slug
const menus = {

    // ── Admin ─────────────────────────────────────────────────
    // Original items + RBAC extras (Users, Programs, Sections, Offerings)
    admin: [
        { section: 'Main', items: [
            { icon: '📊', text: 'Dashboard',           page: 'dashboard',           permission: null },
        ]},
        { section: 'Academic', items: [
            { icon: '🏢', text: 'Departments',         page: 'departments',         permission: 'departments.view' },
            { icon: '🎓', text: 'Programs',            page: 'programs',            permission: 'programs.view' },
            { icon: '📚', text: 'Subjects',            page: 'subjects',            permission: 'subjects.view' },
            { icon: '📋', text: 'Curriculum',          page: 'curriculum',          permission: 'curriculum.view' },
        ]},
        { section: 'Scheduling', items: [
            { icon: '🏫', text: 'Sections',            page: 'sections',            permission: 'sections.view' },
            { icon: '📅', text: 'Subject Offerings',   page: 'subject-offerings',   permission: 'subject_offerings.view' },
            { icon: '👨‍🏫', text: 'Faculty Assignments', page: 'faculty-assignments', permission: 'faculty_assignments.view' },
        ]},
        { section: 'Reports', items: [
            { icon: '📈', text: 'Reports',             page: 'reports',             permission: 'reports.view' },
            { icon: '📉', text: 'Analytics',           page: 'analytics',           permission: 'analytics.view' },
        ]},
        { section: 'System', items: [
            { icon: '👥', text: 'Users',               page: 'users',               permission: 'users.view' },
            { icon: '🔐', text: 'Roles & Permissions', page: 'rbac',                permission: 'rbac.view' },
            { icon: '⚙️', text: 'Settings',            page: 'settings',            permission: 'settings.view' },
        ]},
    ],

    // ── Dean ──────────────────────────────────────────────────
    // Original: Instructors, Subjects, Faculty Assignments, Reports
    // RBAC extras: Departments, Programs, Curriculum, Sections, Offerings, Users, RBAC, Settings
    dean: [
        { section: 'Main', items: [
            { icon: '📊', text: 'Dashboard',           page: 'dashboard',           permission: null },
        ]},
        { section: 'Academic', items: [
            { icon: '👨‍🏫', text: 'Instructors',         page: 'instructors',         permission: 'faculty_assignments.view' },
            { icon: '📚', text: 'Subjects',            page: 'subjects',            permission: 'subjects.view' },
            { icon: '👥', text: 'Faculty Assignments', page: 'faculty-assignments', permission: 'faculty_assignments.view' },
            { icon: '📈', text: 'Reports',             page: 'reports',             permission: 'reports.view' },
            // RBAC-unlockable extras
            { icon: '🏢', text: 'Departments',         page: 'departments',         permission: 'departments.view' },
            { icon: '🎓', text: 'Programs',            page: 'programs',            permission: 'programs.view' },
            { icon: '📋', text: 'Curriculum',          page: 'curriculum',          permission: 'curriculum.view' },
            { icon: '🏫', text: 'Sections',            page: 'sections',            permission: 'sections.view' },
            { icon: '📅', text: 'Subject Offerings',   page: 'subject-offerings',   permission: 'subject_offerings.view' },
            { icon: '📉', text: 'Analytics',           page: 'analytics',           permission: 'analytics.view' },
        ]},
        { section: 'System', items: [
            { icon: '👥', text: 'Users',               page: 'users',               permission: 'users.view' },
            { icon: '🔐', text: 'Roles & Permissions', page: 'rbac',                permission: 'rbac.view' },
            { icon: '⚙️', text: 'Settings',            page: 'settings',            permission: 'settings.view' },
        ]},
    ],

    // ── Instructor ────────────────────────────────────────────
    // Original: Sections, My Classes, Content Bank, Gradebook, Announcements
    // RBAC extras: anything that requires a non-default instructor permission
    instructor: [
        { section: 'Main', items: [
            { icon: '📊', text: 'Dashboard',   page: 'dashboard',    permission: null },
        ]},
        { section: 'Teaching', items: [
            { icon: '🏫', text: 'Sections',    page: 'sections',     permission: 'sections.view' },
            { icon: '📚', text: 'My Classes',  page: 'my-classes',   permission: 'subjects.view' },
            { icon: '🏦', text: 'Content Bank',page: 'content-bank', permission: 'lessons.view' },
        ]},
        { section: 'Assessment', items: [
            { icon: '📋', text: 'Gradebook',   page: 'gradebook',    permission: 'grades.view' },
        ]},
        { section: 'Communication', items: [
            { icon: '📢', text: 'Announcements', page: 'announcements', permission: null },
            { icon: '💬', text: 'Messages',      page: 'messages',      permission: null, badge: true },
        ]},
        // RBAC-unlockable extras (not granted to instructor by default)
        { section: 'Administration', items: [
            { icon: '🏢', text: 'Departments',         page: 'departments',       permission: 'departments.view' },
            { icon: '🎓', text: 'Programs',            page: 'programs',          permission: 'programs.view' },
            { icon: '📋', text: 'Curriculum',          page: 'curriculum',        permission: 'curriculum.view' },
            { icon: '📅', text: 'Subject Offerings',   page: 'subject-offerings', permission: 'subject_offerings.view' },
            { icon: '👥', text: 'Faculty Assignments', page: 'faculty-assignments', permission: 'faculty_assignments.view' },
            { icon: '📈', text: 'Reports',             page: 'reports',           permission: 'reports.view' },
            { icon: '📉', text: 'Analytics',           page: 'analytics',         permission: 'analytics.view' },
            { icon: '👤', text: 'Users',               page: 'users',             permission: 'users.view' },
            { icon: '🔐', text: 'Roles & Permissions', page: 'rbac',              permission: 'rbac.view' },
            { icon: '⚙️', text: 'Settings',            page: 'settings',          permission: 'settings.view' },
        ]},
    ],

    // ── Student ───────────────────────────────────────────────
    // Original: My Subjects, My Grades
    // RBAC extras: anything admin explicitly grants
    student: [
        { section: 'Main', items: [
            { icon: '📊', text: 'Dashboard',   page: 'dashboard',   permission: null },
        ]},
        { section: 'Learning', items: [
            { icon: '📚', text: 'My Subjects', page: 'my-subjects', permission: 'subjects.view' },
        ]},
        { section: 'Progress', items: [
            { icon: '📋', text: 'My Grades',   page: 'grades',      permission: 'grades.view' },
        ]},
        { section: 'Communication', items: [
            { icon: '📢', text: 'Announcements', page: 'announcements', permission: null },
            { icon: '💬', text: 'Messages',      page: 'messages',      permission: null, badge: true },
        ]},
    ],
};

/**
 * Render sidebar into the given element
 */
export function renderSidebar(container) {
    const user     = Auth.user();
    const role     = user.role;
    const roleMenu = menus[role] || [];
    const currentPage = (window.location.hash.replace('#', '').split('/')[1] || 'dashboard').split('?')[0];

    let html = `
        <div class="sidebar-header">
            <a href="#${role}/dashboard" class="logo">
                <img src="/COC-LMS/assets/images/phinma_logo2.png" alt="COC" class="logo-img">
                <span class="logo-text">COC-LMS</span>
            </a>
        </div>
        <nav class="sidebar-nav">
    `;

    for (const section of roleMenu) {
        // Filter items by permission
        const visibleItems = section.items.filter(item =>
            item.permission === null || Auth.can(item.permission)
        );
        if (visibleItems.length === 0) continue;

        html += `<div class="nav-section"><span class="nav-section-title">${section.section}</span>`;

        for (const item of visibleItems) {
            const isActive  = currentPage === item.page ? 'active' : '';
            const badgeHtml = item.badge
                ? `<span class="nav-badge" id="msg-nav-badge" style="display:none">0</span>`
                : '';
            html += `
                <a href="#${role}/${item.page}" class="nav-item ${isActive}" data-page="${item.page}">
                    <span class="nav-icon">${item.icon}</span>
                    <span class="nav-text">${item.text}</span>
                    ${badgeHtml}
                </a>
            `;
        }

        html += `</div>`;
    }

    // Account section — always visible
    html += `
            <div class="nav-section">
                <span class="nav-section-title">Account</span>
                <a href="#${role}/profile" class="nav-item ${currentPage === 'profile' ? 'active' : ''}" data-page="profile">
                    <span class="nav-icon">👤</span>
                    <span class="nav-text">My Profile</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <div class="user-card">
                <div class="user-avatar">${Auth.initials()}</div>
                <div class="user-info">
                    <span class="user-name">${escapeHtml(user.name || (user.first_name + ' ' + user.last_name))}</span>
                    <span class="user-role">${Auth.roleName(role)}</span>
                </div>
            </div>
        </div>
    `;

    container.innerHTML = html;

    // Poll unread message badge
    if (role === 'student' || role === 'instructor') {
        pollUnreadBadge();
        setInterval(pollUnreadBadge, 30000);
    }

    // Re-render sidebar active state on navigation
    window.addEventListener('hashchange', () => {
        const page = (window.location.hash.replace('#', '').split('/')[1] || 'dashboard').split('?')[0];
        container.querySelectorAll('.nav-item').forEach(el => {
            el.classList.toggle('active', el.dataset.page === page);
        });
    });
}

async function pollUnreadBadge() {
    try {
        const res   = await Api.get('/MessagingAPI.php?action=unread_count');
        const count = res.success ? (res.count || 0) : 0;
        const badge = document.getElementById('msg-nav-badge');
        if (badge) {
            badge.textContent    = count > 99 ? '99+' : count;
            badge.style.display  = count > 0 ? 'inline-flex' : 'none';
        }
    } catch (_) { /* silent */ }
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}
