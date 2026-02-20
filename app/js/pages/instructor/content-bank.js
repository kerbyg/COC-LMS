/**
 * Content Bank — combined Lesson Bank + Question Bank (SPA module)
 */
import { Api } from '../../api.js';

let mySubjects        = [];
let myQuizzes         = [];
let section           = 'lessons';   // 'lessons' | 'questions'
let activeTab         = 'browse';    // 'browse' | 'mine'
let searchTimer       = null;
let closeMenusHandler = null;        // tracks document click listener for kebab menus

export async function render(container) {
    const [subjRes, quizRes] = await Promise.all([
        Api.get('/LessonsAPI.php?action=subjects'),
        Api.get('/QuestionBankAPI.php?action=my-quizzes')
    ]);
    mySubjects = subjRes.success ? subjRes.data : [];
    myQuizzes  = quizRes.success ? quizRes.data : [];

    container.innerHTML = `
        <style>
            /* ── Layout ─────────────────────────────────────────── */
            .cb-banner { background:linear-gradient(135deg,#1B4D3E,#2D6A4F); border-radius:16px; padding:24px 28px; color:#fff; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
            .cb-banner h2 { font-size:20px; font-weight:700; margin:0; }
            .cb-banner p  { font-size:13px; opacity:.85; margin:4px 0 0; }
            .cb-banner-actions { display:flex; gap:10px; flex-wrap:wrap; }
            .cb-banner-btn { padding:9px 18px; background:rgba(255,255,255,.18); color:#fff; border:1.5px solid rgba(255,255,255,.5); border-radius:9px; font-size:13px; font-weight:600; cursor:pointer; transition:all .2s; white-space:nowrap; }
            .cb-banner-btn:hover { background:rgba(255,255,255,.28); }
            .cb-banner-btn.primary { background:#fff; color:#1B4D3E; border-color:#fff; }
            .cb-banner-btn.primary:hover { background:#f0fdf4; }

            /* Section switcher */
            .cb-sections { display:flex; gap:4px; margin-bottom:18px; background:#f3f4f6; border-radius:10px; padding:4px; width:fit-content; }
            .cb-section-btn { padding:8px 22px; border-radius:7px; font-size:13px; font-weight:600; cursor:pointer; color:#555; border:none; background:none; transition:all .15s; display:flex; align-items:center; gap:6px; }
            .cb-section-btn.active { background:#fff; color:#1B4D3E; box-shadow:0 1px 4px rgba(0,0,0,.08); }

            /* Tabs */
            .cb-tabs { display:flex; gap:4px; margin-bottom:20px; width:fit-content; }
            .cb-tab { padding:7px 18px; border-radius:7px; font-size:12px; font-weight:600; cursor:pointer; color:#888; border:1px solid #e0e0e0; background:#fff; transition:all .15s; }
            .cb-tab.active { background:#1B4D3E; color:#fff; border-color:#1B4D3E; }

            /* Toolbar */
            .cb-toolbar { display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; align-items:center; }
            .cb-search { flex:1; min-width:220px; padding:9px 14px 9px 36px; border:1px solid #e0e0e0; border-radius:9px; font-size:13px; background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='%23999' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'/%3E%3C/svg%3E") no-repeat 11px center; }
            .cb-search:focus { outline:none; border-color:#1B4D3E; }
            .cb-select { padding:9px 14px; border:1px solid #e0e0e0; border-radius:9px; font-size:13px; cursor:pointer; }
            .cb-select:focus { outline:none; border-color:#1B4D3E; }

            /* Cards */
            .cb-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(320px,1fr)); gap:16px; }
            .cb-card { background:#fff; border:1px solid #e8e8e8; border-radius:14px; padding:20px; display:flex; flex-direction:column; gap:12px; transition:all .15s; }
            .cb-card:hover { border-color:#1B4D3E; box-shadow:0 3px 12px rgba(0,0,0,.06); }
            .cb-card-head { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; }
            .cb-card-title { font-size:14px; font-weight:700; color:#222; line-height:1.4; flex:1; }
            .cb-vis-badge { font-size:10px; font-weight:700; padding:3px 8px; border-radius:5px; white-space:nowrap; flex-shrink:0; }
            .cb-vis-badge.public  { background:#E8F5E9; color:#1B4D3E; }
            .cb-vis-badge.private { background:#FEF3C7; color:#B45309; }
            .cb-type-badge { font-size:10px; font-weight:700; padding:3px 8px; border-radius:5px; background:#EDE9FE; color:#5B21B6; white-space:nowrap; }
            .cb-card-meta { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
            .cb-subject-tag { background:#1B4D3E; color:#fff; font-size:10px; font-weight:700; padding:3px 8px; border-radius:5px; }
            .cb-meta-item { font-size:11px; color:#888; }
            .cb-options-preview { display:flex; flex-direction:column; gap:4px; }
            .cb-option-row { font-size:12px; color:#555; display:flex; align-items:center; gap:6px; }
            .cb-option-row.correct { color:#1B4D3E; font-weight:600; }
            .cb-option-row .dot { width:7px; height:7px; border-radius:50%; background:#ccc; flex-shrink:0; }
            .cb-option-row.correct .dot { background:#1B4D3E; }
            .cb-card-footer { display:flex; justify-content:space-between; align-items:center; padding-top:10px; border-top:1px solid #f0f0f0; gap:8px; flex-wrap:wrap; }
            .cb-author { font-size:12px; color:#999; }
            .cb-author strong { color:#555; }
            .cb-actions { display:flex; gap:7px; }
            .cb-btn { padding:7px 14px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; border:none; transition:all .15s; display:flex; align-items:center; gap:5px; }
            .cb-btn-copy   { background:#1B4D3E; color:#fff; }
            .cb-btn-copy:hover { background:#2D6A4F; }
            .cb-btn-view   { background:#f3f4f6; color:#333; border:1px solid #e0e0e0; }
            .cb-btn-view:hover { background:#eee; }
            .cb-btn-delete { background:#FEE2E2; color:#b91c1c; }
            .cb-btn-delete:hover { background:#fca5a5; }

            .cb-copy-badge { display:inline-flex; align-items:center; gap:3px; font-size:11px; color:#888; }
            .cb-empty { text-align:center; padding:60px 20px; background:#fafafa; border:1px dashed #ddd; border-radius:12px; }
            .cb-empty h3 { font-size:18px; font-weight:600; color:#333; margin:12px 0 6px; }
            .cb-empty p  { font-size:13px; color:#888; margin:0; }

            /* Kebab menu */
            .cb-kebab-wrap { position:relative; flex-shrink:0; }
            .cb-kebab { background:none; border:none; cursor:pointer; padding:3px 7px; border-radius:6px; font-size:18px; color:#bbb; line-height:1; transition:all .15s; }
            .cb-kebab:hover { background:#f3f4f6; color:#555; }
            .cb-kebab-menu { position:absolute; right:0; top:calc(100% + 4px); background:#fff; border:1px solid #e0e0e0; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,.1); z-index:200; min-width:160px; overflow:hidden; display:none; }
            .cb-kebab-menu.open { display:block; }
            .cb-kebab-item { display:flex; align-items:center; gap:8px; padding:10px 14px; font-size:13px; cursor:pointer; border:none; background:none; width:100%; text-align:left; color:#333; font-weight:500; transition:background .1s; }
            .cb-kebab-item:hover { background:#f5f5f5; }
            .cb-kebab-item.danger { color:#b91c1c; }
            .cb-kebab-item.danger:hover { background:#FEE2E2; }

            /* Modal */
            .cb-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; display:flex; align-items:center; justify-content:center; padding:20px; }
            .cb-modal { background:#fff; border-radius:16px; width:100%; max-width:640px; max-height:90vh; overflow-y:auto; animation:cbIn .2s ease-out; }
            @keyframes cbIn { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }
            .cb-modal-header { display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid #e8e8e8; position:sticky; top:0; background:#fff; z-index:1; }
            .cb-modal-header h3 { margin:0; font-size:16px; font-weight:700; }
            .cb-modal-close { background:none; border:none; font-size:24px; color:#999; cursor:pointer; }
            .cb-modal-close:hover { color:#333; }
            .cb-modal-body  { padding:24px; }
            .cb-modal-footer { padding:16px 24px; border-top:1px solid #e8e8e8; display:flex; justify-content:flex-end; gap:10px; background:#fff; }
            .cb-form-group { margin-bottom:16px; }
            .cb-form-group label { display:block; font-size:12px; font-weight:700; color:#444; margin-bottom:5px; text-transform:uppercase; letter-spacing:.4px; }
            .cb-input, .cb-textarea, .cb-form-select { width:100%; padding:9px 12px; border:1px solid #ddd; border-radius:8px; font-size:13px; font-family:inherit; box-sizing:border-box; }
            .cb-input:focus, .cb-textarea:focus, .cb-form-select:focus { outline:none; border-color:#1B4D3E; }
            .cb-textarea { resize:vertical; min-height:90px; }
            .cb-row { display:flex; gap:12px; }
            .cb-row .cb-form-group { flex:1; }
            .btn-primary-sm { padding:9px 20px; background:#1B4D3E; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; }
            .btn-primary-sm:hover { background:#2D6A4F; }
            .btn-outline-sm { padding:9px 18px; background:#fff; border:1px solid #ddd; border-radius:8px; font-size:13px; cursor:pointer; font-weight:500; }

            /* Options builder */
            .cb-options-list { display:flex; flex-direction:column; gap:8px; margin-bottom:10px; }
            .cb-option-item { display:flex; align-items:center; gap:8px; background:#f8f9fa; border-radius:8px; padding:8px 10px; }
            .cb-option-item input[type=text] { flex:1; border:none; background:transparent; font-size:13px; outline:none; }
            .cb-option-correct { width:16px; height:16px; cursor:pointer; accent-color:#1B4D3E; flex-shrink:0; }
            .cb-option-del { background:none; border:none; color:#999; cursor:pointer; font-size:16px; line-height:1; flex-shrink:0; }
            .cb-option-del:hover { color:#b91c1c; }
            .cb-add-option { width:100%; padding:7px; background:none; border:1.5px dashed #ccc; border-radius:8px; font-size:12px; color:#888; cursor:pointer; font-weight:600; }
            .cb-add-option:hover { border-color:#1B4D3E; color:#1B4D3E; }
            .cb-correct-hint { font-size:11px; color:#888; margin-bottom:8px; }

            /* Preview */
            .cb-preview-content { background:#f8f9fa; border-radius:8px; padding:16px; font-size:14px; line-height:1.7; color:#333; white-space:pre-wrap; max-height:300px; overflow-y:auto; }

            /* Attachment */
            .cb-att-type-row { display:flex; gap:16px; flex-wrap:wrap; margin-top:6px; }
            .cb-att-radio { display:flex; align-items:center; gap:6px; font-size:13px; color:#444; cursor:pointer; font-weight:500; }
            .cb-att-radio input { accent-color:#1B4D3E; cursor:pointer; }
            .cb-att-hint { font-size:11px; color:#999; margin:4px 0 0; }
            .cb-att-file-input { width:100%; padding:8px; border:1.5px dashed #ccc; border-radius:8px; font-size:13px; box-sizing:border-box; cursor:pointer; }
            .cb-att-file-input:hover { border-color:#1B4D3E; }
            .cb-attachment-badge { display:inline-flex; align-items:center; gap:5px; background:#f0fdf4; border:1px solid #bbf7d0; color:#1B4D3E; font-size:11px; font-weight:600; padding:4px 10px; border-radius:6px; text-decoration:none; max-width:100%; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
            .cb-attachment-badge:hover { background:#dcfce7; }
            .cb-attachment-link { display:inline-flex; align-items:center; gap:5px; background:#EFF6FF; border:1px solid #BFDBFE; color:#1E40AF; font-size:11px; font-weight:600; padding:4px 10px; border-radius:6px; text-decoration:none; max-width:100%; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
            .cb-attachment-link:hover { background:#DBEAFE; }
            .cb-att-preview-btn { display:inline-flex; align-items:center; gap:8px; padding:10px 16px; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none; cursor:pointer; border:none; }
            .cb-att-preview-btn.file { background:#E8F5E9; color:#1B4D3E; }
            .cb-att-preview-btn.link { background:#EFF6FF; color:#1E40AF; }

            /* Question accordion groups */
            .cb-group { background:#fff; border:1px solid #e8e8e8; border-radius:14px; margin-bottom:14px; overflow:hidden; transition:box-shadow .15s; }
            .cb-group:hover { box-shadow:0 2px 10px rgba(0,0,0,.06); }
            .cb-group-header { display:flex; align-items:center; gap:12px; padding:16px 20px; cursor:pointer; user-select:none; }
            .cb-group-header:hover { background:#f9f9f9; }
            .cb-group-icon { font-size:20px; flex-shrink:0; }
            .cb-group-info { flex:1; min-width:0; }
            .cb-group-name { font-size:15px; font-weight:700; color:#1B4D3E; }
            .cb-group-code { font-size:11px; color:#888; margin-top:1px; }
            .cb-group-count { background:#E8F5E9; color:#1B4D3E; font-size:11px; font-weight:700; padding:4px 10px; border-radius:20px; white-space:nowrap; }
            .cb-copy-all-btn { padding:6px 14px; background:#1B4D3E; color:#fff; border:none; border-radius:7px; font-size:11px; font-weight:700; cursor:pointer; white-space:nowrap; transition:background .15s; }
            .cb-copy-all-btn:hover { background:#2D6A4F; }
            .cb-group-chevron { font-size:14px; color:#aaa; transition:transform .2s; flex-shrink:0; }
            .cb-group.open .cb-group-chevron { transform:rotate(90deg); }
            .cb-group-body { display:none; border-top:1px solid #f0f0f0; }
            .cb-group.open .cb-group-body { display:block; }

            /* Question rows inside group */
            .cb-q-row { display:flex; align-items:flex-start; gap:12px; padding:14px 20px; border-bottom:1px solid #f5f5f5; transition:background .1s; cursor:pointer; }
            .cb-q-row:last-child { border-bottom:none; }
            .cb-q-row:hover { background:#fafafa; }
            .cb-q-row.expanded { background:#f0fdf4; }
            .cb-q-num { font-size:11px; font-weight:700; color:#bbb; min-width:26px; padding-top:2px; flex-shrink:0; }
            .cb-q-main { flex:1; min-width:0; }
            .cb-q-text { font-size:13px; font-weight:600; color:#222; line-height:1.4; margin-bottom:5px; }
            .cb-q-badges { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
            .cb-q-opts { padding:10px 0 4px; display:flex; flex-direction:column; gap:4px; }
            .cb-q-opt { font-size:12px; color:#555; display:flex; align-items:center; gap:6px; }
            .cb-q-opt.correct { color:#1B4D3E; font-weight:700; }
            .cb-q-opt .dot { width:7px; height:7px; border-radius:50%; background:#ddd; flex-shrink:0; }
            .cb-q-opt.correct .dot { background:#1B4D3E; }
            .cb-q-actions { display:flex; gap:6px; align-items:flex-start; flex-shrink:0; padding-top:1px; }
        </style>

        <div class="cb-banner">
            <div>
                <h2>🏦 Content Bank</h2>
                <p>Browse and reuse shared lessons and questions from the community.</p>
            </div>
            <div class="cb-banner-actions">
                <button class="cb-banner-btn" id="cb-publish-btn">+ Publish</button>
            </div>
        </div>

        <!-- Section switcher: Lessons | Questions -->
        <div class="cb-sections">
            <button class="cb-section-btn active" data-section="lessons">📖 Lessons</button>
            <button class="cb-section-btn" data-section="questions">❓ Questions</button>
        </div>

        <!-- Browse / Mine tabs -->
        <div class="cb-tabs">
            <button class="cb-tab active" data-tab="browse">Browse Bank</button>
            <button class="cb-tab" data-tab="mine">My Items</button>
        </div>

        <!-- Toolbar (hidden on mine tab) -->
        <div class="cb-toolbar" id="cb-toolbar">
            <input type="text" class="cb-search" id="cb-search" placeholder="Search...">
            <select class="cb-select" id="cb-subject">
                <option value="">All Subjects</option>
                ${mySubjects.map(s => `<option value="${s.subject_id}">${esc(s.subject_code)} — ${esc(s.subject_name)}</option>`).join('')}
            </select>
            <select class="cb-select" id="cb-type" style="display:none;">
                <option value="">All Types</option>
                <option value="multiple_choice">Multiple Choice</option>
                <option value="true_false">True / False</option>
                <option value="short_answer">Short Answer</option>
                <option value="essay">Essay</option>
            </select>
        </div>

        <div id="cb-content"></div>
    `;

    // Section switching
    container.querySelectorAll('.cb-section-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            container.querySelectorAll('.cb-section-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            section = btn.dataset.section;
            const typeFilter = document.getElementById('cb-type');
            if (typeFilter) typeFilter.style.display = section === 'questions' ? '' : 'none';
            document.getElementById('cb-search').placeholder = section === 'lessons' ? 'Search lessons...' : 'Search questions...';
            loadContent();
        });
    });

    // Tab switching
    container.querySelectorAll('.cb-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            container.querySelectorAll('.cb-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            activeTab = tab.dataset.tab;
            document.getElementById('cb-toolbar').style.display = activeTab === 'browse' ? 'flex' : 'none';
            loadContent();
        });
    });

    // Search & filter
    document.getElementById('cb-search').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadContent, 350);
    });
    document.getElementById('cb-subject').addEventListener('change', loadContent);
    document.getElementById('cb-type').addEventListener('change', loadContent);

    // Publish button
    document.getElementById('cb-publish-btn').addEventListener('click', () => {
        if (section === 'lessons') openLessonPublishModal(container);
        else openQuestionPublishModal(container);
    });

    loadContent();
}

// ─── Load content based on section + tab ─────────────────────────────────────

async function loadContent() {
    const wrap = document.getElementById('cb-content');
    if (!wrap) return;
    wrap.innerHTML = '<div style="text-align:center;padding:40px;color:#888;">Loading...</div>';

    if (section === 'lessons') {
        await loadLessons(wrap);
    } else {
        await loadQuestions(wrap);
    }
}

async function loadLessons(wrap) {
    if (activeTab === 'browse') {
        const search    = document.getElementById('cb-search')?.value  || '';
        const subjectId = document.getElementById('cb-subject')?.value || '';
        let url = '/LessonBankAPI.php?action=browse';
        if (search)    url += '&search='    + encodeURIComponent(search);
        if (subjectId) url += '&subject_id=' + subjectId;
        const res = await Api.get(url);
        renderLessonBrowse(wrap, res.success ? res.data : []);
    } else {
        const res = await Api.get('/LessonBankAPI.php?action=my-bank');
        renderLessonMine(wrap, res.success ? res.data : []);
    }
}

async function loadQuestions(wrap) {
    if (activeTab === 'browse') {
        const search    = document.getElementById('cb-search')?.value  || '';
        const subjectId = document.getElementById('cb-subject')?.value || '';
        const type      = document.getElementById('cb-type')?.value    || '';
        let url = '/QuestionBankAPI.php?action=browse';
        if (search)    url += '&search='    + encodeURIComponent(search);
        if (subjectId) url += '&subject_id=' + subjectId;
        if (type)      url += '&type='      + encodeURIComponent(type);
        const res = await Api.get(url);
        renderQuestionBrowse(wrap, res.success ? res.data : []);
    } else {
        const res = await Api.get('/QuestionBankAPI.php?action=my-bank');
        renderQuestionMine(wrap, res.success ? res.data : []);
    }
}

// ─── Lesson Rendering ─────────────────────────────────────────────────────────

function renderLessonBrowse(wrap, lessons) {
    if (!lessons.length) {
        wrap.innerHTML = emptyHtml('📭', 'No lessons found', 'Try a different search, or be the first to publish a lesson!');
        return;
    }
    wrap.innerHTML = `<div class="cb-grid">${lessons.map(l => lessonCard(l)).join('')}</div>`;
    bindLessonEvents(wrap, lessons);
}

function renderLessonMine(wrap, lessons) {
    if (!lessons.length) {
        wrap.innerHTML = emptyHtml('✏️', "You haven't published any lessons yet", 'Click "+ Publish" to share a lesson with other instructors.');
        return;
    }
    wrap.innerHTML = `<div class="cb-grid">${lessons.map(l => lessonCard(l)).join('')}</div>`;
    bindLessonEvents(wrap, lessons);
}

function lessonCard(l) {
    const date  = l.created_at ? new Date(l.created_at).toLocaleDateString('en-US', {month:'short',day:'numeric',year:'numeric'}) : '';
    const descr = l.lesson_description || 'No description provided.';
    const att   = l.attachment_type && l.attachment_type !== 'none' && l.attachment_path
        ? (l.attachment_type === 'file'
            ? `<a class="cb-attachment-badge" href="${esc(l.attachment_path)}" target="_blank" rel="noopener">📎 ${esc(l.attachment_name || 'Attachment')}</a>`
            : `<a class="cb-attachment-link" href="${esc(l.attachment_path)}" target="_blank" rel="noopener">🔗 ${esc(l.attachment_name || l.attachment_path)}</a>`)
        : '';
    return `<div class="cb-card">
        <div class="cb-card-head">
            <div class="cb-card-title">${esc(l.lesson_title)}</div>
            <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
                <span class="cb-vis-badge ${l.visibility}">${l.visibility}</span>
                <div class="cb-kebab-wrap">
                    <button class="cb-kebab" title="More actions">⋮</button>
                    <div class="cb-kebab-menu">
                        <button class="cb-kebab-item" data-lesson-view="${l.bank_id}">👁 Preview</button>
                        ${!l.is_own ? `<button class="cb-kebab-item" data-lesson-copy="${l.bank_id}" data-title="${esc(l.lesson_title)}">📋 Copy to My Class</button>` : ''}
                        ${l.is_own  ? `<button class="cb-kebab-item danger" data-lesson-delete="${l.bank_id}">🗑 Remove</button>` : ''}
                    </div>
                </div>
            </div>
        </div>
        <div style="font-size:13px;color:#666;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">${esc(descr)}</div>
        ${att ? `<div>${att}</div>` : ''}
        <div class="cb-card-meta">
            ${l.subject_code ? `<span class="cb-subject-tag">${esc(l.subject_code)}</span>` : ''}
            <span class="cb-copy-badge">📋 ${l.copy_count ?? 0} copies</span>
            <span class="cb-meta-item">${date}</span>
        </div>
        <div class="cb-card-footer">
            <div class="cb-author">${l.first_name ? `By <strong>${esc(l.first_name + ' ' + l.last_name)}</strong>` : ''}</div>
        </div>
    </div>`;
}

function bindLessonEvents(wrap, lessons) {
    // Remove old document listener before attaching a new one
    if (closeMenusHandler) document.removeEventListener('click', closeMenusHandler);
    closeMenusHandler = () => document.querySelectorAll('.cb-kebab-menu.open').forEach(m => m.classList.remove('open'));
    document.addEventListener('click', closeMenusHandler);

    // Kebab toggle
    wrap.querySelectorAll('.cb-kebab').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const menu = btn.nextElementSibling;
            const wasOpen = menu.classList.contains('open');
            document.querySelectorAll('.cb-kebab-menu.open').forEach(m => m.classList.remove('open'));
            if (!wasOpen) menu.classList.add('open');
        });
    });

    // Menu items
    wrap.querySelectorAll('[data-lesson-view]').forEach(btn => {
        btn.addEventListener('click', () => openLessonPreview(lessons.find(l => l.bank_id == btn.dataset.lessonView)));
    });
    wrap.querySelectorAll('[data-lesson-copy]').forEach(btn => {
        btn.addEventListener('click', () => openLessonCopyModal(btn.dataset.lessonCopy, btn.dataset.title));
    });
    wrap.querySelectorAll('[data-lesson-delete]').forEach(btn => {
        btn.addEventListener('click', () => confirmLessonDelete(btn.dataset.lessonDelete));
    });
}

// ─── Question Rendering (grouped by subject) ──────────────────────────────────

function renderQuestionBrowse(wrap, questions) {
    if (!questions.length) {
        wrap.innerHTML = emptyHtml('❓', 'No questions found', 'Try a different search, or publish the first question!');
        return;
    }
    renderQuestionGroups(wrap, questions);
}

function renderQuestionMine(wrap, questions) {
    if (!questions.length) {
        wrap.innerHTML = emptyHtml('📝', "You haven't published any questions yet", 'Click "+ Publish" to share questions with other instructors.');
        return;
    }
    renderQuestionGroups(wrap, questions);
}

function renderQuestionGroups(wrap, questions) {
    // Group by subject
    const groups = new Map();
    for (const q of questions) {
        const key   = q.subject_id || '__none__';
        const label = q.subject_name || 'General / No Subject';
        const code  = q.subject_code || '';
        if (!groups.has(key)) groups.set(key, { label, code, questions: [] });
        groups.get(key).questions.push(q);
    }

    const typeLabel = { multiple_choice:'Multiple Choice', true_false:'True/False', short_answer:'Short Answer', essay:'Essay' };

    let html = '';
    let first = true;
    for (const [, grp] of groups) {
        html += `
        <div class="cb-group ${first ? 'open' : ''}">
            <div class="cb-group-header">
                <span class="cb-group-icon">📚</span>
                <div class="cb-group-info">
                    <div class="cb-group-name">${esc(grp.label)}</div>
                    ${grp.code ? `<div class="cb-group-code">${esc(grp.code)}</div>` : ''}
                </div>
                <span class="cb-group-count">${grp.questions.length} question${grp.questions.length !== 1 ? 's' : ''}</span>
                <button class="cb-copy-all-btn" data-ids='${JSON.stringify(grp.questions.map(q => q.qbank_id))}' data-label="${esc(grp.label)}">Copy All</button>
                <span class="cb-group-chevron">▶</span>
            </div>
            <div class="cb-group-body">
                ${grp.questions.map((q, i) => `
                <div class="cb-q-row" data-qbank="${q.qbank_id}">
                    <span class="cb-q-num">${i + 1}</span>
                    <div class="cb-q-main">
                        <div class="cb-q-text">${esc(q.question_text)}</div>
                        <div class="cb-q-badges">
                            <span class="cb-type-badge">${typeLabel[q.question_type] || q.question_type}</span>
                            <span style="font-size:11px;color:#888;">${q.points} pt${q.points != 1 ? 's' : ''}</span>
                            <span class="cb-vis-badge ${q.visibility}">${q.visibility}</span>
                            ${q.first_name ? `<span style="font-size:11px;color:#aaa;">by ${esc(q.first_name + ' ' + q.last_name)}</span>` : ''}
                        </div>
                        ${(q.options || []).length ? `<div class="cb-q-opts" style="display:none;">
                            ${(q.options).map(o => `<div class="cb-q-opt ${o.is_correct ? 'correct' : ''}"><span class="dot"></span>${esc(o.option_text)}</div>`).join('')}
                        </div>` : ''}
                    </div>
                    <div class="cb-q-actions">
                        ${!q.is_own ? `<button class="cb-btn cb-btn-copy" data-q-copy="${q.qbank_id}" data-qtitle="${esc(q.question_text)}">Copy</button>` : ''}
                        ${q.is_own  ? `<button class="cb-btn cb-btn-delete" data-q-delete="${q.qbank_id}">Remove</button>` : ''}
                    </div>
                </div>`).join('')}
            </div>
        </div>`;
        first = false;
    }

    wrap.innerHTML = html;

    // Toggle group open/close (but not when clicking Copy All)
    wrap.querySelectorAll('.cb-group-header').forEach(hdr => {
        hdr.addEventListener('click', e => {
            if (e.target.closest('.cb-copy-all-btn')) return;
            hdr.closest('.cb-group').classList.toggle('open');
        });
    });

    // Copy All button
    wrap.querySelectorAll('.cb-copy-all-btn').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const ids   = JSON.parse(btn.dataset.ids);
            const label = btn.dataset.label;
            openGroupCopyModal(ids, label);
        });
    });

    // Toggle options inline (click row body, not buttons)
    wrap.querySelectorAll('.cb-q-row').forEach(row => {
        row.addEventListener('click', e => {
            if (e.target.closest('.cb-q-actions')) return; // don't interfere with buttons
            const opts = row.querySelector('.cb-q-opts');
            if (opts) {
                const open = opts.style.display !== 'none';
                opts.style.display = open ? 'none' : 'flex';
                row.classList.toggle('expanded', !open);
            }
        });
    });

    // Action buttons
    wrap.querySelectorAll('[data-q-copy]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            openQuestionCopyModal(btn.dataset.qCopy, btn.dataset.qtitle);
        });
    });
    wrap.querySelectorAll('[data-q-delete]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            confirmQuestionDelete(btn.dataset.qDelete);
        });
    });
}

// ─── Lesson Modals ────────────────────────────────────────────────────────────

function openLessonPublishModal(container) {
    const overlay = createOverlay(`
        <div class="cb-modal">
            <div class="cb-modal-header">
                <h3>📖 Publish Lesson to Bank</h3>
                <button class="cb-modal-close">&times;</button>
            </div>
            <div class="cb-modal-body">
                <div id="lp-alert"></div>
                <div class="cb-form-group">
                    <label>Lesson Title *</label>
                    <input type="text" class="cb-input" id="lp-title" placeholder="e.g. Introduction to Binary Numbers" maxlength="200">
                </div>
                <div class="cb-form-group">
                    <label>Short Description</label>
                    <textarea class="cb-textarea" id="lp-desc" rows="2" placeholder="Brief summary of what this lesson covers..."></textarea>
                </div>
                <div class="cb-form-group">
                    <label>Lesson Content</label>
                    <textarea class="cb-textarea" id="lp-content" rows="4" placeholder="Paste or type the lesson content here..."></textarea>
                </div>
                <div class="cb-form-group">
                    <label>Attachment (Optional)</label>
                    <div class="cb-att-type-row">
                        <label class="cb-att-radio"><input type="radio" name="lp-att-type" value="none" checked> None</label>
                        <label class="cb-att-radio"><input type="radio" name="lp-att-type" value="file"> Upload File</label>
                        <label class="cb-att-radio"><input type="radio" name="lp-att-type" value="link"> Add Link</label>
                    </div>
                    <div id="lp-att-file" style="display:none;margin-top:10px;">
                        <input type="file" class="cb-att-file-input" id="lp-file" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.gif">
                        <p class="cb-att-hint">Accepted: PDF, Word (.doc/.docx), Images (PNG/JPG/GIF) · Max 10MB</p>
                    </div>
                    <div id="lp-att-link" style="display:none;margin-top:10px;">
                        <input type="text" class="cb-input" id="lp-link-url" placeholder="https://drive.google.com/... or any URL">
                        <input type="text" class="cb-input" id="lp-link-name" placeholder="Link title (e.g. Week 1 Slides)" style="margin-top:6px;">
                    </div>
                </div>
                <div class="cb-row">
                    <div class="cb-form-group">
                        <label>Subject (optional)</label>
                        <select class="cb-form-select" id="lp-subject">
                            <option value="">-- General --</option>
                            ${mySubjects.map(s => `<option value="${s.subject_id}">${esc(s.subject_code)} — ${esc(s.subject_name)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="cb-form-group">
                        <label>Visibility</label>
                        <select class="cb-form-select" id="lp-vis">
                            <option value="public">Public</option>
                            <option value="private">Private (only me)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="cb-modal-footer">
                <button class="btn-outline-sm modal-cancel">Cancel</button>
                <button class="btn-primary-sm" id="lp-save">Publish Lesson</button>
            </div>
        </div>`);

    // Show/hide attachment sections
    overlay.querySelectorAll('input[name="lp-att-type"]').forEach(radio => {
        radio.addEventListener('change', () => {
            overlay.querySelector('#lp-att-file').style.display = radio.value === 'file' ? 'block' : 'none';
            overlay.querySelector('#lp-att-link').style.display = radio.value === 'link' ? 'block' : 'none';
        });
    });

    overlay.querySelector('#lp-save').addEventListener('click', async () => {
        const title = overlay.querySelector('#lp-title').value.trim();
        if (!title) { overlay.querySelector('#lp-alert').innerHTML = alertHtml('error', 'Lesson title is required.'); return; }

        const attType = overlay.querySelector('input[name="lp-att-type"]:checked')?.value || 'none';
        const btn = overlay.querySelector('#lp-save');
        btn.disabled = true; btn.textContent = 'Publishing...';

        let res;
        if (attType === 'file') {
            const fileInput = overlay.querySelector('#lp-file');
            if (!fileInput.files.length) {
                overlay.querySelector('#lp-alert').innerHTML = alertHtml('error', 'Please select a file to upload.');
                btn.disabled = false; btn.textContent = 'Publish Lesson'; return;
            }
            const fd = new FormData();
            fd.append('lesson_title',       title);
            fd.append('lesson_description', overlay.querySelector('#lp-desc').value.trim());
            fd.append('lesson_content',     overlay.querySelector('#lp-content').value.trim());
            fd.append('subject_id',         overlay.querySelector('#lp-subject').value || '');
            fd.append('visibility',         overlay.querySelector('#lp-vis').value);
            fd.append('attachment_type',    'file');
            fd.append('attachment',         fileInput.files[0]);
            res = await Api.postForm('/LessonBankAPI.php?action=publish', fd);
        } else {
            res = await Api.post('/LessonBankAPI.php?action=publish', {
                lesson_title:       title,
                lesson_description: overlay.querySelector('#lp-desc').value.trim(),
                lesson_content:     overlay.querySelector('#lp-content').value.trim(),
                subject_id:         overlay.querySelector('#lp-subject').value || null,
                visibility:         overlay.querySelector('#lp-vis').value,
                attachment_type:    attType,
                attachment_url:     attType === 'link' ? overlay.querySelector('#lp-link-url').value.trim() : '',
                attachment_name:    attType === 'link' ? overlay.querySelector('#lp-link-name').value.trim() : '',
            });
        }

        if (res.success) { overlay.remove(); loadContent(); showToast('Lesson published to bank!'); }
        else { btn.disabled = false; btn.textContent = 'Publish Lesson'; overlay.querySelector('#lp-alert').innerHTML = alertHtml('error', res.message || 'Failed to publish.'); }
    });
}

function openLessonCopyModal(bankId, title) {
    const overlay = createOverlay(`
        <div class="cb-modal" style="max-width:460px;">
            <div class="cb-modal-header">
                <h3>Copy Lesson to My Class</h3>
                <button class="cb-modal-close">&times;</button>
            </div>
            <div class="cb-modal-body">
                <div id="lc-alert"></div>
                <p style="font-size:13px;color:#555;margin:0 0 16px;">Copying: <strong>${esc(title)}</strong><br><span style="color:#888;font-size:12px;">Creates an independent copy in your subject.</span></p>
                <div class="cb-form-group">
                    <label>Copy into subject *</label>
                    <select class="cb-form-select" id="lc-subject">
                        <option value="">-- Select a subject --</option>
                        ${mySubjects.map(s => `<option value="${s.subject_id}">${esc(s.subject_code)} — ${esc(s.subject_name)}</option>`).join('')}
                    </select>
                </div>
            </div>
            <div class="cb-modal-footer">
                <button class="btn-outline-sm modal-cancel">Cancel</button>
                <button class="btn-primary-sm" id="lc-confirm">Copy Lesson</button>
            </div>
        </div>`);

    overlay.querySelector('#lc-confirm').addEventListener('click', async () => {
        const subjectId = overlay.querySelector('#lc-subject').value;
        if (!subjectId) { overlay.querySelector('#lc-alert').innerHTML = alertHtml('error', 'Please select a subject.'); return; }
        const btn = overlay.querySelector('#lc-confirm');
        btn.disabled = true; btn.textContent = 'Copying...';
        const res = await Api.post('/LessonBankAPI.php?action=copy', { bank_id: parseInt(bankId), subject_id: parseInt(subjectId) });
        if (res.success) { overlay.remove(); loadContent(); showToast('Lesson copied! Find it in your Lessons (saved as draft).'); }
        else { btn.disabled = false; btn.textContent = 'Copy Lesson'; overlay.querySelector('#lc-alert').innerHTML = alertHtml('error', res.message || 'Failed to copy.'); }
    });
}

function openLessonPreview(lesson) {
    if (!lesson) return;
    const hasAtt = lesson.attachment_type && lesson.attachment_type !== 'none' && lesson.attachment_path;
    const attHtml = hasAtt
        ? `<div class="cb-form-group" style="margin-top:14px;">
               <label>Attachment</label>
               <a class="cb-att-preview-btn ${lesson.attachment_type}"
                  href="${esc(lesson.attachment_path)}" target="_blank" rel="noopener">
                   ${lesson.attachment_type === 'file' ? '📎 Download / View File' : '🔗 Open Link'}
                   <span style="font-size:11px;opacity:.75;font-weight:400;">${esc(lesson.attachment_name || lesson.attachment_path)}</span>
               </a>
           </div>`
        : '';
    createOverlay(`
        <div class="cb-modal">
            <div class="cb-modal-header">
                <h3>${esc(lesson.lesson_title)}</h3>
                <button class="cb-modal-close">&times;</button>
            </div>
            <div class="cb-modal-body">
                <div class="cb-card-meta" style="margin-bottom:12px;">
                    ${lesson.subject_code ? `<span class="cb-subject-tag">${esc(lesson.subject_code)}</span>` : ''}
                    ${lesson.first_name ? `<span class="cb-meta-item">By <strong>${esc(lesson.first_name + ' ' + lesson.last_name)}</strong></span>` : ''}
                    <span class="cb-copy-badge">📋 ${lesson.copy_count ?? 0} copies</span>
                </div>
                ${lesson.lesson_description ? `<p style="font-size:13px;color:#555;margin:0 0 14px;">${esc(lesson.lesson_description)}</p>` : ''}
                <div class="cb-form-group">
                    <label>Lesson Content</label>
                    <div class="cb-preview-content">${lesson.lesson_content ? esc(lesson.lesson_content) : '<em style="color:#999">No content provided.</em>'}</div>
                </div>
                ${attHtml}
            </div>
            <div class="cb-modal-footer">
                <button class="btn-outline-sm modal-cancel">Close</button>
                ${!lesson.is_own ? `<button class="btn-primary-sm" id="prev-copy-btn">Copy to My Class</button>` : ''}
            </div>
        </div>`);
    // prev-copy-btn handled via event delegation on overlay (already done by createOverlay close)
    const copyBtn = document.getElementById('prev-copy-btn');
    if (copyBtn) {
        copyBtn.addEventListener('click', () => {
            document.querySelector('.cb-overlay')?.remove();
            openLessonCopyModal(lesson.bank_id, lesson.lesson_title);
        });
    }
}

async function confirmLessonDelete(bankId) {
    if (!confirm('Remove this lesson from the bank?')) return;
    const res = await Api.post('/LessonBankAPI.php?action=delete', { bank_id: parseInt(bankId) });
    if (res.success) { loadContent(); showToast('Lesson removed from bank.'); }
    else alert(res.message || 'Failed to remove.');
}

// ─── Question Modals ──────────────────────────────────────────────────────────

function openQuestionPublishModal(container) {
    const overlay = createOverlay(`
        <div class="cb-modal">
            <div class="cb-modal-header">
                <h3>❓ Publish Question to Bank</h3>
                <button class="cb-modal-close">&times;</button>
            </div>
            <div class="cb-modal-body">
                <div id="qp-alert"></div>
                <div class="cb-form-group">
                    <label>Question Text *</label>
                    <textarea class="cb-textarea" id="qp-text" rows="3" placeholder="Enter the question..."></textarea>
                </div>
                <div class="cb-row">
                    <div class="cb-form-group">
                        <label>Question Type</label>
                        <select class="cb-form-select" id="qp-type">
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="true_false">True / False</option>
                            <option value="short_answer">Short Answer</option>
                            <option value="essay">Essay</option>
                        </select>
                    </div>
                    <div class="cb-form-group">
                        <label>Points</label>
                        <input type="number" class="cb-input" id="qp-points" value="1" min="1" max="100">
                    </div>
                </div>
                <div class="cb-row">
                    <div class="cb-form-group">
                        <label>Subject (optional)</label>
                        <select class="cb-form-select" id="qp-subject">
                            <option value="">-- General --</option>
                            ${mySubjects.map(s => `<option value="${s.subject_id}">${esc(s.subject_code)} — ${esc(s.subject_name)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="cb-form-group">
                        <label>Visibility</label>
                        <select class="cb-form-select" id="qp-vis">
                            <option value="public">Public</option>
                            <option value="private">Private (only me)</option>
                        </select>
                    </div>
                </div>
                <div id="qp-options-section">
                    <div class="cb-form-group">
                        <label>Answer Choices</label>
                        <p class="cb-correct-hint">Check the circle next to the correct answer(s).</p>
                        <div class="cb-options-list" id="qp-options-list"></div>
                        <button type="button" class="cb-add-option" id="qp-add-option">+ Add Option</button>
                    </div>
                </div>
            </div>
            <div class="cb-modal-footer">
                <button class="btn-outline-sm modal-cancel">Cancel</button>
                <button class="btn-primary-sm" id="qp-save">Publish Question</button>
            </div>
        </div>`);

    // Seed options
    const optList = overlay.querySelector('#qp-options-list');
    addOptionRow(optList);
    addOptionRow(optList);

    overlay.querySelector('#qp-add-option').addEventListener('click', () => addOptionRow(optList));

    // Type change: show/hide options
    overlay.querySelector('#qp-type').addEventListener('change', e => {
        const sect = overlay.querySelector('#qp-options-section');
        if (e.target.value === 'short_answer' || e.target.value === 'essay') {
            sect.style.display = 'none';
        } else {
            sect.style.display = '';
            if (e.target.value === 'true_false') {
                optList.innerHTML = '';
                addOptionRow(optList, 'True',  true);
                addOptionRow(optList, 'False', false);
                overlay.querySelector('#qp-add-option').style.display = 'none';
            } else {
                overlay.querySelector('#qp-add-option').style.display = '';
            }
        }
    });

    overlay.querySelector('#qp-save').addEventListener('click', async () => {
        const text = overlay.querySelector('#qp-text').value.trim();
        if (!text) { overlay.querySelector('#qp-alert').innerHTML = alertHtml('error', 'Question text is required.'); return; }

        const type = overlay.querySelector('#qp-type').value;
        const options = [];
        if (type !== 'short_answer' && type !== 'essay') {
            optList.querySelectorAll('.cb-option-item').forEach(row => {
                const txt = row.querySelector('input[type=text]').value.trim();
                const correct = row.querySelector('input[type=radio],input[type=checkbox]')?.checked || false;
                if (txt) options.push({ option_text: txt, is_correct: correct ? 1 : 0 });
            });
        }

        const btn = overlay.querySelector('#qp-save');
        btn.disabled = true; btn.textContent = 'Publishing...';
        const res = await Api.post('/QuestionBankAPI.php?action=publish', {
            question_text: text,
            question_type: type,
            points:        parseInt(overlay.querySelector('#qp-points').value) || 1,
            subject_id:    overlay.querySelector('#qp-subject').value || null,
            visibility:    overlay.querySelector('#qp-vis').value,
            options
        });
        if (res.success) { overlay.remove(); loadContent(); showToast('Question published to bank!'); }
        else { btn.disabled = false; btn.textContent = 'Publish Question'; overlay.querySelector('#qp-alert').innerHTML = alertHtml('error', res.message || 'Failed to publish.'); }
    });
}

function addOptionRow(container, text = '', correct = false) {
    const item = document.createElement('div');
    item.className = 'cb-option-item';
    item.innerHTML = `
        <input type="radio" name="qp-correct" class="cb-option-correct" ${correct ? 'checked' : ''}>
        <input type="text" placeholder="Option text..." value="${esc(text)}">
        <button type="button" class="cb-option-del" title="Remove">&times;</button>
    `;
    item.querySelector('.cb-option-del').addEventListener('click', () => item.remove());
    container.appendChild(item);
}

function openQuestionCopyModal(qbankId, questionText) {
    if (!myQuizzes.length) {
        alert('You have no quizzes yet. Create a quiz first, then copy questions into it.');
        return;
    }
    const overlay = createOverlay(`
        <div class="cb-modal" style="max-width:460px;">
            <div class="cb-modal-header">
                <h3>Copy Question to Quiz</h3>
                <button class="cb-modal-close">&times;</button>
            </div>
            <div class="cb-modal-body">
                <div id="qc-alert"></div>
                <p style="font-size:13px;color:#555;margin:0 0 16px;"><strong>${esc(questionText.length > 80 ? questionText.slice(0,80) + '…' : questionText)}</strong><br><span style="color:#888;font-size:12px;">Creates an independent copy in your quiz.</span></p>
                <div class="cb-form-group">
                    <label>Copy into quiz *</label>
                    <select class="cb-form-select" id="qc-quiz">
                        <option value="">-- Select a quiz --</option>
                        ${myQuizzes.map(q => `<option value="${q.quiz_id}">${esc(q.quiz_title)}${q.subject_code ? ' (' + esc(q.subject_code) + ')' : ''}</option>`).join('')}
                    </select>
                </div>
            </div>
            <div class="cb-modal-footer">
                <button class="btn-outline-sm modal-cancel">Cancel</button>
                <button class="btn-primary-sm" id="qc-confirm">Copy Question</button>
            </div>
        </div>`);

    overlay.querySelector('#qc-confirm').addEventListener('click', async () => {
        const quizId = overlay.querySelector('#qc-quiz').value;
        if (!quizId) { overlay.querySelector('#qc-alert').innerHTML = alertHtml('error', 'Please select a quiz.'); return; }
        const btn = overlay.querySelector('#qc-confirm');
        btn.disabled = true; btn.textContent = 'Copying...';
        const res = await Api.post('/QuestionBankAPI.php?action=copy', { qbank_id: parseInt(qbankId), quiz_id: parseInt(quizId) });
        if (res.success) { overlay.remove(); loadContent(); showToast('Question copied to your quiz!'); }
        else { btn.disabled = false; btn.textContent = 'Copy Question'; overlay.querySelector('#qc-alert').innerHTML = alertHtml('error', res.message || 'Failed to copy.'); }
    });
}

async function confirmQuestionDelete(qbankId) {
    if (!confirm('Remove this question from the bank?')) return;
    const res = await Api.post('/QuestionBankAPI.php?action=delete', { qbank_id: parseInt(qbankId) });
    if (res.success) { loadContent(); showToast('Question removed from bank.'); }
    else alert(res.message || 'Failed to remove.');
}

function openGroupCopyModal(qbankIds, groupLabel) {
    if (!myQuizzes.length) {
        alert('You have no quizzes yet. Create a quiz first, then copy questions into it.');
        return;
    }
    const overlay = createOverlay(`
        <div class="cb-modal" style="max-width:460px;">
            <div class="cb-modal-header">
                <h3>Copy All Questions to Quiz</h3>
                <button class="cb-modal-close">&times;</button>
            </div>
            <div class="cb-modal-body">
                <div id="gca-alert"></div>
                <p style="font-size:13px;color:#555;margin:0 0 16px;">
                    Copying <strong>${qbankIds.length} question${qbankIds.length !== 1 ? 's' : ''}</strong> from <strong>${esc(groupLabel)}</strong> into your quiz.<br>
                    <span style="color:#888;font-size:12px;">Each becomes an independent copy you can edit freely.</span>
                </p>
                <div class="cb-form-group">
                    <label>Copy into quiz *</label>
                    <select class="cb-form-select" id="gca-quiz">
                        <option value="">-- Select a quiz --</option>
                        ${myQuizzes.map(q => `<option value="${q.quiz_id}">${esc(q.quiz_title)}${q.subject_code ? ' (' + esc(q.subject_code) + ')' : ''}</option>`).join('')}
                    </select>
                </div>
                <div id="gca-progress" style="display:none;margin-top:12px;">
                    <div style="font-size:12px;color:#555;margin-bottom:6px;" id="gca-progress-text">Copying...</div>
                    <div style="background:#e8e8e8;border-radius:4px;height:6px;overflow:hidden;">
                        <div id="gca-progress-bar" style="height:100%;background:#1B4D3E;width:0%;transition:width .2s;"></div>
                    </div>
                </div>
            </div>
            <div class="cb-modal-footer">
                <button class="btn-outline-sm modal-cancel">Cancel</button>
                <button class="btn-primary-sm" id="gca-confirm">Copy All</button>
            </div>
        </div>`);

    overlay.querySelector('#gca-confirm').addEventListener('click', async () => {
        const quizId = overlay.querySelector('#gca-quiz').value;
        if (!quizId) {
            overlay.querySelector('#gca-alert').innerHTML = alertHtml('error', 'Please select a quiz.');
            return;
        }

        const confirmBtn = overlay.querySelector('#gca-confirm');
        const cancelBtn  = overlay.querySelector('.modal-cancel');
        confirmBtn.disabled = true;
        cancelBtn.disabled  = true;
        overlay.querySelector('#gca-progress').style.display = 'block';

        let copied = 0;
        let failed = 0;
        const bar  = overlay.querySelector('#gca-progress-bar');
        const txt  = overlay.querySelector('#gca-progress-text');

        for (let i = 0; i < qbankIds.length; i++) {
            txt.textContent = `Copying ${i + 1} of ${qbankIds.length}...`;
            bar.style.width = `${Math.round(((i + 1) / qbankIds.length) * 100)}%`;

            const res = await Api.post('/QuestionBankAPI.php?action=copy', {
                qbank_id: parseInt(qbankIds[i]),
                quiz_id:  parseInt(quizId)
            });
            if (res.success) copied++; else failed++;
        }

        overlay.remove();
        loadContent();
        if (failed === 0) {
            showToast(`✅ ${copied} question${copied !== 1 ? 's' : ''} copied to your quiz!`);
        } else {
            showToast(`Copied ${copied}, failed ${failed}. Some questions may already exist.`);
        }
    });
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function createOverlay(html) {
    const overlay = document.createElement('div');
    overlay.className = 'cb-overlay';
    overlay.innerHTML = html;
    document.body.appendChild(overlay);
    const close = () => overlay.remove();
    overlay.querySelector('.cb-modal-close')?.addEventListener('click', close);
    overlay.querySelector('.modal-cancel')?.addEventListener('click', close);
    overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
    return overlay;
}

function emptyHtml(icon, title, msg) {
    return `<div class="cb-empty"><div style="font-size:44px;">${icon}</div><h3>${title}</h3><p>${msg}</p></div>`;
}

function showToast(msg) {
    const t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#1B4D3E;color:#fff;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,.2);animation:cbIn .2s ease-out';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

function alertHtml(type, msg) {
    const s = type === 'error'
        ? 'background:#FEE2E2;color:#991b1b;border-left:4px solid #ef4444;'
        : 'background:#d1fae5;color:#065f46;border-left:4px solid #10b981;';
    return `<div style="${s}padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;font-weight:600;">${esc(msg)}</div>`;
}

function esc(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
}
