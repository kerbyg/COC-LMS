/**
 * Student Enroll Page
 * Enroll in sections using enrollment code
 */
import { Api } from '../../api.js';

export async function render(container) {
    await renderPage(container);
}

async function renderPage(container) {
    const res = await Api.get('/EnrollmentAPI.php?action=my-subjects');
    const enrolled = res.success ? res.data : [];

    container.innerHTML = `
        <style>
            .enroll-layout { display:grid; grid-template-columns:420px 1fr; gap:24px; align-items:start; }

            .enroll-form-card { background:#fff; border:1px solid #e8e8e8; border-radius:14px; overflow:hidden; position:sticky; top:24px; }
            .efc-header { background:linear-gradient(135deg,#1B4D3E 0%,#2D6A4F 100%); padding:24px; color:#fff; }
            .efc-header h3 { font-size:18px; font-weight:700; margin-bottom:4px; }
            .efc-header p { font-size:13px; opacity:.8; }
            .efc-body { padding:24px; }
            .form-group { margin-bottom:16px; }
            .form-label { display:block; font-size:13px; font-weight:600; color:#404040; margin-bottom:6px; }
            .code-input { width:100%; padding:14px 16px; border:2px solid #e0e0e0; border-radius:10px; font-size:18px; font-family:monospace; font-weight:700; text-align:center; letter-spacing:3px; text-transform:uppercase; box-sizing:border-box; }
            .code-input:focus { outline:none; border-color:#1B4D3E; }
            .code-hint { font-size:12px; color:#737373; margin-top:6px; text-align:center; }
            .btn-primary { width:100%; background:linear-gradient(135deg,#00461B,#006428); color:#fff; border:none; padding:12px 20px; border-radius:10px; font-weight:600; font-size:15px; cursor:pointer; transition:all .2s; }
            .btn-primary:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,70,27,.3); }
            .btn-primary:disabled { opacity:.5; cursor:not-allowed; transform:none; }
            .btn-secondary { width:100%; background:#f5f5f5; color:#404040; border:1px solid #e0e0e0; padding:11px 20px; border-radius:10px; font-weight:600; font-size:14px; cursor:pointer; transition:all .2s; margin-bottom:10px; }
            .btn-secondary:hover { background:#ebebeb; }
            .alert { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:14px; }
            .alert-success { background:#E8F5E9; color:#1B4D3E; }
            .alert-error { background:#FEE2E2; color:#b91c1c; }

            /* Preview state */
            .preview-section-name { font-size:20px; font-weight:800; color:#1a1a1a; margin-bottom:4px; }
            .preview-code-badge { display:inline-block; background:#E8F5E9; color:#1B4D3E; font-family:monospace; font-size:13px; font-weight:700; padding:3px 10px; border-radius:6px; margin-bottom:12px; }
            .preview-capacity { font-size:13px; color:#737373; margin-bottom:16px; }
            .preview-divider { border:none; border-top:1px solid #f0f0f0; margin:16px 0; }
            .preview-subjects-label { font-size:11px; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.8px; margin-bottom:10px; }
            .preview-subject-list { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; max-height:280px; overflow-y:auto; }
            .preview-subject-row { display:flex; align-items:flex-start; gap:10px; padding:10px 12px; background:#f9fafb; border-radius:8px; }
            .psrow-check { color:#1B4D3E; font-size:16px; flex-shrink:0; margin-top:1px; }
            .psrow-info { flex:1; min-width:0; }
            .psrow-code { font-family:monospace; font-size:11px; font-weight:700; color:#1B4D3E; background:#E8F5E9; padding:2px 6px; border-radius:4px; display:inline-block; margin-bottom:3px; }
            .psrow-name { font-size:13px; font-weight:600; color:#1a1a1a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
            .psrow-meta { font-size:11px; color:#737373; margin-top:2px; }

            .enrolled-section h3 { font-size:18px; font-weight:700; color:#262626; margin-bottom:16px; }
            .enrolled-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:14px; }
            .enrolled-card { background:#fff; border:1px solid #e8e8e8; border-radius:12px; padding:18px; transition:border-color .2s; }
            .enrolled-card:hover { border-color:#1B4D3E; }
            .ec-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px; }
            .ec-code { background:#E8F5E9; color:#1B4D3E; padding:3px 8px; border-radius:5px; font-family:monospace; font-size:12px; font-weight:700; }
            .ec-section { background:#DBEAFE; color:#1E40AF; padding:3px 8px; border-radius:5px; font-size:11px; font-weight:600; }
            .ec-name { font-size:15px; font-weight:700; color:#262626; margin-bottom:4px; }
            .ec-instructor { font-size:13px; color:#737373; margin-bottom:8px; }
            .ec-details { display:flex; gap:16px; font-size:12px; color:#737373; }
            .ec-detail { display:flex; align-items:center; gap:4px; }
            .progress-mini { background:#f0f0f0; height:4px; border-radius:2px; margin-top:10px; overflow:hidden; }
            .progress-mini-fill { height:100%; background:linear-gradient(90deg,#00461B,#2D6A4F); border-radius:2px; }

            .empty-state-sm { text-align:center; padding:40px; color:#737373; }
            @media(max-width:768px) { .enroll-layout { grid-template-columns:1fr; } .enroll-form-card { position:static; } }
        </style>

        <div class="enroll-layout">
            <div class="enroll-form-card">
                <div class="efc-header">
                    <h3>Enroll in Section</h3>
                    <p>Enter the enrollment code provided by your instructor</p>
                </div>
                <div class="efc-body">
                    <div id="enroll-alert"></div>
                    <div class="form-group">
                        <label class="form-label">Enrollment Code</label>
                        <input type="text" class="code-input" id="enroll-code" placeholder="XXX-0000" maxlength="8">
                        <div class="code-hint">Format: ABC-1234</div>
                    </div>
                    <button class="btn-primary" id="btn-enroll">Check Code</button>
                </div>
            </div>

            <div class="enrolled-section">
                <h3>Enrolled Subjects (${enrolled.length})</h3>
                ${enrolled.length === 0 ? '<div class="empty-state-sm">No subjects enrolled yet. Enter an enrollment code to get started!</div>' :
                  `<div class="enrolled-grid">
                    ${enrolled.map(s => `
                        <div class="enrolled-card">
                            <div class="ec-top">
                                <span class="ec-code">${esc(s.subject_code)}</span>
                                <span class="ec-section">${esc(s.section_name||'')}</span>
                            </div>
                            <div class="ec-name">${esc(s.subject_name)}</div>
                            <div class="ec-instructor">${esc(s.instructor_name||'TBA')}</div>
                            <div class="ec-details">
                                ${s.schedule ? `<span class="ec-detail">${esc(s.schedule)}</span>` : ''}
                                ${s.room ? `<span class="ec-detail">${esc(s.room)}</span>` : ''}
                                <span class="ec-detail">${s.units||0} units</span>
                            </div>
                            <div class="progress-mini"><div class="progress-mini-fill" style="width:${s.progress||0}%"></div></div>
                        </div>
                    `).join('')}
                  </div>`}
            </div>
        </div>
    `;

    // Auto-format code input
    const codeInput = container.querySelector('#enroll-code');
    codeInput.addEventListener('input', (e) => {
        let v = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        if (v.length > 3) v = v.slice(0,3) + '-' + v.slice(3);
        e.target.value = v.slice(0,8);
    });

    // Step 1: Preview code → show subjects before confirming
    container.querySelector('#btn-enroll').addEventListener('click', async () => {
        const code = codeInput.value.trim();
        const alertEl = container.querySelector('#enroll-alert');
        if (!code || code.length < 8) {
            alertEl.innerHTML = '<div class="alert alert-error">Enter a valid enrollment code (e.g. ABC-1234)</div>';
            return;
        }
        const btn = container.querySelector('#btn-enroll');
        btn.disabled = true; btn.textContent = 'Checking...';

        const res = await Api.post('/EnrollmentAPI.php?action=preview', { enrollment_code: code });

        btn.disabled = false; btn.textContent = 'Check Code';

        if (!res.success) {
            alertEl.innerHTML = `<div class="alert alert-error">${res.message}</div>`;
            return;
        }

        alertEl.innerHTML = '';
        showPreview(container, res.data);
    });

    // Enter key
    codeInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') container.querySelector('#btn-enroll').click();
    });
}

// Step 2: Show preview card with subjects list, Cancel / Confirm buttons
function showPreview(container, data) {
    const { section_name, enrollment_code, max_students, current_enrollment, subjects } = data;
    const spots = max_students - current_enrollment;

    const efcBody = container.querySelector('.efc-body');
    efcBody.innerHTML = `
        <div class="preview-section-name">${esc(section_name)}</div>
        <div class="preview-code-badge">${esc(enrollment_code)}</div>
        <div class="preview-capacity">${current_enrollment} / ${max_students} enrolled &nbsp;·&nbsp; ${spots} spot${spots !== 1 ? 's' : ''} left</div>
        <hr class="preview-divider">
        <div class="preview-subjects-label">You will be enrolled in ${subjects.length} subject${subjects.length !== 1 ? 's' : ''}</div>
        <div class="preview-subject-list">
            ${subjects.map(s => `
                <div class="preview-subject-row">
                    <span class="psrow-check">✓</span>
                    <div class="psrow-info">
                        <span class="psrow-code">${esc(s.subject_code)}</span>
                        <div class="psrow-name">${esc(s.subject_name)}</div>
                        <div class="psrow-meta">
                            ${s.instructor_name ? esc(s.instructor_name) : 'TBA'}
                            ${s.schedule ? ' · ' + esc(s.schedule) : ''}
                            ${s.room ? ' · ' + esc(s.room) : ''}
                            &nbsp;·&nbsp; ${s.units||0} units
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
        <div id="enroll-alert"></div>
        <button class="btn-primary" id="btn-confirm-enroll">Confirm Enrollment</button>
        <button class="btn-secondary" id="btn-cancel-preview" style="margin-top:8px">← Back</button>
    `;

    // Confirm enrollment
    efcBody.querySelector('#btn-confirm-enroll').addEventListener('click', async () => {
        const alertEl = efcBody.querySelector('#enroll-alert');
        const confirmBtn = efcBody.querySelector('#btn-confirm-enroll');
        const cancelBtn = efcBody.querySelector('#btn-cancel-preview');
        confirmBtn.disabled = true; confirmBtn.textContent = 'Enrolling...';
        cancelBtn.disabled = true;

        const res = await Api.post('/EnrollmentAPI.php?action=enroll', { enrollment_code: enrollment_code });

        if (res.success) {
            alertEl.innerHTML = `<div class="alert alert-success">${res.message}</div>`;
            setTimeout(() => renderPage(container), 1200);
        } else {
            confirmBtn.disabled = false; confirmBtn.textContent = 'Confirm Enrollment';
            cancelBtn.disabled = false;
            alertEl.innerHTML = `<div class="alert alert-error">${res.message}</div>`;
        }
    });

    // Back to code input
    efcBody.querySelector('#btn-cancel-preview').addEventListener('click', () => {
        renderPage(container);
    });
}

function esc(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
