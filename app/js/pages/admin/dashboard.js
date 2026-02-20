/**
 * Admin Dashboard Page
 * Shows system overview and statistics
 */

import { Api } from '../../api.js';
import { Auth } from '../../auth.js';

export async function render(container) {
    // Fetch dashboard data from API
    const result = await Api.get('/DashboardAPI.php?action=admin');

    if (!result.success) {
        container.innerHTML = '<div class="alert alert-danger">Failed to load dashboard data.</div>';
        return;
    }

    const s = result.data.stats;
    const users = result.data.recent_users;
    const today = new Date().toLocaleDateString('en-US', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });

    container.innerHTML = `
        <style>
            .dash-wrap { max-width: 1400px; }
            .dash-banner {
                background: linear-gradient(135deg, #00461B 0%, #006428 50%, #004d1f 100%);
                border-radius: 24px; padding: 32px 40px; margin-bottom: 28px;
                display: flex; justify-content: space-between; align-items: center;
                position: relative; overflow: hidden; box-shadow: 0 8px 24px -4px rgba(0, 70, 27, 0.25);
            }
            .dash-banner::before {
                content: ''; position: absolute; top: -50%; right: -10%;
                width: 400px; height: 400px;
                background: rgba(255,255,255,0.05); border-radius: 50%;
            }
            .banner-text { position: relative; z-index: 1; }
            .banner-text h1 { font-size: 28px; font-weight: 800; color: #fff; margin-bottom: 8px; }
            .banner-text p { color: rgba(255,255,255,0.85); font-size: 15px; }
            .banner-date { color: rgba(255,255,255,0.7); font-size: 14px; position: relative; z-index: 1; }

            .stat-row {
                display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
                gap: 20px; margin-bottom: 28px;
            }
            .stat-card-new {
                background: #fff; border-radius: 16px; padding: 24px;
                border: 1px solid #e8e8e8; transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
                position: relative; overflow: hidden;
            }
            .stat-card-new::before {
                content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%;
                background: linear-gradient(135deg, #00461B, #006428); opacity: 0; transition: all 0.3s;
            }
            .stat-card-new:hover { transform: translateY(-4px); box-shadow: 0 6px 16px -4px rgba(0,0,0,0.12); border-color: #00461B; }
            .stat-card-new:hover::before { opacity: 1; }
            .stat-card-new .sc-icon {
                width: 56px; height: 56px; border-radius: 12px;
                display: flex; align-items: center; justify-content: center; font-size: 26px; margin-bottom: 16px;
            }
            .sc-icon.green { background: linear-gradient(135deg, #D1FAE5, #A7F3D0); }
            .sc-icon.blue { background: linear-gradient(135deg, #DBEAFE, #BFDBFE); }
            .sc-icon.yellow { background: linear-gradient(135deg, #FEF3C7, #FDE68A); }
            .sc-icon.purple { background: linear-gradient(135deg, #EDE9FE, #DDD6FE); }
            .sc-num { display: block; font-size: 32px; font-weight: 800; color: #262626; line-height: 1.2; }
            .sc-label { display: block; font-size: 13px; color: #737373; font-weight: 500; margin-top: 4px; }
            .sc-pills { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
            .pill { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
            .pill-green { background: #E8F5E9; color: #1B4D3E; }
            .pill-amber { background: #FEF3C7; color: #B45309; }
            .pill-blue { background: #DBEAFE; color: #1E40AF; }
            .pill-muted { background: #F5F5F5; color: #737373; }

            .dash-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
            .dash-card {
                background: #fff; border-radius: 16px; border: 1px solid #e8e8e8; overflow: hidden;
            }
            .dash-card-head {
                padding: 20px 24px; border-bottom: 1px solid #f5f5f5;
                display: flex; justify-content: space-between; align-items: center;
            }
            .dash-card-head h3 { font-size: 16px; font-weight: 700; color: #262626; }

            .user-row {
                display: flex; align-items: center; gap: 12px;
                padding: 14px 24px; border-bottom: 1px solid #f5f5f5; transition: all 0.15s;
            }
            .user-row:last-child { border-bottom: none; }
            .user-row:hover { background: #fafafa; }
            .user-av {
                width: 40px; height: 40px; border-radius: 50%; font-weight: 700; font-size: 13px;
                display: flex; align-items: center; justify-content: center; flex-shrink: 0;
            }
            .user-av.admin { background: #DBEAFE; color: #1E40AF; }
            .user-av.instructor { background: #D1FAE5; color: #065F46; }
            .user-av.student { background: #FEF3C7; color: #92400E; }
            .user-av.dean { background: #EDE9FE; color: #5B21B6; }
            .user-meta { flex: 1; min-width: 0; }
            .user-meta-name { font-weight: 600; font-size: 14px; color: #262626; display: block; }
            .user-meta-email { font-size: 12px; color: #737373; display: block; }
            .role-badge {
                padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: capitalize;
            }
            .role-badge.admin { background: #DBEAFE; color: #1E40AF; }
            .role-badge.instructor { background: #D1FAE5; color: #065F46; }
            .role-badge.student { background: #FEF3C7; color: #92400E; }
            .role-badge.dean { background: #EDE9FE; color: #5B21B6; }
            .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
            .status-dot.active { background: #10B981; }
            .status-dot.inactive { background: #EF4444; }
            .user-date { font-size: 13px; color: #737373; white-space: nowrap; }

            .quick-links { padding: 16px 20px; }
            .quick-link {
                display: flex; align-items: center; gap: 12px;
                padding: 14px 16px; border-radius: 12px; margin-bottom: 8px;
                color: #404040; font-weight: 500; font-size: 14px;
                transition: all 0.2s; text-decoration: none; cursor: pointer;
            }
            .quick-link:hover { background: #F7F5E8; color: #00461B; }
            .quick-link span:first-child { font-size: 18px; }

            @media (max-width: 1024px) { .dash-grid { grid-template-columns: 1fr; } }
            @media (max-width: 768px) { .stat-row { grid-template-columns: 1fr; } }
        </style>

        <div class="dash-wrap">
            <!-- Welcome Banner -->
            <div class="dash-banner">
                <div class="banner-text">
                    <h1>Welcome back, ${escapeHtml(Auth.user().name)}</h1>
                    <p>Here's what's happening across the system today.</p>
                </div>
                <div class="banner-date">${today}</div>
            </div>

            <!-- Stats -->
            <div class="stat-row">
                <div class="stat-card-new">
                    <div class="sc-icon green">👥</div>
                    <span class="sc-num">${s.total_users}</span>
                    <span class="sc-label">Total Users</span>
                    <div class="sc-pills">
                        <span class="pill pill-green">${s.total_students} Students</span>
                        <span class="pill pill-amber">${s.total_instructors} Instructors</span>
                        <span class="pill pill-blue">${s.total_deans} Deans</span>
                    </div>
                </div>
                <div class="stat-card-new">
                    <div class="sc-icon blue">🎓</div>
                    <span class="sc-num">${s.total_programs}</span>
                    <span class="sc-label">Programs</span>
                    <div class="sc-pills">
                        <span class="pill pill-muted">${s.total_departments} Departments</span>
                        <span class="pill pill-muted">${s.total_subjects} Subjects</span>
                    </div>
                </div>
                <div class="stat-card-new">
                    <div class="sc-icon yellow">📖</div>
                    <span class="sc-num">${s.total_lessons}</span>
                    <span class="sc-label">Lessons</span>
                    <div class="sc-pills">
                        <span class="pill pill-muted">${s.total_quizzes} Quizzes</span>
                        <span class="pill pill-muted">${s.total_offerings} Offerings</span>
                    </div>
                </div>
                <div class="stat-card-new">
                    <div class="sc-icon purple">🏫</div>
                    <span class="sc-num">${s.total_sections}</span>
                    <span class="sc-label">Active Sections</span>
                    <div class="sc-pills">
                        <span class="pill pill-green">${s.total_enrolled} Enrolled</span>
                        <span class="pill pill-blue">${s.total_faculty_assigned} Faculty Assigned</span>
                    </div>
                </div>
            </div>

            <!-- Grid: Recent Users + Quick Actions -->
            <div class="dash-grid">
                <div class="dash-card">
                    <div class="dash-card-head">
                        <h3>Recent Users</h3>
                        <a href="#admin/users" class="view-all">View All →</a>
                    </div>
                    <div>
                        ${users.map(u => {
                            const initials = ((u.first_name || '?')[0] + (u.last_name || '?')[0]).toUpperCase();
                            const date = new Date(u.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                            return `
                                <div class="user-row">
                                    <div class="user-av ${u.role}">${initials}</div>
                                    <div class="user-meta">
                                        <span class="user-meta-name">${escapeHtml(u.first_name + ' ' + u.last_name)}</span>
                                        <span class="user-meta-email">${escapeHtml(u.email)}</span>
                                    </div>
                                    <span class="role-badge ${u.role}">${u.role}</span>
                                    <span style="font-size:13px;color:#737373"><span class="status-dot ${u.status}"></span>${u.status}</span>
                                    <span class="user-date">${date}</span>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>

                <div class="dash-card">
                    <div class="dash-card-head">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="quick-links">
                        <a href="#admin/users" class="quick-link"><span>👤</span><span>Add New User</span></a>
                        <a href="#admin/programs" class="quick-link"><span>🎓</span><span>Add Program</span></a>
                        <a href="#admin/subjects" class="quick-link"><span>📚</span><span>Manage Subjects</span></a>
                        <a href="#admin/subject-offerings" class="quick-link"><span>📅</span><span>Subject Offerings</span></a>
                        <a href="#admin/sections" class="quick-link"><span>🏫</span><span>Manage Sections</span></a>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}
