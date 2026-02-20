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

    const quiz = res.data.quiz;
    const questions = res.data.questions || [];
    const answers = {};
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
                .tq-question { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:28px; }
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
                .tq-modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:1000; }
                .tq-modal { background:#fff; border-radius:16px; padding:32px; max-width:400px; width:90%; text-align:center; }
                .tq-modal h3 { font-size:18px; font-weight:700; color:#262626; margin-bottom:8px; }
                .tq-modal p { font-size:14px; color:#737373; margin-bottom:6px; }
                .tq-modal .warn-text { color:#B45309; font-weight:600; font-size:13px; margin-bottom:20px; }
                .tq-modal-btns { display:flex; gap:12px; justify-content:center; }

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
                        <div class="tq-nav-title">Questions</div>
                        <div class="tq-nav-grid" id="tq-nav-grid">
                            ${questions.map((q, i) => `<div class="tq-nav-num" data-idx="${i}">${i + 1}</div>`).join('')}
                        </div>
                        <div class="tq-nav-stats">
                            <div class="tq-nav-stat-row"><span class="tq-nav-stat-label">Answered</span><span class="tq-nav-stat-value" id="tq-answered-count">0 / ${questions.length}</span></div>
                            <div class="tq-nav-stat-row"><span class="tq-nav-stat-label">Remaining</span><span class="tq-nav-stat-value" id="tq-remaining-count">${questions.length}</span></div>
                        </div>
                        <div class="tq-nav-progress"><div class="tq-nav-progress-fill" id="tq-nav-progress-fill" style="width:0%"></div></div>
                        <button class="tq-submit-btn" id="tq-submit">Submit Quiz</button>
                    </div>
                </div>
            </div>
        `;

        showQuestion(currentQ);
        startTimer();

        container.querySelectorAll('.tq-nav-num').forEach(n => {
            n.addEventListener('click', () => {
                currentQ = parseInt(n.dataset.idx);
                showQuestion(currentQ);
            });
        });

        container.querySelector('#tq-submit').addEventListener('click', () => showSubmitConfirm());
    }

    function showQuestion(idx) {
        currentQ = idx;
        const q = questions[idx];
        const panel = container.querySelector('#tq-question');
        const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        const typeLabel = q.question_type === 'true_false' ? 'True / False' : q.question_type === 'multiple_choice' ? 'Multiple Choice' : q.question_type;
        const cleanText = cleanQuestionText(q.question_text);

        panel.innerHTML = `
            <div class="tq-q-badge">
                <span class="tq-q-num">${idx + 1}</span>
                <span class="tq-q-of">of ${questions.length}</span>
                <span class="tq-q-type">${typeLabel}</span>
            </div>
            <div class="tq-q-text">${esc(cleanText)}</div>
            <div class="tq-q-points">${q.points} point${q.points > 1 ? 's' : ''}</div>
            <div class="tq-options">
                ${q.choices.map((c, ci) => `
                    <div class="tq-opt ${answers[q.questions_id] == c.option_id ? 'selected' : ''}" data-oid="${c.option_id}">
                        <div class="tq-opt-radio"></div>
                        <div class="tq-opt-letter">${letters[ci] || ci + 1}</div>
                        <div class="tq-opt-text">${esc(c.option_text)}</div>
                    </div>
                `).join('')}
            </div>
            <div class="tq-nav-btns">
                <button class="tq-btn" id="tq-prev" ${idx === 0 ? 'disabled' : ''}>&#8592; Previous</button>
                <button class="tq-btn primary" id="tq-next" ${idx === questions.length - 1 ? 'disabled' : ''}>Next &#8594;</button>
            </div>
        `;

        panel.querySelectorAll('.tq-opt').forEach(opt => {
            opt.addEventListener('click', () => {
                panel.querySelectorAll('.tq-opt').forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');
                answers[q.questions_id] = parseInt(opt.dataset.oid);
                updateNavigator();
            });
        });

        panel.querySelector('#tq-prev').addEventListener('click', () => {
            if (idx > 0) showQuestion(idx - 1);
        });
        panel.querySelector('#tq-next').addEventListener('click', () => {
            if (idx < questions.length - 1) showQuestion(idx + 1);
        });

        updateNavigator();
    }

    function updateNavigator() {
        const answered = Object.keys(answers).length;
        const pct = Math.round((answered / questions.length) * 100);

        container.querySelector('#tq-answered-count').textContent = `${answered} / ${questions.length}`;
        container.querySelector('#tq-remaining-count').textContent = questions.length - answered;
        container.querySelector('#tq-progress-fill').style.width = pct + '%';
        container.querySelector('#tq-nav-progress-fill').style.width = pct + '%';

        container.querySelectorAll('.tq-nav-num').forEach(n => {
            const i = parseInt(n.dataset.idx);
            const q = questions[i];
            n.classList.toggle('current', i === currentQ);
            n.classList.toggle('answered', !!answers[q.questions_id]);
        });
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
