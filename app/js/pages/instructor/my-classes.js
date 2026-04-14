/**
 * Instructor My Classes Page
 */
import { Api } from '../../api.js';

function esc(str) { const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }

function cardHtml(c) {
    const noSec = !c.section_name;
    return `
    <div class="mc-card">
        <div class="mc-card-head${noSec ? ' no-sec' : ''}">
            <div class="mc-head-top">
                <span class="mc-code">${esc(c.subject_code)}</span>
                ${c.section_name ? `<span class="mc-sec-tag">${esc(c.section_name)}</span>` : ''}
            </div>
            <div class="mc-subj-name">${esc(c.subject_name)}</div>
            ${(c.schedule || c.room) ? `
            <div class="mc-schedule">
                ${c.schedule ? `<span class="mc-sch-item">
                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    ${esc(c.schedule)}</span>` : ''}
                ${c.room ? `<span class="mc-sch-item">
                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                    ${esc(c.room)}</span>` : ''}
            </div>` : noSec ? `<div style="font-size:11px;opacity:.6;margin-top:8px;">Not assigned to any section</div>` : ''}
        </div>
        <div class="mc-stats">
            <div class="mc-stat"><span class="mc-stat-num">${c.student_count}</span><span class="mc-stat-lbl">Students</span></div>
            <div class="mc-stat"><span class="mc-stat-num">${c.published_lessons}</span><span class="mc-stat-lbl">Lessons</span></div>
            <div class="mc-stat"><span class="mc-stat-num">${c.published_quizzes}</span><span class="mc-stat-lbl">Quizzes</span></div>
            <div class="mc-stat"><span class="mc-stat-num">${c.units}</span><span class="mc-stat-lbl">Units</span></div>
        </div>
        <div class="mc-actions">
            ${noSec
                ? `<button class="mc-btn danger btn-remove-class" data-id="${c.subject_offered_id}" data-name="${esc(c.subject_name)}">🗑 Remove</button>`
                : `<a class="mc-btn primary" href="#instructor/lessons?subject_id=${c.subject_id}">
                       <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25"/></svg>
                       Lessons
                   </a>
                   <a class="mc-btn" href="#instructor/quizzes">
                       <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c.98 0 1.813.626 2.122 1.5"/></svg>
                       Quizzes
                   </a>
                   <a class="mc-btn" href="#instructor/students">
                       <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                       Students
                   </a>`
            }
        </div>
    </div>`;
}

export async function render(container) {
    container.innerHTML = `<div style="display:flex;justify-content:center;padding:60px">
        <div style="width:36px;height:36px;border:3px solid #e8e8e8;border-top-color:#1B4D3E;border-radius:50%;animation:spin .8s linear infinite"></div>
    </div>`;

    const res     = await Api.get('/DashboardAPI.php?action=instructor');
    const classes = res.success ? res.data.classes : [];
    const sections = [...new Set(classes.map(c => c.section_name).filter(Boolean))];

    container.innerHTML = `
        <style>
            .mc-header { background:linear-gradient(135deg,#1B4D3E 0%,#2D6A4F 60%,#40916C 100%); border-radius:16px; padding:28px 32px; margin-bottom:20px; position:relative; overflow:hidden; display:flex; align-items:center; justify-content:space-between; }
            .mc-header::before { content:''; position:absolute; top:-40px; right:-40px; width:180px; height:180px; border-radius:50%; background:rgba(255,255,255,.07); pointer-events:none; }
            .mc-header::after { content:''; position:absolute; bottom:-60px; left:60px; width:220px; height:220px; border-radius:50%; background:rgba(255,255,255,.05); pointer-events:none; }
            .mc-header h2 { font-size:26px; font-weight:800; color:#fff; margin:0 0 4px; position:relative; z-index:1; }
            .mc-header-sub { font-size:14px; color:rgba(255,255,255,.75); margin:0; position:relative; z-index:1; }
            .mc-header-left { position:relative; z-index:1; }
            .mc-btn-announce { position:relative; z-index:1; display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.3); color:#fff; padding:10px 18px; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; transition:all .2s; }
            .mc-btn-announce:hover { background:rgba(255,255,255,.25); }
            .mc-count { background:rgba(255,255,255,.2); color:#fff; padding:2px 10px; border-radius:20px; font-size:13px; font-weight:600; }

            .mc-filters { display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; align-items:center; }
            .mc-search-wrap { position:relative; flex:1; min-width:200px; }
            .mc-search-wrap svg { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:#9ca3af; pointer-events:none; }
            .mc-search { width:100%; padding:9px 14px 9px 34px; border:1px solid #e8ecef; border-radius:10px; font-size:13px; outline:none; box-sizing:border-box; box-shadow:0 1px 2px rgba(0,0,0,.04); }
            .mc-search:focus { border-color:#1B4D3E; box-shadow:0 0 0 3px rgba(27,77,62,.08); }
            .mc-sel { padding:9px 14px; border:1px solid #e8ecef; border-radius:10px; font-size:13px; background:#fff; cursor:pointer; outline:none; color:#374151; box-shadow:0 1px 2px rgba(0,0,0,.04); }
            .mc-sel:focus { border-color:#1B4D3E; }
            .mc-result { font-size:12px; color:#9ca3af; margin-left:auto; white-space:nowrap; align-self:center; }

            .mc-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:16px; }
            .mc-card { background:#fff; border:1px solid #f1f5f9; border-radius:14px; overflow:hidden; display:flex; flex-direction:column; transition:all .2s; box-shadow:0 1px 3px rgba(0,0,0,.07); }
            .mc-card:hover { box-shadow:0 8px 24px rgba(0,0,0,.1); transform:translateY(-2px); }

            .mc-card-head { background:linear-gradient(135deg,#1B4D3E,#2D6A4F); padding:18px 20px 16px; color:#fff; position:relative; overflow:hidden; }
            .mc-card-head::after { content:''; position:absolute; right:-16px; top:-16px; width:70px; height:70px; border-radius:50%; background:rgba(255,255,255,.07); }
            .mc-card-head.no-sec { background:linear-gradient(135deg,#6b7280,#9ca3af); }
            .mc-head-top { display:flex; align-items:flex-start; justify-content:space-between; gap:8px; margin-bottom:6px; }
            .mc-code { background:rgba(255,255,255,.2); padding:3px 9px; border-radius:5px; font-family:monospace; font-size:12px; font-weight:700; }
            .mc-sec-tag { background:rgba(255,255,255,.15); padding:3px 8px; border-radius:5px; font-size:11px; font-weight:600; white-space:nowrap; }
            .mc-subj-name { font-size:15px; font-weight:700; line-height:1.3; }
            .mc-schedule { display:flex; gap:12px; margin-top:10px; flex-wrap:wrap; }
            .mc-sch-item { display:flex; align-items:center; gap:5px; font-size:11.5px; color:rgba(255,255,255,.85); }

            .mc-stats { display:grid; grid-template-columns:repeat(4,1fr); padding:14px 20px; gap:6px; border-bottom:1px solid #f8fafc; }
            .mc-stat { text-align:center; }
            .mc-stat-num { font-size:18px; font-weight:800; color:#111827; display:block; }
            .mc-stat-lbl { font-size:10px; color:#9ca3af; text-transform:uppercase; letter-spacing:.5px; }

            .mc-actions { padding:10px 16px; display:flex; gap:6px; flex-wrap:wrap; }
            .mc-btn { display:inline-flex; align-items:center; gap:4px; padding:7px 13px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; border:1.5px solid #e5e7eb; background:#fff; color:#374151; text-decoration:none; transition:all .15s; white-space:nowrap; }
            .mc-btn:hover { background:#f5f5f5; border-color:#d1d5db; }
            .mc-btn.primary { background:#1B4D3E; color:#fff; border-color:#1B4D3E; }
            .mc-btn.primary:hover { background:#2D6A4F; }
            .mc-btn.danger { background:#FEE2E2; color:#b91c1c; border-color:#fecaca; margin-left:auto; }
            .mc-btn.danger:hover { background:#fecaca; }

            .mc-no-results { text-align:center; padding:48px 20px; color:#9ca3af; font-size:14px; grid-column:1/-1; }
            .mc-empty { text-align:center; padding:60px 20px; color:#9ca3af; }
            @media(max-width:768px) { .mc-grid { grid-template-columns:1fr; } .mc-stats { grid-template-columns:1fr 1fr; } }
        </style>

        <div class="mc-header">
            <div class="mc-header-left">
                <h2>My Classes <span class="mc-count">${classes.length}</span></h2>
                <p class="mc-header-sub">Manage your assigned subjects and sections</p>
            </div>
            <a class="mc-btn-announce" href="#instructor/announcements">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46"/></svg>
                Announcements
            </a>
        </div>

        ${classes.length === 0 ? `<div class="mc-empty">No classes assigned yet.</div>` : `
            <div class="mc-filters">
                <div class="mc-search-wrap">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/></svg>
                    <input class="mc-search" id="mc-search" placeholder="Search subject…" autocomplete="off">
                </div>
                <select class="mc-sel" id="mc-sec-filter">
                    <option value="">All Sections</option>
                    ${sections.map(n => `<option value="${esc(n)}">${esc(n)}</option>`).join('')}
                </select>
                <span class="mc-result" id="mc-result">${classes.length} class${classes.length !== 1 ? 'es' : ''}</span>
            </div>
            <div class="mc-grid" id="mc-grid">
                ${classes.map(cardHtml).join('')}
            </div>
        `}
    `;

    if (classes.length === 0) return;

    const searchEl = container.querySelector('#mc-search');
    const secSel   = container.querySelector('#mc-sec-filter');
    const resultEl = container.querySelector('#mc-result');
    const grid     = container.querySelector('#mc-grid');

    function applyFilters() {
        const q   = searchEl.value.toLowerCase().trim();
        const sec = secSel.value;

        const filtered = classes.filter(c => {
            const txt    = (c.subject_code + ' ' + c.subject_name + ' ' + (c.section_name || '')).toLowerCase();
            const matchQ = !q   || txt.includes(q);
            const matchS = !sec || (c.section_name || '') === sec
                                || (c.section_name || '').split(', ').includes(sec);
            return matchQ && matchS;
        });

        grid.innerHTML = filtered.length > 0
            ? filtered.map(cardHtml).join('')
            : `<div class="mc-no-results">No classes match your search.</div>`;

        resultEl.textContent = `${filtered.length} of ${classes.length} class${classes.length !== 1 ? 'es' : ''}`;

        // Re-attach remove handlers after re-render
        grid.querySelectorAll('.btn-remove-class').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm(`Remove "${btn.dataset.name}" from My Classes?`)) return;
                btn.disabled = true; btn.textContent = 'Removing…';
                const r = await Api.post('/SectionsAPI.php?action=remove-class', { subject_offered_id: parseInt(btn.dataset.id) });
                if (r.success) render(container);
                else { btn.disabled = false; btn.textContent = '🗑 Remove'; alert(r.message); }
            });
        });
    }

    searchEl.addEventListener('input', applyFilters);
    secSel.addEventListener('change', applyFilters);

    // Attach remove handlers for initial render
    grid.querySelectorAll('.btn-remove-class').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm(`Remove "${btn.dataset.name}" from My Classes?`)) return;
            btn.disabled = true; btn.textContent = 'Removing…';
            const r = await Api.post('/SectionsAPI.php?action=remove-class', { subject_offered_id: parseInt(btn.dataset.id) });
            if (r.success) render(container);
            else { btn.disabled = false; btn.textContent = '🗑 Remove'; alert(r.message); }
        });
    });
}
