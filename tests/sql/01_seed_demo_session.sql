-- Demo session for manual SQL testing + UI smoke test.
-- Prerequisite: run 00_reset_pr_data.sql first.
--
-- 1) Set reviewer WordPress user IDs (must exist in wp_users):
SELECT ID, user_login, display_name FROM wp_users ORDER BY ID LIMIT 10;

SET @reviewer_1 = 1;  -- change to a real wp_users.ID
SET @reviewer_2 = 2;  -- change to a second wp_users.ID (can equal @reviewer_1 for quick tests)

SET @now = NOW();

-- ---------- Students ----------
INSERT INTO wp_pr_students (id, reg_no, name, program, batch, created_at, updated_at) VALUES
(1, 'REG001', 'Ada Lovelace', 'B.Tech CSE', '2026', @now, @now),
(2, 'REG002', 'Grace Hopper', 'B.Tech CSE', '2026', @now, @now);

-- ---------- Session ----------
INSERT INTO wp_pr_sessions (id, title, status, created_at, updated_at) VALUES
(1, 'Demo B.Tech Project Review 2026', 'active', @now, @now);

-- ---------- Panel ----------
INSERT INTO wp_pr_panels (id, session_id, name) VALUES
(1, 1, 'Panel A');

INSERT INTO wp_pr_session_students (id, session_id, student_id, panel_id, guide_emp_id, guide_name) VALUES
(1, 1, 1, 1, 'EMP001', 'Dr. Smith'),
(2, 1, 2, 1, 'EMP002', 'Dr. Jones');

INSERT INTO wp_pr_panel_reviewers (id, panel_id, name, email, user_id, weight) VALUES
(1, 1, 'Reviewer Alpha', 'alpha@example.com', @reviewer_1, 1.0000),
(2, 1, 'Reviewer Beta', 'beta@example.com', @reviewer_2, 1.0000);

INSERT INTO wp_pr_session_reviewers (id, session_id, user_id, provisioned_for_session, disabled_at) VALUES
(1, 1, @reviewer_1, 1, NULL),
(2, 1, @reviewer_2, 1, NULL);

-- ---------- Reviews + rubrics (confirmed so scores/progress work) ----------
INSERT INTO wp_pr_reviews (id, session_id, label, sort_order, status) VALUES
(1, 1, 'Review 1', 0, 'confirmed'),
(2, 1, 'Review 2', 1, 'confirmed');

INSERT INTO wp_pr_rubric_criteria (id, review_id, label, max_marks, weight, sort_order) VALUES
(1, 1, 'Technical depth', 10.0000, 1.0000, 0),
(2, 1, 'Presentation', 10.0000, 1.0000, 1),
(3, 2, 'Documentation', 10.0000, 1.0000, 0),
(4, 2, 'Viva', 10.0000, 1.0000, 1);

INSERT INTO wp_pr_review_weights (id, session_id, review_id, weight) VALUES
(1, 1, 1, 1.0000),
(2, 1, 2, 1.0000);

-- Per-review assignments (required for reviewer /reviews/mark/ UI)
INSERT INTO wp_pr_review_student_panels (id, review_id, student_id, panel_id) VALUES
(1, 1, 1, 1),
(2, 1, 2, 1),
(3, 2, 1, 1),
(4, 2, 2, 1);

INSERT INTO wp_pr_review_panel_reviewers (id, review_id, panel_id, user_id, weight) VALUES
(1, 1, 1, @reviewer_1, 1.0000),
(2, 1, 1, @reviewer_2, 1.0000),
(3, 2, 1, @reviewer_1, 1.0000),
(4, 2, 1, @reviewer_2, 1.0000);

-- ---------- Marks (canonical data; view reads from here) ----------
-- Review 1 — Ada — both reviewers — all criteria submitted
INSERT INTO wp_pr_marks (id, session_id, review_id, student_id, reviewer_user_id, criterion_id, score, flagged, status) VALUES
(1,  1, 1, 1, @reviewer_1, 1, 8.0000, 0, 'submitted'),
(2,  1, 1, 1, @reviewer_1, 2, 7.0000, 0, 'submitted'),
(3,  1, 1, 1, @reviewer_2, 1, 7.5000, 0, 'submitted'),
(4,  1, 1, 1, @reviewer_2, 2, 8.5000, 0, 'submitted'),
-- Review 1 — Grace
(5,  1, 1, 2, @reviewer_1, 1, 9.0000, 0, 'submitted'),
(6,  1, 1, 2, @reviewer_1, 2, 8.0000, 0, 'submitted'),
(7,  1, 1, 2, @reviewer_2, 1, 8.0000, 0, 'submitted'),
(8,  1, 1, 2, @reviewer_2, 2, 9.0000, 0, 'submitted'),
-- Review 2 — Ada — partial (one reviewer, one criterion draft)
(9,  1, 2, 1, @reviewer_1, 3, 6.0000, 0, 'submitted'),
(10, 1, 2, 1, @reviewer_1, 4, 7.0000, 0, 'draft'),
-- Review 2 — Grace — full from reviewer 2
(11, 1, 2, 2, @reviewer_2, 3, 8.0000, 0, 'submitted'),
(12, 1, 2, 2, @reviewer_2, 4, 8.5000, 0, 'submitted');

-- ---------- Verify flat view (export grain) ----------
SELECT project_id, review_id, reg_no, reviewer_id, rubric_id, score, status
FROM wp_pr_rubric_scores
WHERE project_id = 1
ORDER BY review_id, reg_no, reviewer_id, rubric_id;

-- ---------- Parity check ----------
SELECT
    (SELECT COUNT(*) FROM wp_pr_rubric_scores WHERE project_id = 1) AS view_rows,
    (SELECT COUNT(*)
     FROM wp_pr_marks m
     INNER JOIN wp_pr_students s ON s.id = m.student_id
     WHERE m.session_id = 1) AS marks_rows;

-- =============================================================================
-- UI smoke test (log in as coordinator with pr_manage_sessions / pr_view_reports)
-- =============================================================================
-- Dashboard:     /reviews/
-- Progress:      /reviews/#/session/1/progress
--                → panel/reviewer % + pick Ada/Grace for score breakdown
-- Reports:       /reviews/#/session/1/reports
--                → download "Marks detail" CSV/XLSX (criterion rows)
-- Reviewer app:  /reviews/mark/  (log in as @reviewer_1 or @reviewer_2)
-- =============================================================================
