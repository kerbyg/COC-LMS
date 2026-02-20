/**
 * Student My Subjects Page
 * Subjects grouped by section
 */
import { Api } from '../../api.js';

export async function render(container) {
    const res = await Api.get('/EnrollmentAPI.php?action=my-subjects');
    const subjects = res.success ? res.data : [];

    // Group subjects by section
    const sections = new Map();
    for (const s of subjects) {
        const key = s.section_name || 'Unassigned';
        if (!sections.has(key)) {
            sections.set(key, { section_name: key, enrollment_code: s.enrollment_code || '', subjects: [] });
        }
        sections.get(key).subjects.push(s);
    }

    container.innerHTML = `
        <style>
            .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }
            .page-header .count { background:#E8F5E9; color:#1B4D3E; padding:4px 12px; border-radius:20px; font-size:13px; font-weight:600; margin-left:8px; }
            .btn-outline { padding:9px 18px; border:2px solid #1B4D3E; color:#1B4D3E; border-radius:10px; font-weight:600; font-size:14px; text-decoration:none; cursor:pointer; }
            .btn-outline:hover { background:#E8F5E9; }

            /* Section group */
            .section-group { margin-bottom:32px; }
            .section-header {
                display:flex; align-items:center; gap:12px;
                padding:12px 18px;
                background:linear-gradient(135deg,#1B4D3E,#2D6A4F);
                border-radius:12px 12px 0 0;
                color:#fff;
            }
            .section-header-icon { font-size:20px; line-height:1; }
            .section-header-name { font-size:16px; font-weight:700; flex:1; }
            .section-header-code {
                background:rgba(255,255,255,.18); color:#fff;
                padding:3px 10px; border-radius:20px; font-family:monospace; font-size:12px; font-weight:700;
                letter-spacing:.5px;
            }
            .section-header-count { font-size:12px; opacity:.8; }

            /* Subject cards inside section */
            .section-subjects { border:1px solid #e8e8e8; border-top:none; border-radius:0 0 12px 12px; overflow:hidden; }
            .subj-card { background:#fff; border-bottom:1px solid #f0f0f0; transition:background .15s; }
            .subj-card:last-child { border-bottom:none; }
            .subj-card:hover { background:#fafffe; }
            .subj-inner { display:grid; grid-template-columns:1fr auto; gap:20px; padding:18px 20px; align-items:center; }
            .subj-info { min-width:0; }
            .subj-top { display:flex; align-items:center; gap:8px; margin-bottom:5px; flex-wrap:wrap; }
            .subj-code-badge { background:#E8F5E9; color:#1B4D3E; padding:3px 8px; border-radius:5px; font-family:monospace; font-size:12px; font-weight:700; }
            .subj-name { font-size:15px; font-weight:700; color:#262626; margin-bottom:3px; }
            .subj-meta { font-size:12px; color:#737373; display:flex; gap:12px; flex-wrap:wrap; align-items:center; }
            .subj-meta-item { display:flex; align-items:center; gap:4px; }

            .subj-progress { text-align:right; }
            .progress-circle { width:56px; height:56px; margin:0 auto 6px; }
            .progress-circle svg { width:56px; height:56px; transform:rotate(-90deg); }
            .progress-label { font-size:11px; color:#737373; text-align:center; white-space:nowrap; }

            .subj-nav { display:flex; gap:8px; padding:10px 20px 14px; background:#fafafa; border-top:1px solid #f0f0f0; }
            .nav-btn { padding:6px 14px; border-radius:6px; font-size:12px; font-weight:600; text-decoration:none; border:1px solid #e0e0e0; color:#404040; background:#fff; }
            .nav-btn:hover { background:#f5f5f5; }
            .nav-btn.primary { background:#1B4D3E; color:#fff; border-color:#1B4D3E; }
            .nav-btn.primary:hover { background:#2D6A4F; }

            .empty-state-sm { text-align:center; padding:60px; color:#737373; }
            @media(max-width:768px) {
                .subj-inner { grid-template-columns:1fr; }
                .subj-progress { text-align:left; display:flex; align-items:center; gap:12px; }
                .progress-label { text-align:left; }
            }
        </style>

        <div class="page-header">
            <h2>My Subjects <span class="count">${subjects.length}</span></h2>
            <a href="#student/enroll" class="btn-outline">+ Enroll</a>
        </div>

        ${subjects.length === 0
            ? '<div class="empty-state-sm">No subjects enrolled. <a href="#student/enroll" style="color:#1B4D3E;font-weight:600">Enroll now</a></div>'
            : [...sections.values()].map(sec => `
                <div class="section-group">
                    <div class="section-header">
                        <span class="section-header-icon">🏫</span>
                        <span class="section-header-name">${esc(sec.section_name)}</span>
                        ${sec.enrollment_code ? `<span class="section-header-code">${esc(sec.enrollment_code)}</span>` : ''}
                        <span class="section-header-count">${sec.subjects.length} subject${sec.subjects.length !== 1 ? 's' : ''}</span>
                    </div>
                    <div class="section-subjects">
                        ${sec.subjects.map(s => {
                            const pct = s.progress || 0;
                            const r = 22, circ = 2 * Math.PI * r;
                            const offset = circ - (pct / 100) * circ;
                            return `
                            <div class="subj-card">
                                <div class="subj-inner">
                                    <div class="subj-info">
                                        <div class="subj-top">
                                            <span class="subj-code-badge">${esc(s.subject_code)}</span>
                                        </div>
                                        <div class="subj-name">${esc(s.subject_name)}</div>
                                        <div class="subj-meta">
                                            <span class="subj-meta-item">👤 ${esc(s.instructor_name || 'TBA')}</span>
                                            ${s.schedule ? `<span class="subj-meta-item">🕐 ${esc(s.schedule)}</span>` : ''}
                                            ${s.room ? `<span class="subj-meta-item">📍 ${esc(s.room)}</span>` : ''}
                                            <span class="subj-meta-item">${s.units || 0} units</span>
                                        </div>
                                    </div>
                                    <div class="subj-progress">
                                        <div class="progress-circle">
                                            <svg viewBox="0 0 56 56">
                                                <circle cx="28" cy="28" r="${r}" fill="none" stroke="#f0f0f0" stroke-width="5"/>
                                                <circle cx="28" cy="28" r="${r}" fill="none" stroke="#1B4D3E" stroke-width="5"
                                                    stroke-dasharray="${circ.toFixed(2)}" stroke-dashoffset="${offset.toFixed(2)}" stroke-linecap="round"/>
                                                <text x="28" y="32" text-anchor="middle" fill="#262626" font-size="13" font-weight="800"
                                                    style="transform:rotate(90deg);transform-origin:center">${pct}%</text>
                                            </svg>
                                        </div>
                                        <div class="progress-label">${s.completed_lessons}/${s.total_lessons} lessons</div>
                                    </div>
                                </div>
                                <div class="subj-nav">
                                    <a class="nav-btn primary" href="#student/lessons?subject_id=${s.subject_id}">Lessons</a>
                                    <a class="nav-btn" href="#student/quizzes?subject_id=${s.subject_id}">Quizzes</a>
                                    <a class="nav-btn" href="#student/progress?subject_id=${s.subject_id}">Progress</a>
                                </div>
                            </div>`;
                        }).join('')}
                    </div>
                </div>
            `).join('')}
    `;
}

function esc(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
