/**
 * Admin Curriculum Page
 * Academic table layout: Year header → 1st & 2nd Semester side-by-side tables
 */
import { Api } from '../../api.js';

let currentProgramId = '';

export async function render(container) {
    const [progRes, deptRes] = await Promise.all([
        Api.get('/CurriculumAPI.php?action=programs'),
        Api.get('/SubjectsAPI.php?action=departments'),
    ]);
    const programs    = progRes.success ? progRes.data : [];
    const departments = deptRes.success ? deptRes.data : [];

    container.innerHTML = `
        <style>
            .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }
            .header-right { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
            .program-select { padding:10px 16px; border:1px solid #e0e0e0; border-radius:10px; font-size:14px; min-width:200px; background:#fff; }
            .program-select:focus { outline:none; border-color:#1B4D3E; }
            .btn-primary { background:linear-gradient(135deg,#1B4D3E,#2D6A4F); color:#fff; border:none; padding:10px 18px; border-radius:10px; font-weight:600; font-size:14px; cursor:pointer; display:none; }
            .btn-primary:hover { opacity:.9; }
            .btn-primary.visible { display:inline-flex; align-items:center; gap:6px; }

            /* ── Year block ────────────────────────────────── */
            .year-block { margin-bottom:36px; }
            .year-banner {
                text-align:center; font-size:13px; font-weight:800;
                color:#fff; background:linear-gradient(135deg,#1B4D3E,#2D6A4F);
                padding:8px 20px; letter-spacing:1.5px; text-transform:uppercase;
                border-radius:8px 8px 0 0; margin-bottom:0;
            }
            .sem-pair {
                display:grid; grid-template-columns:1fr 1fr; gap:0;
                border:2px solid #1B4D3E; border-top:none; border-radius:0 0 8px 8px;
                overflow:hidden;
            }
            .sem-pair .sem-table-wrap:first-child {
                border-right:1px solid #1B4D3E;
            }
            .summer-wrap {
                border:2px solid #1B4D3E; border-top:none;
                border-radius:0 0 8px 8px; overflow:hidden;
            }

            /* ── Semester table ────────────────────────────── */
            .sem-table-wrap { background:#fff; }
            .sem-label {
                font-size:12px; font-weight:700; text-align:center;
                padding:7px 12px; background:#E8F5E9; color:#1B4D3E;
                border-bottom:1px solid #1B4D3E; letter-spacing:.5px;
            }
            .cur-table { width:100%; border-collapse:collapse; font-size:12px; }
            .cur-table thead tr th {
                background:#f7f7f7; color:#404040; font-weight:700;
                padding:6px 8px; border-bottom:1px solid #ccc;
                text-align:center; white-space:nowrap;
            }
            .cur-table thead tr th.th-title { text-align:left; }
            .cur-table thead tr th.th-code  { text-align:left; min-width:80px; }
            .th-unit { width:36px; }
            .th-prereq { min-width:80px; }
            .th-action { width:28px; }

            .cur-table tbody tr { border-bottom:1px solid #f0f0f0; }
            .cur-table tbody tr:last-child { border-bottom:none; }
            .cur-table tbody tr:hover { background:#f9fffe; }
            .cur-table td { padding:6px 8px; vertical-align:top; }
            .td-code { font-family:monospace; font-size:11px; font-weight:700; color:#1B4D3E; white-space:nowrap; }
            .td-title { font-size:12px; color:#262626; line-height:1.35; }
            .td-unit { text-align:center; color:#404040; font-weight:500; white-space:nowrap; }
            .td-prereq { font-size:11px; color:#737373; }

            .cur-table tfoot tr { background:#f7f7f7; border-top:1px solid #ccc; }
            .cur-table tfoot td { padding:6px 8px; font-weight:800; font-size:12px; }
            .tfoot-label { text-align:right; color:#262626; }

            /* ── Kebab ─────────────────────────────────────── */
            .cur-kebab-wrap { position:relative; }
            .cur-kebab { background:none; border:none; width:24px; height:24px; border-radius:5px; cursor:pointer; font-size:16px; color:#9ca3af; display:flex; align-items:center; justify-content:center; padding:0; line-height:1; }
            .cur-kebab:hover { background:#f0f0f0; color:#262626; }
            .cur-kebab-menu { display:none; position:absolute; right:0; top:100%; background:#fff; border:1px solid #e8e8e8; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.12); min-width:150px; z-index:200; overflow:hidden; }
            .cur-kebab-menu.open { display:block; }
            .cur-kebab-item { display:flex; align-items:center; gap:8px; padding:10px 16px; font-size:13px; color:#404040; cursor:pointer; background:none; border:none; width:100%; text-align:left; white-space:nowrap; }
            .cur-kebab-item:hover { background:#f5f5f5; }
            .cur-kebab-item.danger { color:#b91c1c; }
            .cur-kebab-item.danger:hover { background:#FEF2F2; }

            /* ── Modals ────────────────────────────────────── */
            .modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:1000; }
            .modal { background:#fff; border-radius:16px; width:90%; max-width:480px; max-height:90vh; overflow-y:auto; }
            .modal-header { padding:20px 24px; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; }
            .modal-header h3 { font-size:18px; font-weight:700; color:#262626; margin:0; }
            .modal-close { background:none; border:none; font-size:24px; cursor:pointer; color:#737373; padding:0; line-height:1; }
            .modal-body { padding:24px; }
            .modal-footer { padding:16px 24px; border-top:1px solid #f0f0f0; display:flex; justify-content:flex-end; gap:12px; }
            .form-group { margin-bottom:16px; }
            .form-row { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
            .form-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
            .form-label { display:block; font-size:13px; font-weight:600; color:#404040; margin-bottom:6px; }
            .form-select, .form-input { width:100%; padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; box-sizing:border-box; background:#fff; }
            .form-select:focus, .form-input:focus { outline:none; border-color:#1B4D3E; box-shadow:0 0 0 3px rgba(27,77,62,.1); }
            .btn-secondary { background:#f5f5f5; color:#404040; border:1px solid #e0e0e0; padding:9px 18px; border-radius:8px; font-weight:500; cursor:pointer; font-size:14px; }
            .alert { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:14px; }
            .alert-error { background:#FEE2E2; color:#b91c1c; border:1px solid #FECACA; }
            .subj-hint { font-size:12px; color:#737373; margin-top:4px; }
            .empty-msg { text-align:center; padding:60px 20px; color:#737373; }
            .empty-msg h3 { font-size:18px; margin-bottom:8px; color:#404040; }
            @keyframes spin { to { transform:rotate(360deg); } }
            @media(max-width:900px) {
                .sem-pair { grid-template-columns:1fr; }
                .sem-pair .sem-table-wrap:first-child { border-right:none; border-bottom:1px solid #1B4D3E; }
                .header-right { width:100%; }
                .program-select { width:100%; }
            }
        </style>

        <div class="page-header">
            <h2>Curriculum</h2>
            <div class="header-right">
                <select class="program-select" id="dept-filter">
                    <option value="">All Departments</option>
                    ${departments.map(d => `<option value="${d.department_id}">${esc(d.department_code)} - ${esc(d.department_name)}</option>`).join('')}
                </select>
                <select class="program-select" id="program-filter">
                    <option value="">Select a Program</option>
                    ${programs.map(p => `<option value="${p.program_id}" data-dept="${p.department_id}">${esc(p.program_code)} - ${esc(p.program_name)}</option>`).join('')}
                </select>
                <button class="btn-primary" id="btn-add-subject">+ Add Subject</button>
            </div>
        </div>

        <div id="curriculum-content">
            <div class="empty-msg"><h3>Select a Program</h3><p>Choose a program from the dropdown to view its curriculum.</p></div>
        </div>
    `;

    const deptFilter = container.querySelector('#dept-filter');
    const progFilter = container.querySelector('#program-filter');

    deptFilter.addEventListener('change', () => {
        const deptId = deptFilter.value;
        // Show/hide program options based on selected department
        progFilter.querySelectorAll('option').forEach(opt => {
            if (!opt.value) return; // keep "Select a Program"
            opt.hidden = deptId ? opt.dataset.dept !== deptId : false;
        });
        // Reset program selection if the current selection is now hidden
        const selected = progFilter.querySelector(`option[value="${progFilter.value}"]`);
        if (progFilter.value && selected && selected.hidden) {
            progFilter.value = '';
            currentProgramId = '';
            container.querySelector('#btn-add-subject').classList.remove('visible');
            loadCurriculum(container, '');
        }
    });

    progFilter.addEventListener('change', (e) => {
        currentProgramId = e.target.value;
        const addBtn = container.querySelector('#btn-add-subject');
        if (currentProgramId) addBtn.classList.add('visible');
        else addBtn.classList.remove('visible');
        loadCurriculum(container, currentProgramId);
    });

    container.querySelector('#btn-add-subject').addEventListener('click', () => {
        openAddModal(container, currentProgramId);
    });
}

async function loadCurriculum(container, programId) {
    const content = container.querySelector('#curriculum-content');

    if (!programId) {
        content.innerHTML = '<div class="empty-msg"><h3>Select a Program</h3><p>Choose a program from the dropdown to view its curriculum.</p></div>';
        return;
    }

    content.innerHTML = '<div style="text-align:center;padding:40px"><div style="width:36px;height:36px;border:3px solid #e8e8e8;border-top-color:#1B4D3E;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto"></div></div>';

    const res = await Api.get(`/CurriculumAPI.php?action=view&program_id=${programId}`);
    const subjects = res.success ? res.data : [];

    if (subjects.length === 0) {
        content.innerHTML = '<div class="empty-msg"><h3>No Subjects</h3><p>No subjects assigned to this program yet. Click <b>+ Add Subject</b> to begin.</p></div>';
        return;
    }

    // Group by year → semester
    const grouped = {};
    subjects.forEach(s => {
        const yr  = s.year_level || 'Unassigned';
        const sem = s.semester   || 'Unassigned';
        if (!grouped[yr])      grouped[yr] = {};
        if (!grouped[yr][sem]) grouped[yr][sem] = [];
        grouped[yr][sem].push(s);
    });

    const yearLabels = { '1':'First Year','2':'Second Year','3':'Third Year','4':'Fourth Year','Unassigned':'Unassigned' };

    let html = '';
    for (const yr of Object.keys(grouped).sort()) {
        const sem1     = grouped[yr][1]            || [];
        const sem2     = grouped[yr][2]            || [];
        const summer   = grouped[yr][3]            || [];
        const unassign = grouped[yr]['Unassigned'] || [];

        const hasMainSems = sem1.length > 0 || sem2.length > 0;

        html += `<div class="year-block">
            <div class="year-banner">${yearLabels[yr] || yr}</div>`;

        if (hasMainSems) {
            html += `<div class="sem-pair">
                ${renderSemesterTable('First Semester',  sem1)}
                ${renderSemesterTable('Second Semester', sem2)}
            </div>`;
        }

        if (summer.length > 0) {
            html += `<div class="summer-wrap" style="margin-top:${hasMainSems ? '8' : '0'}px;">
                ${renderSemesterTable('Summer', summer)}
            </div>`;
        }

        if (unassign.length > 0) {
            html += `<div class="summer-wrap" style="margin-top:${(hasMainSems || summer.length) ? '8' : '0'}px;">
                ${renderSemesterTable('Unassigned Semester', unassign)}
            </div>`;
        }

        html += `</div>`;
    }

    content.innerHTML = html;
    bindCurriculumEvents(container, content, programId);
}

function renderSemesterTable(label, subjects) {
    const totalLec   = subjects.reduce((s, x) => s + (parseInt(x.lecture_hours) || 0), 0);
    const totalLab   = subjects.reduce((s, x) => s + (parseInt(x.lab_hours)     || 0), 0);
    const totalUnits = subjects.reduce((s, x) => s + (parseInt(x.units)         || 0), 0);

    const rows = subjects.length === 0
        ? `<tr><td colspan="7" style="text-align:center;color:#bbb;padding:14px;font-size:12px;font-style:italic;">No subjects assigned</td></tr>`
        : subjects.map(s => `
            <tr>
                <td class="td-code">${esc(s.subject_code)}</td>
                <td class="td-title">${esc(s.subject_name)}</td>
                <td class="td-unit">${s.lecture_hours ?? 3}</td>
                <td class="td-unit">${s.lab_hours ?? 0}</td>
                <td class="td-unit">${s.units ?? 3}</td>
                <td class="td-prereq">${esc(s.pre_requisite || '')}</td>
                <td class="td-action">
                    <div class="cur-kebab-wrap">
                        <button class="cur-kebab" title="Actions">⋮</button>
                        <div class="cur-kebab-menu">
                            <button class="cur-kebab-item"
                                data-edit="${s.subject_id}"
                                data-year="${esc(s.year_level||'')}"
                                data-sem="${esc(s.semester||'')}"
                                data-units="${s.units ?? 3}"
                                data-lec="${s.lecture_hours ?? 3}"
                                data-lab="${s.lab_hours ?? 0}"
                                data-prereq="${esc(s.pre_requisite||'')}"
                                data-name="${esc(s.subject_name)}">
                                ✏️ Edit
                            </button>
                            <button class="cur-kebab-item"
                                data-offerings="${s.subject_id}">
                                📅 Offerings
                            </button>
                            <button class="cur-kebab-item danger"
                                data-archive="${s.subject_id}"
                                data-name="${esc(s.subject_name)}">
                                📦 Archive
                            </button>
                        </div>
                    </div>
                </td>
            </tr>
        `).join('');

    return `
    <div class="sem-table-wrap">
        <div class="sem-label">${label}</div>
        <table class="cur-table">
            <thead>
                <tr>
                    <th class="th-code">Course Code</th>
                    <th class="th-title" style="text-align:left;">Course Title</th>
                    <th class="th-unit">Lec</th>
                    <th class="th-unit">Lab</th>
                    <th class="th-unit">Total</th>
                    <th class="th-prereq">Pre-requisite</th>
                    <th class="th-action"></th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
            ${subjects.length > 0 ? `
            <tfoot>
                <tr>
                    <td colspan="2" class="tfoot-label">Total</td>
                    <td class="td-unit">${totalLec}</td>
                    <td class="td-unit">${totalLab}</td>
                    <td class="td-unit">${totalUnits}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>` : ''}
        </table>
    </div>`;
}

function bindCurriculumEvents(container, content, programId) {
    let closeMenusHandler = null;

    // Kebab toggle
    content.querySelectorAll('.cur-kebab').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const menu = btn.nextElementSibling;
            const isOpen = menu.classList.contains('open');
            content.querySelectorAll('.cur-kebab-menu.open').forEach(m => m.classList.remove('open'));
            if (!isOpen) menu.classList.add('open');

            if (closeMenusHandler) document.removeEventListener('click', closeMenusHandler);
            closeMenusHandler = () => content.querySelectorAll('.cur-kebab-menu.open').forEach(m => m.classList.remove('open'));
            document.addEventListener('click', closeMenusHandler, { once: true });
        });
    });

    // Edit placement
    content.querySelectorAll('[data-edit]').forEach(btn => {
        btn.addEventListener('click', () => {
            content.querySelectorAll('.cur-kebab-menu.open').forEach(m => m.classList.remove('open'));
            openEditModal(container, {
                subject_id:    parseInt(btn.dataset.edit),
                subject_name:  btn.dataset.name,
                year_level:    btn.dataset.year,
                semester:      btn.dataset.sem,
                units:         parseInt(btn.dataset.units)  || 3,
                lecture_hours: parseInt(btn.dataset.lec)    || 3,
                lab_hours:     parseInt(btn.dataset.lab)    || 0,
                pre_requisite: btn.dataset.prereq || '',
            }, programId);
        });
    });

    // Offerings — navigate to Subject Offerings page filtered to this program
    content.querySelectorAll('[data-offerings]').forEach(btn => {
        btn.addEventListener('click', () => {
            content.querySelectorAll('.cur-kebab-menu.open').forEach(m => m.classList.remove('open'));
            window.location.hash = `#admin/subject-offerings?program_id=${programId}`;
        });
    });

    // Archive
    content.querySelectorAll('[data-archive]').forEach(btn => {
        btn.addEventListener('click', async () => {
            content.querySelectorAll('.cur-kebab-menu.open').forEach(m => m.classList.remove('open'));
            if (!confirm(`Archive "${btn.dataset.name}"?\n\nThis will hide it from the curriculum. It cannot be archived if it has active offerings.`)) return;
            const res = await Api.post('/CurriculumAPI.php?action=archive', {
                subject_id: parseInt(btn.dataset.archive),
                program_id: parseInt(programId),
            });
            if (res.success) loadCurriculum(container, programId);
            else alert(res.message);
        });
    });
}

// ─── Edit Placement Modal ──────────────────────────────────────────────────

function openEditModal(container, subj, programId) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.innerHTML = `
        <div class="modal">
            <div class="modal-header">
                <h3>Edit Subject</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="modal-alert"></div>
                <p style="font-size:13px;color:#737373;margin:0 0 16px"><b style="color:#262626">${esc(subj.subject_name)}</b></p>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Year Level</label>
                        <select class="form-select" id="e-year">
                            <option value="">Not Set</option>
                            <option value="1" ${subj.year_level=='1'?'selected':''}>1st Year</option>
                            <option value="2" ${subj.year_level=='2'?'selected':''}>2nd Year</option>
                            <option value="3" ${subj.year_level=='3'?'selected':''}>3rd Year</option>
                            <option value="4" ${subj.year_level=='4'?'selected':''}>4th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Semester</label>
                        <select class="form-select" id="e-sem">
                            <option value="">Not Set</option>
                            <option value="1" ${subj.semester==1?'selected':''}>1st Semester</option>
                            <option value="2" ${subj.semester==2?'selected':''}>2nd Semester</option>
                            <option value="3" ${subj.semester==3?'selected':''}>Summer</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Lec Hours</label>
                        <input type="number" class="form-input" id="e-lec" value="${subj.lecture_hours}" min="0" max="12">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lab Hours</label>
                        <input type="number" class="form-input" id="e-lab" value="${subj.lab_hours}" min="0" max="12">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Units</label>
                        <input type="number" class="form-input" id="e-units" value="${subj.units}" min="1" max="12">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Pre-requisite</label>
                    <input type="text" class="form-input" id="e-prereq" value="${esc(subj.pre_requisite)}" placeholder="e.g., CC101 or None">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary modal-cancel">Cancel</button>
                <button class="btn-primary visible" id="e-save">Save Changes</button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
    overlay.querySelector('.modal-close').addEventListener('click', () => overlay.remove());
    overlay.querySelector('.modal-cancel').addEventListener('click', () => overlay.remove());
    overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });

    overlay.querySelector('#e-save').addEventListener('click', async () => {
        const res = await Api.post('/CurriculumAPI.php?action=update', {
            subject_id:    subj.subject_id,
            program_id:    parseInt(programId),
            year_level:    overlay.querySelector('#e-year').value   || null,
            semester:      overlay.querySelector('#e-sem').value    || null,
            units:         parseInt(overlay.querySelector('#e-units').value)  || 3,
            lecture_hours: parseInt(overlay.querySelector('#e-lec').value)    || 0,
            lab_hours:     parseInt(overlay.querySelector('#e-lab').value)    || 0,
            pre_requisite: overlay.querySelector('#e-prereq').value.trim(),
        });
        if (res.success) { overlay.remove(); loadCurriculum(container, programId); }
        else overlay.querySelector('#modal-alert').innerHTML = `<div class="alert alert-error">${res.message}</div>`;
    });
}

// ─── Add Subject Modal ─────────────────────────────────────────────────────

async function openAddModal(container, programId) {
    const res = await Api.get(`/CurriculumAPI.php?action=available&program_id=${programId}`);
    const available = res.success ? res.data : [];

    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.innerHTML = `
        <div class="modal">
            <div class="modal-header">
                <h3>Add Subject to Curriculum</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="modal-alert"></div>
                <div class="form-group">
                    <label class="form-label">Subject *</label>
                    <select class="form-select" id="a-subject">
                        <option value="">Select Subject</option>
                        ${available.map(s => `<option value="${s.subject_id}">${esc(s.subject_code)} — ${esc(s.subject_name)}${s.in_program ? ' ✓' : ''}</option>`).join('')}
                    </select>
                    <p class="subj-hint">Subjects marked with ✓ are already in this program.</p>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Year Level</label>
                        <select class="form-select" id="a-year">
                            <option value="">Not Set</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Semester</label>
                        <select class="form-select" id="a-sem">
                            <option value="">Not Set</option>
                            <option value="1">1st Semester</option>
                            <option value="2">2nd Semester</option>
                            <option value="3">Summer</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary modal-cancel">Cancel</button>
                <button class="btn-primary visible" id="a-save">Add to Curriculum</button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
    overlay.querySelector('.modal-close').addEventListener('click', () => overlay.remove());
    overlay.querySelector('.modal-cancel').addEventListener('click', () => overlay.remove());
    overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });

    overlay.querySelector('#a-save').addEventListener('click', async () => {
        const subjectId = overlay.querySelector('#a-subject').value;
        if (!subjectId) {
            overlay.querySelector('#modal-alert').innerHTML = `<div class="alert alert-error">Please select a subject.</div>`;
            return;
        }
        const r = await Api.post('/CurriculumAPI.php?action=add', {
            program_id: parseInt(programId),
            subject_id: parseInt(subjectId),
            year_level: overlay.querySelector('#a-year').value || null,
            semester:   overlay.querySelector('#a-sem').value  || null,
        });
        if (r.success) { overlay.remove(); loadCurriculum(container, programId); }
        else overlay.querySelector('#modal-alert').innerHTML = `<div class="alert alert-error">${r.message}</div>`;
    });
}

function esc(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}
