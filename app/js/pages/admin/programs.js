/**
 * Admin Programs Page
 * Full CRUD for program management
 */
import { Api } from '../../api.js';

let departments = [];

export async function render(container) {
    const deptRes = await Api.get('/ProgramsAPI.php?action=departments');
    departments = deptRes.success ? deptRes.data : [];
    await renderList(container);
}

async function renderList(container) {
    const result = await Api.get('/ProgramsAPI.php?action=list');
    const programs = result.success ? result.data : [];

    container.innerHTML = `
        <style>
            .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }
            .page-header .count { background:#E8F5E9; color:#1B4D3E; padding:4px 12px; border-radius:20px; font-size:13px; font-weight:600; margin-left:8px; }
            .btn-primary { background:linear-gradient(135deg,#00461B,#006428); color:#fff; border:none; padding:10px 20px; border-radius:10px; font-weight:600; font-size:14px; cursor:pointer; transition:all .2s; }
            .btn-primary:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,70,27,.3); }

            .programs-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:20px; }
            .program-card { background:#fff; border:1px solid #e8e8e8; border-radius:16px; overflow:hidden; transition:all .2s; }
            .program-card:hover { border-color:#00461B; box-shadow:0 4px 12px rgba(0,0,0,.08); }
            .program-card-header { padding:20px 20px 0; display:flex; justify-content:space-between; align-items:flex-start; }
            .program-code { background:#E8F5E9; color:#1B4D3E; padding:5px 12px; border-radius:8px; font-family:monospace; font-weight:700; font-size:14px; letter-spacing:.5px; }
            .badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; text-transform:capitalize; }
            .badge-active { background:#E8F5E9; color:#1B4D3E; }
            .badge-inactive { background:#FEE2E2; color:#b91c1c; }
            .program-card-body { padding:16px 20px 20px; }
            .program-name { font-size:17px; font-weight:600; color:#262626; margin-bottom:4px; }
            .program-dept { font-size:14px; color:#737373; margin-bottom:16px; }
            .program-stats { display:flex; gap:16px; }
            .stat-item { text-align:center; flex:1; padding:10px; background:#fafafa; border-radius:8px; }
            .stat-num { display:block; font-size:20px; font-weight:700; color:#262626; }
            .stat-label { display:block; font-size:11px; color:#737373; margin-top:2px; }

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
            .modal { background:#fff; border-radius:16px; width:90%; max-width:560px; max-height:90vh; overflow-y:auto; }
            .modal-header { padding:20px 24px; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; }
            .modal-header h3 { font-size:18px; font-weight:700; color:#262626; }
            .modal-close { background:none; border:none; font-size:24px; cursor:pointer; color:#737373; padding:0; line-height:1; }
            .modal-body { padding:24px; }
            .modal-footer { padding:16px 24px; border-top:1px solid #f0f0f0; display:flex; justify-content:flex-end; gap:12px; }
            .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
            .form-group { margin-bottom:16px; }
            .form-group.full { grid-column:1/-1; }
            .form-label { display:block; font-size:13px; font-weight:600; color:#404040; margin-bottom:6px; }
            .form-input, .form-select, .form-textarea { width:100%; padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; box-sizing:border-box; font-family:inherit; }
            .form-input:focus, .form-select:focus, .form-textarea:focus { outline:none; border-color:#00461B; box-shadow:0 0 0 3px rgba(0,70,27,.1); }
            .btn-secondary { background:#f5f5f5; color:#404040; border:1px solid #e0e0e0; padding:9px 18px; border-radius:8px; font-weight:500; cursor:pointer; font-size:14px; }
            .btn-secondary:hover { background:#e8e8e8; }
            .alert { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:14px; }
            .alert-error { background:#FEE2E2; color:#b91c1c; border:1px solid #FECACA; }
            .empty-state-sm { text-align:center; padding:40px; color:#737373; }
            @media(max-width:768px) { .form-grid { grid-template-columns:1fr; } .programs-grid { grid-template-columns:1fr; } }
        </style>

        <div class="page-header">
            <h2>Programs <span class="count">${programs.length}</span></h2>
            <button class="btn-primary" id="btn-add">+ Add Program</button>
        </div>

        ${programs.length === 0 ? '<div class="empty-state-sm">No programs found. Add your first program!</div>' :
          `<div class="programs-grid">
            ${programs.map(p => `
                <div class="program-card">
                    <div class="program-card-header">
                        <span class="program-code">${esc(p.program_code)}</span>
                        <div class="actions-cell">
                            <button class="btn-actions" data-id="${p.program_id}">⋮</button>
                            <div class="actions-dropdown" data-dropdown="${p.program_id}">
                                <a href="#" data-edit="${p.program_id}">✏️ Edit</a>
                                <div class="divider"></div>
                                <a href="#" class="danger" data-delete="${p.program_id}" data-name="${esc(p.program_name)}">🗑️ Deactivate</a>
                            </div>
                        </div>
                    </div>
                    <div class="program-card-body">
                        <div class="program-name">${esc(p.program_name)}</div>
                        <div class="program-dept">${esc(p.department_name || 'No department')} · <span class="badge badge-${p.status}">${p.status}</span></div>
                        <div class="program-stats">
                            <div class="stat-item">
                                <span class="stat-num">${p.student_count || 0}</span>
                                <span class="stat-label">Students</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-num">${p.total_units || 0}</span>
                                <span class="stat-label">Total Units</span>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('')}
          </div>`}
    `;

    // Events
    container.querySelector('#btn-add').addEventListener('click', () => openModal(container));

    container.querySelectorAll('.btn-actions').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            container.querySelectorAll('.actions-dropdown').forEach(d => d.classList.remove('show'));
            container.querySelector(`[data-dropdown="${btn.dataset.id}"]`).classList.toggle('show');
        });
    });
    document.addEventListener('click', () => container.querySelectorAll('.actions-dropdown').forEach(d => d.classList.remove('show')), { once: true });

    container.querySelectorAll('[data-edit]').forEach(a => {
        a.addEventListener('click', async (e) => {
            e.preventDefault();
            const res = await Api.get('/ProgramsAPI.php?action=get&id=' + a.dataset.edit);
            if (res.success) openModal(container, res.data);
        });
    });

    container.querySelectorAll('[data-delete]').forEach(a => {
        a.addEventListener('click', async (e) => {
            e.preventDefault();
            if (!confirm(`Deactivate "${a.dataset.name}"?`)) return;
            const res = await Api.post('/ProgramsAPI.php?action=delete', { program_id: parseInt(a.dataset.delete) });
            if (res.success) renderList(container);
            else alert(res.message);
        });
    });
}

function openModal(container, prog = null) {
    const isEdit = !!prog;
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';

    const deptOpts = departments.map(d => `<option value="${d.department_id}" ${prog && prog.department_id==d.department_id?'selected':''}>${esc(d.department_name)}</option>`).join('');

    overlay.innerHTML = `
        <div class="modal">
            <div class="modal-header">
                <h3>${isEdit ? 'Edit Program' : 'Add Program'}</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="modal-alert"></div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Program Code *</label>
                        <input class="form-input" id="m-code" value="${esc(prog?.program_code||'')}" placeholder="e.g., BSIT">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Units</label>
                        <input type="number" class="form-input" id="m-units" value="${prog?.total_units||150}" min="0">
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Program Name *</label>
                        <input class="form-input" id="m-name" value="${esc(prog?.program_name||'')}" placeholder="e.g., Bachelor of Science in Information Technology">
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Department *</label>
                        <select class="form-select" id="m-dept"><option value="">Select Department</option>${deptOpts}</select>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Description</label>
                        <textarea class="form-textarea" id="m-desc" rows="3">${esc(prog?.description||'')}</textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="m-status">
                            <option value="active" ${prog?.status==='active'||!prog?'selected':''}>Active</option>
                            <option value="inactive" ${prog?.status==='inactive'?'selected':''}>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary modal-cancel">Cancel</button>
                <button class="btn-primary" id="modal-save">${isEdit ? 'Update' : 'Create'} Program</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
    overlay.querySelector('.modal-close').addEventListener('click', () => overlay.remove());
    overlay.querySelector('.modal-cancel').addEventListener('click', () => overlay.remove());
    overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });

    overlay.querySelector('#modal-save').addEventListener('click', async () => {
        const payload = {
            program_code: overlay.querySelector('#m-code').value,
            program_name: overlay.querySelector('#m-name').value,
            total_units: parseInt(overlay.querySelector('#m-units').value) || 150,
            department_id: overlay.querySelector('#m-dept').value,
            description: overlay.querySelector('#m-desc').value,
            status: overlay.querySelector('#m-status').value,
        };
        if (isEdit) payload.program_id = prog.program_id;

        const action = isEdit ? 'update' : 'create';
        const res = await Api.post(`/ProgramsAPI.php?action=${action}`, payload);

        if (res.success) {
            overlay.remove();
            renderList(container);
        } else {
            overlay.querySelector('#modal-alert').innerHTML = `<div class="alert alert-error">${res.message}</div>`;
        }
    });
}

function esc(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}
