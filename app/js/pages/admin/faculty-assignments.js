/**
 * Admin Faculty Assignments Page
 * Assign instructors to subject offerings/sections
 */
import { Api } from '../../api.js';

export async function render(container) {
    const [semRes, instRes] = await Promise.all([
        Api.get('/SubjectOfferingsAPI.php?action=semesters'),
        Api.get('/SectionsAPI.php?action=instructors')
    ]);
    const semesters = semRes.success ? semRes.data : [];
    const instructors = instRes.success ? instRes.data : [];

    container.innerHTML = `
        <style>
            .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }
            .filters { display:flex; gap:12px; margin-bottom:24px; }
            .filters select { padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; min-width:240px; background:#fff; }

            .offering-card { background:#fff; border:1px solid #e8e8e8; border-radius:14px; margin-bottom:16px; overflow:hidden; }
            .offering-header { padding:16px 20px; background:#fafafa; border-bottom:1px solid #e8e8e8; display:flex; justify-content:space-between; align-items:center; }
            .offering-title { font-weight:700; font-size:15px; color:#262626; }
            .offering-title .code { background:#E8F5E9; color:#1B4D3E; padding:3px 8px; border-radius:4px; font-family:monospace; font-size:12px; margin-right:8px; }
            .offering-meta { font-size:12px; color:#737373; }

            .assignment-list { padding:0; }
            .assignment-item { display:flex; justify-content:space-between; align-items:center; padding:12px 20px; border-bottom:1px solid #f5f5f5; }
            .assignment-item:last-child { border-bottom:none; }
            .assign-info { display:flex; align-items:center; gap:12px; }
            .assign-avatar { width:36px; height:36px; border-radius:50%; background:#DBEAFE; color:#1E40AF; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; flex-shrink:0; }
            .assign-name { font-weight:600; font-size:14px; color:#262626; }
            .assign-section { font-size:12px; color:#737373; }
            .badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; }
            .badge-active { background:#E8F5E9; color:#1B4D3E; }
            .badge-inactive { background:#FEE2E2; color:#b91c1c; }
            .btn-unassign { background:none; border:1px solid #fecaca; color:#b91c1c; padding:4px 12px; border-radius:6px; font-size:12px; cursor:pointer; }
            .btn-unassign:hover { background:#fef2f2; }

            .assign-form { padding:16px 20px; background:#f9fafb; border-top:1px solid #e8e8e8; display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
            .assign-form select { padding:8px 12px; border:1px solid #e0e0e0; border-radius:8px; font-size:13px; flex:1; min-width:160px; }
            .btn-assign { background:#00461B; color:#fff; border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; }
            .btn-assign:hover { background:#006428; }

            .no-assignments { padding:20px; text-align:center; color:#737373; font-size:14px; }
            .empty-state-sm { text-align:center; padding:40px; color:#737373; }
            .alert-inline { background:#FEE2E2; color:#b91c1c; padding:8px 12px; border-radius:6px; font-size:13px; }
        </style>

        <div class="page-header"><h2>Faculty Assignments</h2></div>

        <div class="filters">
            <select id="filter-sem">
                <option value="">All Semesters</option>
                ${semesters.map(s => `<option value="${s.semester_id}">${esc(s.semester_name)} - ${esc(s.academic_year)}</option>`).join('')}
            </select>
        </div>

        <div id="assignments-content">
            <div style="text-align:center;padding:40px"><div style="width:36px;height:36px;border:3px solid #e8e8e8;border-top-color:#00461B;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto"></div></div>
        </div>
    `;

    async function loadAssignments(semesterId = '') {
        const content = container.querySelector('#assignments-content');
        const params = semesterId ? '&semester_id=' + semesterId : '';
        const offRes = await Api.get('/SubjectOfferingsAPI.php?action=list' + params);
        const offerings = offRes.success ? offRes.data : [];

        if (offerings.length === 0) {
            content.innerHTML = '<div class="empty-state-sm">No subject offerings found for this semester.</div>';
            return;
        }

        // Get all current assignments
        const sectRes = await Api.get('/SectionsAPI.php?action=list');
        const allSections = sectRes.success ? sectRes.data : [];

        let html = '';
        for (const off of offerings) {
            // Find sections that have this offering in their subjects array
            const sections = allSections.filter(s =>
                s.subjects && s.subjects.some(subj => subj.subject_offered_id == off.subject_offered_id)
            );
            const sectionNames = sections.map(s => esc(s.section_name)).join(', ') || '—';

            const instOpts = instructors.map(i =>
                `<option value="${i.users_id}" ${off.user_teacher_id == i.users_id ? 'selected' : ''}>${esc(i.last_name)}, ${esc(i.first_name)}</option>`
            ).join('');

            const currentInstructor = off.instructor_name
                ? `<div class="assign-avatar">${off.instructor_name.split(' ').map(w=>w[0]).join('').toUpperCase().slice(0,2)}</div>
                   <div><div class="assign-name">${esc(off.instructor_name)}</div><div class="assign-section">Sections: ${sectionNames}</div></div>`
                : `<div style="color:#737373;font-size:13px">No instructor assigned &nbsp;·&nbsp; Sections: ${sectionNames}</div>`;

            html += `
                <div class="offering-card">
                    <div class="offering-header">
                        <div class="offering-title"><span class="code">${esc(off.subject_code)}</span>${esc(off.subject_name)}</div>
                        <div class="offering-meta">${esc(off.semester_name||'')} ${esc(off.academic_year||'')} · ${off.student_count} student(s)</div>
                    </div>
                    <div class="assignment-list">
                        <div class="assignment-item">
                            <div class="assign-info">${currentInstructor}</div>
                        </div>
                    </div>
                    <div class="assign-form" data-offering="${off.subject_offered_id}">
                        <select class="inst-select"><option value="">— Remove Instructor —</option>${instOpts}</select>
                        <button class="btn-assign">Assign</button>
                        <div class="assign-msg"></div>
                    </div>
                </div>`;
        }
        content.innerHTML = html;

        // Assign click handlers
        content.querySelectorAll('.btn-assign').forEach(btn => {
            btn.addEventListener('click', async () => {
                const form = btn.closest('.assign-form');
                const offeringId = form.dataset.offering;
                const instructorId = form.querySelector('.inst-select').value;
                const msg = form.querySelector('.assign-msg');

                const res = await Api.post('/SubjectOfferingsAPI.php?action=assign', {
                    subject_offered_id: parseInt(offeringId),
                    user_teacher_id: instructorId ? parseInt(instructorId) : 0
                });

                if (res.success) loadAssignments(container.querySelector('#filter-sem').value);
                else msg.innerHTML = `<span class="alert-inline">${res.message}</span>`;
            });
        });
    }

    container.querySelector('#filter-sem').addEventListener('change', (e) => loadAssignments(e.target.value));
    loadAssignments();
}

function esc(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}
