/**
 * Dean Dashboard Page
 * Department-specific overview
 */
import { Api } from '../../api.js';
import { Auth } from '../../auth.js';

export async function render(container) {
    const res = await Api.get('/DashboardAPI.php?action=dean');
    const data = res.success ? res.data : {};
    const stats = data.stats || {};
    const dept = data.department || {};
    const faculty = data.faculty || [];
    const user = Auth.user();

    container.innerHTML = `
        <style>
            .welcome-banner { background:linear-gradient(135deg,#1B4D3E 0%,#2D6A4F 100%); border-radius:16px; padding:32px; color:#fff; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; }
            .welcome-text h2 { font-size:24px; font-weight:700; margin-bottom:4px; }
            .welcome-text p { opacity:.8; font-size:14px; }
            .dept-badge { background:rgba(255,255,255,.2); padding:8px 16px; border-radius:10px; font-weight:700; font-size:14px; }

            .stats-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; margin-bottom:24px; }
            .stat-card { background:#fff; border:1px solid #e8e8e8; border-radius:12px; padding:20px; text-align:center; }
            .stat-card .num { font-size:28px; font-weight:800; color:#1B4D3E; display:block; }
            .stat-card .label { font-size:13px; color:#737373; margin-top:4px; }

            .content-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
            .card { background:#fff; border:1px solid #e8e8e8; border-radius:14px; overflow:hidden; }
            .card-header { padding:16px 20px; background:#fafafa; border-bottom:1px solid #e8e8e8; font-weight:700; font-size:15px; color:#262626; }
            .card-body { padding:20px; }

            .faculty-item { display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid #f5f5f5; }
            .faculty-item:last-child { border-bottom:none; }
            .faculty-info { display:flex; align-items:center; gap:12px; }
            .faculty-avatar { width:38px; height:38px; border-radius:50%; background:#DBEAFE; color:#1E40AF; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; }
            .faculty-name { font-weight:600; font-size:14px; color:#262626; }
            .faculty-id { font-size:12px; color:#737373; }
            .faculty-stats { display:flex; gap:8px; }
            .faculty-stat { background:#f3f4f6; padding:3px 10px; border-radius:12px; font-size:12px; color:#404040; font-weight:500; }

            .quick-links { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
            .quick-link { display:flex; align-items:center; gap:12px; padding:14px 16px; background:#fafafa; border-radius:10px; cursor:pointer; text-decoration:none; color:#262626; font-weight:600; font-size:14px; border:1px solid #e8e8e8; transition:all .2s; }
            .quick-link:hover { border-color:#1B4D3E; background:#E8F5E9; }
            .quick-link .icon { font-size:20px; }

            .empty-text { text-align:center; padding:20px; color:#737373; font-size:14px; }
            @media(max-width:768px) { .content-grid { grid-template-columns:1fr; } .quick-links { grid-template-columns:1fr; } }
        </style>

        <div class="welcome-banner">
            <div class="welcome-text">
                <h2>Welcome, ${esc(user.first_name || user.name)}</h2>
                <p>${esc(dept.department_name || 'Department')} Overview</p>
            </div>
            <div class="dept-badge">${esc(dept.department_code || 'DEPT')}</div>
        </div>

        <div class="stats-row">
            <div class="stat-card"><span class="num">${stats.instructors || 0}</span><span class="label">Instructors</span></div>
            <div class="stat-card"><span class="num">${stats.students || 0}</span><span class="label">Students</span></div>
            <div class="stat-card"><span class="num">${stats.subjects || 0}</span><span class="label">Subjects</span></div>
            <div class="stat-card"><span class="num">${stats.sections || 0}</span><span class="label">Sections</span></div>
            <div class="stat-card"><span class="num">${stats.offerings || 0}</span><span class="label">Active Offerings</span></div>
        </div>

        <div class="content-grid">
            <div class="card">
                <div class="card-header">Faculty Overview</div>
                <div class="card-body">
                    ${faculty.length === 0 ? '<div class="empty-text">No instructors in department</div>' :
                      faculty.map(f => {
                        const initials = ((f.first_name||'?')[0] + (f.last_name||'?')[0]).toUpperCase();
                        return `
                        <div class="faculty-item">
                            <div class="faculty-info">
                                <div class="faculty-avatar">${initials}</div>
                                <div>
                                    <div class="faculty-name">${esc(f.first_name)} ${esc(f.last_name)}</div>
                                    <div class="faculty-id">${esc(f.employee_id || '—')}</div>
                                </div>
                            </div>
                            <div class="faculty-stats">
                                <span class="faculty-stat">${f.subject_count} subj</span>
                                <span class="faculty-stat">${f.section_count} sec</span>
                            </div>
                        </div>`;
                      }).join('')}
                </div>
            </div>

            <div class="card">
                <div class="card-header">Quick Actions</div>
                <div class="card-body">
                    <div class="quick-links">
                        <a class="quick-link" href="#dean/instructors"><span class="icon">👨‍🏫</span>Manage Instructors</a>
                        <a class="quick-link" href="#dean/faculty-assignments"><span class="icon">📋</span>Faculty Assignments</a>
                        <a class="quick-link" href="#dean/sections"><span class="icon">🏫</span>View Sections</a>
                        <a class="quick-link" href="#dean/reports"><span class="icon">📈</span>View Reports</a>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function esc(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}
