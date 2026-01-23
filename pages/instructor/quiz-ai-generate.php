<?php
/**
 * CIT-LMS - AI Quiz Generator
 * Generate quiz questions from uploaded PDF using Groq AI
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('instructor');

$userId = Auth::id();
$subjectId = $_GET['subject_id'] ?? null;
$pageTitle = 'AI Quiz Generator';
$currentPage = 'quizzes';

// Get instructor's subjects
$mySubjects = db()->fetchAll(
    "SELECT DISTINCT s.subject_id, s.subject_code, s.subject_name
     FROM faculty_subject fs
     JOIN subject_offered so ON fs.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     WHERE fs.user_teacher_id = ? AND fs.status = 'active'
     ORDER BY s.subject_code",
    [$userId]
) ?: [];

// Get lessons for selected subject
$lessons = [];
if ($subjectId) {
    $lessons = db()->fetchAll(
        "SELECT lesson_id, lesson_title FROM lessons WHERE subject_id = ? AND user_teacher_id = ? ORDER BY lesson_order",
        [$subjectId, $userId]
    ) ?: [];
}

// Get Groq API key and model from settings
$groqApiKey = '';
$aiModel = 'llama-3.1-8b-instant';
$apiKeyConfigured = false;

$settingsRows = db()->fetchAll("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('groq_api_key', 'ai_model')");
foreach ($settingsRows as $row) {
    if ($row['setting_key'] === 'groq_api_key' && !empty($row['setting_value'])) {
        $groqApiKey = $row['setting_value'];
        $apiKeyConfigured = true;
    }
    if ($row['setting_key'] === 'ai_model' && !empty($row['setting_value'])) {
        $aiModel = $row['setting_value'];
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/instructor_sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">
        <div class="ai-header">
            <a href="quizzes.php" class="back-link">← Back to Quizzes</a>
            <h1>🤖 AI Quiz Generator</h1>
            <p>Upload a PDF document and let AI generate quiz questions automatically</p>
        </div>

        <!-- Step 1: Configuration -->
        <div class="ai-panel" id="step1">
            <div class="panel-header">
                <span class="step-num">1</span>
                <h3>Configuration</h3>
            </div>
            <div class="panel-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Subject <span class="req">*</span></label>
                        <select id="subjectSelect" class="form-input" onchange="loadLessons(this.value)">
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($mySubjects as $s): ?>
                            <option value="<?= $s['subject_id'] ?>" <?= $subjectId == $s['subject_id'] ? 'selected' : '' ?>>
                                <?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Link to Lesson (Optional)</label>
                        <select id="lessonSelect" class="form-input">
                            <option value="">Independent Quiz</option>
                            <?php foreach ($lessons as $l): ?>
                            <option value="<?= $l['lesson_id'] ?>"><?= e($l['lesson_title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Quiz Title <span class="req">*</span></label>
                    <input type="text" id="quizTitle" class="form-input" placeholder="e.g. Chapter 1 Assessment">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Quiz Type</label>
                        <select id="quizType" class="form-input">
                            <option value="graded">Graded Quiz</option>
                            <option value="practice">Practice Quiz</option>
                            <option value="pre_test">Pre-Test</option>
                            <option value="post_test">Post-Test</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Groq API Key</label>
                        <?php if ($apiKeyConfigured): ?>
                        <input type="password" id="groqApiKey" class="form-input" value="<?= e($groqApiKey) ?>" readonly style="background: #f0fdf4; border-color: #86efac;">
                        <span class="hint" style="color: #16a34a;">&#10003; API Key configured by administrator</span>
                        <?php else: ?>
                        <input type="password" id="groqApiKey" class="form-input" placeholder="gsk_xxxxx..." value="">
                        <span class="hint">Get free key at <a href="https://console.groq.com/keys" target="_blank">console.groq.com</a></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 2: Upload PDF -->
        <div class="ai-panel" id="step2">
            <div class="panel-header">
                <span class="step-num">2</span>
                <h3>Upload Content</h3>
            </div>
            <div class="panel-body">
                <div class="upload-zone" id="uploadZone">
                    <input type="file" id="pdfInput" accept=".pdf" hidden>
                    <div class="upload-icon">📄</div>
                    <h4>Drop PDF here or click to upload</h4>
                    <p>Maximum 10MB, PDF format only</p>
                </div>
                <div class="file-info" id="fileInfo" style="display: none;">
                    <span class="file-icon">📄</span>
                    <div class="file-details">
                        <span class="file-name" id="fileName"></span>
                        <span class="file-size" id="fileSize"></span>
                    </div>
                    <button type="button" class="btn-remove" onclick="removeFile()">✕</button>
                </div>

                <!-- Extracted Text Preview -->
                <div class="text-preview" id="textPreview" style="display: none;">
                    <label>Extracted Text Preview:</label>
                    <textarea id="extractedText" class="form-input" rows="6" placeholder="Text will appear here after PDF is processed..."></textarea>
                    <span class="hint">You can edit this text before generating questions</span>
                </div>
            </div>
        </div>

        <!-- Step 3: Generation Options -->
        <div class="ai-panel" id="step3">
            <div class="panel-header">
                <span class="step-num">3</span>
                <h3>Question Settings</h3>
            </div>
            <div class="panel-body">
                <h4 class="section-title">Objective Questions (Auto-graded)</h4>
                <div class="form-row thirds">
                    <div class="form-group">
                        <label>Multiple Choice</label>
                        <input type="number" id="numMC" class="form-input" value="5" min="0" max="20">
                    </div>
                    <div class="form-group">
                        <label>True/False</label>
                        <input type="number" id="numTF" class="form-input" value="5" min="0" max="20">
                    </div>
                    <div class="form-group">
                        <label>Fill in the Blank</label>
                        <input type="number" id="numFIB" class="form-input" value="0" min="0" max="10">
                    </div>
                </div>

                <h4 class="section-title">Subjective Questions (Manual grading)</h4>
                <div class="form-row thirds">
                    <div class="form-group">
                        <label>Short Answer</label>
                        <input type="number" id="numSA" class="form-input" value="0" min="0" max="10">
                    </div>
                    <div class="form-group">
                        <label>Essay</label>
                        <input type="number" id="numEssay" class="form-input" value="0" min="0" max="5">
                    </div>
                    <div class="form-group">
                        <label>Difficulty</label>
                        <select id="difficulty" class="form-input">
                            <option value="easy">Easy</option>
                            <option value="medium" selected>Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                </div>

                <button type="button" id="generateBtn" class="btn-generate" onclick="generateQuestions()">
                    🤖 Generate Questions
                </button>
            </div>
        </div>

        <!-- Step 4: Review & Edit Generated Questions -->
        <div class="ai-panel" id="step4" style="display: none;">
            <div class="panel-header">
                <span class="step-num">4</span>
                <h3>Review & Edit Questions</h3>
                <span class="question-count" id="questionCount">0 questions generated</span>
            </div>
            <div class="panel-body">
                <!-- Objective Questions Section -->
                <div class="questions-section">
                    <h4 class="section-title">📝 Objective Questions <span class="badge objective">Auto-graded</span></h4>
                    <div id="objectiveQuestions"></div>
                </div>

                <!-- Subjective Questions Section -->
                <div class="questions-section">
                    <h4 class="section-title">✍️ Subjective Questions <span class="badge subjective">Manual grading</span></h4>
                    <div id="subjectiveQuestions"></div>
                </div>

                <div class="action-bar">
                    <button type="button" class="btn-secondary" onclick="regenerateQuestions()">🔄 Regenerate</button>
                    <button type="button" class="btn-primary" onclick="saveQuiz()">💾 Save Quiz</button>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay" style="display: none;">
            <div class="loading-content">
                <div class="spinner"></div>
                <h3 id="loadingText">Processing...</h3>
                <p id="loadingSubtext">This may take a moment</p>
            </div>
        </div>
    </div>
</main>

<!-- PDF.js Library for reading PDFs -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
// Initialize PDF.js worker
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

let extractedText = '';
let generatedQuestions = { objective: [], subjective: [] };

// File Upload Handling
const uploadZone = document.getElementById('uploadZone');
const pdfInput = document.getElementById('pdfInput');

uploadZone.addEventListener('click', () => pdfInput.click());
uploadZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadZone.classList.add('dragover');
});
uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
uploadZone.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadZone.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        handleFile(e.dataTransfer.files[0]);
    }
});
pdfInput.addEventListener('change', (e) => {
    if (e.target.files.length) handleFile(e.target.files[0]);
});

async function handleFile(file) {
    if (file.type !== 'application/pdf') {
        alert('Please upload a PDF file');
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        alert('File too large. Maximum 10MB allowed.');
        return;
    }

    // Show file info
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = formatFileSize(file.size);
    document.getElementById('uploadZone').style.display = 'none';
    document.getElementById('fileInfo').style.display = 'flex';

    // Extract text from PDF
    showLoading('Extracting text from PDF...', 'Reading document contents');
    try {
        extractedText = await extractTextFromPDF(file);
        document.getElementById('extractedText').value = extractedText;
        document.getElementById('textPreview').style.display = 'block';
        hideLoading();
    } catch (err) {
        hideLoading();
        alert('Error reading PDF: ' + err.message);
    }
}

async function extractTextFromPDF(file) {
    const arrayBuffer = await file.arrayBuffer();
    const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
    let fullText = '';

    for (let i = 1; i <= pdf.numPages; i++) {
        const page = await pdf.getPage(i);
        const textContent = await page.getTextContent();
        const pageText = textContent.items.map(item => item.str).join(' ');
        fullText += pageText + '\n\n';
    }

    return fullText.trim();
}

function removeFile() {
    pdfInput.value = '';
    extractedText = '';
    document.getElementById('uploadZone').style.display = 'block';
    document.getElementById('fileInfo').style.display = 'none';
    document.getElementById('textPreview').style.display = 'none';
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

// Load lessons when subject changes
function loadLessons(subjectId) {
    if (!subjectId) {
        document.getElementById('lessonSelect').innerHTML = '<option value="">Independent Quiz</option>';
        return;
    }
    window.location.href = 'quiz-ai-generate.php?subject_id=' + subjectId;
}

// Generate Questions using Groq API
async function generateQuestions() {
    const apiKey = document.getElementById('groqApiKey').value.trim();
    const text = document.getElementById('extractedText').value.trim();
    const subjectId = document.getElementById('subjectSelect').value;
    const quizTitle = document.getElementById('quizTitle').value.trim();

    if (!apiKey) {
        alert('Please enter your Groq API key');
        return;
    }
    if (!text) {
        alert('Please upload a PDF first');
        return;
    }
    if (!subjectId) {
        alert('Please select a subject');
        return;
    }
    if (!quizTitle) {
        alert('Please enter a quiz title');
        return;
    }

    const numMC = parseInt(document.getElementById('numMC').value) || 0;
    const numTF = parseInt(document.getElementById('numTF').value) || 0;
    const numFIB = parseInt(document.getElementById('numFIB').value) || 0;
    const numSA = parseInt(document.getElementById('numSA').value) || 0;
    const numEssay = parseInt(document.getElementById('numEssay').value) || 0;
    const difficulty = document.getElementById('difficulty').value;

    if (numMC + numTF + numFIB + numSA + numEssay === 0) {
        alert('Please specify at least one question type');
        return;
    }

    showLoading('Generating questions with AI...', 'This may take 30-60 seconds');

    try {
        // Call our PHP API endpoint that handles Hugging Face
        const response = await fetch('<?= BASE_URL ?>/api/AIQuizAPI.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'generate',
                api_key: apiKey,
                text: text.substring(0, 8000), // Limit text length
                num_mc: numMC,
                num_tf: numTF,
                num_fib: numFIB,
                num_sa: numSA,
                num_essay: numEssay,
                difficulty: difficulty
            })
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to generate questions');
        }

        generatedQuestions = result.questions;
        displayGeneratedQuestions();
        hideLoading();

    } catch (err) {
        hideLoading();
        alert('Error: ' + err.message);
    }
}

function displayGeneratedQuestions() {
    const objectiveContainer = document.getElementById('objectiveQuestions');
    const subjectiveContainer = document.getElementById('subjectiveQuestions');

    objectiveContainer.innerHTML = '';
    subjectiveContainer.innerHTML = '';

    let totalQuestions = 0;

    // Display Objective Questions
    generatedQuestions.objective.forEach((q, idx) => {
        totalQuestions++;
        objectiveContainer.innerHTML += createQuestionCard(q, idx, 'objective');
    });

    // Display Subjective Questions
    generatedQuestions.subjective.forEach((q, idx) => {
        totalQuestions++;
        subjectiveContainer.innerHTML += createQuestionCard(q, idx, 'subjective');
    });

    document.getElementById('questionCount').textContent = totalQuestions + ' questions generated';
    document.getElementById('step4').style.display = 'block';
    document.getElementById('step4').scrollIntoView({ behavior: 'smooth' });
}

function createQuestionCard(question, index, category) {
    const typeLabels = {
        'multiple_choice': 'Multiple Choice',
        'true_false': 'True/False',
        'fill_blank': 'Fill in the Blank',
        'short_answer': 'Short Answer',
        'essay': 'Essay'
    };

    let optionsHtml = '';
    if (question.type === 'multiple_choice' && question.options) {
        optionsHtml = '<div class="options-editor">';
        question.options.forEach((opt, i) => {
            const isCorrect = i === question.correct_index;
            optionsHtml += `
                <div class="option-row">
                    <input type="radio" name="${category}_${index}_correct" ${isCorrect ? 'checked' : ''}
                           onchange="updateCorrectAnswer('${category}', ${index}, ${i})">
                    <input type="text" class="option-input" value="${escapeHtml(opt)}"
                           onchange="updateOption('${category}', ${index}, ${i}, this.value)">
                    <span class="option-label">${isCorrect ? '✓ Correct' : ''}</span>
                </div>
            `;
        });
        optionsHtml += '</div>';
    } else if (question.type === 'true_false') {
        optionsHtml = `
            <div class="tf-editor">
                <label><input type="radio" name="${category}_${index}_tf" ${question.answer === true ? 'checked' : ''}
                       onchange="updateTFAnswer('${category}', ${index}, true)"> True</label>
                <label><input type="radio" name="${category}_${index}_tf" ${question.answer === false ? 'checked' : ''}
                       onchange="updateTFAnswer('${category}', ${index}, false)"> False</label>
            </div>
        `;
    } else if (question.type === 'fill_blank') {
        optionsHtml = `
            <div class="answer-editor">
                <label>Correct Answer:</label>
                <input type="text" class="form-input" value="${escapeHtml(question.answer || '')}"
                       onchange="updateAnswer('${category}', ${index}, this.value)">
            </div>
        `;
    }

    return `
        <div class="question-card" data-category="${category}" data-index="${index}">
            <div class="question-header">
                <span class="question-type">${typeLabels[question.type] || question.type}</span>
                <span class="question-points">
                    <input type="number" class="points-input" value="${question.points || 1}" min="1" max="10"
                           onchange="updatePoints('${category}', ${index}, this.value)"> pts
                </span>
                <button type="button" class="btn-delete" onclick="deleteQuestion('${category}', ${index})">🗑️</button>
            </div>
            <div class="question-body">
                <textarea class="question-text" rows="2"
                          onchange="updateQuestionText('${category}', ${index}, this.value)">${escapeHtml(question.question)}</textarea>
                ${optionsHtml}
            </div>
        </div>
    `;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Update functions for editing questions
function updateQuestionText(category, index, value) {
    generatedQuestions[category][index].question = value;
}

function updateOption(category, index, optIndex, value) {
    generatedQuestions[category][index].options[optIndex] = value;
}

function updateCorrectAnswer(category, index, correctIndex) {
    generatedQuestions[category][index].correct_index = correctIndex;
}

function updateTFAnswer(category, index, value) {
    generatedQuestions[category][index].answer = value;
}

function updateAnswer(category, index, value) {
    generatedQuestions[category][index].answer = value;
}

function updatePoints(category, index, value) {
    generatedQuestions[category][index].points = parseInt(value) || 1;
}

function deleteQuestion(category, index) {
    if (confirm('Delete this question?')) {
        generatedQuestions[category].splice(index, 1);
        displayGeneratedQuestions();
    }
}

function regenerateQuestions() {
    if (confirm('This will replace all current questions. Continue?')) {
        generateQuestions();
    }
}

// Save Quiz to Database
async function saveQuiz() {
    const subjectId = document.getElementById('subjectSelect').value;
    const lessonId = document.getElementById('lessonSelect').value;
    const quizTitle = document.getElementById('quizTitle').value.trim();
    const quizType = document.getElementById('quizType').value;

    const totalQuestions = generatedQuestions.objective.length + generatedQuestions.subjective.length;
    if (totalQuestions === 0) {
        alert('No questions to save');
        return;
    }

    showLoading('Saving quiz...', 'Creating quiz and questions');

    try {
        const response = await fetch('<?= BASE_URL ?>/api/AIQuizAPI.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save',
                subject_id: subjectId,
                lesson_id: lessonId || null,
                quiz_title: quizTitle,
                quiz_type: quizType,
                questions: generatedQuestions
            })
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to save quiz');
        }

        hideLoading();
        alert('Quiz saved successfully!');
        window.location.href = 'quiz-questions.php?quiz_id=' + result.quiz_id;

    } catch (err) {
        hideLoading();
        alert('Error: ' + err.message);
    }
}

// Loading overlay functions
function showLoading(text, subtext) {
    document.getElementById('loadingText').textContent = text;
    document.getElementById('loadingSubtext').textContent = subtext;
    document.getElementById('loadingOverlay').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
}
</script>

<style>
/* AI Quiz Generator Styles */
.ai-header {
    background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
    padding: 28px;
    border-radius: 16px;
    color: #fff;
    margin-bottom: 24px;
}
.ai-header .back-link {
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    font-size: 13px;
    display: block;
    margin-bottom: 8px;
}
.ai-header .back-link:hover { color: #fff; }
.ai-header h1 { font-size: 26px; margin: 0 0 8px; }
.ai-header p { margin: 0; opacity: 0.9; font-size: 14px; }

.ai-panel {
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 14px;
    margin-bottom: 20px;
    overflow: hidden;
}
.panel-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 24px;
    background: #fafafa;
    border-bottom: 1px solid #e5e5e5;
}
.step-num {
    width: 32px;
    height: 32px;
    background: #8b5cf6;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
}
.panel-header h3 { margin: 0; font-size: 16px; color: #1a1a1a; flex: 1; }
.question-count { font-size: 13px; color: #666; }
.panel-body { padding: 24px; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.form-row.thirds { grid-template-columns: repeat(3, 1fr); }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 6px; }
.form-input {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.2s;
}
.form-input:focus { outline: none; border-color: #8b5cf6; }
.hint { font-size: 12px; color: #888; margin-top: 4px; display: block; }
.hint a { color: #8b5cf6; }
.req { color: #ef4444; }

.section-title {
    font-size: 14px;
    color: #333;
    margin: 20px 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid #f0f0f0;
}
.section-title:first-child { margin-top: 0; }

/* Upload Zone */
.upload-zone {
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}
.upload-zone:hover, .upload-zone.dragover {
    border-color: #8b5cf6;
    background: #faf5ff;
}
.upload-icon { font-size: 48px; margin-bottom: 12px; }
.upload-zone h4 { margin: 0 0 8px; color: #333; }
.upload-zone p { margin: 0; color: #888; font-size: 13px; }

.file-info {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 10px;
}
.file-icon { font-size: 32px; }
.file-details { flex: 1; }
.file-name { display: block; font-weight: 600; color: #333; }
.file-size { font-size: 13px; color: #666; }
.btn-remove {
    width: 32px;
    height: 32px;
    border: none;
    background: #fee2e2;
    color: #dc2626;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
}

.text-preview { margin-top: 20px; }
.text-preview label { display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 8px; }

/* Buttons */
.btn-generate {
    display: block;
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}
.btn-generate:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3);
}

.btn-primary {
    padding: 12px 28px;
    background: #16a34a;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
}
.btn-secondary {
    padding: 12px 28px;
    background: #fff;
    color: #333;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
}

/* Questions Section */
.questions-section { margin-bottom: 24px; }
.badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
}
.badge.objective { background: #dbeafe; color: #1d4ed8; }
.badge.subjective { background: #fef3c7; color: #d97706; }

.question-card {
    background: #fafafa;
    border: 1px solid #e5e5e5;
    border-radius: 10px;
    margin-bottom: 14px;
    overflow: hidden;
}
.question-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: #f5f5f5;
    border-bottom: 1px solid #e5e5e5;
}
.question-type {
    font-size: 12px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
}
.question-points {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
    color: #666;
}
.points-input {
    width: 50px;
    padding: 4px 8px;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
    text-align: center;
}
.btn-delete {
    background: none;
    border: none;
    cursor: pointer;
    opacity: 0.5;
    font-size: 16px;
}
.btn-delete:hover { opacity: 1; }

.question-body { padding: 16px; }
.question-text {
    width: 100%;
    padding: 10px;
    border: 1px solid #e5e5e5;
    border-radius: 6px;
    font-size: 14px;
    resize: vertical;
    margin-bottom: 12px;
}

.options-editor { margin-top: 12px; }
.option-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}
.option-row input[type="radio"] { flex-shrink: 0; }
.option-input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #e5e5e5;
    border-radius: 6px;
    font-size: 14px;
}
.option-label {
    width: 80px;
    font-size: 12px;
    color: #16a34a;
    font-weight: 600;
}

.tf-editor {
    display: flex;
    gap: 24px;
    margin-top: 12px;
}
.tf-editor label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    cursor: pointer;
}

.answer-editor { margin-top: 12px; }
.answer-editor label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-bottom: 6px;
}

.action-bar {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 20px;
    border-top: 1px solid #e5e5e5;
}

/* Loading Overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}
.loading-content {
    background: #fff;
    padding: 40px 60px;
    border-radius: 16px;
    text-align: center;
}
.spinner {
    width: 48px;
    height: 48px;
    border: 4px solid #e5e5e5;
    border-top-color: #8b5cf6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}
@keyframes spin { to { transform: rotate(360deg); } }
.loading-content h3 { margin: 0 0 8px; color: #333; }
.loading-content p { margin: 0; color: #666; font-size: 14px; }

/* Responsive */
@media (max-width: 768px) {
    .form-row, .form-row.thirds { grid-template-columns: 1fr; }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
