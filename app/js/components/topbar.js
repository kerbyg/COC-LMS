/**
 * Topbar Component
 * Renders the top navigation bar
 */

import { Auth } from '../auth.js';

export function renderTopbar(container) {
    const user = Auth.user();
    const role = user.role;

    container.innerHTML = `
        <!-- Left Side -->
        <div class="topbar-left">
            <button class="topbar-btn mobile-menu-btn" id="sidebar-toggle" title="Toggle Menu">☰</button>
            <h1 class="page-title">Dashboard</h1>
        </div>

        <!-- Right Side -->
        <div class="topbar-right">
            <!-- Search -->
            <button class="topbar-btn" title="Search">🔍</button>

            <!-- Notifications -->
            <div class="dropdown" id="notification-dropdown">
                <button class="topbar-btn" title="Notifications" id="notification-toggle">
                    🔔
                    <span class="badge">3</span>
                </button>
                <div class="dropdown-menu notification-dropdown">
                    <div class="dropdown-header">
                        <strong>Notifications</strong>
                        <a href="#">Mark all read</a>
                    </div>
                    <div class="dropdown-body">
                        <div class="notification-item unread">
                            <span class="notification-icon">📝</span>
                            <div class="notification-content">
                                <span class="notification-title">New quiz available</span>
                                <span class="notification-time">2 minutes ago</span>
                            </div>
                        </div>
                        <div class="notification-item unread">
                            <span class="notification-icon">📢</span>
                            <div class="notification-content">
                                <span class="notification-title">New announcement posted</span>
                                <span class="notification-time">1 hour ago</span>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown-footer">
                        <a href="#">View all notifications</a>
                    </div>
                </div>
            </div>

            <!-- User Dropdown -->
            <div class="dropdown" id="user-dropdown">
                <div class="topbar-user" id="user-toggle">
                    <div class="topbar-user-avatar">${Auth.initials()}</div>
                    <div class="topbar-user-info">
                        <span class="topbar-user-name">${escapeHtml(user.name)}</span>
                        <span class="topbar-user-role">${Auth.roleName(role)}</span>
                    </div>
                    <span class="dropdown-arrow">▼</span>
                </div>
                <div class="dropdown-menu user-dropdown">
                    <a href="#${role}/profile" class="dropdown-item">
                        <span>👤</span><span>My Profile</span>
                    </a>
                    ${role === 'admin' ? `
                    <a href="#admin/settings" class="dropdown-item">
                        <span>⚙️</span><span>Settings</span>
                    </a>` : ''}
                    <div class="dropdown-divider"></div>
                    <a href="javascript:void(0)" class="dropdown-item danger" id="topbar-logout">
                        <span>🚪</span><span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    `;

    // Add topbar styles (dropdown etc.)
    addTopbarStyles();

    // Event listeners
    // Sidebar toggle (mobile)
    document.getElementById('sidebar-toggle').addEventListener('click', () => {
        document.querySelector('.sidebar').classList.toggle('active');
    });

    // Dropdown toggles
    ['notification', 'user'].forEach(id => {
        const toggle = document.getElementById(`${id}-toggle`);
        const dropdown = document.getElementById(`${id}-dropdown`);
        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            // Close other dropdowns
            document.querySelectorAll('.dropdown.active').forEach(d => {
                if (d !== dropdown) d.classList.remove('active');
            });
            dropdown.classList.toggle('active');
        });
    });

    // Close dropdowns on outside click
    document.addEventListener('click', () => {
        document.querySelectorAll('.dropdown.active').forEach(d => d.classList.remove('active'));
    });

    // Logout — custom modal
    document.getElementById('topbar-logout').addEventListener('click', () => {
        showLogoutModal();
    });
}

function addTopbarStyles() {
    if (document.getElementById('topbar-styles')) return;
    const style = document.createElement('style');
    style.id = 'topbar-styles';
    style.textContent = `
        .mobile-menu-btn { display: none; }
        @media (max-width: 1024px) { .mobile-menu-btn { display: flex !important; } }

        .dropdown { position: relative; }
        .dropdown-menu {
            position: absolute; top: 100%; right: 0; min-width: 200px;
            background: var(--white); border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg); border: 1px solid var(--gray-200);
            display: none;
            z-index: 1000; margin-top: 8px;
        }
        .dropdown.active .dropdown-menu {
            display: block;
        }
        .dropdown-header {
            padding: 12px 16px; border-bottom: 1px solid var(--gray-100);
            display: flex; justify-content: space-between; align-items: center;
        }
        .dropdown-header a { font-size: 12px; color: var(--primary); }
        .dropdown-body { max-height: 300px; overflow-y: auto; }
        .dropdown-footer {
            padding: 12px 16px; border-top: 1px solid var(--gray-100); text-align: center;
        }
        .dropdown-footer a { font-size: 13px; color: var(--primary); font-weight: 500; }
        .dropdown-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px; color: var(--gray-700); font-size: 14px;
            transition: var(--transition-fast); cursor: pointer; text-decoration: none;
        }
        .dropdown-item:hover { background: var(--gray-50); color: var(--primary); }
        .dropdown-item.danger:hover { background: var(--danger-bg); color: var(--danger); }
        .dropdown-divider { height: 1px; background: var(--gray-100); margin: 4px 0; }
        .notification-dropdown { width: 320px; }
        .notification-item {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 12px 16px; border-bottom: 1px solid var(--gray-50);
        }
        .notification-item:hover { background: var(--gray-50); }
        .notification-item.unread { background: var(--cream-light); }
        .notification-icon { font-size: 20px; flex-shrink: 0; }
        .notification-content { flex: 1; }
        .notification-title { display: block; font-size: 13px; font-weight: 500; color: var(--gray-800); }
        .notification-time { font-size: 11px; color: var(--gray-500); }
        .topbar-user {
            display: flex; align-items: center; gap: 12px;
            padding: 8px 12px; border-radius: var(--border-radius);
            cursor: pointer; transition: var(--transition);
        }
        .topbar-user:hover { background: var(--gray-100); }
        .topbar-user-avatar {
            width: 38px; height: 38px; background: var(--primary); color: var(--white);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 14px;
        }
        .topbar-user-info { display: flex; flex-direction: column; }
        .topbar-user-name { font-size: 14px; font-weight: 600; color: var(--gray-800); }
        .topbar-user-role { font-size: 12px; color: var(--gray-500); }
        .dropdown-arrow { font-size: 10px; color: var(--gray-400); margin-left: 4px; }
        .user-dropdown { width: 200px; }
        @media (max-width: 768px) { .topbar-user-info, .dropdown-arrow { display: none; } }
    `;
    document.head.appendChild(style);
}

function showLogoutModal() {
    document.getElementById('logout-modal-overlay')?.remove();

    const overlay = document.createElement('div');
    overlay.id = 'logout-modal-overlay';
    overlay.style.cssText = `
        position:fixed; inset:0; background:rgba(0,0,0,.45);
        display:flex; align-items:center; justify-content:center;
        z-index:9999;
    `;
    overlay.innerHTML = `
        <style>
            @keyframes lmFadeIn  { from{opacity:0} to{opacity:1} }
            @keyframes lmSlideUp { from{transform:translateY(18px);opacity:0} to{transform:translateY(0);opacity:1} }
            #logout-modal {
                background:#fff; border-radius:18px; padding:36px 32px 28px;
                width:380px; max-width:92vw; text-align:center;
                box-shadow:0 32px 80px rgba(0,0,0,.2);
                animation:lmSlideUp .22s cubic-bezier(.4,0,.2,1);
            }
            #logout-modal .lm-icon-wrap {
                width:60px; height:60px; border-radius:16px;
                background:linear-gradient(135deg,#E8F5E9,#d1fae5);
                display:flex; align-items:center; justify-content:center;
                font-size:28px; margin:0 auto 18px;
                box-shadow:0 4px 12px rgba(27,77,62,.12);
            }
            #logout-modal h3 {
                font-size:19px; font-weight:800; color:#111827; margin:0 0 8px;
                letter-spacing:-.3px;
            }
            #logout-modal p {
                font-size:14px; color:#6B7280; margin:0 0 28px; line-height:1.55;
            }
            #logout-modal .lm-actions { display:flex; gap:10px; }
            #logout-modal .lm-cancel {
                flex:1; padding:12px; border-radius:10px;
                border:1.5px solid #E5E7EB; background:#fff;
                font-size:14px; font-weight:600; color:#374151;
                cursor:pointer; transition:all .15s;
            }
            #logout-modal .lm-cancel:hover { background:#F9FAFB; border-color:#D1D5DB; }
            #logout-modal .lm-confirm {
                flex:1; padding:12px; border-radius:10px;
                border:none; background:#1B4D3E;
                font-size:14px; font-weight:600; color:#fff;
                cursor:pointer; transition:background .15s;
            }
            #logout-modal .lm-confirm:hover { background:#2D6A4F; }
        </style>
        <div id="logout-modal">
            <div class="lm-icon-wrap">🚪</div>
            <h3>Logging out?</h3>
            <p>You'll need to sign in again to access your account.</p>
            <div class="lm-actions">
                <button class="lm-cancel" id="lm-cancel">Stay</button>
                <button class="lm-confirm" id="lm-confirm">Yes, Logout</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    document.getElementById('lm-cancel').addEventListener('click', () => overlay.remove());
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
    document.getElementById('lm-confirm').addEventListener('click', () => {
        overlay.remove();
        Auth.logout();
    });
    const onKey = e => { if (e.key === 'Escape') { overlay.remove(); document.removeEventListener('keydown', onKey); } };
    document.addEventListener('keydown', onKey);
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}
