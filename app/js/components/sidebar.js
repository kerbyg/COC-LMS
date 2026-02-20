/**
 * Sidebar Component
 * Renders the navigation sidebar based on user role
 */

import { Auth } from '../auth.js';

// Menu configurations per role
const menus = {
    admin: [
        { section: 'Main', items: [
            { icon: '📊', text: 'Dashboard', page: 'dashboard' }
        ]},
        { section: 'Management', items: [
            { icon: '👥', text: 'Users', page: 'users' },
            { icon: '🏢', text: 'Departments', page: 'departments' },
            { icon: '🎓', text: 'Programs', page: 'programs' },
            { icon: '📚', text: 'Subjects', page: 'subjects' },
            { icon: '📋', text: 'Curriculum', page: 'curriculum' },
            { icon: '📅', text: 'Subject Offerings', page: 'subject-offerings' },
            { icon: '🏫', text: 'Sections', page: 'sections' },
            { icon: '👨‍🏫', text: 'Faculty Assignments', page: 'faculty-assignments' }
        ]},
        { section: 'System', items: [
            { icon: '⚙️', text: 'Settings', page: 'settings' }
        ]}
    ],
    dean: [
        { section: 'Main', items: [
            { icon: '📊', text: 'Dashboard', page: 'dashboard' }
        ]},
        { section: 'Academic', items: [
            { icon: '👨‍🏫', text: 'Instructors', page: 'instructors' },
            { icon: '📚', text: 'Subjects', page: 'subjects' },
            { icon: '📅', text: 'Subject Offerings', page: 'subject-offerings' },
            { icon: '🏫', text: 'Sections', page: 'sections' },
            { icon: '👥', text: 'Faculty Assignments', page: 'faculty-assignments' },
            { icon: '📈', text: 'Reports', page: 'reports' }
        ]}
    ],
    instructor: [
        { section: 'Main', items: [
            { icon: '📊', text: 'Dashboard', page: 'dashboard' }
        ]},
        { section: 'Teaching', items: [
            { icon: '📚', text: 'My Classes', page: 'my-classes' },
            { icon: '👥', text: 'Students', page: 'students' },
            { icon: '📖', text: 'Lessons', page: 'lessons' },
            { icon: '🏦', text: 'Content Bank', page: 'content-bank' },
            { icon: '📝', text: 'Quizzes', page: 'quizzes' },
            { icon: '🤖', text: 'AI Quiz Generator', page: 'quiz-ai-generate' }
        ]},
        { section: 'Assessment', items: [
            { icon: '📋', text: 'Gradebook', page: 'gradebook' },
            { icon: '📈', text: 'Analytics', page: 'analytics' },
            { icon: '📌', text: 'Remedials', page: 'remedials' },
            { icon: '✏️', text: 'Essay Grading', page: 'essay-grading' }
        ]},
        { section: 'Communication', items: [
            { icon: '📢', text: 'Announcements', page: 'announcements' }
        ]}
    ],
    student: [
        { section: 'Main', items: [
            { icon: '📊', text: 'Dashboard', page: 'dashboard' }
        ]},
        { section: 'Learning', items: [
            { icon: '🎓', text: 'Enroll in Section', page: 'enroll' },
            { icon: '📚', text: 'My Subjects', page: 'my-subjects' },
            { icon: '📖', text: 'Lessons', page: 'lessons' },
            { icon: '📝', text: 'Quizzes', page: 'quizzes' }
        ]},
        { section: 'Progress', items: [
            { icon: '📋', text: 'My Grades', page: 'grades' },
            { icon: '📈', text: 'My Progress', page: 'progress' },
            { icon: '📌', text: 'Remedials', page: 'remedials' }
        ]},
        { section: 'Updates', items: [
            { icon: '📢', text: 'Announcements', page: 'announcements' }
        ]}
    ]
};

/**
 * Render sidebar into the given element
 */
export function renderSidebar(container) {
    const user = Auth.user();
    const role = user.role;
    const roleMenu = menus[role] || [];
    const currentHash = window.location.hash.replace('#', '');
    const currentPage = currentHash.split('/')[1] || 'dashboard';

    let html = `
        <!-- Sidebar Header -->
        <div class="sidebar-header">
            <a href="#${role}/dashboard" class="logo">
                <span class="logo-icon">📖</span>
                <span class="logo-text">CIT-LMS</span>
            </a>
        </div>

        <!-- Navigation -->
        <nav class="sidebar-nav">
    `;

    // Render menu sections
    for (const section of roleMenu) {
        html += `
            <div class="nav-section">
                <span class="nav-section-title">${section.section}</span>
        `;
        for (const item of section.items) {
            const isActive = currentPage === item.page ? 'active' : '';
            html += `
                <a href="#${role}/${item.page}" class="nav-item ${isActive}" data-page="${item.page}">
                    <span class="nav-icon">${item.icon}</span>
                    <span class="nav-text">${item.text}</span>
                </a>
            `;
        }
        html += `</div>`;
    }

    // Common section: Profile + Logout
    html += `
            <div class="nav-section">
                <span class="nav-section-title">Account</span>
                <a href="#${role}/profile" class="nav-item ${currentPage === 'profile' ? 'active' : ''}" data-page="profile">
                    <span class="nav-icon">👤</span>
                    <span class="nav-text">My Profile</span>
                </a>
                <a href="javascript:void(0)" class="nav-item logout" id="sidebar-logout">
                    <span class="nav-icon">🚪</span>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </nav>

        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <div class="user-card">
                <div class="user-avatar">${Auth.initials()}</div>
                <div class="user-info">
                    <span class="user-name">${escapeHtml(user.name)}</span>
                    <span class="user-role">${Auth.roleName(role)}</span>
                </div>
            </div>
        </div>
    `;

    container.innerHTML = html;

    // Logout handler
    document.getElementById('sidebar-logout').addEventListener('click', () => {
        if (confirm('Are you sure you want to logout?')) {
            Auth.logout();
        }
    });
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}
