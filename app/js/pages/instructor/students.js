/**
 * Instructor Students Page
 * View enrolled students across assigned classes
 */
import { Api } from '../../api.js';
import { Auth } from '../../auth.js';

export async function render(container) {
    // Get instructor's classes first
    const classRes = await Api.get('/DashboardAPI.php?action=instructor');
    const classes = classRes.success ? classRes.data.classes : [];

    container.innerHTML = `
        <style>
            .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }
            .page-header .count { background:#E8F5E9; color:#1B4D3E; padding:4px 12px; border-radius:20px; font-size:13px; font-weight:600; margin-left:8px; }

            .filters { display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
            .filters input { padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; min-width:240px; }
            .filters select { padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; }

            .subject-group { margin-bottom:24px; }
            .subject-header { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
            .subj-code { background:#E8F5E9; color:#1B4D3E; padding:4px 10px; border-radius:6px; font-family:monospace; font-weight:700; font-size:13px; }
            .subj-name { font-size:16px; font-weight:600; color:#262626; }
            .subj-count { font-size:13px; color:#737373; }

            .data-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; border:1px solid #e8e8e8; }
            .data-table th { text-align:left; padding:12px 16px; font-size:12px; font-weight:600; color:#737373; text-transform:uppercase; letter-spacing:.5px; background:#fafafa; border-bottom:1px solid #e8e8e8; }
            .data-table td { padding:12px 16px; border-bottom:1px solid #f5f5f5; font-size:14px; }
            .data-table tr:last-child td { border-bottom:none; }
            .data-table tr:hover td { background:#fafafa; }

            .user-cell { display:flex; align-items:center; gap:10px; }
            .user-av { width:34px; height:34px; border-radius:50%; background:#EDE9FE; color:#5B21B6; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px; flex-shrink:0; }
            .user-name { font-weight:600; color:#262626; }

            .progress-bar { background:#f0f0f0; height:6px; border-radius:3px; overflow:hidden; width:80px; display:inline-block; vertical-align:middle; margin-right:6px; }
            .progress-fill { height:100%; border-radius:3px; background:#1B4D3E; }
            .progress-text { font-size:12px; color:#737373; }

            .score-badge { padding:3px 8px; border-radius:12px; font-size:12px; font-weight:600; }
            .score-pass { background:#E8F5E9; color:#1B4D3E; }
            .score-fail { background:#FEE2E2; color:#b91c1c; }
            .score-na { background:#f3f4f6; color:#737373; }

            .empty-state-sm { text-align:center; padding:40px; color:#737373; }
            @media(max-width:768px) { .filters { flex-direction:column; } }
        </style>

        <div class="page-header">
            <h2>Students</h2>
        </div>

        <div class="filters">
            <input type="text" id="search" placeholder="Search student name, ID, or email...">
            <select id="filter-subject">
                <option value="">All Subjects</option>
                ${classes.map(c => `<option value="${c.subject_offered_id}">${esc(c.subject_code)} - ${esc(c.subject_name)}</option>`).join('')}
            </select>
        </div>

        <div id="students-content">
            <div style="text-align:center;padding:40px;color:#737373">Loading students...</div>
        </div>
    `;

    async function loadStudents(search = '', subjectFilter = '') {
        const content = container.querySelector('#students-content');

        if (classes.length === 0) {
            content.innerHTML = '<div class="empty-state-sm">No classes assigned. Students will appear when you have assigned classes.</div>';
            return;
        }

        // Get students for each class
        let allStudents = [];
        for (const cls of classes) {
            if (subjectFilter && cls.subject_offered_id != subjectFilter) continue;

            const studRes = await Api.get('/LessonsAPI.php?action=students&subject_offered_id=' + cls.subject_offered_id);
            if (studRes.success && studRes.data) {
                studRes.data.forEach(s => {
                    s._subject_code = cls.subject_code;
                    s._subject_name = cls.subject_name;
                    s._subject_offered_id = cls.subject_offered_id;
                });
                allStudents = allStudents.concat(studRes.data);
            }
        }

        if (search) {
            const q = search.toLowerCase();
            allStudents = allStudents.filter(s =>
                ((s.first_name||'') + ' ' + (s.last_name||'') + ' ' + (s.student_id||'') + ' ' + (s.email||'')).toLowerCase().includes(q)
            );
        }

        if (allStudents.length === 0) {
            content.innerHTML = '<div class="empty-state-sm">No students found</div>';
            return;
        }

        // Group by subject
        const grouped = {};
        allStudents.forEach(s => {
            const key = s._subject_offered_id;
            if (!grouped[key]) grouped[key] = { code: s._subject_code, name: s._subject_name, students: [] };
            grouped[key].students.push(s);
        });

        let html = '';
        for (const [key, group] of Object.entries(grouped)) {
            html += `
                <div class="subject-group">
                    <div class="subject-header">
                        <span class="subj-code">${esc(group.code)}</span>
                        <span class="subj-name">${esc(group.name)}</span>
                        <span class="subj-count">(${group.students.length} students)</span>
                    </div>
                    <table class="data-table">
                        <thead><tr><th>Student</th><th>Student ID</th><th>Progress</th><th>Avg Score</th></tr></thead>
                        <tbody>
                            ${group.students.map(s => {
                                const initials = ((s.first_name||'?')[0] + (s.last_name||'?')[0]).toUpperCase();
                                const progress = s.progress || 0;
                                const avgScore = s.avg_score != null ? parseFloat(s.avg_score) : null;
                                const scoreClass = avgScore === null ? 'score-na' : avgScore >= 70 ? 'score-pass' : 'score-fail';
                                return `<tr>
                                    <td><div class="user-cell"><div class="user-av">${initials}</div><span class="user-name">${esc(s.first_name)} ${esc(s.last_name)}</span></div></td>
                                    <td style="color:#737373">${esc(s.student_id||'—')}</td>
                                    <td><div class="progress-bar"><div class="progress-fill" style="width:${progress}%"></div></div><span class="progress-text">${progress}%</span></td>
                                    <td><span class="score-badge ${scoreClass}">${avgScore !== null ? avgScore.toFixed(1) + '%' : 'N/A'}</span></td>
                                </tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                </div>`;
        }
        content.innerHTML = html;
    }

    let debounce;
    container.querySelector('#search').addEventListener('input', (e) => {
        clearTimeout(debounce);
        debounce = setTimeout(() => loadStudents(e.target.value, container.querySelector('#filter-subject').value), 400);
    });
    container.querySelector('#filter-subject').addEventListener('change', (e) => {
        loadStudents(container.querySelector('#search').value, e.target.value);
    });

    loadStudents();
}

function esc(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
