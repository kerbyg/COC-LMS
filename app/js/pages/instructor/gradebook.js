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

async function renderPage(container, filterSubject = '', searchVal = '') {
    container.innerHTML = `
        <style>
            /* ── Grade color system ── */
            :root {
                --grade-a:  #059669; --grade-a-bg: #d1fae5;
                --grade-b:  #0284c7; --grade-b-bg: #dbeafe;
                --grade-c:  #b45309; --grade-c-bg: #fef3c7;
                --grade-d:  #ea580c; --grade-d-bg: #ffedd5;
                --grade-f:  #dc2626; --grade-f-bg: #fee2e2;
                --grade-p:  #6b7280; --grade-p-bg: #f3f4f6;
            }

            /* ── Banner ── */
            .gb-banner {
                background: linear-gradient(135deg, #00461B 0%, #006428 55%, #40916C 100%);
                border-radius: 18px; padding: 0; margin-bottom: 20px;
                position: relative; overflow: hidden;
                box-shadow: 0 4px 20px rgba(0,70,27,.2);
            }
            .gb-banner::before {
                content:''; position:absolute; top:-60px; right:-60px;
                width:240px; height:240px; border-radius:50%;
                background:rgba(255,255,255,.06); pointer-events:none;
            }
            .gb-banner::after {
                content:''; position:absolute; bottom:-80px; left:80px;
                width:280px; height:280px; border-radius:50%;
                background:rgba(255,255,255,.04); pointer-events:none;
            }
            .gb-banner-top {
                display:flex; align-items:center; justify-content:space-between;
                padding: 24px 28px 20px; gap:16px; flex-wrap:wrap; position:relative; z-index:1;
            }
            .gb-banner-title { font-size:26px; font-weight:800; color:#fff; margin:0 0 3px; letter-spacing:-.3px; }
            .gb-banner-sub   { font-size:13px; color:rgba(255,255,255,.7); margin:0; }
            .gb-banner-btns  { display:flex; gap:8px; flex-wrap:wrap; }
            .gb-btn {
                display:inline-flex; align-items:center; gap:6px;
                padding:8px 14px; border-radius:9px; font-size:12px; font-weight:600;
                border: 1px solid rgba(255,255,255,.22);
                background:rgba(255,255,255,.12); color:#fff;
                text-decoration:none; transition:background .15s;
                cursor:pointer; white-space:nowrap;
            }
            .gb-btn:hover { background:rgba(255,255,255,.22); }

            /* Stats strip inside banner */
            .gb-stats-strip {
                display:grid; grid-template-columns:repeat(5,1fr);
                border-top:1px solid rgba(255,255,255,.12);
                position:relative; z-index:1;
            }
            .gb-stat-pill {
                display:flex; flex-direction:column; align-items:center;
                padding:14px 8px; border-right:1px solid rgba(255,255,255,.1);
                text-align:center;
            }
            .gb-stat-pill:last-child { border-right:none; }
            .gb-stat-val {
                font-size:22px; font-weight:800; color:#fff; line-height:1;
                margin-bottom:3px;
            }
            .gb-stat-lbl {
                font-size:10px; font-weight:600; color:rgba(255,255,255,.6);
                text-transform:uppercase; letter-spacing:.6px;
            }

            /* ── Filter bar ── */
            .gb-filter {
                display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; align-items:center;
            }
            .gb-filter select, .gb-filter input {
                padding:9px 14px; border:1.5px solid #e8e8e8; border-radius:10px;
                font-size:13px; font-family:inherit; color:#374151; background:#fff;
                outline:none; transition:border-color .15s, box-shadow .15s;
                box-shadow:0 1px 2px rgba(0,0,0,.04);
            }
            .gb-filter select { min-width:220px; }
            .gb-filter input  { flex:1; min-width:200px; }
            .gb-filter select:focus, .gb-filter input:focus {
                border-color:#00461B; box-shadow:0 0 0 3px rgba(0,70,27,.08);
            }
            .gb-filter-icon { color:#9ca3af; display:flex; align-items:center; }

            /* ── Charts row ── */
            .gb-charts {
                display:grid; grid-template-columns:200px 1fr 200px;
                gap:14px; margin-bottom:24px;
            }
            .gb-chart-card {
                background:#fff; border:1px solid #e8e8e8; border-radius:14px;
                overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.06);
            }
            .gb-chart-head {
                padding:12px 16px; font-size:12px; font-weight:700; color:#374151;
                border-bottom:1px solid #f3f4f6; background:#fafbfc;
                text-transform:uppercase; letter-spacing:.5px;
            }
            .gb-chart-body { padding:16px; }

            /* Donut chart */
            .gb-donut-wrap {
                display:flex; flex-direction:column; align-items:center; gap:14px;
            }
            .gb-donut-svg-wrap { position:relative; width:120px; height:120px; flex-shrink:0; }
            .gb-donut-svg-wrap svg { transform:rotate(-90deg); }
            .gb-donut-center {
                position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
                text-align:center; pointer-events:none;
            }
            .gb-donut-pct { font-size:22px; font-weight:800; color:#00461B; line-height:1; }
            .gb-donut-lbl { font-size:10px; color:#9ca3af; font-weight:600; letter-spacing:.3px; }
            .gb-donut-legend { display:flex; flex-direction:column; gap:7px; width:100%; }
            .gb-legend-row { display:flex; align-items:center; gap:8px; }
            .gb-legend-dot { width:9px; height:9px; border-radius:3px; flex-shrink:0; }
            .gb-legend-txt { font-size:11px; color:#6b7280; flex:1; }
            .gb-legend-num { font-size:12px; font-weight:700; color:#111827; }

            /* Score bars chart */
            .gb-bar-row { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
            .gb-bar-row:last-child { margin-bottom:0; }
            .gb-bar-label {
                font-size:11px; color:#374151; font-weight:500;
                white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
                min-width:70px; max-width:120px;
            }
            .gb-bar-track { flex:1; background:#f1f5f9; height:8px; border-radius:4px; overflow:hidden; }
            .gb-bar-fill  { height:100%; border-radius:4px; transition:width .5s cubic-bezier(.4,0,.2,1); }
            .gb-bar-pct   { font-size:11px; font-weight:700; color:#374151; min-width:32px; text-align:right; }

            /* Distribution */
            .gb-dist-row  { display:flex; align-items:center; gap:8px; margin-bottom:12px; }
            .gb-dist-row:last-child { margin-bottom:0; }
            .gb-dist-chip {
                font-size:10px; font-weight:700; padding:2px 7px; border-radius:4px;
                min-width:38px; text-align:center; flex-shrink:0;
            }
            .gb-dist-track { flex:1; background:#f1f5f9; height:10px; border-radius:5px; overflow:hidden; }
            .gb-dist-fill  { height:100%; border-radius:5px; transition:width .5s; }
            .gb-dist-cnt   { font-size:12px; font-weight:700; color:#111827; min-width:22px; text-align:right; }

            /* ── Quiz section header ── */
            .quiz-section { margin-bottom:24px; }
            .quiz-section-hd {
                display:flex; align-items:center; gap:10px;
                margin-bottom:12px; padding:14px 18px;
                background:#fff; border:1px solid #e8e8e8; border-radius:12px 12px 0 0;
                border-bottom:2px solid #00461B;
            }
            .qs-icon {
                width:36px; height:36px; background:#e8f5e9; border-radius:10px;
                display:flex; align-items:center; justify-content:center; flex-shrink:0;
            }
            .qs-title { font-size:15px; font-weight:700; color:#111827; flex:1; }
            .qs-meta  { font-size:12px; color:#6b7280; display:flex; gap:10px; flex-wrap:wrap; }
            .qs-chip  {
                background:#f3f4f6; color:#374151; padding:3px 9px;
                border-radius:20px; font-size:11px; font-weight:600;
            }
            .qs-chip.green { background:#d1fae5; color:#059669; }
            .qs-chip.blue  { background:#dbeafe; color:#1e40af; }

            /* ── Student table ── */
            .gb-table-wrap {
                background:#fff; border:1px solid #e8e8e8; border-radius:0 0 12px 12px;
                overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.06);
            }
            .gb-table { width:100%; border-collapse:collapse; }
            .gb-table thead tr {
                background:#fafbfc; border-bottom:1px solid #e8e8e8;
            }
            .gb-table th {
                padding:11px 16px; font-size:11px; font-weight:600; color:#9ca3af;
                text-transform:uppercase; letter-spacing:.5px; text-align:left;
            }
            .gb-table th:not(:first-child) { text-align:center; }

            /* Student row */
            .gb-student-row {
                cursor:pointer; transition:background .12s;
                border-bottom:1px solid #f3f4f6;
            }
            .gb-student-row:hover { background:#f9fffe; }
            .gb-student-row:last-of-type { border-bottom:none; }
            .gb-student-row td {
                padding:13px 16px; font-size:13px; vertical-align:middle;
            }
            .gb-student-row td:not(:first-child) { text-align:center; }

            /* Left colored border by grade */
            .gb-student-row td:first-child {
                border-left:4px solid transparent;
                display:flex; align-items:center; gap:9px;
            }
            .gb-student-row.grade-a td:first-child { border-left-color:#059669; }
            .gb-student-row.grade-b td:first-child { border-left-color:#0284c7; }
            .gb-student-row.grade-c td:first-child { border-left-color:#b45309; }
            .gb-student-row.grade-d td:first-child { border-left-color:#ea580c; }
            .gb-student-row.grade-f td:first-child { border-left-color:#dc2626; }

            .gb-expand-arrow {
                font-size:10px; color:#d1d5db; transition:transform .2s, color .15s;
                flex-shrink:0;
            }
            .gb-student-row.open .gb-expand-arrow { transform:rotate(90deg); color:#00461B; }
            .gb-student-name { font-weight:600; color:#111827; }
            .gb-student-id   { font-size:11px; color:#9ca3af; }

            /* Grade pill */
            .grade-pill {
                display:inline-flex; align-items:center; gap:4px;
                padding:4px 10px; border-radius:20px;
                font-size:11px; font-weight:700; letter-spacing:.2px;
            }
            .grade-pill.a { background:var(--grade-a-bg); color:var(--grade-a); }
            .grade-pill.b { background:var(--grade-b-bg); color:var(--grade-b); }
            .grade-pill.c { background:var(--grade-c-bg); color:var(--grade-c); }
            .grade-pill.d { background:var(--grade-d-bg); color:var(--grade-d); }
            .grade-pill.f { background:var(--grade-f-bg); color:var(--grade-f); }
            .grade-pill.p { background:var(--grade-p-bg); color:var(--grade-p); }

            /* Score bar inline */
            .score-inline { display:flex; align-items:center; gap:6px; justify-content:center; }
            .score-bar-sm { width:60px; height:5px; background:#e8e8e8; border-radius:3px; overflow:hidden; display:inline-block; }
            .score-bar-fill { height:100%; border-radius:3px; }
            .score-bar-fill.a { background:#059669; }
            .score-bar-fill.b { background:#0284c7; }
            .score-bar-fill.c { background:#b45309; }
            .score-bar-fill.d { background:#ea580c; }
            .score-bar-fill.f { background:#dc2626; }

            /* Attempt detail row */
            .gb-detail-row { display:none; }
            .gb-detail-row.open { display:table-row; }
            .gb-detail-inner {
                background:#f9fffe;
                border-left:4px solid #00461B;
                padding:14px 20px 14px 28px;
            }
            .gb-detail-table { width:100%; border-collapse:collapse; }
            .gb-detail-table th {
                padding:7px 12px; font-size:10px; font-weight:600; color:#9ca3af;
                text-transform:uppercase; letter-spacing:.5px; text-align:left;
                background:#f0fdf4; border-bottom:1px solid #d1fae5;
            }
            .gb-detail-table td {
                padding:9px 12px; font-size:12px; color:#374151;
                border-bottom:1px solid #f0faf4;
            }
            .gb-detail-table tr:last-child td { border-bottom:none; }
            .gb-detail-table tr:hover td { background:#f0fdf4; }
            .attempt-best { font-weight:700; color:#059669; font-size:10px; margin-left:6px; }

            /* Status badge */
            .status-badge {
                display:inline-flex; align-items:center; gap:3px;
                padding:3px 9px; border-radius:20px; font-size:11px; font-weight:600;
            }
            .status-badge.passed { background:#d1fae5; color:#059669; }
            .status-badge.failed { background:#fee2e2; color:#dc2626; }
            .status-badge.pending { background:#fef3c7; color:#b45309; }

            /* Empty state */
            .gb-empty {
                padding:48px 24px; text-align:center; color:#9ca3af;
                background:#fff; border:1px solid #e8e8e8; border-radius:0 0 12px 12px;
            }
            .gb-empty-icon { font-size:40px; margin-bottom:12px; }
            .gb-empty h4 { font-size:15px; font-weight:600; color:#374151; margin:0 0 6px; }
            .gb-empty p  { font-size:13px; margin:0; }

            @media(max-width:960px) { .gb-charts { grid-template-columns:1fr 1fr; } }
            @media(max-width:600px) {
                .gb-charts { grid-template-columns:1fr; }
                .gb-stats-strip { grid-template-columns:repeat(3,1fr); }
            }
        </style>

        <!-- ── Banner ── -->
        <div class="gb-banner">
            <div class="gb-banner-top">
                <div>
                    <h2 class="gb-banner-title">Gradebook</h2>
                    <p class="gb-banner-sub">Track student quiz scores and class performance</p>
                </div>
                <div class="gb-banner-btns">
                    <a href="#instructor/analytics" class="gb-btn">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                        Analytics
                    </a>
                    <a href="#instructor/essay-grading" class="gb-btn">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/></svg>
                        Essay Grading
                    </a>
                </div>
            </div>
            <!-- Stats strip — populated after data loads -->
            <div class="gb-stats-strip" id="gb-stats-strip">
                ${['—','—','—','—','—'].map((v,i) => `
                    <div class="gb-stat-pill">
                        <div class="gb-stat-val">${v}</div>
                        <div class="gb-stat-lbl">${['Quizzes','Attempts','Passed','Failed','Avg Score'][i]}</div>
                    </div>`).join('')}
            </div>
        </div>

        <!-- ── Filter bar ── -->
        <div class="gb-filter">
            <span class="gb-filter-icon">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/></svg>
            </span>
            <select id="filter-subject">
                <option value="">All Subjects</option>
                ${subjects.map(s => `<option value="${s.subject_id}" ${filterSubject == s.subject_id ? 'selected' : ''}>${esc(s.subject_code)} — ${esc(s.subject_name)}</option>`).join('')}
            </select>
            <input type="text" id="gb-search" placeholder="Search student name…" value="${esc(searchVal)}">
        </div>

        <!-- ── Charts (shown after data loads) ── -->
        <div id="gb-charts" style="display:none"></div>

        <!-- ── Grade sections ── -->
        <div id="gb-content">
            <div style="display:flex;justify-content:center;padding:48px">
                <div style="width:32px;height:32px;border:3px solid #e8e8e8;border-top-color:#00461B;border-radius:50%;animation:gb-spin .75s linear infinite"></div>
            </div>
        </div>
        <style>@keyframes gb-spin { to { transform:rotate(360deg); } }</style>
    `;

    // Events
    container.querySelector('#filter-subject').addEventListener('change', e => {
        renderPage(container, e.target.value, container.querySelector('#gb-search').value);
    });
    container.querySelector('#gb-search').addEventListener('input', e => {
        filterStudentRows(container, e.target.value);
    });

    // Load quiz data
    const params = filterSubject ? '&subject_id=' + filterSubject : '';
    const quizRes = await Api.get('/QuizzesAPI.php?action=instructor-list' + params);
    const quizzes = quizRes.success ? quizRes.data : [];

    const scoreResults = await Promise.all(
        quizzes.map(q =>
            Api.get('/QuizAttemptsAPI.php?action=quiz-scores&quiz_id=' + q.quiz_id)
               .then(r => ({ quiz: q, scores: r.success ? r.data : [] }))
               .catch(() => ({ quiz: q, scores: [] }))
        )
    );

    buildStats(container, quizzes, scoreResults);
    buildCharts(container, scoreResults);
    buildContent(container, scoreResults, searchVal);
}

/* ─── Stats strip ─────────────────────────────────────────── */
function buildStats(container, quizzes, scoreResults) {
    let attempts = 0, passed = 0, failed = 0, scoreSum = 0;
    scoreResults.forEach(sr => sr.scores.forEach(sc => {
        attempts++;
        scoreSum += parseFloat(sc.percentage || 0);
        if (sc.passed == 1) passed++; else failed++;
    }));
    const avg = attempts > 0 ? (scoreSum / attempts).toFixed(1) + '%' : '—';

    const strip = container.querySelector('#gb-stats-strip');
    const vals  = [quizzes.length, attempts, passed, failed, avg];
    const lbls  = ['Quizzes','Attempts','Passed','Failed','Avg Score'];
    strip.innerHTML = vals.map((v, i) => `
        <div class="gb-stat-pill">
            <div class="gb-stat-val">${v}</div>
            <div class="gb-stat-lbl">${lbls[i]}</div>
        </div>`).join('');
}

/* ─── Charts ──────────────────────────────────────────────── */
function buildCharts(container, scoreResults) {
    let passed = 0, failed = 0;
    const dist = { a: 0, b: 0, c: 0, d: 0, f: 0 };
    const quizStats = [];

    scoreResults.forEach(sr => {
        const cnt = sr.scores.length;
        if (!cnt) return;
        let sum = 0;
        sr.scores.forEach(sc => {
            const p = parseFloat(sc.percentage || 0);
            sum += p;
            if (sc.passed == 1) passed++; else failed++;
            if (p >= 90) dist.a++; else if (p >= 80) dist.b++; else if (p >= 70) dist.c++; else if (p >= 60) dist.d++; else dist.f++;
        });
        quizStats.push({ title: sr.quiz.quiz_title, avg: parseFloat((sum / cnt).toFixed(1)), cnt });
    });

    const total  = passed + failed;
    if (!total) return;

    const circ    = 282.74;
    const passArc = total > 0 ? (passed / total) * circ : 0;
    const failArc = circ - passArc;
    const passRate = total > 0 ? ((passed / total) * 100).toFixed(0) : 0;
    const maxAvg   = Math.max(...quizStats.map(q => q.avg), 1);
    const maxDist  = Math.max(dist.a, dist.b, dist.c, dist.d, dist.f, 1);

    const distData = [
        { key:'a', label:'A  90–100%', color:'#059669', bg:'#d1fae5' },
        { key:'b', label:'B  80–89%',  color:'#0284c7', bg:'#dbeafe' },
        { key:'c', label:'C  70–79%',  color:'#b45309', bg:'#fef3c7' },
        { key:'d', label:'D  60–69%',  color:'#ea580c', bg:'#ffedd5' },
        { key:'f', label:'F  0–59%',   color:'#dc2626', bg:'#fee2e2' },
    ];

    container.querySelector('#gb-charts').style.display = '';
    container.querySelector('#gb-charts').innerHTML = `
        <div class="gb-charts">
            <!-- Donut: Pass vs Fail -->
            <div class="gb-chart-card">
                <div class="gb-chart-head">Pass vs Fail</div>
                <div class="gb-chart-body">
                    <div class="gb-donut-wrap">
                        <div class="gb-donut-svg-wrap">
                            <svg width="120" height="120" viewBox="0 0 120 120">
                                <circle cx="60" cy="60" r="45" fill="none" stroke="#f1f5f9" stroke-width="14"/>
                                <circle cx="60" cy="60" r="45" fill="none" stroke="#fee2e2" stroke-width="14"
                                    stroke-dasharray="${failArc.toFixed(2)} ${circ}"
                                    stroke-dashoffset="${(-passArc).toFixed(2)}"
                                    stroke-linecap="round"/>
                                <circle cx="60" cy="60" r="45" fill="none" stroke="#059669" stroke-width="14"
                                    stroke-dasharray="${passArc.toFixed(2)} ${circ}"
                                    stroke-linecap="round"/>
                            </svg>
                            <div class="gb-donut-center">
                                <div class="gb-donut-pct">${passRate}%</div>
                                <div class="gb-donut-lbl">Pass Rate</div>
                            </div>
                        </div>
                        <div class="gb-donut-legend">
                            <div class="gb-legend-row">
                                <div class="gb-legend-dot" style="background:#059669"></div>
                                <span class="gb-legend-txt">Passed</span>
                                <span class="gb-legend-num">${passed}</span>
                            </div>
                            <div class="gb-legend-row">
                                <div class="gb-legend-dot" style="background:#dc2626"></div>
                                <span class="gb-legend-txt">Failed</span>
                                <span class="gb-legend-num">${failed}</span>
                            </div>
                            <div class="gb-legend-row">
                                <div class="gb-legend-dot" style="background:#e8e8e8"></div>
                                <span class="gb-legend-txt">Total</span>
                                <span class="gb-legend-num">${total}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bar: Quiz Avg Scores -->
            <div class="gb-chart-card">
                <div class="gb-chart-head">Quiz Average Scores</div>
                <div class="gb-chart-body">
                    ${quizStats.length === 0
                        ? '<div style="text-align:center;padding:20px;color:#9ca3af;font-size:13px">No attempts yet</div>'
                        : quizStats.map(q => {
                            const cls = q.avg >= 90 ? 'a' : q.avg >= 80 ? 'b' : q.avg >= 70 ? 'c' : q.avg >= 60 ? 'd' : 'f';
                            const colors = { a:'#059669', b:'#0284c7', c:'#b45309', d:'#ea580c', f:'#dc2626' };
                            return `
                            <div class="gb-bar-row">
                                <div class="gb-bar-label" title="${esc(q.title)}">${esc(q.title.length > 24 ? q.title.substring(0,24)+'…' : q.title)}</div>
                                <div class="gb-bar-track">
                                    <div class="gb-bar-fill" style="width:${(q.avg/maxAvg)*100}%;background:${colors[cls]}"></div>
                                </div>
                                <div class="gb-bar-pct">${q.avg}%</div>
                            </div>`;
                        }).join('')}
                </div>
            </div>

            <!-- Distribution by grade range -->
            <div class="gb-chart-card">
                <div class="gb-chart-head">Grade Distribution</div>
                <div class="gb-chart-body">
                    ${distData.map(d => `
                    <div class="gb-dist-row">
                        <span class="gb-dist-chip" style="background:${d.bg};color:${d.color}">${d.label.split(' ')[0]}</span>
                        <div class="gb-dist-track">
                            <div class="gb-dist-fill" style="width:${(dist[d.key]/maxDist)*100}%;background:${d.color}"></div>
                        </div>
                        <span class="gb-dist-cnt">${dist[d.key]}</span>
                    </div>`).join('')}
                </div>
            </div>
        </div>
    `;
}

/* ─── Grade sections + student rows ──────────────────────── */
function buildContent(container, scoreResults, searchVal) {
    const content = container.querySelector('#gb-content');

    if (scoreResults.length === 0) {
        content.innerHTML = `
            <div class="gb-empty">
                <div class="gb-empty-icon">📋</div>
                <h4>No Quizzes Found</h4>
                <p>Create and publish quizzes to start tracking student grades.</p>
            </div>`;
        return;
    }

    let html = '';
    let rowIdx = 0;

    scoreResults.forEach(sr => {
        const q      = sr.quiz;
        const scores = sr.scores;

        // Quiz section stats
        let qPassed = 0, qSum = 0;
        scores.forEach(sc => {
            if (sc.passed == 1) qPassed++;
            qSum += parseFloat(sc.percentage || 0);
        });
        const qAvg = scores.length ? (qSum / scores.length).toFixed(1) : null;

        html += `
        <div class="quiz-section">
            <div class="quiz-section-hd">
                <div class="qs-icon">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#00461B" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c.98 0 1.813.626 2.122 1.5"/></svg>
                </div>
                <a href="#instructor/quizzes?subject_id=${q.subject_id || ''}" class="qs-title" style="text-decoration:none;color:inherit;" title="View quiz">${esc(q.quiz_title)}</a>
                <div class="qs-meta">
                    <span class="qs-chip blue">${esc(q.subject_code || '')}</span>
                    <span class="qs-chip">${scores.length} attempt${scores.length !== 1 ? 's' : ''}</span>
                    ${qAvg !== null ? `<span class="qs-chip green">Avg ${qAvg}%</span>` : ''}
                    ${scores.length ? `<span class="qs-chip green">${qPassed}/${scores.length} passed</span>` : ''}
                </div>
            </div>`;

        if (scores.length === 0) {
            html += `<div class="gb-empty" style="border-radius:0 0 12px 12px;border-top:none">
                <div class="gb-empty-icon" style="font-size:28px">📭</div>
                <h4>No Attempts Yet</h4>
                <p>Students haven't attempted this quiz.</p>
            </div>`;
        } else {
            // Group by student
            const byStudent = new Map();
            scores.forEach(sc => {
                const key = sc.student_id || (sc.first_name + '_' + sc.last_name);
                if (!byStudent.has(key)) byStudent.set(key, { ...sc, attempts: [] });
                byStudent.get(key).attempts.push(sc);
            });

            html += `<div class="gb-table-wrap"><table class="gb-table">
                <thead><tr>
                    <th>Student</th>
                    <th>Best Score</th>
                    <th>Percentage</th>
                    <th>Status</th>
                    <th>Attempts</th>
                    <th>Last Attempt</th>
                </tr></thead>
                <tbody>`;

            for (const [, st] of byStudent) {
                const best   = st.attempts.reduce((a, b) => parseFloat(a.percentage) >= parseFloat(b.percentage) ? a : b);
                const pct    = parseFloat(best.percentage || 0);
                const passed = st.attempts.some(a => a.passed == 1);
                const gradeClass = pct >= 90 ? 'a' : pct >= 80 ? 'b' : pct >= 70 ? 'c' : pct >= 60 ? 'd' : 'f';
                const gradeLabel = pct >= 90 ? 'A' : pct >= 80 ? 'B' : pct >= 70 ? 'C' : pct >= 60 ? 'D' : 'F';
                const detailId = `detail-${q.quiz_id}-${rowIdx++}`;
                const nameLower = (st.first_name + ' ' + st.last_name).toLowerCase();

                html += `
                <tr class="gb-student-row grade-${gradeClass}" data-name="${nameLower}" data-target="${detailId}">
                    <td>
                        <span class="gb-expand-arrow">▶</span>
                        <div>
                            <div class="gb-student-name">${esc(st.first_name)} ${esc(st.last_name)}</div>
                            ${st.student_id ? `<div class="gb-student-id">${esc(st.student_id)}</div>` : ''}
                        </div>
                    </td>
                    <td style="font-weight:700;color:#111827">${best.earned_points}/${best.total_points}</td>
                    <td>
                        <div class="score-inline">
                            <div class="score-bar-sm"><div class="score-bar-fill ${gradeClass}" style="width:${pct}%"></div></div>
                            <span class="grade-pill ${gradeClass}">${gradeLabel} ${pct.toFixed(1)}%</span>
                        </div>
                    </td>
                    <td>
                        <span class="status-badge ${passed ? 'passed' : 'failed'}">
                            ${passed ? '✓ Passed' : '✕ Failed'}
                        </span>
                    </td>
                    <td style="color:#374151;font-weight:600">${st.attempts.length}</td>
                    <td style="color:#9ca3af;font-size:12px">
                        ${best.completed_at ? new Date(best.completed_at).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' }) : '—'}
                    </td>
                </tr>
                <tr class="gb-detail-row" id="${detailId}">
                    <td colspan="6" style="padding:0;border-bottom:1px solid #f3f4f6">
                        <div class="gb-detail-inner">
                            <table class="gb-detail-table">
                                <thead><tr><th>#</th><th>Score</th><th>Percentage</th><th>Status</th><th>Date & Time</th></tr></thead>
                                <tbody>
                                    ${st.attempts.map((a, idx) => {
                                        const ap   = parseFloat(a.percentage || 0);
                                        const aCls = ap >= 90 ? 'a' : ap >= 80 ? 'b' : ap >= 70 ? 'c' : ap >= 60 ? 'd' : 'f';
                                        const aLbl = ap >= 90 ? 'A' : ap >= 80 ? 'B' : ap >= 70 ? 'C' : ap >= 60 ? 'D' : 'F';
                                        const isBest = a === best;
                                        return `<tr>
                                            <td style="color:#9ca3af">Attempt ${a.attempt_number || idx + 1}${isBest ? '<span class="attempt-best">★ Best</span>' : ''}</td>
                                            <td style="font-weight:600">${a.earned_points}/${a.total_points}</td>
                                            <td><span class="grade-pill ${aCls}" style="font-size:10px">${aLbl} ${ap.toFixed(1)}%</span></td>
                                            <td><span class="status-badge ${a.passed == 1 ? 'passed' : 'failed'}" style="font-size:10px">${a.passed == 1 ? '✓ Passed' : '✕ Failed'}</span></td>
                                            <td style="color:#9ca3af">${a.completed_at ? new Date(a.completed_at).toLocaleString('en-US', { month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' }) : '—'}</td>
                                        </tr>`;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>`;
            }

            html += `</tbody></table></div>`;
        }

        html += `</div>`;
    });

    content.innerHTML = html;

    // Expand/collapse handlers
    content.querySelectorAll('.gb-student-row').forEach(row => {
        row.addEventListener('click', () => {
            const detail = content.querySelector('#' + row.dataset.target);
            if (!detail) return;
            const isOpen = detail.classList.toggle('open');
            row.classList.toggle('open', isOpen);
        });
    });

    // Apply initial search filter if any
    if (searchVal) filterStudentRows(container, searchVal);
}

/* ─── Search filter ───────────────────────────────────────── */
function filterStudentRows(container, q) {
    const term = q.toLowerCase();
    container.querySelectorAll('.gb-student-row').forEach(row => {
        const show = !term || row.dataset.name.includes(term);
        row.style.display = show ? '' : 'none';
        if (!show) {
            const detail = container.querySelector('#' + row.dataset.target);
            if (detail) { detail.classList.remove('open'); row.classList.remove('open'); }
        }
    });
}

function esc(str) { const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }
