<?php
/**
 * Student Enrollment Page - Clean Green Theme
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();
$pageTitle = 'Enroll in Section';
$currentPage = 'enroll';

$successMessage = '';
$errorMessage = '';

// Handle enrollment code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enrollment_code'])) {
    $enrollmentCode = strtoupper(trim($_POST['enrollment_code']));

    try {
        $section = db()->fetchOne(
            "SELECT sec.*,
                so.subject_id,
                so.subject_offered_id,
                so.academic_year,
                so.semester as offering_semester,
                s.subject_code,
                s.subject_name,
                s.units,
                CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
                (SELECT COUNT(*) FROM student_subject ss WHERE ss.section_id = sec.section_id AND ss.status = 'enrolled') as current_enrollment
             FROM section sec
             JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
             JOIN subject s ON so.subject_id = s.subject_id
             LEFT JOIN faculty_subject fs ON sec.section_id = fs.section_id AND fs.status = 'active'
             LEFT JOIN users u ON fs.user_teacher_id = u.users_id
             WHERE sec.enrollment_code = ? AND sec.status = 'active'
             LIMIT 1",
            [$enrollmentCode]
        );

        if (!$section) {
            $errorMessage = "Invalid enrollment code. Please check and try again.";
        } else {
            if ($section['current_enrollment'] >= $section['max_students']) {
                $errorMessage = "This section is full. Please contact your instructor.";
            } else {
                $existingEnrollment = db()->fetchOne(
                    "SELECT student_subject_id FROM student_subject
                     WHERE user_student_id = ? AND section_id = ?",
                    [$userId, $section['section_id']]
                );

                if ($existingEnrollment) {
                    $errorMessage = "You are already enrolled in this section.";
                } else {
                    db()->execute(
                        "INSERT INTO student_subject (user_student_id, subject_offered_id, section_id, status, enrollment_date, updated_at)
                         VALUES (?, ?, ?, 'enrolled', NOW(), NOW())",
                        [$userId, $section['subject_offered_id'], $section['section_id']]
                    );

                    $successMessage = "Successfully enrolled in {$section['subject_code']} - {$section['subject_name']} (Section {$section['section_name']})";
                }
            }
        }
    } catch (Exception $e) {
        $errorMessage = "Enrollment failed: " . $e->getMessage();
    }
}

// Get student's current enrollments
try {
    $enrolledSections = db()->fetchAll(
        "SELECT
            s.subject_code,
            s.subject_name,
            sec.section_name,
            sec.schedule,
            sec.room,
            sec.enrollment_code,
            CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
            ss.enrollment_date
         FROM student_subject ss
         JOIN section sec ON ss.section_id = sec.section_id
         JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
         JOIN subject s ON so.subject_id = s.subject_id
         LEFT JOIN faculty_subject fs ON sec.section_id = fs.section_id AND fs.status = 'active'
         LEFT JOIN users u ON fs.user_teacher_id = u.users_id
         WHERE ss.user_student_id = ? AND ss.status = 'enrolled'
         ORDER BY ss.enrollment_date DESC",
        [$userId]
    );
} catch (Exception $e) {
    $enrolledSections = [];
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="enroll-wrap">

        <?php if ($successMessage): ?>
        <div class="alert alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4"/>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
            <?= htmlspecialchars($successMessage) ?>
        </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
        <div class="alert alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
            <?= htmlspecialchars($errorMessage) ?>
        </div>
        <?php endif; ?>

        <!-- Enrollment Form -->
        <div class="enroll-card">
            <div class="enroll-header">
                <div class="enroll-icon">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                        <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                    </svg>
                </div>
                <h1>Enroll in Section</h1>
                <p>Enter the enrollment code from your instructor</p>
            </div>

            <form method="POST" class="enroll-form">
                <div class="input-group">
                    <input
                        type="text"
                        name="enrollment_code"
                        class="code-input"
                        placeholder="ABC-1234"
                        maxlength="8"
                        required
                        autocomplete="off"
                    >
                    <span class="input-hint">Format: XXX-9999</span>
                </div>
                <button type="submit" class="btn-enroll">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                    Enroll Now
                </button>
            </form>
        </div>

        <!-- Enrolled Sections -->
        <?php if (!empty($enrolledSections)): ?>
        <div class="enrolled-section">
            <h2>Your Enrolled Sections</h2>
            <div class="sections-grid">
                <?php foreach ($enrolledSections as $enrolled): ?>
                <div class="section-card">
                    <div class="card-badges">
                        <span class="badge badge-code"><?= htmlspecialchars($enrolled['subject_code']) ?></span>
                        <span class="badge badge-section"><?= htmlspecialchars($enrolled['section_name']) ?></span>
                    </div>
                    <h3><?= htmlspecialchars($enrolled['subject_name']) ?></h3>
                    <div class="card-details">
                        <div class="detail">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            <span><?= htmlspecialchars($enrolled['instructor_name'] ?: 'TBA') ?></span>
                        </div>
                        <?php if ($enrolled['schedule']): ?>
                        <div class="detail">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            <span><?= htmlspecialchars($enrolled['schedule']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($enrolled['room']): ?>
                        <div class="detail">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                <polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                            <span><?= htmlspecialchars($enrolled['room']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        Enrolled <?= date('M j, Y', strtotime($enrolled['enrollment_date'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
            </svg>
            <h3>No Enrollments Yet</h3>
            <p>Enter an enrollment code above to join a section</p>
        </div>
        <?php endif; ?>

    </div>
</main>

<style>
/* Enroll Page - Green/Cream Theme */
.enroll-wrap {
    padding: 24px;
    max-width: 1000px;
    margin: 0 auto;
}

/* Alerts */
.alert {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
    font-weight: 500;
}

.alert-success {
    background: #E8F5E9;
    color: #1B4D3E;
    border: 1px solid #A5D6A7;
}

.alert-error {
    background: #FFEBEE;
    color: #C62828;
    border: 1px solid #EF9A9A;
}

/* Enrollment Card */
.enroll-card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 16px;
    padding: 40px;
    text-align: center;
    margin-bottom: 32px;
}

.enroll-header {
    margin-bottom: 32px;
}

.enroll-icon {
    width: 64px;
    height: 64px;
    background: #E8F5E9;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    color: #1B4D3E;
}

.enroll-header h1 {
    font-size: 24px;
    font-weight: 700;
    color: #1B4D3E;
    margin: 0 0 8px;
}

.enroll-header p {
    font-size: 14px;
    color: #666;
    margin: 0;
}

/* Form */
.enroll-form {
    max-width: 360px;
    margin: 0 auto;
}

.input-group {
    margin-bottom: 20px;
}

.code-input {
    width: 100%;
    padding: 16px 20px;
    font-size: 24px;
    font-weight: 600;
    text-align: center;
    border: 2px solid #e8e8e8;
    border-radius: 10px;
    font-family: 'Courier New', monospace;
    letter-spacing: 3px;
    text-transform: uppercase;
    transition: all 0.2s ease;
    background: #fafafa;
}

.code-input:focus {
    outline: none;
    border-color: #1B4D3E;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(27, 77, 62, 0.1);
}

.code-input::placeholder {
    color: #ccc;
    letter-spacing: 2px;
}

.input-hint {
    display: block;
    margin-top: 8px;
    font-size: 12px;
    color: #999;
}

.btn-enroll {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 24px;
    background: #1B4D3E;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-enroll:hover {
    background: #2D6A4F;
    transform: translateY(-1px);
}

/* Enrolled Sections */
.enrolled-section {
    margin-top: 32px;
}

.enrolled-section h2 {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin: 0 0 20px;
}

.sections-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
}

.section-card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.2s ease;
}

.section-card:hover {
    border-color: #1B4D3E;
    box-shadow: 0 4px 12px rgba(27, 77, 62, 0.1);
}

.card-badges {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
}

.badge {
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

.badge-code {
    background: #1B4D3E;
    color: #fff;
}

.badge-section {
    background: #E8F5E9;
    color: #1B4D3E;
}

.section-card h3 {
    font-size: 15px;
    font-weight: 600;
    color: #333;
    margin: 0 0 16px;
    line-height: 1.4;
}

.card-details {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 16px;
}

.detail {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: #666;
}

.detail svg {
    color: #1B4D3E;
    flex-shrink: 0;
}

.card-footer {
    padding-top: 12px;
    border-top: 1px solid #f0f0f0;
    font-size: 12px;
    color: #999;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 48px 24px;
    background: #fafafa;
    border: 1px dashed #ddd;
    border-radius: 12px;
    margin-top: 32px;
}

.empty-state svg {
    color: #ccc;
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin: 0 0 8px;
}

.empty-state p {
    font-size: 14px;
    color: #666;
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .enroll-wrap {
        padding: 16px;
    }

    .enroll-card {
        padding: 28px 20px;
    }

    .code-input {
        font-size: 20px;
        padding: 14px 16px;
    }

    .sections-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.querySelector('.code-input').addEventListener('input', function(e) {
    let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (value.length > 3) {
        value = value.slice(0, 3) + '-' + value.slice(3, 7);
    }
    e.target.value = value;
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
