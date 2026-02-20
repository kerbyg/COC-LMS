/**
 * Student Dashboard
 * Clean overview of enrolled subjects, progress, and quick actions
 */
import { Api } from '../../api.js';
import { Auth } from '../../auth.js';

export async function render(container) {
    container.innerHTML = '<div style="display:flex;justify-content:center;padding:60px"><div style="width:40px;height:40px;border:3px solid #e8e8e8;border-top-color:#1B4D3E;border-radius:50%;animation:spin .8s linear infinite"></div></div>';

    const res = await Api.get('/DashboardAPI.php?action=student');
    const data = res.success ? res.data : {};
    const stats = data.stats || {};
    const subjects = data.subjects || [];
    const user = Auth.user();

    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good Morning' : hour < 18 ? 'Good Afternoon' : 'Good Evening';
    const today = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

    container.innerHTML = `
        <style>
            /* ===== Banner ===== */
            .sd-banner {
                background: linear-gradient(135deg, #1B4D3E 0%, #2D6A4F 60%, #40916C 100%);
                border-radius: 16px; padding: 28px 32px; color: #fff;
                margin-bottom: 24px; position: relative; overflow: hidden;
            }
            .sd-banner::before {
                content: ''; position: absolute; right: -30px; top: -30px;
                width: 180px; height: 180px; border-radius: 50%;
                background: rgba(255,255,255,0.04);
            }
            .sd-banner h2 { font-size: 22px; font-weight: 700; margin: 0 0 4px; }
            .sd-banner p { margin: 0; opacity: 0.7; font-size: 13px; }

            /* ===== Stats ===== */
            .sd-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
            .sd-stat {
                background: #fff; border: 1px solid #e8e8e8; border-radius: 14px;
                padding: 20px; display: flex; align-items: center; gap: 16px;
            }
            .sd-stat-icon {
                width: 48px; height: 48px; border-radius: 12px;
                display: flex; align-items: center; justify-content: center;
                flex-shrink: 0;
            }
            .sd-stat-val { font-size: 28px; font-weight: 800; color: #1f2937; line-height: 1; }
            .sd-stat-lbl { font-size: 12px; color: #6b7280; font-weight: 500; margin-top: 2px; }

            /* ===== Quick Actions ===== */
            .sd-actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px; }
            .sd-action {
                display: flex; align-items: center; gap: 14px;
                padding: 18px 20px; background: #fff; border: 1px solid #e8e8e8;
                border-radius: 14px; text-decoration: none; color: #374151;
                transition: all 0.2s;
            }
            .sd-action:hover {
                border-color: #1B4D3E; background: #f0fdf4;
                transform: translateY(-2px); box-shadow: 0 4px 12px rgba(27,77,62,0.1);
            }
            .sd-action-icon {
                width: 44px; height: 44px; border-radius: 12px;
                display: flex; align-items: center; justify-content: center;
                flex-shrink: 0;
            }
            .sd-action-text { font-size: 14px; font-weight: 600; }
            .sd-action-sub { font-size: 11px; color: #9ca3af; font-weight: 400; margin-top: 1px; }

            /* ===== Responsive ===== */
            @media(max-width: 1100px) {
                .sd-stats { grid-template-columns: repeat(2, 1fr); }
                .sd-actions { grid-template-columns: repeat(2, 1fr); }
            }
            @media(max-width: 640px) {
                .sd-stats { grid-template-columns: 1fr; }
                .sd-actions { grid-template-columns: 1fr; }
                .sd-subjects { grid-template-columns: 1fr; }
            }
        </style>

        <!-- Banner -->
        <div class="sd-banner">
            <h2>${greeting}, ${esc(user.first_name)}</h2>
            <p>${today}</p>
        </div>

        <!-- Stats -->
        <div class="sd-stats">
            <div class="sd-stat">
                <div class="sd-stat-icon" style="background:#E8F5E9;color:#1B4D3E">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                </div>
                <div>
                    <div class="sd-stat-val">${stats.subjects || 0}</div>
                    <div class="sd-stat-lbl">Enrolled Subjects</div>
                </div>
            </div>
            <div class="sd-stat">
                <div class="sd-stat-icon" style="background:#DBEAFE;color:#1E40AF">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342"/></svg>
                </div>
                <div>
                    <div class="sd-stat-val">${stats.lessons_completed || 0}<span style="font-size:16px;color:#6b7280;font-weight:500">/${stats.total_lessons || 0}</span></div>
                    <div class="sd-stat-lbl">Lessons Done</div>
                </div>
            </div>
            <div class="sd-stat">
                <div class="sd-stat-icon" style="background:#FEF3C7;color:#92400E">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>
                </div>
                <div>
                    <div class="sd-stat-val">${stats.total_quizzes || 0}</div>
                    <div class="sd-stat-lbl">Quizzes Available</div>
                </div>
            </div>
            <div class="sd-stat">
                <div class="sd-stat-icon" style="background:#EDE9FE;color:#5B21B6">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                </div>
                <div>
                    <div class="sd-stat-val">${stats.avg_score || 0}%</div>
                    <div class="sd-stat-lbl">Average Score</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="sd-actions">
            <a href="#student/enroll" class="sd-action">
                <div class="sd-action-icon" style="background:#E8F5E9;color:#1B4D3E">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                </div>
                <div>
                    <div class="sd-action-text">Enroll in Section</div>
                    <div class="sd-action-sub">Join a new class</div>
                </div>
            </a>
            <a href="#student/lessons" class="sd-action">
                <div class="sd-action-icon" style="background:#DBEAFE;color:#1E40AF">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                </div>
                <div>
                    <div class="sd-action-text">My Lessons</div>
                    <div class="sd-action-sub">Continue learning</div>
                </div>
            </a>
            <a href="#student/grades" class="sd-action">
                <div class="sd-action-icon" style="background:#FEF3C7;color:#92400E">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                </div>
                <div>
                    <div class="sd-action-text">View Grades</div>
                    <div class="sd-action-sub">Check your scores</div>
                </div>
            </a>
            <a href="#student/progress" class="sd-action">
                <div class="sd-action-icon" style="background:#EDE9FE;color:#5B21B6">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/></svg>
                </div>
                <div>
                    <div class="sd-action-text">My Progress</div>
                    <div class="sd-action-sub">Track your learning</div>
                </div>
            </a>
        </div>

    `;
}

function esc(str) { const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }
