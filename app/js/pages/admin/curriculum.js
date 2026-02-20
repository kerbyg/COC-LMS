/**
 * Admin Curriculum Page
 * Read-only view of subjects grouped by year/semester per program
 */
import { Api } from '../../api.js';

export async function render(container) {
    const progRes = await Api.get('/CurriculumAPI.php?action=programs');
    const programs = progRes.success ? progRes.data : [];

    container.innerHTML = `
        <style>
            .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }
            .program-select { padding:10px 16px; border:1px solid #e0e0e0; border-radius:10px; font-size:14px; min-width:280px; background:#fff; }
            .program-select:focus { outline:none; border-color:#00461B; }

            .year-section { margin-bottom:32px; }
            .year-title { font-size:18px; font-weight:700; color:#1B4D3E; margin-bottom:16px; padding-bottom:8px; border-bottom:2px solid #E8F5E9; }
            .semester-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(400px,1fr)); gap:20px; }
            .semester-card { background:#fff; border:1px solid #e8e8e8; border-radius:14px; overflow:hidden; }
            .semester-header { background:#fafafa; padding:14px 20px; font-weight:700; font-size:15px; color:#262626; border-bottom:1px solid #e8e8e8; display:flex; justify-content:space-between; }
            .semester-units { font-size:13px; color:#737373; font-weight:500; }
            .subj-list { padding:0; margin:0; list-style:none; }
            .subj-item { display:flex; justify-content:space-between; align-items:center; padding:12px 20px; border-bottom:1px solid #f5f5f5; }
            .subj-item:last-child { border-bottom:none; }
            .subj-code { background:#E8F5E9; color:#1B4D3E; padding:3px 8px; border-radius:4px; font-family:monospace; font-size:12px; font-weight:600; margin-right:10px; }
            .subj-name { font-size:14px; color:#262626; }
            .subj-units { background:#f3f4f6; color:#404040; padding:2px 8px; border-radius:12px; font-size:12px; font-weight:600; white-space:nowrap; }

            .empty-msg { text-align:center; padding:60px 20px; color:#737373; }
            .empty-msg h3 { font-size:18px; margin-bottom:8px; color:#404040; }
            @media(max-width:768px) { .semester-grid { grid-template-columns:1fr; } }
        </style>

        <div class="page-header">
            <h2>Curriculum</h2>
            <select class="program-select" id="program-filter">
                <option value="">Select a Program</option>
                ${programs.map(p => `<option value="${p.program_id}">${esc(p.program_code)} - ${esc(p.program_name)}</option>`).join('')}
            </select>
        </div>

        <div id="curriculum-content">
            <div class="empty-msg"><h3>Select a Program</h3><p>Choose a program from the dropdown to view its curriculum.</p></div>
        </div>
    `;

    container.querySelector('#program-filter').addEventListener('change', async (e) => {
        const programId = e.target.value;
        const content = container.querySelector('#curriculum-content');

        if (!programId) {
            content.innerHTML = '<div class="empty-msg"><h3>Select a Program</h3><p>Choose a program from the dropdown to view its curriculum.</p></div>';
            return;
        }

        content.innerHTML = '<div style="text-align:center;padding:40px"><div style="width:36px;height:36px;border:3px solid #e8e8e8;border-top-color:#00461B;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto"></div></div>';

        const res = await Api.get(`/CurriculumAPI.php?action=view&program_id=${programId}`);
        const subjects = res.success ? res.data : [];

        if (subjects.length === 0) {
            content.innerHTML = '<div class="empty-msg"><h3>No Subjects</h3><p>No subjects found for this program.</p></div>';
            return;
        }

        // Group by year then semester
        const grouped = {};
        subjects.forEach(s => {
            const yr = s.year_level || 'Unassigned';
            const sem = s.semester || 'Unassigned';
            if (!grouped[yr]) grouped[yr] = {};
            if (!grouped[yr][sem]) grouped[yr][sem] = [];
            grouped[yr][sem].push(s);
        });

        const yearLabels = { '1': '1st Year', '2': '2nd Year', '3': '3rd Year', '4': '4th Year', 'Unassigned': 'Unassigned' };
        const semLabels = { '1': '1st Semester', '2': '2nd Semester', 'summer': 'Summer', 'Unassigned': 'Unassigned' };

        let html = '';
        for (const yr of Object.keys(grouped).sort()) {
            html += `<div class="year-section"><h3 class="year-title">${yearLabels[yr] || yr}</h3><div class="semester-grid">`;
            for (const sem of Object.keys(grouped[yr]).sort()) {
                const subs = grouped[yr][sem];
                const totalUnits = subs.reduce((sum, s) => sum + (parseInt(s.units) || 0), 0);
                html += `
                    <div class="semester-card">
                        <div class="semester-header">
                            <span>${semLabels[sem] || sem}</span>
                            <span class="semester-units">${totalUnits} units</span>
                        </div>
                        <ul class="subj-list">
                            ${subs.map(s => `
                                <li class="subj-item">
                                    <div><span class="subj-code">${esc(s.subject_code)}</span><span class="subj-name">${esc(s.subject_name)}</span></div>
                                    <span class="subj-units">${s.units} units</span>
                                </li>
                            `).join('')}
                        </ul>
                    </div>`;
            }
            html += '</div></div>';
        }

        content.innerHTML = html;
    });
}

function esc(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}
