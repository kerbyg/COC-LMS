/**
 * Student Quizzes Page — redesigned
 */
import { Api } from '../../api.js';

export async function render(container) {
    const params        = new URLSearchParams(window.location.hash.split('?')[1] || '');
    const filterSubject = params.get('subject_id') || '';

    // Spinner
    container.innerHTML = `<div style="display:flex;justify-content:center;padding:80px">
        <div style="width:36px;height:36px;border:3px solid #e8e8e8;border-top-color:#1B4D3E;border-radius:50%;animation:spin .8s linear infinite"></div>
    </div>`;

    const [quizRes, subjRes] = await Promise.all([
        Api.get('/ProgressAPI.php?action=student-quizzes' + (filterSubject ? '&subject_id=' + filterSubject : '')),
        Api.get('/EnrollmentAPI.php?action=my-subjects'),
    ]);

    const quizzes  = quizRes.success ? quizRes.data  : [];
    const subjects = subjRes.success ? subjRes.data  : [];

    // Stats
    const total     = quizzes.length;
    const passed    = quizzes.filter(q => q.quiz_status === 'passed').length;
    const attempted = quizzes.filter(q => q.quiz_status === 'attempted').length;
    const available = quizzes.filter(q => q.can_take).length;

    // Group by subject
    const grouped = {};
    quizzes.forEach(q => {
        const key = q.subject_code;
        if (!grouped[key]) grouped[key] = { code: q.subject_code, name: q.subject_name, quizzes: [] };
        grouped[key].quizzes.push(q);
    });

    // Active subject label for banner
    const activeSub = subjects.find(s => s.subject_id == filterSubject);

    container.innerHTML = `
        <style>
            @keyframes fadeUp { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
            .qp-wrap { animation: fadeUp .3s ease; }

            /* ── Back ── */
            .qp-back {
                display:inline-flex; align-items:center; gap:6px;
                font-size:13px; font-weight:600; color:#6B7280;
                text-decoration:none; margin-bottom:20px;
                transition:color .15s;
            }
            .qp-back:hover { color:#1B4D3E; }

            /* ── Banner ── */
            .qp-banner {
                background: linear-gradient(135deg,#1B4D3E 0%,#2D6A4F 100%);
                border-radius: 16px; padding: 28px 32px; margin-bottom: 28px;
                display: flex; align-items: center; justify-content: space-between;
                flex-wrap: wrap; gap: 20px;
            }
            .qp-banner-left h2 {
                font-size: 22px; font-weight: 800; color: #fff; margin: 0 0 4px;
            }
            .qp-banner-left p {
                font-size: 13px; color: rgba(255,255,255,.7); margin: 0;
            }
            .qp-stats {
                display: flex; gap: 12px; flex-wrap: wrap;
            }
            .qp-stat {
                background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18);
                border-radius: 12px; padding: 12px 20px; text-align: center;
                min-width: 80px; backdrop-filter: blur(4px);
            }
            .qp-stat-val {
                font-size: 24px; font-weight: 800; color: #fff; line-height: 1;
            }
            .qp-stat-lbl {
                font-size: 11px; color: rgba(255,255,255,.65); margin-top: 4px;
                font-weight: 600; text-transform: uppercase; letter-spacing: .5px;
            }
            .qp-stat.green .qp-stat-val { color: #86efac; }
            .qp-stat.amber .qp-stat-val { color: #fcd34d; }
            .qp-stat.blue  .qp-stat-val { color: #93c5fd; }

            /* ── Subject tabs ── */
            .qp-tabs {
                display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 28px;
            }
            .qp-tab {
                padding: 7px 18px; border-radius: 20px; font-size: 13px; font-weight: 600;
                border: 1.5px solid #E5E7EB; color: #6B7280; background: #fff;
                cursor: pointer; transition: all .15s; user-select: none;
            }
            .qp-tab:hover  { border-color: #1B4D3E; color: #1B4D3E; }
            .qp-tab.active { background: #1B4D3E; color: #fff; border-color: #1B4D3E; }

            /* ── Group header ── */
            .qp-group { margin-bottom: 36px; }
            .qp-group-hdr {
                display: flex; align-items: center; gap: 10px;
                margin-bottom: 16px; padding-bottom: 12px;
                border-bottom: 1px solid #F3F4F6;
            }
            .qp-group-pill {
                background: #E8F5E9; color: #1B4D3E;
                padding: 4px 12px; border-radius: 6px;
                font-size: 12px; font-weight: 800; font-family: monospace;
            }
            .qp-group-name { font-size: 15px; font-weight: 700; color: #111827; }
            .qp-group-count {
                margin-left: auto; font-size: 12px; color: #9CA3AF; font-weight: 500;
            }

            /* ── Quiz grid ── */
            .qp-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
                gap: 16px;
            }

            /* ── Quiz card ── */
            .qp-card {
                background: #fff; border-radius: 14px;
                border: 1px solid #F1F5F9;
                box-shadow: 0 1px 4px rgba(0,0,0,.06);
                display: flex; flex-direction: column;
                transition: box-shadow .2s, transform .2s;
                overflow: hidden;
            }
            .qp-card:hover {
                box-shadow: 0 8px 28px rgba(0,0,0,.1);
                transform: translateY(-2px);
            }

            /* top color strip by status */
            .qp-card-top { height: 5px; }
            .qp-card-top.passed   { background: linear-gradient(90deg,#1B4D3E,#2D6A4F); }
            .qp-card-top.attempted{ background: linear-gradient(90deg,#B45309,#D97706); }
            .qp-card-top.available{ background: linear-gradient(90deg,#2563EB,#60A5FA); }
            .qp-card-top.none     { background: #E5E7EB; }

            .qp-card-body { padding: 20px; flex: 1; }

            /* badge row */
            .qp-badge-row { display: flex; gap: 6px; margin-bottom: 12px; flex-wrap: wrap; }
            .qp-badge {
                padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
                text-transform: uppercase; letter-spacing: .3px;
            }
            .qp-badge.quiz     { background: #F3F4F6; color: #374151; }
            .qp-badge.pre      { background: #DBEAFE; color: #1D4ED8; }
            .qp-badge.post     { background: #EDE9FE; color: #6D28D9; }
            .qp-badge.passed   { background: #DCFCE7; color: #15803D; }
            .qp-badge.attempted{ background: #FEF3C7; color: #92400E; }
            .qp-badge.available{ background: #DBEAFE; color: #1D4ED8; }
            .qp-badge.locked   { background: #F3F4F6; color: #9CA3AF; }

            .qp-title { font-size: 15px; font-weight: 700; color: #111827; margin-bottom: 6px; line-height: 1.4; }
            .qp-desc  {
                font-size: 13px; color: #9CA3AF; margin-bottom: 14px; line-height: 1.5;
                display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
            }

            /* meta row */
            .qp-meta { display: flex; gap: 6px; flex-wrap: wrap; }
            .qp-meta-item {
                display: flex; align-items: center; gap: 4px;
                background: #F8FAFC; border-radius: 7px; padding: 4px 10px;
                font-size: 12px; color: #6B7280;
            }
            .qp-meta-item b { color: #111827; font-weight: 700; }

            /* footer */
            .qp-card-footer {
                padding: 16px 20px; background: #FAFAFA;
                border-top: 1px solid #F3F4F6;
                display: flex; align-items: center; justify-content: space-between; gap: 12px;
            }

            /* score */
            .qp-score-side { min-width: 0; flex: 1; }
            .qp-score-lbl { font-size: 10px; font-weight: 700; text-transform: uppercase;
                            letter-spacing: .5px; color: #9CA3AF; margin-bottom: 4px; }
            .qp-score-num { font-size: 22px; font-weight: 800; line-height: 1; }
            .qp-score-num.pass { color: #1B4D3E; }
            .qp-score-num.fail { color: #B91C1C; }
            .qp-score-num.none { font-size: 13px; font-weight: 500; color: #9CA3AF; }
            .qp-bar { height: 4px; background: #E5E7EB; border-radius: 2px; margin-top: 6px; overflow: hidden; }
            .qp-bar-fill { height: 100%; border-radius: 2px; }

            /* CTA button */
            .qp-btn {
                flex-shrink: 0; display: inline-flex; align-items: center; gap: 6px;
                padding: 9px 18px; border-radius: 9px; font-size: 13px; font-weight: 700;
                border: none; cursor: pointer; text-decoration: none; transition: all .15s;
                white-space: nowrap;
            }
            .qp-btn.take       { background: #1B4D3E; color: #fff; }
            .qp-btn.take:hover { background: #2D6A4F; transform: scale(1.02); }
            .qp-btn.retake       { background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; }
            .qp-btn.retake:hover { background: #FDE68A; }
            .qp-btn.done       { background: #DCFCE7; color: #15803D; border: 1px solid #86EFAC; cursor: default; pointer-events: none; }
            .qp-btn.locked     { background: #F3F4F6; color: #9CA3AF; cursor: not-allowed; pointer-events: none; }

            /* empty */
            .qp-empty {
                display: flex; flex-direction: column; align-items: center; justify-content: center;
                min-height: 280px; text-align: center; color: #9CA3AF;
                background: #fff; border-radius: 16px;
                border: 2px dashed #E5E7EB; padding: 40px;
            }
            .qp-empty-icon { font-size: 48px; margin-bottom: 12px; }
            .qp-empty h3 { font-size: 16px; font-weight: 700; color: #374151; margin: 0 0 6px; }
            .qp-empty p  { font-size: 13px; margin: 0; line-height: 1.6; }

            @media(max-width:640px) {
                .qp-grid { grid-template-columns: 1fr; }
                .qp-banner { padding: 20px; }
                .qp-stats { gap: 8px; }
                .qp-stat { min-width: 64px; padding: 10px 14px; }
            }
        </style>

        <div class="qp-wrap">
            <a href="#student/my-subjects" class="qp-back">← Back to My Subjects</a>

            <!-- Banner -->
            <div class="qp-banner">
                <div class="qp-banner-left">
                    <h2>${activeSub ? esc(activeSub.subject_code) + ' — ' + esc(activeSub.subject_name) : 'My Quizzes'}</h2>
                    <p>${activeSub ? 'Showing quizzes for this subject' : 'All your quizzes across enrolled subjects'}</p>
                </div>
                <div class="qp-stats">
                    <div class="qp-stat">
                        <div class="qp-stat-val">${total}</div>
                        <div class="qp-stat-lbl">Total</div>
                    </div>
                    <div class="qp-stat green">
                        <div class="qp-stat-val">${passed}</div>
                        <div class="qp-stat-lbl">Passed</div>
                    </div>
                    <div class="qp-stat amber">
                        <div class="qp-stat-val">${attempted}</div>
                        <div class="qp-stat-lbl">Attempted</div>
                    </div>
                    <div class="qp-stat blue">
                        <div class="qp-stat-val">${available}</div>
                        <div class="qp-stat-lbl">Available</div>
                    </div>
                </div>
            </div>

            <!-- Subject tabs -->
            <div class="qp-tabs">
                <span class="qp-tab ${!filterSubject ? 'active' : ''}" data-id="">All Subjects</span>
                ${subjects.map(s => `
                    <span class="qp-tab ${filterSubject == s.subject_id ? 'active' : ''}" data-id="${s.subject_id}">
                        ${esc(s.subject_code)}
                    </span>
                `).join('')}
            </div>

            <!-- Content -->
            <div id="qp-body">
                ${Object.keys(grouped).length === 0
                    ? `<div class="qp-empty">
                           <div class="qp-empty-icon">📋</div>
                           <h3>No quizzes available</h3>
                           <p>Your instructor hasn't published any quizzes yet.<br>Check back soon.</p>
                       </div>`
                    : Object.values(grouped).map(g => `
                        <div class="qp-group">
                            <div class="qp-group-hdr">
                                <span class="qp-group-pill">${esc(g.code)}</span>
                                <span class="qp-group-name">${esc(g.name)}</span>
                                <span class="qp-group-count">${g.quizzes.length} quiz${g.quizzes.length !== 1 ? 'zes' : ''}</span>
                            </div>
                            <div class="qp-grid">
                                ${g.quizzes.map(q => renderCard(q)).join('')}
                            </div>
                        </div>
                    `).join('')}
            </div>
        </div>
    `;

    // Tab clicks
    container.querySelectorAll('.qp-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            window.location.hash = tab.dataset.id
                ? `#student/quizzes?subject_id=${tab.dataset.id}`
                : '#student/quizzes';
        });
    });
}

function renderCard(q) {
    const status   = q.quiz_status || 'none';
    const canTake  = !!q.can_take;
    const hasScore = q.best_score !== null && q.best_score !== undefined;
    const scorePct = hasScore ? parseFloat(q.best_score) : 0;
    const isPassed = q.passed == 1;

    // Top strip class
    const stripClass = status === 'passed' ? 'passed'
                     : status === 'attempted' ? 'attempted'
                     : canTake ? 'available' : 'none';

    // Type badge
    const typeCls   = q.quiz_type === 'pre_test' ? 'pre' : q.quiz_type === 'post_test' ? 'post' : 'quiz';
    const typeLabel = q.quiz_type === 'pre_test' ? 'Pre-Test' : q.quiz_type === 'post_test' ? 'Post-Test' : 'Quiz';

    // Status badge
    const stCls   = status === 'passed' ? 'passed' : status === 'attempted' ? 'attempted' : canTake ? 'available' : 'locked';
    const stLabel = status === 'passed' ? '✓ Passed' : status === 'attempted' ? '↩ Attempted' : canTake ? 'Available' : 'Locked';

    // Score bar color
    const barColor = isPassed ? '#1B4D3E' : '#EF4444';

    // Button
    let btn = '';
    if (status === 'passed') {
        btn = `<a class="qp-btn done" href="#student/take-quiz?quiz_id=${q.quiz_id}">✓ Passed</a>`;
    } else if (canTake && status === 'attempted') {
        btn = `<a class="qp-btn retake" href="#student/take-quiz?quiz_id=${q.quiz_id}">↩ Retake</a>`;
    } else if (canTake) {
        btn = `<a class="qp-btn take" href="#student/take-quiz?quiz_id=${q.quiz_id}">▶ Take Quiz</a>`;
    } else {
        btn = `<span class="qp-btn locked">🔒 Locked</span>`;
    }

    return `
        <div class="qp-card">
            <div class="qp-card-top ${stripClass}"></div>
            <div class="qp-card-body">
                <div class="qp-badge-row">
                    <span class="qp-badge ${typeCls}">${typeLabel}</span>
                    <span class="qp-badge ${stCls}">${stLabel}</span>
                </div>
                <div class="qp-title">${esc(q.quiz_title)}</div>
                ${q.quiz_description ? `<div class="qp-desc">${esc(q.quiz_description)}</div>` : ''}
                <div class="qp-meta">
                    <div class="qp-meta-item">📝 <b>${q.question_count || 0}</b> questions</div>
                    <div class="qp-meta-item">⏱ <b>${q.time_limit || '∞'}</b>${q.time_limit ? ' min' : ''}</div>
                    <div class="qp-meta-item">🎯 Pass at <b>${q.passing_rate || 0}%</b></div>
                </div>
            </div>
            <div class="qp-card-footer">
                <div class="qp-score-side">
                    <div class="qp-score-lbl">Best Score</div>
                    ${hasScore
                        ? `<div class="qp-score-num ${isPassed ? 'pass' : 'fail'}">${scorePct.toFixed(1)}%</div>
                           <div class="qp-bar"><div class="qp-bar-fill" style="width:${Math.min(scorePct,100)}%;background:${barColor}"></div></div>`
                        : `<div class="qp-score-num none">Not taken yet</div>`
                    }
                </div>
                ${btn}
            </div>
        </div>`;
}

function esc(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
}
