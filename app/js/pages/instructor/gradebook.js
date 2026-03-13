/**
 * Instructor Gradebook Page
 * View quiz scores per subject/section with pass/fail tracking
 */
import { Api } from '../../api.js';

let subjects = [];

export async function render(container) {
    const subjRes = await Api.get('/LessonsAPI.php?action=subjects');
    subjects = subjRes.success ? subjRes.data : [];
    renderPage(container);
}

async function renderPage(container, filterSubject = '') {
    container.innerHTML = `
        <style>
            .gb-banner { background:linear-gradient(135deg,#1B4D3E 0%,#2D6A4F 60%,#40916C 100%); border-radius:16px; padding:28px 32px; margin-bottom:24px; position:relative; overflow:hidden; }
            .gb-banner::before { content:''; position:absolute; top:-40px; right:-40px; width:180px; height:180px; border-radius:50%; background:rgba(255,255,255,.07); pointer-events:none; }
            .gb-banner::after { content:''; position:absolute; bottom:-60px; left:60px; width:220px; height:220px; border-radius:50%; background:rgba(255,255,255,.05); pointer-events:none; }
            .gb-banner-inner { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; position:relative; z-index:1; }
            .gb-banner-title { font-size:26px; font-weight:800; color:#fff; margin:0 0 4px; }
            .gb-banner-sub { font-size:14px; color:rgba(255,255,255,.75); margin:0; }
            .gb-banner-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
            .gb-action-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; background:rgba(255,255,255,.15); color:#fff; border:1px solid rgba(255,255,255,.25); border-radius:10px; font-size:12px; font-weight:600; text-decoration:none; transition:all .15s; white-space:nowrap; }
            .gb-action-btn:hover { background:rgba(255,255,255,.25); }

            .gb-filter-bar { display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
            .gb-filter-bar select, .gb-filter-bar input { padding:9px 14px; border:1px solid #e8ecef; border-radius:10px; font-size:13px; min-width:220px; background:#fff; color:#374151; outline:none; transition:border-color .15s; box-shadow:0 1px 2px rgba(0,0,0,.04); }
            .gb-filter-bar select:focus, .gb-filter-bar input:focus { border-color:#1B4D3E; box-shadow:0 0 0 3px rgba(27,77,62,.08); }

            .grade-summary { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:14px; margin-bottom:24px; }
            .gs-card { background:#fff; border:1px solid #f1f5f9; border-radius:12px; padding:18px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,.07); }
            .gs-num { font-size:26px; font-weight:800; color:#1B4D3E; }
            .gs-label { font-size:12px; color:#9ca3af; margin-top:4px; }

            .quiz-grade-section { margin-bottom:28px; }
            .quiz-grade-header { display:flex; align-items:center; gap:12px; margin-bottom:12px; padding-bottom:10px; border-bottom:2px solid #f1f5f9; }
            .qg-title { font-size:16px; font-weight:700; color:#111827; }
            .qg-badge { background:#E8F5E9; color:#1B4D3E; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:600; }

            .data-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; border:1px solid #f1f5f9; box-shadow:0 1px 3px rgba(0,0,0,.07); }
            .data-table th { text-align:left; padding:12px 16px; font-size:12px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.5px; background:#fafbfc; border-bottom:1px solid #f1f5f9; }
            .data-table td { padding:13px 16px; border-bottom:1px solid #f8fafc; font-size:14px; }
            .data-table tr:last-child td { border-bottom:none; }
            .data-table tr:hover td { background:#f9fffe; }

            .badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; }
            .badge-passed { background:#dcfce7; color:#16a34a; }
            .badge-failed { background:#FEE2E2; color:#b91c1c; }
            .badge-pending { background:#FEF3C7; color:#B45309; }

            .score-bar { background:#e2e8f0; height:6px; border-radius:3px; overflow:hidden; width:80px; display:inline-block; vertical-align:middle; margin-right:6px; }
            .score-fill { height:100%; border-radius:3px; }
            .score-fill.high { background:linear-gradient(90deg,#1B4D3E,#2D6A4F); }
            .score-fill.medium { background:linear-gradient(90deg,#B45309,#f59e0b); }
            .score-fill.low { background:linear-gradient(90deg,#b91c1c,#ef4444); }

            .empty-state-sm { text-align:center; padding:40px; color:#9ca3af; }

            /* Collapsible student rows */
            .student-summary-row { cursor:pointer; }
            .student-summary-row:hover td { background:#f0fdf4 !important; }
            .student-summary-row td:first-child { display:flex; align-items:center; gap:8px; }
            .expand-icon { font-size:11px; color:#9ca3af; transition:transform .2s; flex-shrink:0; }
            .expand-icon.open { transform:rotate(90deg); }
            .attempts-detail-row { display:none; }
            .attempts-detail-row.open { display:table-row; }
            .attempts-detail-row td { padding:0; border-bottom:1px solid #f1f5f9; }
            .attempts-inner { background:#f9fffe; border-left:3px solid #1B4D3E; }
            .attempts-inner table { width:100%; border-collapse:collapse; }
            .attempts-inner th { padding:8px 16px 8px 24px; font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:.5px; background:#f0fdf4; }
            .attempts-inner td { padding:9px 16px 9px 24px; font-size:13px; border-bottom:1px solid #f0fdf4; }
            .attempts-inner tr:last-child td { border-bottom:none; }
            .attempts-count-badge { background:#E8F5E9; color:#1B4D3E; padding:2px 7px; border-radius:10px; font-size:11px; font-weight:700; }

            /* Charts */
            .gb-charts-row { display:grid; grid-template-columns:220px 1fr 210px; gap:16px; margin-bottom:28px; }
            .gb-chart-card { background:#fff; border:1px solid #f1f5f9; border-radius:14px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); }
            .gb-chart-header { padding:14px 18px; font-size:13px; font-weight:700; color:#374151; border-bottom:1px solid #f1f5f9; background:#fafbfc; letter-spacing:.01em; }
            .gb-chart-body { padding:20px; }
            .gb-bar-row { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
            .gb-bar-row:last-child { margin-bottom:0; }
            .gb-bar-label { font-size:12px; font-weight:600; color:#374151; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:80px; max-width:130px; }
            .gb-bar-track { flex:1; background:#e2e8f0; height:8px; border-radius:4px; overflow:hidden; }
            .gb-bar-fill { height:100%; border-radius:4px; transition:width .6s cubic-bezier(.4,0,.2,1); }
            .gb-bar-fill.green { background:linear-gradient(90deg,#1B4D3E,#40916C); }
            .gb-bar-fill.yellow { background:linear-gradient(90deg,#B45309,#f59e0b); }
            .gb-bar-fill.red { background:linear-gradient(90deg,#b91c1c,#ef4444); }
            .gb-bar-val { font-size:12px; font-weight:700; color:#111827; min-width:36px; text-align:right; }
            .gb-dist-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
            .gb-dist-row:last-child { margin-bottom:0; }
            .gb-dist-label { font-size:11px; color:#374151; font-weight:600; min-width:60px; }
            .gb-dist-band { display:inline-block; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:700; margin-bottom:3px; }
            .gb-dist-track { flex:1; background:#e2e8f0; height:10px; border-radius:5px; overflow:hidden; }
            .gb-dist-count { font-size:12px; font-weight:700; color:#111827; min-width:20px; text-align:right; }
            /* Stat card icons */
            .gs-icon { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; }
            @media(max-width:960px) { .gb-charts-row { grid-template-columns:1fr 1fr; } }
            @media(max-width:600px) { .gb-charts-row { grid-template-columns:1fr; } }
            @media(max-width:768px) { .grade-summary { grid-template-columns:1fr 1fr; } .data-table { font-size:13px; } }
        </style>

        <div class="gb-banner">
            <div class="gb-banner-inner">
                <div>
                    <h2 class="gb-banner-title">Gradebook</h2>
                    <p class="gb-banner-sub">Track student quiz scores and performance</p>
                </div>
                <div class="gb-banner-actions">
                    <a href="#instructor/analytics" class="gb-action-btn">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                        Analytics
                    </a>
                    <a href="#instructor/remedials" class="gb-action-btn">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                        Remedials
                    </a>
                    <a href="#instructor/messages" class="gb-action-btn">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>
                        Messages
                    </a>
                    <a href="#instructor/essay-grading" class="gb-action-btn">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/></svg>
                        Subjective
                    </a>
                </div>
            </div>
        </div>
        <div class="gb-filter-bar">
            <select id="filter-subject">
                <option value="">All Subjects</option>
                ${subjects.map(s => `<option value="${s.subject_id}" ${filterSubject==s.subject_id?'selected':''}>${esc(s.subject_code)} - ${esc(s.subject_name)}</option>`).join('')}
            </select>
            <input type="text" id="search" placeholder="Search student name...">
        </div>
        <div id="gradebook-content"><div style="text-align:center;padding:40px;color:#737373">Loading grades...</div></div>
    `;

    container.querySelector('#filter-subject').addEventListener('change', (e) => renderPage(container, e.target.value));

    // Load data
    const params = filterSubject ? '&subject_id=' + filterSubject : '';
    const [quizRes, studentsRes] = await Promise.all([
        Api.get('/QuizzesAPI.php?action=instructor-list' + params),
        Api.get('/LessonsAPI.php?action=students' + params)
    ]);
    const quizzes = quizRes.success ? quizRes.data : [];
    const studentsData = studentsRes.success ? studentsRes.data : [];

    // Flatten students from grouped data
    const allStudents = [];
    if (Array.isArray(studentsData)) {
        studentsData.forEach(group => {
            if (group.students) group.students.forEach(s => {
                s._subject_code = group.subject_code;
                s._subject_name = group.subject_name;
                allStudents.push(s);
            });
        });
    }

    // Fetch scores for each quiz
    const scorePromises = quizzes.map(q =>
        Api.get('/QuizAttemptsAPI.php?action=quiz-scores&quiz_id=' + q.quiz_id)
            .then(r => ({ quiz: q, scores: r.success ? r.data : [] }))
            .catch(() => ({ quiz: q, scores: [] }))
    );
    const quizScores = await Promise.all(scorePromises);

    const content = container.querySelector('#gradebook-content');

    // Build summary stats
    let totalAttempts = 0, totalPassed = 0, totalFailed = 0, scoreSum = 0;
    quizScores.forEach(qs => {
        qs.scores.forEach(sc => {
            totalAttempts++;
            scoreSum += parseFloat(sc.percentage || 0);
            if (sc.passed == 1) totalPassed++;
            else totalFailed++;
        });
    });
    const avgScore = totalAttempts > 0 ? (scoreSum / totalAttempts).toFixed(1) : '—';

    // Chart data
    const quizStats = quizScores.map(qs => {
        const cnt = qs.scores.length;
        const ap = cnt > 0 ? (qs.scores.reduce((s, sc) => s + parseFloat(sc.percentage||0), 0) / cnt) : 0;
        return { title: qs.quiz.quiz_title, attempts: cnt, avgPct: parseFloat(ap.toFixed(1)) };
    }).filter(qs => qs.attempts > 0);

    const dist = { high: 0, mid: 0, low: 0 };
    quizScores.forEach(qs => qs.scores.forEach(sc => {
        const p = parseFloat(sc.percentage||0);
        if (p >= 75) dist.high++; else if (p >= 50) dist.mid++; else dist.low++;
    }));

    const circ = 282.74;
    const passArc = totalAttempts > 0 ? (totalPassed / totalAttempts) * circ : 0;
    const failArc = totalAttempts > 0 ? (totalFailed / totalAttempts) * circ : 0;
    const passRate = totalAttempts > 0 ? ((totalPassed / totalAttempts) * 100).toFixed(0) : 0;

    content.innerHTML = `
        <div class="grade-summary">
            <div class="gs-card">
                <div class="gs-icon" style="background:#e8f5e9;color:#1B4D3E;">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c.98 0 1.813.626 2.122 1.5"/></svg>
                </div>
                <div class="gs-num">${quizzes.length}</div><div class="gs-label">Quizzes</div>
            </div>
            <div class="gs-card">
                <div class="gs-icon" style="background:#dbeafe;color:#1e40af;">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                </div>
                <div class="gs-num">${totalAttempts}</div><div class="gs-label">Total Attempts</div>
            </div>
            <div class="gs-card">
                <div class="gs-icon" style="background:#dcfce7;color:#16a34a;">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="gs-num" style="color:#16a34a">${totalPassed}</div><div class="gs-label">Passed</div>
            </div>
            <div class="gs-card">
                <div class="gs-icon" style="background:#fee2e2;color:#b91c1c;">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="gs-num" style="color:#b91c1c">${totalFailed}</div><div class="gs-label">Failed</div>
            </div>
            <div class="gs-card">
                <div class="gs-icon" style="background:#fef3c7;color:#b45309;">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625z"/></svg>
                </div>
                <div class="gs-num" style="color:#b45309">${avgScore}${avgScore !== '—' ? '%' : ''}</div><div class="gs-label">Avg Score</div>
            </div>
        </div>

        ${totalAttempts > 0 ? `
        <div class="gb-charts-row">
            <!-- Donut: Pass vs Fail -->
            <div class="gb-chart-card">
                <div class="gb-chart-header">Pass vs Fail Rate</div>
                <div class="gb-chart-body" style="display:flex;flex-direction:column;align-items:center;gap:16px;">
                    <svg width="140" height="140" viewBox="0 0 120 120">
                        <circle cx="60" cy="60" r="45" fill="none" stroke="#f1f5f9" stroke-width="16"/>
                        <circle cx="60" cy="60" r="45" fill="none" stroke="#fca5a5" stroke-width="16"
                            stroke-dasharray="${failArc.toFixed(2)} ${circ}" stroke-dashoffset="${(-passArc).toFixed(2)}"
                            transform="rotate(-90 60 60)"/>
                        <circle cx="60" cy="60" r="45" fill="none" stroke="#1B4D3E" stroke-width="16"
                            stroke-dasharray="${passArc.toFixed(2)} ${circ}"
                            transform="rotate(-90 60 60)"/>
                        <text x="60" y="55" text-anchor="middle" font-size="20" font-weight="800" fill="#111827">${passRate}%</text>
                        <text x="60" y="70" text-anchor="middle" font-size="9" fill="#9ca3af">Pass Rate</text>
                    </svg>
                    <div style="width:100%">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                            <div style="width:10px;height:10px;border-radius:3px;background:#1B4D3E;flex-shrink:0;"></div>
                            <span style="font-size:13px;color:#374151;flex:1;">Passed</span>
                            <span style="font-size:16px;font-weight:800;color:#1B4D3E;">${totalPassed}</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:10px;height:10px;border-radius:3px;background:#fca5a5;flex-shrink:0;"></div>
                            <span style="font-size:13px;color:#374151;flex:1;">Failed</span>
                            <span style="font-size:16px;font-weight:800;color:#b91c1c;">${totalFailed}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bar: Quiz Avg Scores -->
            <div class="gb-chart-card">
                <div class="gb-chart-header">Quiz Avg Scores</div>
                <div class="gb-chart-body">
                    ${quizStats.length === 0
                        ? '<div style="text-align:center;padding:24px;color:#9ca3af;font-size:13px;">No attempts yet</div>'
                        : quizStats.map(qs => `
                            <div class="gb-bar-row">
                                <div class="gb-bar-label" title="${esc(qs.title)}">${esc(qs.title.length > 22 ? qs.title.substring(0,22)+'…' : qs.title)}</div>
                                <div class="gb-bar-track">
                                    <div class="gb-bar-fill ${qs.avgPct >= 75 ? 'green' : qs.avgPct >= 50 ? 'yellow' : 'red'}" style="width:${qs.avgPct}%"></div>
                                </div>
                                <div class="gb-bar-val">${qs.avgPct}%</div>
                            </div>`).join('')}
                </div>
            </div>

            <!-- Score Distribution -->
            <div class="gb-chart-card">
                <div class="gb-chart-header">Score Distribution</div>
                <div class="gb-chart-body">
                    <div class="gb-dist-row">
                        <div style="min-width:64px;">
                            <div class="gb-dist-band" style="background:#dcfce7;color:#16a34a;">High</div>
                            <div style="font-size:10px;color:#9ca3af;">75–100%</div>
                        </div>
                        <div class="gb-dist-track">
                            <div style="height:100%;border-radius:5px;background:linear-gradient(90deg,#1B4D3E,#40916C);width:${totalAttempts > 0 ? ((dist.high/totalAttempts)*100).toFixed(0) : 0}%;transition:width .6s;"></div>
                        </div>
                        <div class="gb-dist-count">${dist.high}</div>
                    </div>
                    <div class="gb-dist-row">
                        <div style="min-width:64px;">
                            <div class="gb-dist-band" style="background:#FEF3C7;color:#B45309;">Mid</div>
                            <div style="font-size:10px;color:#9ca3af;">50–74%</div>
                        </div>
                        <div class="gb-dist-track">
                            <div style="height:100%;border-radius:5px;background:linear-gradient(90deg,#B45309,#f59e0b);width:${totalAttempts > 0 ? ((dist.mid/totalAttempts)*100).toFixed(0) : 0}%;transition:width .6s;"></div>
                        </div>
                        <div class="gb-dist-count">${dist.mid}</div>
                    </div>
                    <div class="gb-dist-row">
                        <div style="min-width:64px;">
                            <div class="gb-dist-band" style="background:#FEE2E2;color:#b91c1c;">Low</div>
                            <div style="font-size:10px;color:#9ca3af;">0–49%</div>
                        </div>
                        <div class="gb-dist-track">
                            <div style="height:100%;border-radius:5px;background:linear-gradient(90deg,#b91c1c,#ef4444);width:${totalAttempts > 0 ? ((dist.low/totalAttempts)*100).toFixed(0) : 0}%;transition:width .6s;"></div>
                        </div>
                        <div class="gb-dist-count">${dist.low}</div>
                    </div>
                </div>
            </div>
        </div>
        ` : ''}
        ${quizScores.length === 0 ? '<div class="empty-state-sm">No quizzes found. Create quizzes first to see grades.</div>' :
          quizScores.map(qs => {
            const q = qs.quiz;
            const scores = qs.scores;
            return `
            <div class="quiz-grade-section">
                <div class="quiz-grade-header">
                    <span class="qg-title">${esc(q.quiz_title)}</span>
                    <span class="qg-badge">${esc(q.subject_code)} · ${scores.length} attempt(s)</span>
                </div>
                ${scores.length === 0 ? '<div style="padding:20px;color:#9ca3af;font-size:14px;background:#fff;border:1px solid #f1f5f9;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.06);text-align:center;">No attempts yet</div>' : (() => {
                    // Group attempts by student
                    const byStudent = new Map();
                    scores.forEach(sc => {
                        const key = sc.user_student_id || (sc.first_name + '_' + sc.last_name);
                        if (!byStudent.has(key)) byStudent.set(key, { first_name: sc.first_name, last_name: sc.last_name, name: (sc.first_name+' '+sc.last_name).toLowerCase(), attempts: [] });
                        byStudent.get(key).attempts.push(sc);
                    });

                    let rows = '';
                    let sid = 0;
                    for (const [, st] of byStudent) {
                        sid++;
                        // Best attempt = highest percentage
                        const best = st.attempts.reduce((a, b) => parseFloat(a.percentage) >= parseFloat(b.percentage) ? a : b);
                        const passed = st.attempts.some(a => a.passed == 1);
                        const pct = parseFloat(best.percentage || 0);
                        const cls = pct >= 80 ? 'high' : pct >= 50 ? 'medium' : 'low';
                        const detailId = `attempts-${q.quiz_id}-${sid}`;

                        rows += `
                        <tr class="student-summary-row student-row" data-name="${esc(st.name)}" data-target="${detailId}">
                            <td style="font-weight:600">
                                <span class="expand-icon">▶</span>
                                ${esc(st.first_name)} ${esc(st.last_name)}
                            </td>
                            <td>${best.earned_points}/${best.total_points} <span style="color:#9ca3af;font-size:11px">best</span></td>
                            <td>
                                <div class="score-bar"><div class="score-fill ${cls}" style="width:${pct}%"></div></div>
                                <span style="font-weight:600">${pct.toFixed(1)}%</span>
                            </td>
                            <td><span class="badge badge-${passed?'passed':'failed'}">${passed?'Passed':'Failed'}</span></td>
                            <td><span class="attempts-count-badge">${st.attempts.length} attempt${st.attempts.length!==1?'s':''}</span></td>
                            <td style="color:#737373">${best.completed_at ? new Date(best.completed_at).toLocaleDateString('en-US',{month:'short',day:'numeric'}) : '—'}</td>
                        </tr>
                        <tr class="attempts-detail-row" id="${detailId}">
                            <td colspan="6">
                                <div class="attempts-inner">
                                    <table>
                                        <thead><tr><th>#</th><th>Score</th><th>Percentage</th><th>Status</th><th>Date</th></tr></thead>
                                        <tbody>
                                            ${st.attempts.map(a => {
                                                const ap = parseFloat(a.percentage||0);
                                                const ac = ap>=80?'high':ap>=50?'medium':'low';
                                                return `<tr>
                                                    <td style="color:#737373">Attempt #${a.attempt_number||1}</td>
                                                    <td>${a.earned_points}/${a.total_points}</td>
                                                    <td><div class="score-bar"><div class="score-fill ${ac}" style="width:${ap}%"></div></div><span style="font-weight:600">${ap.toFixed(1)}%</span></td>
                                                    <td><span class="badge badge-${a.passed==1?'passed':'failed'}">${a.passed==1?'Passed':'Failed'}</span></td>
                                                    <td style="color:#737373">${a.completed_at?new Date(a.completed_at).toLocaleDateString('en-US',{month:'short',day:'numeric'}):'—'}</td>
                                                </tr>`;
                                            }).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>`;
                    }
                    return `<table class="data-table">
                        <thead><tr><th>Student</th><th>Best Score</th><th>Percentage</th><th>Status</th><th>Attempts</th><th>Date</th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table>`;
                })()}
            </div>`;
          }).join('')}
    `;

    // Expand/collapse student attempts on row click
    content.querySelectorAll('.student-summary-row').forEach(row => {
        row.addEventListener('click', () => {
            const detailRow = content.querySelector('#' + row.dataset.target);
            const icon      = row.querySelector('.expand-icon');
            if (!detailRow) return;
            const isOpen = detailRow.classList.toggle('open');
            icon.classList.toggle('open', isOpen);
        });
    });

    // Search filter — only affects summary rows
    container.querySelector('#search').addEventListener('input', (e) => {
        const q = e.target.value.toLowerCase();
        content.querySelectorAll('.student-summary-row').forEach(row => {
            const show = row.dataset.name.includes(q);
            row.style.display = show ? '' : 'none';
            // Also hide detail row when student is hidden
            const detailRow = content.querySelector('#' + row.dataset.target);
            if (detailRow && !show) detailRow.classList.remove('open');
        });
    });
}

function esc(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
