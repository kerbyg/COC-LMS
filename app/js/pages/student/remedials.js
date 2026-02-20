/**
 * Student Remedials Page
 * View assigned remedial tasks
 */
import { Api } from '../../api.js';

export async function render(container) {
    // Fetch remedials for the student
    const res = await Api.get('/RemedialAPI.php?action=student-list');
    const remedials = res.success ? res.data : [];

    const pending = remedials.filter(r => r.status === 'pending').length;
    const inProgress = remedials.filter(r => r.status === 'in_progress').length;
    const completed = remedials.filter(r => r.status === 'completed').length;

    container.innerHTML = `
        <style>
            .page-header { margin-bottom:24px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }

            .stats-row { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:24px; }
            .stat-pill { background:#fff; border:1px solid #e8e8e8; border-radius:12px; padding:16px; text-align:center; }
            .stat-pill .num { font-size:24px; font-weight:800; }
            .stat-pill .label { font-size:12px; color:#737373; }
            .stat-pill.pending .num { color:#B45309; }
            .stat-pill.progress .num { color:#1E40AF; }
            .stat-pill.completed .num { color:#1B4D3E; }

            .rem-list { display:flex; flex-direction:column; gap:12px; }
            .rem-card { background:#fff; border:1px solid #e8e8e8; border-radius:12px; padding:18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
            .rem-card:hover { border-color:#1B4D3E; }
            .rem-info { flex:1; min-width:200px; }
            .rem-quiz { font-size:15px; font-weight:700; color:#262626; margin-bottom:4px; }
            .rem-subject { font-size:13px; color:#737373; }
            .rem-subject .code { background:#E8F5E9; color:#1B4D3E; padding:2px 6px; border-radius:4px; font-family:monospace; font-size:11px; margin-right:4px; }
            .rem-reason { font-size:13px; color:#737373; font-style:italic; margin-top:6px; }
            .rem-meta { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
            .badge { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; text-transform:capitalize; }
            .badge-pending { background:#FEF3C7; color:#B45309; }
            .badge-in_progress { background:#DBEAFE; color:#1E40AF; }
            .badge-completed { background:#E8F5E9; color:#1B4D3E; }
            .rem-due { font-size:12px; color:#737373; }
            .btn-retake { padding:7px 16px; border-radius:8px; font-size:12px; font-weight:600; background:#1B4D3E; color:#fff; text-decoration:none; border:none; cursor:pointer; }
            .btn-study { padding:7px 16px; border-radius:8px; font-size:12px; font-weight:600; background:#FEF3C7; color:#92400E; text-decoration:none; border:1px solid #FDE68A; }

            .empty-state-sm { text-align:center; padding:40px; color:#737373; }
            @media(max-width:768px) { .stats-row { grid-template-columns:1fr; } .rem-card { flex-direction:column; align-items:flex-start; } }
        </style>

        <div class="page-header"><h2>Remedials</h2></div>

        <div class="stats-row">
            <div class="stat-pill pending"><div class="num">${pending}</div><div class="label">Pending</div></div>
            <div class="stat-pill progress"><div class="num">${inProgress}</div><div class="label">In Progress</div></div>
            <div class="stat-pill completed"><div class="num">${completed}</div><div class="label">Completed</div></div>
        </div>

        <div class="rem-list">
            ${remedials.length === 0 ? '<div class="empty-state-sm">No remedial assignments. Keep up the good work!</div>' :
              remedials.map(r => `
                <div class="rem-card">
                    <div class="rem-info">
                        <div class="rem-quiz">${esc(r.quiz_title)}</div>
                        <div class="rem-subject"><span class="code">${esc(r.subject_code||'')}</span>${esc(r.subject_name||'')}</div>
                        ${r.reason ? `<div class="rem-reason">${esc(r.reason)}</div>` : ''}
                        ${r.linked_lessons_id && r.status !== 'completed' ? `
                            <div style="margin-top:8px;font-size:12px;color:#737373;">
                                📖 <strong>Review lesson first:</strong>
                                <a class="rem-lesson-link" href="#student/lesson-view?id=${r.linked_lessons_id}" style="display:inline;margin-left:4px;">
                                    ${esc(r.linked_lesson_title||'View Lesson')}
                                </a>
                            </div>` : ''}
                    </div>
                    <div class="rem-meta">
                        <span class="badge badge-${r.status}">${r.status.replace('_',' ')}</span>
                        ${r.due_date ? `<span class="rem-due">Due: ${new Date(r.due_date).toLocaleDateString('en-US',{month:'short',day:'numeric'})}</span>` : ''}
                        ${r.status !== 'completed' ? `
                            ${r.linked_lessons_id ? `<a class="btn-study" href="#student/lesson-view?id=${r.linked_lessons_id}">📖 Study Again</a>` : ''}
                            <a class="btn-retake" href="#student/take-quiz?quiz_id=${r.quiz_id}">🔄 Retake Quiz</a>
                        ` : ''}
                    </div>
                </div>
              `).join('')}
        </div>
    `;
}

function esc(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
