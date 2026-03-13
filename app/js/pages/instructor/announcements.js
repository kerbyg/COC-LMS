/**
 * Instructor Announcements Page
 * Create, view, and manage announcements for classes
 */
import { Api } from '../../api.js';

let subjects = [];

export async function render(container) {
    const subjRes = await Api.get('/LessonsAPI.php?action=subjects');
    subjects = subjRes.success ? subjRes.data : [];
    renderList(container);
}

async function renderList(container, filterSubject = '', filterStatus = '') {
    let params = '';
    if (filterSubject) params += '&subject_id=' + filterSubject;
    if (filterStatus) params += '&status=' + filterStatus;

    const res = await Api.get('/AnnouncementsAPI.php?action=instructor-list' + params);
    const announcements = res.success ? res.data : [];

    container.innerHTML = `
        <style>
            .ac-banner { background:linear-gradient(135deg,#1B4D3E 0%,#2D6A4F 60%,#40916C 100%); border-radius:16px; padding:28px 32px; margin-bottom:24px; position:relative; overflow:hidden; }
            .ac-banner::before { content:''; position:absolute; top:-40px; right:-40px; width:180px; height:180px; border-radius:50%; background:rgba(255,255,255,.07); pointer-events:none; }
            .ac-banner::after { content:''; position:absolute; bottom:-60px; left:60px; width:220px; height:220px; border-radius:50%; background:rgba(255,255,255,.05); pointer-events:none; }
            .ac-banner-inner { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; position:relative; z-index:1; }
            .ac-banner-title { font-size:26px; font-weight:800; color:#fff; margin:0 0 4px; }
            .ac-banner-sub { font-size:14px; color:rgba(255,255,255,.75); margin:0; }
            .btn-primary { background:#fff; color:#1B4D3E; border:none; padding:10px 20px; border-radius:10px; font-weight:700; font-size:14px; cursor:pointer; transition:all .15s; }
            .btn-primary:hover { background:#f0fdf4; transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,0,0,.15); }

            .ac-filter-bar { display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
            .ac-filter-bar select { padding:9px 14px; border:1px solid #e8ecef; border-radius:10px; font-size:13px; min-width:200px; background:#fff; color:#374151; outline:none; transition:border-color .15s; box-shadow:0 1px 2px rgba(0,0,0,.04); }
            .ac-filter-bar select:focus { border-color:#1B4D3E; box-shadow:0 0 0 3px rgba(27,77,62,.08); }

            .ann-list { display:flex; flex-direction:column; gap:12px; }
            .ann-card { background:#fff; border:1px solid #f1f5f9; border-radius:14px; padding:20px; transition:all .2s; box-shadow:0 1px 3px rgba(0,0,0,.07); }
            .ann-card:hover { box-shadow:0 6px 20px rgba(0,0,0,.09); transform:translateY(-1px); }
            .ann-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px; }
            .ann-title { font-size:16px; font-weight:700; color:#111827; }
            .ann-badges { display:flex; gap:6px; }
            .badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; text-transform:capitalize; }
            .badge-published { background:#dcfce7; color:#16a34a; }
            .badge-draft { background:#FEF3C7; color:#B45309; }
            .badge-subject { background:#DBEAFE; color:#1E40AF; }

            .ann-content { font-size:14px; color:#374151; line-height:1.6; margin-bottom:12px; max-height:80px; overflow:hidden; }
            .ann-footer { display:flex; justify-content:space-between; align-items:center; }
            .ann-meta { font-size:12px; color:#9ca3af; }
            .ann-actions { display:flex; gap:8px; }
            .btn-sm { padding:7px 14px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; border:1px solid #e8ecef; background:#fff; color:#374151; transition:all .15s; }
            .btn-sm:hover { background:#f8fafc; border-color:#d1d5db; }
            .btn-sm.danger { color:#b91c1c; border-color:#fecaca; }
            .btn-sm.danger:hover { background:#FEE2E2; }

            .modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:1000; }
            .modal { background:#fff; border-radius:16px; width:90%; max-width:600px; max-height:90vh; overflow-y:auto; }
            .modal-header { padding:20px 24px; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; }
            .modal-header h3 { font-size:18px; font-weight:700; }
            .modal-close { background:none; border:none; font-size:24px; cursor:pointer; color:#737373; }
            .modal-body { padding:24px; }
            .modal-footer { padding:16px 24px; border-top:1px solid #f0f0f0; display:flex; justify-content:flex-end; gap:12px; }
            .form-group { margin-bottom:16px; }
            .form-label { display:block; font-size:13px; font-weight:600; color:#404040; margin-bottom:6px; }
            .form-input, .form-select, .form-textarea { width:100%; padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; box-sizing:border-box; font-family:inherit; }
            .form-input:focus, .form-select:focus, .form-textarea:focus { outline:none; border-color:#1B4D3E; }
            .btn-secondary { background:#f5f5f5; color:#404040; border:1px solid #e0e0e0; padding:9px 18px; border-radius:8px; font-weight:500; cursor:pointer; font-size:14px; }
            .alert { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:14px; }
            .alert-error { background:#FEE2E2; color:#b91c1c; }
            .empty-state-sm { text-align:center; padding:40px; color:#9ca3af; }
            @media(max-width:768px) { .ann-top { flex-direction:column; gap:8px; } }
        </style>

        <div class="ac-banner">
            <div class="ac-banner-inner">
                <div>
                    <h2 class="ac-banner-title">Announcements <span style="font-size:16px;font-weight:600;opacity:.8;">(${announcements.length})</span></h2>
                    <p class="ac-banner-sub">Post updates and notices to your students</p>
                </div>
                <button class="btn-primary" id="btn-add">+ New Announcement</button>
            </div>
        </div>

        <div class="ac-filter-bar">
            <select id="filter-subject">
                <option value="">All Subjects</option>
                ${subjects.map(s => `<option value="${s.subject_id}" ${filterSubject==s.subject_id?'selected':''}>${esc(s.subject_code)} - ${esc(s.subject_name)}</option>`).join('')}
            </select>
            <select id="filter-status">
                <option value="" ${!filterStatus?'selected':''}>All Status</option>
                <option value="published" ${filterStatus==='published'?'selected':''}>Published</option>
                <option value="draft" ${filterStatus==='draft'?'selected':''}>Draft</option>
            </select>
        </div>

        <div class="ann-list">
            ${announcements.length === 0 ? '<div class="empty-state-sm">No announcements yet. Create your first one!</div>' :
              announcements.map(a => `
                <div class="ann-card">
                    <div class="ann-top">
                        <div class="ann-title">${esc(a.title)}</div>
                        <div class="ann-badges">
                            ${a.subject_code ? `<span class="badge badge-subject">${esc(a.subject_code)}</span>` : '<span class="badge badge-subject">All Classes</span>'}
                            <span class="badge badge-${a.status}">${a.status}</span>
                        </div>
                    </div>
                    <div class="ann-content">${esc(a.content)}</div>
                    <div class="ann-footer">
                        <span class="ann-meta">Posted ${a.created_at ? new Date(a.created_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—'}</span>
                        <div class="ann-actions">
                            <button class="btn-sm" data-edit="${a.announcement_id}">Edit</button>
                            <button class="btn-sm danger" data-delete="${a.announcement_id}" data-name="${esc(a.title)}">Delete</button>
                        </div>
                    </div>
                </div>
              `).join('')}
        </div>
    `;

    container.querySelector('#btn-add').addEventListener('click', () => openModal(container, filterSubject, filterStatus));
    container.querySelector('#filter-subject').addEventListener('change', (e) => renderList(container, e.target.value, filterStatus));
    container.querySelector('#filter-status').addEventListener('change', (e) => renderList(container, filterSubject, e.target.value));

    container.querySelectorAll('[data-edit]').forEach(btn => {
        btn.addEventListener('click', () => {
            const a = announcements.find(x => x.announcement_id == btn.dataset.edit);
            if (a) openModal(container, filterSubject, filterStatus, a);
        });
    });

    container.querySelectorAll('[data-delete]').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm(`Delete "${btn.dataset.name}"?`)) return;
            const res = await Api.post('/AnnouncementsAPI.php?action=delete', { announcement_id: parseInt(btn.dataset.delete) });
            if (res.success) renderList(container, filterSubject, filterStatus);
            else alert(res.message);
        });
    });
}

function openModal(container, filterSubject, filterStatus, ann = null) {
    const isEdit = !!ann;
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    const subjOpts = subjects.map(s => `<option value="${s.subject_id}" ${ann ? ann.subject_id==s.subject_id?'selected':'' : filterSubject==s.subject_id?'selected':''}>${esc(s.subject_code)} - ${esc(s.subject_name)}</option>`).join('');

    overlay.innerHTML = `
        <div class="modal">
            <div class="modal-header"><h3>${isEdit ? 'Edit' : 'New'} Announcement</h3><button class="modal-close">&times;</button></div>
            <div class="modal-body">
                <div id="modal-alert"></div>
                <div class="form-group"><label class="form-label">Target Class</label><select class="form-select" id="m-subject"><option value="">All My Classes</option>${subjOpts}</select></div>
                <div class="form-group"><label class="form-label">Title *</label><input class="form-input" id="m-title" value="${esc(ann?.title||'')}"></div>
                <div class="form-group"><label class="form-label">Content *</label><textarea class="form-textarea" id="m-content" rows="5">${esc(ann?.content||'')}</textarea></div>
                <div class="form-group"><label class="form-label">Status</label>
                    <select class="form-select" id="m-status">
                        <option value="published" ${ann?.status==='published'||!ann?'selected':''}>Published</option>
                        <option value="draft" ${ann?.status==='draft'?'selected':''}>Draft</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary modal-cancel">Cancel</button>
                <button class="btn-primary" id="modal-save">${isEdit ? 'Update' : 'Post'}</button>
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
            subject_id: overlay.querySelector('#m-subject').value || null,
            title: overlay.querySelector('#m-title').value,
            content: overlay.querySelector('#m-content').value,
            status: overlay.querySelector('#m-status').value,
        };
        if (!payload.title || !payload.content) {
            overlay.querySelector('#modal-alert').innerHTML = '<div class="alert alert-error">Title and content are required</div>';
            return;
        }
        if (isEdit) payload.announcement_id = ann.announcement_id;
        const action = isEdit ? 'update' : 'create';
        const res = await Api.post(`/AnnouncementsAPI.php?action=${action}`, payload);
        if (res.success) { close(); renderList(container, filterSubject, filterStatus); }
        else overlay.querySelector('#modal-alert').innerHTML = `<div class="alert alert-error">${res.message}</div>`;
    });
}

function esc(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
