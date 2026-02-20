/**
 * Instructor Remedials Page
 * Track and manage remedial assignments for students who failed quizzes
 */
import { Api } from '../../api.js';

let subjects = [];
let quizzes = [];

export async function render(container) {
    const subjRes = await Api.get('/LessonsAPI.php?action=subjects');
    subjects = subjRes.success ? subjRes.data : [];
    renderPage(container);
}

async function renderPage(container, filterSubject = '', filterStatus = '') {
    // Fetch remedials and quizzes
    let params = '';
    if (filterSubject) params += '&subject_id=' + filterSubject;
    if (filterStatus) params += '&status=' + filterStatus;

    const [remRes, quizRes] = await Promise.all([
        Api.get('/RemedialAPI.php?action=instructor-list' + params),
        Api.get('/QuizzesAPI.php?action=instructor-list')
    ]);
    const remedials = remRes.success ? remRes.data : [];
    quizzes = quizRes.success ? quizRes.data : [];

    // Stats
    const total = remedials.length;
    const pending = remedials.filter(r => r.status === 'pending').length;
    const inProgress = remedials.filter(r => r.status === 'in_progress').length;
    const completed = remedials.filter(r => r.status === 'completed').length;

    container.innerHTML = `
        <style>
            .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }
            .btn-primary { background:linear-gradient(135deg,#00461B,#006428); color:#fff; border:none; padding:10px 20px; border-radius:10px; font-weight:600; font-size:14px; cursor:pointer; }
            .btn-primary:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,70,27,.3); }

            .stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px; }
            .stat-pill { background:#fff; border:1px solid #e8e8e8; border-radius:12px; padding:16px; text-align:center; }
            .stat-pill .num { font-size:24px; font-weight:800; }
            .stat-pill .label { font-size:12px; color:#737373; margin-top:2px; }
            .stat-pill.total .num { color:#262626; }
            .stat-pill.pending .num { color:#B45309; }
            .stat-pill.progress .num { color:#1E40AF; }
            .stat-pill.completed .num { color:#1B4D3E; }

            .filters { display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
            .filters select { padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; min-width:200px; }

            .remedial-card { background:#fff; border:1px solid #e8e8e8; border-radius:14px; padding:20px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
            .remedial-card:hover { border-color:#1B4D3E; }
            .rem-info { flex:1; min-width:200px; }
            .rem-student { font-size:15px; font-weight:700; color:#262626; margin-bottom:4px; }
            .rem-quiz { font-size:13px; color:#737373; }
            .rem-quiz .code { background:#E8F5E9; color:#1B4D3E; padding:2px 6px; border-radius:4px; font-family:monospace; font-size:11px; margin-right:4px; }
            .rem-reason { font-size:13px; color:#737373; margin-top:6px; font-style:italic; }
            .rem-meta { display:flex; gap:16px; align-items:center; }
            .rem-date { font-size:12px; color:#737373; }

            .badge { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; text-transform:capitalize; }
            .badge-pending { background:#FEF3C7; color:#B45309; }
            .badge-in_progress { background:#DBEAFE; color:#1E40AF; }
            .badge-completed { background:#E8F5E9; color:#1B4D3E; }

            .rem-actions { display:flex; gap:8px; }
            .btn-sm { padding:6px 14px; border-radius:6px; font-size:12px; font-weight:500; cursor:pointer; border:1px solid #e0e0e0; background:#fff; color:#404040; }
            .btn-sm:hover { background:#f5f5f5; }

            .modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:1000; }
            .modal { background:#fff; border-radius:16px; width:90%; max-width:520px; max-height:90vh; overflow-y:auto; }
            .modal-header { padding:20px 24px; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; }
            .modal-header h3 { font-size:18px; font-weight:700; }
            .modal-close { background:none; border:none; font-size:24px; cursor:pointer; color:#737373; }
            .modal-body { padding:24px; }
            .modal-footer { padding:16px 24px; border-top:1px solid #f0f0f0; display:flex; justify-content:flex-end; gap:12px; }
            .form-group { margin-bottom:16px; }
            .form-label { display:block; font-size:13px; font-weight:600; color:#404040; margin-bottom:6px; }
            .form-input, .form-select, .form-textarea { width:100%; padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; box-sizing:border-box; font-family:inherit; }
            .form-input:focus, .form-select:focus { outline:none; border-color:#00461B; }
            .btn-secondary { background:#f5f5f5; color:#404040; border:1px solid #e0e0e0; padding:9px 18px; border-radius:8px; font-weight:500; cursor:pointer; font-size:14px; }
            .alert { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:14px; }
            .alert-error { background:#FEE2E2; color:#b91c1c; }
            .empty-state-sm { text-align:center; padding:40px; color:#737373; }
            @media(max-width:768px) { .stats-row { grid-template-columns:1fr 1fr; } .remedial-card { flex-direction:column; align-items:flex-start; } }
        </style>

        <div class="page-header">
            <h2>Remedials</h2>
            <button class="btn-primary" id="btn-create">+ Create Remedial</button>
        </div>

        <div class="stats-row">
            <div class="stat-pill total"><div class="num">${total}</div><div class="label">Total</div></div>
            <div class="stat-pill pending"><div class="num">${pending}</div><div class="label">Pending</div></div>
            <div class="stat-pill progress"><div class="num">${inProgress}</div><div class="label">In Progress</div></div>
            <div class="stat-pill completed"><div class="num">${completed}</div><div class="label">Completed</div></div>
        </div>

        <div class="filters">
            <select id="filter-subject">
                <option value="">All Subjects</option>
                ${subjects.map(s => `<option value="${s.subject_id}" ${filterSubject==s.subject_id?'selected':''}>${esc(s.subject_code)} - ${esc(s.subject_name)}</option>`).join('')}
            </select>
            <select id="filter-status">
                <option value="" ${!filterStatus?'selected':''}>All Status</option>
                <option value="pending" ${filterStatus==='pending'?'selected':''}>Pending</option>
                <option value="in_progress" ${filterStatus==='in_progress'?'selected':''}>In Progress</option>
                <option value="completed" ${filterStatus==='completed'?'selected':''}>Completed</option>
            </select>
        </div>

        <div id="remedial-list">
            ${remedials.length === 0 ? '<div class="empty-state-sm">No remedial assignments found</div>' :
              remedials.map(r => `
                <div class="remedial-card">
                    <div class="rem-info">
                        <div class="rem-student">${esc(r.first_name)} ${esc(r.last_name)}</div>
                        <div class="rem-quiz"><span class="code">${esc(r.subject_code||'')}</span>${esc(r.quiz_title)}</div>
                        ${r.reason ? `<div class="rem-reason">${esc(r.reason)}</div>` : ''}
                    </div>
                    <div class="rem-meta">
                        <span class="badge badge-${r.status}">${r.status.replace('_',' ')}</span>
                        ${r.due_date ? `<span class="rem-date">Due: ${new Date(r.due_date).toLocaleDateString('en-US',{month:'short',day:'numeric'})}</span>` : ''}
                        ${r.status !== 'completed' ? `
                        <div class="rem-actions">
                            <button class="btn-sm" data-update="${r.remedial_id}" data-status="${r.status}">Update</button>
                        </div>` : ''}
                    </div>
                </div>
              `).join('')}
        </div>
    `;

    // Event listeners
    container.querySelector('#filter-subject').addEventListener('change', (e) => renderPage(container, e.target.value, filterStatus));
    container.querySelector('#filter-status').addEventListener('change', (e) => renderPage(container, filterSubject, e.target.value));
    container.querySelector('#btn-create').addEventListener('click', () => openCreateModal(container, filterSubject, filterStatus));

    container.querySelectorAll('[data-update]').forEach(btn => {
        btn.addEventListener('click', () => openUpdateModal(container, filterSubject, filterStatus, btn.dataset.update, btn.dataset.status));
    });
}

function openCreateModal(container, filterSubject, filterStatus) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    const quizOpts = quizzes.filter(q => q.status === 'published').map(q =>
        `<option value="${q.quiz_id}">${esc(q.subject_code)} - ${esc(q.quiz_title)}</option>`
    ).join('');

    overlay.innerHTML = `
        <div class="modal">
            <div class="modal-header"><h3>Create Remedial</h3><button class="modal-close">&times;</button></div>
            <div class="modal-body">
                <div id="modal-alert"></div>
                <div class="form-group"><label class="form-label">Quiz *</label><select class="form-select" id="m-quiz"><option value="">Select Quiz</option>${quizOpts}</select></div>
                <div class="form-group"><label class="form-label">Student ID *</label><input class="form-input" id="m-student" placeholder="Enter student user ID"></div>
                <div class="form-group"><label class="form-label">Reason</label><textarea class="form-textarea" id="m-reason" rows="2" placeholder="Why is this remedial being assigned?"></textarea></div>
                <div class="form-group"><label class="form-label">Due Date</label><input type="date" class="form-input" id="m-due"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary modal-cancel">Cancel</button>
                <button class="btn-primary" id="modal-save">Create</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
    const close = () => overlay.remove();
    overlay.querySelector('.modal-close').addEventListener('click', close);
    overlay.querySelector('.modal-cancel').addEventListener('click', close);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });

    overlay.querySelector('#modal-save').addEventListener('click', async () => {
        const payload = {
            quiz_id: parseInt(overlay.querySelector('#m-quiz').value),
            user_student_id: parseInt(overlay.querySelector('#m-student').value),
            reason: overlay.querySelector('#m-reason').value,
            due_date: overlay.querySelector('#m-due').value || null
        };
        if (!payload.quiz_id || !payload.user_student_id) {
            overlay.querySelector('#modal-alert').innerHTML = '<div class="alert alert-error">Quiz and Student are required</div>';
            return;
        }
        const res = await Api.post('/RemedialAPI.php?action=create', payload);
        if (res.success) { close(); renderPage(container, filterSubject, filterStatus); }
        else overlay.querySelector('#modal-alert').innerHTML = `<div class="alert alert-error">${res.message}</div>`;
    });
}

function openUpdateModal(container, filterSubject, filterStatus, remedialId, currentStatus) {
    const nextStatus = currentStatus === 'pending' ? 'in_progress' : 'completed';
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';

    overlay.innerHTML = `
        <div class="modal">
            <div class="modal-header"><h3>Update Remedial</h3><button class="modal-close">&times;</button></div>
            <div class="modal-body">
                <div id="modal-alert"></div>
                <div class="form-group"><label class="form-label">Status</label>
                    <select class="form-select" id="m-status">
                        <option value="pending" ${currentStatus==='pending'?'selected':''}>Pending</option>
                        <option value="in_progress" ${currentStatus==='in_progress'?'selected':''}>In Progress</option>
                        <option value="completed" ${nextStatus==='completed'?'selected':''}>Completed</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Remarks</label><textarea class="form-textarea" id="m-remarks" rows="2" placeholder="Add notes..."></textarea></div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary modal-cancel">Cancel</button>
                <button class="btn-primary" id="modal-save">Update</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
    const close = () => overlay.remove();
    overlay.querySelector('.modal-close').addEventListener('click', close);
    overlay.querySelector('.modal-cancel').addEventListener('click', close);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });

    overlay.querySelector('#modal-save').addEventListener('click', async () => {
        const payload = {
            remedial_id: parseInt(remedialId),
            status: overlay.querySelector('#m-status').value,
            remarks: overlay.querySelector('#m-remarks').value
        };
        const res = await Api.post('/RemedialAPI.php?action=update', payload);
        if (res.success) { close(); renderPage(container, filterSubject, filterStatus); }
        else overlay.querySelector('#modal-alert').innerHTML = `<div class="alert alert-error">${res.message}</div>`;
    });
}

function esc(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
