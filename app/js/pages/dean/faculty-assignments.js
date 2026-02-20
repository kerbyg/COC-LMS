/**
 * Dean Faculty Assignments Page
 * Assign instructors to sections (dept-filtered)
 */
import { Api } from '../../api.js';
import { Auth } from '../../auth.js';

export async function render(container) {
    const [semRes, instRes] = await Promise.all([
        Api.get('/SubjectOfferingsAPI.php?action=semesters'),
        Api.get('/SectionsAPI.php?action=instructors')
    ]);
    const semesters = semRes.success ? semRes.data : [];
    const user = Auth.user();
    const deptId = user.department_id;
    // Filter instructors by department
    const allInstructors = instRes.success ? instRes.data : [];
    const instructors = deptId ? allInstructors.filter(i => true) : allInstructors; // API already filters by role

    container.innerHTML = `
        <style>
            .page-header { margin-bottom:24px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }
            .filters { display:flex; gap:12px; margin-bottom:24px; }
            .filters select { padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; min-width:240px; background:#fff; }

            .offering-card { background:#fff; border:1px solid #e8e8e8; border-radius:14px; margin-bottom:16px; overflow:hidden; }
            .offering-header { padding:16px 20px; background:#fafafa; border-bottom:1px solid #e8e8e8; display:flex; justify-content:space-between; align-items:center; }
            .offering-title { font-weight:700; font-size:15px; color:#262626; }
            .offering-title .code { background:#E8F5E9; color:#1B4D3E; padding:3px 8px; border-radius:4px; font-family:monospace; font-size:12px; margin-right:8px; }
            .offering-meta { font-size:12px; color:#737373; }

            .assignment-item { display:flex; justify-content:space-between; align-items:center; padding:12px 20px; border-bottom:1px solid #f5f5f5; }
            .assignment-item:last-child { border-bottom:none; }
            .assign-info { display:flex; align-items:center; gap:12px; }
            .assign-avatar { width:36px; height:36px; border-radius:50%; background:#DBEAFE; color:#1E40AF; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; }
            .assign-name { font-weight:600; font-size:14px; color:#262626; }
            .assign-section { font-size:12px; color:#737373; }
            .badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; }
            .badge-active { background:#E8F5E9; color:#1B4D3E; }

            .assign-form { padding:16px 20px; background:#f9fafb; border-top:1px solid #e8e8e8; display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
            .assign-form select { padding:8px 12px; border:1px solid #e0e0e0; border-radius:8px; font-size:13px; flex:1; min-width:150px; }
            .btn-assign { background:#00461B; color:#fff; border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; }
            .btn-assign:hover { background:#006428; }
            .assign-msg { font-size:12px; }
            .alert-inline { background:#FEE2E2; color:#b91c1c; padding:6px 12px; border-radius:6px; font-size:12px; }
            .success-inline { background:#E8F5E9; color:#1B4D3E; padding:6px 12px; border-radius:6px; font-size:12px; }

            .no-data { padding:20px; text-align:center; color:#737373; font-size:14px; }
            .empty-state-sm { text-align:center; padding:40px; color:#737373; }
        </style>

        <div class="page-header"><h2>Faculty Assignments</h2></div>
        <div class="filters">
            <select id="filter-sem">
                <option value="">All Semesters</option>
                ${semesters.map(s => `<option value="${s.semester_id}">${esc(s.semester_name)} - ${esc(s.academic_year)}</option>`).join('')}
            </select>
        </div>
        <div id="content"><div style="text-align:center;padding:40px;color:#737373">Loading...</div></div>
    `;

    async function load(semId = '') {
        const content = container.querySelector('#content');
        const params = semId ? '&semester_id=' + semId : '';
        const [offRes, secRes] = await Promise.all([
            Api.get('/SubjectOfferingsAPI.php?action=list' + params),
            Api.get('/SectionsAPI.php?action=list')
        ]);
        const offerings = offRes.success ? offRes.data : [];
        const allSections = secRes.success ? secRes.data : [];

        if (!offerings.length) {
            content.innerHTML = '<div class="empty-state-sm">No subject offerings found</div>';
            return;
        }

        const instOpts = instructors.map(i => `<option value="${i.users_id}">${esc(i.last_name)}, ${esc(i.first_name)}</option>`).join('');

        content.innerHTML = offerings.map(off => {
            const sections = allSections.filter(s => s.subject_offered_id == off.subject_offered_id);
            const secOpts = sections.map(s => `<option value="${s.section_id}">${esc(s.section_name)}</option>`).join('');

            return `
            <div class="offering-card">
                <div class="offering-header">
                    <div class="offering-title"><span class="code">${esc(off.subject_code)}</span>${esc(off.subject_name)}</div>
                    <div class="offering-meta">${esc(off.semester_name||'')} ${esc(off.academic_year||'')} · ${off.instructor_count} instructor(s)</div>
                </div>
                ${sections.filter(s => s.instructor_name).map(s => {
                    const init = s.instructor_name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0,2);
                    return `<div class="assignment-item">
                        <div class="assign-info">
                            <div class="assign-avatar">${init}</div>
                            <div><div class="assign-name">${esc(s.instructor_name)}</div><div class="assign-section">${esc(s.section_name)}</div></div>
                        </div>
                        <span class="badge badge-active">Active</span>
                    </div>`;
                }).join('') || '<div class="no-data">No instructors assigned</div>'}
                ${sections.length > 0 ? `
                <div class="assign-form" data-offering="${off.subject_offered_id}">
                    <select class="inst-select"><option value="">Select Instructor</option>${instOpts}</select>
                    <select class="sec-select"><option value="">Select Section</option>${secOpts}</select>
                    <button class="btn-assign">Assign</button>
                    <div class="assign-msg"></div>
                </div>` : '<div class="no-data" style="font-size:12px">No sections. <a href="#dean/sections" style="color:#00461B">View sections</a></div>'}
            </div>`;
        }).join('');

        // Bind assign buttons
        content.querySelectorAll('.btn-assign').forEach(btn => {
            btn.addEventListener('click', async () => {
                const form = btn.closest('.assign-form');
                const instId = form.querySelector('.inst-select').value;
                const secId = form.querySelector('.sec-select').value;
                const msg = form.querySelector('.assign-msg');

                if (!instId || !secId) { msg.innerHTML = '<span class="alert-inline">Select both</span>'; return; }

                const secName = form.querySelector('.sec-select option:checked').textContent;
                const res = await Api.post('/SectionsAPI.php?action=update', {
                    section_id: parseInt(secId),
                    instructor_id: parseInt(instId),
                    section_name: secName,
                    max_students: 40, schedule: '', room: '', status: 'active'
                });

                if (res.success) {
                    msg.innerHTML = '<span class="success-inline">Assigned!</span>';
                    setTimeout(() => load(semId), 1000);
                } else {
                    msg.innerHTML = `<span class="alert-inline">${res.message}</span>`;
                }
            });
        });
    }

    container.querySelector('#filter-sem').addEventListener('change', (e) => load(e.target.value));
    load();
}

function esc(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
