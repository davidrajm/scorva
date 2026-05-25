-- Manual validation for flat rubric scores export (Story 7.4).
-- Replace {prefix} with your WordPress table prefix (e.g. wp_).
-- Run after plugin activation / `Install::maybe_upgrade()`.

-- 1) Confirm the view exists
SHOW FULL TABLES LIKE '{prefix}pr_rubric_scores';

-- 2) List flat rows for a project (session)
SELECT project_id, review_id, reg_no, reviewer_id, rubric_id, score
FROM {prefix}pr_rubric_scores
WHERE project_id = 1
ORDER BY review_id, reg_no, reviewer_id, rubric_id;

-- 3) Row count must match underlying marks (one row per criterion mark)
SELECT COUNT(*) AS view_rows
FROM {prefix}pr_rubric_scores
WHERE project_id = 1;

SELECT COUNT(*) AS marks_rows
FROM {prefix}pr_marks m
INNER JOIN {prefix}pr_students s ON s.id = m.student_id
WHERE m.session_id = 1;

-- 4) Spot-check a single grain (project × review × student × reviewer × rubric)
SELECT rs.*
FROM {prefix}pr_rubric_scores rs
INNER JOIN {prefix}pr_students s ON s.reg_no = rs.reg_no
WHERE rs.project_id = 1
  AND rs.review_id = 1
  AND rs.reg_no = 'REG001'
  AND rs.reviewer_id = 10
  AND rs.rubric_id = 5;

-- 5) Compare view score to canonical pr_marks row
SELECT
    rs.project_id,
    rs.score AS view_score,
    m.score AS marks_score
FROM {prefix}pr_rubric_scores rs
INNER JOIN {prefix}pr_students s ON s.reg_no = rs.reg_no
INNER JOIN {prefix}pr_marks m
    ON m.session_id = rs.project_id
   AND m.review_id = rs.review_id
   AND m.student_id = s.id
   AND m.reviewer_user_id = rs.reviewer_id
   AND m.criterion_id = rs.rubric_id
WHERE rs.project_id = 1
LIMIT 20;
