/**
 * Instructor Quiz Questions Page
 * Add/Edit/Delete questions for a specific quiz
 */
import { Api } from '../../api.js';

export async function render(container, params = {}) {
    const quizId = params.quiz_id;
    if (!quizId) {
        container.innerHTML = '<div style="text-align:center;padding:60px;color:#737373">No quiz selected. <a href="#instructor/quizzes">Go to Quizzes</a></div>';
        return;
    }

    container.innerHTML = '<div style="text-align:center;padding:60px;color:#737373">Loading questions...</div>';
    await loadPage(container, quizId);
}

async function loadPage(container, quizId) {
    const res = await Api.get('/QuizzesAPI.php?action=list-questions&quiz_id=' + quizId);
    if (!res.success) {
        container.innerHTML = `<div style="text-align:center;padding:60px;color:#b91c1c">${res.message || 'Failed to load quiz'}</div>`;
        return;
    }

    const { quiz, questions } = res.data;

    container.innerHTML = `
        <style>
            .qq-back { display:inline-flex; align-items:center; gap:6px; color:#1B4D3E; font-size:14px; font-weight:500; text-decoration:none; margin-bottom:16px; cursor:pointer; }
            .qq-back:hover { text-decoration:underline; }

            .qq-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
            .qq-title { font-size:22px; font-weight:700; color:#262626; }
            .qq-meta { font-size:13px; color:#737373; margin-top:4px; }
            .qq-meta .code { background:#E8F5E9; color:#1B4D3E; padding:2px 8px; border-radius:4px; font-family:monospace; font-weight:600; font-size:12px; }
            .qq-count { background:#E8F5E9; color:#1B4D3E; padding:4px 12px; border-radius:20px; font-size:13px; font-weight:600; margin-left:8px; }
            .badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; text-transform:capitalize; }
            .badge-published { background:#E8F5E9; color:#1B4D3E; }
            .badge-draft { background:#FEF3C7; color:#B45309; }

            .btn-primary { background:linear-gradient(135deg,#00461B,#006428); color:#fff; border:none; padding:10px 20px; border-radius:10px; font-weight:600; font-size:14px; cursor:pointer; }
            .btn-primary:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,70,27,.3); }
            .btn-secondary { background:#f5f5f5; color:#404040; border:1px solid #e0e0e0; padding:9px 18px; border-radius:8px; font-weight:500; cursor:pointer; font-size:14px; }
            .btn-bank { background:#fff; color:#1B4D3E; border:1px solid #1B4D3E; padding:9px 18px; border-radius:10px; font-weight:600; font-size:14px; cursor:pointer; }
            .btn-bank:hover { background:#E8F5E9; }

            .bank-modal { max-width:780px; }
            .bank-filters { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
            .bank-filters input, .bank-filters select { padding:8px 12px; border:1px solid #e0e0e0; border-radius:8px; font-size:13px; }
            .bank-filters input { flex:1; min-width:160px; }
            .bank-list { display:flex; flex-direction:column; gap:10px; max-height:420px; overflow-y:auto; }
            .bq-card { background:#fafafa; border:1px solid #e8e8e8; border-radius:10px; padding:14px 16px; display:flex; align-items:flex-start; gap:12px; }
            .bq-card:hover { border-color:#1B4D3E; background:#f0fdf4; }
            .bq-body { flex:1; min-width:0; }
            .bq-text { font-size:14px; font-weight:600; color:#262626; margin-bottom:6px; }
            .bq-meta { font-size:11px; color:#737373; display:flex; gap:8px; flex-wrap:wrap; }
            .bq-tag { background:#f3f4f6; color:#404040; padding:2px 7px; border-radius:5px; font-size:11px; font-weight:600; }
            .bq-tag.mc { background:#EDE9FE; color:#5B21B6; }
            .bq-tag.tf { background:#FEF3C7; color:#B45309; }
            .bq-tag.sa { background:#DBEAFE; color:#1D4ED8; }
            .bq-tag.es { background:#FCE7F3; color:#9D174D; }
            .bq-opts { margin-top:8px; display:flex; flex-direction:column; gap:3px; }
            .bq-opt { font-size:12px; color:#404040; display:flex; align-items:center; gap:6px; }
            .bq-opt.correct { color:#1B4D3E; font-weight:600; }
            .btn-copy-q { white-space:nowrap; padding:7px 14px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; background:#1B4D3E; color:#fff; border:none; flex-shrink:0; }
            .btn-copy-q:hover { background:#006428; }
            .btn-copy-q:disabled { background:#ccc; cursor:not-allowed; }
            .bank-empty { text-align:center; padding:40px; color:#737373; font-size:14px; }
            .bank-loading { text-align:center; padding:40px; color:#737373; }

            .qq-list { display:flex; flex-direction:column; gap:12px; }
            .q-card { background:#fff; border:1px solid #e8e8e8; border-radius:12px; overflow:hidden; }
            .q-card:hover { border-color:#ccc; }
            .q-card-header { display:flex; justify-content:space-between; align-items:center; padding:16px 20px; }
            .q-number { width:32px; height:32px; border-radius:50%; background:#E8F5E9; color:#1B4D3E; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; flex-shrink:0; }
            .q-text { flex:1; margin:0 14px; font-size:14px; font-weight:600; color:#262626; line-height:1.4; }
            .q-info { display:flex; align-items:center; gap:8px; flex-shrink:0; }
            .q-type-badge { padding:3px 8px; border-radius:6px; font-size:11px; font-weight:600; background:#f3f4f6; color:#404040; text-transform:capitalize; white-space:nowrap; }
            .q-points { font-size:12px; color:#737373; font-weight:600; }

            .q-options { padding:0 20px 16px; padding-left:66px; }
            .q-opt { display:flex; align-items:center; gap:8px; padding:6px 0; font-size:13px; color:#404040; }
            .q-opt-marker { width:18px; height:18px; border-radius:50%; border:2px solid #e0e0e0; display:flex; align-items:center; justify-content:center; font-size:10px; flex-shrink:0; }
            .q-opt-marker.correct { background:#E8F5E9; border-color:#1B4D3E; color:#1B4D3E; }

            .q-actions { display:flex; gap:8px; }
            .btn-icon { width:32px; height:32px; border-radius:8px; border:1px solid #e0e0e0; background:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:14px; }
            .btn-icon:hover { background:#f5f5f5; }
            .btn-icon.danger { color:#b91c1c; border-color:#fecaca; }
            .btn-icon.danger:hover { background:#FEE2E2; }

            .empty-questions { text-align:center; padding:60px 20px; background:#fff; border:2px dashed #e0e0e0; border-radius:14px; }
            .empty-questions h3 { font-size:18px; color:#262626; margin-bottom:8px; }
            .empty-questions p { font-size:14px; color:#737373; margin-bottom:20px; }

            .modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:1000; }
            .modal { background:#fff; border-radius:16px; width:92%; max-width:640px; max-height:90vh; overflow-y:auto; }
            .modal-header { padding:20px 24px; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; }
            .modal-header h3 { font-size:18px; font-weight:700; }
            .modal-close { background:none; border:none; font-size:24px; cursor:pointer; color:#737373; }
            .modal-body { padding:24px; }
            .modal-footer { padding:16px 24px; border-top:1px solid #f0f0f0; display:flex; justify-content:flex-end; gap:12px; }

            .form-group { margin-bottom:16px; }
            .form-label { display:block; font-size:13px; font-weight:600; color:#404040; margin-bottom:6px; }
            .form-input, .form-select, .form-textarea { width:100%; padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; box-sizing:border-box; font-family:inherit; }
            .form-input:focus, .form-select:focus, .form-textarea:focus { outline:none; border-color:#00461B; }
            .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }

            .options-section { margin-top:8px; }
            .options-section h4 { font-size:13px; font-weight:600; color:#404040; margin-bottom:10px; }
            .opt-row { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
            .opt-row input[type="text"] { flex:1; padding:8px 12px; border:1px solid #e0e0e0; border-radius:8px; font-size:13px; }
            .opt-row input[type="text"]:focus { outline:none; border-color:#00461B; }
            .opt-correct { display:flex; align-items:center; gap:4px; font-size:12px; color:#737373; white-space:nowrap; cursor:pointer; }
            .opt-correct input { cursor:pointer; }
            .btn-remove-opt { width:28px; height:28px; border-radius:6px; border:1px solid #fecaca; background:#fff; color:#b91c1c; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center; }
            .btn-remove-opt:hover { background:#FEE2E2; }
            .btn-add-opt { background:none; border:1px dashed #ccc; padding:6px 14px; border-radius:8px; font-size:12px; color:#737373; cursor:pointer; }
            .btn-add-opt:hover { border-color:#1B4D3E; color:#1B4D3E; }

            .alert { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:14px; }
            .alert-error { background:#FEE2E2; color:#b91c1c; }

            @media(max-width:768px) { .q-card-header { flex-wrap:wrap; } .q-text { margin:8px 0; } .form-row { grid-template-columns:1fr; } }
        </style>

        <a class="qq-back" id="btn-back">&larr; Back to Quizzes</a>

        <div class="qq-header">
            <div>
                <div class="qq-title">${esc(quiz.quiz_title)} <span class="qq-count">${questions.length} questions</span></div>
                <div class="qq-meta"><span class="code">${esc(quiz.subject_id)}</span> <span class="badge badge-${quiz.status}">${quiz.status}</span> &middot; ${quiz.time_limit} min &middot; ${quiz.passing_rate}% to pass</div>
            </div>
            <div style="display:flex;gap:10px;">
                <button class="btn-bank" id="btn-copy-bank">📋 Copy from Bank</button>
                <button class="btn-primary" id="btn-add-q">+ Add Question</button>
            </div>
        </div>

        <div class="qq-list" id="qq-list">
            ${questions.length === 0 ? `
                <div class="empty-questions">
                    <h3>No Questions Yet</h3>
                    <p>Add questions to this quiz so students can take it.</p>
                    <button class="btn-primary" id="btn-add-q-empty">+ Add First Question</button>
                </div>
            ` : questions.map((q, i) => renderQuestionCard(q, i)).join('')}
        </div>
    `;

    // Events
    container.querySelector('#btn-back').addEventListener('click', () => {
        window.location.hash = '#instructor/quizzes';
    });

    container.querySelector('#btn-add-q').addEventListener('click', () => openQuestionModal(container, quizId));
    container.querySelector('#btn-copy-bank').addEventListener('click', () => openBankModal(container, quizId));

    const emptyBtn = container.querySelector('#btn-add-q-empty');
    if (emptyBtn) emptyBtn.addEventListener('click', () => openQuestionModal(container, quizId));

    container.querySelectorAll('[data-edit-q]').forEach(btn => {
        btn.addEventListener('click', () => {
            const q = questions.find(q => q.questions_id == btn.dataset.editQ);
            if (q) openQuestionModal(container, quizId, q);
        });
    });

    container.querySelectorAll('[data-delete-q]').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('Delete this question? This cannot be undone.')) return;
            const res = await Api.post('/QuizzesAPI.php?action=delete-question', { questions_id: parseInt(btn.dataset.deleteQ) });
            if (res.success) loadPage(container, quizId);
            else alert(res.message);
        });
    });
}

function renderQuestionCard(q, index) {
    const typeLabels = { multiple_choice: 'Multiple Choice', true_false: 'True / False', short_answer: 'Short Answer', essay: 'Essay' };
    return `
        <div class="q-card">
            <div class="q-card-header">
                <div class="q-number">${index + 1}</div>
                <div class="q-text">${esc(q.question_text)}</div>
                <div class="q-info">
                    <span class="q-type-badge">${typeLabels[q.question_type] || q.question_type}</span>
                    <span class="q-points">${q.points} pt${q.points > 1 ? 's' : ''}</span>
                    <div class="q-actions">
                        <button class="btn-icon" data-edit-q="${q.questions_id}" title="Edit">&#9998;</button>
                        <button class="btn-icon danger" data-delete-q="${q.questions_id}" title="Delete">&times;</button>
                    </div>
                </div>
            </div>
            ${q.options && q.options.length > 0 ? `
                <div class="q-options">
                    ${q.options.map(o => `
                        <div class="q-opt">
                            <div class="q-opt-marker ${o.is_correct ? 'correct' : ''}">${o.is_correct ? '&#10003;' : ''}</div>
                            <span>${esc(o.option_text)}</span>
                        </div>
                    `).join('')}
                </div>
            ` : ''}
        </div>
    `;
}

function openQuestionModal(container, quizId, question = null) {
    const isEdit = !!question;
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';

    const defaultOptions = question?.options || [
        { option_text: '', is_correct: false },
        { option_text: '', is_correct: false },
        { option_text: '', is_correct: false },
        { option_text: '', is_correct: false }
    ];

    const currentType = question?.question_type || 'multiple_choice';

    overlay.innerHTML = `
        <div class="modal">
            <div class="modal-header"><h3>${isEdit ? 'Edit' : 'Add'} Question</h3><button class="modal-close">&times;</button></div>
            <div class="modal-body">
                <div id="modal-alert"></div>
                <div class="form-group">
                    <label class="form-label">Question Text *</label>
                    <textarea class="form-textarea" id="m-qtext" rows="3" placeholder="Enter your question...">${esc(question?.question_text || '')}</textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Question Type</label>
                        <select class="form-select" id="m-qtype">
                            <option value="multiple_choice" ${currentType === 'multiple_choice' ? 'selected' : ''}>Multiple Choice</option>
                            <option value="true_false" ${currentType === 'true_false' ? 'selected' : ''}>True / False</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Points</label>
                        <input type="number" class="form-input" id="m-qpoints" value="${question?.points || 1}" min="1">
                    </div>
                </div>
                <div class="options-section" id="options-section">
                    <h4>Answer Options</h4>
                    <div id="options-list"></div>
                    <button class="btn-add-opt" id="btn-add-opt">+ Add Option</button>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary modal-cancel">Cancel</button>
                <button class="btn-primary" id="modal-save">${isEdit ? 'Update' : 'Add'} Question</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    const optionsList = overlay.querySelector('#options-list');
    const typeSelect = overlay.querySelector('#m-qtype');

    function renderOptions(opts) {
        const inputType = typeSelect.value === 'true_false' ? 'radio' : 'checkbox';
        optionsList.innerHTML = opts.map((o, i) => `
            <div class="opt-row">
                <input type="text" value="${esc(o.option_text || '')}" placeholder="Option ${i + 1}" data-opt-idx="${i}">
                <label class="opt-correct">
                    <input type="${inputType}" name="correct-opt" data-correct-idx="${i}" ${o.is_correct ? 'checked' : ''}>
                    Correct
                </label>
                ${opts.length > 2 ? `<button class="btn-remove-opt" data-remove-idx="${i}">&times;</button>` : ''}
            </div>
        `).join('');

        optionsList.querySelectorAll('.btn-remove-opt').forEach(btn => {
            btn.addEventListener('click', () => {
                opts.splice(parseInt(btn.dataset.removeIdx), 1);
                renderOptions(opts);
            });
        });

        // For multiple choice with checkbox, handle toggling individually
        // For true_false with radio, ensure only one is correct
        if (inputType === 'radio') {
            optionsList.querySelectorAll('input[type="radio"]').forEach(r => {
                r.addEventListener('change', () => {
                    opts.forEach((o, i) => o.is_correct = false);
                    const idx = parseInt(r.dataset.correctIdx);
                    opts[idx].is_correct = true;
                });
            });
        }
    }

    let options = defaultOptions.map(o => ({ ...o }));

    // Handle type change
    typeSelect.addEventListener('change', () => {
        if (typeSelect.value === 'true_false') {
            options = [
                { option_text: 'True', is_correct: true },
                { option_text: 'False', is_correct: false }
            ];
            overlay.querySelector('#btn-add-opt').style.display = 'none';
        } else {
            if (options.length < 2) {
                options = [
                    { option_text: '', is_correct: false },
                    { option_text: '', is_correct: false },
                    { option_text: '', is_correct: false },
                    { option_text: '', is_correct: false }
                ];
            }
            overlay.querySelector('#btn-add-opt').style.display = '';
        }
        renderOptions(options);
    });

    // Initial render
    if (currentType === 'true_false') {
        overlay.querySelector('#btn-add-opt').style.display = 'none';
    }
    renderOptions(options);

    overlay.querySelector('#btn-add-opt').addEventListener('click', () => {
        options.push({ option_text: '', is_correct: false });
        renderOptions(options);
    });

    overlay.querySelector('.modal-close').addEventListener('click', () => overlay.remove());
    overlay.querySelector('.modal-cancel').addEventListener('click', () => overlay.remove());
    overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });

    overlay.querySelector('#modal-save').addEventListener('click', async () => {
        const alertEl = overlay.querySelector('#modal-alert');

        // Gather option values from inputs
        const optInputs = optionsList.querySelectorAll('input[type="text"]');
        const correctInputs = optionsList.querySelectorAll('input[name="correct-opt"]');
        const finalOptions = [];
        optInputs.forEach((inp, i) => {
            finalOptions.push({
                option_text: inp.value.trim(),
                is_correct: correctInputs[i]?.checked ? true : false
            });
        });

        const payload = {
            quiz_id: quizId,
            question_text: overlay.querySelector('#m-qtext').value.trim(),
            question_type: typeSelect.value,
            points: parseInt(overlay.querySelector('#m-qpoints').value) || 1,
            options: finalOptions.filter(o => o.option_text !== '')
        };

        if (isEdit) payload.questions_id = question.questions_id;

        if (!payload.question_text) {
            alertEl.innerHTML = '<div class="alert alert-error">Question text is required</div>';
            return;
        }

        const action = isEdit ? 'update-question' : 'add-question';
        const saveBtn = overlay.querySelector('#modal-save');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        const res = await Api.post(`/QuizzesAPI.php?action=${action}`, payload);
        if (res.success) {
            overlay.remove();
            loadPage(container, quizId);
        } else {
            alertEl.innerHTML = `<div class="alert alert-error">${res.message}</div>`;
            saveBtn.disabled = false;
            saveBtn.textContent = isEdit ? 'Update Question' : 'Add Question';
        }
    });
}

async function openBankModal(container, quizId) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.innerHTML = `
        <div class="modal bank-modal">
            <div class="modal-header">
                <h3>📋 Copy from Question Bank</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="bank-filters">
                    <input type="text" id="bank-search" placeholder="Search questions...">
                    <select id="bank-type">
                        <option value="">All Types</option>
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="true_false">True / False</option>
                        <option value="short_answer">Short Answer</option>
                        <option value="essay">Essay</option>
                    </select>
                </div>
                <div class="bank-list" id="bank-list"><div class="bank-loading">Loading...</div></div>
            </div>
            <div class="modal-footer" style="justify-content:space-between;align-items:center;">
                <span style="font-size:13px;color:#737373;" id="bank-status">Click <strong>Copy</strong> to add individually or use <strong>Copy All</strong></span>
                <button class="btn-primary" id="btn-copy-all" style="display:none;">Copy All</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    overlay.querySelector('.modal-close').addEventListener('click', () => overlay.remove());
    overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });

    let searchTimer = null;
    let currentQuestions = [];
    const searchInput = overlay.querySelector('#bank-search');
    const typeSelect  = overlay.querySelector('#bank-type');
    const copyAllBtn  = overlay.querySelector('#btn-copy-all');
    const statusEl    = overlay.querySelector('#bank-status');

    async function loadBank() {
        const listEl = overlay.querySelector('#bank-list');
        listEl.innerHTML = '<div class="bank-loading">Loading...</div>';
        copyAllBtn.style.display = 'none';
        currentQuestions = [];

        const search = searchInput.value.trim();
        const type   = typeSelect.value;
        let url = '/QuestionBankAPI.php?action=browse';
        if (search) url += '&search=' + encodeURIComponent(search);
        if (type)   url += '&type=' + encodeURIComponent(type);

        const res = await Api.get(url);
        currentQuestions = res.success ? res.data : [];

        if (currentQuestions.length === 0) {
            listEl.innerHTML = '<div class="bank-empty">No questions found in the bank.</div>';
            statusEl.innerHTML = 'No questions found.';
            return;
        }

        copyAllBtn.style.display = '';
        copyAllBtn.textContent = `Copy All (${currentQuestions.length})`;
        copyAllBtn.disabled = false;
        statusEl.innerHTML = `${currentQuestions.length} question${currentQuestions.length > 1 ? 's' : ''} found`;

        const typeTagClass = { multiple_choice: 'mc', true_false: 'tf', short_answer: 'sa', essay: 'es' };
        const typeLabel    = { multiple_choice: 'Multiple Choice', true_false: 'True/False', short_answer: 'Short Answer', essay: 'Essay' };

        listEl.innerHTML = currentQuestions.map(q => `
            <div class="bq-card">
                <div class="bq-body">
                    <div class="bq-text">${esc(q.question_text)}</div>
                    <div class="bq-meta">
                        <span class="bq-tag ${typeTagClass[q.question_type] || ''}">${typeLabel[q.question_type] || q.question_type}</span>
                        <span>${q.points} pt${q.points > 1 ? 's' : ''}</span>
                        ${q.subject_code ? `<span class="bq-tag">${esc(q.subject_code)}</span>` : ''}
                        <span>${esc(q.first_name)} ${esc(q.last_name)}</span>
                        ${q.copy_count > 0 ? `<span>Used ${q.copy_count}×</span>` : ''}
                    </div>
                    ${q.options && q.options.length > 0 ? `
                        <div class="bq-opts">
                            ${q.options.map(o => `<div class="bq-opt ${o.is_correct ? 'correct' : ''}">${o.is_correct ? '✓' : '○'} ${esc(o.option_text)}</div>`).join('')}
                        </div>
                    ` : ''}
                </div>
                <button class="btn-copy-q" data-qbank="${q.qbank_id}">Copy</button>
            </div>
        `).join('');

        listEl.querySelectorAll('.btn-copy-q').forEach(btn => {
            btn.addEventListener('click', async () => {
                btn.disabled = true;
                btn.textContent = '...';
                const res = await Api.post('/QuestionBankAPI.php?action=copy', {
                    qbank_id: parseInt(btn.dataset.qbank),
                    quiz_id:  parseInt(quizId)
                });
                if (res.success) {
                    btn.textContent = '✓ Copied';
                    btn.style.background = '#1a6635';
                    loadPage(container, quizId);
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Copy';
                    alert(res.message || 'Failed to copy');
                }
            });
        });
    }

    // Copy All handler — copies every visible question sequentially
    copyAllBtn.addEventListener('click', async () => {
        if (!currentQuestions.length) return;
        copyAllBtn.disabled = true;
        copyAllBtn.textContent = 'Copying...';
        searchInput.disabled = true;
        typeSelect.disabled  = true;

        const copyBtns = overlay.querySelectorAll('.btn-copy-q');
        let done = 0, failed = 0;

        for (let i = 0; i < currentQuestions.length; i++) {
            const q   = currentQuestions[i];
            const btn = copyBtns[i];
            if (btn) { btn.disabled = true; btn.textContent = '...'; }

            const res = await Api.post('/QuestionBankAPI.php?action=copy', {
                qbank_id: q.qbank_id,
                quiz_id:  parseInt(quizId)
            });

            if (res.success) {
                done++;
                if (btn) { btn.textContent = '✓'; btn.style.background = '#1a6635'; }
            } else {
                failed++;
                if (btn) { btn.textContent = '✗'; btn.style.background = '#b91c1c'; btn.disabled = false; }
            }

            statusEl.textContent = `Copying... ${done + failed}/${currentQuestions.length}`;
        }

        statusEl.innerHTML = `<strong style="color:#1B4D3E;">✓ ${done} copied${failed ? `, ${failed} failed` : ''}</strong>`;
        copyAllBtn.textContent = 'Done';
        searchInput.disabled = false;
        typeSelect.disabled  = false;
        loadPage(container, quizId);
    });

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadBank, 350);
    });
    typeSelect.addEventListener('change', loadBank);

    loadBank();
}

function esc(str) { const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }
