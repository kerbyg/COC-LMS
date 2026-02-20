/**
 * Instructor Quizzes Page
 * CRUD for quiz management
 */
import { Api } from '../../api.js';

let subjects          = [];
let closeMenusHandler = null;

export async function render(container) {
    const subjRes = await Api.get('/LessonsAPI.php?action=subjects');
    subjects = subjRes.success ? subjRes.data : [];
    renderList(container);
}

async function renderList(container, filterSubject = '') {
    const params = filterSubject ? '&subject_id=' + filterSubject : '';
    const res = await Api.get('/QuizzesAPI.php?action=instructor-list' + params);
    const quizzes = res.success ? res.data : [];

    container.innerHTML = `
        <style>
            .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }
            .page-header .count { background:#E8F5E9; color:#1B4D3E; padding:4px 12px; border-radius:20px; font-size:13px; font-weight:600; margin-left:8px; }
            .btn-primary { background:linear-gradient(135deg,#00461B,#006428); color:#fff; border:none; padding:10px 20px; border-radius:10px; font-weight:600; font-size:14px; cursor:pointer; }
            .btn-primary:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,70,27,.3); }
            .filters { margin-bottom:20px; }
            .filters select { padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; min-width:240px; }

            .quizzes-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:16px; }
            .quiz-card { background:#fff; border:1px solid #e8e8e8; border-radius:14px; overflow:hidden; transition:all .2s; }
            .quiz-card:hover { border-color:#1B4D3E; }
            .quiz-top { padding:16px 20px; display:flex; justify-content:space-between; align-items:flex-start; }
            .quiz-title { font-size:16px; font-weight:700; color:#262626; margin-bottom:4px; }
            .quiz-subject { font-size:12px; color:#737373; }
            .quiz-subject .code { background:#E8F5E9; color:#1B4D3E; padding:2px 6px; border-radius:4px; font-family:monospace; font-size:11px; margin-right:4px; }
            .badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; text-transform:capitalize; }
            .badge-published { background:#E8F5E9; color:#1B4D3E; }
            .badge-draft { background:#FEF3C7; color:#B45309; }

            .quiz-stats { display:grid; grid-template-columns:repeat(4,1fr); padding:0 20px 16px; gap:8px; }
            .qs-item { text-align:center; background:#fafafa; padding:10px 4px; border-radius:8px; }
            .qs-num { font-size:16px; font-weight:700; color:#262626; display:block; }
            .qs-label { font-size:10px; color:#737373; text-transform:uppercase; }

            /* Kebab */
            .quiz-kebab-wrap { position:relative; flex-shrink:0; }
            .quiz-kebab { background:none; border:none; cursor:pointer; padding:3px 8px; border-radius:6px; font-size:20px; color:#bbb; line-height:1; transition:all .15s; }
            .quiz-kebab:hover { background:#f3f4f6; color:#555; }
            .quiz-kebab-menu { position:absolute; right:0; top:calc(100% + 4px); background:#fff; border:1px solid #e0e0e0; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,.1); z-index:200; min-width:160px; overflow:hidden; display:none; }
            .quiz-kebab-menu.open { display:block; }
            .quiz-kebab-item { display:flex; align-items:center; gap:8px; padding:10px 14px; font-size:13px; cursor:pointer; border:none; background:none; width:100%; text-align:left; color:#333; font-weight:500; transition:background .1s; }
            .quiz-kebab-item:hover { background:#f5f5f5; }
            .quiz-kebab-item.danger { color:#b91c1c; }
            .quiz-kebab-item.danger:hover { background:#FEE2E2; }

            .modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:1000; }
            .modal { background:#fff; border-radius:16px; width:90%; max-width:560px; max-height:90vh; overflow-y:auto; }
            .modal-header { padding:20px 24px; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; }
            .modal-header h3 { font-size:18px; font-weight:700; }
            .modal-close { background:none; border:none; font-size:24px; cursor:pointer; color:#737373; }
            .modal-body { padding:24px; }
            .modal-footer { padding:16px 24px; border-top:1px solid #f0f0f0; display:flex; justify-content:flex-end; gap:12px; }
            .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
            .form-group { margin-bottom:16px; }
            .form-group.full { grid-column:1/-1; }
            .form-label { display:block; font-size:13px; font-weight:600; color:#404040; margin-bottom:6px; }
            .form-input, .form-select, .form-textarea { width:100%; padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; box-sizing:border-box; font-family:inherit; }
            .form-input:focus, .form-select:focus { outline:none; border-color:#00461B; }
            .btn-secondary { background:#f5f5f5; color:#404040; border:1px solid #e0e0e0; padding:9px 18px; border-radius:8px; font-weight:500; cursor:pointer; font-size:14px; }
            .alert { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:14px; }
            .alert-error { background:#FEE2E2; color:#b91c1c; }
            .empty-state-sm { text-align:center; padding:40px; color:#737373; }
            @media(max-width:768px) { .quizzes-grid { grid-template-columns:1fr; } .form-grid { grid-template-columns:1fr; } .quiz-stats { grid-template-columns:1fr 1fr; } }
        </style>

        <div class="page-header">
            <h2>Quizzes <span class="count">${quizzes.length}</span></h2>
            <button class="btn-primary" id="btn-add">+ Create Quiz</button>
        </div>
        <div class="filters">
            <select id="filter-subject">
                <option value="">All Subjects</option>
                ${subjects.map(s => `<option value="${s.subject_id}" ${filterSubject==s.subject_id?'selected':''}>${esc(s.subject_code)} - ${esc(s.subject_name)}</option>`).join('')}
            </select>
        </div>

        ${quizzes.length === 0 ? '<div class="empty-state-sm">No quizzes yet. Create your first quiz!</div>' :
          `<div class="quizzes-grid">
            ${quizzes.map(q => `
                <div class="quiz-card">
                    <div class="quiz-top">
                        <div>
                            <div class="quiz-title">${esc(q.quiz_title)}</div>
                            <div class="quiz-subject"><span class="code">${esc(q.subject_code)}</span>${esc(q.subject_name)}</div>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
                            <span class="badge badge-${q.status}">${q.status}</span>
                            <div class="quiz-kebab-wrap">
                                <button class="quiz-kebab" title="More actions">⋮</button>
                                <div class="quiz-kebab-menu">
                                    <button class="quiz-kebab-item" data-questions="${q.quiz_id}">📋 Questions</button>
                                    <button class="quiz-kebab-item" data-edit="${q.quiz_id}">✏️ Edit</button>
                                    <button class="quiz-kebab-item danger" data-delete="${q.quiz_id}" data-name="${esc(q.quiz_title)}">🗑 Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="quiz-stats">
                        <div class="qs-item"><span class="qs-num">${q.question_count}</span><span class="qs-label">Questions</span></div>
                        <div class="qs-item"><span class="qs-num">${q.attempt_count||0}</span><span class="qs-label">Attempts</span></div>
                        <div class="qs-item"><span class="qs-num">${q.avg_score||'—'}</span><span class="qs-label">Avg Score</span></div>
                        <div class="qs-item"><span class="qs-num">${q.time_limit}m</span><span class="qs-label">Time</span></div>
                    </div>
                </div>
            `).join('')}
          </div>`}
    `;

    container.querySelector('#btn-add').addEventListener('click', () => openModal(container, filterSubject));
    container.querySelector('#filter-subject').addEventListener('change', (e) => renderList(container, e.target.value));

    // Kebab: close on outside click
    if (closeMenusHandler) document.removeEventListener('click', closeMenusHandler);
    closeMenusHandler = () => document.querySelectorAll('.quiz-kebab-menu.open').forEach(m => m.classList.remove('open'));
    document.addEventListener('click', closeMenusHandler);

    // Kebab toggle
    container.querySelectorAll('.quiz-kebab').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const menu = btn.nextElementSibling;
            const wasOpen = menu.classList.contains('open');
            document.querySelectorAll('.quiz-kebab-menu.open').forEach(m => m.classList.remove('open'));
            if (!wasOpen) menu.classList.add('open');
        });
    });

    container.querySelectorAll('[data-questions]').forEach(btn => {
        btn.addEventListener('click', () => {
            window.location.hash = '#instructor/quiz-questions?quiz_id=' + btn.dataset.questions;
        });
    });

    container.querySelectorAll('[data-edit]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const q = quizzes.find(q => q.quiz_id == btn.dataset.edit);
            if (q) openModal(container, filterSubject, q);
        });
    });

    container.querySelectorAll('[data-delete]').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm(`Delete "${btn.dataset.name}"?`)) return;
            const res = await Api.post('/QuizzesAPI.php?action=delete', { quiz_id: parseInt(btn.dataset.delete) });
            if (res.success) renderList(container, filterSubject);
            else alert(res.message);
        });
    });
}

function openModal(container, filterSubject, quiz = null) {
    const isEdit = !!quiz;
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    const subjOpts = subjects.map(s => `<option value="${s.subject_id}" ${quiz?quiz.subject_id==s.subject_id?'selected':'':filterSubject==s.subject_id?'selected':''}>${esc(s.subject_code)} - ${esc(s.subject_name)}</option>`).join('');

    overlay.innerHTML = `
        <div class="modal">
            <div class="modal-header"><h3>${isEdit?'Edit':'Create'} Quiz</h3><button class="modal-close">&times;</button></div>
            <div class="modal-body">
                <div id="modal-alert"></div>
                <div class="form-group"><label class="form-label">Subject *</label><select class="form-select" id="m-subject" ${isEdit?'disabled':''}><option value="">Select</option>${subjOpts}</select></div>
                <div class="form-group"><label class="form-label">Quiz Title *</label><input class="form-input" id="m-title" value="${esc(quiz?.quiz_title||'')}"></div>
                <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" id="m-desc" rows="2">${esc(quiz?.quiz_description||'')}</textarea></div>
                <div class="form-grid">
                    <div class="form-group"><label class="form-label">Time Limit (min)</label><input type="number" class="form-input" id="m-time" value="${quiz?.time_limit||30}" min="1"></div>
                    <div class="form-group"><label class="form-label">Passing Rate (%)</label><input type="number" class="form-input" id="m-pass" value="${quiz?.passing_rate||60}" min="0" max="100"></div>
                    <div class="form-group"><label class="form-label">Max Attempts</label><input type="number" class="form-input" id="m-attempts" value="${quiz?.max_attempts||3}" min="1"></div>
                    <div class="form-group"><label class="form-label">Status</label><select class="form-select" id="m-status"><option value="draft" ${quiz?.status==='draft'||!quiz?'selected':''}>Draft</option><option value="published" ${quiz?.status==='published'?'selected':''}>Published</option></select></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary modal-cancel">Cancel</button>
                <button class="btn-primary" id="modal-save">${isEdit?'Update':'Create'} Quiz</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
    overlay.querySelector('.modal-close').addEventListener('click', () => overlay.remove());
    overlay.querySelector('.modal-cancel').addEventListener('click', () => overlay.remove());
    overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });

    overlay.querySelector('#modal-save').addEventListener('click', async () => {
        const payload = {
            subject_id: isEdit ? quiz.subject_id : overlay.querySelector('#m-subject').value,
            quiz_title: overlay.querySelector('#m-title').value,
            quiz_description: overlay.querySelector('#m-desc').value,
            time_limit: parseInt(overlay.querySelector('#m-time').value),
            passing_rate: parseInt(overlay.querySelector('#m-pass').value),
            max_attempts: parseInt(overlay.querySelector('#m-attempts').value),
            status: overlay.querySelector('#m-status').value,
        };
        if (isEdit) payload.quiz_id = quiz.quiz_id;
        const action = isEdit ? 'update' : 'create';
        const res = await Api.post(`/QuizzesAPI.php?action=${action}`, payload);
        if (res.success) { overlay.remove(); renderList(container, filterSubject); }
        else overlay.querySelector('#modal-alert').innerHTML = `<div class="alert alert-error">${res.message}</div>`;
    });
}

function esc(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
