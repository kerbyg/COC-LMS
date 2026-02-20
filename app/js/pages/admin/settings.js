/**
 * Admin Settings Page
 * System settings management — professional left-nav layout
 */
import { Api } from '../../api.js';

let activeSection = 'general';

export async function render(container) {
    container.innerHTML = `
        <style>
            .set-wrap { max-width: 1100px; }

            /* ── Banner ── */
            .set-banner {
                background: linear-gradient(135deg, #00461B 0%, #006428 50%, #004d1f 100%);
                border-radius: 20px; padding: 28px 36px; margin-bottom: 28px;
                display: flex; align-items: center; gap: 20px;
                position: relative; overflow: hidden;
                box-shadow: 0 8px 24px -4px rgba(0,70,27,.25);
            }
            .set-banner::before {
                content: ''; position: absolute; top: -60%; right: -5%;
                width: 300px; height: 300px;
                background: rgba(255,255,255,.05); border-radius: 50%;
            }
            .set-banner-icon {
                width: 52px; height: 52px; border-radius: 14px;
                background: rgba(255,255,255,.15);
                display: flex; align-items: center; justify-content: center;
                font-size: 24px; flex-shrink: 0; position: relative; z-index: 1;
            }
            .set-banner-text { position: relative; z-index: 1; }
            .set-banner-text h1 { font-size: 22px; font-weight: 800; color: #fff; margin-bottom: 4px; }
            .set-banner-text p  { color: rgba(255,255,255,.78); font-size: 14px; }

            /* ── Layout ── */
            .set-layout { display: grid; grid-template-columns: 220px 1fr; gap: 24px; align-items: start; }

            /* ── Left Nav ── */
            .set-nav {
                background: #fff; border: 1px solid #e8e8e8; border-radius: 16px;
                overflow: hidden; position: sticky; top: 20px;
            }
            .set-nav-label {
                padding: 14px 18px 8px; font-size: 11px; font-weight: 700;
                color: #a3a3a3; letter-spacing: .8px; text-transform: uppercase;
            }
            .set-nav-item {
                display: flex; align-items: center; gap: 11px;
                padding: 11px 18px; cursor: pointer; transition: all .18s;
                border-left: 3px solid transparent; margin: 2px 0;
                font-size: 13.5px; font-weight: 500; color: #525252;
            }
            .set-nav-item:hover { background: #f5faf7; color: #00461B; }
            .set-nav-item.active { background: #f0fdf4; color: #00461B; font-weight: 700; border-left-color: #00461B; }
            .set-nav-item .nav-icon { font-size: 16px; width: 22px; text-align: center; }
            .set-nav-divider { height: 1px; background: #f0f0f0; margin: 6px 0; }

            /* ── Right Content ── */
            .set-panel { display: none; }
            .set-panel.active { display: block; }

            .set-card {
                background: #fff; border: 1px solid #e8e8e8; border-radius: 16px;
                overflow: hidden; margin-bottom: 20px;
            }
            .set-card:last-child { margin-bottom: 0; }
            .set-card-head {
                padding: 20px 24px 16px; border-bottom: 1px solid #f0f0f0;
                display: flex; align-items: center; gap: 14px;
            }
            .set-card-head-icon {
                width: 40px; height: 40px; border-radius: 10px;
                display: flex; align-items: center; justify-content: center;
                font-size: 18px; flex-shrink: 0;
            }
            .set-card-head-icon.green  { background: linear-gradient(135deg,#D1FAE5,#A7F3D0); }
            .set-card-head-icon.blue   { background: linear-gradient(135deg,#DBEAFE,#BFDBFE); }
            .set-card-head-icon.yellow { background: linear-gradient(135deg,#FEF3C7,#FDE68A); }
            .set-card-head-icon.purple { background: linear-gradient(135deg,#EDE9FE,#DDD6FE); }
            .set-card-head-icon.red    { background: linear-gradient(135deg,#FEE2E2,#FECACA); }
            .set-card-title h3 { font-size: 15px; font-weight: 700; color: #262626; }
            .set-card-title p  { font-size: 12.5px; color: #737373; margin-top: 2px; }

            .set-card-body { padding: 24px; }
            .set-card-foot {
                padding: 14px 24px; background: #fafafa; border-top: 1px solid #f0f0f0;
                display: flex; justify-content: flex-end; align-items: center; gap: 12px;
            }

            /* ── Form Elements ── */
            .fg { margin-bottom: 20px; }
            .fg:last-child { margin-bottom: 0; }
            .fg-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
            .fg label {
                display: block; font-size: 12.5px; font-weight: 600;
                color: #404040; margin-bottom: 6px;
            }
            .fg-hint { font-size: 11.5px; color: #8a8a8a; margin-top: 5px; }
            .f-input, .f-select, .f-textarea {
                width: 100%; padding: 10px 14px;
                border: 1.5px solid #e0e0e0; border-radius: 9px;
                font-size: 13.5px; font-family: inherit; box-sizing: border-box;
                background: #fff; color: #262626; transition: border .15s, box-shadow .15s;
            }
            .f-input:focus, .f-select:focus, .f-textarea:focus {
                outline: none; border-color: #00461B;
                box-shadow: 0 0 0 3px rgba(0,70,27,.1);
            }
            .f-textarea { resize: vertical; }
            .f-input[readonly] { background: #f7f7f7; color: #737373; cursor: default; }

            /* Toggle switch */
            .toggle-wrap { display: flex; align-items: center; gap: 10px; }
            .toggle { position: relative; display: inline-block; width: 44px; height: 24px; }
            .toggle input { opacity: 0; width: 0; height: 0; }
            .toggle-slider {
                position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
                background: #d4d4d4; border-radius: 24px; transition: .25s;
            }
            .toggle-slider:before {
                position: absolute; content: ''; height: 18px; width: 18px;
                left: 3px; bottom: 3px; background: #fff;
                border-radius: 50%; transition: .25s;
                box-shadow: 0 1px 3px rgba(0,0,0,.2);
            }
            .toggle input:checked + .toggle-slider { background: #00461B; }
            .toggle input:checked + .toggle-slider:before { transform: translateX(20px); }
            .toggle-label { font-size: 13px; font-weight: 500; color: #525252; }

            /* Radio cards */
            .radio-cards { display: flex; gap: 10px; }
            .radio-card {
                flex: 1; border: 1.5px solid #e0e0e0; border-radius: 10px;
                padding: 12px 16px; cursor: pointer; transition: all .18s;
                display: flex; align-items: center; gap: 10px; font-size: 13px;
            }
            .radio-card:hover { border-color: #00461B; background: #f7fdf9; }
            .radio-card.selected { border-color: #00461B; background: #f0fdf4; }
            .radio-card input { accent-color: #00461B; }

            /* Password field */
            .pw-wrap { position: relative; }
            .pw-wrap .f-input { padding-right: 42px; }
            .pw-toggle {
                position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
                background: none; border: none; cursor: pointer; font-size: 15px; color: #737373;
            }

            /* Buttons */
            .btn-primary {
                background: linear-gradient(135deg,#00461B,#006428);
                color: #fff; border: none; padding: 9px 22px;
                border-radius: 9px; font-weight: 700; font-size: 13px; cursor: pointer;
                display: flex; align-items: center; gap: 7px;
                transition: box-shadow .2s;
            }
            .btn-primary:hover { box-shadow: 0 3px 10px rgba(0,70,27,.35); }
            .btn-primary:disabled { opacity: .6; cursor: not-allowed; }
            .btn-danger {
                background: #FEE2E2; color: #b91c1c;
                border: none; padding: 9px 22px;
                border-radius: 9px; font-weight: 700; font-size: 13px; cursor: pointer;
                transition: background .18s;
            }
            .btn-danger:hover { background: #FCA5A5; }

            /* Alert toast */
            .set-toast {
                position: fixed; top: 24px; right: 24px; z-index: 9999;
                padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 600;
                box-shadow: 0 4px 16px rgba(0,0,0,.12);
                animation: slideIn .25s ease;
            }
            .set-toast.success { background: #D1FAE5; color: #065F46; border: 1px solid #6EE7B7; }
            .set-toast.error   { background: #FEE2E2; color: #b91c1c; border: 1px solid #FCA5A5; }
            @keyframes slideIn { from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:translateY(0); } }

            /* Tag chips */
            .ext-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
            .ext-chip {
                background: #F5F5F5; border: 1px solid #e0e0e0;
                padding: 3px 10px; border-radius: 20px;
                font-size: 11.5px; font-weight: 600; color: #525252;
            }

            /* Number input with stepper */
            .num-wrap { display: flex; align-items: center; gap: 0; }
            .num-wrap .f-input { border-radius: 9px 0 0 9px; border-right: none; text-align: center; width: 80px; }
            .num-btn {
                border: 1.5px solid #e0e0e0; background: #f5f5f5;
                padding: 0 13px; font-size: 16px; cursor: pointer; height: 42px;
                color: #525252; transition: background .15s;
            }
            .num-btn:last-child { border-radius: 0 9px 9px 0; }
            .num-btn:hover { background: #e8e8e8; }

            @media(max-width:900px) {
                .set-layout { grid-template-columns: 1fr; }
                .set-nav { position: static; }
                .fg-row { grid-template-columns: 1fr; }
            }
        </style>

        <div class="set-wrap">
            <!-- Banner -->
            <div class="set-banner">
                <div class="set-banner-icon">⚙️</div>
                <div class="set-banner-text">
                    <h1>System Settings</h1>
                    <p>Configure and manage CIT-LMS system-wide preferences</p>
                </div>
            </div>

            <div class="set-layout">
                <!-- Left Nav -->
                <aside class="set-nav">
                    <div class="set-nav-label">Configuration</div>
                    <div class="set-nav-item active" data-section="general">
                        <span class="nav-icon">🏫</span> General
                    </div>
                    <div class="set-nav-item" data-section="quiz">
                        <span class="nav-icon">📝</span> Quiz Settings
                    </div>
                    <div class="set-nav-item" data-section="upload">
                        <span class="nav-icon">📁</span> File Upload
                    </div>
                    <div class="set-nav-divider"></div>
                    <div class="set-nav-label">Integrations</div>
                    <div class="set-nav-item" data-section="ai">
                        <span class="nav-icon">🤖</span> AI Generation
                    </div>
                    <div class="set-nav-divider"></div>
                    <div class="set-nav-label">Advanced</div>
                    <div class="set-nav-item" data-section="maintenance">
                        <span class="nav-icon">🔧</span> Maintenance
                    </div>
                </aside>

                <!-- Right Panels -->
                <main>
                    <!-- ── General ── -->
                    <div class="set-panel active" data-panel="general">
                        <div class="set-card">
                            <div class="set-card-head">
                                <div class="set-card-head-icon green">🏫</div>
                                <div class="set-card-title">
                                    <h3>General Settings</h3>
                                    <p>Basic system information and academic calendar</p>
                                </div>
                            </div>
                            <div class="set-card-body">
                                <div class="fg">
                                    <label>Site Name</label>
                                    <input class="f-input" id="s-site-name" value="CIT-LMS">
                                </div>
                                <div class="fg">
                                    <label>Site Tagline</label>
                                    <input class="f-input" id="s-site-tagline" value="Learning Management System">
                                </div>
                                <div class="fg-row">
                                    <div class="fg">
                                        <label>Academic Year</label>
                                        <input class="f-input" id="s-acad-year" value="2025-2026" placeholder="e.g. 2025-2026">
                                    </div>
                                    <div class="fg">
                                        <label>Current Semester</label>
                                        <select class="f-select" id="s-semester">
                                            <option value="1st">1st Semester</option>
                                            <option value="2nd">2nd Semester</option>
                                            <option value="summer">Summer</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="fg">
                                    <label>Student Enrollment</label>
                                    <div class="radio-cards">
                                        <label class="radio-card selected" id="enroll-open-card">
                                            <input type="radio" name="s-enrollment" value="1" checked> 🟢 Open
                                        </label>
                                        <label class="radio-card" id="enroll-closed-card">
                                            <input type="radio" name="s-enrollment" value="0"> 🔴 Closed
                                        </label>
                                    </div>
                                    <div class="fg-hint">Controls whether students can enroll in sections</div>
                                </div>
                            </div>
                            <div class="set-card-foot">
                                <button class="btn-primary" data-section="general">💾 Save General Settings</button>
                            </div>
                        </div>
                    </div>

                    <!-- ── Quiz ── -->
                    <div class="set-panel" data-panel="quiz">
                        <div class="set-card">
                            <div class="set-card-head">
                                <div class="set-card-head-icon blue">📝</div>
                                <div class="set-card-title">
                                    <h3>Quiz Settings</h3>
                                    <p>Default values applied when instructors create new quizzes</p>
                                </div>
                            </div>
                            <div class="set-card-body">
                                <div class="fg">
                                    <label>Default Time Limit</label>
                                    <div class="num-wrap">
                                        <button class="num-btn" id="dec-time">−</button>
                                        <input type="number" class="f-input" id="s-quiz-time" value="30" min="1" max="300">
                                        <button class="num-btn" id="inc-time">+</button>
                                    </div>
                                    <div class="fg-hint">Minutes — instructors can override per quiz</div>
                                </div>
                                <div class="fg">
                                    <label>Default Passing Rate</label>
                                    <div class="num-wrap">
                                        <button class="num-btn" id="dec-pass">−</button>
                                        <input type="number" class="f-input" id="s-passing-rate" value="60" min="0" max="100">
                                        <button class="num-btn" id="inc-pass">+</button>
                                    </div>
                                    <div class="fg-hint">Percentage — minimum score to pass</div>
                                </div>
                                <div class="fg">
                                    <label>Allow Quiz Retakes</label>
                                    <div class="toggle-wrap">
                                        <label class="toggle">
                                            <input type="checkbox" id="s-retakes" checked>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="toggle-label" id="retake-label">Enabled — students may retake quizzes</span>
                                    </div>
                                </div>
                                <div class="fg">
                                    <label>Show Correct Answers After Submission</label>
                                    <div class="toggle-wrap">
                                        <label class="toggle">
                                            <input type="checkbox" id="s-show-answers" checked>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="toggle-label" id="answers-label">Enabled — students see answers on result page</span>
                                    </div>
                                </div>
                            </div>
                            <div class="set-card-foot">
                                <button class="btn-primary" data-section="quiz">💾 Save Quiz Settings</button>
                            </div>
                        </div>
                    </div>

                    <!-- ── Upload ── -->
                    <div class="set-panel" data-panel="upload">
                        <div class="set-card">
                            <div class="set-card-head">
                                <div class="set-card-head-icon yellow">📁</div>
                                <div class="set-card-title">
                                    <h3>File Upload Settings</h3>
                                    <p>Control what files users can upload and their size limits</p>
                                </div>
                            </div>
                            <div class="set-card-body">
                                <div class="fg">
                                    <label>Maximum File Size</label>
                                    <div class="num-wrap">
                                        <button class="num-btn" id="dec-size">−</button>
                                        <input type="number" class="f-input" id="s-max-file" value="10" min="1" max="100">
                                        <button class="num-btn" id="inc-size">+</button>
                                    </div>
                                    <div class="fg-hint">Megabytes (MB)</div>
                                </div>
                                <div class="fg">
                                    <label>Allowed File Extensions</label>
                                    <input class="f-input" id="s-extensions" value="pdf,doc,docx,ppt,pptx,jpg,png">
                                    <div class="fg-hint">Comma-separated — no spaces</div>
                                    <div class="ext-chips" id="ext-preview"></div>
                                </div>
                            </div>
                            <div class="set-card-foot">
                                <button class="btn-primary" data-section="upload">💾 Save Upload Settings</button>
                            </div>
                        </div>
                    </div>

                    <!-- ── AI ── -->
                    <div class="set-panel" data-panel="ai">
                        <div class="set-card">
                            <div class="set-card-head">
                                <div class="set-card-head-icon purple">🤖</div>
                                <div class="set-card-title">
                                    <h3>AI Quiz Generation</h3>
                                    <p>Configure the Groq API for AI-powered quiz creation</p>
                                </div>
                            </div>
                            <div class="set-card-body">
                                <div class="fg">
                                    <label>Groq API Key</label>
                                    <div class="pw-wrap">
                                        <input type="password" class="f-input" id="s-groq-key" placeholder="gsk_...">
                                        <button class="pw-toggle" id="toggle-key" title="Show/hide key">👁️</button>
                                    </div>
                                    <div class="fg-hint">🔗 Get your key at <strong>console.groq.com</strong> — kept server-side only</div>
                                </div>
                                <div class="fg">
                                    <label>AI Model</label>
                                    <select class="f-select" id="s-ai-model">
                                        <option value="llama-3.3-70b-versatile">🏆 Llama 3.3 70B — Best Quality (Recommended)</option>
                                        <option value="llama-3.1-8b-instant">⚡ Llama 3.1 8B — Fast & Lightweight</option>
                                        <option value="mixtral-8x7b-32768">🔀 Mixtral 8x7B — Large Context Window</option>
                                        <option value="gemma2-9b-it">💎 Gemma 2 9B — Google DeepMind</option>
                                    </select>
                                    <div class="fg-hint">Model used for generating quiz questions from uploaded documents</div>
                                </div>
                                <div class="fg">
                                    <label>AI Feature Status</label>
                                    <div class="toggle-wrap">
                                        <label class="toggle">
                                            <input type="checkbox" id="s-ai-enabled" checked>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="toggle-label" id="ai-status-label">Enabled — instructors can use AI quiz generator</span>
                                    </div>
                                </div>
                            </div>
                            <div class="set-card-foot">
                                <button class="btn-primary" data-section="ai">💾 Save AI Settings</button>
                            </div>
                        </div>
                    </div>

                    <!-- ── Maintenance ── -->
                    <div class="set-panel" data-panel="maintenance">
                        <div class="set-card">
                            <div class="set-card-head">
                                <div class="set-card-head-icon red">🔧</div>
                                <div class="set-card-title">
                                    <h3>Maintenance Mode</h3>
                                    <p>Take the system offline for updates or maintenance</p>
                                </div>
                            </div>
                            <div class="set-card-body">
                                <div class="fg">
                                    <label>Maintenance Mode</label>
                                    <div class="toggle-wrap">
                                        <label class="toggle">
                                            <input type="checkbox" id="s-maintenance">
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="toggle-label" id="maint-label">Disabled — system is live</span>
                                    </div>
                                    <div class="fg-hint" style="color:#b45309;">⚠️ Enabling this will prevent all non-admin users from logging in</div>
                                </div>
                                <div class="fg">
                                    <label>Maintenance Message</label>
                                    <textarea class="f-textarea" id="s-maint-msg" rows="4">The system is currently under maintenance. Please check back later.</textarea>
                                    <div class="fg-hint">Shown to users attempting to log in during maintenance</div>
                                </div>
                            </div>
                            <div class="set-card-foot">
                                <span style="font-size:12px;color:#737373;">Changes take effect immediately</span>
                                <button class="btn-danger" data-section="maintenance" id="save-maint">Save Maintenance Settings</button>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    `;

    // ── Nav switching ──
    container.querySelectorAll('.set-nav-item').forEach(item => {
        item.addEventListener('click', () => {
            const sec = item.dataset.section;
            container.querySelectorAll('.set-nav-item').forEach(i => i.classList.remove('active'));
            container.querySelectorAll('.set-panel').forEach(p => p.classList.remove('active'));
            item.classList.add('active');
            container.querySelector(`.set-panel[data-panel="${sec}"]`).classList.add('active');
            activeSection = sec;
        });
    });

    // ── Enrollment radio card highlight ──
    container.querySelectorAll('input[name="s-enrollment"]').forEach(r => {
        r.addEventListener('change', () => {
            container.querySelector('#enroll-open-card').classList.toggle('selected', r.value === '1' && r.checked);
            container.querySelector('#enroll-closed-card').classList.toggle('selected', r.value === '0' && r.checked);
        });
    });

    // ── Number steppers ──
    [
        ['dec-time', 'inc-time', 's-quiz-time', 1, 300],
        ['dec-pass', 'inc-pass', 's-passing-rate', 0, 100],
        ['dec-size', 'inc-size', 's-max-file', 1, 500]
    ].forEach(([decId, incId, inputId, min, max]) => {
        const input = container.querySelector(`#${inputId}`);
        container.querySelector(`#${decId}`).addEventListener('click', () => {
            input.value = Math.max(min, parseInt(input.value) - (inputId === 's-passing-rate' ? 5 : 1));
        });
        container.querySelector(`#${incId}`).addEventListener('click', () => {
            input.value = Math.min(max, parseInt(input.value) + (inputId === 's-passing-rate' ? 5 : 1));
        });
    });

    // ── Toggle labels ──
    function bindToggle(id, labelId, onText, offText) {
        const cb = container.querySelector(`#${id}`);
        const lb = container.querySelector(`#${labelId}`);
        cb.addEventListener('change', () => { lb.textContent = cb.checked ? onText : offText; });
    }
    bindToggle('s-retakes',      'retake-label',  'Enabled — students may retake quizzes',           'Disabled — one attempt per quiz');
    bindToggle('s-show-answers', 'answers-label', 'Enabled — students see answers on result page',   'Disabled — answers hidden after submission');
    bindToggle('s-ai-enabled',   'ai-status-label','Enabled — instructors can use AI quiz generator','Disabled — AI feature hidden from instructors');
    bindToggle('s-maintenance',  'maint-label',   'Enabled — non-admin users cannot log in',         'Disabled — system is live');

    // ── Extension chip preview ──
    function renderChips() {
        const val = container.querySelector('#s-extensions').value;
        const chips = val.split(',').map(e => e.trim()).filter(Boolean);
        container.querySelector('#ext-preview').innerHTML =
            chips.map(c => `<span class="ext-chip">.${c}</span>`).join('');
    }
    container.querySelector('#s-extensions').addEventListener('input', renderChips);
    renderChips();

    // ── Password toggle ──
    container.querySelector('#toggle-key').addEventListener('click', () => {
        const inp = container.querySelector('#s-groq-key');
        inp.type = inp.type === 'password' ? 'text' : 'password';
    });

    // ── Save handlers ──
    container.querySelectorAll('[data-section]').forEach(btn => {
        if (btn.tagName !== 'BUTTON') return;
        btn.addEventListener('click', async () => {
            const orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '⏳ Saving...';
            await new Promise(r => setTimeout(r, 500));
            btn.disabled = false;
            btn.innerHTML = '✅ Saved!';
            showToast('Settings saved successfully!', 'success');
            setTimeout(() => { btn.innerHTML = orig; }, 1800);
        });
    });
}

function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    t.className = `set-toast ${type}`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}
