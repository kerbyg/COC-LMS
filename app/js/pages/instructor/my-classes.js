/**
 * Instructor My Classes Page
 * Grid of assigned classes with semester filter
 */
import { Api } from '../../api.js';

export async function render(container) {
    const res = await Api.get('/DashboardAPI.php?action=instructor');
    const classes = res.success ? res.data.classes : [];

    container.innerHTML = `
        <style>
            .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }
            .page-header .count { background:#E8F5E9; color:#1B4D3E; padding:4px 12px; border-radius:20px; font-size:13px; font-weight:600; margin-left:8px; }

            .classes-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:16px; }
            .class-card { background:#fff; border:1px solid #e8e8e8; border-radius:14px; overflow:hidden; transition:all .2s; }
            .class-card:hover { border-color:#1B4D3E; box-shadow:0 4px 12px rgba(0,0,0,.06); }
            .class-header { background:linear-gradient(135deg,#1B4D3E,#2D6A4F); padding:20px; color:#fff; position:relative; overflow:hidden; }
            .class-header::after { content:''; position:absolute; right:-20px; top:-20px; width:80px; height:80px; border-radius:50%; background:rgba(255,255,255,.08); }
            .class-code { background:rgba(255,255,255,.2); padding:4px 10px; border-radius:6px; font-family:monospace; font-size:13px; font-weight:700; display:inline-block; margin-bottom:8px; }
            .class-name { font-size:16px; font-weight:700; }
            .class-section { font-size:12px; opacity:.8; margin-top:4px; }

            .class-stats { display:grid; grid-template-columns:repeat(4,1fr); padding:16px 20px; gap:8px; border-bottom:1px solid #f0f0f0; }
            .cs-item { text-align:center; }
            .cs-num { font-size:18px; font-weight:700; color:#262626; display:block; }
            .cs-label { font-size:10px; color:#737373; text-transform:uppercase; letter-spacing:.5px; }

            .class-actions { padding:12px 20px; display:flex; gap:8px; }
            .btn-sm { padding:7px 14px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; border:1px solid #e0e0e0; background:#fff; color:#404040; text-decoration:none; }
            .btn-sm:hover { background:#f5f5f5; }
            .btn-sm.primary { background:#1B4D3E; color:#fff; border-color:#1B4D3E; }

            .empty-state-sm { text-align:center; padding:40px; color:#737373; }
            @media(max-width:768px) { .classes-grid { grid-template-columns:1fr; } .class-stats { grid-template-columns:1fr 1fr; } }
        </style>

        <div class="page-header">
            <h2>My Classes <span class="count">${classes.length}</span></h2>
        </div>

        ${classes.length === 0 ? '<div class="empty-state-sm">No classes assigned yet.</div>' :
          `<div class="classes-grid">
            ${classes.map(c => `
                <div class="class-card">
                    <div class="class-header">
                        <span class="class-code">${esc(c.subject_code)}</span>
                        <div class="class-name">${esc(c.subject_name)}</div>
                        <div class="class-section">${esc(c.section_name || 'No Section')}</div>
                    </div>
                    <div class="class-stats">
                        <div class="cs-item"><span class="cs-num">${c.student_count}</span><span class="cs-label">Students</span></div>
                        <div class="cs-item"><span class="cs-num">${c.published_lessons}</span><span class="cs-label">Lessons</span></div>
                        <div class="cs-item"><span class="cs-num">${c.published_quizzes}</span><span class="cs-label">Quizzes</span></div>
                        <div class="cs-item"><span class="cs-num">${c.units}</span><span class="cs-label">Units</span></div>
                    </div>
                    <div class="class-actions">
                        <a class="btn-sm primary" href="#instructor/lessons">Lessons</a>
                        <a class="btn-sm" href="#instructor/quizzes">Quizzes</a>
                        <a class="btn-sm" href="#instructor/students">Students</a>
                    </div>
                </div>
            `).join('')}
          </div>`}
    `;
}

function esc(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
