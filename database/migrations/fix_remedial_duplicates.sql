-- Clean up existing duplicates: keep only the latest per student+quiz with pending/in_progress status
DELETE ra1 FROM remedial_assignment ra1
INNER JOIN remedial_assignment ra2
  ON ra1.user_student_id = ra2.user_student_id
 AND ra1.quiz_id = ra2.quiz_id
 AND ra1.status IN ('pending','in_progress')
 AND ra2.status IN ('pending','in_progress')
 AND ra1.remedial_id < ra2.remedial_id;

-- Confirm remaining rows
SELECT remedial_id, user_student_id, quiz_id, status FROM remedial_assignment ORDER BY user_student_id, quiz_id;
