-- Reset all Project Reviews plugin tables (NOT WordPress core tables).
-- Replace wp_ with your $table_prefix if different.
--
-- Canonical table list: Install::get_pr_table_names() in includes/Install.php
-- Does NOT drop the pr_rubric_scores VIEW — it stays empty until you insert pr_marks again.
-- Run 01_seed_demo_session.sql after this.

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE wp_pr_mark_audit;
TRUNCATE TABLE wp_pr_marks;
TRUNCATE TABLE wp_pr_unfreeze_requests;
TRUNCATE TABLE wp_pr_panel_unfreeze_requests;
TRUNCATE TABLE wp_pr_review_panel_freezes;
TRUNCATE TABLE wp_pr_review_student_attendance_by_reviewer;
TRUNCATE TABLE wp_pr_review_student_panels;
TRUNCATE TABLE wp_pr_review_panel_reviewers;
TRUNCATE TABLE wp_pr_review_reviewer_overrides;
TRUNCATE TABLE wp_pr_reviewer_weights;
TRUNCATE TABLE wp_pr_review_weights;
TRUNCATE TABLE wp_pr_rubric_criteria;
TRUNCATE TABLE wp_pr_reviews;
TRUNCATE TABLE wp_pr_session_reviewers;
TRUNCATE TABLE wp_pr_panel_reviewers;
TRUNCATE TABLE wp_pr_session_students;
TRUNCATE TABLE wp_pr_panels;
TRUNCATE TABLE wp_pr_sessions;
TRUNCATE TABLE wp_pr_student_meta;
TRUNCATE TABLE wp_pr_field_definitions;
TRUNCATE TABLE wp_pr_students;

SET FOREIGN_KEY_CHECKS = 1;

-- Confirm empty
SELECT 'wp_pr_students' AS tbl, COUNT(*) AS n FROM wp_pr_students
UNION ALL SELECT 'wp_pr_sessions', COUNT(*) FROM wp_pr_sessions
UNION ALL SELECT 'wp_pr_marks', COUNT(*) FROM wp_pr_marks
UNION ALL SELECT 'wp_pr_rubric_scores (view)', COUNT(*) FROM wp_pr_rubric_scores;
