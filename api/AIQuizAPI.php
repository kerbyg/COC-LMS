<?php
/**
 * CIT-LMS - AI Quiz Generation API
 * Handles Groq API calls for generating quiz questions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');

// Require instructor role
if (!Auth::check() || Auth::role() !== 'instructor') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = Auth::id();
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? $input['action'] ?? '';

switch ($action) {
    case 'generate':
        generateQuestions($input);
        break;
    case 'save':
        saveQuiz($input, $userId);
        break;
    case 'subjects':
        getInstructorSubjects($userId);
        break;
    case 'lessons':
        getSubjectLessons($userId);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function getInstructorSubjects($userId) {
    $data = db()->fetchAll(
        "SELECT DISTINCT s.subject_id, s.subject_code, s.subject_name
         FROM subject s
         JOIN subject_offered so ON s.subject_id = so.subject_id
         WHERE so.user_teacher_id = ?
         ORDER BY s.subject_code",
        [$userId]
    );
    echo json_encode(['success' => true, 'data' => $data]);
}

function getSubjectLessons($userId) {
    $subjectId = $_GET['subject_id'] ?? 0;
    if (!$subjectId) {
        echo json_encode(['success' => true, 'data' => []]);
        return;
    }
    $data = db()->fetchAll(
        "SELECT lessons_id, lesson_title FROM lessons
         WHERE subject_id = ? AND user_teacher_id = ? AND status = 'published'
         ORDER BY lesson_order",
        [$subjectId, $userId]
    );
    echo json_encode(['success' => true, 'data' => $data]);
}

/**
 * Generate questions using Groq API
 */
function generateQuestions($input) {
    $text = $input['text'] ?? '';
    $numMC = (int)($input['num_mc'] ?? 5);
    $numTF = (int)($input['num_tf'] ?? 5);
    $numFIB = (int)($input['num_fib'] ?? 0);
    $numSA = (int)($input['num_sa'] ?? 0);
    $numEssay = (int)($input['num_essay'] ?? 0);
    $difficulty = $input['difficulty'] ?? 'medium';

    // Read API key from system_settings
    $apiKey = '';
    $keySetting = db()->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'groq_api_key'");
    if ($keySetting && !empty($keySetting['setting_value'])) {
        $apiKey = $keySetting['setting_value'];
    }

    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'error' => 'Groq API key not configured. Please set it in System Settings.']);
        return;
    }
    if (empty($text)) {
        echo json_encode(['success' => false, 'error' => 'Content text is required']);
        return;
    }

    // Truncate text if too long
    $text = substr($text, 0, 8000);

    // Build the prompt for question generation
    $prompt = buildPrompt($text, $numMC, $numTF, $numFIB, $numSA, $numEssay, $difficulty);

    try {
        // Call Groq API
        $response = callGroqAPI($apiKey, $prompt);

        if (!$response['success']) {
            echo json_encode(['success' => false, 'error' => $response['error']]);
            return;
        }

        // Parse AI response into structured questions
        $questions = parseAIResponse($response['text'], $numMC, $numTF, $numFIB, $numSA, $numEssay);

        echo json_encode([
            'success' => true,
            'questions' => $questions
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'AI Error: ' . $e->getMessage()]);
    }
}

/**
 * Build prompt for AI question generation
 */
function buildPrompt($text, $numMC, $numTF, $numFIB, $numSA, $numEssay, $difficulty) {
    $difficultyDesc = [
        'easy' => 'basic recall and simple understanding',
        'medium' => 'application and analysis',
        'hard' => 'critical thinking and evaluation'
    ];
    $diffLevel = $difficultyDesc[$difficulty] ?? $difficultyDesc['medium'];

    $prompt = "You are an educational quiz generator. Based on the following educational content, generate quiz questions.

CONTENT:
\"\"\"
{$text}
\"\"\"

Generate the following questions (difficulty level: {$diffLevel}):

";

    if ($numMC > 0) {
        $prompt .= "MULTIPLE CHOICE ({$numMC} questions):
Format each as:
MC[number]: [question]
A) [option]
B) [option]
C) [option]
D) [option]
ANSWER: [letter]

";
    }

    if ($numTF > 0) {
        $prompt .= "TRUE/FALSE ({$numTF} questions):
Format each as:
TF[number]: [statement]
ANSWER: [True/False]

";
    }

    if ($numFIB > 0) {
        $prompt .= "FILL IN THE BLANK ({$numFIB} questions):
Format each as:
FIB[number]: [sentence with _____ for blank]
ANSWER: [correct word/phrase]

";
    }

    if ($numSA > 0) {
        $prompt .= "SHORT ANSWER ({$numSA} questions):
Format each as:
SA[number]: [question requiring 1-2 sentence answer]

";
    }

    if ($numEssay > 0) {
        $prompt .= "ESSAY ({$numEssay} questions):
Format each as:
ESSAY[number]: [open-ended question requiring detailed response]

";
    }

    $prompt .= "Generate questions now:";

    return $prompt;
}

/**
 * Call Groq API for text generation
 * Groq offers fast inference with generous free tier
 */
function callGroqAPI($apiKey, $prompt) {
    // Get model from settings or use default
    $model = 'llama-3.1-8b-instant';
    $modelSetting = db()->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'ai_model'");
    if ($modelSetting && !empty($modelSetting['setting_value'])) {
        $model = $modelSetting['setting_value'];
    }

    $url = "https://api.groq.com/openai/v1/chat/completions";

    $payload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are an educational quiz generator. Generate well-formatted quiz questions based on the provided content. Follow the exact format specified in the user prompt.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens' => 4000,
        'temperature' => 0.7
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 120
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => 'Connection error: ' . $error];
    }

    $data = json_decode($response, true);

    if ($httpCode === 401) {
        return ['success' => false, 'error' => 'Invalid API key. Please check your Groq API key.'];
    }

    if ($httpCode === 429) {
        return ['success' => false, 'error' => 'Rate limit exceeded. Please wait a moment and try again.'];
    }

    if ($httpCode !== 200) {
        $errorMsg = $data['error']['message'] ?? $data['error'] ?? 'Unknown API error (HTTP ' . $httpCode . ')';
        return ['success' => false, 'error' => $errorMsg];
    }

    // Extract generated text from chat completion response
    if (isset($data['choices'][0]['message']['content'])) {
        return ['success' => true, 'text' => $data['choices'][0]['message']['content']];
    }

    return ['success' => false, 'error' => 'Unexpected API response format'];
}


/**
 * Parse AI response into structured questions
 */
function parseAIResponse($text, $numMC, $numTF, $numFIB, $numSA, $numEssay) {
    $objective = [];
    $subjective = [];

    // Parse Multiple Choice questions
    preg_match_all('/MC\d*[:\.]?\s*(.+?)\n\s*A\)\s*(.+?)\n\s*B\)\s*(.+?)\n\s*C\)\s*(.+?)\n\s*D\)\s*(.+?)\n\s*ANSWER:\s*([A-D])/is', $text, $mcMatches, PREG_SET_ORDER);

    foreach (array_slice($mcMatches, 0, $numMC) as $match) {
        $correctIndex = ord(strtoupper(trim($match[6]))) - ord('A');
        $objective[] = [
            'type' => 'multiple_choice',
            'question' => trim($match[1]),
            'options' => [trim($match[2]), trim($match[3]), trim($match[4]), trim($match[5])],
            'correct_index' => $correctIndex,
            'points' => 2
        ];
    }

    // Parse True/False questions
    preg_match_all('/TF\d*[:\.]?\s*(.+?)\n\s*ANSWER:\s*(True|False)/is', $text, $tfMatches, PREG_SET_ORDER);

    foreach (array_slice($tfMatches, 0, $numTF) as $match) {
        $objective[] = [
            'type' => 'true_false',
            'question' => trim($match[1]),
            'answer' => strtolower(trim($match[2])) === 'true',
            'points' => 1
        ];
    }

    // Parse Fill in the Blank questions
    preg_match_all('/FIB\d*[:\.]?\s*(.+?)\n\s*ANSWER:\s*(.+?)(?=\n|$)/is', $text, $fibMatches, PREG_SET_ORDER);

    foreach (array_slice($fibMatches, 0, $numFIB) as $match) {
        $objective[] = [
            'type' => 'fill_blank',
            'question' => trim($match[1]),
            'answer' => trim($match[2]),
            'points' => 2
        ];
    }

    // Parse Short Answer questions
    preg_match_all('/SA\d*[:\.]?\s*(.+?)(?=\n(?:SA|ESSAY|$)|\n\n|$)/is', $text, $saMatches, PREG_SET_ORDER);

    foreach (array_slice($saMatches, 0, $numSA) as $match) {
        $subjective[] = [
            'type' => 'short_answer',
            'question' => trim($match[1]),
            'points' => 3
        ];
    }

    // Parse Essay questions
    preg_match_all('/ESSAY\d*[:\.]?\s*(.+?)(?=\n(?:ESSAY|$)|\n\n|$)/is', $text, $essayMatches, PREG_SET_ORDER);

    foreach (array_slice($essayMatches, 0, $numEssay) as $match) {
        $subjective[] = [
            'type' => 'essay',
            'question' => trim($match[1]),
            'points' => 5
        ];
    }

    // If parsing didn't find enough questions, generate fallback questions
    $objective = fillMissingQuestions($objective, 'objective', $numMC, $numTF, $numFIB);
    $subjective = fillMissingQuestions($subjective, 'subjective', $numSA, $numEssay, 0);

    return [
        'objective' => $objective,
        'subjective' => $subjective
    ];
}

/**
 * Fill in missing questions if AI didn't generate enough
 */
function fillMissingQuestions($questions, $category, $num1, $num2, $num3) {
    $currentCount = count($questions);
    $targetCount = $num1 + $num2 + $num3;

    if ($category === 'objective') {
        // Add placeholder MC questions if needed
        while (count($questions) < $targetCount && count($questions) < $num1) {
            $questions[] = [
                'type' => 'multiple_choice',
                'question' => 'Question ' . (count($questions) + 1) . ': [Edit this question]',
                'options' => ['Option A', 'Option B', 'Option C', 'Option D'],
                'correct_index' => 0,
                'points' => 2
            ];
        }
        // Add placeholder TF questions if needed
        $tfCount = count(array_filter($questions, fn($q) => $q['type'] === 'true_false'));
        while ($tfCount < $num2) {
            $questions[] = [
                'type' => 'true_false',
                'question' => 'True/False: [Edit this statement]',
                'answer' => true,
                'points' => 1
            ];
            $tfCount++;
        }
    } else {
        // Add placeholder SA questions if needed
        $saCount = count(array_filter($questions, fn($q) => $q['type'] === 'short_answer'));
        while ($saCount < $num1) {
            $questions[] = [
                'type' => 'short_answer',
                'question' => 'Short Answer: [Edit this question]',
                'points' => 3
            ];
            $saCount++;
        }
        // Add placeholder essay questions if needed
        $essayCount = count(array_filter($questions, fn($q) => $q['type'] === 'essay'));
        while ($essayCount < $num2) {
            $questions[] = [
                'type' => 'essay',
                'question' => 'Essay: [Edit this question]',
                'points' => 5
            ];
            $essayCount++;
        }
    }

    return $questions;
}

/**
 * Save generated quiz to database
 */
function saveQuiz($input, $userId) {
    $subjectId = (int)($input['subject_id'] ?? 0);
    $lessonId = !empty($input['lessons_id']) ? (int)$input['lessons_id'] : null;
    $quizTitle = trim($input['quiz_title'] ?? '');
    $quizType = $input['quiz_type'] ?? 'graded';
    $questions = $input['questions'] ?? ['objective' => [], 'subjective' => []];

    if (!$subjectId || !$quizTitle) {
        echo json_encode(['success' => false, 'error' => 'Subject and quiz title are required']);
        return;
    }

    $allQuestions = array_merge($questions['objective'] ?? [], $questions['subjective'] ?? []);
    if (empty($allQuestions)) {
        echo json_encode(['success' => false, 'error' => 'No questions to save']);
        return;
    }

    // Calculate total points
    $totalPoints = array_sum(array_column($allQuestions, 'points'));

    try {
        $pdo = pdo();
        $pdo->beginTransaction();

        // Insert quiz
        $stmt = $pdo->prepare("INSERT INTO quiz (user_teacher_id, subject_id, quiz_title, quiz_description, time_limit, passing_rate, max_attempts, total_points, status, quiz_type, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$userId, $subjectId, $quizTitle, 'Generated by AI', 30, 60, ($quizType === 'pre_test' ? 1 : 3), $totalPoints, 'draft', $quizType]);
        $quizId = $pdo->lastInsertId();

        if (!$quizId) {
            throw new Exception('Failed to create quiz');
        }

        // Link quiz to lesson via junction table (if table exists)
        if ($lessonId) {
            try {
                $pdo->prepare("INSERT INTO quiz_lessons (quiz_id, lessons_id) VALUES (?, ?)")->execute([$quizId, $lessonId]);
            } catch (Exception $e) {
                // quiz_lessons table may not exist, skip
            }
        }

        // Insert questions into `questions` table, then link via `quiz_questions`
        $pdo = pdo();
        $orderNum = 1;
        foreach ($allQuestions as $q) {
            $questionType = mapQuestionType($q['type']);

            // 1. Insert into `questions` master table
            $stmt = $pdo->prepare("INSERT INTO questions (question_text, question_type, points, question_order, users_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$q['question'], $questionType, $q['points'] ?? 1, $orderNum, $userId]);
            $questionId = $pdo->lastInsertId();

            // 2. Link to quiz via junction table
            $stmt = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, questions_id) VALUES (?, ?)");
            $stmt->execute([$quizId, $questionId]);

            // 3. Insert options (quiz_question_id references questions.questions_id)
            if ($q['type'] === 'multiple_choice' && !empty($q['options'])) {
                foreach ($q['options'] as $idx => $optText) {
                    $isCorrect = ($idx === ($q['correct_index'] ?? 0)) ? 1 : 0;
                    $pdo->prepare("INSERT INTO question_option (quiz_question_id, option_text, is_correct, order_number) VALUES (?, ?, ?, ?)")
                        ->execute([$questionId, $optText, $isCorrect, $idx + 1]);
                }
            }
            elseif ($q['type'] === 'true_false') {
                $answer = $q['answer'] ?? true;
                $pdo->prepare("INSERT INTO question_option (quiz_question_id, option_text, is_correct, order_number) VALUES (?, 'True', ?, 1)")
                    ->execute([$questionId, $answer ? 1 : 0]);
                $pdo->prepare("INSERT INTO question_option (quiz_question_id, option_text, is_correct, order_number) VALUES (?, 'False', ?, 2)")
                    ->execute([$questionId, $answer ? 0 : 1]);
            }
            elseif ($q['type'] === 'fill_blank' && !empty($q['answer'])) {
                $pdo->prepare("INSERT INTO question_option (quiz_question_id, option_text, is_correct, order_number) VALUES (?, ?, 1, 1)")
                    ->execute([$questionId, $q['answer']]);
            }

            $orderNum++;
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'quiz_id' => $quizId,
            'message' => 'Quiz saved successfully with ' . count($allQuestions) . ' questions'
        ]);

    } catch (Exception $e) {
        pdo()->rollBack();
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Map internal question types to database types
 */
function mapQuestionType($type) {
    $map = [
        'multiple_choice' => 'multiple_choice',
        'true_false' => 'true_false',
        'fill_blank' => 'fill_blank',
        'short_answer' => 'short_answer',
        'essay' => 'essay'
    ];
    return $map[$type] ?? 'multiple_choice';
}
