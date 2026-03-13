/**
 * Student Messages — split-panel chat (student ↔ instructor)
 * Polling every 3 s keeps messages "real-time".
 */
import { Api } from '../../api.js';
import { Auth } from '../../auth.js';

let pollTimer  = null;   // setInterval handle for chat polling
let badgeTimer = null;   // setInterval handle for sidebar badge
let activeOtherId = null;

// ── Helpers ────────────────────────────────────────────────────────────────

function esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function relativeTime(ts) {
    if (!ts) return '';
    const d = new Date(ts.replace(' ', 'T'));
    const diff = (Date.now() - d.getTime()) / 1000;
    if (diff < 60)  return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return d.toLocaleDateString();
}

function fmtTime(ts) {
    if (!ts) return '';
    const d = new Date(ts.replace(' ', 'T'));
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// ── Main render ────────────────────────────────────────────────────────────

export async function render(container) {
    // Stop previous polling on page leave
    clearInterval(pollTimer);
    clearInterval(badgeTimer);
    activeOtherId = null;

    container.innerHTML = `
        <style>
            .msg-layout {
                display: flex; height: calc(100vh - 140px); min-height: 500px;
                border: 1px solid #e8e8e8; border-radius: 14px; overflow: hidden;
                background: #fff;
            }
            /* ── Left panel: thread list ── */
            .msg-sidebar {
                width: 300px; min-width: 260px; border-right: 1px solid #f0f0f0;
                display: flex; flex-direction: column; background: #fafafa;
            }
            .msg-sidebar-header {
                padding: 18px 16px 14px;
                border-bottom: 1px solid #f0f0f0;
            }
            .msg-sidebar-header h2 { font-size: 18px; font-weight: 800; color: #111827; margin: 0 0 12px; }
            .msg-new-btn {
                display: flex; align-items: center; gap: 7px;
                width: 100%; padding: 9px 14px; border-radius: 9px;
                background: #1B4D3E; color: #fff; border: none;
                font-size: 13px; font-weight: 600; cursor: pointer;
                transition: background .18s;
            }
            .msg-new-btn:hover { background: #2D6A4F; }
            .thread-list { flex: 1; overflow-y: auto; }
            .thread-item {
                display: flex; align-items: flex-start; gap: 10px;
                padding: 13px 16px; cursor: pointer;
                border-bottom: 1px solid #f5f5f5;
                transition: background .15s;
            }
            .thread-item:hover  { background: #f0f7f5; }
            .thread-item.active { background: #E8F5E9; border-left: 3px solid #1B4D3E; }
            .thread-avatar {
                width: 40px; height: 40px; border-radius: 50%;
                background: linear-gradient(135deg, #1B4D3E, #2D6A4F);
                color: #fff; font-size: 15px; font-weight: 700;
                display: flex; align-items: center; justify-content: center;
                flex-shrink: 0;
            }
            .thread-info { flex: 1; min-width: 0; }
            .thread-name { font-size: 13px; font-weight: 700; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .thread-preview { font-size: 12px; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
            .thread-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
            .thread-time { font-size: 11px; color: #9ca3af; }
            .thread-badge {
                background: #1B4D3E; color: #fff;
                font-size: 10px; font-weight: 700;
                padding: 2px 7px; border-radius: 20px; min-width: 18px; text-align: center;
            }
            .thread-empty { padding: 32px 16px; text-align: center; color: #9ca3af; font-size: 13px; }

            /* ── Right panel: chat window ── */
            .msg-main { flex: 1; display: flex; flex-direction: column; }
            .msg-topbar {
                padding: 14px 20px; border-bottom: 1px solid #f0f0f0;
                display: flex; align-items: center; gap: 12px;
                background: #fff;
            }
            .msg-topbar-avatar {
                width: 38px; height: 38px; border-radius: 50%;
                background: linear-gradient(135deg, #1B4D3E, #2D6A4F);
                color: #fff; font-size: 15px; font-weight: 700;
                display: flex; align-items: center; justify-content: center; flex-shrink: 0;
            }
            .msg-topbar-name { font-size: 15px; font-weight: 700; color: #111827; }
            .msg-topbar-role { font-size: 12px; color: #6b7280; text-transform: capitalize; }
            .msg-body {
                flex: 1; overflow-y: auto; padding: 20px;
                display: flex; flex-direction: column; gap: 12px;
                background: #f9fafb;
            }
            .msg-bubble-row { display: flex; }
            .msg-bubble-row.mine { justify-content: flex-end; }
            .msg-bubble-row.theirs { justify-content: flex-start; }
            .msg-bubble {
                max-width: 68%; padding: 10px 14px;
                border-radius: 16px; font-size: 14px; line-height: 1.5;
                word-break: break-word;
            }
            .msg-bubble-row.mine   .msg-bubble { background: #1B4D3E; color: #fff; border-bottom-right-radius: 4px; }
            .msg-bubble-row.theirs .msg-bubble { background: #fff; color: #111827; border: 1px solid #e8e8e8; border-bottom-left-radius: 4px; }
            .msg-time { font-size: 11px; opacity: .65; margin-top: 3px; display: block; text-align: right; }
            .msg-bubble-row.theirs .msg-time { text-align: left; }
            .msg-date-divider {
                text-align: center; font-size: 11px; color: #9ca3af;
                padding: 8px 0; position: relative;
            }
            .msg-date-divider::before {
                content: ''; position: absolute; left: 0; right: 0; top: 50%;
                border-top: 1px solid #e8e8e8;
            }
            .msg-date-divider span { background: #f9fafb; padding: 0 10px; position: relative; }

            .msg-footer {
                padding: 14px 20px; border-top: 1px solid #f0f0f0;
                display: flex; gap: 10px; background: #fff; align-items: flex-end;
            }
            .msg-input {
                flex: 1; padding: 10px 14px; border-radius: 22px;
                border: 1px solid #e0e0e0; font-size: 14px; outline: none;
                resize: none; max-height: 120px; line-height: 1.5; font-family: inherit;
                transition: border-color .18s;
            }
            .msg-input:focus { border-color: #1B4D3E; }
            .msg-send-btn {
                width: 40px; height: 40px; border-radius: 50%;
                background: #1B4D3E; color: #fff; border: none;
                font-size: 18px; cursor: pointer; transition: background .18s;
                display: flex; align-items: center; justify-content: center; flex-shrink: 0;
            }
            .msg-send-btn:hover { background: #2D6A4F; }
            .msg-send-btn:disabled { background: #d1d5db; cursor: default; }

            .msg-placeholder {
                flex: 1; display: flex; flex-direction: column;
                align-items: center; justify-content: center; color: #9ca3af;
                gap: 12px; padding: 40px;
            }
            .msg-placeholder-icon { font-size: 48px; }
            .msg-placeholder h3 { font-size: 18px; color: #6b7280; margin: 0; }
            .msg-placeholder p { font-size: 14px; margin: 0; text-align: center; }

            /* New chat modal */
            .nc-overlay {
                position: fixed; inset: 0; background: rgba(0,0,0,.45);
                display: flex; align-items: center; justify-content: center; z-index: 900;
            }
            .nc-modal {
                background: #fff; border-radius: 14px; width: 420px; max-width: 95vw;
                max-height: 80vh; display: flex; flex-direction: column;
                box-shadow: 0 20px 60px rgba(0,0,0,.2);
            }
            .nc-header {
                padding: 18px 20px; border-bottom: 1px solid #f0f0f0;
                display: flex; justify-content: space-between; align-items: center;
            }
            .nc-header h3 { font-size: 16px; font-weight: 700; margin: 0; color: #111827; }
            .nc-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #6b7280; padding: 4px 8px; }
            .nc-body { flex: 1; overflow-y: auto; padding: 12px 8px; }
            .nc-contact {
                display: flex; align-items: center; gap: 12px;
                padding: 10px 12px; border-radius: 10px; cursor: pointer;
                transition: background .15s;
            }
            .nc-contact:hover { background: #f0f7f5; }
            .nc-avatar {
                width: 38px; height: 38px; border-radius: 50%;
                background: linear-gradient(135deg, #1B4D3E, #2D6A4F);
                color: #fff; font-size: 14px; font-weight: 700;
                display: flex; align-items: center; justify-content: center;
            }
            .nc-name { font-size: 14px; font-weight: 600; color: #111827; }
            .nc-sub  { font-size: 12px; color: #6b7280; }

            @media (max-width: 640px) {
                .msg-layout { flex-direction: column; height: auto; }
                .msg-sidebar { width: 100%; min-width: unset; border-right: none; border-bottom: 1px solid #f0f0f0; max-height: 260px; }
                .msg-main { min-height: 400px; }
            }
        </style>

        <div style="margin-bottom:20px">
            <h2 style="font-size:22px;font-weight:800;color:#111827;margin:0">Messages</h2>
            <p style="font-size:13px;color:#6b7280;margin:4px 0 0">Chat with your instructors for instant academic support.</p>
        </div>

        <div class="msg-layout">
            <!-- Left: thread list -->
            <div class="msg-sidebar">
                <div class="msg-sidebar-header">
                    <h2>Chats</h2>
                    <button class="msg-new-btn" id="btn-new-chat">
                        ✏️ New Message
                    </button>
                </div>
                <div class="thread-list" id="thread-list">
                    <div class="thread-empty">Loading...</div>
                </div>
            </div>

            <!-- Right: chat window -->
            <div class="msg-main" id="msg-main">
                <div class="msg-placeholder">
                    <div class="msg-placeholder-icon">💬</div>
                    <h3>Select a conversation</h3>
                    <p>Choose an instructor from the list, or start a new message.</p>
                </div>
            </div>
        </div>
    `;

    await loadThreads();
    startBadgePolling();

    document.getElementById('btn-new-chat').addEventListener('click', openNewChatModal);

    // If URL has ?with=ID open that thread immediately
    const params = new URLSearchParams(window.location.hash.split('?')[1] || '');
    if (params.get('with')) openThread(parseInt(params.get('with'), 10), params.get('name') || 'Chat');
}

// ── Thread list ────────────────────────────────────────────────────────────

async function loadThreads() {
    const res = await Api.get('/MessagingAPI.php?action=threads');
    const threads = res.success ? res.data : [];
    const list = document.getElementById('thread-list');
    if (!list) return;

    if (!threads.length) {
        list.innerHTML = '<div class="thread-empty">No conversations yet.<br>Click <strong>New Message</strong> to start.</div>';
        return;
    }

    list.innerHTML = threads.map(t => {
        const initials = (t.name || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
        const unread = parseInt(t.unread || 0);
        const active = activeOtherId === parseInt(t.other_id) ? 'active' : '';
        return `
            <div class="thread-item ${active}" data-id="${t.other_id}" data-name="${esc(t.name)}" onclick="window._openThread(${t.other_id}, '${esc(t.name)}')">
                <div class="thread-avatar">${initials}</div>
                <div class="thread-info">
                    <div class="thread-name">${esc(t.name)}</div>
                    <div class="thread-preview">${esc((t.last_message || '').slice(0, 50))}</div>
                </div>
                <div class="thread-meta">
                    <span class="thread-time">${relativeTime(t.last_at)}</span>
                    ${unread > 0 ? `<span class="thread-badge">${unread}</span>` : ''}
                </div>
            </div>`;
    }).join('');
}

// ── Open a thread (chat window) ────────────────────────────────────────────

window._openThread = function(otherId, name) { openThread(otherId, name); };

async function openThread(otherId, name) {
    clearInterval(pollTimer);
    activeOtherId = otherId;

    // Mark active in thread list
    document.querySelectorAll('.thread-item').forEach(el => {
        el.classList.toggle('active', parseInt(el.dataset.id) === otherId);
    });

    const main = document.getElementById('msg-main');
    if (!main) return;

    const initials = (name || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();

    main.innerHTML = `
        <div class="msg-topbar">
            <div class="msg-topbar-avatar">${initials}</div>
            <div>
                <div class="msg-topbar-name">${esc(name)}</div>
                <div class="msg-topbar-role">Instructor</div>
            </div>
        </div>
        <div class="msg-body" id="chat-body">
            <div style="text-align:center;padding:20px;color:#9ca3af;font-size:13px">Loading messages...</div>
        </div>
        <div class="msg-footer">
            <textarea class="msg-input" id="msg-input" rows="1" placeholder="Type a message..." maxlength="2000"></textarea>
            <button class="msg-send-btn" id="msg-send" title="Send">➤</button>
        </div>
    `;

    // Auto-resize textarea
    const input = document.getElementById('msg-input');
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    });
    // Send on Enter (Shift+Enter = newline)
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });
    document.getElementById('msg-send').addEventListener('click', sendMessage);

    await loadMessages(otherId);
    markRead(otherId);

    // Poll every 3 seconds for new messages
    pollTimer = setInterval(async () => {
        if (activeOtherId === otherId) {
            await loadMessages(otherId, true);
        }
    }, 3000);
}

// ── Load messages ─────────────────────────────────────────────────────────

let lastMessageCount = 0;

async function loadMessages(otherId, isPolling = false) {
    const res = await Api.get(`/MessagingAPI.php?action=messages&with=${otherId}`);
    const msgs = res.success ? res.data : [];
    const body = document.getElementById('chat-body');
    if (!body) return;

    // On polling: only re-render if new messages arrived
    if (isPolling && msgs.length === lastMessageCount) return;
    lastMessageCount = msgs.length;

    const me = Auth.user()?.id;
    const wasAtBottom = body.scrollTop + body.clientHeight >= body.scrollHeight - 40;

    if (!msgs.length) {
        body.innerHTML = '<div style="text-align:center;padding:40px;color:#9ca3af;font-size:13px">No messages yet. Say hello! 👋</div>';
        return;
    }

    let html = '';
    let lastDate = '';
    for (const m of msgs) {
        const d = new Date((m.created_at || '').replace(' ', 'T'));
        const dateStr = d.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
        if (dateStr !== lastDate) {
            html += `<div class="msg-date-divider"><span>${dateStr}</span></div>`;
            lastDate = dateStr;
        }
        const mine = parseInt(m.sender_id) === parseInt(me);
        html += `
            <div class="msg-bubble-row ${mine ? 'mine' : 'theirs'}">
                <div class="msg-bubble">
                    ${esc(m.content)}
                    <span class="msg-time">${fmtTime(m.created_at)}</span>
                </div>
            </div>`;
    }
    body.innerHTML = html;

    // Auto-scroll to bottom if user was already at bottom (or initial load)
    if (!isPolling || wasAtBottom) {
        body.scrollTop = body.scrollHeight;
    }

    // Refresh thread list to update previews
    if (!isPolling) loadThreads();
    markRead(otherId);
}

// ── Send message ───────────────────────────────────────────────────────────

async function sendMessage() {
    const input = document.getElementById('msg-input');
    const btn   = document.getElementById('msg-send');
    if (!input || !activeOtherId) return;

    const content = input.value.trim();
    if (!content) return;

    btn.disabled = true;
    input.disabled = true;

    const res = await Api.post('/MessagingAPI.php?action=send', {
        receiver_id: activeOtherId,
        content
    });

    input.disabled = false;
    btn.disabled = false;

    if (res.success) {
        input.value = '';
        input.style.height = 'auto';
        await loadMessages(activeOtherId);
        loadThreads();
    } else {
        alert(res.message || 'Failed to send message');
    }
    input.focus();
}

// ── Mark read ─────────────────────────────────────────────────────────────

async function markRead(otherId) {
    await Api.post('/MessagingAPI.php?action=mark_read', { other_user_id: otherId });
    updateSidebarBadge();
}

// ── Sidebar badge ─────────────────────────────────────────────────────────

async function updateSidebarBadge() {
    const res = await Api.get('/MessagingAPI.php?action=unread_count');
    const count = res.success ? res.count : 0;
    const badge = document.getElementById('msg-nav-badge');
    if (badge) {
        badge.textContent = count > 0 ? (count > 99 ? '99+' : count) : '';
        badge.style.display = count > 0 ? 'inline-flex' : 'none';
    }
}

function startBadgePolling() {
    updateSidebarBadge();
    clearInterval(badgeTimer);
    badgeTimer = setInterval(updateSidebarBadge, 30000);
}

// ── New chat modal ─────────────────────────────────────────────────────────

async function openNewChatModal() {
    const res = await Api.get('/MessagingAPI.php?action=contacts');
    const contacts = res.success ? res.data : [];

    const overlay = document.createElement('div');
    overlay.className = 'nc-overlay';
    overlay.innerHTML = `
        <div class="nc-modal">
            <div class="nc-header">
                <h3>New Message</h3>
                <button class="nc-close" id="nc-close">✕</button>
            </div>
            <div class="nc-body">
                ${!contacts.length
                    ? '<div style="padding:20px;text-align:center;color:#9ca3af;font-size:13px">No instructors found yet.<br>Enroll in a section first.</div>'
                    : contacts.map(c => {
                        const initials = (c.name || '?').split(' ').map(w => w[0]).join('').slice(0,2).toUpperCase();
                        return `
                        <div class="nc-contact" onclick="window._startNewChat(${c.users_id}, '${esc(c.name)}')">
                            <div class="nc-avatar">${initials}</div>
                            <div>
                                <div class="nc-name">${esc(c.name)}</div>
                                <div class="nc-sub">${esc(c.subject_code ? c.subject_code + ' · ' + c.subject_name : 'Instructor')}</div>
                            </div>
                        </div>`;
                    }).join('')
                }
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
    document.getElementById('nc-close').addEventListener('click', () => overlay.remove());
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });

    window._startNewChat = (id, name) => {
        overlay.remove();
        openThread(id, name);
    };
}
