/**
 * Student Dashboard — green/cream theme, SVG icons only, no emojis
 */
import { Api } from '../../api.js';
import { Auth } from '../../auth.js';

export async function render(container) {
    container.innerHTML = '<div style="display:flex;justify-content:center;padding:60px"><div style="width:40px;height:40px;border:3px solid #e8e4d9;border-top-color:#1B4D3E;border-radius:50%;animation:spin .8s linear infinite"></div></div>';

    const res  = await Api.get('/DashboardAPI.php?action=student');
    const data = res.success ? res.data : {};
    const stats          = data.stats           || {};
    const subjects       = data.subjects        || [];
    const pendingQuizzes = data.pending_quizzes || [];
    const user           = Auth.user();

    const hour     = new Date().getHours();
    const greeting = hour < 12 ? 'Good Morning' : hour < 18 ? 'Good Afternoon' : 'Good Evening';
    const today    = new Date().toLocaleDateString('en-US', { weekday:'long', month:'long', day:'numeric', year:'numeric' });

    // ── Subject cards ──────────────────────────────────────────────────────
    const subjectCards = subjects.length === 0
        ? `<div class="sd-empty">No enrolled subjects yet. <a href="#student/enroll" style="color:#1B4D3E;font-weight:600;">Enroll in a section →</a></div>`
        : subjects.map(s => {
            const pct    = s.progress || 0;
            const qDone  = parseInt(s.completed_quizzes || 0);
            const qTotal = parseInt(s.total_quizzes    || 0);
            const barColor = pct >= 75 ? '#1B4D3E' : pct >= 40 ? '#7A6010' : '#d1d5c8';
            return `
            <div class="sd-subj-card">
                <div class="sd-subj-top">
                    <span class="sd-subj-code">${esc(s.subject_code)}</span>
                    <span class="sd-subj-units">${s.units || 0} units</span>
                </div>
                <div class="sd-subj-name">${esc(s.subject_name)}</div>
                <div class="sd-subj-meta">
                    <span class="sd-meta-row">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                        ${esc(s.instructor_name || 'TBA')}
                    </span>
                    ${s.schedule ? `<span class="sd-meta-row">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        ${esc(s.schedule)}
                    </span>` : ''}
                    ${s.room ? `<span class="sd-meta-row">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                        ${esc(s.room)}
                    </span>` : ''}
                    ${s.section_name ? `<span class="sd-meta-row">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342"/></svg>
                        ${esc(s.section_name)}
                    </span>` : ''}
                </div>
                <div class="sd-subj-progress-row">
                    <span>Lessons</span>
                    <span>${s.completed_lessons}/${s.total_lessons} &bull; ${pct}%</span>
                </div>
                <div class="sd-subj-track"><div class="sd-subj-fill" style="width:${pct}%;background:${barColor}"></div></div>
                <div class="sd-subj-footer">
                    <span class="sd-quiz-stat">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>
                        ${qDone}/${qTotal} quizzes done
                    </span>
                    <a href="#student/quizzes" class="sd-subj-link">Quizzes →</a>
                </div>
            </div>`;
        }).join('');

    // ── Pending quizzes ────────────────────────────────────────────────────
    const todoRows = pendingQuizzes.length === 0 ? '' :
        pendingQuizzes.map(q => `
        <div class="sd-todo-row">
            <span class="sd-todo-code">${esc(q.subject_code)}</span>
            <div class="sd-todo-info">
                <span class="sd-todo-title">${esc(q.quiz_title)}</span>
                <span class="sd-todo-sub">${q.time_limit ? q.time_limit + ' min' : 'No time limit'}</span>
            </div>
            <a href="#student/quizzes" class="sd-todo-btn">Take Quiz</a>
        </div>`).join('');

    container.innerHTML = `
        <style>
            /* ── Banner ─────────────────────────────────── */
            .sd-banner {
                background:linear-gradient(135deg,#1B4D3E 0%,#2D6A4F 55%,#3A7A5C 100%);
                border-radius:16px; padding:32px 36px; color:#fff;
                margin-bottom:24px; position:relative; overflow:hidden;
            }
            .sd-banner::before {
                content:''; position:absolute; right:-60px; top:-60px;
                width:260px; height:260px; border-radius:50%;
                background:rgba(255,255,255,0.04);
            }
            .sd-banner::after {
                content:''; position:absolute; right:60px; bottom:-80px;
                width:180px; height:180px; border-radius:50%;
                background:rgba(255,255,255,0.03);
            }
            .sd-banner-inner { position:relative; z-index:1; }
            .sd-banner h2 { font-size:24px; font-weight:800; margin:0 0 5px; letter-spacing:-.3px; }
            .sd-banner p  { margin:0; opacity:.65; font-size:13px; }
            .sd-banner-rule {
                display:inline-block; margin-top:14px;
                background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.18);
                border-radius:20px; padding:4px 14px; font-size:12px; font-weight:500; opacity:.9;
            }

            /* ── Stats ──────────────────────────────────── */
            .sd-stats {
                display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:22px;
            }
            .sd-stat {
                background:#fff; border:1px solid #e8e4d9; border-radius:14px;
                padding:20px 22px; display:flex; align-items:center; gap:16px;
                transition:box-shadow .2s;
            }
            .sd-stat:hover { box-shadow:0 4px 16px rgba(27,77,62,0.08); }
            .sd-stat-icon {
                width:46px; height:46px; border-radius:12px; flex-shrink:0;
                background:#EDF5ED; color:#1B4D3E;
                display:flex; align-items:center; justify-content:center;
            }
            .sd-stat-val { font-size:30px; font-weight:800; color:#1a2e1a; line-height:1; }
            .sd-stat-val sub { font-size:16px; color:#7a8a7a; font-weight:500; vertical-align:baseline; }
            .sd-stat-lbl { font-size:12px; color:#7a8a7a; font-weight:500; margin-top:3px; }

            /* ── Quick Actions ──────────────────────────── */
            .sd-actions {
                display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px;
            }
            .sd-action {
                display:flex; align-items:center; gap:14px;
                padding:17px 20px; background:#fff; border:1px solid #e8e4d9;
                border-radius:14px; text-decoration:none; color:#2d3d2d; transition:all .2s;
            }
            .sd-action:hover {
                border-color:#1B4D3E; background:#F2F8F2;
                transform:translateY(-2px); box-shadow:0 6px 18px rgba(27,77,62,0.1);
            }
            .sd-action-icon {
                width:42px; height:42px; border-radius:11px; flex-shrink:0;
                background:#EDF5ED; color:#1B4D3E;
                display:flex; align-items:center; justify-content:center;
            }
            .sd-action-text { font-size:13px; font-weight:700; color:#1a2e1a; }
            .sd-action-sub  { font-size:11px; color:#9aaa9a; margin-top:2px; }

            /* ── Section headers ────────────────────────── */
            .sd-section-hdr {
                display:flex; align-items:center; gap:10px; margin-bottom:14px;
            }
            .sd-section-hdr h3 { font-size:15px; font-weight:700; color:#1a2e1a; margin:0; }
            .sd-pill {
                background:#EDF5ED; color:#1B4D3E; font-size:11px; font-weight:700;
                padding:3px 10px; border-radius:20px;
            }
            .sd-pill.warn { background:#FEE2E2; color:#b91c1c; }

            /* ── To-Do card ─────────────────────────────── */
            .sd-todo-card {
                background:#fff; border:1px solid #e8e4d9; border-radius:14px;
                overflow:hidden; margin-bottom:24px;
            }
            .sd-todo-row {
                display:flex; align-items:center; gap:14px;
                padding:14px 20px; border-bottom:1px solid #f5f1eb;
            }
            .sd-todo-row:last-child { border-bottom:none; }
            .sd-todo-code {
                flex-shrink:0; font-size:11px; font-weight:800;
                background:#EDF5ED; color:#1B4D3E; padding:3px 9px;
                border-radius:7px; font-family:'Consolas','Monaco',monospace;
            }
            .sd-todo-info { flex:1; min-width:0; }
            .sd-todo-title { display:block; font-size:13px; font-weight:600; color:#1a2e1a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
            .sd-todo-sub   { font-size:11px; color:#9aaa9a; }
            .sd-todo-btn {
                flex-shrink:0; padding:7px 16px; background:#1B4D3E; color:#fff;
                border-radius:8px; font-size:12px; font-weight:700; text-decoration:none; transition:background .2s;
            }
            .sd-todo-btn:hover { background:#2D6A4F; }

            /* ── Subjects grid ──────────────────────────── */
            .sd-subjects-grid {
                display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
                gap:16px; margin-bottom:28px;
            }
            .sd-subj-card {
                background:#fff; border:1px solid #e8e4d9; border-radius:14px;
                padding:18px; display:flex; flex-direction:column; gap:9px;
                transition:box-shadow .2s, transform .2s;
            }
            .sd-subj-card:hover {
                box-shadow:0 6px 20px rgba(27,77,62,0.1); transform:translateY(-2px);
            }
            .sd-subj-top { display:flex; align-items:center; justify-content:space-between; }
            .sd-subj-code {
                font-size:12px; font-weight:800; color:#1B4D3E;
                background:#EDF5ED; padding:3px 10px; border-radius:7px;
                font-family:'Consolas','Monaco',monospace; letter-spacing:.4px;
            }
            .sd-subj-units { font-size:11px; color:#9aaa9a; }
            .sd-subj-name  { font-size:15px; font-weight:700; color:#1a2e1a; line-height:1.3; }
            .sd-subj-meta  { display:flex; flex-direction:column; gap:4px; }
            .sd-meta-row   { display:flex; align-items:center; gap:6px; font-size:12px; color:#6b7a6b; }
            .sd-meta-row svg { flex-shrink:0; color:#3A7A5C; }
            .sd-subj-progress-row {
                display:flex; justify-content:space-between; font-size:11px; color:#7a8a7a; margin-top:2px;
            }
            .sd-subj-track { height:6px; background:#F0EDE6; border-radius:3px; overflow:hidden; }
            .sd-subj-fill  { height:100%; border-radius:3px; transition:width .5s; }
            .sd-subj-footer {
                display:flex; align-items:center; justify-content:space-between; margin-top:2px;
            }
            .sd-quiz-stat {
                display:flex; align-items:center; gap:5px;
                font-size:12px; color:#7a8a7a;
            }
            .sd-quiz-stat svg { color:#3A7A5C; }
            .sd-subj-link {
                font-size:12px; font-weight:700; color:#1B4D3E; text-decoration:none;
            }
            .sd-subj-link:hover { text-decoration:underline; }

            /* ── Empty ──────────────────────────────────── */
            .sd-empty { padding:28px; text-align:center; color:#9aaa9a; font-size:13px; }

            /* ── Responsive ─────────────────────────────── */
            @media(max-width:1100px){
                .sd-stats   { grid-template-columns:repeat(2,1fr); }
                .sd-actions { grid-template-columns:repeat(2,1fr); }
            }
            @media(max-width:640px){
                .sd-stats           { grid-template-columns:1fr; }
                .sd-actions         { grid-template-columns:1fr; }
                .sd-subjects-grid   { grid-template-columns:1fr; }
            }
        </style>

        <!-- Banner -->
        <div class="sd-banner">
            <div class="sd-banner-inner">
                <h2>${greeting}, ${esc(user.first_name)}</h2>
                <p>Here's your learning overview for today.</p>
                <span class="sd-banner-rule">${today}</span>
            </div>
        </div>

        <!-- Stats -->
        <div class="sd-stats">
            <div class="sd-stat">
                <div class="sd-stat-icon">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                </div>
                <div>
                    <div class="sd-stat-val">${stats.subjects || 0}</div>
                    <div class="sd-stat-lbl">Enrolled Subjects</div>
                </div>
            </div>
            <div class="sd-stat">
                <div class="sd-stat-icon">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342"/></svg>
                </div>
                <div>
                    <div class="sd-stat-val">${stats.lessons_completed || 0}<sub>/${stats.total_lessons || 0}</sub></div>
                    <div class="sd-stat-lbl">Lessons Done</div>
                </div>
            </div>
            <div class="sd-stat">
                <div class="sd-stat-icon">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>
                </div>
                <div>
                    <div class="sd-stat-val">${stats.total_quizzes || 0}</div>
                    <div class="sd-stat-lbl">Quizzes Available</div>
                </div>
            </div>
            <div class="sd-stat">
                <div class="sd-stat-icon">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                </div>
                <div>
                    <div class="sd-stat-val">${stats.avg_score || 0}%</div>
                    <div class="sd-stat-lbl">Average Score</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="sd-actions">
            <a href="#student/enroll" class="sd-action">
                <div class="sd-action-icon">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                </div>
                <div>
                    <div class="sd-action-text">Enroll in Section</div>
                    <div class="sd-action-sub">Join a new class</div>
                </div>
            </a>
            <a href="#student/lessons" class="sd-action">
                <div class="sd-action-icon">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                </div>
                <div>
                    <div class="sd-action-text">My Lessons</div>
                    <div class="sd-action-sub">Continue learning</div>
                </div>
            </a>
            <a href="#student/grades" class="sd-action">
                <div class="sd-action-icon">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                </div>
                <div>
                    <div class="sd-action-text">View Grades</div>
                    <div class="sd-action-sub">Check your scores</div>
                </div>
            </a>
            <a href="#student/progress" class="sd-action">
                <div class="sd-action-icon">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/></svg>
                </div>
                <div>
                    <div class="sd-action-text">My Progress</div>
                    <div class="sd-action-sub">Track your learning</div>
                </div>
            </a>
        </div>

        <!-- Pending Quizzes -->
        ${pendingQuizzes.length > 0 ? `
        <div class="sd-section-hdr">
            <h3>To-Do</h3>
            <span class="sd-pill warn">${pendingQuizzes.length} quiz${pendingQuizzes.length !== 1 ? 'zes' : ''} pending</span>
        </div>
        <div class="sd-todo-card">${todoRows}</div>
        ` : ''}

        <!-- My Subjects -->
        <div class="sd-section-hdr">
            <h3>My Subjects</h3>
            <span class="sd-pill">${subjects.length}</span>
        </div>
        <div class="sd-subjects-grid">${subjectCards}</div>
    `;
}

function esc(str) { const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }
