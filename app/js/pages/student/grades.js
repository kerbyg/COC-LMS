/**
 * Student Grades Page
 * View quiz scores grouped by subject
 */
import { Api } from '../../api.js';

export async function render(container) {
    container.innerHTML = `<div style="display:flex;justify-content:center;padding:60px">
        <div style="width:36px;height:36px;border:3px solid #e8e8e8;border-top-color:#1B4D3E;border-radius:50%;animation:spin .8s linear infinite"></div>
    </div>`;

    const res = await Api.get('/ProgressAPI.php?action=grades');
    const subjects = res.success ? res.data : [];

    // Overall stats
    let totalQuizzes = 0, totalPassed = 0, totalFailed = 0, allScores = [];
    subjects.forEach(s => {
        s.quizzes.forEach(q => {
            totalQuizzes++;
            if (q.best_score !== null) allScores.push(parseFloat(q.best_score));
            if (q.passed == 1) totalPassed++;
            else if (q.best_score !== null) totalFailed++;
        });
    });
    const overallAvg = allScores.length > 0
        ? (allScores.reduce((a, b) => a + b, 0) / allScores.length).toFixed(1)
        : null;

    const avgColor = overallAvg >= 60 ? '#15803D' : '#B91C1C';
    const avgBg    = overallAvg >= 60 ? '#E8F5E9'  : '#FEE2E2';

    container.innerHTML = `
        <style>
            /* ── Banner ── */
            .gr-banner {
                background: linear-gradient(135deg, #1B4D3E 0%, #2D6A4F 60%, #40916C 100%);
                border-radius: 16px; padding: 24px 28px; margin-bottom: 24px;
                display: flex; align-items: center; justify-content: space-between;
                flex-wrap: wrap; gap: 16px; position: relative; overflow: hidden;
            }
            .gr-banner::before {
                content: ''; position: absolute; right: -30px; top: -30px;
                width: 160px; height: 160px; border-radius: 50%;
                background: rgba(255,255,255,.05);
            }
            .gr-banner::after {
                content: ''; position: absolute; right: 80px; bottom: -40px;
                width: 100px; height: 100px; border-radius: 50%;
                background: rgba(255,255,255,.04);
            }
            .gr-banner-left { position: relative; z-index: 1; }
            .gr-banner-left h2 { font-size: 22px; font-weight: 800; color: #fff; margin: 0 0 4px; }
            .gr-banner-left p  { font-size: 13px; color: rgba(255,255,255,.7); margin: 0; }
            .gr-banner-right {
                display: flex; gap: 8px; position: relative; z-index: 1; flex-wrap: wrap;
            }
            .gr-banner-btn {
                display: inline-flex; align-items: center; gap: 6px;
                padding: 8px 16px; border-radius: 9px; font-size: 13px; font-weight: 600;
                text-decoration: none; transition: all .15s; white-space: nowrap;
                background: rgba(255,255,255,.15); color: #fff;
                border: 1px solid rgba(255,255,255,.25);
            }
            .gr-banner-btn:hover { background: rgba(255,255,255,.25); }

            /* ── Summary cards ── */
            .gr-summary { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; margin-bottom: 28px; }
            .gr-sum-card {
                background: #fff; border: 1px solid #f1f5f9; border-radius: 14px;
                box-shadow: 0 1px 3px rgba(0,0,0,.07);
                padding: 20px; display: flex; align-items: center; gap: 16px;
                transition: box-shadow .2s, transform .2s;
            }
            .gr-sum-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.09); transform: translateY(-1px); }
            .gr-sum-icon {
                width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0;
                display: flex; align-items: center; justify-content: center;
            }
            .gr-sum-val { font-size: 26px; font-weight: 800; color: #111827; line-height: 1; }
            .gr-sum-lbl { font-size: 12px; color: #9CA3AF; margin-top: 3px; font-weight: 500; }

            /* ── Subject group card ── */
            .gr-group { margin-bottom: 20px; }
            .gr-group-card {
                background: #fff; border: 1px solid #f1f5f9; border-radius: 16px;
                box-shadow: 0 1px 3px rgba(0,0,0,.07);
                overflow: hidden;
            }
            .gr-group-head {
                display: flex; align-items: center; gap: 12px;
                padding: 16px 20px;
                background: #fafafa;
                border-bottom: 1px solid #f1f5f9;
                flex-wrap: wrap;
            }
            .gr-group-code {
                background: #E8F5E9; color: #1B4D3E;
                padding: 4px 12px; border-radius: 6px;
                font-size: 12px; font-weight: 800; font-family: monospace; letter-spacing: .5px;
                flex-shrink: 0;
            }
            .gr-group-name { font-size: 15px; font-weight: 700; color: #111827; flex: 1; }
            .gr-group-avg {
                font-size: 12px; font-weight: 700; padding: 4px 12px;
                border-radius: 20px; white-space: nowrap; flex-shrink: 0;
            }

            /* ── Table ── */
            .gr-table { width: 100%; border-collapse: collapse; }
            .gr-table thead tr { background: #fff; }
            .gr-table th {
                text-align: left; padding: 10px 20px;
                font-size: 10.5px; font-weight: 700; color: #9CA3AF;
                text-transform: uppercase; letter-spacing: .6px;
                border-bottom: 1px solid #f1f5f9;
            }
            .gr-table tbody tr { border-bottom: 1px solid #f8fafc; transition: background .12s; }
            .gr-table tbody tr:last-child { border-bottom: none; }
            .gr-table tbody tr:hover { background: #fafafa; }
            .gr-table td { padding: 14px 20px; font-size: 14px; vertical-align: middle; }

            /* quiz title cell */
            .gr-quiz-name { font-weight: 600; color: #111827; font-size: 14px; }

            /* type badge */
            .gr-type {
                display: inline-block; padding: 3px 9px; border-radius: 20px;
                font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px;
            }
            .gr-type.quiz { background: #f1f5f9; color: #475569; }
            .gr-type.pre  { background: #DBEAFE; color: #1E40AF; }
            .gr-type.post { background: #EDE9FE; color: #6D28D9; }

            /* score cell */
            .gr-score-cell { display: flex; align-items: center; gap: 10px; }
            .gr-score-track {
                flex: 1; max-width: 90px; height: 6px;
                background: #f1f5f9; border-radius: 99px; overflow: hidden;
            }
            .gr-score-fill  { height: 100%; border-radius: 99px; transition: width .6s ease; }
            .gr-score-num   { font-size: 14px; font-weight: 800; white-space: nowrap; }
            .gr-score-none  { font-size: 13px; color: #9CA3AF; }

            /* status badge */
            .gr-status {
                display: inline-flex; align-items: center; gap: 5px;
                padding: 4px 11px; border-radius: 20px;
                font-size: 11px; font-weight: 700;
            }
            .gr-status.passed   { background: #DCFCE7; color: #15803D; }
            .gr-status.failed   { background: #FEE2E2; color: #B91C1C; }
            .gr-status.nottaken { background: #f1f5f9; color: #9CA3AF; }

            /* attempts */
            .gr-attempts {
                display: inline-flex; align-items: center; justify-content: center;
                width: 28px; height: 28px; border-radius: 8px;
                background: #f8fafc; color: #64748b;
                font-size: 13px; font-weight: 700;
            }

            /* no quizzes */
            .gr-no-quizzes {
                padding: 28px 20px; text-align: center; font-size: 13px; color: #9CA3AF;
            }

            /* empty state */
            .gr-empty {
                display: flex; flex-direction: column; align-items: center;
                justify-content: center; min-height: 260px; text-align: center;
                background: #fff; border-radius: 16px; border: 2px dashed #e2e8f0;
                color: #9CA3AF; padding: 40px;
            }
            .gr-empty-icon {
                width: 56px; height: 56px; background: #f1f5f9;
                border-radius: 16px; display: flex; align-items: center;
                justify-content: center; margin-bottom: 16px;
            }
            .gr-empty h3 { font-size: 16px; font-weight: 700; color: #374151; margin: 0 0 6px; }
            .gr-empty p  { font-size: 13px; margin: 0; }

            @media(max-width: 900px) { .gr-summary { grid-template-columns: 1fr 1fr; } }
            @media(max-width: 640px) {
                .gr-summary { grid-template-columns: 1fr 1fr; }
                .gr-score-track { display: none; }
                .gr-table th:last-child, .gr-table td:last-child { display: none; }
            }
        </style>

        <!-- Banner -->
        <div class="gr-banner">
            <div class="gr-banner-left">
                <h2>My Grades</h2>
                <p>Your quiz performance across all enrolled subjects</p>
            </div>
            <div class="gr-banner-right">
                <a href="#student/messages" class="gr-banner-btn">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                    Messages
                </a>
            </div>
        </div>

        <!-- Summary -->
        <div class="gr-summary">
            <div class="gr-sum-card">
                <div class="gr-sum-icon" style="background:#E8F5E9;color:#1B4D3E">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0118 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                </div>
                <div><div class="gr-sum-val">${subjects.length}</div><div class="gr-sum-lbl">Subjects</div></div>
            </div>
            <div class="gr-sum-card">
                <div class="gr-sum-icon" style="background:#DBEAFE;color:#1E40AF">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>
                </div>
                <div><div class="gr-sum-val">${totalQuizzes}</div><div class="gr-sum-lbl">Quizzes Taken</div></div>
            </div>
            <div class="gr-sum-card">
                <div class="gr-sum-icon" style="background:#DCFCE7;color:#15803D">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div><div class="gr-sum-val" style="color:#15803D">${totalPassed}</div><div class="gr-sum-lbl">Passed</div></div>
            </div>
            <div class="gr-sum-card">
                <div class="gr-sum-icon" style="background:${overallAvg ? avgBg : '#f1f5f9'};color:${overallAvg ? avgColor : '#9CA3AF'}">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                </div>
                <div>
                    <div class="gr-sum-val" style="color:${overallAvg ? avgColor : '#9CA3AF'}">${overallAvg ? overallAvg + '%' : '—'}</div>
                    <div class="gr-sum-lbl">Overall Average</div>
                </div>
            </div>
        </div>

        <!-- Subject groups -->
        ${subjects.length === 0 ? `
            <div class="gr-empty">
                <div class="gr-empty-icon">
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#9CA3AF" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                </div>
                <h3>No grades yet</h3>
                <p>Enroll in subjects and take quizzes to see your scores here.</p>
            </div>
        ` : subjects.map(s => {
            const avg       = s.avg_score !== null ? parseFloat(s.avg_score) : null;
            const avgTxtClr = avg >= 60 ? '#15803D' : avg !== null ? '#B91C1C' : '#9CA3AF';
            const avgBgClr  = avg >= 60 ? '#DCFCE7' : avg !== null ? '#FEE2E2' : '#f1f5f9';

            return `
            <div class="gr-group">
                <div class="gr-group-card">
                    <div class="gr-group-head">
                        <span class="gr-group-code">${esc(s.subject_code)}</span>
                        <span class="gr-group-name">${esc(s.subject_name)}</span>
                        ${avg !== null ? `<span class="gr-group-avg" style="background:${avgBgClr};color:${avgTxtClr}">${avg.toFixed(1)}% avg</span>` : ''}
                    </div>

                    ${s.quizzes.length === 0
                        ? `<div class="gr-no-quizzes">No quizzes published yet.</div>`
                        : `<table class="gr-table">
                            <thead>
                                <tr>
                                    <th>Quiz</th>
                                    <th>Type</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                    <th style="text-align:right">Attempts</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${s.quizzes.map(q => {
                                    const score     = q.best_score !== null ? parseFloat(q.best_score) : null;
                                    const barColor  = score >= 60
                                        ? 'linear-gradient(90deg,#1B4D3E,#2D6A4F)'
                                        : 'linear-gradient(90deg,#B91C1C,#DC2626)';
                                    const numColor  = score >= 60 ? '#15803D' : '#B91C1C';
                                    const typeKey   = q.quiz_type === 'pre_test' ? 'pre' : q.quiz_type === 'post_test' ? 'post' : 'quiz';
                                    const typeLabel = q.quiz_type === 'pre_test' ? 'Pre-Test' : q.quiz_type === 'post_test' ? 'Post-Test' : 'Quiz';
                                    const statusKey   = q.passed == 1 ? 'passed' : score !== null ? 'failed' : 'nottaken';
                                    const statusLabel = q.passed == 1 ? 'Passed' : score !== null ? 'Failed' : 'Not taken';

                                    return `<tr>
                                        <td class="gr-quiz-name">${esc(q.quiz_title)}</td>
                                        <td><span class="gr-type ${typeKey}">${typeLabel}</span></td>
                                        <td>
                                            ${score !== null
                                                ? `<div class="gr-score-cell">
                                                    <div class="gr-score-track">
                                                        <div class="gr-score-fill" style="width:${score}%;background:${barColor}"></div>
                                                    </div>
                                                    <span class="gr-score-num" style="color:${numColor}">${score.toFixed(1)}%</span>
                                                   </div>`
                                                : `<span class="gr-score-none">—</span>`
                                            }
                                        </td>
                                        <td>
                                            <span class="gr-status ${statusKey}">
                                                ${statusKey === 'passed'
                                                    ? `<svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>`
                                                    : statusKey === 'failed'
                                                        ? `<svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>`
                                                        : ''
                                                }
                                                ${statusLabel}
                                            </span>
                                        </td>
                                        <td style="text-align:right">
                                            <span class="gr-attempts">${q.attempts || 0}</span>
                                        </td>
                                    </tr>`;
                                }).join('')}
                            </tbody>
                          </table>`
                    }
                </div>
            </div>`;
        }).join('')}
    `;
}

function esc(str) { const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }
