/**
 * Sidebar Component
 * Renders the navigation sidebar based on user role
 */

import { Auth } from '../auth.js';
import { Api }  from '../api.js';

// Menu configurations per role
const menus = {
    admin: [
        { section: 'Main', items: [
            { icon: '📊', text: 'Dashboard', page: 'dashboard' }
        ]},
        { section: 'Academic', items: [
            { icon: '🏢', text: 'Departments', page: 'departments' },
            { icon: '📚', text: 'Subjects', page: 'subjects' },
            { icon: '📋', text: 'Curriculum', page: 'curriculum' }
        ]},
        { section: 'Scheduling', items: [
            { icon: '👨‍🏫', text: 'Faculty Assignments', page: 'faculty-assignments' }
        ]},
        { section: 'System', items: [
            { icon: '👥', text: 'Users', page: 'users' },
            { icon: '🔐', text: 'Roles & Permissions', page: 'rbac' },
            { icon: '⚙️', text: 'Settings', page: 'settings' }
        ]}
    ],
    dean: [
        { section: 'Main', items: [
            { icon: '📊', text: 'Dashboard', page: 'dashboard' }
        ]},
        { section: 'Academic', items: [
            { icon: '👨‍🏫', text: 'Instructors', page: 'instructors' },
            { icon: '📚', text: 'Subjects', page: 'subjects' },
            { icon: '👥', text: 'Faculty Assignments', page: 'faculty-assignments' },
            { icon: '📈', text: 'Reports', page: 'reports' }
        ]}
    ],
    instructor: [
        { section: 'Main', items: [
            { icon: '📊', text: 'Dashboard', page: 'dashboard' }
        ]},
        { section: 'Teaching', items: [
            { icon: '🏫', text: 'Sections', page: 'sections' },
            { icon: '📚', text: 'My Classes', page: 'my-classes' },
            { icon: '🏦', text: 'Content Bank', page: 'content-bank' }
        ]},
        { section: 'Assessment', items: [
            { icon: '📋', text: 'Gradebook', page: 'gradebook' }
        ]},
        { section: 'Communication', items: [
            { icon: '📢', text: 'Announcements', page: 'announcements' }
        ]}
    ],
    student: [
        { section: 'Main', items: [
            { icon: '📊', text: 'Dashboard', page: 'dashboard' }
        ]},
        { section: 'Learning', items: [
            { icon: '📚', text: 'My Subjects', page: 'my-subjects' }
        ]},
        { section: 'Progress', items: [
            { icon: '📋', text: 'My Grades', page: 'grades' }
        ]}
    ]
};

/**
 * Render sidebar into the given element
 */
export function renderSidebar(container) {
    const user = Auth.user();
    const role = user.role;
    const roleMenu = menus[role] || [];
    const currentHash = window.location.hash.replace('#', '');
    const currentPage = currentHash.split('/')[1] || 'dashboard';

    let html = `
        <!-- Sidebar Header -->
        <div class="sidebar-header">
            <a href="#${role}/dashboard" class="logo">
                <img src="/COC-LMS/assets/images/phinma_logo2.png" alt="COC" class="logo-img">
                <span class="logo-text">COC-LMS</span>
            </a>
        </div>

        <!-- Navigation -->
        <nav class="sidebar-nav">
    `;

    // Render menu sections
    for (const section of roleMenu) {
        html += `
            <div class="nav-section">
                <span class="nav-section-title">${section.section}</span>
        `;
        for (const item of section.items) {
            const isActive = currentPage === item.page ? 'active' : '';
            const badgeHtml = item.badge
                ? `<span class="nav-badge" id="msg-nav-badge" style="display:none">0</span>`
                : '';
            html += `
                <a href="#${role}/${item.page}" class="nav-item ${isActive}" data-page="${item.page}">
                    <span class="nav-text">${item.text}</span>
                    ${badgeHtml}
                </a>
            `;
        }
        html += `</div>`;
    }

    // Common section: Profile only (Logout is in topbar dropdown)
    html += `
            <div class="nav-section">
                <span class="nav-section-title">Account</span>
                <a href="#${role}/profile" class="nav-item ${currentPage === 'profile' ? 'active' : ''}" data-page="profile">
                    <span class="nav-text">My Profile</span>
                </a>
            </div>
        </nav>

        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <div class="user-card">
                <div class="user-avatar">${Auth.initials()}</div>
                <div class="user-info">
                    <span class="user-name">${escapeHtml(user.name)}</span>
                    <span class="user-role">${Auth.roleName(role)}</span>
                </div>
            </div>
        </div>
    `;

    container.innerHTML = html;

    // Start unread badge polling for roles with Messages
    if (role === 'student' || role === 'instructor') {
        pollUnreadBadge();
        // Re-poll every 30 s
        setInterval(pollUnreadBadge, 30000);
    }
}

/**
 * Poll unread message count and update sidebar badge
 */
async function pollUnreadBadge() {
    try {
        const res = await Api.get('/MessagingAPI.php?action=unread_count');
        const count = res.success ? (res.count || 0) : 0;
        const badge = document.getElementById('msg-nav-badge');
        if (badge) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = count > 0 ? 'inline-flex' : 'none';
        }
    } catch (_) { /* silent */ }
}

/**
 * Custom logout confirmation modal
 */
function showLogoutModal() {
    // Remove any existing modal
    document.getElementById('logout-modal-overlay')?.remove();

    const overlay = document.createElement('div');
    overlay.id = 'logout-modal-overlay';
    overlay.style.cssText = `
        position:fixed; inset:0; background:rgba(0,0,0,.5);
        display:flex; align-items:center; justify-content:center;
        z-index:9999; animation:fadeIn .15s ease;
    `;

    overlay.innerHTML = `
        <style>
            @keyframes fadeIn { from { opacity:0 } to { opacity:1 } }
            @keyframes slideUp { from { transform:translateY(16px);opacity:0 } to { transform:translateY(0);opacity:1 } }
            #logout-modal {
                background:#fff; border-radius:16px; padding:32px 28px 24px;
                width:360px; max-width:92vw; text-align:center;
                box-shadow:0 24px 64px rgba(0,0,0,.18);
                animation:slideUp .2s ease;
            }
            #logout-modal .lm-icon {
                width:56px; height:56px; border-radius:16px;
                background:#E8F5E9; display:flex; align-items:center;
                justify-content:center; font-size:26px;
                margin:0 auto 16px;
            }
            #logout-modal h3 {
                font-size:18px; font-weight:800; color:#111827; margin:0 0 8px;
            }
            #logout-modal p {
                font-size:14px; color:#6B7280; margin:0 0 24px; line-height:1.5;
            }
            #logout-modal .lm-actions {
                display:flex; gap:10px;
            }
            #logout-modal .lm-cancel {
                flex:1; padding:11px; border-radius:10px;
                border:1.5px solid #E5E7EB; background:#fff;
                font-size:14px; font-weight:600; color:#374151;
                cursor:pointer; transition:all .15s;
            }
            #logout-modal .lm-cancel:hover { background:#F9FAFB; border-color:#D1D5DB; }
            #logout-modal .lm-confirm {
                flex:1; padding:11px; border-radius:10px;
                border:none; background:#1B4D3E;
                font-size:14px; font-weight:600; color:#fff;
                cursor:pointer; transition:background .15s;
            }
            #logout-modal .lm-confirm:hover { background:#2D6A4F; }
        </style>
        <div id="logout-modal">
            <div class="lm-icon">🚪</div>
            <h3>Logging out?</h3>
            <p>You'll need to sign in again to access your account.</p>
            <div class="lm-actions">
                <button class="lm-cancel" id="lm-cancel">Stay</button>
                <button class="lm-confirm" id="lm-confirm">Yes, Logout</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    // Close on cancel or backdrop click
    document.getElementById('lm-cancel').addEventListener('click', () => overlay.remove());
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });

    // Confirm logout
    document.getElementById('lm-confirm').addEventListener('click', () => {
        overlay.remove();
        Auth.logout();
    });

    // Close on Escape
    const onKey = e => { if (e.key === 'Escape') { overlay.remove(); document.removeEventListener('keydown', onKey); } };
    document.addEventListener('keydown', onKey);
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}
