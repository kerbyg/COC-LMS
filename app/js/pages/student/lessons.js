/**
 * Student Lessons Page
 * View and complete lessons for enrolled subjects
 */
import { Api } from '../../api.js';

export async function render(container) {
    // Check for subject filter in hash
    const params = new URLSearchParams(window.location.hash.split('?')[1] || '');
    const filterSubject = params.get('subject_id') || '';

    // Get enrolled subjects for filter
    const subjRes = await Api.get('/EnrollmentAPI.php?action=my-subjects');
    const subjects = subjRes.success ? subjRes.data : [];

    container.innerHTML = `
        <style>
            .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }
            .filters { display:flex; gap:12px; margin-bottom:20px; }
            .filters select { padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; min-width:220px; }

            .subject-group { margin-bottom:24px; }
            .sg-header { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
            .sg-code { background:#E8F5E9; color:#1B4D3E; padding:4px 10px; border-radius:6px; font-family:monospace; font-size:13px; font-weight:700; }
            .sg-name { font-size:16px; font-weight:700; color:#262626; }
            .sg-progress { font-size:13px; color:#737373; margin-left:auto; }

            .lessons-list { display:flex; flex-direction:column; gap:10px; }
            .lesson-card { background:#fff; border:1px solid #e8e8e8; border-radius:12px; padding:16px 20px; display:flex; align-items:center; gap:16px; transition:all .2s; cursor:pointer; text-decoration:none; color:inherit; }
            .lesson-card:hover:not(.locked) { border-color:#1B4D3E; box-shadow:0 2px 8px rgba(0,0,0,.04); }
            .lesson-card.locked { background:#f8f8f8; border-color:#e0e0e0; cursor:not-allowed; pointer-events:none; opacity:.7; }
            .lesson-order { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; flex-shrink:0; }
            .lesson-order.done { background:#E8F5E9; color:#1B4D3E; }
            .lesson-order.pending { background:#f5f5f5; color:#737373; }
            .lesson-order.locked-icon { background:#f0f0f0; color:#aaa; font-size:16px; }
            .lesson-info { flex:1; min-width:0; }
            .lesson-title { font-size:15px; font-weight:600; color:#262626; }
            .lesson-desc { font-size:13px; color:#737373; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
            .lesson-status { display:flex; align-items:center; gap:8px; }
            .badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; }
            .badge-done { background:#E8F5E9; color:#1B4D3E; }
            .badge-pending { background:#FEF3C7; color:#B45309; }
            .btn-complete { padding:6px 14px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; border:1px solid #1B4D3E; background:#1B4D3E; color:#fff; }
            .btn-complete:hover { background:#006428; }
            .completed-date { font-size:11px; color:#737373; }

            .empty-state-sm { text-align:center; padding:40px; color:#737373; }
        </style>

        <div class="page-header"><h2>Lessons</h2></div>
        <div class="filters">
            <select id="filter-subject">
                <option value="">All Subjects</option>
                ${subjects.map(s => `<option value="${s.subject_id}" ${filterSubject==s.subject_id?'selected':''}>${esc(s.subject_code)} - ${esc(s.subject_name)}</option>`).join('')}
            </select>
        </div>
        <div id="lessons-content"><div style="text-align:center;padding:40px;color:#737373">Loading lessons...</div></div>
    `;

    container.querySelector('#filter-subject').addEventListener('change', (e) => {
        loadLessons(container, subjects, e.target.value);
    });

    loadLessons(container, subjects, filterSubject);
}

async function loadLessons(container, subjects, filterSubject) {
    const content = container.querySelector('#lessons-content');
    const filtered = filterSubject ? subjects.filter(s => s.subject_id == filterSubject) : subjects;

    if (filtered.length === 0) {
        content.innerHTML = '<div class="empty-state-sm">No subjects found. <a href="#student/enroll" style="color:#1B4D3E">Enroll first</a></div>';
        return;
    }

    // Fetch lessons for each subject
    const promises = filtered.map(s =>
        Api.get('/LessonsAPI.php?action=list&subject_id=' + s.subject_offered_id)
            .then(r => ({ subject: s, lessons: r.success ? r.data : [] }))
            .catch(() => ({ subject: s, lessons: [] }))
    );
    const groups = await Promise.all(promises);

    let html = '';
    for (const g of groups) {
        const completed = g.lessons.filter(l => l.is_completed == 1).length;
        html += `
            <div class="subject-group">
                <div class="sg-header">
                    <span class="sg-code">${esc(g.subject.subject_code)}</span>
                    <span class="sg-name">${esc(g.subject.subject_name)}</span>
                    <span class="sg-progress">${completed}/${g.lessons.length} completed</span>
                </div>
                <div class="lessons-list">
                    ${g.lessons.length === 0 ? '<div style="padding:16px;color:#737373;font-size:14px">No lessons published yet</div>' :
                      g.lessons.map(l => {
                          const locked = l.is_locked == 1;
                          const done   = l.is_completed == 1;
                          return `
                            <a class="lesson-card${locked ? ' locked' : ''}" ${locked ? '' : `href="#student/lesson-view?id=${l.lessons_id}"`}>
                                <div class="lesson-order ${done ? 'done' : locked ? 'locked-icon' : 'pending'}">
                                    ${done ? '✓' : locked ? '🔒' : l.order_number}
                                </div>
                                <div class="lesson-info">
                                    <div class="lesson-title">${esc(l.title)}</div>
                                    ${l.description ? `<div class="lesson-desc">${esc(l.description)}</div>` : ''}
                                    ${locked ? `<div style="font-size:11px;color:#b45309;margin-top:3px;">Complete the previous lesson's quiz to unlock</div>` : ''}
                                </div>
                                <div class="lesson-status">
                                    ${done ?
                                        `<span class="badge badge-done">Completed</span><span class="completed-date">${l.completed_at ? new Date(l.completed_at).toLocaleDateString('en-US',{month:'short',day:'numeric'}) : ''}</span>` :
                                        locked ? `<span class="badge" style="background:#f0f0f0;color:#aaa">Locked</span>` :
                                        l.linked_quiz_id ? `<span class="badge badge-pending">Pass Quiz to Complete</span>` :
                                        `<button class="btn-complete" data-lesson="${l.lessons_id}">Mark Complete</button>`}
                                </div>
                            </a>
                          `;
                      }).join('')}
                </div>
            </div>
        `;
    }

    content.innerHTML = html;

    // Mark complete buttons
    content.querySelectorAll('.btn-complete').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            btn.disabled = true; btn.textContent = '...';
            const res = await Api.post('/LessonsAPI.php?action=complete', { lessons_id: parseInt(btn.dataset.lesson) });
            if (res.success) {
                loadLessons(container, subjects, filterSubject);
            } else {
                btn.disabled = false; btn.textContent = 'Mark Complete';
                alert(res.message || 'Failed');
            }
        });
    });
}

function esc(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
