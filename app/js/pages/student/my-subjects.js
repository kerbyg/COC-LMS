/**
 * Student My Subjects Page
 */
import { Api } from '../../api.js';

export async function render(container) {
    container.innerHTML = `<div style="display:flex;justify-content:center;padding:60px">
        <div style="width:36px;height:36px;border:3px solid #e8e8e8;border-top-color:#1B4D3E;border-radius:50%;animation:spin .8s linear infinite"></div>
    </div>`;

    const res      = await Api.get('/EnrollmentAPI.php?action=my-subjects');
    const subjects = res.success ? res.data : [];

    // Unique sections for filter dropdown
    const sectionNames = [...new Set(subjects.map(s => s.section_name).filter(Boolean))];

    container.innerHTML = `
        <style>
            /* ── Topbar row ── */
            .ms-topbar {
                display: flex; align-items: flex-end; justify-content: space-between;
                gap: 16px; margin-bottom: 20px; flex-wrap: wrap;
            }
            .ms-title-block h2 {
                font-size: 20px; font-weight: 800; color: #0f172a; margin: 0 0 2px;
                letter-spacing: -.3px;
            }
            .ms-title-block p { font-size: 13px; color: #94a3b8; margin: 0; }

            .ms-topbar-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
            .ms-enroll-btn {
                display: inline-flex; align-items: center; gap: 6px;
                padding: 9px 16px; background: #1B4D3E; color: #fff;
                border-radius: 10px; font-size: 13px; font-weight: 600;
                text-decoration: none; transition: background .15s, box-shadow .15s;
                white-space: nowrap; box-shadow: 0 1px 3px rgba(27,77,62,.3);
            }
            .ms-enroll-btn:hover { background: #2D6A4F; box-shadow: 0 4px 12px rgba(27,77,62,.35); }
            .ms-announce-btn {
                display: inline-flex; align-items: center; gap: 6px;
                padding: 9px 16px; background: #fff; color: #374151;
                border-radius: 10px; font-size: 13px; font-weight: 600;
                text-decoration: none; border: 1px solid #e2e8f0;
                transition: all .15s; white-space: nowrap;
                box-shadow: 0 1px 3px rgba(0,0,0,.06);
            }
            .ms-announce-btn:hover { border-color: #1B4D3E; color: #1B4D3E; background: #f0fdf4; }

            /* ── Filter bar ── */
            .ms-filterbar {
                display: flex; align-items: center; gap: 10px;
                margin-bottom: 22px; flex-wrap: wrap;
            }
            .ms-search-wrap {
                position: relative; width: 280px;
            }
            .ms-search-wrap svg {
                position: absolute; left: 11px; top: 50%;
                transform: translateY(-50%); color: #94a3b8; pointer-events: none;
            }
            .ms-search {
                width: 100%; padding: 8px 12px 8px 34px;
                border: 1px solid #e2e8f0; border-radius: 8px;
                font-size: 13px; color: #0f172a; background: #fff;
                outline: none; transition: border-color .15s, box-shadow .15s;
                box-sizing: border-box;
            }
            .ms-search:focus { border-color: #1B4D3E; box-shadow: 0 0 0 3px rgba(27,77,62,.1); }
            .ms-search::placeholder { color: #94a3b8; }

            .ms-filter-sel {
                padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px;
                font-size: 13px; background: #fff; color: #374151;
                cursor: pointer; outline: none; transition: border-color .15s;
            }
            .ms-filter-sel:focus { border-color: #1B4D3E; }

            .ms-count-pill {
                margin-left: auto; background: #f1f5f9; color: #64748b;
                border-radius: 20px; padding: 4px 12px;
                font-size: 12px; font-weight: 600; white-space: nowrap;
            }

            /* ── Grid ── */
            .ms-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
                gap: 20px;
            }

            /* ── Card ── */
            .ms-card {
                background: #fff; border-radius: 16px; overflow: hidden;
                display: flex; flex-direction: column;
                box-shadow: 0 1px 3px rgba(0,0,0,.07), 0 1px 2px rgba(0,0,0,.04);
                transition: box-shadow .22s, transform .22s;
            }
            .ms-card:hover {
                box-shadow: 0 10px 30px rgba(0,0,0,.1), 0 4px 8px rgba(0,0,0,.06);
                transform: translateY(-3px);
            }

            /* Card banner */
            .ms-card-banner {
                background: linear-gradient(135deg, #1a4d3c 0%, #1f6348 60%, #2d7a56 100%);
                padding: 18px 20px 16px; position: relative; overflow: hidden;
            }
            .ms-card-banner::before {
                content: ''; position: absolute; right: -20px; top: -20px;
                width: 100px; height: 100px;
                background: rgba(255,255,255,.07); border-radius: 50%;
            }
            .ms-card-banner::after {
                content: ''; position: absolute; right: 30px; bottom: -30px;
                width: 70px; height: 70px;
                background: rgba(255,255,255,.05); border-radius: 50%;
            }
            .ms-banner-top {
                display: flex; align-items: center; justify-content: space-between;
                margin-bottom: 10px; gap: 8px; position: relative; z-index: 1;
            }
            .ms-code {
                background: rgba(255,255,255,.2); color: #fff;
                padding: 2px 8px; border-radius: 5px;
                font-size: 10.5px; font-weight: 700; font-family: monospace;
                letter-spacing: .6px;
            }
            .ms-section-tag {
                background: rgba(255,255,255,.15); color: rgba(255,255,255,.9);
                padding: 2px 8px; border-radius: 5px; font-size: 10px;
                font-weight: 600; white-space: nowrap;
            }
            .ms-card-title {
                font-size: 15.5px; font-weight: 800; color: #fff; line-height: 1.35;
                margin: 0; position: relative; z-index: 1;
            }
            .ms-card-sub {
                font-size: 11px; color: rgba(255,255,255,.6);
                margin-top: 4px; position: relative; z-index: 1;
            }

            /* Card body */
            .ms-card-body { padding: 16px 20px; flex: 1; }

            /* Instructor / schedule row */
            .ms-info-row {
                display: flex; flex-wrap: wrap; gap: 6px 16px;
                margin-bottom: 14px; padding-bottom: 14px;
                border-bottom: 1px solid #f1f5f9;
            }
            .ms-info-item { display: flex; align-items: center; gap: 5px; font-size: 12px; color: #64748b; }
            .ms-info-item svg { flex-shrink: 0; color: #94a3b8; }

            /* Stats chips */
            .ms-stats { display: flex; gap: 8px; margin-bottom: 14px; }
            .ms-stat {
                flex: 1; background: #f8fafc; border-radius: 10px;
                padding: 10px 6px; text-align: center;
            }
            .ms-stat-num { font-size: 20px; font-weight: 800; color: #0f172a; line-height: 1; }
            .ms-stat-lbl { font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; margin-top: 3px; }

            /* Progress */
            .ms-prog { display: flex; flex-direction: column; gap: 9px; }
            .ms-prog-row { display: flex; flex-direction: column; gap: 5px; }
            .ms-prog-head { display: flex; justify-content: space-between; align-items: center; }
            .ms-prog-lbl { font-size: 11px; font-weight: 600; color: #64748b; }
            .ms-prog-pct { font-size: 11px; font-weight: 700; color: #0f172a; }
            .ms-prog-track { height: 6px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
            .ms-prog-fill  { height: 100%; border-radius: 99px; transition: width .8s cubic-bezier(.4,0,.2,1); }

            /* Card footer */
            .ms-card-footer {
                padding: 12px 20px 14px;
                display: flex; gap: 8px;
            }
            .ms-btn {
                flex: 1; display: inline-flex; align-items: center;
                justify-content: center; gap: 5px;
                padding: 8px 6px; border-radius: 9px; font-size: 12px;
                font-weight: 600; text-decoration: none;
                transition: all .15s; white-space: nowrap;
            }
            .ms-btn-primary {
                background: #1B4D3E; color: #fff; border: none;
            }
            .ms-btn-primary:hover { background: #2D6A4F; }
            .ms-btn-ghost {
                background: #f8fafc; color: #475569;
                border: 1px solid #e2e8f0;
            }
            .ms-btn-ghost:hover { background: #f1f5f9; border-color: #cbd5e1; }

            /* Empty state */
            .ms-empty {
                display: flex; flex-direction: column; align-items: center;
                justify-content: center; min-height: 280px; background: #fff;
                border-radius: 16px; border: 2px dashed #e2e8f0;
                text-align: center; padding: 40px;
                box-shadow: 0 1px 3px rgba(0,0,0,.05);
            }
            .ms-empty-icon {
                width: 56px; height: 56px; background: #f1f5f9;
                border-radius: 16px; display: flex; align-items: center;
                justify-content: center; margin-bottom: 16px;
            }
            .ms-empty h3 { font-size: 16px; font-weight: 700; color: #334155; margin: 0 0 6px; }
            .ms-empty p  { font-size: 13px; color: #94a3b8; margin: 0 0 20px; }

            .ms-no-results {
                grid-column: 1/-1; text-align: center;
                padding: 56px 20px; color: #94a3b8; font-size: 14px;
            }

            @media(max-width: 640px) {
                .ms-grid { grid-template-columns: 1fr; }
                .ms-search-wrap { width: 100%; }
                .ms-filterbar { flex-direction: column; align-items: stretch; }
                .ms-count-pill { margin-left: 0; text-align: center; }
            }
        </style>

        <!-- Top row: title + buttons -->
        <div class="ms-topbar">
            <div class="ms-title-block">
                <h2>My Subjects</h2>
                <p>View your enrolled subjects and access lessons and quizzes</p>
            </div>
            <div class="ms-topbar-actions">
                <a href="#student/announcements" class="ms-announce-btn">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46"/></svg>
                    Announcements
                </a>
                <a href="#student/enroll" class="ms-enroll-btn">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Enroll in Section
                </a>
            </div>
        </div>

        ${subjects.length === 0 ? `
            <div class="ms-empty">
                <div class="ms-empty-icon">
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#94a3b8" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                </div>
                <h3>No subjects yet</h3>
                <p>You haven't enrolled in any section yet.</p>
                <a href="#student/enroll" class="ms-enroll-btn">Browse Sections</a>
            </div>
        ` : `
            <!-- Filter bar: compact search + optional section filter + pill count -->
            <div class="ms-filterbar">
                <div class="ms-search-wrap">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/></svg>
                    <input class="ms-search" id="ms-search" placeholder="Search subjects…" autocomplete="off">
                </div>
                ${sectionNames.length > 1 ? `
                <select class="ms-filter-sel" id="ms-sec-filter">
                    <option value="">All Sections</option>
                    ${sectionNames.map(n => `<option value="${esc(n)}">${esc(n)}</option>`).join('')}
                </select>` : ''}
                <span class="ms-count-pill" id="ms-result-info">${subjects.length} subject${subjects.length !== 1 ? 's' : ''}</span>
            </div>

            <div class="ms-grid" id="ms-grid">
                ${subjects.map(s => {
                    const lDone  = parseInt(s.completed_lessons || 0);
                    const lTotal = parseInt(s.total_lessons || 0);
                    const lPct   = s.progress || 0;
                    const qDone  = parseInt(s.completed_quizzes || 0);
                    const qTotal = parseInt(s.total_quizzes || 0);
                    const qPct   = qTotal > 0 ? Math.round((qDone / qTotal) * 100) : 0;
                    const sem    = s.semester_name || s.school_year || '';
                    return `
                    <div class="ms-card"
                         data-search="${esc((s.subject_code + ' ' + s.subject_name).toLowerCase())}"
                         data-section="${esc(s.section_name || '')}">

                        <div class="ms-card-banner">
                            <div class="ms-banner-top">
                                <span class="ms-code">${esc(s.subject_code)}</span>
                                ${s.section_name ? `<span class="ms-section-tag">${esc(s.section_name)}</span>` : ''}
                            </div>
                            <div class="ms-card-title">${esc(s.subject_name)}</div>
                            ${sem ? `<div class="ms-card-sub">${esc(sem)}</div>` : ''}
                        </div>

                        <div class="ms-card-body">
                            ${(s.instructor_name || s.schedule || s.room) ? `
                            <div class="ms-info-row">
                                ${s.instructor_name ? `<span class="ms-info-item"><svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>${esc(s.instructor_name)}</span>` : ''}
                                ${s.schedule ? `<span class="ms-info-item"><svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>${esc(s.schedule)}</span>` : ''}
                                ${s.room ? `<span class="ms-info-item"><svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>${esc(s.room)}</span>` : ''}
                            </div>` : ''}

                            <div class="ms-stats">
                                <div class="ms-stat">
                                    <div class="ms-stat-num">${lTotal}</div>
                                    <div class="ms-stat-lbl">Lessons</div>
                                </div>
                                <div class="ms-stat">
                                    <div class="ms-stat-num">${qTotal}</div>
                                    <div class="ms-stat-lbl">Quizzes</div>
                                </div>
                                <div class="ms-stat">
                                    <div class="ms-stat-num">${s.units || 0}</div>
                                    <div class="ms-stat-lbl">Units</div>
                                </div>
                            </div>

                            <div class="ms-prog">
                                <div class="ms-prog-row">
                                    <div class="ms-prog-head">
                                        <span class="ms-prog-lbl">Lessons</span>
                                        <span class="ms-prog-pct">${lDone}/${lTotal} &nbsp;<span style="color:#94a3b8;font-weight:500">${lPct}%</span></span>
                                    </div>
                                    <div class="ms-prog-track">
                                        <div class="ms-prog-fill" style="width:${lPct}%;background:linear-gradient(90deg,#1B4D3E,#2D6A4F)"></div>
                                    </div>
                                </div>
                                <div class="ms-prog-row">
                                    <div class="ms-prog-head">
                                        <span class="ms-prog-lbl">Quizzes</span>
                                        <span class="ms-prog-pct">${qDone}/${qTotal} &nbsp;<span style="color:#94a3b8;font-weight:500">${qPct}%</span></span>
                                    </div>
                                    <div class="ms-prog-track">
                                        <div class="ms-prog-fill" style="width:${qPct}%;background:linear-gradient(90deg,#0891b2,#06b6d4)"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="ms-card-footer">
                            <a class="ms-btn ms-btn-primary" href="#student/lessons?subject_id=${s.subject_id}">
                                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                                Lessons
                            </a>
                            <a class="ms-btn ms-btn-ghost" href="#student/quizzes?subject_id=${s.subject_id}">
                                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>
                                Quizzes
                            </a>
                            <a class="ms-btn ms-btn-ghost" href="#student/progress?subject_id=${s.subject_id}">
                                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/></svg>
                                Progress
                            </a>
                        </div>
                    </div>`;
                }).join('')}
                <div class="ms-no-results" id="ms-no-results" style="display:none">
                    No subjects match your search.
                </div>
            </div>
        `}
    `;

    if (subjects.length === 0) return;

    const searchEl  = container.querySelector('#ms-search');
    const secFilter = container.querySelector('#ms-sec-filter');
    const resultInfo = container.querySelector('#ms-result-info');
    const cards     = [...container.querySelectorAll('.ms-card')];
    const noResults = container.querySelector('#ms-no-results');

    function applyFilters() {
        const q   = searchEl.value.toLowerCase().trim();
        const sec = secFilter?.value || '';
        let visible = 0;
        cards.forEach(card => {
            const matchSearch  = !q   || card.dataset.search.includes(q);
            const matchSection = !sec || card.dataset.section === sec;
            const show = matchSearch && matchSection;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        resultInfo.textContent = `${visible} of ${subjects.length} subject${subjects.length !== 1 ? 's' : ''}`;
        noResults.style.display = visible === 0 ? '' : 'none';
    }

    searchEl.addEventListener('input', applyFilters);
    secFilter?.addEventListener('change', applyFilters);
}

function esc(str) { const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }
