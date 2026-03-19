/**
 * Instructor Dashboard
 * Clean, professional overview — green/cream theme, red only for critical
 */
import { Api } from '../../api.js';
import { Auth } from '../../auth.js';

export async function render(container) {
    container.innerHTML = '<div style="display:flex;justify-content:center;padding:60px"><div style="width:40px;height:40px;border:3px solid #e8e4d9;border-top-color:#1B4D3E;border-radius:50%;animation:spin .8s linear infinite"></div></div>';

    const res = await Api.get('/DashboardAPI.php?action=instructor');
    const data = res.success ? res.data : {};
    const stats    = data.stats             || {};
    const classes  = data.classes           || [];
    const activity = data.recent_activity   || [];
    const quizPerf = data.quiz_performance  || [];
    const atRisk   = data.at_risk_students  || [];
    const user     = Auth.user();

    const hour     = new Date().getHours();
    const greeting = hour < 12 ? 'Good Morning' : hour < 18 ? 'Good Afternoon' : 'Good Evening';
    const today    = new Date().toLocaleDateString('en-US', { weekday:'long', month:'long', day:'numeric', year:'numeric' });

    container.innerHTML = `
        <style>
            /* ── Banner ─────────────────────────────── */
            .id-banner {
                background: linear-gradient(135deg, #1B4D3E 0%, #2D6A4F 55%, #3A7A5C 100%);
                border-radius: 16px; padding: 32px 36px; color: #fff;
                margin-bottom: 24px; position: relative; overflow: hidden;
            }
            .id-banner::before {
                content:''; position:absolute; right:-60px; top:-60px;
                width:260px; height:260px; border-radius:50%;
                background:rgba(255,255,255,0.04);
            }
            .id-banner::after {
                content:''; position:absolute; right:60px; bottom:-80px;
                width:180px; height:180px; border-radius:50%;
                background:rgba(255,255,255,0.03);
            }
            .id-banner-inner { position:relative; z-index:1; }
            .id-banner h2 { font-size:24px; font-weight:800; margin:0 0 5px; letter-spacing:-0.3px; }
            .id-banner p  { margin:0; opacity:0.65; font-size:13px; font-weight:400; }
            .id-banner-rule {
                display:inline-block; margin-top:14px;
                background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.18);
                border-radius:20px; padding:4px 14px; font-size:12px; font-weight:500; opacity:0.9;
            }

            /* ── Stat cards ──────────────────────────── */
            .id-stats {
                display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:22px;
            }
            .id-stat {
                background:#fff; border:1px solid #e8e4d9; border-radius:14px;
                padding:20px 22px; display:flex; align-items:center; gap:16px;
                transition:box-shadow .2s;
            }
            .id-stat:hover { box-shadow:0 4px 16px rgba(27,77,62,0.08); }
            .id-stat-icon {
                width:46px; height:46px; border-radius:12px; flex-shrink:0;
                display:flex; align-items:center; justify-content:center;
            }
            .id-stat-icon svg { display:block; }
            .id-stat-val { font-size:30px; font-weight:800; color:#1a2e1a; line-height:1; }
            .id-stat-lbl { font-size:12px; color:#7a8a7a; font-weight:500; margin-top:3px; }

            /* ── Quick Actions ───────────────────────── */
            .id-actions {
                display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px;
            }
            .id-action {
                display:flex; align-items:center; gap:14px;
                padding:17px 20px; background:#fff; border:1px solid #e8e4d9;
                border-radius:14px; text-decoration:none; color:#2d3d2d;
                transition:all .2s; cursor:pointer;
            }
            .id-action:hover {
                border-color:#1B4D3E; background:#F2F8F2;
                transform:translateY(-2px); box-shadow:0 6px 18px rgba(27,77,62,0.1);
            }
            .id-action-icon {
                width:42px; height:42px; border-radius:11px; flex-shrink:0;
                display:flex; align-items:center; justify-content:center;
                background:#EDF5ED;
            }
            .id-action-text { font-size:13px; font-weight:700; color:#1a2e1a; }
            .id-action-sub  { font-size:11px; color:#9aaa9a; margin-top:2px; }

            /* ── Layout grid ─────────────────────────── */
            .id-grid    { display:grid; grid-template-columns:1.4fr 1fr; gap:20px; margin-bottom:20px; }
            .id-grid-eq { grid-template-columns:1fr 1fr; }

            /* ── Card shell ──────────────────────────── */
            .id-card { background:#fff; border:1px solid #e8e4d9; border-radius:14px; overflow:hidden; }
            .id-card-hd {
                padding:15px 20px; border-bottom:1px solid #f0ebe2;
                display:flex; justify-content:space-between; align-items:center;
            }
            .id-card-hd h3 {
                margin:0; font-size:14px; font-weight:700; color:#1a2e1a;
                display:flex; align-items:center; gap:8px;
            }
            .id-card-hd h3 svg { color:#3A7A5C; }
            .id-cnt {
                background:#EDF5ED; color:#2D6A4F; padding:3px 10px;
                border-radius:20px; font-size:11px; font-weight:700;
            }
            .id-card-bd { padding:4px 20px 16px; }

            /* ── Activity ────────────────────────────── */
            .id-act {
                display:flex; gap:12px; padding:13px 0;
                border-bottom:1px solid #f5f1eb; align-items:flex-start;
            }
            .id-act:last-child { border-bottom:none; }
            .id-act-line {
                display:flex; flex-direction:column; align-items:center; flex-shrink:0;
                padding-top:4px;
            }
            .id-act-dot {
                width:9px; height:9px; border-radius:50%; background:#2D6A4F;
            }
            .id-act-body   { flex:1; min-width:0; }
            .id-act-text   { font-size:13px; color:#374137; line-height:1.45; }
            .id-act-text strong { color:#1a2e1a; font-weight:600; }
            .id-act-meta   { font-size:11px; color:#9aaa9a; margin-top:3px; display:flex; gap:8px; align-items:center; }
            .id-act-score  { font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px; }
            .id-act-score.pass { background:#EDF5ED; color:#1B4D3E; }
            .id-act-score.fail { background:#FEE2E2; color:#b91c1c; }

            /* ── Quiz Performance bars ───────────────── */
            .id-perf { margin-bottom:16px; }
            .id-perf:last-child { margin-bottom:0; }
            .id-perf-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:7px; }
            .id-perf-title { font-size:13px; color:#2d3d2d; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:72%; }
            .id-perf-score { font-size:13px; font-weight:800; }
            .id-perf-score.good { color:#1B4D3E; }
            .id-perf-score.mid  { color:#7A6010; }
            .id-perf-score.low  { color:#b91c1c; }
            .id-perf-track { background:#F0EDE6; height:7px; border-radius:4px; overflow:hidden; }
            .id-perf-fill  { height:100%; border-radius:4px; transition:width .7s ease; }
            .id-perf-fill.good { background:linear-gradient(90deg,#1B4D3E,#3A7A5C); }
            .id-perf-fill.mid  { background:linear-gradient(90deg,#7A6010,#B8920A); }
            .id-perf-fill.low  { background:linear-gradient(90deg,#b91c1c,#ef4444); }
            .id-perf-meta { font-size:11px; color:#9aaa9a; margin-top:4px; }

            /* ── At-Risk ─────────────────────────────── */
            .id-risk {
                display:flex; justify-content:space-between; align-items:center;
                padding:12px 0; border-bottom:1px solid #f5f1eb;
            }
            .id-risk:last-child { border-bottom:none; }
            .id-risk-info  { display:flex; align-items:center; gap:11px; }
            .id-risk-av    {
                width:34px; height:34px; border-radius:9px;
                background:#FEE2E2; color:#b91c1c;
                display:flex; align-items:center; justify-content:center;
                font-size:11px; font-weight:800; flex-shrink:0; letter-spacing:.5px;
            }
            .id-risk-nm  { font-size:13px; color:#2d3d2d; font-weight:600; }
            .id-risk-sub { font-size:11px; color:#9aaa9a; }
            .id-risk-score { font-size:16px; font-weight:800; color:#b91c1c; }

            /* ── Classes list ────────────────────────── */
            .id-class {
                display:flex; justify-content:space-between; align-items:center;
                padding:12px 0; border-bottom:1px solid #f5f1eb;
            }
            .id-class:last-child { border-bottom:none; }
            .id-class-left  { display:flex; align-items:center; gap:11px; }
            .id-class-code  {
                background:#EDF5ED; color:#1B4D3E; padding:4px 10px;
                border-radius:7px; font-size:11px; font-weight:800;
                font-family:'Consolas','Monaco',monospace; letter-spacing:.4px; flex-shrink:0;
            }
            .id-class-name  { font-size:13px; color:#2d3d2d; font-weight:500; }
            .id-class-sec   { font-size:11px; color:#9aaa9a; }
            .id-class-right { text-align:center; }
            .id-class-num   { font-size:17px; font-weight:800; color:#1B4D3E; }
            .id-class-lbl   { font-size:10px; color:#9aaa9a; text-transform:uppercase; letter-spacing:.5px; }

            /* ── Empty state ─────────────────────────── */
            .id-empty { text-align:center; padding:30px 16px; color:#9aaa9a; font-size:13px; }

            /* ── Divider in card-bd ──────────────────── */
            .id-card-bd > :first-child { margin-top:8px; }

            /* ── Responsive ──────────────────────────── */
            @media(max-width:1100px){
                .id-stats   { grid-template-columns:repeat(2,1fr); }
                .id-actions { grid-template-columns:repeat(2,1fr); }
                .id-grid    { grid-template-columns:1fr; }
            }
            @media(max-width:640px){
                .id-stats   { grid-template-columns:1fr; }
                .id-actions { grid-template-columns:1fr; }
            }
        </style>

        <!-- Banner -->
        <div class="id-banner">
            <div class="id-banner-inner">
                <h2>${greeting}, ${esc(user.first_name)}</h2>
                <p>Here's your teaching overview for today.</p>
                <span class="id-banner-rule">${today}</span>
            </div>
        </div>

        <!-- Stats -->
        <div class="id-stats">
            ${stat('#EDF5ED','#1B4D3E', svgClass(),   stats.classes  || 0, 'My Classes')}
            ${stat('#EDF5ED','#2D6A4F', svgStudents(), stats.students || 0, 'Total Students')}
            ${stat('#F3F5EE','#3A5A2A', svgLesson(),   stats.lessons  || 0, 'Lessons Created')}
            ${stat('#F3F5EE','#3A5A2A', svgQuiz(),     stats.quizzes  || 0, 'Quizzes Created')}
        </div>

        <!-- Quick Actions -->
        <div class="id-actions">
            <a href="#instructor/lessons" class="id-action">
                <div class="id-action-icon">${svgLesson('#1B4D3E')}</div>
                <div>
                    <div class="id-action-text">Manage Lessons</div>
                    <div class="id-action-sub">Create and organize content</div>
                </div>
            </a>
            <a href="#instructor/quizzes" class="id-action">
                <div class="id-action-icon">${svgQuiz('#1B4D3E')}</div>
                <div>
                    <div class="id-action-text">Manage Quizzes</div>
                    <div class="id-action-sub">Create and review assessments</div>
                </div>
            </a>
            <a href="#instructor/quiz-ai-generate" class="id-action">
                <div class="id-action-icon">${svgAI('#1B4D3E')}</div>
                <div>
                    <div class="id-action-text">AI Quiz Generator</div>
                    <div class="id-action-sub">Auto-generate from lessons</div>
                </div>
            </a>
            <a href="#instructor/students" class="id-action">
                <div class="id-action-icon">${svgStudents('#1B4D3E')}</div>
                <div>
                    <div class="id-action-text">My Students</div>
                    <div class="id-action-sub">View enrolled students</div>
                </div>
            </a>
        </div>

        <!-- Row 1: Activity + Classes -->
        <div class="id-grid">
            <div class="id-card">
                <div class="id-card-hd">
                    <h3>
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Recent Activity
                    </h3>
                    <span class="id-cnt">${activity.length} recent</span>
                </div>
                <div class="id-card-bd">
                    ${activity.length === 0
                        ? '<div class="id-empty">No recent student activity yet.</div>'
                        : activity.map(a => `
                        <div class="id-act">
                            <div class="id-act-line"><div class="id-act-dot"></div></div>
                            <div class="id-act-body">
                                <div class="id-act-text">
                                    <strong>${esc(a.student)}</strong>
                                    ${a.type === 'quiz' ? `completed <strong>${esc(a.detail)}</strong>` : `finished <strong>${esc(a.detail)}</strong>`}
                                </div>
                                <div class="id-act-meta">
                                    <span>${esc(a.subject)}</span>
                                    <span>${timeAgo(a.time)}</span>
                                    ${a.type === 'quiz' ? `<span class="id-act-score ${a.passed ? 'pass' : 'fail'}">${a.score}%</span>` : ''}
                                </div>
                            </div>
                        </div>`).join('')}
                </div>
            </div>

            <div class="id-card">
                <div class="id-card-hd">
                    <h3>
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342"/></svg>
                        My Classes
                    </h3>
                    <span class="id-cnt">${classes.length}</span>
                </div>
                <div class="id-card-bd">
                    ${classes.length === 0
                        ? '<div class="id-empty">No classes assigned yet.</div>'
                        : classes.map(c => `
                        <div class="id-class">
                            <div class="id-class-left">
                                <span class="id-class-code">${esc(c.subject_code)}</span>
                                <div>
                                    <div class="id-class-name">${esc(c.subject_name)}</div>
                                    <div class="id-class-sec">${esc(c.section_name || 'No section')}</div>
                                </div>
                            </div>
                            <div class="id-class-right">
                                <div class="id-class-num">${c.student_count}</div>
                                <div class="id-class-lbl">Students</div>
                            </div>
                        </div>`).join('')}
                </div>
            </div>
        </div>

        <!-- Row 2: Quiz Performance + At-Risk -->
        <div class="id-grid id-grid-eq">
            <div class="id-card">
                <div class="id-card-hd">
                    <h3>
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                        Quiz Performance
                    </h3>
                </div>
                <div class="id-card-bd">
                    ${quizPerf.length === 0
                        ? '<div class="id-empty">No quiz data yet. Create quizzes and have students take them.</div>'
                        : quizPerf.map(q => {
                            const score    = parseFloat(q.avg_score) || 0;
                            const level    = score >= 75 ? 'good' : score >= 50 ? 'mid' : 'low';
                            const passRate = q.attempts > 0 ? Math.round((q.passed_count / q.attempts) * 100) : 0;
                            return `
                            <div class="id-perf">
                                <div class="id-perf-top">
                                    <span class="id-perf-title">${esc(q.quiz_title)}</span>
                                    <span class="id-perf-score ${level}">${score}%</span>
                                </div>
                                <div class="id-perf-track"><div class="id-perf-fill ${level}" style="width:${Math.max(3, score)}%"></div></div>
                                <div class="id-perf-meta">${esc(q.subject_code)} &middot; ${q.attempts} attempts &middot; ${passRate}% pass rate</div>
                            </div>`;
                        }).join('')}
                </div>
            </div>

            <div class="id-card">
                <div class="id-card-hd">
                    <h3>
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        At-Risk Students
                    </h3>
                </div>
                <div class="id-card-bd">
                    ${atRisk.length === 0
                        ? '<div class="id-empty">All students are performing well.</div>'
                        : atRisk.map(s => {
                            const init = ((s.first_name||'?')[0] + (s.last_name||'?')[0]).toUpperCase();
                            return `
                            <div class="id-risk">
                                <div class="id-risk-info">
                                    <div class="id-risk-av">${init}</div>
                                    <div>
                                        <div class="id-risk-nm">${esc(s.first_name)} ${esc(s.last_name)}</div>
                                        <div class="id-risk-sub">${s.quiz_count} quiz(es) taken</div>
                                    </div>
                                </div>
                                <span class="id-risk-score">${s.avg_score}%</span>
                            </div>`;
                        }).join('')}
                </div>
            </div>
        </div>
    `;
}

// ── SVG icon helpers ───────────────────────────────────────────────────────

function svgClass(c='currentColor') {
    return `<svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="${c}" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342"/></svg>`;
}
function svgStudents(c='currentColor') {
    return `<svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="${c}" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>`;
}
function svgLesson(c='currentColor') {
    return `<svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="${c}" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>`;
}
function svgQuiz(c='currentColor') {
    return `<svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="${c}" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>`;
}
function svgAI(c='currentColor') {
    return `<svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="${c}" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z"/></svg>`;
}

// ── Stat card helper ───────────────────────────────────────────────────────

function stat(bg, color, svg, value, label) {
    return `
    <div class="id-stat">
        <div class="id-stat-icon" style="background:${bg};color:${color}">${svg}</div>
        <div>
            <div class="id-stat-val">${value}</div>
            <div class="id-stat-lbl">${label}</div>
        </div>
    </div>`;
}

// ── Helpers ────────────────────────────────────────────────────────────────

function timeAgo(dateStr) {
    if (!dateStr) return '';
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60)     return 'just now';
    if (diff < 3600)   return Math.floor(diff / 60)   + 'm ago';
    if (diff < 86400)  return Math.floor(diff / 3600)  + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    return new Date(dateStr).toLocaleDateString();
}

function esc(str) { const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }
