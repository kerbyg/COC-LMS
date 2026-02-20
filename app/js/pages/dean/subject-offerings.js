/**
 * Dean Subject Offerings Page
 * View-only subject offerings
 */
import { Api } from '../../api.js';

export async function render(container) {
    const [offRes, semRes] = await Promise.all([
        Api.get('/SubjectOfferingsAPI.php?action=list'),
        Api.get('/SubjectOfferingsAPI.php?action=semesters')
    ]);
    const offerings = offRes.success ? offRes.data : [];
    const semesters = semRes.success ? semRes.data : [];

    let filtered = [...offerings];

    renderView(container, filtered, semesters);
}

function renderView(container, offerings, semesters, semFilter = '') {
    container.innerHTML = `
        <style>
            .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }
            .page-header .count { background:#E8F5E9; color:#1B4D3E; padding:4px 12px; border-radius:20px; font-size:13px; font-weight:600; margin-left:8px; }
            .stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
            .stat-card { background:#fff; border:1px solid #e8e8e8; border-radius:12px; padding:16px; text-align:center; }
            .stat-card .num { font-size:24px; font-weight:800; color:#1B4D3E; }
            .stat-card .label { font-size:12px; color:#737373; margin-top:2px; }
            .filters { margin-bottom:20px; }
            .filters select { padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; min-width:240px; }
            .data-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; border:1px solid #e8e8e8; }
            .data-table th { text-align:left; padding:14px 16px; font-size:12px; font-weight:600; color:#737373; text-transform:uppercase; letter-spacing:.5px; background:#fafafa; border-bottom:1px solid #e8e8e8; }
            .data-table td { padding:14px 16px; border-bottom:1px solid #f5f5f5; font-size:14px; }
            .data-table tr:last-child td { border-bottom:none; }
            .data-table tr:hover td { background:#fafafa; }
            .subj-code { background:#E8F5E9; color:#1B4D3E; padding:3px 8px; border-radius:4px; font-family:monospace; font-size:12px; font-weight:600; margin-right:8px; }
            .meta-badge { background:#f3f4f6; color:#404040; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:500; }
            .badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; text-transform:capitalize; }
            .badge-open { background:#E8F5E9; color:#1B4D3E; }
            .badge-closed { background:#FEE2E2; color:#b91c1c; }
            .badge-cancelled { background:#f3f4f6; color:#737373; }
            .empty-state-sm { text-align:center; padding:40px; color:#737373; }
            @media(max-width:768px) { .stats-row { grid-template-columns:1fr 1fr; } }
        </style>

        <div class="page-header"><h2>Subject Offerings <span class="count">${offerings.length}</span></h2></div>

        <div class="stats-row">
            <div class="stat-card"><div class="num">${offerings.length}</div><div class="label">Total Offerings</div></div>
            <div class="stat-card"><div class="num">${offerings.filter(o => o.status==='open').length}</div><div class="label">Active</div></div>
            <div class="stat-card"><div class="num">${offerings.reduce((s,o) => s + (parseInt(o.section_count)||0), 0)}</div><div class="label">Total Sections</div></div>
            <div class="stat-card"><div class="num">${offerings.reduce((s,o) => s + (parseInt(o.student_count)||0), 0)}</div><div class="label">Total Enrollments</div></div>
        </div>

        <div class="filters">
            <select id="filter-sem">
                <option value="">All Semesters</option>
                ${semesters.map(s => `<option value="${s.semester_id}" ${semFilter==s.semester_id?'selected':''}>${esc(s.semester_name)} - ${esc(s.academic_year)}</option>`).join('')}
            </select>
        </div>

        <table class="data-table">
            <thead><tr><th>Subject</th><th>Semester</th><th>Units</th><th>Sections</th><th>Instructors</th><th>Students</th><th>Status</th></tr></thead>
            <tbody>
                ${offerings.length === 0 ? '<tr><td colspan="7"><div class="empty-state-sm">No offerings found</div></td></tr>' :
                  offerings.map(o => `<tr>
                    <td><span class="subj-code">${esc(o.subject_code)}</span>${esc(o.subject_name)}</td>
                    <td>${esc(o.semester_name||'—')} <span style="color:#737373;font-size:12px">${esc(o.academic_year||'')}</span></td>
                    <td><span class="meta-badge">${o.units}</span></td>
                    <td><span class="meta-badge">${o.section_count}</span></td>
                    <td><span class="meta-badge">${o.instructor_count}</span></td>
                    <td><span class="meta-badge">${o.student_count}</span></td>
                    <td><span class="badge badge-${o.status}">${o.status}</span></td>
                  </tr>`).join('')}
            </tbody>
        </table>
    `;

    container.querySelector('#filter-sem').addEventListener('change', async (e) => {
        const semId = e.target.value;
        const params = semId ? '&semester_id=' + semId : '';
        const res = await Api.get('/SubjectOfferingsAPI.php?action=list' + params);
        const filtered = res.success ? res.data : [];
        renderView(container, filtered, semesters, semId);
    });
}

function esc(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
