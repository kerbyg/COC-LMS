/**
 * Student Quizzes Page
 * View available quizzes across enrolled subjects
 */
import { Api } from '../../api.js';

export async function render(container) {
    const params = new URLSearchParams(window.location.hash.split('?')[1] || '');
    const filterSubject = params.get('subject_id') || '';

    container.innerHTML = `<div style="display:flex;justify-content:center;padding:60px">
        <div style="width:36px;height:36px;border:3px solid #e8e8e8;border-top-color:#1B4D3E;border-radius:50%;animation:spin .8s linear infinite"></div>
    </div>`;

    const [quizRes, subjRes] = await Promise.all([
        Api.get('/ProgressAPI.php?action=student-quizzes' + (filterSubject ? '&subject_id=' + filterSubject : '')),
        Api.get('/EnrollmentAPI.php?action=my-subjects')
    ]);
    const quizzes  = quizRes.success  ? quizRes.data  : [];
    const subjects = subjRes.success  ? subjRes.data  : [];

    // Group by subject
    const grouped = {};
    quizzes.forEach(q => {
        const key = q.subject_code;
        if (!grouped[key]) grouped[key] = { code: q.subject_code, name: q.subject_name, quizzes: [] };
        grouped[key].quizzes.push(q);
    });

    // Summary stats
    const total    = quizzes.length;
    const passed   = quizzes.filter(q => q.quiz_status === 'passed').length;
    const attempted = quizzes.filter(q => q.quiz_status === 'attempted').length;
    const available = quizzes.filter(q => q.can_take).length;

    container.innerHTML = `
        <style>
            /* ── Header ── */
            .qz-header { margin-bottom: 6px; }
            .qz-header h2 { font-size: 24px; font-weight: 800; color: #111827; margin: 0 0 4px; letter-spacing: -.3px; }
            .qz-header p  { font-size: 13px; color: #9CA3AF; margin: 0 0 20px; }

            /* ── Summary chips ── */
            .qz-summary {
                display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 24px;
            }
            .qz-chip {
                display: flex; align-items: center; gap: 7px;
                padding: 8px 16px; border-radius: 10px; border: 1px solid #f1f5f9;
                background: #fff; font-size: 13px; font-weight: 600; color: #374151;
                box-shadow: 0 1px 3px rgba(0,0,0,.06);
            }
            .qz-chip-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

            /* ── Subject filter tabs ── */
            .qz-tabs {
                display: flex; gap: 8px; flex-wrap: wrap;
                padding-bottom: 20px; margin-bottom: 28px;
                border-bottom: 1px solid #F3F4F6;
            }
            .qz-tab {
                padding: 7px 16px; border-radius: 20px; font-size: 13px; font-weight: 600;
                border: 1.5px solid #E5E7EB; color: #6B7280; background: #fff;
                cursor: pointer; transition: all .15s; user-select: none;
            }
            .qz-tab:hover  { border-color: #1B4D3E; color: #1B4D3E; background: #F0FDF4; }
            .qz-tab.active { background: #1B4D3E; color: #fff; border-color: #1B4D3E; }

            /* ── Subject group ── */
            .qz-group { margin-bottom: 36px; }
            .qz-group-hdr {
                display: flex; align-items: center; gap: 12px;
                margin-bottom: 16px; flex-wrap: wrap;
            }
            .qz-group-code {
                background: #E8F5E9; color: #1B4D3E;
                padding: 4px 12px; border-radius: 6px;
                font-size: 12px; font-weight: 800; font-family: monospace; letter-spacing: .5px;
            }
            .qz-group-name { font-size: 16px; font-weight: 700; color: #111827; }
            .qz-group-count { font-size: 12px; color: #9CA3AF; margin-left: auto; }

            /* ── Quiz grid ── */
            .qz-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }

            /* ── Quiz card ── */
            .qz-card {
                background: #fff; border: 1px solid #f1f5f9; border-radius: 14px;
                box-shadow: 0 1px 3px rgba(0,0,0,.07), 0 1px 2px rgba(0,0,0,.04);
                overflow: hidden; display: flex; flex-direction: column;
                transition: box-shadow .2s, transform .2s;
            }
            .qz-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.1), 0 4px 8px rgba(0,0,0,.06); transform: translateY(-2px); }

            /* left accent strip */
            .qz-card-strip { height: 4px; width: 100%; }

            .qz-card-body { padding: 18px 20px 14px; flex: 1; }

            /* badges row */
            .qz-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; }
            .qz-badge {
                padding: 3px 9px; border-radius: 20px; font-size: 10px;
                font-weight: 800; text-transform: uppercase; letter-spacing: .4px;
            }
            .qz-badge.type-quiz    { background: #F3F4F6; color: #374151; }
            .qz-badge.type-pre     { background: #DBEAFE; color: #1E40AF; }
            .qz-badge.type-post    { background: #EDE9FE; color: #6D28D9; }
            .qz-badge.st-passed    { background: #DCFCE7; color: #15803D; }
            .qz-badge.st-attempted { background: #FEF3C7; color: #B45309; }
            .qz-badge.st-available { background: #E8F5E9; color: #1B4D3E; }
            .qz-badge.st-none      { background: #F3F4F6; color: #9CA3AF; }

            /* title + desc */
            .qz-title { font-size: 15px; font-weight: 700; color: #111827; margin-bottom: 4px; line-height: 1.35; }
            .qz-desc  { font-size: 13px; color: #9CA3AF; margin-bottom: 14px;
                        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

            /* stat pills */
            .qz-stats { display: flex; gap: 8px; flex-wrap: wrap; }
            .qz-stat {
                display: flex; align-items: center; gap: 5px;
                padding: 5px 10px; background: #F9FAFB; border-radius: 8px;
                font-size: 12px; color: #374151;
            }
            .qz-stat strong { font-weight: 700; color: #111827; }
            .qz-stat svg { color: #9CA3AF; }

            /* card footer */
            .qz-card-footer {
                padding: 14px 20px;
                background: #FAFAFA; border-top: 1px solid #F3F4F6;
                display: flex; align-items: center; justify-content: space-between; gap: 12px;
            }

            /* score section */
            .qz-score-wrap { min-width: 0; flex: 1; }
            .qz-score-label { font-size: 10px; color: #9CA3AF; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 4px; }
            .qz-score-val {
                font-size: 20px; font-weight: 800; line-height: 1;
            }
            .qz-score-val.pass { color: #1B4D3E; }
            .qz-score-val.fail { color: #B91C1C; }
            .qz-score-val.none { font-size: 13px; font-weight: 500; color: #9CA3AF; }

            .qz-score-bar-track { height: 4px; background: #E5E7EB; border-radius: 2px; margin-top: 5px; overflow: hidden; }
            .qz-score-bar-fill  { height: 100%; border-radius: 2px; transition: width .6s ease; }

            /* take button */
            .qz-btn {
                flex-shrink: 0; display: inline-flex; align-items: center; gap: 6px;
                padding: 9px 18px; border-radius: 9px; font-size: 13px; font-weight: 700;
                text-decoration: none; border: none; cursor: pointer; transition: all .15s;
            }
            .qz-btn.take { background: #1B4D3E; color: #fff; }
            .qz-btn.take:hover { background: #2D6A4F; }
            .qz-btn.disabled { background: #F3F4F6; color: #9CA3AF; cursor: not-allowed; pointer-events: none; }

            /* empty */
            .qz-empty {
                display: flex; flex-direction: column; align-items: center; justify-content: center;
                min-height: 260px; text-align: center; color: #9CA3AF;
                background: #fff; border-radius: 16px; border: 2px dashed #e2e8f0;
            }
            .qz-empty h3 { font-size: 16px; font-weight: 700; color: #374151; margin: 14px 0 6px; }
            .qz-empty p  { font-size: 13px; margin: 0; }

            @media(max-width:640px) { .qz-grid { grid-template-columns: 1fr; } }

            .btn-back {
                display: inline-flex; align-items: center; gap: 6px;
                font-size: 13px; font-weight: 600; color: #6B7280;
                text-decoration: none; margin-bottom: 16px;
                transition: color .15s;
            }
            .btn-back:hover { color: #1B4D3E; }
        </style>

        <a href="#student/my-subjects" class="btn-back">← Back to My Subjects</a>

        <!-- Page header -->
        <div class="qz-header">
            <h2>Quizzes</h2>
            <p>Track your quiz attempts and scores across all subjects</p>
        </div>

        <!-- Summary chips -->
        <div class="qz-summary">
            <div class="qz-chip"><span class="qz-chip-dot" style="background:#1B4D3E"></span>${total} Total</div>
            <div class="qz-chip"><span class="qz-chip-dot" style="background:#15803D"></span>${passed} Passed</div>
            <div class="qz-chip"><span class="qz-chip-dot" style="background:#B45309"></span>${attempted} Attempted</div>
            <div class="qz-chip"><span class="qz-chip-dot" style="background:#60a5fa"></span>${available} Available</div>
        </div>

        <!-- Subject filter tabs -->
        <div class="qz-tabs">
            <span class="qz-tab ${!filterSubject ? 'active' : ''}" data-id="">All Subjects</span>
            ${subjects.map(s => `
                <span class="qz-tab ${filterSubject == s.subject_id ? 'active' : ''}" data-id="${s.subject_id}">
                    ${esc(s.subject_code)}
                </span>
            `).join('')}
        </div>

        <!-- Quiz content -->
        <div id="qz-body">
            ${Object.keys(grouped).length === 0
                ? `<div class="qz-empty">
                    <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="#D1D5DB" stroke-width="1.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>
                    <h3>No quizzes available</h3>
                    <p>Your instructor hasn't published any quizzes yet.</p>
                   </div>`
                : Object.values(grouped).map(g => `
                    <div class="qz-group">
                        <div class="qz-group-hdr">
                            <span class="qz-group-code">${esc(g.code)}</span>
                            <span class="qz-group-name">${esc(g.name)}</span>
                            <span class="qz-group-count">${g.quizzes.length} quiz${g.quizzes.length !== 1 ? 'zes' : ''}</span>
                        </div>
                        <div class="qz-grid">
                            ${g.quizzes.map(q => {
                                const typeClass = q.quiz_type === 'pre_test' ? 'type-pre' : q.quiz_type === 'post_test' ? 'type-post' : 'type-quiz';
                                const typeLabel = q.quiz_type === 'pre_test' ? 'Pre-Test' : q.quiz_type === 'post_test' ? 'Post-Test' : 'Quiz';
                                const status    = q.quiz_status || 'available';
                                const stClass   = status === 'passed' ? 'st-passed' : status === 'attempted' ? 'st-attempted' : status === 'available' ? 'st-available' : 'st-none';
                                const stLabel   = status === 'passed' ? 'Passed' : status === 'attempted' ? 'Attempted' : status === 'available' ? 'Available' : 'Unavailable';

                                const hasScore  = q.best_score !== null && q.best_score !== undefined;
                                const scorePct  = hasScore ? parseFloat(q.best_score) : 0;
                                const isPassed  = q.passed == 1;

                                // Strip color
                                const stripColor = status === 'passed' ? '#1B4D3E' : status === 'attempted' ? '#B45309' : status === 'available' ? '#1B4D3E' : '#E5E7EB';

                                // Score bar color
                                const barColor  = isPassed ? '#1B4D3E' : '#B91C1C';

                                return `
                                <div class="qz-card">
                                    <div class="qz-card-strip" style="background:${stripColor}"></div>
                                    <div class="qz-card-body">
                                        <div class="qz-badges">
                                            <span class="qz-badge ${typeClass}">${typeLabel}</span>
                                            <span class="qz-badge ${stClass}">${stLabel}</span>
                                        </div>
                                        <div class="qz-title">${esc(q.quiz_title)}</div>
                                        ${q.quiz_description ? `<div class="qz-desc">${esc(q.quiz_description)}</div>` : ''}
                                        <div class="qz-stats">
                                            <div class="qz-stat">
                                                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                                                <strong>${q.question_count || 0}</strong> Questions
                                            </div>
                                            <div class="qz-stat">
                                                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                <strong>${q.time_limit || 0}m</strong> Time
                                            </div>
                                            <div class="qz-stat">
                                                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                <strong>${q.passing_rate || 0}%</strong> to Pass
                                            </div>
                                        </div>
                                    </div>

                                    <div class="qz-card-footer">
                                        <div class="qz-score-wrap">
                                            <div class="qz-score-label">Best Score</div>
                                            ${hasScore
                                                ? `<div class="qz-score-val ${isPassed ? 'pass' : 'fail'}">${scorePct.toFixed(1)}%</div>
                                                   <div class="qz-score-bar-track">
                                                       <div class="qz-score-bar-fill" style="width:${scorePct}%;background:${barColor}"></div>
                                                   </div>`
                                                : `<div class="qz-score-val none">Not taken yet</div>`
                                            }
                                        </div>
                                        ${status === 'passed'
                                            ? `<a class="qz-btn" href="#student/quizzes" style="background:#E8F5E9;color:#1B4D3E;border:1.5px solid #1B4D3E;cursor:default;pointer-events:none;">
                                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                Passed
                                               </a>`
                                            : q.can_take
                                                ? `<a class="qz-btn take" href="#student/take-quiz?quiz_id=${q.quiz_id}">
                                                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z"/></svg>
                                                    ${status === 'attempted' ? 'Retake Quiz' : 'Take Quiz'}
                                                   </a>`
                                                : `<span class="qz-btn disabled">Unavailable</span>`
                                        }
                                    </div>
                                </div>`;
                            }).join('')}
                        </div>
                    </div>
                `).join('')}
        </div>
    `;

    // Tab filter
    container.querySelectorAll('.qz-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            window.location.hash = tab.dataset.id
                ? `#student/quizzes?subject_id=${tab.dataset.id}`
                : '#student/quizzes';
        });
    });
}

function esc(str) { const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }
