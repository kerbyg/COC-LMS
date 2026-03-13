/**
 * Admin – Roles & Permissions (RBAC)
 * Simple: pick a role → check/uncheck permissions → Save.
 */
import { Api } from '../../api.js';

const ROLES = ['admin', 'dean', 'instructor', 'student'];
const ROLE_META = {
    admin:      { label: 'Administrator', color: '#7c3aed', bg: '#f5f3ff', icon: '🛡️' },
    dean:       { label: 'Dean',          color: '#0891b2', bg: '#f0f9ff', icon: '🎓' },
    instructor: { label: 'Instructor',    color: '#059669', bg: '#f0fdf4', icon: '👨‍🏫' },
    student:    { label: 'Student',       color: '#d97706', bg: '#fffbeb', icon: '🎒' },
};
const MODULE_ICONS = {
    users:'👥', departments:'🏢', programs:'🎓', subjects:'📚',
    curriculum:'📋', sections:'🏫', subject_offerings:'📅',
    faculty_assignments:'👨‍🏫', quizzes:'📝', lessons:'📖',
    question_bank:'🏦', grades:'📊', reports:'📈',
    analytics:'📉', remedials:'🔄', settings:'⚙️', rbac:'🔐'
};

export async function render(container) {
    container.innerHTML = `
    <style>
        .rp-wrap { max-width: 960px; }

        /* Banner */
        .rp-banner {
            background: linear-gradient(135deg,#00461B 0%,#006428 100%);
            border-radius: 16px; padding: 24px 28px; margin-bottom: 24px;
            display: flex; align-items: center; gap: 16px;
            box-shadow: 0 4px 20px rgba(0,70,27,.2);
        }
        .rp-banner-icon {
            width: 48px; height: 48px; border-radius: 12px;
            background: rgba(255,255,255,.15);
            display: flex; align-items: center; justify-content: center; font-size: 22px;
        }
        .rp-banner h1 { font-size: 20px; font-weight: 800; color: #fff; margin: 0 0 3px; }
        .rp-banner p  { color: rgba(255,255,255,.75); font-size: 13px; margin: 0; }

        /* Role cards */
        .rp-roles { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .rp-role-card {
            flex: 1; min-width: 130px; padding: 14px 16px; border-radius: 12px;
            border: 2px solid #e5e7eb; background: #fff; cursor: pointer;
            transition: all .18s; text-align: center;
        }
        .rp-role-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
        .rp-role-card.active { border-width: 2px; }
        .rp-role-icon { font-size: 22px; margin-bottom: 6px; }
        .rp-role-name { font-size: 13px; font-weight: 700; }
        .rp-role-count { font-size: 11px; color: #6b7280; margin-top: 3px; }

        /* Toolbar */
        .rp-toolbar {
            display: flex; align-items: center; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;
        }
        .rp-search {
            flex: 1; min-width: 180px; padding: 8px 12px;
            border: 1px solid #e5e7eb; border-radius: 8px; font-size: 13px; outline: none;
        }
        .rp-search:focus { border-color: #00461B; }
        .rp-btn {
            padding: 8px 16px; border-radius: 8px; font-size: 13px;
            font-weight: 600; cursor: pointer; border: 1px solid #e5e7eb;
            background: #fff; color: #374151; transition: background .15s;
        }
        .rp-btn:hover { background: #f3f4f6; }
        .rp-btn-save {
            background: #00461B; color: #fff; border-color: #00461B;
        }
        .rp-btn-save:hover { background: #003315; }
        .rp-btn-save:disabled { opacity: .5; cursor: not-allowed; }

        /* Module group */
        .rp-module { margin-bottom: 12px; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .rp-module-head {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 16px; background: #f9fafb; cursor: pointer;
            user-select: none; border-bottom: 1px solid #e5e7eb;
        }
        .rp-module-head:hover { background: #f3f4f6; }
        .rp-mod-icon { font-size: 15px; }
        .rp-mod-title {
            flex: 1; font-size: 13px; font-weight: 700; color: #374151;
            text-transform: capitalize;
        }
        .rp-mod-badge {
            font-size: 11px; padding: 2px 8px; border-radius: 20px;
            font-weight: 600; transition: all .2s;
        }
        .rp-mod-check-all {
            font-size: 11px; padding: 3px 10px; border-radius: 6px;
            border: 1px solid #d1d5db; background: #fff; cursor: pointer;
            color: #6b7280; transition: all .15s;
        }
        .rp-mod-check-all:hover { background: #f3f4f6; }
        .rp-chevron { color: #9ca3af; font-size: 10px; transition: transform .2s; }
        .rp-chevron.open { transform: rotate(90deg); }

        /* Permission row */
        .rp-perm-list { padding: 4px 0; }
        .rp-perm-list.collapsed { display: none; }
        .rp-perm-row {
            display: flex; align-items: center; gap: 12px;
            padding: 9px 16px; transition: background .12s; cursor: pointer;
        }
        .rp-perm-row:hover { background: #f9fafb; }
        .rp-perm-row.hidden { display: none; }

        /* Checkbox */
        .rp-cb {
            width: 17px; height: 17px; flex-shrink: 0;
            accent-color: #00461B; cursor: pointer;
        }
        .rp-perm-name {
            font-size: 12.5px; font-weight: 600; color: #1e293b;
            font-family: 'Courier New', monospace;
        }
        .rp-perm-desc { font-size: 12px; color: #6b7280; }

        /* Toast */
        .rp-toast {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            background: #1e293b; color: #fff; border-radius: 10px;
            padding: 10px 20px; font-size: 13px; font-weight: 500;
            box-shadow: 0 8px 24px rgba(0,0,0,.2); z-index: 9999;
            opacity: 0; transition: opacity .25s; pointer-events: none;
        }
        .rp-toast.show { opacity: 1; pointer-events: auto; }
        .rp-toast.error { background: #7f1d1d; }
    </style>

    <div class="rp-wrap">
        <div class="rp-banner">
            <div class="rp-banner-icon">🔐</div>
            <div>
                <h1>Roles & Permissions</h1>
                <p>Pick a role, then check or uncheck what it can do — click Save when done.</p>
            </div>
        </div>

        <div class="rp-roles" id="rpRoles"></div>

        <div class="rp-toolbar">
            <input class="rp-search" id="rpSearch" type="text" placeholder="Search permissions…">
            <button class="rp-btn" id="rpCheckAll">Check All</button>
            <button class="rp-btn" id="rpUncheckAll">Uncheck All</button>
            <button class="rp-btn rp-btn-save" id="rpSave">Save Changes</button>
        </div>

        <div id="rpMatrix">
            <div style="padding:40px;text-align:center;color:#9ca3af">Loading…</div>
        </div>
    </div>

    <div class="rp-toast" id="rpToast"></div>
    `;

    // ── State ──────────────────────────────────────────────────────
    let allPerms = {};    // { module: [{id,name,description},...] }
    let dirty    = {};    // { role: Set<id> }
    let active   = 'admin';

    // ── Load ───────────────────────────────────────────────────────
    const res = await Api.get('/RBACApi.php?action=matrix');
    if (!res.success) {
        container.querySelector('#rpMatrix').innerHTML =
            `<div style="padding:24px;color:#ef4444">Failed to load: ${res.message}</div>`;
        return;
    }

    // Group permissions by module
    res.data.permissions.forEach(p => {
        (allPerms[p.module] = allPerms[p.module] || []).push(p);
    });

    // Build dirty state
    ROLES.forEach(r => {
        const granted = new Set(res.data.matrix[r] || []);
        dirty[r] = new Set(
            res.data.permissions.filter(p => granted.has(p.name)).map(p => p.id)
        );
    });

    renderRoles();
    renderMatrix();

    // ── Render role cards ──────────────────────────────────────────
    function renderRoles() {
        const el = container.querySelector('#rpRoles');
        el.innerHTML = ROLES.map(r => {
            const m = ROLE_META[r];
            const isActive = r === active;
            return `
            <div class="rp-role-card ${isActive ? 'active' : ''}" data-role="${r}"
                 style="${isActive ? `border-color:${m.color};background:${m.bg}` : ''}">
                <div class="rp-role-icon">${m.icon}</div>
                <div class="rp-role-name" style="color:${m.color}">${m.label}</div>
                <div class="rp-role-count" id="rc-${r}">${dirty[r].size} permissions</div>
            </div>`;
        }).join('');

        el.querySelectorAll('.rp-role-card').forEach(card => {
            card.addEventListener('click', () => {
                active = card.dataset.role;
                renderRoles();
                renderMatrix();
            });
        });
    }

    function updateRoleCount(role) {
        const el = container.querySelector(`#rc-${role}`);
        if (el) el.textContent = `${dirty[role].size} permissions`;
    }

    // ── Render permission matrix ───────────────────────────────────
    function renderMatrix() {
        const color = ROLE_META[active].color;
        const matEl = container.querySelector('#rpMatrix');

        matEl.innerHTML = Object.entries(allPerms).map(([mod, perms]) => {
            const grantedCount = perms.filter(p => dirty[active].has(p.id)).length;
            const allGranted   = grantedCount === perms.length;
            const badgeBg      = grantedCount ? color + '22' : '#f1f5f9';
            const badgeColor   = grantedCount ? color : '#9ca3af';

            return `
            <div class="rp-module">
                <div class="rp-module-head" data-mod="${mod}">
                    <span class="rp-chevron open">&#9654;</span>
                    <span class="rp-mod-icon">${MODULE_ICONS[mod] || '📌'}</span>
                    <span class="rp-mod-title">${mod.replace(/_/g,' ')}</span>
                    <span class="rp-mod-badge" style="background:${badgeBg};color:${badgeColor}"
                          id="badge-${mod}">${grantedCount}/${perms.length}</span>
                    <button class="rp-mod-check-all" data-mod-all="${mod}"
                            data-action="${allGranted ? 'off' : 'on'}">
                        ${allGranted ? 'Uncheck all' : 'Check all'}
                    </button>
                </div>
                <div class="rp-perm-list" data-mod-list="${mod}">
                    ${perms.map(p => `
                    <label class="rp-perm-row" data-perm="${p.name}">
                        <input class="rp-cb" type="checkbox" data-id="${p.id}"
                            ${dirty[active].has(p.id) ? 'checked' : ''}>
                        <div>
                            <div class="rp-perm-name">${p.name}</div>
                            <div class="rp-perm-desc">${p.description}</div>
                        </div>
                    </label>`).join('')}
                </div>
            </div>`;
        }).join('');

        // Checkbox change
        matEl.querySelectorAll('.rp-cb').forEach(cb => {
            cb.addEventListener('change', () => {
                const id = parseInt(cb.dataset.id);
                cb.checked ? dirty[active].add(id) : dirty[active].delete(id);
                updateRoleCount(active);
                updateModBadge(cb.closest('.rp-module').querySelector('.rp-module-head').dataset.mod);
            });
        });

        // Module collapse
        matEl.querySelectorAll('.rp-module-head').forEach(head => {
            head.addEventListener('click', e => {
                if (e.target.closest('.rp-mod-check-all')) return;
                const mod  = head.dataset.mod;
                const list = matEl.querySelector(`[data-mod-list="${mod}"]`);
                const chev = head.querySelector('.rp-chevron');
                list.classList.toggle('collapsed');
                chev.classList.toggle('open', !list.classList.contains('collapsed'));
            });
        });

        // Check/uncheck all in module
        matEl.querySelectorAll('.rp-mod-check-all').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                const mod    = btn.dataset.modAll;
                const on     = btn.dataset.action === 'on';
                allPerms[mod].forEach(p => on ? dirty[active].add(p.id) : dirty[active].delete(p.id));
                updateRoleCount(active);
                // re-render just this module's checkboxes
                const list = matEl.querySelector(`[data-mod-list="${mod}"]`);
                list.querySelectorAll('.rp-cb').forEach(cb => {
                    cb.checked = on;
                });
                btn.dataset.action = on ? 'off' : 'on';
                btn.textContent    = on ? 'Uncheck all' : 'Check all';
                updateModBadge(mod);
            });
        });
    }

    function updateModBadge(mod) {
        const color  = ROLE_META[active].color;
        const perms  = allPerms[mod] || [];
        const count  = perms.filter(p => dirty[active].has(p.id)).length;
        const badge  = container.querySelector(`#badge-${mod}`);
        if (!badge) return;
        badge.textContent = `${count}/${perms.length}`;
        badge.style.background = count ? color + '22' : '#f1f5f9';
        badge.style.color      = count ? color        : '#9ca3af';
    }

    // ── Toolbar ────────────────────────────────────────────────────
    container.querySelector('#rpCheckAll').addEventListener('click', () => {
        Object.values(allPerms).flat().forEach(p => dirty[active].add(p.id));
        updateRoleCount(active);
        renderMatrix();
    });

    container.querySelector('#rpUncheckAll').addEventListener('click', () => {
        dirty[active].clear();
        updateRoleCount(active);
        renderMatrix();
    });

    container.querySelector('#rpSearch').addEventListener('input', e => {
        const q = e.target.value.toLowerCase();
        container.querySelectorAll('.rp-perm-row').forEach(row => {
            row.classList.toggle('hidden', !!q && !row.dataset.perm.includes(q));
        });
    });

    // ── Save ───────────────────────────────────────────────────────
    container.querySelector('#rpSave').addEventListener('click', async () => {
        const btn = container.querySelector('#rpSave');
        btn.disabled = true;
        btn.textContent = 'Saving…';
        try {
            const r = await Api.post('/RBACApi.php?action=update-role', {
                role: active,
                permission_ids: [...dirty[active]]
            });
            toast(r.success ? `✓ ${ROLE_META[active].label} permissions saved` : `Error: ${r.message}`, !r.success);
        } catch (_) {
            toast('Save failed', true);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Save Changes';
        }
    });

    function toast(msg, isError = false) {
        const el = container.querySelector('#rpToast');
        el.textContent = msg;
        el.className = 'rp-toast show' + (isError ? ' error' : '');
        setTimeout(() => el.classList.remove('show'), 3000);
    }
}
