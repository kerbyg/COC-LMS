/**
 * Student Take Quiz Page
 * Professional quiz-taking interface with timer, navigator, and submit
 */
import { Api } from '../../api.js';

export async function render(container) {
    const params = new URLSearchParams(window.location.hash.split('?')[1] || '');
    const quizId = params.get('quiz_id');

    if (!quizId) {
        container.innerHTML = '<div style="text-align:center;padding:60px;color:#737373">No quiz selected. <a href="#student/quizzes" style="color:#1B4D3E">Go to Quizzes</a></div>';
        return;
    }

    container.innerHTML = '<div style="text-align:center;padding:80px;color:#737373">Loading quiz...</div>';

    const res = await Api.get('/QuizzesAPI.php?action=questions&id=' + quizId);
    if (!res.success) {
        container.innerHTML = `<div style="text-align:center;padding:60px;color:#b91c1c">${res.message || 'Failed to load quiz'}</div>`;
        return;
    }

    const quiz       = res.data.quiz;
    const questions  = res.data.questions || [];
    const answers    = {};
    const oneAtATime = !!quiz.one_at_a_time;
    let currentQ = 0;

    if (questions.length === 0) {
        container.innerHTML = `
            <div style="text-align:center;padding:80px">
                <div style="width:64px;height:64px;background:#f3f4f6;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:28px;color:#737373">?</div>
                <h3 style="color:#262626;margin-bottom:8px;font-size:18px">No Questions Available</h3>
                <p style="color:#737373;font-size:14px;margin-bottom:20px">This quiz doesn't have any questions yet.</p>
                <a href="#student/quizzes" style="color:#1B4D3E;font-weight:600;font-size:14px">Back to Quizzes</a>
            </div>`;
        return;
    }

    let timeLeft = (quiz.time_limit || 30) * 60;
    let timerInterval;
    const startTime = Date.now();
    const totalPoints = questions.reduce((s, q) => s + (parseInt(q.points) || 1), 0);

    function renderQuiz() {
        container.innerHTML = `
            <style>
                .tq-wrap { max-width:960px; margin:0 auto; }

                /* Header */
                .tq-header { background:linear-gradient(135deg,#1B4D3E 0%,#2D6A4F 100%); border-radius:16px; padding:20px 28px; color:#fff; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
                .tq-header-left h3 { font-size:20px; font-weight:700; margin:0 0 4px; }
                .tq-header-left p { font-size:13px; opacity:.85; margin:0; }
                .tq-timer { background:rgba(0,0,0,.2); padding:10px 18px; border-radius:12px; font-size:24px; font-weight:800; font-family:'Courier New',monospace; letter-spacing:2px; min-width:90px; text-align:center; }
                .tq-timer.warn { color:#FCD34D; }
                .tq-timer.danger { color:#FCA5A5; animation:tq-pulse 1s infinite; }
                @keyframes tq-pulse { 0%,100%{opacity:1} 50%{opacity:.5} }

                /* Progress */
                .tq-progress { background:#e5e7eb; height:4px; border-radius:2px; margin-bottom:20px; overflow:hidden; }
                .tq-progress-fill { height:100%; background:linear-gradient(90deg,#1B4D3E,#2D6A4F); border-radius:2px; transition:width .3s ease; }

                /* Layout */
                .tq-body { display:grid; grid-template-columns:1fr 220px; gap:20px; align-items:start; }

                /* Question Card */
                .tq-question { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:28px; user-select:none; -webkit-user-select:none; }
                /* Allow typing inside inputs/textareas but not selecting question text */
                .tq-question input, .tq-question textarea { user-select:text; -webkit-user-select:text; }
                .tq-q-badge { display:inline-flex; align-items:center; gap:6px; margin-bottom:14px; }
                .tq-q-num { background:#1B4D3E; color:#fff; width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; }
                .tq-q-of { font-size:13px; color:#737373; }
                .tq-q-type { background:#f3f4f6; color:#404040; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600; text-transform:capitalize; margin-left:8px; }
                .tq-q-text { font-size:16px; font-weight:600; color:#1a1a1a; line-height:1.6; margin-bottom:8px; }
                .tq-q-points { font-size:12px; color:#737373; margin-bottom:20px; }

                /* Options */
                .tq-options { display:flex; flex-direction:column; gap:10px; }
                .tq-opt { display:flex; align-items:center; gap:14px; padding:14px 18px; border:2px solid #e5e7eb; border-radius:12px; cursor:pointer; transition:all .15s ease; user-select:none; }
                .tq-opt:hover { border-color:#93c5b8; background:#f0fdf4; }
                .tq-opt.selected { border-color:#1B4D3E; background:#ecfdf5; }
                .tq-opt-radio { width:22px; height:22px; border:2px solid #d1d5db; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; transition:all .15s; }
                .tq-opt.selected .tq-opt-radio { border-color:#1B4D3E; background:#1B4D3E; }
                .tq-opt.selected .tq-opt-radio::after { content:''; width:8px; height:8px; background:#fff; border-radius:50%; }
                .tq-opt-letter { width:28px; height:28px; border-radius:8px; background:#f3f4f6; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#404040; flex-shrink:0; }
                .tq-opt.selected .tq-opt-letter { background:#1B4D3E; color:#fff; }
                .tq-opt-text { font-size:14px; color:#262626; line-height:1.4; flex:1; }

                /* Navigation Buttons */
                .tq-nav-btns { display:flex; justify-content:space-between; margin-top:24px; padding-top:20px; border-top:1px solid #f0f0f0; }
                .tq-btn { padding:10px 22px; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; border:1px solid #e0e0e0; background:#fff; color:#404040; transition:all .15s; display:inline-flex; align-items:center; gap:6px; }
                .tq-btn:hover { background:#f5f5f5; }
                .tq-btn:disabled { opacity:.35; cursor:not-allowed; }
                .tq-btn.primary { background:#1B4D3E; color:#fff; border-color:#1B4D3E; }
                .tq-btn.primary:hover { background:#155c3a; }

                /* Navigator Panel */
                .tq-nav { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:20px; position:sticky; top:20px; }
                .tq-nav-title { font-size:12px; font-weight:700; color:#737373; text-transform:uppercase; letter-spacing:.8px; margin-bottom:14px; }
                .tq-nav-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:6px; margin-bottom:16px; }
                .tq-nav-num { width:100%; aspect-ratio:1; border-radius:8px; border:1.5px solid #e5e7eb; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:600; cursor:pointer; background:#fff; color:#6b7280; transition:all .15s; }
                .tq-nav-num:hover { border-color:#93c5b8; }
                .tq-nav-num.current { background:#1B4D3E; color:#fff; border-color:#1B4D3E; }
                .tq-nav-num.answered { background:#dcfce7; color:#166534; border-color:#86efac; }
                .tq-nav-num.answered.current { background:#1B4D3E; color:#fff; border-color:#1B4D3E; }

                .tq-nav-stats { margin-bottom:16px; }
                .tq-nav-stat-row { display:flex; justify-content:space-between; align-items:center; font-size:12px; padding:4px 0; }
                .tq-nav-stat-label { color:#737373; }
                .tq-nav-stat-value { font-weight:700; color:#262626; }

                .tq-nav-progress { background:#e5e7eb; height:6px; border-radius:3px; margin-bottom:16px; overflow:hidden; }
                .tq-nav-progress-fill { height:100%; background:#1B4D3E; border-radius:3px; transition:width .3s; }

                .tq-submit-btn { width:100%; padding:12px; border:none; border-radius:12px; background:linear-gradient(135deg,#1B4D3E,#2D6A4F); color:#fff; font-size:14px; font-weight:700; cursor:pointer; transition:all .2s; }
                .tq-submit-btn:hover { box-shadow:0 4px 14px rgba(0,70,27,.3); transform:translateY(-1px); }

                /* Confirm Modal */
                /* Text-answer question types */
                .tq-text-answer { display:flex; flex-direction:column; gap:8px; }
                .tq-text-label { font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.6px; margin-bottom:2px; }
                .tq-text-input { padding:12px 16px; border:2px solid #e5e7eb; border-radius:10px; font-size:15px; outline:none; transition:border-color .15s; width:100%; box-sizing:border-box; }
                .tq-text-input:focus { border-color:#1B4D3E; box-shadow:0 0 0 3px rgba(27,77,62,.08); }
                .tq-textarea { padding:12px 16px; border:2px solid #e5e7eb; border-radius:10px; font-size:14px; line-height:1.7; outline:none; resize:vertical; font-family:inherit; width:100%; box-sizing:border-box; transition:border-color .15s; }
                .tq-textarea:focus { border-color:#1B4D3E; box-shadow:0 0 0 3px rgba(27,77,62,.08); }
                .tq-essay { min-height:200px; }
                .tq-char-count { font-size:11px; color:#9ca3af; text-align:right; margin-top:2px; }
                .tq-type-hint { display:flex; align-items:flex-start; gap:8px; padding:10px 14px; border-radius:8px; font-size:12.5px; margin-bottom:16px; }
                .tq-type-hint.fill  { background:#EFF6FF; color:#1E40AF; border:1px solid #BFDBFE; }
                .tq-type-hint.short { background:#F0FDF4; color:#166534; border:1px solid #BBF7D0; }
                .tq-type-hint.essay { background:#FEF9C3; color:#854D0E; border:1px solid #FDE68A; }
                .tq-inline-blank { display:inline-block; border:none; border-bottom:2.5px solid #1B4D3E; outline:none; padding:2px 10px; font-size:16px; font-weight:700; color:#1B4D3E; min-width:130px; max-width:260px; background:rgba(27,77,62,.07); border-radius:4px 4px 0 0; text-align:center; transition:background .15s; vertical-align:middle; margin:0 4px; }
                .tq-inline-blank:focus { background:rgba(27,77,62,.14); }

                .tq-modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:1000; }
                .tq-modal { background:#fff; border-radius:16px; padding:32px; max-width:400px; width:90%; text-align:center; }
                .tq-modal h3 { font-size:18px; font-weight:700; color:#262626; margin-bottom:8px; }
                .tq-modal p { font-size:14px; color:#737373; margin-bottom:6px; }
                .tq-modal .warn-text { color:#B45309; font-weight:600; font-size:13px; margin-bottom:20px; }
                .tq-modal-btns { display:flex; gap:12px; justify-content:center; }

                /* Paste-blocked toast */
                .tq-paste-toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%) translateY(20px); background:#1e293b; color:#fff; padding:10px 20px; border-radius:10px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px; z-index:9999; opacity:0; transition:opacity .2s, transform .2s; pointer-events:none; white-space:nowrap; }
                .tq-paste-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
                .tq-paste-toast .tq-pt-icon { font-size:15px; }

                /* Tab-switch warning banner */
                .tq-tabwarn { position:fixed; top:0; left:0; right:0; z-index:9999; padding:13px 24px; display:flex; align-items:center; justify-content:space-between; gap:12px; font-size:13.5px; font-weight:600; animation:tq-slidein .3s ease forwards; }
                .tq-tabwarn.mild   { background:#FEF3C7; color:#92400E; border-bottom:2px solid #FCD34D; }
                .tq-tabwarn.danger { background:#FEE2E2; color:#991B1B; border-bottom:2px solid #FCA5A5; }
                @keyframes tq-slidein { from{transform:translateY(-100%)} to{transform:translateY(0)} }
                .tq-tabwarn-msg { display:flex; align-items:center; gap:10px; }
                .tq-tabwarn-close { background:none; border:none; cursor:pointer; font-size:18px; color:inherit; padding:0 4px; line-height:1; }
                /* Tab-switch count badge in sidebar */
                .tq-switch-badge { display:flex; justify-content:space-between; align-items:center; font-size:12px; padding:4px 0; margin-top:4px; }
                .tq-switch-badge-label { color:#B45309; font-weight:600; }
                .tq-switch-badge-val   { background:#FEF3C7; color:#B45309; border-radius:12px; padding:1px 9px; font-weight:800; font-size:11px; }
                .tq-switch-badge.danger .tq-switch-badge-label { color:#b91c1c; }
                .tq-switch-badge.danger .tq-switch-badge-val   { background:#FEE2E2; color:#b91c1c; }

                @media(max-width:768px) {
                    .tq-body { grid-template-columns:1fr; }
                    .tq-nav { position:static; order:-1; }
                    .tq-nav-grid { grid-template-columns:repeat(10,1fr); }
                    .tq-header { padding:16px 20px; }
                }
            </style>

            <div class="tq-wrap">
                <div class="tq-header">
                    <div class="tq-header-left">
                        <h3>${esc(quiz.title)}</h3>
                        <p>${questions.length} questions &middot; ${totalPoints} points &middot; ${quiz.passing_rate}% to pass</p>
                    </div>
                    <div class="tq-timer" id="tq-timer">${formatTime(timeLeft)}</div>
                </div>

                <div class="tq-progress"><div class="tq-progress-fill" id="tq-progress-fill" style="width:0%"></div></div>

                <div class="tq-body">
                    <div class="tq-question" id="tq-question"></div>
                    <div class="tq-nav">
                        ${oneAtATime ? `
                        <div class="tq-nav-title">Progress</div>
                        <div style="text-align:center;padding:10px 0 6px;">
                            <span style="font-size:32px;font-weight:800;color:#1B4D3E;" id="tq-aat-num">1</span>
                            <span style="font-size:16px;color:#9ca3af;font-weight:600;"> / ${questions.length}</span>
                        </div>
                        <div style="font-size:11px;color:#9ca3af;text-align:center;margin-bottom:10px;">questions</div>
                        <div class="tq-nav-progress" style="margin-bottom:16px;"><div class="tq-nav-progress-fill" id="tq-nav-progress-fill" style="width:${100/questions.length}%"></div></div>
                        <div class="tq-nav-stats">
                            <div class="tq-nav-stat-row"><span class="tq-nav-stat-label">Answered</span><span class="tq-nav-stat-value" id="tq-answered-count">0 / ${questions.length}</span></div>
                            <div class="tq-switch-badge" id="tq-switch-badge" style="display:none;">
                                <span class="tq-switch-badge-label">⚠️ Tab switches</span>
                                <span class="tq-switch-badge-val" id="tq-switch-count">0</span>
                            </div>
                        </div>
                        ` : `
                        <div class="tq-nav-title">Questions</div>
                        <div class="tq-nav-grid" id="tq-nav-grid">
                            ${questions.map((q, i) => `<div class="tq-nav-num" data-idx="${i}">${i + 1}</div>`).join('')}
                        </div>
                        <div class="tq-nav-stats">
                            <div class="tq-nav-stat-row"><span class="tq-nav-stat-label">Answered</span><span class="tq-nav-stat-value" id="tq-answered-count">0 / ${questions.length}</span></div>
                            <div class="tq-nav-stat-row"><span class="tq-nav-stat-label">Remaining</span><span class="tq-nav-stat-value" id="tq-remaining-count">${questions.length}</span></div>
                            <div class="tq-switch-badge" id="tq-switch-badge" style="display:none;">
                                <span class="tq-switch-badge-label">⚠️ Tab switches</span>
                                <span class="tq-switch-badge-val" id="tq-switch-count">0</span>
                            </div>
                        </div>
                        <div class="tq-nav-progress"><div class="tq-nav-progress-fill" id="tq-nav-progress-fill" style="width:0%"></div></div>
                        <button class="tq-submit-btn" id="tq-submit">Submit Quiz</button>
                        `}
                    </div>
                </div>
            </div>
        `;

        showQuestion(currentQ);
        startTimer();
        startTabSwitchDetection();

        // Question navigator — disabled in one-at-a-time mode (can't jump around)
        if (!oneAtATime) {
            container.querySelectorAll('.tq-nav-num').forEach(n => {
                n.addEventListener('click', () => {
                    currentQ = parseInt(n.dataset.idx);
                    showQuestion(currentQ);
                });
            });
        }

        // In one-at-a-time mode the submit button lives only in the last question's nav-btns
        // so we don't need the sidebar submit button — but we wire it just in case
        if (!oneAtATime) {
            container.querySelector('#tq-submit').addEventListener('click', () => showSubmitConfirm());
        }
    }

    function showQuestion(idx) {
        currentQ = idx;
        const q = questions[idx];
        const panel = container.querySelector('#tq-question');
        const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

        const typeLabelMap = {
            multiple_choice:    'Multiple Choice',
            true_false:         'True / False',
            fill_blank:         'Fill in the Blank',
            fill_in_the_blank:  'Fill in the Blank',
            short_answer:       'Short Answer',
            essay:              'Essay'
        };
        const typeLabel  = typeLabelMap[q.question_type] || q.question_type;
        const cleanText  = cleanQuestionText(q.question_text);
        const curAnswer  = answers[q.questions_id] ?? '';
        const isTextType = ['fill_blank','fill_in_the_blank','short_answer','essay'].includes(q.question_type);

        // Build answer area based on type
        let answerHtml = '';
        if (q.question_type === 'fill_blank' || q.question_type === 'fill_in_the_blank') {
            // Blank pattern: _____ (3+ underscores) or [blank]
            const hasBlank = /_{3,}|\[blank\]/i.test(cleanText);
            if (hasBlank) {
                // Replace blank placeholder with inline input — shown inline in question text
                const inlineText = cleanText.replace(/_{3,}|\[blank\]/gi,
                    `<input type="text" class="tq-inline-blank" id="tq-text-ans"
                            placeholder="     ?" value="${esc(String(curAnswer))}"
                            autocomplete="off" spellcheck="false">`
                );
                // Override the question text rendered later
                q._inlineBlankHtml = inlineText;
            }
            answerHtml = hasBlank ? '' : `
                <div class="tq-type-hint fill">
                    ✏️ &nbsp;Type your answer to complete the blank. Spelling matters.
                </div>
                <div class="tq-text-answer">
                    <label class="tq-text-label">Your Answer</label>
                    <input type="text" class="tq-text-input" id="tq-text-ans"
                           placeholder="Fill in the blank…"
                           value="${esc(String(curAnswer))}"
                           autocomplete="off" spellcheck="false">
                </div>`;
        } else if (q.question_type === 'short_answer') {
            const charMax = 500;
            answerHtml = `
                <div class="tq-type-hint short">
                    📝 &nbsp;Answer briefly and clearly — 1 to 3 sentences is ideal. &nbsp;<strong>⚠️ Pasting is disabled.</strong>
                </div>
                <div class="tq-text-answer">
                    <label class="tq-text-label">Your Answer</label>
                    <textarea class="tq-textarea" id="tq-text-ans"
                              placeholder="Write your short answer here…"
                              rows="5" maxlength="${charMax}">${esc(String(curAnswer))}</textarea>
                    <div class="tq-char-count"><span id="tq-char-num">${String(curAnswer).length}</span>/${charMax} characters</div>
                </div>`;
        } else if (q.question_type === 'essay') {
            const charMax = 5000;
            const wordCount = String(curAnswer).trim() ? String(curAnswer).trim().split(/\s+/).length : 0;
            answerHtml = `
                <div class="tq-type-hint essay">
                    📄 &nbsp;Write a complete, well-structured response. Use paragraphs and support your ideas. &nbsp;<strong>⚠️ Pasting is disabled.</strong>
                </div>
                <div class="tq-text-answer">
                    <label class="tq-text-label">Your Essay Response</label>
                    <textarea class="tq-textarea tq-essay" id="tq-text-ans"
                              placeholder="Write your essay here…"
                              rows="10" maxlength="${charMax}">${esc(String(curAnswer))}</textarea>
                    <div class="tq-char-count">
                        <span id="tq-word-num">${wordCount}</span> words &nbsp;·&nbsp;
                        <span id="tq-char-num">${String(curAnswer).length}</span>/${charMax} characters
                    </div>
                </div>`;
        } else {
            // Multiple choice / True-False
            answerHtml = `
                <div class="tq-options">
                    ${q.choices.map((c, ci) => `
                        <div class="tq-opt ${answers[q.questions_id] == c.option_id ? 'selected' : ''}" data-oid="${c.option_id}">
                            <div class="tq-opt-radio"></div>
                            <div class="tq-opt-letter">${letters[ci] || ci + 1}</div>
                            <div class="tq-opt-text">${esc(c.option_text)}</div>
                        </div>
                    `).join('')}
                </div>`;
        }

        panel.innerHTML = `
            <div class="tq-q-badge">
                <span class="tq-q-num">${idx + 1}</span>
                <span class="tq-q-of">of ${questions.length}</span>
                <span class="tq-q-type">${typeLabel}</span>
            </div>
            <div class="tq-q-text">${q._inlineBlankHtml || esc(cleanText)}</div>
            <div class="tq-q-points">${q.points} point${q.points > 1 ? 's' : ''}</div>
            ${answerHtml}
            <div class="tq-nav-btns">
                ${oneAtATime
                    ? `<span></span>
                       ${idx === questions.length - 1
                           ? `<button class="tq-btn primary" id="tq-next" onclick="document.getElementById('tq-submit')?.click()">✓ Submit Quiz</button>`
                           : `<button class="tq-btn primary" id="tq-next">Next &#8594;</button>`
                       }`
                    : `<button class="tq-btn" id="tq-prev" ${idx === 0 ? 'disabled' : ''}>&#8592; Previous</button>
                       <button class="tq-btn primary" id="tq-next" ${idx === questions.length - 1 ? 'disabled' : ''}>Next &#8594;</button>`
                }
            </div>
        `;

        // Block copying question text / options (ignore events that originate inside inputs/textareas)
        panel.addEventListener('copy', e => {
            if (e.target.matches('input, textarea')) return; // student's own typed text — allow
            e.preventDefault();
        });
        panel.addEventListener('contextmenu', e => {
            if (e.target.matches('input, textarea')) return; // keep browser spellcheck etc.
            e.preventDefault();
        });
        panel.addEventListener('keydown', e => {
            // Block Ctrl+C / Cmd+C on question content (not inside answer fields)
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'c' && !e.target.matches('input, textarea')) {
                e.preventDefault();
            }
            // Block Ctrl+A select-all on question content
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'a' && !e.target.matches('input, textarea')) {
                e.preventDefault();
            }
        });

        if (isTextType) {
            const inp = panel.querySelector('#tq-text-ans');
            inp.addEventListener('input', () => {
                answers[q.questions_id] = inp.value;
                updateNavigator();
                if (q.question_type === 'short_answer') {
                    panel.querySelector('#tq-char-num').textContent = inp.value.length;
                } else if (q.question_type === 'essay') {
                    const wc = inp.value.trim() ? inp.value.trim().split(/\s+/).length : 0;
                    panel.querySelector('#tq-word-num').textContent = wc;
                    panel.querySelector('#tq-char-num').textContent = inp.value.length;
                }
            });

            // Block copy-paste on essay & short-answer fields
            if (q.question_type === 'essay' || q.question_type === 'short_answer') {
                inp.addEventListener('paste', e => {
                    e.preventDefault();
                    showPasteBlockedToast();
                });
                inp.addEventListener('copy', e => e.preventDefault());
                inp.addEventListener('cut',  e => e.preventDefault());
                inp.addEventListener('contextmenu', e => e.preventDefault());
                inp.addEventListener('keydown', e => {
                    // Block Ctrl+V / Cmd+V  and  Ctrl+C / Cmd+C  and  Ctrl+X / Cmd+X
                    if ((e.ctrlKey || e.metaKey) && ['v','c','x'].includes(e.key.toLowerCase())) {
                        e.preventDefault();
                        if (e.key.toLowerCase() === 'v') showPasteBlockedToast();
                    }
                });
            }

            // Auto-focus for fill in the blank
            if (q.question_type === 'fill_blank' || q.question_type === 'fill_in_the_blank') {
                setTimeout(() => inp?.focus(), 50);
            }
        } else {
            panel.querySelectorAll('.tq-opt').forEach(opt => {
                opt.addEventListener('click', () => {
                    panel.querySelectorAll('.tq-opt').forEach(o => o.classList.remove('selected'));
                    opt.classList.add('selected');
                    answers[q.questions_id] = parseInt(opt.dataset.oid);
                    updateNavigator();
                });
            });
        }

        // Prev only exists in free-navigation mode
        panel.querySelector('#tq-prev')?.addEventListener('click', () => { if (idx > 0) showQuestion(idx - 1); });
        // Next: in one-at-a-time, last question button triggers submit — already handled inline
        if (!oneAtATime || idx < questions.length - 1) {
            panel.querySelector('#tq-next')?.addEventListener('click', () => { if (idx < questions.length - 1) showQuestion(idx + 1); });
        }

        updateNavigator();
    }

    function updateNavigator() {
        const answered = Object.keys(answers).length;
        const pct      = Math.round((answered / questions.length) * 100);

        container.querySelector('#tq-answered-count').textContent = `${answered} / ${questions.length}`;
        container.querySelector('#tq-progress-fill').style.width  = pct + '%';
        container.querySelector('#tq-nav-progress-fill').style.width = pct + '%';

        if (oneAtATime) {
            // Update "X / total" counter in simplified sidebar
            const aaNum = container.querySelector('#tq-aat-num');
            if (aaNum) aaNum.textContent = currentQ + 1;
            // Progress bar shows how far through the quiz (by question position, not answered count)
            const posPct = Math.round(((currentQ + 1) / questions.length) * 100);
            container.querySelector('#tq-nav-progress-fill').style.width = posPct + '%';
        } else {
            container.querySelector('#tq-remaining-count').textContent = questions.length - answered;
            container.querySelectorAll('.tq-nav-num').forEach(n => {
                const i   = parseInt(n.dataset.idx);
                const q   = questions[i];
                const ans = answers[q.questions_id];
                const isAnswered = ans !== undefined && ans !== null && ans !== '';
                n.classList.toggle('current',  i === currentQ);
                n.classList.toggle('answered', isAnswered);
            });
        }
    }

    function startTimer() {
        const timerEl = container.querySelector('#tq-timer');
        timerInterval = setInterval(() => {
            timeLeft--;
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                submitQuiz();
                return;
            }
            timerEl.textContent = formatTime(timeLeft);
            timerEl.className = 'tq-timer' + (timeLeft <= 60 ? ' danger' : timeLeft <= 300 ? ' warn' : '');
        }, 1000);
    }

    function showSubmitConfirm() {
        const answered = Object.keys(answers).length;
        const unanswered = questions.length - answered;
        const overlay = document.createElement('div');
        overlay.className = 'tq-modal-overlay';
        overlay.innerHTML = `
            <div class="tq-modal">
                <h3>Submit Quiz?</h3>
                <p>You answered <strong>${answered}</strong> of <strong>${questions.length}</strong> questions.</p>
                ${unanswered > 0 ? `<div class="warn-text">${unanswered} question${unanswered > 1 ? 's' : ''} unanswered!</div>` : '<p style="color:#1B4D3E;font-weight:600;margin-bottom:20px">All questions answered!</p>'}
                <div class="tq-modal-btns">
                    <button class="tq-btn" id="tq-cancel-submit">Go Back</button>
                    <button class="tq-btn primary" id="tq-confirm-submit">Submit Quiz</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        overlay.querySelector('#tq-cancel-submit').addEventListener('click', () => overlay.remove());
        overlay.querySelector('#tq-confirm-submit').addEventListener('click', () => {
            overlay.remove();
            submitQuiz();
        });
        overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });
    }

    async function submitQuiz() {
        clearInterval(timerInterval);
        cleanupTabDetection();
        const timeTaken = Math.round((Date.now() - startTime) / 1000);

        container.innerHTML = `
            <div style="text-align:center;padding:100px">
                <div style="width:48px;height:48px;border:4px solid #e5e7eb;border-top-color:#1B4D3E;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 20px"></div>
                <h3 style="color:#262626;font-size:18px;margin-bottom:8px">Submitting your answers...</h3>
                <p style="color:#737373;font-size:14px">Please wait while we grade your quiz.</p>
                <style>@keyframes spin{to{transform:rotate(360deg)}}</style>
            </div>`;

        try {
            const submitData = {
                quiz_id: parseInt(quiz.quiz_id),
                time_taken: timeTaken,
                tab_switches: tabSwitchCount,
                answers: {}
            };
            for (const [qId, optId] of Object.entries(answers)) {
                submitData.answers[qId] = optId;
            }

            const res = await Api.post('/QuizAttemptsAPI.php?action=submit', submitData);
            if (res.success && res.data?.attempt_id) {
                window.location.hash = `#student/quiz-result?attempt_id=${res.data.attempt_id}`;
            } else if (res.success) {
                // Fallback: fetch latest attempt
                const historyRes = await Api.get('/QuizAttemptsAPI.php?action=history&quiz_id=' + quiz.quiz_id);
                if (historyRes.success && historyRes.data?.length > 0) {
                    window.location.hash = `#student/quiz-result?attempt_id=${historyRes.data[0].attempt_id}`;
                } else {
                    window.location.hash = '#student/quizzes';
                }
            } else {
                showError(res.message || 'Failed to submit quiz');
            }
        } catch (err) {
            showError(err.message);
        }
    }

    function showError(msg) {
        container.innerHTML = `
            <div style="text-align:center;padding:80px">
                <div style="width:64px;height:64px;background:#FEE2E2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:28px;color:#b91c1c">!</div>
                <h3 style="color:#b91c1c;margin-bottom:8px;font-size:18px">Submission Error</h3>
                <p style="color:#737373;font-size:14px;margin-bottom:20px">${esc(msg)}</p>
                <a href="#student/quizzes" style="color:#1B4D3E;font-weight:600;font-size:14px">Back to Quizzes</a>
            </div>`;
    }

    // ── Tab-switch detection ──────────────────────────────────────────────────
    let tabSwitchCount  = 0;
    let lastLeaveTime   = 0;
    let warnBannerTimer = null;

    function showTabWarnBanner(count) {
        document.getElementById('tq-tabwarn-banner')?.remove();
        clearTimeout(warnBannerTimer);

        const isDanger = count >= 3;
        const banner   = document.createElement('div');
        banner.id        = 'tq-tabwarn-banner';
        banner.className = 'tq-tabwarn ' + (isDanger ? 'danger' : 'mild');
        banner.innerHTML = `
            <div class="tq-tabwarn-msg">
                <span style="font-size:18px">${isDanger ? '🚨' : '⚠️'}</span>
                <span>
                    <strong>Tab switch #${count} detected!</strong>
                    ${isDanger
                        ? ' Multiple violations have been logged. This will be visible to your instructor.'
                        : ' Please stay on this page during the quiz.'}
                </span>
            </div>
            <button class="tq-tabwarn-close" onclick="document.getElementById('tq-tabwarn-banner')?.remove()">✕</button>
        `;
        document.body.appendChild(banner);

        // Auto-dismiss: 8s for warnings, 12s for danger
        warnBannerTimer = setTimeout(() => banner.remove(), isDanger ? 12000 : 8000);
    }

    function updateSidebarBadge(count) {
        const badge    = document.getElementById('tq-switch-badge');
        const countEl  = document.getElementById('tq-switch-count');
        if (!badge || !countEl) return;
        badge.style.display = 'flex';
        countEl.textContent = count;
        if (count >= 3) badge.classList.add('danger');
    }

    const onVisibilityChange = () => {
        if (document.hidden) {
            // Student left the tab
            const now = Date.now();
            if (now - lastLeaveTime < 1500) return; // debounce
            lastLeaveTime = now;
            tabSwitchCount++;
            updateSidebarBadge(tabSwitchCount);
        } else {
            // Student came back — show the warning
            if (tabSwitchCount > 0) showTabWarnBanner(tabSwitchCount);
        }
    };

    const onWindowBlur = () => {
        // Catches alt-tab to another app (visibilitychange may not fire)
        const now = Date.now();
        if (now - lastLeaveTime < 1500) return; // debounce
        lastLeaveTime = now;
        tabSwitchCount++;
        updateSidebarBadge(tabSwitchCount);
    };

    const onWindowFocus = () => {
        // Show banner when they return via alt-tab
        if (tabSwitchCount > 0 && !document.hidden) showTabWarnBanner(tabSwitchCount);
    };

    function startTabSwitchDetection() {
        document.addEventListener('visibilitychange', onVisibilityChange);
        window.addEventListener('blur', onWindowBlur);
        window.addEventListener('focus', onWindowFocus);
    }

    function cleanupTabDetection() {
        document.removeEventListener('visibilitychange', onVisibilityChange);
        window.removeEventListener('blur', onWindowBlur);
        window.removeEventListener('focus', onWindowFocus);
        document.getElementById('tq-tabwarn-banner')?.remove();
        clearTimeout(warnBannerTimer);
    }
    // ─────────────────────────────────────────────────────────────────────────

    // ── Paste-block toast ─────────────────────────────────────────────────────
    let pasteToastTimer = null;
    function showPasteBlockedToast() {
        let toast = document.getElementById('tq-paste-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id        = 'tq-paste-toast';
            toast.className = 'tq-paste-toast';
            toast.innerHTML = `<span class="tq-pt-icon">🚫</span> Pasting is not allowed on this question.`;
            document.body.appendChild(toast);
        }
        clearTimeout(pasteToastTimer);
        // Force reflow so the transition re-plays if already visible
        toast.classList.remove('show');
        void toast.offsetWidth;
        toast.classList.add('show');
        pasteToastTimer = setTimeout(() => toast.classList.remove('show'), 3000);
    }
    // ─────────────────────────────────────────────────────────────────────────

    renderQuiz();
}

function cleanQuestionText(text) {
    // Remove AI-generated prefixes like [1]:, [2]:, MC1:, TF1:, etc.
    return (text || '').replace(/^\s*\[?\d+\]?\s*[:.]?\s*/, '').replace(/^(MC|TF|FIB|SA|ESSAY)\d*\s*[:.]?\s*/i, '').trim();
}

function formatTime(seconds) {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function esc(str) { const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }
