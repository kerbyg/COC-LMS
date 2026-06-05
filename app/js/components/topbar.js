/**
 * Topbar Component
 * Renders the top navigation bar
 */

import { Auth } from '../auth.js';
import { Api }  from '../api.js';

let _notifPollTimer      = null;
let _cachedAnnouncements = [];   // recent announcements fetched from API
let _topbarRole          = null;
let _topbarUserId        = null;

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
            <button class="topbar-btn" id="search-btn" title="Search  (Ctrl+K)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
            </button>

            <!-- Notifications -->
            <div class="dropdown" id="notification-dropdown">
                <button class="topbar-btn" title="Notifications" id="notification-toggle">
                    🔔
                    <span class="badge" id="notif-badge" style="display:none">0</span>
                </button>
                <div class="dropdown-menu notification-dropdown">
                    <div class="dropdown-header">
                        <strong>Notifications</strong>
                        <a href="javascript:void(0)" id="notif-mark-all">Mark all read</a>
                    </div>
                    <div class="dropdown-body" id="notif-body">
                        <div class="notif-loading">Loading...</div>
                    </div>
                    <div class="dropdown-footer" style="display:flex;justify-content:space-between;gap:8px;">
                        <a href="#${role}/announcements" id="notif-view-ann">All announcements</a>
                        <a href="#${role}/messages" id="notif-view-all">All messages</a>
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
        const toggle   = document.getElementById(`${id}-toggle`);
        const dropdown = document.getElementById(`${id}-dropdown`);
        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            document.querySelectorAll('.dropdown.active').forEach(d => {
                if (d !== dropdown) d.classList.remove('active');
            });
            const opening = !dropdown.classList.contains('active');
            dropdown.classList.toggle('active');
            if (opening && id === 'notification') loadNotifications(role);
        });
    });

    // Close dropdowns on outside click
    document.addEventListener('click', () => {
        document.querySelectorAll('.dropdown.active').forEach(d => d.classList.remove('active'));
    });

    // Mark all read
    document.getElementById('notif-mark-all').addEventListener('click', async (e) => {
        e.stopPropagation();                      // keep dropdown open
        const link = e.currentTarget;
        if (link.dataset.loading) return;         // prevent double-click

        // visual feedback
        const original = link.textContent;
        link.dataset.loading = '1';
        link.textContent = 'Marking…';
        link.style.opacity = '0.6';

        try {
            const res = await Api.post('/MessagingAPI.php?action=mark_all_read', {});
            // Also mark announcements as seen
            markAnnLastSeen();
            if (res.success) {
                updateNotifBadge(0);
                await loadNotifications(role);
                link.textContent = '✓ All read';
                setTimeout(() => { link.textContent = original; }, 2000);
            } else {
                link.textContent = 'Failed';
                setTimeout(() => { link.textContent = original; }, 2000);
            }
        } catch (_) {
            link.textContent = 'Error';
            setTimeout(() => { link.textContent = original; }, 2000);
        } finally {
            link.style.opacity = '';
            delete link.dataset.loading;
        }
    });

    // Search
    document.getElementById('search-btn').addEventListener('click', () => openSearch());
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); openSearch(); }
    });

    // Logout — custom modal
    document.getElementById('topbar-logout').addEventListener('click', () => {
        showLogoutModal();
    });

    // Cache role/userId for helpers
    _topbarRole   = role;
    _topbarUserId = user.id;

    // Start polling unread count
    pollUnreadCount();
    clearInterval(_notifPollTimer);
    _notifPollTimer = setInterval(pollUnreadCount, 30000);
}

async function pollUnreadCount() {
    try {
        const [msgRes, annRes] = await Promise.all([
            Api.get('/MessagingAPI.php?action=unread_count'),
            Api.get('/AnnouncementsAPI.php?action=new-announcements')
        ]);

        const msgCount = msgRes.success ? (msgRes.count || 0) : 0;

        // Cache all recent announcements; count those newer than last-seen
        _cachedAnnouncements = annRes.success ? (annRes.data || []) : [];
        const annCount = countNewAnnouncements();

        updateNotifBadge(msgCount + annCount);
    } catch (_) {}
}

/** Count announcements newer than the user's last-seen timestamp */
function countNewAnnouncements() {
    const lastSeen = getAnnLastSeen();
    return _cachedAnnouncements.filter(a => new Date(a.created_at) > lastSeen).length;
}

/** localStorage key scoped to current user */
function annLastSeenKey() {
    return `ann_last_seen_${_topbarUserId}`;
}

/** Get Date of last time the user acknowledged announcements (default: 7 days ago) */
function getAnnLastSeen() {
    const stored = localStorage.getItem(annLastSeenKey());
    return stored ? new Date(stored) : new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
}

/** Mark all announcements as seen right now */
function markAnnLastSeen() {
    localStorage.setItem(annLastSeenKey(), new Date().toISOString());
}

function updateNotifBadge(count) {
    const badge = document.getElementById('notif-badge');
    if (!badge) return;
    if (count > 0) {
        badge.textContent   = count > 99 ? '99+' : count;
        badge.style.display = 'inline-flex';
    } else {
        badge.style.display = 'none';
    }
}

async function loadNotifications(role) {
    const body = document.getElementById('notif-body');
    if (!body) return;
    body.innerHTML = '<div class="notif-loading">Loading...</div>';

    // Fetch messages in parallel; announcements already cached by pollUnreadCount
    const [msgRes] = await Promise.all([
        Api.get('/MessagingAPI.php?action=threads')
    ]);

    const threads     = msgRes.success ? msgRes.data : [];
    const unreadMsgs  = threads.filter(t => parseInt(t.unread) > 0);

    const lastSeen    = getAnnLastSeen();
    const newAnns     = _cachedAnnouncements.filter(a => new Date(a.created_at) > lastSeen);

    // Mark announcements as seen now that the panel is open
    if (newAnns.length) {
        markAnnLastSeen();
        // Re-poll to update badge (will now count 0 for announcements)
        setTimeout(pollUnreadCount, 300);
    }

    if (!unreadMsgs.length && !newAnns.length) {
        body.innerHTML = '<div class="notif-empty">You\'re all caught up! 🎉</div>';
        return;
    }

    let html = '';

    // ── Announcement section ───────────────────────────────────────────
    if (newAnns.length) {
        html += `<div class="notif-section-label">📢 New Announcements</div>`;
        html += newAnns.map(a => {
            const typeIcon = { urgent:'🚨', reminder:'⏰', event:'📅', general:'📣' }[a.announcement_type] || '📣';
            const subLabel = a.subject_code ? `<span style="font-size:10px;background:#DBEAFE;color:#1E40AF;padding:1px 6px;border-radius:8px;font-weight:700;margin-left:4px;">${escapeHtml(a.subject_code)}</span>` : '';
            return `
                <div class="notification-item unread notif-ann-item" style="cursor:pointer"
                     data-id="${a.announcement_id}">
                    <span class="notification-icon">
                        <span style="width:36px;height:36px;border-radius:10px;
                                     background:linear-gradient(135deg,#E8F5E9,#d1fae5);
                                     font-size:18px;display:flex;align-items:center;justify-content:center;">
                            ${typeIcon}
                        </span>
                    </span>
                    <div class="notification-content">
                        <span class="notification-title">${escapeHtml(a.title)}${subLabel}</span>
                        <span class="notification-time">By ${escapeHtml(a.author_name)} · ${relativeTime(a.created_at)}</span>
                    </div>
                </div>`;
        }).join('');
    }

    // ── Messages section ───────────────────────────────────────────────
    if (unreadMsgs.length) {
        html += `<div class="notif-section-label">💬 Unread Messages</div>`;
        html += unreadMsgs.map(t => {
            const initials = (t.name || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
            const preview  = (t.last_message || '').slice(0, 55);
            const time     = relativeTime(t.last_at);
            return `
                <div class="notification-item unread notif-msg-item" style="cursor:pointer"
                     data-id="${t.other_id}" data-name="${escapeHtml(t.name)}">
                    <span class="notification-icon">
                        <span style="width:36px;height:36px;border-radius:50%;
                                     background:linear-gradient(135deg,#1B4D3E,#2D6A4F);
                                     color:#fff;font-size:13px;font-weight:700;
                                     display:flex;align-items:center;justify-content:center;">
                            ${initials}
                        </span>
                    </span>
                    <div class="notification-content">
                        <span class="notification-title">${escapeHtml(t.name)}</span>
                        <span class="notification-time" style="display:block;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:210px;font-size:12px;color:#555">
                            ${escapeHtml(preview)}
                        </span>
                        <span class="notification-time">${time} · ${parseInt(t.unread)} new</span>
                    </div>
                </div>`;
        }).join('');
    }

    body.innerHTML = html;

    // Announcement click → go to announcements page
    body.querySelectorAll('.notif-ann-item').forEach(el => {
        el.addEventListener('click', () => {
            document.querySelectorAll('.dropdown.active').forEach(d => d.classList.remove('active'));
            window.location.hash = `#${role}/announcements`;
        });
    });

    // Message click → go to thread
    body.querySelectorAll('.notif-msg-item').forEach(el => {
        el.addEventListener('click', async () => {
            document.querySelectorAll('.dropdown.active').forEach(d => d.classList.remove('active'));
            await Api.post('/MessagingAPI.php?action=mark_read', { other_user_id: parseInt(el.dataset.id) });
            window.location.hash = `#${role}/messages?with=${el.dataset.id}&name=${encodeURIComponent(el.dataset.name)}`;
            pollUnreadCount();
        });
    });
}

function relativeTime(ts) {
    if (!ts) return '';
    const d    = new Date(ts.replace(' ', 'T'));
    const diff = (Date.now() - d.getTime()) / 1000;
    if (diff < 60)    return 'Just now';
    if (diff < 3600)  return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return d.toLocaleDateString();
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
        .notif-loading, .notif-empty {
            padding: 24px 16px; text-align: center; color: var(--gray-400); font-size: 13px;
        }
        .notif-section-label {
            padding: 8px 16px 4px;
            font-size: 10px; font-weight: 800; color: #9ca3af;
            text-transform: uppercase; letter-spacing: .06em;
            border-top: 1px solid #f3f4f6;
        }
        .notif-section-label:first-child { border-top: none; }
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

function openSearch() {
    document.getElementById('search-overlay')?.remove();

    const overlay = document.createElement('div');
    overlay.id = 'search-overlay';
    overlay.innerHTML = `
        <style>
            #search-overlay {
                position:fixed; inset:0; background:rgba(0,0,0,.45);
                display:flex; align-items:flex-start; justify-content:center;
                padding-top:90px; z-index:9999;
                animation:srFadeIn .15s ease;
            }
            @keyframes srFadeIn { from{opacity:0} to{opacity:1} }
            @keyframes srSlideDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
            #search-box {
                background:#fff; border-radius:16px; width:600px; max-width:94vw;
                box-shadow:0 24px 72px rgba(0,0,0,.28);
                overflow:hidden; animation:srSlideDown .18s cubic-bezier(.4,0,.2,1);
            }
            #search-input-row {
                display:flex; align-items:center; gap:12px;
                padding:16px 20px; border-bottom:1px solid #f0f0f0;
            }
            #search-input-row svg { flex-shrink:0; color:#9ca3af; }
            #search-input {
                flex:1; border:none; outline:none; font-size:16px;
                color:#111827; background:transparent; font-family:inherit;
            }
            #search-input::placeholder { color:#c5cdd6; }
            #search-kbd {
                font-size:11px; color:#9ca3af; background:#f3f4f6;
                border:1px solid #e5e7eb; border-radius:5px;
                padding:2px 7px; flex-shrink:0; white-space:nowrap;
            }
            #search-results { max-height:420px; overflow-y:auto; }
            .sr-category {
                padding:12px 20px 5px; font-size:10.5px; font-weight:700;
                color:#9ca3af; text-transform:uppercase; letter-spacing:.07em;
            }
            .sr-item {
                display:flex; align-items:center; gap:12px;
                padding:9px 20px; cursor:pointer; text-decoration:none;
                transition:background .1s; border-radius:0;
            }
            .sr-item:hover, .sr-item.sr-active { background:#f0fdf4; }
            .sr-item-icon {
                width:34px; height:34px; border-radius:9px; background:#f3f4f6;
                display:flex; align-items:center; justify-content:center;
                font-size:15px; flex-shrink:0;
            }
            .sr-item.sr-active .sr-item-icon { background:#E8F5E9; }
            .sr-item-body { flex:1; min-width:0; }
            .sr-item-label { font-size:13.5px; font-weight:600; color:#111827; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
            .sr-item-sub   { font-size:12px; color:#9ca3af; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
            .sr-item-arrow { color:#d1d5db; font-size:14px; flex-shrink:0; }
            .sr-item:hover .sr-item-arrow, .sr-item.sr-active .sr-item-arrow { color:#1B4D3E; }
            #search-empty { padding:36px 20px; text-align:center; color:#9ca3af; font-size:14px; }
            #search-empty span { display:block; font-size:28px; margin-bottom:8px; }
            #search-hint {
                padding:9px 20px; font-size:11.5px; color:#9ca3af;
                border-top:1px solid #f3f4f6;
                display:flex; gap:16px;
            }
            #search-hint kbd {
                background:#f3f4f6; border:1px solid #e5e7eb; border-radius:4px;
                padding:1px 6px; font-size:11px; color:#6b7280; font-family:inherit;
            }
            #search-loading { padding:28px 20px; text-align:center; color:#9ca3af; font-size:13px; }
        </style>
        <div id="search-box">
            <div id="search-input-row">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input id="search-input" type="text" placeholder="Search users, subjects, sections…" autocomplete="off" spellcheck="false">
                <span id="search-kbd">Esc to close</span>
            </div>
            <div id="search-results">
                <div id="search-empty"><span>🔍</span>Start typing to search…</div>
            </div>
            <div id="search-hint">
                <span><kbd>↑</kbd><kbd>↓</kbd> navigate</span>
                <span><kbd>Enter</kbd> open</span>
                <span><kbd>Esc</kbd> close</span>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);

    const input   = overlay.querySelector('#search-input');
    const results = overlay.querySelector('#search-results');
    let debounce  = null;
    let activeIdx = -1;

    input.focus();

    function getItems() { return results.querySelectorAll('.sr-item'); }

    function setActive(idx) {
        const items = getItems();
        items.forEach(el => el.classList.remove('sr-active'));
        activeIdx = Math.max(-1, Math.min(idx, items.length - 1));
        if (activeIdx >= 0) {
            items[activeIdx].classList.add('sr-active');
            items[activeIdx].scrollIntoView({ block: 'nearest' });
        }
    }

    function close() {
        overlay.remove();
        document.removeEventListener('keydown', onKey);
    }

    function navigateTo(url) { close(); window.location.hash = url.replace(/^#/, ''); }

    async function doSearch(q) {
        results.innerHTML = '<div id="search-loading">Searching…</div>';
        activeIdx = -1;
        try {
            const res = await Api.get('/SearchAPI.php?q=' + encodeURIComponent(q));
            if (!res.success || !res.data.length) {
                results.innerHTML = '<div id="search-empty"><span>🔍</span>No results for "' + escapeHtml(q) + '"</div>';
                return;
            }
            results.innerHTML = res.data.map(group => `
                <div class="sr-category">${escapeHtml(group.category)}</div>
                ${group.items.map(item => `
                    <div class="sr-item" data-url="${escapeHtml(item.url)}">
                        <div class="sr-item-icon">${item.icon}</div>
                        <div class="sr-item-body">
                            <span class="sr-item-label">${escapeHtml(item.label)}</span>
                            ${item.sub ? `<span class="sr-item-sub">${escapeHtml(item.sub)}</span>` : ''}
                        </div>
                        <span class="sr-item-arrow">›</span>
                    </div>`).join('')}
            `).join('');

            results.querySelectorAll('.sr-item').forEach(el => {
                el.addEventListener('click', () => navigateTo(el.dataset.url));
                el.addEventListener('mouseenter', () => {
                    getItems().forEach(i => i.classList.remove('sr-active'));
                    el.classList.add('sr-active');
                    activeIdx = [...getItems()].indexOf(el);
                });
            });
        } catch (_) {
            results.innerHTML = '<div id="search-empty">Search unavailable. Try again.</div>';
        }
    }

    input.addEventListener('input', () => {
        clearTimeout(debounce);
        const q = input.value.trim();
        if (q.length < 2) {
            results.innerHTML = '<div id="search-empty"><span>🔍</span>Start typing to search…</div>';
            activeIdx = -1;
            return;
        }
        debounce = setTimeout(() => doSearch(q), 280);
    });

    function onKey(e) {
        if (e.key === 'Escape') { close(); return; }
        const items = getItems();
        if (!items.length) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive(activeIdx + 1); }
        if (e.key === 'ArrowUp')   { e.preventDefault(); setActive(activeIdx - 1); }
        if (e.key === 'Enter' && activeIdx >= 0) {
            navigateTo(items[activeIdx].dataset.url);
        }
    }
    document.addEventListener('keydown', onKey);
    overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
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
