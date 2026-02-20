/**
 * Instructor Dashboard
 * Clean, professional overview with quick actions and key metrics
 */
import { Api } from '../../api.js';
import { Auth } from '../../auth.js';

export async function render(container) {
    container.innerHTML = '<div style="display:flex;justify-content:center;padding:60px"><div style="width:40px;height:40px;border:3px solid #e8e8e8;border-top-color:#1B4D3E;border-radius:50%;animation:spin .8s linear infinite"></div></div>';

    const res = await Api.get('/DashboardAPI.php?action=instructor');
    const data = res.success ? res.data : {};
    const stats = data.stats || {};
    const classes = data.classes || [];
    const activity = data.recent_activity || [];
    const quizPerf = data.quiz_performance || [];
    const atRisk = data.at_risk_students || [];
    const user = Auth.user();

    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good Morning' : hour < 18 ? 'Good Afternoon' : 'Good Evening';
    const today = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

    container.innerHTML = `
        <style>
            /* ===== Banner ===== */
            .id-banner {
                background: linear-gradient(135deg, #1B4D3E 0%, #2D6A4F 60%, #40916C 100%);
                border-radius: 16px; padding: 28px 32px; color: #fff;
                margin-bottom: 24px; position: relative; overflow: hidden;
            }
            .id-banner::before {
                content: ''; position: absolute; right: -30px; top: -30px;
                width: 180px; height: 180px; border-radius: 50%;
                background: rgba(255,255,255,0.04);
            }
            .id-banner h2 { font-size: 22px; font-weight: 700; margin: 0 0 4px; }
            .id-banner p { margin: 0; opacity: 0.7; font-size: 13px; }

            /* ===== Stats ===== */
            .id-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
            .id-stat {
                background: #fff; border: 1px solid #e8e8e8; border-radius: 14px;
                padding: 20px; display: flex; align-items: center; gap: 16px;
            }
            .id-stat-icon {
                width: 48px; height: 48px; border-radius: 12px;
                display: flex; align-items: center; justify-content: center;
                font-size: 22px; flex-shrink: 0;
            }
            .id-stat-val { font-size: 28px; font-weight: 800; color: #1f2937; line-height: 1; }
            .id-stat-lbl { font-size: 12px; color: #6b7280; font-weight: 500; margin-top: 2px; }

            /* ===== Quick Actions ===== */
            .id-actions {
                display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px;
                margin-bottom: 24px;
            }
            .id-action {
                display: flex; align-items: center; gap: 14px;
                padding: 18px 20px; background: #fff; border: 1px solid #e8e8e8;
                border-radius: 14px; text-decoration: none; color: #374151;
                transition: all 0.2s; cursor: pointer;
            }
            .id-action:hover {
                border-color: #1B4D3E; background: #f0fdf4;
                transform: translateY(-2px); box-shadow: 0 4px 12px rgba(27,77,62,0.1);
            }
            .id-action-icon {
                width: 44px; height: 44px; border-radius: 12px;
                display: flex; align-items: center; justify-content: center;
                font-size: 20px; flex-shrink: 0;
            }
            .id-action-text { font-size: 14px; font-weight: 600; }
            .id-action-sub { font-size: 11px; color: #9ca3af; font-weight: 400; margin-top: 1px; }

            /* ===== Grid ===== */
            .id-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px; margin-bottom: 20px; }
            .id-grid-eq { grid-template-columns: 1fr 1fr; }

            /* ===== Card ===== */
            .id-card { background: #fff; border: 1px solid #e8e8e8; border-radius: 14px; overflow: hidden; }
            .id-card-hd {
                padding: 16px 20px; border-bottom: 1px solid #f0f0f0;
                display: flex; justify-content: space-between; align-items: center;
            }
            .id-card-hd h3 {
                margin: 0; font-size: 15px; font-weight: 700; color: #1f2937;
                display: flex; align-items: center; gap: 8px;
            }
            .id-cnt {
                background: #f3f4f6; color: #6b7280; padding: 3px 10px;
                border-radius: 12px; font-size: 11px; font-weight: 600;
            }
            .id-card-bd { padding: 16px 20px; }

            /* ===== Activity ===== */
            .id-act {
                display: flex; gap: 12px; padding: 12px 0;
                border-bottom: 1px solid #f5f5f5; align-items: flex-start;
            }
            .id-act:last-child { border-bottom: none; }
            .id-act-dot {
                width: 8px; height: 8px; border-radius: 50%;
                margin-top: 6px; flex-shrink: 0;
            }
            .id-act-dot.quiz { background: #1B4D3E; }
            .id-act-dot.lesson { background: #0D9488; }
            .id-act-body { flex: 1; min-width: 0; }
            .id-act-text { font-size: 13px; color: #374151; line-height: 1.4; }
            .id-act-text strong { color: #1f2937; font-weight: 600; }
            .id-act-meta {
                font-size: 11px; color: #9ca3af; margin-top: 3px;
                display: flex; gap: 8px; align-items: center;
            }
            .id-act-score {
                font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px;
            }
            .id-act-score.pass { background: #E8F5E9; color: #1B4D3E; }
            .id-act-score.fail { background: #FEE2E2; color: #b91c1c; }

            /* ===== Quiz Performance ===== */
            .id-perf { margin-bottom: 14px; }
            .id-perf:last-child { margin-bottom: 0; }
            .id-perf-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
            .id-perf-title { font-size: 13px; color: #374151; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 70%; }
            .id-perf-score { font-size: 13px; font-weight: 700; }
            .id-perf-score.good { color: #1B4D3E; }
            .id-perf-score.mid { color: #B45309; }
            .id-perf-score.low { color: #b91c1c; }
            .id-perf-bar { background: #f3f4f6; height: 8px; border-radius: 4px; overflow: hidden; }
            .id-perf-fill { height: 100%; border-radius: 4px; transition: width 0.6s ease; }
            .id-perf-fill.good { background: linear-gradient(90deg, #1B4D3E, #2D6A4F); }
            .id-perf-fill.mid { background: linear-gradient(90deg, #B45309, #f59e0b); }
            .id-perf-fill.low { background: linear-gradient(90deg, #b91c1c, #ef4444); }
            .id-perf-meta { font-size: 11px; color: #9ca3af; margin-top: 3px; }

            /* ===== At Risk ===== */
            .id-risk {
                display: flex; justify-content: space-between; align-items: center;
                padding: 12px 0; border-bottom: 1px solid #f5f5f5;
            }
            .id-risk:last-child { border-bottom: none; }
            .id-risk-info { display: flex; align-items: center; gap: 10px; }
            .id-risk-av {
                width: 32px; height: 32px; border-radius: 8px;
                background: #FEE2E2; color: #b91c1c;
                display: flex; align-items: center; justify-content: center;
                font-size: 11px; font-weight: 700; flex-shrink: 0;
            }
            .id-risk-nm { font-size: 13px; color: #374151; font-weight: 500; }
            .id-risk-sub { font-size: 11px; color: #9ca3af; }
            .id-risk-score { font-size: 15px; font-weight: 800; color: #b91c1c; }

            /* ===== Classes ===== */
            .id-class {
                display: flex; justify-content: space-between; align-items: center;
                padding: 12px 0; border-bottom: 1px solid #f5f5f5;
            }
            .id-class:last-child { border-bottom: none; }
            .id-class-left { display: flex; align-items: center; gap: 10px; }
            .id-class-code {
                background: #E8F5E9; color: #1B4D3E; padding: 4px 10px;
                border-radius: 6px; font-size: 11px; font-weight: 700;
                font-family: 'Consolas','Monaco',monospace;
            }
            .id-class-name { font-size: 13px; color: #374151; font-weight: 500; }
            .id-class-sec { font-size: 11px; color: #9ca3af; }
            .id-class-right { display: flex; gap: 16px; text-align: center; }
            .id-class-num { font-size: 16px; font-weight: 700; color: #1B4D3E; }
            .id-class-lbl { font-size: 10px; color: #9ca3af; text-transform: uppercase; }

            /* ===== Empty ===== */
            .id-empty { text-align: center; padding: 28px 16px; color: #9ca3af; font-size: 13px; }

            /* ===== Responsive ===== */
            @media(max-width:1100px) {
                .id-stats { grid-template-columns: repeat(2, 1fr); }
                .id-actions { grid-template-columns: repeat(2, 1fr); }
                .id-grid { grid-template-columns: 1fr; }
            }
            @media(max-width:640px) {
                .id-stats { grid-template-columns: 1fr; }
                .id-actions { grid-template-columns: 1fr; }
            }
        </style>

        <!-- Banner -->
        <div class="id-banner">
            <h2>${greeting}, ${esc(user.first_name)}</h2>
            <p>${today}</p>
        </div>

        <!-- Stats -->
        <div class="id-stats">
            ${stat('#E8F5E9', '#1B4D3E', '&#128218;', stats.classes || 0, 'My Classes')}
            ${stat('#DBEAFE', '#1E40AF', '&#128101;', stats.students || 0, 'Total Students')}
            ${stat('#FEF3C7', '#92400E', '&#128196;', stats.lessons || 0, 'Lessons Created')}
            ${stat('#EDE9FE', '#5B21B6', '&#128221;', stats.quizzes || 0, 'Quizzes Created')}
        </div>

        <!-- Quick Actions -->
        <div class="id-actions">
            <a href="#instructor/lessons" class="id-action">
                <div class="id-action-icon" style="background:#E8F5E9;color:#1B4D3E">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                </div>
                <div>
                    <div class="id-action-text">Manage Lessons</div>
                    <div class="id-action-sub">Create and organize content</div>
                </div>
            </a>
            <a href="#instructor/quizzes" class="id-action">
                <div class="id-action-icon" style="background:#FEF3C7;color:#92400E">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>
                </div>
                <div>
                    <div class="id-action-text">Manage Quizzes</div>
                    <div class="id-action-sub">Create and review assessments</div>
                </div>
            </a>
            <a href="#instructor/quiz-ai-generate" class="id-action">
                <div class="id-action-icon" style="background:#DBEAFE;color:#1E40AF">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z"/></svg>
                </div>
                <div>
                    <div class="id-action-text">AI Quiz Generator</div>
                    <div class="id-action-sub">Auto-generate from lessons</div>
                </div>
            </a>
            <a href="#instructor/students" class="id-action">
                <div class="id-action-icon" style="background:#EDE9FE;color:#5B21B6">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                </div>
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
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Recent Activity
                    </h3>
                    <span class="id-cnt">${activity.length} recent</span>
                </div>
                <div class="id-card-bd">
                    ${activity.length === 0 ? '<div class="id-empty">No recent student activity yet</div>' :
                    activity.map(a => `
                        <div class="id-act">
                            <div class="id-act-dot ${a.type}"></div>
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
                        </div>
                    `).join('')}
                </div>
            </div>

            <div class="id-card">
                <div class="id-card-hd">
                    <h3>
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342"/></svg>
                        My Classes
                    </h3>
                    <span class="id-cnt">${classes.length}</span>
                </div>
                <div class="id-card-bd">
                    ${classes.length === 0 ? '<div class="id-empty">No classes assigned yet</div>' :
                    classes.map(c => `
                        <div class="id-class">
                            <div class="id-class-left">
                                <span class="id-class-code">${esc(c.subject_code)}</span>
                                <div>
                                    <div class="id-class-name">${esc(c.subject_name)}</div>
                                    <div class="id-class-sec">${esc(c.section_name || 'No section')}</div>
                                </div>
                            </div>
                            <div class="id-class-right">
                                <div>
                                    <div class="id-class-num">${c.student_count}</div>
                                    <div class="id-class-lbl">Students</div>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>

        <!-- Row 2: Quiz Performance + At-Risk Students -->
        <div class="id-grid id-grid-eq">
            <div class="id-card">
                <div class="id-card-hd">
                    <h3>
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                        Quiz Performance
                    </h3>
                </div>
                <div class="id-card-bd">
                    ${quizPerf.length === 0 ? '<div class="id-empty">No quiz data yet. Create quizzes and have students take them.</div>' :
                    quizPerf.map(q => {
                        const score = parseFloat(q.avg_score) || 0;
                        const level = score >= 75 ? 'good' : score >= 50 ? 'mid' : 'low';
                        const passRate = q.attempts > 0 ? Math.round((q.passed_count / q.attempts) * 100) : 0;
                        return `
                        <div class="id-perf">
                            <div class="id-perf-top">
                                <span class="id-perf-title">${esc(q.quiz_title)}</span>
                                <span class="id-perf-score ${level}">${score}%</span>
                            </div>
                            <div class="id-perf-bar"><div class="id-perf-fill ${level}" style="width:${Math.max(3, score)}%"></div></div>
                            <div class="id-perf-meta">${esc(q.subject_code)} &middot; ${q.attempts} attempts &middot; ${passRate}% pass rate</div>
                        </div>`;
                    }).join('')}
                </div>
            </div>

            <div class="id-card">
                <div class="id-card-hd">
                    <h3>
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        At-Risk Students
                    </h3>
                    ${stats.pending_remedials > 0 ? `<span class="id-cnt" style="background:#FEE2E2;color:#b91c1c">${stats.pending_remedials} remedial</span>` : ''}
                </div>
                <div class="id-card-bd">
                    ${atRisk.length === 0 ? '<div class="id-empty">All students are performing well!</div>' :
                    atRisk.map(s => {
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

function stat(bg, color, icon, value, label) {
    return `
    <div class="id-stat">
        <div class="id-stat-icon" style="background:${bg};color:${color}">${icon}</div>
        <div>
            <div class="id-stat-val">${value}</div>
            <div class="id-stat-lbl">${label}</div>
        </div>
    </div>`;
}

function timeAgo(dateStr) {
    if (!dateStr) return '';
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    return new Date(dateStr).toLocaleDateString();
}

function esc(str) { const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }
