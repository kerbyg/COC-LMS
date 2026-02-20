/**
 * Student Announcements Page
 * View announcements from instructors
 */
import { Api } from '../../api.js';

export async function render(container) {
    const [annRes, subjRes] = await Promise.all([
        Api.get('/AnnouncementsAPI.php?action=student-list'),
        Api.get('/EnrollmentAPI.php?action=my-subjects')
    ]);
    const announcements = annRes.success ? annRes.data : [];
    const subjects = subjRes.success ? subjRes.data : [];
    let filterSubject = '';

    renderList(container, announcements, subjects, filterSubject);
}

function renderList(container, allAnnouncements, subjects, filterSubject) {
    const announcements = filterSubject
        ? allAnnouncements.filter(a => String(a.subject_id) === String(filterSubject) || !a.subject_offered_id)
        : allAnnouncements;

    container.innerHTML = `
        <style>
            .page-header { margin-bottom:24px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }
            .page-header .count { background:#E8F5E9; color:#1B4D3E; padding:4px 12px; border-radius:20px; font-size:13px; font-weight:600; margin-left:8px; }
            .filters { display:flex; gap:12px; margin-bottom:20px; }
            .filters select { padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; min-width:220px; }

            .ann-list { display:flex; flex-direction:column; gap:12px; }
            .ann-card { background:#fff; border:1px solid #e8e8e8; border-radius:14px; padding:20px; transition:border-color .2s; }
            .ann-card:hover { border-color:#1B4D3E; }
            .ann-card.new { border-left:4px solid #1B4D3E; }
            .ann-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px; flex-wrap:wrap; gap:8px; }
            .ann-title { font-size:16px; font-weight:700; color:#262626; }
            .ann-badges { display:flex; gap:6px; }
            .badge { padding:3px 8px; border-radius:12px; font-size:10px; font-weight:700; }
            .badge-subject { background:#DBEAFE; color:#1E40AF; }
            .badge-new { background:#E8F5E9; color:#1B4D3E; }
            .badge-all { background:#f5f5f5; color:#737373; }
            .ann-content { font-size:14px; color:#404040; line-height:1.6; margin-bottom:12px; white-space:pre-line; }
            .ann-footer { display:flex; justify-content:space-between; align-items:center; font-size:12px; color:#737373; }
            .ann-author { font-weight:600; color:#404040; }

            .empty-state-sm { text-align:center; padding:40px; color:#737373; }
        </style>

        <div class="page-header"><h2>Announcements <span class="count">${announcements.length}</span></h2></div>
        <div class="filters">
            <select id="filter-subject">
                <option value="">All Subjects</option>
                ${subjects.map(s => `<option value="${s.subject_id}" ${filterSubject==s.subject_id?'selected':''}>${esc(s.subject_code)} - ${esc(s.subject_name)}</option>`).join('')}
            </select>
        </div>

        <div class="ann-list">
            ${announcements.length === 0 ? '<div class="empty-state-sm">No announcements yet</div>' :
              announcements.map(a => {
                const isNew = a.created_at && (Date.now() - new Date(a.created_at).getTime()) < 3 * 24 * 60 * 60 * 1000;
                return `
                <div class="ann-card ${isNew ? 'new' : ''}">
                    <div class="ann-top">
                        <div class="ann-title">${esc(a.title)}</div>
                        <div class="ann-badges">
                            ${a.subject_code ? `<span class="badge badge-subject">${esc(a.subject_code)}</span>` : '<span class="badge badge-all">All Classes</span>'}
                            ${isNew ? '<span class="badge badge-new">New</span>' : ''}
                        </div>
                    </div>
                    <div class="ann-content">${esc(a.content)}</div>
                    <div class="ann-footer">
                        <span>By <span class="ann-author">${esc(a.author_first||'')} ${esc(a.author_last||'')}</span></span>
                        <span>${a.created_at ? new Date(a.created_at).toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'}) : ''}</span>
                    </div>
                </div>`;
              }).join('')}
        </div>
    `;

    container.querySelector('#filter-subject').addEventListener('change', (e) => {
        renderList(container, allAnnouncements, subjects, e.target.value);
    });
}

function esc(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
