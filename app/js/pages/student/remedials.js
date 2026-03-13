/**
 * Student Remedials Page
 * View assigned remedial tasks
 */
import { Api } from '../../api.js';

export async function render(container) {
    const res = await Api.get('/RemedialAPI.php?action=student-list');
    const remedials = res.success ? res.data : [];

    const pending    = remedials.filter(r => r.status === 'pending').length;
    const inProgress = remedials.filter(r => r.status === 'in_progress').length;
    const completed  = remedials.filter(r => r.status === 'completed').length;

    container.innerHTML = `
        <style>
            /* ── Banner ── */
            .rm-banner {
                background: linear-gradient(135deg, #1B4D3E 0%, #2D6A4F 60%, #40916C 100%);
                border-radius: 16px; padding: 24px 28px; color: #fff;
                margin-bottom: 24px; position: relative; overflow: hidden;
            }
            .rm-banner::before {
                content: ''; position: absolute; right: -20px; top: -20px;
                width: 140px; height: 140px; border-radius: 50%;
                background: rgba(255,255,255,.05);
            }
            .rm-banner h2 { font-size: 22px; font-weight: 800; margin: 0 0 4px; }
            .rm-banner p  { margin: 0; opacity: .75; font-size: 13px; }

            /* ── Stats row ── */
            .rm-stats {
                display: grid; grid-template-columns: repeat(3,1fr);
                gap: 14px; margin-bottom: 24px;
            }
            .rm-stat {
                background: #fff; border-radius: 14px;
                border: 1px solid #f1f5f9;
                box-shadow: 0 1px 3px rgba(0,0,0,.07);
                padding: 18px 20px; display: flex; align-items: center; gap: 14px;
            }
            .rm-stat-icon {
                width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0;
                display: flex; align-items: center; justify-content: center; font-size: 20px;
            }
            .rm-stat-val { font-size: 26px; font-weight: 800; color: #111827; line-height: 1; }
            .rm-stat-lbl { font-size: 12px; color: #9CA3AF; margin-top: 2px; }

            /* ── Section header ── */
            .rm-section-hdr {
                display: flex; align-items: center; gap: 10px; margin-bottom: 14px;
            }
            .rm-section-hdr h3 { font-size: 16px; font-weight: 700; color: #111827; margin: 0; }
            .rm-count-pill {
                background: #f1f5f9; color: #64748b;
                font-size: 11px; font-weight: 700;
                padding: 2px 8px; border-radius: 12px;
            }

            /* ── Remedial cards ── */
            .rm-list { display: flex; flex-direction: column; gap: 12px; }
            .rm-card {
                background: #fff; border-radius: 14px;
                border: 1px solid #f1f5f9;
                box-shadow: 0 1px 3px rgba(0,0,0,.07), 0 1px 2px rgba(0,0,0,.04);
                padding: 18px 20px;
                display: flex; justify-content: space-between; align-items: center;
                flex-wrap: wrap; gap: 14px;
                transition: box-shadow .2s, transform .2s;
            }
            .rm-card:hover {
                box-shadow: 0 6px 20px rgba(0,0,0,.09);
                transform: translateY(-1px);
            }

            .rm-card-left { display: flex; align-items: flex-start; gap: 14px; flex: 1; min-width: 200px; }
            .rm-card-icon {
                width: 42px; height: 42px; border-radius: 10px;
                background: #f8fafc; display: flex; align-items: center;
                justify-content: center; font-size: 18px; flex-shrink: 0;
            }
            .rm-card-info { flex: 1; min-width: 0; }
            .rm-quiz-title { font-size: 15px; font-weight: 700; color: #111827; margin-bottom: 4px; }
            .rm-assigned { font-size: 11px; color: #9CA3AF; font-weight: 400; margin-left: 8px; }
            .rm-subject-row { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; }
            .rm-code {
                background: #E8F5E9; color: #1B4D3E;
                padding: 2px 7px; border-radius: 5px;
                font-family: monospace; font-size: 11px; font-weight: 700;
            }
            .rm-subject-name { font-size: 13px; color: #64748b; }
            .rm-reason { font-size: 12px; color: #9CA3AF; font-style: italic; margin-top: 2px; }
            .rm-lesson-link {
                display: inline-flex; align-items: center; gap: 4px;
                font-size: 12px; color: #1B4D3E; font-weight: 600;
                text-decoration: none; margin-top: 6px;
            }
            .rm-lesson-link:hover { text-decoration: underline; }

            /* ── Card right (status + actions) ── */
            .rm-card-right {
                display: flex; flex-direction: column; align-items: flex-end; gap: 8px; flex-shrink: 0;
            }
            .rm-badge {
                display: inline-flex; align-items: center;
                padding: 4px 12px; border-radius: 20px;
                font-size: 11px; font-weight: 700; text-transform: capitalize;
            }
            .rm-badge-pending    { background: #FEF3C7; color: #B45309; }
            .rm-badge-progress   { background: #DBEAFE; color: #1E40AF; }
            .rm-badge-completed  { background: #DCFCE7; color: #15803D; }
            .rm-due { font-size: 11px; color: #9CA3AF; }

            .rm-actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
            .rm-btn {
                display: inline-flex; align-items: center; gap: 5px;
                padding: 7px 14px; border-radius: 8px;
                font-size: 12px; font-weight: 600; text-decoration: none;
                border: none; cursor: pointer; transition: all .15s;
            }
            .rm-btn-primary { background: #1B4D3E; color: #fff; }
            .rm-btn-primary:hover { background: #2D6A4F; }
            .rm-btn-ghost {
                background: #f8fafc; color: #475569;
                border: 1px solid #e2e8f0;
            }
            .rm-btn-ghost:hover { background: #f1f5f9; border-color: #cbd5e1; }

            /* ── Empty state ── */
            .rm-empty {
                display: flex; flex-direction: column; align-items: center;
                justify-content: center; min-height: 240px; background: #fff;
                border-radius: 16px; border: 2px dashed #e2e8f0;
                text-align: center; padding: 40px;
            }
            .rm-empty-icon {
                width: 52px; height: 52px; background: #f1f5f9;
                border-radius: 14px; display: flex; align-items: center;
                justify-content: center; margin-bottom: 14px; font-size: 24px;
            }
            .rm-empty h3 { font-size: 16px; font-weight: 700; color: #334155; margin: 0 0 6px; }
            .rm-empty p  { font-size: 13px; color: #94a3b8; margin: 0; }

            @media(max-width: 768px) {
                .rm-stats { grid-template-columns: 1fr; }
                .rm-card { flex-direction: column; align-items: flex-start; }
                .rm-card-right { align-items: flex-start; }
            }

            .btn-back {
                display: inline-flex; align-items: center; gap: 6px;
                font-size: 13px; font-weight: 600; color: #6B7280;
                text-decoration: none; margin-bottom: 16px; transition: color .15s;
            }
            .btn-back:hover { color: #1B4D3E; }
        </style>

        <a href="#student/grades" class="btn-back">← Back to My Grades</a>

        <!-- Banner -->
        <div class="rm-banner">
            <h2>Remedials</h2>
            <p>${remedials.length} assigned task${remedials.length !== 1 ? 's' : ''} · ${completed} completed</p>
        </div>

        <!-- Stats -->
        <div class="rm-stats">
            <div class="rm-stat">
                <div class="rm-stat-icon" style="background:#FEF3C7">⏳</div>
                <div>
                    <div class="rm-stat-val" style="color:#B45309">${pending}</div>
                    <div class="rm-stat-lbl">Pending</div>
                </div>
            </div>
            <div class="rm-stat">
                <div class="rm-stat-icon" style="background:#DBEAFE">🔄</div>
                <div>
                    <div class="rm-stat-val" style="color:#1E40AF">${inProgress}</div>
                    <div class="rm-stat-lbl">In Progress</div>
                </div>
            </div>
            <div class="rm-stat">
                <div class="rm-stat-icon" style="background:#DCFCE7">✓</div>
                <div>
                    <div class="rm-stat-val" style="color:#1B4D3E">${completed}</div>
                    <div class="rm-stat-lbl">Completed</div>
                </div>
            </div>
        </div>

        ${remedials.length === 0 ? `
            <div class="rm-empty">
                <div class="rm-empty-icon">🎯</div>
                <h3>No remedial assignments</h3>
                <p>Keep up the good work! You have no pending remedials.</p>
            </div>
        ` : `
            <div class="rm-section-hdr">
                <h3>Assignments</h3>
                <span class="rm-count-pill">${remedials.length}</span>
            </div>

            <div class="rm-list">
                ${remedials.map(r => `
                    <div class="rm-card">
                        <div class="rm-card-left">
                            <div class="rm-card-icon">
                                ${r.status === 'completed' ? '✅' : r.status === 'in_progress' ? '🔄' : '📋'}
                            </div>
                            <div class="rm-card-info">
                                <div class="rm-quiz-title">
                                    ${esc(r.quiz_title)}
                                    ${r.created_at ? `<span class="rm-assigned">· Assigned ${new Date(r.created_at).toLocaleDateString('en-US',{month:'short',day:'numeric'})}</span>` : ''}
                                </div>
                                <div class="rm-subject-row">
                                    <span class="rm-code">${esc(r.subject_code || '')}</span>
                                    <span class="rm-subject-name">${esc(r.subject_name || '')}</span>
                                </div>
                                ${r.reason ? `<div class="rm-reason">"${esc(r.reason)}"</div>` : ''}
                                ${r.linked_lessons_id && r.status !== 'completed' ? `
                                    <a class="rm-lesson-link" href="#student/lesson-view?id=${r.linked_lessons_id}">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0118 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                                        Review: ${esc(r.linked_lesson_title || 'View Lesson')}
                                    </a>` : ''}
                            </div>
                        </div>
                        <div class="rm-card-right">
                            <span class="rm-badge rm-badge-${r.status === 'in_progress' ? 'progress' : r.status}">${r.status.replace('_', ' ')}</span>
                            ${r.due_date ? `<span class="rm-due">Due ${new Date(r.due_date).toLocaleDateString('en-US',{month:'short',day:'numeric'})}</span>` : ''}
                            ${r.status !== 'completed' ? `
                                <div class="rm-actions">
                                    ${r.linked_lessons_id ? `<a class="rm-btn rm-btn-ghost" href="#student/lesson-view?id=${r.linked_lessons_id}">Study</a>` : ''}
                                    <a class="rm-btn rm-btn-primary" href="#student/take-quiz?quiz_id=${r.quiz_id}">Retake Quiz</a>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        `}
    `;
}

function esc(str) { const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }
