/**
 * Student Lessons Page
 * View and complete lessons for enrolled subjects
 */
import { Api } from '../../api.js';

export async function render(container) {
    const params = new URLSearchParams(window.location.hash.split('?')[1] || '');
    const filterSubject = params.get('subject_id') || '';

    container.innerHTML = `<div style="display:flex;justify-content:center;padding:60px">
        <div style="width:36px;height:36px;border:3px solid #e8e8e8;border-top-color:#1B4D3E;border-radius:50%;animation:spin .8s linear infinite"></div>
    </div>`;

    const subjRes = await Api.get('/EnrollmentAPI.php?action=my-subjects');
    const subjects = subjRes.success ? subjRes.data : [];

    container.innerHTML = `
        <style>
            /* ── Page header ── */
            .ls-header { margin-bottom: 24px; }
            .ls-header h2 { font-size: 24px; font-weight: 800; color: #111827; margin: 0 0 4px; letter-spacing: -.3px; }
            .ls-header p  { font-size: 13px; color: #9CA3AF; margin: 0; }

            /* ── Subject filter tabs ── */
            .ls-tabs {
                display: flex; gap: 8px; flex-wrap: wrap;
                margin-bottom: 28px;
                padding-bottom: 20px;
                border-bottom: 1px solid #F3F4F6;
            }
            .ls-tab {
                padding: 7px 16px; border-radius: 20px; font-size: 13px; font-weight: 600;
                border: 1.5px solid #E5E7EB; color: #6B7280; background: #fff;
                cursor: pointer; transition: all .15s; user-select: none;
            }
            .ls-tab:hover { border-color: #1B4D3E; color: #1B4D3E; background: #F0FDF4; }
            .ls-tab.active { background: #1B4D3E; color: #fff; border-color: #1B4D3E; }

            /* ── Subject group ── */
            .ls-group { margin-bottom: 36px; }

            .ls-group-hdr {
                display: flex; align-items: center; gap: 12px;
                margin-bottom: 16px; flex-wrap: wrap;
            }
            .ls-group-code {
                background: #E8F5E9; color: #1B4D3E;
                padding: 4px 12px; border-radius: 6px;
                font-size: 12px; font-weight: 800; font-family: monospace; letter-spacing: .5px;
            }
            .ls-group-name { font-size: 16px; font-weight: 700; color: #111827; flex: 1; }
            .ls-group-stats { font-size: 12px; color: #9CA3AF; white-space: nowrap; }

            .ls-group-prog-track {
                height: 4px; background: #F3F4F6; border-radius: 2px;
                overflow: hidden; margin-bottom: 16px;
            }
            .ls-group-prog-fill {
                height: 100%; background: #1B4D3E; border-radius: 2px; transition: width .6s ease;
            }

            /* ── Lesson list (timeline) ── */
            .ls-list { display: flex; flex-direction: column; gap: 0; position: relative; }

            /* connector line */
            .ls-item-wrap { display: flex; gap: 0; position: relative; }
            .ls-item-wrap:not(:last-child) .ls-connector {
                position: absolute; left: 19px; top: 48px; width: 2px;
                bottom: -8px; background: #E5E7EB; z-index: 0;
            }
            .ls-item-wrap.done:not(:last-child) .ls-connector { background: #BBF7D0; }

            /* step circle */
            .ls-step-col { width: 40px; flex-shrink: 0; display: flex; flex-direction: column; align-items: center; padding-top: 14px; position: relative; z-index: 1; }
            .ls-step {
                width: 38px; height: 38px; border-radius: 50%;
                display: flex; align-items: center; justify-content: center;
                font-size: 14px; font-weight: 800; flex-shrink: 0;
                border: 2px solid transparent;
            }
            .ls-step.done     { background: #1B4D3E; color: #fff; border-color: #1B4D3E; }
            .ls-step.active   { background: #fff; color: #1B4D3E; border-color: #1B4D3E; }
            .ls-step.locked   { background: #F9FAFB; color: #D1D5DB; border-color: #E5E7EB; }

            /* lesson card */
            .ls-card {
                flex: 1; margin-left: 14px; margin-bottom: 10px;
                background: #fff; border: 1px solid #f1f5f9; border-radius: 14px;
                box-shadow: 0 1px 3px rgba(0,0,0,.07);
                display: flex; align-items: center; gap: 16px;
                padding: 16px 20px;
                text-decoration: none; color: inherit;
                transition: all .18s;
                cursor: pointer;
            }
            .ls-card.done   { border-left: 4px solid #1B4D3E; }
            .ls-card.active { border-left: 4px solid #1B4D3E; }
            .ls-card.locked { border-left: 4px solid #E5E7EB; background: #FAFAFA; cursor: not-allowed; pointer-events: none; opacity: .75; }
            .ls-card:not(.locked):hover { border-color: #1B4D3E; box-shadow: 0 8px 24px rgba(0,0,0,.1); transform: translateY(-1px); }

            .ls-card-info { flex: 1; min-width: 0; }
            .ls-card-title { font-size: 15px; font-weight: 700; color: #111827; margin-bottom: 3px; }
            .ls-card-desc  { font-size: 13px; color: #9CA3AF; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .ls-card-lock-msg { font-size: 11.5px; color: #B45309; margin-top: 4px; display: flex; align-items: center; gap: 5px; }

            .ls-card-right { flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }

            .ls-badge {
                display: inline-flex; align-items: center; gap: 5px;
                padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700;
            }
            .ls-badge.done   { background: #DCFCE7; color: #15803D; }
            .ls-badge.quiz   { background: #FEF3C7; color: #B45309; }
            .ls-badge.locked { background: #F3F4F6; color: #9CA3AF; }

            .ls-date { font-size: 11px; color: #9CA3AF; }

            .ls-btn-complete {
                padding: 7px 16px; background: #1B4D3E; color: #fff;
                border: none; border-radius: 8px; font-size: 13px; font-weight: 600;
                cursor: pointer; transition: background .15s;
            }
            .ls-btn-complete:hover { background: #2D6A4F; }
            .ls-btn-complete:disabled { opacity: .6; cursor: not-allowed; }

            /* ── Empty states ── */
            .ls-no-lessons {
                padding: 24px 0 16px; color: #9CA3AF; font-size: 14px;
            }
            .ls-empty-page {
                display: flex; flex-direction: column; align-items: center; justify-content: center;
                min-height: 260px; text-align: center; color: #9CA3AF;
            }
            .ls-empty-page h3 { font-size: 16px; font-weight: 700; color: #374151; margin: 14px 0 6px; }
            .ls-empty-page p  { font-size: 13px; margin: 0; }

            .btn-back {
                display: inline-flex; align-items: center; gap: 6px;
                font-size: 13px; font-weight: 600; color: #6B7280;
                text-decoration: none; margin-bottom: 16px;
                transition: color .15s;
            }
            .btn-back:hover { color: #1B4D3E; }
        </style>

        <a href="#student/my-subjects" class="btn-back">← Back to My Subjects</a>

        <div class="ls-header">
            <h2>Lessons</h2>
            <p>Your learning progress across all subjects</p>
        </div>

        <!-- Subject filter tabs -->
        <div class="ls-tabs">
            <span class="ls-tab ${!filterSubject ? 'active' : ''}" data-id="">All Subjects</span>
            ${subjects.map(s => `
                <span class="ls-tab ${filterSubject == s.subject_id ? 'active' : ''}" data-id="${s.subject_id}">
                    ${esc(s.subject_code)}
                </span>
            `).join('')}
        </div>

        <div id="ls-content">
            <div style="text-align:center;padding:40px;color:#9CA3AF">Loading…</div>
        </div>
    `;

    // Tab click → update active + reload
    let currentFilter = filterSubject;
    container.querySelectorAll('.ls-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            container.querySelectorAll('.ls-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            currentFilter = tab.dataset.id;
            loadLessons(container, subjects, currentFilter);
        });
    });

    loadLessons(container, subjects, currentFilter);
}

async function loadLessons(container, subjects, filterSubject) {
    const content = container.querySelector('#ls-content');
    const filtered = filterSubject ? subjects.filter(s => s.subject_id == filterSubject) : subjects;

    if (filtered.length === 0) {
        content.innerHTML = `
            <div class="ls-empty-page">
                <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="#D1D5DB" stroke-width="1.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                <h3>No subjects enrolled</h3>
                <p><a href="#student/enroll" style="color:#1B4D3E;font-weight:600">Enroll in a section</a> to start learning.</p>
            </div>`;
        return;
    }

    content.innerHTML = `<div style="text-align:center;padding:40px;color:#9CA3AF">Loading…</div>`;

    const groups = await Promise.all(
        filtered.map(s =>
            Api.get('/LessonsAPI.php?action=list&subject_id=' + s.subject_offered_id)
                .then(r => ({ subject: s, lessons: r.success ? r.data : [] }))
                .catch(() => ({ subject: s, lessons: [] }))
        )
    );

    let html = '';
    for (const g of groups) {
        const total     = g.lessons.length;
        const completed = g.lessons.filter(l => l.is_completed == 1).length;
        const pct       = total > 0 ? Math.round((completed / total) * 100) : 0;

        html += `
        <div class="ls-group">
            <div class="ls-group-hdr">
                <span class="ls-group-code">${esc(g.subject.subject_code)}</span>
                <span class="ls-group-name">${esc(g.subject.subject_name)}</span>
                <span class="ls-group-stats">${completed}/${total} completed &nbsp;·&nbsp; ${pct}%</span>
            </div>
            <div class="ls-group-prog-track">
                <div class="ls-group-prog-fill" style="width:${pct}%"></div>
            </div>

            ${total === 0
                ? `<div class="ls-no-lessons">No lessons published yet.</div>`
                : `<div class="ls-list">${g.lessons.map((l) => {
                    const locked = l.is_locked == 1;
                    const done   = l.is_completed == 1;
                    const state  = done ? 'done' : locked ? 'locked' : 'active';
                    const dateStr = l.completed_at
                        ? new Date(l.completed_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
                        : '';
                    return `
                    <div class="ls-item-wrap ${state}">
                        <div class="ls-connector"></div>
                        <div class="ls-step-col">
                            <div class="ls-step ${state}">
                                ${done
                                    ? `<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>`
                                    : locked
                                        ? `<svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>`
                                        : `<span>${l.order_number}</span>`
                                }
                            </div>
                        </div>

                        <a class="ls-card ${state}" ${!locked ? `href="#student/lesson-view?id=${l.lessons_id}"` : ''}>
                            <div class="ls-card-info">
                                <div class="ls-card-title">${esc(l.title)}</div>
                                ${l.description ? `<div class="ls-card-desc">${esc(l.description)}</div>` : ''}
                                ${locked ? `<div class="ls-card-lock-msg">
                                    <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                                    Complete the previous lesson's quiz to unlock
                                </div>` : ''}
                            </div>
                            <div class="ls-card-right">
                                ${done ? `
                                    <span class="ls-badge done">
                                        <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                        Completed
                                    </span>
                                    ${dateStr ? `<span class="ls-date">${dateStr}</span>` : ''}
                                ` : locked ? `
                                    <span class="ls-badge locked">Locked</span>
                                ` : l.linked_quiz_id ? `
                                    <span class="ls-badge quiz">Pass Quiz to Complete</span>
                                ` : `
                                    <button class="ls-btn-complete" data-lesson="${l.lessons_id}">Mark Complete</button>
                                `}
                            </div>
                        </a>
                    </div>`;
                }).join('')}</div>`
            }
        </div>`;
    }

    content.innerHTML = html;

    // Mark Complete handlers
    content.querySelectorAll('.ls-btn-complete').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault(); e.stopPropagation();
            btn.disabled = true;
            btn.innerHTML = `<svg style="animation:spin .8s linear infinite" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>`;
            const res = await Api.post('/LessonsAPI.php?action=complete', { lessons_id: parseInt(btn.dataset.lesson) });
            if (res.success) {
                loadLessons(container, subjects, filterSubject);
            } else {
                btn.disabled = false;
                btn.textContent = 'Mark Complete';
            }
        });
    });
}

function esc(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
