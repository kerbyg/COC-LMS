<?php
/**
 * CIT-LMS - Student Enrollment Page
 * Allows students to self-enroll using enrollment codes
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
        // Find the section by enrollment code
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
            $errorMessage = "Invalid enrollment code. Please check the code and try again.";
        } else {
            // Check if section is full
            if ($section['current_enrollment'] >= $section['max_students']) {
                $errorMessage = "This section is full. Please contact your instructor.";
            } else {
                // Check if already enrolled
                $existingEnrollment = db()->fetchOne(
                    "SELECT student_subject_id FROM student_subject
                     WHERE user_student_id = ? AND section_id = ?",
                    [$userId, $section['section_id']]
                );

                if ($existingEnrollment) {
                    $errorMessage = "You are already enrolled in this section.";
                } else {
                    // Enroll the student
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
        error_log("Enrollment error: " . $e->getMessage());
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
    <div class="content-wrapper">
        <!-- Page Header -->
        <div class="page-header">
            <h2 class="page-title">📚 Enroll in Section</h2>
            <p class="page-subtitle">Enter your enrollment code to join a class section</p>
        </div>

        <?php if ($successMessage): ?>
        <div class="alert alert-success">
            ✓ <?= htmlspecialchars($successMessage) ?>
        </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
        <div class="alert alert-danger">
            ✗ <?= htmlspecialchars($errorMessage) ?>
        </div>
        <?php endif; ?>

        <!-- Enrollment Form Card -->
        <div class="enrollment-card">
            <div class="enrollment-icon">🎓</div>
            <h3 class="enrollment-title">Enter Enrollment Code</h3>
            <p class="enrollment-description">
                Get the enrollment code from your instructor or class announcement
            </p>

            <form method="POST" class="enrollment-form">
                <div class="code-input-wrapper">
                    <input
                        type="text"
                        name="enrollment_code"
                        class="code-input"
                        placeholder="ABC-1234"
                        pattern="[A-Za-z]{3}-[0-9]{4}"
                        maxlength="8"
                        required
                        autocomplete="off"
                    >
                    <small class="input-hint">Format: XXX-9999 (e.g., ABC-1234)</small>
                </div>

                <button type="submit" class="btn btn-enroll">
                    ➜ Enroll Now
                </button>
            </form>
        </div>

        <!-- Current Enrollments -->
        <?php if (!empty($enrolledSections)): ?>
        <div class="enrolled-sections">
            <h3 class="section-heading">Your Enrolled Sections</h3>
            <div class="sections-grid">
                <?php foreach ($enrolledSections as $enrolled): ?>
                <div class="section-card">
                    <div class="section-header">
                        <div class="subject-code-badge">
                            <?= htmlspecialchars($enrolled['subject_code']) ?>
                        </div>
                        <div class="section-badge">
                            Section <?= htmlspecialchars($enrolled['section_name']) ?>
                        </div>
                    </div>

                    <h4 class="subject-title">
                        <?= htmlspecialchars($enrolled['subject_name']) ?>
                    </h4>

                    <div class="section-details">
                        <div class="detail-item">
                            <span class="detail-icon">👨‍🏫</span>
                            <span class="detail-text">
                                <?= htmlspecialchars($enrolled['instructor_name'] ?: 'No instructor assigned') ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-icon">📅</span>
                            <span class="detail-text">
                                <?= htmlspecialchars($enrolled['schedule'] ?: 'TBA') ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-icon">🏫</span>
                            <span class="detail-text">
                                <?= htmlspecialchars($enrolled['room'] ?: 'TBA') ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-icon">🔑</span>
                            <span class="detail-text">
                                Code: <strong><?= htmlspecialchars($enrolled['enrollment_code']) ?></strong>
                            </span>
                        </div>
                    </div>

                    <div class="enrolled-date">
                        Enrolled: <?= date('M j, Y', strtotime($enrolled['enrollment_date'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📖</div>
            <h3>No Enrollments Yet</h3>
            <p>You haven't enrolled in any sections. Enter an enrollment code above to get started!</p>
        </div>
        <?php endif; ?>
    </div>
</main>

<style>
.content-wrapper{max-width:1200px;margin:0 auto;padding:32px}

.page-header{text-align:center;margin-bottom:48px}
.page-title{font-size:36px;font-weight:800;background:linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:8px}
.page-subtitle{font-size:16px;color:#6b7280}

.enrollment-card{background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 100%);border:2px solid #3b82f6;border-radius:20px;padding:48px;text-align:center;margin-bottom:48px;box-shadow:0 4px 12px rgba(59,130,246,0.15)}

.enrollment-icon{font-size:64px;margin-bottom:24px}
.enrollment-title{font-size:28px;font-weight:700;color:#1e3a8a;margin-bottom:12px}
.enrollment-description{font-size:16px;color:#6b7280;margin-bottom:32px}

.enrollment-form{max-width:500px;margin:0 auto}

.code-input-wrapper{margin-bottom:24px}
.code-input{width:100%;padding:20px 24px;font-size:28px;font-weight:700;text-align:center;border:3px solid #3b82f6;border-radius:12px;font-family:monospace;letter-spacing:4px;text-transform:uppercase;transition:all 0.2s}
.code-input:focus{outline:none;border-color:#1e3a8a;box-shadow:0 0 0 4px rgba(59,130,246,0.2)}
.code-input::placeholder{color:#cbd5e1;letter-spacing:2px}

.input-hint{display:block;margin-top:8px;color:#6b7280;font-size:14px}

.btn-enroll{width:100%;padding:18px 32px;background:linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%);color:white;border:none;border-radius:12px;font-size:18px;font-weight:700;cursor:pointer;transition:all 0.3s;box-shadow:0 4px 12px rgba(59,130,246,0.3)}
.btn-enroll:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(59,130,246,0.4)}

.enrolled-sections{margin-top:48px}
.section-heading{font-size:24px;font-weight:700;color:#1e3a8a;margin-bottom:24px}

.sections-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:24px}

.section-card{background:white;border:2px solid #e5e7eb;border-radius:16px;padding:24px;transition:all 0.3s}
.section-card:hover{border-color:#3b82f6;box-shadow:0 8px 16px rgba(59,130,246,0.15);transform:translateY(-4px)}

.section-header{display:flex;gap:12px;margin-bottom:16px}

.subject-code-badge{background:linear-gradient(135deg,#fef3c7 0%,#fde68a 100%);color:#92400e;padding:8px 16px;border-radius:8px;font-weight:700;font-size:14px;border:2px solid #fbbf24}

.section-badge{background:linear-gradient(135deg,#dbeafe 0%,#bfdbfe 100%);color:#1e3a8a;padding:8px 16px;border-radius:8px;font-weight:700;font-size:14px;border:2px solid #3b82f6}

.subject-title{font-size:20px;font-weight:700;color:#1f2937;margin-bottom:20px}

.section-details{display:flex;flex-direction:column;gap:12px;margin-bottom:16px}

.detail-item{display:flex;align-items:center;gap:12px;font-size:14px;color:#4b5563}
.detail-icon{font-size:18px}
.detail-text{flex:1}

.enrolled-date{padding-top:16px;border-top:2px solid #e5e7eb;font-size:13px;color:#6b7280;text-align:center}

.empty-state{text-align:center;padding:64px 32px;background:linear-gradient(135deg,#f9fafb 0%,#f3f4f6 100%);border-radius:16px;border:2px dashed #d1d5db}
.empty-icon{font-size:64px;margin-bottom:16px}
.empty-state h3{font-size:20px;font-weight:700;color:#1f2937;margin-bottom:8px}
.empty-state p{color:#6b7280;font-size:16px}

.alert{padding:18px 24px;border-radius:12px;margin-bottom:24px;font-weight:600;box-shadow:0 1px 3px rgba(0,0,0,0.08)}
.alert-success{background:linear-gradient(135deg,#d1fae5 0%,#a7f3d0 100%);color:#065f46;border-left:5px solid #10b981}
.alert-danger{background:linear-gradient(135deg,#fee2e2 0%,#fecaca 100%);color:#991b1b;border-left:5px solid #ef4444}

@media(max-width:768px){
    .content-wrapper{padding:16px}
    .page-title{font-size:28px}
    .enrollment-card{padding:32px 24px}
    .code-input{font-size:22px;padding:16px}
    .sections-grid{grid-template-columns:1fr}
}
</style>

<script>
// Auto-format enrollment code as user types
document.querySelector('.code-input').addEventListener('input', function(e) {
    let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');

    if (value.length > 3) {
        value = value.slice(0, 3) + '-' + value.slice(3, 7);
    }

    e.target.value = value;
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
