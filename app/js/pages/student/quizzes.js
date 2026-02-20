/**
 * Student Quizzes Page
 * View available quizzes across enrolled subjects
 */
import { Api } from '../../api.js';

export async function render(container) {
    const params = new URLSearchParams(window.location.hash.split('?')[1] || '');
    const filterSubject = params.get('subject_id') || '';

    const [quizRes, subjRes] = await Promise.all([
        Api.get('/ProgressAPI.php?action=student-quizzes' + (filterSubject ? '&subject_id='+filterSubject : '')),
        Api.get('/EnrollmentAPI.php?action=my-subjects')
    ]);
    const quizzes = quizRes.success ? quizRes.data : [];
    const subjects = subjRes.success ? subjRes.data : [];

    // Group by subject
    const grouped = {};
    quizzes.forEach(q => {
        const key = q.subject_code;
        if (!grouped[key]) grouped[key] = { code: q.subject_code, name: q.subject_name, quizzes: [] };
        grouped[key].quizzes.push(q);
    });

    container.innerHTML = `
        <style>
            .page-header { margin-bottom:24px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }
            .filters { display:flex; gap:12px; margin-bottom:20px; }
            .filters select { padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; min-width:220px; }

            .subject-group { margin-bottom:24px; }
            .sg-header { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
            .sg-code { background:#E8F5E9; color:#1B4D3E; padding:4px 10px; border-radius:6px; font-family:monospace; font-size:13px; font-weight:700; }
            .sg-name { font-size:16px; font-weight:700; color:#262626; }

            .quiz-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:14px; }
            .quiz-card { background:#fff; border:1px solid #e8e8e8; border-radius:12px; overflow:hidden; transition:all .2s; }
            .quiz-card:hover { border-color:#1B4D3E; }
            .qc-top { padding:16px 18px; }
            .qc-badges { display:flex; gap:6px; margin-bottom:8px; flex-wrap:wrap; }
            .badge { padding:3px 8px; border-radius:12px; font-size:10px; font-weight:700; text-transform:uppercase; }
            .badge-pre { background:#DBEAFE; color:#1E40AF; }
            .badge-post { background:#EDE9FE; color:#6D28D9; }
            .badge-regular { background:#f5f5f5; color:#404040; }
            .badge-passed { background:#E8F5E9; color:#1B4D3E; }
            .badge-attempted { background:#FEF3C7; color:#B45309; }
            .badge-available { background:#DBEAFE; color:#1E40AF; }
            .qc-title { font-size:15px; font-weight:700; color:#262626; margin-bottom:4px; }
            .qc-desc { font-size:13px; color:#737373; margin-bottom:10px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
            .qc-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
            .qs-item { text-align:center; }
            .qs-num { font-size:14px; font-weight:700; color:#262626; display:block; }
            .qs-label { font-size:10px; color:#737373; }

            .qc-score { padding:12px 18px; border-top:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; }
            .score-display { font-size:20px; font-weight:800; }
            .score-display.pass { color:#1B4D3E; }
            .score-display.fail { color:#b91c1c; }
            .score-display.none { color:#737373; font-size:14px; font-weight:400; }
            .btn-take { padding:8px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; border:none; background:#1B4D3E; color:#fff; text-decoration:none; }
            .btn-take:hover { background:#006428; }
            .btn-take.disabled { background:#e0e0e0; color:#737373; cursor:not-allowed; }

            .empty-state-sm { text-align:center; padding:40px; color:#737373; }
        </style>

        <div class="page-header"><h2>Quizzes</h2></div>
        <div class="filters">
            <select id="filter-subject">
                <option value="">All Subjects</option>
                ${subjects.map(s => `<option value="${s.subject_id}" ${filterSubject==s.subject_id?'selected':''}>${esc(s.subject_code)} - ${esc(s.subject_name)}</option>`).join('')}
            </select>
        </div>

        ${Object.keys(grouped).length === 0 ? '<div class="empty-state-sm">No quizzes available yet</div>' :
          Object.values(grouped).map(g => `
            <div class="subject-group">
                <div class="sg-header">
                    <span class="sg-code">${esc(g.code)}</span>
                    <span class="sg-name">${esc(g.name)}</span>
                </div>
                <div class="quiz-grid">
                    ${g.quizzes.map(q => {
                        const typeBadge = q.quiz_type === 'pre_test' ? 'pre' : q.quiz_type === 'post_test' ? 'post' : 'regular';
                        const typeLabel = q.quiz_type === 'pre_test' ? 'Pre-Test' : q.quiz_type === 'post_test' ? 'Post-Test' : 'Quiz';
                        const statusBadge = q.quiz_status || 'available';
                        const hasScore = q.best_score !== null;
                        return `
                        <div class="quiz-card">
                            <div class="qc-top">
                                <div class="qc-badges">
                                    <span class="badge badge-${typeBadge}">${typeLabel}</span>
                                    <span class="badge badge-${statusBadge}">${statusBadge}</span>
                                </div>
                                <div class="qc-title">${esc(q.quiz_title)}</div>
                                ${q.quiz_description ? `<div class="qc-desc">${esc(q.quiz_description)}</div>` : ''}
                                <div class="qc-stats">
                                    <div class="qs-item"><span class="qs-num">${q.question_count||0}</span><span class="qs-label">Questions</span></div>
                                    <div class="qs-item"><span class="qs-num">${q.time_limit||0}m</span><span class="qs-label">Time</span></div>
                                    <div class="qs-item"><span class="qs-num">${q.passing_rate||0}%</span><span class="qs-label">Passing</span></div>
                                </div>
                            </div>
                            <div class="qc-score">
                                ${hasScore ?
                                    `<span class="score-display ${q.passed==1?'pass':'fail'}">${parseFloat(q.best_score).toFixed(1)}%</span>` :
                                    `<span class="score-display none">Not taken</span>`}
                                ${q.can_take ?
                                    `<a class="btn-take" href="#student/take-quiz?quiz_id=${q.quiz_id}">Take Quiz (${q.attempts_remaining} left)</a>` :
                                    `<span class="btn-take disabled">${hasScore ? 'No attempts left' : 'Unavailable'}</span>`}
                            </div>
                        </div>`;
                    }).join('')}
                </div>
            </div>
          `).join('')}
    `;

    container.querySelector('#filter-subject').addEventListener('change', (e) => {
        window.location.hash = e.target.value ? `#student/quizzes?subject_id=${e.target.value}` : '#student/quizzes';
    });
}

function esc(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
