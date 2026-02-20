/**
 * Admin Subject Offerings Page
 * CRUD for subject offerings
 */
import { Api } from '../../api.js';

let subjects = [];
let semesters = [];

export async function render(container) {
    const [subjRes, semRes] = await Promise.all([
        Api.get('/SubjectOfferingsAPI.php?action=subjects'),
        Api.get('/SubjectOfferingsAPI.php?action=semesters')
    ]);
    subjects = subjRes.success ? subjRes.data : [];
    semesters = semRes.success ? semRes.data : [];
    renderList(container);
}

async function renderList(container, semFilter = '') {
    const params = semFilter ? '&semester_id=' + semFilter : '';
    const result = await Api.get('/SubjectOfferingsAPI.php?action=list' + params);
    const offerings = result.success ? result.data : [];

    container.innerHTML = `
        <style>
            .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }
            .page-header .count { background:#E8F5E9; color:#1B4D3E; padding:4px 12px; border-radius:20px; font-size:13px; font-weight:600; margin-left:8px; }
            .btn-primary { background:linear-gradient(135deg,#00461B,#006428); color:#fff; border:none; padding:10px 20px; border-radius:10px; font-weight:600; font-size:14px; cursor:pointer; transition:all .2s; }
            .btn-primary:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,70,27,.3); }

            .filters { display:flex; gap:12px; margin-bottom:20px; align-items:center; }
            .filters select { padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; background:#fff; min-width:220px; }

            .data-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; border:1px solid #e8e8e8; }
            .data-table th { text-align:left; padding:14px 16px; font-size:12px; font-weight:600; color:#737373; text-transform:uppercase; letter-spacing:.5px; background:#fafafa; border-bottom:1px solid #e8e8e8; }
            .data-table td { padding:14px 16px; border-bottom:1px solid #f5f5f5; font-size:14px; }
            .data-table tr:last-child td { border-bottom:none; }
            .data-table tr:hover td { background:#fafafa; }

            .subj-code { background:#E8F5E9; color:#1B4D3E; padding:3px 8px; border-radius:4px; font-family:monospace; font-size:12px; font-weight:600; margin-right:8px; }
            .meta-badge { background:#f3f4f6; color:#404040; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:500; display:inline-block; margin-right:4px; }
            .badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; text-transform:capitalize; }
            .badge-open { background:#E8F5E9; color:#1B4D3E; }
            .badge-closed { background:#FEE2E2; color:#b91c1c; }
            .badge-cancelled { background:#f3f4f6; color:#737373; }

            .actions-cell { position:relative; }
            .btn-actions { background:none; border:1px solid #e0e0e0; width:32px; height:32px; border-radius:8px; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; }
            .btn-actions:hover { background:#f5f5f5; }
            .actions-dropdown { display:none; position:absolute; right:0; top:100%; background:#fff; border:1px solid #e8e8e8; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.12); min-width:160px; z-index:50; overflow:hidden; }
            .actions-dropdown.show { display:block; }
            .actions-dropdown a { display:flex; align-items:center; gap:8px; padding:10px 16px; font-size:13px; color:#404040; cursor:pointer; text-decoration:none; }
            .actions-dropdown a:hover { background:#f5f5f5; }
            .actions-dropdown a.danger { color:#b91c1c; }
            .actions-dropdown .divider { height:1px; background:#f0f0f0; margin:4px 0; }

            .modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:1000; }
            .modal { background:#fff; border-radius:16px; width:90%; max-width:480px; max-height:90vh; overflow-y:auto; }
            .modal-header { padding:20px 24px; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; }
            .modal-header h3 { font-size:18px; font-weight:700; color:#262626; }
            .modal-close { background:none; border:none; font-size:24px; cursor:pointer; color:#737373; padding:0; line-height:1; }
            .modal-body { padding:24px; }
            .modal-footer { padding:16px 24px; border-top:1px solid #f0f0f0; display:flex; justify-content:flex-end; gap:12px; }
            .form-group { margin-bottom:16px; }
            .form-label { display:block; font-size:13px; font-weight:600; color:#404040; margin-bottom:6px; }
            .form-select { width:100%; padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; box-sizing:border-box; }
            .form-select:focus { outline:none; border-color:#00461B; box-shadow:0 0 0 3px rgba(0,70,27,.1); }
            .radio-group { display:flex; gap:16px; margin-top:6px; }
            .radio-group label { display:flex; align-items:center; gap:6px; font-size:14px; cursor:pointer; }
            .btn-secondary { background:#f5f5f5; color:#404040; border:1px solid #e0e0e0; padding:9px 18px; border-radius:8px; font-weight:500; cursor:pointer; font-size:14px; }
            .alert { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:14px; }
            .alert-error { background:#FEE2E2; color:#b91c1c; border:1px solid #FECACA; }
            .empty-state-sm { text-align:center; padding:40px; color:#737373; }
        </style>

        <div class="page-header">
            <h2>Subject Offerings <span class="count">${offerings.length}</span></h2>
            <button class="btn-primary" id="btn-add">+ Add Offering</button>
        </div>

        <div class="filters">
            <select id="filter-sem">
                <option value="">All Semesters</option>
                ${semesters.map(s => `<option value="${s.semester_id}" ${semFilter==s.semester_id?'selected':''}>${esc(s.semester_name)} - ${esc(s.academic_year)}</option>`).join('')}
            </select>
        </div>

        <table class="data-table">
            <thead><tr><th>Subject</th><th>Semester</th><th>Units</th><th>Sections</th><th>Instructors</th><th>Students</th><th>Status</th><th></th></tr></thead>
            <tbody>
                ${offerings.length === 0 ? '<tr><td colspan="8"><div class="empty-state-sm">No offerings found</div></td></tr>' :
                  offerings.map(o => `
                    <tr>
                        <td><span class="subj-code">${esc(o.subject_code)}</span>${esc(o.subject_name)}</td>
                        <td>${esc(o.semester_name || '—')} <span style="color:#737373;font-size:12px">${esc(o.academic_year || '')}</span></td>
                        <td><span class="meta-badge">${o.units}</span></td>
                        <td><span class="meta-badge">${o.section_count}</span></td>
                        <td><span class="meta-badge">${o.instructor_count}</span></td>
                        <td><span class="meta-badge">${o.student_count}</span></td>
                        <td><span class="badge badge-${o.status}">${o.status}</span></td>
                        <td class="actions-cell">
                            <button class="btn-actions" data-id="${o.subject_offered_id}">⋮</button>
                            <div class="actions-dropdown" data-dropdown="${o.subject_offered_id}">
                                <a href="#" data-toggle="${o.subject_offered_id}" data-status="${o.status === 'open' ? 'closed' : 'open'}">${o.status === 'open' ? '🔒 Close' : '🔓 Open'}</a>
                                <div class="divider"></div>
                                <a href="#" class="danger" data-cancel="${o.subject_offered_id}" data-name="${esc(o.subject_code)}">🗑️ Cancel</a>
                            </div>
                        </td>
                    </tr>
                  `).join('')}
            </tbody>
        </table>
    `;

    // Events
    container.querySelector('#btn-add').addEventListener('click', () => openModal(container, semFilter));

    container.querySelector('#filter-sem').addEventListener('change', (e) => renderList(container, e.target.value));

    container.querySelectorAll('.btn-actions').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            container.querySelectorAll('.actions-dropdown').forEach(d => d.classList.remove('show'));
            container.querySelector(`[data-dropdown="${btn.dataset.id}"]`).classList.toggle('show');
        });
    });
    document.addEventListener('click', () => container.querySelectorAll('.actions-dropdown').forEach(d => d.classList.remove('show')), { once: true });

    container.querySelectorAll('[data-toggle]').forEach(a => {
        a.addEventListener('click', async (e) => {
            e.preventDefault();
            const res = await Api.post('/SubjectOfferingsAPI.php?action=update', { subject_offered_id: parseInt(a.dataset.toggle), status: a.dataset.status });
            if (res.success) renderList(container, semFilter);
            else alert(res.message);
        });
    });

    container.querySelectorAll('[data-cancel]').forEach(a => {
        a.addEventListener('click', async (e) => {
            e.preventDefault();
            if (!confirm(`Cancel offering "${a.dataset.name}"?`)) return;
            const res = await Api.post('/SubjectOfferingsAPI.php?action=delete', { subject_offered_id: parseInt(a.dataset.cancel) });
            if (res.success) renderList(container, semFilter);
            else alert(res.message);
        });
    });
}

function openModal(container, semFilter) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';

    overlay.innerHTML = `
        <div class="modal">
            <div class="modal-header"><h3>Add Subject Offering</h3><button class="modal-close">&times;</button></div>
            <div class="modal-body">
                <div id="modal-alert"></div>
                <div class="form-group">
                    <label class="form-label">Subject *</label>
                    <select class="form-select" id="m-subject"><option value="">Select Subject</option>${subjects.map(s => `<option value="${s.subject_id}">${esc(s.subject_code)} - ${esc(s.subject_name)}</option>`).join('')}</select>
                </div>
                <div class="form-group">
                    <label class="form-label">Semester *</label>
                    <select class="form-select" id="m-semester"><option value="">Select Semester</option>${semesters.map(s => `<option value="${s.semester_id}">${esc(s.semester_name)} - ${esc(s.academic_year)}</option>`).join('')}</select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div class="radio-group">
                        <label><input type="radio" name="m-status" value="open" checked> Open</label>
                        <label><input type="radio" name="m-status" value="closed"> Closed</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary modal-cancel">Cancel</button>
                <button class="btn-primary" id="modal-save">Create Offering</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
    overlay.querySelector('.modal-close').addEventListener('click', () => overlay.remove());
    overlay.querySelector('.modal-cancel').addEventListener('click', () => overlay.remove());
    overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });

    overlay.querySelector('#modal-save').addEventListener('click', async () => {
        const payload = {
            subject_id: overlay.querySelector('#m-subject').value,
            semester_id: overlay.querySelector('#m-semester').value,
            status: overlay.querySelector('input[name="m-status"]:checked').value,
        };
        const res = await Api.post('/SubjectOfferingsAPI.php?action=create', payload);
        if (res.success) { overlay.remove(); renderList(container, semFilter); }
        else overlay.querySelector('#modal-alert').innerHTML = `<div class="alert alert-error">${res.message}</div>`;
    });
}

function esc(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}
